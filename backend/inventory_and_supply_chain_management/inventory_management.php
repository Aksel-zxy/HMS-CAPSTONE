<?php
session_start();
include '../../SQL/config.php';

// INVENTORY filters
$invSearch = $_GET['inv_search'] ?? '';
$invCategory = $_GET['inv_category'] ?? '';

// HISTORY filters
$histSearch = $_GET['hist_search'] ?? '';
$histCategory = $_GET['hist_category'] ?? '';

// Predefined categories
$categories = [
    "IT and supporting tech",
    "Medications and pharmacy supplies",
    "Consumables and disposables",
    "Therapeutic equipment",
    "Diagnostic Equipment"
];

// Inventory query
$invQuery = "SELECT * FROM inventory WHERE 1";
$invParams = [];
if (!empty($invSearch)) {
    $invQuery .= " AND (item_name LIKE :search OR item_type LIKE :search OR sub_type LIKE :search)";
    $invParams[':search'] = "%$invSearch%";
}
if (!empty($invCategory)) {
    $invQuery .= " AND category = :category";
    $invParams[':category'] = $invCategory;
}
$invQuery .= " ORDER BY received_at DESC";
$inventoryStmt = $pdo->prepare($invQuery);
$inventoryStmt->execute($invParams);
$inventory = $inventoryStmt->fetchAll(PDO::FETCH_ASSOC);

// Stock adjustments query
$histQuery = "
    SELECT sa.id, i.item_name, i.category, sa.old_quantity, sa.new_quantity, sa.reason, sa.adjusted_at
    FROM stock_adjustments sa
    JOIN inventory i ON sa.inventory_id = i.id
    WHERE 1
";
$histParams = [];
if (!empty($histSearch)) {
    $histQuery .= " AND (i.item_name LIKE :search OR i.category LIKE :search)";
    $histParams[':search'] = "%$histSearch%";
}
if (!empty($histCategory)) {
    $histQuery .= " AND i.category = :category";
    $histParams[':category'] = $histCategory;
}
$histQuery .= " ORDER BY sa.adjusted_at DESC";
$adjStmt = $pdo->prepare($histQuery);
$adjStmt->execute($histParams);
$adjustments = $adjStmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Inventory Management</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="assets/css/inventory_management.css" rel="stylesheet">
<link rel="stylesheet" href="assets/css/inventory_dashboard.css">
</head>
<body class="bg-light">
    

<div class="main-sidebar">
    <?php include 'inventory_sidebar.php'; ?>
</div>

