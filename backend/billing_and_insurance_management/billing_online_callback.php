<?php
include '../../SQL/config.php';
if (session_status() === PHP_SESSION_NONE) session_start();

// BillEase webhook sends payment info
$payload = file_get_contents('php://input');
$data = json_decode($payload, true);

// Log incoming data for debugging (optional)
// file_put_contents('bill_payment_log.txt', json_encode($data, JSON_PRETTY_PRINT));

if (!$data || !isset($data['status']) || !isset($data['order_id'])) {
    http_response_code(400);
    exit("Invalid request");
}

$order_id = $data['order_id'];
$payment_status = strtolower($data['status']);
$amount_paid = floatval($data['amount'] ?? 0);

// Map order_id to patient and billing
// Assuming you saved order_id in session or DB when creating BillEase payment
$stmt = $conn->prepare("SELECT patient_id FROM billing_orders WHERE order_id=? LIMIT 1");
$stmt->bind_param("s", $order_id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$patient_id = $row['patient_id'] ?? 0;

if (!$patient_id || $payment_status !== 'paid') {
    http_response_code(400);
    exit("Payment not successful or patient not found");
}

// Fetch billing items and insurance coverage
$total_charges = 0;
$billing_items = [];
$service_prices = [];
$service_stmt = $conn->query("SELECT serviceName, description, price FROM dl_services");
while($r = $service_stmt->fetch_assoc()){
    $service_prices[$r['serviceName']] = ['description'=>$r['description'], 'price'=>floatval($r['price'])];
}

$stmt = $conn->prepare("SELECT * FROM dl_results WHERE patientID=? AND status='Completed'");
$stmt->bind_param("i", $patient_id);
$stmt->execute();
$results = $stmt->get_result();
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

// Insurance coverage
$insurance_covered = 0;
$stmt = $conn->prepare("SELECT SUM(covered_amount) AS total_covered FROM insurance_requests WHERE patient_id=? AND status='Approved'");
$stmt->bind_param("i", $patient_id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$insurance_covered = floatval($row['total_covered'] ?? 0);

$grand_total = $total_charges;
$total_out_of_pocket = max($grand_total - $insurance_covered, 0);

// Save receipt
$txn_id = "BE-" . uniqid();
$payment_method = "BillEase Online";
$ref = $data['transaction_id'] ?? $txn_id;

$stmt = $conn->prepare("
    INSERT INTO patient_receipt 
    (patient_id, billing_id, total_charges, total_vat, total_discount, total_out_of_pocket, grand_total, billing_date, insurance_covered, payment_method, status, transaction_id, payment_reference, is_pwd)
    VALUES (?, ?, ?, 0, 0, ?, ?, CURDATE(), ?, ?, 'Paid', ?, ?, 0)
");

// Determine billing_id (latest finalized)
$billing_id = null;
$stmt2 = $conn->prepare("SELECT MAX(billing_id) AS latest_billing_id FROM billing_items WHERE patient_id=? AND finalized=1");
$stmt2->bind_param("i", $patient_id);
$stmt2->execute();
$res = $stmt2->get_result()->fetch_assoc();
$billing_id = $res['latest_billing_id'] ?? null;

$stmt->bind_param("iidddds", $patient_id, $billing_id, $total_charges, $total_out_of_pocket, $grand_total, $insurance_covered, $payment_method, $txn_id, $ref);
$stmt->execute();
$receipt_id = $stmt->insert_id;

// Create journal entry
$description = "Receipt for Patient #$patient_id (BillEase Payment)";
$reference = "RCPT-" . $receipt_id;
$status = "Posted";
$created_by = $_SESSION['username'] ?? 'System';

$stmt = $conn->prepare("INSERT INTO journal_entries (entry_date, module, description, reference, status, created_by, reference_type, reference_id) VALUES (NOW(), 'billing', ?, ?, ?, ?, 'Patient Billing', ?)");
$stmt->bind_param("ssssi", $description, $reference, $status, $created_by, $receipt_id);
$stmt->execute();
$entry_id = $stmt->insert_id;

// Create journal entry lines
// 1. Accounts Receivable - Insurance (if any)
if($insurance_covered > 0){
    $stmt = $conn->prepare("INSERT INTO journal_entry_lines (entry_id, account_name, debit, credit, description) VALUES (?, 'Accounts Receivable - Insurance', ?, 0, ?)");
    $stmt->bind_param("ids", $entry_id, $insurance_covered, $description);
    $stmt->execute();
}

// 2. Accounts Receivable - BillEase (online payment)
if($total_out_of_pocket > 0){
    $stmt = $conn->prepare("INSERT INTO journal_entry_lines (entry_id, account_name, debit, credit, description) VALUES (?, 'Accounts Receivable - BillEase', ?, 0, ?)");
    $stmt->bind_param("ids", $entry_id, $total_out_of_pocket, $description);
    $stmt->execute();
}

// 3. Service Revenue
$stmt = $conn->prepare("INSERT INTO journal_entry_lines (entry_id, account_name, debit, credit, description) VALUES (?, 'Service Revenue', 0, ?, ?)");
$stmt->bind_param("ids", $entry_id, $grand_total, $description);
$stmt->execute();

// Respond to BillEase webhook
http_response_code(200);
echo json_encode(["status"=>"success"]);
?>
