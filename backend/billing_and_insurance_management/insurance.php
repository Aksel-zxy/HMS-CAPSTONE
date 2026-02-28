<?php
include '../../SQL/config.php';

/* ── Initialize error & handle POST ── */
$addError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_insurance') {
    $full_name         = trim($_POST['full_name'] ?? '');
    $relationship      = trim($_POST['relationship_to_insured'] ?? '');
    $insurance_number  = trim($_POST['insurance_number'] ?? '');
    $insurance_company = trim($_POST['insurance_company'] ?? '');
    $promo_name        = trim($_POST['promo_name'] ?? '');
    $discount_value    = trim($_POST['discount_value'] ?? '');
    $discount_type     = trim($_POST['discount_type'] ?? 'Percentage');
    $status            = trim($_POST['status'] ?? 'Active');

    if (!$full_name || !$relationship || !$insurance_number || !$insurance_company || $discount_value === '') {
        $addError = 'Please fill in all required fields and select an insurance company.';
    } else {
        $stmt = $conn->prepare("INSERT INTO patient_insurance 
            (full_name, relationship_to_insured, insurance_number, insurance_company, promo_name, discount_value, discount_type, status, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())");
        $stmt->bind_param('sssssdss',
            $full_name, $relationship, $insurance_number,
            $insurance_company, $promo_name, $discount_value,
            $discount_type, $status
        );

        if ($stmt->execute()) {
            header('Location: ' . $_SERVER['PHP_SELF'] . '?added=1');
            exit;
        } else {
            $addError = 'Database error: ' . htmlspecialchars($conn->error);
        }
        $stmt->close();
    }
}

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
  align-items: center;
}
.cw.sidebar-collapsed { margin-left: 0; }

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

