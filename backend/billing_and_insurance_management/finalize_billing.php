<?php
include '../../SQL/config.php';
<<<<<<< HEAD
session_start(); // ✅ REQUIRED
=======
>>>>>>> 3c4e68ff8af17af976bbd01deabe78d07d942029

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

// Begin transaction
$conn->begin_transaction();
try {
    // ✅ Create billing record with grand_total
    $stmt = $conn->prepare("
        INSERT INTO billing_records 
        (patient_id, billing_date, total_amount, insurance_covered, out_of_pocket, grand_total, status, payment_method, transaction_id)
        VALUES (?, NOW(), ?, 0, ?, ?, 'Pending', 'Unpaid', ?)
    ");
    $txn = "TXN" . uniqid();
    $stmt->bind_param("iddds", $patient_id, $grand_total, $total_out_of_pocket, $grand_total, $txn);
    $stmt->execute();
    $billing_id = $stmt->insert_id;

    // ✅ Insert billing items
    $stmt_item = $conn->prepare("
        INSERT INTO billing_items 
        (billing_id, patient_id, service_id, quantity, unit_price, total_price, finalized)
        VALUES (?, ?, ?, 1, ?, ?, 1)
    ");
    foreach ($cart as $srv) {
        $srv_id = intval($srv['serviceID']);
        $price = floatval($srv['price']);
        $discount = ($is_pwd || $is_senior) ? ($price * 0.20) : 0;
        $total_price = $price - $discount;
        $stmt_item->bind_param("iiidd", $billing_id, $patient_id, $srv_id, $price, $total_price);
        $stmt_item->execute();
    }

    // ✅ Insert patient receipt
    $stmt_receipt = $conn->prepare("
        INSERT INTO patient_receipt
        (patient_id, billing_id, total_charges, total_vat, total_discount, total_out_of_pocket, grand_total, created_at, billing_date, insurance_covered, payment_method, status, transaction_id, is_pwd)
        VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW(), 0, 'Pending', 'Unpaid', ?, ?)
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

    // Commit
    $conn->commit();

    // Clear cart
    unset($_SESSION['billing_cart'][$patient_id]);
    unset($_SESSION['is_pwd'][$patient_id]);

    echo "
    <script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>
    <script>
        Swal.fire({
            icon: 'success',
            title: 'Billing Finalized!',
            html: 'Billing has been finalized successfully.<br>Grand Total: ₱ " . number_format($grand_total,2) . "',
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
            title: 'Error',
            text: 'An error occurred while finalizing billing. Please try again.',
            confirmButtonColor: '#dc3545'
        }).then(() => {
            window.history.back();
        });
    </script>
    ";
}
?>
