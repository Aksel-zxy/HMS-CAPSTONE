<?php

$configPath = realpath(__DIR__ . "/../../../SQL/config.php");

if ($configPath && file_exists($configPath)) {
    include $configPath;
} else {
    die("Critcal Error: Config file not found at: " . __DIR__ . "/../../../SQL/config.php");
}


if (!isset($conn)) {
    die("Critical Error: Database connection variable (\$conn) is not defined. Check config.php.");
}


$scheduleID = $_GET['scheduleID'] ?? null;
$testType   = $_GET['testType'] ?? null;

if (!$scheduleID || !$testType) {
    echo "<p class='text-danger'>Invalid request. Missing Schedule ID or Test Type.</p>";
    exit;
}

$testType = strtolower(trim($testType));


if (strpos($testType, "cbc") !== false || strpos($testType, "complete blood count") !== false) {
    $table = "dl_lab_cbc";
    $mode = "cbc";
} elseif (strpos($testType, "x-ray") !== false || strpos($testType, "xray") !== false) {
    $table = "dl_lab_xray";
    $mode = "xray";
} elseif (strpos($testType, "mri") !== false) {
    $table = "dl_lab_mri";
    $mode = "mri";
} elseif (strpos($testType, "ct") !== false) {
    $table = "dl_lab_ct";
    $mode = "ct";
} else {
    echo "<p class='text-danger'>Unknown test type: " . htmlspecialchars($testType) . "</p>";
    exit;
}


function getAdvancedInterpretation($test, $value, $age, $gender) {
    $age = (int)$age;
    $gender = strtoupper($gender[0] ?? 'M');
    $val = (float)$value;

    
    $ranges = [
        'wbc'        => ($age < 1)  ? [6.0, 17.5] : [4.0, 10.0],
        'rbc'        => ($gender == 'M') ? [4.5, 5.5] : [4.0, 5.0],
        'hemoglobin' => ($age < 1)  ? [110, 170] : ($age < 12 ? [110, 145] : ($gender == 'M' ? [140, 180] : [120, 160])),
        'hematocrit' => ($age < 1)  ? [0.33, 0.55] : ($gender == 'M' ? [0.40, 0.54] : [0.37, 0.47]),
        'platelets'  => [150, 450],
        'mcv'        => ($age < 1)  ? [95, 121] : [80, 97],
        'mch'        => [26.5, 33.5],
        'mchc'       => [32, 36]
    ];

    if (!isset($ranges[$test])) return ['range' => '-', 'status' => '-', 'pct' => ''];

    $min = $ranges[$test][0];
    $max = $ranges[$test][1];
    $median = ($min + $max) / 2;
    
   
    $status = "Normal";
    $color = "#198754"; 
    if ($val < $min) { $status = "LOW"; $color = "#0d6efd"; } 
    elseif ($val > $max) { $status = "HIGH"; $color = "#dc3545"; } 

 
    $deviation = (($val - $median) / $median) * 100;
    $devStr = ($deviation >= 0 ? "+" : "") . number_format($deviation, 1) . "%";
    
    return [
        'range'  => "$min - $max",
        'status' => "<strong style='color:$color;'>$status</strong>",
        'pct'    => "<span style='color:#666; font-size: 0.85rem;'>$devStr</span>"
    ];
}



$query = "
    SELECT t.*, 
           e.first_name as medtech_fname, 
           e.last_name as medtech_lname, 
           e.license_number as medtech_license, 
           v.fname as val_fname, 
           v.lname as val_lname, 
           NULL as val_license 
    FROM $table t 
    LEFT JOIN dl_schedule s ON t.scheduleID = s.scheduleID
    LEFT JOIN dl_result_schedules rs ON s.scheduleID = rs.scheduleID
    LEFT JOIN dl_results r ON rs.resultID = r.resultID
    LEFT JOIN hr_employees e ON COALESCE(t.processed_by, s.employee_id) = e.employee_id
    LEFT JOIN users v ON r.validated_by = v.user_id
    WHERE t.scheduleID = ?
