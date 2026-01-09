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

// Fetch patient insurance
$insurance = $conn->query("
    SELECT * FROM patient_insurance 
    WHERE full_name = '".$billing['fname']." ".(!empty($billing['mname'])?$billing['mname'].' ':'').$billing['lname']."' 
      AND status='Active' LIMIT 1
")->fetch_assoc();

// Fetch doctor info if available
$doctor = null;
if (!empty($billing['attending_doctor'])) {
    $stmt2 = $conn->prepare("SELECT * FROM hr_employees WHERE employee_id = ?");
    $stmt2->bind_param("i", $billing['attending_doctor']);
    $stmt2->execute();
    $doctor = $stmt2->get_result()->fetch_assoc();
}

// Fetch completed lab results for this patient
$billing_items = [];
$total_charges = 0;

$result_stmt = $conn->prepare("SELECT * FROM dl_results WHERE patientID=? AND status='Completed'");
$result_stmt->bind_param("i", $billing['patient_id']);
$result_stmt->execute();
$results = $result_stmt->get_result();

// Fetch service prices
$service_prices = [];
$service_stmt = $conn->query("SELECT serviceName, description, price FROM dl_services");
while ($row = $service_stmt->fetch_assoc()) {
    $service_prices[$row['serviceName']] = [
        'description' => $row['description'],
        'price'       => floatval($row['price'])
    ];
}

// Add billing items and calculate total charges
while ($row = $results->fetch_assoc()) {
    $services = explode(',', $row['result']);
    foreach ($services as $s) {
        $s = trim($s);
        $price = $service_prices[$s]['price'] ?? 0;
        $desc  = $service_prices[$s]['description'] ?? '';
        $billing_items[] = [
            'service_name' => $s,
            'description'  => $desc,
            'quantity'     => 1,
            'unit_price'   => $price,
            'total_price'  => $price
        ];
        $total_charges += $price;
    }
}

// Calculate insurance deduction
$insurance_covered = 0;
if ($insurance) {
    if ($insurance['discount_type'] === 'Percentage') {
        $insurance_covered = ($insurance['discount_value']/100) * $total_charges;
    } else { // Fixed
        $insurance_covered = min($insurance['discount_value'], $total_charges);
    }
}

// Assume total_discount (PWD/Senior) is already stored in receipt
$total_discount = floatval($billing['total_discount']);
$total_out_of_pocket = $total_charges - $insurance_covered - $total_discount;
$grand_total = $total_charges - $total_discount;
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
<?php if($insurance): ?>
<tr><td>Insurance:</td><td><?= htmlspecialchars($insurance['insurance_company'].' ('.$insurance['promo_name'].')'); ?></td></tr>
<tr><td>Insurance Number:</td><td><?= htmlspecialchars($insurance['insurance_number']); ?></td></tr>
<?php endif; ?>
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

<table width="100%" class="invoice-table table table-bordered">
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
<div class="total-box">TOTAL DUE: ₱ <?= number_format($total_out_of_pocket,2); ?></div>
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
