<?php 
session_start();
include '../../SQL/config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Handle setting expiry for new delivered items
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['set_expiry_submit'])) {
    $request_item_id = intval($_POST['request_item_id']);
    $has_expiry      = isset($_POST['has_expiry']) ? 1 : 0;
    $expiry_date     = ($has_expiry && !empty($_POST['expiry_date'])) ? $_POST['expiry_date'] : null;

    $stmt = $pdo->prepare("
        SELECT di.*, dr.id AS req_id
        FROM department_request_items di
        JOIN department_request dr ON di.request_id = dr.id
        WHERE di.id = ? AND dr.status = 'Completed'
    ");
    $stmt->execute([$request_item_id]);
    $item = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($item) {
        $stmt2 = $pdo->prepare("SELECT id FROM vendor_products WHERE item_name LIKE ? LIMIT 1");
        $stmt2->execute(['%' . $item['item_name'] . '%']);
        $vp      = $stmt2->fetch(PDO::FETCH_ASSOC);
        $item_id = $vp ? $vp['id'] : 0;

        $batch_no  = 'DRI-' . $item['id'];
        $total_pcs = intval($item['received_quantity']) * intval($item['pcs_per_box']);
        if ($total_pcs <= 0) $total_pcs = intval($item['received_quantity']);

        $stmt3 = $pdo->prepare("INSERT INTO medicine_batches (item_id, batch_no, quantity, expiration_date, has_expiry) VALUES (?, ?, ?, ?, ?)");
        $stmt3->execute([$item_id, $batch_no, $total_pcs, $expiry_date, $has_expiry]);
    }

    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// Handle expiry update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['batch_id'], $_POST['expiry_date']) && !isset($_POST['set_expiry_submit'])) {
    $batch_id    = intval($_POST['batch_id']);
    $expiry_date = $_POST['expiry_date'];
    $pdo->prepare("UPDATE medicine_batches SET expiration_date = ? WHERE id = ?")->execute([$expiry_date, $batch_id]);
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// Handle category/item_type update on inventory item
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_category_submit'])) {
    $inv_item_name = trim($_POST['inv_item_name']);
    $new_category  = trim($_POST['new_category']);
    $new_item_type = trim($_POST['new_item_type']);

    if ($inv_item_name && $new_category) {
        // Update ALL inventory rows with this item_name
        $pdo->prepare("UPDATE inventory SET category = ?, item_type = ? WHERE item_name = ?")
            ->execute([$new_category, $new_item_type, $inv_item_name]);
    }
    header("Location: " . $_SERVER['PHP_SELF'] . "?tab=delivered");
    exit;
}

// Handle disposal
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['dispose_id'], $_POST['dispose_qty'])) {
    $dispose_id  = intval($_POST['dispose_id']);
    $dispose_qty = intval($_POST['dispose_qty']);

    $stmt = $pdo->prepare("
        SELECT mb.*, vp.item_name, vp.price
        FROM medicine_batches mb
        LEFT JOIN vendor_products vp ON mb.item_id = vp.id
        WHERE mb.id = ?
    ");
    $stmt->execute([$dispose_id]);
    $batch = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($batch && $dispose_qty > 0 && $dispose_qty <= $batch['quantity']) {
        $price     = $batch['price']     ?? 0;
        $item_name = $batch['item_name'] ?? 'Unknown';

        $pdo->prepare("INSERT INTO disposed_medicines (batch_id, batch_no, item_id, item_name, quantity, price, expiration_date) VALUES (?, ?, ?, ?, ?, ?, ?)")
            ->execute([$batch['id'], $batch['batch_no'], $batch['item_id'], $item_name, $dispose_qty, $price, $batch['expiration_date']]);

        $new_qty = $batch['quantity'] - $dispose_qty;
        if ($new_qty > 0) {
            $pdo->prepare("UPDATE medicine_batches SET quantity = ? WHERE id = ?")->execute([$new_qty, $batch['id']]);
        } else {
            $pdo->prepare("DELETE FROM medicine_batches WHERE id = ?")->execute([$batch['id']]);
        }
    }

    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// Fetch new delivered items — join inventory for category & item_type
$stmt = $pdo->prepare("
    SELECT di.*, dr.delivered_at, dr.id AS request_id,
           COALESCE(inv.item_type, '') AS item_type,
           COALESCE(inv.category,  '') AS category
    FROM department_request_items di
    JOIN department_request dr ON di.request_id = dr.id
    LEFT JOIN inventory inv ON inv.item_name = di.item_name
    WHERE dr.status = 'Completed'
      AND NOT EXISTS (
          SELECT 1 FROM medicine_batches mb WHERE mb.batch_no = CONCAT('DRI-', di.id)
      )
    GROUP BY di.id
    ORDER BY di.id ASC
");
$stmt->execute();
$new_delivered = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch inventory — join inventory table to get category & item_type
$stmt = $pdo->prepare("
    SELECT 
        mb.id AS batch_id, mb.item_id,
        COALESCE(vp.item_name, mb.batch_no) AS item_name,
        vp.price, vp.unit_type, vp.pcs_per_box,
        mb.quantity, mb.batch_no, mb.expiration_date, mb.has_expiry,
        COALESCE(inv.item_type, '')  AS item_type,
        COALESCE(inv.category,  '')  AS category
    FROM medicine_batches mb
    LEFT JOIN vendor_products vp ON mb.item_id = vp.id
    LEFT JOIN inventory inv      ON inv.item_name = COALESCE(vp.item_name, mb.batch_no)
    WHERE mb.expiration_date IS NOT NULL OR mb.has_expiry = 0
    GROUP BY mb.id
    ORDER BY COALESCE(vp.item_name, mb.batch_no), mb.expiration_date
");
$stmt->execute();
$inventory_rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Build unique category list for category filter
$all_categories = array_values(array_unique(array_filter(
    array_map(fn($r) => $r['category'] ?? '', $inventory_rows)
)));
sort($all_categories);

// Categorize
$expired = $near_expiry = $safe = $no_expiry = $seven_days_alert = [];

foreach ($inventory_rows as $row) {
    if (!$row['has_expiry']) {
        $row['status'] = 'No Expiry';
        $no_expiry[]   = $row;
        continue;
    }
    $today    = new DateTime();
    $expiry   = new DateTime($row['expiration_date']);
    $diffDays = (int)$today->diff($expiry)->format("%r%a");

    if ($expiry < $today) {
        $row['status'] = 'Expired';
        $expired[]     = $row;
        $seven_days_alert[] = $row;
    } elseif ($diffDays <= 7) {
        $row['status']    = 'Near Expiry';
        $row['days_left'] = $diffDays;
        $near_expiry[]    = $row;
        $seven_days_alert[] = $row;
    } elseif ($diffDays <= 30) {
        $row['status']    = 'Near Expiry';
        $row['days_left'] = $diffDays;
        $near_expiry[]    = $row;
    } else {
        $row['status'] = 'Safe';
        $safe[]        = $row;
    }
}
$all_stocks = array_merge($expired, $near_expiry, $safe, $no_expiry);

// Fetch disposed
$stmt = $pdo->prepare("SELECT * FROM disposed_medicines ORDER BY disposed_at DESC");
$stmt->execute();
$disposed = $stmt->fetchAll(PDO::FETCH_ASSOC);

$total_disposed_value = array_sum(array_map(fn($d) => $d['quantity'] * $d['price'], $disposed));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Batch & Expiry Tracking</title>
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --primary:       #00acc1;
            --primary-light: rgba(0,172,193,.1);
            --danger:        #e05555;
            --danger-light:  rgba(224,85,85,.09);
            --success:       #27ae60;
            --success-light: rgba(39,174,96,.1);
            --warning:       #f39c12;
            --warning-light: rgba(243,156,18,.12);
            --info:          #2980b9;
            --info-light:    rgba(41,128,185,.1);
            --purple:        #7c4dff;
            --purple-light:  rgba(124,77,255,.1);
            --sidebar-w:     250px;
            --text:          #6e768e;
            --text-dark:     #3a4060;
            --bg:            #F5F6F7;
            --card:          #ffffff;
            --border:        #e8eaed;
            --radius:        12px;
            --shadow:        0 2px 12px rgba(0,0,0,.07);
            --shadow-lg:     0 8px 32px rgba(0,0,0,.11);
        }

        body { font-family:"Nunito","Segoe UI",Arial,sans-serif; background:var(--bg); color:var(--text); min-height:100vh; }

        .sidebar-area { position:fixed; left:0; top:0; width:var(--sidebar-w); height:100vh; z-index:100; }

        .main-content { margin-left:var(--sidebar-w); min-height:100vh; padding:32px 32px 56px; }

        /* PAGE HEADER */
        .page-header { margin-bottom:28px; }
        .breadcrumb-row { display:flex; align-items:center; gap:6px; font-size:.8rem; margin-bottom:6px; }
        .breadcrumb-row span { color:var(--primary); font-weight:700; }
        .page-header h1 { font-size:1.65rem; font-weight:800; color:var(--text-dark); }
        .page-header p  { font-size:.9rem; color:var(--text); margin-top:4px; }

        /* EXPIRY ALERT BANNER */
        .expiry-alert-banner {
            background: linear-gradient(135deg, #fff8e6 0%, #fff3cd 100%);
            border: 1.5px solid #f39c12;
            border-left: 5px solid #f39c12;
            border-radius: var(--radius);
            padding: 16px 20px;
            margin-bottom: 24px;
            animation: slideIn .3s ease;
        }
        .expiry-alert-banner .alert-title {
            font-size:.92rem; font-weight:800; color:#8a6300; margin-bottom:10px;
            display:flex; align-items:center; gap:8px;
        }
        .expiry-alert-banner ul { margin:0; padding-left:20px; }
        .expiry-alert-banner li { font-size:.87rem; color:#7a5a00; margin-bottom:4px; }
        .expiry-alert-banner li:last-child { margin-bottom:0; }
        .alert-close {
            float:right; background:none; border:none; font-size:1.1rem;
            cursor:pointer; color:#8a6300; padding:0; line-height:1;
        }
        @keyframes slideIn { from{opacity:0;transform:translateY(-8px);} to{opacity:1;transform:translateY(0);} }

        /* STATS */
        .stats-row { display:grid; grid-template-columns:repeat(5,1fr); gap:14px; margin-bottom:28px; }
        .stat-card {
            background:var(--card); border-radius:var(--radius);
            padding:16px 18px; border:1px solid var(--border); box-shadow:var(--shadow);
            display:flex; align-items:center; gap:12px;
            transition:transform .2s,box-shadow .2s;
        }
        .stat-card:hover { transform:translateY(-2px); box-shadow:var(--shadow-lg); }
        .stat-icon { width:44px;height:44px;border-radius:11px;display:flex;align-items:center;justify-content:center;font-size:1.2rem;flex-shrink:0; }
        .stat-icon.teal   { background:var(--primary-light); }
        .stat-icon.red    { background:var(--danger-light); }
        .stat-icon.amber  { background:var(--warning-light); }
        .stat-icon.green  { background:var(--success-light); }
        .stat-icon.blue   { background:var(--info-light); }
        .stat-info .value { font-size:1.35rem;font-weight:800;color:var(--text-dark);line-height:1; }
        .stat-info .label { font-size:.75rem;color:var(--text);margin-top:3px; }
        .stat-info .value.red   { color:var(--danger); }
        .stat-info .value.amber { color:var(--warning); }
        .stat-info .value.green { color:var(--success); }

        /* TABS */
        .tab-bar { display:flex;gap:4px;background:var(--card);border:1px solid var(--border);border-radius:12px 12px 0 0;padding:8px 8px 0;box-shadow:var(--shadow);overflow-x:auto; }
        .tab-btn {
            display:flex;align-items:center;gap:7px;padding:10px 20px;border:none;background:none;
            font-family:"Nunito",sans-serif;font-size:.88rem;font-weight:700;color:var(--text);cursor:pointer;
            border-radius:8px 8px 0 0;border-bottom:3px solid transparent;
            transition:color .2s,background .2s,border-color .2s;white-space:nowrap;
        }
        .tab-btn:hover  { background:var(--primary-light);color:var(--primary); }
        .tab-btn.active { color:var(--primary);border-bottom-color:var(--primary);background:var(--primary-light); }
        .tab-badge { color:#fff;font-size:.7rem;font-weight:700;padding:2px 7px;border-radius:20px; }
        .tab-badge.red   { background:var(--danger); }
        .tab-badge.teal  { background:var(--primary); }
        .tab-badge.muted { background:var(--text); }

        /* TAB PANELS */
        .tab-panel { display:none;background:var(--card);border:1px solid var(--border);border-top:none;border-radius:0 0 12px 12px;padding:28px;box-shadow:var(--shadow);animation:fadeIn .25s ease; }
        .tab-panel.active { display:block; }
        @keyframes fadeIn { from{opacity:0;} to{opacity:1;} }

        /* SECTION HEADER */
        .section-header { display:flex;align-items:center;gap:12px;margin-bottom:22px;padding-bottom:16px;border-bottom:1px solid var(--border); }
        .icon-wrap { width:40px;height:40px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:1.1rem;flex-shrink:0; }
        .icon-wrap.teal  { background:var(--primary-light); }
        .icon-wrap.amber { background:var(--warning-light); }
        .icon-wrap.red   { background:var(--danger-light); }
        .icon-wrap.green { background:var(--success-light); }
        .icon-wrap.blue  { background:var(--info-light); }
        .section-header h3 { font-size:1.05rem;font-weight:800;color:var(--text-dark); }
        .section-header p  { font-size:.82rem;color:var(--text);margin-top:2px; }

        /* FILTER BAR */
        .filter-bar { display:flex;gap:10px;align-items:center;margin-bottom:18px;flex-wrap:wrap; }
        .filter-bar select, .filter-bar input {
            flex:1;min-width:160px;padding:9px 32px 9px 14px;border:1.5px solid var(--border);border-radius:8px;
            font-family:"Nunito",sans-serif;font-size:.88rem;color:var(--text-dark);background:#fff;outline:none;
            appearance:none;transition:border-color .2s;
            background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%236e768e' d='M6 8L1 3h10z'/%3E%3C/svg%3E");
            background-repeat:no-repeat;background-position:right 12px center;
        }
        .filter-bar input { background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' fill='%236e768e' viewBox='0 0 16 16'%3E%3Cpath d='M11.742 10.344a6.5 6.5 0 1 0-1.397 1.398h-.001c.03.04.062.078.098.115l3.85 3.85a1 1 0 0 0 1.415-1.414l-3.85-3.85a1.007 1.007 0 0 0-.115-.099zm-5.242 1.156a5.5 5.5 0 1 1 0-11 5.5 5.5 0 0 1 0 11z'/%3E%3C/svg%3E"); padding-left:36px; }
        .filter-bar select:focus, .filter-bar input:focus { border-color:var(--primary); box-shadow:0 0 0 3px rgba(0,172,193,.1); }

        /* STATUS PILL SUMMARY */
        .status-pills { display:flex;gap:10px;flex-wrap:wrap;margin-bottom:18px; }
        .status-pill {
            display:flex;align-items:center;gap:7px;padding:8px 16px;
            border-radius:24px;font-size:.82rem;font-weight:700;cursor:pointer;
            transition:transform .15s,box-shadow .15s;border:2px solid transparent;
            user-select:none;
        }
        .status-pill:hover { transform:translateY(-1px);box-shadow:var(--shadow); }
        .status-pill.active { border-color:currentColor; }
        .status-pill.all    { background:#f0f0f0;color:var(--text-dark); }
        .status-pill.exp    { background:var(--danger-light);color:var(--danger); }
        .status-pill.near   { background:var(--warning-light);color:#8a5a00; }
        .status-pill.safe   { background:var(--success-light);color:var(--success); }
        .status-pill.noexp  { background:var(--info-light);color:var(--info); }
        .status-pill .pill-count { font-size:.95rem; }

        /* TABLE */
        .table-wrapper { overflow-x:auto;border-radius:10px;border:1px solid var(--border); }
        .data-table { width:100%;border-collapse:collapse;font-size:.87rem;min-width:600px; }
        .data-table thead th { background:var(--bg);color:var(--text);font-size:.71rem;font-weight:800;text-transform:uppercase;letter-spacing:.05em;padding:11px 14px;border-bottom:2px solid var(--border);white-space:nowrap; }
        .data-table tbody tr { border-bottom:1px solid var(--border);transition:background .15s; }
        .data-table tbody tr:last-child { border-bottom:none; }
        .data-table td { padding:11px 14px;color:var(--text-dark);vertical-align:middle; }

        /* ROW STATUS COLORS */
        .row-expired  { background:rgba(224,85,85,.05)!important; }
        .row-near     { background:rgba(243,156,18,.05)!important; }
        .row-safe     { background:rgba(39,174,96,.03)!important; }
        .row-noexpiry { background:rgba(41,128,185,.04)!important; }
        .data-table tbody tr.row-expired:hover  { background:rgba(224,85,85,.1)!important; }
        .data-table tbody tr.row-near:hover     { background:rgba(243,156,18,.1)!important; }
        .data-table tbody tr.row-safe:hover     { background:rgba(39,174,96,.07)!important; }
        .data-table tbody tr.row-noexpiry:hover { background:rgba(41,128,185,.08)!important; }

        /* BADGES */
        .badge { display:inline-block;padding:3px 10px;border-radius:20px;font-size:.74rem;font-weight:700; }
        .badge-expired  { background:var(--danger-light);  color:var(--danger);  border:1px solid rgba(224,85,85,.2); }
        .badge-near     { background:var(--warning-light); color:#8a5a00;        border:1px solid rgba(243,156,18,.25); }
        .badge-safe     { background:var(--success-light); color:var(--success); border:1px solid rgba(39,174,96,.2); }
        .badge-noexp    { background:var(--info-light);    color:var(--info);    border:1px solid rgba(41,128,185,.2); }
        .badge-type     { background:var(--primary-light); color:var(--primary); }
        .badge-muted    { background:#eee;color:var(--text); }
        .badge-purple   { background:var(--purple-light);  color:var(--purple); }

        /* DAYS LEFT indicator */
        .days-left { font-size:.75rem;color:var(--text);margin-top:3px; }
        .days-left.urgent { color:var(--danger);font-weight:700; }

        /* DELIVERED TABLE — toggle switch */
        .toggle-switch { position:relative;display:inline-block;width:44px;height:24px; }
        .toggle-switch input { opacity:0;width:0;height:0; }
        .toggle-slider {
            position:absolute;inset:0;background:#ccc;border-radius:24px;
            cursor:pointer;transition:background .2s;
        }
        .toggle-slider::before {
            content:"";position:absolute;height:18px;width:18px;left:3px;bottom:3px;
            background:#fff;border-radius:50%;transition:transform .2s;box-shadow:0 1px 4px rgba(0,0,0,.2);
        }
        .toggle-switch input:checked + .toggle-slider { background:var(--primary); }
        .toggle-switch input:checked + .toggle-slider::before { transform:translateX(20px); }
        .toggle-label { font-size:.8rem;font-weight:700;color:var(--text-dark);margin-left:6px;vertical-align:middle; }

        /* FORM ELEMENTS in table */
        .td-input {
            width:100%;padding:7px 10px;border:1.5px solid var(--border);border-radius:7px;
            font-family:"Nunito",sans-serif;font-size:.85rem;color:var(--text-dark);
            background:#fff;outline:none;transition:border-color .2s;
        }
        .td-input:focus { border-color:var(--primary);box-shadow:0 0 0 3px rgba(0,172,193,.1); }

        /* BUTTONS */
        .btn { display:inline-flex;align-items:center;gap:6px;padding:8px 18px;border:none;border-radius:8px;font-family:"Nunito",sans-serif;font-size:.87rem;font-weight:700;cursor:pointer;white-space:nowrap;transition:background .2s,transform .15s;text-decoration:none; }
        .btn:hover { transform:translateY(-1px); }
        .btn:active { transform:translateY(0); }
        .btn-primary { background:var(--primary);color:#fff;box-shadow:0 4px 12px rgba(0,172,193,.3); }
        .btn-primary:hover { background:#009ab0; }
        .btn-danger  { background:var(--danger); color:#fff;box-shadow:0 4px 12px rgba(224,85,85,.3); }
        .btn-danger:hover  { background:#c0392b; }
        .btn-secondary { background:#e8eaed;color:var(--text-dark); }
        .btn-secondary:hover { background:#d8dade; }
        .btn-sm { padding:5px 12px;font-size:.8rem; }

        /* EMPTY STATE */
        .empty-state { text-align:center;padding:48px 20px;color:var(--text); }
        .empty-state .empty-icon { font-size:2.5rem;margin-bottom:12px; }

        /* MODAL */
        .modal-overlay { display:none;position:fixed;inset:0;background:rgba(30,40,60,.45);z-index:500;align-items:center;justify-content:center; }
        .modal-overlay.open { display:flex; }
        .modal-box { background:var(--card);border-radius:var(--radius);box-shadow:var(--shadow-lg);width:100%;max-width:440px;padding:0;position:relative;margin:16px;overflow:hidden;animation:slideUp .25s ease; }
        @keyframes slideUp { from{opacity:0;transform:translateY(20px);} to{opacity:1;transform:translateY(0);} }
        .modal-head { padding:20px 24px;background:var(--danger);color:#fff; }
        .modal-head h4 { font-size:1rem;font-weight:800; }
        .modal-head p  { font-size:.82rem;opacity:.85;margin-top:3px; }
        .modal-body { padding:24px; }
        .modal-foot { display:flex;gap:10px;justify-content:flex-end;padding:16px 24px;border-top:1px solid var(--border); }
        .modal-close-btn { position:absolute;top:14px;right:16px;background:rgba(255,255,255,.2);border:none;color:#fff;font-size:1rem;cursor:pointer;padding:4px 8px;border-radius:6px; }
        .modal-close-btn:hover { background:rgba(255,255,255,.35); }

        .form-group { margin-bottom:16px; }
        .form-group label { display:block;font-size:.77rem;font-weight:800;color:var(--text-dark);margin-bottom:6px;text-transform:uppercase;letter-spacing:.04em; }
        .form-control { width:100%;padding:10px 14px;border:1.5px solid var(--border);border-radius:8px;font-family:"Nunito",sans-serif;font-size:.9rem;color:var(--text-dark);background:#fff;outline:none;transition:border-color .2s; }
        .form-control:focus { border-color:var(--primary);box-shadow:0 0 0 3px rgba(0,172,193,.12); }

        /* DISPOSED VALUE SUMMARY */
        .disposed-summary {
            display:flex;gap:20px;flex-wrap:wrap;
            background:var(--bg);border:1px solid var(--border);
            border-radius:10px;padding:14px 18px;margin-bottom:18px;
        }
        .ds-item .ds-label { font-size:.75rem;font-weight:800;color:var(--text);text-transform:uppercase;letter-spacing:.04em; }
        .ds-item .ds-value { font-size:1.1rem;font-weight:800;color:var(--text-dark);margin-top:2px; }

        /* NO RESULTS */
        .no-results { text-align:center;padding:28px;color:var(--text);font-style:italic;display:none; }

        /* RESPONSIVE */
        @media (max-width:1200px) { .stats-row { grid-template-columns:repeat(3,1fr); } }
        @media (max-width:900px)  { .stats-row { grid-template-columns:repeat(2,1fr); } }
        @media (max-width:768px) {
            .main-content { margin-left:0;padding:20px 16px 48px; }
            .stats-row    { grid-template-columns:1fr 1fr; }
            .filter-bar   { flex-direction:column; }
        }
        @media (max-width:480px) { .stats-row { grid-template-columns:1fr; } }
    </style>
</head>
<body>

<div class="sidebar-area">
    <?php include 'inventory_sidebar.php'; ?>
</div>

<div class="main-content">

    <!-- Page Header -->
    <div class="page-header">
        <div class="breadcrumb-row">Equipment & Medicine Stock &rsaquo; <span>Batch & Expiry Tracking</span></div>
        <h1>Batch & Expiry Tracking</h1>
        <p>Monitor medicine batches, set expiration dates, and manage expired stock disposal</p>
    </div>

    <!-- Expiry Alert Banner -->
    <?php if (!empty($seven_days_alert)): ?>
    <div class="expiry-alert-banner" id="expiryAlertBanner">
        <button class="alert-close" onclick="document.getElementById('expiryAlertBanner').style.display='none'">✕</button>
        <div class="alert-title">⚠️ Urgent Expiry Alerts (<?= count($seven_days_alert) ?> item<?= count($seven_days_alert) > 1 ? 's' : '' ?>)</div>
        <ul>
            <?php foreach ($seven_days_alert as $alertItem):
                $today    = new DateTime();
                $expiry   = new DateTime($alertItem['expiration_date']);
                $diffDays = (int)$today->diff($expiry)->format("%r%a");
                $msg = $expiry < $today
                    ? htmlspecialchars($alertItem['item_name']) . " is <strong>Expired</strong>"
                    : htmlspecialchars($alertItem['item_name']) . " expires in <strong>{$diffDays} day(s)</strong>";
            ?>
                <li><?= $msg ?> — Batch: <?= htmlspecialchars($alertItem['batch_no']) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>

    <!-- Stats -->
    <div class="stats-row">
        <div class="stat-card">
            <div class="stat-icon teal">💊</div>
            <div class="stat-info">
                <div class="value"><?= count($all_stocks) ?></div>
                <div class="label">Total Batches</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon red">🔴</div>
            <div class="stat-info">
                <div class="value red"><?= count($expired) ?></div>
                <div class="label">Expired Batches</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon amber">🟡</div>
            <div class="stat-info">
                <div class="value amber"><?= count($near_expiry) ?></div>
                <div class="label">Near Expiry (≤30d)</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon green">🟢</div>
            <div class="stat-info">
                <div class="value green"><?= count($safe) ?></div>
                <div class="label">Safe Batches</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon blue">🗑️</div>
            <div class="stat-info">
                <div class="value"><?= count($disposed) ?></div>
                <div class="label">Total Disposed</div>
            </div>
        </div>
    </div>

    <!-- Tab Bar -->
    <div class="tab-bar">
        <button class="tab-btn active" onclick="switchTab('delivered', this)">
            📦 New Delivered
            <?php if (!empty($new_delivered)): ?>
                <span class="tab-badge red"><?= count($new_delivered) ?></span>
            <?php endif; ?>
        </button>
        <button class="tab-btn" onclick="switchTab('stocks', this)">
            💊 Stocks
            <span class="tab-badge teal"><?= count($all_stocks) ?></span>
        </button>
        <button class="tab-btn" onclick="switchTab('disposed', this)">
            🗑️ Disposed
            <span class="tab-badge muted"><?= count($disposed) ?></span>
        </button>
    </div>

    <!-- ── NEW DELIVERED TAB ── -->
    <div class="tab-panel active" id="tab-delivered">
        <div class="section-header">
            <div class="icon-wrap teal">📦</div>
            <div>
                <h3>New Delivered Items — Set Expiration</h3>
                <p>Items from completed department requests awaiting expiry setup</p>
            </div>
        </div>

        <?php if (empty($new_delivered)): ?>
            <div class="empty-state">
                <div class="empty-icon">✅</div>
                <p>All delivered items have been processed. No pending expiry setups.</p>
            </div>
        <?php else: ?>
            <div class="table-wrapper">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Item Name</th>
                            <th>Category</th>
                            <th>Description</th>
                            <th>Rcvd Qty (boxes)</th>
                            <th>Pcs/Box</th>
                            <th>Total Pcs</th>
                            <th>Has Expiry?</th>
                            <th>Expiration Date</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $ndNum = 1; foreach ($new_delivered as $nd):
                            $total_pcs = intval($nd['received_quantity']) * intval($nd['pcs_per_box']);
                            if ($total_pcs <= 0) $total_pcs = intval($nd['received_quantity']);
                        ?>
                        <tr>
                            <form method="post">
                                <input type="hidden" name="set_expiry_submit" value="1">
                                <input type="hidden" name="request_item_id" value="<?= $nd['id'] ?>">
                                <td style="color:var(--text);font-size:.8rem;"><?= $ndNum++ ?></td>
                                <td>
                                    <strong><?= htmlspecialchars($nd['item_name']) ?></strong>
                                    <?php if (!empty($nd['item_type'])): ?>
                                        <div style="margin-top:4px;"><span class="badge badge-purple" style="font-size:.7rem;"><?= htmlspecialchars($nd['item_type']) ?></span></div>
                                    <?php else: ?>
                                        <div style="margin-top:4px;"><span class="badge badge-muted" style="font-size:.7rem;">No type set</span></div>
                                    <?php endif; ?>
                                </td>
                                <td style="min-width:200px;">
                                    <div style="display:flex;align-items:center;gap:6px;flex-wrap:wrap;">
                                        <?php if (!empty($nd['category'])): ?>
                                            <span class="badge badge-type" id="catBadge_<?= $nd['id'] ?>"><?= htmlspecialchars($nd['category']) ?></span>
                                        <?php else: ?>
                                            <span class="badge badge-muted" id="catBadge_<?= $nd['id'] ?>">— None —</span>
                                        <?php endif; ?>
                                        <button type="button" class="btn btn-sm"
                                            style="padding:3px 10px;font-size:.75rem;background:var(--primary-light);color:var(--primary);border:1px solid rgba(0,172,193,.3);"
                                            onclick="openCatModal('<?= addslashes(htmlspecialchars($nd['item_name'])) ?>', '<?= addslashes(htmlspecialchars($nd['category'] ?? '')) ?>', '<?= addslashes(htmlspecialchars($nd['item_type'] ?? '')) ?>')">
                                            ✏️ Edit
                                        </button>
                                    </div>
                                </td>
                                <td style="font-size:.83rem;color:var(--text);"><?= htmlspecialchars($nd['description'] ?? '—') ?></td>
                                <td><span class="badge badge-type"><?= intval($nd['received_quantity']) ?></span></td>
                                <td><?= intval($nd['pcs_per_box']) ?></td>
                                <td><span class="badge badge-type"><?= $total_pcs ?></span></td>
                                <td>
                                    <label class="toggle-switch">
                                        <input type="checkbox" name="has_expiry" id="hasExpiry_<?= $nd['id'] ?>" checked
                                               onchange="toggleExpiryField(<?= $nd['id'] ?>, this.checked)">
                                        <span class="toggle-slider"></span>
                                    </label>
                                    <span class="toggle-label" id="expiryLabel_<?= $nd['id'] ?>">Yes</span>
                                </td>
                                <td style="min-width:160px;">
                                    <div id="expiryField_<?= $nd['id'] ?>">
                                        <input type="date" name="expiry_date" class="td-input"
                                               min="<?= date('Y-m-d') ?>" required>
                                    </div>
                                    <div id="noExpiryMsg_<?= $nd['id'] ?>" style="display:none;font-size:.8rem;color:var(--text);font-style:italic;padding:8px 0;">
                                        No expiration date
                                    </div>
                                </td>
                                <td>
                                    <button type="submit" class="btn btn-primary btn-sm">✔ Save</button>
                                </td>
                            </form>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <!-- ── STOCKS TAB ── -->
    <div class="tab-panel" id="tab-stocks">
        <div class="section-header">
            <div class="icon-wrap amber">💊</div>
            <div>
                <h3>Medicine Stock Overview</h3>
                <p>All batches with expiry status — filter to find items needing action</p>
            </div>
        </div>

        <!-- Status Pills Filter -->
        <div class="status-pills">
            <div class="status-pill all active" onclick="filterStocks('all', this)">
                <span>📋 All</span><span class="pill-count"><?= count($all_stocks) ?></span>
            </div>
            <div class="status-pill exp" onclick="filterStocks('expired', this)">
                <span>🔴 Expired</span><span class="pill-count"><?= count($expired) ?></span>
            </div>
            <div class="status-pill near" onclick="filterStocks('near_expiry', this)">
                <span>🟡 Near Expiry</span><span class="pill-count"><?= count($near_expiry) ?></span>
            </div>
            <div class="status-pill safe" onclick="filterStocks('safe', this)">
                <span>🟢 Safe</span><span class="pill-count"><?= count($safe) ?></span>
            </div>
            <div class="status-pill noexp" onclick="filterStocks('no_expiry', this)">
                <span>🚫 No Expiry</span><span class="pill-count"><?= count($no_expiry) ?></span>
            </div>
        </div>

        <!-- Search -->
        <div class="filter-bar">
            <input type="text" id="stockSearch" placeholder="Search by item name or batch no…"
                   oninput="liveSearchStocks()">
        </div>

        <div class="table-wrapper">
            <?php if (empty($all_stocks)): ?>
                <div class="empty-state">
                    <div class="empty-icon">📭</div>
                    <p>No stock records found.</p>
                </div>
            <?php else: ?>
                <table class="data-table" id="stocksTable">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Item Name</th>
                            <th>Qty (pcs)</th>
                            <th>Batch No</th>
                            <th>Expiration Date</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $rowNum = 1; foreach ($all_stocks as $row):
                            switch ($row['status']) {
                                case 'Expired':
                                    $rowClass   = 'row-expired';
                                    $badgeClass = 'badge-expired';
                                    $badgeLabel = '🔴 Expired';
                                    $filterAttr = 'expired';
                                    break;
                                case 'Near Expiry':
                                    $rowClass   = 'row-near';
                                    $badgeClass = 'badge-near';
                                    $badgeLabel = '🟡 Near Expiry';
                                    $filterAttr = 'near_expiry';
                                    break;
                                case 'No Expiry':
                                    $rowClass   = 'row-noexpiry';
                                    $badgeClass = 'badge-noexp';
                                    $badgeLabel = '🚫 No Expiry';
                                    $filterAttr = 'no_expiry';
                                    break;
                                default:
                                    $rowClass   = 'row-safe';
                                    $badgeClass = 'badge-safe';
                                    $badgeLabel = '🟢 Safe';
                                    $filterAttr = 'safe';
                            }
                        ?>
                        <tr class="stock-row <?= $rowClass ?>" data-status="<?= $filterAttr ?>"
                            data-name="<?= strtolower(htmlspecialchars($row['item_name'])) ?>"
                            data-batch="<?= strtolower(htmlspecialchars($row['batch_no'])) ?>"
                            data-category="<?= strtolower(htmlspecialchars($row['category'] ?? '')) ?>">
                            <td style="color:var(--text);font-size:.8rem;"><?= $rowNum++ ?></td>
                            <td><strong><?= htmlspecialchars($row['item_name']) ?></strong></td>
                            <td><span class="badge badge-type"><?= number_format($row['quantity']) ?></span></td>
                            <td><span class="badge badge-muted" style="font-family:monospace;"><?= htmlspecialchars($row['batch_no']) ?></span></td>
                            <td>
                                <?php if ($row['status'] === 'No Expiry'): ?>
                                    <span style="color:var(--text);font-style:italic;">—</span>
                                <?php else: ?>
                                    <?= date('M d, Y', strtotime($row['expiration_date'])) ?>
                                    <?php if (!empty($row['days_left'])): ?>
                                        <div class="days-left <?= $row['days_left'] <= 7 ? 'urgent' : '' ?>">
                                            <?= $row['days_left'] ?> day(s) remaining
                                        </div>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </td>
                            <td><span class="badge <?= $badgeClass ?>"><?= $badgeLabel ?></span></td>
                            <td>
                                <?php if ($row['status'] === 'Expired'): ?>
                                    <button class="btn btn-danger btn-sm"
                                        onclick="openDisposeModal(<?= $row['batch_id'] ?>, '<?= addslashes(htmlspecialchars($row['item_name'])) ?>', <?= $row['quantity'] ?>)">
                                        🗑️ Dispose
                                    </button>
                                <?php else: ?>
                                    <span style="color:var(--text);font-size:.8rem;">—</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <div class="no-results" id="noStockResults">No items match your search.</div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ── DISPOSED TAB ── -->
    <div class="tab-panel" id="tab-disposed">
        <div class="section-header">
            <div class="icon-wrap red">🗑️</div>
            <div>
                <h3>Disposed Medicines</h3>
                <p>Full audit log of all disposed medicine batches</p>
            </div>
        </div>

        <!-- Disposed Summary -->
        <?php if (!empty($disposed)): ?>
        <div class="disposed-summary">
            <div class="ds-item">
                <div class="ds-label">Total Records</div>
                <div class="ds-value"><?= count($disposed) ?></div>
            </div>
            <div class="ds-item">
                <div class="ds-label">Total Units Disposed</div>
                <div class="ds-value"><?= number_format(array_sum(array_column($disposed, 'quantity'))) ?></div>
            </div>
            <div class="ds-item">
                <div class="ds-label">Total Value Lost</div>
                <div class="ds-value" style="color:var(--danger);">₱<?= number_format($total_disposed_value, 2) ?></div>
            </div>
        </div>
        <?php endif; ?>

        <?php if (empty($disposed)): ?>
            <div class="empty-state">
                <div class="empty-icon">🗂️</div>
                <p>No disposed medicines on record.</p>
            </div>
        <?php else: ?>
            <!-- Search -->
            <div class="filter-bar">
                <input type="text" id="disposedSearch" placeholder="Search disposed medicines…" oninput="filterDisposed()">
            </div>
            <div class="table-wrapper">
                <table class="data-table" id="disposedTable">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Batch ID</th>
                            <th>Item Name</th>
                            <th>Qty Disposed</th>
                            <th>Price</th>
                            <th>Total Loss</th>
                            <th>Expiration Date</th>
                            <th>Disposed At</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $i = 1; foreach ($disposed as $row): ?>
                        <tr>
                            <td style="color:var(--text);font-size:.8rem;"><?= $i++ ?></td>
                            <td><span class="badge badge-muted" style="font-family:monospace;"><?= $row['batch_id'] ?></span></td>
                            <td><strong><?= htmlspecialchars($row['item_name']) ?></strong></td>
                            <td><span class="badge badge-expired"><?= number_format($row['quantity']) ?></span></td>
                            <td>₱<?= number_format($row['price'], 2) ?></td>
                            <td style="color:var(--danger);font-weight:700;">₱<?= number_format($row['quantity'] * $row['price'], 2) ?></td>
                            <td><?= $row['expiration_date'] ? date('M d, Y', strtotime($row['expiration_date'])) : '—' ?></td>
                            <td style="font-size:.82rem;color:var(--text);white-space:nowrap;">
                                <?= date('M d, Y g:i A', strtotime($row['disposed_at'])) ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

</div><!-- /main-content -->

<!-- ── DISPOSE MODAL ── -->
<div class="modal-overlay" id="disposeModal">
    <div class="modal-box">
        <div class="modal-head">
            <button class="modal-close-btn" onclick="closeDisposeModal()">✕</button>
            <h4>🗑️ Confirm Disposal</h4>
            <p id="modalSubtitle">Permanently remove expired stock</p>
        </div>
        <form method="post">
            <input type="hidden" name="dispose_id" id="disposeId">
            <div class="modal-body">
                <p style="font-size:.9rem;color:var(--text-dark);margin-bottom:16px;">
                    You are about to dispose: <strong id="medicineName" style="color:var(--danger);"></strong>
                </p>
                <div class="form-group">
                    <label>Quantity to Dispose</label>
                    <input type="number" name="dispose_qty" id="disposeQty" class="form-control"
                           min="1" required placeholder="Enter quantity">
                </div>
                <div style="background:var(--danger-light);border:1px solid rgba(224,85,85,.2);border-radius:8px;padding:10px 14px;font-size:.83rem;color:var(--danger);">
                    ⚠️ This action is permanent and cannot be undone. The quantity will be recorded in the disposal log.
                </div>
            </div>
            <div class="modal-foot">
                <button type="button" class="btn btn-secondary" onclick="closeDisposeModal()">Cancel</button>
                <button type="submit" class="btn btn-danger">🗑️ Confirm Dispose</button>
            </div>
        </form>
    </div>
</div>

<!-- ── CATEGORY EDIT MODAL ── -->
<div class="modal-overlay" id="catModal">
    <div class="modal-box">
        <div class="modal-head" style="background:var(--primary);">
            <button class="modal-close-btn" onclick="closeCatModal()">✕</button>
            <h4>✏️ Edit Item Category & Type</h4>
            <p id="catModalSubtitle" style="font-size:.82rem;opacity:.85;margin-top:3px;">Update category for this item across inventory</p>
        </div>
        <form method="post">
            <input type="hidden" name="update_category_submit" value="1">
            <input type="hidden" name="inv_item_name" id="catItemName">
            <div class="modal-body">

                <div class="form-group">
                    <label>Item Type</label>
                    <select name="new_item_type" id="catItemType" class="form-control">
                        <option value="">— Select Item Type —</option>
                        <optgroup label="Medical Equipment">
                            <option value="Diagnostic Equipment">Diagnostic Equipment</option>
                            <option value="Therapeutic Equipment">Therapeutic Equipment</option>
                            <option value="Surgical Equipment">Surgical Equipment</option>
                            <option value="Laboratory Equipment">Laboratory Equipment</option>
                            <option value="Monitoring Equipment">Monitoring Equipment</option>
                            <option value="Life Support Equipment">Life Support Equipment</option>
                            <option value="Imaging Equipment">Imaging Equipment</option>
                            <option value="Rehabilitation Equipment">Rehabilitation Equipment</option>
                            <option value="Sterilization Equipment">Sterilization Equipment</option>
                        </optgroup>
                        <optgroup label="Medications & Supplies">
                            <option value="Prescription Medicine">Prescription Medicine</option>
                            <option value="Over-the-Counter Medicine">Over-the-Counter Medicine</option>
                            <option value="IV & Infusion Supplies">IV & Infusion Supplies</option>
                            <option value="Vaccines & Biologics">Vaccines & Biologics</option>
                            <option value="Medical Gases">Medical Gases</option>
                        </optgroup>
                        <optgroup label="Consumables">
                            <option value="Consumables and Disposables">Consumables and Disposables</option>
                            <option value="Personal Protective Equipment">Personal Protective Equipment</option>
                            <option value="Wound Care Supplies">Wound Care Supplies</option>
                            <option value="Linen and Patient Care">Linen and Patient Care</option>
                        </optgroup>
                        <optgroup label="Technology">
                            <option value="IT and Supporting Tech">IT and Supporting Tech</option>
                            <option value="Communication Equipment">Communication Equipment</option>
                            <option value="Software & Systems">Software & Systems</option>
                        </optgroup>
                        <optgroup label="Facilities">
                            <option value="Furniture and Fixtures">Furniture and Fixtures</option>
                            <option value="Facility Maintenance">Facility Maintenance</option>
                            <option value="Safety and Security">Safety and Security</option>
                            <option value="Cleaning and Sanitation">Cleaning and Sanitation</option>
                        </optgroup>
                    </select>
                </div>

                <div class="form-group">
                    <label>Category</label>
                    <select name="new_category" id="catCategory" class="form-control" required>
                        <option value="">— Select Category —</option>
                        <optgroup label="Medications & Pharmacy">
                            <option value="Medications and pharmacy supplies">Medications and pharmacy supplies</option>
                            <option value="Vaccines and immunizations">Vaccines and immunizations</option>
                            <option value="IV fluids and solutions">IV fluids and solutions</option>
                            <option value="Antibiotics">Antibiotics</option>
                            <option value="Analgesics and pain management">Analgesics and pain management</option>
                            <option value="Cardiovascular drugs">Cardiovascular drugs</option>
                            <option value="Respiratory medications">Respiratory medications</option>
                            <option value="Oncology medications">Oncology medications</option>
                            <option value="Psychiatric medications">Psychiatric medications</option>
                            <option value="Ophthalmic preparations">Ophthalmic preparations</option>
                            <option value="Vitamins and supplements">Vitamins and supplements</option>
                        </optgroup>
                        <optgroup label="Diagnostic & Imaging Equipment">
                            <option value="Diagnostic Equipment">Diagnostic Equipment</option>
                            <option value="Imaging and radiology machines">Imaging and radiology machines</option>
                            <option value="Laboratory analyzers">Laboratory analyzers</option>
                            <option value="Ultrasound equipment">Ultrasound equipment</option>
                            <option value="ECG and cardiac monitors">ECG and cardiac monitors</option>
                            <option value="Endoscopy equipment">Endoscopy equipment</option>
                        </optgroup>
                        <optgroup label="Therapeutic & Surgical Equipment">
                            <option value="Therapeutic equipment">Therapeutic equipment</option>
                            <option value="Surgical instruments">Surgical instruments</option>
                            <option value="Anesthesia machines">Anesthesia machines</option>
                            <option value="Infusion pumps">Infusion pumps</option>
                            <option value="Ventilators and respirators">Ventilators and respirators</option>
                            <option value="Defibrillators">Defibrillators</option>
                            <option value="Dialysis machines">Dialysis machines</option>
                            <option value="Rehabilitation equipment">Rehabilitation equipment</option>
                            <option value="Sterilization machines">Sterilization machines</option>
                            <option value="Patient beds and furniture">Patient beds and furniture</option>
                            <option value="Wheelchairs and mobility aids">Wheelchairs and mobility aids</option>
                        </optgroup>
                        <optgroup label="Consumables & Disposables">
                            <option value="Consumables and disposables">Consumables and disposables</option>
                            <option value="Syringes and needles">Syringes and needles</option>
                            <option value="Gloves and PPE">Gloves and PPE</option>
                            <option value="Surgical masks and gowns">Surgical masks and gowns</option>
                            <option value="Bandages and wound care">Bandages and wound care</option>
                            <option value="Catheters and tubing">Catheters and tubing</option>
                            <option value="Blood collection supplies">Blood collection supplies</option>
                            <option value="Linen and patient clothing">Linen and patient clothing</option>
                        </optgroup>
                        <optgroup label="Laboratory Supplies">
                            <option value="Laboratory reagents and chemicals">Laboratory reagents and chemicals</option>
                            <option value="Test kits and rapid diagnostics">Test kits and rapid diagnostics</option>
                            <option value="Culture media and specimens">Culture media and specimens</option>
                            <option value="Microscopy supplies">Microscopy supplies</option>
                        </optgroup>
                        <optgroup label="IT & Technology">
                            <option value="IT and supporting tech">IT and supporting tech</option>
                            <option value="Hospital information systems">Hospital information systems</option>
                            <option value="Telemedicine equipment">Telemedicine equipment</option>
                            <option value="Communication devices">Communication devices</option>
                            <option value="CCTV and security systems">CCTV and security systems</option>
                        </optgroup>
                        <optgroup label="Facilities & Maintenance">
                            <option value="Furniture and fixtures">Furniture and fixtures</option>
                            <option value="Cleaning and sanitation supplies">Cleaning and sanitation supplies</option>
                            <option value="Facility maintenance tools">Facility maintenance tools</option>
                            <option value="Safety and fire equipment">Safety and fire equipment</option>
                            <option value="Electrical and plumbing supplies">Electrical and plumbing supplies</option>
                            <option value="Medical gas systems">Medical gas systems</option>
                            <option value="Kitchen and dietary supplies">Kitchen and dietary supplies</option>
                            <option value="Laundry equipment">Laundry equipment</option>
                        </optgroup>
                        <optgroup label="Office & Administrative">
                            <option value="Office supplies">Office supplies</option>
                            <option value="Printing and stationery">Printing and stationery</option>
                            <option value="Administrative equipment">Administrative equipment</option>
                        </optgroup>
                    </select>
                </div>

                <div style="background:var(--primary-light);border:1px solid rgba(0,172,193,.2);border-radius:8px;padding:10px 14px;font-size:.83rem;color:var(--primary);">
                    ℹ️ This will update the category and item type for <strong id="catItemNameDisplay"></strong> across all matching inventory records.
                </div>
            </div>
            <div class="modal-foot">
                <button type="button" class="btn btn-secondary" onclick="closeCatModal()">Cancel</button>
                <button type="submit" class="btn btn-primary">💾 Save Category</button>
            </div>
        </form>
    </div>
</div>

<script>
    // ── Tab switching ──
    function switchTab(name, btn) {
        document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
        document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
        document.getElementById('tab-' + name).classList.add('active');
        btn.classList.add('active');
    }

    // ── Toggle expiry field ──
    function toggleExpiryField(itemId, hasExpiry) {
        const fieldDiv  = document.getElementById('expiryField_' + itemId);
        const msgDiv    = document.getElementById('noExpiryMsg_' + itemId);
        const label     = document.getElementById('expiryLabel_' + itemId);
        const dateInput = fieldDiv.querySelector('input[type="date"]');

        if (hasExpiry) {
            fieldDiv.style.display  = '';
            msgDiv.style.display    = 'none';
            label.textContent       = 'Yes';
            dateInput.required      = true;
        } else {
            fieldDiv.style.display  = 'none';
            msgDiv.style.display    = '';
            label.textContent       = 'No';
            dateInput.required      = false;
            dateInput.value         = '';
        }
    }

    // ── Filter stocks by status pill ──
    let currentStatusFilter = 'all';

    function filterStocks(value, pill) {
        currentStatusFilter = value;

        document.querySelectorAll('.status-pill').forEach(p => p.classList.remove('active'));
        if (pill) pill.classList.add('active');

        applyStockFilters();
    }

    function liveSearchStocks() { applyStockFilters(); }

    function applyStockFilters() {
        const q      = (document.getElementById('stockSearch')?.value || '').toLowerCase();
        const rows   = document.querySelectorAll('#stocksTable .stock-row');
        let   visible = 0;

        rows.forEach(row => {
            const statusMatch = currentStatusFilter === 'all' || row.dataset.status === currentStatusFilter;
            const textMatch   = !q || row.dataset.name.includes(q) || row.dataset.batch.includes(q);
            const show        = statusMatch && textMatch;
            row.style.display = show ? '' : 'none';
            if (show) visible++;
        });

        const noRes = document.getElementById('noStockResults');
        if (noRes) noRes.style.display = visible === 0 ? 'block' : 'none';
    }

    // ── Disposed search ──
    function filterDisposed() {
        const q = document.getElementById('disposedSearch').value.toLowerCase();
        document.querySelectorAll('#disposedTable tbody tr').forEach(row => {
            row.style.display = row.textContent.toLowerCase().includes(q) ? '' : 'none';
        });
    }

    // ── Dispose Modal ──
    function openDisposeModal(id, name, qty) {
        document.getElementById('disposeId').value     = id;
        document.getElementById('medicineName').textContent = name;
        document.getElementById('disposeQty').value   = qty;
        document.getElementById('disposeQty').max     = qty;
        document.getElementById('modalSubtitle').textContent = 'Batch has ' + qty + ' unit(s) available';
        document.getElementById('disposeModal').classList.add('open');
        setTimeout(() => document.getElementById('disposeQty').focus(), 100);
    }

    function closeDisposeModal() {
        document.getElementById('disposeModal').classList.remove('open');
    }

    document.getElementById('disposeModal').addEventListener('click', function(e) {
        if (e.target === this) closeDisposeModal();
    });

    document.addEventListener('keydown', e => {
        if (e.key === 'Escape') { closeDisposeModal(); closeCatModal(); }
    });

    // ── Category Modal ──
    function openCatModal(itemName, currentCat, currentType) {
        document.getElementById('catItemName').value          = itemName;
        document.getElementById('catItemNameDisplay').textContent = itemName;
        document.getElementById('catModalSubtitle').textContent   = 'Item: ' + itemName;

        // Pre-select current values
        const catSel  = document.getElementById('catCategory');
        const typeSel = document.getElementById('catItemType');

        // Try to match existing option, else leave at default
        for (let opt of catSel.options) {
            if (opt.value.toLowerCase() === currentCat.toLowerCase()) { catSel.value = opt.value; break; }
        }
        for (let opt of typeSel.options) {
            if (opt.value.toLowerCase() === currentType.toLowerCase()) { typeSel.value = opt.value; break; }
        }

        document.getElementById('catModal').classList.add('open');
    }

    function closeCatModal() {
        document.getElementById('catModal').classList.remove('open');
        document.getElementById('catCategory').value  = '';
        document.getElementById('catItemType').value  = '';
    }

    document.getElementById('catModal').addEventListener('click', function(e) {
        if (e.target === this) closeCatModal();
    });

    // Auto-open delivered tab if redirected with ?tab=delivered
    if (window.location.search.includes('tab=delivered')) {
        const delBtn = document.querySelector('.tab-btn');
        if (delBtn) switchTab('delivered', delBtn);
    }
</script>
</body>
</html>