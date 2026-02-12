<?php
include '../../SQL/config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ticket_no = $_POST['ticket_no'] ?? '';

    if ($ticket_no) {
        $stmt = $pdo->prepare("SELECT ticket_no, status, created_at FROM repair_requests WHERE ticket_no = ?");
        $stmt->execute([$ticket_no]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            echo json_encode([
                "found" => true,
                "ticket_no" => $row['ticket_no'],
                "status" => $row['status'],
                "created_at" => $row['created_at']
            ]);
        } else {
            echo json_encode(["found" => false]);
        }
    }
}
?>
