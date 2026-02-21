<?php
session_start();
include '../../SQL/config.php';

/* ================================
   PAGINATION + SEARCH
================================ */
$limit  = 10;
$page   = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$page   = max(1, $page);
$offset = ($page - 1) * $limit;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$like   = '%' . $conn->real_escape_string($search) . '%';

/* ================================
   FETCH PAYMENTS
================================ */
$payments_sql = "
SELECT
    pp.payment_id, pp.amount, pp.payment_method, pp.paid_at, pp.remarks,
    br.billing_id, br.patient_id,
    pi.fname, pi.mname, pi.lname
FROM paymongo_payments pp
LEFT JOIN billing_records br ON pp.billing_id = br.billing_id
LEFT JOIN patientinfo pi ON br.patient_id = pi.patient_id
" . ($search ? "WHERE pi.fname LIKE '$like' OR pi.lname LIKE '$like' OR pp.payment_method LIKE '$like' OR pp.payment_id LIKE '$like'" : "") . "
ORDER BY pp.paid_at DESC LIMIT $limit OFFSET $offset";

$pay_res  = $conn->query($payments_sql);
$payments = $pay_res ? $pay_res->fetch_all(MYSQLI_ASSOC) : [];

/* ================================
   FETCH RECEIPTS
================================ */
$receipts_sql = "
SELECT
    pr.receipt_id, pr.status, pr.payment_method, br.transaction_id,
    pr.created_at AS receipt_created,
    br.billing_id, br.patient_id, br.billing_date, br.grand_total, br.insurance_covered,
    pi.fname, pi.mname, pi.lname
FROM billing_records br
INNER JOIN (
    SELECT billing_id, MAX(receipt_id) AS latest_receipt_id
    FROM patient_receipt GROUP BY billing_id
) latest ON latest.billing_id = br.billing_id
INNER JOIN patient_receipt pr ON pr.receipt_id = latest.latest_receipt_id
LEFT JOIN patientinfo pi ON pi.patient_id = br.patient_id
" . ($search ? "WHERE pi.fname LIKE '$like' OR pi.lname LIKE '$like' OR pr.payment_method LIKE '$like' OR pr.receipt_id LIKE '$like'" : "") . "
ORDER BY br.billing_date DESC LIMIT $limit OFFSET $offset";

$rec_res  = $conn->query($receipts_sql);
$receipts = $rec_res ? $rec_res->fetch_all(MYSQLI_ASSOC) : [];

/* ================================
   TOTALS / STATS
================================ */
$total_payment_amount  = 0;
$total_receipt_amount  = 0;
foreach ($payments as $p) $total_payment_amount += (float)$p['amount'];
foreach ($receipts as $r) $total_receipt_amount += (float)$r['grand_total'];
$total_entries = count($payments) + count($receipts);

/* ================================
   PAGINATION
================================ */
$count_pay = $pay_res ? $pay_res->num_rows : 0;
$count_rec = $rec_res ? $rec_res->num_rows : 0;
$total_pages = max(1, ceil(($count_pay + $count_rec) / $limit));
$total_pages = max($page, $total_pages); // ensure current page is valid

