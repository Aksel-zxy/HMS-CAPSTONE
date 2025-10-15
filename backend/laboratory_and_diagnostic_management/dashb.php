<?php
include "../../SQL/config.php";

class Schedule {
    private $conn;

    public function __construct($conn) {
        $this->conn = $conn;
    }

    // ✅ Fetch today's schedule list (not completed)
    public function getTodaysSchedules() {
        $today = date('Y-m-d');
        $query = "
            SELECT 
                p.fname, p.lname, 
                s.serviceName, 
                s.status,
                s.scheduleTime
            FROM dl_schedule s
            INNER JOIN patientinfo p ON p.patient_id = s.patientID
            WHERE DATE(s.scheduleDate) = ?
            ORDER BY s.scheduleTime ASC
        ";

        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("s", $today);
        $stmt->execute();
        $result = $stmt->get_result();

        $schedules = [];
        while ($row = $result->fetch_assoc()) {
            $schedules[] = $row;
        }
        $stmt->close();

        return $schedules;
    }

    // ✅ Fetch summary counts for dashboard cards
    public function getDashboardStats() {
        $today = date('Y-m-d');
        $stats = [
            'total' => 0,
            'completed' => 0,
            'processing' => 0,
            'cancelled' => 0
        ];

        $query = "
            SELECT 
                COUNT(*) AS total,
                SUM(status = 'Completed') AS completed,
                SUM(status = 'Processing') AS processing,
                SUM(status = 'Cancelled') AS cancelled
            FROM dl_schedule
            WHERE DATE(scheduleDate) = ?
        ";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("s", $today);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($result) {
            $stats['total'] = $result['total'] ?? 0;
            $stats['completed'] = $result['completed'] ?? 0;
            $stats['processing'] = $result['processing'] ?? 0;
            $stats['cancelled'] = $result['cancelled'] ?? 0;
        }

        return $stats;
    }
}

// for chart
$startOfWeek = date('Y-m-d', strtotime('monday this week'));

$testTypes = [];
$query = "SELECT DISTINCT serviceName FROM dl_schedule";
$result = $conn->query($query);
while ($row = $result->fetch_assoc()) {
    $testTypes[] = $row['serviceName'];
}

// Define fixed colors for each service
$colorMap = [
    'CBC' => '#2a5adf',
    'MRI' => '#198754',
    'CT Scan' => '#ffc107',
    'X-Ray' => '#dc3545',
    'Ultrasound' => '#6f42c1'
];

$labels = [];
for ($i = 0; $i < 7; $i++) {
    $labels[] = date('D', strtotime("$startOfWeek +$i day"));
}

$datasets = [];

foreach ($testTypes as $test) {
    $counts = array_fill(0, 7, 0);

    $query = "
        SELECT DATE(scheduleDate) AS day, COUNT(*) AS total
        FROM dl_schedule
        WHERE serviceName = ?
          AND status = 'Completed'
          AND DATE(scheduleDate) BETWEEN ? AND DATE_ADD(?, INTERVAL 6 DAY)
        GROUP BY DATE(scheduleDate)
    ";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("sss", $test, $startOfWeek, $startOfWeek);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $dayIndex = (int)date('N', strtotime($row['day'])) - 1; // Monday=0
        $counts[$dayIndex] = (int)$row['total'];
    }
    $stmt->close();

    // Use fixed color, or default blue if not in map
    $color = $colorMap[$test] ?? '#0d6efd';

    $datasets[] = [
        'label' => $test,
        'data' => array_values($counts),
        'borderColor' => $color,
        'backgroundColor' => $color . '33', // transparent fill
        'fill' => true,
        'tension' => 0.4,
        'pointRadius' => 4
    ];
}
?>
