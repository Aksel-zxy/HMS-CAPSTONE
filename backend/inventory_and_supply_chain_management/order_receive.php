<?php
session_start();
include '../../SQL/config.php';

/* =====================================================
   HANDLE RECEIVING ACTION
=====================================================*/
if (isset($_POST['action']) && $_POST['action'] === 'receive') {

    $request_id     = (int)$_POST['id'];
    $received_items = $_POST['received_qty'] ?? [];

    /* ── Resolve logged-in user full name from DB ── */
    $receiverName = 'Unknown';
    if (!empty($_SESSION['user_id'])) {
        $uStmt = $pdo->prepare("SELECT fname, mname, lname FROM users WHERE user_id = ? LIMIT 1");
        $uStmt->execute([$_SESSION['user_id']]);
        $uRow = $uStmt->fetch(PDO::FETCH_ASSOC);
        if ($uRow) {
            $receiverName = trim(
                $uRow['fname'] . ' ' .
                (!empty($uRow['mname']) ? $uRow['mname'] . ' ' : '') .
                $uRow['lname']
            );
        }
    }

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

        $unit_type   = $item['unit']        ?? 'pcs';
        $pcs_per_box = $item['pcs_per_box'] ?? 1;
        $price       = $item['price']       ?? 0;

        // Map unit value to match inventory ENUM('Piece','Box')
        $unit_enum = (strtolower($unit_type) === 'box') ? 'Box' : 'Piece';

        $total_qty = ($unit_enum === 'Box')
            ? $received_qty * $pcs_per_box
            : $received_qty;

        /* ── Inventory update / insert ── */
        $stmtCheck = $pdo->prepare("SELECT * FROM inventory WHERE item_name=? LIMIT 1");
        $stmtCheck->execute([$item['item_name']]);
        $existing = $stmtCheck->fetch(PDO::FETCH_ASSOC);

        if ($existing) {
            $pdo->prepare(
                "UPDATE inventory SET quantity = quantity + ?, total_qty = total_qty + ?, price = ? WHERE id=?"
            )->execute([$received_qty, $total_qty, $price, $existing['id']]);
        } else {
            $pdo->prepare(
                "INSERT INTO inventory
                    (item_id, item_name, item_type, category, sub_type,
                     quantity, total_qty, price, unit_type, pcs_per_box, received_at, location)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?)"
            )->execute([
                $item_id,
                $item['item_name'],
                $item['item_type']  ?? 'Supply',
                $item['category']   ?? '',
                $item['sub_type']   ?? '',
                $received_qty, $total_qty, $price,
                ucfirst($unit_type), $pcs_per_box,
                'Main Storage'
            ]);
        }

        /* ── Update received_quantity on the item ── */
        $new_received = $prev_received + $received_qty;
        $pdo->prepare("UPDATE department_request_items SET received_quantity=? WHERE id=?")
            ->execute([$new_received, $item_id]);

        /* ── Count existing deliveries for this item to get delivery_number ── */
        $dNumStmt = $pdo->prepare(
            "SELECT COUNT(*) FROM department_request_receive_log WHERE request_id=? AND item_id=?"
        );
        $dNumStmt->execute([$request_id, $item_id]);
        $deliveryNumber = (int)$dNumStmt->fetchColumn() + 1;

        /* ── Insert delivery log with receiver, delivery number, and cumulative qty ── */
        $pdo->prepare(
            "INSERT INTO department_request_receive_log
                (request_id, item_id, received_qty, received_by, received_at, delivery_number, cumulative_qty)
             VALUES (?, ?, ?, ?, NOW(), ?, ?)"
        )->execute([
            $request_id,
            $item_id,
            $received_qty,
            $receiverName,
            $deliveryNumber,
            $new_received
        ]);
    }

    /* ── Check if all items are fully received ── */
    $stmtCheckAll = $pdo->prepare(
        "SELECT approved_quantity, received_quantity
         FROM department_request_items
         WHERE request_id=? AND approved_quantity > 0"
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
        $pdo->prepare("UPDATE department_request SET status='Completed', delivered_at=NOW() WHERE id=?")
            ->execute([$request_id]);
    } else {
        $pdo->prepare("UPDATE department_request SET status='Receiving' WHERE id=?")
            ->execute([$request_id]);
    }

    header("Location: order_receive.php");
    exit;
}

/* =====================================================
   FETCH REQUESTS
=====================================================*/
$statusFilter = $_GET['status']      ?? 'Pending';
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
    $query  .= " AND department LIKE ?";
    $params[] = "%$searchDept%";
}
$query .= " ORDER BY created_at DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$requests = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
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
    --bg:             #f0f2f7;
    --surface:        #ffffff;
    --surface-2:      #f7f8fc;
    --border:         #e4e7ef;
    --border-dark:    #d0d4e0;
    --text-primary:   #141824;
    --text-secondary: #5a6079;
    --text-muted:     #9ba3bd;
    --accent:         #2563eb;
    --accent-light:   #eff4ff;
    --accent-mid:     #93b4fb;
    --success:        #16a34a;
    --success-light:  #f0fdf4;
    --success-mid:    #86efac;
    --warning:        #d97706;
    --warning-light:  #fffbeb;
    --warning-mid:    #fcd34d;
    --danger:         #dc2626;
    --danger-light:   #fef2f2;
    --info:           #0891b2;
    --info-light:     #ecfeff;
    --purple:         #7c3aed;
    --purple-light:   #f5f3ff;
    --radius-sm: 6px; --radius: 10px; --radius-lg: 16px;
    --shadow-sm: 0 1px 3px rgba(0,0,0,.06);
    --shadow:    0 4px 16px rgba(0,0,0,.08);
    --shadow-lg: 0 12px 40px rgba(0,0,0,.12);
}
*,*::before,*::after { box-sizing:border-box; }
body { background:var(--bg); font-family:'DM Sans',sans-serif; color:var(--text-primary); font-size:14px; line-height:1.6; -webkit-font-smoothing:antialiased; }
.main-sidebar { position:fixed; left:0; top:0; bottom:0; width:260px; z-index:100; }
.main-content { margin-left:260px; padding:36px 36px 60px; min-height:100vh; }

