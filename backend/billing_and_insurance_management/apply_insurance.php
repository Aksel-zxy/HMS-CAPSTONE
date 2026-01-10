<?php
include '../../SQL/config.php';
session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die("<script>alert('Invalid request.'); window.history.back();</script>");
}

$patient_id = intval($_POST['patient_id'] ?? 0);
$full_name = trim($_POST['full_name'] ?? '');
$insurance_number = trim($_POST['insurance_number'] ?? '');

if (!$patient_id || !$full_name || !$insurance_number) {
    die("<script>alert('All fields are required.'); window.history.back();</script>");
}

// 1. Find patient by ID
$stmt = $conn->prepare("SELECT CONCAT(fname, ' ', IFNULL(mname,''), ' ', lname) AS full_name FROM patientinfo WHERE patient_id = ?");
$stmt->bind_param("i", $patient_id);
$stmt->execute();
$patient = $stmt->get_result()->fetch_assoc();

if (!$patient) {
    die("<script>alert('Patient not found.'); window.history.back();</script>");
}

// 2. Validate entered insurance number
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

// 3. Fetch current total from billing_items
$stmt3 = $conn->prepare("
    SELECT SUM(total_price) AS total_price
    FROM billing_items
    WHERE patient_id = ?
");
$stmt3->bind_param("i", $patient_id);
$stmt3->execute();
$row = $stmt3->get_result()->fetch_assoc();
$totalPrice = floatval($row['total_price'] ?? 0);

if ($totalPrice <= 0) {
    die("<script>alert('No billing items found.'); window.history.back();</script>");
}

// 4. Calculate discount
$discount = 0;
if ($insurance['discount_type'] === 'Percentage') {
    $discount = ($insurance['discount_value'] / 100) * $totalPrice;
} else {
    $discount = min($insurance['discount_value'], $totalPrice);
}
$grand_total = $totalPrice - $discount;

// 5. Update patient_receipt (or billing record) with discount
$conn->begin_transaction();
try {
    // Update billing_records grand_total
    $stmt4 = $conn->prepare("
        UPDATE billing_records
        SET grand_total = ?, insurance_covered = ?
        WHERE patient_id = ?
        ORDER BY billing_id DESC
        LIMIT 1
    ");
    $stmt4->bind_param("ddi", $grand_total, $discount, $patient_id);
    $stmt4->execute();

    // Insert patient_receipt
    $txn = "TXN" . uniqid();
    $stmt5 = $conn->prepare("
        INSERT INTO patient_receipt 
        (patient_id, billing_id, total_charges, total_discount, total_out_of_pocket, grand_total, created_at, billing_date, insurance_covered, payment_method, status, transaction_id, is_pwd)
        SELECT patient_id, billing_id, total_amount, ?, total_amount-?, ?, NOW(), CURDATE(), ?, 'Unpaid', 'Pending', ?, 0
        FROM billing_records
        WHERE patient_id = ?
        ORDER BY billing_id DESC
        LIMIT 1
    ");
    $stmt5->bind_param("ddddsii", $discount, $discount, $grand_total, $discount, $txn, $patient_id);
    $stmt5->execute();

    $conn->commit();

    $_SESSION['insurance_applied'][$patient_id] = 1;

    echo "<script>alert('Insurance applied successfully!'); window.location='patient_billing.php';</script>";
    exit;

} catch (Exception $e) {
    $conn->rollback();
    die("<script>alert('Error applying insurance: {$e->getMessage()}'); window.history.back();</script>");
}
?>
