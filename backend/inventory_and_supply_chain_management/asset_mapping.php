<?php
include '../../SQL/config.php';

// Handle Item Assignment to Department
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['inventory_id'], $_POST['department'], $_POST['assign_qty'])) {
    $inventory_id = intval($_POST['inventory_id']);
    $department = trim($_POST['department']);
    $assign_qty = intval($_POST['assign_qty']);

    // Fetch the inventory item
    $stmt = $pdo->prepare("SELECT * FROM inventory WHERE id = ?");
    $stmt->execute([$inventory_id]);
    $item = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($item && $assign_qty > 0 && $assign_qty <= $item['quantity']) {

        // Deduct quantity from main inventory
        $stmt = $pdo->prepare("UPDATE inventory SET quantity = quantity - ? WHERE id = ?");
        $stmt->execute([$assign_qty, $inventory_id]);

        // Check if department already has this item
        $stmt = $pdo->prepare("SELECT * FROM department_assets WHERE item_id = ? AND department = ?");
        $stmt->execute([$item['item_id'], $department]);
        $dept_item = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($dept_item) {
            // Add quantity to existing department asset
            $stmt = $pdo->prepare("UPDATE department_assets SET quantity = quantity + ? WHERE id = ?");
            $stmt->execute([$assign_qty, $dept_item['id']]);
        } else {
            // Insert new department asset
            $stmt = $pdo->prepare("INSERT INTO department_assets (item_id, department, quantity, assigned_at) VALUES (?, ?, ?, NOW())");
            $stmt->execute([$item['item_id'], $department, $assign_qty]);
        }
    }

    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// Fetch Main Inventory (available items)
$stmt = $pdo->prepare("SELECT * FROM inventory WHERE location = 'Main Storage' AND quantity > 0");
$stmt->execute();
$main_inventory = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch Departments
$stmt = $pdo->prepare("SELECT DISTINCT department FROM users WHERE department IS NOT NULL AND department <> ''");
$stmt->execute();
$departments = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Fetch Department Assets, combine same item per department and exclude zero quantities
$stmt = $pdo->prepare("
    SELECT 
        da.department, 
        i.item_name, 
        SUM(da.quantity) AS total_quantity, 
        i.unit_type, 
        i.price
    FROM department_assets da
    JOIN inventory i ON da.item_id = i.item_id
    GROUP BY da.department, i.item_name, i.unit_type, i.price
    HAVING total_quantity > 0
    ORDER BY da.department, i.item_name
");
$stmt->execute();
$dept_assets = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Department Asset Mapping</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/inventory_dashboard.css">
</head>
<body class="bg-light p-4">

<div class="main-sidebar">
    <?php include 'inventory_sidebar.php'; ?>
</div>

<div class="container">
    <h2 class="mb-4">Department Asset Mapping & Inventory</h2>

    <!-- Tabs -->
    <ul class="nav nav-tabs mb-3">
        <li class="nav-item">
            <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#mapping">Department Asset Mapping</button>
        </li>
        <li class="nav-item">
            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#inventory">Inventory by Location</button>
        </li>
    </ul>

    <div class="tab-content border p-3 bg-white rounded-bottom">

        <!-- Department Mapping -->
        <div class="tab-pane fade show active" id="mapping">
            <div class="card">
                <div class="card-header bg-primary text-white">Assign Items to Departments</div>
                <div class="card-body">
                    <form method="post" class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Select Item</label>
                            <select name="inventory_id" class="form-select" required>
                                <?php foreach ($main_inventory as $inv): ?>
                                    <option value="<?= $inv['id'] ?>">
                                        <?= htmlspecialchars($inv['item_name']) ?> (<?= $inv['quantity'] ?> available)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Select Department</label>
                            <select name="department" class="form-select" required>
                                <?php foreach ($departments as $dept): ?>
                                    <option value="<?= htmlspecialchars($dept) ?>"><?= htmlspecialchars($dept) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Quantity</label>
                            <input type="number" name="assign_qty" class="form-control" min="1" required>
                        </div>
                        <div class="col-md-2 d-flex align-items-end">
                            <button type="submit" class="btn btn-success w-100">Assign</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Inventory by Department -->
        <div class="tab-pane fade" id="inventory">
            <h5>Department Assets</h5>
            <table class="table table-bordered">
                <thead class="table-light">
                    <tr>
                        <th>Department</th>
                        <th>Item Name</th>
                        <th>Total Quantity</th>
                        <th>Unit Type</th>
                        <th>Price</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($dept_assets)): ?>
                        <tr><td colspan="5" class="text-muted text-center">No allocations yet.</td></tr>
                    <?php else: ?>
                        <?php foreach ($dept_assets as $da): ?>
                            <tr>
                                <td><?= htmlspecialchars($da['department']) ?></td>
                                <td><?= htmlspecialchars($da['item_name']) ?></td>
                                <td><?= $da['total_quantity'] ?></td>
                                <td><?= htmlspecialchars($da['unit_type']) ?></td>
                                <td>₱<?= number_format($da['price'],2) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
