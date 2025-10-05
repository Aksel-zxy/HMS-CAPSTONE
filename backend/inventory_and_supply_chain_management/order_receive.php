<?php
session_start();
require 'db.php';
error_reporting(E_ALL);
ini_set('display_errors', 1);

// ---------------- Handle Receiving ----------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['receive'])) {
    $po_number = $_POST['order_id'];
    $received_qtys = $_POST['received_qty'] ?? [];

    // Fetch vendor order
    $stmt = $pdo->prepare("SELECT * FROM vendor_orders WHERE purchase_order_number=? LIMIT 1");
    $stmt->execute([$po_number]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($order) {
        $vendor_id = $order['vendor_id'];
        $items = json_decode($order['items'], true);
        $subtotal = 0;

        foreach ($items as $item_id => $it) {
            $received = isset($received_qtys[$item_id]) ? intval($received_qtys[$item_id]) : 0;
            if ($received <= 0) continue;

            // Fetch product info
            $stmt2 = $pdo->prepare("SELECT * FROM vendor_products WHERE id=? LIMIT 1");
            $stmt2->execute([$item_id]);
            $product = $stmt2->fetch(PDO::FETCH_ASSOC);

            if (!$product) continue;

            $unit_type = $it['unit_type'] ?? 'Piece';
            $pcs_per_box = intval($it['pcs_per_box'] ?? 0);

            if ($unit_type === 'Box' && $pcs_per_box) {
                $total_qty = $received * $pcs_per_box;
                $lineSubtotal = $it['price'] * $received;
            } else {
                $total_qty = $received;
                $lineSubtotal = $it['price'] * $received;
            }

            $subtotal += $lineSubtotal;

            // ---------------- Inventory Update ----------------
            $stmt_inv = $pdo->prepare("SELECT * FROM inventory WHERE item_id=?");
            $stmt_inv->execute([$item_id]);
            $inv = $stmt_inv->fetch(PDO::FETCH_ASSOC);

            $now = date('Y-m-d H:i:s');
            if ($inv) {
                $stmt_upd = $pdo->prepare("UPDATE inventory 
                    SET quantity=quantity+?, total_qty=total_qty+?, price=?, unit_type=?, pcs_per_box=?, received_at=? 
                    WHERE item_id=?");
                $stmt_upd->execute([$received, $total_qty, $it['price'], $unit_type, $pcs_per_box, $now, $item_id]);
            } else {
                $stmt_ins = $pdo->prepare("INSERT INTO inventory 
                    (item_id, item_name, item_type, category, sub_type, quantity, total_qty, price, unit_type, pcs_per_box, received_at, location, min_stock, max_stock) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Main Storage', 0, 9999)");
                $stmt_ins->execute([
                    $item_id,
                    $it['name'],
                    $product['item_type'],
                    null,
                    $product['sub_type'] ?? null,
                    $received,
                    $total_qty,
                    $it['price'],
                    $unit_type,
                    $pcs_per_box,
                    $now
                ]);
            }

            // ---------------- Medicine Batches ----------------
            if (strtolower($product['item_type']) === 'medications and pharmacy supplies') {
                $batch_no = 'BATCH-' . uniqid();
                $stmt_batch = $pdo->prepare("INSERT INTO medicine_batches (item_id, batch_no, quantity, received_at) VALUES (?, ?, ?, ?)");
                $stmt_batch->execute([$item_id, $batch_no, $total_qty, $now]);
            }
        }

        // ---------------- Receipt Handling ----------------
        if ($subtotal > 0) {
            $vat = $subtotal * 0.12;
            $total = $subtotal + $vat;

            $stmt_r = $pdo->prepare("SELECT id FROM receipts WHERE vendor_id=? AND order_id=?");
            $stmt_r->execute([$vendor_id, $po_number]);
            $existing_receipt = $stmt_r->fetch(PDO::FETCH_ASSOC);

            if ($existing_receipt) {
                $receipt_id = $existing_receipt['id'];
                $stmt_upd = $pdo->prepare("UPDATE receipts SET subtotal=subtotal+?, vat=vat+?, total=total+? WHERE id=?");
                $stmt_upd->execute([$subtotal, $vat, $total, $receipt_id]);
            } else {
                $stmt_ins = $pdo->prepare("INSERT INTO receipts (order_id, vendor_id, subtotal, vat, total) VALUES (?, ?, ?, ?, ?)");
                $stmt_ins->execute([$po_number, $vendor_id, $subtotal, $vat, $total]);
                $receipt_id = $pdo->lastInsertId();

                $stmt_pay = $pdo->prepare("INSERT INTO receipt_payments (receipt_id, status) VALUES (?, 'Pending')");
                $stmt_pay->execute([$receipt_id]);
            }

            // Insert receipt items
            foreach ($items as $item_id => $it) {
                $received = isset($received_qtys[$item_id]) ? intval($received_qtys[$item_id]) : 0;
                if ($received <= 0) continue;

                if ($it['unit_type'] === 'Box' && intval($it['pcs_per_box'])) {
                    $lineSubtotal = $it['price'] * $received;
                } else {
                    $lineSubtotal = $it['price'] * $received;
                }

                $stmt_item = $pdo->prepare("INSERT INTO receipt_items (receipt_id, item_id, item_name, quantity_received, price, subtotal, unit_type, pcs_per_box) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt_item->execute([
                    $receipt_id,
                    $item_id,
                    $it['name'],
                    $received,
                    $it['price'],
                    $lineSubtotal,
                    $it['unit_type'],
                    $it['pcs_per_box']
                ]);
            }

            // Mark order as Completed
            $stmt_done = $pdo->prepare("UPDATE vendor_orders SET status='Completed' WHERE purchase_order_number=?");
            $stmt_done->execute([$po_number]);

            header("Location: receipt.php?receipt_id=" . $receipt_id);
            exit;
        }
    }
}

// ---------------- Fetch Orders ----------------
$stmt = $pdo->query("SELECT * FROM vendor_orders WHERE status='Shipped' ORDER BY created_at DESC");
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
    <?php include 'inventory_sidebar.php'; ?>
</div>

<div class="container py-5">
<h2>Orders Ready to Receive</h2>

<?php if (count($orders) > 0): ?>
<table class="table table-bordered bg-white shadow">
    <thead class="table-dark">
        <tr>
            <th>PO Number</th>
            <th>Total Price</th>
            <th>Items</th>
            <th>Status</th>
            <th>Action</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($orders as $o): ?>
        <tr>
            <td><?= htmlspecialchars($o['purchase_order_number']) ?></td>
            <td>₱<?= number_format($o['total_price'],2) ?></td>
            <td>
                <?php 
                $items = json_decode($o['items'], true);
                foreach ($items as $id => $it) echo htmlspecialchars($it['name'])." x".$it['qty']."<br>";
                ?>
            </td>
            <td><span class="badge bg-info"><?= $o['status'] ?></span></td>
            <td>
                <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#viewModal<?= $o['purchase_order_number'] ?>">View</button>
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
<div class="modal fade" id="viewModal<?= $o['purchase_order_number'] ?>" tabindex="-1">
<div class="modal-dialog modal-xl">
<div class="modal-content">
<form method="post" class="order-form">
<div class="modal-header">
<h5 class="modal-title">📦 Order #<?= htmlspecialchars($o['purchase_order_number']) ?></h5>
<button type="button" class="btn-close" data-bs-dismiss="modal"></button>
</div>
<div class="modal-body">
<table class="table table-striped table-bordered">
    <thead class="table-dark">
        <tr>
            <th>Picture</th>
            <th>Item</th>
            <th>Qty Ordered</th>
            <th>Unit</th>
            <th>Qty Received</th>
        </tr>
    </thead>
    <tbody>
    <?php 
    $items = json_decode($o['items'], true);
    foreach($items as $id => $it):
        $stmt_pic = $pdo->prepare("SELECT picture FROM vendor_products WHERE id=? LIMIT 1");
        $stmt_pic->execute([$id]);
        $pic = $stmt_pic->fetchColumn();
    ?>
        <tr>
            <td>
                <?php if ($pic): ?>
                <img src="<?= htmlspecialchars($pic) ?>" style="width:60px;height:60px;object-fit:cover;border-radius:4px;">
                <?php else: ?>
                <span class="text-muted">N/A</span>
                <?php endif; ?>
            </td>
            <td><?= htmlspecialchars($it['name']) ?></td>
            <td><?= $it['qty'] ?></td>
            <td><?= htmlspecialchars($it['unit_type']) ?></td>
            <td><input type="number" name="received_qty[<?= $id ?>]" value="<?= $it['qty'] ?>" min="1" max="<?= $it['qty'] ?>" class="form-control"></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
<div class="modal-footer">
<input type="hidden" name="order_id" value="<?= htmlspecialchars($o['purchase_order_number']) ?>">
<button type="submit" name="receive" class="btn btn-success">Confirm Receive</button>
</div>
</form>
</div>
</div>
</div>
<?php endforeach; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
