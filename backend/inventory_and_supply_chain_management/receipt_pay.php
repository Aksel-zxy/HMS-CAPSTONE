<?php
require 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $receipt_id = intval($_POST['receipt_id']);

    $stmt = $pdo->prepare("SELECT * FROM receipts WHERE id = ?");
    $stmt->execute([$receipt_id]);
    $receipt = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$receipt) {
        die("❌ Receipt not found.");
    }

    $order_id = $receipt['order_id'];

    // Mark receipt payment as Paid
    $stmt = $pdo->prepare("UPDATE receipt_payments SET status='Paid', paid_at=NOW() WHERE receipt_id = ?");
    $stmt->execute([$receipt_id]);

    // Mark vendor order as Received
    $stmt = $pdo->prepare("UPDATE vendor_orders SET status='Received', received_at=NOW() WHERE id = ?");
    $stmt->execute([$order_id]);

    // Remove from order_receive table if exists
    $stmt = $pdo->prepare("DELETE FROM order_receive WHERE vendor_order_id = ?");
    $stmt->execute([$order_id]);

    header("Location: receipt_view.php?receipt_id=$receipt_id");
    exit;
}
