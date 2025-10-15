<?php
include '../../SQL/config.php';
if (session_status() === PHP_SESSION_NONE) session_start();

// BillEase integration
$billease_api_key = "YOUR_BILLEASE_API_KEY"; // replace with your actual key
$billease_payment_url = "https://sandbox.billease.ph/api/v1/payment"; // BillEase endpoint

// Get patient_id
$patient_id = isset($_GET['patient_id']) ? intval($_GET['patient_id']) : 0;
$selected_patient = null;

if ($patient_id > 0) {
    $stmt = $conn->prepare("SELECT * FROM patientinfo WHERE patient_id = ?");
    $stmt->bind_param("i", $patient_id);
    $stmt->execute();
    $selected_patient = $stmt->get_result()->fetch_assoc();
}

// Fetch services from dl_results and prices from dl_services
$billing_items = [];
$total_charges = 0;

if ($patient_id > 0) {
    $stmt = $conn->prepare("SELECT * FROM dl_results WHERE patientID=? AND status='Completed'");
    $stmt->bind_param("i", $patient_id);
    $stmt->execute();
    $results = $stmt->get_result();

    // Get service prices
    $service_prices = [];
    $service_stmt = $conn->query("SELECT serviceName, description, price FROM dl_services");
    while($row = $service_stmt->fetch_assoc()){
        $service_prices[$row['serviceName']] = ['description'=>$row['description'], 'price'=>floatval($row['price'])];
    }

    while($row = $results->fetch_assoc()){
        $services = explode(',', $row['result']);
        foreach($services as $s){
            $s = trim($s);
            $price = $service_prices[$s]['price'] ?? 0;
            $desc = $service_prices[$s]['description'] ?? '';
            $billing_items[] = ['service_name'=>$s, 'description'=>$desc, 'total_price'=>$price];
            $total_charges += $price;
        }
    }
}

// Insurance coverage
$insurance_covered = 0;
$stmt = $conn->prepare("SELECT SUM(covered_amount) AS total_covered FROM insurance_requests WHERE patient_id=? AND status='Approved'");
$stmt->bind_param("i", $patient_id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$insurance_covered = floatval($row['total_covered'] ?? 0);

// Totals
$grand_total = $total_charges;
$total_out_of_pocket = max($grand_total - $insurance_covered,0);

// Handle BillEase payment request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pay_online'])) {

    // Here you would normally call BillEase API to create payment
    // For simplicity, we simulate a successful payment
    $transaction_id = 'BE'.uniqid();

    // Insert receipt
    $stmt = $conn->prepare("INSERT INTO patient_receipt
        (patient_id, total_charges, total_vat, total_discount, total_out_of_pocket, grand_total, billing_date, insurance_covered, payment_method, status, transaction_id, payment_reference, is_pwd)
        VALUES (?, ?, 0, 0, ?, ?, CURDATE(), ?, 'BillEase', 'Paid', ?, ?, ?)
    ");
    $is_pwd = $selected_patient['is_pwd'] ?? 0;
    $stmt->bind_param("iddddsis",$patient_id,$total_charges,$total_out_of_pocket,$grand_total,$insurance_covered,$transaction_id,$transaction_id,$is_pwd);
    $stmt->execute();
    $receipt_id = $stmt->insert_id;

    // Create journal entry
    $description = "BillEase Payment for Patient #$patient_id";
    $stmt = $conn->prepare("INSERT INTO journal_entries (entry_date,module,description,reference_type,reference,status,created_by) VALUES (NOW(),'billing',?,'Patient Billing',?,'Posted','System')");
    $stmt->bind_param("ss",$description,$receipt_id);
    $stmt->execute();
    $entry_id = $stmt->insert_id;

    // Journal entry lines
    if($insurance_covered>0){
        $stmt2 = $conn->prepare("INSERT INTO journal_entry_lines (entry_id, account_name, debit, credit, description) VALUES (?,?,?,?,?)");
        $insurance_account = 'Accounts Receivable - Insurance';
        $zero = 0;
        $stmt2->bind_param("isdss", $entry_id, $insurance_account, $insurance_covered, $zero, $description);
        $stmt2->execute();
    }
    if($total_out_of_pocket>0){
        $stmt2 = $conn->prepare("INSERT INTO journal_entry_lines (entry_id, account_name, debit, credit, description) VALUES (?,?,?,?,?)");
        $account = 'Cash in Bank - Online';
        $zero = 0;
        $stmt2->bind_param("isdss", $entry_id, $account, $total_out_of_pocket, $zero, $description);
        $stmt2->execute();
    }
    $stmt2 = $conn->prepare("INSERT INTO journal_entry_lines (entry_id, account_name, debit, credit, description) VALUES (?,?,?,?,?)");
    $zero = 0;
    $service_revenue_account = 'Service Revenue';
    $stmt2->bind_param("isdss", $entry_id, $service_revenue_account, $zero, $grand_total, $description);
    $stmt2->execute();

    echo "<script>alert('Payment successful via BillEase!'); window.location='billing_summary.php?patient_id={$patient_id}';</script>";
    exit;
}

?>

<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Billing Summary</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<link rel="stylesheet" href="assets/CSS/billing_summary.css">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
</head>
<body class="bg-light p-4">

<div class="receipt-box">
    <div class="d-flex justify-content-between mb-3">
        <h4>Billing Summary</h4>
        <div><?= date('F j, Y') ?></div>
    </div>

    <div class="mb-2">
        <strong>BILLED TO:</strong><br>
        <?= htmlspecialchars($selected_patient ? ($selected_patient['fname'] ?? '') . ' ' . ($selected_patient['mname'] ?? '') . ' ' . ($selected_patient['lname'] ?? '') : 'N/A') ?><br>
        Phone: <?= htmlspecialchars($selected_patient['phone_number'] ?? 'N/A') ?><br>
        Address: <?= htmlspecialchars($selected_patient['address'] ?? 'N/A') ?>
    </div>

    <table class="table table-sm table-bordered mb-3">
        <thead>
            <tr>
                <th>Service</th>
                <th>Description</th>
                <th class="text-end">Amount</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($billing_items)): foreach ($billing_items as $it): ?>
                <tr>
                    <td><?= htmlspecialchars($it['service_name']) ?></td>
                    <td><?= htmlspecialchars($it['description']) ?></td>
                    <td class="text-end">₱<?= number_format($it['total_price'],2) ?></td>
                </tr>
            <?php endforeach; else: ?>
                <tr><td colspan="3" class="text-center">No billed services found.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>

    <div class="text-end mb-3">
        <strong>Total Charges: ₱<?= number_format($total_charges,2) ?></strong><br>
        <strong>Insurance Covered: ₱<?= number_format($insurance_covered,2) ?></strong><br>
        <strong>Total to Pay: ₱<?= number_format($total_out_of_pocket,2) ?></strong>
    </div>

    <?php if ($total_out_of_pocket > 0): ?>
        <form method="POST" class="text-end">
            <button type="submit" name="pay_online" class="btn btn-success">
                <i class="bi bi-credit-card me-1"></i> Pay via BillEase
            </button>
        </form>
    <?php else: ?>
        <div class="text-end">
            <span class="text-success fw-bold">No payment required. Covered by insurance.</span>
        </div>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
