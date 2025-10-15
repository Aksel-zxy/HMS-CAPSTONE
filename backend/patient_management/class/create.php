<?php
ob_start(); //  Prevent "headers already sent" errors by buffering output
include '../../../SQL/config.php';
require_once 'patient.php';

$patient = new Patient($conn);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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

    $conn->begin_transaction();

    try {
        $patient_id = $patient->insertPatient($data);
        if (!$patient_id) {
            throw new Exception("Failed to insert patient");
        }

        $username = $data['fname'];
        $password = '123';

        $stmt_user = $conn->prepare("
            INSERT INTO patient_user (patient_id, fname, lname, mname, username, password)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        if (!$stmt_user) {
            throw new Exception("Prepare failed for patient_user: " . $conn->error);
        }

        $stmt_user->bind_param("isssss", $patient_id, $data['fname'], $data['lname'], $data['mname'], $username, $password);
        if (!$stmt_user->execute()) {
            throw new Exception("Execute failed for patient_user: " . $stmt_user->error);
        }
        $stmt_user->close();

        $stmt = $conn->prepare("
            INSERT INTO p_previous_medical_records (patient_id, condition_name, diagnosis_date, notes)
            VALUES (?, ?, ?, ?)
        ");
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }

        $condition_name = $_POST['condition_name'] ?? '';
        $diagnosis_date = $_POST['diagnosis_date'] ?? '';
        $notes = $_POST['notes'] ?? '';

        $stmt->bind_param("isss", $patient_id, $condition_name, $diagnosis_date, $notes);
        if (!$stmt->execute()) {
            throw new Exception("Execute failed: " . $stmt->error);
        }

        $stmt->close();
        $conn->commit();

        header("Location: ../registered.php?success=1");
        exit();

    } catch (Exception $e) {
        $conn->rollback();
        error_log("Patient creation failed: " . $e->getMessage());

        // Log to console (for debugging)
        echo "<script>console.error('Patient creation failed: " . addslashes($e->getMessage()) . "');</script>";

        header("Location: ../registered.php?error=1");
        exit();
    }
}
ob_end_flush(); //  Sends any remaining buffered output
?>