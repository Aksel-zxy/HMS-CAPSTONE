<?php
session_start();
include '../../SQL/config.php';

// INVENTORY filters
$invSearch   = $_GET['inv_search']   ?? '';
$invCategory = $_GET['inv_category'] ?? '';

// HISTORY filters
$histSearch   = $_GET['hist_search']   ?? '';
$histCategory = $_GET['hist_category'] ?? '';

$categories = [
    "IT and supporting tech",
    "Medications and pharmacy supplies",
    "Consumables and disposables",
    "Therapeutic equipment",
    "Diagnostic Equipment"
];

// Inventory query
$invQuery = "
    SELECT 
        LOWER(item_name) AS item_name_lower,
        MAX(id) AS id,
        item_name,
        item_type,
        sub_type,
        category,
        pcs_per_box,
        SUM(total_qty) AS total_quantity
    FROM inventory
    WHERE 1
";
$invParams = [];
if (!empty($invSearch)) {
    $invQuery .= " AND (item_name LIKE :search OR item_type LIKE :search OR sub_type LIKE :search)";
    $invParams[':search'] = "%$invSearch%";
}
if (!empty($invCategory)) {
    $invQuery .= " AND category = :category";
    $invParams[':category'] = $invCategory;
}
$invQuery .= " GROUP BY LOWER(item_name) ORDER BY item_name ASC";
$inventoryStmt = $pdo->prepare($invQuery);
$inventoryStmt->execute($invParams);
$inventory = $inventoryStmt->fetchAll(PDO::FETCH_ASSOC);

// Stock adjustments query
$histQuery = "
    SELECT sa.id, i.item_name, i.category, sa.old_quantity, sa.new_quantity, sa.reason, sa.adjusted_at
    FROM stock_adjustments sa
    JOIN inventory i ON sa.inventory_id = i.id
    WHERE 1
";
$histParams = [];
if (!empty($histSearch)) {
    $histQuery .= " AND (i.item_name LIKE :search OR i.category LIKE :search)";
    $histParams[':search'] = "%$histSearch%";
}
if (!empty($histCategory)) {
    $histQuery .= " AND i.category = :category";
    $histParams[':category'] = $histCategory;
}
$histQuery .= " ORDER BY sa.adjusted_at DESC";
$adjStmt = $pdo->prepare($histQuery);
$adjStmt->execute($histParams);
$adjustments = $adjStmt->fetchAll(PDO::FETCH_ASSOC);

