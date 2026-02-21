<?php
session_start();
include '../../SQL/config.php';

/* ── Auto-classify accounts ── */
function classifyAccount($name, $isExpense = false) {
    $n = strtolower($name);
    if ($isExpense) return "Expense";
    if (strpos($n, 'cash') !== false || strpos($n, 'receivable') !== false || strpos($n, 'bank') !== false) return "Asset";
    if (strpos($n, 'payable') !== false || strpos($n, 'loan') !== false) return "Liability";
    if (strpos($n, 'revenue') !== false || strpos($n, 'income') !== false || strpos($n, 'service') !== false) return "Revenue";
    if (strpos($n, 'expense') !== false || strpos($n, 'food') !== false || strpos($n, 'supplies') !== false) return "Expense";
    return "Revenue";
}

/* ── Collect all records ── */
$totals  = ["Asset" => 0, "Liability" => 0, "Revenue" => 0, "Expense" => 0];
$records = [];

// 1. Billing items (finalized service line-items)
$billing = $conn->query("
    SELECT bi.item_id, bi.total_price, bi.billing_id, ds.serviceName
    FROM billing_items bi
    JOIN dl_services ds ON bi.service_id = ds.serviceID
    WHERE bi.finalized = 1
    ORDER BY bi.item_id DESC
");
if ($billing) {
    while ($row = $billing->fetch_assoc()) {
        $type = classifyAccount($row['serviceName']);
        $totals[$type] += $row['total_price'];
        $records[] = [
            'id'     => $row['item_id'],
            'name'   => $row['serviceName'],
            'amount' => $row['total_price'],
            'date'   => date('Y-m-d H:i:s'),
            'type'   => $type,
            'source' => 'Billing',
        ];
    }
}

// 1b. Paid billing records (actual payments received from billing_records)
$payments = $conn->query("
    SELECT 
        br.billing_id,
        br.paid_amount,
        br.grand_total,
        br.total_amount,
        br.payment_date,
        br.billing_date,
        br.payment_method,
        br.status,
        br.payment_status,
        CONCAT(
            'Payment #', br.billing_id,
            IF(br.payment_method IS NOT NULL AND br.payment_method != '',
               CONCAT(' (', br.payment_method, ')'), '')
        ) AS payment_label
    FROM billing_records br
    WHERE br.status = 'Paid'
       OR br.paid_amount > 0
       OR br.payment_status = 'paid'
    ORDER BY br.billing_id DESC
");
if ($payments) {
    // Track billing_ids already covered by billing_items to avoid double-counting
    $billingItemIds = [];
    $conn->query("SELECT DISTINCT billing_id FROM billing_items WHERE finalized = 1")->data_seek(0);
    $biCheck = $conn->query("SELECT DISTINCT billing_id FROM billing_items WHERE finalized = 1");
    if ($biCheck) {
        while ($biRow = $biCheck->fetch_assoc()) {
            $billingItemIds[] = $biRow['billing_id'];
        }
    }

    while ($row = $payments->fetch_assoc()) {
        // Use paid_amount if available, else grand_total, else total_amount
        $amount = floatval($row['paid_amount']) > 0
                    ? floatval($row['paid_amount'])
                    : (floatval($row['grand_total']) > 0
                        ? floatval($row['grand_total'])
                        : floatval($row['total_amount']));

        if ($amount <= 0) continue;

        // Use payment_date if available, else billing_date
        $date = (!empty($row['payment_date']) && $row['payment_date'] !== '0000-00-00 00:00:00')
                    ? $row['payment_date']
                    : ($row['billing_date'] ?? date('Y-m-d H:i:s'));

        $type = "Revenue";
        $totals[$type] += $amount;
        $records[] = [
            'id'     => $row['billing_id'],
            'name'   => $row['payment_label'],
            'amount' => $amount,
            'date'   => $date,
            'type'   => $type,
            'source' => 'Payment',
        ];
    }
}

// 2. Expenses
$expenses = $conn->query("SELECT * FROM expense_logs ORDER BY expense_date DESC");
if ($expenses) {
    while ($row = $expenses->fetch_assoc()) {
        $type = classifyAccount($row['expense_name'], true);
        $totals[$type] += $row['amount'];
        $records[] = [
            'id'     => $row['expense_id'],
            'name'   => $row['expense_name'],
            'amount' => $row['amount'],
            'date'   => $row['expense_date'],
            'type'   => $type,
            'source' => 'Expense',
        ];
    }
}

// 3. Journal entry lines
$lines = $conn->query("
    SELECT jel.line_id, jel.entry_id, jel.account_name, jel.debit, jel.credit, je.entry_date
    FROM journal_entry_lines jel
    JOIN journal_entries je ON jel.entry_id = je.entry_id
    ORDER BY jel.line_id DESC
");
if ($lines) {
    while ($row = $lines->fetch_assoc()) {
        $type   = classifyAccount($row['account_name']);
        $amount = floatval($row['debit']) - floatval($row['credit']);
        $totals[$type] += abs($amount);
        $records[] = [
            'id'     => $row['line_id'],
            'name'   => $row['account_name'],
            'amount' => $amount,
            'date'   => $row['entry_date'],
            'type'   => $type,
            'source' => 'Journal',
        ];
    }
}

// Sort newest first
usort($records, fn($a, $b) => strtotime($b['date']) - strtotime($a['date']));

/* ── Pagination ── */
$limit        = 15;
$totalRecords = count($records);
$totalPages   = max(1, ceil($totalRecords / $limit));
$page         = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$page         = max(1, min($page, $totalPages));
$start        = ($page - 1) * $limit;
$pageRecords  = array_slice($records, $start, $limit);

/* ── Net position ── */
$net = $totals['Asset'] + $totals['Revenue'] - $totals['Liability'] - $totals['Expense'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<title>Journal Accounts — HMS</title>

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
  --success:      #059669;
  --warning:      #d97706;
  --danger:       #dc2626;
  --info:         #0284c7;
  --radius:       14px;
  --shadow:       0 2px 20px rgba(11,29,58,.08);
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
  display: flex; align-items: center; gap: 14px;
  margin-bottom: 24px; flex-wrap: wrap;
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
  color: var(--navy); margin: 0; line-height: 1.1;
}
.page-head p { font-size: .82rem; color: var(--ink-light); margin: 3px 0 0; }

/* ── Totals Grid ── */
.totals-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
  gap: 14px;
  margin-bottom: 24px;
}
.total-card {
  background: var(--card);
  border: 1px solid var(--border);
  border-radius: var(--radius);
  padding: 18px 20px;
  box-shadow: var(--shadow);
  position: relative;
  overflow: hidden;
}
.total-card::before {
  content: '';
  position: absolute;
  top: 0; left: 0; right: 0;
  height: 3px;
}
.total-card.asset::before    { background: #10b981; }
.total-card.liability::before { background: #ef4444; }
.total-card.revenue::before  { background: #3b82f6; }
.total-card.expense::before  { background: #f59e0b; }
.total-card.net::before      { background: linear-gradient(90deg, var(--navy), var(--accent)); }

.total-card-label {
  font-size: .7rem; font-weight: 700;
  text-transform: uppercase; letter-spacing: .6px;
  color: var(--ink-light); margin-bottom: 8px;
  display: flex; align-items: center; gap: 6px;
}
.total-card-label i { font-size: .85rem; }
.total-card-amount {
  font-family: var(--ff-head);
  font-size: 1.45rem; font-weight: 700;
  color: var(--navy); line-height: 1;
}
.total-card.asset    .total-card-amount { color: #059669; }
.total-card.liability .total-card-amount { color: #dc2626; }
.total-card.revenue  .total-card-amount { color: #2563eb; }
.total-card.expense  .total-card-amount { color: #d97706; }
.total-card.net      .total-card-amount { color: var(--navy); }

/* ── Filter Bar ── */
.filter-bar {
  display: flex; gap: 10px; flex-wrap: wrap;
  align-items: center; margin-bottom: 18px;
}
.search-wrap {
  position: relative; flex: 1 1 220px; max-width: 320px;
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

/* Filter chips */
.filter-chips { display: flex; gap: 6px; flex-wrap: wrap; }
.chip {
  padding: 6px 14px; border-radius: 999px;
  font-size: .76rem; font-weight: 700;
  border: 1.5px solid var(--border);
  background: var(--card); color: var(--ink-light);
  cursor: pointer; transition: all .15s;
  display: inline-flex; align-items: center; gap: 4px;
}
.chip:hover           { border-color: var(--accent); color: var(--accent); }
.chip.active          { background: var(--navy); color: #fff; border-color: var(--navy); }
.chip.active.asset    { background: #059669; border-color: #059669; }
.chip.active.liability { background: #dc2626; border-color: #dc2626; }
.chip.active.revenue  { background: #2563eb; border-color: #2563eb; }
.chip.active.expense  { background: #d97706; border-color: #d97706; }

/* ── Table Card ── */
.table-card {
  background: var(--card);
  border: 1px solid var(--border);
  border-radius: var(--radius);
  box-shadow: var(--shadow);
  overflow: hidden;
  margin-bottom: 20px;
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
.jnl-table { width: 100%; border-collapse: collapse; font-size: .87rem; }
.jnl-table thead th {
  background: #f8fafc;
  color: var(--ink-light);
  font-size: .69rem; font-weight: 700;
  text-transform: uppercase; letter-spacing: .6px;
  padding: 11px 16px;
  border-bottom: 2px solid var(--border);
  text-align: left; white-space: nowrap;
}
.jnl-table tbody tr {
  border-bottom: 1px solid var(--border);
  transition: background .12s;
}
.jnl-table tbody tr:last-child { border-bottom: none; }
.jnl-table tbody tr:hover { background: #f7faff; }
.jnl-table tbody td { padding: 12px 16px; vertical-align: middle; }

/* Name cell */
.name-cell { display: flex; align-items: center; gap: 10px; }
.name-icon {
  width: 34px; height: 34px; border-radius: 9px; flex-shrink: 0;
  display: flex; align-items: center; justify-content: center;
  font-size: .8rem; font-weight: 700; color: #fff;
}
.name-icon.asset     { background: linear-gradient(135deg, #059669, #10b981); }
.name-icon.liability { background: linear-gradient(135deg, #dc2626, #f87171); }
.name-icon.revenue   { background: linear-gradient(135deg, #1d4ed8, #3b82f6); }
.name-icon.expense   { background: linear-gradient(135deg, #d97706, #fbbf24); }

.name-main { font-weight: 600; color: var(--navy); font-size: .88rem; }

/* Type badge */
.type-badge {
  display: inline-flex; align-items: center; gap: 4px;
  padding: 3px 10px; border-radius: 999px;
  font-size: .71rem; font-weight: 700;
}
.type-asset     { background: #d1fae5; color: #065f46; }
.type-liability { background: #fee2e2; color: #991b1b; }
.type-revenue   { background: #dbeafe; color: #1d4ed8; }
.type-expense   { background: #fef3c7; color: #92400e; }

/* Source badge */
.src-badge {
  display: inline-flex; align-items: center; gap: 3px;
  padding: 2px 8px; border-radius: 999px;
  font-size: .68rem; font-weight: 700;
}
.src-billing { background: #ede9fe; color: #5b21b6; }
.src-expense { background: #fef3c7; color: #92400e; }
.src-journal { background: #e0f2fe; color: #0369a1; }
.src-payment { background: #d1fae5; color: #065f46; }

/* Amount */
.amt-positive { font-weight: 700; color: var(--success); }
.amt-negative { font-weight: 700; color: var(--danger); }

/* Date */
.date-val { font-size: .81rem; color: var(--ink-light); }

/* Empty */
.empty-row td { text-align: center; padding: 52px 16px; color: var(--ink-light); }
.empty-row i  { font-size: 2rem; display: block; margin-bottom: 8px; opacity: .3; }

/* ── Pagination ── */
.pagination-wrap {
  display: flex; justify-content: space-between;
  align-items: center; flex-wrap: wrap; gap: 12px;
  padding: 16px 20px;
  border-top: 1px solid var(--border);
  background: #f8fafc;
}
.page-info { font-size: .82rem; color: var(--ink-light); }
.page-btns { display: flex; gap: 4px; flex-wrap: wrap; }
.page-btn {
  width: 34px; height: 34px;
  display: flex; align-items: center; justify-content: center;
  border: 1.5px solid var(--border); border-radius: 8px;
  background: var(--card); color: var(--ink-light);
  font-size: .82rem; font-weight: 600;
  text-decoration: none; cursor: pointer;
  transition: all .15s;
}
.page-btn:hover { border-color: var(--accent); color: var(--accent); background: #eff6ff; }
.page-btn.active { background: var(--navy); color: #fff; border-color: var(--navy); }
.page-btn.disabled { opacity: .4; pointer-events: none; }
.page-btn.wide { width: auto; padding: 0 12px; }

/* ── Mobile Cards ── */
.mobile-cards { display: none; }
.m-jnl-card {
  background: var(--card);
  border: 1px solid var(--border);
  border-radius: var(--radius);
  box-shadow: var(--shadow);
  padding: 16px; margin-bottom: 10px;
}
.m-jnl-head {
  display: flex; justify-content: space-between;
  align-items: flex-start; gap: 10px; margin-bottom: 12px;
}
.m-jnl-row {
  display: flex; justify-content: space-between;
  align-items: center; padding: 6px 0;
  border-bottom: 1px solid var(--border);
  font-size: .82rem; gap: 8px;
}
.m-jnl-row:last-of-type { border-bottom: none; }
.m-lbl { color: var(--ink-light); font-weight: 600; font-size: .7rem;
         text-transform: uppercase; letter-spacing: .5px; flex-shrink: 0; }
.m-val { font-weight: 500; color: var(--ink); text-align: right; }

/* ── Responsive ── */
@media (max-width: 768px) {
  .cw { margin-left: var(--sidebar-w-sm); padding: 60px 14px 50px; }
  .cw.sidebar-collapsed { margin-left: 0; }
  .table-card  { display: none; }
  .mobile-cards { display: block; }
  .totals-grid { grid-template-columns: 1fr 1fr; }
  .page-head h1 { font-size: 1.3rem; }
  .page-head-icon { width: 44px; height: 44px; font-size: 1.2rem; border-radius: 11px; }
  .filter-bar { flex-direction: column; align-items: stretch; }
  .search-wrap { max-width: 100%; }
}

@media (max-width: 480px) {
  .cw { margin-left: 0 !important; padding: 56px 10px 40px; }
  .totals-grid { grid-template-columns: 1fr 1fr; gap: 10px; }
  .total-card-amount { font-size: 1.1rem; }
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
    <div class="page-head-icon"><i class="bi bi-book-half"></i></div>
    <div>
      <h1>Journal Accounts</h1>
      <p>Aggregated view of billing, payments, expenses, and journal entries</p>
    </div>
    <div style="margin-left:auto;">
      <span style="font-size:.82rem;color:var(--ink-light);">
        <?= $totalRecords ?> record<?= $totalRecords !== 1 ? 's' : '' ?>
        &nbsp;·&nbsp; Page <?= $page ?> of <?= $totalPages ?>
      </span>
    </div>
  </div>

  <!-- Totals -->
  <div class="totals-grid">
    <div class="total-card asset">
      <div class="total-card-label"><i class="bi bi-bank"></i> Total Assets</div>
      <div class="total-card-amount">₱<?= number_format($totals['Asset'], 2) ?></div>
    </div>
    <div class="total-card liability">
      <div class="total-card-label"><i class="bi bi-credit-card"></i> Total Liabilities</div>
      <div class="total-card-amount">₱<?= number_format($totals['Liability'], 2) ?></div>
    </div>
    <div class="total-card revenue">
      <div class="total-card-label"><i class="bi bi-graph-up-arrow"></i> Total Revenue</div>
      <div class="total-card-amount">₱<?= number_format($totals['Revenue'], 2) ?></div>
    </div>
    <div class="total-card expense">
      <div class="total-card-label"><i class="bi bi-wallet2"></i> Total Expenses</div>
      <div class="total-card-amount">₱<?= number_format($totals['Expense'], 2) ?></div>
    </div>
    <div class="total-card net">
      <div class="total-card-label"><i class="bi bi-calculator"></i> Net Position</div>
      <div class="total-card-amount" style="color:<?= $net >= 0 ? '#059669' : '#dc2626' ?>">
        <?= $net < 0 ? '−' : '' ?>₱<?= number_format(abs($net), 2) ?>
      </div>
    </div>
  </div>

  <!-- Filter Bar -->
  <div class="filter-bar">
    <div class="search-wrap">
      <i class="bi bi-search"></i>
      <input type="text" class="search-input" id="searchInput" placeholder="Search accounts…">
    </div>
    <div class="filter-chips">
      <span class="chip active" data-filter="all" onclick="filterType(this,'all')">
        <i class="bi bi-list"></i> All
      </span>
      <span class="chip" data-filter="asset" onclick="filterType(this,'asset')">
        <i class="bi bi-bank"></i> Asset
      </span>
      <span class="chip" data-filter="liability" onclick="filterType(this,'liability')">
        <i class="bi bi-credit-card"></i> Liability
      </span>
      <span class="chip" data-filter="revenue" onclick="filterType(this,'revenue')">
        <i class="bi bi-graph-up"></i> Revenue
      </span>
      <span class="chip" data-filter="expense" onclick="filterType(this,'expense')">
        <i class="bi bi-wallet2"></i> Expense
      </span>
    </div>
  </div>

  <!-- ── Desktop Table ── -->
  <div class="table-card">
    <div class="table-card-header">
      <i class="bi bi-table"></i> Account Ledger
      <span style="margin-left:auto;font-weight:400;opacity:.7;">
        Showing <?= count($pageRecords) ?> of <?= $totalRecords ?> records
      </span>
    </div>
    <div style="overflow-x:auto;">
      <table class="jnl-table" id="jnlTable">
        <thead>
          <tr>
            <th>#</th>
            <th>Account Name</th>
            <th>Type</th>
            <th>Source</th>
            <th>Amount</th>
            <th>Date</th>
          </tr>
        </thead>
        <tbody>
        <?php if (empty($pageRecords)): ?>
          <tr class="empty-row">
            <td colspan="6">
              <i class="bi bi-inbox"></i> No records found.
            </td>
          </tr>
        <?php else: ?>
          <?php foreach ($pageRecords as $i => $rec):
            $typeKey  = strtolower($rec['type']);
            $srcKey   = strtolower($rec['source']);
            $srcClassMap = [
              'billing' => 'src-billing',
              'expense' => 'src-expense',
              'journal' => 'src-journal',
              'payment' => 'src-payment',
            ];
            $srcClass = $srcClassMap[$srcKey] ?? 'src-journal';
            $initial  = strtoupper(substr($rec['name'], 0, 1));
            $isNeg    = $rec['amount'] < 0;
          ?>
          <tr class="jnl-row" data-type="<?= $typeKey ?>">
            <td style="color:var(--ink-light);font-size:.8rem;"><?= $start + $i + 1 ?></td>
            <td>
              <div class="name-cell">
                <div class="name-icon <?= $typeKey ?>"><?= $initial ?></div>
                <span class="name-main"><?= htmlspecialchars($rec['name']) ?></span>
              </div>
            </td>
            <td>
              <span class="type-badge type-<?= $typeKey ?>">
                <?= htmlspecialchars($rec['type']) ?>
              </span>
            </td>
            <td>
              <span class="src-badge <?= $srcClass ?>">
                <?= htmlspecialchars($rec['source']) ?>
              </span>
            </td>
            <td>
              <span class="<?= $isNeg ? 'amt-negative' : 'amt-positive' ?>">
                <?= $isNeg ? '−' : '' ?>₱<?= number_format(abs($rec['amount']), 2) ?>
              </span>
            </td>
            <td>
              <span class="date-val">
                <?= date("M d, Y", strtotime($rec['date'])) ?>
              </span>
            </td>
          </tr>
          <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
      </table>
    </div>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
    <div class="pagination-wrap">
      <span class="page-info">
        Records <?= $start + 1 ?>–<?= min($start + $limit, $totalRecords) ?> of <?= $totalRecords ?>
      </span>
      <div class="page-btns">
        <a href="?page=<?= $page - 1 ?>"
           class="page-btn wide <?= $page <= 1 ? 'disabled' : '' ?>">
          <i class="bi bi-chevron-left"></i>
        </a>
        <?php
          $range = 2;
          $start_p = max(1, $page - $range);
          $end_p   = min($totalPages, $page + $range);
          if ($start_p > 1) echo '<span class="page-btn disabled">…</span>';
          for ($i = $start_p; $i <= $end_p; $i++):
        ?>
          <a href="?page=<?= $i ?>"
             class="page-btn <?= $i === $page ? 'active' : '' ?>"><?= $i ?></a>
        <?php endfor;
          if ($end_p < $totalPages) echo '<span class="page-btn disabled">…</span>';
        ?>
        <a href="?page=<?= $page + 1 ?>"
           class="page-btn wide <?= $page >= $totalPages ? 'disabled' : '' ?>">
          <i class="bi bi-chevron-right"></i>
        </a>
      </div>
    </div>
    <?php endif; ?>
  </div>

  <!-- ── Mobile Cards ── -->
  <div class="mobile-cards" id="mobileCards">
    <?php if (empty($pageRecords)): ?>
      <div style="text-align:center;padding:40px;color:var(--ink-light);">
        <i class="bi bi-inbox" style="font-size:2rem;display:block;margin-bottom:10px;opacity:.3;"></i>
        No records found.
      </div>
    <?php else: ?>
      <?php foreach ($pageRecords as $i => $rec):
        $typeKey  = strtolower($rec['type']);
        $srcKey   = strtolower($rec['source']);
        $srcClassMap = [
          'billing' => 'src-billing',
          'expense' => 'src-expense',
          'journal' => 'src-journal',
          'payment' => 'src-payment',
        ];
        $srcClass = $srcClassMap[$srcKey] ?? 'src-journal';
        $isNeg    = $rec['amount'] < 0;
      ?>
      <div class="m-jnl-card mobile-row" data-type="<?= $typeKey ?>">
        <div class="m-jnl-head">
          <div>
            <div class="name-main" style="font-size:.92rem;">
              <?= htmlspecialchars($rec['name']) ?>
            </div>
            <div style="margin-top:4px;display:flex;gap:5px;flex-wrap:wrap;">
              <span class="type-badge type-<?= $typeKey ?>"><?= htmlspecialchars($rec['type']) ?></span>
              <span class="src-badge <?= $srcClass ?>"><?= htmlspecialchars($rec['source']) ?></span>
            </div>
          </div>
          <span class="<?= $isNeg ? 'amt-negative' : 'amt-positive' ?>" style="white-space:nowrap;">
            <?= $isNeg ? '−' : '' ?>₱<?= number_format(abs($rec['amount']), 2) ?>
          </span>
        </div>
        <div class="m-jnl-row">
          <span class="m-lbl">Date</span>
          <span class="m-val date-val"><?= date("M d, Y", strtotime($rec['date'])) ?></span>
        </div>
      </div>
      <?php endforeach; ?>

      <!-- Mobile Pagination -->
      <?php if ($totalPages > 1): ?>
      <div style="display:flex;justify-content:space-between;align-items:center;margin-top:16px;flex-wrap:wrap;gap:10px;">
        <span style="font-size:.8rem;color:var(--ink-light);">
          Page <?= $page ?> of <?= $totalPages ?>
        </span>
        <div style="display:flex;gap:6px;">
          <?php if ($page > 1): ?>
            <a href="?page=<?= $page - 1 ?>" class="page-btn wide">
              <i class="bi bi-chevron-left"></i> Prev
            </a>
          <?php endif; ?>
          <?php if ($page < $totalPages): ?>
            <a href="?page=<?= $page + 1 ?>" class="page-btn wide">
              Next <i class="bi bi-chevron-right"></i>
            </a>
          <?php endif; ?>
        </div>
      </div>
      <?php endif; ?>
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

/* ── Type filter chips ── */
let activeFilter = 'all';
function filterType(chip, type) {
    document.querySelectorAll('.chip').forEach(c => {
        c.classList.remove('active', 'asset', 'liability', 'revenue', 'expense');
    });
    chip.classList.add('active');
    if (type !== 'all') chip.classList.add(type);
    activeFilter = type;
    applyFilters();
}

/* ── Live search ── */
document.getElementById('searchInput').addEventListener('input', applyFilters);

function applyFilters() {
    const q = document.getElementById('searchInput').value.toLowerCase().trim();

    // Desktop rows
    document.querySelectorAll('#jnlTable tbody .jnl-row').forEach(row => {
        const matchType = activeFilter === 'all' || row.dataset.type === activeFilter;
        const matchQ    = !q || row.textContent.toLowerCase().includes(q);
        row.style.display = (matchType && matchQ) ? '' : 'none';
    });

    // Mobile cards
    document.querySelectorAll('#mobileCards .mobile-row').forEach(card => {
        const matchType = activeFilter === 'all' || card.dataset.type === activeFilter;
        const matchQ    = !q || card.textContent.toLowerCase().includes(q);
        card.style.display = (matchType && matchQ) ? '' : 'none';
    });
}
</script>
</body>
</html>