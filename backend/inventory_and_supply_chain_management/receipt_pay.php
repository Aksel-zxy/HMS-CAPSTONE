<?php
require 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['receipt_id'])) {
    $receipt_id = $_POST['receipt_id'];

    // ✅ Check if payment already exists
    $stmt = $pdo->prepare("SELECT * FROM receipt_payments WHERE receipt_id=?");
    $stmt->execute([$receipt_id]);
    $payment = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($payment) {
        $stmt = $pdo->prepare("UPDATE receipt_payments SET status='Paid', paid_at=NOW() WHERE receipt_id=?");
        $stmt->execute([$receipt_id]);
    } else {
        $stmt = $pdo->prepare("INSERT INTO receipt_payments (receipt_id, status, paid_at) VALUES (?, 'Paid', NOW())");
        $stmt->execute([$receipt_id]);
    }

    // ✅ Fetch receipt items + vendor product info
    $stmt = $pdo->prepare("
        SELECT 
            ri.item_id, 
            ri.item_name, 
            ri.quantity_received, 
            ri.price, 
            vp.item_type,
            vp.unit_type,
            vp.pcs_per_box
        FROM receipt_items ri
        JOIN vendor_products vp ON ri.item_id = vp.id
        WHERE ri.receipt_id = ?
    ");
    $stmt->execute([$receipt_id]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($items as $item) {
        // ✅ Convert to pieces (for inventory consistency)
        $final_quantity = ($item['unit_type'] === 'Box' && !empty($item['pcs_per_box']))
            ? $item['quantity_received'] * $item['pcs_per_box']
            : $item['quantity_received'];

        if (strtolower(trim($item['item_type'])) === 'medications and pharmacy supplies') {
            // ✅ Insert into medicine_batches with NULL expiry (set later in batch&expiry.php)
            $stmt = $pdo->prepare("
                INSERT INTO medicine_batches (
                    item_id, batch_no, quantity, expiration_date, received_at
                ) VALUES (?, CONCAT('BATCH-', UUID()), ?, NULL, NOW())
            ");
            $stmt->execute([$item['item_id'], $item['quantity_received']]);

        } else {
            // ✅ Non-medicine items → update inventory directly
            $stmt = $pdo->prepare("
                INSERT INTO inventory (
                    item_id, item_name, item_type, category, sub_type, quantity, total_qty, price, unit_type, pcs_per_box, received_at, location, min_stock, max_stock
                ) VALUES (?, ?, ?, NULL, NULL, ?, ?, ?, ?, ?, NOW(), 'Main Storage', 0, 9999)
                ON DUPLICATE KEY UPDATE 
                    quantity = quantity + VALUES(quantity),
                    total_qty = total_qty + VALUES(total_qty),
                    price = VALUES(price),
                    unit_type = VALUES(unit_type),
                    pcs_per_box = VALUES(pcs_per_box),
                    received_at = VALUES(received_at)
            ");
            $stmt->execute([
                $item['item_id'],
                $item['item_name'],
                $item['item_type'],
                $item['quantity_received'],  // raw qty (boxes/pieces)
                $final_quantity,             // converted → total pcs
                $item['price'],
                $item['unit_type'],
                $item['pcs_per_box']
            ]);
        }
    }

    // ✅ Redirect to Batch & Expiry Tracking
    header("Location: batch&expiry.php");
    exit;
}