<div class="container py-5">
    <h2 class="mb-4 fw-bold"> Inventory & Stock Tracking</h2>

    <!-- Tabs -->
    <ul class="nav nav-tabs" id="inventoryTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="inventory-tab" data-bs-toggle="tab" data-bs-target="#inventory" type="button" role="tab">Inventory</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="history-tab" data-bs-toggle="tab" data-bs-target="#history" type="button" role="tab">Stock Adjustment History</button>
        </li>
    </ul>

    <div class="tab-content mt-3">
        <!-- Inventory Tab -->
        <div class="tab-pane fade show active" id="inventory" role="tabpanel">

            <!-- Filters for Inventory -->
            <form method="get" class="row g-2 mb-4">
                <div class="col-md-4">
                    <input type="text" name="inv_search" class="form-control" placeholder="Search item..." value="<?= htmlspecialchars($invSearch) ?>">
                </div>
                <div class="col-md-4">
                    <select name="inv_category" class="form-select">
                        <option value="">All Categories</option>
                        <?php foreach($categories as $c): ?>
                            <option value="<?= htmlspecialchars($c) ?>" <?= $invCategory==$c ? 'selected' : '' ?>>
                                <?= htmlspecialchars($c) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <button class="btn btn-primary w-100">Filter</button>
                </div>
                <div class="col-md-2">
                    <a href="inventory_management.php" class="btn btn-secondary w-100">Reset</a>
                </div>
            </form>

            <!-- Inventory Table -->
            <table class="table table-bordered bg-white">
                <thead class="table-dark">
                    <tr>
                        <th>ID</th>
                        <th>Item Name</th>
                        <th>Type</th>
                        <th>Sub-Type</th>
                        <th>Total Quantity (pcs)</th>
                        <th>Price</th>
                        <th>Received At</th>
                        <th>Location</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($inventory as $item): ?>
                        <tr>
                            <td><?= $item['id'] ?></td>
                            <td><?= htmlspecialchars($item['item_name']) ?></td>
                            <td><?= htmlspecialchars($item['item_type']) ?></td>
                            <td><?= htmlspecialchars($item['sub_type']) ?></td>
                            <td><span class="badge bg-primary"><?= (int)$item['total_qty'] ?></span></td>
                            <td>₱<?= number_format($item['price'],2) ?></td>
                            <td><?= htmlspecialchars($item['received_at']) ?></td>
                            <td><?= htmlspecialchars($item['location']) ?></td>
                            <td>
                                <button class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#adjustModal<?= $item['id'] ?>">Adjust</button>
                            </td>
                        </tr>

                        <!-- Adjust Modal -->
                        <div class="modal fade" id="adjustModal<?= $item['id'] ?>" tabindex="-1">
                            <div class="modal-dialog">
                                <div class="modal-content">
                                    <form method="post" action="process_adjustment.php">
                                        <div class="modal-header">
                                            <h5 class="modal-title">Adjust Stock: <?= htmlspecialchars($item['item_name']) ?></h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <div class="modal-body">
                                            <input type="hidden" name="inventory_id" value="<?= $item['id'] ?>">
                                            <input type="hidden" name="old_quantity" value="<?= $item['total_qty'] ?>">

                                            <div class="mb-3">
                                                <label class="form-label">Current Quantity (pcs)</label>
                                                <input type="text" class="form-control" value="<?= $item['total_qty'] ?>" disabled>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">New Quantity (pcs)</label>
                                                <input type="number" name="new_quantity" class="form-control" required>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Reason</label>
                                                <textarea name="reason" class="form-control" required></textarea>
                                            </div>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="submit" class="btn btn-primary">Save Adjustment</button>
                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- History Tab -->
        <div class="tab-pane fade" id="history" role="tabpanel">

            <!-- Filters for History -->
            <form method="get" class="row g-2 mb-4">
                <div class="col-md-4">
                    <input type="text" name="hist_search" class="form-control" placeholder="Search item..." value="<?= htmlspecialchars($histSearch) ?>">
                </div>
                <div class="col-md-4">
                    <select name="hist_category" class="form-select">
                        <option value="">All Categories</option>
                        <?php foreach($categories as $c): ?>
                            <option value="<?= htmlspecialchars($c) ?>" <?= $histCategory==$c ? 'selected' : '' ?>>
                                <?= htmlspecialchars($c) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <button class="btn btn-primary w-100">Filter</button>
                </div>
                <div class="col-md-2">
                    <a href="inventory_management.php#history" class="btn btn-secondary w-100">Reset</a>
                </div>
            </form>

            <!-- History Table -->
            <table class="table table-bordered bg-white">
                <thead class="table-dark">
                    <tr>
                        <th>ID</th>
                        <th>Item Name</th>
                        <th>Category</th>
                        <th>Old Qty (pcs)</th>
                        <th>New Qty (pcs)</th>
                        <th>Reason</th>
                        <th>Adjusted At</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($adjustments as $adj): ?>
                        <tr>
                            <td><?= $adj['id'] ?></td>
                            <td><?= htmlspecialchars($adj['item_name']) ?></td>
                            <td><?= htmlspecialchars($adj['category']) ?></td>
                            <td><span class="badge bg-secondary"><?= (int)$adj['old_quantity'] ?></span></td>
                            <td><span class="badge bg-success"><?= (int)$adj['new_quantity'] ?></span></td>
                            <td><?= htmlspecialchars($adj['reason']) ?></td>
                            <td><?= htmlspecialchars($adj['adjusted_at']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="main-chatbox">
    <?php include 'chatbox.php'; ?>
</div>


<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
