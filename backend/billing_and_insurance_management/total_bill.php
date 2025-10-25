<?php
include '../../SQL/config.php';

// Get patient ID
$patient_id = isset($_GET['patient_id']) ? intval($_GET['patient_id']) : 0;
if ($patient_id <= 0) {
    echo "Invalid patient ID.";
    exit();
}

// Fetch patient info
$patient_stmt = $conn->prepare("
    SELECT p.*, 
           CONCAT(p.fname, ' ', IFNULL(p.mname, ''), ' ', p.lname) AS full_name,
           p.attending_doctor
    FROM patientinfo p
    WHERE p.patient_id = ?
");
$patient_stmt->bind_param("i", $patient_id);
$patient_stmt->execute();
$patient = $patient_stmt->get_result()->fetch_assoc();

if (!$patient) {
    echo "Patient not found.";
    exit();
}

// Fetch doctor info (if any)
$doctor = null;
if (!empty($patient['attending_doctor'])) {
    $doc_stmt = $conn->prepare("SELECT * FROM hr_employees WHERE employee_id = ?");
    $doc_stmt->bind_param("i", $patient['attending_doctor']);
    $doc_stmt->execute();
    $doctor = $doc_stmt->get_result()->fetch_assoc();
}

// Fetch all completed lab results for this patient
$results_stmt = $conn->prepare("SELECT * FROM dl_results WHERE patientID=? AND status='Completed'");
$results_stmt->bind_param("i", $patient_id);
$results_stmt->execute();
$results = $results_stmt->get_result();

// Fetch service prices
$service_prices = [];
$service_query = $conn->query("SELECT serviceName, description, price FROM dl_services");
while ($row = $service_query->fetch_assoc()) {
    $service_prices[$row['serviceName']] = [
        'description' => $row['description'],
        'price' => floatval($row['price'])
    ];
}

// Compute bill items and totals
$billing_items = [];
$total_charges = 0;

while ($row = $results->fetch_assoc()) {
    $services = explode(',', $row['result']);
    foreach ($services as $service) {
        $service = trim($service);
        if (empty($service)) continue;

        $price = $service_prices[$service]['price'] ?? 0;
        $desc  = $service_prices[$service]['description'] ?? '';
        $billing_items[] = [
            'service_name' => $service,
            'description'  => $desc,
            'quantity'     => 1,
            'unit_price'   => $price,
            'total_price'  => $price
        ];
        $total_charges += $price;
    }
}

// Optional: Insurance adjustment or discounts
$discount = 0;
$insurance_covered = 0;
$out_of_pocket = $total_charges - ($discount + $insurance_covered);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Total Bill - <?= htmlspecialchars($patient['full_name']); ?></title>
<link rel="stylesheet" href="assets/CSS/bootstrap.min.css">
<link rel="stylesheet" href="assets/CSS/pdf.css">
<style>
body { background: #f8f9fa; padding: 20px; }
.invoice-box {
    background: #fff;
    padding: 20px 30px;
    border-radius: 10px;
    box-shadow: 0 3px 10px rgba(0,0,0,0.1);
}
.invoice-header {
    text-align: center;
    border-bottom: 2px solid #1976d2;
    margin-bottom: 20px;
    padding-bottom: 10px;
}
.invoice-header h2 { color: #1976d2; margin-bottom: 5px; }
.invoice-header p { margin: 0; color: #555; }
.info-table td { padding: 3px 5px; }
.table th, .table td { vertical-align: middle !important; }
.total-section { text-align: right; margin-top: 20px; font-size: 1.1em; }
.print-btn { float: right; }
@media print {
    .print-btn { display: none !important; }
    body { background: none; }
}
</style>
</head>
<body>

<div class="invoice-box">

    <div class="invoice-header">
        <h2>PATIENT TOTAL BILL SUMMARY</h2>
        <p>Date: <?= date("Y-m-d H:i:s"); ?></p>
    </div>

    <button class="btn btn-primary mb-3 print-btn" onclick="window.print()">
        <i class="bi bi-printer"></i> Print Bill
    </button>

    <table width="100%" style="margin-bottom:10px;">
        <tr>
            <td width="48%" valign="top" style="border-right:1px solid #1976d2;">
                <h6 class="text-primary">PATIENT INFORMATION</h6>
                <table class="info-table">
                    <tr><td>Name:</td><td><?= htmlspecialchars($patient['full_name']); ?></td></tr>
                    <tr><td>Contact:</td><td><?= htmlspecialchars($patient['phone_number']); ?></td></tr>
                    <tr><td>Address:</td><td><?= htmlspecialchars($patient['address']); ?></td></tr>
                </table>
            </td>
            <td width="4%"></td>
            <td width="48%" valign="top" style="border-left:1px solid #1976d2;">
                <h6 class="text-primary">DOCTOR INFORMATION</h6>
                <table class="info-table">
                    <tr><td>Name:</td><td>
                        <?php
                        if ($doctor) {
                            $full = $doctor['first_name'];
                            if (!empty($doctor['middle_name'])) $full .= ' '.$doctor['middle_name'];
                            if (!empty($doctor['last_name'])) $full .= ' '.$doctor['last_name'];
                            echo htmlspecialchars($full);
                        } else echo "N/A";
                        ?>
                    </td></tr>
                    <tr><td>Contact:</td><td><?= ($doctor && !empty($doctor['contact_number']))?htmlspecialchars($doctor['contact_number']):"N/A"; ?></td></tr>
                    <tr><td>Specialization:</td><td><?= ($doctor && !empty($doctor['specialization']))?htmlspecialchars($doctor['specialization']):"N/A"; ?></td></tr>
                </table>
            </td>
        </tr>
    </table>

    <table class="table table-bordered table-striped">
        <thead class="table-primary">
            <tr>
                <th>Item</th>
                <th>Description</th>
                <th class="text-center">Qty</th>
                <th class="text-end">Unit Price</th>
                <th class="text-end">Total</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($billing_items)): ?>
                <?php foreach ($billing_items as $item): ?>
                <tr>
                    <td><?= htmlspecialchars($item['service_name']); ?></td>
                    <td><?= htmlspecialchars($item['description']); ?></td>
                    <td class="text-center"><?= intval($item['quantity']); ?></td>
                    <td class="text-end">₱ <?= number_format($item['unit_price'], 2); ?></td>
                    <td class="text-end">₱ <?= number_format($item['total_price'], 2); ?></td>
                </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="5" class="text-center text-muted">No completed services found.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>

    <div class="total-section">
        <p>Subtotal: ₱ <?= number_format($total_charges, 2); ?></p>
        <p>Discount: ₱ <?= number_format($discount, 2); ?></p>
        <p>Insurance Covered: ₱ <?= number_format($insurance_covered, 2); ?></p>
        <h5><strong>Total Due: ₱ <?= number_format($out_of_pocket, 2); ?></strong></h5>
    </div>

    <div class="mt-4 text-muted">
        <strong>Notes:</strong><br>
        This is a preliminary bill summary based on completed lab results. The final invoice will be generated once approved.
    </div>

</div>

</body>
</html>
