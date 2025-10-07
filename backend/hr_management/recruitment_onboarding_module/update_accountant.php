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
        echo "<script>alert('Accountant not found.'); window.history.back();</script>";
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

    $updateSuccess = $employeeObj->update($employeeId, $data);

    $documentTypes = [
            'resume'              => 'Resume',
            'diploma'              => 'Diploma',
            'nbi_clearance'       => 'NBI/Police Clearance',
            'government_id'       => 'Government ID',
            'birth_certificate'   => 'Birth Certificate',
            'good_moral'          => 'Certificate of Good Moral',
            'application_letter'  => 'Application Letter',
            'medical_certificate' => 'Medical Certificate',
            'tor'          => 'Transcript of Records',
            'id_picture'          => 'ID Picture'
    ];

    foreach ($documentTypes as $fieldName => $docType) {
        if (!empty($_FILES[$fieldName]['name'])) {
            $fileTmp  = $_FILES[$fieldName]['tmp_name'];
            $fileName = time() . '_' . basename($_FILES[$fieldName]['name']);
            $targetDir = 'employees document/';
            if (!is_dir($targetDir)) {
                mkdir($targetDir, 0777, true);
            }
            $targetPath = $targetDir . $fileName;

            if (move_uploaded_file($fileTmp, $targetPath)) {
                $stmt = $conn->prepare("
                    INSERT INTO hr_employees_documents (employee_id, document_type, file_path)
                    VALUES (?, ?, ?)
                    ON DUPLICATE KEY UPDATE file_path = VALUES(file_path)
                ");
                $stmt->bind_param("sss", $employeeId, $docType, $targetPath);
                $stmt->execute();
                $stmt->close();
            }
        }
    }

    if ($updateSuccess) {
        echo "<script>alert('Accountant and documents updated successfully!'); window.location.href = 'view_accountant.php?employee_id=$employeeId';</script>";
    } else {
        echo "<script>alert('Error updating accountant. Please try again.'); window.history.back();</script>";
    }
}

$conn->close();
?>
