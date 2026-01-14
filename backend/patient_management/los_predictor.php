<?php
include '../../SQL/config.php';

if (!isset($_SESSION['patient']) || $_SESSION['patient'] !== true) {
    header('Location: login.php'); // Redirect to login if not logged in
    exit();
}

if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    echo "User ID is not set in session.";
    exit();
}

function predictLoS($age, $severity, $comorbidity) {
    $los = (0.05 * $age)
         + (1.3 * $severity)
         + (0.85 * $comorbidity)
         + 1.7;

    return max(1, round($los, 1));
}

function getComorbidityCount($conn, $patient_id) {
    $sql = "SELECT COUNT(*) AS cnt
            FROM p_previous_medical_records
            WHERE patient_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $patient_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();

    return (int)$result['cnt'];
}