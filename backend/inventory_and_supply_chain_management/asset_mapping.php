<?php
session_start();
include '../../SQL/config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$success_msg = '';
$error_msg   = '';

// Handle Item Assignment to Department
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['inventory_id'], $_POST['department'], $_POST['assign_qty'])) {
    $inventory_id = intval($_POST['inventory_id']);
    $department   = trim($_POST['department']);
    $assign_qty   = intval($_POST['assign_qty']);

    $stmt = $pdo->prepare("SELECT * FROM inventory WHERE id = ?");
    $stmt->execute([$inventory_id]);
    $item = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($item && $assign_qty > 0 && $assign_qty <= $item['quantity']) {
        $pdo->prepare("UPDATE inventory SET quantity = quantity - ? WHERE id = ?")->execute([$assign_qty, $inventory_id]);

        $stmt = $pdo->prepare("SELECT * FROM department_assets WHERE item_id = ? AND department = ?");
        $stmt->execute([$item['item_id'], $department]);
        $dept_item = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($dept_item) {
            $pdo->prepare("UPDATE department_assets SET quantity = quantity + ? WHERE id = ?")->execute([$assign_qty, $dept_item['id']]);
        } else {
            $pdo->prepare("INSERT INTO department_assets (item_id, department, quantity, assigned_at) VALUES (?, ?, ?, NOW())")->execute([$item['item_id'], $department, $assign_qty]);
        }
        $success_msg = "Successfully assigned {$assign_qty}x " . htmlspecialchars($item['item_name']) . " to " . htmlspecialchars($department) . ".";
    } else {
        $error_msg = "Insufficient stock or invalid quantity.";
    }
}

// Fetch departments from departments table
$departments = $pdo->query("SELECT department_name FROM departments ORDER BY department_id")->fetchAll(PDO::FETCH_COLUMN);

// Fetch Main Inventory (available items)
$main_inventory = $pdo->query("SELECT * FROM inventory WHERE quantity > 0 ORDER BY item_name")->fetchAll(PDO::FETCH_ASSOC);

