<?php
require '../../../SQL/config.php';

// Check if 'id' is provided
if (!isset($_GET['id'])) {
    die("No file specified.");
}

$id = intval($_GET['id']);

// Prepare and execute query
$stmt = $conn->prepare("SELECT document_type, file_blob FROM hr_applicant_documents WHERE document_id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$stmt->bind_result($documentType, $fileBlob);
$stmt->fetch();
$stmt->close();

// Check if file exists
if (!$fileBlob) {
    die("File not found.");
}

// Detect MIME type from binary
$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime = $finfo->buffer($fileBlob);

// Map MIME to file extension
$mimeToExt = [
    'application/pdf' => 'pdf',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
    'image/jpeg' => 'jpg',
    'image/png' => 'png'
];
$ext = isset($mimeToExt[$mime]) ? $mimeToExt[$mime] : 'bin';

// Clean filename based on document_type
$downloadName = preg_replace("/[^a-zA-Z0-9_-]/", "_", $documentType) . '.' . $ext;

// Output headers and file
header('Content-Type: ' . $mime);
header('Content-Disposition: attachment; filename="' . $downloadName . '"');
header('Content-Length: ' . strlen($fileBlob));

echo $fileBlob;
exit;
