<?php
session_start();
include '../../SQL/config.php';
require_once 'class/patient.php';
require_once 'class/caller.php';

// Check session
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}
if (!isset($_SESSION['patient']) || $_SESSION['patient'] !== true) {
    header('Location: login.php');
    exit();
}

// Get appointment ID from URL
if (!isset($_GET['appointment_id']) || empty($_GET['appointment_id'])) {
    echo "Invalid request.";
    exit();
}
$appointment_id = intval($_GET['appointment_id']);

// Load appointment details
$appointmentObj = new Caller($conn);
$appointment = $appointmentObj->getAppointmentById($appointment_id);

if (!$appointment) {
    echo "Appointment not found.";
    exit();
}

// Load doctors & patients for dropdowns
$getDoctors = new Caller($conn);
$doctors = $getDoctors->getDoctors();

$patientObj = new Patient($conn);
$patients = $patientObj->getAllPatients();



?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Appointment</title>
    <link rel="stylesheet" href="assets/CSS/bootstrap.min.css">
</head>

<body class="container mt-5">
    <h2>Edit Appointment</h2>
    <form method="POST" action="class/edits_a.php">

        <!-- Hidden Appointment ID -->
        <input type="hidden" name="appointment_id" value="<?php echo $appointment_id; ?>">

        <!-- Patient -->
        <div class="mb-3">
            <label for="patient_id" class="form-label">Patient</label>
            <select class="form-select" id="patient_id" name="patient_id" required>
                <?php
                if ($patients && $patients->num_rows > 0) {
                    while ($p = $patients->fetch_assoc()) {
                        $selected = ($p['patient_id'] == $appointment['patient_id']) ? "selected" : "";
                        $displayName = "{$p['fname']} {$p['mname']} {$p['lname']}";
                        echo "<option value='{$p['patient_id']}' $selected>{$displayName}</option>";
                    }
                }
                ?>
            </select>
        </div>

        <!-- Doctor -->
        <div class="mb-3">
            <label for="doctor" class="form-label">Doctor</label>
            <select class="form-select" id="doctor" name="doctor" required>
                <?php
                if ($doctors && $doctors->num_rows > 0) {
                    while ($d = $doctors->fetch_assoc()) {
                        $doctorName = "{$d['first_name']} {$d['last_name']}";
                        $selected = ($d['employee_id'] == $appointment['doctor_id']) ? "selected" : "";
                        echo "<option value='{$d['employee_id']}' $selected>{$doctorName} - {$d['specialization']}</option>";
                    }
                } else {
                    echo "<option value=''>No doctors available</option>";
                }
                ?>
            </select>
        </div>

        <!-- Date & Time -->
        <div class="mb-3">
            <label for="appointment_date" class="form-label">Appointment Date & Time</label>
            <input type="datetime-local" class="form-control" id="appointment_date" name="appointment_date"
                value="<?php echo date('Y-m-d\TH:i', strtotime($appointment['appointment_date'])); ?>" required>
        </div>

        <!-- Purpose -->
        <div class="mb-3">
            <label for="purpose" class="form-label">Purpose</label>
            <select class="form-select" id="purpose" name="purpose" required>
                <?php
                $purposes = ["Labor","Check-up","consultation","Cardiology","Laboratory","OB-Gyne","Pediatric","Psychiatric"];
                foreach ($purposes as $p) {
                    $selected = ($p == $appointment['purpose']) ? "selected" : "";
                    echo "<option value='{$p}' $selected>{$p}</option>";
                }
                ?>
            </select>
        </div>

        <!-- Status -->
        <div class="mb-3">
            <label for="status" class="form-label">Status</label>
            <select class="form-select" id="status" name="status" required>
                <option value="Scheduled" <?php echo ($appointment['status'] == 'Scheduled') ? "selected" : ""; ?>>
                    Scheduled</option>
                <option value="Completed" <?php echo ($appointment['status'] == 'Completed') ? "selected" : ""; ?>>
                    Completed</option>
                <option value="Cancelled" <?php echo ($appointment['status'] == 'Cancelled') ? "selected" : ""; ?>>
                    Cancelled</option>
            </select>
        </div>

        <!-- Notes -->
        <div class="mb-3">
            <label for="notes" class="form-label">Notes</label>
            <textarea class="form-control" id="notes" name="notes"
                rows="3"><?php echo htmlspecialchars($appointment['notes']); ?></textarea>
        </div>

        <button type="submit" class="btn btn-success">Update Appointment</button>
        <a href="patient_dashboard.php" class="btn btn-secondary">Cancel</a>
    </form>
</body>

</html>