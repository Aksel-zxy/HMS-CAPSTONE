<?php
require 'db.php';

$receipt_id = $_GET['receipt_id'] ?? 0;

// Fetch receipt with vendor info
$stmt = $pdo->prepare("
    SELECT r.*, 
           v.company_name, v.company_address, v.contact_name, v.phone, v.email, v.tin_vat
    FROM receipts r
    JOIN vendors v ON r.vendor_id = v.id
    WHERE r.id = ?
");
$stmt->execute([$receipt_id]);
$receipt = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$receipt) {
    die("❌ Receipt not found for ID: " . htmlspecialchars($receipt_id));
}

// Fetch the original vendor order for this receipt using order_id
$stmt = $pdo->prepare("SELECT * FROM vendor_orders WHERE id = ? LIMIT 1");
$stmt->execute([$receipt['order_id']]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    die("❌ Vendor order not found for this receipt.");
}

// Decode items JSON
$items_json = json_decode($order['items'], true);

// Prepare items array with totals
$items = [];
foreach ($items_json as $item_id => $data) {
    $quantity_received = 0;
    $subtotal = 0;

    // Fetch quantity received per item
    $stmt2 = $pdo->prepare("SELECT SUM(quantity_received) as qty_sum, SUM(subtotal) as subtotal_sum 
                            FROM receipt_items 
                            WHERE receipt_id = ? AND item_id = ?");
    $stmt2->execute([$receipt_id, $item_id]);
    $received_data = $stmt2->fetch(PDO::FETCH_ASSOC);
    if ($received_data) {
        $quantity_received = $received_data['qty_sum'] ?? 0;
        $subtotal = $received_data['subtotal_sum'] ?? 0;
    }

    $items[] = [
        'item_id' => $item_id,
        'item_name' => $data['name'],
        'unit_type' => $data['unit_type'] ?? 'Piece',
        'pcs_per_box' => $data['pcs_per_box'] ?? null,
        'price' => $data['price'],
        'quantity_received' => $quantity_received,
        'subtotal' => $subtotal > 0 ? $subtotal : ($quantity_received * $data['price'])
    ];
}

// Fetch payment status
$stmt = $pdo->prepare("SELECT * FROM receipt_payments WHERE receipt_id = ? LIMIT 1");
$stmt->execute([$receipt_id]);
$payment = $stmt->fetch(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Receipt #<?= $receipt['id'] ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="assets/css/receipt.css">
</head>
<body class="bg-light">

<div class="container py-5">
    <div class="card shadow-lg">
        <div class="card-header bg-dark text-white">
            <h4>🧾 Receipt #<?= $receipt['id'] ?></h4>
        </div>
        <div class="card-body">
            <!-- Vendor Information -->
            <h5 class="mb-3">Vendor Information</h5>
            <p><strong>Company:</strong> <?= htmlspecialchars($receipt['company_name']) ?></p>
            <p><strong>Address:</strong> <?= htmlspecialchars($receipt['company_address']) ?></p>
            <p><strong>Contact Person:</strong> <?= htmlspecialchars($receipt['contact_name']) ?></p>
            <p><strong>Phone:</strong> <?= htmlspecialchars($receipt['phone']) ?></p>
            <p><strong>Email:</strong> <?= htmlspecialchars($receipt['email']) ?></p>
            <p><strong>TIN/VAT:</strong> <?= htmlspecialchars($receipt['tin_vat']) ?></p>
            <p><strong>Date Issued:</strong> <?= $receipt['created_at'] ?></p>

            <hr>
            <p><strong>Purchase Order #:</strong> <?= htmlspecialchars($order['purchase_order_number']) ?></p>
            <p><strong>Status:</strong> <?= htmlspecialchars($order['status']) ?></p>

            <!-- Items Table -->
            <table class="table table-bordered mt-4">
                <thead class="table-dark">
                    <tr>
                        <th>Item</th>
                        <th>Quantity Received</th>
                        <th>Unit</th>
                        <th>Price</th>
                        <th>Subtotal</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $it): ?>
                        <tr>
                            <td><?= htmlspecialchars($it['item_name']) ?></td>
                            <td><?= $it['quantity_received'] ?></td>
                            <td>
                                <?= htmlspecialchars($it['unit_type']) ?>
                                <?php if ($it['unit_type'] === "Box" && $it['pcs_per_box']): ?>
                                    (<?= (int)$it['pcs_per_box'] ?> pcs)
                                <?php endif; ?>
                            </td>
                            <td>₱<?= number_format($it['price'],2) ?></td>
                            <td>₱<?= number_format($it['subtotal'],2) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <!-- Totals -->
            <div class="text-end mt-3">
                <p><strong>Subtotal:</strong> ₱<?= number_format($receipt['subtotal'],2) ?></p>
                <p><strong>VAT (12%):</strong> ₱<?= number_format($receipt['vat'],2) ?></p>
                <h5><strong>Total:</strong> ₱<?= number_format($receipt['total'],2) ?></h5>
            </div>

            <!-- Payment Status -->
            <div class="mt-4">
                <?php if ($payment && $payment['status'] === 'Paid'): ?>
                    <span class="badge bg-success">✅ Paid on <?= $payment['paid_at'] ?></span>
                <?php else: ?>
                    <form method="post" action="receipt_pay.php">
                        <input type="hidden" name="receipt_id" value="<?= $receipt['id'] ?>">
                        <button type="submit" class="btn btn-success">💵 Mark as Paid & Received</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

</body>
</html>
