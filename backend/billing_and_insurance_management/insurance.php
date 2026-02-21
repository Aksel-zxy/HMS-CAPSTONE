<?php
include '../../SQL/config.php';

/* ── COLOR MAP PER INSURANCE COMPANY ── */
function cardGradient($company) {
    return match ($company) {
        'PhilHealth'  => ['#0d2b6e', '#1a56c4', '#4a90d9'],
        'Maxicare'    => ['#0a6b5e', '#0f9b8e', '#38ef7d'],
        'Medicard'    => ['#4a0080', '#8e2de2', '#c470f0'],
        'Intellicare' => ['#b35a00', '#f7971e', '#ffd200'],
        default       => ['#1a1a2e', '#16213e', '#0f3460'],
    };
}

function cardAccent($company) {
    return match ($company) {
        'PhilHealth'  => 'rgba(74,144,217,.4)',
        'Maxicare'    => 'rgba(56,239,125,.35)',
        'Medicard'    => 'rgba(196,112,240,.4)',
        'Intellicare' => 'rgba(255,210,0,.4)',
        default       => 'rgba(255,255,255,.15)',
    };
}

function companyIcon($company) {
    return match ($company) {
        'PhilHealth'  => 'bi-hospital',
        'Maxicare'    => 'bi-heart-pulse',
        'Medicard'    => 'bi-shield-heart',
        'Intellicare' => 'bi-shield-plus',
        default       => 'bi-shield-check',
    };
}

$list = $conn->query("SELECT * FROM patient_insurance ORDER BY created_at DESC");
$rows = [];
while ($row = $list->fetch_assoc()) $rows[] = $row;
$total = count($rows);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<title>Patient Insurance — HMS</title>

<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@400;600;700&family=Figtree:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<link rel="stylesheet" href="assets/css/billing_sidebar.css">

<style>
/* ════════════════════════════════════════
   DESIGN TOKENS
════════════════════════════════════════ */
:root {
  --sidebar-w:     250px;
  --sidebar-w-sm:  200px;

  --navy:          #0c1e3c;
  --navy-2:        #152a50;
  --ink:           #1c2b3a;
  --ink-2:         #3d5068;
  --ink-3:         #7b92aa;
  --ink-4:         #b0c2d4;
  --border:        #dde6f0;
  --border-2:      #c8d8e8;
  --surface:       #eef2f7;
  --surface-2:     #f7f9fc;
  --paper:         #ffffff;

  --blue:          #1a56db;
  --blue-2:        #3b82f6;
  --teal:          #0d9488;
  --green:         #059669;
  --amber:         #d97706;
  --red:           #dc2626;
  --purple:        #7c3aed;

  --radius:        12px;
  --radius-lg:     16px;
  --radius-xl:     22px;
  --shadow-xs:     0 1px 3px rgba(12,30,60,.06);
  --shadow-sm:     0 2px 8px rgba(12,30,60,.08);
  --shadow:        0 4px 20px rgba(12,30,60,.1);
  --shadow-lg:     0 12px 48px rgba(12,30,60,.16);
  --shadow-xl:     0 24px 80px rgba(12,30,60,.22);

  --ff-head:   'Cormorant Garamond', Georgia, serif;
  --ff-body:   'Figtree', system-ui, sans-serif;
  --ff-mono:   'JetBrains Mono', 'Courier New', monospace;
  --tr:        .25s ease;
}

*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

body {
  font-family: var(--ff-body);
  background: var(--surface);
  color: var(--ink);
  margin: 0;
  min-height: 100vh;
}

/* ════════════════════════════════════════
   LAYOUT — CENTERED CONTENT WRAPPER
════════════════════════════════════════ */
.cw {
  margin-left: var(--sidebar-w);
  padding: 32px 40px 72px;
  transition: margin-left var(--tr);
  display: flex;
  flex-direction: column;
  align-items: center;   /* ← centers everything horizontally */
}
.cw.sidebar-collapsed { margin-left: 0; }

