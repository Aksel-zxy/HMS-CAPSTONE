<?php
session_start();
// Include database connection and class
include '../../SQL/config.php';
require_once '../Laboratory and Diagnostic Management/test_class.php';

// Initialize labdiagnostic_tests with MySQLi connection
$labdiagnostic_tests = new labdiagnostic_tests($conn);

// Check if ID is provided in GET request
if (isset($_GET['id']) && !empty($_GET['id'])) {
    // Set the test ID in the object
    $labdiagnostic_tests->test_id = (int) $_GET['id'];

    // Call getOne() method to retrieve test details
    $test = $labdiagnostic_tests->getOne();

    if ($test) {
        // Return the test details as JSON
        header('Content-Type: application/json');
        echo json_encode($test);
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'Test not found.']);
    }
} else {
    http_response_code(400);
    echo json_encode(['error' => 'Test ID is missing.']);
}
?>
