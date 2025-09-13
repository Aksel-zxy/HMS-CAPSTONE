<?php if (!empty($_SESSION['cart'])): ?>
<form id="updateCartForm">
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
        <?php $grand = 0; foreach ($_SESSION['cart'] as $id => $item): 
            $total = $item['qty'] * $item['price'];
            $grand += $total;
        ?>
        <tr>
            <td>
                <?= htmlspecialchars($item['name']) ?><br>
                <small>
                    <?= htmlspecialchars($item['unit_type'] ?? 'Piece') ?>
                    <?php if (($item['unit_type'] ?? '') === "Box" && !empty($item['pcs_per_box'])): ?>
                        (<?= (int)$item['pcs_per_box'] ?> pcs)
                    <?php endif; ?>
                </small>
            </td>
            <td>
                <input type="number" 
                       name="qty[<?= $id ?>]" 
                       value="<?= $item['qty'] ?>" 
                       min="1" 
                       class="form-control qtyInput">
            </td>
            <td>₱<?= number_format($item['price'], 2) ?></td>
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
    <button type="button" id="submitRequest" class="btn btn-primary">Request to Admin</button>
</div>
</form>
<?php else: ?>
    <p>Your cart is empty.</p>
<?php endif; ?>
