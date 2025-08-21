<?php
require '../../SQL/config.php';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (isset($_POST['delete'])) {
        // DELETE REQUEST
        $id = $_POST['med_id'];

        $stmt = $conn->prepare("DELETE FROM pharmacy_inventory WHERE med_id = ?");
        $stmt->bind_param("i", $id);

        if ($stmt->execute()) {
            header("Location: pharmacy_med_inventory.php?msg=deleted");
            exit();
        } else {
            echo "Delete failed: " . $stmt->error;
        }
    } else {
        // UPDATE REQUEST
        $id = $_POST['med_id'];
        $name = $_POST['med_name'];
        $category = $_POST['category'];
        $dosage = $_POST['dosage'];
        $stock = $_POST['stock_quantity'];
        $unit = $_POST['unit'];

        $stmt = $conn->prepare("UPDATE pharmacy_inventory SET med_name=?, category=?, dosage=?, stock_quantity=?, unit=? WHERE med_id=?");
        $stmt->bind_param("sssisi", $name, $category, $dosage, $stock, $unit, $id);

        if ($stmt->execute()) {
            header("Location: pharmacy_med_inventory.php?msg=updated");
            exit();
        } else {
            echo "Update failed: " . $stmt->error;
        }
    }
}
