<?php
require 'db.php';

// Get order IDs from query string
if (!isset($_GET['order_ids']) || empty($_GET['order_ids'])) {
    die("No order specified.");
}

$order_ids = explode(',', $_GET['order_ids']); // split into array
$order_ids = array_map('intval', $order_ids); // sanitize (only numbers)

// Prepare SQL with placeholders
$placeholders = implode(',', array_fill(0, count($order_ids), '?'));

$stmt = $pdo->prepare("
    SELECT vo.id, vo.created_at, vo.quantity, vo.status,
           i.name AS item_name, i.type AS item_type, i.price
    FROM vendor_orders vo
    JOIN items i ON vo.item_id = i.id
    WHERE vo.id IN ($placeholders)
");
$stmt->execute($order_ids);
$order_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (!$order_items) {
    die("No items found for this order.");
}

// Calculate totals
$total_qty = 0;
$total_price = 0;
foreach ($order_items as $item) {
    $total_qty += $item['quantity'];
    $total_price += $item['quantity'] * $item['price'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Order Details</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container py-5">
    <h2 class="mb-4">📋 Order Details</h2>

    <table class="table table-bordered table-striped">
        <thead class="table-dark">
            <tr>
                <th>Item</th>
                <th>Type</th>
                <th>Quantity</th>
                <th>Price</th>
                <th>Subtotal</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($order_items as $item): ?>
            <tr>
                <td><?= htmlspecialchars($item['item_name']) ?></td>
                <td><?= htmlspecialchars($item['item_type']) ?></td>
                <td><?= (int)$item['quantity'] ?></td>
                <td>₱<?= number_format($item['price'], 2) ?></td>
                <td>₱<?= number_format($item['quantity'] * $item['price'], 2) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot class="table-dark">
            <tr>
                <th colspan="2">Total</th>
                <th><?= $total_qty ?></th>
                <th></th>
                <th>₱<?= number_format($total_price, 2) ?></th>
            </tr>
        </tfoot>
    </table>

    <a href="order_received.php" class="btn btn-secondary">⬅ Back to Orders</a>
</div>
</body>
</html>