/* ================================
   HELPER
================================ */
function getPatientName($fname, $mname, $lname, $patient_id = null) {
    $full = trim($fname . ' ' . $mname . ' ' . $lname);
    if (!empty(trim($full))) return $full;
    return $patient_id ? "Unknown (ID: {$patient_id})" : "Unknown Patient";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<title>Journal Entry — HMS</title>

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
  --debit-color:  #059669;
  --credit-color: #dc2626;
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

/* ── Stats ── */
.stats-row {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
  gap: 14px; margin-bottom: 24px;
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
  width: 44px; height: 44px; border-radius: 11px;
  display: flex; align-items: center; justify-content: center;
  font-size: 1.15rem; flex-shrink: 0;
}
.stat-icon.blue   { background: #dbeafe; color: #1d4ed8; }
.stat-icon.green  { background: #d1fae5; color: #065f46; }
.stat-icon.purple { background: #ede9fe; color: #5b21b6; }
.stat-num { font-size: 1.3rem; font-weight: 700; color: var(--navy); line-height: 1; }
.stat-lbl { font-size: .7rem; color: var(--ink-light); font-weight: 600;
            text-transform: uppercase; letter-spacing: .5px; margin-top: 2px; }

/* ── Search Card ── */
.search-card {
  background: var(--card);
  border: 1px solid var(--border);
  border-radius: var(--radius);
  box-shadow: var(--shadow);
  padding: 16px 20px;
  margin-bottom: 20px;
  display: flex; gap: 10px; flex-wrap: wrap; align-items: center;
}
.search-field { position: relative; flex: 1 1 240px; }
.search-field i {
  position: absolute; left: 13px; top: 50%;
  transform: translateY(-50%);
  color: var(--ink-light); font-size: .9rem; pointer-events: none;
}
.search-input {
  width: 100%; padding: 9px 14px 9px 36px;
  border: 1.5px solid var(--border); border-radius: 9px;
  font-family: var(--ff-body); font-size: .87rem;
  color: var(--ink); background: var(--surface);
  outline: none; transition: border-color .2s, box-shadow .2s;
}
.search-input:focus {
  border-color: var(--accent);
  box-shadow: 0 0 0 3px rgba(37,99,235,.12);
  background: var(--card);
}
.btn-search {
  padding: 9px 20px; background: var(--accent); color: #fff;
  border: none; border-radius: 9px;
  font-family: var(--ff-body); font-size: .87rem; font-weight: 700;
  cursor: pointer; display: inline-flex; align-items: center; gap: 6px;
  transition: background .15s; white-space: nowrap;
}
.btn-search:hover { background: #1d4ed8; }
.btn-reset {
  padding: 9px 16px; background: var(--card); color: var(--ink-light);
  border: 1.5px solid var(--border); border-radius: 9px;
  font-family: var(--ff-body); font-size: .87rem; font-weight: 600;
  cursor: pointer; text-decoration: none;
  display: inline-flex; align-items: center; gap: 6px;
  transition: all .15s; white-space: nowrap;
}
.btn-reset:hover { border-color: var(--accent); color: var(--accent); }

/* ── Section separator ── */
.section-label {
  display: flex; align-items: center; gap: 10px;
  font-size: .72rem; font-weight: 700;
  text-transform: uppercase; letter-spacing: .7px;
  color: var(--ink-light); margin: 20px 0 10px;
}
.section-label::after {
  content: ''; flex: 1; height: 1px; background: var(--border);
}

/* ── Table Card ── */
.table-card {
  background: var(--card);
  border: 1px solid var(--border);
  border-radius: var(--radius);
  box-shadow: var(--shadow);
  overflow: hidden;
  margin-bottom: 24px;
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
.jnl-table thead th.th-amount { text-align: right; }
.jnl-table thead th.th-action { text-align: center; }
.jnl-table tbody tr {
  border-bottom: 1px solid var(--border);
  transition: background .12s;
}
.jnl-table tbody tr:last-child { border-bottom: none; }
.jnl-table tbody tr:hover { background: #f7faff; }
.jnl-table tbody td { padding: 13px 16px; vertical-align: middle; }
.jnl-table tbody td.td-amount { text-align: right; }
.jnl-table tbody td.td-action { text-align: center; }

/* Patient cell */
.pat-cell { display: flex; align-items: center; gap: 10px; }
.pat-avatar {
  width: 34px; height: 34px; border-radius: 50%; flex-shrink: 0;
  background: linear-gradient(135deg, var(--navy), var(--accent));
  color: #fff; font-size: .76rem; font-weight: 700;
  display: flex; align-items: center; justify-content: center;
}
.pat-name { font-weight: 600; color: var(--navy); font-size: .88rem; }
.pat-method { font-size: .73rem; color: var(--ink-light); margin-top: 1px; }

/* Debit / Credit cells */
.debit-cell {
  display: inline-flex; align-items: center; gap: 5px;
  padding: 4px 10px; border-radius: 999px;
  background: #d1fae5; color: #065f46;
  font-size: .74rem; font-weight: 700; white-space: nowrap;
}
.credit-cell {
  display: inline-flex; align-items: center; gap: 5px;
  padding: 4px 10px; border-radius: 999px;
  background: #fee2e2; color: #991b1b;
  font-size: .74rem; font-weight: 700; white-space: nowrap;
}

/* Amount */
.amount-val { font-weight: 700; color: var(--navy); font-size: .92rem; }

/* Date */
.date-val  { font-size: .82rem; color: var(--ink-light); }
.date-time { font-size: .72rem; color: var(--ink-light); opacity: .7; }

/* Status badge */
.badge-posted { background: #d1fae5; color: #065f46; border-radius: 999px;
                padding: 3px 10px; font-size: .71rem; font-weight: 700; }
.badge-draft  { background: #fef3c7; color: #92400e; border-radius: 999px;
                padding: 3px 10px; font-size: .71rem; font-weight: 700; }

/* Reference mono */
.ref-mono {
  font-family: 'Courier New', monospace;
  font-size: .78rem; color: var(--ink-light);
  background: #f1f5f9; border-radius: 6px;
  padding: 2px 8px; display: inline-block;
  max-width: 130px; overflow: hidden;
  text-overflow: ellipsis; white-space: nowrap;
}

/* View button */
.btn-view-entry {
  display: inline-flex; align-items: center; gap: 5px;
  padding: 5px 14px; border-radius: 8px;
  background: #eff6ff; color: var(--accent);
  border: 1.5px solid #bfdbfe;
  font-family: var(--ff-body); font-size: .78rem; font-weight: 700;
  text-decoration: none; transition: all .15s;
}
.btn-view-entry:hover { background: var(--accent); color: #fff; border-color: var(--accent); }

/* Empty row */
.empty-row td { text-align: center; padding: 40px 16px; color: var(--ink-light); }
.empty-row i  { font-size: 1.8rem; display: block; margin-bottom: 8px; opacity: .3; }

/* ── Pagination ── */
.pagination-wrap {
  display: flex; justify-content: space-between;
  align-items: center; flex-wrap: wrap; gap: 10px;
  padding: 14px 20px; border-top: 1px solid var(--border);
  background: #f8fafc;
}
.page-info { font-size: .82rem; color: var(--ink-light); }
.page-btns { display: flex; gap: 4px; }
.page-btn {
  width: 34px; height: 34px;
  display: flex; align-items: center; justify-content: center;
  border: 1.5px solid var(--border); border-radius: 8px;
  background: var(--card); color: var(--ink-light);
  font-size: .82rem; font-weight: 600;
  text-decoration: none; transition: all .15s;
}
.page-btn:hover  { border-color: var(--accent); color: var(--accent); background: #eff6ff; }
.page-btn.active { background: var(--navy); color: #fff; border-color: var(--navy); }
.page-btn.disabled { opacity: .4; pointer-events: none; }
.page-btn.wide { width: auto; padding: 0 12px; }

/* ── Mobile Cards ── */
.mobile-cards { display: none; }
.m-entry-card {
  background: var(--card);
  border: 1px solid var(--border);
  border-radius: var(--radius);
  box-shadow: var(--shadow);
  padding: 16px; margin-bottom: 10px;
}
.m-entry-head {
  display: flex; justify-content: space-between;
  align-items: flex-start; gap: 10px; margin-bottom: 12px;
}
.m-entry-row {
  display: flex; justify-content: space-between;
  align-items: center; padding: 6px 0;
  border-bottom: 1px solid var(--border);
  font-size: .82rem; gap: 8px;
}
.m-entry-row:last-of-type { border-bottom: none; }
.m-lbl { color: var(--ink-light); font-weight: 600; font-size: .69rem;
         text-transform: uppercase; letter-spacing: .5px; flex-shrink: 0; }
.m-val { font-weight: 500; color: var(--ink); text-align: right; }
.m-actions { margin-top: 12px; }
.m-actions .btn-view-entry { width: 100%; justify-content: center; }

/* Source tag */
.src-tag {
  display: inline-block; padding: 2px 8px; border-radius: 999px;
  font-size: .68rem; font-weight: 700;
}
.src-payment { background: #ede9fe; color: #5b21b6; }
.src-receipt  { background: #dbeafe; color: #1d4ed8; }

/* ── Responsive ── */
@media (max-width: 768px) {
  .cw { margin-left: var(--sidebar-w-sm); padding: 60px 14px 50px; }
  .cw.sidebar-collapsed { margin-left: 0; }
  .table-card  { display: none; }
  .mobile-cards { display: block; }
  .search-card  { padding: 14px; }
  .page-head h1 { font-size: 1.3rem; }
  .page-head-icon { width: 44px; height: 44px; font-size: 1.2rem; border-radius: 11px; }
  .stats-row { grid-template-columns: repeat(3, 1fr); }
}

@media (max-width: 480px) {
  .cw { margin-left: 0 !important; padding: 56px 10px 40px; }
  .search-card { flex-direction: column; }
  .search-field, .btn-search, .btn-reset { width: 100%; justify-content: center; }
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
    <div class="page-head-icon"><i class="bi bi-journal-bookmark-fill"></i></div>
    <div>
      <h1>Journal Entry</h1>
      <p>Payment transactions recorded in the billing system</p>
    </div>
    <div style="margin-left:auto;">
      <span style="font-size:.82rem;color:var(--ink-light);">
        Page <?= $page ?> of <?= $total_pages ?>
      </span>
    </div>
  </div>

  <!-- Stats -->
  <div class="stats-row">
    <div class="stat-card">
      <div class="stat-icon blue"><i class="bi bi-receipt"></i></div>
      <div>
        <div class="stat-num"><?= $total_entries ?></div>
        <div class="stat-lbl">Entries (page)</div>
      </div>
    </div>
    <div class="stat-card">
      <div class="stat-icon green"><i class="bi bi-currency-exchange"></i></div>
      <div>
        <div class="stat-num">₱<?= number_format($total_payment_amount, 0) ?></div>
        <div class="stat-lbl">Payments</div>
      </div>
    </div>
    <div class="stat-card">
      <div class="stat-icon purple"><i class="bi bi-file-earmark-text"></i></div>
      <div>
        <div class="stat-num">₱<?= number_format($total_receipt_amount, 0) ?></div>
        <div class="stat-lbl">Receipts</div>
      </div>
    </div>
  </div>

  <!-- Search -->
  <form method="GET" class="search-card" action="journal_entry.php">
    <div class="search-field">
      <i class="bi bi-search"></i>
      <input type="text" name="search" class="search-input"
             placeholder="Search patient name, method, reference…"
             value="<?= htmlspecialchars($search) ?>">
    </div>
    <button type="submit" class="btn-search">
      <i class="bi bi-search"></i> Search
    </button>
    <a href="journal_entry.php" class="btn-reset">
      <i class="bi bi-x-circle"></i> Reset
    </a>
    <?php if ($search): ?>
      <span style="font-size:.82rem;color:var(--ink-light);align-self:center;">
        Results for "<strong><?= htmlspecialchars($search) ?></strong>"
      </span>
    <?php endif; ?>
  </form>

  <!-- ══════════════ PAYMENTS TABLE ══════════════ -->
  <?php if (!empty($payments)): ?>
  <div class="section-label">
    <i class="bi bi-credit-card-2-front"></i> Payment Transactions
    <span style="background:#ede9fe;color:#5b21b6;padding:2px 10px;border-radius:999px;font-size:.7rem;margin-left:4px;">
      <?= count($payments) ?> record<?= count($payments) !== 1 ? 's' : '' ?>
    </span>
  </div>

  <div class="table-card">
    <div class="table-card-header">
      <i class="bi bi-table"></i> Payments Ledger
    </div>
    <div style="overflow-x:auto;">
      <table class="jnl-table">
        <thead>
          <tr>
            <th>Date &amp; Time</th>
            <th>Patient</th>
            <th>Debit</th>
            <th>Credit</th>
            <th class="th-amount">Amount</th>
            <th>Reference</th>
            <th class="th-action">Action</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($payments as $p):
            $full_name = getPatientName($p['fname'], $p['mname'], $p['lname'], $p['patient_id']);
            $initials  = strtoupper(substr($full_name, 0, 1));
          ?>
          <tr>
            <td>
              <div class="date-val"><?= date('M d, Y', strtotime($p['paid_at'])) ?></div>
              <div class="date-time"><?= date('h:i A', strtotime($p['paid_at'])) ?></div>
            </td>
            <td>
              <div class="pat-cell">
                <div class="pat-avatar"><?= $initials ?></div>
                <div>
                  <div class="pat-name"><?= htmlspecialchars($full_name) ?></div>
                  <div class="pat-method"><?= htmlspecialchars($p['payment_method']) ?></div>
                </div>
              </div>
            </td>
            <td><span class="debit-cell"><i class="bi bi-arrow-down-circle"></i> Cash / Bank</span></td>
            <td><span class="credit-cell"><i class="bi bi-arrow-up-circle"></i> Receivable</span></td>
            <td class="td-amount"><span class="amount-val">₱<?= number_format($p['amount'], 2) ?></span></td>
            <td>
              <span class="ref-mono" title="<?= htmlspecialchars($p['payment_id']) ?>">
                <?= htmlspecialchars($p['payment_id']) ?>
              </span>
            </td>
            <td class="td-action">
              <a href="journal_entry_line.php?payment_id=<?= urlencode($p['payment_id']) ?>"
                 class="btn-view-entry">
                <i class="bi bi-eye"></i> View
              </a>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Payments Mobile Cards -->
  <div class="mobile-cards">
    <?php foreach ($payments as $p):
      $full_name = getPatientName($p['fname'], $p['mname'], $p['lname'], $p['patient_id']);
      $initials  = strtoupper(substr($full_name, 0, 1));
    ?>
    <div class="m-entry-card">
      <div class="m-entry-head">
        <div class="pat-cell">
          <div class="pat-avatar"><?= $initials ?></div>
          <div>
            <div class="pat-name"><?= htmlspecialchars($full_name) ?></div>
            <span class="src-tag src-payment">Payment</span>
          </div>
        </div>
        <span class="amount-val">₱<?= number_format($p['amount'], 2) ?></span>
      </div>
      <div class="m-entry-row">
        <span class="m-lbl">Date</span>
        <span class="m-val"><?= date('M d, Y h:i A', strtotime($p['paid_at'])) ?></span>
      </div>
      <div class="m-entry-row">
        <span class="m-lbl">Debit</span>
        <span class="m-val"><span class="debit-cell">Cash / Bank</span></span>
      </div>
      <div class="m-entry-row">
        <span class="m-lbl">Credit</span>
        <span class="m-val"><span class="credit-cell">Receivable</span></span>
      </div>
      <div class="m-entry-row">
        <span class="m-lbl">Method</span>
        <span class="m-val"><?= htmlspecialchars($p['payment_method']) ?></span>
      </div>
      <div class="m-actions">
        <a href="journal_entry_line.php?payment_id=<?= urlencode($p['payment_id']) ?>"
           class="btn-view-entry">
          <i class="bi bi-eye"></i> View Entry
        </a>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <!-- ══════════════ RECEIPTS TABLE ══════════════ -->
  <?php if (!empty($receipts)): ?>
  <div class="section-label">
    <i class="bi bi-file-earmark-text"></i> Billing Receipts
    <span style="background:#dbeafe;color:#1d4ed8;padding:2px 10px;border-radius:999px;font-size:.7rem;margin-left:4px;">
      <?= count($receipts) ?> record<?= count($receipts) !== 1 ? 's' : '' ?>
    </span>
  </div>

  <div class="table-card">
    <div class="table-card-header">
      <i class="bi bi-table"></i> Receipts Ledger
    </div>
    <div style="overflow-x:auto;">
      <table class="jnl-table">
        <thead>
          <tr>
            <th>Date &amp; Time</th>
            <th>Patient</th>
            <th>Debit</th>
            <th>Credit</th>
            <th class="th-amount">Total</th>
            <th>Status</th>
            <th>Receipt ID</th>
            <th class="th-action">Action</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($receipts)): ?>
            <tr class="empty-row">
              <td colspan="8"><i class="bi bi-inbox"></i> No receipt records found.</td>
            </tr>
          <?php else: ?>
          <?php foreach ($receipts as $r):
            $full_name = getPatientName($r['fname'], $r['mname'], $r['lname'], $r['patient_id']);
            $initials  = strtoupper(substr($full_name, 0, 1));
            $is_posted = strtolower($r['status']) === 'posted';
          ?>
          <tr>
            <td>
              <div class="date-val"><?= date('M d, Y', strtotime($r['receipt_created'])) ?></div>
              <div class="date-time"><?= date('h:i A', strtotime($r['receipt_created'])) ?></div>
            </td>
            <td>
              <div class="pat-cell">
                <div class="pat-avatar"><?= $initials ?></div>
                <div>
                  <div class="pat-name"><?= htmlspecialchars($full_name) ?></div>
                  <div class="pat-method"><?= htmlspecialchars($r['payment_method'] ?: 'Unpaid') ?></div>
                </div>
              </div>
            </td>
            <td><span class="debit-cell"><i class="bi bi-arrow-down-circle"></i> Cash / Bank</span></td>
            <td><span class="credit-cell"><i class="bi bi-arrow-up-circle"></i> Receivable</span></td>
            <td class="td-amount"><span class="amount-val">₱<?= number_format($r['grand_total'], 2) ?></span></td>
            <td>
              <span class="<?= $is_posted ? 'badge-posted' : 'badge-draft' ?>">
                <?= $is_posted ? 'Posted' : 'Draft' ?>
              </span>
            </td>
            <td>
              <span class="ref-mono" title="<?= $r['receipt_id'] ?>">#<?= $r['receipt_id'] ?></span>
            </td>
            <td class="td-action">
              <a href="journal_entry_line.php?receipt_id=<?= urlencode($r['receipt_id']) ?>"
                 class="btn-view-entry">
                <i class="bi bi-eye"></i> View
              </a>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Receipts Mobile Cards -->
  <div class="mobile-cards">
    <?php foreach ($receipts as $r):
      $full_name = getPatientName($r['fname'], $r['mname'], $r['lname'], $r['patient_id']);
      $initials  = strtoupper(substr($full_name, 0, 1));
      $is_posted = strtolower($r['status']) === 'posted';
    ?>
    <div class="m-entry-card">
      <div class="m-entry-head">
        <div class="pat-cell">
          <div class="pat-avatar"><?= $initials ?></div>
          <div>
            <div class="pat-name"><?= htmlspecialchars($full_name) ?></div>
            <span class="src-tag src-receipt">Receipt</span>
          </div>
        </div>
        <span class="amount-val">₱<?= number_format($r['grand_total'], 2) ?></span>
      </div>
      <div class="m-entry-row">
        <span class="m-lbl">Date</span>
        <span class="m-val"><?= date('M d, Y', strtotime($r['receipt_created'])) ?></span>
      </div>
      <div class="m-entry-row">
        <span class="m-lbl">Debit</span>
        <span class="m-val"><span class="debit-cell">Cash / Bank</span></span>
      </div>
      <div class="m-entry-row">
        <span class="m-lbl">Credit</span>
        <span class="m-val"><span class="credit-cell">Receivable</span></span>
      </div>
      <div class="m-entry-row">
        <span class="m-lbl">Status</span>
        <span class="m-val">
          <span class="<?= $is_posted ? 'badge-posted' : 'badge-draft' ?>">
            <?= $is_posted ? 'Posted' : 'Draft' ?>
          </span>
        </span>
      </div>
      <div class="m-entry-row">
        <span class="m-lbl">Receipt ID</span>
        <span class="m-val">#<?= $r['receipt_id'] ?></span>
      </div>
      <div class="m-actions">
        <a href="journal_entry_line.php?receipt_id=<?= urlencode($r['receipt_id']) ?>"
           class="btn-view-entry">
          <i class="bi bi-eye"></i> View Entry
        </a>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <!-- Empty state (both empty) -->
  <?php if (empty($payments) && empty($receipts)): ?>
  <div class="table-card">
    <div style="text-align:center;padding:56px 16px;color:var(--ink-light);">
      <i class="bi bi-inbox" style="font-size:2.2rem;display:block;margin-bottom:10px;opacity:.3;"></i>
      <?= $search ? 'No entries match your search.' : 'No journal entries found.' ?>
    </div>
  </div>
  <?php endif; ?>

  <!-- Pagination -->
  <?php if ($total_pages > 1): ?>
  <div class="table-card" style="margin-top:0;">
    <div class="pagination-wrap">
      <span class="page-info">Page <?= $page ?> of <?= $total_pages ?></span>
      <div class="page-btns">
        <a href="?page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>"
           class="page-btn wide <?= $page <= 1 ? 'disabled' : '' ?>">
          <i class="bi bi-chevron-left"></i> Prev
        </a>
        <?php
          $rng = 2;
          $sp  = max(1, $page - $rng);
          $ep  = min($total_pages, $page + $rng);
          if ($sp > 1) echo '<span class="page-btn disabled">…</span>';
          for ($i = $sp; $i <= $ep; $i++):
        ?>
          <a href="?page=<?= $i ?>&search=<?= urlencode($search) ?>"
             class="page-btn <?= $i === $page ? 'active' : '' ?>"><?= $i ?></a>
        <?php endfor;
          if ($ep < $total_pages) echo '<span class="page-btn disabled">…</span>';
        ?>
        <a href="?page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>"
           class="page-btn wide <?= $page >= $total_pages ? 'disabled' : '' ?>">
          Next <i class="bi bi-chevron-right"></i>
        </a>
      </div>
    </div>
  </div>
  <?php endif; ?>

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
</script>
</body>
</html>