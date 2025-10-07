<?php 
include '../../../SQL/config.php';
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

    // Split appointment date into day and time
    $appointmentDay  = date("l", strtotime($appointment_date));
    $appointmentTime = date("H:i:s", strtotime($appointment_date));

    // Map day of week to table columns
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
    $result = $stmt->get_result();
    $shift  = $result->fetch_assoc();

    if (!$shift || $shift['shift_status'] == 0) {
        echo "<script>
                alert('Doctor not scheduled on $appointmentDay');
                window.history.back();
              </script>";
        exit();
    }

    $shift_start = $shift['shift_start'];
    $shift_end   = $shift['shift_end'];

    // Convert to natural format
    $shift_start_natural = date("g:i A", strtotime($shift_start));
    $shift_end_natural   = date("g:i A", strtotime($shift_end));
    $appointmentTime_natural = date("g:i A", strtotime($appointmentTime));

    if ($appointmentTime < $shift_start || $appointmentTime > $shift_end) {
        echo "<script>
                alert('Doctor available only between {$shift_start_natural} and {$shift_end_natural}. You selected {$appointmentTime_natural}.');
                window.history.back();
              </script>";
        exit();
    }

    // 2️⃣ Validate no conflicting appointment
    $sql = "SELECT * FROM p_appointments 
            WHERE doctor_id = ? 
            AND appointment_date = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("is", $doctor_id, $appointment_date);
    $stmt->execute();
    $apptResult = $stmt->get_result();

    if ($apptResult->num_rows > 0) {
        echo "<script>
                alert('Doctor already has an appointment at this time.');
                window.history.back();
              </script>";
        exit();
    }

    // 3️⃣ Insert appointment
    $sql = "INSERT INTO p_appointments (patient_id, doctor_id, appointment_date, purpose, status, notes)
            VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param(
        "iissss",
        $data['patient_id'],
        $data['doctor_id'],
        $data['appointment_date'],
        $data['purpose'],
        $data['status'],
        $data['notes']
    );

    $success = $stmt->execute();

    // 4️⃣ Redirect based on who submitted
    $submitted_by = $_POST['submitted_by'] ?? 'patient';

    if ($submitted_by === 'admin') {
        header("Location: ../appointment.php?success=" . ($success ? "1" : "0"));
    } else {
        header("Location: ../user_panel/user_appointment.php?success=" . ($success ? "1" : "0"));
    }

    exit();
}
?>