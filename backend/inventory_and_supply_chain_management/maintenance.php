<?php
include '../../SQL/config.php';
$today       = date("Y-m-d");
$today_day   = intval(date('d'));
$today_month = intval(date('m'));
$today_year  = intval(date('Y'));

try { $pdo->exec("ALTER TABLE repair_requests ADD COLUMN remarks TEXT NULL"); } catch (PDOException $e) {}
try { $pdo->exec("ALTER TABLE repair_requests ADD COLUMN ticket_no VARCHAR(100) NULL"); } catch (PDOException $e) {}

/* =====================================================
   AJAX HANDLER — updates ONE request only
   Triggered by X-Requested-With: XMLHttpRequest header
=====================================================*/
if (
    isset($_SERVER['HTTP_X_REQUESTED_WITH']) &&
    strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest' &&
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    ($_POST['action'] ?? '') === 'update_request'
) {
    header('Content-Type: application/json');

    $request_id = intval($_POST['request_id'] ?? 0);
    $new_status = trim($_POST['status']        ?? '');
    $remarks    = trim($_POST['remarks']       ?? '');

    if ($request_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid request ID.']);
        exit;
    }

    $stmt = $pdo->prepare("SELECT * FROM repair_requests WHERE id = ?");
    $stmt->execute([$request_id]);
    $request = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$request) {
        echo json_encode(['success' => false, 'message' => 'Request not found.']);
        exit;
    }

    $current_status = $request['status'];
    $allowed        = ['Open' => ['In Progress'], 'In Progress' => ['Completed']];
    $can_transition = isset($allowed[$current_status]) && in_array($new_status, $allowed[$current_status]);

    if ($new_status === $current_status) {
        if ($remarks !== '') {
            $pdo->prepare("UPDATE repair_requests SET remarks = ? WHERE id = ?")->execute([$remarks, $request_id]);
        }
        echo json_encode(['success' => true, 'message' => 'Remarks saved.', 'deleted' => false]);

    } elseif ($can_transition) {
        if ($new_status === 'Completed') {
            $type         = ($request['issue'] === 'Preventive Maintenance') ? 'Preventive' : 'Repair';
            $finalRemarks = $remarks !== '' ? $remarks : ($request['remarks'] ?? '');
            $pdo->prepare("INSERT INTO maintenance_history (equipment, maintenance_type, status, remarks, completed_at) VALUES (?, ?, 'Completed', ?, NOW())")
                ->execute([$request['equipment'], $type, $finalRemarks]);
            $pdo->prepare("DELETE FROM repair_requests WHERE id = ?")->execute([$request_id]);
            echo json_encode(['success' => true, 'message' => 'Marked as completed and archived.', 'deleted' => true]);
        } else {
            $finalRemarks = $remarks !== '' ? $remarks : ($request['remarks'] ?? '');
            $pdo->prepare("UPDATE repair_requests SET status = ?, remarks = ? WHERE id = ?")
                ->execute([$new_status, $finalRemarks, $request_id]);
            echo json_encode(['success' => true, 'message' => 'Status updated to ' . $new_status . '.', 'deleted' => false, 'new_status' => $new_status]);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid status transition.']);
    }
    exit;
}

/* =====================================================
   NON-AJAX POST — schedule / edit / delete
=====================================================*/
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'schedule') {
        $inventory_id    = intval($_POST['inventory_id']);
        $maintenance_day = intval($_POST['maintenance_day']);
        $remarks         = trim($_POST['remarks']);
        $chk = $pdo->prepare("SELECT id FROM maintenance_records WHERE inventory_id = ? LIMIT 1");
        $chk->execute([$inventory_id]);
        if ($inventory_id > 0 && $maintenance_day >= 1 && $maintenance_day <= 31 && !$chk->fetch()) {
            $pdo->prepare("INSERT INTO maintenance_records (inventory_id, maintenance_day, maintenance_type, remarks, created_at) VALUES (?, ?, 'Preventive', ?, NOW())")
                ->execute([$inventory_id, $maintenance_day, $remarks]);
        }
        header("Location: " . $_SERVER['PHP_SELF']); exit;
    }

    if ($action === 'edit_schedule') {
        $pdo->prepare("UPDATE maintenance_records SET maintenance_day = ?, remarks = ? WHERE id = ?")
            ->execute([intval($_POST['maintenance_day']), trim($_POST['remarks']), intval($_POST['schedule_id'])]);
        header("Location: " . $_SERVER['PHP_SELF']); exit;
    }

    if ($action === 'delete_schedule') {
        $pdo->prepare("DELETE FROM maintenance_records WHERE id = ?")->execute([intval($_POST['schedule_id'])]);
        header("Location: " . $_SERVER['PHP_SELF']); exit;
    }
}

/* =====================================================
   DATA FETCH
=====================================================*/

/*
 * ── ALL inventory items, grouped by item_type for the schedule dropdown.
 *    Changed from: WHERE item_type = 'Diagnostic Equipment'
 *    to: all items, ordered by type then name so the <optgroup> grouping works.
 */
