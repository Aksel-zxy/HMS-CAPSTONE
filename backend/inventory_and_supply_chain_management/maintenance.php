<?php
include '../../SQL/config.php';
$today       = date("Y-m-d");
$today_day   = intval(date('d'));
$today_month = intval(date('m'));
$today_year  = intval(date('Y'));

try { $pdo->exec("ALTER TABLE repair_requests ADD COLUMN remarks TEXT NULL"); } catch (PDOException $e) {}
try { $pdo->exec("ALTER TABLE repair_requests ADD COLUMN ticket_no VARCHAR(100) NULL"); } catch (PDOException $e) {}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'update_request') {
    $request_id = intval($_POST['request_id']);
    $new_status = trim($_POST['status']);
    $remarks    = trim($_POST['remarks'] ?? '');
    $stmt = $pdo->prepare("SELECT * FROM repair_requests WHERE id = ?");
    $stmt->execute([$request_id]);
    $request = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($request) {
        $current_status = $request['status'];
        $allowed = ['Open' => ['In Progress'], 'In Progress' => ['Completed']];
        $can_transition = isset($allowed[$current_status]) && in_array($new_status, $allowed[$current_status]);
        if ($new_status === $current_status) {
            if ($remarks !== '') {
                $pdo->prepare("UPDATE repair_requests SET remarks = ? WHERE id = ?")->execute([$remarks, $request_id]);
            }
        } elseif ($can_transition) {
            if ($new_status === 'Completed') {
                $ins = $pdo->prepare("INSERT INTO maintenance_history (equipment, maintenance_type, status, remarks, completed_at) VALUES (?, ?, 'Completed', ?, NOW())");
                $ins->execute([$request['equipment'], ($request['issue'] === 'Preventive Maintenance' ? 'Preventive' : 'Repair'), $remarks !== '' ? $remarks : ($request['remarks'] ?? '')]);
                $pdo->prepare("DELETE FROM repair_requests WHERE id = ?")->execute([$request_id]);
            } else {
                $pdo->prepare("UPDATE repair_requests SET status = ?, remarks = ? WHERE id = ?")->execute([$new_status, $remarks !== '' ? $remarks : ($request['remarks'] ?? ''), $request_id]);
            }
        }
    }
    header("Location: " . $_SERVER['PHP_SELF']); exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'schedule') {
    $inventory_id    = intval($_POST['inventory_id']);
    $maintenance_day = intval($_POST['maintenance_day']);
    $remarks         = trim($_POST['remarks']);
    $checkStmt = $pdo->prepare("SELECT id FROM maintenance_records WHERE inventory_id = ? LIMIT 1");
    $checkStmt->execute([$inventory_id]);
    if ($inventory_id > 0 && $maintenance_day >= 1 && $maintenance_day <= 31 && !$checkStmt->fetch()) {
        $pdo->prepare("INSERT INTO maintenance_records (inventory_id, maintenance_day, maintenance_type, remarks, created_at) VALUES (?, ?, 'Preventive', ?, NOW())")->execute([$inventory_id, $maintenance_day, $remarks]);
    }
    header("Location: " . $_SERVER['PHP_SELF']); exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'edit_schedule') {
    $id              = intval($_POST['schedule_id']);
    $maintenance_day = intval($_POST['maintenance_day']);
    $remarks         = trim($_POST['remarks']);
    $pdo->prepare("UPDATE maintenance_records SET maintenance_day = ?, remarks = ? WHERE id = ?")->execute([$maintenance_day, $remarks, $id]);
    header("Location: " . $_SERVER['PHP_SELF']); exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'delete_schedule') {
    $pdo->prepare("DELETE FROM maintenance_records WHERE id = ?")->execute([intval($_POST['schedule_id'])]);
    header("Location: " . $_SERVER['PHP_SELF']); exit;
}

