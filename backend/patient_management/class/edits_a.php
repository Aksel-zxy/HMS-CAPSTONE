<?php 
session_start(); // Make sure session is started
include '../../../SQL/config.php';
include 'logs.php'; // Include logs

// Check session
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Get logged-in user ID
    $user_id = $_SESSION['user_id'];

    // Sanitize inputs
    $appointment_id   = intval($_POST['appointment_id'] ?? 0);
    $patient_id       = intval($_POST['patient_id'] ?? 0);
    $doctor_id        = intval($_POST['doctor'] ?? 0);
    $appointment_date = $_POST['appointment_date'] ?? '';
    $purpose          = trim($_POST['purpose'] ?? '');
    $status           = trim($_POST['status'] ?? '');
    $notes            = trim($_POST['notes'] ?? '');

    // Validate required fields
    if (!$appointment_id || !$patient_id || !$doctor_id || !$appointment_date || !$purpose || !$status) {
        echo "Missing required fields.";
        exit();
    }

    // Prepare update query
    $query = "UPDATE p_appointments 
              SET patient_id = ?, doctor_id = ?, appointment_date = ?, purpose = ?, status = ?, notes = ? 
              WHERE appointment_id = ?";
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        die("Prepare failed: " . $conn->error);
    }

    $stmt->bind_param(
        "iissssi", 
        $patient_id, 
        $doctor_id, 
        $appointment_date, 
        $purpose, 
        $status, 
        $notes, 
        $appointment_id
    );

    if ($stmt->execute()) {
        // Log the update action
        logAction($conn, $user_id, 'UPDATE_APPOINTMENT', $patient_id);

        header("Location: ../patient_dashboard.php?success=Appointment+updated");
        exit();
    } else {
        echo "Error updating appointment: " . htmlspecialchars($stmt->error);
    }

    $stmt->close();
}
?>