/* Inner width cap — gives centered, comfortable reading area */
.cw-inner {
  width: 100%;
  max-width: 1100px;
}

/* ════════════════════════════════════════
   MASTHEAD
════════════════════════════════════════ */
.masthead {
  background: var(--paper);
  border: 1px solid var(--border);
  border-radius: var(--radius-xl);
  box-shadow: var(--shadow);
  padding: 28px 36px;
  margin-bottom: 22px;
  position: relative;
  overflow: hidden;
}
.masthead::before {
  content: '';
  position: absolute; top: 0; left: 0; right: 0; height: 4px;
  background: linear-gradient(90deg, var(--navy) 0%, var(--blue) 50%, var(--teal) 100%);
}
/* Subtle background pattern */
.masthead::after {
  content: '';
  position: absolute;
  top: -80px; right: -60px;
  width: 280px; height: 280px;
  border-radius: 50%;
  background: radial-gradient(circle, rgba(26,86,219,.06) 0%, transparent 70%);
  pointer-events: none;
}

.masthead-inner {
  display: flex; align-items: center;
  justify-content: space-between; gap: 20px; flex-wrap: wrap;
}
.masthead-left { display: flex; align-items: center; gap: 18px; }
.masthead-icon {
  width: 60px; height: 60px;
  background: linear-gradient(135deg, var(--navy), var(--blue));
  border-radius: var(--radius-lg);
  display: flex; align-items: center; justify-content: center;
  color: #fff; font-size: 1.6rem;
  box-shadow: 0 6px 20px rgba(26,86,219,.35);
  flex-shrink: 0;
}
.masthead-title {
  font-family: var(--ff-head);
  font-size: 2rem; font-weight: 700;
  color: var(--navy); letter-spacing: -.01em; line-height: 1;
}
.masthead-sub {
  font-size: .78rem; color: var(--ink-3); margin-top: 5px;
  text-transform: uppercase; letter-spacing: .08em; font-weight: 500;
}

.masthead-stats {
  display: flex; gap: 20px; flex-wrap: wrap; align-items: center;
}
.stat-chip {
  display: flex; flex-direction: column; align-items: center;
  background: var(--surface-2); border: 1px solid var(--border);
  border-radius: var(--radius); padding: 10px 20px;
  min-width: 90px;
}
.stat-chip-val {
  font-family: var(--ff-head); font-size: 1.6rem; font-weight: 700;
  color: var(--navy); line-height: 1;
}
.stat-chip-lbl {
  font-size: .65rem; font-weight: 700; text-transform: uppercase;
  letter-spacing: .08em; color: var(--ink-3); margin-top: 3px;
}

/* ════════════════════════════════════════
   TOOLBAR
════════════════════════════════════════ */
.toolbar {
  display: flex; gap: 10px; align-items: center;
  flex-wrap: wrap; margin-bottom: 20px;
}
.search-wrap {
  position: relative; flex: 1 1 240px; max-width: 340px;
}
.search-wrap .si {
  position: absolute; left: 13px; top: 50%;
  transform: translateY(-50%); color: var(--ink-4);
  font-size: .9rem; pointer-events: none;
}
.search-input {
  width: 100%; padding: 10px 14px 10px 36px;
  border: 1.5px solid var(--border-2); border-radius: var(--radius);
  font-family: var(--ff-body); font-size: .86rem;
  color: var(--ink); background: var(--paper);
  outline: none; transition: border-color var(--tr), box-shadow var(--tr);
  box-shadow: var(--shadow-xs);
}
.search-input:focus {
  border-color: var(--blue-2);
  box-shadow: 0 0 0 3px rgba(59,130,246,.12), var(--shadow-xs);
}

