<?php
include '../../SQL/config.php';
session_start();

// Get patient ID
$patient_id = isset($_GET['patient_id']) ? intval($_GET['patient_id']) : 0;
if ($patient_id <= 0) die("<script>alert('Invalid patient ID.'); window.history.back();</script>");

// Fetch patient info
$stmt = $conn->prepare("SELECT * FROM patientinfo WHERE patient_id = ?");
$stmt->bind_param("i", $patient_id);
$stmt->execute();
$patient = $stmt->get_result()->fetch_assoc();
if (!$patient) die("<script>alert('Patient not found.'); window.history.back();</script>");

// Check billing cart
if (!isset($_SESSION['billing_cart'][$patient_id]) || empty($_SESSION['billing_cart'][$patient_id])) {
    die("<script>alert('No services to finalize.'); window.history.back();</script>");
}
$cart = $_SESSION['billing_cart'][$patient_id];

// Compute age and discount eligibility
$dob = $patient['dob'];
$age = 0;
if (!empty($dob) && $dob != '0000-00-00') {
    $birth = new DateTime($dob);
    $today = new DateTime();
    $age = $today->diff($birth)->y;
}
$is_pwd = $patient['is_pwd'] ?? 0;
$is_senior = $age >= 60 ? 1 : 0;

// VAT percentage
$vat_rate = 0.12;

// Compute totals
$subtotal = 0;
$total_discount = 0;
foreach ($cart as $srv) {
    $price = round(floatval($srv['price']), 2);
    $discount = ($is_pwd || $is_senior) ? round($price * 0.20, 2) : 0;
    $subtotal += $price;
    $total_discount += $discount;
}

$vat_amount = round(($subtotal - $total_discount) * $vat_rate, 2);
$grand_total = round(($subtotal - $total_discount) + $vat_amount, 2);
$total_out_of_pocket = $grand_total;

// Start transaction
$conn->begin_transaction();

try {
    $txn = "TXN" . uniqid();

    // -------------------------------
    // Insert billing_records
    // -------------------------------
    $stmt = $conn->prepare("
        INSERT INTO billing_records 
        (patient_id, billing_date, total_amount, insurance_covered, out_of_pocket, grand_total, status, payment_method, transaction_id)
        VALUES (?, NOW(), ?, 0, ?, ?, 'Pending', 'Unpaid', ?)
    ");
    if (!$stmt) throw new Exception($conn->error);
    $stmt->bind_param("iddds", $patient_id, $grand_total, $total_out_of_pocket, $grand_total, $txn);
    if (!$stmt->execute()) throw new Exception($stmt->error);
    $billing_id = $stmt->insert_id; // AUTO_INCREMENT now works

    // -------------------------------
    // Insert billing_items
    // -------------------------------
    $stmt_item = $conn->prepare("
        INSERT INTO billing_items 
        (billing_id, patient_id, service_id, quantity, unit_price, total_price, finalized)
        VALUES (?, ?, ?, 1, ?, ?, 1)
    ");
    if (!$stmt_item) throw new Exception($conn->error);

    foreach ($cart as $srv) {
        $srv_id = intval($srv['serviceID']);
        $price = round(floatval($srv['price']), 2);
        $discount = ($is_pwd || $is_senior) ? round($price * 0.20, 2) : 0;
        $total_price = round($price - $discount, 2);
        $stmt_item->bind_param("iiidd", $billing_id, $patient_id, $srv_id, $price, $total_price);
        if (!$stmt_item->execute()) throw new Exception($stmt_item->error);
    }

    // -------------------------------
    // Insert patient_receipt
    // -------------------------------
    $stmt_receipt = $conn->prepare("
        INSERT INTO patient_receipt
        (patient_id, billing_id, total_charges, total_vat, total_discount, total_out_of_pocket, grand_total, created_at, billing_date, insurance_covered, payment_method, status, transaction_id, is_pwd)
        VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), CURDATE(), 0, 'Unpaid', 'Pending', ?, ?)
    ");
    if (!$stmt_receipt) throw new Exception($conn->error);
    $stmt_receipt->bind_param(
        "iidddddsi",
        $patient_id,
        $billing_id,
        $subtotal,
        $vat_amount,
        $total_discount,
        $total_out_of_pocket,
        $grand_total,
        $txn,
        $is_pwd
    );
    if (!$stmt_receipt->execute()) throw new Exception($stmt_receipt->error);

    // Commit transaction
    $conn->commit();

    // Clear session
    unset($_SESSION['billing_cart'][$patient_id]);
    unset($_SESSION['is_pwd'][$patient_id]);

    // Redirect with success
    header("Location: billing_items.php?patient_id=$patient_id&success=1&total=" . urlencode($grand_total));
    exit;

} catch (Exception $e) {
    $conn->rollback();
    error_log('Finalize billing error: ' . $e->getMessage());
    echo "<script>alert('Error finalizing billing: {$e->getMessage()}'); window.history.back();</script>";
    exit;
}
?>
