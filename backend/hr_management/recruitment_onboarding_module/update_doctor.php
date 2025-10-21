<?php
require '../../../SQL/config.php';
require_once '../classes/Auth.php';
require_once '../classes/User.php';
require_once '../classes/Employee.php';

Auth::checkHR();

$userId = Auth::getUserId();
if (!$userId) {
    die("User ID not set.");
}

$userObj = new User($conn);
$user = $userObj->getById($userId);
if (!$user) {
    die("User not found.");
}

$employeeObj = new Employee($conn);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $employeeId = $_POST['employee_id'] ?? '';

    $employee = $employeeObj->getById($employeeId);
    if (!$employee) {
        echo "<script>alert('Doctor not found.'); window.history.back();</script>";
        exit;
    }

    $fields = [
        'first_name', 'middle_name', 'last_name', 'suffix_name', 'gender', 'date_of_birth',
        'contact_number', 'email', 'citizenship', 'house_no', 'barangay', 'city',
        'province', 'region', 'profession', 'role', 'department', 'specialization',
        'employment_type', 'status', 'educational_status', 'degree_type', 'medical_school',
        'graduation_year', 'license_type', 'license_number', 'license_issued', 'license_expiry',
        'eg_name', 'eg_relationship', 'eg_cn'
    ];

    $data = [];
    foreach ($fields as $field) {
        $data[$field] = $conn->real_escape_string($_POST[$field] ?? '');
    }

    // Handle "Others" specialization
    $specialization = $_POST['specialization'] ?? '';
    if ($specialization === "Others") {
        $specialization = $_POST['otherSpecialization'] ?? '';
    }
    $data['specialization'] = $conn->real_escape_string($specialization);

    $updateSuccess = $employeeObj->update($employeeId, $data);

    $documentTypes = [
        'resume'              => 'Resume',
        'license_id'          => 'License ID',
        'board_certificate'   => 'Board Rating & Certificate of Passing',
        'diploma'             => 'Diploma',
        'government_id'       => 'Government ID',
        'application_letter'  => 'Application Letter',
        'tor'                 => 'Transcript of Records',
        'id_picture'          => 'ID Picture'
    ];

    foreach ($documentTypes as $fieldName => $docType) {
        if (!empty($_FILES[$fieldName]['name']) && $_FILES[$fieldName]['error'] === 0) {
            $fileTmp  = $_FILES[$fieldName]['tmp_name'];
            $fileSize = $_FILES[$fieldName]['size'];

            // Optional: limit file size to 5MB
            if ($fileSize > 5242880) continue;

            // Read file as binary
            $fileData = file_get_contents($fileTmp);

            $stmt = $conn->prepare("
                INSERT INTO hr_employees_documents (employee_id, document_type, file_blob)
                VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE file_blob = VALUES(file_blob)
            ");

            $null = NULL; // placeholder for blob
            $stmt->bind_param("ssb", $employeeId, $docType, $null);
            $stmt->send_long_data(2, $fileData);
            $stmt->execute();
            $stmt->close();
        }
    }

    if ($updateSuccess) {
        echo "<script>alert('Doctor and documents updated successfully!'); window.location.href = 'view_doctor.php?employee_id=$employeeId';</script>";
    } else {
        echo "<script>alert('Error updating doctor. Please try again.'); window.history.back();</script>";
    }
}

$conn->close();
?>
