<?php
include '../../SQL/config.php';
$today = date("Y-m-d");
$today_day = intval(date('d'));
$today_month = intval(date('m'));
$today_year = intval(date('Y'));

// Handle New Schedule
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'schedule') {
    $inventory_id = intval($_POST['inventory_id']);
    $maintenance_day = intval($_POST['maintenance_day']);
    $remarks = trim($_POST['remarks']);

    $checkStmt = $pdo->prepare("SELECT id FROM maintenance_records WHERE inventory_id = ? LIMIT 1");
    $checkStmt->execute([$inventory_id]);
    if ($inventory_id > 0 && $maintenance_day >= 1 && $maintenance_day <= 31 && !$checkStmt->fetch()) {
        $stmt = $pdo->prepare("
            INSERT INTO maintenance_records (inventory_id, maintenance_day, maintenance_type, remarks, created_at) 
            VALUES (?, ?, 'Preventive', ?, NOW())
        ");
        $stmt->execute([$inventory_id, $maintenance_day, $remarks]);
    }
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// Edit Schedule
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'edit_schedule') {
    $id = intval($_POST['schedule_id']);
    $maintenance_day = intval($_POST['maintenance_day']);
    $remarks = trim($_POST['remarks']);

    $stmt = $pdo->prepare("
        UPDATE maintenance_records 
        SET maintenance_day = ?, remarks = ? 
        WHERE id = ?
    ");
    $stmt->execute([$maintenance_day, $remarks, $id]);
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// Delete Schedule
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'delete_schedule') {
    $id = intval($_POST['schedule_id']);
    $pdo->prepare("DELETE FROM maintenance_records WHERE id = ?")->execute([$id]);
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// Update Repair Request Status
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'update_request') {
    $id = intval($_POST['request_id']);
    $status = $_POST['status'];
    $remarks = trim($_POST['remarks']);

    $stmt = $pdo->prepare("SELECT rr.*, i.item_id, i.item_name 
                           FROM repair_requests rr
                           JOIN inventory i ON rr.equipment = i.item_name
                           WHERE rr.id = ?");
    $stmt->execute([$id]);
    $req = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($req) {
        if ($status === "Completed") {
            $locStmt = $pdo->prepare("SELECT department FROM department_assets WHERE item_id = ? LIMIT 1");
            $locStmt->execute([$req['item_id']]);
            $dept = $locStmt->fetchColumn() ?: "Unknown";

            $ins = $pdo->prepare("
                INSERT INTO maintenance_history (source, equipment, maintenance_type, remarks, status, location, created_at, completed_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $ins->execute([
                "Repair Request",
                $req['equipment'],
                'Preventive',
                $remarks,
                $status,
                $dept,
                $req['created_at']
            ]);

            $pdo->prepare("DELETE FROM repair_requests WHERE id = ?")->execute([$id]);
        } else {
            $updateStmt = $pdo->prepare("UPDATE repair_requests SET status = ?, issue = ? WHERE id = ?");
            $updateStmt->execute([$status, $remarks, $id]);
        }
    }
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// Fetch Equipment
$stmt = $pdo->prepare("SELECT * FROM inventory WHERE item_type = 'Diagnostic Equipment'");
$stmt->execute();
$equipment = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch Schedules
$stmt = $pdo->prepare("
    SELECT mr.*, i.item_name, da.department AS location
    FROM maintenance_records mr
    JOIN inventory i ON mr.inventory_id = i.id
    LEFT JOIN department_assets da ON i.item_id = da.item_id
    ORDER BY mr.maintenance_day ASC
");
$stmt->execute();
$schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Auto-Create Monthly Preventive Requests
foreach ($schedules as $due) {
    if ($due['maintenance_day'] == $today_day) {
        $ticket_no = "MAINT-" . $due['id'] . "-" . $today_year . "-" . $today_month;
        $check = $pdo->prepare("SELECT id FROM repair_requests WHERE ticket_no = ? LIMIT 1");
        $check->execute([$ticket_no]);
        if (!$check->fetch()) {
            $ins = $pdo->prepare("INSERT INTO repair_requests (ticket_no, user_name, equipment, issue, priority, status, created_at) 
                                   VALUES (?, ?, ?, 'Preventive Maintenance', ?, 'Open', NOW())");
            $ins->execute([
                $ticket_no,
                "System",
                $due['item_name'],
                "Medium"
            ]);
        }
    }
}

// Fetch Repair Requests
$stmt = $pdo->prepare("
    SELECT rr.*, da.department AS location
    FROM repair_requests rr
    JOIN inventory i ON rr.equipment = i.item_name
    LEFT JOIN department_assets da ON i.item_id = da.item_id
    ORDER BY rr.created_at DESC
");
$stmt->execute();
$requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch Maintenance History
$stmt = $pdo->prepare("SELECT * FROM maintenance_history ORDER BY completed_at DESC");
$stmt->execute();
$history = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Preventive & Repair Maintenance</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/inventory_dashboard.css">
    <link rel="stylesheet" href="assets/css/maintenance.css">
</head>
<body class="bg-light p-4">

<div class="main-sidebar">
    <?php include 'inventory_sidebar.php'; ?>
</div>

<div class="container">
    <h2 class="mb-4">Preventive & Repair Maintenance</h2>

    <ul class="nav nav-tabs mb-3">
        <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#requests">Repair Requests</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#schedule">Schedule Maintenance</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#history">Maintenance History</button></li>
    </ul>

    <div class="tab-content border p-3 bg-white rounded-bottom">
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
                    <?php else: foreach ($requests as $req): ?>
                        <tr>
                            <td><?= htmlspecialchars($req['ticket_no']) ?></td>
                            <td><?= htmlspecialchars($req['equipment']) ?></td>
                            <td><?= htmlspecialchars($req['issue']) ?></td>
                            <td><?= htmlspecialchars($req['location'] ?: "Unknown") ?></td>
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
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>

        <div class="tab-pane fade" id="schedule">
            <ul class="nav nav-pills mb-3">
                <li class="nav-item"><button class="nav-link active" data-bs-toggle="pill" data-bs-target="#setSchedule">Set Schedule</button></li>
                <li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#listSchedule">Scheduled List</button></li>
            </ul>

            <div class="tab-content">
                <div class="tab-pane fade show active" id="setSchedule">
                    <form method="post" class="row g-3">
                        <input type="hidden" name="action" value="schedule">
                        <div class="col-md-4">
                            <label class="form-label">Select Equipment</label>
                            <select name="inventory_id" class="form-select" required>
                                <?php
                                $scheduled_ids = array_column($schedules, 'inventory_id');
                                foreach ($equipment as $eq):
                                    $disabled = in_array($eq['id'], $scheduled_ids) ? "disabled" : "";
                                ?>
                                    <option value="<?= $eq['id'] ?>" <?= $disabled ?>>
                                        <?= htmlspecialchars($eq['item_name']) ?>
                                        <?= $disabled ? "(Already Scheduled)" : "" ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label">Maintenance Day (1-31)</label>
                            <input type="number" name="maintenance_day" class="form-control" min="1" max="31" required>
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

                <div class="tab-pane fade" id="listSchedule">
                    <h5>Upcoming Schedules</h5>
                    <table class="table table-bordered">
                        <thead class="table-light">
                            <tr>
                                <th>Equipment</th>
                                <th>Day</th>
                                <th>Remarks</th>
                                <th>Location</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($schedules)): ?>
                                <tr><td colspan="5" class="text-muted text-center">No schedules found.</td></tr>
                            <?php else: foreach ($schedules as $s): ?>
                                <tr>
                                    <td><?= htmlspecialchars($s['item_name']) ?></td>
                                    <td><?= htmlspecialchars($s['maintenance_day']) ?></td>
                                    <td><?= htmlspecialchars($s['remarks']) ?></td>
                                    <td><?= htmlspecialchars($s['location'] ?: "Unknown") ?></td>
                                    <td>
                                        <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#editModal<?= $s['id'] ?>">Edit</button>
                                        <button type="button" class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#deleteModal<?= $s['id'] ?>">Delete</button>
                                    </td>
                                </tr>

                                <!-- Edit Modal -->
                                <div class="modal fade" id="editModal<?= $s['id'] ?>" tabindex="-1">
                                  <div class="modal-dialog">
                                    <form method="post">
                                        <input type="hidden" name="action" value="edit_schedule">
                                        <input type="hidden" name="schedule_id" value="<?= $s['id'] ?>">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title">Edit Schedule - <?= htmlspecialchars($s['item_name']) ?></h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <div class="modal-body">
                                                <div class="mb-3">
                                                    <label class="form-label">Maintenance Day</label>
                                                    <input type="number" name="maintenance_day" class="form-control" min="1" max="31" value="<?= $s['maintenance_day'] ?>" required>
                                                </div>
                                                <div class="mb-3">
                                                    <label class="form-label">Remarks</label>
                                                    <textarea name="remarks" class="form-control"><?= htmlspecialchars($s['remarks']) ?></textarea>
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                <button type="submit" class="btn btn-primary">Save Changes</button>
                                            </div>
                                        </div>
                                    </form>
                                  </div>
                                </div>

                                <!-- Delete Modal -->
                                <div class="modal fade" id="deleteModal<?= $s['id'] ?>" tabindex="-1">
                                  <div class="modal-dialog">
                                    <form method="post">
                                        <input type="hidden" name="action" value="delete_schedule">
                                        <input type="hidden" name="schedule_id" value="<?= $s['id'] ?>">
                                        <div class="modal-content">
                                            <div class="modal-header bg-danger text-white">
                                                <h5 class="modal-title">Delete Schedule</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <div class="modal-body">
                                                Are you sure you want to delete the schedule for <strong><?= htmlspecialchars($s['item_name']) ?></strong>?
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                <button type="submit" class="btn btn-danger">Delete</button>
                                            </div>
                                        </div>
                                    </form>
                                  </div>
                                </div>

                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="tab-pane fade" id="history">
            <h5>Past Maintenance</h5>
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
                    <?php else: foreach ($history as $h): ?>
                        <tr>
                            <td><?= htmlspecialchars($h['equipment']) ?></td>
                            <td><?= htmlspecialchars($h['maintenance_type']) ?></td>
                            <td><?= htmlspecialchars($h['status']) ?></td>
                            <td><?= htmlspecialchars($h['remarks']) ?></td>
                            <td><?= htmlspecialchars($h['completed_at']) ?></td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
