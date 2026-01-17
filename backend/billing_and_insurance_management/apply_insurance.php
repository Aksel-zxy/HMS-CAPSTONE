<?php
include '../../SQL/config.php';
session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die("<script>alert('Invalid request'); window.history.back();</script>");
}

/* ===============================
   INPUT VALIDATION
================================ */
$patient_id = intval($_POST['patient_id'] ?? 0);
$full_name = trim($_POST['full_name'] ?? '');
$insurance_number = trim($_POST['insurance_number'] ?? '');

if (!$patient_id || !$full_name || !$insurance_number) {
    die("<script>alert('All fields are required'); window.history.back();</script>");
}

/* ===============================
   VERIFY PATIENT
================================ */
$stmt = $conn->prepare("
    SELECT CONCAT(fname,' ',IFNULL(mname,''),' ',lname) AS fullname
    FROM patientinfo WHERE patient_id=?
");
$stmt->bind_param("i", $patient_id);
$stmt->execute();
$patient = $stmt->get_result()->fetch_assoc();

if (!$patient) {
    die("<script>alert('Patient not found'); window.history.back();</script>");
}

/* ===============================
   VERIFY INSURANCE
================================ */
$stmt = $conn->prepare("
    SELECT insurance_company, promo_name, discount_type, discount_value
    FROM patient_insurance
    WHERE insurance_number=? AND full_name=? AND status='Active'
    LIMIT 1
");
$stmt->bind_param("ss", $insurance_number, $full_name);
$stmt->execute();
$insurance = $stmt->get_result()->fetch_assoc();

if (!$insurance) {
    die("<script>alert('Invalid insurance or name mismatch'); window.history.back();</script>");
}

/* ===============================
   GET LATEST UNPAID BILLING
================================ */
$stmt = $conn->prepare("
    SELECT billing_id, total_amount
    FROM billing_records
    WHERE patient_id=? AND status='Pending'
    ORDER BY billing_id DESC
    LIMIT 1
");
$stmt->bind_param("i", $patient_id);
$stmt->execute();
$billing = $stmt->get_result()->fetch_assoc();

if (!$billing) {
    die("<script>alert('No pending billing found'); window.history.back();</script>");
}

$billing_id = (int)$billing['billing_id'];
$total_amount = (float)$billing['total_amount'];

/* ===============================
   CALCULATE INSURANCE
================================ */
if ($insurance['discount_type'] === 'Percentage') {
    $insurance_covered = round(($insurance['discount_value'] / 100) * $total_amount, 2);
} else {
    $insurance_covered = round(min($insurance['discount_value'], $total_amount), 2);
}

$out_of_pocket = round($total_amount - $insurance_covered, 2);
if ($out_of_pocket < 0) $out_of_pocket = 0;

/* ===============================
   APPLY INSURANCE
================================ */
$conn->begin_transaction();

try {

    /* ---- UPDATE billing_records ---- */
    $stmt = $conn->prepare("
        UPDATE billing_records
        SET insurance_covered=?, out_of_pocket=?, grand_total=?
        WHERE billing_id=? AND patient_id=?
    ");
    $stmt->bind_param(
        "dddii",
        $insurance_covered,
        $out_of_pocket,
        $out_of_pocket,
        $billing_id,
        $patient_id
    );
    $stmt->execute();

    /* ---- UPDATE patient_receipt (NOT INSERT) ---- */
    $stmt = $conn->prepare("
        UPDATE patient_receipt
        SET insurance_covered=?,
            total_out_of_pocket=?,
            grand_total=?,
            payment_method=?,
            status='Pending'
        WHERE billing_id=? AND patient_id=?
    ");
    $payment_method = $insurance['promo_name'];
    $stmt->bind_param(
        "dddsii",
        $insurance_covered,
        $out_of_pocket,
        $out_of_pocket,
        $payment_method,
        $billing_id,
        $patient_id
    );
    $stmt->execute();

    $conn->commit();

    $_SESSION['insurance_applied'][$patient_id] = true;

    echo "<script>
        alert('Insurance applied successfully!');
        window.location='../billing_records/billing_summary.php';
    </script>";
    exit;

} catch (Exception $e) {
    $conn->rollback();
    die("<script>alert('Insurance application failed: {$e->getMessage()}'); window.history.back();</script>");
}
