<?php
session_start();
include '../../SQL/config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$success_msg = '';
$error_msg   = '';

// ── 1. ALLOCATE: Send item from main inventory to a department ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'allocate') {
    $item_id   = intval($_POST['item_id']);
    $dept      = trim($_POST['department']);
    $qty       = intval($_POST['qty']);

    if ($item_id && $dept && $qty > 0) {
        $s = $pdo->prepare("SELECT quantity, item_name FROM inventory WHERE item_id = ?");
        $s->execute([$item_id]);
        $inv = $s->fetch(PDO::FETCH_ASSOC);

        if ($inv && $qty <= $inv['quantity']) {
            try {
                $pdo->beginTransaction();

                $pdo->prepare("UPDATE inventory SET quantity = quantity - ? WHERE item_id = ?")
                    ->execute([$qty, $item_id]);

                $s2 = $pdo->prepare("SELECT id FROM department_assets WHERE item_id = ? AND department = ?");
                $s2->execute([$item_id, $dept]);
                $existing = $s2->fetch(PDO::FETCH_ASSOC);

                if ($existing) {
                    $pdo->prepare("UPDATE department_assets SET quantity = quantity + ? WHERE id = ?")
                        ->execute([$qty, $existing['id']]);
                } else {
                    $pdo->prepare("INSERT INTO department_assets (item_id, department, quantity) VALUES (?, ?, ?)")
                        ->execute([$item_id, $dept, $qty]);
                }

                $pdo->commit();
                $success_msg = "Successfully allocated {$qty}x " . htmlspecialchars($inv['item_name']) . " to " . htmlspecialchars($dept) . ".";
            } catch (Exception $e) {
                $pdo->rollBack();
                $error_msg = "Database error: " . $e->getMessage();
            }
        } else {
            $error_msg = "Insufficient stock in main inventory.";
        }
    } else {
        $error_msg = "Please fill in all fields correctly.";
    }
}

// ── 2. TRANSFER: Move between departments ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'transfer') {
    $source_dept = trim($_POST['source_department']);
    $dest_dept   = trim($_POST['destination_department']);
    $item_id     = intval($_POST['item_id']);
    $qty         = intval($_POST['transfer_qty']);

    if ($source_dept === $dest_dept) {
        $error_msg = "Source and destination cannot be the same.";
    } elseif ($qty > 0) {
        $s = $pdo->prepare("SELECT * FROM department_assets WHERE item_id = ? AND department = ?");
        $s->execute([$item_id, $source_dept]);
        $src = $s->fetch(PDO::FETCH_ASSOC);

        if ($src && $qty <= $src['quantity']) {
            try {
                $pdo->beginTransaction();

                $pdo->prepare("UPDATE department_assets SET quantity = quantity - ? WHERE id = ?")
                    ->execute([$qty, $src['id']]);

                $s2 = $pdo->prepare("SELECT id FROM department_assets WHERE item_id = ? AND department = ?");
                $s2->execute([$item_id, $dest_dept]);
                $dst = $s2->fetch(PDO::FETCH_ASSOC);

                if ($dst) {
                    $pdo->prepare("UPDATE department_assets SET quantity = quantity + ? WHERE id = ?")
                        ->execute([$qty, $dst['id']]);
                } else {
                    $pdo->prepare("INSERT INTO department_assets (item_id, department, quantity) VALUES (?, ?, ?)")
                        ->execute([$item_id, $dest_dept, $qty]);
                }

                $pdo->commit();
                $success_msg = "Transfer successful.";
            } catch (Exception $e) {
                $pdo->rollBack();
                $error_msg = "Database error: " . $e->getMessage();
            }
        } else {
            $error_msg = "Insufficient quantity in source department.";
        }
    }
}

