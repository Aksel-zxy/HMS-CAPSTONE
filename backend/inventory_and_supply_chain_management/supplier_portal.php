<?php
include '../../SQL/config.php';

/* =====================================================
   SAFE DEFAULTS
=====================================================*/
$errorMessage   = '';
$successMessage = '';
$filterView     = strtolower(trim($_GET['view'] ?? 'pending_prices'));

/* =====================================================
   HELPER
=====================================================*/
function findDbEnumMatch(PDO $pdo, string $table, string $column, string $desired): string {
    $stmt = $pdo->prepare("SELECT COLUMN_TYPE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?");
    $stmt->execute([$table, $column]);
    $ct = $stmt->fetchColumn();
    if (!$ct || !preg_match("/^enum\(/i", $ct)) return ucfirst($desired);
    if (preg_match("/^enum\((.*)\)$/i", $ct, $m)) {
        $vals = str_getcsv($m[1], ',', "'");
        foreach ($vals as $v) { if (strcasecmp($v, $desired) === 0) return $v; }
        return $vals[0] ?? ucfirst($desired);
    }
    return ucfirst($desired);
}

/* =====================================================
   HANDLE ACTIONS
=====================================================*/
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

    /* ── SET PRICES & MARK RECEIVING ── */
    if ($_POST['action'] === 'set_prices') {
        $request_ids     = $_POST['request_ids']    ?? [];
        $prices          = $_POST['price']           ?? [];
        $units           = $_POST['unit']            ?? [];
        $pcs_arr         = $_POST['pcs_per_box']     ?? [];
        $payment_types   = $_POST['payment_type']    ?? [];
        $estimated_dates = $_POST['estimated_delivery'] ?? [];

        foreach ($request_ids as $rid) {
            $rid = (int)$rid;
            if ($rid <= 0) continue;

            $sStmt = $pdo->prepare("SELECT status FROM department_request WHERE id=? LIMIT 1");
            $sStmt->execute([$rid]);
            $st = strtolower(trim($sStmt->fetchColumn() ?? ''));
            if ($st !== 'purchased') continue;

            $iStmt = $pdo->prepare("SELECT * FROM department_request_items WHERE request_id=? ORDER BY id ASC");
            $iStmt->execute([$rid]);
            $items = $iStmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($items as $item) {
                $item_id = $item['id'];
                $approved_qty = (int)($item['approved_quantity'] ?? 0);
                if ($approved_qty <= 0) continue;

                $price  = (float)($prices[$item_id]    ?? 0);
                $unit   = $units[$item_id]              ?? ($item['unit'] ?? 'pcs');
                $pcs    = (int)($pcs_arr[$item_id]      ?? ($item['pcs_per_box'] ?? 1));
                $total  = $price * $approved_qty;

                $pdo->prepare("UPDATE department_request_items SET price=?, total_price=?, unit=?, pcs_per_box=? WHERE id=?")
                    ->execute([$price, $total, $unit, $pcs, $item_id]);
            }

            $payment     = $payment_types[$rid]    ?? 'Direct';
            $estDate     = $estimated_dates[$rid]  ?? null;
            $dbReceiving = findDbEnumMatch($pdo, 'department_request', 'status', 'receiving');

            $pdo->prepare("UPDATE department_request SET status=?, payment_type=?, estimated_delivery=? WHERE id=?")
                ->execute([$dbReceiving, $payment, $estDate ?: null, $rid]);
        }

        header("Location: supplier_portal.php?view=priced&success=prices_set");
        exit;
    }

    /* ── HANDLE RETURN REQUEST ACTION (approve/reject) ── */
    if ($_POST['action'] === 'update_return_status') {
        $return_id  = (int)($_POST['return_id']  ?? 0);
        $new_status = trim($_POST['new_status']  ?? '');
        $note       = trim($_POST['admin_note']  ?? '');

        $allowed = ['Approved', 'Rejected', 'Returned'];
        if ($return_id > 0 && in_array($new_status, $allowed)) {
            if ($new_status === 'Approved') {
                $rrStmt = $pdo->prepare("SELECT inventory_id, quantity FROM return_requests WHERE id = ?");
                $rrStmt->execute([$return_id]);
                $rr = $rrStmt->fetch(PDO::FETCH_ASSOC);
                if ($rr) {
                    $pdo->prepare("UPDATE inventory SET quantity = GREATEST(0, quantity - ?) WHERE id = ?")
                        ->execute([$rr['quantity'], $rr['inventory_id']]);
                }
            }
            $pdo->prepare("UPDATE return_requests SET status = ?, updated_at = NOW() WHERE id = ?")
                ->execute([$new_status, $return_id]);
        }
        header("Location: supplier_portal.php?view=returns&success=return_updated");
        exit;
    }

    header("Location: supplier_portal.php");
    exit;
}

/* =====================================================
   LOAD PURCHASED REQUESTS WITH ITEMS
=====================================================*/
$purchasedRequests = [];
try {
    $sql  = "SELECT * FROM department_request WHERE LOWER(status) = 'purchased' ORDER BY purchased_at DESC";
    $stmt = $pdo->query($sql);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?? [];
    foreach ($rows as $req) {
        $iStmt = $pdo->prepare("SELECT * FROM department_request_items WHERE request_id=? ORDER BY id ASC");
        $iStmt->execute([$req['id']]);
        $req['items'] = $iStmt->fetchAll(PDO::FETCH_ASSOC) ?? [];
        $purchasedRequests[] = $req;
    }
} catch (Exception $e) { $purchasedRequests = []; }

