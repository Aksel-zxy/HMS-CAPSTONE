<?php
require_once __DIR__ . '../../../../../SQL/config.php';
require_once 'roommanager.php';

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (isset($_POST['action']) && $_POST['action'] === 'ai_suggest') {
    if (ob_get_length() > 0) {
        ob_clean();
    }
    header('Content-Type: application/json');

    try {
        $service_id = $_POST['service_id'];
        $date = $_POST['date'];

        $stmt = $conn->prepare("SELECT serviceName, room_type FROM dl_services WHERE serviceID = ?");
        $stmt->bind_param("i", $service_id);
        $stmt->execute();
        $service = $stmt->get_result()->fetch_assoc();

        if (!$service) throw new Exception("Service ID not found.");

        $requiredRoomType = $service['room_type'];

        $staffList = $conn->query("SELECT employee_id FROM hr_employees WHERE profession = 'Laboratorist'")->fetch_all(MYSQLI_ASSOC);

        $bestSlot = null;
        $minLoad = 999;

        for ($h = 8; $h < 17; $h++) {
            $time = sprintf("%02d:00:00", $h);

            $roomQuery = $conn->prepare("
                SELECT COUNT(*) as booked 
                FROM dl_schedule s
                JOIN rooms r ON s.room_id = r.roomID
                WHERE r.roomType = ? AND s.scheduleDate = ? AND s.scheduleTime = ?
            ");

            if (!$roomQuery) {
                throw new Exception("DB Error: " . $conn->error);
            }

            $roomQuery->bind_param("sss", $requiredRoomType, $date, $time);
            $roomQuery->execute();
            $roomBooked = $roomQuery->get_result()->fetch_assoc()['booked'];

            if ($roomBooked >= 1) continue;

            foreach ($staffList as $staff) {
                $eid = $staff['employee_id'];

                $busyQuery = $conn->prepare("SELECT count(*) as c FROM dl_schedule WHERE employee_id=? AND scheduleDate=? AND scheduleTime=?");
                $busyQuery->bind_param("iss", $eid, $date, $time);
                $busyQuery->execute();
                if ($busyQuery->get_result()->fetch_assoc()['c'] > 0) continue;

                $loadQuery = $conn->prepare("SELECT count(*) as c FROM dl_schedule WHERE employee_id=? AND scheduleDate=?");
                $loadQuery->bind_param("is", $eid, $date);
                $loadQuery->execute();
                $load = $loadQuery->get_result()->fetch_assoc()['c'];

                if ($load < $minLoad) {
                    $minLoad = $load;
                    $bestSlot = [
                        'success' => true,
                        'recommended_staff_id' => $eid,
                        'recommended_time' => $time,
                        'message' => 'Optimal slot found.'
                    ];
                    break 2;
                }
            }
        }

        if ($bestSlot) {
            echo json_encode($bestSlot);
        } else {
            echo json_encode(['success' => false, 'message' => 'No slots available for this date.']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

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

        $roomManager = new RoomManager($this->conn);
        $room = $roomManager->getAvailableRoom($service['room_type']);

        if (!$room) {
            alertAndExit("No available room for this service.");
        }

        $roomID = (int)$room['roomID'];

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

class SmartScheduler
{
    private $conn;

    public function __construct($db)
    {
        $this->conn = $db;
    }

    public function getOptimalSchedule($service_id, $target_date)
    {
        $stmt = $this->conn->prepare("SELECT serviceName, room_type FROM dl_services WHERE serviceID = ?");
        $stmt->bind_param("i", $service_id);
        $stmt->execute();
        $service = $stmt->get_result()->fetch_assoc();

        if (!$service) return ['error' => 'Service not found'];

        $laboratorists = $this->conn->query("SELECT employee_id FROM hr_employees WHERE profession = 'Laboratorist'")->fetch_all(MYSQLI_ASSOC);

        $startHour = 8;
        $endHour = 17;

        for ($hour = $startHour; $hour < $endHour; $hour++) {
            $timeString = sprintf("%02d:00:00", $hour);

            $availableStaff = [];

            foreach ($laboratorists as $staff) {
                if ($this->isStaffFree($staff['employee_id'], $target_date, $timeString)) {
                    $load = $this->getStaffDailyLoad($staff['employee_id'], $target_date);
                    $availableStaff[] = [
                        'id' => $staff['employee_id'],
                        'load' => $load
                    ];
                }
            }

            if (!empty($availableStaff)) {
                usort($availableStaff, function ($a, $b) {
                    return $a['load'] <=> $b['load'];
                });

                $bestStaff = $availableStaff[0];

                if ($this->isRoomAvailable($service['room_type'], $target_date, $timeString)) {
                    return [
                        'success' => true,
                        'recommended_staff_id' => $bestStaff['id'],
                        'recommended_time' => $timeString,
                        'recommended_date' => $target_date,
                        'message' => 'Optimal slot found based on lowest staff workload.'
                    ];
                }
            }
        }

        return ['success' => false, 'message' => 'No slots available for this date.'];
    }

    private function isStaffFree($emp_id, $date, $time)
    {
        $stmt = $this->conn->prepare("SELECT COUNT(*) as count FROM dl_schedule WHERE employee_id = ? AND scheduleDate = ? AND scheduleTime = ?");
        $stmt->bind_param("iss", $emp_id, $date, $time);
        $stmt->execute();
        $res = $stmt->get_result()->fetch_assoc();
        return $res['count'] == 0;
    }

    private function getStaffDailyLoad($emp_id, $date)
    {
        $stmt = $this->conn->prepare("SELECT COUNT(*) as count FROM dl_schedule WHERE employee_id = ? AND scheduleDate = ?");
        $stmt->bind_param("is", $emp_id, $date);
        $stmt->execute();
        $res = $stmt->get_result()->fetch_assoc();
        return $res['count'];
    }

    private function isRoomAvailable($room_type, $date, $time)
    {
        $stmtTotal = $this->conn->prepare("SELECT COUNT(*) as total FROM rooms WHERE room_type = ?");
        $stmtTotal->bind_param("s", $room_type);
        $stmtTotal->execute();
        $totalRooms = $stmtTotal->get_result()->fetch_assoc()['total'];

        $stmtBooked = $this->conn->prepare("
            SELECT COUNT(*) as booked 
            FROM dl_schedule s
            JOIN rooms r ON s.room_id = r.roomID
            WHERE r.room_type = ? AND s.scheduleDate = ? AND s.scheduleTime = ?
        ");
        $stmtBooked->bind_param("sss", $room_type, $date, $time);
        $stmtBooked->execute();
        $bookedRooms = $stmtBooked->get_result()->fetch_assoc()['booked'];

        return $bookedRooms < $totalRooms;
    }
}
