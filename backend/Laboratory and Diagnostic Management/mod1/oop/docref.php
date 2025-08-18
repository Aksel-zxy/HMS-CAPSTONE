<?php
require_once __DIR__ . '../../../../../SQL/config.php';

class Calendar {
    private $conn;
    public function __construct($conn){ $this->conn = $conn; }

    public function getSchedules() {
        $sql = "
            SELECT 
              s.patientID,
              p.fname, p.lname,
              s.serviceName,
              s.scheduleDate,
              s.scheduleTime
            FROM dl_schedule s
            JOIN patientinfo p ON p.patient_id = s.patientID
            WHERE COALESCE(s.status,'') <> 'Processing'
        ";
        $res = $this->conn->query($sql);

        $events = [];
        while ($row = $res->fetch_assoc()) {
            $date = $row['scheduleDate'];
            $time = $row['scheduleTime'];
            $pretty = date('g:i A', strtotime($time));
            $events[] = [
                'title' => "{$row['fname']} {$row['lname']} — {$row['serviceName']} — {$pretty}",
                'start' => "{$date}T{$time}",
                'allDay' => false
            ];
        }
        return $events;
    }

    // Get all patients scheduled for a given date
    public function getDayDetails($date) {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) return [];

        $stmt = $this->conn->prepare("
            SELECT p.fname, p.lname, s.serviceName, s.scheduleTime
            FROM dl_schedule s
            JOIN patientinfo p ON p.patient_id = s.patientID
            WHERE s.scheduleDate = ?
            ORDER BY s.scheduleTime
        ");
        $stmt->bind_param('s', $date);
        $stmt->execute();
        $result = $stmt->get_result();

        $details = [];
        while ($row = $result->fetch_assoc()) {
            $details[] = [
                'patient' => $row['fname'] . " " . $row['lname'],
                'service' => $row['serviceName'],
                'time'    => date('g:i A', strtotime($row['scheduleTime']))
            ];
        }
        return $details;
    }
}

$calendar = new Calendar($conn);

header('Content-Type: application/json');
$action = $_GET['action'] ?? 'schedules';

if ($action === 'schedules') {
    echo json_encode($calendar->getSchedules(), JSON_UNESCAPED_UNICODE);
} elseif ($action === 'dayDetails') {
    $date = $_GET['date'] ?? '';
    echo json_encode($calendar->getDayDetails($date), JSON_UNESCAPED_UNICODE);
} else {
    echo json_encode([]);
}
