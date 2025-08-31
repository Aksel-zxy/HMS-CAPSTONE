<?php
require '../../SQL/config.php';
header('Content-Type: application/json');
date_default_timezone_set('Asia/Manila');

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// Disable raw PHP warnings in JSON output
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);

$id = intval($_POST['prescription_id'] ?? 0);
$status = $_POST['status'] ?? null;
$payment_type = $_POST['payment_type'] ?? null;

if (!$id) {
    echo json_encode(['error' => 'Prescription ID is required.']);
    exit;
}

try {
    // --- Update Payment Type Only ---
    if ($payment_type !== null && !$status) {
        $stmt = $conn->prepare("UPDATE pharmacy_prescription SET payment_type=? WHERE prescription_id=?");
        $stmt->bind_param("si", $payment_type, $id);
        $stmt->execute();

        echo json_encode(['success' => 'Payment type updated successfully.']);
        exit;
    }

    // --- Handle Status Update ---
    if ($status) {
        $items = $conn->query("
            SELECT i.item_id, i.med_id, i.quantity_prescribed, i.quantity_dispensed, inv.stock_quantity, inv.med_name, inv.unit_price
            FROM pharmacy_prescription_items i
            JOIN pharmacy_inventory inv ON i.med_id = inv.med_id
            WHERE i.prescription_id = {$id}
        ");

        $warnings = [];
        while ($item = $items->fetch_assoc()) {
            if ($item['stock_quantity'] == 0) {
                $warnings[] = "{$item['med_name']} - Stock is not available";
            } elseif ($item['stock_quantity'] < $item['quantity_prescribed']) {
                $warnings[] = "{$item['med_name']} - Stock is insufficient (Available: {$item['stock_quantity']}, Needed: {$item['quantity_prescribed']})";
            }
        }

        if (!empty($warnings)) {
            echo json_encode(['error' => implode("\n", $warnings)]);
            exit;
        }

        // Update prescription status
        $stmt = $conn->prepare("UPDATE pharmacy_prescription SET status=? WHERE prescription_id=?");
        $stmt->bind_param("si", $status, $id);
        $stmt->execute();

        // Dispense items and deduct stock
        $items->data_seek(0);
        while ($item = $items->fetch_assoc()) {
            $item_id = (int)$item['item_id'];
            $med_id = (int)$item['med_id'];
            $quantity_dispensed = (int)$item['quantity_prescribed'];
            $unit_price = (float)$item['unit_price'];
            $total_price = $unit_price * $quantity_dispensed;
            $dispensed_date = date("Y-m-d H:i:s");

            $updateItem = $conn->prepare("
                UPDATE pharmacy_prescription_items
                SET quantity_dispensed=?, unit_price=?, total_price=?, dispensed_date=?
                WHERE item_id=?
            ");
            $updateItem->bind_param("iddsi", $quantity_dispensed, $unit_price, $total_price, $dispensed_date, $item_id);
            $updateItem->execute();

            // Deduct stock
            $newStock = (int)$item['stock_quantity'] - $quantity_dispensed;
            $updateStock = $conn->prepare("UPDATE pharmacy_inventory SET stock_quantity=? WHERE med_id=?");
            $updateStock->bind_param("ii", $newStock, $med_id);
            $updateStock->execute();
        }

        // Update billing_status
        $ptypeRes = $conn->query("SELECT payment_type FROM pharmacy_prescription WHERE prescription_id={$id}");
        $ptype = strtolower($ptypeRes->fetch_assoc()['payment_type'] ?? '');
        $billing_status = ($ptype === 'cash') ? 'paid' : 'pending';
        $conn->query("UPDATE pharmacy_prescription SET billing_status='{$billing_status}' WHERE prescription_id={$id}");

        // Return totals
        $res = $conn->query("SELECT SUM(quantity_dispensed) AS total_dispensed, SUM(total_price) AS total_cost 
                             FROM pharmacy_prescription_items WHERE prescription_id={$id}");
        $row = $res->fetch_assoc();

        echo json_encode([
            'success' => 'Prescription dispensed successfully.',
            'dispensed_quantity' => $row['total_dispensed'],
            'total_cost' => $row['total_cost']
        ]);
        exit;
    }

    // If only payment_type was updated
    if ($payment_type !== null && !$status) {
        echo json_encode(['success' => 'Payment type updated successfully.']);
        exit;
    }

    echo json_encode(['error' => 'No action provided.']);
} catch (Exception $e) {
    echo json_encode(['error' => 'Error: ' . $e->getMessage()]);
}
