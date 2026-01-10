<?php
include '../../SQL/config.php';
session_start();

/* ===============================
   VALIDATE PATIENT
================================ */
$patient_id = intval($_GET['patient_id'] ?? 0);
if ($patient_id <= 0) {
    die("<script>alert('Invalid patient ID'); window.history.back();</script>");
}

$stmt = $conn->prepare("SELECT * FROM patientinfo WHERE patient_id = ?");
$stmt->bind_param("i", $patient_id);
$stmt->execute();
$patient = $stmt->get_result()->fetch_assoc();
if (!$patient) {
    die("<script>alert('Patient not found'); window.history.back();</script>");
}

/* ===============================
   CHECK BILLING CART
================================ */
if (empty($_SESSION['billing_cart'][$patient_id])) {
    die("<script>alert('No services to finalize'); window.history.back();</script>");
}
$cart = $_SESSION['billing_cart'][$patient_id];

/* ===============================
   COMPUTE AGE / DISCOUNTS
================================ */
$age = 0;
if (!empty($patient['dob']) && $patient['dob'] !== '0000-00-00') {
    $age = (new DateTime())->diff(new DateTime($patient['dob']))->y;
}

$is_pwd = $_SESSION['is_pwd'][$patient_id] ?? ($patient['is_pwd'] ?? 0);
$is_senior = $age >= 60;
$discount_rate = ($is_pwd || $is_senior) ? 0.20 : 0;

/* ===============================
   COMPUTE TOTALS
================================ */
$subtotal = 0;
foreach ($cart as $srv) {
    $subtotal += floatval($srv['price']);
}

$total_discount = round($subtotal * $discount_rate, 2);
$vat_amount = round(($subtotal - $total_discount) * 0.12, 2);
$grand_total = round(($subtotal - $total_discount) + $vat_amount, 2);

$total_out_of_pocket = $grand_total;
$txn = "TXN" . uniqid();

/* ===============================
   START TRANSACTION
================================ */
$conn->begin_transaction();

try {

    /* ===============================
       INSERT billing_records
    ================================ */
    $stmt = $conn->prepare("
        INSERT INTO billing_records
        (patient_id, billing_date, total_amount, insurance_covered, out_of_pocket, grand_total, status, payment_method, transaction_id)
        VALUES (?, NOW(), ?, 0, ?, ?, 'Pending', 'Unpaid', ?)
    ");
    $stmt->bind_param(
        "iddds",
        $patient_id,
        $grand_total,
        $total_out_of_pocket,
        $grand_total,
        $txn
    );
    $stmt->execute();
    $billing_id = $conn->insert_id;

    /* ===============================
       INSERT billing_items
    ================================ */
    $stmt_item = $conn->prepare("
        INSERT INTO billing_items
        (billing_id, patient_id, service_id, quantity, unit_price, total_price, finalized)
        VALUES (?, ?, ?, 1, ?, ?, 1)
    ");

    foreach ($cart as $srv) {
        $stmt_item->bind_param(
            "iiidd",
            $billing_id,
            $patient_id,
            $srv['serviceID'],
            $srv['price'],
            $srv['price'] // FULL PRICE — discount handled globally
        );
        $stmt_item->execute();
    }

    /* ===============================
       INSERT patient_receipt
       (NO receipt_id column!)
    ================================ */
    $stmt_receipt = $conn->prepare("
        INSERT INTO patient_receipt
        (patient_id, billing_id, total_charges, total_vat, total_discount,
         total_out_of_pocket, grand_total, billing_date,
         insurance_covered, payment_method, status, transaction_id, is_pwd)
        VALUES (?, ?, ?, ?, ?, ?, ?, CURDATE(), 0, 'Unpaid', 'Pending', ?, ?)
    ");

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
    $stmt_receipt->execute();

    /* ===============================
       COMMIT
    ================================ */
    $conn->commit();

    unset($_SESSION['billing_cart'][$patient_id], $_SESSION['is_pwd'][$patient_id]);

    header("Location: billing_items.php?success=1");
    exit;

} catch (Exception $e) {
    $conn->rollback();
    die("<script>alert('Finalize failed: {$e->getMessage()}'); window.history.back();</script>");
}
