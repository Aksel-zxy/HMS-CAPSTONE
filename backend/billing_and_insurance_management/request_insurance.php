<?php
include '../../SQL/config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $patient_id = $_POST['patient_id'];
    $insurance_company = $_POST['insurance_company'];
    $insurance_number = $_POST['insurance_number'];
    $relationship = $_POST['relationship_to_insured'];

    // ✅ Use 'request_date' instead of 'created_at'
    $sql = "INSERT INTO insurance_requests 
        (patient_id, insurance_company, insurance_number, relationship_to_insured, total_bill, status, request_date)
        VALUES (?, ?, ?, ?, ?, 'Pending', NOW())";

$stmt = $conn->prepare($sql);
$stmt->bind_param("isssd", $patient_id, $insurance_company, $insurance_number, $relationship, $total_bill);

    if ($stmt->execute()) {
        echo "<script>alert('Insurance request submitted successfully!'); window.location='patient_billing.php';</script>";
    } else {
        echo "<script>alert('Error submitting insurance request.'); window.location='patient_billing.php';</script>";
    }
}
?>