.filter-tabs {
  display: flex; gap: 4px; flex-wrap: wrap;
}
.ftab {
  padding: 8px 14px; border-radius: var(--radius);
  font-family: var(--ff-body); font-size: .76rem; font-weight: 600;
  border: 1.5px solid var(--border-2);
  background: var(--paper); color: var(--ink-2);
  cursor: pointer; transition: all .15s;
  display: inline-flex; align-items: center; gap: 5px;
  white-space: nowrap;
}
.ftab:hover { background: var(--surface-2); border-color: var(--ink-3); }
.ftab.active { background: var(--navy); color: #fff; border-color: var(--navy); box-shadow: var(--shadow-sm); }

/* ════════════════════════════════════════
   INSURANCE CARD GRID
════════════════════════════════════════ */
.cards-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
  gap: 18px;
  margin-bottom: 24px;
}

/* ── Individual Insurance Record Card ── */
.ins-record-card {
  background: var(--paper);
  border: 1px solid var(--border);
  border-radius: var(--radius-xl);
  box-shadow: var(--shadow-sm);
  overflow: hidden;
  transition: transform var(--tr), box-shadow var(--tr);
  display: flex; flex-direction: column;
  cursor: pointer;
  animation: riseUp .4s ease both;
}
.ins-record-card:hover {
  transform: translateY(-4px);
  box-shadow: var(--shadow-lg);
}

/* Top colored band (mini insurance card preview) */
.irc-top {
  height: 110px;
  position: relative;
  overflow: hidden;
  padding: 16px 20px;
  display: flex; flex-direction: column; justify-content: space-between;
}
.irc-top::after {
  content: '';
  position: absolute; bottom: -40px; right: -30px;
  width: 160px; height: 160px; border-radius: 50%;
  background: rgba(255,255,255,.08);
  pointer-events: none;
}
.irc-top::before {
  content: '';
  position: absolute; top: -50px; left: -20px;
  width: 120px; height: 120px; border-radius: 50%;
  background: rgba(255,255,255,.06);
  pointer-events: none;
}

