<?php
require '../../SQL/config.php';
require_once 'classes/Dashboard.php';

$date = $_GET['date'] ?? date('Y-m-d');

$sql = "
    SELECT status, COUNT(*) AS total
    FROM hr_daily_attendance
    WHERE attendance_date = ?
    GROUP BY status
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $date);
$stmt->execute();
$result = $stmt->get_result();

// All 9 statuses
$statuses = [
    'Present', 'Late', 'Undertime', 'Overtime',
    'Half Day', 'On Leave', 'On Leave (Half Day)',
    'Absent', 'Absent (Half Day)'
];

$data = array_fill_keys($statuses, 0);

while ($row = $result->fetch_assoc()) {
    $status = trim($row['status']);
    if (in_array($status, $statuses)) {
        $data[$status] = (int)$row['total'];
    }
}

echo json_encode($data);
