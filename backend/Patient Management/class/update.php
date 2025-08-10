<?php
include '../../SQL/config.php';
require_once 'class/patient.php';

$patientObj = new Patient($conn);

$patient_id = $_GET['patient_id'] ?? null;
if (!$patient_id) {
    die("No patient ID provided.");
}

$patient = $patientObj->getPatientById($patient_id);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $updatedData = [
        'fname'            => $_POST["fname"] ?? '',
        'mname'            => $_POST["mname"] ?? '',
        'lname'            => $_POST["lname"] ?? '',
        'address'          => $_POST["address"] ?? '',
        'age'              => (int)($_POST["age"] ?? 0),
        'dob'              => $_POST["dob"] ?? '',
        'gender'           => $_POST["gender"] ?? '',
        'civil_status'     => $_POST["civil_status"] ?? '',
        'phone_number'     => $_POST["phone_number"] ?? '',
        'email'            => $_POST["email"] ?? '',
        'admission_type'   => $_POST["admission_type"] ?? '',
        'attending_doctor' => $_POST["attending_doctor"] ?? ''
    ];

    $conn->begin_transaction();

    try {
        // Update patient table
        $updateResult = $patientObj->updatePatient($patient_id, $updatedData);

        if (!$updateResult) {
            throw new Exception("Failed to update patient information.");
        }

        // Try updating medical history
        if (!empty($_POST['condition_name']) || !empty($_POST['diagnosis_date']) || !empty($_POST['notes'])) {
            try {
                $stmt = $conn->prepare("
                    UPDATE p_previous_medical_history 
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

                // Log to PHP error log
                error_log("Updated p_previous_medical_history for patient_id={$patient_id} | Data: " . json_encode($_POST));

                // Log to browser console
                echo "<script>console.log('p_previous_medical_history updated', " . json_encode([
                    'patient_id'     => $patient_id,
                    'condition_name' => $_POST['condition_name'],
                    'diagnosis_date' => $_POST['diagnosis_date'],
                    'notes'          => $_POST['notes']
                ]) . ");</script>";

            } catch (Exception $mhErr) {
                error_log($mhErr->getMessage());
                echo "<script>console.error('{$mhErr->getMessage()}');</script>";
            }
        }

        $conn->commit();
        header("Location: ../Patient Management/inpatient.php?success=1");
        exit();
    } catch (Exception $e) {
        $conn->rollback();
        $error = "Failed to update patient: " . $e->getMessage();
        error_log($error);
        echo "<script>console.error('{$error}');</script>";
    }
}
?>