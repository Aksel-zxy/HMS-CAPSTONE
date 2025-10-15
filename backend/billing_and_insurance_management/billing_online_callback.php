<?php
include '../../SQL/config.php';
if (session_status() === PHP_SESSION_NONE) session_start();

// Get the incoming data from BillEase (JSON)
$input = file_get_contents('php://input');
$data = json_decode($input, true);

// Log the callback (optional, for debugging)
// file_put_contents('billease_callback.log', $input.PHP_EOL, FILE_APPEND);

if (!$data || !isset($data['payment_status']) || $data['payment_status'] !== 'success') {
    http_response_code(400);
    exit('Invalid payment callback');
}

// Get patient_id and amount from callback (these should be passed in BillEase payload)
$patient_id = intval($data['patient_id'] ?? 0);
$amount_paid = floatval($data['amount'] ?? 0);
$transaction_id = $data['transaction_id'] ?? 'BE'.uniqid();

if($patient_id <= 0 || $amount_paid <= 0){
    http_response_code(400);
    exit('Invalid data');
}

// --- Fetch patient and billing info ---
$stmt = $conn->prepare("SELECT * FROM patientinfo WHERE patient_id=?");
$stmt->bind_param("i",$patient_id);
$stmt->execute();
$patient = $stmt->get_result()->fetch_assoc();

if(!$patient){
    http_response_code(404);
    exit('Patient not found');
}

// --- Calculate billing totals ---
$total_charges = 0;
$billing_items = [];

$stmt = $conn->prepare("SELECT * FROM dl_results WHERE patientID=? AND status='Completed'");
$stmt->bind_param("i",$patient_id);
$stmt->execute();
$res = $stmt->get_result();

$service_prices = [];
$service_stmt = $conn->query("SELECT serviceName, description, price FROM dl_services");
while($row=$service_stmt->fetch_assoc()){
    $service_prices[$row['serviceName']] = [
        'description'=>$row['description'],
        'price'=>floatval($row['price'])
    ];
}

while($row = $res->fetch_assoc()){
    $services = explode(',',$row['result']);
    foreach($services as $s){
        $s = trim($s);
        $price = $service_prices[$s]['price'] ?? 0;
        $desc = $service_prices[$s]['description'] ?? '';
        $billing_items[] = ['service_name'=>$s,'description'=>$desc,'total_price'=>$price];
        $total_charges += $price;
    }
}

// --- Insurance ---
$insurance_covered = 0;
$stmt = $conn->prepare("SELECT SUM(covered_amount) AS total_covered FROM insurance_requests WHERE patient_id=? AND status='Approved'");
$stmt->bind_param("i",$patient_id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$insurance_covered = floatval($row['total_covered'] ?? 0);

// --- Totals ---
$grand_total = $total_charges;
$total_out_of_pocket = max($grand_total - $insurance_covered,0);

// --- Insert receipt ---
$stmt = $conn->prepare("INSERT INTO patient_receipt
    (patient_id, total_charges, total_vat, total_discount, total_out_of_pocket, grand_total, billing_date, insurance_covered, payment_method, status, transaction_id, payment_reference, is_pwd)
    VALUES (?, ?, 0, 0, ?, ?, CURDATE(), ?, 'BillEase', 'Paid', ?, ?, ?)
");
$is_pwd = $patient['is_pwd'] ?? 0;
$stmt->bind_param("iddddsis",$patient_id,$total_charges,$total_out_of_pocket,$grand_total,$insurance_covered,$transaction_id,$transaction_id,$is_pwd);
$stmt->execute();
$receipt_id = $stmt->insert_id;

// --- Create journal entry ---
$description = "BillEase Payment for Patient #$patient_id";
$stmt = $conn->prepare("INSERT INTO journal_entries (entry_date,module,description,reference_type,reference,status,created_by) VALUES (NOW(),'billing',?,'Patient Billing',?,'Posted',?)");
$created_by = 'System';
$stmt->bind_param("sss",$description,$receipt_id,$created_by);
$stmt->execute();
$entry_id = $stmt->insert_id;

// --- Journal entry lines ---
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

// Respond to BillEase
http_response_code(200);
echo json_encode(['status'=>'success']);
