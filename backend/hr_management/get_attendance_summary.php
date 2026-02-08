<?php
require '../../SQL/config.php';
require_once 'classes/Dashboard.php';

$dashboard = new Dashboard($conn);

$employeeId = isset($_GET['employee_id']) ? (int)$_GET['employee_id'] : 0;
$month      = isset($_GET['month']) ? (int)$_GET['month'] : null;
$year       = isset($_GET['year']) ? (int)$_GET['year'] : null;

$allStatuses = [
    'Present', 'Late', 'Undertime', 'Overtime',
    'Half Day', 'On Leave', 'On Leave (Half Day)',
    'Absent', 'Absent (Half Day)'
];

if ($employeeId) {
    $summary = $dashboard->getEmployeeAttendanceSummary($employeeId, $month, $year);

    foreach ($allStatuses as $status) {
        if (!isset($summary[$status])) {
            $summary[$status] = 0;
        }
    }

    echo json_encode($summary);
} else {
    echo json_encode(array_fill_keys($allStatuses, 0));
}

