<?php

header('Content-Type: application/json');
include '../../../../SQL/config.php';

$notifications = [];
$currentDate = date("Y-m-d"); // Get current date in Y-m-d format

$sql_emp = "SELECT first_name, last_name, license_expiry FROM hr_employees WHERE license_expiry IS NOT NULL AND license_expiry != '0000-00-00'";
$result_emp = $conn->query($sql_emp);

if ($result_emp->num_rows > 0) {
    while($row = $result_emp->fetch_assoc()) {
        $expiryDate = $row['license_expiry'];
        $daysUntilExpiry = (strtotime($expiryDate) - strtotime($currentDate)) / (60 * 60 * 24); // Calculate days until expiry

        if ($daysUntilExpiry < 0) {
            $notifications[] = [
                'name' => $row['first_name'] . " " . $row['last_name'] . ", your license is expired.",
                'expiryDate' => $expiryDate
            ];
        } elseif ($daysUntilExpiry <= 7) {
            $notifications[] = [
                'name' => $row['first_name'] . " " . $row['last_name'] . ", your license is expiring soon.",
                'expiryDate' => $expiryDate
            ];
        }
    }
}

echo json_encode($notifications);

$conn->close();
?>
