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
        'height' => $_POST["height"] ?? '',
        'weight' => $_POST["weight"] ?? '',
        'color_of_eyes' => $_POST["coe"] ?? '',
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

        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }

        // Collect POST data for medical record
        $condition_name = $_POST['condition_name'] ?? '';
        $diagnosis_date = $_POST['diagnosis_date'] ?? '';
        $notes = $_POST['notes'] ?? '';

        $stmt->bind_param("isss", $patient_id, $condition_name, $diagnosis_date, $notes);

        if (!$stmt->execute()) {
            throw new Exception("Execute failed: " . $stmt->error);
        }

        $stmt->close();

        // Commit if everything is successful
        $conn->commit();

        // Redirect before any output
        header("Location: ../registered.php?success=1");
        exit();

    } catch (Exception $e) {
        $conn->rollback();  
    
        // Log to server error log (still useful for debugging later)
        error_log("Patient creation failed: " . $e->getMessage());

        // Also log to browser console
        echo "<script>console.error('Patient creation failed: " . addslashes($e->getMessage()) . "');</script>";

        header("Location: ../registered.php?error=1");
        exit();
    }
}
?>