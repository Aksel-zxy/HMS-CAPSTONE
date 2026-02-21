<?php
include '../../../SQL/config.php';

// Set JSON response
header('Content-Type: application/json');

$generic = $_GET['generic'] ?? null;
$brand = $_GET['brand'] ?? null;
$dosage = $_GET['dosage'] ?? null;

if ($generic && !$brand && !$dosage) {
    // Only generic provided → return brands
    $stmt = $conn->prepare("SELECT DISTINCT brand_name FROM pharmacy_inventory WHERE generic_name=?");
    $stmt->bind_param("s", $generic);
    $stmt->execute();
    $res = $stmt->get_result();
    $brands = [];
    while ($row = $res->fetch_assoc()) {
        $brands[] = $row;
    }
    echo json_encode($brands);
    exit;
}

if ($generic && $brand && !$dosage) {
    // Generic + Brand provided → return dosages
    $stmt = $conn->prepare("SELECT DISTINCT dosage FROM pharmacy_inventory WHERE generic_name=? AND brand_name=?");
    $stmt->bind_param("ss", $generic, $brand);
    $stmt->execute();
    $res = $stmt->get_result();
    $dosages = [];
    while ($row = $res->fetch_assoc()) {
        $dosages[] = $row;
    }
    echo json_encode($dosages);
    exit;
}

if ($generic && $brand && $dosage) {
    // Generic + Brand + Dosage → return unit price
    $stmt = $conn->prepare("SELECT unit_price FROM pharmacy_inventory WHERE generic_name=? AND brand_name=? AND dosage=? LIMIT 1");
    $stmt->bind_param("sss", $generic, $brand, $dosage);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res->fetch_assoc();
    echo json_encode(['unit_price' => $row['unit_price'] ?? 0]);
    exit;
}

// If no valid parameters
echo json_encode([]);
