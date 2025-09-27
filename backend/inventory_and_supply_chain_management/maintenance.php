<?php
require 'db.php';
$today = date("Y-m-d");

// =============================
// Handle New Schedule
// =============================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'schedule') {
    $inventory_id = intval($_POST['inventory_id']);
    $maintenance_date = $_POST['maintenance_date'];
    $maintenance_type = $_POST['maintenance_type'];
    $remarks = trim($_POST['remarks']);

    if ($inventory_id > 0 && $maintenance_date >= $today) {
        $stmt = $pdo->prepare("
            INSERT INTO maintenance_records (inventory_id, maintenance_date, maintenance_type, remarks, created_at) 
            VALUES (?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$inventory_id, $maintenance_date, $maintenance_type, $remarks]);
    }
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// =============================
// Edit Schedule
// =============================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'edit_schedule') {
    $id = intval($_POST['schedule_id']);
    $maintenance_date = $_POST['maintenance_date'];
    $maintenance_type = $_POST['maintenance_type'];
    $remarks = trim($_POST['remarks']);

    $stmt = $pdo->prepare("
        UPDATE maintenance_records 
        SET maintenance_date = ?, maintenance_type = ?, remarks = ? 
        WHERE id = ?
    ");
    $stmt->execute([$maintenance_date, $maintenance_type, $remarks, $id]);

    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// =============================
// Delete Schedule
// =============================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'delete_schedule') {
    $id = intval($_POST['schedule_id']);
    $pdo->prepare("DELETE FROM maintenance_records WHERE id = ?")->execute([$id]);
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// =============================
// Update Repair Request Status
// =============================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'update_request') {
    $id = intval($_POST['request_id']);
    $status = $_POST['status'];
    $remarks = $_POST['remarks'];

    $stmt = $pdo->prepare("UPDATE repair_requests SET status = ?, issue = ? WHERE id = ?");
    $stmt->execute([$status, $remarks, $id]);

    if ($status === "Completed") {
        $stmt = $pdo->prepare("SELECT * FROM repair_requests WHERE id = ?");
        $stmt->execute([$id]);
        $req = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($req) {
            $ins = $pdo->prepare("
                INSERT INTO maintenance_history (source, equipment, maintenance_type, remarks, status, location, created_at, completed_at) 
                VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())
            ");
            $ins->execute([
                "Repair Request",
                $req['equipment'],
                "Repair Request",
                $remarks,
                $status,
                $req['location']
            ]);

            $pdo->prepare("DELETE FROM repair_requests WHERE id = ?")->execute([$id]);
        }
    }
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// =============================
// Fetch Equipment
// =============================
$stmt = $pdo->prepare("SELECT * FROM inventory WHERE item_type = 'Diagnostic Equipment'");
$stmt->execute();
$equipment = $stmt->fetchAll(PDO::FETCH_ASSOC);

// =============================
// Fetch Upcoming Schedules
// =============================
$stmt = $pdo->prepare("
    SELECT mr.*, i.item_name 
    FROM maintenance_records mr
    JOIN inventory i ON mr.inventory_id = i.id
    WHERE mr.maintenance_date >= ?
    ORDER BY mr.maintenance_date ASC
");
$stmt->execute([$today]);
$schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);

// =============================
// Auto-Create Repair Requests for Due Today
// =============================
$stmt = $pdo->prepare("
    SELECT mr.*, i.item_name, i.item_id
    FROM maintenance_records mr
    JOIN inventory i ON mr.inventory_id = i.id
    WHERE mr.maintenance_date = ?
");
$stmt->execute([$today]);
$dueToday = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($dueToday as $due) {
    $locStmt = $pdo->prepare("SELECT department FROM department_assets WHERE item_id = ? LIMIT 1");
    $locStmt->execute([$due['item_id']]);
    $dept = $locStmt->fetchColumn() ?: "Unknown";

    $check = $pdo->prepare("SELECT id FROM repair_requests WHERE ticket_no = ?");
    $check->execute(["MAINT-" . $due['id']]);
    if (!$check->fetch()) {
        $ins = $pdo->prepare("INSERT INTO repair_requests (ticket_no, user_name, equipment, issue, location, priority, status, created_at) 
                               VALUES (?, ?, ?, ?, ?, ?, 'Open', NOW())");
        $ins->execute([
            "MAINT-" . $due['id'],
            "System",
            $due['item_name'],
            "MAINTENANCE",
            $dept,
            "Medium"
        ]);
    }
}

// =============================
// Fetch Repair Requests
// =============================
$stmt = $pdo->prepare("SELECT * FROM repair_requests ORDER BY created_at DESC");
$stmt->execute();
$requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

// =============================
// Fetch Maintenance History
// =============================
$filter = $_GET['filter'] ?? 'all';
$query = "SELECT * FROM maintenance_history";
if ($filter === "Repair Request") {
    $query .= " WHERE maintenance_type = 'Repair Request'";
} elseif ($filter === "Preventive") {
    $query .= " WHERE maintenance_type = 'Preventive'";
}
$query .= " ORDER BY completed_at DESC";
$stmt = $pdo->prepare($query);
$stmt->execute();
$history = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
        <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#requests">Repair Requests</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#schedule">Schedule Maintenance</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#history">Maintenance History</button></li>
    </ul>

    <div class="tab-content border p-3 bg-white rounded-bottom">
        <!-- Repair Requests -->
        <div class="tab-pane fade show active" id="requests">
            <h5>Repair Requests</h5>
            <table class="table table-bordered">
                <thead class="table-light">
                    <tr>
                        <th>Ticket No</th>
                        <th>Equipment</th>
                        <th>Issue</th>
                        <th>Location</th>
                        <th>Priority</th>
                        <th>Status</th>
                        <th>Update</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($requests)): ?>
                        <tr><td colspan="7" class="text-center text-muted">No requests found.</td></tr>
                    <?php else: ?>
                        <?php foreach ($requests as $req): ?>
                            <tr>
                                <td><?= htmlspecialchars($req['ticket_no']) ?></td>
                                <td><?= htmlspecialchars($req['equipment']) ?></td>
                                <td><?= nl2br(htmlspecialchars($req['issue'])) ?></td>
                                <td><?= htmlspecialchars($req['location']) ?></td>
                                <td><?= htmlspecialchars($req['priority']) ?></td>
                                <td><?= htmlspecialchars($req['status']) ?></td>
                                <td>
                                    <form method="post" class="d-flex gap-1">
                                        <input type="hidden" name="action" value="update_request">
                                        <input type="hidden" name="request_id" value="<?= $req['id'] ?>">
                                        <select name="status" class="form-select form-select-sm">
                                            <option <?= $req['status']=="Open"?"selected":"" ?>>Open</option>
                                            <option <?= $req['status']=="In Progress"?"selected":"" ?>>In Progress</option>
                                            <option <?= $req['status']=="Completed"?"selected":"" ?>>Completed</option>
                                        </select>
                                        <input type="text" name="remarks" class="form-control form-control-sm" placeholder="Remarks">
                                        <button type="submit" class="btn btn-sm btn-primary">Update</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Schedule Maintenance -->
        <div class="tab-pane fade" id="schedule">
            <ul class="nav nav-pills mb-3">
                <li class="nav-item"><button class="nav-link active" data-bs-toggle="pill" data-bs-target="#setSchedule">Set Schedule</button></li>
                <li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#listSchedule">Scheduled List</button></li>
            </ul>

            <div class="tab-content">
                <!-- Set Schedule -->
                <div class="tab-pane fade show active" id="setSchedule">
                    <form method="post" class="row g-3">
                        <input type="hidden" name="action" value="schedule">
                        <div class="col-md-4">
                            <label class="form-label">Select Equipment</label>
                            <select name="inventory_id" class="form-select" required>
                                <?php foreach ($equipment as $eq): ?>
                                    <option value="<?= $eq['id'] ?>"><?= htmlspecialchars($eq['item_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Maintenance Date</label>
                            <input type="date" name="maintenance_date" class="form-control" required min="<?= $today ?>">
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
                            <textarea name="remarks" class="form-control"></textarea>
                        </div>
                        <div class="col-md-12">
                            <button type="submit" class="btn btn-success">Save Schedule</button>
                        </div>
                    </form>
                </div>

                <!-- Scheduled List -->
                <div class="tab-pane fade" id="listSchedule">
                    <h5>Upcoming Schedules</h5>
                    <table class="table table-bordered">
                        <thead class="table-light">
                            <tr>
                                <th>Equipment</th>
                                <th>Date</th>
                                <th>Type</th>
                                <th>Remarks</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($schedules)): ?>
                                <tr><td colspan="5" class="text-muted text-center">No schedules found.</td></tr>
                            <?php else: ?>
                                <?php foreach ($schedules as $s): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($s['item_name']) ?></td>
                                        <td><?= htmlspecialchars($s['maintenance_date']) ?></td>
                                        <td><?= htmlspecialchars($s['maintenance_type']) ?></td>
                                        <td><?= htmlspecialchars($s['remarks']) ?></td>
                                        <td>
                                            <!-- Edit Button -->
                                            <button class="btn btn-sm btn-primary" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#editModal<?= $s['id'] ?>">Edit</button>

                                            <!-- Delete Button -->
                                            <button class="btn btn-sm btn-danger" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#deleteModal<?= $s['id'] ?>">Delete</button>
                                        </td>
                                    </tr>

                                    <!-- Edit Modal -->
                                    <div class="modal fade" id="editModal<?= $s['id'] ?>" tabindex="-1">
                                        <div class="modal-dialog">
                                            <form method="post" class="modal-content">
                                                <input type="hidden" name="action" value="edit_schedule">
                                                <input type="hidden" name="schedule_id" value="<?= $s['id'] ?>">
                                                <div class="modal-header">
                                                    <h5 class="modal-title">Edit Schedule</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <div class="mb-3">
                                                        <label>Date</label>
                                                        <input type="date" name="maintenance_date" class="form-control" value="<?= $s['maintenance_date'] ?>" min="<?= $today ?>" required>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label>Type</label>
                                                        <select name="maintenance_type" class="form-select">
                                                            <option <?= $s['maintenance_type']=="Preventive"?"selected":"" ?>>Preventive</option>
                                                            <option <?= $s['maintenance_type']=="Repair"?"selected":"" ?>>Repair</option>
                                                        </select>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label>Remarks</label>
                                                        <textarea name="remarks" class="form-control"><?= htmlspecialchars($s['remarks']) ?></textarea>
                                                    </div>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="submit" class="btn btn-success">Save Changes</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>

                                    <!-- Delete Modal -->
                                    <div class="modal fade" id="deleteModal<?= $s['id'] ?>" tabindex="-1">
                                        <div class="modal-dialog">
                                            <form method="post" class="modal-content">
                                                <input type="hidden" name="action" value="delete_schedule">
                                                <input type="hidden" name="schedule_id" value="<?= $s['id'] ?>">
                                                <div class="modal-header">
                                                    <h5 class="modal-title">Confirm Deletion</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>
                                                <div class="modal-body">
                                                    Are you sure you want to delete this schedule for 
                                                    <b><?= htmlspecialchars($s['item_name']) ?></b>?
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="submit" class="btn btn-danger">Yes, Delete</button>
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>

                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Maintenance History -->
        <div class="tab-pane fade" id="history">
            <h5>Past Maintenance</h5>
            <form method="get" class="mb-3">
                <label class="form-label">Filter:</label>
                <select name="filter" class="form-select w-auto d-inline" onchange="this.form.submit()">
                    <option value="all" <?= $filter=="all"?"selected":"" ?>>All</option>
                    <option value="Repair Request" <?= $filter=="Repair Request"?"selected":"" ?>>Repair Request</option>
                    <option value="Preventive" <?= $filter=="Preventive"?"selected":"" ?>>Preventive</option>
                </select>
            </form>
            <table class="table table-bordered">
                <thead class="table-light">
                    <tr>
                        <th>Equipment</th>
                        <th>Type</th>
                        <th>Status</th>
                        <th>Remarks</th>
                        <th>Completed At</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($history)): ?>
                        <tr><td colspan="5" class="text-muted text-center">No history found.</td></tr>
                    <?php else: ?>
                        <?php foreach ($history as $h): ?>
                            <tr>
                                <td><?= htmlspecialchars($h['equipment']) ?></td>
                                <td><?= htmlspecialchars($h['maintenance_type']) ?></td>
                                <td><?= htmlspecialchars($h['status']) ?></td>
                                <td><?= htmlspecialchars($h['remarks']) ?></td>
                                <td><?= htmlspecialchars($h['completed_at']) ?></td>
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
