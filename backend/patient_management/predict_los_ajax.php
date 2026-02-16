<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json');

include '../../SQL/config.php';
include 'los_predictor.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $patient_id = intval($_POST['patient_id'] ?? 0);
    $severity = intval($_POST['severity'] ?? 3);
    $admission_type = $_POST['admission_type'] ?? '';

    if (!$patient_id || !$severity || !$admission_type) {
        echo json_encode(["los" => 0]);
        exit();
    }

    // Fetch patient age
    $stmt = $conn->prepare("SELECT age FROM patientinfo WHERE patient_id=?");
    $stmt->bind_param("i", $patient_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();

    if (!$result) {
        echo json_encode(["los" => 0]);
        exit();
    }

    $age = floatval($result['age']);

    // Get comorbidities
    $comorbidities = function_exists('getComorbidities') ? getComorbidities($conn, $patient_id) : '';
    $comorbidity_count = $comorbidities ? count(explode(",", $comorbidities)) : 0;

    // Predict LoS safely
    try {
        $predicted_los = function_exists('predictLoS') 
            ? floatval(predictLoS($age, $severity, $comorbidities, $admission_type))
            : max(1, round(0.05 * $age + 1.3 * $severity + 0.85 * $comorbidity_count + 1.7));

        if (!$predicted_los) throw new Exception("Invalid AI output");
    } catch (Exception $e) {
        // fallback formula
        $predicted_los = max(1, round(0.05 * $age + 1.3 * $severity + 0.85 * $comorbidity_count + 1.7));
    }

    echo json_encode(["los" => $predicted_los]);
    exit();
}