$stmt = $pdo->prepare("SELECT MIN(id) AS id, item_name FROM inventory WHERE item_type = 'Diagnostic Equipment' GROUP BY item_name ORDER BY item_name ASC");
$stmt->execute();
$equipment = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare("SELECT mr.id, mr.inventory_id, mr.maintenance_day, mr.remarks, i.item_name, GROUP_CONCAT(DISTINCT da.department SEPARATOR ', ') AS location FROM maintenance_records mr JOIN inventory i ON mr.inventory_id = i.id LEFT JOIN department_assets da ON i.item_id = da.item_id GROUP BY mr.id, mr.inventory_id, mr.maintenance_day, mr.remarks, i.item_name ORDER BY mr.maintenance_day ASC");
$stmt->execute();
$schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($schedules as $due) {
    if ($due['maintenance_day'] == $today_day) {
        $ticket_no = "MAINT-" . $due['id'] . "-" . $today_year . "-" . $today_month;
        $check = $pdo->prepare("SELECT id FROM repair_requests WHERE ticket_no = ? LIMIT 1");
        $check->execute([$ticket_no]);
        if (!$check->fetch()) {
            $pdo->prepare("INSERT INTO repair_requests (ticket_no, user_name, equipment, issue, priority, status, created_at) VALUES (?, 'System', ?, 'Preventive Maintenance', 'Medium', 'Open', NOW())")->execute([$ticket_no, $due['item_name']]);
        }
    }
}

// FIX 1: Use repair_requests.location column directly — no JOIN needed
$stmt = $pdo->prepare("SELECT * FROM repair_requests ORDER BY created_at DESC");
$stmt->execute();
$requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare("SELECT * FROM maintenance_history ORDER BY completed_at DESC");
$stmt->execute();
$history = $stmt->fetchAll(PDO::FETCH_ASSOC);

