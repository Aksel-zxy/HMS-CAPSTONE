<?php
require '../../../SQL/config.php';

if (!isset($_GET['leave_id'])) {
    die("No file specified.");
}

$leave_id = intval($_GET['leave_id']);

$stmt = $conn->prepare("SELECT leave_type, medical_cert FROM hr_leave WHERE leave_id = ?");
$stmt->bind_param("i", $leave_id);
$stmt->execute();
$stmt->bind_result($leaveType, $fileBlob);
$stmt->fetch();
$stmt->close();

if (!$fileBlob) {
    die("File not found.");
}

// Detect MIME type from binary
$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime = $finfo->buffer($fileBlob);

// Map MIME to extension
$mimeToExt = [
    'application/pdf' => 'pdf',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
    'image/jpeg' => 'jpg',
    'image/png' => 'png'
];

$ext = isset($mimeToExt[$mime]) ? $mimeToExt[$mime] : 'bin';

// Clean filename based on leave_type
$downloadName = preg_replace("/[^a-zA-Z0-9_-]/", "_", $leaveType ?: "medical_cert") . '.' . $ext;

header('Content-Type: ' . $mime);
header('Content-Disposition: attachment; filename="' . $downloadName . '"');
header('Content-Length: ' . strlen($fileBlob));

echo $fileBlob;
exit;
