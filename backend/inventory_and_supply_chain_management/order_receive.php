<?php
include '../../SQL/config.php';

/* =====================================================
   HANDLE RECEIVING ACTION
=====================================================*/
if (isset($_POST['action']) && $_POST['action'] === 'receive') {

    $request_id     = $_POST['id'];
    $received_items = $_POST['received_qty'] ?? [];

    $stmt = $pdo->prepare("SELECT * FROM department_request WHERE id=? LIMIT 1");
    $stmt->execute([$request_id]);
    $request = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$request || !$request['purchased_at']) {
        header("Location: order_receive.php");
        exit;
    }

    $stmtItems = $pdo->prepare("SELECT * FROM department_request_items WHERE request_id=?");
    $stmtItems->execute([$request_id]);
    $items = $stmtItems->fetchAll(PDO::FETCH_ASSOC);

    foreach ($items as $item) {
        $item_id       = $item['id'];
        $approved_qty  = (int)$item['approved_quantity'];
        $prev_received = (int)($item['received_quantity'] ?? 0);
        $received_qty  = isset($received_items[$item_id]) ? (int)$received_items[$item_id] : 0;
        $remaining     = $approved_qty - $prev_received;

        if ($received_qty <= 0) continue;
        if ($received_qty > $remaining) continue;

        $unit_type   = $item['unit'] ?? 'pcs';
        $pcs_per_box = $item['pcs_per_box'] ?? 1;
        $price       = $item['price'] ?? 0;

        $total_qty = (strtolower($unit_type) === 'box')
            ? $received_qty * $pcs_per_box
            : $received_qty;

        $stmtCheck = $pdo->prepare("SELECT * FROM inventory WHERE item_name=? LIMIT 1");
        $stmtCheck->execute([$item['item_name']]);
        $existing = $stmtCheck->fetch(PDO::FETCH_ASSOC);

        if ($existing) {
            $stmtUpdate = $pdo->prepare(
                "UPDATE inventory SET quantity = quantity + ?, total_qty = total_qty + ?, price = ? WHERE id=?"
            );
            $stmtUpdate->execute([$received_qty, $total_qty, $price, $existing['id']]);
        } else {
            // Get next available ID to handle tables without AUTO_INCREMENT
            $stmtMaxId = $pdo->query("SELECT COALESCE(MAX(id), 0) + 1 FROM inventory");
            $nextId    = (int)$stmtMaxId->fetchColumn();

            $stmtInsert = $pdo->prepare(
                "INSERT INTO inventory (id, item_id, item_name, item_type, category, sub_type, quantity, total_qty, price, unit_type, pcs_per_box, received_at, location)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?)"
            );
            $stmtInsert->execute([
                $nextId,
                $item_id, $item['item_name'], $item['item_type'] ?? 'Supply',
                $item['category'] ?? '', $item['sub_type'] ?? '',
                $received_qty, $total_qty, $price, ucfirst($unit_type),
                $pcs_per_box, 'Main Storage'
            ]);
        }

        $new_received = $prev_received + $received_qty;
        $stmtUpdateItem = $pdo->prepare("UPDATE department_request_items SET received_quantity=? WHERE id=?");
        $stmtUpdateItem->execute([$new_received, $item_id]);
    }

    $stmtCheckAll = $pdo->prepare(
        "SELECT approved_quantity, received_quantity FROM department_request_items WHERE request_id=? AND approved_quantity > 0"
    );
    $stmtCheckAll->execute([$request_id]);
    $checkItems = $stmtCheckAll->fetchAll(PDO::FETCH_ASSOC);

    $all_completed = true;
    foreach ($checkItems as $ci) {
        if ((int)$ci['received_quantity'] !== (int)$ci['approved_quantity']) {
            $all_completed = false;
            break;
        }
    }

    if ($all_completed && count($checkItems) > 0) {
        $stmtDone = $pdo->prepare("UPDATE department_request SET status='Completed', delivered_at=NOW() WHERE id=?");
        $stmtDone->execute([$request_id]);
    } else {
        $stmtReceiving = $pdo->prepare("UPDATE department_request SET status='Receiving' WHERE id=?");
        $stmtReceiving->execute([$request_id]);
    }

    header("Location: order_receive.php");
    exit;
}

