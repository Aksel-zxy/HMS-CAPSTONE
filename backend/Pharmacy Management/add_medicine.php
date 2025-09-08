<?php
include '../../SQL/config.php';
require_once 'classes/Medicine.php';

$medicineObj = new Medicine($conn);

// Shelf life in years by unit/formulation
$shelf_life = [
    "Tablets & Capsules" => 3,
    "Syrups / Oral Liquids" => 2,
    "Antibiotic Dry Syrup (Powder)" => 2,
    "Injectables (Ampoules / Vials)" => 3,
    "Eye Drops / Ear Drops" => 2,
    "Insulin" => 2,
    "Topical Creams / Ointments" => 3,
    "Vaccines" => 2,
    "IV Fluids" => 2
];

// Get POST data safely
$med_name = $_POST['med_name'] ?? '';
$category = $_POST['category'] ?? '';
$dosage = $_POST['dosage'] ?? '';
$stock_quantity = intval($_POST['stock_quantity'] ?? 0);
$unit = $_POST['unit'] ?? '';
$unit_price = floatval($_POST['unit_price'] ?? 0);
$batch_no = $_POST['batch_no'] ?? null;

// Basic validation
if (!$med_name || !$dosage || $stock_quantity <= 0 || !$unit) {
    die("Medicine name, dosage, unit, and stock quantity are required.");
}

try {
    // 1️⃣ Determine expiry date based on unit if not provided
    $years = $shelf_life[$unit] ?? 1; // default 1 year if unit not in list
    $expiry_date = date("Y-m-d", strtotime("+$years year"));

    // 2️⃣ Check if medicine already exists
    $stmt = $conn->prepare("SELECT med_id FROM pharmacy_inventory WHERE med_name = ? AND dosage = ?");
    $stmt->bind_param("ss", $med_name, $dosage);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        // Medicine exists
        $row = $result->fetch_assoc();
        $med_id = $row['med_id'];

        // Add stock batch with expiry
        $medicineObj->addStock($med_id, $stock_quantity, $expiry_date, $batch_no);

        // Optionally update unit price & unit
        $stmt2 = $conn->prepare("UPDATE pharmacy_inventory SET unit_price = ?, unit = ? WHERE med_id = ?");
        $stmt2->bind_param("dsi", $unit_price, $unit, $med_id);
        $stmt2->execute();
    } else {
        // Insert new medicine
        $stmt = $conn->prepare("
            INSERT INTO pharmacy_inventory 
            (med_name, category, dosage, stock_quantity, unit_price, unit, status)
            VALUES (?, ?, ?, 0, ?, ?, 'Available')
        ");
        $stmt->bind_param("sssds", $med_name, $category, $dosage, $unit_price, $unit);
        $stmt->execute();

        $med_id = $conn->insert_id;

        // Add initial stock batch with expiry
        $medicineObj->addStock($med_id, $stock_quantity, $expiry_date, $batch_no);
    }

    header("Location: pharmacy_med_inventory.php?success=1");
    exit;
} catch (Exception $e) {
    die("Error: " . $e->getMessage());
}
