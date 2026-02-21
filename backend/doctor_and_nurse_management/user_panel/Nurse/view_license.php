<?php
session_start();
include '../../../../SQL/config.php';

if (!isset($_SESSION['employee_id'])) {
    die("Unauthorized access.");
}

$employee_id = $_GET['employee_id'] ?? null;

if ($employee_id) {
    $sql = "SELECT file_blob FROM hr_employees_documents 
            WHERE employee_id = ? AND document_type = 'License ID' LIMIT 1";
            
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $employee_id);
    $stmt->execute();
    $stmt->store_result();
    $stmt->bind_result($file_content);
    $stmt->fetch();

    if ($stmt->num_rows > 0 && !empty($file_content)) {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime_type = $finfo->buffer($file_content);

        header("Content-Type: " . $mime_type);
        header("Content-Disposition: inline; filename=license_view"); 
        echo $file_content;
    } else {
        echo "No document found or file is empty.";
    }
    
    $stmt->close();
} else {
    echo "Invalid Request.";
}
$conn->close();
?>