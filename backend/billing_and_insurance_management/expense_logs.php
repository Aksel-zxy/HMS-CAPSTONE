<?php
session_start();
include '../../SQL/config.php';

$success = '';
$error   = '';

/* ── Handle form submission ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $expense_name = trim($_POST['expense_name'] ?? '');
    $category     = trim($_POST['category']     ?? '');
    $amount       = $_POST['amount']       ?? 0;
    $expense_date = $_POST['expense_date'] ?? '';
    $notes        = trim($_POST['notes']   ?? '');
    $created_by   = $_SESSION['username']  ?? 'Unknown';

    $sql  = "INSERT INTO expense_logs (expense_name, category, amount, expense_date, notes, created_by)
             VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssdsss", $expense_name, $category, $amount, $expense_date, $notes, $created_by);

    if ($stmt->execute()) {
        $success = "Expense added successfully!";
    } else {
        $error = "Error adding expense: " . $stmt->error;
    }
    $stmt->close();
}

/* ── Fetch all expense logs ── */
$logs       = [];
$total_spend = 0;
$res = $conn->query("SELECT * FROM expense_logs ORDER BY expense_date DESC");
if ($res) {
    while ($r = $res->fetch_assoc()) {
        $logs[] = $r;
        $total_spend += (float)$r['amount'];
    }
}

/* ── Category totals for quick stats ── */
$cat_totals = [];
foreach ($logs as $l) {
    $cat = $l['category'] ?: 'Uncategorized';
    $cat_totals[$cat] = ($cat_totals[$cat] ?? 0) + (float)$l['amount'];
}
arsort($cat_totals);
$top_cat = array_key_first($cat_totals) ?? '—';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<title>Expense Logs — HMS</title>

<link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display&family=DM+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<link rel="stylesheet" href="assets/css/billing_sidebar.css">

<style>
/* ── Tokens ── */
:root {
  --sidebar-w:    250px;
  --sidebar-w-sm: 200px;
  --navy:         #0b1d3a;
  --ink:          #1e293b;
  --ink-light:    #64748b;
  --border:       #e2e8f0;
  --surface:      #f1f5f9;
  --card:         #ffffff;
  --accent:       #2563eb;
  --accent-2:     #0ea5e9;
  --success:      #059669;
  --warning:      #d97706;
  --danger:       #dc2626;
  --radius:       14px;
  --shadow:       0 2px 20px rgba(11,29,58,.08);
  --shadow-lg:    0 8px 40px rgba(11,29,58,.14);
  --ff-head:      'DM Serif Display', serif;
  --ff-body:      'DM Sans', sans-serif;
  --transition:   .3s ease-in-out;
}

*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

body {
  font-family: var(--ff-body);
  background: var(--surface);
  color: var(--ink);
  margin: 0;
}

/* ── Content wrapper ── */
.cw {
  margin-left: var(--sidebar-w);
  padding: 60px 28px 60px;
  transition: margin-left var(--transition);
}
.cw.sidebar-collapsed { margin-left: 0; }

/* ── Page Header ── */
.page-head {
  display: flex;
  align-items: center;
  gap: 14px;
  margin-bottom: 24px;
  flex-wrap: wrap;
}
.page-head-icon {
  width: 52px; height: 52px;
  background: linear-gradient(135deg, var(--navy), var(--accent));
  border-radius: 14px;
  display: flex; align-items: center; justify-content: center;
  color: #fff; font-size: 1.4rem;
  box-shadow: 0 6px 18px rgba(11,29,58,.2);
  flex-shrink: 0;
}
.page-head h1 {
  font-family: var(--ff-head);
  font-size: clamp(1.3rem, 3vw, 1.85rem);
  color: var(--navy);
  margin: 0; line-height: 1.1;
}
.page-head p { font-size: .82rem; color: var(--ink-light); margin: 3px 0 0; }
.head-actions { margin-left: auto; }

