<?php
include '../../SQL/config.php';
if (session_status() === PHP_SESSION_NONE) session_start();

/*
|----------------------------------------------------------------------
| FETCH PATIENTS WITH UNPAID / PARTIALLY PAID BILLINGS
|----------------------------------------------------------------------
| - billing_items is the source of services
| - billing_id is the source of truth
| - patient_receipt defines payment status
*/

$sql = "
SELECT
    p.patient_id,
    CONCAT(p.fname,' ',IFNULL(p.mname,''),' ',p.lname) AS full_name,
    p.address,
    p.phone_number,

    bi.billing_id,

    -- Billing totals
    SUM(bi.total_price) AS total_charges,

    -- Latest receipt (if any)
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

HAVING 
    pr.status IS NULL OR pr.status != 'Paid'

ORDER BY p.lname ASC, p.fname ASC
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
<link rel="stylesheet" href="assets/CSS/patient_billing.css">
<link rel="stylesheet" href="assets/css/billing_sidebar.css">

<style>
.dashboard-wrapper {
    display: flex;
    min-height: 100vh;
}
.main-content-wrapper {
    flex-grow: 1;
    padding: 20px;
}
.main-sidebar {
    width: 260px;
    flex-shrink: 0;
}
@media (max-width: 992px) {
    .main-sidebar {
        width: 100%;
    }
}
</style>

<script>
// Auto refresh every 10 seconds to show updated payment status and references
setInterval(() => {
    location.reload();
}, 10000);
</script>

</head>

<body>
<div class="dashboard-wrapper">

<div class="main-content-wrapper">
<div class="container-fluid bg-white p-4 rounded shadow">

<div class="d-flex justify-content-between align-items-center mb-3">
    <h1>Patient Billing</h1>
    <a href="patient_billing.php" class="btn btn-outline-primary btn-sm">
        <i class="bi bi-arrow-clockwise"></i> Refresh
    </a>
</div>

<table class="table table-bordered table-striped align-middle">
<thead class="table-dark">
<tr>
    <th>Patient Name</th>
    <th>Insurance</th>
    <th>Payment Status</th>
    <th>Total Charges</th>
    <th class="text-end">Actions</th>
</tr>
</thead>

<tbody>

<?php if ($result && $result->num_rows > 0): ?>
<?php while ($row = $result->fetch_assoc()): ?>

<?php
$billing_id       = (int)$row['billing_id'];
$receipt_id       = $row['receipt_id'];
$totalCharges     = (float)$row['total_charges'];
$status           = $row['payment_status'] ?? 'Pending';
$paymongoRef      = $row['paymongo_reference'] ?? '';
$insuranceApplied = ((float)$row['insurance_covered'] > 0);
$insuranceLabel   = $insuranceApplied ? htmlspecialchars($row['payment_method']) : 'N/A';

// ✅ Ensure a receipt exists for "View Bill"
if (!$receipt_id) {
    $stmt = $conn->prepare("
        INSERT INTO patient_receipt (patient_id, billing_id, status, insurance_covered, payment_method)
        VALUES (?, ?, 'Pending', ?, ?)
    ");
    $insuranceCovered = $insuranceApplied ? 1 : 0;
    $stmt->bind_param("iiis", $row['patient_id'], $billing_id, $insuranceCovered, $insuranceLabel);
    $stmt->execute();
    $receipt_id = $conn->insert_id;
}
?>

<tr>
<td><?= htmlspecialchars($row['full_name']); ?></td>

<td>
<?php if ($insuranceApplied): ?>
    <span class="badge bg-success"><?= $insuranceLabel ?></span>
<?php else: ?>
    <span class="badge bg-secondary">N/A</span>
<?php endif; ?>
</td>

<td>
<?php if ($status === 'Paid'): ?>
    <span class="badge bg-success">Paid</span>
<?php else: ?>
    <span class="badge bg-warning text-dark">Pending</span>
<?php endif; ?>
</td>

<td>₱ <?= number_format($totalCharges, 2); ?>
<?php if ($paymongoRef): ?>
    <br><small class="text-muted">Ref: <?= htmlspecialchars($paymongoRef) ?></small>
<?php endif; ?>
</td>

<td class="text-end">
<div class="d-flex justify-content-end gap-2 flex-wrap">

<!-- ✅ Always link to print_receipt.php -->
<a href="print_receipt.php?receipt_id=<?= $receipt_id ?>" 
   class="btn btn-secondary btn-sm" target="_blank">
   <i class="bi bi-receipt"></i> View Bill
</a>

<a href="billing_summary.php?patient_id=<?= $row['patient_id']; ?>" 
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
<h5 class="modal-title">Enter Insurance Number</h5>
<button type="button" class="btn-close" data-bs-dismiss="modal"></button>
</div>

<div class="modal-body">
<input type="hidden" name="patient_id" value="<?= $row['patient_id'] ?>">
<input type="hidden" name="billing_id" value="<?= $billing_id ?>">

<div class="mb-3">
<label class="form-label">Insurance Number</label>
<input type="text" name="insurance_number" class="form-control" required>
</div>
</div>

<div class="modal-footer">
<button type="submit" class="btn btn-primary">Apply Insurance</button>
<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
</div>

</form>
</div>
</div>
<?php endif; ?>

</td>
</tr>

<?php endwhile; ?>
<?php else: ?>
<tr>
<td colspan="5" class="text-center">No patients ready for billing.</td>
</tr>
<?php endif; ?>

</tbody>
</table>

</div>
</div>

<div class="main-sidebar">
<?php include 'billing_sidebar.php'; ?>
</div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
