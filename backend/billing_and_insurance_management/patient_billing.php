<?php
include '../../SQL/config.php';
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/vendor/autoload.php';
use GuzzleHttp\Client;

/* =====================================================
   PAYMONGO CONFIG
===================================================== */
define('PAYMONGO_SECRET_KEY', 'sk_test_akT1ZW6za7m6FC9S9VqYNiVV');


/* =====================================================
   SYNC PAID PAYMENTS FROM paymongo_payments TABLE
===================================================== */
$paidPmts = $conn->query("
    SELECT id, payment_id, payment_intent_id, amount, status
    FROM paymongo_payments
    WHERE status = 'paid'
    ORDER BY paid_at DESC
");

while ($pm = $paidPmts->fetch_assoc()) {
    // Find matching receipt by paymongo_reference or transaction_id
    $rStmt = $conn->prepare("
        SELECT receipt_id, patient_id, billing_id, status
        FROM patient_receipt
        WHERE paymongo_reference = ? OR transaction_id = ?
        LIMIT 1
    ");
    $rStmt->bind_param("ss", $pm['payment_id'], $pm['payment_intent_id']);
    $rStmt->execute();
    $receipt = $rStmt->get_result()->fetch_assoc();

    if ($receipt && $receipt['status'] !== 'Paid') {
        $billing_id = (int)$receipt['billing_id'];

        // Update billing_records
        $bStmt = $conn->prepare("
            UPDATE billing_records
            SET status = 'Paid'
            WHERE billing_id = ?
        ");
        $bStmt->bind_param("i", $billing_id);
        $bStmt->execute();

        // Update patient_receipt
        $uStmt = $conn->prepare("
            UPDATE patient_receipt
            SET status = 'Paid', paymongo_reference = ?
            WHERE receipt_id = ?
        ");
        $uStmt->bind_param("si", $pm['payment_id'], $receipt['receipt_id']);
        $uStmt->execute();
    }
}

/* =====================================================
   LEGACY: Check pending billings with paymongo_payment_id
===================================================== */
$pending = $conn->query("
    SELECT billing_id, paymongo_payment_id
    FROM billing_records
    WHERE status = 'Pending'
      AND paymongo_payment_id IS NOT NULL
");

while ($row = $pending->fetch_assoc()) {
    try {
        $response = $client->get(PAYMONGO_PAYMENT_API . '/' . $row['paymongo_payment_id']);
        $payment  = json_decode($response->getBody(), true);

        if (
            isset($payment['data']['attributes']['status']) &&
            $payment['data']['attributes']['status'] === 'paid'
        ) {
            $billing_id = (int)$row['billing_id'];

            // billing_records
            $stmt = $conn->prepare("
                UPDATE billing_records
                SET status='Paid'
                WHERE billing_id=?
            ");
            $stmt->bind_param("i", $billing_id);
            $stmt->execute();

            // patient_receipt
            $stmt = $conn->prepare("
                UPDATE patient_receipt
                SET status='Paid'
                WHERE billing_id=?
            ");
            $stmt->bind_param("i", $billing_id);
            $stmt->execute();
        }
    } catch (Exception $e) {
        // Silent fail (do NOT break UI)
    }
}

/* =====================================================
   FETCH PENDING BILLINGS
===================================================== */
$sql = "
SELECT
    p.patient_id,
    CONCAT(p.fname,' ',IFNULL(p.mname,''),' ',p.lname) AS full_name,
    bi.billing_id,
    SUM(bi.total_price) AS total_charges,
    MAX(pr.receipt_id) AS receipt_id,
    MAX(pr.status) AS payment_status,
    MAX(pr.insurance_covered) AS insurance_covered,
    MAX(pr.payment_method) AS payment_method,
    MAX(pr.paymongo_reference) AS paymongo_reference

FROM patientinfo p
INNER JOIN billing_items bi
    ON bi.patient_id = p.patient_id
    AND bi.finalized = 1

LEFT JOIN patient_receipt pr
    ON pr.billing_id = bi.billing_id

GROUP BY p.patient_id, bi.billing_id
HAVING MAX(pr.status) IS NULL OR MAX(pr.status) != 'Paid'
ORDER BY p.lname, p.fname
";

$result = $conn->query($sql);
if (!$result) {
    error_log("SQL Error: " . $conn->error);
    $result = null;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Patient Billing</title>
<meta name="viewport" content="width=device-width, initial-scale=1">

<link rel="stylesheet" href="assets/CSS/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
<link rel="stylesheet" href="assets/css/patient_billing.css">

<script>
async function refreshAndSync(btn) {
    btn.disabled = true;
    const original = btn.innerHTML;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Checking...';

    try {
        // call the updated endpoint
        const res = await fetch('fetch_paid_payments.php', { method: 'GET', headers: { 'Accept': 'application/json' } });
        const json = await res.json().catch(() => null);
        if (json) console.log('Sync result', json);
    } catch (err) {
        console.error('Sync request failed', err);
    } finally {
        // reload page to reflect updated statuses
        window.location.reload();
    }
}
</script>
</head>

<body>
<div class="dashboard-wrapper">



<div class="main-content-wrapper">
<div class="bg-white p-4 shadow rounded">

<div class="d-flex justify-content-between align-items-center mb-3">
    <h3>Patient Billing</h3>
    <div class="gap-2">
        <!-- Refresh now triggers server-side fetch_paymongo_payments.php then reloads -->
        <button class="btn btn-outline-primary btn-sm" onclick="refreshAndSync(this)">
            <i class="bi bi-arrow-clockwise"></i> Refresh
        </button>
    </div>
</div>

<div class="table-responsive">
<table class="table table-bordered align-middle">
<thead class="table-dark">
<tr>
    <th>Patient</th>
    <th>Insurance</th>
    <th>Status</th>
    <th>Total</th>
    <th class="text-end">Actions</th>
</tr>
</thead>
<tbody>

<?php if ($result && $result->num_rows > 0): ?>
<?php while ($row = $result->fetch_assoc()): ?>

<?php
// Safe variable extraction with null checks
$patient_id       = (int)($row['patient_id'] ?? 0);
$full_name        = $row['full_name'] ?? 'Unknown Patient';
$billing_id       = (int)($row['billing_id'] ?? 0);
$total            = (float)($row['total_charges'] ?? 0);
$receipt_id       = $row['receipt_id'] ?? null;
$status           = $row['payment_status'] ?? 'Pending';
$insurance_covered = (float)($row['insurance_covered'] ?? 0);
$payment_method   = $row['payment_method'] ?? null;
$insuranceApplied = ($insurance_covered > 0);
$insuranceLabel   = $insuranceApplied ? $payment_method : 'N/A';

// Validate required fields
if (!$patient_id || !$billing_id) {
    continue; // Skip row if essential data missing
}

/* Ensure receipt exists */
if (!$receipt_id) {
    $stmt = $conn->prepare("
        INSERT INTO patient_receipt (patient_id, billing_id, status)
        VALUES (?, ?, 'Pending')
    ");
    if ($stmt) {
        $stmt->bind_param("ii", $patient_id, $billing_id);
        if ($stmt->execute()) {
            $receipt_id = $conn->insert_id;
        } else {
            error_log("Failed to insert receipt for billing_id=$billing_id: " . $stmt->error);
            continue;
        }
        $stmt->close();
    }
}
?>

<tr>
<td><?= htmlspecialchars($full_name) ?></td>

<td>
<?= $insuranceApplied
    ? '<span class="badge bg-success">' . htmlspecialchars($insuranceLabel) . '</span>'
    : '<span class="badge bg-secondary">N/A</span>' ?>
</td>

<td>
<span class="badge <?= $status === 'Paid' ? 'bg-success' : 'bg-warning text-dark' ?>">
<?= htmlspecialchars($status) ?>
</span>
</td>

<td>₱ <?= number_format($total, 2) ?></td>

<td class="text-end">
<div class="d-flex gap-2 justify-content-end flex-wrap">

<a href="print_receipt.php?receipt_id=<?= urlencode($receipt_id) ?>"
   target="_blank"
   class="btn btn-secondary btn-sm">
   <i class="bi bi-receipt"></i> View Bill
</a>

<a href="billing_summary.php?patient_id=<?= urlencode($patient_id) ?>"
   class="btn btn-success btn-sm">
   <i class="bi bi-cash-stack"></i> Process Payment
</a>

<?php if (!$insuranceApplied): ?>
<button class="btn btn-info btn-sm"
        data-bs-toggle="modal"
        data-bs-target="#insuranceModal<?= $patient_id ?>">
    Enter Insurance
</button>
<?php endif; ?>

</div>

<?php if (!$insuranceApplied): ?>
<div class="modal fade" id="insuranceModal<?= $patient_id ?>" tabindex="-1">
<div class="modal-dialog">
<form method="POST" action="apply_insurance.php" class="modal-content">

<div class="modal-header">
<h5 class="modal-title">Apply Insurance</h5>
<button type="button" class="btn-close" data-bs-dismiss="modal"></button>
</div>

<div class="modal-body">
<input type="hidden" name="patient_id" value="<?= $patient_id ?>">
<input type="hidden" name="billing_id" value="<?= $billing_id ?>">

<label class="form-label">Insurance Number</label>
<input type="text" name="insurance_number" class="form-control" required>
</div>

<div class="modal-footer">
<button class="btn btn-primary">Apply</button>
<button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
</div>

</form>
</div>
</div>
<?php endif; ?>

</td>
</tr>

<?php endwhile; ?>
<?php else: ?>
<tr><td colspan="5" class="text-center">No pending billings 🎉</td></tr>
<?php endif; ?>

</tbody>
</table>
</div>

<div class="main-sidebar">
<?php include 'billing_sidebar.php'; ?>
</div>

</div>
</div>


<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>