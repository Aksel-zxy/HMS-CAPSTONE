<?php
include '../../SQL/config.php';

if (!isset($_SESSION['doctor']) || $_SESSION['doctor'] !== true) {
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$profession = isset($_GET['profession']) ? $_GET['profession'] : '';
$department = isset($_GET['department']) ? $_GET['department'] : '';

if (empty($profession) || empty($department)) {
    echo json_encode(['error' => 'Missing profession or department']);
    exit();
}

// Fetch employees based on profession, department and joined with attendance to get only active ones
$query = "SELECT e.employee_id, e.first_name, e.last_name, e.specialization
          FROM hr_employees e
          JOIN hr_daily_attendance a ON e.employee_id = a.employee_id
          WHERE e.profession = ? AND e.department = ? AND a.attendance_date = CURRENT_DATE() AND a.duty_status = 'On Duty'";

$stmt = $conn->prepare($query);
if ($stmt) {
    $stmt->bind_param("ss", $profession, $department);
    $stmt->execute();
    $result = $stmt->get_result();
    $employees = [];
    while ($row = $result->fetch_assoc()) {
        $employees[] = $row;
    }
    echo json_encode(['success' => true, 'data' => $employees]);
} else {
    echo json_encode(['error' => 'Database query failed']);
}
?>
