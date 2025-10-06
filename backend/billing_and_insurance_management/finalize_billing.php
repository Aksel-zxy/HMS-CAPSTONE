<?php
session_start();
include '../../SQL/config.php';

// Enable exceptions for mysqli
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// Get patient ID
$patient_id = isset($_GET['patient_id']) ? intval($_GET['patient_id']) : 0;
if ($patient_id <= 0) die("Invalid patient ID.");

// Fetch patient info
$stmt = $conn->prepare("SELECT * FROM patientinfo WHERE patient_id=?");
$stmt->bind_param("i", $patient_id);
$stmt->execute();
$patient = $stmt->get_result()->fetch_assoc();
if (!$patient) die("Patient not found.");

// Check billing cart
if (!isset($_SESSION['billing_cart'][$patient_id]) || empty($_SESSION['billing_cart'][$patient_id])) {
    die("No services to finalize.");
}
$cart = $_SESSION['billing_cart'][$patient_id];

// Compute age
$dob = $patient['dob'];
$age = 0;
if (!empty($dob) && $dob != '0000-00-00') {
    $birth = new DateTime($dob);
    $today = new DateTime();
    $age = $today->diff($birth)->y;
}

// Check PWD status
$is_pwd = $_SESSION['is_pwd'][$patient_id] ?? ($patient['is_pwd'] ?? 0);

// Compute subtotal, item discounts, and grand total
$subtotal = 0;
foreach ($cart as &$srv) {
    $unit_price = $srv['price'];
    $srv_discount = ($is_pwd && $age < 60) ? ($unit_price * 0.20) : 0; // adjust business logic if needed
    $srv['total_price'] = $unit_price - $srv_discount;
    $subtotal += $srv['total_price'];
}
$discount = ($is_pwd && $age < 60) ? array_sum(array_column($cart, 'price')) - $subtotal : 0;
$grand_total = $subtotal;

// Begin transaction
$conn->begin_transaction();
try {
    // Insert into patient_receipt first to get billing_id (use AUTO_INCREMENT)
    $stmt_receipt = $conn->prepare("
        INSERT INTO patient_receipt
        (patient_id, total_charges, total_discount, total_out_of_pocket, grand_total, billing_date, payment_method, status, transaction_id, payment_reference, is_pwd)
        VALUES (?, ?, ?, ?, ?, CURDATE(), ?, ?, ?, ?, ?)
    ");

    $payment_method = "Unpaid";
    $status = "Pending";
    $txn = "TXN" . uniqid();
    $pay_ref = "Not Paid Yet";

    $stmt_receipt->bind_param(
        "iddddssssi",
        $patient_id,
        array_sum(array_column($cart, 'price')),
        $discount,
        $grand_total,
        $grand_total,
        $payment_method,
        $status,
        $txn,
        $pay_ref,
        $is_pwd
    );
    $stmt_receipt->execute();

    $billing_id = $conn->insert_id; // Get auto-generated billing_id

    // Insert each service into billing_items
    $stmt_item = $conn->prepare("
        INSERT INTO billing_items 
        (billing_id, item_type, item_description, quantity, unit_price, total_price)
        VALUES (?, 'Service', ?, 1, ?, ?)
    ");

    foreach ($cart as $srv) {
        $srv_name = $srv['serviceName'];
        $unit_price = $srv['price'];
        $total_price = $srv['total_price'];

        $stmt_item->bind_param("isdd", $billing_id, $srv_name, $unit_price, $total_price);
        $stmt_item->execute();
    }

    // Auto-create Journal Entry
    $stmt_journal = $conn->prepare("
        INSERT INTO journal_entries (entry_date, reference, status, created_by) 
        VALUES (NOW(), ?, 'Posted', 'System')
    ");
    $ref = "BILL-" . $billing_id;
    $stmt_journal->bind_param("s", $ref);
    $stmt_journal->execute();
    $entry_id = $stmt_journal->insert_id;

    // Debit Accounts Receivable
    $stmt_line = $conn->prepare("
        INSERT INTO journal_entry_lines (entry_id, account_name, debit, credit, description) 
        VALUES (?, 'Accounts Receivable', ?, 0, ?)
    ");
    $desc = "Patient #$patient_id Billing ID $billing_id";
    $stmt_line->bind_param("ids", $entry_id, $grand_total, $desc);
    $stmt_line->execute();

    // Credit Service Revenue
    $stmt_line = $conn->prepare("
        INSERT INTO journal_entry_lines (entry_id, account_name, debit, credit, description) 
        VALUES (?, 'Service Revenue', 0, ?, ?)
    ");
    $stmt_line->bind_param("ids", $entry_id, $grand_total, $desc);
    $stmt_line->execute();

    $conn->commit();
} catch (Exception $e) {
    $conn->rollback();
    error_log("Finalize billing error: " . $e->getMessage());
    die("Error finalizing billing.");
}

// Clear session cart
unset($_SESSION['billing_cart'][$patient_id]);
unset($_SESSION['is_pwd'][$patient_id]);

// Redirect to billing summary
header("Location: billing_summary.php?patient_id=$patient_id&billing_id=$billing_id");
exit;
?>
