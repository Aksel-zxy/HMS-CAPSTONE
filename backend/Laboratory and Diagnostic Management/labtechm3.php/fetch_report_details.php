<?php
include '../../../SQL/config.php';
require_once 'report_deliveries_class.php';

header('Content-Type: application/json');

$response = ['success' => false, 'message' => ''];

if (isset($_GET['delivery_id']) && isset($_GET['result_id'])) {
    $delivery_id = $_GET['delivery_id'];
    $result_id = $_GET['result_id'];

    $labdiagnostic_reportdeliveries = new labdiagnostic_reportdeliveries($conn);

    $testResultDetails = $labdiagnostic_reportdeliveries->getTestResultDetails($result_id);
    $resultItems = $labdiagnostic_reportdeliveries->getResultItemsByResultId($result_id);

    if ($testResultDetails) {
        $response['success'] = true;
        $response['testResult'] = $testResultDetails;
        $response['resultItems'] = $resultItems;
    } else {
        $response['message'] = 'Test result details not found.';
    }
} else {
    $response['message'] = 'Missing delivery_id or result_id.';
}

echo json_encode($response);
?>