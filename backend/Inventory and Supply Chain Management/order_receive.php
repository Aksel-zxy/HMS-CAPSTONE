<?php
session_start();
require 'db.php';

// ---------------- Handle Receiving ----------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['receive'])) {
    $purchase_request_id = $_POST['order_id'];
    $received_qtys = $_POST['received_qty'] ?? [];

    // Fetch vendor info
    $stmt = $pdo->prepare("SELECT * FROM vendor_orders WHERE purchase_request_id=? LIMIT 1");
    $stmt->execute([$purchase_request_id]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($order) {
        $vendor_id = $order['vendor_id'];

        // Get shipped items with product info
        $stmt = $pdo->prepare("
            SELECT vo.*, vp.item_name, vp.item_type, vp.sub_type, vp.price, vp.unit_type, vp.pcs_per_box
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
                    $now = date('Y-m-d H:i:s');

                    // ✅ If Box → convert to total pieces
                    if ($o['unit_type'] === "Box" && $o['pcs_per_box']) {
                        $box_qty   = $received; // number of boxes
                        $total_qty = $box_qty * (int)$o['pcs_per_box']; // convert to pieces
                    } else {
                        $box_qty   = 0;
                        $total_qty = $received; // already in pieces
                    }

                    // ---------------- Inventory Update ----------------
                    $stmt2 = $pdo->prepare("SELECT * FROM inventory WHERE item_id=?");
                    $stmt2->execute([$item_id]);
                    $inv = $stmt2->fetch(PDO::FETCH_ASSOC);

                    if ($inv) {
                        // Update existing inventory
                        $stmt2 = $pdo->prepare("UPDATE inventory 
                            SET total_qty = total_qty + ?, 
                                price = ?, 
                                unit_type = ?, 
                                pcs_per_box = ?, 
                                received_at = ? 
                            WHERE item_id=?");
                        $stmt2->execute([
                            $total_qty,      // always pieces ✅
                            $o['price'],
                            $o['unit_type'],
                            $o['pcs_per_box'],
                            $now,
                            $item_id
                        ]);
                    } else {
                        // Insert new item into inventory
                        $stmt2 = $pdo->prepare("INSERT INTO inventory 
                            (item_id, item_name, item_type, category, sub_type, quantity, total_qty, price, unit_type, pcs_per_box, received_at, location, min_stock, max_stock) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Main Storage', 0, 9999)");
                        $stmt2->execute([
                            $item_id,
                            $o['item_name'],
                            $o['item_type'],
                            null,
                            $o['sub_type'],
                            $received,      // as entered (boxes or pcs)
                            $total_qty,     // always pcs ✅
                            $o['price'],
                            $o['unit_type'],
                            $o['pcs_per_box'],
                            $now
                        ]);
                    }

                    // ---------------- Medicine Batches ----------------
                    if (strtolower($o['item_type']) === 'medications and pharmacy supplies') {
                        $batch_no = 'BATCH-' . uniqid();
                        $stmt2 = $pdo->prepare("INSERT INTO medicine_batches 
                            (item_id, batch_no, quantity, received_at, unit_type, pcs_per_box) 
                            VALUES (?, ?, ?, ?, ?, ?)");
                        $stmt2->execute([$item_id, $batch_no, $total_qty, $now, $o['unit_type'], $o['pcs_per_box']]);
                    }

                    // ---------------- Subtotal ----------------
                    $subtotal += $o['price'] * $received;

                    // Mark as completed
                    $stmt = $pdo->prepare("UPDATE vendor_orders SET status='Completed' WHERE id=?");
                    $stmt->execute([$o['id']]);
                }
            }

            // ---------------- Receipt Handling ----------------
            if ($subtotal > 0) {
                $vat = $subtotal * 0.12;
                $total = $subtotal + $vat;

                $stmt = $pdo->prepare("SELECT id FROM receipts WHERE vendor_id=? AND order_id=?");
                $stmt->execute([$vendor_id, $purchase_request_id]);
                $existing_receipt = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($existing_receipt) {
                    $receipt_id = $existing_receipt['id'];
                    $stmt = $pdo->prepare("UPDATE receipts 
                        SET subtotal=subtotal+?, vat=vat+?, total=total+? 
                        WHERE id=?");
                    $stmt->execute([$subtotal, $vat, $total, $receipt_id]);
                } else {
                    $stmt = $pdo->prepare("INSERT INTO receipts 
                        (order_id, vendor_id, subtotal, vat, total) 
                        VALUES (?, ?, ?, ?, ?)");
                    $stmt->execute([$purchase_request_id, $vendor_id, $subtotal, $vat, $total]);
                    $receipt_id = $pdo->lastInsertId();

                    $stmt = $pdo->prepare("INSERT INTO receipt_payments (receipt_id, status) VALUES (?, 'Pending')");
                    $stmt->execute([$receipt_id]);
                }

                foreach ($orders_to_receive as $o) {
                    $received = isset($received_qtys[$o['id']]) ? intval($received_qtys[$o['id']]) : 0;
                    if ($received > 0) {
                        $lineSubtotal = $o['price'] * $received;
                        $stmt = $pdo->prepare("
                            INSERT INTO receipt_items 
                            (receipt_id, item_id, item_name, quantity_received, price, subtotal, unit_type, pcs_per_box)
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                        ");
                        $stmt->execute([
                            $receipt_id, 
                            $o['item_id'], 
                            $o['item_name'], 
                            $received, 
                            $o['price'], 
                            $lineSubtotal,
                            $o['unit_type'], 
                            $o['pcs_per_box']
                        ]);
                    }
                }

                header("Location: receipt.php?receipt_id=" . $receipt_id);
                exit;
            }
        }
    }
}

// ---------------- Fetch Orders ----------------
$stmt = $pdo->query("
    SELECT purchase_request_id, vendor_id, SUM(quantity) AS total_qty, SUM(price*quantity) AS total_price, GROUP_CONCAT(item_id) AS items
    FROM vendor_orders
    WHERE status='Shipped'
    GROUP BY purchase_request_id, vendor_id
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

    <?php if (count($orders) > 0): ?>
        <table class="table table-bordered bg-white shadow">
            <thead class="table-dark">
                <tr>
                    <th>Purchase Request</th>
                    <th>Ordered Qty</th>
                    <th>Total Price</th>
                    <th>Items</th>
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
                    <td><?= $o['items'] ?></td>
                    <td><span class="badge bg-info">Shipped</span></td>
                    <td>
                        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#viewModal<?= $o['purchase_request_id'] ?>">View</button>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p class="alert alert-info">No shipped orders available for receiving.</p>
    <?php endif; ?>
</div>

<!-- Modals -->
<?php foreach ($orders as $o): ?>
<div class="modal fade" id="viewModal<?= $o['purchase_request_id'] ?>" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <form method="post" class="order-form">
                <div class="modal-header">
                    <h5 class="modal-title">📦 Order #<?= $o['purchase_request_id'] ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <?php
                    $stmt2 = $pdo->prepare("
                        SELECT vo.*, vp.item_name, vp.price, vp.unit_type, vp.pcs_per_box,
                               IFNULL(inv.total_qty, 0) AS inventory_qty
                        FROM vendor_orders vo
                        JOIN vendor_products vp ON vo.item_id = vp.id
                        LEFT JOIN inventory inv ON vp.id = inv.item_id
                        WHERE vo.purchase_request_id=? AND vo.status='Shipped'
                    ");
                    $stmt2->execute([$o['purchase_request_id']]);
                    $items = $stmt2->fetchAll(PDO::FETCH_ASSOC);
                    ?>
                    <table class="table table-striped table-bordered">
                        <thead class="table-dark">
                            <tr>
                                <th>Item</th>
                                <th>Ordered Qty</th>
                                <th>Unit</th>
                                <th>Total Pcs</th>
                                <th>Current Inventory</th>
                                <th>Price</th>
                                <th>Status</th>
                                <th>Qty Received</th>
                                <th>Subtotal</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($items as $it):
                            $lineTotal = $it['price'] * $it['quantity'];
                            $totalPcs = ($it['unit_type'] === "Box")
                                ? $it['quantity'] * ($it['pcs_per_box'] ?? 0)
                                : $it['quantity'];
                        ?>
                            <tr>
                                <td><?= htmlspecialchars($it['item_name']) ?></td>
                                <td><?= $it['quantity'] ?></td>
                                <td><?= htmlspecialchars($it['unit_type']) ?><?php if($it['unit_type']==='Box'): ?> (<?= $it['pcs_per_box'] ?> pcs)<?php endif; ?></td>
                                <td class="total-pcs"><?= $totalPcs ?></td>
                                <td><?= $it['inventory_qty'] ?></td>
                                <td>₱<?= number_format($it['price'], 2) ?></td>
                                <td><span class="badge bg-info"><?= $it['status'] ?></span></td>
                                <td>
                                    <input type="number"
                                           name="received_qty[<?= $it['id'] ?>]"
                                           value="<?= $it['quantity'] ?>"
                                           min="1" max="<?= $it['quantity'] ?>"
                                           class="form-control qty-input"
                                           data-price="<?= $it['price'] ?>"
                                           data-unit="<?= $it['unit_type'] ?>"
                                           data-pcs="<?= $it['pcs_per_box'] ?>">
                                </td>
                                <td class="subtotal">₱<?= number_format($lineTotal,2) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
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

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.querySelectorAll('.order-form').forEach(form => {
    form.querySelectorAll('.qty-input').forEach(input => {
        input.addEventListener('input', () => {
            form.querySelectorAll('tbody tr').forEach(row => {
                const qtyInput = row.querySelector('.qty-input');
                if(!qtyInput) return;

                let qty = parseInt(qtyInput.value) || 0;
                let price = parseFloat(qtyInput.dataset.price) || 0;
                let unit = qtyInput.dataset.unit;
                let pcs = parseInt(qtyInput.dataset.pcs) || 0;

                let subtotal = price * qty;
                let totalPcs = (unit === "Box") ? qty * pcs : qty;

                row.querySelector('.total-pcs').innerText = totalPcs;
                row.querySelector('.subtotal').innerText = "₱" + subtotal.toLocaleString(undefined, {minimumFractionDigits:2, maximumFractionDigits:2});
            });
        });
    });
});
</script>

</body>
</html>
