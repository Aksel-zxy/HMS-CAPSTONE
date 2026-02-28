<?php
include __DIR__ . '../../../../SQL/config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $patientID   = $_POST['patientID'] ?? null;
    $scheduleIDs = isset($_POST['scheduleIDs']) ? json_decode($_POST['scheduleIDs'], true) : [];
    $status      = $_POST['status'] ?? 'Processing';
    $remarks     = $_POST['remarks'] ?? ''; 

    if (!$patientID || empty($scheduleIDs)) {
        die("Invalid data submitted.");
    }

    
    $placeholders = implode(',', array_fill(0, count($scheduleIDs), '?'));
    $types = str_repeat('i', count($scheduleIDs));

    $sql = "SELECT scheduleID, serviceName FROM dl_schedule WHERE scheduleID IN ($placeholders)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$scheduleIDs);
    $stmt->execute();
    $result = $stmt->get_result();

    $testNames = [];
    $firstScheduleID = null;
    while ($row = $result->fetch_assoc()) {
        $testNames[] = $row['serviceName'];
        if ($firstScheduleID === null) {
            $firstScheduleID = $row['scheduleID'];
        }
    }
    $stmt->close();

    
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $validatorID = $_SESSION['user_id'] ?? null;

    $testList = implode(", ", $testNames);
    $sql = "INSERT INTO dl_results (scheduleID, patientID, status, resultDate, result, remarks, validated_by) 
            VALUES (?, ?, ?, NOW(), ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $bindValidatorID = $validatorID ? (int)$validatorID : 0;
    $stmt->bind_param("iisssi", $firstScheduleID, $patientID, $status, $testList, $remarks, $bindValidatorID);
    $stmt->execute();
    $resultID = $stmt->insert_id;
    $stmt->close();
    $sql = "INSERT INTO dl_result_schedules (resultID, scheduleID) VALUES (?, ?)";
    $stmt = $conn->prepare($sql);

    foreach ($scheduleIDs as $sid) {
        $stmt->bind_param("ii", $resultID, $sid);
        $stmt->execute();
    }
    $stmt->close();

    echo "<script>alert('✅ Result saved successfully!'); window.location.href='test_process.php';</script>";
}
?>
