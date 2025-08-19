<?php
require '../../SQL/config.php';

// Set JSON response header
header('Content-Type: application/json');

if (isset($_POST['prescription_id'], $_POST['status'])) {
    $id = intval($_POST['prescription_id']);
    $status = $_POST['status'];

    // Check if Dispensed
    if ($status === 'Dispensed') {
        $items = $conn->query("
            SELECT i.med_id, i.quantity_prescribed, inv.stock_quantity, inv.med_name
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

        // Update dispensed quantity
        $conn->query("
            UPDATE pharmacy_prescription_items 
            SET quantity_dispensed = quantity_prescribed 
            WHERE prescription_id = {$id}
        ");

        // Deduct stock
        $items->data_seek(0); // Reset pointer
        while ($item = $items->fetch_assoc()) {
            $conn->query("
                UPDATE pharmacy_inventory 
                SET stock_quantity = stock_quantity - {$item['quantity_prescribed']} 
                WHERE med_id = {$item['med_id']}
            ");
        }

        // Get total dispensed for the prescription
        $res = $conn->query("SELECT SUM(quantity_dispensed) AS total_dispensed FROM pharmacy_prescription_items WHERE prescription_id = {$id}");
        $dispensed = $res->fetch_assoc()['total_dispensed'];

        echo json_encode([
            'success' => 'Prescription dispensed successfully.',
            'dispensed_quantity' => $dispensed
        ]);
        exit;
    } else {
        // Only update status if not Dispensed
        $stmt = $conn->prepare("UPDATE pharmacy_prescription SET status = ? WHERE prescription_id = ?");
        $stmt->bind_param("si", $status, $id);
        $stmt->execute();

        // Get total dispensed for the prescription (so table stays in sync)
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
