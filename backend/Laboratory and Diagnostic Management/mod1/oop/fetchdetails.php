<?php
require_once __DIR__ . '../../../../../SQL/config.php';

class Patient
{
    private $conn;
    private $appointmentsTable = "p_appointments";
    private $patientTable = "patientinfo";

    public function __construct($db)
    {
        $this->conn = $db;
    }
    public function getAllPatients()
    {
        $query = "
        SELECT p.*, a.*
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

class Schedule {
    private $conn;

    public function __construct($conn) {
        $this->conn = $conn;
    }

    public function save($patient_id, $service_id, $laboratorist_id, $schedule_datetime) {

        $scheduleDate = date('Y-m-d', strtotime($schedule_datetime));
        $scheduleTime = date('H:i:s', strtotime($schedule_datetime));

        // Get the service name
        $stmtService = $this->conn->prepare("SELECT serviceName FROM dl_services WHERE serviceID = ?");
        $stmtService->bind_param("i", $service_id);
        $stmtService->execute();
        $resultService = $stmtService->get_result();
        $serviceName = $resultService->fetch_assoc()['serviceName'] ?? null;
        $stmtService->close();

        if (!$serviceName) {
            return false;
        }

        // Insert into dl_schedule
        $stmt = $this->conn->prepare("
            INSERT INTO dl_schedule (patientID, serviceName, employee_id, scheduleDate, scheduleTime) 
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->bind_param("isiss", $patient_id, $serviceName, $laboratorist_id, $scheduleDate, $scheduleTime);
        $result = $stmt->execute();
        $stmt->close();

        if ($result) {
            // ✅ Update appointment_date in p_appointments
            $stmtUpdate = $this->conn->prepare("
                UPDATE p_appointments 
                SET appointment_date = ? 
                WHERE patient_id = ?
            ");
            $stmtUpdate->bind_param("si", $schedule_datetime, $patient_id);
            $stmtUpdate->execute();
            $stmtUpdate->close();
        }

        return $result;
    }
}


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $schedule = new Schedule($conn);

    $patient_id       = $_POST['patient_id'] ?? null;
    $service_id       = $_POST['service_id'] ?? null;
    $laboratorist_id  = $_POST['laboratorist_id'] ?? null;
    $schedule_datetime = $_POST['schedule_datetime'] ?? null;

    if ($patient_id && $service_id && $laboratorist_id && $schedule_datetime) {
        if ($schedule->save($patient_id, $service_id, $laboratorist_id, $schedule_datetime)) {
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