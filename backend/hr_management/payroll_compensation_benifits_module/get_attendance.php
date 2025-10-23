<?php
require '../../../SQL/config.php';
require 'classes/Salary.php';

$employee_id = intval($_GET['employee_id']);
$salary = new Salary($conn);

// Example: get attendance for current month
$start_date = date('Y-m-01');
$end_date = date('Y-m-t');

$attendance = $salary->getAttendanceSummary($employee_id, $start_date, $end_date);

echo json_encode($attendance);
