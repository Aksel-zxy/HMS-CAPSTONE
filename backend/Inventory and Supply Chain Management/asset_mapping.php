<?php
require 'db.php';

// ==========================
// Handle assignment of items to departments
// ==========================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['inventory_id'], $_POST['department'], $_POST['assign_qty'])) {
    $inventory_id = intval($_POST['inventory_id']);
    $department = trim($_POST['department']);
    $assign_qty = intval($_POST['assign_qty']);

    // Fetch inventory item
    $stmt = $pdo->prepare("SELECT * FROM inventory WHERE id = ?");
    $stmt->execute([$inventory_id]);
    $item = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($item && $assign_qty > 0 && $assign_qty <= $item['quantity']) {
        // Deduct from main storage
        $stmt = $pdo->prepare("UPDATE inventory SET quantity = quantity - ? WHERE id = ?");
        $stmt->execute([$assign_qty, $inventory_id]);

        // Check if department already has this item
        $stmt = $pdo->prepare("SELECT * FROM inventory WHERE item_id = ? AND location = ?");
        $stmt->execute([$item['item_id'], $department]);
        $dept_item = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($dept_item) {
            // Update existing record for department
            $stmt = $pdo->prepare("UPDATE inventory 
                                   SET quantity = quantity + ?,
                                       total_qty = total_qty + ?
                                   WHERE id = ?");
            $stmt->execute([$assign_qty, $assign_qty, $dept_item['id']]);
        } else {
            // Insert new record for department
            $stmt = $pdo->prepare("INSERT INTO inventory
                (item_id, item_name, item_type, category, sub_type, quantity, total_qty, price, unit_type, pcs_per_box, received_at, location, min_stock, max_stock)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?, 0, 9999)
            ");
            $stmt->execute([
                $item['item_id'],
                $item['item_name'],
                $item['item_type'],
                $item['category'],
                $item['sub_type'],
                $assign_qty,
                $assign_qty,
                $item['price'],
                $item['unit_type'],
                $item['pcs_per_box'],
                $department
            ]);
        }
    }

    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}


$stmt = $pdo->prepare("SELECT * FROM inventory ORDER BY location, item_name");
$stmt->execute();
$inventory = $stmt->fetchAll(PDO::FETCH_ASSOC);


$stmt = $pdo->prepare("SELECT DISTINCT department FROM users WHERE department IS NOT NULL AND department <> ''");
$stmt->execute();
$departments = $stmt->fetchAll(PDO::FETCH_COLUMN);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Department Asset Mapping</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light p-4">


<div class="main-sidebar">
    <?php include 'Inventory_dashboard.php'; ?>
</div>


<div class="container">
    <h2 class="mb-4">Department Asset Mapping</h2>

    <div class="card mb-4">
        <div class="card-header bg-primary text-white">Assign Items to Departments</div>
        <div class="card-body">
            <form method="post" class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Select Item</label>
                    <select name="inventory_id" class="form-select" required>
                        <?php foreach ($inventory as $inv): ?>
                            <?php if ($inv['location'] === 'Main Storage' && $inv['quantity'] > 0): ?>
                                <option value="<?= $inv['id'] ?>">
                                    <?= htmlspecialchars($inv['item_name']) ?> (<?= $inv['quantity'] ?> available)
                                </option>
                            <?php endif; ?>
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

    <h4>Inventory by Location</h4>
    <table class="table table-bordered">
        <thead class="table-light">
            <tr>
                <th>Location / Department</th>
                <th>Item Name</th>
                <th>Quantity</th>
                <th>Unit Type</th>
                <th>Price</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($inventory as $inv): ?>
                <tr>
                    <td><?= htmlspecialchars($inv['location']) ?></td>
                    <td><?= htmlspecialchars($inv['item_name']) ?></td>
                    <td><?= $inv['quantity'] ?></td>
                    <td><?= $inv['unit_type'] ?></td>
                    <td><?= number_format($inv['price'],2) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
