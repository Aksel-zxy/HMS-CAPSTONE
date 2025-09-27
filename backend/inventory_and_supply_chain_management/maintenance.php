<?php
require 'db.php';
$today = date("Y-m-d");

// =============================
// Schedule New Maintenance
// =============================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'schedule') {
    $inventory_id = intval($_POST['inventory_id']);
    $maintenance_date = $_POST['maintenance_date'];
    $maintenance_type = $_POST['maintenance_type'];
    $remarks = trim($_POST['remarks']);

    $stmt = $pdo->prepare("
        INSERT INTO maintenance_records (inventory_id, maintenance_date, maintenance_type, remarks, status, created_at) 
        VALUES (?, ?, ?, ?, 'Scheduled', NOW())
    ");
    $stmt->execute([$inventory_id, $maintenance_date, $maintenance_type, $remarks]);

    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// =============================
// Update Repair Request
// =============================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'update_request') {
    $id = intval($_POST['request_id']);
    $status = $_POST['status'];
    $remarks = $_POST['remarks'];

    // Update repair request
    $stmt = $pdo->prepare("UPDATE repair_requests SET status=?, issue=CONCAT(issue, '\n[Update: ', ?, ']') WHERE id=?");
    $stmt->execute([$status, $remarks, $id]);

    // If completed, move to history
    if ($status === 'Completed') {
        $req = $pdo->prepare("SELECT * FROM repair_requests WHERE id=?");
        $req->execute([$id]);
        $row = $req->fetch(PDO::FETCH_ASSOC);

        $ins = $pdo->prepare("INSERT INTO maintenance_history (equipment, type, remarks, completed_at) VALUES (?, 'Repair', ?, NOW())");
        $ins->execute([$row['equipment'], $row['issue']]);

        $del = $pdo->prepare("DELETE FROM repair_requests WHERE id=?");
        $del->execute([$id]);
    }

    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// =============================
// Auto-generate repair requests from today's maintenance
// =============================
$stmt = $pdo->prepare("
    SELECT mr.*, i.item_name, i.location 
    FROM maintenance_records mr
    JOIN inventory i ON mr.inventory_id = i.id
    WHERE mr.maintenance_date = ? AND mr.status='Scheduled'
");
$stmt->execute([$today]);
$dueToday = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($dueToday as $due) {
    $check = $pdo->prepare("SELECT id FROM repair_requests WHERE ticket_no=?");
    $check->execute(["MAINT-" . $due['id']]);
    if (!$check->fetch()) {
        $ins = $pdo->prepare("
            INSERT INTO repair_requests (ticket_no, user_name, equipment, issue, location, priority, status, created_at) 
            VALUES (?, 'System', ?, 'MAINTENANCE', ?, 'Medium', 'Open', NOW())
        ");
        $ins->execute(["MAINT-" . $due['id'], $due['item_name'], $due['location'] ?? "Unknown"]);
    }
}

// =============================
// Fetch Diagnostic Equipment
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
    WHERE mr.maintenance_date > ? AND mr.status='Scheduled'
    ORDER BY mr.maintenance_date ASC
");
$stmt->execute([$today]);
$schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);

// =============================
// Fetch Repair Requests
// =============================
$stmt = $pdo->prepare("SELECT * FROM repair_requests ORDER BY created_at DESC");
$stmt->execute();
$requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

// =============================
// Fetch Maintenance History
// =============================
$stmt = $pdo->prepare("SELECT * FROM maintenance_history ORDER BY completed_at DESC");
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
            <h5>Repair Requests (Including Today’s Scheduled Maintenance)</h5>
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
                <li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#listSchedule">Upcoming Schedules</button></li>
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
                            <textarea name="remarks" class="form-control"></textarea>
                        </div>
                        <div class="col-md-12">
                            <button type="submit" class="btn btn-success">Save Schedule</button>
                        </div>
                    </form>
                </div>

                <!-- Upcoming Schedules -->
                <div class="tab-pane fade" id="listSchedule">
                    <h5>Upcoming Schedules</h5>
                    <table class="table table-bordered">
                        <thead class="table-light">
                            <tr>
                                <th>Equipment</th>
                                <th>Date</th>
                                <th>Type</th>
                                <th>Remarks</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($schedules)): ?>
                                <tr><td colspan="4" class="text-muted text-center">No schedules found.</td></tr>
                            <?php else: ?>
                                <?php foreach ($schedules as $s): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($s['item_name']) ?></td>
                                        <td><?= htmlspecialchars($s['maintenance_date']) ?></td>
                                        <td><?= htmlspecialchars($s['maintenance_type']) ?></td>
                                        <td><?= htmlspecialchars($s['remarks']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Maintenance History -->
        <div class="tab-pane fade" id="history">
            <h5>Maintenance History</h5>
            <table class="table table-bordered">
                <thead class="table-light">
                    <tr>
                        <th>Equipment</th>
                        <th>Type</th>
                        <th>Remarks</th>
                        <th>Completed At</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($history)): ?>
                        <tr><td colspan="4" class="text-muted text-center">No history found.</td></tr>
                    <?php else: ?>
                        <?php foreach ($history as $h): ?>
                            <tr>
                                <td><?= htmlspecialchars($h['equipment']) ?></td>
                                <td><?= htmlspecialchars($h['type']) ?></td>
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
