<?php
session_start();
include '../../SQL/config.php';

if (!isset($_SESSION['billing']) || $_SESSION['billing'] !== true) {
    header('Location: login.php'); exit();
}
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    echo "User ID is not set in session."; exit();
}

$query = "SELECT * FROM users WHERE user_id = ?";
$stmt  = $conn->prepare($query);
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
if (!$user) { echo "No user found."; exit(); }

// --- Totals ---
$total_patients = $conn->query("SELECT COUNT(DISTINCT patient_id) AS cnt FROM patient_receipt")->fetch_assoc()['cnt'];
$total_receipts = $conn->query("SELECT COUNT(*) AS cnt FROM patient_receipt")->fetch_assoc()['cnt'];
$total_paid     = $conn->query("SELECT SUM(grand_total) AS total FROM patient_receipt WHERE status='Paid'")->fetch_assoc()['total'] ?? 0;
$total_unpaid   = $conn->query("SELECT SUM(grand_total) AS total FROM patient_receipt WHERE status!='Paid'")->fetch_assoc()['total'] ?? 0;

// --- Payment Methods ---
$payment_methods = [];
$pm_result = $conn->query("SELECT payment_method, COUNT(*) AS count, SUM(grand_total) AS total FROM patient_receipt GROUP BY payment_method");
while ($row = $pm_result->fetch_assoc()) $payment_methods[] = $row;

// --- Recent Receipts ---
$recent_receipts = $conn->query("SELECT * FROM patient_receipt ORDER BY created_at DESC LIMIT 8")->fetch_all(MYSQLI_ASSOC);

