<?php
include '../../SQL/config.php';

/* =====================================================
   SAFE DEFAULTS
=====================================================*/
$errorMessage   = '';
$successMessage = '';
$filterView     = strtolower(trim($_GET['view'] ?? 'pending_prices'));  // pending_prices | priced | all

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

            // Verify it's still 'purchased'
            $sStmt = $pdo->prepare("SELECT status FROM department_request WHERE id=? LIMIT 1");
            $sStmt->execute([$rid]);
            $st = strtolower(trim($sStmt->fetchColumn() ?? ''));
            if ($st !== 'purchased') continue;

            // Fetch approved items for this request
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

    header("Location: supplier_portal.php");
    exit;
}

/* =====================================================
   LOAD PURCHASED REQUESTS WITH ITEMS
=====================================================*/
$purchasedRequests = [];
try {
    $sql = "SELECT * FROM department_request WHERE LOWER(status) = 'purchased' ORDER BY purchased_at DESC";
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
   LOAD RECEIVING REQUESTS (already priced / sent back)
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
   SUMMARY COUNTS
=====================================================*/
$pendingPricesCount = count($purchasedRequests);
$pricedCount        = count($receivingRequests);

/* Alert messages */
$successMessages = ['prices_set' => 'Prices submitted successfully. Requests have been marked for receiving.'];
$successMessage  = $successMessages[$_GET['success'] ?? ''] ?? '';
$errorMessage    = '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Supplier Portal — Purchase Order Management</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;0,9..40,800;1,9..40,400&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
<style>
/* ═══════════════════════════════
   SUPPLIER PORTAL — DESIGN SYSTEM
   Aesthetic: Industrial / Professional
   Palette: Dark navy header, warm white body, teal accents
═══════════════════════════════*/
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
    --green:      #16a34a;
    --green-light:#dcfce7;
    --blue:       #2563eb;
    --blue-light: #dbeafe;
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
    --radius-xs:  4px;
    --radius-sm:  6px;
    --radius:     10px;
    --radius-lg:  16px;
    --shadow-sm:  0 1px 3px rgba(0,0,0,.08);
    --shadow:     0 4px 16px rgba(0,0,0,.1);
    --shadow-lg:  0 12px 40px rgba(0,0,0,.15);
}

*,*::before,*::after { box-sizing: border-box; margin: 0; padding: 0; }

body {
    font-family: 'DM Sans', sans-serif;
    background: var(--slate-50);
    color: var(--slate-800);
    font-size: 14px;
    line-height: 1.6;
    min-height: 100vh;
}

/* ── TOP HEADER BAR ── */
.portal-topbar {
    background: var(--navy);
    border-bottom: 2px solid var(--teal);
    padding: 0 32px;
    height: 64px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    position: sticky;
    top: 0;
    z-index: 100;
    box-shadow: 0 4px 20px rgba(0,0,0,.3);
}
.portal-logo {
    display: flex;
    align-items: center;
    gap: 12px;
    text-decoration: none;
}
.portal-logo-icon {
    width: 38px; height: 38px;
    background: var(--teal);
    border-radius: 8px;
    display: flex; align-items: center; justify-content: center;
    font-size: 1.1rem;
    color: #fff;
    flex-shrink: 0;
}
.portal-logo-text {
    color: #fff;
    font-size: .95rem;
    font-weight: 700;
    letter-spacing: -.3px;
    line-height: 1.2;
}
.portal-logo-text small {
    display: block;
    font-size: .68rem;
    font-weight: 400;
    color: var(--slate-400);
    letter-spacing: .3px;
    text-transform: uppercase;
}
.topbar-right {
    display: flex;
    align-items: center;
    gap: 12px;
}
.topbar-date {
    color: var(--slate-400);
    font-size: .78rem;
    display: flex;
    align-items: center;
    gap: 5px;
}
.topbar-badge {
    background: var(--teal);
    color: #fff;
    border-radius: 6px;
    padding: 4px 12px;
    font-size: .72rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .4px;
}
.back-btn {
    background: rgba(255,255,255,.1);
    color: rgba(255,255,255,.8);
    border: 1px solid rgba(255,255,255,.15);
    border-radius: var(--radius-sm);
    padding: 6px 14px;
    font-size: .78rem;
    font-weight: 600;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 5px;
    transition: all .2s;
    font-family: 'DM Sans', sans-serif;
}
.back-btn:hover {
    background: rgba(255,255,255,.18);
    color: #fff;
}

