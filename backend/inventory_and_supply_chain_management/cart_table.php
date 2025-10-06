<?php if(!empty($_SESSION['cart'])): ?>
<form id="updateCartForm">
<table class="table table-bordered align-middle">
    <thead class="table-dark">
        <tr>
            <th>Item</th>
            <th>Unit</th>
            <th>Qty</th>
            <th>Price</th>
            <th>Subtotal</th>
            <th>Action</th>
        </tr>
    </thead>
    <tbody>
        <?php 
        $grand_total = 0;
        foreach($_SESSION['cart'] as $id => $item):
            $subtotal = $item['price'] * $item['qty'];
            $grand_total += $subtotal;
        ?>
        <tr>
            <td><?= htmlspecialchars($item['name']) ?></td>
            <td><?= htmlspecialchars($item['unit_type'] ?? 'Piece') ?></td>
            <td><input type="number" class="form-control qtyInput" name="qty[<?= $id ?>]" value="<?= $item['qty'] ?>" min="1"></td>
            <td>₱<?= number_format($item['price'],2) ?></td>
            <td>₱<?= number_format($subtotal,2) ?></td>
            <td>
                <button type="button" class="btn btn-sm btn-danger removeItem" data-id="<?= $id ?>">Remove</button>
            </td>
        </tr>
        <?php endforeach; ?>
    </tbody>
    <tfoot>
        <tr class="table-dark">
            <td colspan="4" class="text-end"><strong>Total:</strong></td>
            <td colspan="2"><strong>₱<?= number_format($grand_total,2) ?></strong></td>
        </tr>
    </tfoot>
</table>
<button type="button" class="btn btn-success w-100" id="submitRequest">Place Order</button>
</form>

<script>
$(document).on("click","#submitRequest",function(){
    $.post("purchase_order.php",{ajax:"submit"},function(res){
        let data = JSON.parse(res);
        alert(data.message);
        if(data.success){
            $("#cartContent").html('');
            $("#cartCount").text('0');
        }
    });
});
</script>
<?php else: ?>
<p class="text-center text-muted">Your cart is empty.</p>
<?php endif; ?>