/* PAGE HEADER */
.page-header { display:flex; align-items:flex-end; justify-content:space-between; margin-bottom:28px; padding-bottom:20px; border-bottom:1px solid var(--border); }
.page-eyebrow { font-size:11px; font-weight:600; letter-spacing:.08em; text-transform:uppercase; color:var(--accent); margin-bottom:4px; }
.page-header h1 { font-size:24px; font-weight:700; color:var(--text-primary); margin:0; display:flex; align-items:center; gap:10px; }
.page-icon { width:38px; height:38px; background:var(--accent-light); border-radius:var(--radius); display:flex; align-items:center; justify-content:center; color:var(--accent); font-size:18px; flex-shrink:0; }

/* STATS */
.stats-bar { display:grid; grid-template-columns:repeat(3,1fr); gap:16px; margin-bottom:24px; }
.stat-card { background:var(--surface); border:1px solid var(--border); border-radius:var(--radius); padding:16px 20px; display:flex; align-items:center; gap:14px; box-shadow:var(--shadow-sm); transition:box-shadow .2s; }
.stat-card:hover { box-shadow:var(--shadow); }
.stat-icon { width:42px; height:42px; border-radius:var(--radius-sm); display:flex; align-items:center; justify-content:center; font-size:19px; flex-shrink:0; }
.stat-icon.blue   { background:var(--accent-light);  color:var(--accent); }
.stat-icon.green  { background:var(--success-light); color:var(--success); }
.stat-icon.yellow { background:var(--warning-light); color:var(--warning); }
.stat-label { font-size:11.5px; color:var(--text-muted); font-weight:500; }
.stat-value { font-size:22px; font-weight:700; line-height:1.2; color:var(--text-primary); }

