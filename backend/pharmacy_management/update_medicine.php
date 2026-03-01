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
        $generic = $_POST['generic_name'];
        $brand = $_POST['brand_name'];
        $category = $_POST['category'];
        $dosage = $_POST['dosage'];
        $stock = $_POST['stock_quantity']; // read-only but still submitted
        $unit = $_POST['unit'];
        $unit_price = $_POST['unit_price'];
        $prescription = $_POST['prescription_required'];

        $stmt = $conn->prepare("UPDATE pharmacy_inventory 
            SET med_name=?, 
                generic_name=?, 
                brand_name=?, 
                category=?, 
                dosage=?, 
                stock_quantity=?, 
                unit_price=?, 
                unit=?, 
                prescription_required=? 
            WHERE med_id=?");

        $stmt->bind_param(
            "sssssidssi",
            $name,           // s
            $generic,        // s
            $brand,          // s
            $category,       // s
            $dosage,         // s
            $stock,          // i
            $unit_price,     // d
            $unit,           // s
            $prescription,   // s
            $id              // i
        );

        if ($stmt->execute()) {
            header("Location: pharmacy_med_inventory.php?msg=updated");
            exit();
        } else {
            echo "Update failed: " . $stmt->error;
        }
    }
}
