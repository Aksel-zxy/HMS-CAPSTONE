<?php
require 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['receipt_id'])) {
    $receipt_id = $_POST['receipt_id'];

    // ✅ Check if payment exists
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

    // ✅ Fetch items with unit_type & pcs_per_box
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

    // ✅ Save medicines into medicine_batches, others into inventory
    foreach ($items as $item) {
        // Convert quantity to pieces if unit is Box
        $final_quantity = $item['unit_type'] === 'Box' && !empty($item['pcs_per_box'])
            ? $item['quantity_received'] * $item['pcs_per_box']
            : $item['quantity_received'];

        if (strtolower(trim($item['item_type'])) === 'medications and pharmacy supplies') {
            // Save into medicine_batches
            $stmt = $pdo->prepare("
                INSERT INTO medicine_batches (
                    item_id, batch_no, quantity, expiration_date, received_at, unit_type, pcs_per_box
                ) VALUES (?, CONCAT('BATCH-', UUID()), ?, NULL, NOW(), ?, ?)
            ");
            $stmt->execute([
                $item['item_id'],
                $final_quantity,
                $item['unit_type'],
                $item['pcs_per_box']
            ]);
        } else {
            // Non-medicine items go directly to inventory
            $stmt = $pdo->prepare("
                INSERT INTO inventory (
                    item_id, item_name, quantity, price, item_type, unit_type, pcs_per_box, received_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
                ON DUPLICATE KEY UPDATE 
                    quantity = quantity + VALUES(quantity),
                    price = VALUES(price),
                    unit_type = VALUES(unit_type),
                    pcs_per_box = VALUES(pcs_per_box)
            ");
            $stmt->execute([
                $item['item_id'],
                $item['item_name'],
                $final_quantity,
                $item['price'],
                $item['item_type'],
                $item['unit_type'],
                $item['pcs_per_box']
            ]);
        }
    }

    // ✅ Redirect to Batch & Expiry Tracking
    header("Location: batch&expiry.php");
    exit;
}
