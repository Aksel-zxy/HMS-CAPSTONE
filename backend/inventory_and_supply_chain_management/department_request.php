<?php
include '../../SQL/config.php';

// Ensure variables have safe defaults in case earlier logic didn't run
if (!isset($statusFilter))   $statusFilter   = strtolower(trim($_GET['status'] ?? 'pending'));
if (!isset($searchDept))     $searchDept     = trim($_GET['search_dept'] ?? '');
if (!isset($errorMessage))   $errorMessage   = '';
if (!isset($successMessage)) $successMessage = '';
if (!isset($requests))       $requests       = [];

/* =====================================================
   SAFE DEFAULTS (PREVENT UNDEFINED ERRORS)
=====================================================*/
$statusFilter   = strtolower(trim($_GET['status'] ?? 'pending'));
$searchDept     = trim($_GET['search_dept'] ?? '');
$requests       = [];
$counts         = [];
$errorMessage   = '';
$successMessage = '';

// Helper: returns the correctly-cased status string.
// Works for both ENUM and VARCHAR columns by checking existing rows
// or falling back to ucfirst().
function findDbEnumMatch(PDO $pdo, string $table, string $column, string $desired): string {
    $sql  = "SELECT COLUMN_TYPE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$table, $column]);
    $colType = $stmt->fetchColumn();

    // If not found or not an ENUM, return ucfirst (handles VARCHAR)
    if (!$colType || !preg_match("/^enum\(/i", $colType)) {
        return ucfirst($desired);
    }

    // Handle ENUM columns
    if (preg_match("/^enum\((.*)\)$/i", $colType, $m)) {
        $csv  = $m[1];
        $vals = str_getcsv($csv, ',', "'");
        foreach ($vals as $v) {
            if (strcasecmp($v, $desired) === 0) return $v;
        }
        foreach ($vals as $v) {
            if (strcasecmp($v, ucfirst($desired)) === 0) return $v;
        }
        return $vals[0] ?? ucfirst($desired);
    }

    return ucfirst($desired);
}

/* =====================================================
   HANDLE ACTIONS (Approve / Reject / Purchase)
=====================================================*/
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

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

            /* ================= APPROVE ================= */
            if ($_POST['action'] === 'approve' && $currentStatus === 'pending') {

                $approved_quantities = $_POST['approved_quantity'] ?? [];

                foreach ($items as $item) {
                    $idx      = $item['id'];
                    $approved = isset($approved_quantities[$idx]) ? (int)$approved_quantities[$idx] : 0;
                    if ($approved < 0)                $approved = 0;
                    if ($approved > $item['quantity']) $approved = $item['quantity'];

                    $pdo->prepare("UPDATE department_request_items SET approved_quantity=? WHERE id=?")
                        ->execute([$approved, $idx]);
                }

                $stmtTotal = $pdo->prepare("SELECT SUM(approved_quantity) FROM department_request_items WHERE request_id=?");
                $stmtTotal->execute([$request_id]);
                $totalApproved = (int)($stmtTotal->fetchColumn() ?? 0);

                if ($totalApproved <= 0) {
                    header("Location: department_request.php?status=pending&error=no_approved");
                    exit;
                }

                $dbStatus = findDbEnumMatch($pdo, 'department_request', 'status', 'approved');
                $pdo->prepare("UPDATE department_request SET status=?, total_approved_items=? WHERE id=?")
                    ->execute([$dbStatus, $totalApproved, $request_id]);

                header("Location: department_request.php?status=approved&success=approved");
                exit;
            }

            /* ================= REJECT ================= */
            if ($_POST['action'] === 'reject' && $currentStatus === 'pending') {

                $dbStatus = findDbEnumMatch($pdo, 'department_request', 'status', 'rejected');
                $pdo->prepare("UPDATE department_request SET status=? WHERE id=?")
                    ->execute([$dbStatus, $request_id]);

                header("Location: department_request.php?status=rejected&success=rejected");
                exit;
            }

            /* ================= PURCHASE ================= */
            if ($_POST['action'] === 'purchase' && $currentStatus === 'approved') {

                $prices       = $_POST['price']        ?? [];
                $units        = $_POST['unit']         ?? [];
                $pcsArr       = $_POST['pcs_per_box']  ?? [];
                $payment_type = $_POST['payment_type'] ?? 'Direct';

                foreach ($items as $item) {
                    $item_id      = $item['id'];
                    $approved_qty = (int)($item['approved_quantity'] ?? 0);
                    if ($approved_qty <= 0) continue;

                    $unit  = $units[$item_id]   ?? 'pcs';
                    $pcs   = (int)($pcsArr[$item_id] ?? 1);
                    $price = (float)($prices[$item_id] ?? 0);
                    $total = $price * $approved_qty;

                    $pdo->prepare("
                        UPDATE department_request_items
                        SET price=?, total_price=?, unit=?, pcs_per_box=?
                        WHERE id=?
                    ")->execute([$price, $total, $unit, $pcs, $item_id]);
                }

                $dbStatus = findDbEnumMatch($pdo, 'department_request', 'status', 'purchased');
                $pdo->prepare("
                    UPDATE department_request
                    SET status=?, purchased_at=NOW(), payment_type=?
                    WHERE id=?
                ")->execute([$dbStatus, $payment_type, $request_id]);

                header("Location: department_request.php?status=purchased&success=purchased");
                exit;
            }
        }
    }

    header("Location: department_request.php");
    exit;
}

