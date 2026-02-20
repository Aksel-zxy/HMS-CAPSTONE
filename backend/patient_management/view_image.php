<?php
include '../../SQL/config.php';


if (!isset($_GET['type']) || !isset($_GET['patient_id'])) {
    exit('Missing parameters.');
}

$type = $_GET['type'];
$patient_id = $_GET['patient_id'];

if (!in_array($type, ['ct', 'mri', 'xray'])) {
    exit('Invalid image type');
}

switch ($type) {
    case 'ct':
        $table = 'dl_lab_ct';
        break;
    case 'mri':
        $table = 'dl_lab_mri';
        break;
    case 'xray':
        $table = 'dl_lab_xray';
        break;
}

$stmt = $conn->prepare("SELECT image_blob FROM $table WHERE patientID = ?");
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