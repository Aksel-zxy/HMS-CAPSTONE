<?php
require '../../../SQL/config.php';
require_once '../classes/Auth.php';
require_once '../classes/User.php';
require_once '../classes/Employee.php';

Auth::checkHR();

$conn = $conn;

$userId = Auth::getUserId();
if (!$userId) {
    die("User ID not set.");
}

$userObj = new User($conn);
$user = $userObj->getById($userId);
if (!$user) {
    die("User not found.");
}
    
$type = isset($_GET['type']) ? $_GET['type'] : '';
$value = isset($_GET['value']) ? trim($_GET['value']) : '';

$response = ['exists' => false];

if (!empty($type) && !empty($value)) {
    $employee = new Employee($conn);

    if ($type === 'employee_id') {
        $response['exists'] = $employee->existsById($value);
    }
}

echo json_encode($response);

