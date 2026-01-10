<?php
include '../../SQL/config.php';

// ==============================
// Fetch patients who already have billing records
// ==============================

$sql = "
SELECT 
    p.patient_id,
    CONCAT(p.fname, ' ', IFNULL(p.mname,''), ' ', p.lname) AS full_name,
    p.address,
    p.dob,
    p.phone_number,

    -- Latest payment status from patient_receipt
    (
        SELECT pr.status 
        FROM patient_receipt pr 
        WHERE pr.patient_id = p.patient_id
        ORDER BY pr.created_at DESC
        LIMIT 1
    ) AS payment_status,

    -- Latest receipt_id
    (
        SELECT pr.receipt_id
        FROM patient_receipt pr 
        WHERE pr.patient_id = p.patient_id
        ORDER BY pr.created_at DESC
        LIMIT 1
    ) AS latest_receipt_id,

    -- Total charges from billing_items
    (
        SELECT IFNULL(SUM(bi.total_price),0)
        FROM billing_items bi
        WHERE bi.patient_id = p.patient_id
    ) AS total_price

FROM patientinfo p

WHERE EXISTS (
    SELECT 1 
    FROM billing_records br 
    WHERE br.patient_id = p.patient_id
)

ORDER BY p.lname ASC, p.fname ASC
";

$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Patient Billing</title>

<link rel="stylesheet" href="assets/CSS/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
<link rel="stylesheet" href="assets/CSS/patient_billing.css">
<link rel="stylesheet" href="assets/css/billing_sidebar.css">
</head>

<body>
<div class="dashboard-wrapper">
<div class="main-content-wrapper">
<div class="container-fluid bg-white p-4 rounded shadow">

<h1 class="mb-4">Patient Billing</h1>

<table class="table table-bordered table-striped">
<thead class="table-dark">
<tr>
    <th>Patient Name</th>
    <th>Insurance Status / Plan</th>
    <th>Payment Status</th>
    <th>Total Charges</th>
    <th class="text-end">Actions</th>
</tr>
</thead>

<tbody>
<?php if ($result && $result->num_rows > 0): ?>
<?php while ($row = $result->fetch_assoc()): ?>

<?php
$insuranceStatus = 'N/A';
$insuranceDiscount = 0;
$insurance = null;

$paymentStatus = $row['payment_status'] ?? 'Pending';
$receipt_id = $row['latest_receipt_id'] ?? 0;
$totalPrice = (float)$row['total_price'];

$disableBill = ($paymentStatus === 'Paid');
$showInsuranceButton = true;
$rowClass = 'pending-insurance';

// --------------------------
// If insurance was already applied manually, show Applied
// --------------------------
if (isset($row['insurance_applied']) && $row['insurance_applied'] == 1) {
    $insuranceStatus = 'Applied';
    $showInsuranceButton = false;
    $rowClass = '';
}

// --------------------------
// Fetch insurance rule if user enters insurance number
// --------------------------
if ($showInsuranceButton) {
    // When the modal is used, user inputs insurance number
    // The logic to check and apply discount will be handled in apply_insurance.php
    // Here we just display the button to input insurance number
}
?>

<tr class="<?= $rowClass ?>">
<td><?= htmlspecialchars($row['full_name']); ?></td>

<td>
<?php if ($insuranceStatus === 'Applied' && $insurance): ?>
    <span class="badge bg-success">Applied</span><br>
    <small class="text-muted">
        <?= htmlspecialchars($insurance['insurance_company']); ?> – 
        <?= htmlspecialchars($insurance['promo_name']); ?>
        (<?= $insurance['discount_type']; ?> <?= $insurance['discount_value']; ?>)
    </small>
<?php else: ?>
    <span class="badge bg-secondary">N/A</span>
<?php endif; ?>
</td>

<td>
<?php if ($paymentStatus === 'Paid'): ?>
    <span class="badge bg-success">Paid</span>
<?php else: ?>
    <span class="badge bg-warning text-dark">Pending</span>
<?php endif; ?>
</td>

<td>₱ <?= number_format($totalPrice, 2); ?></td>

<td class="text-end">
<div class="d-flex justify-content-end gap-2 flex-wrap">

<?php if (!$receipt_id): ?>
<a href="total_bill.php?patient_id=<?= $row['patient_id']; ?>" class="btn btn-secondary btn-sm" target="_blank">
View Total Bill
</a>
<?php else: ?>
<a href="print_receipt.php?receipt_id=<?= $receipt_id ?>" class="btn btn-secondary btn-sm" target="_blank">
View Total Bill
</a>
<?php endif; ?>

<?php if ($disableBill): ?>
<button class="btn btn-success btn-sm" disabled>Already Paid</button>
<?php else: ?>
<a href="billing_summary.php?patient_id=<?= $row['patient_id']; ?>" class="btn btn-success btn-sm">
Process Payment
</a>
<?php endif; ?>

<?php if ($showInsuranceButton): ?>
<button class="btn btn-info btn-sm" data-bs-toggle="modal" data-bs-target="#insuranceModal<?= $row['patient_id'] ?>">
Enter Insurance
</button>
<?php endif; ?>

</div>

<?php if ($showInsuranceButton): ?>
<div class="modal fade" id="insuranceModal<?= $row['patient_id'] ?>" tabindex="-1">
<div class="modal-dialog">
<form method="POST" action="apply_insurance.php" class="modal-content">

<div class="modal-header">
<h5 class="modal-title">Enter Insurance Number</h5>
<button type="button" class="btn-close" data-bs-dismiss="modal"></button>
</div>

<div class="modal-body">
<input type="hidden" name="full_name" value="<?= htmlspecialchars($row['full_name']) ?>">
<input type="hidden" name="patient_id" value="<?= $row['patient_id'] ?>">

<div class="mb-3">
<label>Insurance Number</label>
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
