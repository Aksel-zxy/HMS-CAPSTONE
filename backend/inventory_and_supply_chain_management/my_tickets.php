<?php
session_start();
include '../../SQL/config.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(["success" => false]);
    exit;
}

$user_id = $_SESSION['user_id'];

$stmt = $pdo->prepare("SELECT ticket_no, equipment, status, priority, created_at 
                       FROM repair_requests 
                       WHERE user_id = ?
                       ORDER BY created_at DESC");

$stmt->execute([$user_id]);
$tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);

if ($tickets) {

    $html = "<strong>Your Tickets:</strong><br><br>";

    foreach ($tickets as $row) {
        $html .= "
            Ticket: <b>{$row['ticket_no']}</b><br>
            Equipment: {$row['equipment']}<br>
            Status: {$row['status']}<br>
            Priority: {$row['priority']}<br>
            Date: {$row['created_at']}<br>
            <hr>
        ";
    }

    echo json_encode([
        "success" => true,
        "html" => $html
    ]);

} else {
    echo json_encode(["success" => false]);
}
?>