.btn-add-exp {
  display: inline-flex; align-items: center; gap: 6px;
  padding: 9px 20px;
  background: var(--accent); color: #fff;
  border: none; border-radius: 9px;
  font-family: var(--ff-body); font-size: .87rem; font-weight: 700;
  cursor: pointer; transition: background .15s, transform .1s;
  text-decoration: none;
}
.btn-add-exp:hover { background: #1d4ed8; color: #fff; transform: translateY(-1px); }

/* ── Stats Row ── */
.stats-row {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
  gap: 14px;
  margin-bottom: 24px;
}
.stat-card {
  background: var(--card);
  border: 1px solid var(--border);
  border-radius: var(--radius);
  padding: 16px 18px;
  box-shadow: var(--shadow);
  display: flex; align-items: center; gap: 14px;
}
.stat-icon {
  width: 44px; height: 44px;
  border-radius: 11px;
  display: flex; align-items: center; justify-content: center;
  font-size: 1.15rem; flex-shrink: 0;
}
.stat-icon.blue   { background: #dbeafe; color: #1d4ed8; }
.stat-icon.red    { background: #fee2e2; color: #991b1b; }
.stat-icon.purple { background: #ede9fe; color: #5b21b6; }
.stat-num { font-size: 1.3rem; font-weight: 700; color: var(--navy); line-height: 1; }
.stat-lbl { font-size: .7rem; color: var(--ink-light); font-weight: 600;
            text-transform: uppercase; letter-spacing: .5px; margin-top: 2px; }

/* ── Alert ── */
.alert-box {
  display: flex; align-items: center; gap: 10px;
  padding: 12px 16px; border-radius: 10px;
  font-size: .88rem; font-weight: 600;
  margin-bottom: 20px;
}
.alert-success { background: #d1fae5; color: #065f46; border: 1px solid #6ee7b7; }
.alert-error   { background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; }

/* ── Form Card ── */
.form-card {
  background: var(--card);
  border: 1px solid var(--border);
  border-radius: var(--radius);
  box-shadow: var(--shadow);
  overflow: hidden;
  margin-bottom: 24px;
}
.form-card-header {
  background: var(--navy);
  padding: 14px 20px;
  color: rgba(255,255,255,.8);
  font-size: .72rem; font-weight: 700;
  text-transform: uppercase; letter-spacing: .7px;
  display: flex; align-items: center; gap: 8px;
  cursor: pointer;
  user-select: none;
}
.form-card-header .toggle-icon {
  margin-left: auto;
  transition: transform .25s;
}
.form-card-header.collapsed .toggle-icon { transform: rotate(-90deg); }
.form-card-body { padding: 22px 24px; }

/* Form fields */
.field-group {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 16px;
}
.field-group.single { grid-template-columns: 1fr; }

.form-field { display: flex; flex-direction: column; gap: 6px; }
.form-label {
  font-size: .78rem; font-weight: 700;
  color: var(--ink-light);
  text-transform: uppercase; letter-spacing: .5px;
}
.form-input,
.form-textarea,
.form-select {
  padding: 9px 13px;
  border: 1.5px solid var(--border);
  border-radius: 9px;
  font-family: var(--ff-body); font-size: .9rem;
  color: var(--ink); background: var(--surface);
  outline: none;
  transition: border-color .2s, box-shadow .2s;
  width: 100%;
}
.form-input:focus,
.form-textarea:focus,
.form-select:focus {
  border-color: var(--accent);
  box-shadow: 0 0 0 3px rgba(37,99,235,.12);
  background: var(--card);
}
.form-textarea { resize: vertical; min-height: 80px; }

.form-divider { height: 1px; background: var(--border); margin: 18px 0; }

.form-actions {
  display: flex; gap: 10px; flex-wrap: wrap;
}
.btn-submit {
  padding: 10px 26px;
  background: var(--success); color: #fff;
  border: none; border-radius: 9px;
  font-family: var(--ff-body); font-size: .9rem; font-weight: 700;
  cursor: pointer; display: inline-flex; align-items: center; gap: 6px;
  transition: background .15s, transform .1s;
  box-shadow: 0 4px 14px rgba(5,150,105,.25);
}
.btn-submit:hover { background: #047857; transform: translateY(-1px); }
.btn-back {
  padding: 10px 20px;
  background: var(--card); color: var(--ink-light);
  border: 1.5px solid var(--border); border-radius: 9px;
  font-family: var(--ff-body); font-size: .9rem; font-weight: 600;
  cursor: pointer; text-decoration: none;
  display: inline-flex; align-items: center; gap: 6px;
  transition: all .15s;
}
.btn-back:hover { border-color: var(--accent); color: var(--accent); background: #eff6ff; }

/* ── Search ── */
.search-wrap {
  position: relative; max-width: 320px; margin-bottom: 16px;
}
.search-wrap i {
  position: absolute; left: 13px; top: 50%;
  transform: translateY(-50%);
  color: var(--ink-light); font-size: .9rem; pointer-events: none;
}
.search-input {
  width: 100%; padding: 9px 14px 9px 36px;
  border: 1.5px solid var(--border); border-radius: 9px;
  font-family: var(--ff-body); font-size: .87rem;
  color: var(--ink); background: var(--card);
  outline: none; transition: border-color .2s, box-shadow .2s;
}
.search-input:focus {
  border-color: var(--accent);
  box-shadow: 0 0 0 3px rgba(37,99,235,.12);
}

/* ── Table Card ── */
.table-card {
  background: var(--card);
  border: 1px solid var(--border);
  border-radius: var(--radius);
  box-shadow: var(--shadow);
  overflow: hidden;
}
.table-card-header {
  background: var(--navy);
  padding: 13px 20px;
  color: rgba(255,255,255,.8);
  font-size: .72rem; font-weight: 700;
  text-transform: uppercase; letter-spacing: .7px;
  display: flex; align-items: center; gap: 8px;
}

/* ── Desktop Table ── */
.exp-table { width: 100%; border-collapse: collapse; font-size: .87rem; }
.exp-table thead th {
  background: #f8fafc;
  color: var(--ink-light);
  font-size: .69rem; font-weight: 700;
  text-transform: uppercase; letter-spacing: .6px;
  padding: 11px 16px;
  border-bottom: 2px solid var(--border);
  text-align: left; white-space: nowrap;
}
.exp-table thead th:last-child { text-align: right; }
.exp-table tbody tr {
  border-bottom: 1px solid var(--border);
  transition: background .12s;
}
.exp-table tbody tr:last-child { border-bottom: none; }
.exp-table tbody tr:hover { background: #f7faff; }
.exp-table tbody td { padding: 13px 16px; vertical-align: middle; }
.exp-table tbody td:last-child { text-align: right; }

/* Expense name cell */
.exp-cell { display: flex; align-items: center; gap: 10px; }
.exp-icon {
  width: 36px; height: 36px; border-radius: 9px;
  background: linear-gradient(135deg, var(--navy), var(--accent));
  color: #fff; font-size: .85rem;
  display: flex; align-items: center; justify-content: center;
  flex-shrink: 0;
}
.exp-name { font-weight: 600; color: var(--navy); font-size: .88rem; }
.exp-by   { font-size: .72rem; color: var(--ink-light); margin-top: 1px; }

/* Category badge */
.cat-badge {
  display: inline-block;
  padding: 3px 10px; border-radius: 999px;
  font-size: .72rem; font-weight: 700;
  background: #ede9fe; color: #5b21b6;
}

/* Amount */
.amount-val { font-weight: 700; color: var(--danger); font-size: .92rem; }

/* Date */
.date-val { font-size: .83rem; color: var(--ink-light); }

/* Notes */
.notes-val {
  font-size: .8rem; color: var(--ink-light);
  max-width: 180px; overflow: hidden;
  text-overflow: ellipsis; white-space: nowrap;
}

/* Empty */
.empty-row td { text-align: center; padding: 48px 16px; color: var(--ink-light); }
.empty-row i { font-size: 2rem; display: block; margin-bottom: 8px; opacity: .3; }

/* ── Mobile Cards ── */
.mobile-cards { display: none; }
.m-exp-card {
  background: var(--card);
  border: 1px solid var(--border);
  border-radius: var(--radius);
  box-shadow: var(--shadow);
  padding: 16px; margin-bottom: 12px;
}
.m-exp-head {
  display: flex; justify-content: space-between;
  align-items: flex-start; gap: 10px; margin-bottom: 12px;
}
.m-exp-row {
  display: flex; justify-content: space-between;
  align-items: center; padding: 7px 0;
  border-bottom: 1px solid var(--border);
  font-size: .83rem; gap: 8px;
}
.m-exp-row:last-of-type { border-bottom: none; }
.m-lbl { color: var(--ink-light); font-weight: 600; font-size: .7rem;
         text-transform: uppercase; letter-spacing: .5px; flex-shrink: 0; }
.m-val { font-weight: 500; color: var(--ink); text-align: right; }

/* ── Responsive ── */
@media (max-width: 768px) {
  .cw { margin-left: var(--sidebar-w-sm); padding: 60px 14px 50px; }
  .cw.sidebar-collapsed { margin-left: 0; }
  .table-card  { display: none; }
  .mobile-cards { display: block; }
  .field-group { grid-template-columns: 1fr; }
  .search-wrap { max-width: 100%; }
  .page-head h1 { font-size: 1.3rem; }
  .page-head-icon { width: 44px; height: 44px; font-size: 1.2rem; border-radius: 11px; }
  .head-actions { margin-left: 0; width: 100%; }
  .btn-add-exp { width: 100%; justify-content: center; }
  .stats-row { grid-template-columns: 1fr 1fr; }
}

@media (max-width: 480px) {
  .cw { margin-left: 0 !important; padding: 56px 10px 40px; }
  .form-card-body { padding: 16px; }
  .form-actions { flex-direction: column; }
  .btn-submit, .btn-back { width: 100%; justify-content: center; }
  .stats-row { grid-template-columns: 1fr 1fr; gap: 10px; }
}

@supports (padding: env(safe-area-inset-bottom)) {
  .cw { padding-bottom: calc(60px + env(safe-area-inset-bottom)); }
}
</style>
</head>
<body>

<?php include 'billing_sidebar.php'; ?>

<div class="cw" id="mainCw">

  <!-- Page Header -->
  <div class="page-head">
    <div class="page-head-icon"><i class="bi bi-wallet2"></i></div>
    <div>
      <h1>Expense Logs</h1>
      <p>Track and manage all hospital operational expenses</p>
    </div>
    <div class="head-actions">
      <button class="btn-add-exp" onclick="toggleForm()">
        <i class="bi bi-plus-lg"></i> Add Expense
      </button>
    </div>
  </div>

  <!-- Stats -->
  <div class="stats-row">
    <div class="stat-card">
      <div class="stat-icon blue"><i class="bi bi-receipt"></i></div>
      <div>
        <div class="stat-num"><?= count($logs) ?></div>
        <div class="stat-lbl">Total Entries</div>
      </div>
    </div>
    <div class="stat-card">
      <div class="stat-icon red"><i class="bi bi-currency-exchange"></i></div>
      <div>
        <div class="stat-num">₱<?= number_format($total_spend, 0) ?></div>
        <div class="stat-lbl">Total Spent</div>
      </div>
    </div>
    <div class="stat-card">
      <div class="stat-icon purple"><i class="bi bi-tag"></i></div>
      <div>
        <div class="stat-num" style="font-size:1rem;"><?= htmlspecialchars($top_cat) ?></div>
        <div class="stat-lbl">Top Category</div>
      </div>
    </div>
  </div>

  <!-- Alert Messages -->
  <?php if ($success): ?>
    <div class="alert-box alert-success">
      <i class="bi bi-check-circle-fill"></i>
      <?= htmlspecialchars($success) ?>
    </div>
  <?php elseif ($error): ?>
    <div class="alert-box alert-error">
      <i class="bi bi-exclamation-circle-fill"></i>
      <?= htmlspecialchars($error) ?>
    </div>
  <?php endif; ?>

  <!-- Add Expense Form (collapsible) -->
  <div class="form-card" id="expenseFormCard" style="<?= $success ? 'display:none;' : '' ?>">
    <div class="form-card-header" id="formToggleHeader" onclick="toggleForm()">
      <i class="bi bi-plus-circle"></i> Add New Expense
      <i class="bi bi-chevron-down toggle-icon" id="toggleIcon"></i>
    </div>
    <div class="form-card-body" id="formBody">
      <form method="POST" action="">
        <div class="field-group">
          <div class="form-field">
            <label class="form-label" for="expense_name">Expense Name <span style="color:var(--danger);">*</span></label>
            <input type="text" class="form-input" id="expense_name" name="expense_name"
                   placeholder="e.g. Office Supplies" required>
          </div>
          <div class="form-field">
            <label class="form-label" for="category">Category</label>
            <input type="text" class="form-input" id="category" name="category"
                   placeholder="e.g. Supplies, Utilities, Maintenance"
                   list="categoryList">
            <datalist id="categoryList">
              <?php foreach (array_keys($cat_totals) as $cat): ?>
                <option value="<?= htmlspecialchars($cat) ?>">
              <?php endforeach; ?>
            </datalist>
          </div>
        </div>

        <div class="field-group" style="margin-top:16px;">
          <div class="form-field">
            <label class="form-label" for="amount">Amount (₱) <span style="color:var(--danger);">*</span></label>
            <input type="number" step="0.01" min="0" class="form-input"
                   id="amount" name="amount" placeholder="0.00" required>
          </div>
          <div class="form-field">
            <label class="form-label" for="expense_date">Date <span style="color:var(--danger);">*</span></label>
            <input type="date" class="form-input" id="expense_date" name="expense_date"
                   value="<?= date('Y-m-d') ?>" required>
          </div>
        </div>

        <div class="form-field" style="margin-top:16px;">
          <label class="form-label" for="notes">Notes</label>
          <textarea class="form-textarea" id="notes" name="notes"
                    placeholder="Optional additional details…"></textarea>
        </div>

        <div class="form-divider"></div>

        <div class="form-actions">
          <button type="submit" class="btn-submit">
            <i class="bi bi-check-circle"></i> Add Expense
          </button>
          <a href="billing_dashboard.php" class="btn-back">
            <i class="bi bi-arrow-left"></i> Dashboard
          </a>
        </div>
      </form>
    </div>
  </div>

  <!-- Search -->
  <div class="search-wrap">
    <i class="bi bi-search"></i>
    <input type="text" class="search-input" id="searchInput"
           placeholder="Search expenses…">
  </div>

  <!-- ── Desktop Table ── -->
  <div class="table-card">
    <div class="table-card-header">
      <i class="bi bi-list-ul"></i> All Expense Records
      <span style="margin-left:auto;font-weight:400;opacity:.7;"><?= count($logs) ?> entr<?= count($logs) !== 1 ? 'ies' : 'y' ?></span>
    </div>
    <div style="overflow-x:auto;">
      <table class="exp-table" id="expTable">
        <thead>
          <tr>
            <th>Expense</th>
            <th>Category</th>
            <th>Amount</th>
            <th>Date</th>
            <th>Notes</th>
          </tr>
        </thead>
        <tbody>
        <?php if (empty($logs)): ?>
          <tr class="empty-row">
            <td colspan="5">
              <i class="bi bi-inbox"></i>
              No expense records yet. Add your first expense above.
            </td>
          </tr>
        <?php else: ?>
          <?php foreach ($logs as $log):
            $exp_date = $log['expense_date'] ? date('M d, Y', strtotime($log['expense_date'])) : '—';
            $initials  = strtoupper(substr($log['expense_name'], 0, 1));
          ?>
          <tr class="exp-row">
            <td>
              <div class="exp-cell">
                <div class="exp-icon"><?= $initials ?></div>
                <div>
                  <div class="exp-name"><?= htmlspecialchars($log['expense_name']) ?></div>
                  <div class="exp-by">By <?= htmlspecialchars($log['created_by'] ?? '—') ?></div>
                </div>
              </div>
            </td>
            <td>
              <?php if (!empty($log['category'])): ?>
                <span class="cat-badge"><?= htmlspecialchars($log['category']) ?></span>
              <?php else: ?>
                <span style="color:var(--ink-light);font-size:.82rem;">—</span>
              <?php endif; ?>
            </td>
            <td><span class="amount-val">₱<?= number_format((float)$log['amount'], 2) ?></span></td>
            <td><span class="date-val"><?= $exp_date ?></span></td>
            <td>
              <span class="notes-val" title="<?= htmlspecialchars($log['notes'] ?? '') ?>">
                <?= htmlspecialchars($log['notes'] ?? '—') ?>
              </span>
            </td>
          </tr>
          <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- ── Mobile Cards ── -->
  <div class="mobile-cards" id="mobileCards">
    <?php if (empty($logs)): ?>
      <div style="text-align:center;padding:40px;color:var(--ink-light);">
        <i class="bi bi-inbox" style="font-size:2rem;display:block;margin-bottom:10px;opacity:.3;"></i>
        No expense records yet.
      </div>
    <?php else: ?>
      <?php foreach ($logs as $log):
        $exp_date = $log['expense_date'] ? date('M d, Y', strtotime($log['expense_date'])) : '—';
        $initials  = strtoupper(substr($log['expense_name'], 0, 1));
      ?>
      <div class="m-exp-card mobile-row">
        <div class="m-exp-head">
          <div class="exp-cell">
            <div class="exp-icon"><?= $initials ?></div>
            <div>
              <div class="exp-name"><?= htmlspecialchars($log['expense_name']) ?></div>
              <div class="exp-by">By <?= htmlspecialchars($log['created_by'] ?? '—') ?></div>
            </div>
          </div>
          <span class="amount-val">₱<?= number_format((float)$log['amount'], 2) ?></span>
        </div>
        <?php if (!empty($log['category'])): ?>
        <div class="m-exp-row">
          <span class="m-lbl">Category</span>
          <span class="m-val"><span class="cat-badge"><?= htmlspecialchars($log['category']) ?></span></span>
        </div>
        <?php endif; ?>
        <div class="m-exp-row">
          <span class="m-lbl">Date</span>
          <span class="m-val"><?= $exp_date ?></span>
        </div>
        <?php if (!empty($log['notes'])): ?>
        <div class="m-exp-row">
          <span class="m-lbl">Notes</span>
          <span class="m-val" style="font-size:.8rem;color:var(--ink-light);">
            <?= htmlspecialchars($log['notes']) ?>
          </span>
        </div>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>

</div><!-- /cw -->

<script>
/* ── Sidebar sync ── */
(function () {
    const sidebar = document.getElementById('mySidebar');
    const cw      = document.getElementById('mainCw');
    if (!sidebar || !cw) return;
    function sync() { cw.classList.toggle('sidebar-collapsed', sidebar.classList.contains('closed')); }
    new MutationObserver(sync).observe(sidebar, { attributes: true, attributeFilter: ['class'] });
    document.getElementById('sidebarToggle')?.addEventListener('click', () => requestAnimationFrame(sync));
    sync();
})();

/* ── Form toggle ── */
let formOpen = <?= $success ? 'false' : 'true' ?>;
function toggleForm() {
    const card   = document.getElementById('expenseFormCard');
    const header = document.getElementById('formToggleHeader');
    const icon   = document.getElementById('toggleIcon');
    const body   = document.getElementById('formBody');

    formOpen = !formOpen;
    card.style.display   = formOpen ? '' : 'none';
    header?.classList.toggle('collapsed', !formOpen);

    if (icon) icon.style.transform = formOpen ? '' : 'rotate(-90deg)';
}

/* ── Live search — desktop + mobile ── */
document.getElementById('searchInput').addEventListener('input', function () {
    const q = this.value.toLowerCase().trim();
    document.querySelectorAll('#expTable tbody .exp-row').forEach(r => {
        r.style.display = r.textContent.toLowerCase().includes(q) ? '' : 'none';
    });
    document.querySelectorAll('#mobileCards .mobile-row').forEach(c => {
        c.style.display = c.textContent.toLowerCase().includes(q) ? '' : 'none';
    });
});

/* ── Auto-hide alert after 4s ── */
setTimeout(() => {
    document.querySelector('.alert-box')?.remove();
}, 4000);
</script>
</body>
</html>