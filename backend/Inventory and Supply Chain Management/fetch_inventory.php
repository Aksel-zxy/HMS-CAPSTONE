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
                    <th>Price per Unit</th>
                    <th>Total Value</th>
                    <th>Unit Type</th>
                    <th>Pcs per Box</th>
                    <th>Location</th>
                    <th>Received Date</th>
                </tr>
            </thead>
            <tbody>';
    foreach ($inventory as $i) {
        $totalValue = $i['quantity'] * $i['price'];

        // Smarter quantity display
        if ($i['unit_type'] === 'Box' && !empty($i['pcs_per_box'])) {
            $qtyDisplay = "{$i['quantity']} Boxes (" . ($i['quantity'] * $i['pcs_per_box']) . " pcs)";
        } else {
            $qtyDisplay = "{$i['quantity']} pcs";
        }

        echo "<tr>
                <td>{$i['id']}</td>
                <td>" . htmlspecialchars($i['item_name']) . "</td>
                <td>" . htmlspecialchars($i['item_type']) . "</td>
                <td>" . htmlspecialchars($i['sub_type']) . "</td>
                <td>{$qtyDisplay}</td>
                <td>₱" . number_format($i['price'], 2) . "</td>
                <td>₱" . number_format($totalValue, 2) . "</td>
                <td>" . htmlspecialchars($i['unit_type'] ?? '-') . "</td>
                <td>" . (!empty($i['pcs_per_box']) ? (int)$i['pcs_per_box'] : '-') . "</td>
                <td>" . htmlspecialchars($i['location'] ?? 'Main Storage') . "</td>
                <td>{$i['received_at']}</td>
              </tr>";
    }
    echo "</tbody></table>";
}
