<?php
require 'db.php';

// Handle Asset Transfer
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['transfer_action']) && $_POST['transfer_action'] === 'transfer') {
    $source_dept = trim($_POST['source_department']);
    $destination_dept = trim($_POST['destination_department']);
    $item_id = intval($_POST['item_id']);
    $transfer_qty = intval($_POST['transfer_qty']);

    if ($source_dept !== $destination_dept && $transfer_qty > 0) {
        $stmt = $pdo->prepare("SELECT * FROM department_assets WHERE item_id = ? AND department = ?");
        $stmt->execute([$item_id, $source_dept]);
        $source_item = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($source_item && $transfer_qty <= $source_item['quantity']) {
            $pdo->prepare("UPDATE department_assets SET quantity = quantity - ? WHERE id = ?")->execute([$transfer_qty, $source_item['id']]);

            $stmt = $pdo->prepare("SELECT * FROM department_assets WHERE item_id = ? AND department = ?");
            $stmt->execute([$item_id, $destination_dept]);
            $dest_item = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($dest_item) {
                $pdo->prepare("UPDATE department_assets SET quantity = quantity + ? WHERE id = ?")->execute([$transfer_qty, $dest_item['id']]);
            } else {
                $pdo->prepare("INSERT INTO department_assets (item_id, department, quantity) VALUES (?, ?, ?)")->execute([$item_id, $destination_dept, $transfer_qty]);
            }
        }
    }
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// Handle Asset Disposal
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['disposal_action']) && $_POST['disposal_action'] === 'dispose') {
    $item_type = trim($_POST['item_type']);
    $item_id = intval($_POST['item_id']);
    $dispose_qty = intval($_POST['dispose_qty']);
    $location = trim($_POST['location']); // 'Main Storage' or department

    if ($dispose_qty > 0) {
        if ($location === 'Main Storage') {
            $stmt = $pdo->prepare("SELECT quantity FROM inventory WHERE id = ?");
            $stmt->execute([$item_id]);
            $inv = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($inv && $dispose_qty <= $inv['quantity']) {
                $pdo->prepare("UPDATE inventory SET quantity = quantity - ? WHERE id = ?")->execute([$dispose_qty, $item_id]);
            }
        } else {
            $stmt = $pdo->prepare("SELECT * FROM department_assets WHERE item_id = ? AND department = ?");
            $stmt->execute([$item_id, $location]);
            $dept_item = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($dept_item && $dispose_qty <= $dept_item['quantity']) {
                $pdo->prepare("UPDATE department_assets SET quantity = quantity - ? WHERE id = ?")->execute([$dispose_qty, $dept_item['id']]);
            }
        }
    }
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// Fetch Departments
$stmt = $pdo->prepare("SELECT DISTINCT department FROM department_assets WHERE department IS NOT NULL AND department <> ''");
$stmt->execute();
$departments = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Fetch Department Assets
$stmt = $pdo->prepare("
    SELECT da.id, da.item_id, da.department, da.quantity, i.item_name, i.unit_type, i.price, i.item_type
    FROM department_assets da
    JOIN inventory i ON da.item_id = i.item_id
    ORDER BY da.department, i.item_name
");
$stmt->execute();
$dept_assets = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch Main Storage Inventory (all three types)
$stmt = $pdo->prepare("SELECT * FROM inventory WHERE item_type IN ('Diagnostic Equipment','Therapeutic Equipment','IT and Supporting Tech') AND quantity > 0");
$stmt->execute();
$main_inventory = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Asset Transfer & Disposal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light p-4">

<div class="main-sidebar">
    <?php include 'Inventory_dashboard.php'; ?>
</div>

<div class="container">
    <h2 class="mb-4">Asset Management: Transfer & Disposal</h2>

    <!-- Tabs -->
    <ul class="nav nav-tabs mb-3">
        <li class="nav-item">
            <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#transfer">Transfer Asset</button>
        </li>
        <li class="nav-item">
            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#disposal">Dispose Asset</button>
        </li>
        <li class="nav-item">
            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#current_assets">Current Assets</button>
        </li>
    </ul>

    <div class="tab-content border p-3 bg-white rounded-bottom">
        <!-- Transfer Tab -->
        <div class="tab-pane fade show active" id="transfer">
            <div class="card">
                <div class="card-header bg-primary text-white">Transfer Asset Between Departments</div>
                <div class="card-body">
                    <form method="post" class="row g-3">
                        <input type="hidden" name="transfer_action" value="transfer">
                        <div class="col-md-3">
                            <label class="form-label">Source Department</label>
                            <select name="source_department" id="source_department" class="form-select" required>
                                <option value="">Select Source</option>
                                <?php foreach ($departments as $dept): ?>
                                    <option value="<?= htmlspecialchars($dept) ?>"><?= htmlspecialchars($dept) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Item</label>
                            <select name="item_id" id="transfer_item" class="form-select" required>
                                <option value="">Select Item</option>
                                <?php foreach ($dept_assets as $asset): ?>
                                    <option data-dept="<?= htmlspecialchars($asset['department']) ?>" value="<?= $asset['item_id'] ?>">
                                        <?= htmlspecialchars($asset['item_name']) ?> (<?= $asset['quantity'] ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Destination Department</label>
                            <select name="destination_department" class="form-select" required>
                                <option value="">Select Destination</option>
                                <?php foreach ($departments as $dept): ?>
                                    <option value="<?= htmlspecialchars($dept) ?>"><?= htmlspecialchars($dept) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Quantity</label>
                            <input type="number" name="transfer_qty" class="form-control" min="1" required>
                        </div>
                        <div class="col-md-1 d-flex align-items-end">
                            <button type="submit" class="btn btn-success w-100">Transfer</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Disposal Tab -->
        <div class="tab-pane fade" id="disposal">
            <div class="card">
                <div class="card-header bg-danger text-white">Dispose Asset</div>
                <div class="card-body">
                    <form method="post" class="row g-3">
                        <input type="hidden" name="disposal_action" value="dispose">
                        <div class="col-md-3">
                            <label class="form-label">Asset Type</label>
                            <select name="item_type" class="form-select" required>
                                <option value="Diagnostic Equipment">Diagnostic Equipment</option>
                                <option value="Therapeutic Equipment">Therapeutic Equipment</option>
                                <option value="IT and Supporting Tech">IT and Supporting Tech</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Item</label>
                            <select name="item_id" class="form-select" required>
                                <?php foreach ($main_inventory as $inv): ?>
                                    <option value="<?= $inv['id'] ?>"><?= htmlspecialchars($inv['item_name']) ?> (<?= $inv['quantity'] ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Location</label>
                            <select name="location" class="form-select" required>
                                <option value="Main Storage">Main Storage</option>
                                <?php foreach ($departments as $dept): ?>
                                    <option value="<?= htmlspecialchars($dept) ?>"><?= htmlspecialchars($dept) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Quantity</label>
                            <input type="number" name="dispose_qty" class="form-control" min="1" required>
                        </div>
                        <div class="col-md-1 d-flex align-items-end">
                            <button type="submit" class="btn btn-danger w-100">Dispose</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Current Assets Tab -->
        <div class="tab-pane fade" id="current_assets">
            <h5>Department Assets</h5>
            <table class="table table-bordered">
                <thead class="table-light">
                    <tr>
                        <th>Department</th>
                        <th>Item Name</th>
                        <th>Quantity</th>
                        <th>Type</th>
                        <th>Unit</th>
                        <th>Price</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($dept_assets)): ?>
                        <tr><td colspan="6" class="text-center text-muted">No assets allocated.</td></tr>
                    <?php else: ?>
                        <?php foreach ($dept_assets as $da): ?>
                            <tr>
                                <td><?= htmlspecialchars($da['department']) ?></td>
                                <td><?= htmlspecialchars($da['item_name']) ?></td>
                                <td><?= $da['quantity'] ?></td>
                                <td><?= htmlspecialchars($da['item_type']) ?></td>
                                <td><?= htmlspecialchars($da['unit_type']) ?></td>
                                <td><?= number_format($da['price'],2) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Filter transfer items by selected source department
document.getElementById('source_department').addEventListener('change', function() {
    let source = this.value;
    let options = document.querySelectorAll('#transfer_item option');
    options.forEach(option => {
        option.style.display = option.dataset.dept === source ? 'block' : 'none';
    });
});
</script>
</body>
</html>
