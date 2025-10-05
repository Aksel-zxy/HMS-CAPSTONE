<?php
session_start();
require 'db.php';

$vendor_id = $_SESSION['vendor_id'] ?? 0;
if (!$vendor_id) {
    echo "Please login first.";
    exit;
}

$po_number = $_GET['po_number'] ?? '';
if (!$po_number) {
    echo "Invalid request.";
    exit;
}

// 🔹 Fetch all items for this PO number for this vendor
$stmt = $pdo->prepare("
    SELECT vo.*, vp.item_name, vp.price, vp.picture
    FROM vendor_orders vo
    JOIN vendor_products vp ON vo.item_id = vp.id
    WHERE vo.vendor_id = ? AND vo.purchase_order_number = ?
");
$stmt->execute([$vendor_id, $po_number]);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (!$items) {
    echo "No items found for this PO.";
    exit;
}

// 🔹 Determine current overall PO status
$status_order = ["Processing", "Packed", "Shipped"];
$po_statuses = array_column($items, 'status');
$current_status = max(array_map(fn($s) => array_search($s, $status_order), $po_statuses));
$current_status = $status_order[$current_status];
?>

<table class="table table-bordered align-middle">
    <thead class="table-dark">
        <tr>
            <th>Picture</th>
            <th>Item</th>
            <th>Quantity</th>
            <th>Price</th>
            <th>Subtotal</th>
            <th>Status</th>
        </tr>
    </thead>
    <tbody>
        <?php 
        $grand_total = 0;
        foreach ($items as $it): 
            $subtotal = $it['quantity'] * $it['price'];
            $grand_total += $subtotal;
        ?>
        <tr>
            <td>
                <?php if ($it['picture']): ?>
                    <img src="<?= htmlspecialchars($it['picture']) ?>" width="50" height="50" style="object-fit:cover;border-radius:6px;">
                <?php else: ?>
                    N/A
                <?php endif; ?>
            </td>
            <td><?= htmlspecialchars($it['item_name']) ?></td>
            <td><?= $it['quantity'] ?></td>
            <td>₱<?= number_format($it['price'], 2) ?></td>
            <td>₱<?= number_format($subtotal, 2) ?></td>
            <td>
                <span class="badge 
                    <?= $it['status']=='Processing'?'bg-warning':($it['status']=='Packed'?'bg-primary':'bg-success') ?>">
                    <?= $it['status'] ?>
                </span>
            </td>
        </tr>
        <?php endforeach; ?>
        <tr class="table-secondary">
            <td colspan="4" class="text-end"><strong>Total:</strong></td>
            <td colspan="2"><strong>₱<?= number_format($grand_total,2) ?></strong></td>
        </tr>
    </tbody>
</table>

<!-- 🔹 Overall PO Status Update -->
<?php if ($current_status !== "Shipped"): ?>
<form method="post" action="vendor_orders.php" class="mt-3">
    <input type="hidden" name="po_number" value="<?= htmlspecialchars($po_number) ?>">
    <label for="status" class="form-label fw-bold">Update PO Status:</label>
    <select name="status" class="form-select" required>
        <?php 
        $current_index = array_search($current_status, $status_order);
        foreach ($status_order as $i => $status): 
            // Only allow current status and next status
            $disabled = ($i < $current_index || $i > $current_index + 1) ? "disabled" : "";
            $selected = ($status === $current_status) ? "selected" : "";
        ?>
            <option value="<?= $status ?>" <?= $disabled ?> <?= $selected ?>>
                <?= $status ?>
            </option>
        <?php endforeach; ?>
    </select>
    <button type="submit" name="update_po_status" class="btn btn-primary mt-3 w-100">Update PO Status</button>
</form>
<?php else: ?>
    <div class="alert alert-success mt-3 text-center fw-bold">
        ✅ PO Completed
    </div>
<?php endif; ?>
