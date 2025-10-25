<?php
require '../../../SQL/config.php';
require 'classes/Salary.php';

$employee_id = intval($_GET['employee_id']);
$salary = new Salary($conn);

$employee_id = intval($_GET['employee_id']);
$month = $_GET['month'] ?? date('m'); // default current month
$year = $_GET['year'] ?? date('Y');  // default current year

$start_date = "$year-$month-01";
$end_date = date("Y-m-t", strtotime($start_date));

$salary = new Salary($conn);
$attendance = $salary->getAttendanceSummary($employee_id, $start_date, $end_date);

echo json_encode($attendance);

