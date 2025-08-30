<?php
require '../../SQL/config.php';

// Set JSON response header
header('Content-Type: application/json');

// Set timezone for accurate dispensed_date
date_default_timezone_set('Asia/Manila');

if (isset($_POST['prescription_id'], $_POST['status'])) {
    $id = intval($_POST['prescription_id']);
    $status = $_POST['status'];

    // Check if Dispensed
    if ($status === 'Dispensed') {
        $items = $conn->query("
            SELECT i.item_id, i.med_id, i.quantity_prescribed, inv.stock_quantity, inv.med_name, inv.unit_price
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
        $stmt = $conn->prepare("UPDATE pharmacy_prescription SET status = ? WHERE prescription_id = ?");
        $stmt->bind_param("si", $status, $id);
        $stmt->execute();

        // Reset pointer and loop again for updates
        $items->data_seek(0);
        while ($item = $items->fetch_assoc()) {
            $quantity_dispensed = $item['quantity_prescribed']; // dispense all
            $unit_price = $item['unit_price'];
            $total_price = $unit_price * $quantity_dispensed;
            $dispensed_date = date("Y-m-d H:i:s"); // Manila timezone

            // Update dispensed qty + pricing + dispensed_date
            $updateItem = $conn->prepare("
                UPDATE pharmacy_prescription_items 
                SET quantity_dispensed = ?, unit_price = ?, total_price = ?, dispensed_date = ?
                WHERE item_id = ?
            ");
            $updateItem->bind_param("iddsi", $quantity_dispensed, $unit_price, $total_price, $dispensed_date, $item['item_id']);
            $updateItem->execute();

            // Deduct stock from inventory
            $newStock = $item['stock_quantity'] - $quantity_dispensed;
            $updateStock = $conn->prepare("UPDATE pharmacy_inventory SET stock_quantity = ? WHERE med_id = ?");
            $updateStock->bind_param("ii", $newStock, $item['med_id']);
            $updateStock->execute();
        }

        // Get total dispensed + total price
        $res = $conn->query("
            SELECT SUM(quantity_dispensed) AS total_dispensed, SUM(total_price) AS total_cost 
            FROM pharmacy_prescription_items 
            WHERE prescription_id = {$id}
        ");
        $row = $res->fetch_assoc();

        echo json_encode([
            'success' => 'Prescription dispensed successfully.',
            'dispensed_quantity' => $row['total_dispensed'],
            'total_cost' => $row['total_cost']
        ]);
        exit;
    } else {
        // Only update status if not Dispensed
        $stmt = $conn->prepare("UPDATE pharmacy_prescription SET status = ? WHERE prescription_id = ?");
        $stmt->bind_param("si", $status, $id);
        $stmt->execute();

        // Get total dispensed for the prescription
        $res = $conn->query("SELECT SUM(quantity_dispensed) AS total_dispensed FROM pharmacy_prescription_items WHERE prescription_id = {$id}");
        $dispensed = $res->fetch_assoc()['total_dispensed'];

        echo json_encode([
            'success' => 'Prescription status updated successfully.',
            'dispensed_quantity' => $dispensed
        ]);
        exit;
    }
} else {
    echo json_encode(['error' => 'Invalid request.']);
    exit;
}
