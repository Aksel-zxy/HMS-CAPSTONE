<?php
session_start();
include '../../../SQL/config.php';
header('Content-Type: application/json');

if (!isset($_POST['submit_otc'])) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request']);
    exit;
}

// Make sure staff is logged in
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'You must be logged in to create invoice']);
    exit;
}

// Fetch staff info from users table
$stmt = $conn->prepare("SELECT fname, lname FROM users WHERE user_id = ?");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();
$staff_user = $result->fetch_assoc();
$stmt->close();

if (!$staff_user) {
    echo json_encode(['status' => 'error', 'message' => 'Logged-in staff not found']);
    exit;
}

$staff = $staff_user['fname'] . ' ' . $staff_user['lname'];

// Collect data
$customer = $_POST['customer_name'] ?? "Walk-in";
$generic = $_POST['generic_name'] ?? '';
$brand = $_POST['brand_name'] ?? '';
$dosage = $_POST['dosage'] ?? '';
$quantity = max(0, (int)$_POST['quantity']);
$unit_price = max(0, (float)$_POST['unit_price']);
$total_price = max(0, (float)$_POST['total_price']);
$payment = $_POST['payment_method'] ?? '';
$transaction_type = 'OTC';

// Validate required fields
if (!$generic || !$brand || !$dosage || $quantity <= 0 || $unit_price <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid form data']);
    exit;
}

// Get med_id and stock_quantity
$medQuery = $conn->prepare("SELECT med_id, stock_quantity FROM pharmacy_inventory WHERE generic_name=? AND brand_name=? AND dosage=? LIMIT 1");
$medQuery->bind_param("sss", $generic, $brand, $dosage);
$medQuery->execute();
$medResult = $medQuery->get_result()->fetch_assoc();
$medQuery->close();

if (!$medResult) {
    echo json_encode(['status' => 'error', 'message' => 'Medicine not found in inventory']);
    exit;
}

$med_id = $medResult['med_id'];
$stock_quantity = $medResult['stock_quantity'];

// Check stock
if ($quantity > $stock_quantity) {
    echo json_encode(['status' => 'error', 'message' => "Not enough stock. Available: $stock_quantity"]);
    exit;
}

// Check expiry in pharmacy_stock_batches
$expiryCheck = $conn->prepare("SELECT COUNT(*) FROM pharmacy_stock_batches WHERE med_id=? AND expiry_date >= CURDATE()");
$expiryCheck->bind_param("i", $med_id);
$expiryCheck->execute();
$expiryCheck->bind_result($valid_batches);
$expiryCheck->fetch();
$expiryCheck->close();

if ($valid_batches == 0) {
    echo json_encode(['status' => 'error', 'message' => 'All stock is expired']);
    exit;
}

// Set med_name as generic + brand
$med_name = $generic . " " . $brand;

// Insert sale
$insert = $conn->prepare("
    INSERT INTO pharmacy_sales 
    (customer_name, med_name, quantity_sold, price_per_unit, total_price, payment_method, staff_name, transaction_type) 
    VALUES (?,?,?,?,?,?,?,?)
");
$insert->bind_param("ssiddsss", $customer, $med_name, $quantity, $unit_price, $total_price, $payment, $staff, $transaction_type);
$insert->execute();
$insert->close();

// Deduct stock from pharmacy_inventory
$updateInventory = $conn->prepare("UPDATE pharmacy_inventory SET stock_quantity = stock_quantity - ? WHERE med_id=?");
$updateInventory->bind_param("ii", $quantity, $med_id);
$updateInventory->execute();
$updateInventory->close();

// Success
echo json_encode(['status' => 'success', 'message' => 'Invoice created successfully']);
exit;