// ── 3. DISPOSE ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'dispose') {
    $item_id     = intval($_POST['item_id']);
    $dispose_qty = intval($_POST['dispose_qty']);
    $location    = trim($_POST['location']);

    if ($dispose_qty > 0) {
        try {
            if ($location === 'Main Storage') {
                $s = $pdo->prepare("SELECT quantity FROM inventory WHERE item_id = ?");
                $s->execute([$item_id]);
                $inv = $s->fetch(PDO::FETCH_ASSOC);
                if ($inv && $dispose_qty <= $inv['quantity']) {
                    $pdo->prepare("UPDATE inventory SET quantity = quantity - ? WHERE item_id = ?")
                        ->execute([$dispose_qty, $item_id]);
                    $success_msg = "Asset disposed from Main Storage.";
                } else {
                    $error_msg = "Insufficient quantity.";
                }
            } else {
                $s = $pdo->prepare("SELECT * FROM department_assets WHERE item_id = ? AND department = ?");
                $s->execute([$item_id, $location]);
                $di = $s->fetch(PDO::FETCH_ASSOC);
                if ($di && $dispose_qty <= $di['quantity']) {
                    $pdo->prepare("UPDATE department_assets SET quantity = quantity - ? WHERE id = ?")
                        ->execute([$dispose_qty, $di['id']]);
                    $success_msg = "Asset disposed from " . htmlspecialchars($location) . ".";
                } else {
                    $error_msg = "Insufficient quantity.";
                }
            }
        } catch (Exception $e) {
            $error_msg = "Database error: " . $e->getMessage();
        }
    }
}

// ── DATA FETCHING ──

// All inventory items
$all_inventory = $pdo->query("SELECT item_id, item_name, item_type, quantity, unit_type, price FROM inventory ORDER BY item_name")->fetchAll(PDO::FETCH_ASSOC);

// Departments from the departments table
$departments = $pdo->query("SELECT department_name FROM departments ORDER BY department_id")->fetchAll(PDO::FETCH_COLUMN);

