<?php
session_start();
include '../../SQL/config.php';

// ✅ Get patient ID
$patient_id = isset($_GET['patient_id']) ? intval($_GET['patient_id']) : 0;
if ($patient_id <= 0) {
    die("<script>alert('Invalid patient ID.'); window.history.back();</script>");
}

// ✅ Fetch patient info
$stmt = $conn->prepare("SELECT * FROM patientinfo WHERE patient_id = ?");
$stmt->bind_param("i", $patient_id);
$stmt->execute();
$patient = $stmt->get_result()->fetch_assoc();
if (!$patient) {
    die("<script>alert('Patient not found.'); window.history.back();</script>");
}

// ✅ Check if there are items in billing cart
if (!isset($_SESSION['billing_cart'][$patient_id]) || empty($_SESSION['billing_cart'][$patient_id])) {
    die("<script>alert('No services to finalize.'); window.history.back();</script>");
}
$cart = $_SESSION['billing_cart'][$patient_id];

// ✅ Compute age and discounts
$dob = $patient['dob'] ?? null;
$age = 0;
if (!empty($dob) && $dob != '0000-00-00') {
    $birth = new DateTime($dob);
    $today = new DateTime();
    $age = $today->diff($birth)->y;
}

$is_pwd = $patient['is_pwd'] ?? 0;
$is_senior = ($age >= 60) ? 1 : 0;
$vat_rate = 0.12; // 12%

// ✅ Compute totals
$subtotal = 0;
$total_discount = 0;
foreach ($cart as $srv) {
    $price = floatval($srv['price']);
    $discount = 0;
    if ($is_pwd || $is_senior) {
        $discount = $price * 0.20; // 20% discount
    }
    $subtotal += $price;
    $total_discount += $discount;
}

$vat_amount = ($subtotal - $total_discount) * $vat_rate;
$grand_total = ($subtotal - $total_discount) + $vat_amount;
$total_out_of_pocket = $grand_total;

// ✅ Begin transaction
$conn->begin_transaction();
try {
    // 🔹 Insert billing record
    $txn = "TXN" . uniqid();
    $stmt_billing = $conn->prepare("
        INSERT INTO billing_records 
        (patient_id, billing_date, total_amount, insurance_covered, out_of_pocket, status, payment_method, transaction_id)
        VALUES (?, NOW(), ?, 0, ?, 'Pending', 'Unpaid', ?)
    ");
    if (!$stmt_billing) throw new Exception($conn->error);

    $stmt_billing->bind_param("idds", $patient_id, $grand_total, $total_out_of_pocket, $txn);
    $stmt_billing->execute();
    $billing_id = $stmt_billing->insert_id;

    // 🔹 Insert billing items
    $stmt_item = $conn->prepare("
        INSERT INTO billing_items 
        (billing_id, patient_id, service_id, service_name, unit_price, total_price, finalized, created_at)
        VALUES (?, ?, ?, ?, ?, ?, 1, NOW())
    ");
    if (!$stmt_item) throw new Exception($conn->error);

    foreach ($cart as $srv) {
        $srv_id = intval($srv['serviceID']);
        $srv_name = $srv['service_name'] ?? 'Service';
        $price = floatval($srv['price']);
        $discount = ($is_pwd || $is_senior) ? ($price * 0.20) : 0;
        $total_price = $price - $discount;

        $stmt_item->bind_param("iiisdd", $billing_id, $patient_id, $srv_id, $srv_name, $price, $total_price);
        $stmt_item->execute();
    }

    // 🔹 Insert into patient_receipt
    $stmt_receipt = $conn->prepare("
        INSERT INTO patient_receipt
        (patient_id, billing_id, total_charges, total_vat, total_discount, total_out_of_pocket, grand_total, 
         created_at, billing_date, insurance_covered, payment_method, status, transaction_id, payment_reference, is_pwd)
        VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW(), 0, 'Unpaid', 'Pending', ?, 'Not Paid Yet', ?)
    ");
    if (!$stmt_receipt) throw new Exception($conn->error);

    $stmt_receipt->bind_param(
        "iidddds i",
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

    // Fix for extra space in "iidddds i"
    $stmt_receipt->bind_param(
        "iidddds i",
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

    $stmt_receipt = $conn->prepare("
        INSERT INTO patient_receipt
        (patient_id, billing_id, total_charges, total_vat, total_discount, total_out_of_pocket, grand_total, created_at, billing_date, insurance_covered, payment_method, status, transaction_id, payment_reference, is_pwd)
        VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW(), 0, 'Unpaid', 'Pending', ?, 'Not Paid Yet', ?)
    ");
    $stmt_receipt->bind_param("iidddds i", $patient_id, $billing_id, $subtotal, $vat_amount, $total_discount, $total_out_of_pocket, $grand_total, $txn, $is_pwd);
    $stmt_receipt->execute();

    // ✅ Commit transaction
    $conn->commit();

    // ✅ Clear session
    unset($_SESSION['billing_cart'][$patient_id]);
    unset($_SESSION['is_pwd'][$patient_id]);

    // ✅ Success message
    echo "
    <script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>
    <script>
        Swal.fire({
            icon: 'success',
            title: 'Billing Finalized!',
            html: 'Billing and patient receipt created successfully.<br><b>Grand Total: ₱ " . number_format($grand_total, 2) . "</b>',
            confirmButtonColor: '#198754',
            confirmButtonText: 'OK'
        }).then(() => {
            window.location.href = document.referrer || 'patient_billing.php';
        });
    </script>
    ";

} catch (Exception $e) {
    $conn->rollback();
    error_log('Finalize billing error: ' . $e->getMessage());
    echo "
    <script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>
    <script>
        Swal.fire({
            icon: 'error',
            title: 'Error!',
            text: 'An error occurred while finalizing billing: " . addslashes($e->getMessage()) . "',
            confirmButtonColor: '#dc3545'
        }).then(() => {
            window.history.back();
        });
    </script>
    ";
}
?>
