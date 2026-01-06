<?php
include __DIR__ . '/../../../SQL/config.php';
require_once 'dashb.php';

header('Content-Type: application/json');

$type = $_GET['type'] ?? 'inpatient';
$range = $_GET['range'] ?? 'monthly';

// Default 12 months array
$data = array_fill(0, 12, 0);

switch ($type) {
    case 'inpatient':
        $data = ($range === 'weekly') ? ChartData::getWeeklyAdmissions($conn) : ChartData::getMonthlyAdmissions($conn);
        break;

    case 'outpatient':
        $data = ($range === 'weekly') ? ChartData::getWeeklyOutpatients($conn) : ChartData::getMonthlyOutpatients($conn);
        break;

    case 'appointments':
        $data = ($range === 'weekly') ? ChartData::getWeeklyAppointments($conn) : ChartData::getMonthlyAppointments($conn);
        break;

    case 'total':
        $data = ($range === 'weekly') ? ChartData::getWeeklyTotalPatients($conn) : ChartData::getMonthlyTotalPatients($conn);
        break;

    default:
        $data = ($range === 'weekly') ? ChartData::getWeeklyAdmissions($conn) : ChartData::getMonthlyAdmissions($conn);
}


echo json_encode($data);
exit;