// Department assets
$dept_assets = $pdo->query("
    SELECT da.id, da.item_id, da.department, da.quantity,
           i.item_name, i.unit_type, i.price, i.item_type
    FROM department_assets da
    JOIN inventory i ON da.item_id = i.item_id
    ORDER BY da.department, i.item_name
")->fetchAll(PDO::FETCH_ASSOC);

// Main inventory > 0
$main_inventory = $pdo->query("SELECT item_id, item_name, item_type, quantity, price FROM inventory WHERE quantity > 0 ORDER BY item_name")->fetchAll(PDO::FETCH_ASSOC);

// Stats
$total_units    = array_sum(array_column($dept_assets, 'quantity'));
$dept_count     = count($departments);
$total_value    = array_sum(array_map(fn($a) => $a['quantity'] * $a['price'], $dept_assets));
$main_stock_val = array_sum(array_map(fn($a) => $a['quantity'] * $a['price'], $main_inventory));

// JS data
$dept_items_js = [];
foreach ($dept_assets as $da) {
    $dept_items_js[$da['department']][] = ['item_id' => $da['item_id'], 'name' => $da['item_name'], 'qty' => $da['quantity']];
}

$main_inv_js = array_map(fn($m) => ['item_id' => $m['item_id'], 'name' => $m['item_name'], 'qty' => $m['quantity']], $main_inventory);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Asset Transfer & Disposal</title>
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
            --warning-light: rgba(243,156,18,.1);
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

        body {
            font-family: "Nunito", "Segoe UI", Arial, sans-serif;
            background: var(--bg); color: var(--text); min-height: 100vh;
        }

        .sidebar-area {
            position: fixed; left: 0; top: 0;
            width: var(--sidebar-w); height: 100vh; z-index: 100;
        }

        .main-content {
            margin-left: var(--sidebar-w);
            min-height: 100vh;
            padding: 32px 32px 56px;
        }

        /* PAGE HEADER */
        .page-header { margin-bottom: 28px; }
        .breadcrumb {
            display: flex; align-items: center; gap: 6px;
            font-size: .8rem; margin-bottom: 6px;
        }
        .breadcrumb span { color: var(--primary); font-weight: 700; }
        .page-header h1 { font-size: 1.65rem; font-weight: 800; color: var(--text-dark); }
        .page-header p  { font-size: .9rem; color: var(--text); margin-top: 4px; }

        /* ALERTS */
        .alert {
            padding: 13px 18px; border-radius: 10px;
            font-size: .9rem; font-weight: 600;
            display: flex; align-items: center; gap: 10px;
            margin-bottom: 22px; animation: slideIn .3s ease;
        }
        @keyframes slideIn { from{opacity:0;transform:translateY(-8px);} to{opacity:1;transform:translateY(0);} }
        .alert-success { background: var(--success-light); color: var(--success); border:1px solid rgba(39,174,96,.25); }
        .alert-danger  { background: var(--danger-light);  color: var(--danger);  border:1px solid rgba(224,85,85,.25); }

        /* STATS */
        .stats-row {
            display: grid; grid-template-columns: repeat(4,1fr);
            gap: 16px; margin-bottom: 28px;
        }
        .stat-card {
            background: var(--card); border-radius: var(--radius);
            padding: 18px 20px; border: 1px solid var(--border);
            box-shadow: var(--shadow);
            display: flex; align-items: center; gap: 14px;
            transition: transform .2s, box-shadow .2s;
        }
        .stat-card:hover { transform: translateY(-2px); box-shadow: var(--shadow-lg); }
        .stat-icon {
            width: 48px; height: 48px; border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.3rem; flex-shrink: 0;
        }
        .stat-icon.teal   { background: var(--primary-light); }
        .stat-icon.green  { background: var(--success-light); }
        .stat-icon.amber  { background: var(--warning-light); }
        .stat-icon.purple { background: var(--purple-light);  }
        .stat-info .value { font-size: 1.4rem; font-weight: 800; color: var(--text-dark); line-height: 1; }
        .stat-info .label { font-size: .78rem; color: var(--text); margin-top: 3px; }

        /* TABS */
        .tab-bar {
            display: flex; gap: 4px;
            background: var(--card); border: 1px solid var(--border);
            border-radius: 12px 12px 0 0;
            padding: 8px 8px 0;
            box-shadow: var(--shadow); overflow-x: auto;
        }
        .tab-btn {
            display: flex; align-items: center; gap: 7px;
            padding: 10px 18px; border: none; background: none;
            font-family: "Nunito", sans-serif; font-size: .88rem; font-weight: 700;
            color: var(--text); cursor: pointer;
            border-radius: 8px 8px 0 0; border-bottom: 3px solid transparent;
            transition: color .2s, background .2s, border-color .2s; white-space: nowrap;
        }
        .tab-btn:hover  { background: var(--primary-light); color: var(--primary); }
        .tab-btn.active { color: var(--primary); border-bottom-color: var(--primary); background: var(--primary-light); }
        .tab-badge {
            background: var(--primary); color: #fff;
            font-size: .7rem; font-weight: 700;
            padding: 2px 7px; border-radius: 20px;
        }

        /* TAB PANELS */
        .tab-panel {
            display: none; background: var(--card);
            border: 1px solid var(--border); border-top: none;
            border-radius: 0 0 12px 12px;
            padding: 28px; box-shadow: var(--shadow);
            animation: fadeIn .25s ease;
        }
        .tab-panel.active { display: block; }
        @keyframes fadeIn { from{opacity:0;} to{opacity:1;} }

        /* SECTION HEADER */
        .section-header {
            display: flex; align-items: center; gap: 12px;
            margin-bottom: 24px; padding-bottom: 16px;
            border-bottom: 1px solid var(--border);
        }
        .icon-wrap {
            width: 40px; height: 40px; border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.15rem; flex-shrink: 0;
        }
        .icon-wrap.teal   { background: var(--primary-light); }
        .icon-wrap.blue   { background: rgba(41,128,185,.1);  }
        .icon-wrap.red    { background: var(--danger-light);  }
        .icon-wrap.purple { background: var(--purple-light);  }
        .section-header h3 { font-size: 1.05rem; font-weight: 800; color: var(--text-dark); }
        .section-header p  { font-size: .82rem; color: var(--text); margin-top: 2px; }

        /* ITEM PREVIEW */
        .item-preview {
            background: var(--bg); border: 1px solid var(--border);
            border-radius: 10px; padding: 14px 18px;
            margin-top: 18px; display: none;
            gap: 28px; flex-wrap: wrap; animation: fadeIn .2s ease;
        }
        .item-preview.show { display: flex; }
        .pi-label { font-size: .72rem; font-weight: 800; color: var(--text); text-transform: uppercase; letter-spacing: .04em; }
        .pi-value { font-size: 1rem; font-weight: 700; color: var(--text-dark); margin-top: 2px; }
        .pi-value.low { color: var(--danger); }
        .pi-value.ok  { color: var(--success); }

        /* FORMS */
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
            gap: 18px; align-items: end;
        }
        .form-group label {
            display: block; font-size: .77rem; font-weight: 800;
            color: var(--text-dark); margin-bottom: 7px;
            letter-spacing: .04em; text-transform: uppercase;
        }
        .form-control {
            width: 100%; padding: 10px 14px;
            border: 1.5px solid var(--border); border-radius: 8px;
            font-family: "Nunito", sans-serif; font-size: .9rem;
            color: var(--text-dark); background: #fff; outline: none;
            transition: border-color .2s, box-shadow .2s; appearance: none;
        }
        .form-control:focus { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(0,172,193,.12); }
        select.form-control {
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%236e768e' d='M6 8L1 3h10z'/%3E%3C/svg%3E");
            background-repeat: no-repeat; background-position: right 12px center; padding-right: 32px;
        }

        /* BUTTONS */
        .btn {
            display: inline-flex; align-items: center; gap: 7px;
            padding: 10px 22px; border: none; border-radius: 8px;
            font-family: "Nunito", sans-serif; font-size: .9rem; font-weight: 700;
            cursor: pointer; white-space: nowrap;
            transition: background .2s, transform .15s, box-shadow .2s;
        }
        .btn:hover  { transform: translateY(-1px); }
        .btn:active { transform: translateY(0); }
        .btn-primary { background: var(--primary); color:#fff; box-shadow:0 4px 14px rgba(0,172,193,.3); }
        .btn-primary:hover { background:#009ab0; }
        .btn-success { background: var(--success); color:#fff; box-shadow:0 4px 14px rgba(39,174,96,.3); }
        .btn-success:hover { background:#219150; }
        .btn-danger  { background: var(--danger);  color:#fff; box-shadow:0 4px 14px rgba(224,85,85,.3); }
        .btn-danger:hover  { background:#c0392b; }

        /* ARROW */
        .arrow-cell {
            display: flex; align-items: flex-end; justify-content: center;
            padding-bottom: 11px; color: var(--primary); font-size: 1.4rem; font-weight: 800;
        }

        /* TABLES */
        .table-search-bar {
            display: flex; gap: 12px; align-items: center;
            margin-bottom: 16px; flex-wrap: wrap;
        }
        .search-input {
            flex: 1; min-width: 200px; padding: 9px 14px 9px 36px;
            border: 1.5px solid var(--border); border-radius: 8px;
            font-family: "Nunito", sans-serif; font-size: .88rem;
            color: var(--text-dark); outline: none;
            background: #fff url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' fill='%236e768e' viewBox='0 0 16 16'%3E%3Cpath d='M11.742 10.344a6.5 6.5 0 1 0-1.397 1.398h-.001c.03.04.062.078.098.115l3.85 3.85a1 1 0 0 0 1.415-1.414l-3.85-3.85a1.007 1.007 0 0 0-.115-.099zm-5.242 1.156a5.5 5.5 0 1 1 0-11 5.5 5.5 0 0 1 0 11z'/%3E%3C/svg%3E") no-repeat 12px center;
            transition: border-color .2s;
        }
        .search-input:focus { border-color: var(--primary); }
        .filter-select {
            padding: 9px 14px; border: 1.5px solid var(--border); border-radius: 8px;
            font-family: "Nunito", sans-serif; font-size: .88rem;
            color: var(--text-dark); background: #fff; outline: none; cursor: pointer;
        }
        .filter-select:focus { border-color: var(--primary); }
        .table-wrapper { overflow-x: auto; border-radius: 10px; border: 1px solid var(--border); }
        .data-table { width: 100%; border-collapse: collapse; font-size: .88rem; }
        .data-table thead th {
            background: var(--bg); color: var(--text);
            font-size: .72rem; font-weight: 800; text-transform: uppercase;
            letter-spacing: .05em; padding: 12px 16px;
            border-bottom: 2px solid var(--border); white-space: nowrap;
        }
        .data-table tbody tr { border-bottom: 1px solid var(--border); transition: background .15s; }
        .data-table tbody tr:hover { background: var(--primary-light); }
        .data-table tbody tr:last-child { border-bottom: none; }
        .data-table td { padding: 11px 16px; color: var(--text-dark); vertical-align: middle; }

        .badge {
            display: inline-block; padding: 3px 10px;
            border-radius: 20px; font-size: .75rem; font-weight: 700;
        }
        .badge-dept   { background: var(--primary-light); color: var(--primary); }
        .badge-type   { background: var(--purple-light);  color: var(--purple);  }
        .badge-qty    { background: var(--success-light); color: var(--success); }
        .badge-warn   { background: var(--warning-light); color: var(--warning); }
        .badge-out    { background: #fde8e8; color: var(--danger); }

        .empty-state { text-align: center; padding: 48px 20px; color: var(--text); }
        .empty-state .empty-icon { font-size: 2.5rem; margin-bottom: 12px; }

        /* RESPONSIVE */
        @media (max-width: 1100px) { .stats-row { grid-template-columns: repeat(2,1fr); } }
        @media (max-width: 768px) {
            .main-content { margin-left: 0; padding: 20px 16px 48px; }
            .stats-row    { grid-template-columns: 1fr 1fr; }
            .form-grid    { grid-template-columns: 1fr; }
            .arrow-cell   { display: none; }
        }
        @media (max-width: 480px) { .stats-row { grid-template-columns: 1fr; } }
    </style>
</head>
<body>

<div class="sidebar-area">
    <?php include 'inventory_sidebar.php'; ?>
</div>

<div class="main-content">

    <!-- Page Header -->
    <div class="page-header">
        <div class="breadcrumb">Asset Tracking &rsaquo; <span>Transfer & Disposal</span></div>
        <h1>Asset Transfer & Disposal</h1>
        <p>Allocate items from inventory to departments, transfer between departments, or dispose assets</p>
    </div>

    <!-- Alerts -->
    <?php if ($success_msg): ?>
        <div class="alert alert-success">✅ <?= htmlspecialchars($success_msg) ?></div>
    <?php endif; ?>
    <?php if ($error_msg): ?>
        <div class="alert alert-danger">⚠️ <?= htmlspecialchars($error_msg) ?></div>
    <?php endif; ?>

    <!-- Stats -->
    <div class="stats-row">
        <div class="stat-card">
            <div class="stat-icon teal">📦</div>
            <div class="stat-info">
                <div class="value"><?= number_format($total_units) ?></div>
                <div class="label">Dept. Asset Units</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon green">🏢</div>
            <div class="stat-info">
                <div class="value"><?= $dept_count ?></div>
                <div class="label">Active Departments</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon amber">💰</div>
            <div class="stat-info">
                <div class="value">₱<?= number_format($total_value, 0) ?></div>
                <div class="label">Dept. Asset Value</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon purple">🏪</div>
            <div class="stat-info">
                <div class="value">₱<?= number_format($main_stock_val, 0) ?></div>
                <div class="label">Main Storage Value</div>
            </div>
        </div>
    </div>

    <!-- Tab Bar -->
    <div class="tab-bar">
        <button class="tab-btn active" onclick="switchTab('allocate', this)">📤 Allocate to Department</button>
        <button class="tab-btn" onclick="switchTab('transfer', this)">🔄 Transfer Between Depts</button>
        <button class="tab-btn" onclick="switchTab('disposal', this)">🗑️ Dispose Asset</button>
        <button class="tab-btn" onclick="switchTab('current', this)">
            📋 Current Assets <span class="tab-badge"><?= count($dept_assets) ?></span>
        </button>
        <button class="tab-btn" onclick="switchTab('mainstorage', this)">
            🏪 Main Storage <span class="tab-badge"><?= count($main_inventory) ?></span>
        </button>
    </div>

    <!-- ── ALLOCATE TAB ── -->
    <div class="tab-panel active" id="tab-allocate">
        <div class="section-header">
            <div class="icon-wrap teal">📤</div>
            <div>
                <h3>Allocate Item to Department</h3>
                <p>Pick any item from main inventory and send it to a specific department or location</p>
            </div>
        </div>

        <form method="post" id="allocateForm">
            <input type="hidden" name="action" value="allocate">
            <div class="form-grid" style="grid-template-columns: 2fr 2fr 1fr auto;">

                <div class="form-group">
                    <label>Item from Inventory</label>
                    <select name="item_id" id="alloc_item" class="form-control" required onchange="updateItemPreview()">
                        <option value="">— Select an item —</option>
                        <?php foreach ($all_inventory as $item): ?>
                            <option value="<?= $item['item_id'] ?>"
                                data-qty="<?= $item['quantity'] ?>"
                                data-type="<?= htmlspecialchars($item['item_type']) ?>"
                                data-unit="<?= htmlspecialchars($item['unit_type']) ?>"
                                data-price="<?= $item['price'] ?>">
                                <?= htmlspecialchars($item['item_name']) ?>
                                (Stock: <?= $item['quantity'] ?> <?= htmlspecialchars($item['unit_type']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Destination Department</label>
                    <select name="department" class="form-control" required>
                        <option value="">— Select Department —</option>
                        <?php foreach ($departments as $d): ?>
                            <option value="<?= htmlspecialchars($d) ?>"><?= htmlspecialchars($d) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Quantity</label>
                    <input type="number" name="qty" id="alloc_qty" class="form-control" min="1" placeholder="0" required oninput="updateItemPreview()">
                </div>

                <div class="form-group" style="display:flex;align-items:flex-end;">
                    <button type="submit" class="btn btn-success">📤 Allocate</button>
                </div>
            </div>

            <!-- Live preview -->
            <div class="item-preview" id="itemPreview">
                <div><div class="pi-label">Item Type</div><div class="pi-value" id="prev_type">—</div></div>
                <div><div class="pi-label">Available Stock</div><div class="pi-value" id="prev_stock">—</div></div>
                <div><div class="pi-label">After Allocation</div><div class="pi-value" id="prev_remaining">—</div></div>
                <div><div class="pi-label">Unit Price</div><div class="pi-value" id="prev_price">—</div></div>
                <div><div class="pi-label">Total Cost</div><div class="pi-value" id="prev_total">—</div></div>
            </div>
        </form>
    </div>

    <!-- ── TRANSFER TAB ── -->
    <div class="tab-panel" id="tab-transfer">
        <div class="section-header">
            <div class="icon-wrap blue">🔄</div>
            <div>
                <h3>Transfer Asset Between Departments</h3>
                <p>Move allocated assets from one department to another</p>
            </div>
        </div>

        <form method="post">
            <input type="hidden" name="action" value="transfer">
            <div class="form-grid" style="grid-template-columns: 1fr 40px 1fr 1fr 1fr auto;">

                <div class="form-group">
                    <label>Source Department</label>
                    <select name="source_department" id="src_dept" class="form-control" required onchange="updateTransferItems()">
                        <option value="">— Select Source —</option>
                        <?php foreach ($departments as $d): ?>
                            <option value="<?= htmlspecialchars($d) ?>"><?= htmlspecialchars($d) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="arrow-cell">→</div>

                <div class="form-group">
                    <label>Destination Department</label>
                    <select name="destination_department" class="form-control" required>
                        <option value="">— Select Destination —</option>
                        <?php foreach ($departments as $d): ?>
                            <option value="<?= htmlspecialchars($d) ?>"><?= htmlspecialchars($d) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Item</label>
                    <select name="item_id" id="transfer_item" class="form-control" required>
                        <option value="">— Select Source First —</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Quantity</label>
                    <input type="number" name="transfer_qty" class="form-control" min="1" placeholder="0" required>
                </div>

                <div class="form-group" style="display:flex;align-items:flex-end;">
                    <button type="submit" class="btn btn-primary">🔄 Transfer</button>
                </div>
            </div>
        </form>
    </div>

    <!-- ── DISPOSE TAB ── -->
    <div class="tab-panel" id="tab-disposal">
        <div class="section-header">
            <div class="icon-wrap red">🗑️</div>
            <div>
                <h3>Dispose Asset</h3>
                <p>Permanently remove assets from inventory or department allocation</p>
            </div>
        </div>

        <form method="post" onsubmit="return confirm('Are you sure? This cannot be undone.')">
            <input type="hidden" name="action" value="dispose">
            <div class="form-grid">

                <div class="form-group">
                    <label>Location</label>
                    <select name="location" id="dispose_location" class="form-control" required onchange="updateDisposeItems()">
                        <option value="">— Select Location —</option>
                        <option value="Main Storage">🏪 Main Storage</option>
                        <?php foreach ($departments as $d): ?>
                            <option value="<?= htmlspecialchars($d) ?>"><?= htmlspecialchars($d) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Item</label>
                    <select name="item_id" id="dispose_item" class="form-control" required>
                        <option value="">— Select Location First —</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Quantity</label>
                    <input type="number" name="dispose_qty" class="form-control" min="1" placeholder="0" required>
                </div>

                <div class="form-group" style="display:flex;align-items:flex-end;">
                    <button type="submit" class="btn btn-danger">🗑️ Dispose</button>
                </div>
            </div>
        </form>
    </div>

    <!-- ── CURRENT ASSETS TAB ── -->
    <div class="tab-panel" id="tab-current">
        <div class="section-header">
            <div class="icon-wrap purple">📋</div>
            <div>
                <h3>Current Department Assets</h3>
                <p>All items currently allocated across departments</p>
            </div>
        </div>

        <div class="table-search-bar">
            <input type="text" class="search-input" id="assetSearch" placeholder="Search by department, item, or type…" oninput="filterTable('assetTable','assetSearch','deptFilter')">
            <select class="filter-select" id="deptFilter" onchange="filterTable('assetTable','assetSearch','deptFilter')">
                <option value="">All Departments</option>
                <?php foreach ($departments as $d): ?>
                    <option value="<?= htmlspecialchars($d) ?>"><?= htmlspecialchars($d) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="table-wrapper">
            <?php if (empty($dept_assets)): ?>
                <div class="empty-state">
                    <div class="empty-icon">📦</div>
                    <p>No assets allocated yet. Use the <strong>Allocate to Department</strong> tab to get started.</p>
                </div>
            <?php else: ?>
                <table class="data-table" id="assetTable">
                    <thead>
                        <tr>
                            <th>Department</th>
                            <th>Item Name</th>
                            <th>Type</th>
                            <th>Quantity</th>
                            <th>Unit</th>
                            <th>Unit Price</th>
                            <th>Total Value</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($dept_assets as $da): ?>
                            <tr data-dept="<?= htmlspecialchars($da['department']) ?>">
                                <td><span class="badge badge-dept"><?= htmlspecialchars($da['department']) ?></span></td>
                                <td><strong><?= htmlspecialchars($da['item_name']) ?></strong></td>
                                <td><span class="badge badge-type"><?= htmlspecialchars($da['item_type']) ?></span></td>
                                <td><span class="badge badge-qty"><?= $da['quantity'] ?></span></td>
                                <td><?= htmlspecialchars($da['unit_type']) ?></td>
                                <td>₱<?= number_format($da['price'], 2) ?></td>
                                <td><strong>₱<?= number_format($da['quantity'] * $da['price'], 2) ?></strong></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <!-- ── MAIN STORAGE TAB ── -->
    <div class="tab-panel" id="tab-mainstorage">
        <div class="section-header">
            <div class="icon-wrap purple">🏪</div>
            <div>
                <h3>Main Storage Inventory</h3>
                <p>All items currently in main storage available for allocation</p>
            </div>
        </div>

        <div class="table-search-bar">
            <input type="text" class="search-input" id="mainSearch" placeholder="Search items…" oninput="filterTable('mainTable','mainSearch', null)">
        </div>

        <div class="table-wrapper">
            <?php if (empty($main_inventory)): ?>
                <div class="empty-state">
                    <div class="empty-icon">🏪</div>
                    <p>Main storage is empty.</p>
                </div>
            <?php else: ?>
                <table class="data-table" id="mainTable">
                    <thead>
                        <tr>
                            <th>Item Name</th>
                            <th>Type</th>
                            <th>Stock Qty</th>
                            <th>Unit Price</th>
                            <th>Total Value</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($main_inventory as $mi): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($mi['item_name']) ?></strong></td>
                                <td><span class="badge badge-type"><?= htmlspecialchars($mi['item_type']) ?></span></td>
                                <td>
                                    <span class="badge <?= $mi['quantity'] <= 10 ? 'badge-warn' : 'badge-qty' ?>">
                                        <?= number_format($mi['quantity']) ?>
                                    </span>
                                </td>
                                <td>₱<?= number_format($mi['price'], 2) ?></td>
                                <td><strong>₱<?= number_format($mi['quantity'] * $mi['price'], 2) ?></strong></td>
                                <td>
                                    <?php if ($mi['quantity'] <= 0): ?>
                                        <span class="badge badge-out">Out of Stock</span>
                                    <?php elseif ($mi['quantity'] <= 10): ?>
                                        <span class="badge badge-warn">Low Stock</span>
                                    <?php else: ?>
                                        <span class="badge badge-qty">In Stock</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

</div><!-- /main-content -->

<script>
const deptData = <?= json_encode($dept_items_js) ?>;
const mainInv  = <?= json_encode($main_inv_js) ?>;

// ── Tab switching ──
function switchTab(name, btn) {
    document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    document.getElementById('tab-' + name).classList.add('active');
    btn.classList.add('active');
}

// ── Allocate: live item preview ──
function updateItemPreview() {
    const sel   = document.getElementById('alloc_item');
    const qtyIn = document.getElementById('alloc_qty');
    const prev  = document.getElementById('itemPreview');

    if (!sel.value) { prev.classList.remove('show'); return; }

    const opt   = sel.selectedOptions[0];
    const stock = parseInt(opt.dataset.qty);
    const price = parseFloat(opt.dataset.price) || 0;
    const qty   = parseInt(qtyIn.value) || 0;
    const rem   = stock - qty;

    document.getElementById('prev_type').textContent      = opt.dataset.type || '—';
    document.getElementById('prev_stock').textContent     = stock + ' ' + (opt.dataset.unit || '');
    document.getElementById('prev_remaining').textContent = rem >= 0 ? rem + ' remaining' : '⚠ Insufficient!';
    document.getElementById('prev_remaining').className   = 'pi-value ' + (rem < 0 ? 'low' : 'ok');
    document.getElementById('prev_price').textContent     = '₱' + price.toLocaleString('en-PH', {minimumFractionDigits:2});
    document.getElementById('prev_total').textContent     = qty > 0 ? '₱' + (qty * price).toLocaleString('en-PH', {minimumFractionDigits:2}) : '—';

    prev.classList.add('show');
}

// ── Transfer: load items for source dept ──
function updateTransferItems() {
    const dept = document.getElementById('src_dept').value;
    const sel  = document.getElementById('transfer_item');
    sel.innerHTML = '<option value="">— Select Item —</option>';

    if (dept && deptData[dept] && deptData[dept].length > 0) {
        deptData[dept].forEach(item => {
            const opt = document.createElement('option');
            opt.value = item.item_id;
            opt.textContent = item.name + ' (Qty: ' + item.qty + ')';
            sel.appendChild(opt);
        });
    } else {
        sel.innerHTML = '<option value="">No items in this department</option>';
    }
}

// ── Dispose: load items for selected location ──
function updateDisposeItems() {
    const loc = document.getElementById('dispose_location').value;
    const sel = document.getElementById('dispose_item');
    sel.innerHTML = '<option value="">— Select Item —</option>';

    let items = [];
    if (loc === 'Main Storage') {
        items = mainInv;
    } else if (loc && deptData[loc]) {
        items = deptData[loc];
    }

    if (items.length > 0) {
        items.forEach(item => {
            const opt = document.createElement('option');
            opt.value = item.item_id;
            opt.textContent = item.name + ' (Qty: ' + item.qty + ')';
            sel.appendChild(opt);
        });
    } else if (loc) {
        sel.innerHTML = '<option value="">No items in this location</option>';
    }
}

// ── Live table filter with optional dept filter ──
function filterTable(tableId, searchId, filterId) {
    const q    = document.getElementById(searchId).value.toLowerCase();
    const dept = filterId ? (document.getElementById(filterId)?.value.toLowerCase() || '') : '';

    document.querySelectorAll('#' + tableId + ' tbody tr').forEach(row => {
        const text     = row.textContent.toLowerCase();
        const rowDept  = (row.dataset.dept || '').toLowerCase();
        const textOk   = text.includes(q);
        const deptOk   = !dept || rowDept === dept;
        row.style.display = (textOk && deptOk) ? '' : 'none';
    });
}
</script>
</body>
</html>