$stmt = $pdo->prepare("
    SELECT MIN(id) AS id, item_name, item_type
    FROM inventory
    GROUP BY item_name, item_type
    ORDER BY item_type ASC, item_name ASC
");
$stmt->execute();
$equipment = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare("SELECT mr.id, mr.inventory_id, mr.maintenance_day, mr.remarks, i.item_name, GROUP_CONCAT(DISTINCT da.department SEPARATOR ', ') AS location FROM maintenance_records mr JOIN inventory i ON mr.inventory_id = i.id LEFT JOIN department_assets da ON i.item_id = da.item_id GROUP BY mr.id, mr.inventory_id, mr.maintenance_day, mr.remarks, i.item_name ORDER BY mr.maintenance_day ASC");
$stmt->execute();
$schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($schedules as $due) {
    if ($due['maintenance_day'] == $today_day) {
        $tn = "MAINT-{$due['id']}-{$today_year}-{$today_month}";
        $chk = $pdo->prepare("SELECT id FROM repair_requests WHERE ticket_no = ? LIMIT 1");
        $chk->execute([$tn]);
        if (!$chk->fetch()) {
            $pdo->prepare("INSERT INTO repair_requests (ticket_no, user_name, equipment, issue, priority, status, created_at) VALUES (?, 'System', ?, 'Preventive Maintenance', 'Medium', 'Open', NOW())")
                ->execute([$tn, $due['item_name']]);
        }
    }
}

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
function statusBadgeHtml(string $status): string {
    $key  = strtolower(str_replace(' ', '-', $status));
    $icon = match($status) {
        'Open'        => 'bi-circle-fill',
        'In Progress' => 'bi-arrow-repeat',
        default       => 'bi-check-circle-fill',
    };
    return "<span class=\"status-badge {$key}\"><i class=\"bi {$icon}\" style=\"font-size:.62rem;\"></i> " . htmlspecialchars($status) . "</span>";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Maintenance Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root {
            --sidebar-w:    250px;
            --sidebar-w-md: 200px;
            --primary:  #2563eb; --success: #16a34a;
            --warning:  #d97706; --danger:  #dc2626;
            --gray-50:  #f8fafc; --gray-100: #f1f5f9;
            --gray-200: #e2e8f0; --gray-400: #94a3b8;
            --gray-600: #475569; --gray-800: #1e293b;
            --surface:  #F5F6F7;
            --radius: 10px;
            --shadow:    0 1px 4px rgba(0,0,0,.08);
            --shadow-md: 0 4px 16px rgba(0,0,0,.10);
        }
        *, *::before, *::after { box-sizing: border-box; }
        html { scroll-behavior: smooth; }
        body {
            background: var(--surface);
            font-family: 'Segoe UI', system-ui, sans-serif;
            font-size: 14px; color: var(--gray-800);
            min-height: 100vh; overflow-x: hidden;
            margin-left: var(--sidebar-w);
            transition: margin-left 0.3s ease-in-out;
        }
        .main-wrapper { padding: 70px 1.5rem 2.5rem; max-width: 1400px; margin: 0 auto; width: 100%; }

        /* PAGE HEADER */
        .page-header { display:flex; align-items:flex-start; justify-content:space-between; flex-wrap:wrap; gap:.75rem; margin-bottom:1.5rem; }
        .page-header h4 { margin:0; font-weight:700; font-size:clamp(1.05rem,3vw,1.4rem); }
        .page-header p  { margin:.2rem 0 0; color:var(--gray-600); font-size:.82rem; }
        .page-date { font-size:.8rem; color:var(--gray-600); background:#fff; border:1px solid var(--gray-200); border-radius:8px; padding:.4rem .85rem; white-space:nowrap; flex-shrink:0; }

        /* STAT CARDS */
        .stats-grid { display:grid; grid-template-columns:repeat(4,1fr); gap:.85rem; margin-bottom:1.5rem; }
        .stat-card { background:#fff; border-radius:var(--radius); box-shadow:var(--shadow); padding:1.1rem 1.2rem; display:flex; align-items:center; gap:.85rem; border-left:4px solid transparent; transition:box-shadow .2s, transform .2s; }
        .stat-card:hover { box-shadow:var(--shadow-md); transform:translateY(-2px); }
        .stat-card.blue  { border-color:var(--primary); } .stat-card.red   { border-color:var(--danger); }
        .stat-card.amber { border-color:var(--warning); } .stat-card.green { border-color:var(--success); }
        .stat-icon { width:42px; height:42px; border-radius:10px; display:flex; align-items:center; justify-content:center; font-size:1.2rem; flex-shrink:0; }
        .stat-card.blue  .stat-icon { background:#dbeafe; color:var(--primary); }
        .stat-card.red   .stat-icon { background:#fee2e2; color:var(--danger); }
        .stat-card.amber .stat-icon { background:#fef3c7; color:var(--warning); }
        .stat-card.green .stat-icon { background:#dcfce7; color:var(--success); }
        .stat-value { font-size:1.5rem; font-weight:700; line-height:1; }
        .stat-label { font-size:.74rem; color:var(--gray-600); margin-top:3px; }

        /* MAIN CARD & TABS */
        .page-card { background:#fff; border-radius:var(--radius); box-shadow:var(--shadow); overflow:hidden; }
        .page-card-header { padding:0 1.25rem; border-bottom:1px solid var(--gray-200); background:var(--gray-50); overflow-x:auto; scrollbar-width:none; }
        .page-card-header::-webkit-scrollbar { display:none; }
        .custom-tabs { display:flex; white-space:nowrap; border:none; margin:0; padding:0; }
        .custom-tabs .nav-link { color:var(--gray-600); border:none; border-bottom:2px solid transparent; padding:.8rem 1.1rem; font-size:.85rem; font-weight:500; display:inline-flex; align-items:center; gap:.4rem; transition:color .15s; background:transparent; }
        .custom-tabs .nav-link:hover  { color:var(--primary); }
        .custom-tabs .nav-link.active { color:var(--primary); border-bottom-color:var(--primary); }
        .page-card-body { padding:1.25rem; }
        .custom-pills { display:flex; flex-wrap:wrap; gap:.4rem; margin-bottom:1rem; }
        .custom-pills .nav-link { color:var(--gray-600); font-size:.82rem; font-weight:500; border-radius:20px; padding:.35rem 1rem; background:var(--gray-100); border:none; display:inline-flex; align-items:center; gap:.3rem; transition:background .15s; }
        .custom-pills .nav-link.active { background:var(--primary); color:#fff; }

        /* BADGES */
        .status-badge { display:inline-flex; align-items:center; gap:.3rem; font-size:.73rem; font-weight:600; padding:.28rem .7rem; border-radius:20px; white-space:nowrap; }
        .status-badge.open        { background:#dcfce7; color:#15803d; }
        .status-badge.in-progress { background:#ffedd5; color:#c2410c; }
        .status-badge.completed   { background:#bbf7d0; color:#166534; }
        .priority-badge { display:inline-block; font-size:.7rem; font-weight:600; padding:.22rem .65rem; border-radius:20px; white-space:nowrap; }
        .badge-priority-high   { background:#fee2e2; color:#b91c1c; }
        .badge-priority-medium { background:#fef3c7; color:#92400e; }
        .badge-priority-low    { background:#dcfce7; color:#15803d; }

        /* TABLES */
        .table-scroll { overflow-x:auto; -webkit-overflow-scrolling:touch; }
        .pro-table { width:100%; border-collapse:separate; border-spacing:0; font-size:.84rem; }
        .pro-table thead th { background:var(--gray-50); color:var(--gray-600); font-size:.71rem; font-weight:600; text-transform:uppercase; letter-spacing:.05em; padding:.7rem 1rem; border-bottom:1px solid var(--gray-200); white-space:nowrap; position:sticky; top:0; z-index:1; }
        .pro-table tbody td { padding:.72rem 1rem; border-bottom:1px solid var(--gray-200); vertical-align:middle; }
        .pro-table tbody tr:last-child td { border-bottom:none; }
        .pro-table tbody tr:hover td { background:var(--gray-50); }

        /* INLINE UPDATE CONTROLS */
        .update-row { display:flex; gap:.4rem; align-items:center; flex-wrap:nowrap; }
        .update-row select,
        .update-row input[type=text] { font-size:.82rem; border:1px solid var(--gray-200); border-radius:6px; padding:.3rem .55rem; background:#fff; color:var(--gray-800); transition:border-color .15s; }
        .update-row select:focus,
        .update-row input[type=text]:focus { outline:none; border-color:var(--primary); box-shadow:0 0 0 3px rgba(37,99,235,.1); }
        .update-row select { min-width:130px; }
        .update-row input[type=text] { min-width:140px; flex:1; }

        .btn-save {
            display:inline-flex; align-items:center; gap:.3rem;
            font-size:.8rem; font-weight:600; padding:.32rem .85rem;
            border-radius:6px; background:var(--primary); color:#fff;
            border:none; white-space:nowrap; cursor:pointer;
            transition:background .15s, opacity .15s; flex-shrink:0;
        }
        .btn-save:hover    { background:#1d4ed8; }
        .btn-save:disabled { opacity:.55; cursor:not-allowed; }

        /* Toast */
        .toast-wrap { position:fixed; bottom:1.5rem; right:1.5rem; z-index:9999; display:flex; flex-direction:column; gap:.5rem; pointer-events:none; }
        .toast-msg { background:#1e293b; color:#fff; padding:.65rem 1.1rem; border-radius:8px; font-size:.83rem; font-weight:500; box-shadow:0 4px 16px rgba(0,0,0,.18); opacity:0; transform:translateY(10px); transition:opacity .25s, transform .25s; pointer-events:none; display:flex; align-items:center; gap:.5rem; }
        .toast-msg.show { opacity:1; transform:translateY(0); }
        .toast-msg.ok  { border-left:4px solid #22c55e; }
        .toast-msg.err { border-left:4px solid #ef4444; }

        /* MOBILE CARDS */
        .mobile-cards { display:none; }
        .req-card { background:#fff; border:1px solid var(--gray-200); border-radius:10px; padding:1rem; margin-bottom:.75rem; box-shadow:var(--shadow); }
        .req-card-header { display:flex; align-items:flex-start; justify-content:space-between; gap:.5rem; margin-bottom:.6rem; }
        .req-card-title  { font-size:.88rem; font-weight:600; color:var(--gray-800); margin:0; }
        .req-card-meta   { display:flex; flex-wrap:wrap; gap:.4rem .9rem; margin-bottom:.65rem; }
        .req-card-meta span { font-size:.78rem; color:var(--gray-600); display:inline-flex; align-items:center; gap:.25rem; }
        .req-card-actions { display:flex; flex-direction:column; gap:.5rem; padding-top:.7rem; border-top:1px solid var(--gray-200); }
        .req-card-actions select,
        .req-card-actions input[type=text] { width:100%; font-size:.83rem; border:1px solid var(--gray-200); border-radius:7px; padding:.45rem .7rem; color:var(--gray-800); background:#fff; }
        .req-card-actions select:focus,
        .req-card-actions input[type=text]:focus { outline:none; border-color:var(--primary); box-shadow:0 0 0 3px rgba(37,99,235,.1); }
        .btn-save-full { width:100%; display:flex; align-items:center; justify-content:center; gap:.4rem; font-size:.83rem; font-weight:600; padding:.5rem; border-radius:7px; background:var(--primary); color:#fff; border:none; cursor:pointer; transition:background .15s, opacity .15s; }
        .btn-save-full:hover    { background:#1d4ed8; }
        .btn-save-full:disabled { opacity:.55; cursor:not-allowed; }

        /* SCHEDULE */
        .schedule-form-card { background:var(--gray-50); border:1px solid var(--gray-200); border-radius:var(--radius); padding:1.4rem; }
        .section-title { font-size:.88rem; font-weight:600; margin-bottom:1rem; display:flex; align-items:center; gap:.4rem; }
        .schedule-hide-sm {} .schedule-hide-xs {} .history-hide-sm {} .history-hide-xs {}
        .ticket-no { font-family:'Courier New', monospace; font-size:.77rem; background:var(--gray-100); padding:.15rem .45rem; border-radius:4px; color:var(--gray-600); word-break:break-all; }
        .day-circle { width:34px; height:34px; border-radius:50%; background:var(--primary); color:#fff; display:inline-flex; align-items:center; justify-content:center; font-size:.82rem; font-weight:700; flex-shrink:0; }
        .empty-state { text-align:center; padding:3rem 1rem; color:var(--gray-400); }
        .empty-state i { font-size:2.5rem; margin-bottom:.75rem; display:block; }
        .empty-state p { font-size:.88rem; margin:0; color:var(--gray-600); }
        .modal-content { border-radius:var(--radius); border:none; box-shadow:var(--shadow-md); }
        .modal-header  { padding:.9rem 1.2rem; }
        .modal-body    { padding:1.2rem; }
        .modal-footer  { padding:.8rem 1.2rem; background:var(--gray-50); border-top:1px solid var(--gray-200); }

        @supports (padding: env(safe-area-inset-bottom)) {
            .main-wrapper { padding-bottom: calc(2.5rem + env(safe-area-inset-bottom)); }
        }

        @media (min-width:1400px) { .main-wrapper { padding:75px 2rem 3rem; } }
        @media (max-width:1199px) { .update-row input[type=text] { min-width:110px; } .update-row select { min-width:120px; } }
        @media (max-width:991px) {
            .main-wrapper { padding:68px 1rem 2rem; }
            .stats-grid { grid-template-columns:repeat(2,1fr); gap:.75rem; }
            .desktop-table-requests { display:none !important; }
            .mobile-cards { display:block; }
            .pro-table thead th, .pro-table tbody td { padding:.6rem .75rem; font-size:.8rem; }
            .page-card-body { padding:1rem; }
            .schedule-form-card { padding:1.1rem; }
        }
        @media (max-width:768px) {
            body { margin-left: var(--sidebar-w-md); }
            .main-wrapper { padding:64px 1rem 2rem; }
            .page-header { flex-direction:column; gap:.5rem; }
            .page-date { align-self:flex-start; }
            .stats-grid { grid-template-columns:repeat(2,1fr); gap:.65rem; }
            .stat-card { padding:.85rem 1rem; gap:.7rem; }
            .stat-icon { width:38px; height:38px; font-size:1.1rem; } .stat-value { font-size:1.3rem; }
            .page-card-header { padding:0 .85rem; }
            .custom-tabs .nav-link { padding:.7rem .85rem; font-size:.8rem; }
            .page-card-body { padding:.9rem; }
            .schedule-hide-sm { display:none; } .history-hide-sm { display:none; }
            .schedule-form-card .row > div { flex:0 0 100%; max-width:100%; }
        }
        @media (max-width:480px) {
            body { margin-left: 0; }
            .main-wrapper { padding:58px .65rem 2rem; }
            .page-header h4 { font-size:1rem; } .page-header p { font-size:.76rem; }
            .stats-grid { grid-template-columns:repeat(2,1fr); gap:.5rem; }
            .stat-card { padding:.7rem .8rem; flex-direction:column; align-items:flex-start; gap:.45rem; }
            .stat-icon { width:34px; height:34px; font-size:1rem; } .stat-value { font-size:1.2rem; }
            .page-card-body { padding:.75rem; } .page-card-header { padding:0 .65rem; }
            .custom-tabs .nav-link { padding:.65rem .7rem; font-size:.78rem; gap:.25rem; }
            .custom-tabs .nav-link .tab-text { display:none; }
            .req-card { padding:.85rem; } .schedule-form-card { padding:.85rem; }
            .schedule-hide-xs { display:none; } .history-hide-xs { display:none; }
            .modal-dialog { margin:.5rem; }
            .day-circle { width:30px; height:30px; font-size:.74rem; }
            .custom-pills .nav-link { font-size:.77rem; padding:.3rem .75rem; }
        }
        @media (max-width:380px) {
            .stats-grid { gap:.4rem; } .stat-card { padding:.6rem .7rem; } .stat-value { font-size:1.1rem; }
            .custom-tabs .nav-link { padding:.6rem .55rem; font-size:.73rem; }
        }
        @media print {
            .sidebar, .custom-tabs, .update-row, .req-card-actions, .btn-save, .btn-save-full { display:none !important; }
            .page-card { box-shadow:none; border:1px solid #ccc; }
            .pro-table thead th { background:#f0f0f0 !important; }
        }
    </style>
</head>
<body>

<?php include 'inventory_sidebar.php'; ?>

<div class="main-wrapper">

    <div class="page-header">
        <div>
            <h4><i class="bi bi-tools me-2 text-primary"></i>Maintenance Management</h4>
            <p>Manage repair requests, preventive schedules, and maintenance history</p>
        </div>
        <div class="page-date"><i class="bi bi-calendar3 me-1"></i><?= date('F d, Y') ?></div>
    </div>

    <div class="stats-grid">
        <div class="stat-card blue">
            <div class="stat-icon"><i class="bi bi-clipboard-pulse"></i></div>
            <div><div class="stat-value" id="statTotal"><?= $total_requests ?></div><div class="stat-label">Total Requests</div></div>
        </div>
        <div class="stat-card red">
            <div class="stat-icon"><i class="bi bi-exclamation-circle"></i></div>
            <div><div class="stat-value" id="statOpen"><?= $open_count ?></div><div class="stat-label">Open</div></div>
        </div>
        <div class="stat-card amber">
            <div class="stat-icon"><i class="bi bi-arrow-repeat"></i></div>
            <div><div class="stat-value" id="statProg"><?= $progress_count ?></div><div class="stat-label">In Progress</div></div>
        </div>
        <div class="stat-card green">
            <div class="stat-icon"><i class="bi bi-check-circle"></i></div>
            <div><div class="stat-value"><?= $history_count ?></div><div class="stat-label">Completed</div></div>
        </div>
    </div>

    <div class="page-card">
        <div class="page-card-header">
            <ul class="nav custom-tabs">
                <li class="nav-item">
                    <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#requests">
                        <i class="bi bi-wrench-adjustable"></i>
                        <span class="tab-text">Repair Requests</span>
                        <span class="badge rounded-pill bg-danger ms-1" id="openBadge"
                              style="font-size:.62rem;<?= $open_count === 0 ? 'display:none' : '' ?>">
                            <?= $open_count ?>
                        </span>
                    </button>
                </li>
                <li class="nav-item">
                    <button class="nav-link" data-bs-toggle="tab" data-bs-target="#schedule">
                        <i class="bi bi-calendar-check"></i><span class="tab-text">Schedule</span>
                    </button>
                </li>
                <li class="nav-item">
                    <button class="nav-link" data-bs-toggle="tab" data-bs-target="#history">
                        <i class="bi bi-clock-history"></i><span class="tab-text">History</span>
                    </button>
                </li>
            </ul>
        </div>

        <div class="tab-content page-card-body">

            <!-- ══ TAB 1: REPAIR REQUESTS ══ -->
            <div class="tab-pane fade show active" id="requests">

                <!-- DESKTOP TABLE -->
                <div class="table-scroll desktop-table-requests">
                    <table class="pro-table">
                        <thead>
                            <tr>
                                <th>Ticket No.</th><th>Equipment</th><th>Issue / Type</th>
                                <th>Location</th><th>Priority</th><th>Status</th>
                                <th style="min-width:370px;">Update</th>
                            </tr>
                        </thead>
                        <tbody id="requestTableBody">
                        <?php if (empty($requests)): ?>
                            <tr><td colspan="7"><div class="empty-state"><i class="bi bi-inbox"></i><p>No repair requests found.</p></div></td></tr>
                        <?php else: foreach ($requests as $req):
                            $opts = getStatusOptions($req['status']);
                        ?>
                            <tr data-id="<?= intval($req['id']) ?>">
                                <td><span class="ticket-no"><?= htmlspecialchars($req['ticket_no'] ?? 'N/A') ?></span></td>
                                <td><strong><?= htmlspecialchars($req['equipment']) ?></strong></td>
                                <td>
                                    <?php if ($req['issue'] === 'Preventive Maintenance'): ?>
                                        <span class="d-flex align-items-center gap-1"><i class="bi bi-shield-check text-success"></i> Preventive</span>
                                    <?php else: ?><?= htmlspecialchars($req['issue']) ?><?php endif; ?>
                                </td>
                                <td style="color:var(--gray-600);"><i class="bi bi-geo-alt me-1"></i><?= htmlspecialchars($req['location'] ?: 'Unknown') ?></td>
                                <td><span class="priority-badge <?= priorityBadge($req['priority']) ?>"><?= htmlspecialchars($req['priority']) ?></span></td>
                                <td class="status-cell"><?= statusBadgeHtml($req['status']) ?></td>
                                <td>
                                    <div class="update-row"
                                         data-id="<?= intval($req['id']) ?>"
                                         data-current="<?= htmlspecialchars($req['status']) ?>">
                                        <select class="status-select">
                                            <?php foreach ($opts as $opt): ?>
                                                <option value="<?= htmlspecialchars($opt) ?>"
                                                        <?= $opt === $req['status'] ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($opt) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <input type="text" class="remarks-input"
                                               placeholder="Add remarks…"
                                               value="<?= htmlspecialchars($req['remarks'] ?? '') ?>">
                                        <button type="button" class="btn-save btn-update">
                                            <i class="bi bi-check2"></i> Save
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- MOBILE CARDS -->
                <div class="mobile-cards" id="mobileCards">
                    <?php if (empty($requests)): ?>
                        <div class="empty-state"><i class="bi bi-inbox"></i><p>No repair requests found.</p></div>
                    <?php else: foreach ($requests as $req):
                        $opts      = getStatusOptions($req['status']);
                        $statusKey = strtolower(str_replace(' ', '-', $req['status']));
                        $statusIcon = match($req['status']) { 'Open'=>'bi-circle-fill','In Progress'=>'bi-arrow-repeat',default=>'bi-check-circle-fill' };
                    ?>
                        <div class="req-card" data-id="<?= intval($req['id']) ?>">
                            <div class="req-card-header">
                                <div>
                                    <p class="req-card-title"><?= htmlspecialchars($req['equipment']) ?></p>
                                    <span class="ticket-no"><?= htmlspecialchars($req['ticket_no'] ?? 'N/A') ?></span>
                                </div>
                                <span class="status-badge <?= $statusKey ?> mobile-status-badge">
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
                                <span><i class="bi bi-geo-alt"></i><?= htmlspecialchars($req['location'] ?: 'Unknown') ?></span>
                                <span><span class="priority-badge <?= priorityBadge($req['priority']) ?>"><?= htmlspecialchars($req['priority']) ?></span></span>
                            </div>
                            <div class="req-card-actions"
                                 data-id="<?= intval($req['id']) ?>"
                                 data-current="<?= htmlspecialchars($req['status']) ?>">
                                <select class="status-select">
                                    <?php foreach ($opts as $opt): ?>
                                        <option value="<?= htmlspecialchars($opt) ?>"
                                                <?= $opt === $req['status'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($opt) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <input type="text" class="remarks-input"
                                       placeholder="Add remarks…"
                                       value="<?= htmlspecialchars($req['remarks'] ?? '') ?>">
                                <button type="button" class="btn-save-full btn-update">
                                    <i class="bi bi-check2"></i> Save Changes
                                </button>
                            </div>
                        </div>
                    <?php endforeach; endif; ?>
                </div>

            </div><!-- end #requests -->

            <!-- ══ TAB 2: SCHEDULE ══ -->
            <div class="tab-pane fade" id="schedule">
                <ul class="nav custom-pills">
                    <li class="nav-item"><button class="nav-link active" data-bs-toggle="pill" data-bs-target="#setSchedule"><i class="bi bi-plus-circle"></i> Add Schedule</button></li>
                    <li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#listSchedule"><i class="bi bi-list-ul"></i> Scheduled List <span class="badge rounded-pill bg-primary ms-1" style="font-size:.62rem;"><?= count($schedules) ?></span></button></li>
                </ul>
                <div class="tab-content">
                    <div class="tab-pane fade show active" id="setSchedule">
                        <div class="schedule-form-card">
                            <p class="section-title"><i class="bi bi-calendar-plus text-primary"></i> New Preventive Maintenance Schedule</p>
                            <form method="POST" action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>" class="row g-3">
                                <input type="hidden" name="action" value="schedule">
                                <div class="col-12 col-sm-6 col-lg-5">
                                    <label class="form-label fw-semibold" style="font-size:.82rem;">Equipment / Item</label>
                                    <select name="inventory_id" class="form-select form-select-sm" required>
                                        <option value="" disabled selected>— Select item from inventory —</option>
                                        <?php
                                        /*
                                         * Build <optgroup> sections per item_type so the dropdown
                                         * is easy to navigate even with many inventory items.
                                         * Items already scheduled are shown as disabled.
                                         */
                                        $scheduled_ids = array_column($schedules, 'inventory_id');

                                        // Group by item_type
                                        $grouped = [];
                                        foreach ($equipment as $eq) {
                                            $grouped[$eq['item_type']][] = $eq;
                                        }

                                        foreach ($grouped as $type => $items):
                                        ?>
                                            <optgroup label="<?= htmlspecialchars($type) ?>">
                                                <?php foreach ($items as $eq):
                                                    $dis = in_array($eq['id'], $scheduled_ids) ? 'disabled' : '';
                                                ?>
                                                    <option value="<?= $eq['id'] ?>" <?= $dis ?>>
                                                        <?= htmlspecialchars($eq['item_name']) ?><?= $dis ? ' — Already Scheduled' : '' ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </optgroup>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="form-text" style="font-size:.72rem;">All inventory items listed, grouped by type.</div>
                                </div>
                                <div class="col-12 col-sm-6 col-lg-3">
                                    <label class="form-label fw-semibold" style="font-size:.82rem;">Day of Month</label>
                                    <input type="number" name="maintenance_day" class="form-control form-control-sm" min="1" max="31" placeholder="1–31" required>
                                    <div class="form-text" style="font-size:.72rem;">Auto-ticket created on this day each month.</div>
                                </div>
                                <div class="col-12 col-lg-4">
                                    <label class="form-label fw-semibold" style="font-size:.82rem;">Remarks</label>
                                    <input type="text" name="remarks" class="form-control form-control-sm" placeholder="Optional notes…">
                                </div>
                                <div class="col-12">
                                    <button type="submit" class="btn btn-primary btn-sm px-4"><i class="bi bi-save me-1"></i> Save Schedule</button>
                                </div>
                            </form>
                        </div>
                    </div>
                    <div class="tab-pane fade" id="listSchedule">
                        <div class="table-scroll">
                            <table class="pro-table">
                                <thead><tr><th>Equipment</th><th>Day</th><th class="schedule-hide-sm">Remarks</th><th class="schedule-hide-xs">Location</th><th>Actions</th></tr></thead>
                                <tbody>
                                <?php if (empty($schedules)): ?>
                                    <tr><td colspan="5"><div class="empty-state"><i class="bi bi-calendar-x"></i><p>No schedules found.</p></div></td></tr>
                                <?php else: foreach ($schedules as $s): $isToday = ($s['maintenance_day'] == $today_day); ?>
                                    <tr <?= $isToday ? 'style="background:#eff6ff;"' : '' ?>>
                                        <td><strong style="font-size:.84rem;"><?= htmlspecialchars($s['item_name']) ?></strong><?php if ($isToday): ?> <span class="badge bg-primary ms-1" style="font-size:.61rem;">Today</span><?php endif; ?></td>
                                        <td><span class="day-circle"><?= $s['maintenance_day'] ?></span></td>
                                        <td class="schedule-hide-sm" style="color:var(--gray-600);font-size:.82rem;"><?= $s['remarks'] ? htmlspecialchars($s['remarks']) : '<span class="text-muted">—</span>' ?></td>
                                        <td class="schedule-hide-xs" style="color:var(--gray-600);font-size:.82rem;"><i class="bi bi-geo-alt me-1"></i><?= htmlspecialchars($s['location'] ?: 'Unknown') ?></td>
                                        <td>
                                            <div class="d-flex gap-1 flex-wrap">
                                                <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editModal<?= $s['id'] ?>" style="font-size:.76rem;padding:.25rem .6rem;"><i class="bi bi-pencil"></i><span class="d-none d-md-inline ms-1">Edit</span></button>
                                                <button class="btn btn-sm btn-outline-danger"  data-bs-toggle="modal" data-bs-target="#deleteModal<?= $s['id'] ?>" style="font-size:.76rem;padding:.25rem .6rem;"><i class="bi bi-trash"></i><span class="d-none d-md-inline ms-1">Delete</span></button>
                                            </div>
                                        </td>
                                    </tr>
                                    <!-- Edit Modal -->
                                    <div class="modal fade" id="editModal<?= $s['id'] ?>" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><form method="POST" action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>"><input type="hidden" name="action" value="edit_schedule"><input type="hidden" name="schedule_id" value="<?= $s['id'] ?>"><div class="modal-content"><div class="modal-header"><h6 class="modal-title fw-bold"><i class="bi bi-pencil-square me-2 text-primary"></i>Edit Schedule</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><p class="text-muted mb-3" style="font-size:.82rem;"><i class="bi bi-box-seam me-1"></i><?= htmlspecialchars($s['item_name']) ?></p><div class="mb-3"><label class="form-label fw-semibold" style="font-size:.82rem;">Maintenance Day</label><input type="number" name="maintenance_day" class="form-control form-control-sm" min="1" max="31" value="<?= $s['maintenance_day'] ?>" required></div><div class="mb-2"><label class="form-label fw-semibold" style="font-size:.82rem;">Remarks</label><textarea name="remarks" class="form-control form-control-sm" rows="3"><?= htmlspecialchars($s['remarks']) ?></textarea></div></div><div class="modal-footer"><button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-sm btn-primary"><i class="bi bi-save me-1"></i>Save Changes</button></div></div></form></div></div>
                                    <!-- Delete Modal -->
                                    <div class="modal fade" id="deleteModal<?= $s['id'] ?>" tabindex="-1"><div class="modal-dialog modal-dialog-centered modal-sm"><form method="POST" action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>"><input type="hidden" name="action" value="delete_schedule"><input type="hidden" name="schedule_id" value="<?= $s['id'] ?>"><div class="modal-content"><div class="modal-header bg-danger text-white py-2"><h6 class="modal-title fw-bold mb-0"><i class="bi bi-trash me-2"></i>Delete Schedule</h6><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div><div class="modal-body text-center py-3"><i class="bi bi-exclamation-triangle-fill text-danger" style="font-size:2rem;"></i><p class="mt-2 mb-0" style="font-size:.86rem;">Remove schedule for<br><strong><?= htmlspecialchars($s['item_name']) ?></strong>?</p></div><div class="modal-footer justify-content-center py-2"><button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-sm btn-danger"><i class="bi bi-trash me-1"></i>Delete</button></div></div></form></div></div>
                                <?php endforeach; endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div><!-- end #schedule -->

            <!-- ══ TAB 3: HISTORY ══ -->
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
                                <td><?php if ($h['maintenance_type'] === 'Preventive'): ?><span style="font-size:.82rem;display:flex;align-items:center;gap:.25rem;"><i class="bi bi-shield-check text-success"></i> Preventive</span><?php else: ?><span style="font-size:.82rem;display:flex;align-items:center;gap:.25rem;"><i class="bi bi-wrench text-primary"></i> Repair</span><?php endif; ?></td>
                                <td><span class="status-badge completed"><i class="bi bi-check-circle-fill" style="font-size:.6rem;"></i> Completed</span></td>
                                <td class="history-hide-sm" style="font-size:.82rem;color:var(--gray-600);"><?= $h['remarks'] ? htmlspecialchars($h['remarks']) : '<span class="text-muted">—</span>' ?></td>
                                <td class="history-hide-xs" style="font-size:.81rem;color:var(--gray-600);white-space:nowrap;"><i class="bi bi-clock me-1"></i><?= date('M d, Y — h:i A', strtotime($h['completed_at'])) ?></td>
                            </tr>
                        <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div><!-- end #history -->

        </div><!-- tab-content -->
    </div><!-- page-card -->
</div><!-- main-wrapper -->

<!-- Toast container -->
<div class="toast-wrap" id="toastWrap"></div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
/* ── Sidebar margin sync ── */
(function () {
    const sb = document.querySelector('.sidebar');
    if (!sb) return;
    function w() { return window.innerWidth <= 480 ? 0 : window.innerWidth <= 768 ? 200 : 250; }
    function apply() { document.body.style.marginLeft = (sb.classList.contains('closed') || sb.style.display === 'none' ? 0 : w()) + 'px'; }
    apply();
    new MutationObserver(apply).observe(sb, { attributes: true, attributeFilter: ['class', 'style'] });
    window.addEventListener('resize', apply);
})();

/* ── Toast helper ── */
function showToast(msg, type = 'ok') {
    const wrap  = document.getElementById('toastWrap');
    const el    = document.createElement('div');
    el.className = `toast-msg ${type}`;
    el.innerHTML = `<i class="bi ${type === 'ok' ? 'bi-check-circle-fill' : 'bi-x-circle-fill'}"></i> ${msg}`;
    wrap.appendChild(el);
    requestAnimationFrame(() => requestAnimationFrame(() => el.classList.add('show')));
    setTimeout(() => { el.classList.remove('show'); setTimeout(() => el.remove(), 300); }, 3500);
}

/* ── Stat helpers ── */
function adjustStat(id, delta) {
    const el = document.getElementById(id);
    if (el) el.textContent = Math.max(0, (parseInt(el.textContent) || 0) + delta);
}
function refreshBadge() {
    const badge = document.getElementById('openBadge');
    const n     = parseInt(document.getElementById('statOpen').textContent) || 0;
    badge.textContent  = n;
    badge.style.display = n > 0 ? '' : 'none';
}

/* ── JS mirror of PHP statusBadgeHtml() ── */
function makeBadge(status) {
    const key  = status.toLowerCase().replace(' ', '-');
    const icons = { 'Open':'bi-circle-fill', 'In Progress':'bi-arrow-repeat', 'Completed':'bi-check-circle-fill' };
    return `<span class="status-badge ${key}"><i class="bi ${icons[status]||'bi-circle'}" style="font-size:.62rem;"></i> ${status}</span>`;
}

/* ── AJAX UPDATE ── */
document.addEventListener('click', function (e) {
    const btn = e.target.closest('.btn-update');
    if (!btn) return;

    const wrap      = btn.closest('[data-id]');
    const id        = wrap.dataset.id;
    const oldStatus = wrap.dataset.current;
    const newStatus = wrap.querySelector('.status-select').value;
    const remarks   = wrap.querySelector('.remarks-input').value.trim();

    btn.disabled = true;
    const origHtml = btn.innerHTML;
    btn.innerHTML = '<i class="bi bi-hourglass-split"></i> Saving…';

    fetch(window.location.pathname, {
        method:  'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
        body:    new URLSearchParams({ action: 'update_request', request_id: id, status: newStatus, remarks }).toString(),
    })
    .then(r => r.json())
    .then(data => {
        if (!data.success) {
            showToast(data.message || 'Update failed.', 'err');
            btn.disabled = false; btn.innerHTML = origHtml; return;
        }
        showToast(data.message, 'ok');

        if (data.deleted) {
            document.querySelector(`#requestTableBody tr[data-id="${id}"]`)?.remove();
            document.querySelector(`#mobileCards .req-card[data-id="${id}"]`)?.remove();

            adjustStat('statTotal', -1);
            if (oldStatus === 'Open')        adjustStat('statOpen', -1);
            if (oldStatus === 'In Progress') adjustStat('statProg', -1);
            refreshBadge();

            if (!document.querySelector('#requestTableBody tr[data-id]')) {
                const emptyHtml = `<div class="empty-state"><i class="bi bi-inbox"></i><p>No repair requests found.</p></div>`;
                document.getElementById('requestTableBody').innerHTML = `<tr><td colspan="7">${emptyHtml}</td></tr>`;
                document.getElementById('mobileCards').innerHTML = emptyHtml;
            }
        } else {
            const tr = document.querySelector(`#requestTableBody tr[data-id="${id}"]`);
            if (tr) tr.querySelector('.status-cell').innerHTML = makeBadge(newStatus);

            const card = document.querySelector(`#mobileCards .req-card[data-id="${id}"]`);
            if (card) {
                const mb = card.querySelector('.mobile-status-badge');
                if (mb) {
                    const key  = newStatus.toLowerCase().replace(' ', '-');
                    const icon = newStatus === 'Open' ? 'bi-circle-fill' : 'bi-arrow-repeat';
                    mb.className = `status-badge ${key} mobile-status-badge`;
                    mb.innerHTML = `<i class="bi ${icon}" style="font-size:.6rem;"></i> ${newStatus}`;
                }
            }

            if (oldStatus !== newStatus) {
                if (oldStatus === 'Open')        adjustStat('statOpen', -1);
                if (oldStatus === 'In Progress') adjustStat('statProg', -1);
                if (newStatus === 'Open')        adjustStat('statOpen', +1);
                if (newStatus === 'In Progress') adjustStat('statProg', +1);
                refreshBadge();
            }

            wrap.dataset.current = newStatus;
            btn.disabled = false;
            btn.innerHTML = origHtml;
        }
    })
    .catch(() => {
        showToast('Network error. Please try again.', 'err');
        btn.disabled = false; btn.innerHTML = origHtml;
    });
});
</script>
</body>
</html>