$total_requests = count($requests);
$open_count     = count(array_filter($requests, fn($r) => $r['status'] === 'Open'));
$progress_count = count(array_filter($requests, fn($r) => $r['status'] === 'In Progress'));
$history_count  = count($history);

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
            --gray-400:  #94a3b8;
            --gray-600:  #475569;
            --gray-800:  #1e293b;
            --radius:    10px;
            --shadow:    0 1px 4px rgba(0,0,0,.08);
            --shadow-md: 0 4px 16px rgba(0,0,0,.10);
        }
        *, *::before, *::after { box-sizing: border-box; }
        html { scroll-behavior: smooth; }
        body { background: var(--gray-100); font-family: 'Segoe UI', system-ui, -apple-system, sans-serif; font-size: 14px; color: var(--gray-800); min-height: 100vh; }
        .main-wrapper { padding: 1.5rem; max-width: 1400px; margin: 0 auto; width: 100%; }
        .page-header { display: flex; align-items: flex-start; justify-content: space-between; flex-wrap: wrap; gap: .75rem; margin-bottom: 1.5rem; }
        .page-header h4 { margin: 0; font-weight: 700; font-size: clamp(1.1rem, 3vw, 1.4rem); color: var(--gray-800); }
        .page-header p { margin: .2rem 0 0; color: var(--gray-600); font-size: .82rem; }
        .page-date { font-size: .8rem; color: var(--gray-600); background: #fff; border: 1px solid var(--gray-200); border-radius: 8px; padding: .4rem .85rem; white-space: nowrap; flex-shrink: 0; }
        .stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: .85rem; margin-bottom: 1.5rem; }
        .stat-card { background: #fff; border-radius: var(--radius); box-shadow: var(--shadow); padding: 1.1rem 1.25rem; display: flex; align-items: center; gap: .9rem; border-left: 4px solid transparent; transition: box-shadow .2s, transform .2s; }
        .stat-card:hover { box-shadow: var(--shadow-md); transform: translateY(-1px); }
        .stat-card.blue { border-color: var(--primary); } .stat-card.red { border-color: var(--danger); } .stat-card.amber { border-color: var(--warning); } .stat-card.green { border-color: var(--success); }
        .stat-icon { width: 44px; height: 44px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 1.25rem; flex-shrink: 0; }
        .stat-card.blue .stat-icon { background: #dbeafe; color: var(--primary); } .stat-card.red .stat-icon { background: #fee2e2; color: var(--danger); } .stat-card.amber .stat-icon { background: #fef3c7; color: var(--warning); } .stat-card.green .stat-icon { background: #dcfce7; color: var(--success); }
        .stat-value { font-size: 1.5rem; font-weight: 700; color: var(--gray-800); line-height: 1; }
        .stat-label { font-size: .76rem; color: var(--gray-600); margin-top: 3px; }
        .page-card { background: #fff; border-radius: var(--radius); box-shadow: var(--shadow); overflow: hidden; }
        .page-card-header { padding: 0 1.25rem; border-bottom: 1px solid var(--gray-200); background: var(--gray-50); overflow-x: auto; -webkit-overflow-scrolling: touch; }
        .custom-tabs { display: flex; gap: 0; white-space: nowrap; border: none; margin: 0; padding: 0; }
        .custom-tabs .nav-link { color: var(--gray-600); border: none; border-bottom: 2px solid transparent; padding: .8rem 1.1rem; font-size: .85rem; font-weight: 500; display: inline-flex; align-items: center; gap: .4rem; transition: color .15s; white-space: nowrap; background: transparent; }
        .custom-tabs .nav-link:hover { color: var(--primary); }
        .custom-tabs .nav-link.active { color: var(--primary); border-bottom-color: var(--primary); background: transparent; }
        .page-card-body { padding: 1.25rem; }
        .custom-pills { display: flex; flex-wrap: wrap; gap: .4rem; margin-bottom: 1rem; }
        .custom-pills .nav-link { color: var(--gray-600); font-size: .82rem; font-weight: 500; border-radius: 20px; padding: .35rem 1rem; background: var(--gray-100); border: none; display: inline-flex; align-items: center; gap: .3rem; transition: background .15s, color .15s; }
        .custom-pills .nav-link.active { background: var(--primary); color: #fff; }

        /* =============================================
           FIX 2 — STATUS BADGE COLORS
           Open        = green
           In Progress = orange
           Completed   = green (deeper)
        ============================================= */
        .status-badge { display: inline-flex; align-items: center; gap: .3rem; font-size: .73rem; font-weight: 600; padding: .28rem .7rem; border-radius: 20px; white-space: nowrap; }
        .status-badge.open        { background: #dcfce7; color: #15803d; }
        .status-badge.in-progress { background: #ffedd5; color: #c2410c; }
        .status-badge.completed   { background: #bbf7d0; color: #166534; }

        .priority-badge { display: inline-block; font-size: .7rem; font-weight: 600; padding: .22rem .65rem; border-radius: 20px; white-space: nowrap; }
        .badge-priority-high   { background: #fee2e2; color: #b91c1c; }
        .badge-priority-medium { background: #fef3c7; color: #92400e; }
        .badge-priority-low    { background: #dcfce7; color: #15803d; }
        .pro-table { width: 100%; border-collapse: separate; border-spacing: 0; font-size: .84rem; }
        .pro-table thead th { background: var(--gray-50); color: var(--gray-600); font-size: .72rem; font-weight: 600; text-transform: uppercase; letter-spacing: .05em; padding: .7rem 1rem; border-bottom: 1px solid var(--gray-200); white-space: nowrap; position: sticky; top: 0; z-index: 1; }
        .pro-table tbody td { padding: .75rem 1rem; border-bottom: 1px solid var(--gray-200); vertical-align: middle; }
        .pro-table tbody tr:last-child td { border-bottom: none; }
        .pro-table tbody tr:hover td { background: var(--gray-50); }
        .mobile-cards { display: none; }
        .req-card { background: #fff; border: 1px solid var(--gray-200); border-radius: 10px; padding: 1rem; margin-bottom: .75rem; box-shadow: var(--shadow); }
        .req-card-header { display: flex; align-items: flex-start; justify-content: space-between; gap: .5rem; margin-bottom: .65rem; }
        .req-card-title { font-size: .88rem; font-weight: 600; color: var(--gray-800); margin: 0; }
        .req-card-meta { display: flex; flex-wrap: wrap; gap: .4rem .9rem; margin-bottom: .75rem; }
        .req-card-meta span { font-size: .78rem; color: var(--gray-600); display: inline-flex; align-items: center; gap: .25rem; }
        .req-card-form { display: flex; flex-direction: column; gap: .5rem; padding-top: .75rem; border-top: 1px solid var(--gray-200); }
        .req-card-form select, .req-card-form input[type=text] { width: 100%; font-size: .83rem; border: 1px solid var(--gray-200); border-radius: 7px; padding: .45rem .7rem; color: var(--gray-800); background: #fff; }
        .req-card-form select:focus, .req-card-form input[type=text]:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 3px rgba(37,99,235,.1); }
        .btn-save-full { width: 100%; display: flex; align-items: center; justify-content: center; gap: .4rem; font-size: .83rem; font-weight: 600; padding: .5rem; border-radius: 7px; background: var(--primary); color: #fff; border: none; transition: background .15s; }
        .btn-save-full:hover { background: #1d4ed8; }
        .update-form { display: flex; gap: .4rem; align-items: center; flex-wrap: nowrap; }
        .update-form select, .update-form input[type=text] { font-size: .82rem; border: 1px solid var(--gray-200); border-radius: 6px; padding: .3rem .55rem; background: #fff; color: var(--gray-800); transition: border-color .15s; }
        .update-form select:focus, .update-form input[type=text]:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 3px rgba(37,99,235,.1); }
        .update-form select { min-width: 140px; } .update-form input[type=text] { min-width: 150px; }
        .btn-save { display: inline-flex; align-items: center; gap: .3rem; font-size: .8rem; font-weight: 600; padding: .32rem .85rem; border-radius: 6px; background: var(--primary); color: #fff; border: none; white-space: nowrap; transition: background .15s; flex-shrink: 0; }
        .btn-save:hover { background: #1d4ed8; }
        .schedule-form-card { background: var(--gray-50); border: 1px solid var(--gray-200); border-radius: var(--radius); padding: 1.4rem; }
        .empty-state { text-align: center; padding: 3rem 1rem; color: var(--gray-400); }
        .empty-state i { font-size: 2.5rem; margin-bottom: .75rem; display: block; }
        .empty-state p { font-size: .88rem; margin: 0; color: var(--gray-600); }
        .ticket-no { font-family: 'Courier New', monospace; font-size: .78rem; background: var(--gray-100); padding: .15rem .45rem; border-radius: 4px; color: var(--gray-600); word-break: break-all; }
        .day-circle { width: 34px; height: 34px; border-radius: 50%; background: var(--primary); color: #fff; display: inline-flex; align-items: center; justify-content: center; font-size: .82rem; font-weight: 700; flex-shrink: 0; }
        .section-title { font-size: .88rem; font-weight: 600; color: var(--gray-800); margin-bottom: 1rem; display: flex; align-items: center; gap: .4rem; }
        .table-scroll { overflow-x: auto; -webkit-overflow-scrolling: touch; }
        .modal-header { padding: .9rem 1.2rem; } .modal-body { padding: 1.2rem; }
        .modal-footer { padding: .8rem 1.2rem; background: var(--gray-50); border-top: 1px solid var(--gray-200); }
        @media (min-width: 1400px) { .main-wrapper { padding: 2rem; } .stats-grid { gap: 1.1rem; } }
        @media (max-width: 1199px) { .update-form input[type=text] { min-width: 120px; } .update-form select { min-width: 130px; } }
        @media (max-width: 991px) { .main-wrapper { padding: 1rem; } .stats-grid { grid-template-columns: repeat(2, 1fr); gap: .75rem; } .desktop-table-requests { display: none !important; } .mobile-cards { display: block; } .pro-table thead th, .pro-table tbody td { padding: .6rem .75rem; font-size: .8rem; } .page-card-body { padding: 1rem; } .schedule-form-card { padding: 1.1rem; } }
        @media (max-width: 767px) { .main-wrapper { padding: .75rem; } .page-header { flex-direction: column; gap: .5rem; } .page-date { align-self: flex-start; } .stats-grid { grid-template-columns: repeat(2, 1fr); gap: .6rem; } .stat-card { padding: .85rem 1rem; gap: .7rem; } .stat-icon { width: 38px; height: 38px; font-size: 1.1rem; } .stat-value { font-size: 1.3rem; } .stat-label { font-size: .72rem; } .page-card-header { padding: 0 .85rem; } .custom-tabs .nav-link { padding: .7rem .85rem; font-size: .8rem; } .page-card-body { padding: .85rem; } .schedule-form-card .row > div { flex: 0 0 100%; max-width: 100%; } .schedule-hide-sm { display: none; } .history-hide-sm { display: none; } }
        @media (max-width: 575px) { .main-wrapper { padding: .6rem; } .page-header h4 { font-size: 1.05rem; } .page-header p { font-size: .78rem; } .stats-grid { grid-template-columns: repeat(2, 1fr); gap: .5rem; } .stat-card { padding: .75rem .85rem; flex-direction: column; align-items: flex-start; gap: .5rem; } .stat-icon { width: 34px; height: 34px; font-size: 1rem; } .stat-value { font-size: 1.2rem; } .stat-label { font-size: .7rem; } .page-card-body { padding: .75rem; } .page-card-header { padding: 0 .75rem; } .custom-tabs .nav-link { padding: .65rem .7rem; font-size: .78rem; gap: .25rem; } .custom-tabs .nav-link .tab-text { display: none; } .req-card { padding: .85rem; } .req-card-title { font-size: .85rem; } .schedule-form-card { padding: .9rem; } .modal-dialog { margin: .5rem; } .day-circle { width: 30px; height: 30px; font-size: .76rem; } .custom-pills { gap: .35rem; } .custom-pills .nav-link { font-size: .78rem; padding: .3rem .8rem; } .history-hide-xs { display: none; } .schedule-hide-xs { display: none; } }
        @media (max-width: 379px) { .stats-grid { grid-template-columns: 1fr 1fr; gap: .4rem; } .stat-card { padding: .65rem .75rem; } .stat-value { font-size: 1.1rem; } .custom-tabs .nav-link { padding: .6rem .6rem; font-size: .74rem; } .req-card-meta span { font-size: .73rem; } }
        @media print { .main-sidebar, .page-card-header .custom-tabs, .update-form, .btn-save, .btn-save-full { display: none !important; } .page-card { box-shadow: none; border: 1px solid #ccc; } .pro-table thead th { background: #f0f0f0 !important; } }
    </style>
</head>
<body>
<div class="main-sidebar">
    <?php include 'inventory_sidebar.php'; ?>
</div>

<div class="main-wrapper">
    <div class="page-header">
        <div>
            <h4><i class="bi bi-tools me-2 text-primary"></i>Maintenance Management</h4>
            <p>Manage repair requests, preventive schedules, and maintenance history</p>
        </div>
        <div class="page-date"><i class="bi bi-calendar3 me-1"></i><?= date('F d, Y') ?></div>
    </div>

    <div class="stats-grid">
        <div class="stat-card blue"><div class="stat-icon"><i class="bi bi-clipboard-pulse"></i></div><div><div class="stat-value"><?= $total_requests ?></div><div class="stat-label">Total Requests</div></div></div>
        <div class="stat-card red"><div class="stat-icon"><i class="bi bi-exclamation-circle"></i></div><div><div class="stat-value"><?= $open_count ?></div><div class="stat-label">Open</div></div></div>
        <div class="stat-card amber"><div class="stat-icon"><i class="bi bi-arrow-repeat"></i></div><div><div class="stat-value"><?= $progress_count ?></div><div class="stat-label">In Progress</div></div></div>
        <div class="stat-card green"><div class="stat-icon"><i class="bi bi-check-circle"></i></div><div><div class="stat-value"><?= $history_count ?></div><div class="stat-label">Completed</div></div></div>
    </div>

    <div class="page-card">
        <div class="page-card-header">
            <ul class="nav custom-tabs" id="mainTab">
                <li class="nav-item">
                    <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#requests">
                        <i class="bi bi-wrench-adjustable"></i><span class="tab-text">Repair Requests</span>
                        <?php if ($open_count > 0): ?><span class="badge rounded-pill bg-danger ms-1" style="font-size:.63rem;"><?= $open_count ?></span><?php endif; ?>
                    </button>
                </li>
                <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#schedule"><i class="bi bi-calendar-check"></i><span class="tab-text">Schedule</span></button></li>
                <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#history"><i class="bi bi-clock-history"></i><span class="tab-text">History</span></button></li>
            </ul>
        </div>

        <div class="tab-content page-card-body">

            <!-- REPAIR REQUESTS -->
            <div class="tab-pane fade show active" id="requests">

                <!-- DESKTOP TABLE -->
                <div class="table-scroll desktop-table-requests">
                    <table class="pro-table">
                        <thead>
                            <tr>
                                <th>Ticket No.</th><th>Equipment</th><th>Issue / Type</th>
                                <th>Location</th><th>Priority</th><th>Status</th>
                                <th style="min-width:400px;">Update</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (empty($requests)): ?>
                            <tr><td colspan="7"><div class="empty-state"><i class="bi bi-inbox"></i><p>No repair requests found.</p></div></td></tr>
                        <?php else: foreach ($requests as $req):
                            $opts      = getStatusOptions($req['status']);
                            $statusKey = strtolower(str_replace(' ', '-', $req['status']));
                            $statusIcon = match($req['status']) { 'Open' => 'bi-circle-fill', 'In Progress' => 'bi-arrow-repeat', 'Completed' => 'bi-check-circle-fill', default => 'bi-circle' };
                        ?>
                            <tr>
                                <td><span class="ticket-no"><?= htmlspecialchars($req['ticket_no'] ?? 'N/A') ?></span></td>
                                <td><strong><?= htmlspecialchars($req['equipment']) ?></strong></td>
                                <td>
                                    <?php if ($req['issue'] === 'Preventive Maintenance'): ?>
                                        <span class="d-flex align-items-center gap-1"><i class="bi bi-shield-check text-success"></i> Preventive</span>
                                    <?php else: ?><?= htmlspecialchars($req['issue']) ?><?php endif; ?>
                                </td>
                                <td>
                                    <!-- FIX 1: location read directly from repair_requests table -->
                                    <span class="d-flex align-items-center gap-1" style="color:var(--gray-600);">
                                        <i class="bi bi-geo-alt"></i><?= htmlspecialchars($req['location'] ?: 'Unknown') ?>
                                    </span>
                                </td>
                                <td><span class="priority-badge <?= priorityBadge($req['priority']) ?>"><?= htmlspecialchars($req['priority']) ?></span></td>
                                <td>
                                    <!-- FIX 2: open=green, in-progress=orange, completed=green -->
                                    <span class="status-badge <?= $statusKey ?>">
                                        <i class="bi <?= $statusIcon ?>" style="font-size:.63rem;"></i>
                                        <?= htmlspecialchars($req['status']) ?>
                                    </span>
                                </td>
                                <td>
                                    <form method="POST" action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>" class="update-form">
                                        <input type="hidden" name="action" value="update_request">
                                        <input type="hidden" name="request_id" value="<?= intval($req['id']) ?>">
                                        <select name="status">
                                            <?php foreach ($opts as $opt): ?>
                                                <option value="<?= htmlspecialchars($opt) ?>" <?= $opt === $req['status'] ? 'selected' : '' ?>><?= htmlspecialchars($opt) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <input type="text" name="remarks" placeholder="Add remarks…" value="<?= htmlspecialchars($req['remarks'] ?? '') ?>">
                                        <button type="submit" class="btn-save"><i class="bi bi-check2"></i> Save</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- MOBILE CARDS -->
                <div class="mobile-cards">
                    <?php if (empty($requests)): ?>
                        <div class="empty-state"><i class="bi bi-inbox"></i><p>No repair requests found.</p></div>
                    <?php else: foreach ($requests as $req):
                        $opts      = getStatusOptions($req['status']);
                        $statusKey = strtolower(str_replace(' ', '-', $req['status']));
                        $statusIcon = match($req['status']) { 'Open' => 'bi-circle-fill', 'In Progress' => 'bi-arrow-repeat', 'Completed' => 'bi-check-circle-fill', default => 'bi-circle' };
                    ?>
                        <div class="req-card">
                            <div class="req-card-header">
                                <div>
                                    <p class="req-card-title"><?= htmlspecialchars($req['equipment']) ?></p>
                                    <span class="ticket-no"><?= htmlspecialchars($req['ticket_no'] ?? 'N/A') ?></span>
                                </div>
                                <span class="status-badge <?= $statusKey ?>">
                                    <i class="bi <?= $statusIcon ?>" style="font-size:.6rem;"></i>
                                    <?= htmlspecialchars($req['status']) ?>
                                </span>
                            </div>
                            <div class="req-card-meta">
                                <span>
                                    <?php if ($req['issue'] === 'Preventive Maintenance'): ?>
                                        <i class="bi bi-shield-check text-success"></i> Preventive
                                    <?php else: ?><i class="bi bi-wrench text-primary"></i> <?= htmlspecialchars($req['issue']) ?><?php endif; ?>
                                </span>
                                <span>
                                    <!-- FIX 1: location from repair_requests.location -->
                                    <i class="bi bi-geo-alt"></i><?= htmlspecialchars($req['location'] ?: 'Unknown') ?>
                                </span>
                                <span><span class="priority-badge <?= priorityBadge($req['priority']) ?>"><?= htmlspecialchars($req['priority']) ?></span></span>
                            </div>
                            <form method="POST" action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>" class="req-card-form">
                                <input type="hidden" name="action" value="update_request">
                                <input type="hidden" name="request_id" value="<?= intval($req['id']) ?>">
                                <select name="status">
                                    <?php foreach ($opts as $opt): ?>
                                        <option value="<?= htmlspecialchars($opt) ?>" <?= $opt === $req['status'] ? 'selected' : '' ?>><?= htmlspecialchars($opt) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <input type="text" name="remarks" placeholder="Add remarks…" value="<?= htmlspecialchars($req['remarks'] ?? '') ?>">
                                <button type="submit" class="btn-save-full"><i class="bi bi-check2"></i> Save Changes</button>
                            </form>
                        </div>
                    <?php endforeach; endif; ?>
                </div>

            </div>

            <!-- SCHEDULE -->
            <div class="tab-pane fade" id="schedule">
                <ul class="nav custom-pills" id="schedulePill">
                    <li class="nav-item"><button class="nav-link active" data-bs-toggle="pill" data-bs-target="#setSchedule"><i class="bi bi-plus-circle"></i> Add Schedule</button></li>
                    <li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#listSchedule"><i class="bi bi-list-ul"></i> Scheduled List <span class="badge rounded-pill bg-primary ms-1" style="font-size:.63rem;"><?= count($schedules) ?></span></button></li>
                </ul>
                <div class="tab-content">
                    <div class="tab-pane fade show active" id="setSchedule">
                        <div class="schedule-form-card">
                            <p class="section-title"><i class="bi bi-calendar-plus text-primary"></i> New Preventive Maintenance Schedule</p>
                            <form method="POST" action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>" class="row g-3">
                                <input type="hidden" name="action" value="schedule">
                                <div class="col-12 col-sm-6 col-lg-5">
                                    <label class="form-label fw-semibold" style="font-size:.82rem;">Equipment</label>
                                    <select name="inventory_id" class="form-select form-select-sm" required>
                                        <option value="" disabled selected>— Select equipment —</option>
                                        <?php $scheduled_ids = array_column($schedules, 'inventory_id'); foreach ($equipment as $eq): $disabled = in_array($eq['id'], $scheduled_ids) ? 'disabled' : ''; ?>
                                            <option value="<?= $eq['id'] ?>" <?= $disabled ?>><?= htmlspecialchars($eq['item_name']) ?><?= $disabled ? ' — Already Scheduled' : '' ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-12 col-sm-6 col-lg-3">
                                    <label class="form-label fw-semibold" style="font-size:.82rem;">Day of Month</label>
                                    <input type="number" name="maintenance_day" class="form-control form-control-sm" min="1" max="31" placeholder="1 – 31" required>
                                    <div class="form-text" style="font-size:.73rem;">Auto-ticket created on this day each month.</div>
                                </div>
                                <div class="col-12 col-lg-4">
                                    <label class="form-label fw-semibold" style="font-size:.82rem;">Remarks</label>
                                    <input type="text" name="remarks" class="form-control form-control-sm" placeholder="Optional notes…">
                                </div>
                                <div class="col-12"><button type="submit" class="btn btn-primary btn-sm px-4"><i class="bi bi-save me-1"></i> Save Schedule</button></div>
                            </form>
                        </div>
                    </div>
                    <div class="tab-pane fade" id="listSchedule">
                        <div class="table-scroll">
                            <table class="pro-table">
                                <thead><tr><th>Equipment</th><th>Day</th><th class="schedule-hide-sm">Remarks</th><th class="schedule-hide-xs">Location</th><th>Actions</th></tr></thead>
                                <tbody>
                                <?php if (empty($schedules)): ?>
                                    <tr><td colspan="5"><div class="empty-state"><i class="bi bi-calendar-x"></i><p>No schedules found. Add one above.</p></div></td></tr>
                                <?php else: foreach ($schedules as $s): $isToday = ($s['maintenance_day'] == $today_day); ?>
                                    <tr <?= $isToday ? 'style="background:#eff6ff;"' : '' ?>>
                                        <td>
                                            <strong style="font-size:.84rem;"><?= htmlspecialchars($s['item_name']) ?></strong>
                                            <?php if ($isToday): ?><span class="badge bg-primary ms-1" style="font-size:.62rem;">Today</span><?php endif; ?>
                                        </td>
                                        <td><span class="day-circle"><?= $s['maintenance_day'] ?></span></td>
                                        <td class="schedule-hide-sm" style="color:var(--gray-600); font-size:.82rem;"><?= $s['remarks'] ? htmlspecialchars($s['remarks']) : '<span class="text-muted">—</span>' ?></td>
                                        <td class="schedule-hide-xs" style="color:var(--gray-600); font-size:.82rem;"><i class="bi bi-geo-alt me-1"></i><?= htmlspecialchars($s['location'] ?: 'Unknown') ?></td>
                                        <td>
                                            <div class="d-flex gap-1 flex-wrap">
                                                <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editModal<?= $s['id'] ?>" style="font-size:.76rem; padding:.25rem .6rem;"><i class="bi bi-pencil"></i><span class="d-none d-md-inline ms-1">Edit</span></button>
                                                <button class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteModal<?= $s['id'] ?>" style="font-size:.76rem; padding:.25rem .6rem;"><i class="bi bi-trash"></i><span class="d-none d-md-inline ms-1">Delete</span></button>
                                            </div>
                                        </td>
                                    </tr>
                                    <div class="modal fade" id="editModal<?= $s['id'] ?>" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><form method="POST" action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>"><input type="hidden" name="action" value="edit_schedule"><input type="hidden" name="schedule_id" value="<?= $s['id'] ?>"><div class="modal-content"><div class="modal-header"><h6 class="modal-title fw-bold"><i class="bi bi-pencil-square me-2 text-primary"></i>Edit Schedule</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><p class="text-muted mb-3" style="font-size:.82rem;"><i class="bi bi-box-seam me-1"></i><?= htmlspecialchars($s['item_name']) ?></p><div class="mb-3"><label class="form-label fw-semibold" style="font-size:.82rem;">Maintenance Day</label><input type="number" name="maintenance_day" class="form-control form-control-sm" min="1" max="31" value="<?= $s['maintenance_day'] ?>" required></div><div class="mb-2"><label class="form-label fw-semibold" style="font-size:.82rem;">Remarks</label><textarea name="remarks" class="form-control form-control-sm" rows="3"><?= htmlspecialchars($s['remarks']) ?></textarea></div></div><div class="modal-footer"><button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-sm btn-primary"><i class="bi bi-save me-1"></i>Save Changes</button></div></div></form></div></div>
                                    <div class="modal fade" id="deleteModal<?= $s['id'] ?>" tabindex="-1"><div class="modal-dialog modal-dialog-centered modal-sm"><form method="POST" action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>"><input type="hidden" name="action" value="delete_schedule"><input type="hidden" name="schedule_id" value="<?= $s['id'] ?>"><div class="modal-content"><div class="modal-header bg-danger text-white py-2"><h6 class="modal-title fw-bold mb-0"><i class="bi bi-trash me-2"></i>Delete Schedule</h6><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div><div class="modal-body text-center py-3"><i class="bi bi-exclamation-triangle-fill text-danger" style="font-size:2rem;"></i><p class="mt-2 mb-0" style="font-size:.86rem;">Remove schedule for<br><strong><?= htmlspecialchars($s['item_name']) ?></strong>?</p></div><div class="modal-footer justify-content-center py-2"><button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-sm btn-danger"><i class="bi bi-trash me-1"></i>Delete</button></div></div></form></div></div>
                                <?php endforeach; endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- HISTORY -->
            <div class="tab-pane fade" id="history">
                <div class="table-scroll">
                    <table class="pro-table">
                        <thead><tr><th>Equipment</th><th>Type</th><th>Status</th><th class="history-hide-sm">Remarks</th><th class="history-hide-xs">Completed At</th></tr></thead>
                        <tbody>
                        <?php if (empty($history)): ?>
                            <tr><td colspan="5"><div class="empty-state"><i class="bi bi-clock-history"></i><p>No maintenance history yet.</p></div></td></tr>
                        <?php else: foreach ($history as $h): ?>
                            <tr>
                                <td><strong style="font-size:.84rem;"><?= htmlspecialchars($h['equipment']) ?></strong></td>
                                <td><?php if ($h['maintenance_type'] === 'Preventive'): ?><span class="d-flex align-items-center gap-1" style="font-size:.82rem;"><i class="bi bi-shield-check text-success"></i> Preventive</span><?php else: ?><span class="d-flex align-items-center gap-1" style="font-size:.82rem;"><i class="bi bi-wrench text-primary"></i> Repair</span><?php endif; ?></td>
                                <td><span class="status-badge completed"><i class="bi bi-check-circle-fill" style="font-size:.6rem;"></i> Completed</span></td>
                                <td class="history-hide-sm" style="font-size:.82rem; color:var(--gray-600);"><?= $h['remarks'] ? htmlspecialchars($h['remarks']) : '<span class="text-muted">—</span>' ?></td>
                                <td class="history-hide-xs" style="font-size:.81rem; color:var(--gray-600); white-space:nowrap;"><i class="bi bi-clock me-1"></i><?= htmlspecialchars(date('M d, Y — h:i A', strtotime($h['completed_at']))) ?></td>
                            </tr>
                        <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>