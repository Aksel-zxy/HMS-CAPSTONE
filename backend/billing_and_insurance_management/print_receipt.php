<?php
session_start();
include '../../SQL/config.php';

// Get receipt ID
$receipt_id = isset($_GET['receipt_id']) ? intval($_GET['receipt_id']) : 0;
if ($receipt_id <= 0) {
    echo "Invalid receipt ID.";
    exit();
}

// Fetch receipt with patient info
$stmt = $conn->prepare("
    SELECT pr.*, pi.fname, pi.mname, pi.lname, pi.phone_number, pi.address, pi.attending_doctor
    FROM patient_receipt pr
    JOIN patientinfo pi ON pr.patient_id = pi.patient_id
    WHERE pr.receipt_id = ?
");
$stmt->bind_param("i", $receipt_id);
$stmt->execute();
$billing = $stmt->get_result()->fetch_assoc();
if (!$billing) {
    echo "Receipt not found.";
    exit();
}

// Fetch doctor info if available
$doctor = null;
if (!empty($billing['attending_doctor'])) {
    $stmt2 = $conn->prepare("SELECT * FROM hr_employees WHERE employee_id = ?");
    $stmt2->bind_param("i", $billing['attending_doctor']);
    $stmt2->execute();
    $doctor = $stmt2->get_result()->fetch_assoc();
}

// Fetch billing items with service details
$stmt3 = $conn->prepare("
    SELECT bi.quantity, bi.unit_price, bi.total_price, ds.serviceName AS service_name, ds.description
    FROM billing_items bi
    LEFT JOIN dl_services ds ON bi.service_id = ds.serviceID
    WHERE bi.patient_id = ? AND bi.billing_id = ?
");
$stmt3->bind_param("ii", $billing['patient_id'], $billing['billing_id']);
$stmt3->execute();
$billing_items = $stmt3->get_result()->fetch_all(MYSQLI_ASSOC);

// Use stored totals from patient_receipt
$total_charges = floatval($billing['total_charges']);
$total_discount = floatval($billing['total_discount']);
$insurance_covered = floatval($billing['insurance_covered']);
$total_out_of_pocket = floatval($billing['total_out_of_pocket']);
$grand_total = floatval($billing['grand_total']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="assets/CSS/bootstrap.min.css">
<link rel="stylesheet" href="assets/CSS/pdf.css">
<link rel="stylesheet" type="text/css" href="assets/css/billing_sidebar.css">
<title>Patient Invoice</title>
<style>
.fully-covered { background-color: #28a745; color: white; padding: 10px; text-align: center; font-weight: bold; margin-bottom: 15px; border-radius: 5px; }
@media print { .print-btn { display: none !important; } }
.total-box { font-weight: bold; font-size: 16px; margin-top: 5px; }
.invoice-table th, .invoice-table td { padding: 5px; }
</style>
</head>
<body>

<?php if ($total_out_of_pocket <= 0): ?>
    <div class="fully-covered">FULLY PAID</div>
<?php endif; ?>

<button class="print-btn mb-2 btn btn-primary" onclick="window.print()">Print Invoice</button>

<h2>PATIENT INVOICE REPORT</h2>

<table width="100%" style="margin-bottom:10px;">
<tr>
<td width="48%" valign="top" style="border-right:1px solid #1976d2;">
<span class="blue-label">PATIENT INFORMATION</span>
<table class="info-table">
<tr><td>Name:</td><td><?= htmlspecialchars($billing['fname'].' '.(!empty($billing['mname'])?$billing['mname'].' ':'').$billing['lname']); ?></td></tr>
<tr><td>Contact Number:</td><td><?= htmlspecialchars($billing['phone_number']); ?></td></tr>
<tr><td>Address:</td><td><?= htmlspecialchars($billing['address']); ?></td></tr>
</table>
</td>
<td width="4%"></td>
<td width="48%" valign="top" style="border-left:1px solid #1976d2;">
<span class="blue-label">DOCTOR INFORMATION</span>
<table class="info-table">
<tr><td>Name:</td><td>
<?php
if ($doctor) {
    $full = $doctor['first_name'];
    if (!empty($doctor['middle_name'])) $full .= ' '.$doctor['middle_name'];
    if (!empty($doctor['last_name'])) $full .= ' '.$doctor['last_name'];
    if (!empty($doctor['suffix_name'])) $full .= ', '.$doctor['suffix_name'];
    echo htmlspecialchars($full);
} else echo "N/A";
?></td></tr>
<tr><td>Contact Number:</td><td><?= ($doctor && !empty($doctor['contact_number']))?htmlspecialchars($doctor['contact_number']):"N/A"; ?></td></tr>
<tr><td>Specialization:</td><td><?= ($doctor && !empty($doctor['specialization']))?htmlspecialchars($doctor['specialization']):"N/A"; ?></td></tr>
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
<td><?= 'INV-'.$receipt_id; ?></td>
<td><?= htmlspecialchars($billing['billing_date']); ?></td>
<td class="amount-due-box"><?= '₱ '.number_format($total_out_of_pocket, 2); ?></td>
</tr>
</table>

<table class="invoice-table table table-bordered">
<tr>
<th>ITEM</th>
<th>DESCRIPTION</th>
<th>QTY</th>
<th>UNIT PRICE</th>
<th>AMOUNT</th>
</tr>
<?php if (!empty($billing_items)): foreach ($billing_items as $item): ?>
<tr>
<td><?= htmlspecialchars($item['service_name']); ?></td>
<td><?= htmlspecialchars($item['description']); ?></td>
<td><?= intval($item['quantity']); ?></td>
<td>₱ <?= number_format($item['unit_price'],2); ?></td>
<td>₱ <?= number_format($item['total_price'],2); ?></td>
</tr>
<?php endforeach; else: ?>
<tr><td colspan="5" style="text-align:center;">No billing items found.</td></tr>
<?php endif; ?>
</table>

<table width="100%" style="margin-bottom:10px;">
<tr>
<td width="70%"></td>
<td width="30%">
SUB TOTAL: ₱ <?= number_format($total_charges,2); ?><br>
PWD/SENIOR DISCOUNT: ₱ <?= number_format($total_discount,2); ?><br>
INSURANCE COVERED: ₱ <?= number_format($insurance_covered,2); ?><br>
<div class="total-box">TOTAL: ₱ <?= number_format($total_out_of_pocket,2); ?></div>
</td>
</tr>
</table>

<b style="font-size:15px;">NOTES</b><br>
Thank you for choosing our hospital!

<div class="footer-bar">
<table width="100%">
<tr>
<td width="50%" style="font-weight:bold;">Name of the Hospital</td>
<td width="50%" align="right">ADDRESS</td>
</tr>
</table>
</div>
</body>
</html>
