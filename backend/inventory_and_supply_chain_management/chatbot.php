<?php
include '../../SQL/config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $equipment = $_POST['equipment'] ?? '';
    $issue = $_POST['issue'] ?? '';
    $location = $_POST['location'] ?? '';
    $priority = $_POST['priority'] ?? '';

    if ($equipment && $issue && $location && $priority) {
        $ticket_no = "TCK-" . strtoupper(uniqid());

        $stmt = $pdo->prepare("INSERT INTO repair_requests (ticket_no, user_name, equipment, issue, location, priority, status) 
                               VALUES (?, 'Anonymous', ?, ?, ?, ?, 'Open')");
        $stmt->execute([$ticket_no, $equipment, $issue, $location, $priority]);

        echo json_encode([
            "success" => true,
            "ticket_no" => $ticket_no,
            "status" => "Open",
            "created_at" => date("Y-m-d H:i:s"),
            "reply" => "✅ Request logged. Ticket No: $ticket_no"
        ]);
    } else {
        echo json_encode(["success" => false, "reply" => "⚠️ Missing information."]);
    }
}
?>
