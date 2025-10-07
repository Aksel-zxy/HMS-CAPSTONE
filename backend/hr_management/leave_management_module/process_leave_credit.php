<?php
require '../../../SQL/config.php';
require '../classes/Auth.php';
require '../classes/User.php';
require 'classes/LeaveCredit.php';

Auth::checkHR();

$conn = $conn;
$leaveCreditModel = new LeaveCredit($conn);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $employeeId = $_POST['employee_id'];
    $year = $_POST['year'];
    $leaveTypes = $_POST['leave_type'];
    $allocatedDays = $_POST['allocated_days'];

    foreach ($leaveTypes as $index => $type) {
        $days = (int)$allocatedDays[$index];
        $leaveCreditModel->assignLeaveCredit($employeeId, $type, $days, $year);
    }

    header("Location: leave_credit_management.php?success=1");
    exit;
}