/* ── PAGE LAYOUT ── */
.portal-body {
    max-width: 1280px;
    margin: 0 auto;
    padding: 32px 24px;
}

/* ── PAGE TITLE ── */
.portal-page-title {
    margin-bottom: 28px;
}
.portal-page-title h1 {
    font-size: 1.65rem;
    font-weight: 800;
    color: var(--slate-900);
    letter-spacing: -.4px;
    margin-bottom: 4px;
}
.portal-page-title p {
    color: var(--slate-500);
    font-size: .875rem;
}

/* ── ALERTS ── */
.alert-bar {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 14px 18px;
    border-radius: var(--radius);
    margin-bottom: 24px;
    font-size: .875rem;
    font-weight: 500;
    animation: fadeSlide .3s ease;
}
@keyframes fadeSlide {
    from { opacity:0; transform:translateY(-6px); }
    to   { opacity:1; transform:translateY(0); }
}
.alert-success { background: var(--green-light); color: #14532d; border: 1px solid #86efac; }
.alert-error   { background: var(--red-light);   color: #7f1d1d; border: 1px solid #fca5a5; }

/* ── STAT STRIP ── */
.stat-strip {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 16px;
    margin-bottom: 28px;
}
.stat-tile {
    background: #fff;
    border-radius: var(--radius);
    padding: 20px 22px;
    border: 1px solid var(--slate-200);
    box-shadow: var(--shadow-sm);
    cursor: pointer;
    text-decoration: none;
    display: block;
    transition: all .2s;
    position: relative;
    overflow: hidden;
}
.stat-tile::after {
    content: '';
    position: absolute;
    bottom: 0; left: 0; right: 0;
    height: 3px;
    border-radius: 0 0 var(--radius) var(--radius);
}
.stat-tile.st-pending::after  { background: var(--amber); }
.stat-tile.st-priced::after   { background: var(--teal); }
.stat-tile:hover { box-shadow: var(--shadow); transform: translateY(-2px); }
.stat-tile.active { border-color: var(--teal); box-shadow: 0 0 0 2px rgba(13,148,136,.2); }
.st-label { font-size: .7rem; font-weight: 700; text-transform: uppercase; letter-spacing: .6px; color: var(--slate-500); margin-bottom: 8px; }
.st-num   { font-family: 'DM Mono', monospace; font-size: 2rem; font-weight: 500; color: var(--slate-800); line-height: 1; }
.st-sub   { font-size: .75rem; color: var(--slate-400); margin-top: 4px; }

/* ── VIEW TABS ── */
.view-tabs {
    display: flex;
    gap: 0;
    background: #fff;
    border: 1px solid var(--slate-200);
    border-radius: var(--radius);
    overflow: hidden;
    margin-bottom: 24px;
    box-shadow: var(--shadow-sm);
}
.view-tab {
    flex: 1;
    padding: 12px 16px;
    text-align: center;
    font-size: .83rem;
    font-weight: 600;
    text-decoration: none;
    color: var(--slate-500);
    border-right: 1px solid var(--slate-200);
    transition: all .2s;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 7px;
}
.view-tab:last-child { border-right: none; }
.view-tab:hover { background: var(--slate-50); color: var(--slate-700); }
.view-tab.active { background: var(--navy); color: #fff; }
.view-tab .tab-count {
    background: rgba(255,255,255,.2);
    border-radius: 100px;
    padding: 1px 7px;
    font-size: .7rem;
    font-family: 'DM Mono', monospace;
}
.view-tab:not(.active) .tab-count { background: var(--slate-100); color: var(--slate-600); }

/* ── EMPTY STATE ── */
.empty-portal {
    text-align: center;
    padding: 72px 24px;
    color: var(--slate-400);
}
.empty-portal-icon {
    width: 72px; height: 72px;
    background: var(--slate-100);
    border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    margin: 0 auto 16px;
    font-size: 2rem;
    color: var(--slate-400);
}
.empty-portal h3 { font-size: 1.1rem; font-weight: 700; color: var(--slate-600); margin-bottom: 6px; }
.empty-portal p  { font-size: .875rem; max-width: 320px; margin: 0 auto; }

/* ── REQUEST CARD ── */
.req-card {
    background: #fff;
    border: 1px solid var(--slate-200);
    border-radius: var(--radius-lg);
    margin-bottom: 20px;
    box-shadow: var(--shadow-sm);
    overflow: hidden;
    transition: box-shadow .2s;
}
.req-card:hover { box-shadow: var(--shadow); }
.req-card-header {
    background: var(--navy);
    padding: 14px 20px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 10px;
}
.req-card-id {
    display: flex;
    align-items: center;
    gap: 10px;
}
.req-num {
    background: rgba(255,255,255,.15);
    color: #fff;
    border-radius: var(--radius-xs);
    padding: 3px 10px;
    font-family: 'DM Mono', monospace;
    font-size: .8rem;
    font-weight: 500;
}
.req-dept-tag {
    color: #fff;
    font-size: .875rem;
    font-weight: 700;
    display: flex;
    align-items: center;
    gap: 5px;
}
.req-card-meta {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
}
.meta-chip {
    background: rgba(255,255,255,.12);
    color: rgba(255,255,255,.85);
    border-radius: var(--radius-xs);
    padding: 3px 9px;
    font-size: .7rem;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 4px;
}
.req-card-body { padding: 20px; }

/* ── Pricing options row ── */
.pricing-options {
    display: flex;
    align-items: flex-end;
    gap: 14px;
    flex-wrap: wrap;
    padding: 14px 16px;
    background: var(--slate-50);
    border-radius: var(--radius-sm);
    border: 1px solid var(--slate-200);
    margin-bottom: 16px;
}
.pricing-opt-group {
    display: flex;
    flex-direction: column;
    gap: 4px;
}
.pricing-opt-group label {
    font-size: .68rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .5px;
    color: var(--slate-500);
}
.portal-select {
    padding: 8px 12px;
    border: 1px solid var(--slate-200);
    border-radius: var(--radius-sm);
    font-family: 'DM Sans', sans-serif;
    font-size: .83rem;
    font-weight: 500;
    color: var(--slate-700);
    background: #fff;
    cursor: pointer;
    transition: all .2s;
    min-width: 150px;
}
.portal-select:focus { outline: none; border-color: var(--teal); box-shadow: 0 0 0 2px rgba(13,148,136,.15); }
.portal-date {
    padding: 8px 12px;
    border: 1px solid var(--slate-200);
    border-radius: var(--radius-sm);
    font-family: 'DM Mono', monospace;
    font-size: .83rem;
    color: var(--slate-700);
    background: #fff;
    cursor: pointer;
    transition: all .2s;
}
.portal-date:focus { outline: none; border-color: var(--teal); box-shadow: 0 0 0 2px rgba(13,148,136,.15); }

/* ── Items table ── */
.portal-table-wrap { border: 1px solid var(--slate-200); border-radius: var(--radius-sm); overflow: hidden; }
.portal-table { width: 100%; border-collapse: collapse; }
.portal-table thead th {
    background: var(--slate-100);
    color: var(--slate-500);
    font-size: .68rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .5px;
    padding: 10px 14px;
    border-bottom: 1px solid var(--slate-200);
    text-align: left;
    white-space: nowrap;
}
.portal-table tbody td {
    padding: 12px 14px;
    border-bottom: 1px solid var(--slate-100);
    font-size: .83rem;
    color: var(--slate-700);
    vertical-align: middle;
}
.portal-table tbody tr:last-child td { border-bottom: none; }
.portal-table tbody tr:hover { background: var(--slate-50); }
.item-name-cell { font-weight: 600; color: var(--slate-800); }
.qty-badge {
    display: inline-flex;
    align-items: center;
    background: var(--teal-light);
    color: var(--teal-dark);
    border-radius: var(--radius-xs);
    padding: 2px 8px;
    font-family: 'DM Mono', monospace;
    font-size: .78rem;
    font-weight: 500;
}
.price-input-wrap { display: flex; align-items: center; gap: 6px; }
.currency-prefix {
    font-size: .8rem;
    font-weight: 700;
    color: var(--slate-500);
}
.price-inp {
    width: 110px;
    padding: 7px 10px;
    border: 1px solid var(--slate-200);
    border-radius: var(--radius-sm);
    font-family: 'DM Mono', monospace;
    font-size: .83rem;
    text-align: right;
    background: #fff;
    color: var(--slate-800);
    transition: all .2s;
}
.price-inp:focus {
    outline: none;
    border-color: var(--teal);
    box-shadow: 0 0 0 2px rgba(13,148,136,.15);
    background: #fff;
}
.price-inp.has-value {
    border-color: var(--teal);
    background: #f0fdfa;
}
.unit-inp {
    width: 70px;
    padding: 7px 8px;
    border: 1px solid var(--slate-200);
    border-radius: var(--radius-sm);
    font-family: 'DM Sans', sans-serif;
    font-size: .8rem;
    background: #fff;
    color: var(--slate-700);
    transition: all .2s;
    text-align: center;
}
.unit-inp:focus { outline: none; border-color: var(--teal); box-shadow: 0 0 0 2px rgba(13,148,136,.15); }
.pcs-inp {
    width: 60px;
    padding: 7px 8px;
    border: 1px solid var(--slate-200);
    border-radius: var(--radius-sm);
    font-family: 'DM Mono', monospace;
    font-size: .8rem;
    text-align: center;
    background: #fff;
    color: var(--slate-700);
    transition: all .2s;
}
.pcs-inp:focus { outline: none; border-color: var(--teal); box-shadow: 0 0 0 2px rgba(13,148,136,.15); }
.line-total-cell {
    font-family: 'DM Mono', monospace;
    font-size: .83rem;
    font-weight: 500;
    color: var(--slate-600);
    text-align: right;
    min-width: 100px;
}
.line-total-cell.filled { color: var(--teal-dark); font-weight: 700; }

/* ── Card footer bar ── */
.req-card-footer {
    padding: 12px 20px;
    background: var(--slate-50);
    border-top: 1px solid var(--slate-200);
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 10px;
}
.subtotal-bar {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: .83rem;
    color: var(--slate-600);
}
.subtotal-bar .stval {
    font-family: 'DM Mono', monospace;
    font-size: .95rem;
    font-weight: 700;
    color: var(--teal-dark);
}
.prices-progress {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: .75rem;
    color: var(--slate-500);
}
.prices-progress .prog-bar {
    width: 80px;
    height: 5px;
    background: var(--slate-200);
    border-radius: 100px;
    overflow: hidden;
}
.prices-progress .prog-fill {
    height: 100%;
    background: var(--teal);
    border-radius: 100px;
    transition: width .3s;
}

/* ── Submit section ── */
.submit-section {
    background: var(--navy);
    border-radius: var(--radius-lg);
    padding: 24px 28px;
    margin-top: 12px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 16px;
    position: sticky;
    bottom: 20px;
    box-shadow: 0 8px 32px rgba(13,27,42,.4);
}
.submit-section-info {
    color: rgba(255,255,255,.85);
}
.submit-section-info strong {
    display: block;
    font-size: 1rem;
    font-weight: 700;
    color: #fff;
    margin-bottom: 2px;
}
.submit-section-info small {
    font-size: .78rem;
    opacity: .65;
}
.grand-total-display {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    color: rgba(255,255,255,.7);
}
.grand-total-display .gt-label { font-size: .7rem; text-transform: uppercase; letter-spacing: .5px; margin-bottom: 2px; }
.grand-total-display .gt-amount {
    font-family: 'DM Mono', monospace;
    font-size: 1.5rem;
    font-weight: 500;
    color: #fff;
}
.btn-submit-prices {
    background: var(--teal);
    color: #fff;
    border: none;
    border-radius: var(--radius-sm);
    padding: 12px 28px;
    font-size: .95rem;
    font-weight: 700;
    font-family: 'DM Sans', sans-serif;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    transition: all .2s;
    letter-spacing: -.2px;
}
.btn-submit-prices:hover {
    background: var(--teal-dark);
    transform: translateY(-1px);
    box-shadow: 0 6px 20px rgba(13,148,136,.4);
}
.btn-submit-prices:disabled {
    background: var(--slate-600);
    cursor: not-allowed;
    transform: none;
    box-shadow: none;
}

/* ── Priced / History view ── */
.history-card {
    background: #fff;
    border: 1px solid var(--slate-200);
    border-radius: var(--radius-lg);
    overflow: hidden;
    margin-bottom: 16px;
    box-shadow: var(--shadow-sm);
}
.history-card-header {
    padding: 14px 20px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 8px;
    border-bottom: 1px solid var(--slate-100);
}
.hist-status {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    border-radius: 100px;
    padding: 4px 10px;
    font-size: .72rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .4px;
}
.hs-receiving  { background: #e0f2fe; color: #0c4a6e; }
.hs-completed  { background: #dcfce7; color: #14532d; }
.history-table { width: 100%; border-collapse: collapse; }
.history-table th { background: var(--slate-50); color: var(--slate-500); font-size: .68rem; font-weight: 700; text-transform: uppercase; letter-spacing: .5px; padding: 9px 14px; border-bottom: 1px solid var(--slate-100); text-align: left; }
.history-table td { padding: 10px 14px; border-bottom: 1px solid var(--slate-50); font-size: .82rem; color: var(--slate-700); }
.history-table tr:last-child td { border-bottom: none; }
.history-total-row td { background: var(--slate-50); font-weight: 700; border-top: 2px solid var(--slate-200); }

/* ── Responsive ── */
@media(max-width: 768px) {
    .portal-topbar { padding: 0 16px; }
    .portal-body   { padding: 20px 16px; }
    .portal-page-title h1 { font-size: 1.3rem; }
    .stat-strip { grid-template-columns: repeat(2, 1fr); gap: 12px; }
    .view-tabs { flex-direction: column; }
    .view-tab  { border-right: none; border-bottom: 1px solid var(--slate-200); }
    .view-tab:last-child { border-bottom: none; }
    .pricing-options { flex-direction: column; align-items: stretch; }
    .portal-select, .portal-date { width: 100%; }
    .submit-section { flex-direction: column; }
    .btn-submit-prices { width: 100%; justify-content: center; }
    .topbar-date { display: none; }
}
</style>
</head>
<body>

<!-- ════════ TOP BAR ════════ -->
<header class="portal-topbar">
    <a href="#" class="portal-logo">
        <div class="portal-logo-icon"><i class="bi bi-shop"></i></div>
        <div class="portal-logo-text">
            Supplier Portal
            <small>Purchase Order Management</small>
        </div>
    </a>
    <div class="topbar-right">
        <span class="topbar-date">
            <i class="bi bi-calendar3"></i>
            <?= date('F j, Y') ?>
        </span>
        <span class="topbar-badge">Supplier View</span>
        <a href="department_request.php?status=purchased" class="back-btn">
            <i class="bi bi-arrow-left"></i> Back to Hospital
        </a>
    </div>
</header>

<!-- ════════ PAGE BODY ════════ -->
<div class="portal-body">

    <div class="portal-page-title">
        <h1>Purchase Order Pricing</h1>
        <p>Review purchased items from the hospital, assign unit prices, and confirm delivery details before sending back.</p>
    </div>

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

    <!-- Stat strip -->
    <div class="stat-strip">
        <div class="stat-tile st-pending <?= $filterView === 'pending_prices' ? 'active' : '' ?>"
             onclick="switchView('pending_prices')" style="cursor:pointer;">
            <div class="st-label"><i class="bi bi-hourglass-split" style="margin-right:4px;"></i>Awaiting Pricing</div>
            <div class="st-num"><?= $pendingPricesCount ?></div>
            <div class="st-sub">requests need prices</div>
        </div>
        <div class="stat-tile st-priced <?= $filterView === 'priced' ? 'active' : '' ?>"
             onclick="switchView('priced')" style="cursor:pointer;">
            <div class="st-label"><i class="bi bi-check-circle" style="margin-right:4px;"></i>Priced & Sent</div>
            <div class="st-num"><?= $pricedCount ?></div>
            <div class="st-sub">orders submitted</div>
        </div>
    </div>

    <!-- View tabs -->
    <div class="view-tabs">
        <a href="#" class="view-tab <?= $filterView === 'pending_prices' ? 'active' : '' ?>"
           onclick="switchView('pending_prices'); return false;">
            <i class="bi bi-clock-history"></i>
            Awaiting Pricing
            <span class="tab-count"><?= $pendingPricesCount ?></span>
        </a>
        <a href="#" class="view-tab <?= $filterView === 'priced' ? 'active' : '' ?>"
           onclick="switchView('priced'); return false;">
            <i class="bi bi-bag-check-fill"></i>
            Priced &amp; Sent Back
            <span class="tab-count"><?= $pricedCount ?></span>
        </a>
    </div>

    <!-- ════════ PENDING PRICES VIEW ════════ -->
    <div id="view-pending_prices" class="<?= $filterView !== 'pending_prices' ? 'd-none' : '' ?>">

    <?php if (empty($purchasedRequests)): ?>
        <div class="empty-portal">
            <div class="empty-portal-icon"><i class="bi bi-inbox"></i></div>
            <h3>No Pending Orders</h3>
            <p>All purchase orders have been priced and sent back to the hospital. Check the <strong>Priced &amp; Sent Back</strong> tab for history.</p>
        </div>
    <?php else: ?>

    <form method="post" id="priceForm">
        <input type="hidden" name="action" value="set_prices">

        <?php foreach ($purchasedRequests as $req):
            $items       = $req['items'];
            $purchDate   = $req['purchased_at'] ?? $req['created_at'];
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
                    <span class="meta-chip">
                        <i class="bi bi-boxes"></i>
                        <?= count($items) ?> item<?= count($items) !== 1 ? 's' : '' ?>
                    </span>
                    <?php if ($purchDate): ?>
                    <span class="meta-chip">
                        <i class="bi bi-calendar-check"></i>
                        Ordered: <?= date('M j, Y', strtotime($purchDate)) ?>
                    </span>
                    <?php endif; ?>
                    <span class="meta-chip" style="background:rgba(13,148,136,.3);color:#ccfbf1;">
                        <i class="bi bi-clock"></i> Needs Pricing
                    </span>
                </div>
            </div>

            <div class="req-card-body">

                <!-- Per-request options -->
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

                <!-- Items table -->
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
                        <td style="text-align:center;">
                            <span class="qty-badge"><?= $approved_qty ?></span>
                        </td>
                        <td>
                            <input type="text" class="unit-inp"
                                   name="unit[<?= $item['id'] ?>]"
                                   value="<?= htmlspecialchars($item['unit'] ?? 'pcs') ?>"
                                   placeholder="pcs">
                        </td>
                        <td style="text-align:center;">
                            <input type="number" class="pcs-inp"
                                   name="pcs_per_box[<?= $item['id'] ?>]"
                                   value="<?= $item['pcs_per_box'] ?? 1 ?>"
                                   min="1">
                        </td>
                        <td>
                            <div class="price-input-wrap" style="justify-content:flex-end;">
                                <span class="currency-prefix">₱</span>
                                <input type="number"
                                       step="0.01"
                                       min="0"
                                       class="price-inp"
                                       name="price[<?= $item['id'] ?>]"
                                       placeholder="0.00"
                                       data-item-id="<?= $item['id'] ?>"
                                       data-req="<?= $req['id'] ?>">
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
                    <span>Subtotal for this request:</span>
                    <span class="stval" id="sub-<?= $req['id'] ?>">₱0.00</span>
                </div>
            </div>
        </div>

        <?php endforeach; ?>

        <!-- Sticky Submit -->
        <div class="submit-section">
            <div class="submit-section-info">
                <strong><i class="bi bi-send-fill" style="margin-right:6px;"></i>Submit Prices to Hospital</strong>
                <small>All <?= $pendingPricesCount ?> request<?= $pendingPricesCount !== 1 ? 's' : '' ?> will be marked as <em>Receiving</em> and returned to the hospital system.</small>
            </div>
            <div class="grand-total-display">
                <span class="gt-label">Grand Total</span>
                <span class="gt-amount" id="grandTotal">₱0.00</span>
            </div>
            <button type="submit" class="btn-submit-prices" id="submitBtn">
                <i class="bi bi-check2-circle"></i>
                Confirm &amp; Send Back to Hospital
            </button>
        </div>

    </form>

    <?php endif; ?>
    </div><!-- /view-pending_prices -->

    <!-- ════════ PRICED / HISTORY VIEW ════════ -->
    <div id="view-priced" class="<?= $filterView !== 'priced' ? 'd-none' : '' ?>">

    <?php if (empty($receivingRequests)): ?>
        <div class="empty-portal">
            <div class="empty-portal-icon"><i class="bi bi-archive"></i></div>
            <h3>No History Yet</h3>
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
                    <span style="font-weight:700;color:var(--slate-800);display:flex;align-items:center;gap:5px;">
                        <i class="bi bi-building" style="color:var(--slate-400);"></i>
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
                    <?php if (!empty($rr['purchased_at'])): ?>
                    <span style="color:var(--slate-400);font-size:.72rem;">
                        Ordered <?= date('M j, Y', strtotime($rr['purchased_at'])) ?>
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
                    $lineTotal = (float)($hi['total_price'] ?? 0);
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
    </div><!-- /view-priced -->

</div><!-- /portal-body -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
/* ── View switcher ── */
function switchView(v) {
    document.getElementById('view-pending_prices').classList.add('d-none');
    document.getElementById('view-priced').classList.add('d-none');
    document.getElementById('view-' + v).classList.remove('d-none');
    document.querySelectorAll('.view-tab').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('.stat-tile').forEach(t => t.classList.remove('active'));
}

/* ── Live price calculation ── */
function fc(n) {
    return '₱' + parseFloat(n || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function recalcAll() {
    let grandTotal = 0;

    // Group rows by request id
    const reqIds = new Set();
    document.querySelectorAll('.portal-table tbody tr[data-req]').forEach(row => {
        reqIds.add(row.dataset.req);
    });

    reqIds.forEach(reqId => {
        let subtotal   = 0;
        let totalItems = 0;
        let pricedItems = 0;

        const rows = document.querySelectorAll(`.portal-table tbody tr[data-req="${reqId}"]`);
        rows.forEach(row => {
            totalItems++;
            const itemId  = row.dataset.itemId;
            const qty     = parseFloat(row.dataset.qty || 0);
            const priceEl = row.querySelector('.price-inp');
            if (!priceEl) return;
            const price   = parseFloat(priceEl.value || 0);
            const line    = price * qty;

            if (price > 0) {
                pricedItems++;
                priceEl.classList.add('has-value');
            } else {
                priceEl.classList.remove('has-value');
            }

            const ltEl = document.getElementById('lt-' + row.dataset.itemId);
            if (ltEl) {
                ltEl.textContent = price > 0 ? fc(line) : '—';
                ltEl.classList.toggle('filled', price > 0);
            }
            subtotal += line;
        });

        // Update subtotal
        const subEl = document.getElementById('sub-' + reqId);
        if (subEl) subEl.textContent = fc(subtotal);

        // Update progress bar
        const prog = totalItems > 0 ? (pricedItems / totalItems) * 100 : 0;
        const pb = document.getElementById('prog-' + reqId);
        if (pb) pb.style.width = prog + '%';
        const pt = document.getElementById('prog-text-' + reqId);
        if (pt) pt.textContent = `${pricedItems} / ${totalItems} priced`;

        grandTotal += subtotal;
    });

    const gtEl = document.getElementById('grandTotal');
    if (gtEl) gtEl.textContent = fc(grandTotal);
}

// Attach listeners to all price inputs
document.querySelectorAll('.price-inp, .pcs-inp').forEach(inp => {
    inp.addEventListener('input', recalcAll);
});
recalcAll();

// ── Form validation before submit ── 
document.getElementById('priceForm')?.addEventListener('submit', function(e) {
    const priceInps = document.querySelectorAll('.price-inp');
    let allFilled = true;
    priceInps.forEach(inp => {
        if (!inp.value || parseFloat(inp.value) <= 0) allFilled = false;
    });
    if (!allFilled) {
        if (!confirm('Some items still have no price set (₱0.00). Submit anyway?')) {
            e.preventDefault();
        }
    }
});
</script>
</body>
</html>