<?php
session_start();
require_once 'db.php';

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

// Compute totals
$subtotal = array_sum(array_column($cart, 'price'));
$discount = ($is_pwd && $age < 60) ? $subtotal * 0.20 : 0;
$grand_total = $subtotal - $discount;
$total_out_of_pocket = $grand_total;

// Begin transaction
$conn->begin_transaction();
try {
    // Generate a new billing_id
    $res = $conn->query("SELECT IFNULL(MAX(billing_id),0)+1 AS new_id FROM billing_items");
    $billing_id = $res->fetch_assoc()['new_id'];

    // Save each service in billing_items
    $stmt = $conn->prepare("
        INSERT INTO billing_items 
        (patient_id, service_id, service_name, discount, created_at, finalized, total_price, billing_id)
        VALUES (?,?,?,?, NOW(), 1, ?, ?)
    ");
    foreach ($cart as $srv) {
        $srv_id = $srv['serviceID'];
        $srv_name = $srv['serviceName'];
        $srv_discount = ($is_pwd && $age < 60) ? ($srv['price'] * 0.20) : 0;
        $total_price = $srv['price'] - $srv_discount;

        $stmt->bind_param("iisddi", $patient_id, $srv_id, $srv_name, $srv_discount, $total_price, $billing_id);
        $stmt->execute();
    }

    // Save summary into patient_receipt
    $stmt = $conn->prepare("
        INSERT INTO patient_receipt
        (patient_id, billing_id, total_charges, total_discount, total_out_of_pocket, grand_total, billing_date, payment_method, status, transaction_id, payment_reference, is_pwd)
        VALUES (?,?,?,?,?,?, CURDATE(), ?, ?, ?, ?, ?)
    ");

    $payment_method = "Unpaid";
    $status = "Pending";
    $txn = "TXN" . uniqid();
    $pay_ref = "Not Paid Yet";

    // Bind parameters correctly
    $stmt->bind_param(
        "iiddddssssi",
        $patient_id,
        $billing_id,
        $subtotal,
        $discount,
        $total_out_of_pocket,
        $grand_total,
        $payment_method,
        $status,
        $txn,
        $pay_ref,
        $is_pwd
    );
    $stmt->execute();

    // Auto-create Journal Entry
    $stmt = $conn->prepare("INSERT INTO journal_entries (entry_date, reference, status, created_by) VALUES (NOW(), ?, 'Posted', 'System')");
    $ref = "BILL-" . $billing_id;
    $stmt->bind_param("s", $ref);
    $stmt->execute();
    $entry_id = $stmt->insert_id;

    // Debit Accounts Receivable
    $stmt = $conn->prepare("INSERT INTO journal_entry_lines (entry_id, account_name, debit, credit, description) VALUES (?, 'Accounts Receivable', ?, 0, ?)");
    $desc = "Patient #$patient_id Billing ID $billing_id";
    $stmt->bind_param("ids", $entry_id, $grand_total, $desc);
    $stmt->execute();

    // Credit Service Revenue
    $stmt = $conn->prepare("INSERT INTO journal_entry_lines (entry_id, account_name, debit, credit, description) VALUES (?, 'Service Revenue', 0, ?, ?)");
    $stmt->bind_param("ids", $entry_id, $grand_total, $desc);
    $stmt->execute();

    // Commit transaction
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
