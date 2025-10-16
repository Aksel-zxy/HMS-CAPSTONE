<?php
if (!isset($conn)) {
    include __DIR__ . "/../../../../SQL/config.php";
}

$scheduleID = $_GET['scheduleID'] ?? null;
$testType   = $_GET['testType'] ?? null;

if (!$scheduleID || !$testType) {
    echo "<p class='text-danger'>Invalid request.</p>";
    exit;
}

$testType = strtolower(trim($testType));

// Determine table based on test type
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

// Map friendly names for display
$displayName = match ($mode) {
    "cbc" => "Complete Blood Count (CBC)",
    "xray" => "X-Ray",
    "mri" => "MRI Scan",
    "ct" => "CT Scan",
    default => ucfirst($testType),
};

// Render output
echo "<h4 style='margin-bottom:15px; color:#198754; font-family:Arial, sans-serif;'>
        🧪 Test Result: {$displayName}
      </h4>";

if ($mode === "cbc") {
    echo "
    <div class='table-responsive'>
      <table class='table table-bordered'>
        <tr><th>WBC</th><td>{$data['wbc']}</td></tr>
        <tr><th>RBC</th><td>{$data['rbc']}</td></tr>
        <tr><th>Hemoglobin</th><td>{$data['hemoglobin']}</td></tr>
        <tr><th>Hematocrit</th><td>{$data['hematocrit']}</td></tr>
        <tr><th>Platelets</th><td>{$data['platelets']}</td></tr>
        <tr><th>MCV</th><td>{$data['mcv']}</td></tr>
        <tr><th>MCH</th><td>{$data['mch']}</td></tr>
        <tr><th>MCHC</th><td>{$data['mchc']}</td></tr>
        <tr><th>Remarks</th><td>{$data['remarks']}</td></tr>
      </table>
    </div>
    ";
} else {
    // MRI, CT, and X-Ray share same structure
    echo "
    <div class='table-responsive'>
      <table class='table table-bordered'>
        <tr><th>Findings</th><td>{$data['findings']}</td></tr>
        <tr><th>Impression</th><td>{$data['impression']}</td></tr>
        <tr><th>Remarks</th><td>{$data['remarks']}</td></tr>
    ";

    // ✅ Display the image directly from the BLOB
    if (!empty($data['image_blob'])) {
        $base64Image = base64_encode($data['image_blob']);
        echo "<tr><th>Image</th><td><img src='data:image/jpeg;base64,{$base64Image}' style='max-width:100%; height:auto;'></td></tr>";
    } else {
        echo "<tr><th>Image</th><td><em>No image uploaded.</em></td></tr>";
    }

    echo "</table></div>";
}
?>
