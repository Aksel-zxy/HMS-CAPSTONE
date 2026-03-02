<?php
// dashboard_chart.php
include __DIR__ . '/../../../SQL/config.php';
require_once 'dashb.php';

header('Content-Type: application/json');

// Disable PHP warnings to prevent invalid JSON
error_reporting(0);

// Get type and range from query params
$type = $_GET['type'] ?? 'inpatient';
$range = $_GET['range'] ?? 'monthly';

// Helper function to ensure numeric array of desired length
function ensureLength(array $arr, int $length, int $default = 0): array {
    $arr = array_values($arr); // reindex
    if (count($arr) < $length) {
        return array_pad($arr, $length, $default);
    } elseif (count($arr) > $length) {
        return array_slice($arr, 0, $length);
    }
    return $arr;
}

// Initialize empty data
$data = [];

try {
    switch ($type) {
        case 'inpatient':
            $data = ($range === 'weekly') 
                ? ChartData::getWeeklyAdmissions($conn) 
                : ChartData::getMonthlyAdmissions($conn);
            break;

        case 'outpatient':
            $data = ($range === 'weekly') 
                ? ChartData::getWeeklyOutpatients($conn) 
                : ChartData::getMonthlyOutpatients($conn);
            break;

        case 'appointments':
            $data = ($range === 'weekly') 
                ? ChartData::getWeeklyAppointments($conn) 
                : ChartData::getMonthlyAppointments($conn);
            break;

        case 'total':
            $data = ($range === 'weekly') 
                ? ChartData::getWeeklyTotalPatients($conn) 
                : ChartData::getMonthlyTotalPatients($conn);
            break;

        default:
            $data = ($range === 'weekly') 
                ? ChartData::getWeeklyAdmissions($conn) 
                : ChartData::getMonthlyAdmissions($conn);
    }

    // Ensure $data is a numeric array
    $data = array_map('intval', (array)$data);

    // Force length: 12 for monthly, 7 for weekly
    $data = ensureLength($data, $range === 'weekly' ? 7 : 12);

} catch (Exception $e) {
    // On error, return zero-filled array
    $data = array_fill(0, $range === 'weekly' ? 7 : 12, 0);
}

// Return JSON
echo json_encode($data);
exit();