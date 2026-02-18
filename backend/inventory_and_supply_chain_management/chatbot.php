<?php
session_start();
include '../../SQL/config.php';

header('Content-Type: application/json');

// Ensure PDO exists
if (!isset($pdo)) {
    echo json_encode([
        "success" => false,
        "message" => "Database connection failed."
    ]);
    exit;
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode([
        "success" => false,
        "message" => "❌ User not logged in."
    ]);
    exit;
}

$user_id   = $_SESSION['user_id'];
$user_name = $_SESSION['fname'] . " " . $_SESSION['lname'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Sanitize inputs
    $equipment = trim($_POST['equipment'] ?? '');
    $issue     = trim($_POST['issue'] ?? '');
    $location  = trim($_POST['location'] ?? '');
    $priority  = trim($_POST['priority'] ?? '');

    // Validate required fields
    if (empty($equipment) || empty($issue) || empty($location) || empty($priority)) {
        echo json_encode([
            "success" => false,
            "message" => "⚠️ Missing required information."
        ]);
        exit;
    }

    try {

        // Generate strong ticket number
        $ticket_no = "TCK-" . strtoupper(bin2hex(random_bytes(4)));

        // Insert into database
        $stmt = $pdo->prepare("
            INSERT INTO repair_requests 
            (ticket_no, user_id, user_name, equipment, issue, location, priority, status, created_at) 
            VALUES 
            (:ticket_no, :user_id, :user_name, :equipment, :issue, :location, :priority, :status, NOW())
        ");

        $stmt->execute([
            ':ticket_no' => $ticket_no,
            ':user_id'   => $user_id,
            ':user_name' => $user_name,
            ':equipment' => $equipment,
            ':issue'     => $issue,
            ':location'  => $location,
            ':priority'  => $priority,
            ':status'    => 'Open'
        ]);

        echo json_encode([
            "success"    => true,
            "ticket_no"  => $ticket_no,
            "status"     => "Open",
            "priority"   => $priority,
            "created_at" => date("Y-m-d H:i:s")
        ]);

    } catch (PDOException $e) {

        echo json_encode([
            "success" => false,
            "message" => "Database error.",
            "error"   => $e->getMessage() // Remove in production
        ]);
    }

} else {

    echo json_encode([
        "success" => false,
        "message" => "Invalid request method."
    ]);
}
?>
