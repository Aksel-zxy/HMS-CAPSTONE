<?php
require_once __DIR__ . '../../../../../SQL/config.php';
require_once 'roommanager.php';

// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

/**
 * Show JS alert and exit
 */
function alertAndExit($message)
{
    echo "<script>alert(" . json_encode($message) . "); history.back();</script>";
    exit;
}

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
        return $this->conn->query($query)->fetch_all(MYSQLI_ASSOC);
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

        // Get service details including room_type
        $stmtService = $this->conn->prepare("
            SELECT serviceName, room_type 
            FROM dl_services 
            WHERE serviceID = ?
        ");
        if (!$stmtService) {
            alertAndExit("Database Error: " . $this->conn->error);
        }
        $stmtService->bind_param("i", $service_id);
        if (!$stmtService->execute()) {
            alertAndExit("Database Error: " . $stmtService->error);
        }
        $service = $stmtService->get_result()->fetch_assoc();
        $stmtService->close();

        if (!$service) {
            alertAndExit("Service not found.");
        }

        // Get available room
        $roomManager = new RoomManager($this->conn);
        $room = $roomManager->getAvailableRoom($service['room_type']);

        if (!$room) {
            alertAndExit("No available room for this service.");
        }

        $roomID = (int)$room['roomID'];

        // Insert schedule
        $stmt = $this->conn->prepare("
            INSERT INTO dl_schedule 
            (appointment_id, patientID, serviceName, employee_id, scheduleDate, scheduleTime, room_id, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, 'Processing')
        ");
        if (!$stmt) {
            alertAndExit("Database Error: " . $this->conn->error);
        }

        $stmt->bind_param(
            "iisissi",
            $appointment_id,
            $patient_id,
            $service['serviceName'],
            $laboratorist_id,
            $scheduleDate,
            $scheduleTime,
            $roomID
        );

        if (!$stmt->execute()) {
            alertAndExit("Database Error: " . $stmt->error);
        }

        $stmt->close();

        // Mark room as occupied
        $roomManager->occupyRoom($roomID);

        return true;
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
    if (!$stmt) {
        alertAndExit("Database Error: " . $conn->error);
    }
    $stmt->bind_param("i", $appointment_id);
    if (!$stmt->execute()) {
        alertAndExit("Database Error: " . $stmt->error);
    }
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $result ?: null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $schedule = new Schedule($conn);

    if (
        !empty($_POST['patient_id']) &&
        !empty($_POST['service_id']) &&
        !empty($_POST['laboratorist_id']) &&
        !empty($_POST['schedule_datetime']) &&
        !empty($_POST['appointment_id'])
    ) {
        $success = $schedule->save(
            (int)$_POST['patient_id'],
            (int)$_POST['service_id'],
            (int)$_POST['laboratorist_id'],
            $_POST['schedule_datetime'],
            (int)$_POST['appointment_id']
        );

        if ($success) {
            echo "<script>alert('Schedule saved successfully'); window.location='../doctor_referral.php';</script>";
            exit;
        }
    } else {
        alertAndExit("Missing required fields.");
    }
}
