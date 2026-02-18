<?php
include '../../SQL/config.php';
$today       = date("Y-m-d");
$today_day   = intval(date('d'));
$today_month = intval(date('m'));
$today_year  = intval(date('Y'));

/* ============================================================ */
/* AUTO-ADD MISSING COLUMNS (safe — silently ignored if exists) */
/* ============================================================ */
try { $pdo->exec("ALTER TABLE repair_requests ADD COLUMN remarks TEXT NULL"); } catch (PDOException $e) {}
try { $pdo->exec("ALTER TABLE repair_requests ADD COLUMN ticket_no VARCHAR(100) NULL"); } catch (PDOException $e) {}

/* ============================= */
/* UPDATE REPAIR REQUEST STATUS  */
/* ============================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'update_request') {
    $request_id = intval($_POST['request_id']);
    $new_status = trim($_POST['status']);
    $remarks    = trim($_POST['remarks'] ?? '');

    $stmt = $pdo->prepare("SELECT * FROM repair_requests WHERE id = ?");
    $stmt->execute([$request_id]);
    $request = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($request) {
        $current_status = $request['status'];

        $allowed = [
            'Open'        => ['In Progress'],
            'In Progress' => ['Completed'],
        ];

        $can_transition = isset($allowed[$current_status]) && in_array($new_status, $allowed[$current_status]);

        if ($new_status === $current_status) {
            // Same status: just update remarks if given
            if ($remarks !== '') {
                $pdo->prepare("UPDATE repair_requests SET remarks = ? WHERE id = ?")
                    ->execute([$remarks, $request_id]);
            }
        } elseif ($can_transition) {
            if ($new_status === 'Completed') {
                // Archive to maintenance_history
                $ins = $pdo->prepare("
                    INSERT INTO maintenance_history
                        (equipment, maintenance_type, status, remarks, completed_at)
                    VALUES (?, ?, 'Completed', ?, NOW())
                ");
                $ins->execute([
                    $request['equipment'],
                    ($request['issue'] === 'Preventive Maintenance' ? 'Preventive' : 'Repair'),
                    $remarks !== '' ? $remarks : ($request['remarks'] ?? '')
                ]);
                $pdo->prepare("DELETE FROM repair_requests WHERE id = ?")->execute([$request_id]);
            } else {
                // Advance to In Progress
                $pdo->prepare("UPDATE repair_requests SET status = ?, remarks = ? WHERE id = ?")
                    ->execute([
                        $new_status,
                        $remarks !== '' ? $remarks : ($request['remarks'] ?? ''),
                        $request_id
                    ]);
            }
        }
    }

    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

/* ============================= */
/* HANDLE SCHEDULE CRUD          */
/* ============================= */

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'schedule') {
    $inventory_id    = intval($_POST['inventory_id']);
    $maintenance_day = intval($_POST['maintenance_day']);
    $remarks         = trim($_POST['remarks']);

    $checkStmt = $pdo->prepare("SELECT id FROM maintenance_records WHERE inventory_id = ? LIMIT 1");
    $checkStmt->execute([$inventory_id]);

    if ($inventory_id > 0 && $maintenance_day >= 1 && $maintenance_day <= 31 && !$checkStmt->fetch()) {
        $pdo->prepare("
            INSERT INTO maintenance_records (inventory_id, maintenance_day, maintenance_type, remarks, created_at)
            VALUES (?, ?, 'Preventive', ?, NOW())
        ")->execute([$inventory_id, $maintenance_day, $remarks]);
    }
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'edit_schedule') {
    $id              = intval($_POST['schedule_id']);
    $maintenance_day = intval($_POST['maintenance_day']);
    $remarks         = trim($_POST['remarks']);
    $pdo->prepare("UPDATE maintenance_records SET maintenance_day = ?, remarks = ? WHERE id = ?")
        ->execute([$maintenance_day, $remarks, $id]);
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'delete_schedule') {
    $pdo->prepare("DELETE FROM maintenance_records WHERE id = ?")->execute([intval($_POST['schedule_id'])]);
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

/* ============================= */
/* FETCH DATA                    */
/* ============================= */

$stmt = $pdo->prepare("
    SELECT MIN(id) AS id, item_name
    FROM inventory
    WHERE item_type = 'Diagnostic Equipment'
    GROUP BY item_name
    ORDER BY item_name ASC
");
$stmt->execute();
$equipment = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare("
    SELECT mr.id, mr.inventory_id, mr.maintenance_day, mr.remarks, i.item_name,
           GROUP_CONCAT(DISTINCT da.department SEPARATOR ', ') AS location
    FROM maintenance_records mr
    JOIN inventory i ON mr.inventory_id = i.id
    LEFT JOIN department_assets da ON i.item_id = da.item_id
    GROUP BY mr.id, mr.inventory_id, mr.maintenance_day, mr.remarks, i.item_name
    ORDER BY mr.maintenance_day ASC
");
$stmt->execute();
$schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Auto-create preventive maintenance tickets on scheduled day
foreach ($schedules as $due) {
    if ($due['maintenance_day'] == $today_day) {
        $ticket_no = "MAINT-" . $due['id'] . "-" . $today_year . "-" . $today_month;
        $check = $pdo->prepare("SELECT id FROM repair_requests WHERE ticket_no = ? LIMIT 1");
        $check->execute([$ticket_no]);
        if (!$check->fetch()) {
            $pdo->prepare("
                INSERT INTO repair_requests (ticket_no, user_name, equipment, issue, priority, status, created_at)
                VALUES (?, 'System', ?, 'Preventive Maintenance', 'Medium', 'Open', NOW())
            ")->execute([$ticket_no, $due['item_name']]);
        }
    }
}

$stmt = $pdo->prepare("
    SELECT rr.*,
           GROUP_CONCAT(DISTINCT da.department SEPARATOR ', ') AS location
    FROM repair_requests rr
    LEFT JOIN inventory i ON rr.equipment = i.item_name
    LEFT JOIN department_assets da ON i.item_id = da.item_id
    GROUP BY rr.id
    ORDER BY rr.created_at DESC
");
$stmt->execute();
$requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare("SELECT * FROM maintenance_history ORDER BY completed_at DESC");
$stmt->execute();
$history = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Count stats
$total_requests  = count($requests);
$open_count      = count(array_filter($requests, fn($r) => $r['status'] === 'Open'));
$progress_count  = count(array_filter($requests, fn($r) => $r['status'] === 'In Progress'));
$history_count   = count($history);

function getStatusOptions(string $current): array {
    return match($current) {
        'Open'        => ['Open', 'In Progress'],
        'In Progress' => ['In Progress', 'Completed'],
        'Completed'   => ['Completed'],
        default       => [$current],
    };
}

function priorityBadge(string $p): string {
    return match(strtolower($p)) {
        'high'   => 'badge-priority-high',
        'medium' => 'badge-priority-medium',
        'low'    => 'badge-priority-low',
        default  => 'badge-priority-medium',
    };
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Maintenance Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/inventory_dashboard.css">
    <link rel="stylesheet" href="assets/css/maintenance.css">
    <style>
        :root {
            --primary:   #2563eb;
            --success:   #16a34a;
            --warning:   #d97706;
            --danger:    #dc2626;
            --gray-50:   #f8fafc;
            --gray-100:  #f1f5f9;
            --gray-200:  #e2e8f0;
            --gray-600:  #475569;
            --gray-800:  #1e293b;
            --radius:    10px;
            --shadow:    0 1px 4px rgba(0,0,0,.08);
            --shadow-md: 0 4px 16px rgba(0,0,0,.10);
        }

        body { background: var(--gray-100); font-family: 'Segoe UI', sans-serif; }

        /* ── STAT CARDS ── */
        .stat-card {
            background: #fff;
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            padding: 1.25rem 1.5rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            border-left: 4px solid transparent;
            transition: box-shadow .2s;
        }
        .stat-card:hover { box-shadow: var(--shadow-md); }
        .stat-card.blue   { border-color: var(--primary); }
        .stat-card.red    { border-color: var(--danger); }
        .stat-card.amber  { border-color: var(--warning); }
        .stat-card.green  { border-color: var(--success); }
        .stat-icon {
            width: 48px; height: 48px;
            border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.4rem; flex-shrink: 0;
        }
        .stat-card.blue  .stat-icon { background: #dbeafe; color: var(--primary); }
        .stat-card.red   .stat-icon { background: #fee2e2; color: var(--danger); }
        .stat-card.amber .stat-icon { background: #fef3c7; color: var(--warning); }
        .stat-card.green .stat-icon { background: #dcfce7; color: var(--success); }
        .stat-value { font-size: 1.6rem; font-weight: 700; color: var(--gray-800); line-height: 1; }
        .stat-label { font-size: .8rem; color: var(--gray-600); margin-top: 2px; }

        /* ── PAGE CARD ── */
        .page-card {
            background: #fff;
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            overflow: hidden;
        }
        .page-card-header {
            padding: .85rem 1.25rem;
            border-bottom: 1px solid var(--gray-200);
            background: var(--gray-50);
            display: flex; align-items: center; justify-content: space-between;
        }
        .page-card-header h6 { margin: 0; font-weight: 600; color: var(--gray-800); font-size: .95rem; }
        .page-card-body { padding: 1.25rem; }

        /* ── CUSTOM NAV TABS ── */
        .custom-tabs { border-bottom: 2px solid var(--gray-200); margin-bottom: 0; }
        .custom-tabs .nav-link {
            color: var(--gray-600);
            border: none; border-bottom: 2px solid transparent;
            margin-bottom: -2px;
            padding: .65rem 1.2rem;
            font-size: .88rem;
            font-weight: 500;
            display: flex; align-items: center; gap: .4rem;
            transition: color .15s;
        }
        .custom-tabs .nav-link:hover { color: var(--primary); }
        .custom-tabs .nav-link.active {
            color: var(--primary);
            border-bottom-color: var(--primary);
            background: transparent;
        }

        /* ── PILLS ── */
        .custom-pills .nav-link {
            color: var(--gray-600);
            font-size: .82rem;
            font-weight: 500;
            border-radius: 20px;
            padding: .3rem .9rem;
        }
        .custom-pills .nav-link.active {
            background: var(--primary);
            color: #fff;
        }

        /* ── STATUS BADGES ── */
        .status-badge {
            display: inline-flex; align-items: center; gap: .3rem;
            font-size: .75rem; font-weight: 600;
            padding: .28rem .7rem; border-radius: 20px; white-space: nowrap;
        }
        .status-badge.open        { background: #fee2e2; color: #b91c1c; }
        .status-badge.in-progress { background: #fef3c7; color: #92400e; }
        .status-badge.completed   { background: #dcfce7; color: #15803d; }

        /* ── PRIORITY BADGES ── */
        .badge-priority-high   { background: #fee2e2; color: #b91c1c; }
        .badge-priority-medium { background: #fef3c7; color: #92400e; }
        .badge-priority-low    { background: #dcfce7; color: #15803d; }
        .priority-badge {
            display: inline-block;
            font-size: .72rem; font-weight: 600;
            padding: .22rem .6rem; border-radius: 20px;
        }

        /* ── TABLE ── */
        .pro-table { width: 100%; border-collapse: separate; border-spacing: 0; font-size: .86rem; }
        .pro-table thead th {
            background: var(--gray-50);
            color: var(--gray-600);
            font-size: .75rem; font-weight: 600;
            text-transform: uppercase; letter-spacing: .05em;
            padding: .7rem 1rem;
            border-bottom: 1px solid var(--gray-200);
            white-space: nowrap;
        }
        .pro-table tbody td {
            padding: .75rem 1rem;
            border-bottom: 1px solid var(--gray-200);
            color: var(--gray-800);
            vertical-align: middle;
        }
        .pro-table tbody tr:last-child td { border-bottom: none; }
        .pro-table tbody tr:hover td { background: var(--gray-50); }

        /* ── INLINE UPDATE FORM ── */
        .update-form { display: flex; gap: .4rem; align-items: center; flex-wrap: nowrap; }
        .update-form select, .update-form input[type=text] {
            font-size: .82rem;
            border: 1px solid var(--gray-200);
            border-radius: 6px;
            padding: .3rem .55rem;
            background: #fff;
            color: var(--gray-800);
            transition: border-color .15s;
        }
        .update-form select:focus,
        .update-form input[type=text]:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(37,99,235,.1);
        }
        .update-form select  { min-width: 140px; }
        .update-form input[type=text] { min-width: 160px; }
        .btn-save {
            display: inline-flex; align-items: center; gap: .3rem;
            font-size: .8rem; font-weight: 600;
            padding: .32rem .85rem;
            border-radius: 6px;
            background: var(--primary); color: #fff; border: none;
            white-space: nowrap; transition: background .15s;
        }
        .btn-save:hover { background: #1d4ed8; }

        /* ── SCHEDULE FORM ── */
        .schedule-form-card {
            background: var(--gray-50);
            border: 1px solid var(--gray-200);
            border-radius: var(--radius);
            padding: 1.5rem;
        }

        /* ── EMPTY STATE ── */
        .empty-state {
            text-align: center;
            padding: 3rem 1rem;
            color: var(--gray-600);
        }
        .empty-state i { font-size: 2.5rem; margin-bottom: .75rem; opacity: .4; display: block; }
        .empty-state p { font-size: .9rem; margin: 0; }

        /* ── TICKET LABEL ── */
        .ticket-no {
            font-family: monospace;
            font-size: .8rem;
            background: var(--gray-100);
            padding: .15rem .45rem;
            border-radius: 4px;
            color: var(--gray-600);
        }

        /* ── SECTION TITLE ── */
        .section-title {
            font-size: .88rem;
            font-weight: 600;
            color: var(--gray-800);
            margin-bottom: 1rem;
            display: flex; align-items: center; gap: .4rem;
        }

        /* ── MODAL ── */
        .modal-header { padding: 1rem 1.25rem; }
        .modal-body   { padding: 1.25rem; }
        .modal-footer { padding: .85rem 1.25rem; background: var(--gray-50); }

        /* day badge */
        .day-circle {
            width: 34px; height: 34px;
            border-radius: 50%;
            background: var(--primary);
            color: #fff;
            display: inline-flex; align-items: center; justify-content: center;
            font-size: .82rem; font-weight: 700;
        }

        /* responsive scroll */
        .table-responsive { overflow-x: auto; }
    </style>
</head>
<body>

<div class="main-sidebar">
    <?php include 'inventory_sidebar.php'; ?>
</div>

<div class="container-fluid px-4 py-4" style="max-width:1400px;">

    <!-- PAGE HEADER -->
    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <h4 class="mb-0 fw-bold" style="color:var(--gray-800);">
                <i class="bi bi-tools me-2 text-primary"></i>Maintenance Management
            </h4>
            <p class="text-muted mb-0" style="font-size:.85rem;">
                Manage repair requests, preventive schedules, and maintenance history
            </p>
        </div>
        <div class="text-muted" style="font-size:.82rem;">
            <i class="bi bi-calendar3 me-1"></i><?= date('F d, Y') ?>
        </div>
    </div>

    <!-- STAT CARDS -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="stat-card blue">
                <div class="stat-icon"><i class="bi bi-clipboard-pulse"></i></div>
                <div>
                    <div class="stat-value"><?= $total_requests ?></div>
                    <div class="stat-label">Total Requests</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card red">
                <div class="stat-icon"><i class="bi bi-exclamation-circle"></i></div>
                <div>
                    <div class="stat-value"><?= $open_count ?></div>
                    <div class="stat-label">Open</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card amber">
                <div class="stat-icon"><i class="bi bi-arrow-repeat"></i></div>
                <div>
                    <div class="stat-value"><?= $progress_count ?></div>
                    <div class="stat-label">In Progress</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card green">
                <div class="stat-icon"><i class="bi bi-check-circle"></i></div>
                <div>
                    <div class="stat-value"><?= $history_count ?></div>
                    <div class="stat-label">Completed</div>
                </div>
            </div>
        </div>
    </div>

    <!-- MAIN CARD -->
    <div class="page-card">
        <div class="page-card-header">
            <ul class="nav custom-tabs mb-0" id="mainTab">
                <li class="nav-item">
                    <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#requests">
                        <i class="bi bi-wrench-adjustable"></i> Repair Requests
                        <?php if ($open_count > 0): ?>
                            <span class="badge rounded-pill bg-danger ms-1" style="font-size:.65rem;"><?= $open_count ?></span>
                        <?php endif; ?>
                    </button>
                </li>
                <li class="nav-item">
                    <button class="nav-link" data-bs-toggle="tab" data-bs-target="#schedule">
                        <i class="bi bi-calendar-check"></i> Schedule Maintenance
                    </button>
                </li>
                <li class="nav-item">
                    <button class="nav-link" data-bs-toggle="tab" data-bs-target="#history">
                        <i class="bi bi-clock-history"></i> History
                    </button>
                </li>
            </ul>
        </div>

        <div class="tab-content page-card-body">

            <!-- ====================================================== -->
            <!-- REPAIR REQUESTS                                          -->
            <!-- ====================================================== -->
            <div class="tab-pane fade show active" id="requests">
                <div class="table-responsive">
                    <table class="pro-table">
                        <thead>
                            <tr>
                                <th>Ticket No.</th>
                                <th>Equipment</th>
                                <th>Issue / Type</th>
                                <th>Location</th>
                                <th>Priority</th>
                                <th>Current Status</th>
                                <th style="min-width:420px;">Update</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (empty($requests)): ?>
                            <tr>
                                <td colspan="7">
                                    <div class="empty-state">
                                        <i class="bi bi-inbox"></i>
                                        <p>No repair requests found.</p>
                                    </div>
                                </td>
                            </tr>
                        <?php else: foreach ($requests as $req):
                            $opts      = getStatusOptions($req['status']);
                            $statusKey = strtolower(str_replace(' ', '-', $req['status']));
                            $statusIcon = match($req['status']) {
                                'Open'        => 'bi-circle-fill',
                                'In Progress' => 'bi-arrow-repeat',
                                'Completed'   => 'bi-check-circle-fill',
                                default       => 'bi-circle'
                            };
                        ?>
                            <tr>
                                <td>
                                    <span class="ticket-no"><?= htmlspecialchars($req['ticket_no'] ?? 'N/A') ?></span>
                                </td>
                                <td>
                                    <strong style="font-size:.85rem;"><?= htmlspecialchars($req['equipment']) ?></strong>
                                </td>
                                <td>
                                    <?php if ($req['issue'] === 'Preventive Maintenance'): ?>
                                        <span class="d-flex align-items-center gap-1">
                                            <i class="bi bi-shield-check text-success" style="font-size:.85rem;"></i>
                                            <span style="font-size:.83rem;">Preventive</span>
                                        </span>
                                    <?php else: ?>
                                        <span style="font-size:.83rem;"><?= htmlspecialchars($req['issue']) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="d-flex align-items-center gap-1" style="font-size:.83rem; color:var(--gray-600);">
                                        <i class="bi bi-geo-alt"></i>
                                        <?= htmlspecialchars($req['location'] ?: 'Unknown') ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="priority-badge <?= priorityBadge($req['priority']) ?>">
                                        <?= htmlspecialchars($req['priority']) ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="status-badge <?= $statusKey ?>">
                                        <i class="bi <?= $statusIcon ?>" style="font-size:.65rem;"></i>
                                        <?= htmlspecialchars($req['status']) ?>
                                    </span>
                                </td>
                                <td>
                                    <form method="POST"
                                          action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>"
                                          class="update-form">
                                        <input type="hidden" name="action"     value="update_request">
                                        <input type="hidden" name="request_id" value="<?= intval($req['id']) ?>">

                                        <select name="status">
                                            <?php foreach ($opts as $opt): ?>
                                                <option value="<?= htmlspecialchars($opt) ?>"
                                                    <?= $opt === $req['status'] ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($opt) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>

                                        <input type="text"
                                               name="remarks"
                                               placeholder="Add remarks…"
                                               value="<?= htmlspecialchars($req['remarks'] ?? '') ?>">

                                        <button type="submit" class="btn-save">
                                            <i class="bi bi-check2"></i> Save
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- ====================================================== -->
            <!-- SCHEDULE MAINTENANCE                                     -->
            <!-- ====================================================== -->
            <div class="tab-pane fade" id="schedule">
                <ul class="nav custom-pills mb-3" id="schedulePill">
                    <li class="nav-item">
                        <button class="nav-link active" data-bs-toggle="pill" data-bs-target="#setSchedule">
                            <i class="bi bi-plus-circle me-1"></i>Add Schedule
                        </button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link" data-bs-toggle="pill" data-bs-target="#listSchedule">
                            <i class="bi bi-list-ul me-1"></i>Scheduled List
                            <span class="badge rounded-pill bg-primary ms-1" style="font-size:.65rem;"><?= count($schedules) ?></span>
                        </button>
                    </li>
                </ul>

                <div class="tab-content">

                    <!-- Add Schedule -->
                    <div class="tab-pane fade show active" id="setSchedule">
                        <div class="schedule-form-card">
                            <p class="section-title">
                                <i class="bi bi-calendar-plus text-primary"></i> New Preventive Maintenance Schedule
                            </p>
                            <form method="POST" action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>" class="row g-3">
                                <input type="hidden" name="action" value="schedule">

                                <div class="col-md-5">
                                    <label class="form-label fw-semibold" style="font-size:.83rem;">Equipment</label>
                                    <select name="inventory_id" class="form-select form-select-sm" required>
                                        <option value="" disabled selected>— Select equipment —</option>
                                        <?php
                                        $scheduled_ids = array_column($schedules, 'inventory_id');
                                        foreach ($equipment as $eq):
                                            $disabled = in_array($eq['id'], $scheduled_ids) ? 'disabled' : '';
                                        ?>
                                            <option value="<?= $eq['id'] ?>" <?= $disabled ?>>
                                                <?= htmlspecialchars($eq['item_name']) ?>
                                                <?= $disabled ? '— Already Scheduled' : '' ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="col-md-3">
                                    <label class="form-label fw-semibold" style="font-size:.83rem;">Maintenance Day of Month</label>
                                    <input type="number" name="maintenance_day" class="form-control form-control-sm"
                                           min="1" max="31" placeholder="1 – 31" required>
                                    <div class="form-text" style="font-size:.75rem;">
                                        Auto-ticket is created on this day each month.
                                    </div>
                                </div>

                                <div class="col-md-4">
                                    <label class="form-label fw-semibold" style="font-size:.83rem;">Remarks</label>
                                    <input type="text" name="remarks" class="form-control form-control-sm"
                                           placeholder="Optional notes…">
                                </div>

                                <div class="col-12">
                                    <button type="submit" class="btn btn-primary btn-sm px-4">
                                        <i class="bi bi-save me-1"></i> Save Schedule
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Scheduled List -->
                    <div class="tab-pane fade" id="listSchedule">
                        <div class="table-responsive">
                            <table class="pro-table">
                                <thead>
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
                                    <tr>
                                        <td colspan="5">
                                            <div class="empty-state">
                                                <i class="bi bi-calendar-x"></i>
                                                <p>No schedules found. Add one above.</p>
                                            </div>
                                        </td>
                                    </tr>
                                <?php else: foreach ($schedules as $s):
                                    $isToday = ($s['maintenance_day'] == $today_day);
                                ?>
                                    <tr <?= $isToday ? 'style="background:#eff6ff;"' : '' ?>>
                                        <td>
                                            <strong style="font-size:.85rem;"><?= htmlspecialchars($s['item_name']) ?></strong>
                                            <?php if ($isToday): ?>
                                                <span class="badge bg-primary ms-1" style="font-size:.65rem;">Today</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="day-circle"><?= $s['maintenance_day'] ?></span>
                                        </td>
                                        <td style="font-size:.83rem; color:var(--gray-600);">
                                            <?= $s['remarks'] ? htmlspecialchars($s['remarks']) : '<span class="text-muted">—</span>' ?>
                                        </td>
                                        <td>
                                            <span style="font-size:.83rem; color:var(--gray-600);">
                                                <i class="bi bi-geo-alt me-1"></i>
                                                <?= htmlspecialchars($s['location'] ?: 'Unknown') ?>
                                            </span>
                                        </td>
                                        <td>
                                            <button class="btn btn-sm btn-outline-primary me-1"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#editModal<?= $s['id'] ?>"
                                                    style="font-size:.78rem; padding:.25rem .65rem;">
                                                <i class="bi bi-pencil me-1"></i>Edit
                                            </button>
                                            <button class="btn btn-sm btn-outline-danger"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#deleteModal<?= $s['id'] ?>"
                                                    style="font-size:.78rem; padding:.25rem .65rem;">
                                                <i class="bi bi-trash me-1"></i>Delete
                                            </button>
                                        </td>
                                    </tr>

                                    <!-- Edit Modal -->
                                    <div class="modal fade" id="editModal<?= $s['id'] ?>" tabindex="-1">
                                        <div class="modal-dialog modal-dialog-centered">
                                            <form method="POST" action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>">
                                                <input type="hidden" name="action"      value="edit_schedule">
                                                <input type="hidden" name="schedule_id" value="<?= $s['id'] ?>">
                                                <div class="modal-content">
                                                    <div class="modal-header">
                                                        <h6 class="modal-title fw-bold">
                                                            <i class="bi bi-pencil-square me-2 text-primary"></i>
                                                            Edit Schedule
                                                        </h6>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <p class="text-muted mb-3" style="font-size:.83rem;">
                                                            <i class="bi bi-box-seam me-1"></i>
                                                            <?= htmlspecialchars($s['item_name']) ?>
                                                        </p>
                                                        <div class="mb-3">
                                                            <label class="form-label fw-semibold" style="font-size:.83rem;">
                                                                Maintenance Day of Month
                                                            </label>
                                                            <input type="number" name="maintenance_day"
                                                                   class="form-control form-control-sm"
                                                                   min="1" max="31"
                                                                   value="<?= $s['maintenance_day'] ?>" required>
                                                        </div>
                                                        <div class="mb-2">
                                                            <label class="form-label fw-semibold" style="font-size:.83rem;">
                                                                Remarks
                                                            </label>
                                                            <textarea name="remarks" class="form-control form-control-sm"
                                                                      rows="3"><?= htmlspecialchars($s['remarks']) ?></textarea>
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-sm btn-secondary"
                                                                data-bs-dismiss="modal">Cancel</button>
                                                        <button type="submit" class="btn btn-sm btn-primary">
                                                            <i class="bi bi-save me-1"></i>Save Changes
                                                        </button>
                                                    </div>
                                                </div>
                                            </form>
                                        </div>
                                    </div>

                                    <!-- Delete Modal -->
                                    <div class="modal fade" id="deleteModal<?= $s['id'] ?>" tabindex="-1">
                                        <div class="modal-dialog modal-dialog-centered modal-sm">
                                            <form method="POST" action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>">
                                                <input type="hidden" name="action"      value="delete_schedule">
                                                <input type="hidden" name="schedule_id" value="<?= $s['id'] ?>">
                                                <div class="modal-content">
                                                    <div class="modal-header bg-danger text-white py-2">
                                                        <h6 class="modal-title fw-bold mb-0">
                                                            <i class="bi bi-trash me-2"></i>Delete Schedule
                                                        </h6>
                                                        <button type="button" class="btn-close btn-close-white"
                                                                data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <div class="modal-body text-center py-3">
                                                        <i class="bi bi-exclamation-triangle-fill text-danger"
                                                           style="font-size:2rem;"></i>
                                                        <p class="mt-2 mb-0" style="font-size:.87rem;">
                                                            Remove schedule for<br>
                                                            <strong><?= htmlspecialchars($s['item_name']) ?></strong>?
                                                        </p>
                                                    </div>
                                                    <div class="modal-footer justify-content-center py-2">
                                                        <button type="button" class="btn btn-sm btn-secondary"
                                                                data-bs-dismiss="modal">Cancel</button>
                                                        <button type="submit" class="btn btn-sm btn-danger">
                                                            <i class="bi bi-trash me-1"></i>Delete
                                                        </button>
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

                </div><!-- /inner tab-content -->
            </div>

            <!-- ====================================================== -->
            <!-- MAINTENANCE HISTORY                                      -->
            <!-- ====================================================== -->
            <div class="tab-pane fade" id="history">
                <div class="table-responsive">
                    <table class="pro-table">
                        <thead>
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
                            <tr>
                                <td colspan="5">
                                    <div class="empty-state">
                                        <i class="bi bi-clock-history"></i>
                                        <p>No maintenance history yet.</p>
                                    </div>
                                </td>
                            </tr>
                        <?php else: foreach ($history as $h): ?>
                            <tr>
                                <td>
                                    <strong style="font-size:.85rem;"><?= htmlspecialchars($h['equipment']) ?></strong>
                                </td>
                                <td>
                                    <?php if ($h['maintenance_type'] === 'Preventive'): ?>
                                        <span class="d-flex align-items-center gap-1" style="font-size:.83rem;">
                                            <i class="bi bi-shield-check text-success"></i> Preventive
                                        </span>
                                    <?php else: ?>
                                        <span class="d-flex align-items-center gap-1" style="font-size:.83rem;">
                                            <i class="bi bi-wrench text-primary"></i> Repair
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="status-badge completed">
                                        <i class="bi bi-check-circle-fill" style="font-size:.65rem;"></i>
                                        Completed
                                    </span>
                                </td>
                                <td style="font-size:.83rem; color:var(--gray-600);">
                                    <?= $h['remarks'] ? htmlspecialchars($h['remarks']) : '<span class="text-muted">—</span>' ?>
                                </td>
                                <td style="font-size:.83rem; color:var(--gray-600); white-space:nowrap;">
                                    <i class="bi bi-clock me-1"></i>
                                    <?= htmlspecialchars(date('M d, Y — h:i A', strtotime($h['completed_at']))) ?>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div><!-- /tab-content -->
    </div><!-- /page-card -->

</div><!-- /container -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>