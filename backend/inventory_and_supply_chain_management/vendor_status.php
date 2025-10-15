<?php
include '../../SQL/config.php';

if (isset($_GET['id']) && isset($_GET['action'])) {
    $id = (int) $_GET['id'];
    $action = $_GET['action'];

    if ($action === 'approve') {
        // Approve vendor + set contract dates
        $stmt = $pdo->prepare("
            UPDATE vendors 
            SET status = 'Approved', 
                approved_at = NOW(), 
                contract_end_date = DATE_ADD(NOW(), INTERVAL 6 MONTH) 
            WHERE id = ?
        ");
        $stmt->execute([$id]);

    } elseif ($action === 'reject') {
        // Reject vendor
        $stmt = $pdo->prepare("UPDATE vendors SET status = 'Rejected' WHERE id = ?");
        $stmt->execute([$id]);
    }

    // Redirect back to applications page
    header("Location: vendor_application.php");
    exit;
}
?>
