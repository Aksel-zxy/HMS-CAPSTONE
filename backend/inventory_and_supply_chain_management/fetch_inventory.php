<?php
include '../../SQL/config.php';

$category = $_GET['category'] ?? '';
$search   = $_GET['search'] ?? '';

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

$query .= " ORDER BY item_name ASC";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<table class="table table-bordered table-hover bg-white shadow-sm">
    <thead class="table-dark">
        <tr>
            <th>Item Name</th>
            <th>Category</th>
            <th>Unit</th>
            <th>Qty (Boxes/Pcs)</th>
            <th>Pcs per Box</th>
            <th><strong>Total Qty (pcs)</strong></th>
            <th>Price</th>
            <th>Last Updated</th>
        </tr>
    </thead>
    <tbody>
        <?php if ($items): ?>
            <?php foreach ($items as $item): 
                // ✅ Calculate total qty
                if ($item['unit_type'] === "Box" && !empty($item['pcs_per_box'])) {
                    $total_qty = $item['quantity'] * $item['pcs_per_box'];
                } else {
                    $total_qty = $item['quantity'];
                }
            ?>
                <tr>
                    <td><?= htmlspecialchars($item['item_name']) ?></td>
                    <td><?= htmlspecialchars($item['item_type']) ?></td>
                    <td><?= htmlspecialchars($item['unit_type']) ?></td>
                    <td><?= (int)$item['quantity'] ?></td>
                    <td><?= $item['pcs_per_box'] ? (int)$item['pcs_per_box'] : '-' ?></td>
                    <td><strong><?= $total_qty ?></strong></td>
                    <td>₱<?= number_format($item['price'], 2) ?></td>
                    <td><?= $item['updated_at'] ?? $item['received_at'] ?></td>
                </tr>
            <?php endforeach; ?>
        <?php else: ?>
            <tr>
                <td colspan="8" class="text-center">No items found.</td>
            </tr>
        <?php endif; ?>
    </tbody>
</table>
