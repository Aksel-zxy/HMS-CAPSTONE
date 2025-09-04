<?php
require 'db.php';

if (!isset($_GET['request_id'])) {
    echo "Invalid request.";
    exit;
}
$request_id = $_GET['request_id'];

$stmt = $pdo->prepare("
    SELECT vo.*, vp.item_name, vp.price, vp.picture
    FROM vendor_orders vo
    JOIN vendor_products vp ON vo.item_id = vp.id
    WHERE vo.purchase_request_id = ?
");
$stmt->execute([$request_id]);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (!$items) {
    echo "No items found.";
    exit;
}

// 🔹 Check payment status
$payStmt = $pdo->prepare("
    SELECT paid_at 
    FROM receipt_payments 
    WHERE receipt_id = ?
    AND status = 'Paid'
    LIMIT 1
");
$payStmt->execute([$request_id]); 
$payment = $payStmt->fetch(PDO::FETCH_ASSOC);

// If paid, override status to Completed
if ($payment) {
    $current_status = "Completed";
    $delivered_date = $payment['paid_at'];
} else {
    $current_status = $items[0]['status'];
    $delivered_date = "N/A";
}

$status_order = ["Processing", "Packed", "Shipped"]; // ✅ removed Completed
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
        <?php 
        $grand_total = 0;
        foreach ($items as $it): 
            $subtotal = $it['quantity'] * $it['price'];
            $grand_total += $subtotal;
        ?>
        <tr>
            <td>
                <?php if ($it['picture']): ?>
                    <img src="<?= htmlspecialchars($it['picture']) ?>" width="60" height="60" style="object-fit:cover;border-radius:8px;">
                <?php else: ?>
                    <span class="text-muted">N/A</span>
                <?php endif; ?>
            </td>
            <td><?= htmlspecialchars($it['item_name']) ?></td>
            <td><?= (int)$it['quantity'] ?></td>
            <td>₱<?= number_format($it['price'], 2) ?></td>
            <td>₱<?= number_format($subtotal, 2) ?></td>
        </tr>
        <?php endforeach; ?>
        <tr class="table-secondary">
            <td colspan="4" class="text-end"><strong>Total Amount:</strong></td>
            <td><strong>₱<?= number_format($grand_total, 2) ?></strong></td>
        </tr>
        <tr class="table-success">
            <td colspan="4" class="text-end"><strong>Delivered Date:</strong></td>
            <td><strong><?= $delivered_date ?></strong></td>
        </tr>
    </tbody>
</table>

<!-- 🔹 Status update form (hide if already Completed) -->
<?php if ($current_status !== "Completed"): ?>
<form method="post" action="vendor_orders.php" class="mt-3">
    <input type="hidden" name="purchase_request_id" value="<?= $request_id ?>">
    <label for="status" class="form-label fw-bold">Update Status:</label>
    <select name="status" class="form-select" required>
        <?php 
        $current_index = array_search($current_status, $status_order);
        foreach ($status_order as $i => $status): 
            $disabled = ($i < $current_index) ? "disabled" : "";
            $selected = ($status === $current_status) ? "selected" : "";
        ?>
            <option value="<?= $status ?>" <?= $disabled ?> <?= $selected ?>>
                <?= $status ?>
            </option>
        <?php endforeach; ?>
    </select>
    <button type="submit" name="update_status" class="btn btn-primary mt-3 w-100">Update</button>
</form>
<?php else: ?>
    <div class="alert alert-success mt-3 text-center fw-bold">
        ✅ Order Completed on <?= $delivered_date ?>
    </div>
<?php endif; ?>
