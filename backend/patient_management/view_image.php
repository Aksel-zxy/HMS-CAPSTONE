<?php
include '../../SQL/config.php';

if (!isset($_GET['type']) || !isset($_GET['patient_id'])) {
    exit('Missing parameters.');
}

$type = $_GET['type'];
$patient_id = $_GET['patient_id'];

if (!in_array($type, ['ct', 'mri', 'xray', 'history'])) {
    exit('Invalid image type');
}

// Determine table and correct column name
switch ($type) {
    case 'ct':
        $table = 'dl_lab_ct';
        $column = 'patientID';
        break;
    case 'mri':
        $table = 'dl_lab_mri';
        $column = 'patientID';
        break;
    case 'xray':
        $table = 'dl_lab_xray';
        $column = 'patientID';
        break;
    case 'history':
        $table = 'p_previous_medical_records';
        $column = 'patient_id';
        break;
}

// Prepare query safely
$stmt = $conn->prepare("SELECT image_blob FROM $table WHERE $column = ?");
$stmt->bind_param("i", $patient_id);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();

if ($row && !empty($row['image_blob'])) {
    header("Content-Type: image/jpeg");
    echo $row['image_blob'];
} else {
    echo "No image found.";
}
?>