// --- Monthly Revenue (last 6 months) ---
$monthly = [];
$m_result = $conn->query("
    SELECT DATE_FORMAT(created_at,'%b %Y') AS month,
           DATE_FORMAT(created_at,'%Y-%m') AS month_key,
           SUM(grand_total) AS total
    FROM patient_receipt
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    GROUP BY month_key, month
    ORDER BY month_key ASC
");
while ($row = $m_result->fetch_assoc()) $monthly[] = $row;

// --- Daily Revenue (last 7 days for 2nd bar chart) ---
$daily = [];
$d_result = $conn->query("
    SELECT DATE_FORMAT(created_at,'%a %d') AS day_label,
           DATE(created_at) AS day_key,
           SUM(grand_total) AS total,
           COUNT(*) AS count
    FROM patient_receipt
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    GROUP BY day_key, day_label
    ORDER BY day_key ASC
");
while ($row = $d_result->fetch_assoc()) $daily[] = $row;

$total_billing   = $total_paid + $total_unpaid;
$collection_rate = $total_billing > 0 ? round(($total_paid / $total_billing) * 100, 1) : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Billing Dashboard — Hospital</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Sora:wght@300;400;500;600;700;800&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<style>
/* ══════════════════════════════════════
   DESIGN TOKENS
══════════════════════════════════════*/
:root {
    --bg:           #f0f2f7;
    --surface:      #ffffff;
    --surface-2:    #f8f9fc;
    --surface-3:    #f0f2f7;
    --border:       #e3e7f0;
    --border-light: #eef0f7;

    --text-1:       #0d1117;
    --text-2:       #4b5675;
    --text-3:       #8b93ad;
    --text-4:       #b8bece;

    --blue:         #1b56f5;
    --blue-light:   #edf1ff;
    --blue-dark:    #1040d8;
    --blue-glow:    rgba(27,86,245,.14);

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
    --teal-border:  #89d8ef;

    --indigo:       #5046e5;
    --indigo-light: #eeedfd;
    --indigo-border:#c4bef9;

    --sidebar-w:    260px;
    --radius-xs:    4px;
    --radius-sm:    8px;
    --radius:       12px;
    --radius-lg:    16px;
    --radius-xl:    20px;

    --shadow-xs:    0 1px 3px rgba(0,0,0,.05);
    --shadow-sm:    0 2px 8px rgba(0,0,0,.06), 0 1px 3px rgba(0,0,0,.04);
    --shadow:       0 4px 20px rgba(0,0,0,.07), 0 2px 8px rgba(0,0,0,.04);
    --shadow-lg:    0 16px 48px rgba(0,0,0,.10), 0 6px 16px rgba(0,0,0,.05);
    --shadow-blue:  0 6px 20px rgba(27,86,245,.22);
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

/* ══ LAYOUT ══ */
.main-sidebar {
    position: fixed; left: 0; top: 0; bottom: 0;
    width: var(--sidebar-w); z-index: 1000; overflow-y: auto;
}
.main-content {
    margin-left: var(--sidebar-w);
    min-height: 100vh;
    padding: 28px 32px 64px;
}

/* ══ PAGE HEADER ══ */
.page-header {
    display: flex; align-items: center;
    justify-content: space-between; flex-wrap: wrap;
    gap: 16px; margin-bottom: 28px;
    background: var(--surface);
    border: 1.5px solid var(--border);
    border-radius: var(--radius-lg);
    padding: 18px 24px;
    box-shadow: var(--shadow-xs);
}
.ph-left { display: flex; align-items: center; gap: 14px; }
.ph-icon-wrap {
    width: 46px; height: 46px;
    background: linear-gradient(135deg, var(--blue) 0%, #4d7fff 100%);
    border-radius: var(--radius); box-shadow: var(--shadow-blue);
    display: flex; align-items: center; justify-content: center; flex-shrink: 0;
}
.ph-icon-wrap i { font-size: 20px; color: #fff; }
.ph-eyebrow {
    font-size: 10px; font-weight: 700; letter-spacing: .14em;
    text-transform: uppercase; color: var(--blue); margin-bottom: 2px;
}
.ph-title { font-size: 18px; font-weight: 800; color: var(--text-1); letter-spacing: -.3px; line-height: 1.2; }
.ph-sub   { font-size: 11.5px; color: var(--text-3); margin-top: 2px; }
.ph-right { display: flex; align-items: center; gap: 14px; }
.ph-datebadge {
    background: var(--surface-2); border: 1.5px solid var(--border);
    border-radius: var(--radius-sm); padding: 8px 14px; text-align: right;
}
.ph-datebadge .date-day { font-size: 10px; font-weight: 600; color: var(--text-3); text-transform: uppercase; letter-spacing: .08em; }
.ph-datebadge .date-full { font-size: 13px; font-weight: 700; color: var(--text-1); }

/* ══ KPI CARDS ══ */
.kpi-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 14px;
    margin-bottom: 28px;
}
.kpi-card {
    background: var(--surface);
    border: 1.5px solid var(--border);
    border-radius: var(--radius-lg);
    padding: 20px 22px 18px;
    position: relative; overflow: hidden;
    box-shadow: var(--shadow-xs);
    transition: box-shadow .2s, transform .2s;
}
.kpi-card:hover { box-shadow: var(--shadow); transform: translateY(-2px); }
.kpi-card::before {
    content: ''; position: absolute;
    top: 0; left: 0; bottom: 0; width: 3px;
    border-radius: var(--radius-lg) 0 0 var(--radius-lg);
}
.kc-blue::before   { background: var(--blue); }
.kc-green::before  { background: var(--green); }
.kc-teal::before   { background: var(--teal); }
.kc-red::before    { background: var(--red); }
.kpi-top {
    display: flex; align-items: flex-start; justify-content: space-between; margin-bottom: 16px;
}
.kpi-icon {
    width: 38px; height: 38px; border-radius: var(--radius-sm);
    display: flex; align-items: center; justify-content: center; font-size: 16px;
}
.kc-blue  .kpi-icon { background: var(--blue-light);  color: var(--blue);  }
.kc-green .kpi-icon { background: var(--green-light); color: var(--green); }
.kc-teal  .kpi-icon { background: var(--teal-light);  color: var(--teal);  }
.kc-red   .kpi-icon { background: var(--red-light);   color: var(--red);   }
.kpi-badge {
    font-size: 10px; font-weight: 700; padding: 3px 8px; border-radius: 20px;
    letter-spacing: .05em; text-transform: uppercase;
}
.kc-blue  .kpi-badge { background: var(--blue-light);  color: var(--blue);  }
.kc-green .kpi-badge { background: var(--green-light); color: var(--green); }
.kc-teal  .kpi-badge { background: var(--teal-light);  color: var(--teal);  }
.kc-red   .kpi-badge { background: var(--red-light);   color: var(--red);   }
.kpi-number {
    font-family: 'DM Mono', monospace;
    font-size: 26px; font-weight: 700; line-height: 1; margin-bottom: 5px;
}
.kc-blue  .kpi-number { color: var(--blue);  }
.kc-green .kpi-number { color: var(--green); }
.kc-teal  .kpi-number { color: var(--teal);  }
.kc-red   .kpi-number { color: var(--red);   }
.kpi-label {
    font-size: 10.5px; font-weight: 600; text-transform: uppercase;
    letter-spacing: .07em; color: var(--text-3);
}
.kpi-divider { height: 1px; background: var(--border-light); margin: 14px 0 10px; }
.kpi-footer { font-size: 11px; color: var(--text-4); display: flex; align-items: center; gap: 5px; }

/* ══ SECTION TITLE ══ */
.section-title {
    font-size: 10.5px; font-weight: 700; text-transform: uppercase;
    letter-spacing: .12em; color: var(--text-3);
    margin-bottom: 14px; display: flex; align-items: center; gap: 8px;
}
.section-title::after { content:''; flex:1; height:1px; background:var(--border); }

/* ══════════════════════════════════════
   CHART CARDS — unified, equal-height
══════════════════════════════════════*/

/* Both chart rows use the same 2-column grid */
.charts-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
    margin-bottom: 20px;
    /* Each card in the same row will stretch to the tallest sibling */
    align-items: stretch;
}

.chart-card {
    background: var(--surface);
    border: 1.5px solid var(--border);
    border-radius: var(--radius-lg);
    box-shadow: var(--shadow-xs);
    overflow: hidden;
    display: flex;
    flex-direction: column;   /* header + body stack vertically */
    transition: box-shadow .2s, transform .18s;
}
.chart-card:hover { box-shadow: var(--shadow-sm); transform: translateY(-2px); }

.chart-card-hdr {
    padding: 16px 22px 14px;
    border-bottom: 1px solid var(--border);
    display: flex; align-items: center; justify-content: space-between;
    flex-shrink: 0;             /* never squish the header */
}
.chart-card-title {
    font-size: 13.5px; font-weight: 700; color: var(--text-1);
    display: flex; align-items: center; gap: 8px;
}
.chart-card-title i { font-size: 15px; }
.chart-card-sub { font-size: 11.5px; color: var(--text-3); margin-top: 3px; }
.chart-hdr-badge {
    font-size: 10px; font-weight: 700; padding: 3px 10px; border-radius: 20px;
    background: var(--surface-2); color: var(--text-3);
    border: 1px solid var(--border); letter-spacing: .04em; text-transform: uppercase;
    display: flex; align-items: center; gap: 5px;
}
.legend-dot { width: 8px; height: 8px; border-radius: 50%; display: inline-block; }

/* Chart body fills remaining card height */
.chart-card-body {
    padding: 20px 22px;
    flex: 1;                    /* stretches to fill card */
    display: flex;
    flex-direction: column;
    justify-content: center;
}

/* Canvas fills its parent completely */
.chart-card-body canvas {
    width: 100% !important;
}

/* ── PIE CARD specific ── */
.pie-card-body {
    padding: 22px 22px 18px;
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 0;
}

/* Doughnut with centered label */
.donut-wrap {
    position: relative;
    width: 190px;
    height: 190px;
    flex-shrink: 0;
}
.donut-center {
    position: absolute; inset: 0;
    display: flex; flex-direction: column;
    align-items: center; justify-content: center;
    pointer-events: none;
}
.donut-pct {
    font-family: 'DM Mono', monospace;
    font-size: 24px; font-weight: 700; color: var(--green); line-height: 1;
}
.donut-lbl {
    font-size: 9px; font-weight: 700; text-transform: uppercase;
    letter-spacing: .1em; color: var(--text-3); margin-top: 4px;
}

/* Shared legend */
.chart-legend {
    display: flex; flex-wrap: wrap;
    justify-content: center; gap: 10px 20px;
    margin-top: 18px;
}
.cl-item { display: flex; align-items: center; gap: 7px; }
.cl-dot { width: 10px; height: 10px; border-radius: 50%; flex-shrink: 0; }
.cl-label { font-size: 11.5px; font-weight: 600; color: var(--text-2); }
.cl-value { font-size: 10.5px; color: var(--text-3); margin-left: 2px; }

/* Stat row under doughnut */
.donut-stat-divider { width: 100%; height: 1px; background: var(--border); margin: 16px 0 12px; }
.donut-stat-row {
    display: flex; justify-content: space-between; width: 100%; gap: 12px;
}
.ds-item { flex: 1; text-align: center; }
.ds-num { font-family: 'DM Mono', monospace; font-size: 15px; font-weight: 700; }
.ds-lbl { font-size: 10px; color: var(--text-3); font-weight: 600; text-transform: uppercase;
          letter-spacing: .06em; margin-top: 3px; }
.ds-divider { width: 1px; background: var(--border); align-self: stretch; }

/* ── Payment Method breakdown bar card ── */
.pm-list { display: flex; flex-direction: column; gap: 16px; }
.pm-item { display: flex; flex-direction: column; gap: 6px; }
.pm-item-top { display: flex; align-items: center; gap: 9px; }
.pm-dot { width: 9px; height: 9px; border-radius: 50%; flex-shrink: 0; }
.pm-name { font-size: 12.5px; font-weight: 600; color: var(--text-1); flex: 1; min-width: 0;
           overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.pm-count { font-size: 11px; color: var(--text-3); margin-right: 4px; white-space: nowrap; }
.pm-amount { font-family: 'DM Mono', monospace; font-size: 12.5px; font-weight: 700;
             color: var(--text-1); white-space: nowrap; }
.pm-bar-track { height: 6px; background: var(--border); border-radius: 99px; overflow: hidden; }
.pm-bar-fill { height: 100%; border-radius: 99px; transition: width .8s cubic-bezier(.22,1,.36,1); }

/* ══ TABLE PANEL ══ */
.panel {
    background: var(--surface);
    border: 1.5px solid var(--border);
    border-radius: var(--radius-lg);
    box-shadow: var(--shadow-xs);
    overflow: hidden;
    margin-bottom: 24px;
}
.panel-hdr {
    padding: 14px 22px;
    border-bottom: 1.5px solid var(--border);
    display: flex; align-items: center; justify-content: space-between;
}
.panel-title {
    font-size: 13.5px; font-weight: 700; color: var(--text-1);
    display: flex; align-items: center; gap: 8px;
}
.panel-title i { color: var(--blue); }
.panel-count {
    font-size: 11px; background: var(--blue-light); color: var(--blue);
    border-radius: 20px; padding: 3px 11px; font-weight: 700; letter-spacing: .04em;
}
.data-table { width: 100%; border-collapse: collapse; }
.data-table thead th {
    background: var(--surface-2); padding: 10px 20px;
    font-size: 10px; font-weight: 700; text-transform: uppercase;
    letter-spacing: .1em; color: var(--text-3);
    border-bottom: 1.5px solid var(--border); white-space: nowrap; text-align: left;
}
.data-table tbody tr {
    border-bottom: 1px solid var(--border-light); transition: background .12s;
    animation: rowIn .2s ease both;
}
.data-table tbody tr:last-child { border-bottom: none; }
.data-table tbody tr:hover { background: #f6f8ff; }
@keyframes rowIn {
    from { opacity:0; transform:translateY(3px); }
    to   { opacity:1; transform:none; }
}
<?php foreach(range(1,10) as $i): ?>
.data-table tbody tr:nth-child(<?= $i ?>) { animation-delay: <?= ($i-1)*0.035 ?>s; }
<?php endforeach; ?>
.data-table td { padding: 12px 20px; color: var(--text-2); vertical-align: middle; font-size: 12.5px; }
.id-chip {
    font-family: 'DM Mono', monospace; font-size: 11px; font-weight: 500;
    background: var(--surface-2); border: 1px solid var(--border);
    border-radius: var(--radius-xs); padding: 3px 8px; color: var(--text-2); display: inline-block;
}
.patient-chip { display: inline-flex; align-items: center; gap: 7px; font-size: 12px; font-weight: 600; color: var(--text-1); }
.patient-avatar {
    width: 26px; height: 26px; border-radius: 50%;
    background: var(--indigo-light); color: var(--indigo);
    font-size: 10px; font-weight: 700;
    display: flex; align-items: center; justify-content: center;
    border: 1.5px solid var(--indigo-border);
}
.amount-mono { font-family: 'DM Mono', monospace; font-size: 13px; font-weight: 700; color: var(--text-1); }
.method-tag {
    display: inline-flex; align-items: center; gap: 5px;
    background: var(--surface-2); border: 1px solid var(--border);
    border-radius: 20px; padding: 3px 10px; font-size: 11px; font-weight: 600; color: var(--text-2);
}
.method-tag i { font-size: 10px; color: var(--text-3); }
.status-pill {
    display: inline-flex; align-items: center; gap: 4px;
    padding: 4px 10px; border-radius: 20px; font-size: 10.5px; font-weight: 700;
    letter-spacing: .05em; text-transform: uppercase; border: 1px solid;
}
.sp-paid   { background: var(--green-light); border-color: var(--green-border); color: var(--green); }
.sp-unpaid { background: var(--amber-light); border-color: var(--amber-border); color: var(--amber); }
.sp-other  { background: var(--red-light);   border-color: var(--red-border);   color: var(--red);   }
.date-mono { font-family: 'DM Mono', monospace; font-size: 11.5px; color: var(--text-3); }

/* ══ SIDEBAR MOBILE ══ */
.sidebar-toggle {
    display: none; background: var(--surface); color: var(--text-2);
    border: 1.5px solid var(--border); border-radius: var(--radius-sm);
    padding: 7px 13px; font-size: 13px; font-weight: 600;
    align-items: center; gap: 7px; cursor: pointer; margin-bottom: 14px;
    font-family: 'Sora', sans-serif;
}
@media (max-width: 768px) { .sidebar-toggle { display: inline-flex; } }
.sidebar-overlay {
    display: none; position: fixed; inset: 0;
    background: rgba(0,0,0,.45); z-index: 999; backdrop-filter: blur(3px);
}
.sidebar-overlay.show { display: block; }

/* ══ RESPONSIVE ══ */
@media (max-width: 1024px) {
    .kpi-grid { grid-template-columns: repeat(2, 1fr); }
}
@media (max-width: 768px) {
    .main-content { margin-left: 0; padding: 14px 14px 48px; }
    .kpi-grid { grid-template-columns: repeat(2, 1fr); gap: 10px; }
    .charts-row { grid-template-columns: 1fr; }
    .page-header { flex-direction: column; align-items: flex-start; gap: 10px; }
    .ph-right { width: 100%; }
    .data-table thead th:nth-child(4),
    .data-table tbody td:nth-child(4) { display: none; }
    .kpi-number { font-size: 22px; }
}
@media (max-width: 480px) {
    .kpi-grid { grid-template-columns: 1fr 1fr; gap: 8px; }
    .kpi-card { padding: 14px; }
    .kpi-number { font-size: 20px; }
    .data-table thead th:nth-child(2),
    .data-table tbody td:nth-child(2) { display: none; }
}
</style>
</head>
<body>

<div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>
<div class="main-sidebar" id="mainSidebar"><?php include 'billing_sidebar.php'; ?></div>

<div class="main-content">

    <!-- ── Page Header ── -->
    <div class="page-header">
        <div class="ph-left">
            <button class="sidebar-toggle" onclick="openSidebar()"><i class="bi bi-list"></i> Menu</button>
            <div class="ph-icon-wrap"><i class="bi bi-receipt-cutoff"></i></div>
            <div>
                <div class="ph-eyebrow">Hospital Billing System</div>
                <div class="ph-title">Billing Dashboard</div>
                <div class="ph-sub">Financial overview, payment tracking &amp; recent activity</div>
            </div>
        </div>
        <div class="ph-right">
            <div class="ph-datebadge">
                <div class="date-day">Today</div>
                <div class="date-full"><?= date('F d, Y') ?></div>
            </div>
        </div>
    </div>

    <!-- ── KPI Cards ── -->
    <div class="kpi-grid">
        <div class="kpi-card kc-blue">
            <div class="kpi-top">
                <div class="kpi-icon"><i class="bi bi-person-fill"></i></div>
                <span class="kpi-badge">Patients</span>
            </div>
            <div class="kpi-number"><?= number_format($total_patients) ?></div>
            <div class="kpi-label">Total Patients Billed</div>
            <div class="kpi-divider"></div>
            <div class="kpi-footer"><i class="bi bi-people" style="color:var(--blue);"></i> Unique patients on record</div>
        </div>
        <div class="kpi-card kc-teal">
            <div class="kpi-top">
                <div class="kpi-icon"><i class="bi bi-file-earmark-text-fill"></i></div>
                <span class="kpi-badge">Records</span>
            </div>
            <div class="kpi-number"><?= number_format($total_receipts) ?></div>
            <div class="kpi-label">Total Receipts Issued</div>
            <div class="kpi-divider"></div>
            <div class="kpi-footer"><i class="bi bi-receipt" style="color:var(--teal);"></i> All billing transactions</div>
        </div>
        <div class="kpi-card kc-green">
            <div class="kpi-top">
                <div class="kpi-icon"><i class="bi bi-check-circle-fill"></i></div>
                <span class="kpi-badge">Collected</span>
            </div>
            <div class="kpi-number" style="font-size:19px;">₱<?= number_format($total_paid, 0) ?></div>
            <div class="kpi-label">Total Amount Paid</div>
            <div class="kpi-divider"></div>
            <div class="kpi-footer"><i class="bi bi-arrow-down-circle" style="color:var(--green);"></i> Successfully collected</div>
        </div>
        <div class="kpi-card kc-red">
            <div class="kpi-top">
                <div class="kpi-icon"><i class="bi bi-exclamation-circle-fill"></i></div>
                <span class="kpi-badge">Outstanding</span>
            </div>
            <div class="kpi-number" style="font-size:19px;">₱<?= number_format($total_unpaid, 0) ?></div>
            <div class="kpi-label">Unpaid / Outstanding</div>
            <div class="kpi-divider"></div>
            <div class="kpi-footer"><i class="bi bi-clock-history" style="color:var(--red);"></i> Pending collection</div>
        </div>
    </div>

    <!-- ════════════════════════════════
         ROW 1 — BAR CHARTS (aligned)
    ════════════════════════════════ -->
    <div class="section-title">
        <i class="bi bi-bar-chart-fill" style="color:var(--blue);"></i> Revenue Trends
    </div>

    <div class="charts-row">

        <!-- Bar Chart 1: Monthly Revenue -->
        <div class="chart-card">
            <div class="chart-card-hdr">
                <div>
                    <div class="chart-card-title">
                        <i class="bi bi-graph-up-arrow" style="color:var(--blue);"></i>
                        Monthly Revenue
                    </div>
                    <div class="chart-card-sub">Last 6 months — total billed amounts</div>
                </div>
                <span class="chart-hdr-badge">
                    <span class="legend-dot" style="background:var(--blue);"></span> Revenue
                </span>
            </div>
            <div class="chart-card-body">
                <canvas id="monthlyChart" height="200"></canvas>
            </div>
        </div>

        <!-- Bar Chart 2: Daily Transactions (last 7 days) -->
        <div class="chart-card">
            <div class="chart-card-hdr">
                <div>
                    <div class="chart-card-title">
                        <i class="bi bi-calendar3-week" style="color:var(--teal);"></i>
                        Daily Collections
                    </div>
                    <div class="chart-card-sub">Last 7 days — daily revenue</div>
                </div>
                <span class="chart-hdr-badge">
                    <span class="legend-dot" style="background:var(--teal);"></span> Daily
                </span>
            </div>
            <div class="chart-card-body">
                <canvas id="dailyChart" height="200"></canvas>
            </div>
        </div>

    </div>

    <!-- ════════════════════════════════
         ROW 2 — PIE CHARTS (aligned)
    ════════════════════════════════ -->
    <div class="section-title">
        <i class="bi bi-pie-chart-fill" style="color:var(--green);"></i> Collection Breakdown
    </div>

    <div class="charts-row">

        <!-- Pie Chart 1: Paid vs Unpaid Doughnut -->
        <div class="chart-card">
            <div class="chart-card-hdr">
                <div>
                    <div class="chart-card-title">
                        <i class="bi bi-pie-chart-fill" style="color:var(--green);"></i>
                        Paid vs Unpaid
                    </div>
                    <div class="chart-card-sub">Overall collection rate</div>
                </div>
                <span class="chart-hdr-badge" style="background:var(--green-light);color:var(--green);border-color:var(--green-border);">
                    <?= $collection_rate ?>% Collected
                </span>
            </div>
            <div class="pie-card-body">
                <div class="donut-wrap">
                    <canvas id="statusChart"></canvas>
                    <div class="donut-center">
                        <div class="donut-pct"><?= $collection_rate ?>%</div>
                        <div class="donut-lbl">Collected</div>
                    </div>
                </div>

                <div class="chart-legend">
                    <div class="cl-item">
                        <div class="cl-dot" style="background:var(--green);"></div>
                        <span class="cl-label">Paid</span>
                        <span class="cl-value">₱<?= number_format($total_paid, 0) ?></span>
                    </div>
                    <div class="cl-item">
                        <div class="cl-dot" style="background:var(--red);"></div>
                        <span class="cl-label">Unpaid</span>
                        <span class="cl-value">₱<?= number_format($total_unpaid, 0) ?></span>
                    </div>
                </div>

                <div class="donut-stat-divider"></div>
                <div class="donut-stat-row">
                    <div class="ds-item">
                        <div class="ds-num" style="color:var(--green);">₱<?= number_format($total_paid/1000, 1) ?>K</div>
                        <div class="ds-lbl">Paid</div>
                    </div>
                    <div class="ds-divider"></div>
                    <div class="ds-item">
                        <div class="ds-num" style="color:var(--blue);">₱<?= number_format($total_billing/1000, 1) ?>K</div>
                        <div class="ds-lbl">Total Billed</div>
                    </div>
                    <div class="ds-divider"></div>
                    <div class="ds-item">
                        <div class="ds-num" style="color:var(--red);">₱<?= number_format($total_unpaid/1000, 1) ?>K</div>
                        <div class="ds-lbl">Unpaid</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Pie Chart 2: Revenue by Payment Method -->
        <div class="chart-card">
            <div class="chart-card-hdr">
                <div>
                    <div class="chart-card-title">
                        <i class="bi bi-credit-card-fill" style="color:var(--indigo);"></i>
                        Revenue by Payment Method
                    </div>
                    <div class="chart-card-sub">Share of total collected per channel</div>
                </div>
            </div>
            <div class="pie-card-body">
                <?php
                $pm_colors = ['#1b56f5','#0d9f6b','#c77b0a','#e12b2b','#5046e5','#0580a4'];
                $pm_max    = !empty($payment_methods) ? max(array_column($payment_methods, 'total')) : 1;
                if ($pm_max == 0) $pm_max = 1;
                ?>

                <!-- Pie canvas centred, same donut-wrap size -->
                <div class="donut-wrap">
                    <canvas id="paymentChart"></canvas>
                </div>

                <!-- Dynamic legend matching the pie slices -->
                <div class="chart-legend">
                    <?php foreach ($payment_methods as $idx => $pm): ?>
                    <div class="cl-item">
                        <div class="cl-dot" style="background:<?= $pm_colors[$idx % count($pm_colors)] ?>;"></div>
                        <span class="cl-label"><?= htmlspecialchars($pm['payment_method'] ?: 'N/A') ?></span>
                        <span class="cl-value"><?= $pm['count'] ?> tx</span>
                    </div>
                    <?php endforeach; ?>
                </div>

                <div class="donut-stat-divider"></div>

                <!-- Breakdown list inside the pie card -->
                <div class="pm-list" style="width:100%;">
                    <?php foreach ($payment_methods as $idx => $pm):
                        $color = $pm_colors[$idx % count($pm_colors)];
                        $pct   = $pm_max > 0 ? round(($pm['total'] / $pm_max) * 100) : 0;
                    ?>
                    <div class="pm-item">
                        <div class="pm-item-top">
                            <span class="pm-dot" style="background:<?= $color ?>;"></span>
                            <span class="pm-name"><?= htmlspecialchars($pm['payment_method'] ?: 'N/A') ?></span>
                            <span class="pm-count"><?= $pm['count'] ?> tx</span>
                            <span class="pm-amount">₱<?= number_format($pm['total'], 2) ?></span>
                        </div>
                        <div class="pm-bar-track">
                            <div class="pm-bar-fill" style="width:<?= $pct ?>%;background:<?= $color ?>;"></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

            </div>
        </div>

    </div>

    <!-- ── Recent Receipts Table ── -->
    <div class="section-title">
        <i class="bi bi-clock-history" style="color:var(--green);"></i> Recent Activity
    </div>

    <div class="panel">
        <div class="panel-hdr">
            <div class="panel-title">
                <i class="bi bi-receipt"></i> Recent Receipts
            </div>
            <span class="panel-count"><?= count($recent_receipts) ?> latest</span>
        </div>
        <div style="overflow-x:auto;">
        <table class="data-table">
        <thead>
            <tr>
                <th>Receipt ID</th>
                <th>Patient</th>
                <th>Amount</th>
                <th>Payment Method</th>
                <th>Status</th>
                <th>Date</th>
            </tr>
        </thead>
        <tbody>
        <?php if ($recent_receipts): ?>
            <?php foreach ($recent_receipts as $r):
                $status    = $r['status'];
                $pill      = $status === 'Paid' ? 'sp-paid' : ($status === 'Unpaid' ? 'sp-unpaid' : 'sp-other');
                $pill_icon = $status === 'Paid' ? 'bi-check-circle-fill' : 'bi-clock-fill';
            ?>
            <tr>
                <td><span class="id-chip">#<?= str_pad($r['receipt_id'], 5, '0', STR_PAD_LEFT) ?></span></td>
                <td>
                    <div class="patient-chip">
                        <div class="patient-avatar">P</div>
                        <?= htmlspecialchars($r['patient_id']) ?>
                    </div>
                </td>
                <td><span class="amount-mono">₱<?= number_format($r['grand_total'], 2) ?></span></td>
                <td>
                    <span class="method-tag">
                        <i class="bi bi-credit-card"></i>
                        <?= htmlspecialchars($r['payment_method'] ?: 'N/A') ?>
                    </span>
                </td>
                <td>
                    <span class="status-pill <?= $pill ?>">
                        <i class="bi <?= $pill_icon ?>"></i> <?= htmlspecialchars($status) ?>
                    </span>
                </td>
                <td><span class="date-mono"><?= date('M d, Y', strtotime($r['created_at'])) ?></span></td>
            </tr>
            <?php endforeach; ?>
        <?php else: ?>
            <tr>
                <td colspan="6" style="text-align:center;padding:48px;color:var(--text-3);">
                    <i class="bi bi-inbox" style="font-size:28px;display:block;margin-bottom:10px;"></i>
                    No receipts found.
                </td>
            </tr>
        <?php endif; ?>
        </tbody>
        </table>
        </div>
    </div>

</div><!-- /main-content -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
/* ── Sidebar ── */
function openSidebar()  { document.getElementById('mainSidebar').classList.add('open');    document.getElementById('sidebarOverlay').classList.add('show'); }
function closeSidebar() { document.getElementById('mainSidebar').classList.remove('open'); document.getElementById('sidebarOverlay').classList.remove('show'); }

/* ── Chart Defaults ── */
Chart.defaults.font.family = "'Sora', sans-serif";
Chart.defaults.font.size   = 11.5;
Chart.defaults.color       = '#8b93ad';
const gridColor = 'rgba(227,231,240,0.65)';

/* ── Data from PHP ── */
const pmLabels = <?= json_encode(array_column($payment_methods, 'payment_method')) ?>;
const pmValues = <?= json_encode(array_map(fn($p) => floatval($p['total']), $payment_methods)) ?>;
const pmColors = ['#1b56f5','#0d9f6b','#c77b0a','#e12b2b','#5046e5','#0580a4'];

const monthlyLabels = <?= json_encode(array_column($monthly, 'month')) ?>;
const monthlyValues = <?= json_encode(array_map(fn($m) => floatval($m['total']), $monthly)) ?>;

const dailyLabels = <?= json_encode(array_column($daily, 'day_label')) ?>;
const dailyValues = <?= json_encode(array_map(fn($d) => floatval($d['total']), $daily)) ?>;

/* ── Shared tooltip style ── */
const tooltipStyle = {
    backgroundColor: '#0d1117', titleColor: '#fff', bodyColor: '#8b93ad',
    padding: 12, cornerRadius: 8,
    borderColor: 'rgba(255,255,255,.06)', borderWidth: 1,
    callbacks: {
        label: ctx => '  ₱' + ctx.parsed.y.toLocaleString('en-PH', { minimumFractionDigits: 2 })
    }
};
const pieTooltipStyle = {
    backgroundColor: '#0d1117', titleColor: '#fff', bodyColor: '#8b93ad',
    padding: 12, cornerRadius: 8,
    callbacks: {
        label: ctx => '  ₱' + ctx.parsed.toLocaleString('en-PH', { minimumFractionDigits: 2 })
    }
};

/* ══════════════════════════════════
   BAR CHART 1 — Monthly Revenue
══════════════════════════════════*/
new Chart(document.getElementById('monthlyChart'), {
    type: 'bar',
    data: {
        labels: monthlyLabels,
        datasets: [{
            label: 'Revenue',
            data: monthlyValues,
            backgroundColor: 'rgba(27,86,245,0.85)',
            hoverBackgroundColor: '#1040d8',
            borderRadius: 6,
            borderSkipped: false,
            barPercentage: 0.62,
            categoryPercentage: 0.72,
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        plugins: {
            legend: { display: false },
            tooltip: tooltipStyle,
        },
        scales: {
            x: {
                grid: { display: false },
                ticks: { font: { size: 11, weight: '600' } },
                border: { display: false }
            },
            y: {
                beginAtZero: true,
                grid: { color: gridColor },
                border: { display: false, dash: [4, 4] },
                ticks: {
                    callback: v => v >= 1000 ? '₱' + (v/1000).toFixed(0) + 'K' : '₱' + v
                }
            }
        }
    }
});

/* ══════════════════════════════════
   BAR CHART 2 — Daily Revenue
══════════════════════════════════*/
new Chart(document.getElementById('dailyChart'), {
    type: 'bar',
    data: {
        labels: dailyLabels,
        datasets: [{
            label: 'Daily Revenue',
            data: dailyValues,
            backgroundColor: 'rgba(5,128,164,0.82)',
            hoverBackgroundColor: '#046b8c',
            borderRadius: 6,
            borderSkipped: false,
            barPercentage: 0.62,
            categoryPercentage: 0.72,
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        plugins: {
            legend: { display: false },
            tooltip: tooltipStyle,
        },
        scales: {
            x: {
                grid: { display: false },
                ticks: { font: { size: 11, weight: '600' } },
                border: { display: false }
            },
            y: {
                beginAtZero: true,
                grid: { color: gridColor },
                border: { display: false, dash: [4, 4] },
                ticks: {
                    callback: v => v >= 1000 ? '₱' + (v/1000).toFixed(0) + 'K' : '₱' + v
                }
            }
        }
    }
});

/* ══════════════════════════════════
   PIE CHART 1 — Paid vs Unpaid (Doughnut)
══════════════════════════════════*/
new Chart(document.getElementById('statusChart'), {
    type: 'doughnut',
    data: {
        labels: ['Paid', 'Unpaid'],
        datasets: [{
            data: [<?= floatval($total_paid) ?>, <?= floatval($total_unpaid) ?>],
            backgroundColor: ['#0d9f6b', '#e12b2b'],
            borderColor: '#fff',
            borderWidth: 3,
            hoverOffset: 6,
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        cutout: '72%',
        plugins: {
            legend: { display: false },
            tooltip: pieTooltipStyle,
        }
    }
});

/* ══════════════════════════════════
   PIE CHART 2 — Revenue by Payment Method
══════════════════════════════════*/
new Chart(document.getElementById('paymentChart'), {
    type: 'pie',
    data: {
        labels: pmLabels,
        datasets: [{
            data: pmValues,
            backgroundColor: pmColors,
            borderColor: '#fff',
            borderWidth: 3,
            hoverOffset: 6,
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        plugins: {
            legend: { display: false },
            tooltip: pieTooltipStyle,
        }
    }
});
</script>
</body>
</html>