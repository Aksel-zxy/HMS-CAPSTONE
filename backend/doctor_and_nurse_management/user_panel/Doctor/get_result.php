<?php
if (!isset($conn)) {
    include __DIR__ . "../../../../../SQL/config.php";
}

$appointmentID = $_GET['appointmentID'] ?? null;
if (!$appointmentID) {
    echo "<p class='text-danger'>⚠️ Missing appointment ID.</p>";
    exit;
}

// Step 1: Get all schedules for this appointment
$querySchedules = "SELECT scheduleID FROM dl_schedule WHERE appointment_id = ?";
$stmtSchedules = $conn->prepare($querySchedules);
$stmtSchedules->bind_param("i", $appointmentID);
$stmtSchedules->execute();
$resSchedules = $stmtSchedules->get_result();

if ($resSchedules->num_rows === 0) {
    echo "<p class='text-muted'>No schedules found for this appointment.</p>";
    exit;
}

// Map lab tables
$labTables = [
    'CBC'     => 'dl_lab_cbc',
    'X-Ray'   => 'dl_lab_xray',
    'MRI'     => 'dl_lab_mri',
    'CT Scan' => 'dl_lab_ct'
];

$foundAny = false;

// Step 2: Loop through schedules
while ($schedule = $resSchedules->fetch_assoc()) {
    $scheduleID = $schedule['scheduleID'];

    // Get results for this schedule
    $queryResults = "SELECT * FROM dl_results WHERE scheduleID = ?";
    $stmtResults = $conn->prepare($queryResults);
    $stmtResults->bind_param("i", $scheduleID);
    $stmtResults->execute();
    $resResults = $stmtResults->get_result();

    if ($resResults->num_rows === 0) continue;

    $foundAny = true;

    while ($result = $resResults->fetch_assoc()) {
        echo "<h4 style='margin-top:20px; color:#198754; font-family:Arial, sans-serif;'>🧪 Result for Schedule #{$scheduleID}</h4>";

        foreach ($labTables as $label => $table) {
            $queryLab = "SELECT * FROM $table WHERE scheduleID = ?";
            $stmtLab = $conn->prepare($queryLab);
            $stmtLab->bind_param("i", $scheduleID);
            $stmtLab->execute();
            $resLab = $stmtLab->get_result();

            if ($resLab->num_rows > 0) {
                $data = $resLab->fetch_assoc();
                echo "<h5>{$label}</h5>";

                if ($label === "CBC") {
                    echo "<div class='table-responsive'><table class='table table-bordered'>
                        <tr><th>WBC</th><td>{$data['wbc']}</td></tr>
                        <tr><th>RBC</th><td>{$data['rbc']}</td></tr>
                        <tr><th>Hemoglobin</th><td>{$data['hemoglobin']}</td></tr>
                        <tr><th>Hematocrit</th><td>{$data['hematocrit']}</td></tr>
                        <tr><th>Platelets</th><td>{$data['platelets']}</td></tr>
                        <tr><th>MCV</th><td>{$data['mcv']}</td></tr>
                        <tr><th>MCH</th><td>{$data['mch']}</td></tr>
                        <tr><th>MCHC</th><td>{$data['mchc']}</td></tr>
                        <tr><th>Remarks</th><td>{$data['remarks']}</td></tr>
                    </table></div>";
                } else {
                    echo "<div class='table-responsive'><table class='table table-bordered'>
                        <tr><th>Findings</th><td>{$data['findings']}</td></tr>
                        <tr><th>Impression</th><td>{$data['impression']}</td></tr>
                        <tr><th>Remarks</th><td>{$data['remarks']}</td></tr>";
                    if (!empty($data['image_path'])) {
                        echo "<tr><th>Image</th><td><img src='{$data['image_path']}' style='max-width:100%; height:auto;'></td></tr>";
                    }
                    echo "</table></div>";
                }
            }
        }
    }
}

if (!$foundAny) {
    echo "<p class='text-muted'>No lab results found for this appointment.</p>";
}
?>