/* =====================================================
   LOAD RECEIVING REQUESTS
=====================================================*/
$receivingRequests = [];
try {
    $rStmt = $pdo->query("SELECT dr.*,
        (SELECT SUM(dri.total_price) FROM department_request_items dri WHERE dri.request_id = dr.id) AS grand_total
        FROM department_request dr
        WHERE LOWER(dr.status) IN ('receiving','completed')
        ORDER BY dr.purchased_at DESC LIMIT 50");
    $receivingRequests = $rStmt->fetchAll(PDO::FETCH_ASSOC) ?? [];
} catch (Exception $e) { $receivingRequests = []; }

/* =====================================================
   LOAD RETURN REQUESTS
=====================================================*/
$returnRequests = [];
try {
    // Use subqueries instead of JOINs to guarantee exactly one row
    // per return_request — a JOIN can produce duplicate rows if
    // inventory or users tables have non-unique key values.
    $retStmt = $pdo->query("
        SELECT
            rr.id,
            rr.inventory_id,
            rr.requested_by,
            rr.quantity,
            rr.reason,
            rr.photo,
            rr.status,
            rr.requested_at,
            rr.updated_at,
            (SELECT i.item_name FROM inventory i
             WHERE i.id = rr.inventory_id LIMIT 1) AS item_name,
            (SELECT u.username FROM users u
             WHERE u.user_id = rr.requested_by LIMIT 1) AS username
        FROM return_requests rr
        GROUP BY rr.id
        ORDER BY rr.id DESC
    ");
    $returnRequests = $retStmt->fetchAll(PDO::FETCH_ASSOC) ?? [];
} catch (Exception $e) { $returnRequests = []; }

$retPending  = count(array_filter($returnRequests, fn($r) => strtolower($r['status']) === 'pending'));
$retApproved = count(array_filter($returnRequests, fn($r) => strtolower($r['status']) === 'approved'));
$retRejected = count(array_filter($returnRequests, fn($r) => strtolower($r['status']) === 'rejected'));

/* =====================================================
   SUMMARY COUNTS
=====================================================*/
$pendingPricesCount = count($purchasedRequests);
$pricedCount        = count($receivingRequests);
$returnCount        = count($returnRequests);

$successMessages = [
    'prices_set'     => 'Prices submitted successfully. Requests have been marked for receiving.',
    'return_updated' => 'Return request status updated successfully.',
];
$successMessage = $successMessages[$_GET['success'] ?? ''] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Supplier Portal — Purchase Order &amp; Return Management</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;0,9..40,800;1,9..40,400&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
<style>
:root {
    --navy:       #0d1b2a;
    --navy-mid:   #1b2d42;
    --navy-light: #243547;
    --teal:       #0d9488;
    --teal-light: #ccfbf1;
    --teal-dark:  #0f766e;
    --amber:      #d97706;
    --amber-light:#fef3c7;
    --red:        #dc2626;
    --red-light:  #fee2e2;
    --red-mid:    #fca5a5;
    --orange:     #ea580c;
    --orange-light:#fff7ed;
    --green:      #16a34a;
    --green-light:#dcfce7;
    --blue:       #2563eb;
    --blue-light: #dbeafe;
    --purple:     #7c3aed;
    --purple-light:#f5f3ff;
    --slate-50:   #f8fafc;
    --slate-100:  #f1f5f9;
    --slate-200:  #e2e8f0;
    --slate-300:  #cbd5e1;
    --slate-400:  #94a3b8;
    --slate-500:  #64748b;
    --slate-600:  #475569;
    --slate-700:  #334155;
    --slate-800:  #1e293b;
    --slate-900:  #0f172a;
    --sidebar-w:  260px;
    --topbar-h:   60px;
    --radius-xs:  4px;
    --radius-sm:  6px;
    --radius:     10px;
    --radius-lg:  16px;
    --shadow-sm:  0 1px 3px rgba(0,0,0,.08);
    --shadow:     0 4px 16px rgba(0,0,0,.1);
    --shadow-lg:  0 12px 40px rgba(0,0,0,.15);
    --transition: .2s cubic-bezier(.4,0,.2,1);
}

*,*::before,*::after { box-sizing: border-box; margin: 0; padding: 0; }

body {
    font-family: 'DM Sans', sans-serif;
    background: var(--slate-50);
    color: var(--slate-800);
    font-size: 14px;
    line-height: 1.6;
    min-height: 100vh;
    display: flex;
    flex-direction: column;
}

/* ══════════════════════════════════
   TOP HEADER BAR
══════════════════════════════════*/
.portal-topbar {
    background: var(--navy);
    border-bottom: 2px solid var(--teal);
    padding: 0 24px;
    height: var(--topbar-h);
    display: flex;
    align-items: center;
    justify-content: space-between;
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    z-index: 200;
    box-shadow: 0 4px 20px rgba(0,0,0,.3);
}
.portal-logo { display: flex; align-items: center; gap: 12px; text-decoration: none; }
.portal-logo-icon {
    width: 36px; height: 36px;
    background: var(--teal);
    border-radius: 8px;
    display: flex; align-items: center; justify-content: center;
    font-size: 1rem; color: #fff; flex-shrink: 0;
}
.portal-logo-text { color: #fff; font-size: .9rem; font-weight: 700; letter-spacing: -.3px; line-height: 1.2; }
.portal-logo-text small { display: block; font-size: .65rem; font-weight: 400; color: var(--slate-400); letter-spacing: .3px; text-transform: uppercase; }
.topbar-right { display: flex; align-items: center; gap: 12px; }
.topbar-date { color: var(--slate-400); font-size: .78rem; display: flex; align-items: center; gap: 5px; }
.topbar-badge { background: var(--teal); color: #fff; border-radius: 6px; padding: 4px 12px; font-size: .72rem; font-weight: 700; text-transform: uppercase; letter-spacing: .4px; }
.back-btn {
    background: rgba(255,255,255,.1); color: rgba(255,255,255,.8);
    border: 1px solid rgba(255,255,255,.15); border-radius: var(--radius-sm);
    padding: 6px 14px; font-size: .78rem; font-weight: 600; text-decoration: none;
    display: inline-flex; align-items: center; gap: 5px; transition: all .2s; font-family: 'DM Sans', sans-serif;
}
.back-btn:hover { background: rgba(255,255,255,.18); color: #fff; }
.hamburger-btn {
    display: none;
    background: rgba(255,255,255,.1);
    border: 1px solid rgba(255,255,255,.15);
    border-radius: var(--radius-sm);
    color: rgba(255,255,255,.8);
    padding: 6px 10px;
    cursor: pointer;
    font-size: 1rem;
    transition: all .2s;
}
.hamburger-btn:hover { background: rgba(255,255,255,.18); }

/* ══════════════════════════════════
   LAYOUT: SIDEBAR + CONTENT
══════════════════════════════════*/
.layout-wrap {
    display: flex;
    margin-top: var(--topbar-h);
    min-height: calc(100vh - var(--topbar-h));
}

/* ══════════════════════════════════
   SIDEBAR
══════════════════════════════════*/
.sidebar {
    width: var(--sidebar-w);
    background: var(--navy);
    flex-shrink: 0;
    position: fixed;
    top: var(--topbar-h);
    left: 0;
    bottom: 0;
    overflow-y: auto;
    z-index: 150;
    display: flex;
    flex-direction: column;
    border-right: 1px solid rgba(255,255,255,.06);
    transition: transform .25s cubic-bezier(.4,0,.2,1);
}
.sidebar::-webkit-scrollbar { width: 4px; }
.sidebar::-webkit-scrollbar-track { background: transparent; }
.sidebar::-webkit-scrollbar-thumb { background: rgba(255,255,255,.1); border-radius: 2px; }

.sidebar-section {
    padding: 20px 14px 8px;
}
.sidebar-section-label {
    font-size: .62rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: .08em;
    color: rgba(255,255,255,.3);
    padding: 0 10px;
    margin-bottom: 6px;
}

.sidebar-nav { display: flex; flex-direction: column; gap: 2px; }

.sidebar-item {
    display: flex;
    align-items: center;
    gap: 11px;
    padding: 10px 12px;
    border-radius: var(--radius-sm);
    text-decoration: none;
    color: rgba(255,255,255,.6);
    font-size: .84rem;
    font-weight: 600;
    cursor: pointer;
    border: 1px solid transparent;
    transition: all .2s;
    position: relative;
    background: transparent;
    width: 100%;
    text-align: left;
    font-family: 'DM Sans', sans-serif;
}
.sidebar-item:hover {
    background: rgba(255,255,255,.06);
    color: rgba(255,255,255,.9);
}
.sidebar-item.active {
    background: rgba(13,148,136,.18);
    border-color: rgba(13,148,136,.35);
    color: #fff;
}
.sidebar-item.active .si-icon {
    color: var(--teal);
}
.sidebar-item.active-returns {
    background: rgba(234,88,12,.18);
    border-color: rgba(234,88,12,.35);
    color: #fff;
}
.sidebar-item.active-returns .si-icon {
    color: #fb923c;
}
.si-icon {
    width: 32px; height: 32px;
    border-radius: var(--radius-sm);
    display: flex; align-items: center; justify-content: center;
    font-size: .95rem;
    flex-shrink: 0;
    background: rgba(255,255,255,.06);
    color: rgba(255,255,255,.45);
    transition: all .2s;
}
.sidebar-item.active .si-icon { background: rgba(13,148,136,.25); }
.sidebar-item.active-returns .si-icon { background: rgba(234,88,12,.25); }
.si-text { flex: 1; line-height: 1.3; }
.si-label { display: block; }
.si-sub { display: block; font-size: .68rem; font-weight: 400; color: rgba(255,255,255,.35); margin-top: 1px; }
.sidebar-item.active .si-sub { color: rgba(255,255,255,.5); }

.si-badge {
    font-family: 'DM Mono', monospace;
    font-size: .68rem;
    font-weight: 700;
    min-width: 22px;
    height: 22px;
    border-radius: 100px;
    display: flex; align-items: center; justify-content: center;
    padding: 0 6px;
    flex-shrink: 0;
}
.sib-amber   { background: rgba(217,119,6,.25);  color: #fcd34d; }
.sib-teal    { background: rgba(13,148,136,.25); color: #5eead4; }
.sib-orange  { background: rgba(234,88,12,.25);  color: #fb923c; }
.sib-red     { background: rgba(220,38,38,.25);  color: #fca5a5; }

.sidebar-divider {
    height: 1px;
    background: rgba(255,255,255,.07);
    margin: 8px 14px;
}

.sidebar-footer {
    margin-top: auto;
    padding: 16px 14px;
    border-top: 1px solid rgba(255,255,255,.07);
}
.sidebar-footer-info {
    display: flex; align-items: center; gap: 9px;
    padding: 10px 12px;
    border-radius: var(--radius-sm);
    background: rgba(255,255,255,.04);
}
.sfi-icon {
    width: 30px; height: 30px;
    background: rgba(13,148,136,.2);
    border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    color: var(--teal);
    font-size: .8rem;
    flex-shrink: 0;
}
.sfi-text { font-size: .72rem; color: rgba(255,255,255,.4); line-height: 1.3; }
.sfi-text strong { display: block; color: rgba(255,255,255,.7); font-weight: 700; font-size: .75rem; }

/* ══════════════════════════════════
   MAIN CONTENT
══════════════════════════════════*/
.main-content {
    flex: 1;
    margin-left: var(--sidebar-w);
    padding: 32px 28px;
    min-width: 0;
}

/* ── PAGE HEADER ── */
.page-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 16px;
    margin-bottom: 28px;
    flex-wrap: wrap;
}
.page-header-text h1 {
    font-size: 1.5rem;
    font-weight: 800;
    color: var(--slate-900);
    letter-spacing: -.4px;
    margin-bottom: 3px;
}
.page-header-text p { color: var(--slate-500); font-size: .875rem; }
.page-header-badge {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 6px 14px;
    border-radius: var(--radius-sm);
    font-size: .75rem;
    font-weight: 700;
    white-space: nowrap;
}
.phb-amber  { background: var(--amber-light); color: #92400e; border: 1px solid #fcd34d; }
.phb-teal   { background: var(--teal-light); color: var(--teal-dark); border: 1px solid #99f6e4; }
.phb-orange { background: var(--orange-light); color: #9a3412; border: 1px solid #fdba74; }

/* ── ALERTS ── */
.alert-bar {
    display: flex; align-items: center; gap: 10px;
    padding: 14px 18px; border-radius: var(--radius); margin-bottom: 24px;
    font-size: .875rem; font-weight: 500; animation: fadeSlide .3s ease;
}
@keyframes fadeSlide { from{opacity:0;transform:translateY(-6px)} to{opacity:1;transform:none} }
.alert-success { background: var(--green-light); color: #14532d; border: 1px solid #86efac; }
.alert-error   { background: var(--red-light);   color: #7f1d1d; border: 1px solid #fca5a5; }

/* ── EMPTY STATE ── */
.empty-portal { text-align: center; padding: 72px 24px; color: var(--slate-400); }
.empty-portal-icon { width: 72px; height: 72px; background: var(--slate-100); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 16px; font-size: 2rem; color: var(--slate-400); }
.empty-portal h3 { font-size: 1.1rem; font-weight: 700; color: var(--slate-600); margin-bottom: 6px; }
.empty-portal p  { font-size: .875rem; max-width: 320px; margin: 0 auto; }

/* ── REQUEST CARD ── */
.req-card { background: #fff; border: 1px solid var(--slate-200); border-radius: var(--radius-lg); margin-bottom: 20px; box-shadow: var(--shadow-sm); overflow: hidden; transition: box-shadow .2s; }
.req-card:hover { box-shadow: var(--shadow); }
.req-card-header { background: var(--navy); padding: 14px 20px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px; }
.req-card-id { display: flex; align-items: center; gap: 10px; }
.req-num { background: rgba(255,255,255,.15); color: #fff; border-radius: var(--radius-xs); padding: 3px 10px; font-family: 'DM Mono', monospace; font-size: .8rem; font-weight: 500; }
.req-dept-tag { color: #fff; font-size: .875rem; font-weight: 700; display: flex; align-items: center; gap: 5px; }
.req-card-meta { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
.meta-chip { background: rgba(255,255,255,.12); color: rgba(255,255,255,.85); border-radius: var(--radius-xs); padding: 3px 9px; font-size: .7rem; font-weight: 600; display: inline-flex; align-items: center; gap: 4px; }
.req-card-body { padding: 20px; }

/* Pricing */
.pricing-options { display: flex; align-items: flex-end; gap: 14px; flex-wrap: wrap; padding: 14px 16px; background: var(--slate-50); border-radius: var(--radius-sm); border: 1px solid var(--slate-200); margin-bottom: 16px; }
.pricing-opt-group { display: flex; flex-direction: column; gap: 4px; }
.pricing-opt-group label { font-size: .68rem; font-weight: 700; text-transform: uppercase; letter-spacing: .5px; color: var(--slate-500); }
.portal-select { padding: 8px 12px; border: 1px solid var(--slate-200); border-radius: var(--radius-sm); font-family: 'DM Sans', sans-serif; font-size: .83rem; font-weight: 500; color: var(--slate-700); background: #fff; cursor: pointer; transition: all .2s; min-width: 150px; }
.portal-select:focus { outline: none; border-color: var(--teal); box-shadow: 0 0 0 2px rgba(13,148,136,.15); }
.portal-date { padding: 8px 12px; border: 1px solid var(--slate-200); border-radius: var(--radius-sm); font-family: 'DM Mono', monospace; font-size: .83rem; color: var(--slate-700); background: #fff; cursor: pointer; transition: all .2s; }
.portal-date:focus { outline: none; border-color: var(--teal); box-shadow: 0 0 0 2px rgba(13,148,136,.15); }

.portal-table-wrap { border: 1px solid var(--slate-200); border-radius: var(--radius-sm); overflow: hidden; }
.portal-table { width: 100%; border-collapse: collapse; }
.portal-table thead th { background: var(--slate-100); color: var(--slate-500); font-size: .68rem; font-weight: 700; text-transform: uppercase; letter-spacing: .5px; padding: 10px 14px; border-bottom: 1px solid var(--slate-200); text-align: left; white-space: nowrap; }
.portal-table tbody td { padding: 12px 14px; border-bottom: 1px solid var(--slate-100); font-size: .83rem; color: var(--slate-700); vertical-align: middle; }
.portal-table tbody tr:last-child td { border-bottom: none; }
.portal-table tbody tr:hover { background: var(--slate-50); }
.item-name-cell { font-weight: 600; color: var(--slate-800); }
.qty-badge { display: inline-flex; align-items: center; background: var(--teal-light); color: var(--teal-dark); border-radius: var(--radius-xs); padding: 2px 8px; font-family: 'DM Mono', monospace; font-size: .78rem; font-weight: 500; }
.price-input-wrap { display: flex; align-items: center; gap: 6px; }
.currency-prefix { font-size: .8rem; font-weight: 700; color: var(--slate-500); }
.price-inp { width: 110px; padding: 7px 10px; border: 1px solid var(--slate-200); border-radius: var(--radius-sm); font-family: 'DM Mono', monospace; font-size: .83rem; text-align: right; background: #fff; color: var(--slate-800); transition: all .2s; }
.price-inp:focus { outline: none; border-color: var(--teal); box-shadow: 0 0 0 2px rgba(13,148,136,.15); }
.price-inp.has-value { border-color: var(--teal); background: #f0fdfa; }
.unit-inp { width: 70px; padding: 7px 8px; border: 1px solid var(--slate-200); border-radius: var(--radius-sm); font-size: .8rem; background: #fff; color: var(--slate-700); transition: all .2s; text-align: center; }
.unit-inp:focus { outline: none; border-color: var(--teal); box-shadow: 0 0 0 2px rgba(13,148,136,.15); }
.pcs-inp { width: 60px; padding: 7px 8px; border: 1px solid var(--slate-200); border-radius: var(--radius-sm); font-family: 'DM Mono', monospace; font-size: .8rem; text-align: center; background: #fff; color: var(--slate-700); transition: all .2s; }
.pcs-inp:focus { outline: none; border-color: var(--teal); box-shadow: 0 0 0 2px rgba(13,148,136,.15); }
.line-total-cell { font-family: 'DM Mono', monospace; font-size: .83rem; font-weight: 500; color: var(--slate-600); text-align: right; min-width: 100px; }
.line-total-cell.filled { color: var(--teal-dark); font-weight: 700; }

.req-card-footer { padding: 12px 20px; background: var(--slate-50); border-top: 1px solid var(--slate-200); display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px; }
.subtotal-bar { display: flex; align-items: center; gap: 8px; font-size: .83rem; color: var(--slate-600); }
.subtotal-bar .stval { font-family: 'DM Mono', monospace; font-size: .95rem; font-weight: 700; color: var(--teal-dark); }
.prices-progress { display: flex; align-items: center; gap: 6px; font-size: .75rem; color: var(--slate-500); }
.prices-progress .prog-bar { width: 80px; height: 5px; background: var(--slate-200); border-radius: 100px; overflow: hidden; }
.prices-progress .prog-fill { height: 100%; background: var(--teal); border-radius: 100px; transition: width .3s; }

.submit-section {
    background: var(--navy); border-radius: var(--radius-lg); padding: 20px 24px; margin-top: 12px;
    display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 16px;
    position: sticky; bottom: 16px; box-shadow: 0 8px 32px rgba(13,27,42,.4);
}
.submit-section-info { color: rgba(255,255,255,.85); }
.submit-section-info strong { display: block; font-size: .95rem; font-weight: 700; color: #fff; margin-bottom: 2px; }
.submit-section-info small { font-size: .78rem; opacity: .65; }
.grand-total-display { display: flex; flex-direction: column; align-items: flex-end; color: rgba(255,255,255,.7); }
.grand-total-display .gt-label { font-size: .7rem; text-transform: uppercase; letter-spacing: .5px; margin-bottom: 2px; }
.grand-total-display .gt-amount { font-family: 'DM Mono', monospace; font-size: 1.4rem; font-weight: 500; color: #fff; }
.btn-submit-prices { background: var(--teal); color: #fff; border: none; border-radius: var(--radius-sm); padding: 11px 24px; font-size: .9rem; font-weight: 700; font-family: 'DM Sans', sans-serif; cursor: pointer; display: inline-flex; align-items: center; gap: 8px; transition: all .2s; letter-spacing: -.2px; }
.btn-submit-prices:hover { background: var(--teal-dark); transform: translateY(-1px); box-shadow: 0 6px 20px rgba(13,148,136,.4); }
.btn-submit-prices:disabled { background: var(--slate-600); cursor: not-allowed; transform: none; box-shadow: none; }

/* ── HISTORY VIEW ── */
.history-card { background: #fff; border: 1px solid var(--slate-200); border-radius: var(--radius-lg); overflow: hidden; margin-bottom: 16px; box-shadow: var(--shadow-sm); }
.history-card-header { padding: 14px 20px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 8px; border-bottom: 1px solid var(--slate-100); }
.hist-status { display: inline-flex; align-items: center; gap: 5px; border-radius: 100px; padding: 4px 10px; font-size: .72rem; font-weight: 700; text-transform: uppercase; letter-spacing: .4px; }
.hs-receiving { background: #e0f2fe; color: #0c4a6e; }
.hs-completed { background: #dcfce7; color: #14532d; }
.history-table { width: 100%; border-collapse: collapse; }
.history-table th { background: var(--slate-50); color: var(--slate-500); font-size: .68rem; font-weight: 700; text-transform: uppercase; letter-spacing: .5px; padding: 9px 14px; border-bottom: 1px solid var(--slate-100); text-align: left; }
.history-table td { padding: 10px 14px; border-bottom: 1px solid var(--slate-50); font-size: .82rem; color: var(--slate-700); }
.history-table tr:last-child td { border-bottom: none; }
.history-total-row td { background: var(--slate-50); font-weight: 700; border-top: 2px solid var(--slate-200); }

/* ══════════════════════════════════
   RETURN REQUESTS VIEW
══════════════════════════════════*/
.returns-header-strip {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 14px;
    margin-bottom: 24px;
}
.ret-stat {
    background: #fff;
    border: 1px solid var(--slate-200);
    border-radius: var(--radius);
    padding: 16px 18px;
    display: flex;
    align-items: center;
    gap: 12px;
    box-shadow: var(--shadow-sm);
}
.ret-stat-icon {
    width: 38px; height: 38px;
    border-radius: var(--radius-sm);
    display: flex; align-items: center; justify-content: center;
    font-size: 1rem; flex-shrink: 0;
}
.ri-amber  { background: #fef3c7; color: #d97706; }
.ri-green  { background: #dcfce7; color: #16a34a; }
.ri-red    { background: #fee2e2; color: #dc2626; }

.returns-filter-bar {
    display: flex;
    gap: 8px;
    margin-bottom: 18px;
    flex-wrap: wrap;
}
.filter-pill {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 16px;
    border-radius: 100px;
    font-size: .78rem;
    font-weight: 700;
    cursor: pointer;
    border: 1.5px solid transparent;
    background: var(--slate-100);
    color: var(--slate-600);
    transition: all var(--transition);
    font-family: 'DM Sans', sans-serif;
}
.filter-pill:hover { background: var(--slate-200); }
.filter-pill.active { background: var(--navy); color: #fff; border-color: transparent; }
.filter-pill.fp-pending.active  { background: #fef3c7; color: #92400e; border-color: #fcd34d; }
.filter-pill.fp-approved.active { background: #dcfce7; color: #14532d; border-color: #86efac; }
.filter-pill.fp-rejected.active { background: #fee2e2; color: #7f1d1d; border-color: #fca5a5; }

.return-card {
    background: #fff;
    border: 1px solid var(--slate-200);
    border-radius: var(--radius-lg);
    margin-bottom: 16px;
    overflow: hidden;
    box-shadow: var(--shadow-sm);
    transition: box-shadow var(--transition);
}
.return-card:hover { box-shadow: var(--shadow); }

.return-card-head {
    padding: 14px 20px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 10px;
    border-bottom: 1px solid var(--slate-100);
    background: var(--slate-50);
}
.rch-left { display: flex; align-items: center; gap: 10px; }
.ret-id-badge {
    font-family: 'DM Mono', monospace;
    font-size: .78rem;
    font-weight: 600;
    background: var(--slate-200);
    color: var(--slate-600);
    border-radius: var(--radius-xs);
    padding: 3px 9px;
}
.ret-item-name { font-weight: 700; color: var(--slate-800); font-size: .9rem; }
.rch-right { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }

.return-card-body {
    padding: 18px 20px;
    display: grid;
    grid-template-columns: 1fr 1fr auto;
    gap: 20px;
    align-items: start;
}
.ret-detail-group { display: flex; flex-direction: column; gap: 10px; }
.ret-detail-row { display: flex; flex-direction: column; gap: 3px; }
.ret-detail-label { font-size: .68rem; font-weight: 800; text-transform: uppercase; letter-spacing: .07em; color: var(--slate-400); }
.ret-detail-value { font-size: .875rem; font-weight: 600; color: var(--slate-700); }
.ret-reason-box {
    background: var(--slate-50);
    border: 1px solid var(--slate-200);
    border-radius: var(--radius-sm);
    padding: 10px 14px;
    font-size: .83rem;
    color: var(--slate-600);
    line-height: 1.5;
}
.ret-photo-wrap { flex-shrink: 0; }
.ret-photo-img {
    width: 80px; height: 80px;
    object-fit: cover;
    border-radius: var(--radius-sm);
    border: 1px solid var(--slate-200);
    cursor: pointer;
    transition: transform var(--transition), box-shadow var(--transition);
}
.ret-photo-img:hover { transform: scale(1.06); box-shadow: var(--shadow); }
.no-photo-ret {
    width: 80px; height: 80px;
    background: var(--slate-100);
    border-radius: var(--radius-sm);
    display: flex; flex-direction: column; align-items: center; justify-content: center;
    gap: 4px;
    border: 1px dashed var(--slate-300);
    color: var(--slate-400);
    font-size: .65rem;
    font-weight: 700;
    text-transform: uppercase;
}
.no-photo-ret i { font-size: 1.3rem; }

.return-actions {
    display: flex;
    flex-direction: column;
    gap: 8px;
    min-width: 160px;
}
.ret-action-title { font-size: .65rem; font-weight: 800; text-transform: uppercase; letter-spacing: .07em; color: var(--slate-400); margin-bottom: 2px; }
.btn-ret-approve {
    display: inline-flex; align-items: center; justify-content: center; gap: 6px;
    padding: 9px 16px; border-radius: var(--radius-sm);
    background: var(--green); color: #fff;
    border: none; font-family: 'DM Sans', sans-serif; font-size: .83rem; font-weight: 700;
    cursor: pointer; transition: all .2s; width: 100%;
    box-shadow: 0 2px 8px rgba(22,163,74,.25);
}
.btn-ret-approve:hover { background: #15803d; transform: translateY(-1px); }
.btn-ret-reject {
    display: inline-flex; align-items: center; justify-content: center; gap: 6px;
    padding: 9px 16px; border-radius: var(--radius-sm);
    background: #fff; color: var(--red);
    border: 1.5px solid #fca5a5;
    font-family: 'DM Sans', sans-serif; font-size: .83rem; font-weight: 700;
    cursor: pointer; transition: all .2s; width: 100%;
}
.btn-ret-reject:hover { background: var(--red-light); border-color: var(--red); transform: translateY(-1px); }
.btn-ret-returned {
    display: inline-flex; align-items: center; justify-content: center; gap: 6px;
    padding: 9px 16px; border-radius: var(--radius-sm);
    background: #e0f2fe; color: #0c4a6e;
    border: 1.5px solid #bae6fd;
    font-family: 'DM Sans', sans-serif; font-size: .83rem; font-weight: 700;
    cursor: pointer; transition: all .2s; width: 100%;
}
.btn-ret-returned:hover { background: #bae6fd; transform: translateY(-1px); }

/* Status badges */
.status-pill {
    display: inline-flex; align-items: center; gap: 5px;
    padding: 4px 12px; border-radius: 100px;
    font-size: .72rem; font-weight: 800; white-space: nowrap; letter-spacing: .03em;
}
.sp-pending  { background: #fef3c7; color: #92400e; }
.sp-approved { background: #dcfce7; color: #14532d; }
.sp-rejected { background: #fee2e2; color: #7f1d1d; }
.sp-returned { background: #e0f2fe; color: #0c4a6e; }
.sp-unknown  { background: var(--slate-100); color: var(--slate-500); }

/* User avatar row */
.user-row { display: flex; align-items: center; gap: 7px; }
.user-av {
    width: 26px; height: 26px; border-radius: 50%;
    background: #dbeafe; color: #1d4ed8;
    font-size: .7rem; font-weight: 800;
    display: flex; align-items: center; justify-content: center; flex-shrink: 0;
}

/* Lightbox */
.lb-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,.82); z-index: 9999; align-items: center; justify-content: center; backdrop-filter: blur(4px); }
.lb-overlay.open { display: flex; }
.lb-overlay img { max-width: 90vw; max-height: 85vh; border-radius: var(--radius); box-shadow: 0 24px 64px rgba(0,0,0,.5); }
.lb-close { position: absolute; top: 20px; right: 24px; font-size: 1.8rem; color: #fff; cursor: pointer; background: rgba(255,255,255,.12); width: 38px; height: 38px; border-radius: 50%; display: flex; align-items: center; justify-content: center; transition: opacity .2s; }
.lb-close:hover { opacity: .7; }

/* Sidebar overlay for mobile */
.sidebar-overlay {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,.5);
    z-index: 140;
    backdrop-filter: blur(2px);
}
.sidebar-overlay.open { display: block; }

/* ══════════════════════════════════
   RESPONSIVE
══════════════════════════════════*/
@media(max-width: 900px) {
    :root { --sidebar-w: 240px; }
}

@media(max-width: 768px) {
    .portal-topbar { padding: 0 16px; }
    .topbar-date { display: none; }
    .hamburger-btn { display: flex; align-items: center; justify-content: center; }

    .sidebar {
        transform: translateX(-100%);
    }
    .sidebar.open {
        transform: translateX(0);
    }

    .main-content {
        margin-left: 0;
        padding: 20px 16px;
    }

    .pricing-options { flex-direction: column; align-items: stretch; }
    .submit-section { flex-direction: column; }
    .btn-submit-prices { width: 100%; justify-content: center; }
    .returns-header-strip { grid-template-columns: 1fr 1fr; }
    .return-card-body { grid-template-columns: 1fr; }
    .page-header { flex-direction: column; align-items: flex-start; }
}
</style>
</head>
<body>

<!-- ════════ TOP BAR ════════ -->
<header class="portal-topbar">
    <div style="display:flex;align-items:center;gap:12px;">
        <button class="hamburger-btn" id="sidebarToggle" onclick="toggleSidebar()">
            <i class="bi bi-list"></i>
        </button>
        <a href="#" class="portal-logo">
            <div class="portal-logo-icon"><i class="bi bi-shop"></i></div>
            <div class="portal-logo-text">
                Supplier Portal
                <small>Purchase &amp; Return Management</small>
            </div>
        </a>
    </div>
    <div class="topbar-right">
        <span class="topbar-date">
            <i class="bi bi-calendar3"></i>
            <?= date('F j, Y') ?>
        </span>
        <span class="topbar-badge">Admin View</span>
        <a href="department_request.php?status=purchased" class="back-btn">
            <i class="bi bi-arrow-left"></i> Back
        </a>
    </div>
</header>

<!-- Sidebar overlay (mobile) -->
<div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>

<!-- ════════ LAYOUT ════════ -->
<div class="layout-wrap">

    <!-- ════════ SIDEBAR ════════ -->
    <aside class="sidebar" id="sidebar">

        <div class="sidebar-section">
            <div class="sidebar-section-label">Navigation</div>
            <nav class="sidebar-nav">

                <!-- Process Order -->
                <button class="sidebar-item <?= $filterView === 'pending_prices' ? 'active' : '' ?>"
                        onclick="switchView('pending_prices')" id="nav-pending_prices">
                    <div class="si-icon"><i class="bi bi-cart-plus"></i></div>
                    <div class="si-text">
                        <span class="si-label">Process Order</span>
                        <span class="si-sub">Set prices & send back</span>
                    </div>
                    <?php if ($pendingPricesCount > 0): ?>
                    <span class="si-badge sib-amber"><?= $pendingPricesCount ?></span>
                    <?php endif; ?>
                </button>

                <!-- Completed Orders -->
                <button class="sidebar-item <?= $filterView === 'priced' ? 'active' : '' ?>"
                        onclick="switchView('priced')" id="nav-priced">
                    <div class="si-icon"><i class="bi bi-bag-check-fill"></i></div>
                    <div class="si-text">
                        <span class="si-label">Completed Orders</span>
                        <span class="si-sub">Priced &amp; sent to hospital</span>
                    </div>
                    <?php if ($pricedCount > 0): ?>
                    <span class="si-badge sib-teal"><?= $pricedCount ?></span>
                    <?php endif; ?>
                </button>

            </nav>
        </div>

        <div class="sidebar-divider"></div>

        <div class="sidebar-section">
            <div class="sidebar-section-label">Requests</div>
            <nav class="sidebar-nav">

                <!-- Return Request -->
                <button class="sidebar-item <?= $filterView === 'returns' ? 'active-returns' : '' ?>"
                        onclick="switchView('returns')" id="nav-returns">
                    <div class="si-icon"><i class="bi bi-arrow-return-left"></i></div>
                    <div class="si-text">
                        <span class="si-label">Return Request</span>
                        <span class="si-sub">Review &amp; damage reports</span>
                    </div>
                    <?php if ($retPending > 0): ?>
                    <span class="si-badge sib-orange"><?= $retPending ?></span>
                    <?php endif; ?>
                </button>

            </nav>
        </div>

        <div class="sidebar-divider"></div>

        <!-- Stats mini summary -->
        <div class="sidebar-section">
            <div class="sidebar-section-label">Overview</div>
            <div style="display:flex;flex-direction:column;gap:6px;padding:0 4px;">
                <div style="display:flex;justify-content:space-between;align-items:center;padding:8px 10px;background:rgba(255,255,255,.04);border-radius:var(--radius-sm);">
                    <span style="font-size:.75rem;color:rgba(255,255,255,.5);display:flex;align-items:center;gap:6px;"><i class="bi bi-clock" style="color:#fcd34d;"></i> Pending Pricing</span>
                    <span style="font-family:'DM Mono',monospace;font-size:.85rem;font-weight:700;color:#fff;"><?= $pendingPricesCount ?></span>
                </div>
                <div style="display:flex;justify-content:space-between;align-items:center;padding:8px 10px;background:rgba(255,255,255,.04);border-radius:var(--radius-sm);">
                    <span style="font-size:.75rem;color:rgba(255,255,255,.5);display:flex;align-items:center;gap:6px;"><i class="bi bi-check-circle" style="color:#5eead4;"></i> Completed</span>
                    <span style="font-family:'DM Mono',monospace;font-size:.85rem;font-weight:700;color:#fff;"><?= $pricedCount ?></span>
                </div>
                <div style="display:flex;justify-content:space-between;align-items:center;padding:8px 10px;background:rgba(255,255,255,.04);border-radius:var(--radius-sm);">
                    <span style="font-size:.75rem;color:rgba(255,255,255,.5);display:flex;align-items:center;gap:6px;"><i class="bi bi-arrow-return-left" style="color:#fb923c;"></i> Returns</span>
                    <span style="font-family:'DM Mono',monospace;font-size:.85rem;font-weight:700;color:#fff;"><?= $returnCount ?></span>
                </div>
            </div>
        </div>

        <div class="sidebar-footer">
            <div class="sidebar-footer-info">
                <div class="sfi-icon"><i class="bi bi-shield-check"></i></div>
                <div class="sfi-text">
                    <strong>Admin Access</strong>
                    Full portal management
                </div>
            </div>
        </div>

    </aside>

    <!-- ════════ MAIN CONTENT ════════ -->
    <main class="main-content">

        <?php if ($successMessage): ?>
        <div class="alert-bar alert-success">
            <i class="bi bi-check-circle-fill"></i>
            <span><?= htmlspecialchars($successMessage) ?></span>
        </div>
        <?php endif; ?>
        <?php if ($errorMessage): ?>
        <div class="alert-bar alert-error">
            <i class="bi bi-exclamation-triangle-fill"></i>
            <span><?= htmlspecialchars($errorMessage) ?></span>
        </div>
        <?php endif; ?>

        <!-- ════════ PROCESS ORDER VIEW ════════ -->
        <div id="view-pending_prices" class="<?= $filterView !== 'pending_prices' ? 'd-none' : '' ?>">

            <div class="page-header">
                <div class="page-header-text">
                    <h1><i class="bi bi-cart-plus" style="color:var(--teal);margin-right:10px;"></i>Process Order</h1>
                    <p>Set unit prices for purchased items and send them back to the hospital for receiving.</p>
                </div>
                <?php if ($pendingPricesCount > 0): ?>
                <span class="page-header-badge phb-amber">
                    <i class="bi bi-hourglass-split"></i>
                    <?= $pendingPricesCount ?> awaiting pricing
                </span>
                <?php endif; ?>
            </div>

        <?php if (empty($purchasedRequests)): ?>
            <div class="empty-portal">
                <div class="empty-portal-icon"><i class="bi bi-inbox"></i></div>
                <h3>No Pending Orders</h3>
                <p>All purchase orders have been priced and sent back to the hospital. Check <strong>Completed Orders</strong> for history.</p>
            </div>
        <?php else: ?>

        <form method="post" id="priceForm">
            <input type="hidden" name="action" value="set_prices">

            <?php foreach ($purchasedRequests as $req):
                $items    = $req['items'];
                $purchDate = $req['purchased_at'] ?? $req['created_at'];
            ?>
            <input type="hidden" name="request_ids[]" value="<?= $req['id'] ?>">

            <div class="req-card" data-req-id="<?= $req['id'] ?>">
                <div class="req-card-header">
                    <div class="req-card-id">
                        <span class="req-num">#<?= str_pad($req['id'], 4, '0', STR_PAD_LEFT) ?></span>
                        <span class="req-dept-tag">
                            <i class="bi bi-building"></i>
                            <?= htmlspecialchars($req['department']) ?>
                        </span>
                    </div>
                    <div class="req-card-meta">
                        <span class="meta-chip"><i class="bi bi-boxes"></i> <?= count($items) ?> item<?= count($items) !== 1 ? 's' : '' ?></span>
                        <?php if ($purchDate): ?>
                        <span class="meta-chip"><i class="bi bi-calendar-check"></i> Ordered: <?= date('M j, Y', strtotime($purchDate)) ?></span>
                        <?php endif; ?>
                        <span class="meta-chip" style="background:rgba(13,148,136,.3);color:#ccfbf1;">
                            <i class="bi bi-clock"></i> Needs Pricing
                        </span>
                    </div>
                </div>

                <div class="req-card-body">
                    <div class="pricing-options">
                        <div class="pricing-opt-group">
                            <label>Payment Method</label>
                            <select class="portal-select" name="payment_type[<?= $req['id'] ?>]">
                                <option value="Direct">💵 Direct Payment</option>
                                <option value="Credit">💳 Credit</option>
                                <option value="Check">🏦 Check</option>
                                <option value="Terms">📋 Net 30 Terms</option>
                            </select>
                        </div>
                        <div class="pricing-opt-group">
                            <label>Estimated Delivery Date</label>
                            <input type="date" class="portal-date" name="estimated_delivery[<?= $req['id'] ?>]"
                                   min="<?= date('Y-m-d') ?>"
                                   value="<?= date('Y-m-d', strtotime('+3 days')) ?>">
                        </div>
                        <div class="pricing-opt-group" style="margin-left:auto;">
                            <label>Progress</label>
                            <div class="prices-progress">
                                <div class="prog-bar">
                                    <div class="prog-fill" id="prog-<?= $req['id'] ?>" style="width:0%"></div>
                                </div>
                                <span id="prog-text-<?= $req['id'] ?>">0 / <?= count($items) ?> priced</span>
                            </div>
                        </div>
                    </div>

                    <div class="portal-table-wrap">
                    <table class="portal-table" id="table-<?= $req['id'] ?>">
                        <thead>
                            <tr>
                                <th>Item Name</th>
                                <th style="text-align:center;">Approved Qty</th>
                                <th>Unit</th>
                                <th style="text-align:center;">Pcs / Box</th>
                                <th style="text-align:right;">Unit Price (₱)</th>
                                <th style="text-align:right;">Line Total (₱)</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($items as $item):
                            $approved_qty = (int)($item['approved_quantity'] ?? 0);
                            if ($approved_qty <= 0) continue;
                        ?>
                        <tr data-item-id="<?= $item['id'] ?>" data-qty="<?= $approved_qty ?>" data-req="<?= $req['id'] ?>">
                            <td class="item-name-cell"><?= htmlspecialchars($item['item_name']) ?></td>
                            <td style="text-align:center;"><span class="qty-badge"><?= $approved_qty ?></span></td>
                            <td>
                                <input type="text" class="unit-inp"
                                       name="unit[<?= $item['id'] ?>]"
                                       value="<?= htmlspecialchars($item['unit'] ?? 'pcs') ?>"
                                       placeholder="pcs">
                            </td>
                            <td style="text-align:center;">
                                <input type="number" class="pcs-inp"
                                       name="pcs_per_box[<?= $item['id'] ?>]"
                                       value="<?= $item['pcs_per_box'] ?? 1 ?>" min="1">
                            </td>
                            <td>
                                <div class="price-input-wrap" style="justify-content:flex-end;">
                                    <span class="currency-prefix">₱</span>
                                    <input type="number" step="0.01" min="0" class="price-inp"
                                           name="price[<?= $item['id'] ?>]" placeholder="0.00"
                                           data-item-id="<?= $item['id'] ?>" data-req="<?= $req['id'] ?>">
                                </div>
                            </td>
                            <td class="line-total-cell" id="lt-<?= $item['id'] ?>">—</td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    </div>
                </div>

                <div class="req-card-footer">
                    <div class="subtotal-bar">
                        <span>Subtotal:</span>
                        <span class="stval" id="sub-<?= $req['id'] ?>">₱0.00</span>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>

            <div class="submit-section">
                <div class="submit-section-info">
                    <strong><i class="bi bi-send-fill" style="margin-right:6px;"></i>Submit Prices to Hospital</strong>
                    <small>All <?= $pendingPricesCount ?> request<?= $pendingPricesCount !== 1 ? 's' : '' ?> will be marked as <em>Receiving</em>.</small>
                </div>
                <div class="grand-total-display">
                    <span class="gt-label">Grand Total</span>
                    <span class="gt-amount" id="grandTotal">₱0.00</span>
                </div>
                <button type="submit" class="btn-submit-prices">
                    <i class="bi bi-check2-circle"></i>
                    Confirm &amp; Send Back to Hospital
                </button>
            </div>
        </form>

        <?php endif; ?>
        </div>

        <!-- ════════ COMPLETED ORDERS VIEW ════════ -->
        <div id="view-priced" class="<?= $filterView !== 'priced' ? 'd-none' : '' ?>">

            <div class="page-header">
                <div class="page-header-text">
                    <h1><i class="bi bi-bag-check-fill" style="color:var(--teal);margin-right:10px;"></i>Completed Orders</h1>
                    <p>Orders that have been priced and sent back to the hospital for receiving.</p>
                </div>
                <?php if ($pricedCount > 0): ?>
                <span class="page-header-badge phb-teal">
                    <i class="bi bi-check-circle-fill"></i>
                    <?= $pricedCount ?> orders
                </span>
                <?php endif; ?>
            </div>

        <?php if (empty($receivingRequests)): ?>
            <div class="empty-portal">
                <div class="empty-portal-icon"><i class="bi bi-archive"></i></div>
                <h3>No Orders Yet</h3>
                <p>Once you submit prices, processed orders will appear here.</p>
            </div>
        <?php else: ?>

            <?php foreach ($receivingRequests as $rr):
                $hStatus = strtolower(trim($rr['status'] ?? ''));
            ?>
            <div class="history-card">
                <div class="history-card-header">
                    <div style="display:flex;align-items:center;gap:10px;">
                        <span style="background:var(--slate-100);border-radius:4px;padding:3px 9px;font-family:'DM Mono',monospace;font-size:.78rem;color:var(--slate-600);">
                            #<?= str_pad($rr['id'], 4, '0', STR_PAD_LEFT) ?>
                        </span>
                        <span style="font-weight:700;color:var(--slate-800);">
                            <i class="bi bi-building" style="color:var(--slate-400);margin-right:4px;"></i>
                            <?= htmlspecialchars($rr['department']) ?>
                        </span>
                    </div>
                    <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
                        <span class="hist-status <?= $hStatus === 'completed' ? 'hs-completed' : 'hs-receiving' ?>">
                            <i class="bi <?= $hStatus === 'completed' ? 'bi-patch-check-fill' : 'bi-box-seam' ?>"></i>
                            <?= ucfirst($hStatus) ?>
                        </span>
                        <?php if (!empty($rr['payment_type'])): ?>
                        <span style="background:var(--slate-100);color:var(--slate-600);border-radius:4px;padding:3px 9px;font-size:.72rem;font-weight:600;">
                            <?= htmlspecialchars($rr['payment_type']) ?>
                        </span>
                        <?php endif; ?>
                        <?php if (!empty($rr['estimated_delivery'])): ?>
                        <span style="background:#f0fdf4;color:#166534;border-radius:4px;padding:3px 9px;font-size:.72rem;font-weight:600;">
                            <i class="bi bi-truck" style="margin-right:3px;"></i>
                            Est. <?= date('M j, Y', strtotime($rr['estimated_delivery'])) ?>
                        </span>
                        <?php endif; ?>
                    </div>
                </div>
                <?php
                $hItems = $pdo->prepare("SELECT * FROM department_request_items WHERE request_id=? ORDER BY id ASC");
                $hItems->execute([$rr['id']]);
                $histItems = $hItems->fetchAll(PDO::FETCH_ASSOC);
                $histTotal = 0;
                ?>
                <div style="overflow-x:auto;">
                <table class="history-table">
                    <thead>
                        <tr>
                            <th>Item Name</th>
                            <th style="text-align:center;">Approved Qty</th>
                            <th>Unit</th>
                            <th style="text-align:right;">Unit Price</th>
                            <th style="text-align:right;">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($histItems as $hi):
                        if (($hi['approved_quantity'] ?? 0) <= 0) continue;
                        $lineTotal  = (float)($hi['total_price'] ?? 0);
                        $histTotal += $lineTotal;
                    ?>
                    <tr>
                        <td style="font-weight:600;"><?= htmlspecialchars($hi['item_name']) ?></td>
                        <td style="text-align:center;font-family:'DM Mono',monospace;"><?= $hi['approved_quantity'] ?></td>
                        <td><?= htmlspecialchars($hi['unit'] ?? 'pcs') ?></td>
                        <td style="text-align:right;font-family:'DM Mono',monospace;color:var(--teal-dark);">
                            <?= $hi['price'] > 0 ? '₱' . number_format($hi['price'], 2) : '—' ?>
                        </td>
                        <td style="text-align:right;font-family:'DM Mono',monospace;font-weight:700;">
                            <?= $lineTotal > 0 ? '₱' . number_format($lineTotal, 2) : '—' ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if ($histTotal > 0): ?>
                    <tr class="history-total-row">
                        <td colspan="4" style="text-align:right;color:var(--slate-600);">Total Amount:</td>
                        <td style="text-align:right;font-family:'DM Mono',monospace;font-size:1rem;color:var(--teal-dark);">
                            ₱<?= number_format($histTotal, 2) ?>
                        </td>
                    </tr>
                    <?php endif; ?>
                    </tbody>
                </table>
                </div>
            </div>
            <?php endforeach; ?>

        <?php endif; ?>
        </div>

        <!-- ════════ RETURN REQUEST VIEW ════════ -->
        <div id="view-returns" class="<?= $filterView !== 'returns' ? 'd-none' : '' ?>">

            <div class="page-header">
                <div class="page-header-text">
                    <h1><i class="bi bi-arrow-return-left" style="color:var(--orange);margin-right:10px;"></i>Return Request</h1>
                    <p>Review return and damage requests submitted by hospital staff.</p>
                </div>
                <?php if ($retPending > 0): ?>
                <span class="page-header-badge phb-orange">
                    <i class="bi bi-hourglass-split"></i>
                    <?= $retPending ?> pending review
                </span>
                <?php endif; ?>
            </div>

            <!-- Mini stats -->
            <div class="returns-header-strip">
                <div class="ret-stat">
                    <div class="ret-stat-icon ri-amber"><i class="bi bi-hourglass-split"></i></div>
                    <div>
                        <div style="font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--slate-500);margin-bottom:3px;">Pending Review</div>
                        <div style="font-size:1.6rem;font-weight:800;color:var(--slate-800);line-height:1;"><?= $retPending ?></div>
                    </div>
                </div>
                <div class="ret-stat">
                    <div class="ret-stat-icon ri-green"><i class="bi bi-check-circle-fill"></i></div>
                    <div>
                        <div style="font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--slate-500);margin-bottom:3px;">Approved</div>
                        <div style="font-size:1.6rem;font-weight:800;color:var(--slate-800);line-height:1;"><?= $retApproved ?></div>
                    </div>
                </div>
                <div class="ret-stat">
                    <div class="ret-stat-icon ri-red"><i class="bi bi-x-circle-fill"></i></div>
                    <div>
                        <div style="font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--slate-500);margin-bottom:3px;">Rejected</div>
                        <div style="font-size:1.6rem;font-weight:800;color:var(--slate-800);line-height:1;"><?= $retRejected ?></div>
                    </div>
                </div>
            </div>

            <!-- Filter pills -->
            <div class="returns-filter-bar">
                <button class="filter-pill active" data-ret-filter="all" onclick="filterReturns('all', this)">
                    <i class="bi bi-grid-3x3-gap-fill"></i> All <span style="font-family:'DM Mono',monospace;">(<?= $returnCount ?>)</span>
                </button>
                <button class="filter-pill fp-pending" data-ret-filter="pending" onclick="filterReturns('pending', this)">
                    <i class="bi bi-hourglass-split"></i> Pending <span style="font-family:'DM Mono',monospace;">(<?= $retPending ?>)</span>
                </button>
                <button class="filter-pill fp-approved" data-ret-filter="approved" onclick="filterReturns('approved', this)">
                    <i class="bi bi-check-circle-fill"></i> Approved <span style="font-family:'DM Mono',monospace;">(<?= $retApproved ?>)</span>
                </button>
                <button class="filter-pill fp-rejected" data-ret-filter="rejected" onclick="filterReturns('rejected', this)">
                    <i class="bi bi-x-circle-fill"></i> Rejected <span style="font-family:'DM Mono',monospace;">(<?= $retRejected ?>)</span>
                </button>
            </div>

            <?php if (empty($returnRequests)): ?>
            <div class="empty-portal">
                <div class="empty-portal-icon"><i class="bi bi-arrow-return-left"></i></div>
                <h3>No Return Requests</h3>
                <p>Return and damage requests submitted by hospital staff will appear here for review.</p>
            </div>
            <?php else: ?>

            <?php foreach ($returnRequests as $rr):
                $s = strtolower($rr['status'] ?? 'pending');
                $statusMap = [
                    'pending'  => ['sp-pending',  'bi-hourglass-split',       'Pending'],
                    'approved' => ['sp-approved', 'bi-check-circle-fill',      'Approved'],
                    'rejected' => ['sp-rejected', 'bi-x-circle-fill',          'Rejected'],
                    'returned' => ['sp-returned', 'bi-arrow-counterclockwise', 'Returned'],
                ];
                [$sCls, $sIcon, $sLbl] = $statusMap[$s] ?? ['sp-unknown','bi-question-circle','Unknown'];
            ?>
            <div class="return-card" data-ret-status="<?= $s ?>">
                <div class="return-card-head">
                    <div class="rch-left">
                        <span class="ret-id-badge">#<?= str_pad($rr['id'], 4, '0', STR_PAD_LEFT) ?></span>
                        <span class="ret-item-name"><?= htmlspecialchars($rr['item_name']) ?></span>
                    </div>
                    <div class="rch-right">
                        <span class="status-pill <?= $sCls ?>">
                            <i class="bi <?= $sIcon ?>"></i> <?= $sLbl ?>
                        </span>
                        <span style="font-size:.75rem;color:var(--slate-400);font-family:'DM Mono',monospace;">
                            <?= isset($rr['requested_at']) ? date('M j, Y', strtotime($rr['requested_at'])) : '' ?>
                        </span>
                    </div>
                </div>

                <div class="return-card-body">
                    <!-- Left: details -->
                    <div class="ret-detail-group">
                        <div class="ret-detail-row">
                            <span class="ret-detail-label">Requested By</span>
                            <div class="user-row">
                                <div class="user-av"><?= strtoupper(substr($rr['username'], 0, 1)) ?></div>
                                <span class="ret-detail-value"><?= htmlspecialchars($rr['username']) ?></span>
                            </div>
                        </div>
                        <div class="ret-detail-row">
                            <span class="ret-detail-label">Quantity to Return</span>
                            <span class="ret-detail-value">
                                <span style="background:#f1f5f9;border:1px solid #e2e8f0;border-radius:4px;padding:2px 9px;font-family:'DM Mono',monospace;font-weight:700;">
                                    <?= $rr['quantity'] ?>
                                </span>
                                unit<?= $rr['quantity'] != 1 ? 's' : '' ?>
                            </span>
                        </div>
                    </div>

                    <!-- Middle: reason -->
                    <div>
                        <div class="ret-detail-label" style="margin-bottom:6px;">Reason for Return</div>
                        <div class="ret-reason-box"><?= htmlspecialchars($rr['reason']) ?></div>
                    </div>

                    <!-- Right: photo + actions -->
                    <div style="display:flex;flex-direction:column;gap:14px;align-items:flex-end;">
                        <div class="ret-photo-wrap">
                            <?php if (!empty($rr['photo'])): ?>
                                <img src="<?= htmlspecialchars($rr['photo']) ?>"
                                     alt="Evidence photo"
                                     class="ret-photo-img"
                                     onclick="openLightbox(this.src)">
                            <?php else: ?>
                                <div class="no-photo-ret">
                                    <i class="bi bi-image-alt"></i>
                                    No photo
                                </div>
                            <?php endif; ?>
                        </div>

                        <?php if ($s === 'pending'): ?>
                        <div class="return-actions">
                            <div class="ret-action-title">Actions</div>
                            <form method="post" style="margin:0;">
                                <input type="hidden" name="action"      value="update_return_status">
                                <input type="hidden" name="return_id"   value="<?= $rr['id'] ?>">
                                <input type="hidden" name="new_status"  value="Approved">
                                <button type="submit" class="btn-ret-approve"
                                        onclick="return confirm('Approve this return request? This will deduct the quantity from inventory.')">
                                    <i class="bi bi-check-circle-fill"></i> Approve
                                </button>
                            </form>
                            <form method="post" style="margin:0;">
                                <input type="hidden" name="action"      value="update_return_status">
                                <input type="hidden" name="return_id"   value="<?= $rr['id'] ?>">
                                <input type="hidden" name="new_status"  value="Rejected">
                                <button type="submit" class="btn-ret-reject"
                                        onclick="return confirm('Reject this return request?')">
                                    <i class="bi bi-x-circle"></i> Reject
                                </button>
                            </form>
                        </div>
                        <?php elseif ($s === 'approved'): ?>
                        <div class="return-actions">
                            <div class="ret-action-title">Mark as Returned</div>
                            <form method="post" style="margin:0;">
                                <input type="hidden" name="action"      value="update_return_status">
                                <input type="hidden" name="return_id"   value="<?= $rr['id'] ?>">
                                <input type="hidden" name="new_status"  value="Returned">
                                <button type="submit" class="btn-ret-returned"
                                        onclick="return confirm('Mark this item as physically returned?')">
                                    <i class="bi bi-arrow-counterclockwise"></i> Mark Returned
                                </button>
                            </form>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>

            <?php endif; ?>
        </div><!-- /view-returns -->

    </main>
</div><!-- /layout-wrap -->

<!-- Lightbox -->
<div class="lb-overlay" id="lightbox" onclick="closeLightbox()">
    <div class="lb-close" onclick="closeLightbox()"><i class="bi bi-x"></i></div>
    <img id="lbImg" src="" alt="Preview" onclick="event.stopPropagation()">
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
/* ══════════════════════════════════
   SIDEBAR TOGGLE (mobile)
══════════════════════════════════*/
function toggleSidebar() {
    document.getElementById('sidebar').classList.toggle('open');
    document.getElementById('sidebarOverlay').classList.toggle('open');
}
function closeSidebar() {
    document.getElementById('sidebar').classList.remove('open');
    document.getElementById('sidebarOverlay').classList.remove('open');
}

/* ══════════════════════════════════
   VIEW SWITCHER
══════════════════════════════════*/
function switchView(v) {
    // Hide all views
    ['pending_prices','priced','returns'].forEach(id => {
        document.getElementById('view-' + id).classList.add('d-none');
    });
    // Show selected view
    document.getElementById('view-' + v).classList.remove('d-none');

    // Update sidebar active states
    ['pending_prices','priced','returns'].forEach(id => {
        const navEl = document.getElementById('nav-' + id);
        if (!navEl) return;
        navEl.classList.remove('active', 'active-returns');
    });
    const activeNav = document.getElementById('nav-' + v);
    if (activeNav) {
        if (v === 'returns') activeNav.classList.add('active-returns');
        else activeNav.classList.add('active');
    }

    // Close mobile sidebar on nav
    if (window.innerWidth <= 768) closeSidebar();
}

/* ══════════════════════════════════
   RETURN FILTER
══════════════════════════════════*/
function filterReturns(status, btn) {
    document.querySelectorAll('.filter-pill').forEach(p => p.classList.remove('active'));
    btn.classList.add('active');
    document.querySelectorAll('.return-card').forEach(card => {
        const show = status === 'all' || card.dataset.retStatus === status;
        card.style.display = show ? '' : 'none';
    });
}

/* ══════════════════════════════════
   PRICE CALCULATION
══════════════════════════════════*/
function fc(n) {
    return '₱' + parseFloat(n || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function recalcAll() {
    let grandTotal = 0;
    const reqIds = new Set();
    document.querySelectorAll('.portal-table tbody tr[data-req]').forEach(r => reqIds.add(r.dataset.req));

    reqIds.forEach(reqId => {
        let subtotal = 0, totalItems = 0, pricedItems = 0;
        document.querySelectorAll(`.portal-table tbody tr[data-req="${reqId}"]`).forEach(row => {
            totalItems++;
            const priceEl = row.querySelector('.price-inp');
            if (!priceEl) return;
            const price = parseFloat(priceEl.value || 0);
            const qty   = parseFloat(row.dataset.qty || 0);
            const line  = price * qty;
            if (price > 0) { pricedItems++; priceEl.classList.add('has-value'); }
            else priceEl.classList.remove('has-value');
            const ltEl = document.getElementById('lt-' + row.dataset.itemId);
            if (ltEl) { ltEl.textContent = price > 0 ? fc(line) : '—'; ltEl.classList.toggle('filled', price > 0); }
            subtotal += line;
        });
        const subEl = document.getElementById('sub-' + reqId);
        if (subEl) subEl.textContent = fc(subtotal);
        const pb = document.getElementById('prog-' + reqId);
        if (pb) pb.style.width = (totalItems > 0 ? (pricedItems / totalItems) * 100 : 0) + '%';
        const pt = document.getElementById('prog-text-' + reqId);
        if (pt) pt.textContent = `${pricedItems} / ${totalItems} priced`;
        grandTotal += subtotal;
    });

    const gtEl = document.getElementById('grandTotal');
    if (gtEl) gtEl.textContent = fc(grandTotal);
}

document.querySelectorAll('.price-inp, .pcs-inp').forEach(i => i.addEventListener('input', recalcAll));
recalcAll();

document.getElementById('priceForm')?.addEventListener('submit', function(e) {
    const allFilled = [...document.querySelectorAll('.price-inp')].every(i => parseFloat(i.value) > 0);
    if (!allFilled && !confirm('Some items still have no price set (₱0.00). Submit anyway?')) e.preventDefault();
});

/* ══════════════════════════════════
   LIGHTBOX
══════════════════════════════════*/
function openLightbox(src) {
    document.getElementById('lbImg').src = src;
    document.getElementById('lightbox').classList.add('open');
}
function closeLightbox() { document.getElementById('lightbox').classList.remove('open'); }
document.addEventListener('keydown', e => { if (e.key === 'Escape') closeLightbox(); });
</script>
</body>
</html>