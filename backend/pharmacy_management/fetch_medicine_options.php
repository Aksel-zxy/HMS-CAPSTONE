<?php
include '../../SQL/config.php';

$type = $_GET['type'] ?? '';

if ($type === 'brands') {
    $generic = $_GET['generic'];

    $stmt = $conn->prepare("
        SELECT DISTINCT brand_name 
        FROM pharmacy_inventory 
        WHERE generic_name = ?
        ORDER BY brand_name
    ");
    $stmt->bind_param("s", $generic);
    $stmt->execute();

    $res = $stmt->get_result();
    $data = [];

    while ($row = $res->fetch_assoc()) {
        $data[] = $row['brand_name'];
    }

    echo json_encode($data);
    exit;
}

if ($type === 'dosage') {
    $generic = $_GET['generic'];
    $brand   = $_GET['brand'];

    $stmt = $conn->prepare("
        SELECT DISTINCT dosage 
        FROM pharmacy_inventory 
        WHERE generic_name = ? AND brand_name = ?
        ORDER BY dosage
    ");
    $stmt->bind_param("ss", $generic, $brand);
    $stmt->execute();

    $res = $stmt->get_result();
    $data = [];

    while ($row = $res->fetch_assoc()) {
        $data[] = $row['dosage'];
    }

    echo json_encode($data);
    exit;
}