/* =====================================================
   FETCH PURCHASED REQUESTS
=====================================================*/
$statusFilter = $_GET['status'] ?? 'Pending';
$searchDept   = $_GET['search_dept'] ?? '';

$query  = "SELECT * FROM department_request WHERE 1=1";
$params = [];

if ($statusFilter === 'Pending') {
    $query .= " AND purchased_at IS NOT NULL AND status IN ('Purchased','Receiving')";
} elseif ($statusFilter === 'Completed') {
    $query .= " AND status = 'Completed'";
} elseif ($statusFilter === 'All') {
    $query .= " AND purchased_at IS NOT NULL";
}

if (!empty($searchDept)) {
    $query .= " AND department LIKE ?";
    $params[] = "%$searchDept%";
}

$query .= " ORDER BY created_at DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
if (!$requests) $requests = [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Department Receiving — Inventory</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;1,9..40,400&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">

<style>
:root {
    --bg:          #f0f2f7;
    --surface:     #ffffff;
    --surface-2:   #f7f8fc;
    --border:      #e4e7ef;
    --border-dark: #d0d4e0;
    --text-primary:   #141824;
    --text-secondary: #5a6079;
    --text-muted:     #9ba3bd;
    --accent:      #2563eb;
    --accent-light: #eff4ff;
    --accent-mid:  #93b4fb;
    --success:     #16a34a;
    --success-light: #f0fdf4;
    --success-mid: #86efac;
    --warning:     #d97706;
    --warning-light: #fffbeb;
    --warning-mid: #fcd34d;
    --danger:      #dc2626;
    --info:        #0891b2;
    --info-light:  #ecfeff;
    --radius-sm:   6px;
    --radius:      10px;
    --radius-lg:   16px;
    --shadow-sm:   0 1px 3px rgba(0,0,0,.06), 0 1px 2px rgba(0,0,0,.04);
    --shadow:      0 4px 16px rgba(0,0,0,.07), 0 1px 4px rgba(0,0,0,.04);
    --shadow-lg:   0 12px 40px rgba(0,0,0,.10), 0 4px 12px rgba(0,0,0,.06);
}

*, *::before, *::after { box-sizing: border-box; }

body {
    background: var(--bg);
    font-family: 'DM Sans', sans-serif;
    color: var(--text-primary);
    font-size: 14px;
    line-height: 1.6;
    -webkit-font-smoothing: antialiased;
}

/* ── SIDEBAR (pass-through) ── */
.main-sidebar { position: fixed; left: 0; top: 0; bottom: 0; width: 260px; z-index: 100; }

/* ── LAYOUT ── */
.main-content {
    margin-left: 260px;
    padding: 36px 36px 60px;
    min-height: 100vh;
}

/* ── PAGE HEADER ── */
.page-header {
    display: flex;
    align-items: flex-end;
    justify-content: space-between;
    margin-bottom: 28px;
    padding-bottom: 20px;
    border-bottom: 1px solid var(--border);
}
.page-header-left .page-eyebrow {
    font-size: 11px;
    font-weight: 600;
    letter-spacing: .08em;
    text-transform: uppercase;
    color: var(--accent);
    margin-bottom: 4px;
}
.page-header-left h1 {
    font-size: 24px;
    font-weight: 700;
    color: var(--text-primary);
    margin: 0;
    display: flex;
    align-items: center;
    gap: 10px;
}
.page-header-left h1 .page-icon {
    width: 38px; height: 38px;
    background: var(--accent-light);
    border-radius: var(--radius);
    display: flex; align-items: center; justify-content: center;
    color: var(--accent);
    font-size: 18px;
    flex-shrink: 0;
}

/* ── STATS BAR ── */
.stats-bar {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 16px;
    margin-bottom: 24px;
}
.stat-card {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    padding: 16px 20px;
    display: flex;
    align-items: center;
    gap: 14px;
    box-shadow: var(--shadow-sm);
    transition: box-shadow .2s;
}
.stat-card:hover { box-shadow: var(--shadow); }
.stat-icon {
    width: 42px; height: 42px;
    border-radius: var(--radius-sm);
    display: flex; align-items: center; justify-content: center;
    font-size: 19px;
    flex-shrink: 0;
}
.stat-icon.blue   { background: var(--accent-light); color: var(--accent); }
.stat-icon.green  { background: var(--success-light); color: var(--success); }
.stat-icon.yellow { background: var(--warning-light); color: var(--warning); }
.stat-label { font-size: 11.5px; color: var(--text-muted); font-weight: 500; letter-spacing: .03em; }
.stat-value { font-size: 22px; font-weight: 700; line-height: 1.2; color: var(--text-primary); }

/* ── FILTER CARD ── */
.filter-card {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    padding: 18px 22px;
    margin-bottom: 20px;
    box-shadow: var(--shadow-sm);
}
.filter-card .form-label {
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: .06em;
    color: var(--text-muted);
    margin-bottom: 6px;
}
.form-control, .form-select {
    font-family: 'DM Sans', sans-serif;
    font-size: 13.5px;
    border: 1px solid var(--border);
    border-radius: var(--radius-sm);
    background: var(--surface-2);
    color: var(--text-primary);
    padding: 8px 12px;
    height: 38px;
    transition: border-color .2s, box-shadow .2s;
}
.form-control:focus, .form-select:focus {
    border-color: var(--accent);
    box-shadow: 0 0 0 3px rgba(37,99,235,.1);
    background: var(--surface);
    outline: none;
}

/* Tab-style status filter */
.status-tabs { display: flex; gap: 4px; }
.status-tab {
    padding: 7px 16px;
    font-size: 13px;
    font-weight: 500;
    border-radius: var(--radius-sm);
    border: 1px solid var(--border);
    background: var(--surface-2);
    color: var(--text-secondary);
    cursor: pointer;
    text-decoration: none;
    transition: all .15s;
    display: flex; align-items: center; gap: 6px;
}
.status-tab:hover { background: var(--accent-light); color: var(--accent); border-color: var(--accent-mid); }
.status-tab.active { background: var(--accent); color: #fff; border-color: var(--accent); }

/* ── TABLE CARD ── */
.table-card {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
    box-shadow: var(--shadow);
    overflow: hidden;
}
.table-card-header {
    padding: 16px 22px;
    border-bottom: 1px solid var(--border);
    display: flex;
    align-items: center;
    justify-content: space-between;
    background: var(--surface);
}
.table-card-header h6 {
    font-size: 13px;
    font-weight: 600;
    color: var(--text-secondary);
    margin: 0;
    display: flex; align-items: center; gap: 7px;
}
.record-count {
    font-size: 12px;
    background: var(--accent-light);
    color: var(--accent);
    border-radius: 20px;
    padding: 2px 10px;
    font-weight: 600;
}

/* TABLE */
.data-table { width: 100%; border-collapse: collapse; }
.data-table thead tr { background: var(--surface-2); }
.data-table thead th {
    padding: 12px 16px;
    font-size: 11px;
    font-weight: 600;
    letter-spacing: .07em;
    text-transform: uppercase;
    color: var(--text-muted);
    border-bottom: 1px solid var(--border);
    white-space: nowrap;
    text-align: left;
}
.data-table thead th.center { text-align: center; }
.data-table tbody tr {
    border-bottom: 1px solid var(--border);
    transition: background .15s;
}
.data-table tbody tr:last-child { border-bottom: none; }
.data-table tbody tr:hover { background: #f7f9ff; }
.data-table tbody tr.is-completed { background: #f6fef9; }
.data-table tbody tr.is-completed:hover { background: #edfaf3; }
.data-table td {
    padding: 13px 16px;
    font-size: 13.5px;
    color: var(--text-primary);
    vertical-align: middle;
}
.data-table td.center { text-align: center; }

/* ID badge */
.id-badge {
    font-family: 'DM Mono', monospace;
    font-size: 12px;
    font-weight: 500;
    color: var(--text-secondary);
    background: var(--surface-2);
    border: 1px solid var(--border);
    border-radius: var(--radius-sm);
    padding: 3px 8px;
    display: inline-block;
}

/* Department */
.dept-cell { font-weight: 600; color: var(--text-primary); }
.dept-sub  { font-size: 12px; color: var(--text-muted); font-weight: 400; }

/* Status badges */
.status-badge {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 4px 11px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
    letter-spacing: .02em;
    white-space: nowrap;
}
.badge-completed { background: var(--success-light); color: var(--success); }
.badge-partial   { background: var(--warning-light); color: var(--warning); }
.badge-ready     { background: var(--accent-light);  color: var(--accent);  }

/* Progress */
.progress-wrap { min-width: 150px; }
.progress-track {
    height: 6px;
    background: var(--border);
    border-radius: 99px;
    overflow: hidden;
    margin-bottom: 5px;
}
.progress-fill {
    height: 100%;
    border-radius: 99px;
    background: var(--accent);
    transition: width .4s ease;
}
.progress-fill.done { background: var(--success); }
.progress-label {
    font-size: 11px;
    color: var(--text-muted);
    font-family: 'DM Mono', monospace;
    display: flex;
    justify-content: space-between;
}
.progress-label span { font-weight: 600; color: var(--text-secondary); }

/* Dates */
.date-cell { font-size: 12.5px; color: var(--text-secondary); white-space: nowrap; }
.date-cell strong { display: block; font-size: 13px; font-weight: 600; color: var(--text-primary); }
.date-cell.done strong { color: var(--success); }

/* Actions */
.actions-cell { display: flex; gap: 6px; justify-content: center; align-items: center; flex-wrap: nowrap; }
.btn-action {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 6px 13px;
    font-size: 12.5px;
    font-weight: 500;
    border-radius: var(--radius-sm);
    border: 1px solid;
    cursor: pointer;
    text-decoration: none;
    transition: all .15s;
    white-space: nowrap;
    font-family: 'DM Sans', sans-serif;
}
.btn-receipt { background: var(--info-light); color: var(--info); border-color: #a5f3fc; }
.btn-receipt:hover { background: var(--info); color: #fff; border-color: var(--info); }
.btn-receive { background: var(--success-light); color: var(--success); border-color: var(--success-mid); }
.btn-receive:hover { background: var(--success); color: #fff; border-color: var(--success); }
.btn-done { background: var(--surface-2); color: var(--text-muted); border-color: var(--border); cursor: not-allowed; }

/* Empty state */
.empty-state {
    text-align: center;
    padding: 64px 24px;
    color: var(--text-muted);
}
.empty-state .empty-icon {
    width: 64px; height: 64px;
    background: var(--surface-2);
    border-radius: 50%;
    display: inline-flex; align-items: center; justify-content: center;
    font-size: 28px;
    margin-bottom: 16px;
    border: 1px solid var(--border);
}
.empty-state p { font-size: 14px; margin: 0; }

/* ── MODAL ── */
.modal-content {
    border: none;
    border-radius: var(--radius-lg);
    box-shadow: var(--shadow-lg);
    overflow: hidden;
    font-family: 'DM Sans', sans-serif;
}
.modal-header {
    background: linear-gradient(135deg, #16a34a, #15803d);
    padding: 20px 24px;
    border: none;
}
.modal-title {
    font-size: 16px;
    font-weight: 700;
    color: #fff;
    display: flex; align-items: center; gap: 8px;
}
.modal-body { padding: 24px; background: var(--surface); }
.modal-alert {
    background: var(--accent-light);
    border: 1px solid var(--accent-mid);
    border-radius: var(--radius);
    padding: 12px 16px;
    font-size: 13px;
    color: var(--accent);
    margin-bottom: 20px;
    display: flex; gap: 10px; align-items: flex-start;
}
.modal-alert i { flex-shrink: 0; font-size: 15px; margin-top: 1px; }

/* Modal table */
.modal-table { width: 100%; border-collapse: collapse; }
.modal-table thead th {
    font-size: 11px;
    font-weight: 600;
    letter-spacing: .07em;
    text-transform: uppercase;
    color: var(--text-muted);
    padding: 10px 12px;
    border-bottom: 2px solid var(--border);
    text-align: left;
}
.modal-table tbody tr { border-bottom: 1px solid var(--border); }
.modal-table tbody tr:last-child { border-bottom: none; }
.modal-table tbody tr.row-done { opacity: .55; }
.modal-table td {
    padding: 11px 12px;
    font-size: 13.5px;
    vertical-align: middle;
    color: var(--text-primary);
}
.modal-table .item-name { font-weight: 600; }
.modal-table .remaining-qty { font-weight: 700; color: var(--danger); font-family: 'DM Mono', monospace; }
.modal-table .done-check { color: var(--success); font-size: 15px; }
.qty-input-modal {
    width: 90px;
    font-family: 'DM Mono', monospace;
    font-size: 14px;
    font-weight: 500;
    text-align: center;
    border: 1.5px solid var(--border);
    border-radius: var(--radius-sm);
    padding: 6px 8px;
    background: var(--surface-2);
    color: var(--text-primary);
    transition: border-color .2s, box-shadow .2s;
}
.qty-input-modal:focus {
    border-color: var(--success);
    box-shadow: 0 0 0 3px rgba(22,163,74,.1);
    outline: none;
    background: var(--surface);
}
.modal-footer-area {
    margin-top: 22px;
    padding-top: 18px;
    border-top: 1px solid var(--border);
    display: flex;
    justify-content: flex-end;
    gap: 10px;
}
.btn-modal-cancel {
    padding: 9px 20px;
    border: 1px solid var(--border);
    border-radius: var(--radius-sm);
    background: var(--surface-2);
    color: var(--text-secondary);
    font-size: 13.5px;
    font-weight: 500;
    font-family: 'DM Sans', sans-serif;
    cursor: pointer;
    transition: all .15s;
}
.btn-modal-cancel:hover { background: var(--border); }
.btn-modal-submit {
    padding: 9px 22px;
    border: none;
    border-radius: var(--radius-sm);
    background: var(--success);
    color: #fff;
    font-size: 13.5px;
    font-weight: 600;
    font-family: 'DM Sans', sans-serif;
    cursor: pointer;
    display: inline-flex; align-items: center; gap: 7px;
    transition: all .15s;
    box-shadow: 0 2px 8px rgba(22,163,74,.25);
}
.btn-modal-submit:hover { background: #15803d; transform: translateY(-1px); box-shadow: 0 4px 14px rgba(22,163,74,.35); }

/* Animate rows in */
@keyframes fadeSlideIn {
    from { opacity: 0; transform: translateY(6px); }
    to   { opacity: 1; transform: translateY(0); }
}
.data-table tbody tr {
    animation: fadeSlideIn .25s ease both;
}
<?php foreach(range(1,20) as $i): ?>
.data-table tbody tr:nth-child(<?= $i ?>) { animation-delay: <?= ($i-1)*0.03 ?>s; }
<?php endforeach; ?>
</style>
</head>
<body>

<div class="main-sidebar"><?php include 'inventory_sidebar.php'; ?></div>

<div class="main-content">

    <?php
    /* Pre-compute stats */
    $totalPending   = 0;
    $totalCompleted = 0;
    $totalReceiving = 0;
    foreach ($requests as $r) {
        $s = strtolower(trim($r['status']));
        if ($s === 'completed')  $totalCompleted++;
        elseif ($s === 'receiving') $totalReceiving++;
        else $totalPending++;
    }
    ?>

    <!-- Page Header -->
    <div class="page-header">
        <div class="page-header-left">
            <div class="page-eyebrow">Inventory Management</div>
            <h1>
                <span class="page-icon"><i class="bi bi-box-seam-fill"></i></span>
                Department Receiving
            </h1>
        </div>
        <div class="date-cell" style="text-align:right;">
            <span style="font-size:12px; color:var(--text-muted);">Today</span>
            <strong style="font-size:14px;"><?= date('F d, Y') ?></strong>
        </div>
    </div>

    <!-- Stats Bar -->
    <div class="stats-bar">
        <div class="stat-card">
            <div class="stat-icon blue"><i class="bi bi-archive"></i></div>
            <div>
                <div class="stat-label">Ready to Receive</div>
                <div class="stat-value"><?= $totalPending ?></div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon yellow"><i class="bi bi-arrow-repeat"></i></div>
            <div>
                <div class="stat-label">Partially Received</div>
                <div class="stat-value"><?= $totalReceiving ?></div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon green"><i class="bi bi-check-circle"></i></div>
            <div>
                <div class="stat-label">Completed</div>
                <div class="stat-value"><?= $totalCompleted ?></div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <form method="get">
        <div class="filter-card">
            <div class="row g-3 align-items-end">
                <div class="col-auto">
                    <div class="form-label">Status</div>
                    <div class="status-tabs">
                        <a href="?status=Pending&search_dept=<?= urlencode($searchDept) ?>"
                           class="status-tab <?= $statusFilter==='Pending' ? 'active' : '' ?>">
                            <i class="bi bi-inbox"></i> Ready to Receive
                        </a>
                        <a href="?status=Completed&search_dept=<?= urlencode($searchDept) ?>"
                           class="status-tab <?= $statusFilter==='Completed' ? 'active' : '' ?>">
                            <i class="bi bi-check-all"></i> Completed
                        </a>
                        <a href="?status=All&search_dept=<?= urlencode($searchDept) ?>"
                           class="status-tab <?= $statusFilter==='All' ? 'active' : '' ?>">
                            <i class="bi bi-grid"></i> All
                        </a>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-label">Search Department</div>
                    <div style="position:relative;">
                        <i class="bi bi-search" style="position:absolute; left:10px; top:50%; transform:translateY(-50%); color:var(--text-muted); font-size:13px;"></i>
                        <input type="text" name="search_dept" class="form-control"
                               style="padding-left:32px;"
                               placeholder="e.g. Finance, HR…"
                               value="<?= htmlspecialchars($searchDept) ?>">
                        <input type="hidden" name="status" value="<?= htmlspecialchars($statusFilter) ?>">
                    </div>
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn-action btn-receive" style="height:38px; border-radius:6px; font-size:13px;">
                        <i class="bi bi-funnel-fill"></i> Apply
                    </button>
                    <a href="order_receive.php" class="btn-action btn-done" style="height:38px; border-radius:6px; font-size:13px; margin-left:6px;">
                        <i class="bi bi-arrow-clockwise"></i> Reset
                    </a>
                </div>
            </div>
        </div>
    </form>

    <!-- Table Card -->
    <div class="table-card">
        <div class="table-card-header">
            <h6>
                <i class="bi bi-list-ul" style="color:var(--accent);"></i>
                Purchase Orders
            </h6>
            <span class="record-count"><?= count($requests) ?> record<?= count($requests) !== 1 ? 's' : '' ?></span>
        </div>

        <div style="overflow-x:auto;">
        <table class="data-table">
        <thead>
        <tr>
            <th>Order ID</th>
            <th>Department</th>
            <th>User ID</th>
            <th class="center">Items</th>
            <th class="center">Status</th>
            <th>Progress</th>
            <th>Purchased At</th>
            <th>Completed At</th>
            <th class="center">Actions</th>
        </tr>
        </thead>
        <tbody>

        <?php if (empty($requests)): ?>
        <tr>
            <td colspan="9" style="padding:0; border:none;">
                <div class="empty-state">
                    <div class="empty-icon"><i class="bi bi-inbox"></i></div>
                    <p><strong style="display:block; font-size:15px; margin-bottom:4px; color:var(--text-secondary);">No records found</strong>
                    Try adjusting your filters or search term.</p>
                </div>
            </td>
        </tr>
        <?php endif; ?>

        <?php foreach($requests as $r):
            $stmtItems = $pdo->prepare("SELECT * FROM department_request_items WHERE request_id=? ORDER BY id ASC");
            $stmtItems->execute([$r['id']]);
            $itemsArray = $stmtItems->fetchAll(PDO::FETCH_ASSOC);

            $status = strtolower(trim($r['status']));

            $totalApproved = 0;
            $totalReceived = 0;
            foreach ($itemsArray as $it) {
                $totalApproved += (int)$it['approved_quantity'];
                $totalReceived += (int)($it['received_quantity'] ?? 0);
            }
            $progressPct = ($totalApproved > 0) ? round(($totalReceived / $totalApproved) * 100) : 0;
            $isCompleted = ($status === 'completed');
        ?>
        <tr class="<?= $isCompleted ? 'is-completed' : '' ?>">
            <td>
                <span class="id-badge">#<?= str_pad($r['id'], 4, '0', STR_PAD_LEFT) ?></span>
            </td>
            <td>
                <div class="dept-cell"><?= htmlspecialchars($r['department']) ?></div>
            </td>
            <td>
                <span style="font-family:'DM Mono',monospace; font-size:12.5px; color:var(--text-secondary);">
                    <?= htmlspecialchars($r['user_id']) ?>
                </span>
            </td>
            <td class="center">
                <span style="font-weight:600;"><?= count($itemsArray) ?></span>
                <span style="color:var(--text-muted); font-size:12px;"> item<?= count($itemsArray)!==1?'s':'' ?></span>
            </td>
            <td class="center">
                <?php if ($isCompleted): ?>
                    <span class="status-badge badge-completed"><i class="bi bi-check-circle-fill"></i> Completed</span>
                <?php elseif ($status === 'receiving'): ?>
                    <span class="status-badge badge-partial"><i class="bi bi-arrow-repeat"></i> Partial</span>
                <?php else: ?>
                    <span class="status-badge badge-ready"><i class="bi bi-box-seam"></i> Ready</span>
                <?php endif; ?>
            </td>
            <td>
                <div class="progress-wrap">
                    <div class="progress-track">
                        <div class="progress-fill <?= $isCompleted ? 'done' : '' ?>"
                             style="width:<?= $progressPct ?>%"></div>
                    </div>
                    <div class="progress-label">
                        <span><?= $progressPct ?>%</span>
                        <span><?= $totalReceived ?>/<?= $totalApproved ?> units</span>
                    </div>
                </div>
            </td>
            <td>
                <?php if (!empty($r['purchased_at'])): ?>
                    <div class="date-cell">
                        <strong><?= date('M d, Y', strtotime($r['purchased_at'])) ?></strong>
                        <?= date('h:i A', strtotime($r['purchased_at'])) ?>
                    </div>
                <?php else: ?>
                    <span style="color:var(--text-muted);">—</span>
                <?php endif; ?>
            </td>
            <td>
                <?php if ($isCompleted && !empty($r['delivered_at'])): ?>
                    <div class="date-cell done">
                        <strong><i class="bi bi-check2-all" style="margin-right:3px;"></i><?= date('M d, Y', strtotime($r['delivered_at'])) ?></strong>
                        <?= date('h:i A', strtotime($r['delivered_at'])) ?>
                    </div>
                <?php else: ?>
                    <span style="color:var(--text-muted);">—</span>
                <?php endif; ?>
            </td>
            <td class="center">
                <div class="actions-cell">
                    <a href="view_receipt.php?request_id=<?= $r['id'] ?>" class="btn-action btn-receipt">
                        <i class="bi bi-file-earmark-text"></i> Receipt
                    </a>
                    <?php if ($isCompleted): ?>
                        <span class="btn-action btn-done">
                            <i class="bi bi-check2-all"></i> Received
                        </span>
                    <?php else: ?>
                        <button type="button" class="btn-action btn-receive receive-items-btn"
                            data-id="<?= $r['id'] ?>"
                            data-items='<?= htmlspecialchars(json_encode($itemsArray, JSON_UNESCAPED_UNICODE), ENT_QUOTES, "UTF-8") ?>'>
                            <i class="bi bi-check2-square"></i> Receive
                        </button>
                    <?php endif; ?>
                </div>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
        </table>
        </div>
    </div>

</div><!-- /main-content -->


<!-- ═══════════ RECEIVE MODAL ═══════════ -->
<div class="modal fade" id="receiveModal" tabindex="-1" aria-labelledby="receiveModalLabel" aria-hidden="true">
<div class="modal-dialog modal-lg modal-dialog-scrollable modal-dialog-centered">
<div class="modal-content">
    <div class="modal-header">
        <span class="modal-title" id="receiveModalLabel">
            <i class="bi bi-box-seam-fill"></i>
            Receive Items — Add to Inventory
        </span>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
    </div>
    <div class="modal-body" id="modalBodyContent"></div>
</div>
</div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
const receiveModal = new bootstrap.Modal(document.getElementById('receiveModal'));

document.querySelectorAll('.receive-items-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        const items = JSON.parse(btn.dataset.items || '[]');
        const orderId = btn.dataset.id;

        const receivableItems = items.filter(item => {
            const approved = parseInt(item.approved_quantity || 0);
            const received = parseInt(item.received_quantity || 0);
            return (approved - received) > 0;
        });
                        
        if (receivableItems.length === 0) {
            alert('All items have already been fully received.');
            return;
        }

        let rows = '';
        items.forEach(item => {
            const idx       = item.id;
            const approved  = parseInt(item.approved_quantity || 0);
            const prev      = parseInt(item.received_quantity || 0);
            const remaining = approved - prev;
            const unit      = item.unit || 'pcs';
            const price     = parseFloat(item.price || 0).toFixed(2);
            const isDone    = remaining <= 0;

            rows += `<tr class="${isDone ? 'row-done' : ''}">
                <td class="item-name">${item.item_name}</td>
                <td style="font-family:'DM Mono',monospace; font-size:13px;">${approved}</td>
                <td style="font-family:'DM Mono',monospace; font-size:13px;">${prev}</td>
                <td>
                    ${isDone
                        ? '<span class="status-badge badge-completed" style="font-size:11px;"><i class="bi bi-check-circle-fill done-check"></i> Done</span>'
                        : `<span class="remaining-qty">${remaining}</span>`
                    }
                </td>
                <td style="color:var(--text-secondary);">${unit}</td>
                <td style="font-family:'DM Mono',monospace; font-size:13px; color:var(--text-secondary);">₱${price}</td>
                <td>
                    <input type="number"
                           class="qty-input-modal"
                           name="received_qty[${idx}]"
                           value="0"
                           min="0"
                           max="${remaining}"
                           ${isDone ? 'disabled' : 'required'}>
                </td>
            </tr>`;
        });

        const html = `
        <form method="post" id="receiveForm">
            <input type="hidden" name="id" value="${orderId}">
            <input type="hidden" name="action" value="receive">

            <div class="modal-alert">
                <i class="bi bi-info-circle-fill"></i>
                <span>Enter the quantity received for each item. The maximum is the <strong>remaining</strong> quantity not yet received.</span>
            </div>

            <div style="overflow-x:auto;">
            <table class="modal-table">
            <thead>
                <tr>
                    <th>Item Name</th>
                    <th>Approved</th>
                    <th>Received</th>
                    <th>Remaining</th>
                    <th>Unit</th>
                    <th>Price</th>
                    <th>Receive Qty</th>
                </tr>
            </thead>
            <tbody>${rows}</tbody>
            </table>
            </div>

            <div class="modal-footer-area">
                <button type="button" class="btn-modal-cancel" data-bs-dismiss="modal">
                    Cancel
                </button>
                <button type="submit" class="btn-modal-submit">
                    <i class="bi bi-check2-circle"></i> Confirm & Add to Inventory
                </button>
            </div>
        </form>`;

        document.getElementById('modalBodyContent').innerHTML = html;
        receiveModal.show();
    });
});
</script>
</body>
</html>
