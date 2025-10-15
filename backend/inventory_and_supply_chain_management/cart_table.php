<?php
include '../../SQL/config.php';
?>

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
                <?php
                $grand = 0;
                foreach ($_SESSION['cart'] as $id => $item):
                    $unitType = $item['unit_type'] ?? 'Piece';
                    $pcsPerBox = !empty($item['pcs_per_box']) ? (int)$item['pcs_per_box'] : 0;
                    $total = $item['qty'] * $item['price'];
                    $grand += $total;
                    $qtyLabel = ($unitType === 'Box' && $pcsPerBox > 0)
                        ? "{$item['qty']} Boxes (" . ($item['qty'] * $pcsPerBox) . " pcs)"
                        : "{$item['qty']} pcs";
                ?>
                    <tr data-id="<?= $id ?>" data-unit="<?= htmlspecialchars($unitType) ?>" data-pcs="<?= $pcsPerBox ?>" data-price="<?= $item['price'] ?>">
                        <td><?= htmlspecialchars($item['name']) ?><br>
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
                        <td>₱<?= number_format($item['price'], 2) ?></td>
                        <td>₱<span class="rowTotal"><?= number_format($total, 2) ?></span></td>
                        <td>
                            <button type="button" class="btn btn-danger btn-sm removeItem" data-id="<?= $id ?>">Remove</button>
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
    <p class="text-center">🛒 Your cart is empty.</p>
<?php endif; ?>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
    // Remove item
    $(document).on("click", ".removeItem", function() {
        $.post("purchase_order.php", {
            ajax: "remove",
            id: $(this).data("id")
        }, function(res) {
            let data = JSON.parse(res);
            $("#cartContent").html(data.cart_html);
            $("#cartCount").text(data.count);
        });
    });

    // Update qty
    $(document).on("input", ".qtyInput", function() {
        let formData = $("#updateCartForm").serialize();
        $.post("purchase_order.php", formData + "&ajax=update", function(res) {
            let data = JSON.parse(res);
            $("#cartContent").html(data.cart_html);
            $("#cartCount").text(data.count);
        });
    });


    // Submit request
    $(document).on("click", "#submitRequest", function() {
        $.post("purchase_order.php", {
            ajax: "submit"
        }, function(res) {
            let data = JSON.parse(res);

            if (data.success) {
                alert("✅ " + data.message);
                location.reload();
            } else if (data.failed) {
                alert("⚠️ " + data.message);
            }
        });
    });
</script>