<?php
include '../../SQL/config.php';

/* =====================================================
   SAFE DEFAULTS
=====================================================*/
$statusFilter   = strtolower(trim($_GET['status'] ?? 'pending'));
$searchDept     = trim($_GET['search_dept'] ?? '');
$requests       = [];
$counts         = [];
$errorMessage   = '';
$successMessage = '';

function findDbEnumMatch(PDO $pdo, string $table, string $column, string $desired): string {
    $sql  = "SELECT COLUMN_TYPE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$table, $column]);
    $colType = $stmt->fetchColumn();
    if (!$colType || !preg_match("/^enum\(/i", $colType)) return ucfirst($desired);
    if (preg_match("/^enum\((.*)\)$/i", $colType, $m)) {
        $vals = str_getcsv($m[1], ',', "'");
        foreach ($vals as $v) { if (strcasecmp($v, $desired) === 0) return $v; }
        foreach ($vals as $v) { if (strcasecmp($v, ucfirst($desired)) === 0) return $v; }
        return $vals[0] ?? ucfirst($desired);
    }
    return ucfirst($desired);
}

/* =====================================================
   HANDLE ACTIONS
=====================================================*/
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    if ($action === 'bulk_purchase') {
        $ids = $_POST['request_ids'] ?? [];
        if (!empty($ids)) {
            $dbStatus = findDbEnumMatch($pdo, 'department_request', 'status', 'purchased');
            foreach ($ids as $rid) {
                $rid = (int)$rid;
                if ($rid <= 0) continue;
                $pdo->prepare("UPDATE department_request SET status=?, purchased_at=NOW() WHERE id=? AND LOWER(status)='approved'")
                    ->execute([$dbStatus, $rid]);
            }
        }
        header("Location: department_request.php?status=purchased&success=bulk_purchased");
        exit;
    }

    $request_id = (int)($_POST['id'] ?? 0);
    if ($request_id > 0) {
        $stmt = $pdo->prepare("SELECT * FROM department_request WHERE id=? LIMIT 1");
        $stmt->execute([$request_id]);
        $request = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($request) {
            $currentStatus = strtolower(trim($request['status'] ?? ''));
            $stmtItems = $pdo->prepare("SELECT * FROM department_request_items WHERE request_id=? ORDER BY id ASC");
            $stmtItems->execute([$request_id]);
            $items = $stmtItems->fetchAll(PDO::FETCH_ASSOC) ?? [];

            if ($action === 'approve' && $currentStatus === 'pending') {
                $approved_quantities = $_POST['approved_quantity'] ?? [];
                foreach ($items as $item) {
                    $idx      = $item['id'];
                    $approved = isset($approved_quantities[$idx]) ? (int)$approved_quantities[$idx] : 0;
                    if ($approved < 0) $approved = 0;
                    if ($approved > $item['quantity']) $approved = $item['quantity'];
                    $pdo->prepare("UPDATE department_request_items SET approved_quantity=? WHERE id=?")
                        ->execute([$approved, $idx]);
                }
                $stmtTotal = $pdo->prepare("SELECT SUM(approved_quantity) FROM department_request_items WHERE request_id=?");
                $stmtTotal->execute([$request_id]);
                $totalApproved = (int)($stmtTotal->fetchColumn() ?? 0);
                if ($totalApproved <= 0) { header("Location: department_request.php?status=pending&error=no_approved"); exit; }
                $dbStatus = findDbEnumMatch($pdo, 'department_request', 'status', 'approved');
                $pdo->prepare("UPDATE department_request SET status=?, total_approved_items=? WHERE id=?")
                    ->execute([$dbStatus, $totalApproved, $request_id]);
                header("Location: department_request.php?status=approved&success=approved"); exit;
            }

            if ($action === 'reject' && $currentStatus === 'pending') {
                $dbStatus = findDbEnumMatch($pdo, 'department_request', 'status', 'rejected');
                $pdo->prepare("UPDATE department_request SET status=? WHERE id=?")
                    ->execute([$dbStatus, $request_id]);
                header("Location: department_request.php?status=rejected&success=rejected"); exit;
            }
        }
    }
    header("Location: department_request.php"); exit;
}

/* =====================================================
   LOAD STATUS COUNTS
=====================================================*/
try {
    $countsRaw = $pdo->query("SELECT LOWER(status) AS s, COUNT(*) AS cnt FROM department_request GROUP BY LOWER(status)")
                     ->fetchAll(PDO::FETCH_ASSOC) ?? [];
    foreach ($countsRaw as $c) $counts[ucfirst($c['s'])] = $c['cnt'];
} catch (Exception $e) { $counts = []; }

/* =====================================================
   FETCH FILTERED REQUESTS
=====================================================*/
try {
    $params = [$statusFilter];
    $sql    = "SELECT * FROM department_request WHERE LOWER(status)=?";
    if (!empty($searchDept)) { $sql .= " AND department LIKE ?"; $params[] = "%$searchDept%"; }
    $sql .= " ORDER BY created_at DESC";
    $stmtR = $pdo->prepare($sql);
    $stmtR->execute($params);
    $requests = $stmtR->fetchAll(PDO::FETCH_ASSOC) ?? [];
} catch (Exception $e) { $requests = []; }

