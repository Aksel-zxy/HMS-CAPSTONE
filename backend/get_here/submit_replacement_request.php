<?php
require '../../../SQL/config.php';
require_once '../classes/Auth.php';
require_once '../classes/User.php';

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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Collect and trim POST data
    $profession = trim($_POST['profession'] ?? '');
    $department = trim($_POST['department'] ?? '');
    $position = trim($_POST['position'] ?? '');
    $other_specialization = trim($_POST['other_specialization'] ?? '');
    $leaving_employee_name = trim($_POST['leaving_employee_name'] ?? '');
    $leaving_employee_id = trim($_POST['leaving_employee_id'] ?? '');
    $reason_for_leaving = trim($_POST['reason_for_leaving'] ?? '');
    $requested_by = trim($_POST['requested_by'] ?? ''); // fallback to logged-in user

    // Use "Other" specialization if provided
    if (!empty($other_specialization)) {
        $position = $other_specialization;
    }

    // Basic validation
    if (empty($profession) || empty($department) || empty($position) || empty($requested_by)) {
        echo "<script>
            alert('Profession, Department, Position, and Requested By fields are require_onced.');
            window.history.back();
        </script>";
        exit;
    }

    // Prepare and execute insert
    $stmt = $conn->prepare("
        INSERT INTO hr_replacement_requests 
        (profession, department, position, leaving_employee_name, leaving_employee_id, reason_for_leaving, requested_by, date_requested) 
        VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
    ");
    $stmt->bind_param(
        "sssssss",
        $profession,
        $department,
        $position,
        $leaving_employee_name,
        $leaving_employee_id,
        $reason_for_leaving,
        $requested_by
    );

    if ($stmt->execute()) {
        echo "<script>
            alert('Replacement request submitted successfully.');
            window.location.href='replacement_button.php';
        </script>";
        exit;
    } else {
        echo "<script>
            alert('Error submitting request: " . addslashes($stmt->error) . "');
            window.history.back();
        </script>";
    }

    $stmt->close();
} else {
    die("Invalid request method.");
}
