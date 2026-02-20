<?php
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
ob_start();
header('Content-Type: application/json');
session_start();

require '../../../SQL/config.php';
require_once '../classes/medicine.php';
date_default_timezone_set('Asia/Manila');

$id = intval($_POST['prescription_id'] ?? 0);
$status = $_POST['status'] ?? null;
$payment_type = $_POST['payment_type'] ?? null;

// ===============================
// STAFF SIDE: logged-in employee
// ===============================
$employee_id = $_SESSION['employee_id'] ?? 0;
$dispensed_role = 'pharmacist';

if (!$id) {
    echo json_encode(['error' => 'Prescription ID is required.']);
    exit;
}

if (!$employee_id) {
    echo json_encode(['error' => 'Unauthorized access.']);
    exit;
}

$medicineObj = new Medicine($conn);

try {
    $warnings = [];
    $total_dispensed = 0;
    $total_cost = 0;

    // ===============================
    // UPDATE PAYMENT TYPE ONLY
    // ===============================
    if ($payment_type !== null && !$status) {
        $stmt = $conn->prepare("UPDATE pharmacy_prescription SET payment_type=? WHERE prescription_id=?");
        $stmt->bind_param("si", $payment_type, $id);
        $stmt->execute();

        ob_clean();
        echo json_encode(['success' => 'Payment type updated successfully.']);
        exit;
    }

    // ===============================
    // PROCESS STATUS UPDATE
    // ===============================
    if ($status) {

        // -------------------------------
        // Handle Cancel immediately
        // -------------------------------
        if ($status === 'Cancelled') {
            $stmt = $conn->prepare("UPDATE pharmacy_prescription SET status='Cancelled' WHERE prescription_id=?");
            $stmt->bind_param("i", $id);
            $stmt->execute();

            ob_clean();
            echo json_encode(['success' => 'Prescription cancelled successfully.']);
            exit;
        }

        // Only continue if status is Dispensed
        if ($status === 'Dispensed') {
            // Fetch prescription items
            $items = $conn->query("
                SELECT i.item_id, i.med_id, i.quantity_prescribed,
                       inv.med_name, inv.unit_price
                FROM pharmacy_prescription_items i
                JOIN pharmacy_inventory inv ON i.med_id = inv.med_id
                WHERE i.prescription_id = {$id}
            ");

            $dispense_items = [];

            while ($item = $items->fetch_assoc()) {
                $med_id = (int)$item['med_id'];
                $quantity_needed = (int)$item['quantity_prescribed'];

                // Check NON-EXPIRED stock
                $batchRes = $conn->query("
                    SELECT SUM(stock_quantity) AS total_stock
                    FROM pharmacy_stock_batches
                    WHERE med_id={$med_id} AND expiry_date >= CURDATE()
                ");
                $total_stock = (int)($batchRes->fetch_assoc()['total_stock'] ?? 0);

                if ($total_stock <= 0) {
                    $warnings[] = "{$item['med_name']} - Cannot dispense (all batches expired)";
                } elseif ($total_stock < $quantity_needed) {
                    $warnings[] = "{$item['med_name']} - Cannot dispense (Available: {$total_stock}, Needed: {$quantity_needed})";
                } else {
                    $dispense_items[] = $item;
                }
            }

            // Dispense valid items
            foreach ($dispense_items as $item) {
                $item_id = (int)$item['item_id'];
                $med_id = (int)$item['med_id'];
                $quantity_dispensed = (int)$item['quantity_prescribed'];
                $unit_price = (float)$item['unit_price'];
                $total_price = $unit_price * $quantity_dispensed;
                $dispensed_date = date("Y-m-d H:i:s");

                $updateItem = $conn->prepare("
                    UPDATE pharmacy_prescription_items
                    SET quantity_dispensed=?, unit_price=?, total_price=?, dispensed_date=?, dispensed_by=?, dispensed_role=?
                    WHERE item_id=?
                ");
                $updateItem->bind_param(
                    "iddsisi",
                    $quantity_dispensed,
                    $unit_price,
                    $total_price,
                    $dispensed_date,
                    $employee_id,
                    $dispensed_role,
                    $item_id
                );
                $updateItem->execute();

                $medicineObj->dispenseMedicine($med_id, $quantity_dispensed);

                $total_dispensed += $quantity_dispensed;
                $total_cost += $total_price;
            }

            // Update billing status
            $ptypeRes = $conn->query("SELECT payment_type FROM pharmacy_prescription WHERE prescription_id={$id}");
            $ptype = strtolower($ptypeRes->fetch_assoc()['payment_type'] ?? '');
            $billing_status = ($ptype === 'cash') ? 'paid' : 'pending';

            $stmt = $conn->prepare("UPDATE pharmacy_prescription SET billing_status=? WHERE prescription_id=?");
            $stmt->bind_param("si", $billing_status, $id);
            $stmt->execute();

            // Update prescription status ONLY if at least one item was dispensed
            if ($total_dispensed > 0) {
                $stmt = $conn->prepare("UPDATE pharmacy_prescription SET status='Dispensed' WHERE prescription_id=?");
                $stmt->bind_param("i", $id);
                $stmt->execute();
                $current_status = 'Dispensed';
            } else {
                $current_status = 'Pending';
            }

            ob_clean();
            echo json_encode([
                'success' => "Prescription status: {$current_status}",
                'dispensed_quantity' => $total_dispensed,
                'total_cost' => $total_cost,
                'warnings' => $warnings
            ]);
            exit;
        }
    }

    echo json_encode(['error' => 'No action provided.']);
    exit;
} catch (Exception $e) {
    ob_clean();
    echo json_encode(['error' => 'Error: ' . $e->getMessage()]);
    exit;
}
