<?php
session_start();
include '../../../../SQL/config.php';

// Ensure only logged-in doctors can access
if (!isset($_SESSION['employee_id']) || $_SESSION['profession'] !== 'Doctor') {
    header('Location: ../../login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $result_id = $_POST['result_id'] ?? null;

    // Fetch doctor name from database using employee_id in session
    $doctor_id = $_SESSION['employee_id'];
    $stmt = $conn->prepare("SELECT CONCAT(first_name, ' ', last_name) AS full_name FROM hr_employees WHERE employee_id = ?");
    $stmt->bind_param("i", $doctor_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $doctor = $result->fetch_assoc();
    $doctor_name = $doctor['full_name'] ?? '';

    // Validate before updating
    if ($result_id && !empty($doctor_name)) {
        $update = $conn->prepare("UPDATE dl_results SET received_by = ? WHERE resultID = ?");
        $update->bind_param("si", $doctor_name, $result_id);

        if ($update->execute()) {
            header("Location: doctor_duty.php?msg=accepted");
            exit;
        } else {
            echo "❌ Failed to accept result: " . $update->error;
        }
    } else {
        echo "⚠️ Invalid data — missing result ID or doctor name.<br>";
        echo "Debug: result_id = " . htmlspecialchars($result_id) . ", doctor_name = " . htmlspecialchars($doctor_name);
    }
}
?>
