<?php
include '../../SQL/config.php';
header('Content-Type: application/json');

$user_name = "Guest"; // Replace with $_SESSION['username'] when login system is added

$result = $conn->query("
    SELECT ticket_no, issue, status, created_at 
    FROM repair_requests 
    WHERE user_name='$user_name' 
    ORDER BY created_at DESC
");

$requests = [];
while ($row = $result->fetch_assoc()) {
    $requests[] = $row;
}

echo json_encode($requests);
