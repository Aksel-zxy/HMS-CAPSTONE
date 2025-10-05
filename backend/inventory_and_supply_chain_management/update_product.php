<?php
session_start();
require 'db.php';

// Ensure vendor is logged in
if (!isset($_SESSION['vendor_id'])) {
    header("Location: vlogin.php");
    exit;
}

$vendor_id = $_SESSION['vendor_id'];

// Collect form inputs
$id = $_POST['id'];
$item_name = $_POST['item_name'];
$item_description = $_POST['item_description'];
$item_type = $_POST['item_type'];
$sub_type = $_POST['sub_type'] ?? null;
$price = $_POST['price'];

// ✅ Handle unit type and pcs per box
$unit_type = $_POST['unit_type'] ?? 'Piece';
$pcs_per_box = ($unit_type === "Box" && !empty($_POST['pcs_per_box'])) 
    ? (int)$_POST['pcs_per_box'] 
    : null;

// Fetch existing product (to retain or delete old picture)
$stmt = $pdo->prepare("SELECT picture FROM vendor_products WHERE id=? AND vendor_id=?");
$stmt->execute([$id, $vendor_id]);
$product = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$product) {
    die("❌ Unauthorized action.");
}

$picture = $product['picture']; // Keep old picture by default

// ✅ Handle new image upload
if (!empty($_FILES['picture']['name'])) {
    $targetDir = "uploads/";
    if (!is_dir($targetDir)) {
        mkdir($targetDir, 0777, true);
    }

    $fileName = time() . "_" . basename($_FILES['picture']['name']);
    $targetFile = $targetDir . $fileName;

    if (move_uploaded_file($_FILES['picture']['tmp_name'], $targetFile)) {
        // Delete old picture if it exists
        if (!empty($product['picture']) && file_exists($product['picture'])) {
            unlink($product['picture']);
        }
        $picture = $targetFile;
    }
}

// ✅ Update product query
$stmt = $pdo->prepare("UPDATE vendor_products 
    SET item_name=?, item_description=?, item_type=?, sub_type=?, price=?, unit_type=?, pcs_per_box=?, picture=? 
    WHERE id=? AND vendor_id=?");
$stmt->execute([
    $item_name,
    $item_description,
    $item_type,
    $sub_type,
    $price,
    $unit_type,
    $pcs_per_box,
    $picture,
    $id,
    $vendor_id
]);

// Redirect back to Products page
header("Location: vendor_products.php");
exit;
?>
