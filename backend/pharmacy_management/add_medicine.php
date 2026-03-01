<?php
include '../../SQL/config.php';
require_once 'classes/medicine.php';

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
$generic_name = $_POST['generic_name'] ?? '';
$brand_name = $_POST['brand_name'] ?? '';
$prescription_required = $_POST['prescription_required'] ?? 'No';
$category = $_POST['category'] ?? '';
$dosage = $_POST['dosage'] ?? '';
$stock_quantity = intval($_POST['stock_quantity'] ?? 0);
$unit = $_POST['unit'] ?? '';
$unit_price = floatval($_POST['unit_price'] ?? 0);
$batch_no = $_POST['batch_no'] ?? null;

// Basic validation
if (!$med_name || !$generic_name || !$dosage || $stock_quantity <= 0 || !$unit) {
    die("Medicine name, generic name, dosage, unit, and stock quantity are required.");
}

try {
    // 1️⃣ Determine expiry date based on unit if not provided
    $years = $shelf_life[$unit] ?? 1; // default 1 year if unit not in list
    $expiry_date = date("Y-m-d", strtotime("+$years year"));

    // 2️⃣ Check if medicine already exists
    $stmt = $conn->prepare("
    SELECT med_id 
    FROM pharmacy_inventory 
    WHERE med_name = ?
    AND generic_name = ?
    AND brand_name = ?
    AND dosage = ?
    AND unit_price = ?
");
    $stmt->bind_param(
        "ssssd",
        $med_name,
        $generic_name,
        $brand_name,
        $dosage,
        $unit_price
    );

    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        // Medicine exists
        $row = $result->fetch_assoc();
        $med_id = $row['med_id'];

        // Add stock batch with expiry
        $medicineObj->addStock($med_id, $stock_quantity, $expiry_date, $batch_no);

        // Optionally update unit price, unit, brand, generic, prescription
        $stmt2 = $conn->prepare("
            UPDATE pharmacy_inventory 
            SET unit_price = ?, unit = ?, generic_name = ?, brand_name = ?, prescription_required = ? 
            WHERE med_id = ?
        ");
        $stmt2->bind_param("dssssi", $unit_price, $unit, $generic_name, $brand_name, $prescription_required, $med_id);
        $stmt2->execute();
    } else {
        // Insert new medicine with auto-location and initial batch
        $medicineObj->addMedicineWithAutoLocation(
            $med_name,
            $generic_name,
            $brand_name,
            $prescription_required,
            $category,
            $dosage,
            $unit,
            $unit_price,
            $stock_quantity,
            $expiry_date // ✅ Pass expiry date here
        );
    }

    header("Location: pharmacy_med_inventory.php?success=1");
    exit;
} catch (Exception $e) {
    die("Error: " . $e->getMessage());
}
