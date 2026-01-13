<?php
session_start(); // 

include '../../../SQL/config.php';
include 'logs.php'; // 
require_once 'patient.php';

$patient = new Patient($conn);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Collect form data
    $data = [
        'patient_id'       => $_POST["patient_id"] ?? '',
        'doctor_id'        => $_POST["doctor"] ?? '',
        'appointment_date' => $_POST["appointment_date"] ?? '',
        'purpose'          => $_POST["purpose"] ?? '',
        'status'           => $_POST["status"] ?? '',
        'notes'            => $_POST["notes"] ?? '',
    ];

    $doctor_id = $data['doctor_id'];
    $appointment_date = $data['appointment_date'];

    // Split appointment date
    $appointmentDay  = date("l", strtotime($appointment_date));
    $appointmentTime = date("H:i:s", strtotime($appointment_date));

    $dayMap = [
        "Monday"    => ["mon_start", "mon_end", "mon_status"],
        "Tuesday"   => ["tue_start", "tue_end", "tue_status"],
        "Wednesday" => ["wed_start", "wed_end", "wed_status"],
        "Thursday"  => ["thu_start", "thu_end", "thu_status"],
        "Friday"    => ["fri_start", "fri_end", "fri_status"],
        "Saturday"  => ["sat_start", "sat_end", "sat_status"],
        "Sunday"    => ["sun_start", "sun_end", "sun_status"]
    ];

    if (!isset($dayMap[$appointmentDay])) {
        echo "<script>alert('Invalid appointment day.'); window.history.back();</script>";
        exit();
    }

    $cols = $dayMap[$appointmentDay];

    // 1️⃣ Validate doctor schedule
    $sql = "SELECT {$cols[0]} AS shift_start, {$cols[1]} AS shift_end, {$cols[2]} AS shift_status 
            FROM shift_scheduling 
            WHERE employee_id = ? 
            ORDER BY created_at DESC LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $doctor_id);
    $stmt->execute();
    $shift = $stmt->get_result()->fetch_assoc();

    if (!$shift || $shift['shift_status'] == 0) {
        echo "<script>alert('Doctor not scheduled on $appointmentDay'); window.history.back();</script>";
        exit();
    }

    if ($appointmentTime < $shift['shift_start'] || $appointmentTime > $shift['shift_end']) {
        echo "<script>alert('Doctor not available at this time.'); window.history.back();</script>";
        exit();
    }

    // 2️⃣ Check conflict
    $stmt = $conn->prepare("
        SELECT 1 FROM p_appointments 
        WHERE doctor_id = ? AND appointment_date = ?
    ");
    $stmt->bind_param("is", $doctor_id, $appointment_date);
    $stmt->execute();

    if ($stmt->get_result()->num_rows > 0) {
        echo "<script>alert('Doctor already has an appointment at this time.'); window.history.back();</script>";
        exit();
    }

    // 3️⃣ Insert appointment
    $stmt = $conn->prepare("
        INSERT INTO p_appointments 
        (patient_id, doctor_id, appointment_date, purpose, status, notes)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmt->bind_param(
        "iissss",
        $data['patient_id'],
        $doctor_id,
        $appointment_date,
        $data['purpose'],
        $data['status'],
        $data['notes']
    );

    $success = $stmt->execute();

    // ✅ 4️⃣ LOG AFTER SUCCESSFUL INSERT
    if ($success) {
        $user_id = $_SESSION['user_id'] ?? null;

        if ($user_id) {
            logAction(
                $conn,
                $user_id,
                'CREATE_APPOINTMENT',
                $data['patient_id']
            );
        }
    }

    // 5️⃣ Redirect
    $submitted_by = $_POST['submitted_by'] ?? 'patient';

    if ($submitted_by === 'admin') {
        header("Location: ../appointment.php?success=" . ($success ? "1" : "0"));
    } else {
        header("Location: ../user_panel/user_appointment.php?success=" . ($success ? "1" : "0"));
    }

    exit();
}
?>