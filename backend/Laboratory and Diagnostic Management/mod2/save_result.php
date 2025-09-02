<?php
include __DIR__ . '../../../../SQL/config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $patientID   = $_POST['patientID'] ?? null;
    $scheduleIDs = isset($_POST['scheduleIDs']) ? json_decode($_POST['scheduleIDs'], true) : [];
    $status      = $_POST['status'] ?? 'Processing';

    if (!$patientID || empty($scheduleIDs)) {
        die("Invalid data submitted.");
    }

    // 1. Get all test names from dl_schedule
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
            $firstScheduleID = $row['scheduleID']; // keep one valid scheduleID for dl_results
        }
    }
    $stmt->close();

    $testList = implode(", ", $testNames); // e.g. "CBC, X-Ray"

    // 2. Insert into dl_results (keep scheduleID)
    $sql = "INSERT INTO dl_results (scheduleID, patientID, status, resultDate, result, remarks) 
            VALUES (?, ?, ?, NOW(), ?, '')";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iiss", $firstScheduleID, $patientID, $status, $testList);
    $stmt->execute();
    $resultID = $stmt->insert_id;
    $stmt->close();

    // 3. Insert into dl_result_schedules (junction table)
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
