<?php
require_once __DIR__ . '../../../../../SQL/config.php';

class Calendar
{
    private $conn;

    public function __construct($conn)
    {
        $this->conn = $conn;
    }

    // Fetch schedules for calendar
    public function getSchedules()
    {
        $stmt = $this->conn->prepare("
        SELECT 
            s.patientID, 
            p.fname, 
            p.lname, 
            s.serviceName, 
            s.scheduleDate, 
            s.scheduleTime
        FROM dl_schedule s
        JOIN patientinfo p ON s.patientID = p.patient_id
    ");
        $stmt->execute();
        $result = $stmt->get_result();

        $events = [];
        while ($row = $result->fetch_assoc()) {
            $formattedTime = date("g:i A", strtotime($row['scheduleTime']));
            $events[] = [
                'title' => "{$row['fname']} {$row['lname']} — {$row['serviceName']} — {$formattedTime}",
                'start' => "{$row['scheduleDate']}T{$row['scheduleTime']}"
            ];
        }
        return $events;
    }
    // Fetch available slots
    public function getAvailableSlots($date)
    {
        $stmt = $this->conn->prepare("
            SELECT scheduletime AS time, 
                   (5 - COUNT(patientID)) AS remaining -- max 5 slots
            FROM dl_schedules
            WHERE scheduledate = ?
            GROUP BY scheduletime
        ");
        $stmt->bind_param("s", $date);
        $stmt->execute();
        $result = $stmt->get_result();

        $slots = [];
        while ($row = $result->fetch_assoc()) {
            $slots[] = [
                'time' => date("g:i A", strtotime($row['time'])),
                'remaining' => $row['remaining']
            ];
        }
        return $slots;
    }
}

// === Main handler ===
$calendar = new Calendar($conn);

$type = $_GET['type'] ?? 'schedules';
if ($type === 'slots') {
    $date = $_GET['date'] ?? '';
    $data = $calendar->getAvailableSlots($date);
} else {
    $data = $calendar->getSchedules();
}

header('Content-Type: application/json');
echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
