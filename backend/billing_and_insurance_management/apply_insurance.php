<?php
include '../../SQL/config.php';
session_start();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status'=>'error','message'=>'Invalid request']);
    exit;
}

$action           = $_POST['action'] ?? '';
$patient_id       = (int)($_POST['patient_id'] ?? 0);
$billing_id       = (int)($_POST['billing_id'] ?? 0);
$insurance_number = trim($_POST['insurance_number'] ?? '');

if ($patient_id <= 0 || $billing_id <= 0 || $insurance_number === '') {
    echo json_encode(['status'=>'error','message'=>'Missing required fields']);
    exit;
}

/* ================= GET PATIENT ================= */
$stmt = $conn->prepare("
    SELECT CONCAT(fname,' ',IFNULL(mname,''),' ',lname) AS full_name
    FROM patientinfo WHERE patient_id=?
");
$stmt->bind_param("i", $patient_id);
$stmt->execute();
$patient = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$patient) {
    echo json_encode(['status'=>'error','message'=>'Patient not found']);
    exit;
}

$full_name = trim($patient['full_name']);

/* ================= VERIFY INSURANCE ================= */
$stmt = $conn->prepare("
    SELECT insurance_company, promo_name, discount_type, discount_value
    FROM patient_insurance
    WHERE insurance_number=? AND full_name=? AND status='Active'
    LIMIT 1
");
$stmt->bind_param("ss", $insurance_number, $full_name);
$stmt->execute();
$insurance = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$insurance) {
    echo json_encode(['status'=>'error','message'=>'Invalid or inactive insurance']);
    exit;
}

/* ================= GET BILLING ================= */
$stmt = $conn->prepare("
    SELECT total_amount
    FROM billing_records
    WHERE billing_id=? AND patient_id=? AND status='Pending'
");
$stmt->bind_param("ii", $billing_id, $patient_id);
$stmt->execute();
$billing = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$billing) {
    echo json_encode(['status'=>'error','message'=>'No pending billing found']);
    exit;
}

$total = (float)$billing['total_amount'];

/* ================= CALCULATE ================= */
if ($insurance['discount_type'] === 'Percentage') {
    $covered = round(($insurance['discount_value'] / 100) * $total, 2);
    $discountLabel = $insurance['discount_value'] . '%';
} else {
    $covered = round(min($insurance['discount_value'], $total), 2);
    $discountLabel = '₱' . number_format($insurance['discount_value'], 2);
}

$out_of_pocket = max(0, round($total - $covered, 2));

/* ================= PREVIEW MODE ================= */
if ($action === 'preview') {
    echo json_encode([
        'status' => 'preview',
        'insurance_company' => $insurance['insurance_company'],
        'promo_name' => $insurance['promo_name'],
        'discount_label' => $discountLabel,
        'insurance_covered' => number_format($covered,2),
        'out_of_pocket' => number_format($out_of_pocket,2)
    ]);
    exit;
}

/* ================= APPLY MODE ================= */
$conn->begin_transaction();

try {
    $stmt = $conn->prepare("
        UPDATE billing_records
        SET insurance_covered=?, out_of_pocket=?, grand_total=?
        WHERE billing_id=? AND patient_id=?
    ");
    $stmt->bind_param("dddii", $covered, $out_of_pocket, $out_of_pocket, $billing_id, $patient_id);
    $stmt->execute();

    $stmt = $conn->prepare("
        UPDATE patient_receipt
        SET insurance_covered=?, total_out_of_pocket=?, grand_total=?,
            payment_method=?, status='Pending'
        WHERE billing_id=? AND patient_id=?
    ");
    $stmt->bind_param(
        "dddsii",
        $covered,
        $out_of_pocket,
        $out_of_pocket,
        $insurance['promo_name'],
        $billing_id,
        $patient_id
    );
    $stmt->execute();

    $conn->commit();

    echo json_encode([
        'status'=>'success',
        'message'=>'Insurance applied successfully'
    ]);
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['status'=>'error','message'=>'Failed to apply insurance']);
}
