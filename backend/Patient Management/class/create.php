<?php
include '../../../SQL/config.php';
require_once 'patient.php';

$patient = new Patient($conn);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Collect form data
    $data = [
        'fname' => trim($_POST["fname"] ?? ''),
        'mname' => trim($_POST["mname"] ?? ''),
        'lname' => trim($_POST["lname"] ?? ''),
        'address' => trim($_POST["address"] ?? ''),
        'age' => $_POST["age"] ?? '',
        'dob' => $_POST["dob"] ?? '',
        'gender' => $_POST["gender"] ?? '',
        'civil_status' => $_POST["civil_status"] ?? '',
        'phone_number' => trim($_POST["phone_number"] ?? ''),
        'email' => trim($_POST["email"] ?? ''),
        'admission_type' => $_POST["admission_type"] ?? '',
        'attending_doctor' => $_POST["attending_doctor"] ?? '',
    ];

    // ✅ Validate first name (required: only letters, spaces, hyphens, and must contain at least one vowel)
    if (!preg_match("/^(?=.*[AEIOUaeiou])[A-Za-z\s\-]+$/", $data['fname'])) {
        header("Location: ../registered.php?error=Invalid First Name");
        exit();
    }

    // ✅ Validate last name (required)
    if (!preg_match("/^[A-Za-z\s\-]+$/", $data['lname'])) {
        header("Location: ../registered.php?error=Invalid Last Name");
        exit();
    }

    // ✅ Validate middle name (optional, only if provided)
    if (!empty($data['mname']) && !preg_match("/^[A-Za-z\s\-]+$/", $data['mname'])) {
        header("Location: ../registered.php?error=Invalid Middle Name");
        exit();
    }

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
        header("Location: ../registered.php?success=1");
        exit();

    } catch (Exception $e) {
        $conn->rollback();  
        error_log("Patient creation failed: " . $e->getMessage());
        header("Location: ../registered.php?error=1");
        exit();
    }
}
?>