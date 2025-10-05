<?php
require 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['receipt_id'])) {
    $receipt_id = $_POST['receipt_id'];

    // ✅ Check if receipt exists
    $stmt = $pdo->prepare("SELECT * FROM receipts WHERE id = ?");
    $stmt->execute([$receipt_id]);
    $receipt = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$receipt) {
        die("❌ Invalid receipt ID.");
    }

    // ✅ Check if already paid
    $stmt = $pdo->prepare("SELECT * FROM receipt_payments WHERE receipt_id = ?");
    $stmt->execute([$receipt_id]);
    $payment = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($payment) {
        if ($payment['status'] === 'Paid') {
            // Already paid — redirect
            header("Location: receipt.php?receipt_id=" . $receipt_id);
            exit;
        }

        // Update existing payment to Paid
        $stmt = $pdo->prepare("UPDATE receipt_payments SET status = 'Paid', paid_at = NOW() WHERE receipt_id = ?");
        $stmt->execute([$receipt_id]);
    } else {
        // Insert new payment record
        $stmt = $pdo->prepare("INSERT INTO receipt_payments (receipt_id, status, paid_at) VALUES (?, 'Paid', NOW())");
        $stmt->execute([$receipt_id]);
    }

    // Redirect back to the receipt view
    header("Location: receipt.php?receipt_id=" . $receipt_id);
    exit;
}
?>