";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $scheduleID);
$stmt->execute();
$data = $stmt->get_result()->fetch_assoc();

if (!$data) {
    echo "<div style='padding:20px; border:1px solid #ddd; border-radius:8px; text-align:center;'>
            <p class='text-muted'>No result found for this test. (Test is not completed yet)</p>
          </div>";
    exit;
}


$queryPatient = "SELECT p.*, s.scheduleID 
                 FROM dl_schedule s 
                 LEFT JOIN patientinfo p ON s.patientId = p.patient_id 
                 WHERE s.scheduleID = ?";
$stmtPatient = $conn->prepare($queryPatient);
$stmtPatient->bind_param("i", $scheduleID);
$stmtPatient->execute();
$patient = $stmtPatient->get_result()->fetch_assoc();


?>

<div style="font-family: Arial, sans-serif; margin: 20px auto; padding: 30px; border: 1px solid #333; border-radius: 8px; max-width: 850px; background-color: #fff; box-shadow: 0 4px 10px rgba(0,0,0,0.1);">
    
    <div style="text-align:center; border-bottom: 2px solid #000; padding-bottom: 10px; margin-bottom: 20px;">
        <h2 style="margin:0; font-size:1.4rem;">Dr. Eduardo V. Roquero Memorial Hospital</h2>
        <p style="font-size:0.8rem; margin:5px 0;">Purok 7, Area F, Brgy. San Pedro, City of San Jose Del Monte, Bulacan</p>
        <h4 style="margin:10px 0; color: #198754; text-decoration: underline;">LABORATORY REPORT</h4>
    </div>

    <?php if ($patient): ?>
    <table style="width:100%; border-collapse:collapse; font-size:0.9rem; margin-bottom:20px;">
        <tr>
            <td style="padding:4px;"><strong>Reg No:</strong> <?= $patient['scheduleID'] ?></td>
            <td style="padding:4px; text-align:right;"><strong>Date:</strong> <?= date('Y-m-d') ?></td>
        </tr>
        <tr>
            <td style="padding:4px;"><strong>Name:</strong> <?= strtoupper($patient['fname'] . " " . $patient['lname']) ?></td>
            <td style="padding:4px; text-align:right;"><strong>Sex/Age:</strong> <?= $patient['gender'] ?> / <?= $patient['age'] ?> yrs</td>
        </tr>
        <tr>
            <td colspan="2" style="padding:4px;"><strong>Address:</strong> <?= $patient['address'] ?></td>
        </tr>
    </table>
    <?php endif; ?>

    <?php if ($mode === "cbc"): 
        $age = $patient['age'] ?? 0;
        $gender = $patient['gender'] ?? 'M';
    ?>
    <table style="width:100%; border:1px solid #000; border-collapse:collapse; font-size:0.9rem;">
        <thead>
            <tr style="background:#f2f2f2;">
                <th style="border:1px solid #000; padding:8px; text-align:left;">Test Name</th>
                <th style="border:1px solid #000; padding:8px; text-align:center;">Result</th>
                <th style="border:1px solid #000; padding:8px; text-align:center;">Normal Range</th>
                <th style="border:1px solid #000; padding:8px; text-align:center;">Status</th>
                <th style="border:1px solid #000; padding:8px; text-align:center;">Dev %</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $tests = [
                'wbc' => 'WBC Count', 'rbc' => 'RBC Count', 'hemoglobin' => 'Hemoglobin', 
                'hematocrit' => 'Hematocrit', 'platelets' => 'Platelet Count', 
                'mcv' => 'MCV', 'mch' => 'MCH', 'mchc' => 'MCHC'
            ];
            foreach ($tests as $key => $label): 
                $res = getAdvancedInterpretation($key, $data[$key], $age, $gender);
            ?>
            <tr>
                <td style="border:1px solid #000; padding:8px;"><?= $label ?></td>
                <td style="border:1px solid #000; padding:8px; text-align:center; font-weight:bold;"><?= $data[$key] ?></td>
                <td style="border:1px solid #000; padding:8px; text-align:center;"><?= $res['range'] ?></td>
                <td style="border:1px solid #000; padding:8px; text-align:center;"><?= $res['status'] ?></td>
                <td style="border:1px solid #000; padding:8px; text-align:center; background:#fafafa;"><?= $res['pct'] ?></td>
            </tr>
            <?php endforeach; ?>
            <tr>
                <td style="border:1px solid #000; padding:10px; font-weight:bold;">Remarks</td>
                <td colspan="4" style="border:1px solid #000; padding:10px;"><?= htmlspecialchars($data['remarks']) ?></td>
            </tr>
        </tbody>
    </table>
    
    <?php else: ?>
        <table style="width:100%; border:1px solid #000; border-collapse:collapse; font-size:0.95rem;">
            <tr><th style="border:1px solid #000; padding:10px; text-align:left; background:#f2f2f2; width:20%;">Findings</th><td style="border:1px solid #000; padding:10px;"><?= nl2br(htmlspecialchars($data['findings'])) ?></td></tr>
            <tr><th style="border:1px solid #000; padding:10px; text-align:left; background:#f2f2f2;">Impression</th><td style="border:1px solid #000; padding:10px; font-weight:bold;"><?= nl2br(htmlspecialchars($data['impression'])) ?></td></tr>
            <?php if (!empty($data['image_blob'])): 
                $base64Image = base64_encode($data['image_blob']);
            ?>
            <tr>
                <td colspan="2" style="border:1px solid #000; padding:20px; text-align:center;">
                    <img src="data:image/jpeg;base64,<?= $base64Image ?>" style="max-width:100%; border:1px solid #000;">
                </td>
            </tr>
            <?php endif; ?>
        </table>
    <?php endif; ?>

            <div style="margin-top:40px; display:flex; justify-content:space-around; text-align:center; font-size:0.8rem;">
        <div>
            <?php if (!empty($data['medtech_fname'])): ?>
                <div style="margin-bottom: 5px; font-weight: bold; font-size: 0.9rem;">
                    <?= strtoupper($data['medtech_fname'] . " " . $data['medtech_lname']) ?>
                </div>
                <div style="margin-bottom: 2px; font-size: 0.75rem;">
                    Lic No: <?= htmlspecialchars($data['medtech_license'] ?? 'N/A') ?>
                </div>
            <?php else: ?>
                <div style="margin-bottom: 5px; font-weight: bold; font-size: 0.9rem; color: transparent;">-</div>
                <div style="margin-bottom: 2px; font-size: 0.75rem; color: transparent;">-</div>
            <?php endif; ?>
            <p style="border-top:1px solid #000; padding-top:5px; width:200px; margin: 0 auto;">MedTech (Processed)</p>
        </div>

        <div>
            <?php if (!empty($data['val_fname'])): ?>
                <div style="margin-bottom: 5px; font-weight: bold; font-size: 0.9rem;">
                    <?= strtoupper($data['val_fname'] . " " . $data['val_lname']) ?>
                </div>
                <div style="margin-bottom: 2px; font-size: 0.75rem;">
                    Lic No: <?= htmlspecialchars($data['val_license'] ?? 'N/A') ?>
                </div>
            <?php else: ?>
                <div style="margin-bottom: 5px; font-weight: bold; font-size: 0.9rem; color: transparent;">-</div>
                <div style="margin-bottom: 2px; font-size: 0.75rem; color: transparent;">-</div>
            <?php endif; ?>
            <p style="border-top:1px solid #000; padding-top:5px; width:200px; margin: 0 auto;">MedTech (Validated)</p>
        </div>
    </div>
</div>
</div>
</div>
</div>