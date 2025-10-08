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

// Compute totals
$subtotal = 0;
$total_discount = 0;
foreach ($cart as $srv) {
    $price = floatval($srv['price']);
    $subtotal += $price;
    if ($is_pwd && $age < 60) {
        $total_discount += $price * 0.20;
    }
}
$grand_total = $subtotal - $total_discount;
$total_out_of_pocket = $grand_total;

// Begin transaction
$conn->begin_transaction();
try {
    // Create billing record
    $stmt = $conn->prepare("
        INSERT INTO billing_records 
        (patient_id, billing_date, total_amount, insurance_covered, out_of_pocket, status, payment_method, transaction_id)
        VALUES (?, NOW(), ?, 0, ?, 'Pending', 'Unpaid', ?)
    ");
    $txn = "TXN" . uniqid();
    $stmt->bind_param("idds", $patient_id, $grand_total, $total_out_of_pocket, $txn);
    $stmt->execute();
    $billing_id = $stmt->insert_id;

    // Insert billing items
    $stmt = $conn->prepare("
        INSERT INTO billing_items 
        (billing_id, patient_id, service_id, quantity, unit_price, total_price, finalized)
        VALUES (?, ?, ?, 1, ?, ?, 1)
    ");
    foreach ($cart as $srv) {
        $srv_id = intval($srv['serviceID']);
        $price = floatval($srv['price']);
        $discount = ($is_pwd && $age < 60) ? ($price * 0.20) : 0;
        $total_price = $price - $discount;
        $stmt->bind_param("iiidd", $billing_id, $patient_id, $srv_id, $price, $total_price);
        $stmt->execute();
    }

    // Commit transaction
    $conn->commit();

    // Clear cart session
    unset($_SESSION['billing_cart'][$patient_id]);
    unset($_SESSION['is_pwd'][$patient_id]);

    // ✅ Success popup using SweetAlert2
    echo "
    <script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>
    <script>
        Swal.fire({
            icon: 'success',
            title: 'Billing Finalized!',
            text: 'The billing has been finalized successfully.',
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
