<?php
require 'db.php';

$receipt_id = $_GET['receipt_id'] ?? 0;

// Fetch receipt
$stmt = $pdo->prepare("SELECT r.*, v.name as vendor_name 
                       FROM receipts r
                       JOIN vendors v ON r.vendor_id = v.id
                       WHERE r.id=?");
$stmt->execute([$receipt_id]);
$receipt = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$receipt) {
    die("❌ Receipt not found for ID: " . htmlspecialchars($receipt_id));
}

// Fetch receipt items
$stmt = $pdo->prepare("SELECT * FROM receipt_items WHERE receipt_id=?");
$stmt->execute([$receipt_id]);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch payment status
$stmt = $pdo->prepare("SELECT * FROM receipt_payments WHERE receipt_id=? LIMIT 1");
$stmt->execute([$receipt_id]);
$payment = $stmt->fetch(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Receipt #<?= $receipt['id'] ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<div class="container py-5">
    <div class="card shadow-lg">
        <div class="card-header bg-dark text-white">
            <h4>🧾 Receipt #<?= $receipt['id'] ?></h4>
        </div>
        <div class="card-body">
            <p><strong>Vendor:</strong> <?= htmlspecialchars($receipt['vendor_name']) ?></p>
            <p><strong>Date:</strong> <?= $receipt['created_at'] ?></p>

            <table class="table table-bordered mt-4">
                <thead class="table-dark">
                    <tr>
                        <th>Item</th>
                        <th>Quantity</th>
                        <th>Price</th>
                        <th>Subtotal</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $it): ?>
                        <tr>
                            <td><?= htmlspecialchars($it['item_name']) ?></td>
                            <td><?= $it['quantity_received'] ?></td>
                            <td>₱<?= number_format($it['price'], 2) ?></td>
                            <td>₱<?= number_format($it['subtotal'], 2) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <div class="text-end mt-3">
                <p><strong>Subtotal:</strong> ₱<?= number_format($receipt['subtotal'], 2) ?></p>
                <p><strong>VAT (12%):</strong> ₱<?= number_format($receipt['vat'], 2) ?></p>
                <h5><strong>Total:</strong> ₱<?= number_format($receipt['total'], 2) ?></h5>
            </div>

            <div class="mt-4">
                <?php if ($payment && $payment['status'] === 'Paid'): ?>
                    <span class="badge bg-success">✅ Paid on <?= $payment['paid_at'] ?></span>
                <?php else: ?>
                    <form method="post" action="receipt_pay.php">
                        <input type="hidden" name="receipt_id" value="<?= $receipt['id'] ?>">
                        <button type="submit" class="btn btn-success">💵 Mark as Paid</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

</body>
</html>
