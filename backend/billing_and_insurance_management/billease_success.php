<?php
include '../../SQL/config.php';
if (session_status() === PHP_SESSION_NONE) session_start();

$patient_id = $_GET['patient_id'] ?? 0;
$billing_id = $_GET['billing_id'] ?? 0;
$status = $_GET['status'] ?? 'paid'; // BillEase will send this

if ($status === 'paid') {
    $stmt = $conn->prepare("UPDATE patient_receipt 
                            SET status='Paid', payment_reference='BillEase Paid'
                            WHERE patient_id=? AND billing_id=? AND payment_method='BillEase'");
    $stmt->bind_param("ii", $patient_id, $billing_id);
    $stmt->execute();

    echo "<script>alert('BillEase payment successful!'); window.location='billing_records.php';</script>";
} else {
    echo "<script>alert('BillEase payment not completed.'); window.location='billing_summary.php?patient_id={$patient_id}';</script>";
}
?>
