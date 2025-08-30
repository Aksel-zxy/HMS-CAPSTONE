<?php
include '../../SQL/config.php';
require_once 'classes/Medicine.php';

session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $medicineObj = new Medicine($conn);

        $med_name = $_POST['med_name'];
        $category = $_POST['category'];
        $dosage = $_POST['dosage'];
        $stock_quantity = $_POST['stock_quantity'];
        $unit = $_POST['unit'];
        $status = $_POST['status'];
        $unit_price = $_POST['unit_price']; // ✅ new field

        $medicineObj->addMedicine(
            $med_name,
            $category,
            $dosage,
            $stock_quantity,
            $unit,
            $status,
            $unit_price
        );

        header("Location: pharmacy_med_inventory.php?success=1");
        exit;
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage();
    }
}
