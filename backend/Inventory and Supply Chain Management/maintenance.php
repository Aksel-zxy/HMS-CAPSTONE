<?php
require 'db.php';


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['inventory_id'], $_POST['maintenance_date'], $_POST['maintenance_type'])) {
    $inventory_id = intval($_POST['inventory_id']);
    $maintenance_date = $_POST['maintenance_date'];
    $maintenance_type = $_POST['maintenance_type'];
    $remarks = trim($_POST['remarks']);

    $stmt = $pdo->prepare("
        INSERT INTO maintenance_records (inventory_id, maintenance_date, maintenance_type, remarks, created_at) 
        VALUES (?, ?, ?, ?, NOW())
    ");
    $stmt->execute([$inventory_id, $maintenance_date, $maintenance_type, $remarks]);

    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}


$stmt = $pdo->prepare("SELECT * FROM inventory WHERE item_type = 'Diagnostic Equipment'");
$stmt->execute();
$equipment = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare("
    SELECT mr.id, mr.inventory_id, mr.maintenance_date, mr.maintenance_type, mr.remarks, i.item_name
    FROM maintenance_records mr
    JOIN inventory i ON mr.inventory_id = i.id
    ORDER BY mr.maintenance_date DESC
");
$stmt->execute();
$records = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Preventive & Repair Maintenance</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light p-4">

<div class="main-sidebar">
    <?php include 'Inventory_dashboard.php'; ?>
</div>

<div class="container">
    <h2 class="mb-4">Preventive & Repair Maintenance</h2>

    <!-- Tabs -->
    <ul class="nav nav-tabs mb-3">
        <li class="nav-item">
            <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#schedule">Schedule Maintenance</button>
        </li>
        <li class="nav-item">
            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#history">Maintenance History</button>
        </li>
    </ul>

    <div class="tab-content border p-3 bg-white rounded-bottom">
        <!-- Schedule Maintenance -->
        <div class="tab-pane fade show active" id="schedule">
            <div class="card">
                <div class="card-header bg-primary text-white">Set Maintenance</div>
                <div class="card-body">
                    <form method="post" class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Select Equipment</label>
                            <select name="inventory_id" class="form-select" required>
                                <?php foreach ($equipment as $eq): ?>
                                    <option value="<?= $eq['id'] ?>"><?= htmlspecialchars($eq['item_name']) ?> (<?= $eq['quantity'] ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Maintenance Date</label>
                            <input type="date" name="maintenance_date" class="form-control" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Type</label>
                            <select name="maintenance_type" class="form-select" required>
                                <option value="Preventive">Preventive</option>
                                <option value="Repair">Repair</option>
                            </select>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label">Remarks</label>
                            <textarea name="remarks" class="form-control" rows="2"></textarea>
                        </div>
                        <div class="col-md-12">
                            <button type="submit" class="btn btn-success">Save Maintenance</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Maintenance History -->
        <div class="tab-pane fade" id="history">
            <h5>Maintenance Records</h5>
            <table class="table table-bordered">
                <thead class="table-light">
                    <tr>
                        <th>Equipment</th>
                        <th>Maintenance Date</th>
                        <th>Type</th>
                        <th>Remarks</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($records)): ?>
                        <tr><td colspan="4" class="text-muted text-center">No records yet.</td></tr>
                    <?php else: ?>
                        <?php foreach ($records as $r): ?>
                            <tr>
                                <td><?= htmlspecialchars($r['item_name']) ?></td>
                                <td><?= htmlspecialchars($r['maintenance_date']) ?></td>
                                <td><?= htmlspecialchars($r['maintenance_type']) ?></td>
                                <td><?= htmlspecialchars($r['remarks']) ?></td>
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
