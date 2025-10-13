<?php
include '../../SQL/config.php';

$categories = [
    "IT and supporting tech",
    "Medications and pharmacy supplies",
    "Consumables and disposables",
    "Therapeutic equipment",
    "Diagnostic Equipment"
];

$search = $_GET['search'] ?? '';
$selected_category = $_GET['category'] ?? '';
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = 8;
$offset = ($page - 1) * $limit;

// Build query
$where = "WHERE 1";
$params = [];

if ($selected_category) {
    $where .= " AND item_type = ?";
    $params[] = $selected_category;
}
if ($search) {
    $where .= " AND item_name LIKE ?";
    $params[] = "%$search%";
}

$stmt = $pdo->prepare("SELECT * FROM vendor_products $where ORDER BY item_name ASC LIMIT $limit OFFSET $offset");
$stmt->execute($params);
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

if ($products) {
    foreach ($products as $p) { ?>
        <div class="col-md-3 mb-4">
            <div class="card h-100">
                <?php if ($p['picture']): ?>
                    <img src="<?= htmlspecialchars($p['picture']) ?>" class="card-img-top" style="height:150px;object-fit:cover;">
                <?php endif; ?>
                <div class="card-body text-center">
                    <h6 class="card-title"><?= htmlspecialchars($p['item_name']) ?></h6>
                    <p class="small"><?= htmlspecialchars($p['item_description']) ?></p>
                    <p><strong>₱<?= number_format($p['price'], 2) ?></strong></p>
                    <form method="post">
                        <input type="hidden" name="id" value="<?= $p['id'] ?>">
                        <input type="hidden" name="name" value="<?= htmlspecialchars($p['item_name']) ?>">
                        <input type="hidden" name="price" value="<?= $p['price'] ?>">
                        <button type="submit" name="add_to_cart" class="btn btn-success btn-sm w-100">Add to Cart</button>
                    </form>
                </div>
            </div>
        </div>
    <?php }
} else {
    echo "<p class='text-muted'>No products found.</p>";
}
