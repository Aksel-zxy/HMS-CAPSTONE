<?php
require 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['receipt_id'])) {
    $receipt_id = $_POST['receipt_id'];

    // Check if payment exists
    $stmt = $pdo->prepare("SELECT * FROM receipt_payments WHERE receipt_id=?");
    $stmt->execute([$receipt_id]);
    $payment = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($payment) {
        $stmt = $pdo->prepare("UPDATE receipt_payments SET status='Paid', paid_at=NOW() WHERE receipt_id=?");
        $stmt->execute([$receipt_id]);
    } else {
        $stmt = $pdo->prepare("INSERT INTO receipt_payments (receipt_id, status, paid_at) VALUES (?, 'Paid', NOW())");
        $stmt->execute([$receipt_id]);
    }

    header("Location: receipt.php?receipt_id=" . $receipt_id);
    exit;
}
