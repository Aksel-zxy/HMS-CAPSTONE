<?php
require '../../../SQL/config.php';

$id = intval($_GET['id'] ?? 0); // ✅ get id safely
if ($id <= 0) {
    die("Invalid file request.");
}

$stmt = $conn->prepare("SELECT document_type, file_blob FROM hr_employees_documents WHERE document_id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$stmt->bind_result($documentType, $fileBlob);

if ($stmt->fetch()) {
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_buffer($finfo, $fileBlob);
    finfo_close($finfo);

    header("Content-Type: $mime");
    header("Content-Disposition: inline; filename=\"" . basename($documentType) . "\"");
    echo $fileBlob;
} else {
    echo "File not found.";
}

$stmt->close();
$conn->close();
?>
