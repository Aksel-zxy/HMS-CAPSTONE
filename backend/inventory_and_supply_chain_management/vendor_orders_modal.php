<?php
session_start();
require 'db.php';

$vendor_id = $_SESSION['vendor_id'] ?? 0;
if (!$vendor_id) {
    echo "Please login first.";
    exit;
}

$po_number = $_GET['po_number'] ?? null;
if (!$po_number) {
    echo "Invalid request.";
    exit;
}

// Fetch all items in this PO for this vendor
$stmt = $pdo->prepare("
    SELECT vo.*, vp.item_name, vp.price, vp.picture
    FROM vendor_orders vo
    JOIN vendor_products vp ON vo.item_id = vp.id
    WHERE vo.purchase_order_number = ? AND vo.vendor_id = ?
");
$stmt->execute([$po_number, $vendor_id]);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (!$items) {
    echo "No items found for this order.";
    exit;
}

// Ensure status at least "Processing"
$status_order = ["Processing", "Packed", "Shipped"];
foreach ($items as $i => $item) {
    if (empty($item['status'])) {
        $pdo->prepare("UPDATE vendor_orders SET status='Processing' WHERE id=?")->execute([$item['id']]);
        $items[$i]['status'] = 'Processing';
    }
}

// Determine current status (max of all items)
$current_status = $items[0]['status'];
foreach ($items as $it) {
    if (array_search($it['status'], $status_order) > array_search($current_status, $status_order)) {
        $current_status = $it['status'];
    }
}

$grand_total = 0;
?>

<table class="table table-bordered align-middle">
    <thead class="table-dark">
        <tr>
            <th>Picture</th>
            <th>Item</th>
            <th>Qty</th>
            <th>Price</th>
            <th>Subtotal</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($items as $it):
            $subtotal = $it['quantity'] * $it['price'];
            $grand_total += $subtotal;
        ?>
        <tr>
            <td>
                <?php if ($it['picture']): ?>
                    <img src="<?= htmlspecialchars($it['picture']) ?>" width="60" height="60" style="object-fit:cover;border-radius:8px;">
                <?php else: ?>
                    N/A
                <?php endif; ?>
            </td>
            <td><?= htmlspecialchars($it['item_name']) ?></td>
            <td><?= $it['quantity'] ?></td>
            <td>₱<?= number_format($it['price'], 2) ?></td>
            <td>₱<?= number_format($subtotal, 2) ?></td>
        </tr>
        <?php endforeach; ?>
        <tr class="table-secondary">
            <td colspan="4" class="text-end"><strong>Total:</strong></td>
            <td><strong>₱<?= number_format($grand_total, 2) ?></strong></td>
        </tr>
    </tbody>
</table>

<?php if ($current_status !== "Shipped"): ?>
<form method="post" action="vendor_orders.php" class="mt-3">
    <input type="hidden" name="po_number" value="<?= htmlspecialchars($po_number) ?>">
    <label class="form-label fw-bold">Update Status:</label>
    <select name="status" class="form-select" required>
        <?php 
        $current_index = array_search($current_status, $status_order);
        foreach($status_order as $i => $status):
            $disabled = ($i < $current_index || $i > $current_index + 1) ? "disabled" : "";
            $selected = ($status === $current_status) ? "selected" : "";
        ?>
        <option value="<?= $status ?>" <?= $disabled ?> <?= $selected ?>><?= $status ?></option>
        <?php endforeach; ?>
    </select>
    <button type="submit" name="update_status" class="btn btn-primary mt-3 w-100">Update</button>
</form>
<?php else: ?>
<div class="alert alert-success mt-3 text-center">Order Completed</div>
<?php endif; ?>