// Summary stats
$totalItems    = count($inventory);
$totalQty      = array_sum(array_column($inventory, 'total_quantity'));
$lowStockCount = count(array_filter($inventory, fn($i) => (int)$i['total_quantity'] <= 10));
$adjCount      = count($adjustments);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory & Stock Tracking</title>
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

        /* SIDEBAR */
        .sidebar-area {
            position: fixed; left: 0; top: 0;
            width: var(--sidebar-w); height: 100vh; z-index: 100;
        }

        /* MAIN CONTENT */
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

        /* STAT CARDS */
        .stats-row {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
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
        .tab-badge.red { background: var(--danger); }

        /* TAB PANEL */
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
            margin-bottom: 22px; padding-bottom: 16px;
            border-bottom: 1px solid var(--border);
        }
        .icon-wrap {
            width: 40px; height: 40px; border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.1rem; flex-shrink: 0;
        }
        .icon-wrap.teal   { background: var(--primary-light); }
        .icon-wrap.purple { background: var(--purple-light);  }
        .section-header h3 { font-size: 1.05rem; font-weight: 800; color: var(--text-dark); }
        .section-header p  { font-size: .82rem; color: var(--text); margin-top: 2px; }

        /* FILTER BAR */
        .filter-bar {
            display: flex; gap: 10px; align-items: center;
            margin-bottom: 20px; flex-wrap: wrap;
        }
        .filter-bar input,
        .filter-bar select {
            flex: 1; min-width: 160px;
            padding: 9px 14px;
            border: 1.5px solid var(--border); border-radius: 8px;
            font-family: "Nunito", sans-serif; font-size: .88rem;
            color: var(--text-dark); background: #fff; outline: none;
            transition: border-color .2s;
            appearance: none;
        }
        .filter-bar input:focus,
        .filter-bar select:focus { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(0,172,193,.1); }
        .filter-bar input {
            background: #fff url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' fill='%236e768e' viewBox='0 0 16 16'%3E%3Cpath d='M11.742 10.344a6.5 6.5 0 1 0-1.397 1.398h-.001c.03.04.062.078.098.115l3.85 3.85a1 1 0 0 0 1.415-1.414l-3.85-3.85a1.007 1.007 0 0 0-.115-.099zm-5.242 1.156a5.5 5.5 0 1 1 0-11 5.5 5.5 0 0 1 0 11z'/%3E%3C/svg%3E") no-repeat 12px center;
            padding-left: 36px;
        }
        .filter-bar select {
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%236e768e' d='M6 8L1 3h10z'/%3E%3C/svg%3E");
            background-repeat: no-repeat; background-position: right 12px center; padding-right: 32px;
        }

        /* BUTTONS */
        .btn {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 9px 18px; border: none; border-radius: 8px;
            font-family: "Nunito", sans-serif; font-size: .88rem; font-weight: 700;
            cursor: pointer; white-space: nowrap;
            transition: background .2s, transform .15s;
            text-decoration: none;
        }
        .btn:hover  { transform: translateY(-1px); }
        .btn:active { transform: translateY(0); }
        .btn-primary  { background: var(--primary); color: #fff; }
        .btn-primary:hover  { background: #009ab0; }
        .btn-secondary { background: #e8eaed; color: var(--text-dark); }
        .btn-secondary:hover { background: #d8dade; }
        .btn-warning  { background: var(--warning); color: #fff; font-size: .82rem; padding: 6px 14px; }
        .btn-warning:hover { background: #d68910; }

        /* TABLE */
        .table-wrapper { overflow-x: auto; border-radius: 10px; border: 1px solid var(--border); }
        .data-table { width: 100%; border-collapse: collapse; font-size: .88rem; min-width: 600px; }
        .data-table thead th {
            background: var(--bg); color: var(--text);
            font-size: .72rem; font-weight: 800; text-transform: uppercase;
            letter-spacing: .05em; padding: 12px 16px;
            border-bottom: 2px solid var(--border); white-space: nowrap;
        }
        .data-table tbody tr { border-bottom: 1px solid var(--border); transition: background .15s; }
        .data-table tbody tr:hover { background: var(--primary-light); }
        .data-table tbody tr:last-child { border-bottom: none; }
        .data-table td { padding: 12px 16px; color: var(--text-dark); vertical-align: middle; }

        /* BADGES */
        .badge {
            display: inline-block; padding: 3px 10px;
            border-radius: 20px; font-size: .75rem; font-weight: 700;
        }
        .badge-primary { background: var(--primary-light); color: var(--primary); }
        .badge-info    { background: rgba(41,128,185,.1);  color: #2980b9; }
        .badge-success { background: var(--success-light); color: var(--success); }
        .badge-warning { background: var(--warning-light); color: var(--warning); }
        .badge-danger  { background: var(--danger-light);  color: var(--danger);  }
        .badge-muted   { background: #eee;                 color: var(--text);    }
        .badge-purple  { background: var(--purple-light);  color: var(--purple);  }

        /* ITEM NAME */
        .item-name { font-weight: 700; color: var(--text-dark); }
        .item-sub  { font-size: .78rem; color: var(--text); margin-top: 2px; }

        /* EMPTY STATE */
        .empty-state { text-align: center; padding: 48px 20px; color: var(--text); }
        .empty-state .empty-icon { font-size: 2.5rem; margin-bottom: 12px; }

        /* MODAL */
        .modal-overlay {
            display: none; position: fixed; inset: 0;
            background: rgba(30,40,60,.45); z-index: 500;
            align-items: center; justify-content: center;
            animation: fadeIn .2s ease;
        }
        .modal-overlay.open { display: flex; }
        .modal-box {
            background: var(--card); border-radius: var(--radius);
            box-shadow: var(--shadow-lg);
            width: 100%; max-width: 480px;
            padding: 28px; position: relative;
            animation: slideUp .25s ease;
            margin: 16px;
        }
        @keyframes slideUp { from{opacity:0;transform:translateY(20px);} to{opacity:1;transform:translateY(0);} }
        .modal-close {
            position: absolute; top: 16px; right: 16px;
            background: none; border: none; font-size: 1.2rem;
            cursor: pointer; color: var(--text); padding: 4px 8px;
            border-radius: 6px; transition: background .2s;
        }
        .modal-close:hover { background: var(--bg); }
        .modal-title { font-size: 1.05rem; font-weight: 800; color: var(--text-dark); margin-bottom: 4px; }
        .modal-subtitle { font-size: .82rem; color: var(--text); margin-bottom: 20px; }

        .form-group { margin-bottom: 16px; }
        .form-group label {
            display: block; font-size: .77rem; font-weight: 800;
            color: var(--text-dark); margin-bottom: 6px;
            text-transform: uppercase; letter-spacing: .04em;
        }
        .form-control {
            width: 100%; padding: 10px 14px;
            border: 1.5px solid var(--border); border-radius: 8px;
            font-family: "Nunito", sans-serif; font-size: .9rem;
            color: var(--text-dark); background: #fff; outline: none;
            transition: border-color .2s;
        }
        .form-control:focus { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(0,172,193,.12); }
        .form-control:disabled { background: var(--bg); color: var(--text); }
        textarea.form-control { resize: vertical; min-height: 80px; }

        .modal-footer {
            display: flex; gap: 10px; justify-content: flex-end;
            margin-top: 20px; padding-top: 16px;
            border-top: 1px solid var(--border);
        }

        /* QTY change indicator */
        .qty-change {
            display: flex; align-items: center; gap: 8px;
            background: var(--bg); border-radius: 8px;
            padding: 10px 14px; margin: 12px 0;
            font-size: .88rem;
        }
        .qty-change .old { color: var(--text); text-decoration: line-through; }
        .qty-change .arrow { color: var(--text); }
        .qty-change .new  { color: var(--primary); font-weight: 700; }

        /* RESPONSIVE */
        @media (max-width: 1100px) { .stats-row { grid-template-columns: repeat(2, 1fr); } }
        @media (max-width: 768px) {
            .main-content { margin-left: 0; padding: 20px 16px 48px; }
            .stats-row    { grid-template-columns: 1fr 1fr; }
            .filter-bar   { flex-direction: column; }
            .filter-bar input, .filter-bar select { min-width: 100%; }
        }
        @media (max-width: 480px) {
            .stats-row { grid-template-columns: 1fr; }
            .page-header h1 { font-size: 1.3rem; }
        }
    </style>
</head>
<body>

<div class="sidebar-area">
    <?php include 'inventory_sidebar.php'; ?>
</div>

<div class="main-content">

    <!-- Page Header -->
    <div class="page-header">
        <div class="breadcrumb-row">Inventory & Supply Chain &rsaquo; <span>Stock Tracking</span></div>
        <h1>Inventory & Stock Tracking</h1>
        <p>Monitor stock levels, adjust quantities, and review adjustment history</p>
    </div>

    <!-- Stats -->
    <div class="stats-row">
        <div class="stat-card">
            <div class="stat-icon teal">📦</div>
            <div class="stat-info">
                <div class="value"><?= number_format($totalItems) ?></div>
                <div class="label">Total Item Types</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon green">🔢</div>
            <div class="stat-info">
                <div class="value"><?= number_format($totalQty) ?></div>
                <div class="label">Total Pieces in Stock</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon amber">⚠️</div>
            <div class="stat-info">
                <div class="value"><?= $lowStockCount ?></div>
                <div class="label">Low Stock Items (≤10)</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon purple">📋</div>
            <div class="stat-info">
                <div class="value"><?= number_format($adjCount) ?></div>
                <div class="label">Adjustment Records</div>
            </div>
        </div>
    </div>

    <!-- Tab Bar -->
    <div class="tab-bar">
        <button class="tab-btn active" onclick="switchTab('inventory', this)">
            📦 Inventory
            <span class="tab-badge"><?= count($inventory) ?></span>
        </button>
        <button class="tab-btn" onclick="switchTab('history', this)">
            📋 Stock Adjustment History
            <span class="tab-badge"><?= count($adjustments) ?></span>
        </button>
    </div>

    <!-- ── INVENTORY TAB ── -->
    <div class="tab-panel active" id="tab-inventory">

        <div class="section-header">
            <div class="icon-wrap teal">📦</div>
            <div>
                <h3>Current Inventory</h3>
                <p>All items grouped by name with total quantities</p>
            </div>
        </div>

        <!-- Filters -->
        <form method="get" id="invFilterForm">
            <input type="hidden" name="hist_search"   value="<?= htmlspecialchars($histSearch) ?>">
            <input type="hidden" name="hist_category" value="<?= htmlspecialchars($histCategory) ?>">
            <div class="filter-bar">
                <input type="text" name="inv_search" placeholder="Search by item, type, or sub-type…"
                       value="<?= htmlspecialchars($invSearch) ?>">
                <select name="inv_category">
                    <option value="">All Categories</option>
                    <?php foreach ($categories as $c): ?>
                        <option value="<?= htmlspecialchars($c) ?>" <?= $invCategory === $c ? 'selected' : '' ?>>
                            <?= htmlspecialchars($c) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="btn btn-primary">🔍 Filter</button>
                <a href="inventory_management.php" class="btn btn-secondary">✕ Reset</a>
            </div>
        </form>

        <!-- Inventory Table -->
        <div class="table-wrapper">
            <?php if (empty($inventory)): ?>
                <div class="empty-state">
                    <div class="empty-icon">📭</div>
                    <p>No inventory items found. Try adjusting your filters.</p>
                </div>
            <?php else: ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Item Name</th>
                            <th>Type / Sub-Type</th>
                            <th>Total Qty (pcs)</th>
                            <th>Total Boxes</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($inventory as $idx => $item):
                            $totalBoxes = $item['pcs_per_box'] > 0 ? floor($item['total_quantity'] / $item['pcs_per_box']) : 0;
                            $qty = (int)$item['total_quantity'];
                            $statusClass = $qty <= 0 ? 'badge-danger' : ($qty <= 10 ? 'badge-warning' : 'badge-success');
                            $statusLabel = $qty <= 0 ? 'Out of Stock' : ($qty <= 10 ? 'Low Stock' : 'In Stock');
                        ?>
                            <tr>
                                <td style="color:var(--text);font-size:.82rem;"><?= $idx + 1 ?></td>
                                <td>
                                    <div class="item-name"><?= htmlspecialchars($item['item_name']) ?></div>
                                    <?php if ($item['category']): ?>
                                        <div class="item-sub"><?= htmlspecialchars($item['category']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($item['item_type']): ?>
                                        <span class="badge badge-purple"><?= htmlspecialchars($item['item_type']) ?></span>
                                    <?php endif; ?>
                                    <?php if ($item['sub_type']): ?>
                                        <div style="margin-top:4px;">
                                            <span class="badge badge-muted"><?= htmlspecialchars($item['sub_type']) ?></span>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td><span class="badge badge-primary"><?= number_format($qty) ?></span></td>
                                <td><span class="badge badge-info"><?= number_format($totalBoxes) ?></span></td>
                                <td><span class="badge <?= $statusClass ?>"><?= $statusLabel ?></span></td>
                                <td>
                                    <button class="btn btn-warning"
                                        onclick="openAdjustModal(<?= $item['id'] ?>, '<?= addslashes(htmlspecialchars($item['item_name'])) ?>', <?= $qty ?>)">
                                        ✏️ Adjust
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <!-- ── HISTORY TAB ── -->
    <div class="tab-panel" id="tab-history">

        <div class="section-header">
            <div class="icon-wrap purple">📋</div>
            <div>
                <h3>Stock Adjustment History</h3>
                <p>Full audit trail of all stock quantity changes</p>
            </div>
        </div>

        <!-- Filters -->
        <form method="get" id="histFilterForm">
            <input type="hidden" name="inv_search"   value="<?= htmlspecialchars($invSearch) ?>">
            <input type="hidden" name="inv_category" value="<?= htmlspecialchars($invCategory) ?>">
            <div class="filter-bar">
                <input type="text" name="hist_search" placeholder="Search by item or category…"
                       value="<?= htmlspecialchars($histSearch) ?>">
                <select name="hist_category">
                    <option value="">All Categories</option>
                    <?php foreach ($categories as $c): ?>
                        <option value="<?= htmlspecialchars($c) ?>" <?= $histCategory === $c ? 'selected' : '' ?>>
                            <?= htmlspecialchars($c) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="btn btn-primary">🔍 Filter</button>
                <a href="inventory_management.php" class="btn btn-secondary">✕ Reset</a>
            </div>
        </form>

        <!-- History Table -->
        <div class="table-wrapper">
            <?php if (empty($adjustments)): ?>
                <div class="empty-state">
                    <div class="empty-icon">📭</div>
                    <p>No adjustment records found.</p>
                </div>
            <?php else: ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Item Name</th>
                            <th>Category</th>
                            <th>Old Qty</th>
                            <th>New Qty</th>
                            <th>Change</th>
                            <th>Reason</th>
                            <th>Adjusted At</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($adjustments as $idx => $adj):
                            $diff = (int)$adj['new_quantity'] - (int)$adj['old_quantity'];
                            $diffClass = $diff > 0 ? 'badge-success' : ($diff < 0 ? 'badge-danger' : 'badge-muted');
                            $diffLabel = ($diff > 0 ? '+' : '') . $diff;
                        ?>
                            <tr>
                                <td style="color:var(--text);font-size:.82rem;"><?= $idx + 1 ?></td>
                                <td><span class="item-name"><?= htmlspecialchars($adj['item_name']) ?></span></td>
                                <td><span class="badge badge-purple"><?= htmlspecialchars($adj['category']) ?></span></td>
                                <td><span class="badge badge-muted"><?= number_format((int)$adj['old_quantity']) ?></span></td>
                                <td><span class="badge badge-primary"><?= number_format((int)$adj['new_quantity']) ?></span></td>
                                <td><span class="badge <?= $diffClass ?>"><?= $diffLabel ?></span></td>
                                <td style="max-width:200px;font-size:.85rem;"><?= htmlspecialchars($adj['reason']) ?></td>
                                <td style="font-size:.82rem;white-space:nowrap;color:var(--text);">
                                    <?= date('M d, Y g:i A', strtotime($adj['adjusted_at'])) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

</div><!-- /main-content -->

<!-- ── ADJUST MODAL ── -->
<div class="modal-overlay" id="adjustModal">
    <div class="modal-box">
        <button class="modal-close" onclick="closeAdjustModal()">✕</button>
        <div class="modal-title" id="modalItemName">Adjust Stock</div>
        <div class="modal-subtitle">Update the quantity for this inventory item</div>

        <form method="post" action="process_adjustment.php">
            <input type="hidden" name="inventory_id"  id="modal_inventory_id">
            <input type="hidden" name="old_quantity"  id="modal_old_quantity">

            <div class="form-group">
                <label>Current Quantity (pcs)</label>
                <input type="text" class="form-control" id="modal_current_display" disabled>
            </div>

            <div class="qty-change" id="qtyPreview" style="display:none;">
                <span class="old" id="prev_old">—</span>
                <span class="arrow">→</span>
                <span class="new" id="prev_new">—</span>
                <span id="prev_diff" style="font-size:.78rem;"></span>
            </div>

            <div class="form-group">
                <label>New Quantity (pcs)</label>
                <input type="number" name="new_quantity" id="modal_new_qty"
                       class="form-control" required min="0"
                       placeholder="Enter new quantity"
                       oninput="previewQtyChange()">
            </div>

            <div class="form-group">
                <label>Reason for Adjustment</label>
                <textarea name="reason" class="form-control" required
                          placeholder="e.g. Received new shipment, damaged stock, audit correction…"></textarea>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeAdjustModal()">Cancel</button>
                <button type="submit" class="btn btn-primary">💾 Save Adjustment</button>
            </div>
        </form>
    </div>
</div>

<div class="main-chatbox">
    <?php include 'chatbox.php'; ?>
</div>

<script>
    // ── Tab switching ──
    function switchTab(name, btn) {
        document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
        document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
        document.getElementById('tab-' + name).classList.add('active');
        btn.classList.add('active');
    }

    // Auto-open history tab if hist filters are active
    <?php if (!empty($histSearch) || !empty($histCategory)): ?>
    document.addEventListener('DOMContentLoaded', () => {
        document.querySelectorAll('.tab-btn')[1].click();
    });
    <?php endif; ?>

    // ── Adjust Modal ──
    let currentQty = 0;

    function openAdjustModal(id, name, qty) {
        currentQty = qty;
        document.getElementById('modal_inventory_id').value    = id;
        document.getElementById('modal_old_quantity').value    = qty;
        document.getElementById('modal_current_display').value = qty;
        document.getElementById('modal_new_qty').value         = '';
        document.getElementById('modalItemName').textContent   = '✏️ Adjust: ' + name;
        document.getElementById('qtyPreview').style.display    = 'none';
        document.getElementById('adjustModal').classList.add('open');
        setTimeout(() => document.getElementById('modal_new_qty').focus(), 100);
    }

    function closeAdjustModal() {
        document.getElementById('adjustModal').classList.remove('open');
    }

    // Close on backdrop click
    document.getElementById('adjustModal').addEventListener('click', function(e) {
        if (e.target === this) closeAdjustModal();
    });

    // Live qty preview
    function previewQtyChange() {
        const newVal = parseInt(document.getElementById('modal_new_qty').value);
        const preview = document.getElementById('qtyPreview');

        if (isNaN(newVal)) { preview.style.display = 'none'; return; }

        const diff = newVal - currentQty;
        const sign = diff > 0 ? '+' : '';
        const color = diff > 0 ? 'var(--success)' : (diff < 0 ? 'var(--danger)' : 'var(--text)');

        document.getElementById('prev_old').textContent  = currentQty + ' pcs';
        document.getElementById('prev_new').textContent  = newVal + ' pcs';
        document.getElementById('prev_diff').textContent = '(' + sign + diff + ')';
        document.getElementById('prev_diff').style.color = color;
        preview.style.display = 'flex';
    }

    // Close modal on Escape
    document.addEventListener('keydown', e => {
        if (e.key === 'Escape') closeAdjustModal();
    });
</script>
</body>
</html>