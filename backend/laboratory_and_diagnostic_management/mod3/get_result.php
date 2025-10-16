<?php
if (!isset($conn)) {
    include __DIR__ . "../../../../SQL/config.php";
}

$scheduleID = $_GET['scheduleID'] ?? null;
$testType   = $_GET['testType'] ?? null;

if (!$scheduleID || !$testType) {
    echo "<p class='text-danger'>Invalid request.</p>";
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

// Run query
$query = "SELECT * FROM $table WHERE scheduleID = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $scheduleID);
$stmt->execute();
$result = $stmt->get_result();
$data = $result->fetch_assoc();

if (!$data) {
    echo "<p class='text-muted'>No result found for this test. (Test is not done yet)</p>";
    exit;
}

// Friendly display name
switch ($mode) {
    case "cbc":  $displayName = "Complete Blood Count (CBC)"; break;
    case "xray": $displayName = "X-Ray"; break;
    case "mri":  $displayName = "MRI Scan"; break;
    case "ct":   $displayName = "CT Scan"; break;
    default:     $displayName = ucfirst($testType); break;
}

// Get patient info
$queryPatient = "SELECT p.*, s.scheduleID 
                 FROM dl_schedule s 
                 LEFT JOIN patientinfo p ON s.patientId = p.patient_id 
                 WHERE s.scheduleID = ?";
$stmtPatient = $conn->prepare($queryPatient);
$stmtPatient->bind_param("i", $scheduleID);
$stmtPatient->execute();
$resPatient = $stmtPatient->get_result();
$patient = $resPatient->fetch_assoc();

echo "
<div style='
    font-family: Arial, sans-serif;
    margin-bottom: 50px;
    padding: 25px;
    border: 2px solid #000;
    border-radius: 10px;
    max-width: 800px;
    margin-left: auto;
    margin-right: auto;
    background-color: #fff;
    box-shadow: 0 0 8px rgba(0,0,0,0.15);
'>
    <h2 style='text-align:center; margin:0; font-size:1.5rem;'>Dr. Eduardo V. Roquero Memorial Hospital</h2>
    <p style='text-align:center; font-size:0.9rem; margin:5px 0;'>Purok 7, Area F, Brgy. San Pedro, City of San Jose Del Monte, Bulacan</p>
    <p style='text-align:center; font-size:0.9rem; margin:5px 0;'>DOH LICENSE NO. -------------</p>
    <hr style='border: 1.5px solid #000; margin: 15px 0;'>
";

// ✅ Patient Information
if ($patient) {
    echo "
    <table style='width:100%; border-collapse:collapse; font-size:0.95rem; margin-bottom:15px;'>
        <tr>
            <td><strong>Reg No.:</strong> {$patient['scheduleID']}</td>
            <td><strong>Date:</strong> " . date('Y-m-d') . "</td>
        </tr>
        <tr>
            <td><strong>Name:</strong> {$patient['fname']} {$patient['mname']} {$patient['lname']}</td>
            <td><strong>Sex:</strong> {$patient['gender']}</td>
        </tr>
        <tr>
            <td><strong>Age:</strong> {$patient['age']}</td>
            <td><strong>Civil Status:</strong> {$patient['civil_status']}</td>
        </tr>
        <tr>
            <td colspan='2'><strong>Address:</strong> {$patient['address']}</td>
        </tr>
    </table>
    ";
}

echo "<h4 style='margin-bottom:15px; text-align:center; color:#198754;'>{$displayName}</h4>";

// ✅ LAB TABLE OUTPUT
if ($mode === "cbc") {
    echo "
    <table style='width:100%; border:2px solid #000; border-collapse:collapse; font-size:0.95rem;'>
        <tr style='background:#f8f8f8;'>
            <th style='border:2px solid #000; padding:8px; text-align:left;'>Test</th>
            <th style='border:2px solid #000; padding:8px; text-align:center;'>Result</th>
            <th style='border:2px solid #000; padding:8px; text-align:center;'>Normal Values</th>
        </tr>
        <tr><td style='border:1.5px solid #000; padding:6px;'>WBC</td><td style='border:1.5px solid #000; text-align:center;'>{$data['wbc']}</td><td style='border:1.5px solid #000; text-align:center;'>4.0-10 x10⁹/L</td></tr>
        <tr><td style='border:1.5px solid #000; padding:6px;'>RBC</td><td style='border:1.5px solid #000; text-align:center;'>{$data['rbc']}</td><td style='border:1.5px solid #000; text-align:center;'>M:4.5-5.0 F:4.0-4.5 g/L</td></tr>
        <tr><td style='border:1.5px solid #000; padding:6px;'>Hemoglobin</td><td style='border:1.5px solid #000; text-align:center;'>{$data['hemoglobin']}</td><td style='border:1.5px solid #000; text-align:center;'>M:140-180 F:120-160</td></tr>
        <tr><td style='border:1.5px solid #000; padding:6px;'>Hematocrit</td><td style='border:1.5px solid #000; text-align:center;'>{$data['hematocrit']}</td><td style='border:1.5px solid #000; text-align:center;'>M:0.40-0.54 F:0.37-0.47</td></tr>
        <tr><td style='border:1.5px solid #000; padding:6px;'>Platelets</td><td style='border:1.5px solid #000; text-align:center;'>{$data['platelets']}</td><td style='border:1.5px solid #000; text-align:center;'>150-450 x10⁹/L</td></tr>
        <tr><td style='border:1.5px solid #000; padding:6px;'>MCV</td><td style='border:1.5px solid #000; text-align:center;'>{$data['mcv']}</td><td style='border:1.5px solid #000; text-align:center;'>80-97 fl</td></tr>
        <tr><td style='border:1.5px solid #000; padding:6px;'>MCH</td><td style='border:1.5px solid #000; text-align:center;'>{$data['mch']}</td><td style='border:1.5px solid #000; text-align:center;'>26.5-33.5 pg</td></tr>
        <tr><td style='border:1.5px solid #000; padding:6px;'>MCHC</td><td style='border:1.5px solid #000; text-align:center;'>{$data['mchc']}</td><td style='border:1.5px solid #000; text-align:center;'>33-36%</td></tr>
        <tr><td style='border:1.5px solid #000; padding:6px;'>Remarks</td><td colspan='2' style='border:1.5px solid #000; text-align:center;'>{$data['remarks']}</td></tr>
    </table>
    ";
} else {
    echo "
    <table style='width:100%; border:2px solid #000; border-collapse:collapse; font-size:0.95rem;'>
        <tr><th style='border:2px solid #000; padding:8px;'>Findings</th><td style='border:2px solid #000; padding:8px;'>{$data['findings']}</td></tr>
        <tr><th style='border:2px solid #000; padding:8px;'>Impression</th><td style='border:2px solid #000; padding:8px;'>{$data['impression']}</td></tr>
        <tr><th style='border:2px solid #000; padding:8px;'>Remarks</th><td style='border:2px solid #000; padding:8px;'>{$data['remarks']}</td></tr>";

// ✅ Image from database (using base64)
    if (!empty($data['image_blob'])) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_buffer($finfo, $data['image_blob']);
        finfo_close($finfo);
        $base64Image = base64_encode($data['image_blob']);

        echo "
        <tr>
            <th style='border:2px solid #000; padding:8px;'>Image</th>
            <td style='border:2px solid #000; padding:8px; text-align:center;'>
                <img src='data:{$mimeType};base64,{$base64Image}' 
                     style='max-width:100%; border:2px solid #000; border-radius:8px;'>
            </td>
        </tr>";
    }

    echo "</table>";
}

// ✅ Footer section
echo "
<div style='margin-top:40px; display:flex; justify-content:space-between; text-align:center;'>
    <div>
        <p style='margin:0;'>PROCESSED BY:</p>
        <p style='font-weight:bold; margin:3px 0;'>JAHZEEL EVI F. LARIOSA</p>
        <small>Medical Technologist - PRC No. --------</small>
    </div>
    <div>
        <p style='margin:0;'>VALIDATED BY:</p>
        <p style='font-weight:bold; margin:3px 0;'>CASSIE CYRIS C. FERNANDO</p>
        <small>Medical Technologist - PRC No. ------------</small>
    </div>
    <div>
        <p style='font-weight:bold; margin:0;'>ALMA N. AQUILIZAN, MD, FPSP</p>
        <small>Pathologist - PRC No. --------------</small>
    </div>
</div>
</div>";
?>
