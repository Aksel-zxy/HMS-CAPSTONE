<?php
require_once __DIR__ . '../../../../../SQL/config.php';

class Patient
{
    public $conn;
    public $appointmentsTable = "p_appointments";
    public $patientTable = "patientinfo";

    public function __construct($db)
    {
        $this->conn = $db;
    }
    public function getAllPatients()
    {
        $query = "
        SELECT p.*, a.appointment_id, a.appointment_date, a.notes, a.purpose, a.status
        FROM {$this->patientTable} p
        INNER JOIN {$this->appointmentsTable} a 
            ON p.patient_id = a.patient_id
        WHERE a.purpose = 'laboratory'
    ";
        $result = $this->conn->query($query);
        return $result->fetch_all(MYSQLI_ASSOC);
    }

    public function getPatientById($id)
    {
        $stmt = $this->conn->prepare("
            SELECT p.*, a.*
            FROM {$this->patientTable} p
            INNER JOIN {$this->appointmentsTable} a 
                ON p.patient_id = a.patient_id
            WHERE p.patient_id = ?
        ");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_assoc();
    }
}

class Schedule
{
    private $conn;

    public function __construct($conn)
    {
        $this->conn = $conn;
    }

    public function save($patient_id, $service_id, $laboratorist_id, $schedule_datetime, $appointment_id)
    {
        $scheduleDate = date('Y-m-d', strtotime($schedule_datetime));
        $scheduleTime = date('H:i:s', strtotime($schedule_datetime));

        // Get service name
        $stmtService = $this->conn->prepare("SELECT serviceName FROM dl_services WHERE serviceID = ?");
        $stmtService->bind_param("i", $service_id);
        $stmtService->execute();
        $resultService = $stmtService->get_result();
        $serviceName = $resultService->fetch_assoc()['serviceName'] ?? null;
        $stmtService->close();

        if (!$serviceName) return false;

        // Insert into dl_schedule (with appointment_id ✅)
        $stmt = $this->conn->prepare("
        INSERT INTO dl_schedule (appointment_id, patientID, serviceName, employee_id, scheduleDate, scheduleTime, status) 
        VALUES (?, ?, ?, ?, ?, ?, 'Processing')
    ");
        $stmt->bind_param("iissss", $appointment_id, $patient_id, $serviceName, $laboratorist_id, $scheduleDate, $scheduleTime);
        $result = $stmt->execute();
        $stmt->close();

        return $result;
    }
}

function getLatestSchedule($conn, $appointment_id)
{
    $stmt = $conn->prepare("
        SELECT status, cancel_reason, scheduleDate, scheduleTime
        FROM dl_schedule
        WHERE appointment_id = ?
        ORDER BY scheduleID DESC
        LIMIT 1
    ");
    $stmt->bind_param("i", $appointment_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    return $row ?: null; // return null if no record
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $schedule = new Schedule($conn);

    $patient_id       = $_POST['patient_id'] ?? null;
    $service_id       = $_POST['service_id'] ?? null;
    $laboratorist_id  = $_POST['laboratorist_id'] ?? null;
    $schedule_datetime = $_POST['schedule_datetime'] ?? null;

    if ($patient_id && $service_id && $laboratorist_id && $schedule_datetime) {
        if ($schedule->save($patient_id, $service_id, $laboratorist_id, $schedule_datetime, $_POST['appointment_id'] ?? null)) {
            header("Location: ../doctor_referral.php?success=1");
            exit;
        } else {
            echo "Failed to save schedule.";
        }
    } else {
        echo "Missing required fields.";
    }
} 
// else {
//     echo "Invalid request.";
// }