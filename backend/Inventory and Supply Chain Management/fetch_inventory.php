<?php
require 'db.php';

$category = $_GET['category'] ?? '';
$search = $_GET['search'] ?? '';

$query = "SELECT * FROM inventory WHERE 1=1";
$params = [];

if (!empty($category)) {
    $query .= " AND item_type = ?";
    $params[] = $category;
}

if (!empty($search)) {
    $query .= " AND (item_name LIKE ? OR sub_type LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$query .= " ORDER BY received_at DESC";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$inventory = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($inventory)) {
    echo '<div class="alert alert-warning">No items found.</div>';
} else {
    echo '<table class="table table-bordered bg-white table-hover">
            <thead class="table-dark">
                <tr>
                    <th>ID</th>
                    <th>Item Name</th>
                    <th>Type</th>
                    <th>Sub Type</th>
                    <th>Quantity</th>
                    <th>Price per Item</th>
                    <th>Total Value</th>
                    <th>Received Date</th>
                </tr>
            </thead>
            <tbody>';
    foreach ($inventory as $i) {
        echo "<tr>
                <td>{$i['id']}</td>
                <td>".htmlspecialchars($i['item_name'])."</td>
                <td>".htmlspecialchars($i['item_type'])."</td>
                <td>".htmlspecialchars($i['sub_type'])."</td>
                <td>{$i['quantity']}</td>
                <td>₱".number_format($i['price'], 2)."</td>
                <td>₱".number_format($i['quantity'] * $i['price'], 2)."</td>
                <td>{$i['received_at']}</td>
              </tr>";
    }
    echo "</tbody></table>";
}
