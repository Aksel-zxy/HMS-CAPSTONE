<?php
include '../../../SQL/config.php';
require_once 'patient.php';

$patient = new Patient($conn);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Collect form data
    $data = [
        'fname' => $_POST["fname"] ?? '',
        'mname' => $_POST["mname"] ?? '',
        'lname' => $_POST["lname"] ?? '',
        'address' => $_POST["address"] ?? '',
        'age' => $_POST["age"] ?? '',
        'dob' => $_POST["dob"] ?? '',
        'gender' => $_POST["gender"] ?? '',
        'civil_status' => $_POST["civil_status"] ?? '',
        'phone_number' => $_POST["phone_number"] ?? '',
        'email' => $_POST["email"] ?? '',
        'admission_type' => $_POST["admission_type"] ?? '',
        'attending_doctor' => $_POST["attending_doctor"] ?? '',
    ];

    // Start transaction
    $conn->begin_transaction();

    try {
        $patient_id = $patient->insertPatient($data);
        if (!$patient_id) {
            throw new Exception("Failed to insert patient");
        }

        $stmt = $conn->prepare("
            INSERT INTO p_previous_medical_records (patient_id, condition_name, diagnosis_date, notes)
            VALUES (?, ?, ?, ?)
        ");
       $condition_name = $_POST['condition_name'] ?? '';
$diagnosis_date = $_POST['diagnosis_date'] ?? '';
$notes = $_POST['notes'] ?? '';

$stmt->bind_param(
    "isss",
    $patient_id,
    $condition_name,
    $diagnosis_date,
    $notes
);

        $stmt->execute();

        $conn->commit();

        // Redirect before any output
        header("Location: ../inpatient.php?success=1");
        exit();

    } catch (Exception $e) {
        $conn->rollback();  
        // Log instead of echo (to prevent header issues)
        error_log("Patient creation failed: " . $e->getMessage());
        header("Location: ../inpatient.php?error=1");
        exit();
    }
}
?>