<?php
session_start();
include '../../SQL/config.php';

if (!isset($_SESSION['user_id'])) die("Login required.");

// --- Fetch Inventory Totals ---
$inventory_stmt = $pdo->query("
    SELECT 
        COUNT(*) AS total_items,
        COALESCE(SUM(quantity),0) AS total_quantity,
        COALESCE(SUM(quantity * price),0) AS total_value
    FROM inventory
");
$inventory_data = $inventory_stmt->fetch(PDO::FETCH_ASSOC);

// --- Low stock items (quantity <= 10) ---
$lowStmt = $pdo->query("SELECT COUNT(*) FROM inventory WHERE quantity <= 10 AND quantity > 0");
$low_stock_count = $lowStmt->fetchColumn();

// --- Out of stock ---
$outStmt = $pdo->query("SELECT COUNT(*) FROM inventory WHERE quantity = 0");
$out_of_stock = $outStmt->fetchColumn();

// --- Fetch by Item Type ---
$item_type_stmt = $pdo->query("
    SELECT 
        COALESCE(item_type,'Uncategorized') AS item_type,
        COUNT(*) AS item_count,
        COALESCE(SUM(quantity),0) AS total_quantity,
        COALESCE(SUM(quantity * price),0) AS total_value
    FROM inventory
    GROUP BY item_type
    ORDER BY total_value DESC
");
$item_type_data = $item_type_stmt->fetchAll(PDO::FETCH_ASSOC);

// --- Recent inventory additions ---
$recentStmt = $pdo->query("
    SELECT item_name, item_type, quantity, price, received_at
    FROM inventory
    ORDER BY received_at DESC
    LIMIT 6
");
$recent_items = $recentStmt->fetchAll(PDO::FETCH_ASSOC);

// Chart data
$item_types       = [];
$total_values     = [];
$total_quantities = [];
foreach ($item_type_data as $i) {
    $item_types[]       = $i['item_type'];
    $total_values[]     = (float)$i['total_value'];
    $total_quantities[] = (float)$i['total_quantity'];
}
$item_types_json  = json_encode($item_types);
$values_json      = json_encode($total_values);
$quantities_json  = json_encode($total_quantities);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Inventory Dashboard — Hospital Assets</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Sora:wght@300;400;500;600;700;800&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<style>
/* ══════════════════════════════════════
   DESIGN TOKENS — Matches system
══════════════════════════════════════*/
:root {
    --bg:           #f1f3f8;
    --surface:      #ffffff;
    --surface-2:    #f8f9fc;
    --surface-3:    #f0f2f7;
    --border:       #e3e7f0;
    --border-2:     #cdd2e0;
    --text-1:       #0d1117;
    --text-2:       #4b5675;
    --text-3:       #8b93ad;

    --blue:         #1b56f5;
    --blue-light:   #edf1ff;
    --blue-dark:    #1040d8;
    --blue-glow:    rgba(27,86,245,.15);

    --green:        #0d9f6b;
    --green-light:  #e6faf3;
    --green-border: #7de8c5;

    --amber:        #c77b0a;
    --amber-light:  #fef8e7;
    --amber-border: #fde58a;

    --red:          #e12b2b;
    --red-light:    #fef0f0;
    --red-border:   #f8b4b4;

    --teal:         #0580a4;
    --teal-light:   #e0f4fb;

    --indigo:       #5046e5;
    --indigo-light: #eeedfd;

    --orange:       #d4520c;
    --orange-light: #fff2ea;

    --purple:       #7c3aed;
    --purple-light: #f5f3ff;

    --sidebar-w:    260px;
    --radius-xs:    4px;
    --radius-sm:    8px;
    --radius:       12px;
    --radius-lg:    18px;

    --shadow-xs:    0 1px 2px rgba(0,0,0,.05);
    --shadow-sm:    0 2px 8px rgba(0,0,0,.06), 0 1px 3px rgba(0,0,0,.04);
    --shadow:       0 4px 20px rgba(0,0,0,.07), 0 2px 8px rgba(0,0,0,.04);
    --shadow-lg:    0 16px 48px rgba(0,0,0,.12), 0 6px 16px rgba(0,0,0,.06);
    --shadow-blue:  0 8px 24px rgba(27,86,245,.2);
}

*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

body {
    font-family: 'Sora', sans-serif;
    background: var(--bg);
    color: var(--text-1);
    font-size: 13.5px;
    line-height: 1.65;
    -webkit-font-smoothing: antialiased;
}

.main-sidebar {
    position: fixed; left: 0; top: 0; bottom: 0;
    width: var(--sidebar-w); z-index: 1000; overflow-y: auto;
}
.main-content {
    margin-left: var(--sidebar-w);
    min-height: 100vh;
    padding: 32px 36px 64px;
}

/* ══════════════════════════════════════
   PAGE HEADER
══════════════════════════════════════*/
.page-header {
    display: flex; align-items: flex-end;
    justify-content: space-between; flex-wrap: wrap;
    gap: 16px; margin-bottom: 32px;
}
.ph-left { display: flex; align-items: center; gap: 16px; }
.ph-icon-wrap {
    width: 52px; height: 52px;
    background: linear-gradient(135deg, var(--blue) 0%, #5b87ff 100%);
    border-radius: var(--radius); box-shadow: var(--shadow-blue);
    display: flex; align-items: center; justify-content: center; flex-shrink: 0;
}
.ph-icon-wrap i { font-size: 22px; color: #fff; }
.ph-eyebrow {
    font-size: 10.5px; font-weight: 700; letter-spacing: .12em;
    text-transform: uppercase; color: var(--blue); margin-bottom: 3px;
}
.ph-title { font-size: 22px; font-weight: 800; color: var(--text-1); letter-spacing: -.4px; }
.ph-sub   { font-size: 12.5px; color: var(--text-3); margin-top: 2px; }
.ph-date  { text-align: right; font-size: 12px; color: var(--text-3); line-height: 1.4; }
.ph-date strong { display: block; font-size: 13.5px; font-weight: 700; color: var(--text-2); }

/* ══════════════════════════════════════
   KPI CARDS
══════════════════════════════════════*/
.kpi-grid {
    display: grid;
    grid-template-columns: repeat(5, 1fr);
    gap: 14px;
    margin-bottom: 26px;
}
.kpi-card {
    background: var(--surface);
    border: 1.5px solid var(--border);
    border-radius: var(--radius);
    padding: 18px 20px;
    position: relative; overflow: hidden;
    box-shadow: var(--shadow-xs);
    transition: box-shadow .2s, transform .2s;
}
.kpi-card:hover { box-shadow: var(--shadow); transform: translateY(-2px); }
.kpi-card::before {
    content: '';
    position: absolute; top: 0; left: 0; right: 0; height: 3px;
    border-radius: var(--radius) var(--radius) 0 0;
}
.kc-blue::before   { background: var(--blue); }
.kc-green::before  { background: var(--green); }
.kc-amber::before  { background: var(--amber); }
.kc-red::before    { background: var(--red); }
.kc-indigo::before { background: var(--indigo); }

.kpi-icon-row {
    display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px;
}
.kpi-icon {
    width: 38px; height: 38px; border-radius: var(--radius-sm);
    display: flex; align-items: center; justify-content: center; font-size: 17px;
}
.kc-blue   .kpi-icon { background: var(--blue-light);   color: var(--blue);   }
.kc-green  .kpi-icon { background: var(--green-light);  color: var(--green);  }
.kc-amber  .kpi-icon { background: var(--amber-light);  color: var(--amber);  }
.kc-red    .kpi-icon { background: var(--red-light);    color: var(--red);    }
.kc-indigo .kpi-icon { background: var(--indigo-light); color: var(--indigo); }

.kpi-trend {
    font-size: 11px; font-weight: 600; padding: 2px 7px; border-radius: 20px;
}
.kpi-number {
    font-family: 'DM Mono', monospace;
    font-size: 26px; font-weight: 700; line-height: 1; margin-bottom: 4px;
}
.kc-blue   .kpi-number { color: var(--blue);   }
.kc-green  .kpi-number { color: var(--green);  }
.kc-amber  .kpi-number { color: var(--amber);  }
.kc-red    .kpi-number { color: var(--red);    }
.kc-indigo .kpi-number { color: var(--indigo); }

.kpi-label {
    font-size: 11px; font-weight: 600; text-transform: uppercase;
    letter-spacing: .07em; color: var(--text-3);
}

/* ══════════════════════════════════════
   SECTION TITLES
══════════════════════════════════════*/
.section-title {
    font-size: 11px; font-weight: 700; text-transform: uppercase;
    letter-spacing: .1em; color: var(--text-3);
    margin-bottom: 14px; display: flex; align-items: center; gap: 8px;
}
.section-title::after {
    content: ''; flex: 1; height: 1px; background: var(--border);
}

/* ══════════════════════════════════════
   CHART CARDS
══════════════════════════════════════*/
.chart-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
    margin-bottom: 26px;
}
.chart-card {
    background: var(--surface);
    border: 1.5px solid var(--border);
    border-radius: var(--radius-lg);
    box-shadow: var(--shadow);
    overflow: hidden;
}
.chart-card-header {
    padding: 16px 22px 14px;
    border-bottom: 1px solid var(--border);
    display: flex; align-items: center; justify-content: space-between;
    background: linear-gradient(180deg, var(--surface) 0%, var(--surface-2) 100%);
}
.chart-card-title {
    font-size: 13.5px; font-weight: 700; color: var(--text-1);
    display: flex; align-items: center; gap: 8px;
}
.chart-card-title i { font-size: 15px; }
.chart-card-sub { font-size: 11.5px; color: var(--text-3); margin-top: 1px; }
.chart-legend-dot {
    width: 9px; height: 9px; border-radius: 50%; display: inline-block; margin-right: 5px;
}
.chart-card-body {
    padding: 20px 22px;
    position: relative;
}

/* ══════════════════════════════════════
   TABLE PANEL
══════════════════════════════════════*/
.panel {
    background: var(--surface);
    border: 1.5px solid var(--border);
    border-radius: var(--radius-lg);
    box-shadow: var(--shadow);
    overflow: hidden;
    margin-bottom: 26px;
}
.panel-header {
    padding: 16px 24px;
    border-bottom: 1.5px solid var(--border);
    display: flex; align-items: center; justify-content: space-between;
    background: linear-gradient(180deg, var(--surface) 0%, var(--surface-2) 100%);
}
.panel-title {
    font-size: 14px; font-weight: 700; color: var(--text-1);
    display: flex; align-items: center; gap: 8px;
}
.panel-title i { color: var(--blue); }
.panel-count {
    font-size: 12px; background: var(--blue-light); color: var(--blue);
    border-radius: 20px; padding: 2px 10px; font-weight: 600;
}

/* Table */
.data-table { width: 100%; border-collapse: collapse; }
.data-table thead th {
    background: var(--surface-2); padding: 11px 20px;
    font-size: 10.5px; font-weight: 700; text-transform: uppercase;
    letter-spacing: .08em; color: var(--text-3);
    border-bottom: 1.5px solid var(--border); white-space: nowrap;
}
.data-table thead th:not(:first-child) { text-align: right; }
.data-table tbody tr {
    border-bottom: 1px solid var(--border); transition: background .14s;
    animation: rowIn .22s ease both;
}
.data-table tbody tr:last-child { border-bottom: none; }
.data-table tbody tr:hover { background: #f6f8ff; }
@keyframes rowIn { from { opacity:0; transform:translateY(4px); } to { opacity:1; transform:none; } }
<?php foreach(range(1,20) as $i): ?>
.data-table tbody tr:nth-child(<?= $i ?>) { animation-delay: <?= ($i-1)*0.035 ?>s; }
<?php endforeach; ?>

.data-table td { padding: 13px 20px; color: var(--text-2); vertical-align: middle; font-size: 13.5px; }
.data-table td:not(:first-child) { text-align: right; }

.type-tag {
    display: inline-flex; align-items: center; gap: 5px;
    background: var(--blue-light); color: var(--blue-dark);
    border-radius: 20px; padding: 3px 11px; font-size: 12px; font-weight: 600;
}
.rank-badge {
    display: inline-flex; align-items: center; justify-content: center;
    width: 22px; height: 22px; border-radius: 50%;
    font-size: 11px; font-weight: 700; margin-right: 8px;
}
.rank-1 { background: #fef3c7; color: #92400e; }
.rank-2 { background: var(--surface-3); color: var(--text-2); border: 1px solid var(--border); }
.rank-3 { background: #fff2ea; color: var(--orange); }
.rank-n { background: var(--surface-3); color: var(--text-3); font-size: 10px; }

.count-mono {
    font-family: 'DM Mono', monospace; font-size: 13px; font-weight: 600; color: #0d1117;
}
.qty-mono  { font-family: 'DM Mono', monospace; font-size: 13px; font-weight: 600; color: #0d1117; }
.value-mono {
    font-family: 'DM Mono', monospace; font-size: 13px; font-weight: 700; color: #0d1117;
}

/* Value bar (mini progress) */
.value-bar-wrap { display: flex; align-items: center; gap: 10px; justify-content: flex-end; }
.value-bar-track {
    width: 80px; height: 5px; background: var(--border); border-radius: 99px; overflow: hidden; flex-shrink: 0;
}
.value-bar-fill { height: 100%; border-radius: 99px; background: linear-gradient(90deg, var(--green) 0%, #34d399 100%); }

/* ══════════════════════════════════════
   RECENT ITEMS GRID
══════════════════════════════════════*/
.recent-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 14px;
    margin-bottom: 26px;
}
.recent-item-card {
    background: var(--surface);
    border: 1.5px solid var(--border);
    border-radius: var(--radius);
    padding: 14px 16px;
    box-shadow: var(--shadow-xs);
    display: flex; align-items: center; gap: 12px;
    transition: box-shadow .2s, transform .2s;
    animation: rowIn .28s ease both;
}
.recent-item-card:hover { box-shadow: var(--shadow); transform: translateY(-2px); }
.ri-icon {
    width: 38px; height: 38px; border-radius: var(--radius-sm);
    background: var(--blue-light); color: var(--blue);
    display: flex; align-items: center; justify-content: center; font-size: 17px; flex-shrink: 0;
}
.ri-name { font-size: 13px; font-weight: 700; color: var(--text-1); margin-bottom: 2px;
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.ri-type { font-size: 11px; color: var(--text-3); font-weight: 500; }
.ri-qty  {
    margin-left: auto; text-align: right; flex-shrink: 0;
    font-family: 'DM Mono', monospace; font-size: 14px; font-weight: 700; color: var(--blue);
}
.ri-qty small { display: block; font-size: 10px; font-weight: 500; color: var(--text-3); font-family: 'Sora', sans-serif; }

/* ══════════════════════════════════════
   RESPONSIVE
══════════════════════════════════════*/
@media (max-width: 1200px) {
    .kpi-grid   { grid-template-columns: repeat(3, 1fr); }
    .recent-grid{ grid-template-columns: repeat(2, 1fr); }
}
@media (max-width: 992px) {
    .chart-grid { grid-template-columns: 1fr; }
}
@media (max-width: 768px) {
    .main-content { margin-left: 0; padding: 16px; }
    .kpi-grid     { grid-template-columns: repeat(2, 1fr); gap: 10px; }
    .recent-grid  { grid-template-columns: 1fr; }
    .data-table td:nth-child(2),
    .data-table th:nth-child(2) { display: none; }
}
</style>
</head>
<body>

<div class="main-sidebar"><?php include 'inventory_sidebar.php'; ?></div>

<div class="main-content">

    <!-- ── Page Header ── -->
    <div class="page-header">
        <div class="ph-left">
            <div class="ph-icon-wrap"><i class="bi bi-hospital-fill"></i></div>
            <div>
                <div class="ph-eyebrow">Hospital Inventory</div>
                <div class="ph-title">Assets Dashboard</div>
                <div class="ph-sub">Real-time overview of all inventory assets and valuations</div>
            </div>
        </div>
        <div class="ph-date">
            <span>Today</span>
            <strong><?= date('F d, Y') ?></strong>
        </div>
    </div>

    <!-- ── KPI Cards ── -->
    <div class="kpi-grid">

        <div class="kpi-card kc-blue">
            <div class="kpi-icon-row">
                <div class="kpi-icon"><i class="bi bi-boxes"></i></div>
                <span class="kpi-trend" style="background:var(--blue-light);color:var(--blue);">Items</span>
            </div>
            <div class="kpi-number"><?= number_format($inventory_data['total_items']) ?></div>
            <div class="kpi-label">Total Inventory Items</div>
        </div>

        <div class="kpi-card kc-green">
            <div class="kpi-icon-row">
                <div class="kpi-icon"><i class="bi bi-stack"></i></div>
                <span class="kpi-trend" style="background:var(--green-light);color:var(--green);">Stock</span>
            </div>
            <div class="kpi-number"><?= number_format($inventory_data['total_quantity']) ?></div>
            <div class="kpi-label">Total Quantity in Stock</div>
        </div>

        <div class="kpi-card kc-amber">
            <div class="kpi-icon-row">
                <div class="kpi-icon"><i class="bi bi-cash-coin"></i></div>
                <span class="kpi-trend" style="background:var(--amber-light);color:var(--amber);">Value</span>
            </div>
            <div class="kpi-number" style="font-size:20px;">₱<?= number_format($inventory_data['total_value'], 2) ?></div>
            <div class="kpi-label">Total Asset Value</div>
        </div>

        <div class="kpi-card kc-red">
            <div class="kpi-icon-row">
                <div class="kpi-icon"><i class="bi bi-exclamation-triangle-fill"></i></div>
                <span class="kpi-trend" style="background:var(--red-light);color:var(--red);">Alert</span>
            </div>
            <div class="kpi-number"><?= number_format($low_stock_count) ?></div>
            <div class="kpi-label">Low Stock Items (≤10)</div>
        </div>

        <div class="kpi-card kc-indigo">
            <div class="kpi-icon-row">
                <div class="kpi-icon"><i class="bi bi-archive-fill"></i></div>
                <span class="kpi-trend" style="background:var(--indigo-light);color:var(--indigo);">Empty</span>
            </div>
            <div class="kpi-number"><?= number_format($out_of_stock) ?></div>
            <div class="kpi-label">Out of Stock</div>
        </div>

    </div>

    <!-- ── Charts ── -->
    <div class="section-title"><i class="bi bi-bar-chart-fill" style="color:var(--blue);"></i> Inventory Analysis</div>

    <div class="chart-grid">

        <!-- Value Chart -->
        <div class="chart-card">
            <div class="chart-card-header">
                <div>
                    <div class="chart-card-title">
                        <i class="bi bi-bar-chart-fill" style="color:var(--blue);"></i>
                        Asset Value by Item Type
                    </div>
                    <div class="chart-card-sub">Total monetary value per category</div>
                </div>
                <div style="font-size:11px;color:var(--text-3);font-weight:600;">
                    <span class="chart-legend-dot" style="background:var(--blue);"></span>₱ Value
                </div>
            </div>
            <div class="chart-card-body">
                <canvas id="assetValueChart" height="220"></canvas>
            </div>
        </div>

        <!-- Quantity Chart -->
        <div class="chart-card">
            <div class="chart-card-header">
                <div>
                    <div class="chart-card-title">
                        <i class="bi bi-boxes" style="color:var(--teal);"></i>
                        Quantity by Item Type
                    </div>
                    <div class="chart-card-sub">Total units on hand per category</div>
                </div>
                <div style="font-size:11px;color:var(--text-3);font-weight:600;">
                    <span class="chart-legend-dot" style="background:var(--teal);"></span>Units
                </div>
            </div>
            <div class="chart-card-body">
                <canvas id="assetQuantityChart" height="220"></canvas>
            </div>
        </div>

    </div>

    <!-- ── Recently Added ── -->
    <?php if (!empty($recent_items)): ?>
    <div class="section-title"><i class="bi bi-clock-history" style="color:var(--green);"></i> Recently Added</div>

    <div class="recent-grid">
        <?php foreach ($recent_items as $idx => $ri): ?>
        <div class="recent-item-card" style="animation-delay:<?= $idx * 0.06 ?>s;">
            <div class="ri-icon"><i class="bi bi-box2-fill"></i></div>
            <div style="min-width:0;">
                <div class="ri-name"><?= htmlspecialchars($ri['item_name']) ?></div>
                <div class="ri-type"><?= htmlspecialchars($ri['item_type'] ?? 'Supply') ?></div>
            </div>
            <div class="ri-qty">
                <?= number_format($ri['quantity']) ?>
                <small>units</small>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- ── Overview Table ── -->
    <div class="section-title"><i class="bi bi-table" style="color:var(--indigo);"></i> Full Breakdown</div>

    <?php
    $maxVal = !empty($total_values) ? max($total_values) : 1;
    if ($maxVal == 0) $maxVal = 1;
    ?>
    <div class="panel">
        <div class="panel-header">
            <div class="panel-title">
                <i class="bi bi-list-ul"></i>
                Assets Overview by Item Type
            </div>
            <span class="panel-count"><?= count($item_type_data) ?> categor<?= count($item_type_data) !== 1 ? 'ies' : 'y' ?></span>
        </div>
        <div style="overflow-x:auto;">
        <table class="data-table">
        <thead>
            <tr>
                <th>Item Type</th>
                <th># Items</th>
                <th>Total Quantity</th>
                <th>Asset Value</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($item_type_data as $idx => $i):
            $rank = $idx + 1;
            $pct  = $maxVal > 0 ? round(($i['total_value'] / $maxVal) * 100) : 0;
        ?>
        <tr>
            <td>
                <span class="rank-badge <?= $rank === 1 ? 'rank-1' : ($rank === 2 ? 'rank-2' : ($rank === 3 ? 'rank-3' : 'rank-n')) ?>">
                    <?= $rank <= 3 ? $rank : '#' ?>
                </span>
                <span class="type-tag"><?= htmlspecialchars($i['item_type']) ?></span>
            </td>
            <td><span class="count-mono"><?= number_format($i['item_count']) ?></span></td>
            <td><span class="qty-mono"><?= number_format($i['total_quantity']) ?></span></td>
            <td>
                <div class="value-bar-wrap">
                    <span class="value-mono">₱<?= number_format($i['total_value'], 2) ?></span>
                    <div class="value-bar-track">
                        <div class="value-bar-fill" style="width:<?= $pct ?>%;"></div>
                    </div>
                </div>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
        </table>
        </div>
    </div>

</div><!-- /main-content -->
<script>
const itemTypes  = <?= $item_types_json ?>;
const values     = <?= $values_json ?>;
const quantities = <?= $quantities_json ?>;

/* ── Shared chart defaults ── */
Chart.defaults.font.family = "'Sora', sans-serif";
Chart.defaults.font.size   = 12;
Chart.defaults.color       = '#8b93ad';

const gridColor  = 'rgba(227,231,240,0.7)';
const tickColor  = '#8b93ad';

/* ── Value Chart (LINE) ── */
new Chart(document.getElementById('assetValueChart'), {
    type: 'line',
    data: {
        labels: itemTypes,
        datasets: [{
            label: 'Asset Value (₱)',
            data: values,
            borderColor: '#1b56f5',
            backgroundColor: 'rgba(27,86,245,0.15)',
            fill: true,
            tension: 0.4,
            pointBackgroundColor: '#1b56f5',
            pointBorderColor: '#ffffff',
            pointBorderWidth: 2,
            pointRadius: 5,
            pointHoverRadius: 7
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: { display: false },
            tooltip: {
                backgroundColor: '#0d1117',
                titleColor: '#fff',
                bodyColor: '#8b93ad',
                padding: 12,
                cornerRadius: 8,
                callbacks: {
                    label: ctx => 
                        ' ₱' + ctx.parsed.y.toLocaleString('en-PH', { minimumFractionDigits: 2 })
                }
            }
        },
        scales: {
            x: {
                grid: { color: gridColor },
                ticks: { color: tickColor, font: { size: 11, weight: '600' } }
            },
            y: {
                beginAtZero: true,
                grid: { color: gridColor },
                ticks: {
                    color: tickColor,
                    callback: v => 
                        '₱' + (v >= 1000 ? (v/1000).toFixed(0) + 'K' : v.toLocaleString())
                }
            }
        }
    }
});

/* ── Quantity Chart (LINE) ── */
new Chart(document.getElementById('assetQuantityChart'), {
    type: 'line',
    data: {
        labels: itemTypes,
        datasets: [{
            label: 'Quantity',
            data: quantities,
            borderColor: '#0580a4',
            backgroundColor: 'rgba(5,128,164,0.15)',
            fill: true,
            tension: 0.4,
            pointBackgroundColor: '#0580a4',
            pointBorderColor: '#ffffff',
            pointBorderWidth: 2,
            pointRadius: 5,
            pointHoverRadius: 7
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: { display: false },
            tooltip: {
                backgroundColor: '#0d1117',
                titleColor: '#fff',
                bodyColor: '#8b93ad',
                padding: 12,
                cornerRadius: 8,
                callbacks: {
                    label: ctx => 
                        ' ' + ctx.parsed.y.toLocaleString() + ' units'
                }
            }
        },
        scales: {
            x: {
                grid: { color: gridColor },
                ticks: { color: tickColor, font: { size: 11, weight: '600' } }
            },
            y: {
                beginAtZero: true,
                grid: { color: gridColor },
                ticks: {
                    color: tickColor,
                    callback: v => 
                        v >= 1000 ? (v/1000).toFixed(0) + 'K' : v
                }
            }
        }
    }
});
</script>

</body>
</html>