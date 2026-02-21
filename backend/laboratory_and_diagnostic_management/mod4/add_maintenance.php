<?php
session_start();
include '../../../SQL/config.php';

if (!isset($_SESSION['labtech']) || $_SESSION['labtech'] !== true) {
    header('Location: ' . BASE_URL . 'backend/login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $source = $_POST['source'] ?? '';
    $equipment = $_POST['equipment'] ?? '';
    $maintenance_date = $_POST['maintenance_date'] ?? '';
    $maintenance_type = $_POST['maintenance_type'] ?? '';
    $status = $_POST['status'] ?? '';
    $remarks = $_POST['remarks'] ?? '';

    if (empty($source) || empty($equipment) || empty($maintenance_date) || empty($maintenance_type) || empty($status)) {
        header('Location: maintenance.php?error=emptyfields');
        exit();
    }

    $query = "INSERT INTO maintenance_history (source, equipment, maintenance_date, maintenance_type, status, remarks) 
              VALUES (?, ?, ?, ?, ?, ?)";
    
    $stmt = $conn->prepare($query);
    if ($stmt) {
        $stmt->bind_param("ssssss", $source, $equipment, $maintenance_date, $maintenance_type, $status, $remarks);
        if ($stmt->execute()) {
            // Update the equipment status based on the maintenance log status
            if ($status === 'In Progress' || $status === 'Pending' || $source === 'Maintenance') {
                $newEquipmentStatus = ($status === 'Completed') ? 'Available' : 'Under Maintenance';
                $updateQuery = "UPDATE machine_equipments SET status = ? WHERE machine_name = ?";
                $updateStmt = $conn->prepare($updateQuery);
                if ($updateStmt) {
                    $updateStmt->bind_param("ss", $newEquipmentStatus, $equipment);
                    $updateStmt->execute();
                    $updateStmt->close();
                }
            }
            
            header('Location: maintenance.php?success=1');
        } else {
            header('Location: maintenance.php?error=sqlerror');
        }
        $stmt->close();
    } else {
        header('Location: maintenance.php?error=stmtfailed');
    }
} else {
    header('Location: maintenance.php');
    exit();
}
?>
