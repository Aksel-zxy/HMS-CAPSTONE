<?php 
include __DIR__ . '/../../../SQL/config.php';

function logAction($conn, $user_id, $action, $patient_id = NULL) {
    $stmt = $conn->prepare("
        INSERT INTO p_access_logs (user_id, patient_id, action)
        VALUES (?, ?, ?)
    ");
    $stmt->bind_param("iis", $user_id, $patient_id, $action);
    $stmt->execute();
    $stmt->close();
}

?>