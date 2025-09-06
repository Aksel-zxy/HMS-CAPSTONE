<?php
session_start();
require 'db.php';

// ✅ Assume vendor login is stored in session
$logged_vendor_id = $_SESSION['vendor_id'] ?? 0;
if (!$logged_vendor_id) {
    echo "Please login first.";
    exit;
}

if (!isset($_GET['request_id'])) {
    echo "Invalid request.";
    exit;
}
$request_id = $_GET['request_id'];

// 🔹 Fetch items for this request belonging to this vendor
$stmt = $pdo->prepare("
    SELECT vo.*, vp.item_name, vp.price, vp.picture, vo.vendor_id
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

// Filter items by logged-in vendor
$items = array_filter($items, fn($it) => $it['vendor_id'] == $logged_vendor_id);

if (!$items) {
    echo "No items found for your account.";
    exit;
}

// 🔹 Get receipt_id for this purchase request
$receiptStmt = $pdo->prepare("
    SELECT id 
    FROM receipts 
    WHERE order_id = ? 
    LIMIT 1
");
$receiptStmt->execute([$request_id]);
$receipt = $receiptStmt->fetch(PDO::FETCH_ASSOC);

// 🔹 Get paid_at from receipt_payments
$payment = null;
if ($receipt) {
    $payStmt = $pdo->prepare("
        SELECT id, paid_at 
        FROM receipt_payments 
        WHERE receipt_id = ? AND status = 'Paid'
        ORDER BY id DESC 
        LIMIT 1
    ");
    $payStmt->execute([$receipt['id']]);
    $payment = $payStmt->fetch(PDO::FETCH_ASSOC);
}

// 🔹 Decide status & delivered date
if ($payment) {
    $current_status = "Completed";
    $delivered_date = $payment['paid_at'] ? date("F d, Y h:i A", strtotime($payment['paid_at'])) : "N/A";
    $receipt_id = $receipt['id'];
} else {
    $current_status = $items[0]['status']; // fallback from vendor_orders
    $delivered_date = "N/A";
    $receipt_id = null;
}

$status_order = ["Processing", "Packed", "Shipped"]; // ✅ no "Completed" here
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

<!-- 🔹 Status update form (hidden if Completed) -->
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
    <div class="alert alert-success mt-3 text-center fw-bold d-flex justify-content-between align-items-center">
         Order Completed
         <?php if ($receipt_id): ?>
             <a href="receipt_view.php?id=<?= $payment['id'] ?>" target="_blank" class="btn btn-sm btn-success">&#128196; View Receipt</a>
         <?php endif; ?>
    </div>
<?php endif; ?>
