<?php
include '../../SQL/config.php';

$request_id = $_GET['id'];

$stmt = $pdo->prepare("
    SELECT p.*, d.items 
    FROM department_request_prices p
    JOIN department_request d ON p.request_id = d.id
    WHERE p.request_id=?
    ORDER BY p.id DESC
");
$stmt->execute([$request_id]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$items = json_decode($rows[0]['items'], true);

$result = [];

foreach ($rows as $row) {

    $index = $row['item_index'];
    $itemName = $items[$index]['name'] ?? 'Unknown';
    $qty = $items[$index]['approved_quantity'] ?? 0;

    $result[] = [
        'name' => $itemName,
        'quantity' => $qty,
        'price' => $row['price'],
        'total_price' => $row['total_price']
    ];
}

echo json_encode($result);
