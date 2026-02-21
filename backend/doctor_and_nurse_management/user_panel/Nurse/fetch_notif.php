<?php
session_start();
header('Content-Type: application/json');
include '../../../../SQL/config.php';

$notifications = [];

if (!isset($_SESSION['employee_id'])) {
    echo json_encode(['error' => 'Not logged in']);
    exit();
}

$employee_id = $_SESSION['employee_id'];
$currentDate = date("Y-m-d"); // Get current date in Y-m-d format

$sql_emp = "SELECT first_name, last_name, license_expiry FROM hr_employees WHERE employee_id = ? AND license_expiry IS NOT NULL AND license_expiry != '0000-00-00'";
$stmt = $conn->prepare($sql_emp);
$stmt->bind_param("i", $employee_id);
$stmt->execute();
$result_emp = $stmt->get_result();

if ($result_emp->num_rows > 0) {
    while($row = $result_emp->fetch_assoc()) {
        $expiryDate = $row['license_expiry'];
        // Use DateTime instead of strtotime which doesn't handle older dates correctly
        $expiryTime = new DateTime($expiryDate);
        $currentTime = new DateTime($currentDate);
        $interval = $currentTime->diff($expiryTime);
        $daysUntilExpiry = (int)$interval->format('%R%a');

        if ($daysUntilExpiry < 0) {
            $notifications[] = [
                'name' => "Your license is expired.",
                'expiryDate' => $expiryDate
            ];
        } else if ($daysUntilExpiry <= 30) {
            $notifications[] = [
                'name' => "Your license is expiring soon.",
                'expiryDate' => $expiryDate
            ];
        }
    }
}

echo json_encode($notifications);

$conn->close();
?>