// Fetch Department Assets
$dept_assets = $pdo->query("
    SELECT 
        da.department,
        i.item_name,
        i.item_type,
        SUM(da.quantity) AS total_quantity,
        i.unit_type,
        i.price
    FROM department_assets da
    JOIN inventory i ON da.item_id = i.item_id
    GROUP BY da.department, i.item_name, i.item_type, i.unit_type, i.price
    HAVING total_quantity > 0
    ORDER BY da.department, i.item_name
")->fetchAll(PDO::FETCH_ASSOC);

// Stats
$total_assigned  = array_sum(array_column($dept_assets, 'total_quantity'));
$dept_count      = count(array_unique(array_column($dept_assets, 'department')));
$total_value     = array_sum(array_map(fn($a) => $a['total_quantity'] * $a['price'], $dept_assets));
$main_items      = count($main_inventory);

// JS lookup: inventory_id -> available qty (for live stock preview)
$inv_js = [];
foreach ($main_inventory as $i) {
    $inv_js[$i['id']] = ['name' => $i['item_name'], 'qty' => $i['quantity'], 'type' => $i['item_type'] ?? ''];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Department Asset Mapping</title>
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
        .breadcrumb-row {
            display: flex; align-items: center; gap: 6px;
            font-size: .8rem; margin-bottom: 6px;
        }
        .breadcrumb-row span { color: var(--primary); font-weight: 700; }
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
        .alert-success { background: var(--success-light); color: var(--success); border: 1px solid rgba(39,174,96,.25); }
        .alert-danger  { background: var(--danger-light);  color: var(--danger);  border: 1px solid rgba(224,85,85,.25); }

        /* STATS */
        .stats-row {
            display: grid; grid-template-columns: repeat(4, 1fr);
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
            padding: 8px 8px 0; box-shadow: var(--shadow); overflow-x: auto;
        }
        .tab-btn {
            display: flex; align-items: center; gap: 7px;
            padding: 10px 20px; border: none; background: none;
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
            font-size: 1.1rem; flex-shrink: 0;
        }
        .icon-wrap.teal   { background: var(--primary-light); }
        .icon-wrap.purple { background: var(--purple-light); }
        .section-header h3 { font-size: 1.05rem; font-weight: 800; color: var(--text-dark); }
        .section-header p  { font-size: .82rem; color: var(--text); margin-top: 2px; }

        /* ASSIGN FORM */
        .assign-form-grid {
            display: grid;
            grid-template-columns: 2fr 2fr 1fr auto;
            gap: 16px; align-items: end;
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

        /* STOCK PREVIEW BOX */
        .stock-preview {
            display: none;
            background: var(--bg); border: 1px solid var(--border);
            border-radius: 10px; padding: 14px 18px;
            margin-top: 16px; gap: 28px; flex-wrap: wrap;
            animation: fadeIn .2s ease;
        }
        .stock-preview.show { display: flex; }
        .sp-label { font-size: .72rem; font-weight: 800; color: var(--text); text-transform: uppercase; letter-spacing: .04em; }
        .sp-value { font-size: 1rem; font-weight: 700; color: var(--text-dark); margin-top: 2px; }
        .sp-value.ok  { color: var(--success); }
        .sp-value.low { color: var(--danger); }

        /* BUTTONS */
        .btn {
            display: inline-flex; align-items: center; gap: 7px;
            padding: 10px 22px; border: none; border-radius: 8px;
            font-family: "Nunito", sans-serif; font-size: .9rem; font-weight: 700;
            cursor: pointer; white-space: nowrap;
            transition: background .2s, transform .15s; text-decoration: none;
        }
        .btn:hover  { transform: translateY(-1px); }
        .btn:active { transform: translateY(0); }
        .btn-success  { background: var(--success); color: #fff; box-shadow: 0 4px 14px rgba(39,174,96,.3); }
        .btn-success:hover  { background: #219150; }
        .btn-secondary { background: #e8eaed; color: var(--text-dark); }
        .btn-secondary:hover { background: #d8dade; }

        /* FILTER BAR */
        .filter-bar {
            display: flex; gap: 10px; align-items: center;
            margin-bottom: 18px; flex-wrap: wrap;
        }
        .filter-bar input,
        .filter-bar select {
            flex: 1; min-width: 160px;
            padding: 9px 14px;
            border: 1.5px solid var(--border); border-radius: 8px;
            font-family: "Nunito", sans-serif; font-size: .88rem;
            color: var(--text-dark); background: #fff; outline: none;
            transition: border-color .2s; appearance: none;
        }
        .filter-bar input:focus,
        .filter-bar select:focus { border-color: var(--primary); }
        .filter-bar input {
            background: #fff url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' fill='%236e768e' viewBox='0 0 16 16'%3E%3Cpath d='M11.742 10.344a6.5 6.5 0 1 0-1.397 1.398h-.001c.03.04.062.078.098.115l3.85 3.85a1 1 0 0 0 1.415-1.414l-3.85-3.85a1.007 1.007 0 0 0-.115-.099zm-5.242 1.156a5.5 5.5 0 1 1 0-11 5.5 5.5 0 0 1 0 11z'/%3E%3C/svg%3E") no-repeat 12px center;
            padding-left: 36px;
        }
        .filter-bar select {
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%236e768e' d='M6 8L1 3h10z'/%3E%3C/svg%3E");
            background-position: right 12px center; background-repeat: no-repeat; padding-right: 32px;
        }

        /* DEPARTMENT GROUPS */
        .dept-group { margin-bottom: 28px; }
        .dept-group-header {
            display: flex; align-items: center; gap: 10px;
            margin-bottom: 12px;
        }
        .dept-label {
            font-size: .88rem; font-weight: 800; color: var(--primary);
            background: var(--primary-light); padding: 5px 14px;
            border-radius: 20px; letter-spacing: .02em;
        }
        .dept-line { flex: 1; height: 1px; background: var(--border); }

        /* TABLE */
        .table-wrapper { overflow-x: auto; border-radius: 10px; border: 1px solid var(--border); }
        .data-table { width: 100%; border-collapse: collapse; font-size: .88rem; min-width: 500px; }
        .data-table thead th {
            background: var(--bg); color: var(--text);
            font-size: .72rem; font-weight: 800; text-transform: uppercase;
            letter-spacing: .05em; padding: 11px 16px;
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
        .badge-primary { background: var(--primary-light); color: var(--primary); }
        .badge-success { background: var(--success-light); color: var(--success); }
        .badge-purple  { background: var(--purple-light);  color: var(--purple); }
        .badge-muted   { background: #eee; color: var(--text); }

        .item-name { font-weight: 700; color: var(--text-dark); }

        /* EMPTY STATE */
        .empty-state { text-align: center; padding: 48px 20px; color: var(--text); }
        .empty-state .empty-icon { font-size: 2.5rem; margin-bottom: 12px; }

        /* GROUPED SUMMARY FOOTER */
        .dept-summary {
            display: flex; gap: 20px; flex-wrap: wrap;
            font-size: .8rem; color: var(--text);
            padding: 8px 4px 0;
        }
        .dept-summary strong { color: var(--text-dark); }

        /* RESPONSIVE */
        @media (max-width: 1100px) { .stats-row { grid-template-columns: repeat(2,1fr); } }
        @media (max-width: 900px)  { .assign-form-grid { grid-template-columns: 1fr 1fr; } }
        @media (max-width: 768px) {
            .main-content { margin-left: 0; padding: 20px 16px 48px; }
            .stats-row    { grid-template-columns: 1fr 1fr; }
            .assign-form-grid { grid-template-columns: 1fr; }
            .filter-bar   { flex-direction: column; }
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
        <div class="breadcrumb-row">Asset Tracking &rsaquo; <span>Department Asset Mapping</span></div>
        <h1>Department Asset Mapping</h1>
        <p>Assign inventory items to departments and view allocations by location</p>
    </div>

    <!-- Alerts -->
    <?php if ($success_msg): ?>
        <div class="alert alert-success">✅ <?= $success_msg ?></div>
    <?php endif; ?>
    <?php if ($error_msg): ?>
        <div class="alert alert-danger">⚠️ <?= $error_msg ?></div>
    <?php endif; ?>

    <!-- Stats -->
    <div class="stats-row">
        <div class="stat-card">
            <div class="stat-icon teal">🗂️</div>
            <div class="stat-info">
                <div class="value"><?= count($departments) ?></div>
                <div class="label">Total Departments</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon green">📦</div>
            <div class="stat-info">
                <div class="value"><?= number_format($total_assigned) ?></div>
                <div class="label">Total Units Assigned</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon amber">💰</div>
            <div class="stat-info">
                <div class="value">₱<?= number_format($total_value, 0) ?></div>
                <div class="label">Total Assigned Value</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon purple">🏪</div>
            <div class="stat-info">
                <div class="value"><?= $main_items ?></div>
                <div class="label">Main Storage Items</div>
            </div>
        </div>
    </div>

    <!-- Tabs -->
    <div class="tab-bar">
        <button class="tab-btn active" onclick="switchTab('mapping', this)">🗂️ Assign to Department</button>
        <button class="tab-btn" onclick="switchTab('assets', this)">
            📋 Department Assets
            <span class="tab-badge"><?= count($dept_assets) ?></span>
        </button>
    </div>

    <!-- ── ASSIGN TAB ── -->
    <div class="tab-panel active" id="tab-mapping">
        <div class="section-header">
            <div class="icon-wrap teal">🗂️</div>
            <div>
                <h3>Assign Item to Department</h3>
                <p>Select an item from main storage and allocate it to a department</p>
            </div>
        </div>

        <form method="post" id="assignForm">
            <div class="assign-form-grid">

                <div class="form-group">
                    <label>Select Item</label>
                    <select name="inventory_id" id="item_select" class="form-control" required onchange="updateStockPreview()">
                        <option value="">— Choose an item —</option>
                        <?php foreach ($main_inventory as $inv): ?>
                            <option value="<?= $inv['id'] ?>"
                                data-qty="<?= $inv['quantity'] ?>"
                                data-type="<?= htmlspecialchars($inv['item_type'] ?? '') ?>">
                                <?= htmlspecialchars($inv['item_name']) ?>
                                (<?= number_format($inv['quantity']) ?> available)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Destination Department</label>
                    <select name="department" class="form-control" required>
                        <option value="">— Choose department —</option>
                        <?php foreach ($departments as $dept): ?>
                            <option value="<?= htmlspecialchars($dept) ?>"><?= htmlspecialchars($dept) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Quantity</label>
                    <input type="number" name="assign_qty" id="assign_qty" class="form-control"
                           min="1" placeholder="0" required oninput="updateStockPreview()">
                </div>

                <div class="form-group" style="display:flex;align-items:flex-end;">
                    <button type="submit" class="btn btn-success">✅ Assign</button>
                </div>
            </div>

            <!-- Live stock preview -->
            <div class="stock-preview" id="stockPreview">
                <div><div class="sp-label">Item Type</div><div class="sp-value" id="sp_type">—</div></div>
                <div><div class="sp-label">Available Stock</div><div class="sp-value ok" id="sp_stock">—</div></div>
                <div><div class="sp-label">After Assignment</div><div class="sp-value" id="sp_remaining">—</div></div>
            </div>
        </form>

        <?php if (!empty($main_inventory)): ?>
        <div style="margin-top:32px;">
            <div class="section-header" style="margin-bottom:16px;">
                <div class="icon-wrap purple">🏪</div>
                <div>
                    <h3>Main Storage Overview</h3>
                    <p>Items currently available for assignment</p>
                </div>
            </div>
            <div class="filter-bar" style="margin-bottom:14px;">
                <input type="text" id="mainStorageSearch" placeholder="Search items…" oninput="filterMainTable()">
            </div>
            <div class="table-wrapper">
                <table class="data-table" id="mainStorageTable">
                    <thead>
                        <tr>
                            <th>Item Name</th>
                            <th>Type</th>
                            <th>Available Qty</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($main_inventory as $inv):
                            $q = (int)$inv['quantity'];
                            $sc = $q <= 10 ? 'badge-muted' : 'badge-success';
                            $sl = $q <= 10 ? 'Low Stock' : 'In Stock';
                        ?>
                        <tr>
                            <td><span class="item-name"><?= htmlspecialchars($inv['item_name']) ?></span></td>
                            <td><span class="badge badge-purple"><?= htmlspecialchars($inv['item_type'] ?? '—') ?></span></td>
                            <td><span class="badge badge-primary"><?= number_format($q) ?></span></td>
                            <td><span class="badge <?= $sc ?>"><?= $sl ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- ── DEPARTMENT ASSETS TAB ── -->
    <div class="tab-panel" id="tab-assets">
        <div class="section-header">
            <div class="icon-wrap purple">📋</div>
            <div>
                <h3>Department Assets</h3>
                <p>All items currently allocated, grouped by department</p>
            </div>
        </div>

        <div class="filter-bar">
            <input type="text" id="assetSearch" placeholder="Search by item name or type…" oninput="filterAssets()">
            <select id="deptFilter" onchange="filterAssets()">
                <option value="">All Departments</option>
                <?php foreach ($departments as $d): ?>
                    <option value="<?= htmlspecialchars($d) ?>"><?= htmlspecialchars($d) ?></option>
                <?php endforeach; ?>
            </select>
            <a href="asset_transfer.php" class="btn btn-success">🔄 Transfer / Dispose</a>
        </div>

        <?php if (empty($dept_assets)): ?>
            <div class="empty-state">
                <div class="empty-icon">📦</div>
                <p>No assets have been assigned yet. Use the <strong>Assign to Department</strong> tab to get started.</p>
            </div>
        <?php else:
            // Group by department
            $grouped = [];
            foreach ($dept_assets as $da) { $grouped[$da['department']][] = $da; }
        ?>
            <div id="assetGroups">
            <?php foreach ($grouped as $dept => $items):
                $deptTotal = array_sum(array_column($items, 'total_quantity'));
                $deptValue = array_sum(array_map(fn($i) => $i['total_quantity'] * $i['price'], $items));
            ?>
                <div class="dept-group" data-dept="<?= htmlspecialchars($dept) ?>">
                    <div class="dept-group-header">
                        <span class="dept-label">🏢 <?= htmlspecialchars($dept) ?></span>
                        <div class="dept-line"></div>
                    </div>
                    <div class="table-wrapper">
                        <table class="data-table dept-table">
                            <thead>
                                <tr>
                                    <th>Item Name</th>
                                    <th>Type</th>
                                    <th>Quantity</th>
                                    <th>Unit</th>
                                    <th>Unit Price</th>
                                    <th>Total Value</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($items as $da): ?>
                                    <tr data-item="<?= strtolower(htmlspecialchars($da['item_name'])) ?>">
                                        <td><span class="item-name"><?= htmlspecialchars($da['item_name']) ?></span></td>
                                        <td><span class="badge badge-purple"><?= htmlspecialchars($da['item_type'] ?? '—') ?></span></td>
                                        <td><span class="badge badge-primary"><?= number_format($da['total_quantity']) ?></span></td>
                                        <td><span class="badge badge-muted"><?= htmlspecialchars($da['unit_type']) ?></span></td>
                                        <td>₱<?= number_format($da['price'], 2) ?></td>
                                        <td><strong>₱<?= number_format($da['total_quantity'] * $da['price'], 2) ?></strong></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="dept-summary">
                        <span>Items: <strong><?= count($items) ?></strong></span>
                        <span>Total Units: <strong><?= number_format($deptTotal) ?></strong></span>
                        <span>Total Value: <strong>₱<?= number_format($deptValue, 2) ?></strong></span>
                    </div>
                </div>
            <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

</div><!-- /main-content -->

<script>
const invData = <?= json_encode($inv_js) ?>;

// ── Tab switching ──
function switchTab(name, btn) {
    document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    document.getElementById('tab-' + name).classList.add('active');
    btn.classList.add('active');
}

// ── Live stock preview ──
function updateStockPreview() {
    const sel  = document.getElementById('item_select');
    const qtyI = document.getElementById('assign_qty');
    const prev = document.getElementById('stockPreview');

    if (!sel.value) { prev.classList.remove('show'); return; }

    const opt   = sel.selectedOptions[0];
    const stock = parseInt(opt.dataset.qty) || 0;
    const qty   = parseInt(qtyI.value) || 0;
    const rem   = stock - qty;

    document.getElementById('sp_type').textContent  = opt.dataset.type || '—';
    document.getElementById('sp_stock').textContent = stock + ' units';

    const remEl = document.getElementById('sp_remaining');
    remEl.textContent = rem >= 0 ? rem + ' remaining' : '⚠ Exceeds stock!';
    remEl.className = 'sp-value ' + (rem < 0 ? 'low' : 'ok');

    prev.classList.add('show');
}

// ── Filter main storage table ──
function filterMainTable() {
    const q = document.getElementById('mainStorageSearch').value.toLowerCase();
    document.querySelectorAll('#mainStorageTable tbody tr').forEach(row => {
        row.style.display = row.textContent.toLowerCase().includes(q) ? '' : 'none';
    });
}

// ── Filter department assets by search + dept dropdown ──
function filterAssets() {
    const q    = (document.getElementById('assetSearch')?.value || '').toLowerCase();
    const dept = (document.getElementById('deptFilter')?.value || '').toLowerCase();

    document.querySelectorAll('.dept-group').forEach(group => {
        const groupDept = (group.dataset.dept || '').toLowerCase();
        const deptMatch = !dept || groupDept === dept;

        let anyVisible = false;
        group.querySelectorAll('tbody tr').forEach(row => {
            const textMatch = !q || row.textContent.toLowerCase().includes(q);
            row.style.display = (textMatch && deptMatch) ? '' : 'none';
            if (textMatch && deptMatch) anyVisible = true;
        });

        group.style.display = (deptMatch && anyVisible) ? '' : 'none';
    });
}
</script>

</body>
</html>