.masthead-right {
  display: flex; align-items: center; gap: 16px; flex-wrap: wrap;
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

/* ── Add Insurance Button ── */
.btn-add-insurance {
  display: inline-flex; align-items: center; gap: 8px;
  padding: 12px 22px;
  background: linear-gradient(135deg, var(--navy), var(--blue));
  color: #fff; border: none;
  border-radius: var(--radius-lg);
  font-family: var(--ff-body); font-size: .86rem; font-weight: 700;
  cursor: pointer; text-decoration: none;
  box-shadow: 0 6px 20px rgba(26,86,219,.35);
  transition: all .2s;
  white-space: nowrap;
  letter-spacing: .02em;
}
.btn-add-insurance:hover {
  background: linear-gradient(135deg, var(--blue), var(--teal));
  transform: translateY(-2px);
  box-shadow: 0 10px 28px rgba(26,86,219,.45);
  color: #fff;
}
.btn-add-insurance i { font-size: 1rem; }

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
.irc-detail-label {
  font-size: .63rem; font-weight: 700; text-transform: uppercase;
  letter-spacing: .08em; color: var(--ink-4); margin-bottom: 2px;
}
.irc-detail-val {
  font-size: .82rem; font-weight: 600; color: var(--ink);
}
.irc-detail-val.discount { color: var(--green); }
.irc-detail-val.mono    { font-family: var(--ff-mono); font-size: .76rem; }

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
   SUCCESS TOAST
════════════════════════════════════════ */
.toast-success {
  position: fixed; top: 24px; right: 24px; z-index: 9999;
  background: #fff; border: 1.5px solid #bbf7d0;
  border-radius: var(--radius-lg);
  padding: 14px 20px; display: flex; align-items: center; gap: 12px;
  box-shadow: var(--shadow-lg);
  animation: slideInRight .35s ease, fadeOut .4s ease 3.5s forwards;
  min-width: 280px;
}
.toast-icon {
  width: 36px; height: 36px; border-radius: 50%;
  background: #d1fae5; color: var(--green);
  display: flex; align-items: center; justify-content: center;
  font-size: 1.1rem; flex-shrink: 0;
}
.toast-text strong { display: block; font-size: .88rem; color: var(--ink); }
.toast-text span   { font-size: .78rem; color: var(--ink-3); }

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

/* ── Add Insurance Modal specific ── */
.add-modal .modal-body {
  background: var(--paper);
  padding: 28px 28px 24px;
}
.add-modal .modal-header {
  background: linear-gradient(135deg, var(--navy), var(--blue));
}

.form-section {
  margin-bottom: 22px;
}
.form-section-title {
  font-size: .7rem; font-weight: 800; text-transform: uppercase;
  letter-spacing: .1em; color: var(--ink-4);
  margin-bottom: 12px; padding-bottom: 8px;
  border-bottom: 1px solid var(--border);
  display: flex; align-items: center; gap: 6px;
}
.form-section-title i { color: var(--blue-2); }

.form-row { display: grid; gap: 14px; margin-bottom: 14px; }
.form-row.cols-2 { grid-template-columns: 1fr 1fr; }
.form-row.cols-3 { grid-template-columns: 1fr 1fr 1fr; }

.form-group { display: flex; flex-direction: column; gap: 5px; }
.form-label {
  font-size: .72rem; font-weight: 700; text-transform: uppercase;
  letter-spacing: .07em; color: var(--ink-2);
}
.form-label .req { color: var(--red); margin-left: 2px; }

.form-control-custom {
  padding: 10px 13px;
  border: 1.5px solid var(--border-2); border-radius: var(--radius);
  font-family: var(--ff-body); font-size: .86rem; color: var(--ink);
  background: var(--surface-2);
  outline: none; transition: border-color .2s, box-shadow .2s, background .2s;
  width: 100%;
}
.form-control-custom:focus {
  border-color: var(--blue-2);
  box-shadow: 0 0 0 3px rgba(59,130,246,.1);
  background: var(--paper);
}
select.form-control-custom { cursor: pointer; }
.form-control-custom.is-invalid { border-color: var(--red); }

/* Company selector cards */
.company-selector {
  display: grid; grid-template-columns: repeat(4, 1fr); gap: 8px;
  margin-bottom: 0;
}
.company-opt {
  display: flex; flex-direction: column; align-items: center; gap: 6px;
  padding: 10px 6px;
  border: 2px solid var(--border-2); border-radius: var(--radius);
  cursor: pointer; transition: all .15s;
  background: var(--surface-2);
}
.company-opt:hover { border-color: var(--ink-3); background: var(--paper); }
.company-opt.selected { border-color: var(--blue); background: #eff6ff; }
.company-opt input[type="radio"] { display: none; }
.company-opt-icon {
  width: 36px; height: 36px; border-radius: 10px;
  display: flex; align-items: center; justify-content: center;
  font-size: 1.1rem; color: #fff;
}
.company-opt-label {
  font-size: .65rem; font-weight: 700; text-transform: uppercase;
  letter-spacing: .07em; color: var(--ink-2); text-align: center; line-height: 1.2;
}
.company-opt.selected .company-opt-label { color: var(--blue); }

/* Discount toggle */
.discount-toggle {
  display: flex; gap: 8px;
}
.dtype-btn {
  flex: 1; padding: 10px 8px; border-radius: var(--radius);
  border: 2px solid var(--border-2); background: var(--surface-2);
  font-family: var(--ff-body); font-size: .8rem; font-weight: 600;
  cursor: pointer; transition: all .15s; color: var(--ink-2);
  display: flex; align-items: center; justify-content: center; gap: 5px;
}
.dtype-btn.active {
  border-color: var(--blue); background: #eff6ff; color: var(--blue);
}

/* Status pills */
.status-selector { display: flex; gap: 8px; }
.status-opt {
  flex: 1; padding: 10px 8px; border-radius: var(--radius);
  border: 2px solid var(--border-2); background: var(--surface-2);
  font-family: var(--ff-body); font-size: .8rem; font-weight: 600;
  cursor: pointer; transition: all .15s; color: var(--ink-2);
  display: flex; align-items: center; justify-content: center; gap: 5px;
  text-align: center;
}
.status-opt.active-opt  { border-color: var(--green); background: #d1fae5; color: var(--green); }
.status-opt.inactive-opt{ border-color: var(--red);   background: #fee2e2; color: var(--red);   }
.status-opt.pending-opt { border-color: var(--amber);  background: #fef3c7; color: var(--amber); }

/* Add modal footer */
.add-modal-footer {
  padding: 16px 28px 20px;
  border-top: 1px solid var(--border);
  display: flex; gap: 10px; justify-content: flex-end;
  background: var(--surface-2);
}
.btn-cancel {
  padding: 10px 22px; border-radius: var(--radius);
  border: 1.5px solid var(--border-2); background: var(--paper);
  font-family: var(--ff-body); font-size: .86rem; font-weight: 600;
  color: var(--ink-2); cursor: pointer; transition: all .15s;
}
.btn-cancel:hover { background: var(--surface); border-color: var(--ink-3); }
.btn-submit {
  padding: 10px 26px; border-radius: var(--radius);
  border: none;
  background: linear-gradient(135deg, var(--navy), var(--blue));
  color: #fff; font-family: var(--ff-body); font-size: .86rem; font-weight: 700;
  cursor: pointer; transition: all .2s;
  box-shadow: 0 4px 14px rgba(26,86,219,.3);
  display: inline-flex; align-items: center; gap: 7px;
}
.btn-submit:hover {
  background: linear-gradient(135deg, var(--blue), var(--teal));
  transform: translateY(-1px);
  box-shadow: 0 6px 20px rgba(26,86,219,.4);
}

.error-alert {
  background: #fee2e2; border: 1.5px solid #fecaca;
  border-radius: var(--radius); padding: 12px 16px;
  color: var(--red); font-size: .84rem; font-weight: 600;
  display: flex; align-items: center; gap: 8px; margin-bottom: 18px;
}

/* ── The Card itself (modal viewer) ── */
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
.detail-cell-val.green         { color: var(--green); }
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
@keyframes slideInRight {
  from { opacity: 0; transform: translateX(60px); }
  to   { opacity: 1; transform: translateX(0); }
}
@keyframes fadeOut {
  to { opacity: 0; pointer-events: none; }
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
  .form-row.cols-3 { grid-template-columns: 1fr 1fr; }
}
@media (max-width: 600px) {
  .cw { margin-left: 0 !important; padding: 56px 10px 40px; }
  .masthead-stats { display: none; }
  .toolbar { flex-direction: column; align-items: stretch; }
  .search-wrap { max-width: 100%; }
  .detail-grid { grid-template-columns: 1fr 1fr; }
  .ins-card { height: auto; min-height: 210px; }
  .form-row.cols-2,
  .form-row.cols-3 { grid-template-columns: 1fr; }
  .company-selector { grid-template-columns: repeat(2, 1fr); }
  .btn-add-insurance span { display: none; }
}
@supports (padding: env(safe-area-inset-bottom)) {
  .cw { padding-bottom: calc(72px + env(safe-area-inset-bottom)); }
}
</style>
</head>
<body>

<?php include 'billing_sidebar.php'; ?>

<?php if (isset($_GET['added'])): ?>
<div class="toast-success" id="successToast">
  <div class="toast-icon"><i class="bi bi-check-lg"></i></div>
  <div class="toast-text">
    <strong>Insurance Added Successfully</strong>
    <span>New patient insurance record has been saved.</span>
  </div>
</div>
<?php endif; ?>

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
      <div class="masthead-right">
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
        <button class="btn-add-insurance" data-bs-toggle="modal" data-bs-target="#addInsuranceModal">
          <i class="bi bi-plus-circle-fill"></i>
          <span>Add Insurance</span>
        </button>
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
        $colors   = cardGradient($row['insurance_company']);
        $co       = $row['insurance_company'];
        $coKey    = strtolower(str_replace(' ', '', $co));
        $status   = strtolower($row['status'] ?? 'active');
        $discount = $row['discount_type'] === 'Percentage'
          ? htmlspecialchars($row['discount_value']) . '%'
          : '₱' . number_format($row['discount_value'], 2);
        $modalId  = 'modal_' . intval($row['patient_insurance_id']);
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

        <div class="irc-top" style="background:linear-gradient(145deg,<?= $colors[0] ?>,<?= $colors[1] ?> 60%,<?= $colors[2] ?>);">
          <div class="irc-company-row">
            <span class="irc-company-name"><?= htmlspecialchars($co) ?></span>
            <span class="irc-status-dot <?= $status ?>"></span>
          </div>
          <span class="irc-number"><?= $numParts ?></span>
        </div>

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
     ADD INSURANCE MODAL
════════════════════════════════════ -->
<div class="modal fade add-modal" id="addInsuranceModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">

      <div class="modal-header">
        <div class="modal-header-icon"><i class="bi bi-plus-circle"></i></div>
        <div>
          <div class="modal-title">Add Patient Insurance</div>
          <div class="modal-sub">Register a new insurance record for a patient</div>
        </div>
        <button type="button" class="btn-close ms-auto" data-bs-dismiss="modal"></button>
      </div>

      <form method="POST" action="" id="addInsuranceForm">
        <input type="hidden" name="action" value="add_insurance">
        <input type="hidden" name="insurance_company" id="selectedCompany" required>
        <input type="hidden" name="discount_type" id="selectedDiscountType" value="Percentage" required>
        <input type="hidden" name="status" id="selectedStatus" value="Active" required>

        <div class="modal-body">

          <?php if ($addError): ?>
          <div class="error-alert">
            <i class="bi bi-exclamation-triangle-fill"></i>
            <?= htmlspecialchars($addError) ?>
          </div>
          <?php endif; ?>

          <!-- Patient Info -->
          <div class="form-section">
            <div class="form-section-title">
              <i class="bi bi-person-vcard"></i> Patient Information
            </div>
            <div class="form-row">
              <div class="form-group">
                <label class="form-label">Full Name <span class="req">*</span></label>
                <input type="text" name="full_name" class="form-control-custom"
                       placeholder="e.g. Juan Dela Cruz"
                       value="<?= htmlspecialchars($_POST['full_name'] ?? '') ?>" required>
              </div>
            </div>
            <div class="form-row cols-2">
              <div class="form-group">
                <label class="form-label">Relationship to Insured <span class="req">*</span></label>
                <select name="relationship_to_insured" class="form-control-custom" required>
                  <option value="" disabled <?= empty($_POST['relationship_to_insured']) ? 'selected' : '' ?>>Select relationship…</option>
                  <option value="Self"      <?= ($_POST['relationship_to_insured'] ?? '') === 'Self'      ? 'selected' : '' ?>>Self</option>
                  <option value="Spouse"    <?= ($_POST['relationship_to_insured'] ?? '') === 'Spouse'    ? 'selected' : '' ?>>Spouse</option>
                  <option value="Child"     <?= ($_POST['relationship_to_insured'] ?? '') === 'Child'     ? 'selected' : '' ?>>Child</option>
                  <option value="Parent"    <?= ($_POST['relationship_to_insured'] ?? '') === 'Parent'    ? 'selected' : '' ?>>Parent</option>
                  <option value="Sibling"   <?= ($_POST['relationship_to_insured'] ?? '') === 'Sibling'   ? 'selected' : '' ?>>Sibling</option>
                  <option value="Dependent" <?= ($_POST['relationship_to_insured'] ?? '') === 'Dependent' ? 'selected' : '' ?>>Dependent</option>
                  <option value="Other"     <?= ($_POST['relationship_to_insured'] ?? '') === 'Other'     ? 'selected' : '' ?>>Other</option>
                </select>
              </div>
              <div class="form-group">
                <label class="form-label">Insurance Number <span class="req">*</span></label>
                <input type="text" name="insurance_number" class="form-control-custom"
                       placeholder="e.g. PH-1234-5678"
                       value="<?= htmlspecialchars($_POST['insurance_number'] ?? '') ?>" required>
              </div>
            </div>
          </div>

          <!-- Insurance Company -->
          <div class="form-section">
            <div class="form-section-title">
              <i class="bi bi-building-check"></i> Insurance Company <span class="req" style="margin-left:3px;">*</span>
            </div>
            <div class="company-selector" id="companySelector">
              <label class="company-opt <?= ($_POST['insurance_company'] ?? '') === 'PhilHealth' ? 'selected' : '' ?>" data-company="PhilHealth">
                <input type="radio" name="_company_radio" value="PhilHealth" <?= ($_POST['insurance_company'] ?? '') === 'PhilHealth' ? 'checked' : '' ?>>
                <div class="company-opt-icon" style="background:linear-gradient(135deg,#0d2b6e,#1a56c4);">
                  <i class="bi bi-hospital"></i>
                </div>
                <span class="company-opt-label">PhilHealth</span>
              </label>
              <label class="company-opt <?= ($_POST['insurance_company'] ?? '') === 'Maxicare' ? 'selected' : '' ?>" data-company="Maxicare">
                <input type="radio" name="_company_radio" value="Maxicare" <?= ($_POST['insurance_company'] ?? '') === 'Maxicare' ? 'checked' : '' ?>>
                <div class="company-opt-icon" style="background:linear-gradient(135deg,#0a6b5e,#0f9b8e);">
                  <i class="bi bi-heart-pulse"></i>
                </div>
                <span class="company-opt-label">Maxicare</span>
              </label>
              <label class="company-opt <?= ($_POST['insurance_company'] ?? '') === 'Medicard' ? 'selected' : '' ?>" data-company="Medicard">
                <input type="radio" name="_company_radio" value="Medicard" <?= ($_POST['insurance_company'] ?? '') === 'Medicard' ? 'checked' : '' ?>>
                <div class="company-opt-icon" style="background:linear-gradient(135deg,#4a0080,#8e2de2);">
                  <i class="bi bi-shield-heart"></i>
                </div>
                <span class="company-opt-label">Medicard</span>
              </label>
              <label class="company-opt <?= ($_POST['insurance_company'] ?? '') === 'Intellicare' ? 'selected' : '' ?>" data-company="Intellicare">
                <input type="radio" name="_company_radio" value="Intellicare" <?= ($_POST['insurance_company'] ?? '') === 'Intellicare' ? 'checked' : '' ?>>
                <div class="company-opt-icon" style="background:linear-gradient(135deg,#b35a00,#f7971e);">
                  <i class="bi bi-shield-plus"></i>
                </div>
                <span class="company-opt-label">Intellicare</span>
              </label>
            </div>
          </div>

          <!-- Plan & Discount -->
          <div class="form-section">
            <div class="form-section-title">
              <i class="bi bi-tag"></i> Plan & Discount
            </div>
            <div class="form-row cols-2">
              <div class="form-group">
                <label class="form-label">Promo / Plan Name</label>
                <input type="text" name="promo_name" class="form-control-custom"
                       placeholder="e.g. Gold Plan, Basic Coverage"
                       value="<?= htmlspecialchars($_POST['promo_name'] ?? '') ?>">
              </div>
              <div class="form-group">
                <label class="form-label">Discount Value <span class="req">*</span></label>
                <input type="number" name="discount_value" class="form-control-custom"
                       placeholder="e.g. 20" min="0" step="0.01"
                       value="<?= htmlspecialchars($_POST['discount_value'] ?? '') ?>" required>
              </div>
            </div>
            <div class="form-row cols-2">
              <div class="form-group">
                <label class="form-label">Discount Type <span class="req">*</span></label>
                <div class="discount-toggle" id="discountToggle">
                  <button type="button"
                          class="dtype-btn <?= ($_POST['discount_type'] ?? 'Percentage') === 'Percentage' ? 'active' : '' ?>"
                          data-type="Percentage"
                          onclick="selectDiscountType(this,'Percentage')">
                    <i class="bi bi-percent"></i> Percentage
                  </button>
                  <button type="button"
                          class="dtype-btn <?= ($_POST['discount_type'] ?? '') === 'Fixed' ? 'active' : '' ?>"
                          data-type="Fixed"
                          onclick="selectDiscountType(this,'Fixed')">
                    <i class="bi bi-cash-coin"></i> Fixed (₱)
                  </button>
                </div>
              </div>
              <div class="form-group">
                <label class="form-label">Status <span class="req">*</span></label>
                <div class="status-selector" id="statusSelector">
                  <button type="button" class="status-opt active-opt"
                          onclick="selectStatus(this,'Active')" data-status="Active">
                    <i class="bi bi-check-circle-fill"></i> Active
                  </button>
                  <button type="button" class="status-opt inactive-opt"
                          onclick="selectStatus(this,'Inactive')" data-status="Inactive">
                    <i class="bi bi-x-circle"></i> Inactive
                  </button>
                  <button type="button" class="status-opt pending-opt"
                          onclick="selectStatus(this,'Pending')" data-status="Pending">
                    <i class="bi bi-clock"></i> Pending
                  </button>
                </div>
              </div>
            </div>
          </div>

        </div><!-- /modal-body -->

        <div class="add-modal-footer">
          <button type="button" class="btn-cancel" data-bs-dismiss="modal">
            <i class="bi bi-x-lg"></i> Cancel
          </button>
          <button type="submit" class="btn-submit" id="submitBtn">
            <i class="bi bi-shield-plus"></i> Save Insurance Record
          </button>
        </div>
      </form>

    </div>
  </div>
</div>

<!-- ════════════════════════════════════
     MODALS — Insurance Card Viewer
════════════════════════════════════ -->
<?php foreach ($rows as $row):
  $colors   = cardGradient($row['insurance_company']);
  $accent   = cardAccent($row['insurance_company']);
  $co       = $row['insurance_company'];
  $modalId  = 'modal_' . intval($row['patient_insurance_id']);
  $status   = strtolower($row['status'] ?? 'active');
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

/* ── Company selector ── */
document.querySelectorAll('.company-opt').forEach(opt => {
  opt.addEventListener('click', function () {
    document.querySelectorAll('.company-opt').forEach(o => o.classList.remove('selected'));
    this.classList.add('selected');
    document.getElementById('selectedCompany').value = this.dataset.company;
    this.querySelector('input[type="radio"]').checked = true;
  });
});

/* Pre-select company if POST came back with error */
(function () {
  const preselect = '<?= htmlspecialchars($_POST['insurance_company'] ?? '') ?>';
  if (preselect) {
    document.getElementById('selectedCompany').value = preselect;
    document.getElementById('selectedDiscountType').value = '<?= htmlspecialchars($_POST['discount_type'] ?? 'Percentage') ?>';
    document.getElementById('selectedStatus').value = '<?= htmlspecialchars($_POST['status'] ?? 'Active') ?>';
    // Highlight matching status button
    document.querySelectorAll('.status-opt').forEach(b => b.style.outline = 'none');
    const sBtn = document.querySelector(`.status-opt[data-status="<?= htmlspecialchars($_POST['status'] ?? 'Active') ?>"]`);
    if (sBtn) { sBtn.style.outline = '2px solid currentColor'; sBtn.style.outlineOffset = '2px'; }
  }
})();

/* ── Discount type toggle ── */
function selectDiscountType(btn, type) {
  document.querySelectorAll('.dtype-btn').forEach(b => b.classList.remove('active'));
  btn.classList.add('active');
  document.getElementById('selectedDiscountType').value = type;
}

/* ── Status selector ── */
function selectStatus(btn, status) {
  document.querySelectorAll('.status-opt').forEach(b => {
    b.style.outline = 'none';
    b.style.outlineOffset = '';
  });
  btn.style.outline = '2px solid currentColor';
  btn.style.outlineOffset = '2px';
  document.getElementById('selectedStatus').value = status;
}

/* Set default status highlight on page load */
(function () {
  const defaultStatus = document.querySelector('.status-opt[data-status="Active"]');
  if (defaultStatus && !document.getElementById('selectedStatus').value) {
    defaultStatus.style.outline = '2px solid currentColor';
    defaultStatus.style.outlineOffset = '2px';
  }
})();

/* ── Form validation before submit ── */
document.getElementById('addInsuranceForm').addEventListener('submit', function(e) {
  const company = document.getElementById('selectedCompany').value;
  if (!company) {
    e.preventDefault();
    const cs = document.getElementById('companySelector');
    cs.style.outline = '2px solid #dc2626';
    cs.style.borderRadius = '12px';
    cs.scrollIntoView({ behavior: 'smooth', block: 'center' });
    setTimeout(() => { cs.style.outline = ''; }, 2500);
    return;
  }
  const btn = document.getElementById('submitBtn');
  btn.innerHTML = '<i class="bi bi-hourglass-split"></i> Saving…';
  btn.disabled = true;
});

/* ── Auto-dismiss toast ── */
const toast = document.getElementById('successToast');
if (toast) setTimeout(() => toast.remove(), 4000);

/* ── Re-open add modal on validation error ── */
<?php if ($addError): ?>
const addModal = new bootstrap.Modal(document.getElementById('addInsuranceModal'));
addModal.show();
<?php endif; ?>
</script>
</body>
</html>