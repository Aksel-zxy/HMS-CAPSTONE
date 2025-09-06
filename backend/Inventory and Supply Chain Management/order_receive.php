<?php
session_start();
require 'db.php';

// Handle receiving
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['receive'])) {
    $purchase_request_id = $_POST['order_id'];
    $received_qtys = $_POST['received_qty'];
    $expirations = $_POST['expiration'] ?? [];

    // Fetch vendor info
    $stmt = $pdo->prepare("SELECT * FROM vendor_orders WHERE purchase_request_id=? LIMIT 1");
    $stmt->execute([$purchase_request_id]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($order) {
        $vendor_id = $order['vendor_id'];

        // Get all shipped items
        $stmt = $pdo->prepare("
            SELECT vo.*, vp.item_name, vp.item_type, vp.sub_type, vp.price
            FROM vendor_orders vo
            JOIN vendor_products vp ON vo.item_id = vp.id
            WHERE vo.purchase_request_id=? AND vo.vendor_id=? AND vo.status='Shipped'
        ");
        $stmt->execute([$purchase_request_id, $vendor_id]);
        $orders_to_receive = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if ($orders_to_receive) {
            $subtotal = 0;

            foreach ($orders_to_receive as $o) {
                $item_id = $o['item_id'];
                $received = isset($received_qtys[$o['id']]) ? intval($received_qtys[$o['id']]) : 0;

                if ($received > 0) {
                    // ✅ If medicine → put into medicine_batches only
                    if (strtolower($o['item_type']) === 'medicine' || strtolower($o['item_type']) === 'medications and pharmacy supplies') {
                        $exp_date = $expirations[$o['id']] ?? null;
                        if (!$exp_date) {
                            $_SESSION['error_message'] = "❌ Expiration date required for medicine: " . htmlspecialchars($o['item_name']);
                            header("Location: " . $_SERVER['REQUEST_URI']);
                            exit;
                        }

                        $stmtMed = $pdo->prepare("INSERT INTO medicine_batches (item_id, batch_no, quantity, expiration_date) VALUES (?, ?, ?, ?)");
                        $stmtMed->execute([$item_id, uniqid("BATCH"), $received, $exp_date]);

                    } else {
                        // ✅ Non-medicine → goes directly into inventory
                        $stmt2 = $pdo->prepare("SELECT * FROM inventory WHERE item_id=?");
                        $stmt2->execute([$item_id]);
                        $inv = $stmt2->fetch(PDO::FETCH_ASSOC);

                        if ($inv) {
                            $stmt2 = $pdo->prepare("UPDATE inventory SET quantity = quantity + ?, received_at = NOW() WHERE item_id=?");
                            $stmt2->execute([$received, $item_id]);
                        } else {
                            $stmt2 = $pdo->prepare("INSERT INTO inventory (item_id, item_name, item_type, sub_type, quantity, price) VALUES (?, ?, ?, ?, ?, ?)");
                            $stmt2->execute([$item_id, $o['item_name'], $o['item_type'], $o['sub_type'], $received, $o['price']]);
                        }
                    }

                    $subtotal += $received * $o['price'];

                    // ✅ Mark vendor order completed
                    $stmt = $pdo->prepare("UPDATE vendor_orders SET status='Completed' WHERE id=?");
                    $stmt->execute([$o['id']]);
                }
            }

            if ($subtotal > 0) {
                $vat = $subtotal * 0.12;
                $total = $subtotal + $vat;

                // ✅ Check if receipt already exists today for vendor
                $stmt = $pdo->prepare("SELECT id FROM receipts WHERE vendor_id=? AND DATE(created_at)=CURDATE()");
                $stmt->execute([$vendor_id]);
                $existing_receipt = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($existing_receipt) {
                    $receipt_id = $existing_receipt['id'];

                    $stmt = $pdo->prepare("UPDATE receipts SET subtotal=subtotal+?, vat=vat+?, total=total+? WHERE id=?");
                    $stmt->execute([$subtotal, $vat, $total, $receipt_id]);
                } else {
                    $stmt = $pdo->prepare("INSERT INTO receipts (order_id, vendor_id, subtotal, vat, total) VALUES (?, ?, ?, ?, ?)");
                    $stmt->execute([$purchase_request_id, $vendor_id, $subtotal, $vat, $total]);
                    $receipt_id = $pdo->lastInsertId();

                    $stmt = $pdo->prepare("INSERT INTO receipt_payments (receipt_id, status) VALUES (?, 'Pending')");
                    $stmt->execute([$receipt_id]);
                }

                // ✅ Insert receipt items
                foreach ($orders_to_receive as $o) {
                    $received = isset($received_qtys[$o['id']]) ? intval($received_qtys[$o['id']]) : 0;
                    if ($received > 0) {
                        $stmt = $pdo->prepare("
                            INSERT INTO receipt_items (receipt_id, item_id, item_name, quantity_received, price, subtotal)
                            VALUES (?, ?, ?, ?, ?, ?)
                        ");
                        $stmt->execute([$receipt_id, $o['item_id'], $o['item_name'], $received, $o['price'], $received * $o['price']]);
                    }
                }

                header("Location: receipt.php?receipt_id=" . $receipt_id);
                exit;
            }
        }
    }
}

// Fetch shipped grouped by purchase request
$stmt = $pdo->query("
    SELECT vo.purchase_request_id, vo.vendor_id, vo.created_at,
           SUM(vo.quantity) as total_qty, SUM(vo.quantity * vp.price) as total_price
    FROM vendor_orders vo
    JOIN vendor_products vp ON vo.item_id = vp.id
    WHERE vo.status='Shipped'
    GROUP BY vo.purchase_request_id, vo.vendor_id, vo.created_at
    ORDER BY vo.created_at DESC
");
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Orders Ready to Receive</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<div class="main-sidebar">
    <?php include 'Inventory_dashboard.php'; ?>
</div>

<div class="container py-5">
    <h2 class="mb-4">Orders Ready to Receive</h2>

    <?php if (isset($_SESSION['error_message'])): ?>
        <div class="alert alert-danger"><?= $_SESSION['error_message']; unset($_SESSION['error_message']); ?></div>
    <?php endif; ?>

    <?php if (count($orders) > 0): ?>
        <table class="table table-bordered bg-white shadow">
            <thead class="table-dark">
                <tr>
                    <th>Purchase Request</th>
                    <th>Ordered Qty</th>
                    <th>Total Price</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($orders as $o): ?>
                <tr>
                    <td>Purchase Request #<?= $o['purchase_request_id'] ?></td>
                    <td><?= $o['total_qty'] ?></td>
                    <td>₱<?= number_format($o['total_price'], 2) ?></td>
                    <td><span class="badge bg-info">Shipped</span></td>
                    <td>
                        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#viewModal<?= $o['purchase_request_id'] ?>">
                            View
                        </button>
                    </td>
                </tr>

                <!-- Modal -->
                <div class="modal fade" id="viewModal<?= $o['purchase_request_id'] ?>" tabindex="-1">
                    <div class="modal-dialog modal-xl">
                        <div class="modal-content">
                            <form method="post" class="order-form">
                                <div class="modal-header">
                                    <h5 class="modal-title">📦 Order #<?= $o['purchase_request_id'] ?></h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>
                                <div class="modal-body">
                                    <table class="table table-striped table-bordered">
                                        <thead class="table-dark">
                                            <tr>
                                                <th>Item</th>
                                                <th>Ordered Qty</th>
                                                <th>Price per Item</th>
                                                <th>Status</th>
                                                <th>Qty Received</th>
                                                <th>Expiration Date</th>
                                                <th>Subtotal</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                        <?php
                                        $stmt2 = $pdo->prepare("
                                            SELECT vo.*, vp.item_name, vp.price, vp.item_type
                                            FROM vendor_orders vo
                                            JOIN vendor_products vp ON vo.item_id = vp.id
                                            WHERE vo.purchase_request_id=? AND vo.status='Shipped'
                                        ");
                                        $stmt2->execute([$o['purchase_request_id']]);
                                        $items = $stmt2->fetchAll(PDO::FETCH_ASSOC);
                                        $subtotal = 0;
                                        foreach ($items as $it): 
                                            $lineTotal = $it['quantity'] * $it['price'];
                                            $subtotal += $lineTotal;
                                        ?>
                                            <tr>
                                                <td><?= htmlspecialchars($it['item_name']) ?></td>
                                                <td><?= $it['quantity'] ?></td>
                                                <td>₱<?= number_format($it['price'], 2) ?></td>
                                                <td><span class="badge bg-info"><?= $it['status'] ?></span></td>
                                                <td>
                                                    <input type="number" 
                                                           name="received_qty[<?= $it['id'] ?>]" 
                                                           value="<?= $it['quantity'] ?>" 
                                                           min="1" max="<?= $it['quantity'] ?>" 
                                                           class="form-control qty-input" 
                                                           data-price="<?= $it['price'] ?>">
                                                </td>
                                                <td>
                                                    <?php if (strtolower($it['item_type']) === 'medicine' || strtolower($it['item_type']) === 'medications and pharmacy supplies'): ?>
                                                        <input type="date" name="expiration[<?= $it['id'] ?>]" class="form-control" required>
                                                    <?php else: ?>
                                                        -
                                                    <?php endif; ?>
                                                </td>
                                                <td class="subtotal">₱<?= number_format($lineTotal, 2) ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                        </tbody>
                                    </table>

                                    <?php 
                                        $vat = $subtotal * 0.12;
                                        $grandTotal = $subtotal + $vat;
                                    ?>
                                    <div class="text-end mt-4">
                                        <p><strong>Subtotal:</strong> <span class="subtotal-display">₱<?= number_format($subtotal, 2) ?></span></p>
                                        <p><strong>VAT (12%):</strong> <span class="vat-display">₱<?= number_format($vat, 2) ?></span></p>
                                        <h5><strong>Grand Total:</strong> <span class="grandtotal-display">₱<?= number_format($grandTotal, 2) ?></span></h5>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <input type="hidden" name="order_id" value="<?= $o['purchase_request_id'] ?>">
                                    <button type="submit" name="receive" class="btn btn-success">Confirm Receive</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

            <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p class="alert alert-info">No shipped orders available for receiving.</p>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.querySelectorAll('.order-form').forEach(form => {
    form.querySelectorAll('.qty-input').forEach(input => {
        input.addEventListener('input', () => {
            let subtotal = 0;
            form.querySelectorAll('.qty-input').forEach(inp => {
                let price = parseFloat(inp.dataset.price);
                let qty = parseInt(inp.value) || 0;
                subtotal += price * qty;

                inp.closest('tr').querySelector('.subtotal').innerText =
                    "₱" + (price * qty).toLocaleString(undefined, { minimumFractionDigits: 2 });
            });

            let vat = subtotal * 0.12;
            let grandTotal = subtotal + vat;

            form.querySelector('.subtotal-display').innerText =
                "₱" + subtotal.toLocaleString(undefined, { minimumFractionDigits: 2 });
            form.querySelector('.vat-display').innerText =
                "₱" + vat.toLocaleString(undefined, { minimumFractionDigits: 2 });
            form.querySelector('.grandtotal-display').innerText =
                "₱" + grandTotal.toLocaleString(undefined, { minimumFractionDigits: 2 });
        });
    });
});
</script>

</body>
</html>
