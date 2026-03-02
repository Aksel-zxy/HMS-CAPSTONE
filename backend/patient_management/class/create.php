<?php
ob_start(); // Prevent "headers already sent" errors
include '../../../SQL/config.php';
require_once 'patient.php';
include 'logs.php';
    
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
        'discount' => $_POST["discount"] ?? ''
    ];

    $conn->begin_transaction();

    try {
        // Insert into patientinfo
        $patient_id = $patient->insertPatient($data);
        if (!$patient_id) {
            throw new Exception("Failed to insert patient");
        }

        // Create login for patient
        $username = $data['fname'];
        $password = password_hash('123', PASSWORD_DEFAULT); // secure password hashing

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

        // Insert into previous medical records
        $stmt = $conn->prepare("
            INSERT INTO p_previous_medical_records (patient_id, condition_name, diagnosis_date, notes, image_blob)
            VALUES (?, ?, ?, ?, ?)
        ");
        if (!$stmt) {
            throw new Exception("Prepare failed for p_previous_medical_records: " . $conn->error);
        }

        $condition_name = $_POST['condition_name'] ?? '';
        $diagnosis_date = !empty($_POST['diagnosis_date']) ? $_POST['diagnosis_date'] : null;
        $notes = $_POST['notes'] ?? '';
        $image_blob = null;

        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $image_blob = file_get_contents($_FILES['image']['tmp_name']);
        }

        // Bind parameters (b = blob placeholder)
        $stmt->bind_param("isssb", 
            $patient_id, 
            $condition_name, 
            $diagnosis_date, 
            $notes, 
            $null  // blob placeholder
        );

        // Send the blob if it exists
        if ($image_blob !== null) {
            $stmt->send_long_data(4, $image_blob); // index 4 = fifth parameter (0-based)
        }

        if (!$stmt->execute()) {
            throw new Exception("Execute failed for p_previous_medical_records: " . $stmt->error);
        }

        $stmt->close();
        $conn->commit();

        // Log action
        $user_id = $_SESSION['user_id'] ?? null;
        if ($user_id) {
            logAction($conn, $user_id, 'ADD_PATIENT', $patient_id);
        }

        header("Location: ../registered.php?success=1");
        exit();

    } catch (Exception $e) {
        $conn->rollback();

        if (isset($_SESSION['user_id'])) {
            logAction($conn, $_SESSION['user_id'], 'ADD_PATIENT_FAILED');
        }

        error_log("Patient creation failed: " . $e->getMessage());
        echo "<pre style='color:red; font-weight:bold;'>Patient creation failed: " . htmlspecialchars($e->getMessage()) . "</pre>";
        exit();
    }
}
ob_end_flush();
?>