.irc-company-row {
  display: flex; align-items: center;
  justify-content: space-between; position: relative; z-index: 1;
}
.irc-company-name {
  font-size: .68rem; font-weight: 700;
  text-transform: uppercase; letter-spacing: .12em;
  color: rgba(255,255,255,.85);
}
.irc-status-dot {
  width: 8px; height: 8px; border-radius: 50%;
  border: 2px solid rgba(255,255,255,.4);
}
.irc-status-dot.active   { background: #4ade80; border-color: #4ade80; box-shadow: 0 0 6px #4ade80; }
.irc-status-dot.inactive { background: #f87171; border-color: #f87171; }
.irc-status-dot.pending  { background: #fbbf24; border-color: #fbbf24; }

.irc-number {
  font-family: var(--ff-mono);
  font-size: .82rem; letter-spacing: 2px;
  color: rgba(255,255,255,.9); position: relative; z-index: 1;
}

/* Body section */
.irc-body {
  padding: 16px 20px 14px;
  flex: 1;
}
.irc-name {
  font-family: var(--ff-head);
  font-size: 1.25rem; font-weight: 700;
  color: var(--navy); line-height: 1.1;
  margin-bottom: 4px;
}
.irc-meta {
  font-size: .76rem; color: var(--ink-3); margin-bottom: 14px;
  display: flex; align-items: center; gap: 6px;
}
.irc-meta i { font-size: .78rem; }

.irc-details {
  display: grid; grid-template-columns: 1fr 1fr;
  gap: 10px;
}
.irc-detail-item {}
.irc-detail-label {
  font-size: .63rem; font-weight: 700; text-transform: uppercase;
  letter-spacing: .08em; color: var(--ink-4); margin-bottom: 2px;
}
.irc-detail-val {
  font-size: .82rem; font-weight: 600; color: var(--ink);
}
.irc-detail-val.discount { color: var(--green); }
.irc-detail-val.mono    { font-family: var(--ff-mono); font-size: .76rem; }

/* Footer */
.irc-footer {
  padding: 12px 20px;
  border-top: 1px solid var(--border);
  background: var(--surface-2);
  display: flex; align-items: center; justify-content: space-between;
}
.irc-company-badge {
  display: inline-flex; align-items: center; gap: 5px;
  padding: 3px 10px; border-radius: 999px;
  font-size: .71rem; font-weight: 700;
}
.badge-philhealth  { background: #dbeafe; color: #1d4ed8; }
.badge-maxicare    { background: #ccfbf1; color: #0f766e; }
.badge-medicard    { background: #ede9fe; color: #6d28d9; }
.badge-intellicare { background: #fef3c7; color: #b45309; }
.badge-default     { background: var(--surface); color: var(--ink-2); }

.btn-view-card {
  display: inline-flex; align-items: center; gap: 5px;
  padding: 6px 14px; border-radius: var(--radius);
  background: var(--navy); color: #fff;
  border: none; font-family: var(--ff-body);
  font-size: .76rem; font-weight: 600;
  cursor: pointer; transition: all .15s;
  box-shadow: var(--shadow-xs);
  text-decoration: none;
}
.btn-view-card:hover { background: var(--blue); transform: scale(1.02); }

/* ════════════════════════════════════════
   EMPTY STATE
════════════════════════════════════════ */
.empty-state {
  text-align: center; padding: 80px 20px;
  color: var(--ink-3);
}
.empty-state i { font-size: 3rem; display: block; margin-bottom: 16px; opacity: .25; }
.empty-state h3 { font-family: var(--ff-head); font-size: 1.4rem; color: var(--ink-2); margin-bottom: 6px; }
.empty-state p  { font-size: .85rem; }

/* ════════════════════════════════════════
   MODAL — INSURANCE CARD VIEWER
════════════════════════════════════════ */
.modal-content {
  border-radius: var(--radius-xl);
  border: none;
  box-shadow: var(--shadow-xl);
  overflow: hidden;
}
.modal-header {
  background: var(--navy);
  color: #fff; padding: 18px 24px; border-bottom: none;
  display: flex; align-items: center; gap: 10px;
}
.modal-header-icon {
  width: 36px; height: 36px; border-radius: 8px;
  background: rgba(255,255,255,.15);
  display: flex; align-items: center; justify-content: center;
  font-size: 1rem;
}
.modal-title {
  font-family: var(--ff-head); font-size: 1.15rem; font-weight: 700;
  color: #fff; flex: 1;
}
.modal-sub { font-size: .73rem; color: rgba(255,255,255,.55); margin-top: 1px; }
.modal-header .btn-close { filter: invert(1) brightness(1.5); }

.modal-body {
  padding: 28px 24px 24px;
  background: #f4f7fb;
}

/* ── The Card itself ── */
.card-stage {
  display: flex; justify-content: center;
  perspective: 1000px;
  margin-bottom: 22px;
}
.ins-card {
  width: 360px; max-width: 100%;
  height: 220px;
  border-radius: 20px;
  padding: 22px 26px;
  color: #fff;
  position: relative; overflow: hidden;
  box-shadow: 0 24px 64px rgba(0,0,0,.45), 0 4px 12px rgba(0,0,0,.2);
  transform-style: preserve-3d;
  transition: transform .5s cubic-bezier(.23,1,.32,1);
}
.ins-card:hover { transform: rotateY(6deg) rotateX(-3deg) scale(1.03); }

/* Decorative circles */
.ins-card .deco1 {
  position: absolute; top: -70px; right: -50px;
  width: 220px; height: 220px; border-radius: 50%;
  background: rgba(255,255,255,.08); pointer-events: none;
}
.ins-card .deco2 {
  position: absolute; bottom: -90px; left: -50px;
  width: 240px; height: 240px; border-radius: 50%;
  background: rgba(255,255,255,.05); pointer-events: none;
}
.ins-card .deco3 {
  position: absolute; top: 50%; left: 50%;
  transform: translate(-50%,-50%);
  width: 180px; height: 180px; border-radius: 50%;
  background: rgba(255,255,255,.03); pointer-events: none;
}

/* Card inner sections */
.ic-top {
  display: flex; justify-content: space-between; align-items: center;
  margin-bottom: 12px; position: relative; z-index: 1;
}
.ic-company {
  font-size: .65rem; font-weight: 700;
  letter-spacing: .15em; text-transform: uppercase;
  opacity: .85;
}
.ic-logo { display: flex; gap: 4px; }
.ic-logo span {
  width: 22px; height: 22px; border-radius: 50%;
  opacity: .6;
}

.ic-chip {
  width: 44px; height: 34px;
  background: linear-gradient(135deg, #c9952a, #f5d475, #c9952a);
  border-radius: 6px;
  margin-bottom: 14px;
  position: relative; z-index: 1;
  box-shadow: 0 2px 10px rgba(0,0,0,.3);
  display: flex; align-items: center; justify-content: center;
}
.ic-chip::before {
  content: '';
  position: absolute;
  width: 62%; height: 55%;
  border: 1.5px solid rgba(0,0,0,.18);
  border-radius: 4px;
}
.ic-chip::after {
  content: '';
  position: absolute; top: 50%; left: 0; right: 0;
  height: 1px; background: rgba(0,0,0,.12);
}

.ic-number {
  font-family: var(--ff-mono);
  font-size: 1.02rem; letter-spacing: 3px;
  margin-bottom: 16px; position: relative; z-index: 1;
  text-shadow: 0 1px 6px rgba(0,0,0,.3);
}

.ic-bottom {
  position: relative; z-index: 1;
  display: flex; justify-content: space-between; align-items: flex-end;
}
.ic-holder-label {
  font-size: .55rem; letter-spacing: .1em;
  text-transform: uppercase; opacity: .55; margin-bottom: 3px;
}
.ic-holder-name {
  font-family: var(--ff-head);
  font-size: 1.1rem; font-weight: 700;
  letter-spacing: .03em; line-height: 1;
}
.ic-promo { text-align: right; }
.ic-promo-label { font-size: .55rem; text-transform: uppercase; letter-spacing: .08em; opacity: .6; margin-bottom: 2px; }
.ic-discount {
  font-family: var(--ff-mono);
  font-size: .95rem; font-weight: 700;
  background: rgba(255,255,255,.2); backdrop-filter: blur(4px);
  padding: 2px 8px; border-radius: 5px;
  border: 1px solid rgba(255,255,255,.25);
}

/* Detail pills below card */
.detail-grid {
  display: grid; grid-template-columns: 1fr 1fr 1fr;
  gap: 10px; margin-top: 6px;
}
.detail-cell {
  background: var(--paper); border: 1px solid var(--border);
  border-radius: var(--radius); padding: 12px 14px;
  text-align: center; box-shadow: var(--shadow-xs);
}
.detail-cell-label {
  font-size: .62rem; font-weight: 700; text-transform: uppercase;
  letter-spacing: .07em; color: var(--ink-4); margin-bottom: 4px;
}
.detail-cell-val {
  font-size: .85rem; font-weight: 700; color: var(--ink);
}
.detail-cell-val.green  { color: var(--green); }
.detail-cell-val.status-active   { color: var(--green); }
.detail-cell-val.status-inactive { color: var(--red); }
.detail-cell-val.status-pending  { color: var(--amber); }

/* ════════════════════════════════════════
   ANIMATIONS
════════════════════════════════════════ */
@keyframes riseUp {
  from { opacity: 0; transform: translateY(16px); }
  to   { opacity: 1; transform: translateY(0); }
}

/* ════════════════════════════════════════
   RESPONSIVE
════════════════════════════════════════ */
@media (max-width: 900px) {
  .cw { margin-left: var(--sidebar-w-sm); padding: 60px 16px 50px; }
  .cw.sidebar-collapsed { margin-left: 0; }
  .masthead { padding: 20px; }
  .masthead-title { font-size: 1.5rem; }
  .cards-grid { grid-template-columns: 1fr; }
  .detail-grid { grid-template-columns: 1fr 1fr; }
}
@media (max-width: 600px) {
  .cw { margin-left: 0 !important; padding: 56px 10px 40px; }
  .masthead-stats { display: none; }
  .toolbar { flex-direction: column; align-items: stretch; }
  .search-wrap { max-width: 100%; }
  .detail-grid { grid-template-columns: 1fr 1fr; }
  .ins-card { height: auto; min-height: 210px; }
}
@supports (padding: env(safe-area-inset-bottom)) {
  .cw { padding-bottom: calc(72px + env(safe-area-inset-bottom)); }
}
</style>
</head>
<body>

<?php include 'billing_sidebar.php'; ?>

<div class="cw" id="mainCw">
<div class="cw-inner">

  <!-- ════ MASTHEAD ════ -->
  <div class="masthead">
    <div class="masthead-inner">
      <div class="masthead-left">
        <div class="masthead-icon"><i class="bi bi-shield-check"></i></div>
        <div>
          <div class="masthead-title">Patient Insurance</div>
          <div class="masthead-sub">HMS · Insurance Registry · Coverage Management</div>
        </div>
      </div>
      <div class="masthead-stats">
        <div class="stat-chip">
          <div class="stat-chip-val"><?= $total ?></div>
          <div class="stat-chip-lbl">Total Records</div>
        </div>
        <div class="stat-chip">
          <div class="stat-chip-val">
            <?= count(array_filter($rows, fn($r) => strtolower($r['status'] ?? 'active') === 'active')) ?>
          </div>
          <div class="stat-chip-lbl">Active</div>
        </div>
        <div class="stat-chip">
          <div class="stat-chip-val">
            <?= count(array_unique(array_column($rows, 'insurance_company'))) ?>
          </div>
          <div class="stat-chip-lbl">Companies</div>
        </div>
      </div>
    </div>
  </div>

  <!-- ════ TOOLBAR ════ -->
  <div class="toolbar">
    <div class="search-wrap">
      <i class="bi bi-search si"></i>
      <input type="text" class="search-input" id="searchInput"
             placeholder="Search name, company, insurance number…">
    </div>
    <div class="filter-tabs" id="filterTabs">
      <button class="ftab active" data-filter="all" onclick="filterCards(this,'all')">
        <i class="bi bi-list-ul"></i> All
      </button>
      <button class="ftab" data-filter="philhealth" onclick="filterCards(this,'philhealth')">
        <i class="bi bi-hospital"></i> PhilHealth
      </button>
      <button class="ftab" data-filter="maxicare" onclick="filterCards(this,'maxicare')">
        <i class="bi bi-heart-pulse"></i> Maxicare
      </button>
      <button class="ftab" data-filter="medicard" onclick="filterCards(this,'medicard')">
        <i class="bi bi-shield-heart"></i> Medicard
      </button>
      <button class="ftab" data-filter="intellicare" onclick="filterCards(this,'intellicare')">
        <i class="bi bi-shield-plus"></i> Intellicare
      </button>
    </div>
  </div>

  <!-- ════ CARDS GRID ════ -->
  <div class="cards-grid" id="cardsGrid">
    <?php if (empty($rows)): ?>
      <div class="empty-state" style="grid-column:1/-1;">
        <i class="bi bi-shield-x"></i>
        <h3>No Insurance Records</h3>
        <p>No patient insurance records have been added yet.</p>
      </div>
    <?php else: ?>
      <?php foreach ($rows as $i => $row):
        $colors  = cardGradient($row['insurance_company']);
        $co      = $row['insurance_company'];
        $coKey   = strtolower(str_replace(' ', '', $co));
        $status  = strtolower($row['status'] ?? 'active');
        $discount = $row['discount_type'] === 'Percentage'
          ? htmlspecialchars($row['discount_value']) . '%'
          : '₱' . number_format($row['discount_value'], 2);
        $modalId = 'modal_' . intval($row['patient_insurance_id']);
        $numParts = implode(' ', str_split(preg_replace('/\s+/', '', $row['insurance_number']), 4));

        $badgeClass = match($co) {
          'PhilHealth'  => 'badge-philhealth',
          'Maxicare'    => 'badge-maxicare',
          'Medicard'    => 'badge-medicard',
          'Intellicare' => 'badge-intellicare',
          default       => 'badge-default',
        };
        $icon = match($co) {
          'PhilHealth'  => 'bi-hospital',
          'Maxicare'    => 'bi-heart-pulse',
          'Medicard'    => 'bi-shield-heart',
          'Intellicare' => 'bi-shield-plus',
          default       => 'bi-shield-check',
        };
      ?>
      <div class="ins-record-card record-card"
           data-company="<?= $coKey ?>"
           data-search="<?= htmlspecialchars(strtolower($row['full_name'] . ' ' . $co . ' ' . $row['insurance_number'])) ?>"
           style="animation-delay:<?= $i * 0.06 ?>s"
           data-bs-toggle="modal" data-bs-target="#<?= $modalId ?>">

        <!-- Top colored band -->
        <div class="irc-top" style="background:linear-gradient(145deg,<?= $colors[0] ?>,<?= $colors[1] ?> 60%,<?= $colors[2] ?>);">
          <div class="irc-company-row">
            <span class="irc-company-name"><?= htmlspecialchars($co) ?></span>
            <span class="irc-status-dot <?= $status ?>"></span>
          </div>
          <span class="irc-number"><?= $numParts ?></span>
        </div>

        <!-- Body -->
        <div class="irc-body">
          <div class="irc-name"><?= htmlspecialchars($row['full_name']) ?></div>
          <div class="irc-meta">
            <i class="bi bi-people"></i>
            <?= htmlspecialchars($row['relationship_to_insured']) ?>
          </div>
          <div class="irc-details">
            <div class="irc-detail-item">
              <div class="irc-detail-label">Promo Plan</div>
              <div class="irc-detail-val"><?= htmlspecialchars($row['promo_name']) ?></div>
            </div>
            <div class="irc-detail-item">
              <div class="irc-detail-label">Discount</div>
              <div class="irc-detail-val discount"><?= $discount ?></div>
            </div>
            <div class="irc-detail-item">
              <div class="irc-detail-label">Type</div>
              <div class="irc-detail-val"><?= htmlspecialchars($row['discount_type']) ?></div>
            </div>
            <div class="irc-detail-item">
              <div class="irc-detail-label">Status</div>
              <div class="irc-detail-val" style="color:<?= $status === 'active' ? 'var(--green)' : ($status === 'inactive' ? 'var(--red)' : 'var(--amber)') ?>">
                <?= ucfirst($status) ?>
              </div>
            </div>
          </div>
        </div>

        <!-- Footer -->
        <div class="irc-footer">
          <span class="irc-company-badge <?= $badgeClass ?>">
            <i class="bi <?= $icon ?>"></i>
            <?= htmlspecialchars($co) ?>
          </span>
          <span class="btn-view-card">
            <i class="bi bi-credit-card-2-front"></i> View Card
          </span>
        </div>
      </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>

</div><!-- /cw-inner -->
</div><!-- /cw -->

<!-- ════════════════════════════════════
     MODALS — Insurance Card Viewer
════════════════════════════════════ -->
<?php foreach ($rows as $row):
  $colors  = cardGradient($row['insurance_company']);
  $accent  = cardAccent($row['insurance_company']);
  $co      = $row['insurance_company'];
  $modalId = 'modal_' . intval($row['patient_insurance_id']);
  $status  = strtolower($row['status'] ?? 'active');
  $discount = $row['discount_type'] === 'Percentage'
    ? htmlspecialchars($row['discount_value']) . '%'
    : '₱' . number_format($row['discount_value'], 2);
  $numParts = implode(' ', str_split(preg_replace('/\s+/', '', $row['insurance_number']), 4));
  $icon = match($co) {
    'PhilHealth'  => 'bi-hospital',
    'Maxicare'    => 'bi-heart-pulse',
    'Medicard'    => 'bi-shield-heart',
    'Intellicare' => 'bi-shield-plus',
    default       => 'bi-shield-check',
  };
?>
<div class="modal fade" id="<?= $modalId ?>" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" style="max-width:440px;">
    <div class="modal-content">

      <div class="modal-header">
        <div class="modal-header-icon"><i class="bi <?= $icon ?>"></i></div>
        <div>
          <div class="modal-title"><?= htmlspecialchars($row['full_name']) ?></div>
          <div class="modal-sub"><?= htmlspecialchars($co) ?> · Insurance Card</div>
        </div>
        <button type="button" class="btn-close ms-auto" data-bs-dismiss="modal"></button>
      </div>

      <div class="modal-body">

        <!-- The Card -->
        <div class="card-stage">
          <div class="ins-card"
               style="background:linear-gradient(150deg,<?= $colors[0] ?>,<?= $colors[1] ?> 52%,<?= $colors[2] ?>);">
            <div class="deco1"></div>
            <div class="deco2"></div>
            <div class="deco3"></div>

            <div class="ic-top">
              <div class="ic-company"><?= htmlspecialchars($co) ?></div>
              <div class="ic-logo">
                <span style="background:<?= $accent ?>;border:1.5px solid rgba(255,255,255,.3);"></span>
                <span style="background:rgba(255,255,255,.22);border:1.5px solid rgba(255,255,255,.3);"></span>
              </div>
            </div>

            <div class="ic-chip"></div>

            <div class="ic-number"><?= $numParts ?></div>

            <div class="ic-bottom">
              <div>
                <div class="ic-holder-label">Card Holder</div>
                <div class="ic-holder-name"><?= htmlspecialchars(strtoupper($row['full_name'])) ?></div>
              </div>
              <div class="ic-promo">
                <div class="ic-promo-label"><?= htmlspecialchars($row['promo_name']) ?></div>
                <div class="ic-discount"><?= $discount ?></div>
              </div>
            </div>
          </div>
        </div>

        <!-- Detail cells -->
        <div class="detail-grid">
          <div class="detail-cell">
            <div class="detail-cell-label">Relationship</div>
            <div class="detail-cell-val"><?= htmlspecialchars($row['relationship_to_insured']) ?></div>
          </div>
          <div class="detail-cell">
            <div class="detail-cell-label">Discount Type</div>
            <div class="detail-cell-val"><?= htmlspecialchars($row['discount_type']) ?></div>
          </div>
          <div class="detail-cell">
            <div class="detail-cell-label">Status</div>
            <div class="detail-cell-val status-<?= $status ?>"><?= ucfirst($status) ?></div>
          </div>
        </div>

      </div>
    </div>
  </div>
</div>
<?php endforeach; ?>

<script>
/* ── Sidebar sync ── */
(function () {
  const sidebar = document.getElementById('mySidebar');
  const cw      = document.getElementById('mainCw');
  if (!sidebar || !cw) return;
  const sync = () => cw.classList.toggle('sidebar-collapsed', sidebar.classList.contains('closed'));
  new MutationObserver(sync).observe(sidebar, { attributes: true, attributeFilter: ['class'] });
  document.getElementById('sidebarToggle')?.addEventListener('click', () => requestAnimationFrame(sync));
  sync();
})();

/* ── Filter by company ── */
let activeFilter = 'all';
function filterCards(btn, filter) {
  document.querySelectorAll('.ftab').forEach(b => b.classList.remove('active'));
  btn.classList.add('active');
  activeFilter = filter;
  applyFilters();
}

/* ── Live search ── */
document.getElementById('searchInput').addEventListener('input', applyFilters);

function applyFilters() {
  const q = document.getElementById('searchInput').value.toLowerCase().trim();
  document.querySelectorAll('.record-card').forEach(card => {
    const matchFilter = activeFilter === 'all' || card.dataset.company === activeFilter;
    const matchSearch = !q || card.dataset.search.includes(q);
    card.style.display = (matchFilter && matchSearch) ? '' : 'none';
  });
}
</script>
</body>
</html>