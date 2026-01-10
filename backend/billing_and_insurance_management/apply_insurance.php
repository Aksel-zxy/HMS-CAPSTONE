<?php
include '../../SQL/config.php';
if (session_status() === PHP_SESSION_NONE) session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die("<script>alert('Invalid request.'); window.history.back();</script>");
}

$patient_id = intval($_POST['patient_id'] ?? 0);
$full_name = trim($_POST['full_name'] ?? '');
$insurance_number = trim($_POST['insurance_number'] ?? '');

if (!$patient_id || !$full_name || !$insurance_number) {
    die("<script>alert('All fields are required.'); window.history.back();</script>");
}

/* ----------------------------------------------------
   1️⃣ Verify patient
---------------------------------------------------- */
$stmt = $conn->prepare("
    SELECT CONCAT(fname,' ',IFNULL(mname,''),' ',lname) AS full_name
    FROM patientinfo
    WHERE patient_id = ?
");
$stmt->bind_param("i", $patient_id);
$stmt->execute();
$patient = $stmt->get_result()->fetch_assoc();

if (!$patient) {
    die("<script>alert('Patient not found.'); window.history.back();</script>");
}

/* ----------------------------------------------------
   2️⃣ Verify insurance (AI-like validation)
---------------------------------------------------- */
$stmt2 = $conn->prepare("
    SELECT insurance_company, promo_name, discount_type, discount_value
    FROM patient_insurance
    WHERE insurance_number = ?
      AND full_name = ?
      AND status = 'Active'
    LIMIT 1
");
$stmt2->bind_param("ss", $insurance_number, $full_name);
$stmt2->execute();
$insurance = $stmt2->get_result()->fetch_assoc();

if (!$insurance) {
    die("<script>alert('Invalid insurance number or patient name mismatch.'); window.history.back();</script>");
}

/* ----------------------------------------------------
   3️⃣ Get latest billing record
---------------------------------------------------- */
$stmt3 = $conn->prepare("
    SELECT billing_id, total_amount
    FROM billing_records
    WHERE patient_id = ?
    ORDER BY billing_id DESC
    LIMIT 1
");
$stmt3->bind_param("i", $patient_id);
$stmt3->execute();
$billing = $stmt3->get_result()->fetch_assoc();

if (!$billing) {
    die("<script>alert('No billing record found.'); window.history.back();</script>");
}

$billing_id  = (int)$billing['billing_id'];
$totalAmount = (float)$billing['total_amount'];

/* ----------------------------------------------------
   4️⃣ AI-style insurance deduction calculation
---------------------------------------------------- */
if ($insurance['discount_type'] === 'Percentage') {
    $insurance_covered = ($insurance['discount_value'] / 100) * $totalAmount;
} else {
    $insurance_covered = min($insurance['discount_value'], $totalAmount);
}

$grand_total = max(0, $totalAmount - $insurance_covered);

/* ----------------------------------------------------
   5️⃣ Update billing_records + insert receipt
---------------------------------------------------- */
$conn->begin_transaction();

try {

    // ✅ Update billing_records
    $stmt4 = $conn->prepare("
        UPDATE billing_records
        SET insurance_covered = ?, grand_total = ?
        WHERE billing_id = ? AND patient_id = ?
    ");
    $stmt4->bind_param("ddii", $insurance_covered, $grand_total, $billing_id, $patient_id);
    $stmt4->execute();

    // ✅ Insert patient_receipt
    $txn = "TXN" . uniqid();
    $payment_method = $insurance['promo_name'] ?? 'Insurance';
    $total_out_of_pocket = $grand_total;

    $stmt5 = $conn->prepare("
        INSERT INTO patient_receipt
        (patient_id, billing_id, total_charges, total_discount, total_out_of_pocket,
         grand_total, billing_date, insurance_covered, payment_method, status,
         transaction_id, is_pwd)
        VALUES (?, ?, ?, ?, ?, ?, CURDATE(), ?, ?, 'Pending', ?, 0)
    ");

    // 🔥 EXACT MATCH — 9 TYPES, 9 VALUES
    $stmt5->bind_param(
        "iidddddss",
        $patient_id,
        $billing_id,
        $totalAmount,
        $insurance_covered,
        $total_out_of_pocket,
        $grand_total,
        $insurance_covered,
        $payment_method,
        $txn
    );

    $stmt5->execute();

    $conn->commit();

    $_SESSION['insurance_applied'][$patient_id] = true;

    echo "<script>
        alert('Insurance applied successfully!');
        window.location='patient_billing.php';
    </script>";
    exit;

} catch (Exception $e) {
    $conn->rollback();
    die("<script>alert('Error: {$e->getMessage()}'); window.history.back();</script>");
}
