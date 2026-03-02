<?php
date_default_timezone_set('Asia/Manila');
include '../../SQL/config.php';
require_once 'class/patient.php';

$patientObj = new Patient($conn);

$patient_id = $_GET['patient_id'] ?? null;
if (!$patient_id) {
    die("No patient ID provided.");
}

$patient = $patientObj->getPatientById($patient_id);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // ✅ FIXED DOB HANDLING (bulletproof and version-safe)
    $dob = trim($_POST["dob"] ?? '');

    if ($dob === '') {
        $dob = null;
    } elseif (preg_match('/^\d{4}$/', $dob)) {
        // Year only (e.g., "2013")
        $dob = $dob . '-01-01';
    } elseif (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dob)) {
        // Already a valid YYYY-MM-DD
        $dob = $dob;
    } else {
        // Invalid format or parsing error
        $dob = null;
    }

    // --- Prepare updated data ---
    $updatedData = [
        'fname'            => $_POST["fname"] ?? '',
        'mname'            => $_POST["mname"] ?? '',
        'lname'            => $_POST["lname"] ?? '',
        'address'          => $_POST["address"] ?? '',
        'age'              => (int)($_POST["age"] ?? 0),
        'dob'              => $dob, // ✅ uses the cleaned DOB
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
// Handle image upload
$image_blob = null;
$imageUploaded = false;

if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {

    $allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
    $maxSize = 5 * 1024 * 1024; // 5MB

    if (!in_array($_FILES['image']['type'], $allowedTypes)) {
        throw new Exception("Invalid image type.");
    }

    if ($_FILES['image']['size'] > $maxSize) {
        throw new Exception("Image too large.");
    }

    $image_blob = file_get_contents($_FILES['image']['tmp_name']);
    $imageUploaded = true;
}

// Update medical history if provided
if (!empty($_POST['condition_name']) || 
    !empty($_POST['diagnosis_date']) || 
    !empty($_POST['notes']) || 
    $imageUploaded) {

    if ($imageUploaded) {

        $stmt = $conn->prepare("
            UPDATE p_previous_medical_records 
            SET condition_name = ?, 
                diagnosis_date = ?, 
                notes = ?, 
                image_blob = ?
            WHERE patient_id = ?
        ");

        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }

        $condition_name = $_POST['condition_name'] ?? '';
        $diagnosis_date = $_POST['diagnosis_date'] ?? null;
        $notes = $_POST['notes'] ?? '';
        $null = NULL; // placeholder for blob

        $stmt->bind_param(
            "sssbi",
            $condition_name,
            $diagnosis_date,
            $notes,
            $null,
            $patient_id
        );

        // Send binary data (image is parameter index 3)
        $stmt->send_long_data(3, $image_blob);

    } else {

        $stmt = $conn->prepare("
            UPDATE p_previous_medical_records 
            SET condition_name = ?, 
                diagnosis_date = ?, 
                notes = ?
            WHERE patient_id = ?
        ");

        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }

        $condition_name = $_POST['condition_name'] ?? '';
        $diagnosis_date = $_POST['diagnosis_date'] ?? null;
        $notes = $_POST['notes'] ?? '';

        $stmt->bind_param(
            "sssi",
            $condition_name,
            $diagnosis_date,
            $notes,
            $patient_id
        );
    }

    if (!$stmt->execute()) {
        throw new Exception("Medical history update failed: " . $stmt->error);
    }

    $stmt->close();
}

        // Commit if everything is successful
        $conn->commit();
        header("Location: ../patient_management/inpatient.php?success=1");
        exit();

    } catch (Exception $e) {
        // Rollback if there’s an error
        $conn->rollback();
        $error = "Failed to update patient: " . $e->getMessage();
        error_log($error);

        // Safely show error in browser console
        echo "<script>console.error(" . json_encode($error) . ");</script>";

        // Optional visible error message
        echo "<p style='color:red; font-weight:bold;'>$error</p>";
    }
}
?>