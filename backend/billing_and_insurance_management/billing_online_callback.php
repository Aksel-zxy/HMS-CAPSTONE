<?php
include '../../SQL/config.php';
$data = json_decode(file_get_contents('php://input'), true);
if($data['status']=='success'){
    $patient_id = intval($data['patient_id']);
    $amount = floatval($data['amount']);
    // Update patient_receipt & journal_entries here
}
http_response_code(200);
