<?php
session_start();
include '../../SQL/config.php';

/* ===============================
   VALIDATE RECEIPT
================================ */
$receipt_id = isset($_GET['receipt_id']) ? intval($_GET['receipt_id']) : 0;
if ($receipt_id <= 0) {
    die("Invalid receipt ID.");
}

/* ===============================
   FETCH RECEIPT + PATIENT
================================ */
$stmt = $conn->prepare("
    SELECT pr.*, 
           pi.fname, pi.mname, pi.lname,
           pi.phone_number, pi.address, pi.attending_doctor
    FROM patient_receipt pr
    INNER JOIN patientinfo pi ON pr.patient_id = pi.patient_id
    WHERE pr.receipt_id = ?
");
$stmt->bind_param("i", $receipt_id);
$stmt->execute();
$billing = $stmt->get_result()->fetch_assoc();

if (!$billing) {
    die("Receipt not found.");
}

$billing_id = (int)$billing['billing_id'];
$patient_id = (int)$billing['patient_id'];

$full_name = trim(
    $billing['fname'].' '.
    (!empty($billing['mname']) ? $billing['mname'].' ' : '').
    $billing['lname']
);

/* ===============================
   INSURANCE
================================ */
$stmt = $conn->prepare("
    SELECT * FROM patient_insurance
    WHERE full_name = ? AND status='Active'
    LIMIT 1
");
$stmt->bind_param("s", $full_name);
$stmt->execute();
$insurance = $stmt->get_result()->fetch_assoc();

/* ===============================
   DOCTOR
================================ */
$doctor = null;
if (!empty($billing['attending_doctor'])) {
    $stmt = $conn->prepare("SELECT * FROM hr_employees WHERE employee_id=?");
    $stmt->bind_param("i", $billing['attending_doctor']);
    $stmt->execute();
    $doctor = $stmt->get_result()->fetch_assoc();
}

/* ===============================
   BILLING ITEMS (FIXED SOURCE)
================================ */
$billing_items = [];
$total_charges = 0;

$stmt = $conn->prepare("
    SELECT 
        bi.quantity,
        bi.unit_price,
        bi.total_price,
        ds.serviceName,
        ds.description
    FROM billing_items bi
    LEFT JOIN dl_services ds ON bi.service_id = ds.serviceID
    WHERE bi.billing_id = ?
");
$stmt->bind_param("i", $billing_id);
$stmt->execute();
$res = $stmt->get_result();

while ($row = $res->fetch_assoc()) {
    $billing_items[] = $row;
    $total_charges += floatval($row['total_price']);
}

/* ===============================
   TOTALS
================================ */
$total_discount      = floatval($billing['total_discount']);
$insurance_covered   = floatval($billing['insurance_covered']);
$total_out_of_pocket = floatval($billing['total_out_of_pocket']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Patient Invoice</title>

<link rel="stylesheet" href="assets/CSS/bootstrap.min.css">
<link rel="stylesheet" href="assets/CSS/pdf.css">
<link rel="stylesheet" href="assets/css/billing_sidebar.css">

<style>
.fully-covered {
    background:#28a745;
    color:white;
    padding:10px;
    text-align:center;
    font-weight:bold;
    margin-bottom:15px;
    border-radius:5px;
}
@media print { .print-btn { display:none; } }
.total-box { font-weight:bold; font-size:16px; margin-top:5px; }
.invoice-table th, .invoice-table td { padding:6px; }
</style>
</head>

<body>

<?php if ($total_out_of_pocket <= 0): ?>
<div class="fully-covered">FULLY PAID</div>
<?php endif; ?>

<button class="btn btn-primary print-btn mb-2" onclick="window.print()">Print Invoice</button>

<h2>PATIENT INVOICE REPORT</h2>

<table width="100%" style="margin-bottom:10px;">
<tr>
<td width="48%" valign="top" style="border-right:1px solid #1976d2;">
<span class="blue-label">PATIENT INFORMATION</span>
<table class="info-table">
<tr><td>Name:</td><td><?= htmlspecialchars($full_name) ?></td></tr>
<tr><td>Contact Number:</td><td><?= htmlspecialchars($billing['phone_number']) ?></td></tr>
<tr><td>Address:</td><td><?= htmlspecialchars($billing['address']) ?></td></tr>
<?php if($insurance): ?>
<tr><td>Insurance:</td><td><?= htmlspecialchars($insurance['insurance_company'].' ('.$insurance['promo_name'].')') ?></td></tr>
<tr><td>Insurance No:</td><td><?= htmlspecialchars($insurance['insurance_number']) ?></td></tr>
<?php endif; ?>
</table>
</td>

<td width="4%"></td>

<td width="48%" valign="top" style="border-left:1px solid #1976d2;">
<span class="blue-label">DOCTOR INFORMATION</span>
<table class="info-table">
<tr>
<td>Name:</td>
<td>
<?php
if ($doctor) {
    $doc = $doctor['first_name'];
    if (!empty($doctor['middle_name'])) $doc .= ' '.$doctor['middle_name'];
    if (!empty($doctor['last_name'])) $doc .= ' '.$doctor['last_name'];
    if (!empty($doctor['suffix_name'])) $doc .= ', '.$doctor['suffix_name'];
    echo htmlspecialchars($doc);
} else {
    echo "N/A";
}
?>
</td>
</tr>
<tr><td>Contact:</td><td><?= $doctor['contact_number'] ?? 'N/A' ?></td></tr>
<tr><td>Specialization:</td><td><?= $doctor['specialization'] ?? 'N/A' ?></td></tr>
</table>
</td>
</tr>
</table>

<table width="100%" class="invoice-meta" style="margin-bottom:10px;">
<tr>
<td class="blue-label">INVOICE NUMBER</td>
<td class="blue-label">DATE</td>
<td class="blue-label">AMOUNT DUE</td>
</tr>
<tr>
<td><?= 'INV-'.$receipt_id ?></td>
<td><?= htmlspecialchars($billing['billing_date']) ?></td>
<td class="amount-due-box">₱ <?= number_format($total_out_of_pocket,2) ?></td>
</tr>
</table>

<table width="100%" class="invoice-table table table-bordered">
<thead>
<tr>
<th>ITEM</th>
<th>DESCRIPTION</th>
<th>QTY</th>
<th>UNIT PRICE</th>
<th>AMOUNT</th>
</tr>
</thead>
<tbody>
<?php if ($billing_items): ?>
<?php foreach ($billing_items as $item): ?>
<tr>
<td><?= htmlspecialchars($item['serviceName']) ?></td>
<td><?= htmlspecialchars($item['description']) ?></td>
<td><?= (int)$item['quantity'] ?></td>
<td>₱ <?= number_format($item['unit_price'],2) ?></td>
<td>₱ <?= number_format($item['total_price'],2) ?></td>
</tr>
<?php endforeach; ?>
<?php else: ?>
<tr><td colspan="5" class="text-center">No billing items found.</td></tr>
<?php endif; ?>
</tbody>
</table>

<table width="100%" style="margin-bottom:10px;">
<tr>
<td width="70%"></td>
<td width="30%">
SUB TOTAL: ₱ <?= number_format($total_charges,2) ?><br>
DISCOUNT: ₱ <?= number_format($total_discount,2) ?><br>
INSURANCE: ₱ <?= number_format($insurance_covered,2) ?><br>
<div class="total-box">TOTAL DUE: ₱ <?= number_format($total_out_of_pocket,2) ?></div>
</td>
</tr>
</table>

<b>NOTES</b><br>
Thank you for choosing our hospital!

</body>
</html>
