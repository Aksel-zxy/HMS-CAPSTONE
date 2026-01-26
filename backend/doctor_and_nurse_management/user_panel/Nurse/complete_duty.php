<?php
session_start();
include '../../../../SQL/config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['duty_id'])) {
        $duty_id = $_POST['duty_id'];
        
        $query = "UPDATE duty_assignments SET status = 'Completed' WHERE duty_id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $duty_id);
        
        if ($stmt->execute()) {
            echo "success";
        } else {
            echo "error";
        }
        $stmt->close();
    }
}
?>