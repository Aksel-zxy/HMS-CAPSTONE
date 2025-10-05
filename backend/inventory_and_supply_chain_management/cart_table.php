<?php
require 'db.php';
?>

<?php if (!empty($_SESSION['cart'])): ?>
    <form id="updateCartForm">
        <table class="table table-bordered align-middle">
            <thead class="table-dark">
                <tr class="text-center">
                    <th>Item</th>
                    <th width="120">Qty</th>
                    <th width="120">Price</th>
                    <th width="120">Total</th>
                    <th width="100">Action</th>
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
                        <td>
                            <strong><?= htmlspecialchars($item['name']) ?></strong><br>
                            <small class="text-muted">
                                <?= htmlspecialchars($unitType) ?>
                                <?php if ($unitType === "Box" && $pcsPerBox > 0): ?>
                                    (<?= $pcsPerBox ?> pcs/Box)
                                <?php endif; ?>
                            </small>
                        </td>
                        <td class="text-center">
                            <input type="number"
                                name="qty[<?= $id ?>]"
                                value="<?= $item['qty'] ?>"
                                min="1"
                                class="form-control text-center qtyInput">
                            <small class="text-muted qtyLabel"><?= $qtyLabel ?></small>
                        </td>
                        <td class="text-end">₱<?= number_format($item['price'], 2) ?></td>
                        <td class="text-end">₱<span class="rowTotal"><?= number_format($total, 2) ?></span></td>
                        <td class="text-center">
                            <button type="button" class="btn btn-outline-danger btn-sm removeItem" data-id="<?= $id ?>">
                                <i class="bi bi-trash"></i>
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <tr>
                    <th colspan="3" class="text-end">Grand Total:</th>
                    <th colspan="2" class="text-end text-success fw-bold">₱<span id="cartTotal"><?= number_format($grand, 2) ?></span></th>
                </tr>
            </tbody>
        </table>

        <div class="d-flex justify-content-between mt-3">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                <i class="bi bi-arrow-left-circle"></i> Continue Shopping
            </button>
            <button type="button" id="submitOrder" class="btn btn-success">
                <i class="bi bi-bag-check"></i> Process Order
            </button>
        </div>
    </form>

<?php else: ?>
    <p class="text-center text-muted fs-5 py-4">🛒 Your cart is empty.</p>
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

    // Update quantity
    $(document).on("input", ".qtyInput", function() {
        let formData = $("#updateCartForm").serialize();
        $.post("purchase_order.php", formData + "&ajax=update", function(res) {
            let data = JSON.parse(res);
            $("#cartContent").html(data.cart_html);
            $("#cartCount").text(data.count);
        });
    });

    // ✅ Process Order (direct to vendor_orders)
    $(document).on("click", "#submitOrder", function() {
        if (confirm("Are you sure you want to process this order?")) {
            $.post("purchase_order.php", { ajax: "submit" }, function(res) {
                let data = JSON.parse(res);

                if (data.success) {
                    alert("✅ " + data.message);
                    location.reload();
                } else {
                    alert("⚠️ " + data.message);
                }
            });
        }
    });
</script>
