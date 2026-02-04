<?php
include '../../SQL/config.php';
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/vendor/autoload.php';
use GuzzleHttp\Client;

/* =====================================================
   PAYMONGO CONFIG
===================================================== */
define('PAYMONGO_SECRET_KEY', 'sk_test_akT1ZW6za7m6FC9S9VqYNiVV');
define('PAYMONGO_PAYMENT_API', 'https://api.paymongo.com/v1/payments');

/* =====================================================
   AUTO-SYNC PAYMENTS FROM PAYMONGO (ON REFRESH)
===================================================== */
$client = new Client([
    'headers' => [
        'Accept'        => 'application/json',
        'Authorization' => 'Basic ' . base64_encode(PAYMONGO_SECRET_KEY . ':'),
    ],
    'timeout' => 5
]);

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

    pr.receipt_id,
    pr.status AS payment_status,
    pr.insurance_covered,
    pr.payment_method,
    pr.paymongo_reference

FROM patientinfo p
INNER JOIN billing_items bi
    ON bi.patient_id = p.patient_id
    AND bi.finalized = 1

LEFT JOIN patient_receipt pr
    ON pr.billing_id = bi.billing_id
    AND pr.receipt_id = (
        SELECT MAX(r2.receipt_id)
        FROM patient_receipt r2
        WHERE r2.billing_id = bi.billing_id
    )

GROUP BY p.patient_id, bi.billing_id
HAVING pr.status IS NULL OR pr.status != 'Paid'
ORDER BY p.lname, p.fname
";

$result = $conn->query($sql);
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
setInterval(() => location.reload(), 15000);
</script>
</head>

<body>
<div class="dashboard-wrapper">

<div class="main-content-wrapper">
<div class="bg-white p-4 shadow rounded">

<div class="d-flex justify-content-between align-items-center mb-3">
    <h3>Patient Billing</h3>
    <a href="patient_billing.php" class="btn btn-outline-primary btn-sm">
        <i class="bi bi-arrow-clockwise"></i> Refresh
    </a>
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

<?php if ($result && $result->num_rows): ?>
<?php while ($row = $result->fetch_assoc()): ?>

<?php
$billing_id       = (int)$row['billing_id'];
$receipt_id       = $row['receipt_id'];
$status           = $row['payment_status'] ?? 'Pending';
$total            = (float)$row['total_charges'];
$insuranceApplied = ((float)$row['insurance_covered'] > 0);
$insuranceLabel   = $insuranceApplied ? $row['payment_method'] : 'N/A';

/* Ensure receipt exists */
if (!$receipt_id) {
    $stmt = $conn->prepare("
        INSERT INTO patient_receipt (patient_id, billing_id, status)
        VALUES (?, ?, 'Pending')
    ");
    $stmt->bind_param("ii", $row['patient_id'], $billing_id);
    $stmt->execute();
    $receipt_id = $conn->insert_id;
}
?>

<tr>
<td><?= htmlspecialchars($row['full_name']) ?></td>

<td>
<?= $insuranceApplied
    ? '<span class="badge bg-success">'.$insuranceLabel.'</span>'
    : '<span class="badge bg-secondary">N/A</span>' ?>
</td>

<td>
<span class="badge <?= $status === 'Paid' ? 'bg-success' : 'bg-warning text-dark' ?>">
<?= $status ?>
</span>
</td>

<td>₱ <?= number_format($total,2) ?></td>

<td class="text-end">
<div class="d-flex gap-2 justify-content-end flex-wrap">

<a href="print_receipt.php?receipt_id=<?= $receipt_id ?>"
   target="_blank"
   class="btn btn-secondary btn-sm">
   <i class="bi bi-receipt"></i> View Bill
</a>

<a href="billing_summary.php?patient_id=<?= $row['patient_id'] ?>"
   class="btn btn-success btn-sm">
   <i class="bi bi-cash-stack"></i> Process Payment
</a>

<?php if (!$insuranceApplied): ?>
<button class="btn btn-info btn-sm"
        data-bs-toggle="modal"
        data-bs-target="#insuranceModal<?= $row['patient_id'] ?>">
    Enter Insurance
</button>
<?php endif; ?>

</div>

<?php if (!$insuranceApplied): ?>
<div class="modal fade" id="insuranceModal<?= $row['patient_id'] ?>" tabindex="-1">
<div class="modal-dialog">
<form method="POST" action="apply_insurance.php" class="modal-content">

<div class="modal-header">
<h5 class="modal-title">Apply Insurance</h5>
<button type="button" class="btn-close" data-bs-dismiss="modal"></button>
</div>

<div class="modal-body">
<input type="hidden" name="patient_id" value="<?= $row['patient_id'] ?>">
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

</div>
</div>

<div class="main-sidebar">
<?php include 'billing_sidebar.php'; ?>
</div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
