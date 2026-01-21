<?php
session_start();
include '../../../../SQL/config.php';

if ($_SERVER["REQUEST_METHOD"] == "POST" && !empty($_FILES['license_file']['tmp_name'])) {

    $employee_id = $_POST['employee_id'];
    
    $file_content = file_get_contents($_FILES['license_file']['tmp_name']);
    
    if ($_FILES['license_file']['size'] > 4194304) { 
        die("<script>alert('File is too large! Max 4MB.'); window.history.back();</script>");
    }

    $check_sql = "SELECT document_id FROM hr_employees_documents 
                  WHERE employee_id = ? AND document_type = 'License ID'";
    $stmt_check = $conn->prepare($check_sql);
    $stmt_check->bind_param("i", $employee_id);
    $stmt_check->execute();
    $result = $stmt_check->get_result();

    if ($result->num_rows > 0) {
    
        $sql = "UPDATE hr_employees_documents 
                SET file_blob = ?, uploaded_at = NOW() 
                WHERE employee_id = ? AND document_type = 'License ID'";
        
        $stmt = $conn->prepare($sql);
        $null = NULL; 
        $stmt->bind_param("bi", $null, $employee_id);
        $stmt->send_long_data(0, $file_content);
        
    } else {
        $doc_type = 'License ID';
        $sql = "INSERT INTO hr_employees_documents (employee_id, document_type, file_blob, uploaded_at) 
                VALUES (?, ?, ?, NOW())";
        
        $stmt = $conn->prepare($sql);
        $null = NULL;
        $stmt->bind_param("isb", $employee_id, $doc_type, $null);
        $stmt->send_long_data(2, $file_content);
    }

    if ($stmt->execute()) {
        echo "<script>
                alert('License updated successfully!');
                window.location.href = 'renew.php?id=$employee_id';
              </script>";
    } else {
        echo "Error: " . $conn->error;
    }

    $stmt->close();
    $conn->close();

} else {
    echo "<script>alert('Please select a file to upload.'); window.history.back();</script>";
}
?>