/* =====================================================
   LOAD STATUS COUNTS
=====================================================*/
try {
    $countsRaw = $pdo->query("
        SELECT LOWER(status) AS s, COUNT(*) AS cnt
        FROM department_request
        GROUP BY LOWER(status)
    ")->fetchAll(PDO::FETCH_ASSOC) ?? [];

    foreach ($countsRaw as $c) {
        $counts[ucfirst($c['s'])] = $c['cnt'];
    }
} catch (Exception $e) {
    $counts = [];
}

/* =====================================================
   FETCH FILTERED REQUESTS
=====================================================*/
try {
    $params = [$statusFilter];
    $sql    = "SELECT * FROM department_request WHERE LOWER(status)=?";

    if (!empty($searchDept)) {
        $sql     .= " AND department LIKE ?";
        $params[] = "%$searchDept%";
    }

    $sql .= " ORDER BY created_at DESC";

    $stmtR = $pdo->prepare($sql);
    $stmtR->execute($params);
    $requests = $stmtR->fetchAll(PDO::FETCH_ASSOC) ?? [];

} catch (Exception $e) {
    $requests = [];
}

/* =====================================================
   ALERT MESSAGES
=====================================================*/
$errorMessages = [
    'no_approved' => 'Please set at least one approved quantity greater than zero.',
];
$successMessages = [
    'approved'  => 'Request approved successfully.',
    'rejected'  => 'Request rejected successfully.',
    'purchased' => 'Purchase order confirmed successfully.',
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
/* ════════════════════════════════════
   CSS VARIABLES
════════════════════════════════════*/
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
    --purple:        #7c3aed;
    --purple-light:  #ede9fe;
    --gray-50:       #f9fafb;
    --gray-100:      #f3f4f6;
    --gray-200:      #e5e7eb;
    --gray-300:      #d1d5db;
    --gray-400:      #9ca3af;
    --gray-500:      #6b7280;
    --gray-600:      #4b5563;
    --gray-700:      #374151;
    --gray-800:      #1f2937;
    --gray-900:      #111827;
    --sidebar-w:     260px;
    --radius-sm:     6px;
    --radius:        10px;
    --radius-lg:     16px;
    --shadow-sm:     0 1px 3px rgba(0,0,0,.08), 0 1px 2px rgba(0,0,0,.04);
    --shadow:        0 4px 16px rgba(0,0,0,.08), 0 2px 6px rgba(0,0,0,.04);
    --shadow-lg:     0 10px 40px rgba(0,0,0,.12), 0 4px 12px rgba(0,0,0,.06);
}

*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

body {
    font-family: 'Plus Jakarta Sans', sans-serif;
    background: var(--gray-50);
    color: var(--gray-800);
    font-size: 14px;
    line-height: 1.6;
}

.main-sidebar {
    position: fixed;
    left: 0; top: 0; bottom: 0;
    width: var(--sidebar-w);
    z-index: 1000;
    overflow-y: auto;
}
.main-content {
    margin-left: var(--sidebar-w);
    min-height: 100vh;
    padding: 28px 32px;
    transition: margin-left .25s;
}

/* ── Page Header ── */
.page-header {
    display: flex; align-items: flex-start; justify-content: space-between;
    flex-wrap: wrap; gap: 16px; margin-bottom: 28px;
}
.page-header-left h1 {
    font-size: 1.6rem; font-weight: 800; color: var(--gray-900); letter-spacing: -.4px;
}
.page-header-left p { color: var(--gray-500); margin-top: 2px; font-size: .85rem; }
.page-badge {
    display: inline-flex; align-items: center; gap: 6px;
    background: var(--primary-light); color: var(--primary);
    font-size: .72rem; font-weight: 700; text-transform: uppercase;
    letter-spacing: .6px; padding: 4px 10px; border-radius: 100px; margin-bottom: 6px;
}

/* ── Alerts ── */
.alert-custom {
    display: flex; align-items: center; gap: 10px;
    padding: 14px 18px; border-radius: var(--radius); margin-bottom: 20px;
    font-size: .875rem; font-weight: 500; animation: slideDown .25s ease;
}
@keyframes slideDown {
    from { opacity: 0; transform: translateY(-8px); }
    to   { opacity: 1; transform: translateY(0); }
}
.alert-danger-custom  { background: var(--danger-light);  color: #991b1b; border: 1px solid #fca5a5; }
.alert-success-custom { background: var(--success-light); color: #065f46; border: 1px solid #6ee7b7; }

/* ── Stat Cards ── */
.stats-row {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(130px, 1fr));
    gap: 14px; margin-bottom: 24px;
}
.stat-card {
    background: #fff; border-radius: var(--radius); padding: 16px 18px;
    box-shadow: var(--shadow-sm); border: 1px solid var(--gray-200);
    cursor: pointer; transition: all .2s; text-decoration: none;
    display: block; position: relative; overflow: hidden;
}
.stat-card::before {
    content: ''; position: absolute; top: 0; left: 0; right: 0; height: 3px;
    border-radius: var(--radius) var(--radius) 0 0;
}
.stat-card.sc-pending::before   { background: #f59e0b; }
.stat-card.sc-approved::before  { background: var(--success); }
.stat-card.sc-purchased::before { background: var(--primary); }
.stat-card.sc-receiving::before { background: var(--info); }
.stat-card.sc-completed::before { background: #6366f1; }
.stat-card.sc-rejected::before  { background: var(--danger); }
.stat-card:hover { box-shadow: var(--shadow); transform: translateY(-2px); }
.stat-card.sc-pending.active    { border-color: #f59e0b; background: #fffbeb; }
.stat-card.sc-approved.active   { border-color: var(--success); background: var(--success-light); }
.stat-card.sc-purchased.active  { border-color: var(--primary); background: var(--primary-light); }
.stat-card.sc-receiving.active  { border-color: var(--info); background: var(--info-light); }
.stat-card.sc-completed.active  { border-color: #6366f1; background: #eef2ff; }
.stat-card.sc-rejected.active   { border-color: var(--danger); background: var(--danger-light); }
.stat-label { font-size: .72rem; font-weight: 700; text-transform: uppercase; letter-spacing: .5px; color: var(--gray-500); margin-bottom: 6px; }
.stat-number { font-size: 1.8rem; font-weight: 800; line-height: 1; font-family: 'JetBrains Mono', monospace; }
.sc-pending   .stat-number { color: #d97706; }
.sc-approved  .stat-number { color: var(--success); }
.sc-purchased .stat-number { color: var(--primary); }
.sc-receiving .stat-number { color: var(--info); }
.sc-completed .stat-number { color: #6366f1; }
.sc-rejected  .stat-number { color: var(--danger); }

/* ── Panel ── */
.panel { background: #fff; border-radius: var(--radius-lg); box-shadow: var(--shadow); border: 1px solid var(--gray-200); overflow: hidden; }
.panel-header {
    padding: 18px 24px; border-bottom: 1px solid var(--gray-100);
    display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px;
}
.panel-title { font-size: 1rem; font-weight: 700; color: var(--gray-800); display: flex; align-items: center; gap: 6px; }

/* ── Filter Bar ── */
.filter-bar { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
.filter-bar .form-control {
    font-size: .83rem; border-radius: var(--radius-sm); border: 1px solid var(--gray-200);
    padding: 8px 12px; background: var(--gray-50); color: var(--gray-700);
    transition: all .2s; font-family: 'Plus Jakarta Sans', sans-serif; min-width: 200px;
}
.filter-bar .form-control:focus { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(26,86,219,.12); background: #fff; outline: none; }
.btn-filter {
    background: var(--primary); color: #fff; border: none; border-radius: var(--radius-sm);
    padding: 8px 18px; font-size: .83rem; font-weight: 600;
    font-family: 'Plus Jakarta Sans', sans-serif; cursor: pointer; display: inline-flex; align-items: center; gap: 6px; transition: all .2s;
}
.btn-filter:hover { background: var(--primary-dark); }
.btn-reset {
    background: var(--gray-100); color: var(--gray-600); border: 1px solid var(--gray-200);
    border-radius: var(--radius-sm); padding: 8px 14px; font-size: .83rem; font-weight: 600;
    font-family: 'Plus Jakarta Sans', sans-serif; cursor: pointer; display: inline-flex; align-items: center; gap: 6px;
    text-decoration: none; transition: all .2s;
}
.btn-reset:hover { background: var(--gray-200); color: var(--gray-700); }

/* ── Data Table ── */
.data-table { width: 100%; border-collapse: collapse; }
.data-table thead th {
    background: var(--gray-50); color: var(--gray-500); font-size: .7rem; font-weight: 700;
    text-transform: uppercase; letter-spacing: .6px; padding: 12px 20px;
    border-bottom: 1px solid var(--gray-200); text-align: left; white-space: nowrap;
}
.data-table tbody td { padding: 14px 20px; border-bottom: 1px solid var(--gray-100); color: var(--gray-700); vertical-align: middle; font-size: .875rem; }
.data-table tbody tr:last-child td { border-bottom: none; }
.data-table tbody tr { transition: background .15s; }
.data-table tbody tr:hover { background: var(--gray-50); }
.data-table td.center { text-align: center; }
.data-table td.mono { font-family: 'JetBrains Mono', monospace; font-size: .8rem; color: var(--gray-500); }

.req-id {
    display: inline-flex; align-items: center;
    background: var(--gray-100); border-radius: 6px; padding: 3px 8px;
    font-family: 'JetBrains Mono', monospace; font-size: .75rem; font-weight: 500; color: var(--gray-600);
}
.dept-pill {
    display: inline-flex; align-items: center; gap: 5px;
    background: var(--primary-light); color: var(--primary-dark);
    border-radius: 100px; padding: 3px 10px; font-size: .75rem; font-weight: 600;
}
.badge-status {
    display: inline-flex; align-items: center; gap: 5px; padding: 4px 10px;
    border-radius: 100px; font-size: .72rem; font-weight: 700;
    text-transform: uppercase; letter-spacing: .4px; white-space: nowrap;
}
.bs-pending   { background: #fef3c7; color: #92400e; }
.bs-approved  { background: var(--success-light); color: #065f46; }
.bs-purchased { background: var(--primary-light); color: var(--primary-dark); }
.bs-receiving { background: var(--info-light); color: #155e75; }
.bs-completed { background: #ede9fe; color: #4c1d95; }
.bs-rejected  { background: var(--danger-light); color: #991b1b; }

.item-count {
    display: inline-flex; align-items: center; justify-content: center;
    background: var(--gray-100); color: var(--gray-600); border-radius: 6px;
    padding: 2px 8px; font-size: .78rem; font-weight: 600;
    font-family: 'JetBrains Mono', monospace; min-width: 28px;
}
.purchased-date { display: inline-flex; align-items: center; gap: 5px; color: var(--success); font-size: .78rem; font-weight: 600; }
.no-date        { color: var(--gray-300); font-size: .85rem; }

.btn-view {
    display: inline-flex; align-items: center; gap: 5px; padding: 6px 14px;
    border-radius: var(--radius-sm); font-size: .78rem; font-weight: 600; border: none;
    cursor: pointer; transition: all .2s; background: var(--primary-light); color: var(--primary);
    font-family: 'Plus Jakarta Sans', sans-serif;
}
.btn-view:hover { background: var(--primary); color: #fff; }

.empty-state { text-align: center; padding: 60px 20px; color: var(--gray-400); }
.empty-state i { font-size: 3rem; margin-bottom: 12px; display: block; opacity: .4; }
.empty-state p { font-size: .9rem; }

/* ── Modal ── */
.modal-content {
    border: none; border-radius: var(--radius-lg); box-shadow: var(--shadow-lg);
    font-family: 'Plus Jakarta Sans', sans-serif; overflow: hidden;
}
.modal-header {
    background: linear-gradient(135deg, #0f172a 0%, #1e3a5f 100%);
    padding: 20px 24px; border-bottom: none; display: flex; align-items: flex-start; gap: 12px;
}
.modal-title-wrap { flex: 1; min-width: 0; }
.modal-title { color: #fff; font-size: 1rem; font-weight: 700; display: flex; align-items: center; gap: 8px; margin-bottom: 8px; }
.modal-meta { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
.modal-meta-chip { background: rgba(255,255,255,.15); color: rgba(255,255,255,.9); border-radius: 6px; padding: 3px 10px; font-size: .72rem; font-weight: 600; }
.btn-close-custom {
    background: rgba(255,255,255,.15); border: none; border-radius: 8px;
    width: 32px; height: 32px; flex-shrink: 0;
    display: flex; align-items: center; justify-content: center;
    color: #fff; cursor: pointer; transition: background .2s; margin-top: 2px;
}
.btn-close-custom:hover { background: rgba(255,255,255,.28); }
.modal-body { padding: 24px; background: var(--gray-50); }

/* ── Status banners ── */
.modal-banner {
    display: flex; align-items: flex-start; gap: 12px;
    padding: 14px 16px; border-radius: var(--radius); margin-bottom: 20px; font-size: .875rem;
}
.modal-banner i      { font-size: 1.2rem; flex-shrink: 0; margin-top: 1px; }
.modal-banner strong { font-weight: 700; display: block; margin-bottom: 2px; }
.modal-banner small  { opacity: .8; font-size: .8rem; }
.mb-approved  { background: var(--success-light); color: #065f46; border: 1px solid #a7f3d0; }
.mb-pending   { background: #fffbeb; color: #92400e; border: 1px solid #fde68a; }
.mb-rejected  { background: var(--danger-light); color: #991b1b; border: 1px solid #fca5a5; }
.mb-receiving { background: var(--info-light); color: #155e75; border: 1px solid #a5f3fc; }
.mb-completed { background: #ede9fe; color: #4c1d95; border: 1px solid #ddd6fe; }
.mb-waiting   { background: #fff7ed; color: #7c2d12; border: 1px solid #fed7aa; border-left: 4px solid var(--orange); }
.delivery-info { display: flex; gap: 8px; flex-wrap: wrap; margin-top: 10px; }
.delivery-chip {
    display: inline-flex; align-items: center; gap: 5px;
    background: rgba(255,255,255,.7); border: 1px solid rgba(0,0,0,.1);
    border-radius: 6px; padding: 3px 10px; font-size: .75rem; font-weight: 600;
}

/* ── Action Selector ── */
.action-selector {
    background: #fff; border-radius: var(--radius); border: 1px solid var(--gray-200);
    padding: 16px 18px; margin-bottom: 20px; transition: all .3s;
}
.action-selector label { font-size: .72rem; font-weight: 700; text-transform: uppercase; letter-spacing: .5px; color: var(--gray-500); margin-bottom: 8px; display: block; }
.action-selector select {
    width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: var(--radius-sm);
    font-family: 'Plus Jakarta Sans', sans-serif; font-size: .875rem; font-weight: 500;
    color: var(--gray-700); background: var(--gray-50); cursor: pointer; transition: all .2s; appearance: auto;
}
.action-selector select:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 3px rgba(26,86,219,.12); background: #fff; }
.action-selector.is-approve { border-color: var(--success); background: var(--success-light); }
.action-selector.is-reject  { border-color: var(--danger);  background: var(--danger-light);  }
.hint-text { font-size: .75rem; color: var(--gray-400); margin-top: 6px; }

/* ── Items Card ── */
.items-card { background: #fff; border-radius: var(--radius); border: 1px solid var(--gray-200); overflow: hidden; margin-bottom: 20px; box-shadow: var(--shadow-sm); }
.items-card-header {
    background: var(--gray-50); padding: 10px 16px; border-bottom: 1px solid var(--gray-100);
    font-size: .72rem; font-weight: 700; text-transform: uppercase; letter-spacing: .5px; color: var(--gray-500);
    display: flex; align-items: center; gap: 6px;
}
.modal-table { width: 100%; border-collapse: collapse; }
.modal-table th {
    background: var(--gray-50); color: var(--gray-500); font-size: .68rem; font-weight: 700;
    text-transform: uppercase; letter-spacing: .5px; padding: 10px 14px;
    border-bottom: 1px solid var(--gray-100); text-align: left; white-space: nowrap;
}
.modal-table td { padding: 11px 14px; border-bottom: 1px solid var(--gray-100); font-size: .83rem; color: var(--gray-700); vertical-align: middle; }
.modal-table tbody tr:last-child td { border-bottom: none; }
.modal-table tbody tr:hover { background: #fafafa; }

.qty-input, .price-input {
    width: 84px; padding: 6px 10px; border: 1px solid var(--gray-200); border-radius: var(--radius-sm);
    font-family: 'JetBrains Mono', monospace; font-size: .8rem; text-align: right; transition: all .2s; background: var(--gray-50);
}
.qty-input:focus, .price-input:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 2px rgba(26,86,219,.12); background: #fff; }
.qty-input[readonly], .price-input[readonly], .qty-input:disabled { background: var(--gray-100); color: var(--gray-400); cursor: not-allowed; }
.locked-icon { font-size: .68rem; color: var(--gray-400); margin-left: 4px; vertical-align: middle; }

/* ── Purchase form ── */
.purchase-section {
    background: #fff; border-radius: var(--radius); border: 1px solid var(--gray-200);
    padding: 18px; margin-bottom: 20px; box-shadow: var(--shadow-sm);
}
.purchase-section label { font-size: .72rem; font-weight: 700; text-transform: uppercase; letter-spacing: .5px; color: var(--gray-500); margin-bottom: 8px; display: block; }
.purchase-section select {
    padding: 8px 14px; border: 1px solid var(--gray-200); border-radius: var(--radius-sm);
    font-family: 'Plus Jakarta Sans', sans-serif; font-size: .875rem; font-weight: 600;
    color: var(--gray-700); background: var(--gray-50); cursor: pointer; transition: all .2s; min-width: 180px;
}
.purchase-section select:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 3px rgba(26,86,219,.12); }

.grand-total-bar {
    display: flex; align-items: center; justify-content: space-between;
    background: var(--primary-light); border-radius: var(--radius);
    padding: 14px 18px; margin-bottom: 16px; border: 1px solid #bfdbfe;
}
.grand-total-bar .gtlabel  { font-size: .75rem; font-weight: 700; text-transform: uppercase; letter-spacing: .5px; color: var(--primary); display: flex; align-items: center; gap: 6px; }
.grand-total-bar .gtamount { font-family: 'JetBrains Mono', monospace; font-size: 1.3rem; font-weight: 700; color: var(--primary-dark); }

.summary-total-bar {
    display: flex; align-items: center; justify-content: space-between;
    background: var(--gray-100); border-radius: var(--radius);
    padding: 12px 18px; margin-bottom: 16px; border: 1px solid var(--gray-200);
}
.summary-total-bar .stlabel  { font-size: .75rem; font-weight: 700; text-transform: uppercase; letter-spacing: .5px; color: var(--gray-500); }
.summary-total-bar .stamount { font-family: 'JetBrains Mono', monospace; font-size: 1.1rem; font-weight: 700; color: var(--gray-700); }

/* ── Modal footer ── */
.modal-footer-btns {
    display: flex; align-items: center; justify-content: flex-end;
    gap: 10px; flex-wrap: wrap; padding-top: 16px; border-top: 1px solid var(--gray-200);
}
.btn-approve {
    background: var(--success); color: #fff; border: none; border-radius: var(--radius-sm);
    padding: 10px 22px; font-size: .875rem; font-weight: 700; font-family: 'Plus Jakarta Sans', sans-serif;
    cursor: pointer; display: inline-flex; align-items: center; gap: 6px; transition: all .2s;
}
.btn-approve:hover { background: #059669; transform: translateY(-1px); box-shadow: 0 4px 12px rgba(14,159,110,.3); }
.btn-reject {
    background: var(--danger); color: #fff; border: none; border-radius: var(--radius-sm);
    padding: 10px 22px; font-size: .875rem; font-weight: 700; font-family: 'Plus Jakarta Sans', sans-serif;
    cursor: pointer; display: inline-flex; align-items: center; gap: 6px; transition: all .2s;
}
.btn-reject:hover { background: #c81e1e; transform: translateY(-1px); box-shadow: 0 4px 12px rgba(224,36,36,.3); }
.btn-purchase {
    background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
    color: #fff; border: none; border-radius: var(--radius-sm);
    padding: 10px 24px; font-size: .875rem; font-weight: 700; font-family: 'Plus Jakarta Sans', sans-serif;
    cursor: pointer; display: inline-flex; align-items: center; gap: 6px; transition: all .2s;
    box-shadow: 0 2px 8px rgba(26,86,219,.25);
}
.btn-purchase:hover { transform: translateY(-1px); box-shadow: 0 6px 18px rgba(26,86,219,.35); }
.btn-cancel-modal {
    background: var(--gray-100); color: var(--gray-600); border: 1px solid var(--gray-200);
    border-radius: var(--radius-sm); padding: 10px 16px; font-size: .875rem; font-weight: 600;
    font-family: 'Plus Jakarta Sans', sans-serif;
    cursor: pointer; display: inline-flex; align-items: center; gap: 6px; transition: all .2s;
}
.btn-cancel-modal:hover { background: var(--gray-200); }
.price-cell { font-family: 'JetBrains Mono', monospace; font-size: .82rem; text-align: right; }
.price-cell.total { font-weight: 700; color: var(--gray-800); }

/* ── Responsive ── */
@media (max-width: 1024px) {
    .main-content { padding: 20px; }
    .stats-row { grid-template-columns: repeat(3, 1fr); }
}
@media (max-width: 768px) {
    :root { --sidebar-w: 0px; }
    .main-sidebar { transform: translateX(-260px); width: 260px; transition: transform .25s; }
    .main-sidebar.open { transform: translateX(0); }
    .main-content { margin-left: 0; padding: 16px; }
    .page-header { flex-direction: column; gap: 10px; }
    .page-header-left h1 { font-size: 1.25rem; }
    .stats-row { grid-template-columns: repeat(2, 1fr); gap: 10px; }
    .stat-number { font-size: 1.5rem; }
    .filter-bar { flex-direction: column; align-items: stretch; }
    .filter-bar .form-control, .btn-filter, .btn-reset { width: 100%; }
    .data-table thead th:nth-child(3), .data-table tbody td:nth-child(3) { display: none; }
    .panel-header { flex-direction: column; align-items: flex-start; }
    .modal-dialog { margin: 8px; max-width: calc(100vw - 16px); }
    .grand-total-bar .gtamount { font-size: 1.1rem; }
    .qty-input, .price-input { width: 70px; }
    .modal-table th, .modal-table td { padding: 8px 10px; }
    .modal-footer-btns { justify-content: stretch; }
    .modal-footer-btns > * { flex: 1; justify-content: center; }
}
@media (max-width: 480px) {
    .stats-row { grid-template-columns: repeat(2, 1fr); }
    .stat-number { font-size: 1.4rem; }
    .data-table thead th:nth-child(7), .data-table tbody td:nth-child(7) { display: none; }
    .modal-table th:nth-child(4), .modal-table td:nth-child(4),
    .modal-table th:nth-child(5), .modal-table td:nth-child(5) { display: none; }
}
.sidebar-toggle {
    display: none; background: #fff; border: 1px solid var(--gray-200);
    border-radius: var(--radius-sm); padding: 8px 12px; cursor: pointer;
    color: var(--gray-700); font-size: .875rem; font-weight: 600;
    align-items: center; gap: 6px; box-shadow: var(--shadow-sm); margin-bottom: 10px;
}
@media (max-width: 768px) { .sidebar-toggle { display: inline-flex; } }
.sidebar-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,.4); z-index: 999; backdrop-filter: blur(2px); }
.sidebar-overlay.show { display: block; }
</style>
</head>
<body>

<div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>
<div class="main-sidebar" id="mainSidebar">
    <?php include 'inventory_sidebar.php'; ?>
</div>

<div class="main-content">

    <!-- Page Header -->
    <div class="page-header">
        <div class="page-header-left">
            <button class="sidebar-toggle" onclick="openSidebar()"><i class="bi bi-list"></i> Menu</button>
            <div class="page-badge"><i class="bi bi-bag-check"></i> Purchase Management</div>
            <h1>Department Requests</h1>
            <p>Review, approve, and process departmental purchase orders</p>
        </div>
    </div>

    <!-- Alerts -->
    <?php if ($errorMessage): ?>
    <div class="alert-custom alert-danger-custom">
        <i class="bi bi-exclamation-triangle-fill"></i>
        <span><?= htmlspecialchars((string)($errorMessage ?? '')) ?></span>
    </div>
    <?php endif; ?>
    <?php if ($successMessage): ?>
    <div class="alert-custom alert-success-custom">
        <i class="bi bi-check-circle-fill"></i>
        <span><?= htmlspecialchars((string)($successMessage ?? '')) ?></span>
    </div>
    <?php endif; ?>

    <!-- Stat Cards -->
    <div class="stats-row">
        <?php
        $statDefs = [
            'pending'   => 'sc-pending',
            'approved'  => 'sc-approved',
            'purchased' => 'sc-purchased',
            'receiving' => 'sc-receiving',
            'completed' => 'sc-completed',
            'rejected'  => 'sc-rejected',
        ];
        foreach ($statDefs as $sKey => $cls):
            $isActive = ($statusFilter === $sKey) ? 'active' : '';
        ?>
        <a href="?status=<?= $sKey ?>&search_dept=<?= urlencode($searchDept) ?>"
           class="stat-card <?= $cls ?> <?= $isActive ?>">
            <div class="stat-label"><?= ucfirst($sKey) ?></div>
            <div class="stat-number"><?= $counts[ucfirst($sKey)] ?? 0 ?></div>
        </a>
        <?php endforeach; ?>
    </div>

    <!-- Panel -->
    <div class="panel">
        <div class="panel-header">
            <div class="panel-title">
                <i class="bi bi-table" style="color:var(--primary);"></i>
                Request List
                <span style="font-size:.75rem;font-weight:600;color:var(--gray-400);">
                    — <?= ucfirst((string)($statusFilter ?? '')) ?> (<?= is_countable($requests) ? count($requests) : 0 ?>)
                </span>
            </div>
            <form method="get" class="filter-bar">
                <input type="hidden" name="status" value="<?= htmlspecialchars((string)($statusFilter ?? '')) ?>">
                <input type="text" name="search_dept" class="form-control"
                       placeholder="Search department…"
                       value="<?= htmlspecialchars($searchDept) ?>"
                       style="max-width:220px;">
                <button type="submit" class="btn-filter"><i class="bi bi-search"></i> Search</button>
                <a href="department_request.php" class="btn-reset"><i class="bi bi-arrow-counterclockwise"></i> Reset</a>
            </form>
        </div>

        <div style="overflow-x:auto;">
        <?php if (empty($requests)): ?>
            <div class="empty-state">
                <i class="bi bi-inbox"></i>
                <p>No <?= htmlspecialchars((string)($statusFilter ?? '')) ?> requests found.</p>
            </div>
        <?php else: ?>
        <table class="data-table">
            <thead>
                <tr>
                    <th style="width:70px;">ID</th>
                    <th>Department</th>
                    <th>User</th>
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
                $statusLabel = ucfirst($statusRaw);

                $badgeMap = [
                    'pending'   => ['bs-pending',   'bi-hourglass-split'],
                    'approved'  => ['bs-approved',  'bi-check-circle'],
                    'purchased' => ['bs-purchased', 'bi-bag-check-fill'],
                    'receiving' => ['bs-receiving', 'bi-box-seam'],
                    'completed' => ['bs-completed', 'bi-patch-check-fill'],
                    'rejected'  => ['bs-rejected',  'bi-x-circle'],
                ];
                [$bClass, $bIcon] = $badgeMap[$statusRaw] ?? ['', 'bi-circle'];
            ?>
            <tr>
                <td><span class="req-id">#<?= str_pad($r['id'], 4, '0', STR_PAD_LEFT) ?></span></td>
                <td>
                    <span class="dept-pill">
                        <i class="bi bi-building"></i>
                        <?= htmlspecialchars($r['department']) ?>
                    </span>
                </td>
                <td class="mono"><?= htmlspecialchars($r['user_id']) ?></td>
                <td class="center"><span class="item-count"><?= count($itemsArr) ?></span></td>
                <td>
                    <span class="badge-status <?= $bClass ?>">
                        <i class="bi <?= $bIcon ?>"></i> <?= $statusLabel ?>
                    </span>
                </td>
                <td class="mono" style="font-size:.78rem;">
                    <?= date('M d, Y', strtotime($r['created_at'])) ?>
                    <br><span style="color:var(--gray-400);"><?= date('h:i A', strtotime($r['created_at'])) ?></span>
                </td>
                <td>
                    <?php if (!empty($r['purchased_at'])): ?>
                        <span class="purchased-date">
                            <i class="bi bi-bag-check-fill"></i>
                            <?= date('M d, Y', strtotime($r['purchased_at'])) ?>
                        </span>
                    <?php else: ?>
                        <span class="no-date">—</span>
                    <?php endif; ?>
                </td>
                <td class="center">
                    <button class="btn-view view-items-btn"
                        data-id="<?= $r['id'] ?>"
                        data-dept="<?= htmlspecialchars((string)($r['department'] ?? '')) ?>"
                        data-status="<?= htmlspecialchars((string)($statusRaw ?? '')) ?>"
                        data-purchased="<?= htmlspecialchars((string)($r['purchased_at'] ?? '')) ?>"
                        data-paymenttype="<?= htmlspecialchars((string)($r['payment_type'] ?? '')) ?>"
                        data-items='<?= htmlspecialchars((string)json_encode($itemsArr, JSON_UNESCAPED_UNICODE), ENT_QUOTES, "UTF-8") ?>'>
                        <i class="bi bi-eye"></i> View
                    </button>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
        </div>
    </div>

</div><!-- /main-content -->

<!-- ════════════════ MODAL ════════════════ -->
<div class="modal fade" id="viewModal" tabindex="-1">
<div class="modal-dialog modal-lg modal-dialog-scrollable">
<div class="modal-content">
    <div class="modal-header">
        <div class="modal-title-wrap">
            <div class="modal-title">
                <i class="bi bi-file-earmark-text"></i>
                <span id="modalTitle">Request Details</span>
            </div>
            <div class="modal-meta" id="modalMeta"></div>
        </div>
        <button type="button" class="btn-close-custom" data-bs-dismiss="modal" aria-label="Close">
            <i class="bi bi-x-lg"></i>
        </button>
    </div>
    <div class="modal-body" id="modalBodyContent"></div>
</div>
</div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
/* ── Sidebar mobile ── */
function openSidebar()  { document.getElementById('mainSidebar').classList.add('open');    document.getElementById('sidebarOverlay').classList.add('show'); }
function closeSidebar() { document.getElementById('mainSidebar').classList.remove('open'); document.getElementById('sidebarOverlay').classList.remove('show'); }

/* ── Helpers ── */
function fc(n) {
    return '₱' + parseFloat(n || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}
function pad(n) { return String(n).padStart(4, '0'); }

/* ── View modal ── */
document.querySelectorAll('.view-items-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        const items       = JSON.parse(btn.dataset.items || '[]');
        const _st         = (btn.dataset.status || '').toLowerCase().trim();
        const purchased   = btn.dataset.purchased || '';
        const dept        = btn.dataset.dept;
        const reqId       = btn.dataset.id;
        const paymentType = btn.dataset.paymenttype || '';

        const isPending   = _st === 'pending';
        const isApproved  = _st === 'approved';
        const isPurchased = _st === 'purchased';
        const isReceiving = _st === 'receiving';
        const isCompleted = _st === 'completed';
        const isRejected  = _st === 'rejected';
        const isDone      = isPurchased || isReceiving || isCompleted;
        const showPurchaseForm = isApproved;

        /* ── Modal title & meta ── */
        document.getElementById('modalTitle').textContent = `Request #${pad(reqId)} — ${dept}`;

        const statusColors = {
            pending:   'background:#fef3c7;color:#92400e;',
            approved:  'background:#def7ec;color:#065f46;',
            purchased: 'background:#e8f0fe;color:#1241b0;',
            receiving: 'background:#e0f2fe;color:#155e75;',
            completed: 'background:#ede9fe;color:#4c1d95;',
            rejected:  'background:#fde8e8;color:#991b1b;',
        };
        const sc = statusColors[_st] || 'background:rgba(255,255,255,.2);color:#fff;';
        const statusLabel = _st.charAt(0).toUpperCase() + _st.slice(1);

        let purchasedChip = '';
        if (purchased) {
            try {
                const d = new Date(purchased);
                purchasedChip = `<span class="modal-meta-chip">
                    <i class="bi bi-calendar-check" style="margin-right:3px;"></i>
                    ${d.toLocaleDateString('en-PH',{month:'short',day:'numeric',year:'numeric'})}
                </span>`;
            } catch(e){}
        }

        document.getElementById('modalMeta').innerHTML =
            `<span class="modal-meta-chip">${items.length} item${items.length !== 1 ? 's' : ''}</span>
             <span style="${sc}border-radius:6px;padding:3px 10px;font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.4px;">${statusLabel}</span>
             ${purchasedChip}`;

        let html = `<form method="post"><input type="hidden" name="id" value="${reqId}">`;

        /* ── Banners ── */
        if (isPurchased) {
            html += `<div class="modal-banner mb-waiting">
                <i class="bi bi-truck"></i>
                <div>
                    <strong>Waiting for Delivery</strong>
                    <small>This request has been purchased and is awaiting delivery from the supplier.</small>
                    <div class="delivery-info">
                        ${purchased ? `<span class="delivery-chip"><i class="bi bi-bag-check"></i> Purchased: ${purchased}</span>` : ''}
                        ${paymentType ? `<span class="delivery-chip"><i class="bi bi-credit-card"></i> Payment: ${paymentType}</span>` : ''}
                    </div>
                </div>
            </div>`;
        } else if (isReceiving) {
            html += `<div class="modal-banner mb-receiving">
                <i class="bi bi-box-seam"></i>
                <div><strong>Items Being Received</strong>
                <small>This order is currently being received and checked into inventory.</small></div>
            </div>`;
        } else if (isCompleted) {
            html += `<div class="modal-banner mb-completed">
                <i class="bi bi-patch-check-fill"></i>
                <div><strong>Order Completed</strong>
                <small>All items have been received and added to inventory successfully.</small></div>
            </div>`;
        } else if (isApproved) {
            html += `<div class="modal-banner mb-approved">
                <i class="bi bi-check-circle-fill"></i>
                <div><strong>Request Approved</strong>
                <small>Approved quantities are locked. Enter unit prices below to confirm the purchase order.</small></div>
            </div>`;
        } else if (isPending) {
            html += `<div class="modal-banner mb-pending">
                <i class="bi bi-hourglass-split"></i>
                <div><strong>Awaiting Review</strong>
                <small>Choose an action below to approve or reject this request.</small></div>
            </div>`;
        } else if (isRejected) {
            html += `<div class="modal-banner mb-rejected">
                <i class="bi bi-x-circle-fill"></i>
                <div><strong>Request Rejected</strong>
                <small>This request was denied. No items will be purchased.</small></div>
            </div>`;
        }

        /* ── Action selector (Pending only) ── */
        if (isPending) {
            html += `<div class="action-selector" id="actionSection">
                <label>Select Action</label>
                <select id="actionDropdown" name="actionDropdown">
                    <option value="">— Choose an action —</option>
                    <option value="approve">✓ Approve — set approved quantities</option>
                    <option value="reject">✗ Reject — deny this request</option>
                </select>
                <div class="hint-text">Select an action to enable the corresponding fields below.</div>
            </div>`;
        }

        /* ── Items Table ── */
        const locked = (isApproved || isDone)
            ? `<i class="bi bi-lock-fill locked-icon" title="Locked"></i>` : '';

        html += `<div class="items-card">
            <div class="items-card-header"><i class="bi bi-list-ul"></i> Requested Items</div>
            <div style="overflow-x:auto;">
            <table class="modal-table"><thead><tr>
                <th>Item Name</th>
                <th style="text-align:center;">Requested</th>
                <th style="text-align:center;">Approved ${locked}</th>
                <th>Unit</th>
                <th style="text-align:center;">Pcs/Box</th>`;
        if (showPurchaseForm) html += `<th style="text-align:right;">Price (₱)</th><th style="text-align:right;">Total (₱)</th>`;
        if (isDone)           html += `<th style="text-align:right;">Price (₱)</th><th style="text-align:right;">Total (₱)</th>`;
        html += `</tr></thead><tbody>`;

        let grandTotalStatic = 0;

        items.forEach(item => {
            const idx       = item.id;
            const approved  = item.approved_quantity || 0;
            const unit      = item.unit || 'pcs';
            const pcs       = item.pcs_per_box || 1;
            const price     = parseFloat(item.price || 0);
            const lineTotal = parseFloat(item.total_price || 0);
            grandTotalStatic += lineTotal;

            const qtyAttr = isPending
                ? `disabled style="background:var(--gray-100);"` 
                : `readonly style="background:var(--gray-100);"`;

            html += `<tr>
                <td style="font-weight:600;color:var(--gray-800);">${item.item_name}</td>
                <td style="text-align:center;font-family:'JetBrains Mono',monospace;font-weight:600;">${item.quantity}</td>
                <td style="text-align:center;">
                    <input type="number" class="qty-input" name="approved_quantity[${idx}]"
                           value="${approved}" min="0" max="${item.quantity}" ${qtyAttr}>
                </td>
                <td>${unit}<input type="hidden" name="unit[${idx}]" value="${unit}"></td>
                <td style="text-align:center;">${pcs}<input type="hidden" name="pcs_per_box[${idx}]" value="${pcs}"></td>`;

            if (showPurchaseForm) {
                html += `<td>
                    <input type="number" step="0.01" class="price-input" name="price[${idx}]"
                           value="${price > 0 ? price.toFixed(2) : ''}" min="0" placeholder="0.00">
                </td>
                <td class="price-cell total" id="total_${idx}">₱0.00</td>`;
            } else if (isDone) {
                html += `<td class="price-cell">${fc(price)}</td>
                         <td class="price-cell total">${fc(lineTotal)}</td>`;
            }
            html += `</tr>`;
        });
        html += `</tbody></table></div></div>`;

        /* ── Purchase form (Approved only) ── */
        if (showPurchaseForm) {
            html += `<div class="purchase-section">
                <label>Payment Method</label>
                <select name="payment_type">
                    <option value="Direct">💵 Direct Payment</option>
                    <option value="Credit">💳 Credit</option>
                    <option value="Check">🏦 Check</option>
                </select>
            </div>
            <div class="grand-total-bar">
                <span class="gtlabel"><i class="bi bi-calculator"></i> Total Purchase Amount</span>
                <span class="gtamount" id="grandTotal">₱0.00</span>
            </div>`;
        }

        /* ── Summary total (read-only for done states) ── */
        if (isDone && grandTotalStatic > 0) {
            html += `<div class="summary-total-bar">
                <span class="stlabel"><i class="bi bi-receipt" style="margin-right:5px;"></i> Total Amount Purchased</span>
                <span class="stamount">${fc(grandTotalStatic)}</span>
            </div>`;
        }

        /* ── Footer buttons ── */
        html += `<div class="modal-footer-btns">
            <button type="button" class="btn-cancel-modal" data-bs-dismiss="modal">
                <i class="bi bi-x"></i> Close
            </button>`;

        if (isPending) {
            html += `<button type="submit" name="action" value="approve" class="btn-approve" id="submitApprove" style="display:none;">
                         <i class="bi bi-check-circle"></i> Approve Request
                     </button>
                     <button type="submit" name="action" value="reject" class="btn-reject" id="submitReject" style="display:none;">
                         <i class="bi bi-x-circle"></i> Reject Request
                     </button>`;
        }
        if (showPurchaseForm) {
            html += `<button type="submit" name="action" value="purchase" class="btn-purchase">
                         <i class="bi bi-bag-check"></i> Confirm Purchase
                     </button>`;
        }

        html += `</div></form>`;

        document.getElementById('modalBodyContent').innerHTML = html;

        /* ── Pending: dropdown logic ── */
        if (isPending) {
            const dd       = document.getElementById('actionDropdown');
            const secEl    = document.getElementById('actionSection');
            const btnApprv = document.getElementById('submitApprove');
            const btnRej   = document.getElementById('submitReject');
            const qtyIns   = document.querySelectorAll('#modalBodyContent .qty-input');

            dd.addEventListener('change', function () {
                const v = this.value;
                secEl.classList.remove('is-approve', 'is-reject');

                if (v === 'approve') {
                    qtyIns.forEach(i => { i.disabled = false; i.style.background = '#fff'; i.style.borderColor = 'var(--success)'; });
                    secEl.classList.add('is-approve');
                    btnApprv.style.display = 'inline-flex';
                    btnRej.style.display   = 'none';
                } else if (v === 'reject') {
                    qtyIns.forEach(i => { i.disabled = true; i.value = 0; i.style.background = 'var(--danger-light)'; i.style.borderColor = 'var(--danger)'; });
                    secEl.classList.add('is-reject');
                    btnApprv.style.display = 'none';
                    btnRej.style.display   = 'inline-flex';
                } else {
                    qtyIns.forEach(i => { i.disabled = true; i.style.background = 'var(--gray-100)'; i.style.borderColor = ''; });
                    btnApprv.style.display = 'none';
                    btnRej.style.display   = 'none';
                }
            });
        }

        /* ── Live price calculation ── */
        if (showPurchaseForm) {
            function computeTotals() {
                let grand = 0;
                items.forEach(item => {
                    const priceEl = document.querySelector(`[name="price[${item.id}]"]`);
                    const qtyEl   = document.querySelector(`[name="approved_quantity[${item.id}]"]`);
                    if (!priceEl) return;
                    const price = parseFloat(priceEl.value || 0);
                    const qty   = parseFloat(qtyEl?.value  || item.approved_quantity || 0);
                    const total = price * qty;
                    const cel   = document.getElementById(`total_${item.id}`);
                    if (cel) cel.textContent = fc(total);
                    grand += total;
                });
                const gt = document.getElementById('grandTotal');
                if (gt) gt.textContent = fc(grand);
            }
            document.querySelectorAll('#modalBodyContent .price-input, #modalBodyContent .qty-input')
                    .forEach(i => i.addEventListener('input', computeTotals));
            computeTotals();
        }

        new bootstrap.Modal(document.getElementById('viewModal')).show();
    });
});
</script>
</body>
</html>