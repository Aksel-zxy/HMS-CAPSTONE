<?php
require 'db.php'; // DB connection ($pdo)

$type = $_GET['type'] ?? 'username';
$value = trim($_GET['value'] ?? '');
$response = ["status" => "unavailable"]; // default

if (!empty($value)) {
    if ($type === "username") {
        $stmt = $pdo->prepare("SELECT status FROM vendors WHERE username = ? ORDER BY created_at DESC LIMIT 1");
    } elseif ($type === "company") {
        $stmt = $pdo->prepare("SELECT status FROM vendors WHERE company_name = ? ORDER BY created_at DESC LIMIT 1");
    } elseif ($type === "email") {
        $stmt = $pdo->prepare("SELECT status FROM vendors WHERE email = ? ORDER BY created_at DESC LIMIT 1");
    }

    if (isset($stmt)) {
        $stmt->execute([$value]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            if ($row['status'] === 'Rejected') {
                $response["status"] = "available"; // allow reuse
            } elseif ($row['status'] === 'Pending') {
                $response["status"] = "pending"; // still under review
            } elseif ($row['status'] === 'Approved') {
                $response["status"] = "approved"; // disallow reuse
            }
        } else {
            $response["status"] = "available"; // never used before
        }
    }
}

header('Content-Type: application/json');
echo json_encode($response);
