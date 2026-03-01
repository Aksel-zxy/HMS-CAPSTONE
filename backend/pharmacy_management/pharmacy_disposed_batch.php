<?php
include '../../SQL/config.php';

// Enable mysqli exceptions
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $batch_id = intval($_POST['batch_id']);
    $med_id = intval($_POST['med_id']);
    $med_name = $_POST['med_name'];
    $quantity = intval($_POST['quantity']);
    $price = floatval($_POST['price']);
    $expiration_date = $_POST['expiration_date'];

    // Start transaction
    $conn->begin_transaction();

    try {
        // 1. Insert into disposed_medicines
        $stmt = $conn->prepare("
            INSERT INTO disposed_medicines 
                (batch_id, item_id, item_name, quantity, price, expiration_date, disposed_at)
            VALUES (?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->bind_param("iisids", $batch_id, $med_id, $med_name, $quantity, $price, $expiration_date);
        $stmt->execute();

        // 2. Delete from pharmacy_stock_batches
        $stmt2 = $conn->prepare("DELETE FROM pharmacy_stock_batches WHERE batch_id = ?");
        $stmt2->bind_param("i", $batch_id);
        $stmt2->execute();

        // 3. Update main inventory stock (recalculate from remaining batches)
        $stmt3 = $conn->prepare("
    UPDATE pharmacy_inventory i
    SET i.stock_quantity = (
        SELECT IFNULL(SUM(b.stock_quantity), 0)
        FROM pharmacy_stock_batches b
        WHERE b.med_id = i.med_id
    )
    WHERE i.med_id = ?
");
        $stmt3->bind_param("i", $med_id);
        $stmt3->execute();

        // Commit
        $conn->commit();

        header("Location: pharmacy_expiry_tracking.php?msg=disposed_success");
        exit;
    } catch (Exception $e) {
        $conn->rollback();
        die("Error disposing batch: " . $e->getMessage());
    }
}
