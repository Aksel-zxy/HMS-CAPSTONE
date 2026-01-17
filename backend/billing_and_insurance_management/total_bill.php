<?php
include '../../SQL/config.php';
if (session_status() === PHP_SESSION_NONE) session_start();

/* =======================
   GET PATIENT
======================= */
$patient_id = intval($_GET['patient_id'] ?? 0);
if ($patient_id <= 0) {
    die("Invalid patient ID.");
}

$stmt = $conn->prepare("
    SELECT *, CONCAT(fname,' ',IFNULL(mname,''),' ',lname) AS full_name
    FROM patientinfo
    WHERE patient_id = ?
");
$stmt->bind_param("i", $patient_id);
$stmt->execute();
$patient = $stmt->get_result()->fetch_assoc();

if (!$patient) {
    die("Patient not found.");
}

/* =======================
   GET LATEST BILLING ID
======================= */
$stmt = $conn->prepare("
    SELECT MAX(billing_id) AS billing_id
    FROM billing_items
    WHERE patient_id = ? AND finalized = 1
");
$stmt->bind_param("i", $patient_id);
$stmt->execute();
$billing = $stmt->get_result()->fetch_assoc();
$billing_id = $billing['billing_id'] ?? null;

if (!$billing_id) {
    die("No finalized billing found.");
}

/* =======================
   INSURANCE (same logic)
======================= */
$insurance_covered = 0;
$insurance_plan = null;
$insurance_discount = 0;
$insurance_discount_type = null;

$stmt = $conn->prepare("
    SELECT promo_name, discount_type, discount_value, insurance_number, insurance_company
    FROM patient_insurance
    WHERE full_name = ? AND status = 'Active'
    LIMIT 1
");
$stmt->bind_param("s", $patient['full_name']);
$stmt->execute();
$insurance = $stmt->get_result()->fetch_assoc();

if ($insurance) {
    $insurance_plan = $insurance['promo_name'];
    $insurance_discount = floatval($insurance['discount_value']);
    $insurance_discount_type = $insurance['discount_type'];
}

/* =======================
   BILLING ITEMS (SOURCE)
======================= */
$billing_items = [];
$total_charges = 0;

$stmt = $conn->prepare("
    SELECT bi.*, ds.serviceName, ds.description
    FROM billing_items bi
    LEFT JOIN dl_services ds ON bi.service_id = ds.serviceID
    WHERE bi.patient_id = ? AND bi.billing_id = ?
");
$stmt->bind_param("ii", $patient_id, $billing_id);
$stmt->execute();
$res = $stmt->get_result();

while ($row = $res->fetch_assoc()) {
    $billing_items[] = $row;
    $total_charges += floatval($row['total_price']);
}

/* =======================
   APPLY INSURANCE
======================= */
if ($insurance_discount > 0) {
    if ($insurance_discount_type === 'Percentage') {
        $insurance_covered = $total_charges * ($insurance_discount / 100);
    } else {
        $insurance_covered = min($insurance_discount, $total_charges);
    }
}

$total_discount = 0;
$total_out_of_pocket = max($total_charges - $insurance_covered, 0);

/* =======================
   DOCTOR
======================= */
$doctor = null;
if (!empty($patient['attending_doctor'])) {
    $stmt = $conn->prepare("SELECT * FROM hr_employees WHERE employee_id = ?");
    $stmt->bind_param("i", $patient['attending_doctor']);
    $stmt->execute();
    $doctor = $stmt->get_result()->fetch_assoc();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Total Bill</title>
<link rel="stylesheet" href="assets/CSS/bootstrap.min.css">
<link rel="stylesheet" href="assets/CSS/pdf.css">
<style>
@media print { .print-btn { display:none; } }
.total-box { font-size:16px; font-weight:bold; }
</style>
</head>
<body>

<button class="btn btn-primary print-btn mb-3" onclick="window.print()">Print Bill</button>

<h2>PATIENT TOTAL BILL</h2>

<table width="100%" style="margin-bottom:10px;">
<tr>
<td width="48%" valign="top">
<b>PATIENT INFORMATION</b><br>
Name: <?= htmlspecialchars($patient['full_name']); ?><br>
Contact: <?= htmlspecialchars($patient['phone_number']); ?><br>
Address: <?= htmlspecialchars($patient['address']); ?><br>
<?php if ($insurance): ?>
Insurance: <?= htmlspecialchars($insurance['insurance_company'].' ('.$insurance['promo_name'].')'); ?><br>
Insurance #: <?= htmlspecialchars($insurance['insurance_number']); ?>
<?php endif; ?>
</td>

<td width="48%" valign="top">
<b>DOCTOR INFORMATION</b><br>
<?php if ($doctor): ?>
<?= htmlspecialchars($doctor['first_name'].' '.$doctor['last_name']); ?><br>
<?= htmlspecialchars($doctor['specialization']); ?>
<?php else: ?>
N/A
<?php endif; ?>
</td>
</tr>
</table>

<table class="table table-bordered">
<thead class="table-primary">
<tr>
<th>Service</th>
<th>Description</th>
<th class="text-end">Amount</th>
</tr>
</thead>
<tbody>
<?php if ($billing_items): foreach ($billing_items as $item): ?>
<tr>
<td><?= htmlspecialchars($item['serviceName']); ?></td>
<td><?= htmlspecialchars($item['description']); ?></td>
<td class="text-end">₱<?= number_format($item['total_price'],2); ?></td>
</tr>
<?php endforeach; else: ?>
<tr><td colspan="3" class="text-center">No billing items found.</td></tr>
<?php endif; ?>
</tbody>
</table>

<div class="text-end mt-3">
Subtotal: ₱<?= number_format($total_charges,2); ?><br>
Insurance Covered: ₱<?= number_format($insurance_covered,2); ?><br>
<div class="total-box">TOTAL DUE: ₱<?= number_format($total_out_of_pocket,2); ?></div>
</div>

</body>
</html>
