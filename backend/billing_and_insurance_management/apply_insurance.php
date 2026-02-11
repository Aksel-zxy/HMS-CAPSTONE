<?php
include '../../SQL/config.php';
if (session_status() === PHP_SESSION_NONE) session_start();

/* =====================================================
   HELPERS
===================================================== */
function cardColor($company) {
    return match ($company) {
        'PhilHealth'   => 'linear-gradient(135deg, #1e3c72, #2a5298)',
        'Maxicare'     => 'linear-gradient(135deg, #0f9b8e, #38ef7d)',
        'Medicard'     => 'linear-gradient(135deg, #8e2de2, #4a00e0)',
        'Intellicare'  => 'linear-gradient(135deg, #f7971e, #ffd200)',
        default        => 'linear-gradient(135deg, #232526, #414345)',
    };
}

function normalizeName($name) {
    return preg_replace('/\s+/', ' ', strtolower(trim($name)));
}

function renderInsuranceCard($insurance_number, $full_name, $insurance) {
    $bg = cardColor($insurance['insurance_company']);
    $discount = $insurance['discount_type'] === 'Percentage'
        ? $insurance['discount_value'] . '%'
        : '₱' . number_format($insurance['discount_value'], 2);

    return "
    <div class='insurance-card' style='background:{$bg}'>
        <div class='card-header'>{$insurance['insurance_company']}</div>
        <div class='chip'></div>
        <div class='insurance-number'>{$insurance_number}</div>
        <div class='patient-name'>{$full_name}</div>
        <small>{$insurance['promo_name']}</small>
        <div class='card-footer'>
            <div><strong>Discount</strong><br>{$discount}</div>
            <div><strong>Relation</strong><br>{$insurance['relationship_to_insured']}</div>
        </div>
    </div>";
}

/* =====================================================
   AJAX ONLY
===================================================== */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(403);
    exit;
}

header('Content-Type: application/json');

$action           = $_POST['action'] ?? '';
$patient_id       = (int)($_POST['patient_id'] ?? 0);
$billing_id       = (int)($_POST['billing_id'] ?? 0);
$insurance_number = trim($_POST['insurance_number'] ?? '');

if (!$action || !$patient_id || !$billing_id || !$insurance_number) {
    echo json_encode(['status'=>'error','message'=>'Missing required data']);
    exit;
}

/* =====================================================
   FETCH PATIENT
===================================================== */
$stmt = $conn->prepare("SELECT fname, mname, lname FROM patientinfo WHERE patient_id=?");
$stmt->bind_param("i", $patient_id);
$stmt->execute();
$patient = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$patient) {
    echo json_encode(['status'=>'error','message'=>'Patient not found']);
    exit;
}

$patient_full_name = normalizeName(
    $patient['fname'].' '.
    ($patient['mname'] ?? '').' '.
    $patient['lname']
);

/* =====================================================
   FETCH INSURANCE BY NUMBER
===================================================== */
$stmt = $conn->prepare("
    SELECT insurance_company, promo_name, discount_type, discount_value,
           relationship_to_insured, full_name
    FROM patient_insurance
    WHERE insurance_number = ?
      AND status = 'Active'
    LIMIT 1
");
$stmt->bind_param("s", $insurance_number);
$stmt->execute();
$insurance = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$insurance) {
    echo json_encode(['status'=>'error','message'=>'Invalid insurance card number']);
    exit;
}

/* =====================================================
   NAME VALIDATION (FIXED)
===================================================== */
$insurance_name = normalizeName($insurance['full_name']);

if ($patient_full_name !== $insurance_name) {
    echo json_encode([
        'status'  => 'error',
        'message' => 'Insurance card does not belong to this patient'
    ]);
    exit;
}

/* =====================================================
   FETCH BILL TOTAL
===================================================== */
$stmt = $conn->prepare("
    SELECT SUM(total_price) AS total
    FROM billing_items
    WHERE billing_id=? AND patient_id=? AND finalized=1
");
$stmt->bind_param("ii", $billing_id, $patient_id);
$stmt->execute();
$bill = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$bill || !$bill['total']) {
    echo json_encode(['status'=>'error','message'=>'Billing not found']);
    exit;
}

$total = (float)$bill['total'];

/* =====================================================
   CALCULATE DISCOUNT
===================================================== */
if ($insurance['discount_type'] === 'Percentage') {
    $covered = round(($insurance['discount_value'] / 100) * $total, 2);
} else {
    $covered = round(min($insurance['discount_value'], $total), 2);
}

$out_of_pocket = round(max(0, $total - $covered), 2);
$grand_total   = $out_of_pocket;

/* =====================================================
   PREVIEW
===================================================== */
if ($action === 'preview') {
    echo json_encode([
        'status' => 'preview',
        'insurance_card_html' => renderInsuranceCard(
            $insurance_number,
            ucwords($insurance['full_name']),
            $insurance
        ),
        'insurance_covered' => number_format($covered, 2),
        'out_of_pocket'     => number_format($out_of_pocket, 2)
    ]);
    exit;
}

/* =====================================================
   APPLY INSURANCE
===================================================== */
if ($action === 'apply') {
    $conn->begin_transaction();
    try {
        $stmt = $conn->prepare("
            UPDATE patient_receipt
            SET insurance_covered=?,
                total_out_of_pocket=?,
                grand_total=?,
                payment_method=?
            WHERE billing_id=? AND patient_id=?
        ");
        $stmt->bind_param(
            "dddsii",
            $covered,
            $out_of_pocket,
            $grand_total,
            $insurance['promo_name'],
            $billing_id,
            $patient_id
        );
        $stmt->execute();
        $stmt->close();

        $conn->commit();

        echo json_encode([
            'status'=>'success',
            'message'=>'Insurance applied successfully',
            'insurance_covered'=>number_format($covered,2),
            'out_of_pocket'=>number_format($out_of_pocket,2),
            'grand_total'=>number_format($grand_total,2)
        ]);
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['status'=>'error','message'=>'Failed to apply insurance']);
    }
    exit;
}
