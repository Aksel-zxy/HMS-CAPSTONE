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

// ══════════════════════════════════════
//   ASSET VALUE TRACKING QUERIES
// ══════════════════════════════════════

// --- WEEKLY: last 7 days, grouped by day ---
$weekly_stmt = $pdo->query("
    SELECT 
        DATE_FORMAT(received_at, '%a %d') AS label,
        DATE(received_at) AS day_key,
        COALESCE(SUM(quantity * price), 0) AS value,
        COALESCE(SUM(quantity), 0) AS qty,
        COUNT(*) AS items
    FROM inventory
    WHERE received_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    GROUP BY day_key, label
    ORDER BY day_key ASC
");
$weekly_data = $weekly_stmt->fetchAll(PDO::FETCH_ASSOC);

// --- MONTHLY: last 12 months, grouped by month ---
$monthly_stmt = $pdo->query("
    SELECT 
        DATE_FORMAT(received_at, '%b %Y') AS label,
        DATE_FORMAT(received_at, '%Y-%m') AS month_key,
        COALESCE(SUM(quantity * price), 0) AS value,
        COALESCE(SUM(quantity), 0) AS qty,
        COUNT(*) AS items
    FROM inventory
    WHERE received_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
    GROUP BY month_key, label
    ORDER BY month_key ASC
");
$monthly_data = $monthly_stmt->fetchAll(PDO::FETCH_ASSOC);

// --- YEARLY: last 5 years, grouped by year ---
$yearly_stmt = $pdo->query("
    SELECT 
        DATE_FORMAT(received_at, '%Y') AS label,
        YEAR(received_at) AS year_key,
        COALESCE(SUM(quantity * price), 0) AS value,
        COALESCE(SUM(quantity), 0) AS qty,
        COUNT(*) AS items
    FROM inventory
    WHERE received_at >= DATE_SUB(NOW(), INTERVAL 5 YEAR)
    GROUP BY year_key, label
    ORDER BY year_key ASC
");
$yearly_data = $yearly_stmt->fetchAll(PDO::FETCH_ASSOC);

// Chart data for item types
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

// Asset tracking chart JSON
$weekly_json  = json_encode($weekly_data);
$monthly_json = json_encode($monthly_data);
$yearly_json  = json_encode($yearly_data);

// Compute week-over-week change
$current_week  = array_sum(array_column($weekly_data, 'value'));
$prev_week_stmt = $pdo->query("
    SELECT COALESCE(SUM(quantity * price), 0) AS value
    FROM inventory
    WHERE received_at >= DATE_SUB(NOW(), INTERVAL 14 DAY)
      AND received_at < DATE_SUB(NOW(), INTERVAL 7 DAY)
");
$prev_week = (float)$prev_week_stmt->fetchColumn();
$week_change = $prev_week > 0 ? round((($current_week - $prev_week) / $prev_week) * 100, 1) : 0;

// Month over month
$current_month_stmt = $pdo->query("
    SELECT COALESCE(SUM(quantity * price), 0) AS value
    FROM inventory
    WHERE MONTH(received_at) = MONTH(NOW()) AND YEAR(received_at) = YEAR(NOW())
");
$current_month = (float)$current_month_stmt->fetchColumn();

$prev_month_stmt = $pdo->query("
    SELECT COALESCE(SUM(quantity * price), 0) AS value
    FROM inventory
    WHERE MONTH(received_at) = MONTH(DATE_SUB(NOW(), INTERVAL 1 MONTH))
      AND YEAR(received_at)  = YEAR(DATE_SUB(NOW(), INTERVAL 1 MONTH))
");
$prev_month = (float)$prev_month_stmt->fetchColumn();
$month_change = $prev_month > 0 ? round((($current_month - $prev_month) / $prev_month) * 100, 1) : 0;

// Year over year
$current_year_stmt = $pdo->query("
    SELECT COALESCE(SUM(quantity * price), 0) AS value
    FROM inventory
    WHERE YEAR(received_at) = YEAR(NOW())
");
$current_year = (float)$current_year_stmt->fetchColumn();

$prev_year_stmt = $pdo->query("
    SELECT COALESCE(SUM(quantity * price), 0) AS value
    FROM inventory
    WHERE YEAR(received_at) = YEAR(NOW()) - 1
");
$prev_year = (float)$prev_year_stmt->fetchColumn();
$year_change = $prev_year > 0 ? round((($current_year - $prev_year) / $prev_year) * 100, 1) : 0;
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

/* ── PAGE HEADER ── */
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
.ph-eyebrow { font-size: 10.5px; font-weight: 700; letter-spacing: .12em; text-transform: uppercase; color: var(--blue); margin-bottom: 3px; }
.ph-title   { font-size: 22px; font-weight: 800; color: var(--text-1); letter-spacing: -.4px; }
.ph-sub     { font-size: 12.5px; color: var(--text-3); margin-top: 2px; }
.ph-date    { text-align: right; font-size: 12px; color: var(--text-3); line-height: 1.4; }
.ph-date strong { display: block; font-size: 13.5px; font-weight: 700; color: var(--text-2); }

/* ── KPI CARDS ── */
.kpi-grid {
    display: grid;
    grid-template-columns: repeat(5, 1fr);
    gap: 14px;
    margin-bottom: 26px;
}
.kpi-card {
    background: var(--surface); border: 1.5px solid var(--border);
    border-radius: var(--radius); padding: 18px 20px;
    position: relative; overflow: hidden;
    box-shadow: var(--shadow-xs); transition: box-shadow .2s, transform .2s;
}
.kpi-card:hover { box-shadow: var(--shadow); transform: translateY(-2px); }
.kpi-card::before {
    content: ''; position: absolute; top: 0; left: 0; right: 0; height: 3px;
    border-radius: var(--radius) var(--radius) 0 0;
}
.kc-blue::before   { background: var(--blue);   }
.kc-green::before  { background: var(--green);  }
.kc-amber::before  { background: var(--amber);  }
.kc-red::before    { background: var(--red);    }
.kc-indigo::before { background: var(--indigo); }
.kpi-icon-row { display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px; }
.kpi-icon { width: 38px; height: 38px; border-radius: var(--radius-sm); display: flex; align-items: center; justify-content: center; font-size: 17px; }
.kc-blue   .kpi-icon { background: var(--blue-light);   color: var(--blue);   }
.kc-green  .kpi-icon { background: var(--green-light);  color: var(--green);  }
.kc-amber  .kpi-icon { background: var(--amber-light);  color: var(--amber);  }
.kc-red    .kpi-icon { background: var(--red-light);    color: var(--red);    }
.kc-indigo .kpi-icon { background: var(--indigo-light); color: var(--indigo); }
.kpi-trend { font-size: 11px; font-weight: 600; padding: 2px 7px; border-radius: 20px; }
.kpi-number { font-family: 'DM Mono', monospace; font-size: 26px; font-weight: 700; line-height: 1; margin-bottom: 4px; }
.kc-blue   .kpi-number { color: var(--blue);   }
.kc-green  .kpi-number { color: var(--green);  }
.kc-amber  .kpi-number { color: var(--amber);  }
.kc-red    .kpi-number { color: var(--red);    }
.kc-indigo .kpi-number { color: var(--indigo); }
.kpi-label { font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: .07em; color: var(--text-3); }

/* ── SECTION TITLE ── */
.section-title {
    font-size: 11px; font-weight: 700; text-transform: uppercase;
    letter-spacing: .1em; color: var(--text-3);
    margin-bottom: 14px; display: flex; align-items: center; gap: 8px;
}
.section-title::after { content: ''; flex: 1; height: 1px; background: var(--border); }

/* ══════════════════════════════════════
   ASSET VALUE TRACKER  ← NEW
══════════════════════════════════════*/
.tracker-card {
    background: var(--surface);
    border: 1.5px solid var(--border);
    border-radius: var(--radius-lg);
    box-shadow: var(--shadow);
    overflow: hidden;
    margin-bottom: 26px;
}

.tracker-header {
    padding: 18px 24px 16px;
    border-bottom: 1px solid var(--border);
    display: flex; align-items: flex-start; justify-content: space-between;
    flex-wrap: wrap; gap: 14px;
    background: linear-gradient(135deg, #0d1117 0%, #1a2240 100%);
}
.tracker-hdr-left { display: flex; align-items: center; gap: 14px; }
.tracker-hdr-icon {
    width: 46px; height: 46px; border-radius: var(--radius-sm);
    background: linear-gradient(135deg, var(--amber) 0%, #f59e0b 100%);
    display: flex; align-items: center; justify-content: center; font-size: 20px; color: #fff;
    box-shadow: 0 4px 16px rgba(199,123,10,.4); flex-shrink: 0;
}
.tracker-hdr-title  { font-size: 15px; font-weight: 800; color: #fff; letter-spacing: -.2px; }
.tracker-hdr-sub    { font-size: 11.5px; color: rgba(255,255,255,.45); margin-top: 2px; }

/* Period tabs */
.period-tabs {
    display: flex; gap: 4px;
    background: rgba(255,255,255,.06);
    border: 1px solid rgba(255,255,255,.1);
    border-radius: var(--radius-sm);
    padding: 4px;
}
.period-tab {
    padding: 7px 18px; border-radius: 6px; border: none;
    font-family: 'Sora', sans-serif; font-size: 12px; font-weight: 700;
    color: rgba(255,255,255,.45); background: transparent; cursor: pointer;
    transition: background .15s, color .15s;
    letter-spacing: .04em;
}
.period-tab.active {
    background: var(--amber);
    color: #fff;
    box-shadow: 0 2px 8px rgba(199,123,10,.4);
}
.period-tab:hover:not(.active) { color: rgba(255,255,255,.8); background: rgba(255,255,255,.08); }

/* Summary stat row */
.tracker-stats {
    display: flex; gap: 0;
    border-bottom: 1px solid var(--border);
}
.tstat {
    flex: 1; padding: 16px 24px;
    border-right: 1px solid var(--border);
    display: flex; align-items: center; gap: 12px;
}
.tstat:last-child { border-right: none; }
.tstat-icon {
    width: 36px; height: 36px; border-radius: var(--radius-sm);
    display: flex; align-items: center; justify-content: center; font-size: 16px;
    flex-shrink: 0;
}
.tstat-num {
    font-family: 'DM Mono', monospace; font-size: 18px; font-weight: 700;
    color: var(--text-1); line-height: 1;
}
.tstat-lbl { font-size: 10.5px; font-weight: 600; text-transform: uppercase; letter-spacing: .07em; color: var(--text-3); margin-top: 2px; }
.tstat-change {
    margin-left: auto; font-size: 12px; font-weight: 700;
    padding: 3px 8px; border-radius: 20px; white-space: nowrap; flex-shrink: 0;
}
.tc-up   { background: var(--green-light); color: var(--green); }
.tc-down { background: var(--red-light);   color: var(--red);   }
.tc-flat { background: var(--surface-3);   color: var(--text-3); }

/* Chart area */
.tracker-body {
    padding: 24px 24px 20px;
    position: relative;
}
.tracker-period-panel { display: none; }
.tracker-period-panel.active { display: block; animation: fadePanel .25s ease; }
@keyframes fadePanel { from { opacity:0; transform:translateY(4px); } to { opacity:1; transform:none; } }

/* No-data state */
.no-data-msg {
    display: flex; flex-direction: column; align-items: center; justify-content: center;
    padding: 48px 24px; color: var(--text-3); text-align: center;
}
.no-data-msg i { font-size: 36px; margin-bottom: 10px; opacity: .4; }
.no-data-msg p { font-size: 13px; font-weight: 600; }

/* ── CHART CARDS (original) ── */
.chart-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 26px; }
.chart-card { background: var(--surface); border: 1.5px solid var(--border); border-radius: var(--radius-lg); box-shadow: var(--shadow); overflow: hidden; }
.chart-card-header { padding: 16px 22px 14px; border-bottom: 1px solid var(--border); display: flex; align-items: center; justify-content: space-between; background: linear-gradient(180deg, var(--surface) 0%, var(--surface-2) 100%); }
.chart-card-title { font-size: 13.5px; font-weight: 700; color: var(--text-1); display: flex; align-items: center; gap: 8px; }
.chart-card-title i { font-size: 15px; }
.chart-card-sub { font-size: 11.5px; color: var(--text-3); margin-top: 1px; }
.chart-legend-dot { width: 9px; height: 9px; border-radius: 50%; display: inline-block; margin-right: 5px; }
.chart-card-body { padding: 20px 22px; position: relative; }

/* ── TABLE PANEL ── */
.panel { background: var(--surface); border: 1.5px solid var(--border); border-radius: var(--radius-lg); box-shadow: var(--shadow); overflow: hidden; margin-bottom: 26px; }
.panel-header { padding: 16px 24px; border-bottom: 1.5px solid var(--border); display: flex; align-items: center; justify-content: space-between; background: linear-gradient(180deg, var(--surface) 0%, var(--surface-2) 100%); }
.panel-title { font-size: 14px; font-weight: 700; color: var(--text-1); display: flex; align-items: center; gap: 8px; }
.panel-title i { color: var(--blue); }
.panel-count { font-size: 12px; background: var(--blue-light); color: var(--blue); border-radius: 20px; padding: 2px 10px; font-weight: 600; }
.data-table { width: 100%; border-collapse: collapse; }
.data-table thead th { background: var(--surface-2); padding: 11px 20px; font-size: 10.5px; font-weight: 700; text-transform: uppercase; letter-spacing: .08em; color: var(--text-3); border-bottom: 1.5px solid var(--border); white-space: nowrap; }
.data-table thead th:not(:first-child) { text-align: right; }
.data-table tbody tr { border-bottom: 1px solid var(--border); transition: background .14s; animation: rowIn .22s ease both; }
.data-table tbody tr:last-child { border-bottom: none; }
.data-table tbody tr:hover { background: #f6f8ff; }
@keyframes rowIn { from { opacity:0; transform:translateY(4px); } to { opacity:1; transform:none; } }
<?php foreach(range(1,20) as $i): ?>
.data-table tbody tr:nth-child(<?= $i ?>) { animation-delay: <?= ($i-1)*0.035 ?>s; }
<?php endforeach; ?>
.data-table td { padding: 13px 20px; color: var(--text-2); vertical-align: middle; font-size: 13.5px; }
.data-table td:not(:first-child) { text-align: right; }
.type-tag { display: inline-flex; align-items: center; gap: 5px; background: var(--blue-light); color: var(--blue-dark); border-radius: 20px; padding: 3px 11px; font-size: 12px; font-weight: 600; }
.rank-badge { display: inline-flex; align-items: center; justify-content: center; width: 22px; height: 22px; border-radius: 50%; font-size: 11px; font-weight: 700; margin-right: 8px; }
.rank-1 { background: #fef3c7; color: #92400e; }
.rank-2 { background: var(--surface-3); color: var(--text-2); border: 1px solid var(--border); }
.rank-3 { background: #fff2ea; color: var(--orange); }
.rank-n { background: var(--surface-3); color: var(--text-3); font-size: 10px; }
.count-mono  { font-family: 'DM Mono', monospace; font-size: 13px; font-weight: 600; color: #0d1117; }
.qty-mono    { font-family: 'DM Mono', monospace; font-size: 13px; font-weight: 600; color: #0d1117; }
.value-mono  { font-family: 'DM Mono', monospace; font-size: 13px; font-weight: 700; color: #0d1117; }
.value-bar-wrap { display: flex; align-items: center; gap: 10px; justify-content: flex-end; }
.value-bar-track { width: 80px; height: 5px; background: var(--border); border-radius: 99px; overflow: hidden; flex-shrink: 0; }
.value-bar-fill { height: 100%; border-radius: 99px; background: linear-gradient(90deg, var(--green) 0%, #34d399 100%); }

/* ── RECENT GRID ── */
.recent-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 14px; margin-bottom: 26px; }
.recent-item-card { background: var(--surface); border: 1.5px solid var(--border); border-radius: var(--radius); padding: 14px 16px; box-shadow: var(--shadow-xs); display: flex; align-items: center; gap: 12px; transition: box-shadow .2s, transform .2s; animation: rowIn .28s ease both; }
.recent-item-card:hover { box-shadow: var(--shadow); transform: translateY(-2px); }
.ri-icon { width: 38px; height: 38px; border-radius: var(--radius-sm); background: var(--blue-light); color: var(--blue); display: flex; align-items: center; justify-content: center; font-size: 17px; flex-shrink: 0; }
.ri-name { font-size: 13px; font-weight: 700; color: var(--text-1); margin-bottom: 2px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.ri-type { font-size: 11px; color: var(--text-3); font-weight: 500; }
.ri-qty  { margin-left: auto; text-align: right; flex-shrink: 0; font-family: 'DM Mono', monospace; font-size: 14px; font-weight: 700; color: var(--blue); }
.ri-qty small { display: block; font-size: 10px; font-weight: 500; color: var(--text-3); font-family: 'Sora', sans-serif; }

/* ── RESPONSIVE ── */
@media (max-width: 1200px) { .kpi-grid { grid-template-columns: repeat(3,1fr); } .recent-grid { grid-template-columns: repeat(2,1fr); } }
@media (max-width: 992px)  { .chart-grid { grid-template-columns: 1fr; } }
@media (max-width: 768px)  {
    .main-content { margin-left:0; padding:16px; }
    .kpi-grid     { grid-template-columns: repeat(2,1fr); gap:10px; }
    .recent-grid  { grid-template-columns: 1fr; }
    .tracker-stats { flex-wrap: wrap; }
    .tstat { min-width: 50%; }
    .data-table td:nth-child(2), .data-table th:nth-child(2) { display: none; }
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

    <!-- ══════════════════════════════════════
         ASSET VALUE TRACKER — NEW SECTION
    ══════════════════════════════════════ -->
    <div class="section-title">
        <i class="bi bi-graph-up-arrow" style="color:var(--amber);"></i> Asset Value Tracker
    </div>

    <div class="tracker-card">

        <!-- Dark header with period tabs -->
        <div class="tracker-header">
            <div class="tracker-hdr-left">
                <div class="tracker-hdr-icon"><i class="bi bi-cash-stack"></i></div>
                <div>
                    <div class="tracker-hdr-title">Asset Value Over Time</div>
                    <div class="tracker-hdr-sub">Track how asset value changes weekly, monthly, and yearly</div>
                </div>
            </div>
            <div class="period-tabs">
                <button class="period-tab active" onclick="switchPeriod('weekly', this)">Weekly</button>
                <button class="period-tab" onclick="switchPeriod('monthly', this)">Monthly</button>
                <button class="period-tab" onclick="switchPeriod('yearly', this)">Yearly</button>
            </div>
        </div>

        <!-- Summary stats row -->
        <div class="tracker-stats">
            <!-- This week -->
            <div class="tstat">
                <div class="tstat-icon" style="background:var(--amber-light);color:var(--amber);">
                    <i class="bi bi-calendar-week-fill"></i>
                </div>
                <div>
                    <div class="tstat-num">₱<?= number_format($current_week, 0) ?></div>
                    <div class="tstat-lbl">This Week</div>
                </div>
                <span class="tstat-change <?= $week_change >= 0 ? 'tc-up' : 'tc-down' ?>">
                    <i class="bi bi-arrow-<?= $week_change >= 0 ? 'up' : 'down' ?>-short"></i>
                    <?= abs($week_change) ?>%
                </span>
            </div>
            <!-- This month -->
            <div class="tstat">
                <div class="tstat-icon" style="background:var(--blue-light);color:var(--blue);">
                    <i class="bi bi-calendar-month-fill"></i>
                </div>
                <div>
                    <div class="tstat-num">₱<?= number_format($current_month, 0) ?></div>
                    <div class="tstat-lbl">This Month</div>
                </div>
                <span class="tstat-change <?= $month_change >= 0 ? 'tc-up' : 'tc-down' ?>">
                    <i class="bi bi-arrow-<?= $month_change >= 0 ? 'up' : 'down' ?>-short"></i>
                    <?= abs($month_change) ?>%
                </span>
            </div>
            <!-- This year -->
            <div class="tstat">
                <div class="tstat-icon" style="background:var(--green-light);color:var(--green);">
                    <i class="bi bi-calendar-check-fill"></i>
                </div>
                <div>
                    <div class="tstat-num">₱<?= number_format($current_year, 0) ?></div>
                    <div class="tstat-lbl">This Year</div>
                </div>
                <span class="tstat-change <?= $year_change >= 0 ? 'tc-up' : 'tc-down' ?>">
                    <i class="bi bi-arrow-<?= $year_change >= 0 ? 'up' : 'down' ?>-short"></i>
                    <?= abs($year_change) ?>%
                </span>
            </div>
            <!-- Total all time -->
            <div class="tstat">
                <div class="tstat-icon" style="background:var(--indigo-light);color:var(--indigo);">
                    <i class="bi bi-bank2"></i>
                </div>
                <div>
                    <div class="tstat-num">₱<?= number_format($inventory_data['total_value'], 0) ?></div>
                    <div class="tstat-lbl">Total Assets</div>
                </div>
                <span class="tstat-change tc-flat">All Time</span>
            </div>
        </div>

        <!-- Chart panels -->
        <div class="tracker-body">

            <!-- WEEKLY PANEL -->
            <div class="tracker-period-panel active" id="panel-weekly">
                <?php if (!empty($weekly_data)): ?>
                <canvas id="weeklyChart" height="120"></canvas>
                <?php else: ?>
                <div class="no-data-msg">
                    <i class="bi bi-calendar-x"></i>
                    <p>No inventory added in the last 7 days.</p>
                </div>
                <?php endif; ?>
            </div>

            <!-- MONTHLY PANEL -->
            <div class="tracker-period-panel" id="panel-monthly">
                <?php if (!empty($monthly_data)): ?>
                <canvas id="monthlyChart" height="120"></canvas>
                <?php else: ?>
                <div class="no-data-msg">
                    <i class="bi bi-calendar-x"></i>
                    <p>No inventory data for the last 12 months.</p>
                </div>
                <?php endif; ?>
            </div>

            <!-- YEARLY PANEL -->
            <div class="tracker-period-panel" id="panel-yearly">
                <?php if (!empty($yearly_data)): ?>
                <canvas id="yearlyChart" height="120"></canvas>
                <?php else: ?>
                <div class="no-data-msg">
                    <i class="bi bi-calendar-x"></i>
                    <p>No inventory data for the last 5 years.</p>
                </div>
                <?php endif; ?>
            </div>

        </div>
    </div>

    <!-- ── Category Charts ── -->
    <div class="section-title"><i class="bi bi-bar-chart-fill" style="color:var(--blue);"></i> Inventory Analysis</div>

    <div class="chart-grid">
        <div class="chart-card">
            <div class="chart-card-header">
                <div>
                    <div class="chart-card-title"><i class="bi bi-bar-chart-fill" style="color:var(--blue);"></i> Asset Value by Item Type</div>
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
        <div class="chart-card">
            <div class="chart-card-header">
                <div>
                    <div class="chart-card-title"><i class="bi bi-boxes" style="color:var(--teal);"></i> Quantity by Item Type</div>
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

    <?php $maxVal = !empty($total_values) ? max($total_values) : 1; if ($maxVal == 0) $maxVal = 1; ?>

    <div class="panel">
        <div class="panel-header">
            <div class="panel-title"><i class="bi bi-list-ul"></i> Assets Overview by Item Type</div>
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
                <span class="rank-badge <?= $rank===1?'rank-1':($rank===2?'rank-2':($rank===3?'rank-3':'rank-n')) ?>">
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
/* ── Chart defaults ── */
Chart.defaults.font.family = "'Sora', sans-serif";
Chart.defaults.font.size   = 12;
Chart.defaults.color       = '#8b93ad';
const gridColor = 'rgba(227,231,240,0.7)';
const tickColor = '#8b93ad';

/* ── PHP data → JS ── */
const itemTypes  = <?= $item_types_json ?>;
const values     = <?= $values_json ?>;
const quantities = <?= $quantities_json ?>;

const weeklyData  = <?= $weekly_json ?>;
const monthlyData = <?= $monthly_json ?>;
const yearlyData  = <?= $yearly_json ?>;

/* ══════════════════════════════════════
   ASSET TRACKER CHARTS
══════════════════════════════════════*/

// Shared config builder for tracker charts
function buildTrackerChart(canvasId, data, color, label) {
    const canvas = document.getElementById(canvasId);
    if (!canvas || !data.length) return null;

    const labels = data.map(d => d.label);
    const vals   = data.map(d => parseFloat(d.value));
    const items  = data.map(d => parseInt(d.items));
    const qty    = data.map(d => parseInt(d.qty));

    return new Chart(canvas, {
        type: 'bar',
        data: {
            labels,
            datasets: [
                {
                    type: 'bar',
                    label: 'Asset Value (₱)',
                    data: vals,
                    backgroundColor: color + '22',
                    borderColor: color,
                    borderWidth: 2,
                    borderRadius: 6,
                    borderSkipped: false,
                    barPercentage: 0.6,
                    categoryPercentage: 0.7,
                    yAxisID: 'y',
                    order: 2,
                },
                {
                    type: 'line',
                    label: 'Items Added',
                    data: items,
                    borderColor: '#5046e5',
                    backgroundColor: 'rgba(80,70,229,0.08)',
                    borderWidth: 2.5,
                    fill: false,
                    tension: 0.4,
                    pointBackgroundColor: '#5046e5',
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2,
                    pointRadius: 5,
                    pointHoverRadius: 7,
                    yAxisID: 'y2',
                    order: 1,
                }
            ]
        },
        options: {
            responsive: true,
            interaction: { mode: 'index', intersect: false },
            plugins: {
                legend: {
                    display: true,
                    position: 'top',
                    align: 'end',
                    labels: {
                        boxWidth: 10, boxHeight: 10,
                        borderRadius: 5,
                        useBorderRadius: true,
                        font: { size: 11, weight: '600' },
                        padding: 16,
                    }
                },
                tooltip: {
                    backgroundColor: '#0d1117',
                    titleColor: '#fff',
                    bodyColor: '#8b93ad',
                    padding: 14,
                    cornerRadius: 10,
                    borderColor: 'rgba(255,255,255,.06)',
                    borderWidth: 1,
                    callbacks: {
                        label: ctx => {
                            if (ctx.dataset.yAxisID === 'y') {
                                return '  ₱ ' + ctx.parsed.y.toLocaleString('en-PH', { minimumFractionDigits: 2 });
                            }
                            return '  ' + ctx.parsed.y + ' items added';
                        }
                    }
                }
            },
            scales: {
                x: {
                    grid: { display: false },
                    border: { display: false },
                    ticks: { font: { size: 11, weight: '600' }, color: tickColor }
                },
                y: {
                    beginAtZero: true,
                    position: 'left',
                    grid: { color: gridColor },
                    border: { display: false, dash: [4,4] },
                    ticks: {
                        color: tickColor,
                        callback: v => v >= 1000 ? '₱' + (v/1000).toFixed(0) + 'K' : '₱' + v
                    }
                },
                y2: {
                    beginAtZero: true,
                    position: 'right',
                    grid: { display: false },
                    border: { display: false },
                    ticks: {
                        color: '#5046e5',
                        callback: v => Number.isInteger(v) ? v + ' items' : ''
                    }
                }
            }
        }
    });
}

// Build all three tracker charts
buildTrackerChart('weeklyChart',  weeklyData,  '#c77b0a', 'Weekly');
buildTrackerChart('monthlyChart', monthlyData, '#1b56f5', 'Monthly');
buildTrackerChart('yearlyChart',  yearlyData,  '#0d9f6b', 'Yearly');

/* ── Period tab switcher ── */
function switchPeriod(period, btn) {
    // Update active tab
    document.querySelectorAll('.period-tab').forEach(t => t.classList.remove('active'));
    btn.classList.add('active');

    // Show correct panel
    document.querySelectorAll('.tracker-period-panel').forEach(p => p.classList.remove('active'));
    document.getElementById('panel-' + period).classList.add('active');
}

/* ══════════════════════════════════════
   CATEGORY CHARTS (original)
══════════════════════════════════════*/

// Asset Value Line Chart
new Chart(document.getElementById('assetValueChart'), {
    type: 'line',
    data: {
        labels: itemTypes,
        datasets: [{
            label: 'Asset Value (₱)',
            data: values,
            borderColor: '#1b56f5',
            backgroundColor: 'rgba(27,86,245,0.15)',
            fill: true, tension: 0.4,
            pointBackgroundColor: '#1b56f5',
            pointBorderColor: '#ffffff',
            pointBorderWidth: 2,
            pointRadius: 5, pointHoverRadius: 7
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: { display: false },
            tooltip: {
                backgroundColor: '#0d1117', titleColor: '#fff', bodyColor: '#8b93ad',
                padding: 12, cornerRadius: 8,
                callbacks: { label: ctx => ' ₱' + ctx.parsed.y.toLocaleString('en-PH', { minimumFractionDigits: 2 }) }
            }
        },
        scales: {
            x: { grid: { color: gridColor }, ticks: { color: tickColor, font: { size: 11, weight: '600' } } },
            y: { beginAtZero: true, grid: { color: gridColor }, ticks: { color: tickColor, callback: v => '₱' + (v >= 1000 ? (v/1000).toFixed(0)+'K' : v.toLocaleString()) } }
        }
    }
});

// Quantity Line Chart
new Chart(document.getElementById('assetQuantityChart'), {
    type: 'line',
    data: {
        labels: itemTypes,
        datasets: [{
            label: 'Quantity',
            data: quantities,
            borderColor: '#0580a4',
            backgroundColor: 'rgba(5,128,164,0.15)',
            fill: true, tension: 0.4,
            pointBackgroundColor: '#0580a4',
            pointBorderColor: '#ffffff',
            pointBorderWidth: 2,
            pointRadius: 5, pointHoverRadius: 7
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: { display: false },
            tooltip: {
                backgroundColor: '#0d1117', titleColor: '#fff', bodyColor: '#8b93ad',
                padding: 12, cornerRadius: 8,
                callbacks: { label: ctx => ' ' + ctx.parsed.y.toLocaleString() + ' units' }
            }
        },
        scales: {
            x: { grid: { color: gridColor }, ticks: { color: tickColor, font: { size: 11, weight: '600' } } },
            y: { beginAtZero: true, grid: { color: gridColor }, ticks: { color: tickColor, callback: v => v >= 1000 ? (v/1000).toFixed(0)+'K' : v } }
        }
    }
});
</script>

</body>
</html>