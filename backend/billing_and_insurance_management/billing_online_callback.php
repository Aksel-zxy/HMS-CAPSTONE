<?php
include '../../SQL/config.php';
if (session_status() === PHP_SESSION_NONE) session_start();

// -------------------- BillEase API configuration --------------------
$billease_api_key = "YOUR_BILLEASE_API_KEY"; // Replace with your API key
$billease_api_secret = "YOUR_BILLEASE_API_SECRET"; // Replace with your secret
$use_sandbox = true; // Change to false for production

$api_base = $use_sandbox ? "https://sandbox.billease.ph/api/v1" : "https://api.billease.ph/v1";

// -------------------- Get callback info --------------------
$patient_id = isset($_GET['patient_id']) ? intval($_GET['patient_id']) : 0;
$status     = $_GET['status'] ?? 'failed';
$order_id   = $_GET['order_id'] ?? null;

if ($patient_id <= 0 || !$order_id) {
    echo "<script>alert('Invalid request.'); window.location='billing_summary.php?patient_id=$patient_id';</script>";
    exit;
}

// -------------------- Verify payment --------------------
$ch = curl_init("$api_base/transactions/$order_id");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Authorization: Bearer $billease_api_key",
    "Content-Type: application/json"
]);
$response = curl_exec($ch);
$httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_err = curl_error($ch);
curl_close($ch);

if ($curl_err) {
    error_log("BillEase callback error: $curl_err");
    echo "<script>alert('Error connecting to BillEase.'); window.location='billing_summary.php?patient_id=$patient_id';</script>";
    exit;
}

$payment_info = json_decode($response, true);

if ($httpcode != 200 || !isset($payment_info['status']) || strtolower($payment_info['status']) != 'paid') {
    echo "<script>alert('Payment not confirmed by BillEase.'); window.location='billing_summary.php?patient_id=$patient_id';</script>";
    exit;
}

// -------------------- Fetch patient --------------------
$patient_stmt = $conn->prepare("SELECT * FROM patientinfo WHERE patient_id=?");
$patient_stmt->bind_param("i", $patient_id);
$patient_stmt->execute();
$patient = $patient_stmt->get_result()->fetch_assoc();

// -------------------- Fetch completed services --------------------
$billing_items = [];
$total_charges = 0;
$service_prices = [];
$service_stmt = $conn->query("SELECT serviceName, description, price FROM dl_services");
while ($row = $service_stmt->fetch_assoc()) {
    $service_prices[$row['serviceName']] = [
        'description' => $row['description'],
        'price'       => floatval($row['price'])
    ];
}

$result_stmt = $conn->prepare("SELECT * FROM dl_results WHERE patientID=? AND status='Completed'");
$result_stmt->bind_param("i", $patient_id);
$result_stmt->execute();
$results = $result_stmt->get_result();

while ($row = $results->fetch_assoc()) {
    $services = explode(',', $row['result']);
    foreach ($services as $s) {
        $s = trim($s);
        $price = $service_prices[$s]['price'] ?? 0;
        $desc  = $service_prices[$s]['description'] ?? '';
        $billing_items[] = ['service_name' => $s, 'description' => $desc, 'total_price' => $price];
        $total_charges += $price;
    }
}

// -------------------- Fetch insurance coverage --------------------
$insurance_stmt = $conn->prepare("SELECT SUM(covered_amount) AS total_covered FROM insurance_requests WHERE patient_id=? AND status='Approved'");
$insurance_stmt->bind_param("i", $patient_id);
$insurance_stmt->execute();
$insurance_row = $insurance_stmt->get_result()->fetch_assoc();
$insurance_covered = floatval($insurance_row['total_covered'] ?? 0);

$grand_total          = $total_charges;
$total_out_of_pocket   = max($grand_total - $insurance_covered, 0);

// -------------------- Insert patient_receipt --------------------
$stmt = $conn->prepare("
    INSERT INTO patient_receipt 
    (patient_id, total_charges, total_discount, total_out_of_pocket, grand_total, billing_date, insurance_covered, payment_method, status, transaction_id, payment_reference)
    VALUES (?, ?, 0, ?, ?, CURDATE(), ?, 'BillEase', 'Paid', ?, ?)
");
$txn_id       = $payment_info['id'] ?? 'N/A';
$payment_ref  = $payment_info['payment_code'] ?? $order_id;

$stmt->bind_param(
    "idddiss",
    $patient_id,
    $grand_total,
    $total_out_of_pocket,
    $grand_total,
    $insurance_covered,
    $txn_id,
    $payment_ref
);
$stmt->execute();
$receipt_id = $stmt->insert_id;

// -------------------- Create journal entry --------------------
$description = "Patient #$patient_id Billing via BillEase";
$reference   = "RCPT-$receipt_id";
$created_by  = $_SESSION['username'] ?? 'System';

$je_stmt = $conn->prepare("
    INSERT INTO journal_entries (entry_date, module, description, reference, status, created_by, reference_type, reference_id) 
    VALUES (NOW(), 'billing', ?, ?, 'Posted', ?, 'Patient Billing', ?)
");
$je_stmt->bind_param("sssi", $description, $reference, $created_by, $receipt_id);
$je_stmt->execute();
$entry_id = $conn->insert_id;

// -------------------- Insert journal entry lines --------------------
$line_stmt = $conn->prepare("
    INSERT INTO journal_entry_lines (entry_id, account_name, debit, credit, description) 
    VALUES (?, ?, ?, ?, ?)
");

// Insurance portion
if ($insurance_covered > 0) {
    $acc_name = 'Accounts Receivable - Insurance';
    $debit = $insurance_covered;
    $credit = 0;
    $desc = $description;
    $line_stmt->bind_param("isdds", $entry_id, $acc_name, $debit, $credit, $desc);
    $line_stmt->execute();
}

// Patient/BillEase payment portion
if ($total_out_of_pocket > 0) {
    $acc_name = 'Accounts Receivable - BillEase';
    $debit = $total_out_of_pocket;
    $credit = 0;
    $desc = $description;
    $line_stmt->bind_param("isdds", $entry_id, $acc_name, $debit, $credit, $desc);
    $line_stmt->execute();
}

// Revenue
$acc_name = 'Service Revenue';
$debit = 0;
$credit = $grand_total;
$desc = $description;
$line_stmt->bind_param("isdds", $entry_id, $acc_name, $debit, $credit, $desc);
$line_stmt->execute();

// -------------------- Redirect to summary --------------------
echo "<script>
    alert('Payment confirmed and journal entries created successfully!');
    window.location='billing_summary.php?patient_id=$patient_id';
</script>";
exit;