/* FILTERS */
.filter-card { background:var(--surface); border:1px solid var(--border); border-radius:var(--radius); padding:18px 22px; margin-bottom:20px; box-shadow:var(--shadow-sm); }
.filter-card .form-label { font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:.06em; color:var(--text-muted); margin-bottom:6px; }
.form-control,.form-select { font-family:'DM Sans',sans-serif; font-size:13.5px; border:1px solid var(--border); border-radius:var(--radius-sm); background:var(--surface-2); color:var(--text-primary); padding:8px 12px; height:38px; transition:border-color .2s,box-shadow .2s; }
.form-control:focus,.form-select:focus { border-color:var(--accent); box-shadow:0 0 0 3px rgba(37,99,235,.1); background:var(--surface); outline:none; }
.status-tabs { display:flex; gap:4px; }
.status-tab { padding:7px 16px; font-size:13px; font-weight:500; border-radius:var(--radius-sm); border:1px solid var(--border); background:var(--surface-2); color:var(--text-secondary); cursor:pointer; text-decoration:none; transition:all .15s; display:flex; align-items:center; gap:6px; }
.status-tab:hover { background:var(--accent-light); color:var(--accent); border-color:var(--accent-mid); }
.status-tab.active { background:var(--accent); color:#fff; border-color:var(--accent); }

/* TABLE */
.table-card { background:var(--surface); border:1px solid var(--border); border-radius:var(--radius-lg); box-shadow:var(--shadow); overflow:hidden; }
.table-card-header { padding:16px 22px; border-bottom:1px solid var(--border); display:flex; align-items:center; justify-content:space-between; }
.table-card-header h6 { font-size:13px; font-weight:600; color:var(--text-secondary); margin:0; display:flex; align-items:center; gap:7px; }
.record-count { font-size:12px; background:var(--accent-light); color:var(--accent); border-radius:20px; padding:2px 10px; font-weight:600; }
.data-table { width:100%; border-collapse:collapse; }
.data-table thead tr { background:var(--surface-2); }
.data-table thead th { padding:12px 16px; font-size:11px; font-weight:600; letter-spacing:.07em; text-transform:uppercase; color:var(--text-muted); border-bottom:1px solid var(--border); white-space:nowrap; text-align:left; }
.data-table thead th.center { text-align:center; }
.data-table tbody tr { border-bottom:1px solid var(--border); transition:background .15s; }
.data-table tbody tr:last-child { border-bottom:none; }
.data-table tbody tr:hover { background:#f7f9ff; }
.data-table tbody tr.is-completed { background:#f6fef9; }
.data-table tbody tr.is-completed:hover { background:#edfaf3; }
.data-table td { padding:13px 16px; font-size:13.5px; color:var(--text-primary); vertical-align:middle; }
.data-table td.center { text-align:center; }
.id-badge { font-family:'DM Mono',monospace; font-size:12px; font-weight:500; color:var(--text-secondary); background:var(--surface-2); border:1px solid var(--border); border-radius:var(--radius-sm); padding:3px 8px; display:inline-block; }
.dept-cell { font-weight:600; color:var(--text-primary); }
.status-badge { display:inline-flex; align-items:center; gap:5px; padding:4px 11px; border-radius:20px; font-size:12px; font-weight:600; white-space:nowrap; }
.badge-completed { background:var(--success-light); color:var(--success); }
.badge-partial   { background:var(--warning-light); color:var(--warning); }
.badge-ready     { background:var(--accent-light);  color:var(--accent); }
.progress-wrap { min-width:150px; }
.progress-track { height:6px; background:var(--border); border-radius:99px; overflow:hidden; margin-bottom:5px; }
.progress-fill { height:100%; border-radius:99px; background:var(--accent); transition:width .4s; }
.progress-fill.done    { background:var(--success); }
.progress-fill.partial { background:var(--warning); }
.progress-label { font-size:11px; color:var(--text-muted); font-family:'DM Mono',monospace; display:flex; justify-content:space-between; }
.progress-label span { font-weight:600; color:var(--text-secondary); }
.date-cell { font-size:12.5px; color:var(--text-secondary); white-space:nowrap; }
.date-cell strong { display:block; font-size:13px; font-weight:600; color:var(--text-primary); }
.date-cell.done strong { color:var(--success); }
.actions-cell { display:flex; gap:6px; justify-content:center; align-items:center; flex-wrap:nowrap; }
.btn-action { display:inline-flex; align-items:center; gap:5px; padding:6px 13px; font-size:12.5px; font-weight:500; border-radius:var(--radius-sm); border:1px solid; cursor:pointer; text-decoration:none; transition:all .15s; white-space:nowrap; font-family:'DM Sans',sans-serif; }
.btn-receipt { background:var(--info-light);    color:var(--info);    border-color:#a5f3fc; }
.btn-receipt:hover { background:var(--info); color:#fff; border-color:var(--info); }
.btn-receive { background:var(--success-light); color:var(--success); border-color:var(--success-mid); }
.btn-receive:hover { background:var(--success); color:#fff; border-color:var(--success); }
.btn-track   { background:var(--purple-light);  color:var(--purple);  border-color:#ddd6fe; }
.btn-track:hover { background:var(--purple); color:#fff; border-color:var(--purple); }
.btn-done    { background:var(--surface-2); color:var(--text-muted); border-color:var(--border); cursor:not-allowed; }
.empty-state { text-align:center; padding:64px 24px; color:var(--text-muted); }
.empty-state .empty-icon { width:64px; height:64px; background:var(--surface-2); border-radius:50%; display:inline-flex; align-items:center; justify-content:center; font-size:28px; margin-bottom:16px; border:1px solid var(--border); }

/* MODALS SHARED */
.modal-content { border:none; border-radius:var(--radius-lg); box-shadow:var(--shadow-lg); overflow:hidden; font-family:'DM Sans',sans-serif; }
.modal-header { padding:20px 24px; border:none; }
.modal-header.green-header  { background:linear-gradient(135deg,#16a34a,#15803d); }
.modal-header.purple-header { background:linear-gradient(135deg,#7c3aed,#6d28d9); }
.modal-title { font-size:16px; font-weight:700; color:#fff; display:flex; align-items:center; gap:8px; }
.modal-body { padding:24px; background:var(--surface); }
.modal-alert { background:var(--accent-light); border:1px solid var(--accent-mid); border-radius:var(--radius); padding:12px 16px; font-size:13px; color:var(--accent); margin-bottom:20px; display:flex; gap:10px; align-items:flex-start; }
.modal-alert i { flex-shrink:0; font-size:15px; margin-top:1px; }
.modal-table { width:100%; border-collapse:collapse; }
.modal-table thead th { font-size:11px; font-weight:600; letter-spacing:.07em; text-transform:uppercase; color:var(--text-muted); padding:10px 12px; border-bottom:2px solid var(--border); text-align:left; }
.modal-table tbody tr { border-bottom:1px solid var(--border); }
.modal-table tbody tr:last-child { border-bottom:none; }
.modal-table tbody tr.row-done { opacity:.55; }
.modal-table td { padding:11px 12px; font-size:13.5px; vertical-align:middle; color:var(--text-primary); }
.modal-table .item-name { font-weight:600; }
.modal-table .remaining-qty { font-weight:700; color:var(--danger); font-family:'DM Mono',monospace; }
.qty-input-modal { width:90px; font-family:'DM Mono',monospace; font-size:14px; font-weight:500; text-align:center; border:1.5px solid var(--border); border-radius:var(--radius-sm); padding:6px 8px; background:var(--surface-2); color:var(--text-primary); transition:border-color .2s,box-shadow .2s; }
.qty-input-modal:focus { border-color:var(--success); box-shadow:0 0 0 3px rgba(22,163,74,.1); outline:none; background:var(--surface); }
.modal-footer-area { margin-top:22px; padding-top:18px; border-top:1px solid var(--border); display:flex; justify-content:flex-end; gap:10px; }
.btn-modal-cancel { padding:9px 20px; border:1px solid var(--border); border-radius:var(--radius-sm); background:var(--surface-2); color:var(--text-secondary); font-size:13.5px; font-weight:500; font-family:'DM Sans',sans-serif; cursor:pointer; transition:all .15s; }
.btn-modal-cancel:hover { background:var(--border); }
.btn-modal-submit { padding:9px 22px; border:none; border-radius:var(--radius-sm); background:var(--success); color:#fff; font-size:13.5px; font-weight:600; font-family:'DM Sans',sans-serif; cursor:pointer; display:inline-flex; align-items:center; gap:7px; transition:all .15s; box-shadow:0 2px 8px rgba(22,163,74,.25); }
.btn-modal-submit:hover { background:#15803d; transform:translateY(-1px); box-shadow:0 4px 14px rgba(22,163,74,.35); }

/* ═══════════════════════════════
   TRACK MODAL
═══════════════════════════════ */
.track-summary-bar { display:grid; grid-template-columns:repeat(4,1fr); gap:12px; margin-bottom:20px; }
.track-summary-tile { background:var(--surface-2); border:1px solid var(--border); border-radius:var(--radius); padding:14px 16px; text-align:center; }
.ts-label { font-size:10.5px; font-weight:700; text-transform:uppercase; letter-spacing:.06em; color:var(--text-muted); margin-bottom:5px; }
.ts-value { font-family:'DM Mono',monospace; font-size:1.4rem; font-weight:600; line-height:1; }
.ts-value.blue   { color:var(--accent); }
.ts-value.green  { color:var(--success); }
.ts-value.orange { color:var(--warning); }
.ts-value.red    { color:var(--danger); }
.ts-sub { font-size:10px; color:var(--text-muted); margin-top:3px; }

.overall-progress-strip { margin-bottom:20px; background:var(--surface-2); border:1px solid var(--border); border-radius:var(--radius); padding:14px 18px; }
.ops-top { display:flex; justify-content:space-between; align-items:center; margin-bottom:8px; }
.ops-label { font-size:12px; font-weight:700; text-transform:uppercase; letter-spacing:.05em; color:var(--text-muted); }
.ops-pct { font-family:'DM Mono',monospace; font-size:13px; font-weight:700; color:var(--text-secondary); }
.big-progress-track { height:10px; background:var(--border); border-radius:99px; overflow:hidden; }
.big-progress-fill { height:100%; border-radius:99px; transition:width .5s; }

/* Item delivery blocks */
.item-delivery-block { border:1px solid var(--border); border-radius:var(--radius); margin-bottom:14px; overflow:hidden; }
.item-delivery-block:last-child { margin-bottom:0; }
.item-delivery-header { display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:10px; padding:12px 16px; background:var(--surface-2); border-bottom:1px solid var(--border); cursor:pointer; user-select:none; transition:background .15s; }
.item-delivery-header:hover { background:#eef1fa; }
.item-delivery-name { font-size:13.5px; font-weight:700; color:var(--text-primary); display:flex; align-items:center; gap:8px; }
.item-icon { width:26px; height:26px; border-radius:6px; background:var(--accent-light); color:var(--accent); display:flex; align-items:center; justify-content:center; font-size:13px; flex-shrink:0; }
.item-delivery-meta { display:flex; align-items:center; gap:8px; flex-wrap:wrap; }
.mini-prog-wrap { display:flex; align-items:center; gap:7px; font-size:11.5px; color:var(--text-secondary); font-family:'DM Mono',monospace; }
.mini-prog-track { width:90px; height:5px; background:var(--border); border-radius:99px; overflow:hidden; }
.mini-prog-fill { height:100%; border-radius:99px; transition:width .4s; }
.fill-done    { background:var(--success); }
.fill-partial { background:var(--warning); }
.fill-none    { background:var(--danger); }
.item-status-chip { display:inline-flex; align-items:center; gap:4px; padding:3px 9px; border-radius:99px; font-size:11px; font-weight:700; }
.chip-done    { background:var(--success-light); color:var(--success); }
.chip-partial { background:var(--warning-light); color:var(--warning); }
.chip-none    { background:var(--danger-light);  color:var(--danger); }
.collapse-arrow { color:var(--text-muted); font-size:13px; transition:transform .25s; }
.collapse-arrow.open { transform:rotate(180deg); }

/* Timeline */
.delivery-timeline { padding:20px 20px 14px; background:var(--surface); }
.timeline-empty { text-align:center; padding:24px; color:var(--text-muted); font-size:13px; font-style:italic; }

/* Timeline entry */
.tl-entry { display:flex; gap:0; margin-bottom:10px; }
.tl-left-col { display:flex; flex-direction:column; align-items:center; width:44px; flex-shrink:0; }
.tl-number-badge { width:34px; height:34px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:13px; font-weight:800; flex-shrink:0; border:2.5px solid; z-index:1; }
.tl-nb-1 { background:#eff4ff; color:var(--accent);  border-color:var(--accent); }
.tl-nb-2 { background:#f0fdf4; color:var(--success); border-color:var(--success); }
.tl-nb-3 { background:#fffbeb; color:var(--warning); border-color:var(--warning); }
.tl-nb-4 { background:#fdf4ff; color:#9333ea; border-color:#9333ea; }
.tl-nb-n { background:var(--surface-2); color:var(--text-secondary); border-color:var(--border-dark); }
.tl-connector { width:2px; flex:1; min-height:16px; background:var(--border); margin-top:3px; }

.tl-card { flex:1; margin-left:12px; margin-bottom:6px; border:1px solid var(--border); border-radius:var(--radius); overflow:hidden; }

/* Card top bar */
.tl-card-top { display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:8px; padding:9px 14px; background:var(--surface-2); border-bottom:1px solid var(--border); }
.tl-delivery-ordinal { font-size:11.5px; font-weight:800; text-transform:uppercase; letter-spacing:.07em; display:flex; align-items:center; gap:5px; }
.tl-ord-1 { color:var(--accent); }
.tl-ord-2 { color:var(--success); }
.tl-ord-3 { color:var(--warning); }
.tl-ord-4 { color:#9333ea; }
.tl-ord-n { color:var(--text-secondary); }
.tl-datetime-badge { display:flex; align-items:center; gap:6px; font-size:11.5px; color:var(--text-secondary); background:var(--surface); border:1px solid var(--border); border-radius:99px; padding:3px 10px; }
.tl-datetime-badge i { font-size:11px; color:var(--text-muted); }

/* Card body: 3 panels */
.tl-card-body { display:flex; align-items:stretch; }

.tl-qty-panel { display:flex; flex-direction:column; align-items:center; justify-content:center; padding:12px 18px; background:var(--success-light); border-right:1px solid var(--success-mid); min-width:88px; }
.tl-qty-num   { font-family:'DM Mono',monospace; font-size:1.55rem; font-weight:800; color:var(--success); line-height:1; }
.tl-qty-label { font-size:10px; font-weight:700; text-transform:uppercase; letter-spacing:.05em; color:var(--success); margin-top:3px; }

.tl-info-panel { flex:1; padding:10px 14px; display:flex; flex-direction:column; justify-content:center; gap:5px; }
.tl-info-row { display:flex; align-items:center; gap:8px; font-size:12.5px; color:var(--text-secondary); }
.tl-info-row i { font-size:13px; width:16px; text-align:center; flex-shrink:0; }
.tl-info-label { color:var(--text-muted); font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:.05em; min-width:64px; }
.tl-info-val { font-weight:600; color:var(--text-primary); }

/* Receiver pill — stands out */
.tl-receiver-pill { display:inline-flex; align-items:center; gap:5px; background:var(--accent-light); border:1px solid var(--accent-mid); border-radius:99px; padding:3px 10px; font-size:12px; font-weight:700; color:var(--accent); }
.tl-receiver-pill i { font-size:11px; }

.tl-cumulative-panel { display:flex; flex-direction:column; align-items:center; justify-content:center; padding:10px 14px; background:var(--surface-2); border-left:1px solid var(--border); min-width:96px; text-align:center; }
.tl-cum-label { font-size:10px; font-weight:700; text-transform:uppercase; letter-spacing:.05em; color:var(--text-muted); margin-bottom:4px; }
.tl-cum-val   { font-family:'DM Mono',monospace; font-size:1rem; font-weight:700; color:var(--text-secondary); }
.tl-cum-of    { font-size:10px; color:var(--text-muted); font-weight:400; margin-top:2px; }
.tl-cum-pct   { font-size:11px; font-weight:700; margin-top:3px; }
.tl-cum-pct.c-done    { color:var(--success); }
.tl-cum-pct.c-partial { color:var(--warning); }
.tl-cum-pct.c-low     { color:var(--danger); }

/* Status pill at bottom of item timeline */
.tl-status-pill { display:inline-flex; align-items:center; gap:6px; margin-top:8px; padding:6px 14px; border-radius:99px; font-size:12px; font-weight:700; }
.tl-sp-done    { background:var(--success-light); color:var(--success); border:1px solid var(--success-mid); }
.tl-sp-partial { background:var(--warning-light); color:var(--warning); border:1px solid var(--warning-mid); }

@keyframes fadeSlideIn { from{opacity:0;transform:translateY(6px);}to{opacity:1;transform:translateY(0);} }
.data-table tbody tr { animation:fadeSlideIn .25s ease both; }
<?php foreach(range(1,20) as $i): ?>
.data-table tbody tr:nth-child(<?= $i ?>) { animation-delay:<?= ($i-1)*0.03 ?>s; }
<?php endforeach; ?>
</style>
</head>
<body>
<div class="main-sidebar"><?php include 'inventory_sidebar.php'; ?></div>
<div class="main-content">

    <?php
    $totalPending = $totalCompleted = $totalReceiving = 0;
    foreach ($requests as $r) {
        $s = strtolower(trim($r['status']));
        if      ($s === 'completed') $totalCompleted++;
        elseif  ($s === 'receiving') $totalReceiving++;
        else                         $totalPending++;
    }
    ?>

    <div class="page-header">
        <div>
            <div class="page-eyebrow">Inventory Management</div>
            <h1><span class="page-icon"><i class="bi bi-box-seam-fill"></i></span>Department Receiving</h1>
        </div>
        <div class="date-cell" style="text-align:right;">
            <span style="font-size:12px;color:var(--text-muted);">Today</span>
            <strong style="font-size:14px;"><?= date('F d, Y') ?></strong>
        </div>
    </div>

    <div class="stats-bar">
        <div class="stat-card"><div class="stat-icon blue"><i class="bi bi-archive"></i></div>
            <div><div class="stat-label">Ready to Receive</div><div class="stat-value"><?= $totalPending ?></div></div></div>
        <div class="stat-card"><div class="stat-icon yellow"><i class="bi bi-arrow-repeat"></i></div>
            <div><div class="stat-label">Partially Received</div><div class="stat-value"><?= $totalReceiving ?></div></div></div>
        <div class="stat-card"><div class="stat-icon green"><i class="bi bi-check-circle"></i></div>
            <div><div class="stat-label">Completed</div><div class="stat-value"><?= $totalCompleted ?></div></div></div>
    </div>

    <form method="get">
        <div class="filter-card">
            <div class="row g-3 align-items-end">
                <div class="col-auto">
                    <div class="form-label">Status</div>
                    <div class="status-tabs">
                        <a href="?status=Pending&search_dept=<?= urlencode($searchDept) ?>"
                           class="status-tab <?= $statusFilter==='Pending'?'active':'' ?>"><i class="bi bi-inbox"></i> Ready to Receive</a>
                        <a href="?status=Completed&search_dept=<?= urlencode($searchDept) ?>"
                           class="status-tab <?= $statusFilter==='Completed'?'active':'' ?>"><i class="bi bi-check-all"></i> Completed</a>
                        <a href="?status=All&search_dept=<?= urlencode($searchDept) ?>"
                           class="status-tab <?= $statusFilter==='All'?'active':'' ?>"><i class="bi bi-grid"></i> All</a>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-label">Search Department</div>
                    <div style="position:relative;">
                        <i class="bi bi-search" style="position:absolute;left:10px;top:50%;transform:translateY(-50%);color:var(--text-muted);font-size:13px;"></i>
                        <input type="text" name="search_dept" class="form-control" style="padding-left:32px;"
                               placeholder="e.g. Finance, HR…" value="<?= htmlspecialchars($searchDept) ?>">
                        <input type="hidden" name="status" value="<?= htmlspecialchars($statusFilter) ?>">
                    </div>
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn-action btn-receive" style="height:38px;border-radius:6px;font-size:13px;"><i class="bi bi-funnel-fill"></i> Apply</button>
                    <a href="order_receive.php" class="btn-action btn-done" style="height:38px;border-radius:6px;font-size:13px;margin-left:6px;"><i class="bi bi-arrow-clockwise"></i> Reset</a>
                </div>
            </div>
        </div>
    </form>

    <div class="table-card">
        <div class="table-card-header">
            <h6><i class="bi bi-list-ul" style="color:var(--accent);"></i> Purchase Orders</h6>
            <span class="record-count"><?= count($requests) ?> record<?= count($requests)!==1?'s':'' ?></span>
        </div>
        <div style="overflow-x:auto;">
        <table class="data-table">
        <thead><tr>
            <th>Order ID</th><th>Department</th><th>User ID</th>
            <th class="center">Items</th><th class="center">Status</th>
            <th>Progress</th><th>Purchased At</th><th>Completed At</th>
            <th class="center">Actions</th>
        </tr></thead>
        <tbody>

        <?php if (empty($requests)): ?>
        <tr><td colspan="9" style="padding:0;border:none;">
            <div class="empty-state">
                <div class="empty-icon"><i class="bi bi-inbox"></i></div>
                <p><strong style="display:block;font-size:15px;margin-bottom:4px;color:var(--text-secondary);">No records found</strong>Try adjusting your filters.</p>
            </div>
        </td></tr>
        <?php endif; ?>

        <?php foreach ($requests as $r):
            $stmtI = $pdo->prepare("SELECT * FROM department_request_items WHERE request_id=? ORDER BY id ASC");
            $stmtI->execute([$r['id']]);
            $itemsArray = $stmtI->fetchAll(PDO::FETCH_ASSOC);

            $status = strtolower(trim($r['status']));
            $totalApproved = $totalReceived = 0;
            foreach ($itemsArray as $it) {
                $totalApproved += (int)$it['approved_quantity'];
                $totalReceived += (int)($it['received_quantity'] ?? 0);
            }
            $progressPct = $totalApproved > 0 ? round(($totalReceived/$totalApproved)*100) : 0;
            $isCompleted = ($status === 'completed');
            $isPartial   = ($status === 'receiving');

            /* Delivery logs — join item info for JS, order by delivery_number */
            $stmtL = $pdo->prepare(
                "SELECT l.*, i.item_name, i.approved_quantity, i.received_quantity, i.unit
                 FROM department_request_receive_log l
                 JOIN department_request_items i ON i.id = l.item_id
                 WHERE l.request_id = ?
                 ORDER BY l.item_id ASC, l.delivery_number ASC, l.received_at ASC"
            );
            $stmtL->execute([$r['id']]);
            $allLogs = $stmtL->fetchAll(PDO::FETCH_ASSOC);
            $hasLogs = !empty($allLogs);
        ?>
        <tr class="<?= $isCompleted?'is-completed':'' ?>">
            <td><span class="id-badge">#<?= str_pad($r['id'],4,'0',STR_PAD_LEFT) ?></span></td>
            <td><div class="dept-cell"><?= htmlspecialchars($r['department']) ?></div></td>
            <td><span style="font-family:'DM Mono',monospace;font-size:12.5px;color:var(--text-secondary);"><?= htmlspecialchars($r['user_id']) ?></span></td>
            <td class="center">
                <span style="font-weight:600;"><?= count($itemsArray) ?></span>
                <span style="color:var(--text-muted);font-size:12px;"> item<?= count($itemsArray)!==1?'s':'' ?></span>
            </td>
            <td class="center">
                <?php if ($isCompleted): ?>
                    <span class="status-badge badge-completed"><i class="bi bi-check-circle-fill"></i> Completed</span>
                <?php elseif ($isPartial): ?>
                    <span class="status-badge badge-partial"><i class="bi bi-arrow-repeat"></i> Partial</span>
                <?php else: ?>
                    <span class="status-badge badge-ready"><i class="bi bi-box-seam"></i> Ready</span>
                <?php endif; ?>
            </td>
            <td>
                <div class="progress-wrap">
                    <div class="progress-track">
                        <div class="progress-fill <?= $isCompleted?'done':($isPartial?'partial':'') ?>" style="width:<?= $progressPct ?>%"></div>
                    </div>
                    <div class="progress-label">
                        <span><?= $progressPct ?>%</span>
                        <span><?= $totalReceived ?>/<?= $totalApproved ?> units</span>
                    </div>
                </div>
            </td>
            <td>
                <?php if (!empty($r['purchased_at'])): ?>
                <div class="date-cell"><strong><?= date('M d, Y',strtotime($r['purchased_at'])) ?></strong><?= date('h:i A',strtotime($r['purchased_at'])) ?></div>
                <?php else: ?><span style="color:var(--text-muted);">—</span><?php endif; ?>
            </td>
            <td>
                <?php if ($isCompleted && !empty($r['delivered_at'])): ?>
                <div class="date-cell done"><strong><i class="bi bi-check2-all" style="margin-right:3px;"></i><?= date('M d, Y',strtotime($r['delivered_at'])) ?></strong><?= date('h:i A',strtotime($r['delivered_at'])) ?></div>
                <?php else: ?><span style="color:var(--text-muted);">—</span><?php endif; ?>
            </td>
            <td class="center">
                <div class="actions-cell">
                    <a href="view_receipt.php?request_id=<?= $r['id'] ?>" class="btn-action btn-receipt">
                        <i class="bi bi-file-earmark-text"></i> Receipt
                    </a>

                    <?php if ($hasLogs || $isPartial || $isCompleted): ?>
                    <button type="button" class="btn-action btn-track track-btn"
                        data-id="<?= $r['id'] ?>"
                        data-dept="<?= htmlspecialchars($r['department']) ?>"
                        data-items='<?= htmlspecialchars(json_encode($itemsArray,JSON_UNESCAPED_UNICODE),ENT_QUOTES,'UTF-8') ?>'
                        data-logs='<?= htmlspecialchars(json_encode($allLogs,JSON_UNESCAPED_UNICODE),ENT_QUOTES,'UTF-8') ?>'>
                        <i class="bi bi-graph-up-arrow"></i> Track
                    </button>
                    <?php endif; ?>

                    <?php if ($isCompleted): ?>
                        <span class="btn-action btn-done"><i class="bi bi-check2-all"></i> Received</span>
                    <?php else: ?>
                        <button type="button" class="btn-action btn-receive receive-items-btn"
                            data-id="<?= $r['id'] ?>"
                            data-items='<?= htmlspecialchars(json_encode($itemsArray,JSON_UNESCAPED_UNICODE),ENT_QUOTES,'UTF-8') ?>'>
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
</div>

<!-- RECEIVE MODAL -->
<div class="modal fade" id="receiveModal" tabindex="-1" aria-hidden="true">
<div class="modal-dialog modal-lg modal-dialog-scrollable modal-dialog-centered">
<div class="modal-content">
    <div class="modal-header green-header">
        <span class="modal-title"><i class="bi bi-box-seam-fill"></i> Receive Items — Add to Inventory</span>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
    </div>
    <div class="modal-body" id="modalBodyContent"></div>
</div></div></div>

<!-- TRACK MODAL -->
<div class="modal fade" id="trackModal" tabindex="-1" aria-hidden="true">
<div class="modal-dialog modal-xl modal-dialog-scrollable modal-dialog-centered">
<div class="modal-content">
    <div class="modal-header purple-header">
        <span class="modal-title" id="trackModalTitle"><i class="bi bi-graph-up-arrow"></i> Delivery Tracking</span>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
    </div>
    <div class="modal-body" id="trackModalBody"></div>
</div></div></div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
const receiveModal = new bootstrap.Modal(document.getElementById('receiveModal'));
const trackModal   = new bootstrap.Modal(document.getElementById('trackModal'));

/* helpers */
const ORD_LABELS = ['1st','2nd','3rd','4th','5th','6th','7th','8th','9th','10th'];
function ordLabel(n)  { return ORD_LABELS[n] || (n+1)+'th'; }
function nbClass(i)   { return ['tl-nb-1','tl-nb-2','tl-nb-3','tl-nb-4'][i] || 'tl-nb-n'; }
function ordClass(i)  { return ['tl-ord-1','tl-ord-2','tl-ord-3','tl-ord-4'][i] || 'tl-ord-n'; }
function fmtDate(s)   { if(!s)return'—'; const d=new Date(s); return d.toLocaleDateString('en-PH',{month:'short',day:'numeric',year:'numeric'}); }
function fmtTime(s)   { if(!s)return''; const d=new Date(s); return d.toLocaleTimeString('en-PH',{hour:'2-digit',minute:'2-digit'}); }

/* ── RECEIVE MODAL ── */
document.querySelectorAll('.receive-items-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        const items   = JSON.parse(btn.dataset.items || '[]');
        const orderId = btn.dataset.id;
        const receivable = items.filter(it => (parseInt(it.approved_quantity||0)-parseInt(it.received_quantity||0)) > 0);
        if (!receivable.length) { alert('All items have already been fully received.'); return; }

        let rows = '';
        items.forEach(item => {
            const approved  = parseInt(item.approved_quantity||0);
            const prev      = parseInt(item.received_quantity||0);
            const remaining = approved - prev;
            const unit      = item.unit  || 'pcs';
            const price     = parseFloat(item.price||0).toFixed(2);
            const isDone    = remaining <= 0;
            rows += `<tr class="${isDone?'row-done':''}">
                <td class="item-name">${item.item_name}</td>
                <td style="font-family:'DM Mono',monospace;font-size:13px;">${approved}</td>
                <td style="font-family:'DM Mono',monospace;font-size:13px;">${prev}</td>
                <td>${isDone ? '<span class="status-badge badge-completed" style="font-size:11px;"><i class="bi bi-check-circle-fill"></i> Done</span>' : `<span class="remaining-qty">${remaining}</span>`}</td>
                <td style="color:var(--text-secondary);">${unit}</td>
                <td style="font-family:'DM Mono',monospace;font-size:13px;color:var(--text-secondary);">₱${price}</td>
                <td><input type="number" class="qty-input-modal" name="received_qty[${item.id}]" value="0" min="0" max="${remaining}" ${isDone?'disabled':''}></td>
            </tr>`;
        });

        document.getElementById('modalBodyContent').innerHTML = `
        <form method="post" id="receiveForm">
            <input type="hidden" name="id" value="${orderId}">
            <input type="hidden" name="action" value="receive">
            <div class="modal-alert"><i class="bi bi-info-circle-fill"></i>
                <span>Enter the quantity received for each item. Maximum is the <strong>remaining</strong> quantity.</span></div>
            <div style="overflow-x:auto;">
            <table class="modal-table">
            <thead><tr><th>Item Name</th><th>Approved</th><th>Received</th><th>Remaining</th><th>Unit</th><th>Price</th><th>Receive Qty</th></tr></thead>
            <tbody>${rows}</tbody></table></div>
            <div class="modal-footer-area">
                <button type="button" class="btn-modal-cancel" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn-modal-submit"><i class="bi bi-check2-circle"></i> Confirm &amp; Add to Inventory</button>
            </div>
        </form>`;
        receiveModal.show();
    });
});

/* ── TRACK MODAL ── */
document.querySelectorAll('.track-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        const orderId = btn.dataset.id;
        const dept    = btn.dataset.dept;
        const items   = JSON.parse(btn.dataset.items || '[]');
        const logs    = JSON.parse(btn.dataset.logs  || '[]');

        document.getElementById('trackModalTitle').innerHTML =
            `<i class="bi bi-graph-up-arrow"></i> Delivery Tracking — Order #${orderId.toString().padStart(4,'0')}
             <span style="opacity:.65;font-size:13px;font-weight:500;margin-left:6px;">${dept}</span>`;

        /* totals */
        let totalApproved=0, totalReceived=0;
        items.forEach(it=>{ totalApproved+=parseInt(it.approved_quantity||0); totalReceived+=parseInt(it.received_quantity||0); });
        const totalRemaining = totalApproved - totalReceived;
        const totalPct       = totalApproved>0 ? Math.round((totalReceived/totalApproved)*100) : 0;
        const orderDone      = totalRemaining<=0 && totalApproved>0;
        const totalDeliveries= logs.length;

        /* group logs by item_id */
        const logsByItem = {};
        logs.forEach(l => { if(!logsByItem[l.item_id]) logsByItem[l.item_id]=[]; logsByItem[l.item_id].push(l); });

        /* build item blocks */
        let itemBlocksHTML = '';
        items.forEach(item => {
            const approved   = parseInt(item.approved_quantity||0);
            const received   = parseInt(item.received_quantity||0);
            const itemRemain = approved - received;
            const itemPct    = approved>0 ? Math.round((received/approved)*100) : 0;
            const itemLogs   = logsByItem[item.id] || [];
            const itemDone   = received>=approved && approved>0;
            const itemPart   = received>0 && received<approved;
            const unit       = item.unit || 'pcs';
            const chipClass  = itemDone?'chip-done':(itemPart?'chip-partial':'chip-none');
            const chipIcon   = itemDone?'bi-check-circle-fill':(itemPart?'bi-arrow-repeat':'bi-hourglass-split');
            const chipTxt    = itemDone?'Fully Received':(itemPart?'Partial':'Not Received');
            const fillClass  = itemDone?'fill-done':(itemPart?'fill-partial':'fill-none');
            const colId      = `tlC_${orderId}_${item.id}`;

            let tlHTML = '';
            if (!itemLogs.length) {
                tlHTML = `<div class="timeline-empty"><i class="bi bi-hourglass" style="margin-right:6px;"></i>No deliveries recorded yet for this item.</div>`;
            } else {
                itemLogs.forEach((log, li) => {
                    const delivNum    = log.delivery_number || (li+1);
                    const cumQty      = log.cumulative_qty  != null ? log.cumulative_qty : '—';
                    const cumPct      = approved>0 ? Math.round((parseInt(cumQty||0)/approved)*100) : 0;
                    const cumPctClass = cumPct>=100?'c-done':(cumPct>=50?'c-partial':'c-low');
                    const receivedBy  = log.received_by || 'Unknown';
                    const isLast      = li===itemLogs.length-1;

                    tlHTML += `
                    <div class="tl-entry">
                        <div class="tl-left-col">
                            <div class="tl-number-badge ${nbClass(li)}">${delivNum}</div>
                            <div class="tl-connector" ${isLast?'style="display:none;"':''}></div>
                        </div>
                        <div class="tl-card">
                            <div class="tl-card-top">
                                <span class="tl-delivery-ordinal ${ordClass(li)}">
                                    <i class="bi bi-truck"></i> ${ordLabel(li)} Delivery
                                </span>
                                <span class="tl-datetime-badge">
                                    <i class="bi bi-calendar3"></i> ${fmtDate(log.received_at)}
                                    &nbsp;·&nbsp;
                                    <i class="bi bi-clock"></i> ${fmtTime(log.received_at)}
                                </span>
                            </div>
                            <div class="tl-card-body">
                                <div class="tl-qty-panel">
                                    <div class="tl-qty-num">+${log.received_qty}</div>
                                    <div class="tl-qty-label">${unit} received</div>
                                </div>
                                <div class="tl-info-panel">
                                    <div class="tl-info-row">
                                        <i class="bi bi-person-check-fill" style="color:var(--accent);"></i>
                                        <span class="tl-info-label">Received by</span>
                                        <span class="tl-receiver-pill">
                                            <i class="bi bi-person-fill"></i> ${receivedBy}
                                        </span>
                                    </div>
                                    <div class="tl-info-row">
                                        <i class="bi bi-calendar-event" style="color:var(--text-muted);"></i>
                                        <span class="tl-info-label">Date</span>
                                        <span class="tl-info-val">${fmtDate(log.received_at)}</span>
                                    </div>
                                    <div class="tl-info-row">
                                        <i class="bi bi-clock-history" style="color:var(--text-muted);"></i>
                                        <span class="tl-info-label">Time</span>
                                        <span class="tl-info-val">${fmtTime(log.received_at)}</span>
                                    </div>
                                </div>
                                <div class="tl-cumulative-panel">
                                    <div class="tl-cum-label">Cumulative</div>
                                    <div class="tl-cum-val">${cumQty}</div>
                                    <div class="tl-cum-of">of ${approved} ${unit}</div>
                                    <div class="tl-cum-pct ${cumPctClass}">${cumPct}% done</div>
                                </div>
                            </div>
                        </div>
                    </div>`;
                });

                tlHTML += itemDone
                    ? `<span class="tl-status-pill tl-sp-done"><i class="bi bi-check-circle-fill"></i> All ${approved} ${unit} received — complete</span>`
                    : `<span class="tl-status-pill tl-sp-partial"><i class="bi bi-exclamation-circle-fill"></i> Still waiting for <strong style="margin:0 3px;">${itemRemain}</strong> ${unit} more</span>`;
            }

            itemBlocksHTML += `
            <div class="item-delivery-block">
                <div class="item-delivery-header" onclick="toggleBlock('${colId}',this)">
                    <div class="item-delivery-name">
                        <span class="item-icon"><i class="bi bi-box-seam"></i></span>
                        ${item.item_name}
                    </div>
                    <div class="item-delivery-meta">
                        <div class="mini-prog-wrap">
                            <div class="mini-prog-track"><div class="mini-prog-fill ${fillClass}" style="width:${itemPct}%"></div></div>
                            <span>${received}/${approved} ${unit}</span>
                        </div>
                        <span class="item-status-chip ${chipClass}"><i class="bi ${chipIcon}"></i> ${chipTxt}</span>
                        <span style="font-size:11.5px;color:var(--text-muted);font-family:'DM Mono',monospace;">${itemLogs.length} delivery${itemLogs.length!==1?'ies':'y'}</span>
                        <i class="bi bi-chevron-down collapse-arrow" id="arr_${colId}"></i>
                    </div>
                </div>
                <div class="delivery-timeline" id="${colId}" style="display:none;">${tlHTML}</div>
            </div>`;
        });

        const barColor = orderDone ? 'var(--success)' : (totalPct>0 ? 'var(--warning)' : 'var(--danger)');

        document.getElementById('trackModalBody').innerHTML = `
            <div class="track-summary-bar">
                <div class="track-summary-tile"><div class="ts-label">Total Approved</div><div class="ts-value blue">${totalApproved}</div><div class="ts-sub">units</div></div>
                <div class="track-summary-tile"><div class="ts-label">Total Received</div><div class="ts-value green">${totalReceived}</div><div class="ts-sub">units</div></div>
                <div class="track-summary-tile"><div class="ts-label">Remaining</div><div class="ts-value ${totalRemaining>0?'red':'green'}">${totalRemaining}</div><div class="ts-sub">units</div></div>
                <div class="track-summary-tile"><div class="ts-label">Total Deliveries</div><div class="ts-value orange">${totalDeliveries}</div><div class="ts-sub">log entries</div></div>
            </div>
            <div class="overall-progress-strip">
                <div class="ops-top"><span class="ops-label">Overall Order Progress</span><span class="ops-pct">${totalPct}%</span></div>
                <div class="big-progress-track"><div class="big-progress-fill" style="width:${totalPct}%;background:${barColor};"></div></div>
            </div>
            <div>${itemBlocksHTML}</div>`;

        /* auto-expand items with logs */
        items.forEach(item => {
            const colId = `tlC_${orderId}_${item.id}`;
            const el    = document.getElementById(colId);
            const arr   = document.getElementById('arr_' + colId);
            if (el && (logsByItem[item.id]||[]).length > 0) {
                el.style.display = 'block';
                if (arr) arr.classList.add('open');
            }
        });

        trackModal.show();
    });
});

function toggleBlock(id, header) {
    const el  = document.getElementById(id);
    const arr = document.getElementById('arr_' + id);
    if (!el) return;
    const open = el.style.display !== 'none';
    el.style.display = open ? 'none' : 'block';
    if (arr) arr.classList.toggle('open', !open);
}
</script>
</body>
</html>