/* =====================================================
   ALWAYS LOAD ALL APPROVED ITEMS FOR BULK MODAL
=====================================================*/
$allApprovedItems   = [];
$allApprovedGrouped = [];
try {
    $stmtAll = $pdo->query("
        SELECT dr.id AS request_id, dr.department, dr.created_at AS req_date,
               dri.id AS item_id, dri.item_name, dri.quantity,
               COALESCE(dri.approved_quantity, 0) AS approved_quantity,
               COALESCE(dri.unit, 'pcs') AS unit
        FROM department_request dr
        JOIN department_request_items dri ON dri.request_id = dr.id
        WHERE LOWER(dr.status) = 'approved'
          AND COALESCE(dri.approved_quantity, 0) > 0
        ORDER BY dr.id ASC, dri.id ASC
    ");
    $allApprovedItems = $stmtAll->fetchAll(PDO::FETCH_ASSOC) ?? [];
    foreach ($allApprovedItems as $ai) $allApprovedGrouped[$ai['request_id']][] = $ai;
} catch (Exception $e) { $allApprovedItems = []; $allApprovedGrouped = []; }

$approvedReqCount  = count($allApprovedGrouped);
$approvedItemCount = count($allApprovedItems);
$approvedQtyTotal  = $approvedItemCount > 0 ? array_sum(array_column($allApprovedItems, 'approved_quantity')) : 0;

/* =====================================================
   ALERT MESSAGES
=====================================================*/
$errorMessages   = ['no_approved' => 'Please set at least one approved quantity greater than zero.'];
$successMessages = [
    'approved'       => 'Request approved successfully.',
    'rejected'       => 'Request rejected successfully.',
    'bulk_purchased' => 'All approved items have been sent to the Supplier Portal for pricing.',
];
$errorMessage   = $errorMessages[$_GET['error']   ?? ''] ?? '';
$successMessage = $successMessages[$_GET['success'] ?? ''] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Department Requests — Purchase Order</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
<style>
:root {
    --primary:       #1a56db;
    --primary-light: #e8f0fe;
    --primary-dark:  #1241b0;
    --success:       #0e9f6e;
    --success-light: #def7ec;
    --danger:        #e02424;
    --danger-light:  #fde8e8;
    --warning:       #c27803;
    --warning-light: #fdf6b2;
    --info:          #0891b2;
    --info-light:    #e0f2fe;
    --orange:        #ea580c;
    --gray-50:  #f9fafb; --gray-100: #f3f4f6; --gray-200: #e5e7eb;
    --gray-300: #d1d5db; --gray-400: #9ca3af; --gray-500: #6b7280;
    --gray-600: #4b5563; --gray-700: #374151; --gray-800: #1f2937; --gray-900: #111827;
    --sidebar-w: 260px;
    --radius-sm: 6px; --radius: 10px; --radius-lg: 16px;
    --shadow-sm: 0 1px 3px rgba(0,0,0,.08),0 1px 2px rgba(0,0,0,.04);
    --shadow:    0 4px 16px rgba(0,0,0,.08),0 2px 6px rgba(0,0,0,.04);
    --shadow-lg: 0 10px 40px rgba(0,0,0,.12),0 4px 12px rgba(0,0,0,.06);
}
*,*::before,*::after { box-sizing:border-box; margin:0; padding:0; }
body { font-family:'Plus Jakarta Sans',sans-serif; background:var(--gray-50); color:var(--gray-800); font-size:14px; line-height:1.6; }
.main-sidebar { position:fixed; left:0; top:0; bottom:0; width:var(--sidebar-w); z-index:1000; overflow-y:auto; }
.main-content  { margin-left:var(--sidebar-w); min-height:100vh; padding:28px 32px; }

/* ── Page Header ── */
.page-header { display:flex; align-items:flex-start; justify-content:space-between; flex-wrap:wrap; gap:16px; margin-bottom:28px; }
.page-header-left h1 { font-size:1.6rem; font-weight:800; color:var(--gray-900); letter-spacing:-.4px; }
.page-header-left p  { color:var(--gray-500); margin-top:2px; font-size:.85rem; }
.page-badge { display:inline-flex; align-items:center; gap:6px; background:var(--primary-light); color:var(--primary); font-size:.72rem; font-weight:700; text-transform:uppercase; letter-spacing:.6px; padding:4px 10px; border-radius:100px; margin-bottom:6px; }
.supplier-link { display:inline-flex; align-items:center; gap:6px; background:var(--warning-light); color:var(--warning); border:1px solid #fde68a; border-radius:var(--radius-sm); padding:9px 14px; font-size:.83rem; font-weight:700; text-decoration:none; transition:all .2s; }
.supplier-link:hover { background:#fef9c3; color:#854d0e; }

/* ── Alerts ── */
.alert-custom { display:flex; align-items:center; gap:10px; padding:14px 18px; border-radius:var(--radius); margin-bottom:20px; font-size:.875rem; font-weight:500; animation:slideDown .25s ease; }
@keyframes slideDown { from{opacity:0;transform:translateY(-8px);} to{opacity:1;transform:translateY(0);} }
.alert-danger-custom  { background:var(--danger-light);  color:#991b1b; border:1px solid #fca5a5; }
.alert-success-custom { background:var(--success-light); color:#065f46; border:1px solid #6ee7b7; }

/* ── Stat Cards ── */
.stats-row { display:grid; grid-template-columns:repeat(auto-fit,minmax(130px,1fr)); gap:14px; margin-bottom:20px; }
.stat-card { background:#fff; border-radius:var(--radius); padding:16px 18px; box-shadow:var(--shadow-sm); border:1px solid var(--gray-200); cursor:pointer; transition:all .2s; text-decoration:none; display:block; position:relative; overflow:hidden; }
.stat-card::before { content:''; position:absolute; top:0; left:0; right:0; height:3px; border-radius:var(--radius) var(--radius) 0 0; }
.stat-card.sc-pending::before   { background:#f59e0b; }
.stat-card.sc-approved::before  { background:var(--success); }
.stat-card.sc-purchased::before { background:var(--primary); }
.stat-card.sc-receiving::before { background:var(--info); }
.stat-card.sc-completed::before { background:#6366f1; }
.stat-card.sc-rejected::before  { background:var(--danger); }
.stat-card:hover { box-shadow:var(--shadow); transform:translateY(-2px); }
.stat-card.sc-pending.active   { border-color:#f59e0b;         background:#fffbeb; }
.stat-card.sc-approved.active  { border-color:var(--success);  background:var(--success-light); }
.stat-card.sc-purchased.active { border-color:var(--primary);  background:var(--primary-light); }
.stat-card.sc-receiving.active { border-color:var(--info);     background:var(--info-light); }
.stat-card.sc-completed.active { border-color:#6366f1;         background:#eef2ff; }
.stat-card.sc-rejected.active  { border-color:var(--danger);   background:var(--danger-light); }
.stat-label  { font-size:.72rem; font-weight:700; text-transform:uppercase; letter-spacing:.5px; color:var(--gray-500); margin-bottom:6px; }
.stat-number { font-size:1.8rem; font-weight:800; line-height:1; font-family:'JetBrains Mono',monospace; }
.sc-pending .stat-number   { color:#d97706; }
.sc-approved .stat-number  { color:var(--success); }
.sc-purchased .stat-number { color:var(--primary); }
.sc-receiving .stat-number { color:var(--info); }
.sc-completed .stat-number { color:#6366f1; }
.sc-rejected .stat-number  { color:var(--danger); }

/* ── Purchase Action Bar ── */
.purchase-action-bar {
    display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:14px;
    background:linear-gradient(135deg,#064e3b 0%,#065f46 60%,#047857 100%);
    border-radius:var(--radius-lg); padding:16px 22px; margin-bottom:20px;
    box-shadow:0 4px 18px rgba(6,79,60,.28),0 1px 4px rgba(0,0,0,.12);
    border:1px solid rgba(255,255,255,.08); position:relative; overflow:hidden;
}
.purchase-action-bar::before { content:''; position:absolute; top:-30px; right:-20px; width:130px; height:130px; background:rgba(255,255,255,.04); border-radius:50%; pointer-events:none; }
.pab-left  { display:flex; align-items:center; gap:14px; flex:1; min-width:0; }
.pab-icon  { width:44px; height:44px; flex-shrink:0; background:rgba(255,255,255,.15); border-radius:12px; display:flex; align-items:center; justify-content:center; font-size:1.2rem; color:#fff; border:1px solid rgba(255,255,255,.18); }
.pab-info  { display:flex; flex-direction:column; gap:5px; min-width:0; }
.pab-title { color:#fff; font-size:.95rem; font-weight:700; letter-spacing:-.2px; line-height:1.2; }
.pab-sub   { display:flex; align-items:center; gap:6px; flex-wrap:wrap; }
.pab-stat  { display:inline-flex; align-items:center; gap:4px; color:rgba(255,255,255,.75); font-size:.75rem; font-weight:500; }
.pab-stat i { font-size:.72rem; opacity:.8; }
.pab-dot   { color:rgba(255,255,255,.3); font-size:.7rem; }
.pab-btn   { background:#fff; color:#065f46; border:none; border-radius:var(--radius-sm); padding:10px 20px; font-size:.875rem; font-weight:700; font-family:'Plus Jakarta Sans',sans-serif; cursor:pointer; display:inline-flex; align-items:center; gap:8px; transition:all .2s; white-space:nowrap; box-shadow:0 2px 8px rgba(0,0,0,.18); flex-shrink:0; }
.pab-btn:hover { background:#f0fdf4; transform:translateY(-1px); box-shadow:0 6px 18px rgba(0,0,0,.22); }
.pab-count { background:#065f46; color:#fff; border-radius:100px; padding:1px 8px; font-size:.72rem; font-family:'JetBrains Mono',monospace; font-weight:700; }

/* ── Panel / Table ── */
.panel { background:#fff; border-radius:var(--radius-lg); box-shadow:var(--shadow); border:1px solid var(--gray-200); overflow:hidden; }
.data-table { width:100%; border-collapse:collapse; }
.data-table thead th { background:var(--gray-50); color:var(--gray-500); font-size:.7rem; font-weight:700; text-transform:uppercase; letter-spacing:.6px; padding:12px 20px; border-bottom:1px solid var(--gray-200); text-align:left; white-space:nowrap; }
.data-table tbody td { padding:14px 20px; border-bottom:1px solid var(--gray-100); color:var(--gray-700); vertical-align:middle; font-size:.875rem; }
.data-table tbody tr:last-child td { border-bottom:none; }
.data-table tbody tr { transition:background .15s; }
.data-table tbody tr:hover { background:var(--gray-50); }
.data-table td.center { text-align:center; }
.data-table td.mono   { font-family:'JetBrains Mono',monospace; font-size:.8rem; color:var(--gray-500); }

/* ── Inline elements ── */
.req-id       { display:inline-flex; align-items:center; background:var(--gray-100); border-radius:6px; padding:3px 8px; font-family:'JetBrains Mono',monospace; font-size:.75rem; font-weight:500; color:var(--gray-600); }
.dept-pill    { display:inline-flex; align-items:center; gap:5px; background:var(--primary-light); color:var(--primary-dark); border-radius:100px; padding:3px 10px; font-size:.75rem; font-weight:600; }
.badge-status { display:inline-flex; align-items:center; gap:5px; padding:4px 10px; border-radius:100px; font-size:.72rem; font-weight:700; text-transform:uppercase; letter-spacing:.4px; white-space:nowrap; }
.bs-pending   { background:#fef3c7;         color:#92400e; }
.bs-approved  { background:var(--success-light); color:#065f46; }
.bs-purchased { background:var(--primary-light); color:var(--primary-dark); }
.bs-receiving { background:var(--info-light);    color:#155e75; }
.bs-completed { background:#ede9fe;         color:#4c1d95; }
.bs-rejected  { background:var(--danger-light);  color:#991b1b; }
.item-count   { display:inline-flex; align-items:center; justify-content:center; background:var(--gray-100); color:var(--gray-600); border-radius:6px; padding:2px 8px; font-size:.78rem; font-weight:600; font-family:'JetBrains Mono',monospace; min-width:28px; }
.purchased-date { display:inline-flex; align-items:center; gap:5px; color:var(--success); font-size:.78rem; font-weight:600; }
.no-date      { color:var(--gray-300); font-size:.85rem; }
.btn-view     { display:inline-flex; align-items:center; gap:5px; padding:6px 14px; border-radius:var(--radius-sm); font-size:.78rem; font-weight:600; border:none; cursor:pointer; transition:all .2s; background:var(--primary-light); color:var(--primary); font-family:'Plus Jakarta Sans',sans-serif; }
.btn-view:hover { background:var(--primary); color:#fff; }

/* ── Professional Empty State ── */
.empty-pro {
    display:flex; flex-direction:column; align-items:center; justify-content:center;
    padding:80px 32px; text-align:center;
}
.empty-pro-icon {
    width:72px; height:72px; border-radius:20px; margin-bottom:20px;
    display:flex; align-items:center; justify-content:center; font-size:1.75rem;
    background:var(--gray-100); color:var(--gray-400);
    border:1px solid var(--gray-200);
}
.empty-pro-icon.ep-pending   { background:#fffbeb; color:#d97706; border-color:#fde68a; }
.empty-pro-icon.ep-approved  { background:var(--success-light); color:var(--success); border-color:#a7f3d0; }
.empty-pro-icon.ep-purchased { background:var(--primary-light); color:var(--primary); border-color:#bfdbfe; }
.empty-pro-icon.ep-receiving { background:var(--info-light);    color:var(--info);    border-color:#a5f3fc; }
.empty-pro-icon.ep-completed { background:#ede9fe; color:#7c3aed; border-color:#ddd6fe; }
.empty-pro-icon.ep-rejected  { background:var(--danger-light);  color:var(--danger);  border-color:#fca5a5; }
.empty-pro-title { font-size:1rem; font-weight:700; color:var(--gray-700); margin-bottom:6px; letter-spacing:-.2px; }
.empty-pro-sub   { font-size:.83rem; color:var(--gray-400); max-width:300px; line-height:1.65; }

/* ── Modal shell ── */
.modal-content    { border:none; border-radius:var(--radius-lg); box-shadow:var(--shadow-lg); font-family:'Plus Jakarta Sans',sans-serif; overflow:hidden; }
.modal-header     { background:linear-gradient(135deg,#0f172a 0%,#1e3a5f 100%); padding:20px 24px; border-bottom:none; display:flex; align-items:flex-start; gap:12px; }
.modal-title-wrap { flex:1; min-width:0; }
.modal-title      { color:#fff; font-size:1rem; font-weight:700; display:flex; align-items:center; gap:8px; margin-bottom:8px; }
.modal-meta       { display:flex; align-items:center; gap:8px; flex-wrap:wrap; }
.modal-meta-chip  { background:rgba(255,255,255,.15); color:rgba(255,255,255,.9); border-radius:6px; padding:3px 10px; font-size:.72rem; font-weight:600; }
.btn-close-custom { background:rgba(255,255,255,.15); border:none; border-radius:8px; width:32px; height:32px; flex-shrink:0; display:flex; align-items:center; justify-content:center; color:#fff; cursor:pointer; transition:background .2s; margin-top:2px; }
.btn-close-custom:hover { background:rgba(255,255,255,.28); }
.modal-body       { padding:24px; background:var(--gray-50); }

/* ── Status banners (modal) ── */
.modal-banner-msg { display:flex; align-items:flex-start; gap:12px; padding:14px 16px; border-radius:var(--radius); margin-bottom:20px; font-size:.875rem; }
.modal-banner-msg i      { font-size:1.2rem; flex-shrink:0; margin-top:1px; }
.modal-banner-msg strong { font-weight:700; display:block; margin-bottom:2px; }
.modal-banner-msg small  { opacity:.8; font-size:.8rem; }
.mb-approved  { background:var(--success-light); color:#065f46; border:1px solid #a7f3d0; }
.mb-pending   { background:#fffbeb;  color:#92400e; border:1px solid #fde68a; }
.mb-rejected  { background:var(--danger-light);  color:#991b1b; border:1px solid #fca5a5; }
.mb-receiving { background:var(--info-light);    color:#155e75; border:1px solid #a5f3fc; }
.mb-completed { background:#ede9fe;  color:#4c1d95; border:1px solid #ddd6fe; }
.mb-waiting   { background:#fff7ed;  color:#7c2d12; border:1px solid #fed7aa; border-left:4px solid var(--orange); }
.mb-info      { background:var(--primary-light); color:var(--primary-dark); border:1px solid #bfdbfe; }
.delivery-chip { display:inline-flex; align-items:center; gap:5px; background:rgba(255,255,255,.7); border:1px solid rgba(0,0,0,.1); border-radius:6px; padding:3px 10px; font-size:.75rem; font-weight:600; }

/* ── Action selector (pending modal) ── */
.action-selector { background:#fff; border-radius:var(--radius); border:1px solid var(--gray-200); padding:16px 18px; margin-bottom:20px; transition:all .3s; }
.action-selector label  { font-size:.72rem; font-weight:700; text-transform:uppercase; letter-spacing:.5px; color:var(--gray-500); margin-bottom:8px; display:block; }
.action-selector select { width:100%; padding:10px 14px; border:1px solid var(--gray-200); border-radius:var(--radius-sm); font-family:'Plus Jakarta Sans',sans-serif; font-size:.875rem; font-weight:500; color:var(--gray-700); background:var(--gray-50); cursor:pointer; transition:all .2s; appearance:auto; }
.action-selector select:focus { outline:none; border-color:var(--primary); box-shadow:0 0 0 3px rgba(26,86,219,.12); background:#fff; }
.action-selector.is-approve { border-color:var(--success); background:var(--success-light); }
.action-selector.is-reject  { border-color:var(--danger);  background:var(--danger-light); }
.hint-text { font-size:.75rem; color:var(--gray-400); margin-top:6px; }

/* ── Items card (view modal) ── */
.items-card        { background:#fff; border-radius:var(--radius); border:1px solid var(--gray-200); overflow:hidden; margin-bottom:20px; box-shadow:var(--shadow-sm); }
.items-card-header { background:var(--gray-50); padding:10px 16px; border-bottom:1px solid var(--gray-100); font-size:.72rem; font-weight:700; text-transform:uppercase; letter-spacing:.5px; color:var(--gray-500); display:flex; align-items:center; gap:6px; }
.modal-table { width:100%; border-collapse:collapse; }
.modal-table th { background:var(--gray-50); color:var(--gray-500); font-size:.68rem; font-weight:700; text-transform:uppercase; letter-spacing:.5px; padding:10px 14px; border-bottom:1px solid var(--gray-100); text-align:left; white-space:nowrap; }
.modal-table td { padding:11px 14px; border-bottom:1px solid var(--gray-100); font-size:.83rem; color:var(--gray-700); vertical-align:middle; }
.modal-table tbody tr:last-child td { border-bottom:none; }
.modal-table tbody tr:hover { background:#fafafa; }
.qty-input { width:84px; padding:6px 10px; border:1px solid var(--gray-200); border-radius:var(--radius-sm); font-family:'JetBrains Mono',monospace; font-size:.8rem; text-align:right; transition:all .2s; background:var(--gray-50); }
.qty-input:focus   { outline:none; border-color:var(--primary); box-shadow:0 0 0 2px rgba(26,86,219,.12); background:#fff; }
.qty-input[readonly],
.qty-input:disabled { background:var(--gray-100); color:var(--gray-400); cursor:not-allowed; }
.locked-icon { font-size:.68rem; color:var(--gray-400); margin-left:4px; vertical-align:middle; }

/* ── Modal footer ── */
.modal-footer-btns { display:flex; align-items:center; justify-content:flex-end; gap:10px; flex-wrap:wrap; padding-top:16px; border-top:1px solid var(--gray-200); }
.btn-approve { background:var(--success); color:#fff; border:none; border-radius:var(--radius-sm); padding:10px 22px; font-size:.875rem; font-weight:700; font-family:'Plus Jakarta Sans',sans-serif; cursor:pointer; display:inline-flex; align-items:center; gap:6px; transition:all .2s; }
.btn-approve:hover { background:#059669; transform:translateY(-1px); box-shadow:0 4px 12px rgba(14,159,110,.3); }
.btn-reject  { background:var(--danger);  color:#fff; border:none; border-radius:var(--radius-sm); padding:10px 22px; font-size:.875rem; font-weight:700; font-family:'Plus Jakarta Sans',sans-serif; cursor:pointer; display:inline-flex; align-items:center; gap:6px; transition:all .2s; }
.btn-reject:hover  { background:#c81e1e;  transform:translateY(-1px); box-shadow:0 4px 12px rgba(224,36,36,.3); }
.btn-cancel-modal  { background:var(--gray-100); color:var(--gray-600); border:1px solid var(--gray-200); border-radius:var(--radius-sm); padding:10px 16px; font-size:.875rem; font-weight:600; font-family:'Plus Jakarta Sans',sans-serif; cursor:pointer; display:inline-flex; align-items:center; gap:6px; transition:all .2s; }
.btn-cancel-modal:hover { background:var(--gray-200); }

/* ── Bulk Purchase Modal ── */
.bulk-summary-bar { display:flex; align-items:center; background:var(--gray-800); border-radius:var(--radius); overflow:hidden; margin-bottom:16px; }
.bsb-cell { flex:1; text-align:center; padding:14px 10px; border-right:1px solid rgba(255,255,255,.1); }
.bsb-cell:last-child { border-right:none; }
.bsb-val { font-family:'JetBrains Mono',monospace; font-size:1.4rem; font-weight:700; color:#fff; display:block; line-height:1; }
.bsb-lbl { font-size:.65rem; text-transform:uppercase; letter-spacing:.6px; color:rgba(255,255,255,.45); margin-top:4px; display:block; }
.bulk-req-group  { margin-bottom:12px; border:1px solid var(--gray-200); border-radius:var(--radius-sm); overflow:hidden; }
.bulk-req-header { background:var(--gray-800); color:#fff; padding:10px 16px; font-size:.8rem; font-weight:700; display:flex; align-items:center; gap:8px; }
.bulk-req-header .r-id   { font-family:'JetBrains Mono',monospace; font-size:.75rem; opacity:.7; }
.bulk-req-header .r-dept { background:rgba(255,255,255,.18); border-radius:4px; padding:2px 9px; font-size:.72rem; }
.bulk-req-header .r-date { margin-left:auto; opacity:.45; font-size:.7rem; font-weight:400; }
.bulk-req-header .r-qty  { background:rgba(14,159,110,.35); border-radius:4px; padding:2px 8px; font-size:.7rem; font-family:'JetBrains Mono',monospace; }
.bulk-items-tbl { width:100%; border-collapse:collapse; background:#fff; }
.bulk-items-tbl thead th { background:var(--gray-50); color:var(--gray-500); font-size:.67rem; font-weight:700; text-transform:uppercase; letter-spacing:.5px; padding:8px 14px; border-bottom:1px solid var(--gray-100); }
.bulk-items-tbl tbody td { padding:10px 14px; border-bottom:1px solid var(--gray-100); font-size:.83rem; color:var(--gray-700); }
.bulk-items-tbl tbody tr:last-child td { border-bottom:none; }
.bulk-items-tbl tbody tr:hover { background:var(--gray-50); }
.bulk-items-tbl tfoot td  { background:var(--primary-light); color:var(--primary-dark); padding:7px 14px; font-size:.75rem; font-weight:600; }
.approved-chip  { display:inline-flex; align-items:center; background:var(--success-light); color:#065f46; border-radius:6px; padding:2px 9px; font-size:.78rem; font-weight:700; font-family:'JetBrains Mono',monospace; }
.btn-confirm-bulk { background:linear-gradient(135deg,var(--primary) 0%,var(--primary-dark) 100%); color:#fff; border:none; border-radius:var(--radius-sm); padding:11px 26px; font-size:.9rem; font-weight:700; font-family:'Plus Jakarta Sans',sans-serif; cursor:pointer; display:inline-flex; align-items:center; gap:8px; transition:all .2s; box-shadow:0 2px 8px rgba(26,86,219,.25); }
.btn-confirm-bulk:hover { transform:translateY(-1px); box-shadow:0 6px 18px rgba(26,86,219,.35); }

/* ── Sidebar / Toggle ── */
.sidebar-toggle  { display:none; background:#fff; border:1px solid var(--gray-200); border-radius:var(--radius-sm); padding:8px 12px; cursor:pointer; color:var(--gray-700); font-size:.875rem; font-weight:600; align-items:center; gap:6px; box-shadow:var(--shadow-sm); margin-bottom:10px; }
.sidebar-overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,.4); z-index:999; backdrop-filter:blur(2px); }
.sidebar-overlay.show { display:block; }

/* ── Responsive ── */
@media(max-width:1024px) { .main-content { padding:20px; } .stats-row { grid-template-columns:repeat(3,1fr); } }
@media(max-width:768px) {
    :root { --sidebar-w:0px; }
    .main-sidebar { transform:translateX(-260px); width:260px; transition:transform .25s; }
    .main-sidebar.open { transform:translateX(0); }
    .main-content { margin-left:0; padding:16px; }
    .page-header { flex-direction:column; gap:10px; }
    .page-header-left h1 { font-size:1.25rem; }
    .stats-row { grid-template-columns:repeat(2,1fr); gap:10px; }
    .stat-number { font-size:1.5rem; }
    .sidebar-toggle { display:inline-flex; }
    .purchase-action-bar { flex-direction:column; align-items:flex-start; padding:14px 16px; }
    .pab-btn { width:100%; justify-content:center; }
    .data-table thead th:nth-child(3),
    .data-table tbody td:nth-child(3) { display:none; }
    .modal-dialog { margin:8px; max-width:calc(100vw - 16px); }
    .qty-input { width:70px; }
    .modal-table th, .modal-table td,
    .bulk-items-tbl th, .bulk-items-tbl td { padding:8px 10px; }
    .modal-footer-btns { justify-content:stretch; }
    .modal-footer-btns > * { flex:1; justify-content:center; }
    .bulk-summary-bar { flex-wrap:wrap; }
    .bsb-cell { min-width:33%; }
}
@media(max-width:480px) {
    .stats-row { grid-template-columns:repeat(2,1fr); }
    .stat-number { font-size:1.4rem; }
    .data-table thead th:nth-child(7),
    .data-table tbody td:nth-child(7) { display:none; }
}
</style>
</head>
<body>

<div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>
<div class="main-sidebar" id="mainSidebar">
    <?php include 'inventory_sidebar.php'; ?>
</div>

<div class="main-content">

    <!-- ── Page Header ── -->
    <div class="page-header">
        <div class="page-header-left">
            <button class="sidebar-toggle" onclick="openSidebar()"><i class="bi bi-list"></i> Menu</button>
            <div class="page-badge"><i class="bi bi-bag-check"></i> Purchase Management</div>
            <h1>Department Requests</h1>
            <p>Review, approve, and process departmental purchase orders</p>
        </div>
        <?php if (in_array($statusFilter, ['purchased','receiving'])): ?>
        <a href="supplier_portal.php" class="supplier-link" target="_blank">
            <i class="bi bi-shop"></i> Supplier Portal
        </a>
        <?php endif; ?>
    </div>

    <!-- ── Alerts ── -->
    <?php if ($errorMessage): ?>
    <div class="alert-custom alert-danger-custom"><i class="bi bi-exclamation-triangle-fill"></i><span><?= htmlspecialchars($errorMessage) ?></span></div>
    <?php endif; ?>
    <?php if ($successMessage): ?>
    <div class="alert-custom alert-success-custom"><i class="bi bi-check-circle-fill"></i><span><?= htmlspecialchars($successMessage) ?></span></div>
    <?php endif; ?>

    <!-- ── Stat Cards ── -->
    <div class="stats-row">
        <?php
        $statDefs = ['pending'=>'sc-pending','approved'=>'sc-approved','purchased'=>'sc-purchased','receiving'=>'sc-receiving','completed'=>'sc-completed','rejected'=>'sc-rejected'];
        foreach ($statDefs as $sKey => $cls):
            $isActive = ($statusFilter === $sKey) ? 'active' : '';
        ?>
        <a href="?status=<?= $sKey ?>&search_dept=<?= urlencode($searchDept) ?>" class="stat-card <?= $cls ?> <?= $isActive ?>">
            <div class="stat-label"><?= ucfirst($sKey) ?></div>
            <div class="stat-number"><?= $counts[ucfirst($sKey)] ?? 0 ?></div>
        </a>
        <?php endforeach; ?>
    </div>

    <!-- ── Purchase Action Bar (only when approved requests exist) ── -->
    <?php if ($approvedReqCount > 0): ?>
    <div class="purchase-action-bar">
        <div class="pab-left">
            <div class="pab-icon"><i class="bi bi-cart-check-fill"></i></div>
            <div class="pab-info">
                <span class="pab-title">Purchase All Approved Items</span>
                <span class="pab-sub">
                    <span class="pab-stat"><i class="bi bi-file-earmark-check"></i><?= $approvedReqCount ?> request<?= $approvedReqCount!==1?'s':'' ?></span>
                    <span class="pab-dot">&bull;</span>
                    <span class="pab-stat"><i class="bi bi-boxes"></i><?= $approvedItemCount ?> item type<?= $approvedItemCount!==1?'s':'' ?></span>
                    <span class="pab-dot">&bull;</span>
                    <span class="pab-stat"><i class="bi bi-stack"></i><?= $approvedQtyTotal ?> total approved units</span>
                </span>
            </div>
        </div>
        <button class="pab-btn" onclick="openBulkModal()">
            <i class="bi bi-bag-check-fill"></i>
            Purchase All Approved
            <span class="pab-count"><?= $approvedReqCount ?></span>
        </button>
    </div>
    <?php endif; ?>

    <!-- ── Panel: table or empty state, NO header bar ── -->
    <div class="panel">
        <?php if (empty($requests)):
            $emptyCfg = [
                'pending'   => ['bi-hourglass-split', 'ep-pending',   'No pending requests',    'All department requests have been reviewed, or none have been submitted yet.'],
                'approved'  => ['bi-check-circle',    'ep-approved',  'No approved requests',   'Approve pending requests first to see them here.'],
                'purchased' => ['bi-bag-check-fill',  'ep-purchased', 'No purchased orders',    'Once approved requests are sent to the supplier, they will appear here.'],
                'receiving' => ['bi-box-seam',        'ep-receiving', 'Nothing in receiving',   'Orders sent back by the supplier will show here during receiving.'],
                'completed' => ['bi-patch-check-fill','ep-completed', 'No completed orders',    'Fully received and completed orders will be archived here.'],
                'rejected'  => ['bi-x-circle',        'ep-rejected',  'No rejected requests',   'Rejected department requests will be listed here for reference.'],
            ];
            [$eIcon, $eClass, $eTitle, $eSub] = $emptyCfg[$statusFilter] ?? ['bi-inbox','','No records','Nothing to display.'];
        ?>
        <div class="empty-pro">
            <div class="empty-pro-icon <?= $eClass ?>">
                <i class="bi <?= $eIcon ?>"></i>
            </div>
            <div class="empty-pro-title"><?= $eTitle ?></div>
            <div class="empty-pro-sub"><?= $eSub ?></div>
        </div>

        <?php else: ?>
        <div style="overflow-x:auto;">
        <table class="data-table">
            <thead>
                <tr>
                    <th style="width:70px;">ID</th>
                    <th>Department</th>
                    <th>Requested By</th>
                    <th class="center">Items</th>
                    <th>Status</th>
                    <th>Requested At</th>
                    <th>Purchased At</th>
                    <th class="center" style="width:90px;">Action</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($requests as $r):
                $stmtI = $pdo->prepare("SELECT * FROM department_request_items WHERE request_id=? ORDER BY id ASC");
                $stmtI->execute([$r['id']]);
                $itemsArr  = $stmtI->fetchAll(PDO::FETCH_ASSOC);
                $statusRaw = strtolower(trim($r['status']));
                $badgeMap  = [
                    'pending'   => ['bs-pending',   'bi-hourglass-split'],
                    'approved'  => ['bs-approved',  'bi-check-circle'],
                    'purchased' => ['bs-purchased', 'bi-bag-check-fill'],
                    'receiving' => ['bs-receiving', 'bi-box-seam'],
                    'completed' => ['bs-completed', 'bi-patch-check-fill'],
                    'rejected'  => ['bs-rejected',  'bi-x-circle'],
                ];
                [$bClass, $bIcon] = $badgeMap[$statusRaw] ?? ['','bi-circle'];
            ?>
            <tr>
                <td><span class="req-id">#<?= str_pad($r['id'],4,'0',STR_PAD_LEFT) ?></span></td>
                <td><span class="dept-pill"><i class="bi bi-building"></i><?= htmlspecialchars($r['department']) ?></span></td>
                <td class="mono"><?= htmlspecialchars($r['user_id']) ?></td>
                <td class="center"><span class="item-count"><?= count($itemsArr) ?></span></td>
                <td><span class="badge-status <?= $bClass ?>"><i class="bi <?= $bIcon ?>"></i> <?= ucfirst($statusRaw) ?></span></td>
                <td class="mono" style="font-size:.78rem;">
                    <?= date('M d, Y', strtotime($r['created_at'])) ?>
                    <br><span style="color:var(--gray-400);"><?= date('h:i A', strtotime($r['created_at'])) ?></span>
                </td>
                <td>
                    <?php if (!empty($r['purchased_at'])): ?>
                    <span class="purchased-date"><i class="bi bi-bag-check-fill"></i><?= date('M d, Y', strtotime($r['purchased_at'])) ?></span>
                    <?php else: ?><span class="no-date">—</span><?php endif; ?>
                </td>
                <td class="center">
                    <button class="btn-view view-items-btn"
                        data-id="<?= $r['id'] ?>"
                        data-dept="<?= htmlspecialchars($r['department']) ?>"
                        data-status="<?= htmlspecialchars($statusRaw) ?>"
                        data-purchased="<?= htmlspecialchars($r['purchased_at'] ?? '') ?>"
                        data-paymenttype="<?= htmlspecialchars($r['payment_type'] ?? '') ?>"
                        data-estdelivery="<?= htmlspecialchars($r['estimated_delivery'] ?? '') ?>"
                        data-items='<?= htmlspecialchars(json_encode($itemsArr, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8') ?>'>
                        <i class="bi bi-eye"></i> View
                    </button>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php endif; ?>
    </div><!-- /panel -->

</div><!-- /main-content -->


<!-- ══════════════════════════════
     VIEW REQUEST MODAL
══════════════════════════════ -->
<div class="modal fade" id="viewModal" tabindex="-1">
<div class="modal-dialog modal-lg modal-dialog-scrollable">
<div class="modal-content">
    <div class="modal-header">
        <div class="modal-title-wrap">
            <div class="modal-title"><i class="bi bi-file-earmark-text"></i><span id="modalTitle">Request Details</span></div>
            <div class="modal-meta" id="modalMeta"></div>
        </div>
        <button type="button" class="btn-close-custom" data-bs-dismiss="modal"><i class="bi bi-x-lg"></i></button>
    </div>
    <div class="modal-body" id="modalBodyContent"></div>
</div>
</div>
</div>


<!-- ══════════════════════════════
     BULK PURCHASE MODAL
══════════════════════════════ -->
<div class="modal fade" id="bulkModal" tabindex="-1">
<div class="modal-dialog modal-xl modal-dialog-scrollable">
<div class="modal-content">
    <div class="modal-header">
        <div class="modal-title-wrap">
            <div class="modal-title"><i class="bi bi-cart-check-fill"></i> Purchase All Approved Items</div>
            <div class="modal-meta">
                <span class="modal-meta-chip"><i class="bi bi-file-earmark-text" style="margin-right:3px;"></i><?= $approvedReqCount ?> request<?= $approvedReqCount!==1?'s':'' ?></span>
                <span class="modal-meta-chip"><i class="bi bi-boxes" style="margin-right:3px;"></i><?= $approvedItemCount ?> item type<?= $approvedItemCount!==1?'s':'' ?></span>
                <span class="modal-meta-chip" style="background:rgba(14,159,110,.3);color:#d1fae5;"><?= $approvedQtyTotal ?> total units</span>
            </div>
        </div>
        <button type="button" class="btn-close-custom" data-bs-dismiss="modal"><i class="bi bi-x-lg"></i></button>
    </div>
    <div class="modal-body">
        <?php if (empty($allApprovedGrouped)): ?>
        <div class="empty-pro" style="padding:52px 20px;">
            <div class="empty-pro-icon ep-approved"><i class="bi bi-check-circle"></i></div>
            <div class="empty-pro-title">No approved requests</div>
            <div class="empty-pro-sub">Approve pending requests first, then come back here to bulk-purchase them.</div>
        </div>
        <?php else: ?>
        <div class="bulk-summary-bar">
            <div class="bsb-cell"><span class="bsb-val"><?= $approvedReqCount ?></span><span class="bsb-lbl">Requests</span></div>
            <div class="bsb-cell"><span class="bsb-val"><?= $approvedItemCount ?></span><span class="bsb-lbl">Item Types</span></div>
            <div class="bsb-cell"><span class="bsb-val"><?= $approvedQtyTotal ?></span><span class="bsb-lbl">Total Units</span></div>
            <div class="bsb-cell" style="background:rgba(13,148,136,.18);"><span class="bsb-val" style="font-size:.85rem;">Supplier Portal</span><span class="bsb-lbl">Sets prices after confirm</span></div>
        </div>
        <div class="modal-banner-msg mb-info" style="margin-bottom:18px;">
            <i class="bi bi-info-circle-fill"></i>
            <div><strong>How this works</strong><small>All requests below will be marked as <strong>Purchased</strong> and forwarded to the Supplier Portal, where the supplier assigns unit prices before shipping back to the hospital.</small></div>
        </div>
        <form method="post" id="bulkPurchaseForm">
            <input type="hidden" name="action" value="bulk_purchase">
            <?php foreach ($allApprovedGrouped as $rid => $gitems):
                $gDept   = htmlspecialchars($gitems[0]['department']);
                $gDate   = date('M d, Y', strtotime($gitems[0]['req_date']));
                $gReqQty = array_sum(array_column($gitems, 'approved_quantity'));
            ?>
            <input type="hidden" name="request_ids[]" value="<?= (int)$rid ?>">
            <div class="bulk-req-group">
                <div class="bulk-req-header">
                    <i class="bi bi-file-earmark-check"></i>
                    <span class="r-id">#<?= str_pad($rid,4,'0',STR_PAD_LEFT) ?></span>
                    <span class="r-dept"><?= $gDept ?></span>
                    <span class="r-qty"><?= $gReqQty ?> units</span>
                    <span class="r-date"><?= $gDate ?></span>
                </div>
                <table class="bulk-items-tbl">
                    <thead><tr>
                        <th style="width:45%;">Item Name</th>
                        <th style="text-align:center;">Requested</th>
                        <th style="text-align:center;">Approved</th>
                        <th>Unit</th>
                    </tr></thead>
                    <tbody>
                    <?php foreach ($gitems as $gi): ?>
                    <tr>
                        <td style="font-weight:600;color:var(--gray-800);"><?= htmlspecialchars($gi['item_name']) ?></td>
                        <td style="text-align:center;font-family:'JetBrains Mono',monospace;color:var(--gray-500);"><?= (int)$gi['quantity'] ?></td>
                        <td style="text-align:center;"><span class="approved-chip"><?= (int)$gi['approved_quantity'] ?></span></td>
                        <td style="color:var(--gray-500);font-size:.8rem;"><?= htmlspecialchars($gi['unit']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                    <tfoot><tr>
                        <td colspan="2" style="text-align:right;"><i class="bi bi-check-circle-fill" style="color:var(--success);margin-right:4px;"></i>Total approved units:</td>
                        <td style="text-align:center;font-family:'JetBrains Mono',monospace;font-weight:700;"><?= $gReqQty ?></td>
                        <td></td>
                    </tr></tfoot>
                </table>
            </div>
            <?php endforeach; ?>
            <div class="modal-footer-btns">
                <button type="button" class="btn-cancel-modal" data-bs-dismiss="modal"><i class="bi bi-x"></i> Cancel</button>
                <button type="submit" class="btn-confirm-bulk"><i class="bi bi-bag-check-fill"></i> Confirm &amp; Send to Supplier</button>
            </div>
        </form>
        <?php endif; ?>
    </div>
</div>
</div>
</div>


<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
function openSidebar()  { document.getElementById('mainSidebar').classList.add('open');    document.getElementById('sidebarOverlay').classList.add('show'); }
function closeSidebar() { document.getElementById('mainSidebar').classList.remove('open'); document.getElementById('sidebarOverlay').classList.remove('show'); }
function openBulkModal(){ new bootstrap.Modal(document.getElementById('bulkModal')).show(); }

function pad(n){ return String(n).padStart(4,'0'); }
function fc(n) { return '₱' + parseFloat(n||0).toLocaleString('en-PH',{minimumFractionDigits:2,maximumFractionDigits:2}); }

document.querySelectorAll('.view-items-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        const items       = JSON.parse(btn.dataset.items || '[]');
        const _st         = (btn.dataset.status || '').toLowerCase().trim();
        const purchased   = btn.dataset.purchased   || '';
        const dept        = btn.dataset.dept;
        const reqId       = btn.dataset.id;
        const payment     = btn.dataset.paymenttype  || '';
        const estDelivery = btn.dataset.estdelivery  || '';

        const isPending   = _st === 'pending';
        const isApproved  = _st === 'approved';
        const isPurchased = _st === 'purchased';
        const isReceiving = _st === 'receiving';
        const isCompleted = _st === 'completed';
        const isRejected  = _st === 'rejected';
        const isDone      = isPurchased || isReceiving || isCompleted;

        document.getElementById('modalTitle').textContent = `Request #${pad(reqId)} — ${dept}`;

        const scMap = {
            pending:  'background:#fef3c7;color:#92400e;',
            approved: 'background:#def7ec;color:#065f46;',
            purchased:'background:#e8f0fe;color:#1241b0;',
            receiving:'background:#e0f2fe;color:#155e75;',
            completed:'background:#ede9fe;color:#4c1d95;',
            rejected: 'background:#fde8e8;color:#991b1b;',
        };
        const sc = scMap[_st] || 'background:rgba(255,255,255,.2);color:#fff;';

        let chips = `<span class="modal-meta-chip">${items.length} item${items.length!==1?'s':''}</span>
                     <span style="${sc}border-radius:6px;padding:3px 10px;font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.4px;">${_st.charAt(0).toUpperCase()+_st.slice(1)}</span>`;
        if (purchased)   { try { const d  = new Date(purchased);   chips += `<span class="modal-meta-chip"><i class="bi bi-calendar-check" style="margin-right:3px;"></i>${d.toLocaleDateString('en-PH',{month:'short',day:'numeric',year:'numeric'})}</span>`; } catch(e){} }
        if (estDelivery) { try { const d2 = new Date(estDelivery); chips += `<span class="modal-meta-chip" style="background:rgba(14,159,110,.25);color:#d1fae5;"><i class="bi bi-truck" style="margin-right:3px;"></i>Est. ${d2.toLocaleDateString('en-PH',{month:'short',day:'numeric',year:'numeric'})}</span>`; } catch(e){} }
        document.getElementById('modalMeta').innerHTML = chips;

        let html = `<form method="post"><input type="hidden" name="id" value="${reqId}">`;

        if (isPurchased) {
            html += `<div class="modal-banner-msg mb-waiting"><i class="bi bi-truck"></i><div>
                <strong>Sent to Supplier Portal</strong>
                <small>Awaiting price assignment and dispatch from the supplier.</small>
                <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:8px;">
                    ${payment     ? `<span class="delivery-chip"><i class="bi bi-credit-card"></i>${payment}</span>` : ''}
                    ${estDelivery ? `<span class="delivery-chip"><i class="bi bi-calendar-event"></i>Est. ${estDelivery}</span>` : ''}
                </div></div></div>`;
        } else if (isReceiving) {
            html += `<div class="modal-banner-msg mb-receiving"><i class="bi bi-box-seam"></i><div><strong>Items Being Received</strong><small>Order is being checked into inventory.</small></div></div>`;
        } else if (isCompleted) {
            html += `<div class="modal-banner-msg mb-completed"><i class="bi bi-patch-check-fill"></i><div><strong>Order Completed</strong><small>All items received and added to inventory.</small></div></div>`;
        } else if (isApproved) {
            html += `<div class="modal-banner-msg mb-approved"><i class="bi bi-check-circle-fill"></i><div><strong>Request Approved — Quantities Locked</strong><small>Use the <strong>Purchase All Approved Items</strong> bar to send all approved requests to the supplier at once.</small></div></div>`;
        } else if (isPending) {
            html += `<div class="modal-banner-msg mb-pending"><i class="bi bi-hourglass-split"></i><div><strong>Awaiting Review</strong><small>Choose an action below to approve or reject this request.</small></div></div>`;
        } else if (isRejected) {
            html += `<div class="modal-banner-msg mb-rejected"><i class="bi bi-x-circle-fill"></i><div><strong>Request Rejected</strong><small>This request was denied. No items will be purchased.</small></div></div>`;
        }

        if (isPending) {
            html += `<div class="action-selector" id="actionSection">
                <label>Select Action</label>
                <select id="actionDropdown" name="actionDropdown">
                    <option value="">— Choose an action —</option>
                    <option value="approve">✓ Approve — set approved quantities</option>
                    <option value="reject">✗ Reject — deny this request</option>
                </select>
                <div class="hint-text">Select an action to enable the fields below.</div>
            </div>`;
        }

        const locked = (isApproved || isDone) ? `<i class="bi bi-lock-fill locked-icon" title="Locked"></i>` : '';

        html += `<div class="items-card">
            <div class="items-card-header">
                <i class="bi bi-list-ul"></i> Requested Items
                <span style="margin-left:auto;font-size:.7rem;color:var(--gray-400);text-transform:none;letter-spacing:0;">${items.length} item${items.length!==1?'s':''}</span>
            </div>
            <div style="overflow-x:auto;">
            <table class="modal-table"><thead><tr>
                <th>Item Name</th>
                <th style="text-align:center;">Requested</th>
                <th style="text-align:center;">Approved ${locked}</th>
                <th>Unit</th>
                <th style="text-align:center;">Pcs/Box</th>`;
        if (isDone) html += `<th style="text-align:right;">Unit Price</th><th style="text-align:right;">Line Total</th>`;
        html += `</tr></thead><tbody>`;

        let staticTotal = 0;
        items.forEach(item => {
            const idx     = item.id;
            const approved= parseInt(item.approved_quantity) || 0;
            const unit    = item.unit        || 'pcs';
            const pcs     = item.pcs_per_box || 1;
            const price   = parseFloat(item.price       || 0);
            const lineT   = parseFloat(item.total_price || 0);
            staticTotal  += lineT;
            const qAttr   = isPending ? `disabled style="background:var(--gray-100);"` : `readonly style="background:var(--gray-100);"`;

            html += `<tr>
                <td style="font-weight:600;color:var(--gray-800);">${item.item_name}</td>
                <td style="text-align:center;font-family:'JetBrains Mono',monospace;color:var(--gray-500);">${item.quantity}</td>
                <td style="text-align:center;"><input type="number" class="qty-input" name="approved_quantity[${idx}]" value="${approved}" min="0" max="${item.quantity}" ${qAttr}></td>
                <td>${unit}<input type="hidden" name="unit[${idx}]" value="${unit}"></td>
                <td style="text-align:center;font-family:'JetBrains Mono',monospace;">${pcs}<input type="hidden" name="pcs_per_box[${idx}]" value="${pcs}"></td>`;
            if (isDone) {
                html += `<td style="text-align:right;font-family:'JetBrains Mono',monospace;font-size:.82rem;color:var(--gray-600);">${price>0?fc(price):'<span style="color:var(--gray-300);">—</span>'}</td>
                         <td style="text-align:right;font-family:'JetBrains Mono',monospace;font-size:.82rem;font-weight:700;">${lineT>0?fc(lineT):'<span style="color:#f59e0b;font-size:.75rem;font-weight:600;">Pending</span>'}</td>`;
            }
            html += `</tr>`;
        });
        html += `</tbody></table></div></div>`;

        if (isDone) {
            const hasTot = staticTotal > 0;
            html += `<div style="display:flex;align-items:center;justify-content:space-between;background:${hasTot?'var(--primary-light)':'var(--gray-100)'};border-radius:var(--radius);padding:12px 18px;margin-bottom:16px;border:1px solid ${hasTot?'#bfdbfe':'var(--gray-200)'};">
                <span style="font-size:.75rem;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:${hasTot?'var(--primary)':'var(--gray-500)'};">
                    <i class="bi bi-receipt" style="margin-right:5px;"></i>Total Amount
                </span>
                <span style="font-family:'JetBrains Mono',monospace;font-size:1.1rem;font-weight:700;color:${hasTot?'var(--primary-dark)':'var(--gray-400)'};">
                    ${hasTot ? fc(staticTotal) : 'Awaiting supplier pricing'}
                </span>
            </div>`;
        }

        html += `<div class="modal-footer-btns">
            <button type="button" class="btn-cancel-modal" data-bs-dismiss="modal"><i class="bi bi-x"></i> Close</button>`;
        if (isPending) {
            html += `<button type="submit" name="action" value="approve" class="btn-approve" id="btnApprove" style="display:none;"><i class="bi bi-check-circle"></i> Approve Request</button>
                     <button type="submit" name="action" value="reject"  class="btn-reject"  id="btnReject"  style="display:none;"><i class="bi bi-x-circle"></i> Reject Request</button>`;
        }
        html += `</div></form>`;

        document.getElementById('modalBodyContent').innerHTML = html;

        if (isPending) {
            const dd   = document.getElementById('actionDropdown');
            const sec  = document.getElementById('actionSection');
            const bApp = document.getElementById('btnApprove');
            const bRej = document.getElementById('btnReject');
            const qtys = document.querySelectorAll('#modalBodyContent .qty-input');
            dd.addEventListener('change', function() {
                sec.classList.remove('is-approve','is-reject');
                if (this.value === 'approve') {
                    qtys.forEach(i => { i.disabled=false; i.style.background='#fff'; i.style.borderColor='var(--success)'; });
                    sec.classList.add('is-approve'); bApp.style.display='inline-flex'; bRej.style.display='none';
                } else if (this.value === 'reject') {
                    qtys.forEach(i => { i.disabled=true; i.value=0; i.style.background='var(--danger-light)'; i.style.borderColor='var(--danger)'; });
                    sec.classList.add('is-reject'); bApp.style.display='none'; bRej.style.display='inline-flex';
                } else {
                    qtys.forEach(i => { i.disabled=true; i.style.background='var(--gray-100)'; i.style.borderColor=''; });
                    bApp.style.display='none'; bRej.style.display='none';
                }
            });
        }

        new bootstrap.Modal(document.getElementById('viewModal')).show();
    });
});
</script>
</body>
</html>