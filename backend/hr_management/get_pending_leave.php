<?php
require '../../SQL/config.php';
require_once 'classes/Dashboard.php';

$dashboard = new Dashboard($conn);

$employeeId = isset($_GET['employee_id']) ? (int)$_GET['employee_id'] : 0;

// Fetch all pending leaves (no employee filter)
$data = $dashboard->getPendingLeaves(); // employeeId = null by default

echo json_encode($data);
?>
