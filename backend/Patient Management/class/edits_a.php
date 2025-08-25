<?php 
include '../../../SQL/config.php';



// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $appointment_id   = $_POST['appointment_id']; // <-- Missing before
    $patient_id = $_POST['patient_id'];
    $doctor_id = $_POST['doctor'];
    $appointment_date = $_POST['appointment_date'];
    $purpose = $_POST['purpose'];
    $status = $_POST['status'];
    $notes = $_POST['notes'];

    $query = "UPDATE p_appointments 
              SET patient_id = ?, doctor_id = ?, appointment_date = ?, purpose = ?, status = ?, notes = ? 
              WHERE appointment_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("iissssi", $patient_id, $doctor_id, $appointment_date, $purpose, $status, $notes, $appointment_id);

    if ($stmt->execute()) {
        header("Location: ../patient_dashboard.php?success=Appointment+updated");
        exit();
    } else {
        echo "Error updating appointment.";
    }
}

?>