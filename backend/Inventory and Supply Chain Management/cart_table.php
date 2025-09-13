<?php

require 'db.php'; // only if you want to save requests to DB

// ✅ Handle Request to Admin
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submitRequest'])) {
    if (!empty($_SESSION['cart'])) {
        // Example: save request in DB (optional)
        /*
        $stmt = $pdo->prepare("INSERT INTO purchase_requests (user_id, created_at) VALUES (?, NOW())");
        $stmt->execute([$_SESSION['user_id']]); 
        $request_id = $pdo->lastInsertId();

        foreach ($_SESSION['cart'] as $id => $item) {
            $stmt = $pdo->prepare("INSERT INTO purchase_request_items 
                (request_id, item_id, item_name, qty, price, unit_type, pcs_per_box) 
                VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $request_id,
                $id,
                $item['name'],
                $item['qty'],
                $item['price'],
                $item['unit_type'] ?? 'Piece',
                $item['pcs_per_box'] ?? null
            ]);
        }
        */

        // ✅ Clear cart
        unset($_SESSION['cart']);

        // ✅ Redirect with success message
        echo "<script>
            alert('✅ Request successfully sent to admin!');
            window.location.href='purchase_order.php';
        </script>";
        exit;
    } else {
        echo "<script>
            alert('⚠️ Your cart is empty.');
            window.location.href='purchase_order.php';
        </script>";
        exit;
    }
}
?>

<?php if (!empty($_SESSION['cart'])): ?>
<form id="updateCartForm" method="post">
<table class="table table-bordered">
    <thead class="table-dark">
        <tr>
            <th>Item</th>
            <th>Qty</th>
            <th>Price</th>
            <th>Total</th>
            <th>Action</th>
        </tr>
    </thead>
    <tbody>
        <?php 
        $grand = 0; 
        foreach ($_SESSION['cart'] as $id => $item): 
            $unitType = $item['unit_type'] ?? 'Piece';
            $pcsPerBox = !empty($item['pcs_per_box']) ? (int)$item['pcs_per_box'] : 0;

            $total = $item['qty'] * $item['price'];
            $grand += $total;

            if ($unitType === "Box" && $pcsPerBox > 0) {
                $qtyLabel = "{$item['qty']} Boxes (" . ($item['qty'] * $pcsPerBox) . " pcs)";
            } else {
                $qtyLabel = "{$item['qty']} pcs";
            }
        ?>
        <tr data-id="<?= $id ?>" 
            data-unit="<?= htmlspecialchars($unitType) ?>" 
            data-pcs="<?= $pcsPerBox ?>" 
            data-price="<?= $item['price'] ?>">
            
            <td>
                <?= htmlspecialchars($item['name']) ?><br>
                <small>
                    <?= htmlspecialchars($unitType) ?>
                    <?php if ($unitType === "Box" && $pcsPerBox > 0): ?>
                        (<?= $pcsPerBox ?> pcs per Box)
                    <?php endif; ?>
                </small>
            </td>
            <td>
                <input type="number" 
                       name="qty[<?= $id ?>]" 
                       value="<?= $item['qty'] ?>" 
                       min="1" 
                       class="form-control qtyInput">
                <small class="text-muted qtyLabel"><?= $qtyLabel ?></small>
            </td>
            <td>
                ₱<?= number_format($item['price'], 2) ?> 
                <small class="text-muted">/ <?= $unitType ?></small>
            </td>
            <td>₱<span class="rowTotal"><?= number_format($total, 2) ?></span></td>
            <td>
                <button type="button" 
                        class="btn btn-danger btn-sm removeItem" 
                        data-id="<?= $id ?>">
                    Remove
                </button>
            </td>
        </tr>
        <?php endforeach; ?>
        <tr>
            <th colspan="3" class="text-end">Grand Total</th>
            <th colspan="2">₱<span id="cartTotal"><?= number_format($grand, 2) ?></span></th>
        </tr>
    </tbody>
</table>
<div class="d-flex justify-content-between">
    <button type="submit" name="submitRequest" class="btn btn-primary">Request to Admin</button>
</div>
</form>

<script>
// ✅ Auto-update totals & qty labels
document.querySelectorAll(".qtyInput").forEach(input => {
    input.addEventListener("input", function() {
        const row = this.closest("tr");
        const unit = row.dataset.unit;
        const pcsPerBox = parseInt(row.dataset.pcs) || 0;
        const price = parseFloat(row.dataset.price);
        const qty = parseInt(this.value) || 1;

        // Update label
        let label = qty + " pcs";
        if (unit === "Box" && pcsPerBox > 0) {
            label = qty + " Boxes (" + (qty * pcsPerBox) + " pcs)";
        }
        row.querySelector(".qtyLabel").textContent = label;

        // Update row total
        const rowTotal = qty * price;
        row.querySelector(".rowTotal").textContent = rowTotal.toLocaleString(undefined, {minimumFractionDigits:2, maximumFractionDigits:2});

        // Update grand total
        let grand = 0;
        document.querySelectorAll(".qtyInput").forEach(i => {
            const r = i.closest("tr");
            const p = parseFloat(r.dataset.price);
            const q = parseInt(i.value) || 1;
            grand += p * q;
        });
        document.getElementById("cartTotal").textContent = grand.toLocaleString(undefined, {minimumFractionDigits:2, maximumFractionDigits:2});
    });
});
</script>
<?php else: ?>
    <p>Your cart is empty.</p>
<?php endif; ?>
