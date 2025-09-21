<?php
include '../../SQL/config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $patient_id = intval($_POST['patient_id']);
    $insurance_company = trim($_POST['insurance_company']);
    $insurance_number = trim($_POST['insurance_number']);

    // Calculate total bill from patient_receipt for this patient
    $stmtTotal = $conn->prepare("SELECT SUM(grand_total) as total_bill FROM patient_receipt WHERE patient_id = ?");
    $stmtTotal->bind_param("i", $patient_id);
    $stmtTotal->execute();
    $totalResult = $stmtTotal->get_result()->fetch_assoc();
    $total_bill = $totalResult['total_bill'] ?? 0;

    // Insert request
    $stmt = $conn->prepare("
        INSERT INTO insurance_requests
        (patient_id, insurance_company, insurance_number, total_bill, status)
        VALUES (?, ?, ?, ?, 'Pending')
    ");
    $stmt->bind_param("issd", $patient_id, $insurance_company, $insurance_number, $total_bill);
    $stmt->execute();

    echo "<script>alert('Insurance request submitted successfully.');window.location='patient_billing.php';</script>";
    exit;
}
