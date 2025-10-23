<?php
include '../../SQL/config.php';
require_once 'class/patient.php';

$patientObj = new Patient($conn);

$patient_id = $_GET['patient_id'] ?? null;
if (!$patient_id) {
    die("No patient ID provided.");
}

$patient = $patientObj->getPatientById($patient_id);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $dob = $_POST["dob"] ?? '';

    // If only year provided (YYYY), append -01-01
    if (preg_match('/^\d{4}$/', $dob)) {
        $dob .= '-01-01';
    }

    if (empty($dob)) {
        $dob = null;
    }

    $updatedData = [
        'fname'            => $_POST["fname"] ?? '',
        'mname'            => $_POST["mname"] ?? '',
        'lname'            => $_POST["lname"] ?? '',
        'address'          => $_POST["address"] ?? '',
        'age'              => (int)($_POST["age"] ?? 0),
        'dob'              => $dob,
        'gender'           => $_POST["gender"] ?? '',
        'civil_status'     => $_POST["civil_status"] ?? '',
        'phone_number'     => $_POST["phone_number"] ?? '',
        'email'            => $_POST["email"] ?? '',
        'admission_type'   => $_POST["admission_type"] ?? '',
        'attending_doctor' => $_POST["attending_doctor"] ?? ''
    ];

    $conn->begin_transaction();

    try {
        // Update patient info
        $updateResult = $patientObj->updatePatient($patient_id, $updatedData);

        if (!$updateResult) {
            throw new Exception("Failed to update patient information.");
        }

        // Update medical history if provided
        if (!empty($_POST['condition_name']) || !empty($_POST['diagnosis_date']) || !empty($_POST['notes'])) {
            $stmt = $conn->prepare("
                UPDATE p_previous_medical_records 
                SET condition_name = ?, diagnosis_date = ?, notes = ? 
                WHERE patient_id = ?
            ");
            $stmt->bind_param(
                "sssi",
                $_POST['condition_name'],
                $_POST['diagnosis_date'],
                $_POST['notes'],
                $patient_id
            );

            if (!$stmt->execute()) {
                throw new Exception("Medical history update failed: " . $stmt->error);
            }
            $stmt->close();
        }

        $conn->commit();
        header("Location: ../Patient Management/inpatient.php?success=1");
        exit();

    } catch (Exception $e) {
        $conn->rollback();
        $error = "Failed to update patient: " . $e->getMessage();
        error_log($error);

        echo "<script>console.error(" . json_encode($error) . ");</script>";

        
        echo "<p style='color:red;'>$error</p>";
    }
}
?>