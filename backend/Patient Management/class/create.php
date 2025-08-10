<?php
include '../../SQL/config.php';
require_once 'patient.php';

$patient = new Patient($conn);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $data = [
        'fname' => $_POST["fname"],
        'mname' => $_POST["mname"],
        'lname' => $_POST["lname"],
        'address' => $_POST["address"],
        'age' => $_POST["age"],
        'dob' => $_POST["dob"],
        'gender' => $_POST["gender"],
        'civil_status' => $_POST["civil_status"],
        'phone_number' => $_POST["phone_number"],
        'email' => $_POST["email"],
        'admission_type' => $_POST["admission_type"],
        'attending_doctor' => $_POST["attending_doctor"] ?? '',
    ];

    // Start transaction
    $conn->begin_transaction();

    try {
        // Insert patient and get new ID
        $patient_id = $patient->insertPatient($data);

        if (!$patient_id) {
            throw new Exception("Failed to insert patient");
        }

        // Insert into medical_records table
        $stmt = $conn->prepare("
            INSERT INTO  p_previous_medical_history (patient_id, condition_name, diagnosis_date, notes)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->bind_param(
            "isss",
            $patient_id,
            $_POST['condition_name'],
            $_POST['diagnosis_dae'],
            $_POST['notes']
        );
        $stmt->execute();

        // Commit transaction
        $conn->commit();

        header("Location: ../Patient Management/inpatient.php?success=1");
        exit();
    } catch (Exception $e) {
        $conn->rollback();
        $error = "Failed to add patient and medical record: " . $e->getMessage();
    }
}
?>