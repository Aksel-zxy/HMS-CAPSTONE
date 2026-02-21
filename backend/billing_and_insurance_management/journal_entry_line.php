<?php
session_start();
include '../../SQL/config.php';

/* =========================
   Determine which entry to load
========================= */
$entry_id   = isset($_GET['entry_id'])   ? intval($_GET['entry_id'])   : 0;
$payment_id = $_GET['payment_id']        ?? null;
$receipt_id = isset($_GET['receipt_id']) ? intval($_GET['receipt_id']) : null;

$payment      = null;
$entry        = null;
$lines        = [];
$total_debit  = 0;
$total_credit = 0;
$paid_at      = null;
$page_title   = 'Journal Entry Details';

/* ── Load by entry_id ── */
if ($entry_id > 0) {
    $stmt = $conn->prepare("SELECT * FROM journal_entries WHERE entry_id = ?");
    $stmt->bind_param("i", $entry_id);
    $stmt->execute();
    $entry = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$entry) { header("Location: journal_entry.php"); exit; }

    $stmt = $conn->prepare("SELECT * FROM journal_entry_lines WHERE entry_id = ?");
    $stmt->bind_param("i", $entry_id);
    $stmt->execute();
    $lines = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    foreach ($lines as $line) {
        $total_debit  += floatval($line['debit']  ?? 0);
        $total_credit += floatval($line['credit'] ?? 0);
    }
    $page_title = 'Journal Entry #' . $entry_id;

/* ── Load by payment_id ── */
} elseif ($payment_id) {
    $stmt = $conn->prepare("
        SELECT pp.*, pi.fname, pi.mname, pi.lname
        FROM paymongo_payments pp
        LEFT JOIN patientinfo pi ON pp.patient_id = pi.patient_id
        WHERE pp.payment_id = ?
    ");
    $stmt->bind_param("s", $payment_id);
    $stmt->execute();
    $payment = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$payment) { header("Location: journal_entry.php"); exit; }

    $full_name   = trim($payment['fname'] . ' ' . $payment['mname'] . ' ' . $payment['lname']);
    $amount      = floatval($payment['amount']);
    $paid_at     = $payment['paid_at'] ?? null;
    $description = "Payment received from {$full_name}\nMethod: {$payment['payment_method']}\nRemarks: " . ($payment['remarks'] ?? '—');

    $lines[] = ['account_name' => 'Cash / Bank',        'debit' => $amount,  'credit' => 0,       'description' => $description];
    $lines[] = ['account_name' => 'Patient Receivable', 'debit' => 0,        'credit' => $amount, 'description' => 'Settlement of patient account'];
    $total_debit  = $amount;
    $total_credit = $amount;
    $page_title   = 'Payment Entry — ' . $payment_id;

/* ── Load by receipt_id ── */
} elseif ($receipt_id) {
    $stmt = $conn->prepare("
        SELECT pr.*, br.grand_total, br.billing_date, br.transaction_id, br.insurance_covered,
               pi.fname, pi.mname, pi.lname
        FROM patient_receipt pr
        LEFT JOIN billing_records br ON pr.billing_id = br.billing_id
        LEFT JOIN patientinfo pi ON pi.patient_id = br.patient_id
        WHERE pr.receipt_id = ?
    ");
    $stmt->bind_param("i", $receipt_id);
    $stmt->execute();
    $receipt = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$receipt) { header("Location: journal_entry.php"); exit; }

    $full_name = trim($receipt['fname'] . ' ' . $receipt['mname'] . ' ' . $receipt['lname']);
    $amount    = floatval($receipt['grand_total'] ?? 0);
    $ins       = floatval($receipt['insurance_covered'] ?? 0);
    $paid_at   = $receipt['created_at'] ?? null;
    $method    = $receipt['payment_method'] ?? 'N/A';

    $lines[] = ['account_name' => 'Cash / Bank',        'debit' => $amount,  'credit' => 0,       'description' => "Receipt for {$full_name}\nMethod: {$method}"];
    $lines[] = ['account_name' => 'Patient Receivable', 'debit' => 0,        'credit' => $amount, 'description' => 'Settlement of billing record'];
    if ($ins > 0) {
        $lines[] = ['account_name' => 'Insurance Receivable', 'debit' => 0,  'credit' => $ins,    'description' => 'Insurance coverage applied'];
    }

    $total_debit  = $amount;
    $total_credit = $amount + $ins;
    $page_title   = 'Receipt Entry #' . $receipt_id;

} else {
    header("Location: journal_entry.php");
    exit;
}

/* ── Balance check ── */
$is_balanced = abs($total_debit - $total_credit) < 0.01;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<title><?= htmlspecialchars($page_title) ?> — HMS</title>

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
  max-width: calc(1000px + var(--sidebar-w));
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
  font-size: clamp(1.2rem, 2.5vw, 1.7rem);
  color: var(--navy); margin: 0; line-height: 1.1;
}
.page-head p { font-size: .82rem; color: var(--ink-light); margin: 3px 0 0; }
.head-actions { margin-left: auto; display: flex; gap: 8px; flex-wrap: wrap; }

/* ── Buttons ── */
.btn-back {
  padding: 9px 18px;
  background: var(--card); color: var(--ink-light);
  border: 1.5px solid var(--border); border-radius: 9px;
  font-family: var(--ff-body); font-size: .87rem; font-weight: 600;
  cursor: pointer; text-decoration: none;
  display: inline-flex; align-items: center; gap: 6px;
  transition: all .15s;
}
.btn-back:hover { border-color: var(--accent); color: var(--accent); background: #eff6ff; }

.btn-print {
  padding: 9px 18px;
  background: var(--navy); color: #fff;
  border: none; border-radius: 9px;
  font-family: var(--ff-body); font-size: .87rem; font-weight: 700;
  cursor: pointer;
  display: inline-flex; align-items: center; gap: 6px;
  transition: background .15s;
}
.btn-print:hover { background: #1e3a6e; }

/* ── Meta Info Card ── */
.meta-card {
  background: var(--card);
  border: 1px solid var(--border);
  border-radius: var(--radius);
  box-shadow: var(--shadow);
  overflow: hidden;
  margin-bottom: 20px;
}
.meta-card-header {
  background: var(--navy);
  padding: 13px 20px;
  color: rgba(255,255,255,.8);
  font-size: .72rem; font-weight: 700;
  text-transform: uppercase; letter-spacing: .7px;
  display: flex; align-items: center; gap: 8px;
}
.meta-card-body {
  padding: 20px 24px;
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
  gap: 16px 24px;
}
.meta-field {}
.meta-label {
  font-size: .69rem; font-weight: 700;
  text-transform: uppercase; letter-spacing: .6px;
  color: var(--ink-light); margin-bottom: 4px;
}
.meta-value {
  font-size: .9rem; font-weight: 600; color: var(--navy);
  display: flex; align-items: center; gap: 6px;
}
.meta-value.muted { color: var(--ink-light); font-weight: 500; }

/* Status badge */
.badge-posted   { background: #d1fae5; color: #065f46; border-radius: 999px; padding: 3px 12px; font-size: .72rem; font-weight: 700; }
.badge-draft    { background: #fef3c7; color: #92400e; border-radius: 999px; padding: 3px 12px; font-size: .72rem; font-weight: 700; }
.badge-balanced { background: #d1fae5; color: #065f46; border-radius: 999px; padding: 3px 12px; font-size: .72rem; font-weight: 700; }
.badge-unbal    { background: #fee2e2; color: #991b1b; border-radius: 999px; padding: 3px 12px; font-size: .72rem; font-weight: 700; }

/* ── Entry Lines Table ── */
.entry-card {
  background: var(--card);
  border: 1px solid var(--border);
  border-radius: var(--radius);
  box-shadow: var(--shadow);
  overflow: hidden;
  margin-bottom: 20px;
}
.entry-card-header {
  background: var(--navy);
  padding: 13px 20px;
  color: rgba(255,255,255,.8);
  font-size: .72rem; font-weight: 700;
  text-transform: uppercase; letter-spacing: .7px;
  display: flex; align-items: center; gap: 8px;
}

.entry-table { width: 100%; border-collapse: collapse; font-size: .88rem; }
.entry-table thead th {
  background: #f8fafc;
  color: var(--ink-light);
  font-size: .69rem; font-weight: 700;
  text-transform: uppercase; letter-spacing: .6px;
  padding: 11px 16px;
  border-bottom: 2px solid var(--border);
  text-align: left;
}
.entry-table thead th.th-num { text-align: right; }
.entry-table tbody tr {
  border-bottom: 1px solid var(--border);
  transition: background .12s;
}
.entry-table tbody tr:last-child { border-bottom: none; }
.entry-table tbody tr:hover { background: #f7faff; }
.entry-table tbody td { padding: 14px 16px; vertical-align: top; }
.entry-table tbody td.td-num { text-align: right; font-variant-numeric: tabular-nums; }

/* Account cell */
.acct-cell { display: flex; align-items: center; gap: 10px; }
.acct-icon {
  width: 34px; height: 34px; border-radius: 9px; flex-shrink: 0;
  display: flex; align-items: center; justify-content: center;
  font-size: .82rem; font-weight: 700; color: #fff;
}
.acct-icon.debit-icon  { background: linear-gradient(135deg, #059669, #34d399); }
.acct-icon.credit-icon { background: linear-gradient(135deg, #dc2626, #f87171); }
.acct-icon.neutral-icon { background: linear-gradient(135deg, #2563eb, #60a5fa); }

.acct-name { font-weight: 700; color: var(--navy); font-size: .9rem; }

/* Debit/credit amounts */
.debit-amt  { font-weight: 700; color: #059669; font-size: .92rem; }
.credit-amt { font-weight: 700; color: #dc2626; font-size: .92rem; }
.empty-amt  { color: var(--border); font-size: .82rem; }

/* Description */
.desc-text {
  font-size: .8rem; color: var(--ink-light);
  white-space: pre-line; line-height: 1.5;
}

/* Tfoot totals */
.entry-table tfoot tr { background: #f8fafc; }
.entry-table tfoot td {
  padding: 12px 16px;
  border-top: 2px solid var(--border);
  font-weight: 700; font-size: .9rem;
  color: var(--navy);
}
.entry-table tfoot td.td-num { text-align: right; }
.entry-table tfoot td.total-label { color: var(--ink-light); font-size: .72rem;
  text-transform: uppercase; letter-spacing: .6px; }

/* Balance bar */
.balance-bar {
  display: flex; align-items: center; gap: 12px;
  padding: 14px 20px;
  border-top: 1px solid var(--border);
  background: #f8fafc;
  flex-wrap: wrap;
}
.balance-icon { font-size: 1.1rem; }
.balance-text { font-size: .85rem; font-weight: 600; }

/* ── Mobile line cards ── */
.mobile-lines { display: none; }
.m-line-card {
  background: var(--card);
  border: 1px solid var(--border);
  border-radius: 11px;
  padding: 14px; margin-bottom: 10px;
  box-shadow: 0 1px 6px rgba(11,29,58,.05);
}
.m-line-head {
  display: flex; justify-content: space-between;
  align-items: flex-start; gap: 10px; margin-bottom: 10px;
}
.m-line-row {
  display: flex; justify-content: space-between;
  align-items: center; padding: 6px 0;
  border-bottom: 1px solid var(--border);
  font-size: .82rem; gap: 8px;
}
.m-line-row:last-of-type { border-bottom: none; }
.m-lbl { color: var(--ink-light); font-weight: 600; font-size: .69rem;
         text-transform: uppercase; letter-spacing: .5px; flex-shrink: 0; }
.m-val { font-weight: 500; color: var(--ink); text-align: right; }

/* Totals summary card */
.totals-summary {
  background: var(--card);
  border: 1px solid var(--border);
  border-radius: var(--radius);
  box-shadow: var(--shadow);
  padding: 20px 24px;
  margin-bottom: 20px;
  display: flex; gap: 20px; flex-wrap: wrap;
  justify-content: flex-end; align-items: center;
}
.totals-item { text-align: right; }
.totals-item-label {
  font-size: .69rem; font-weight: 700;
  text-transform: uppercase; letter-spacing: .6px; color: var(--ink-light);
}
.totals-item-val { font-size: 1.2rem; font-weight: 700; color: var(--navy); margin-top: 2px; }
.totals-item-val.debit-color  { color: #059669; }
.totals-item-val.credit-color { color: #dc2626; }
.totals-divider { width: 1px; background: var(--border); align-self: stretch; }

/* ── Print styles ── */
@media print {
  /* Hide absolutely everything except the content wrapper */
  body > *:not(.cw) { display: none !important; }

  /* Also catch sidebar rendered inside body directly */
  #mySidebar,
  .billing-sidebar,
  .sidebar,
  nav,
  aside,
  [id*="sidebar"],
  [class*="sidebar"],
  #sidebarToggle,
  .head-actions,
  .no-print,
  .btn-back,
  .btn-print,
  .mobile-lines { display: none !important; }

  /* Reset content wrapper so it fills the page */
  .cw {
    margin-left: 0 !important;
    padding: 20px !important;
    max-width: 100% !important;
    width: 100% !important;
  }

  /* Clean card styles for paper */
  .meta-card,
  .entry-card {
    box-shadow: none !important;
    border: 1px solid #ccc !important;
    break-inside: avoid;
  }

  .meta-card-header,
  .entry-card-header {
    background: #0b1d3a !important;
    -webkit-print-color-adjust: exact;
    print-color-adjust: exact;
  }

  body { background: white !important; }
  .entry-table { font-size: 12px; }
  h1 { font-size: 1.3rem !important; }
}

/* ── Responsive ── */
@media (max-width: 768px) {
  .cw { margin-left: var(--sidebar-w-sm); padding: 60px 14px 50px; }
  .cw.sidebar-collapsed { margin-left: 0; }
  .entry-card > div:first-of-type { display: none; } /* hide desktop table */
  .mobile-lines { display: block; }
  .meta-card-body { grid-template-columns: 1fr 1fr; }
  .head-actions { margin-left: 0; width: 100%; }
  .btn-back, .btn-print { width: 100%; justify-content: center; }
  .totals-summary { justify-content: center; }
}

@media (max-width: 480px) {
  .cw { margin-left: 0 !important; padding: 56px 10px 40px; }
  .meta-card-body { grid-template-columns: 1fr; gap: 12px; }
  .page-head-icon { width: 44px; height: 44px; font-size: 1.2rem; border-radius: 11px; }
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
    <div class="page-head-icon"><i class="bi bi-journal-check"></i></div>
    <div>
      <h1><?= htmlspecialchars($page_title) ?></h1>
      <p>Double-entry accounting record</p>
    </div>
    <div class="head-actions no-print">
      <a href="journal_entry.php" class="btn-back">
        <i class="bi bi-arrow-left"></i> Back
      </a>
      <button onclick="window.print()" class="btn-print">
        <i class="bi bi-printer"></i> Print
      </button>
    </div>
  </div>

  <!-- Meta Info -->
  <div class="meta-card">
    <div class="meta-card-header">
      <i class="bi bi-info-circle"></i> Entry Information
    </div>
    <div class="meta-card-body">
      <div class="meta-field">
        <div class="meta-label">Date</div>
        <div class="meta-value">
          <i class="bi bi-calendar3" style="opacity:.5;font-size:.85rem;"></i>
          <?php
            $dt = $entry['entry_date'] ?? $paid_at ?? $receipt['created_at'] ?? null;
            echo $dt ? date('F d, Y h:i A', strtotime($dt)) : '—';
          ?>
        </div>
      </div>
      <div class="meta-field">
        <div class="meta-label">Reference</div>
        <div class="meta-value" style="font-family:monospace;font-size:.85rem;">
          <?= htmlspecialchars($entry['reference'] ?? $payment['payment_id'] ?? ('#' . ($receipt_id ?? '—'))) ?>
        </div>
      </div>
      <div class="meta-field">
        <div class="meta-label">Status</div>
        <div class="meta-value">
          <?php $st = $entry['status'] ?? 'Posted'; ?>
          <span class="<?= strtolower($st) === 'posted' ? 'badge-posted' : 'badge-draft' ?>">
            <?= htmlspecialchars($st) ?>
          </span>
        </div>
      </div>
      <div class="meta-field">
        <div class="meta-label">Module</div>
        <div class="meta-value muted">
          <?= htmlspecialchars(ucfirst($entry['module'] ?? 'Billing')) ?>
        </div>
      </div>
      <div class="meta-field">
        <div class="meta-label">Created By</div>
        <div class="meta-value muted">
          <i class="bi bi-person" style="opacity:.5;font-size:.85rem;"></i>
          <?= htmlspecialchars($entry['created_by'] ?? 'System') ?>
        </div>
      </div>
      <div class="meta-field">
        <div class="meta-label">Balance</div>
        <div class="meta-value">
          <span class="<?= $is_balanced ? 'badge-balanced' : 'badge-unbal' ?>">
            <i class="bi bi-<?= $is_balanced ? 'check-circle' : 'exclamation-triangle' ?>"></i>
            <?= $is_balanced ? 'Balanced' : 'Unbalanced' ?>
          </span>
        </div>
      </div>
    </div>
  </div>

  <!-- Entry Lines (Desktop Table) -->
  <div class="entry-card">
    <div class="entry-card-header">
      <i class="bi bi-table"></i> Journal Entry Lines
      <span style="margin-left:auto;font-weight:400;opacity:.7;">
        <?= count($lines) ?> line<?= count($lines) !== 1 ? 's' : '' ?>
      </span>
    </div>

    <!-- Desktop -->
    <div style="overflow-x:auto;">
      <table class="entry-table">
        <thead>
          <tr>
            <th style="width:30px;">#</th>
            <th>Account</th>
            <th class="th-num">Debit (₱)</th>
            <th class="th-num">Credit (₱)</th>
            <th>Description</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($lines as $idx => $line):
            $hasDebit  = floatval($line['debit']  ?? 0) > 0;
            $hasCredit = floatval($line['credit'] ?? 0) > 0;
            $iconClass = $hasDebit ? 'debit-icon' : ($hasCredit ? 'credit-icon' : 'neutral-icon');
            $initial   = strtoupper(substr($line['account_name'] ?? 'A', 0, 1));
          ?>
          <tr>
            <td style="color:var(--ink-light);font-size:.8rem;"><?= $idx + 1 ?></td>
            <td>
              <div class="acct-cell">
                <div class="acct-icon <?= $iconClass ?>"><?= $initial ?></div>
                <span class="acct-name"><?= htmlspecialchars($line['account_name'] ?? '') ?></span>
              </div>
            </td>
            <td class="td-num">
              <?php if ($hasDebit): ?>
                <span class="debit-amt"><?= number_format(floatval($line['debit']), 2) ?></span>
              <?php else: ?>
                <span class="empty-amt">—</span>
              <?php endif; ?>
            </td>
            <td class="td-num">
              <?php if ($hasCredit): ?>
                <span class="credit-amt"><?= number_format(floatval($line['credit']), 2) ?></span>
              <?php else: ?>
                <span class="empty-amt">—</span>
              <?php endif; ?>
            </td>
            <td>
              <span class="desc-text"><?= nl2br(htmlspecialchars($line['description'] ?? '—')) ?></span>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
        <tfoot>
          <tr>
            <td colspan="2" class="total-label">TOTALS</td>
            <td class="td-num debit-amt">₱<?= number_format($total_debit, 2) ?></td>
            <td class="td-num credit-amt">₱<?= number_format($total_credit, 2) ?></td>
            <td>
              <?php if ($is_balanced): ?>
                <span class="badge-balanced"><i class="bi bi-check-circle"></i> Balanced</span>
              <?php else: ?>
                <span class="badge-unbal"><i class="bi bi-exclamation-triangle"></i> Difference: ₱<?= number_format(abs($total_debit - $total_credit), 2) ?></span>
              <?php endif; ?>
            </td>
          </tr>
        </tfoot>
      </table>
    </div>

    <!-- Balance bar -->
    <div class="balance-bar">
      <?php if ($is_balanced): ?>
        <i class="bi bi-check-circle-fill balance-icon" style="color:#059669;"></i>
        <span class="balance-text" style="color:#059669;">This entry is balanced — debits equal credits.</span>
      <?php else: ?>
        <i class="bi bi-exclamation-triangle-fill balance-icon" style="color:#dc2626;"></i>
        <span class="balance-text" style="color:#dc2626;">
          This entry is unbalanced — difference of ₱<?= number_format(abs($total_debit - $total_credit), 2) ?>.
        </span>
      <?php endif; ?>
    </div>
  </div>

  <!-- Mobile Line Cards -->
  <div class="mobile-lines">
    <?php foreach ($lines as $idx => $line):
      $hasDebit  = floatval($line['debit']  ?? 0) > 0;
      $hasCredit = floatval($line['credit'] ?? 0) > 0;
      $iconClass = $hasDebit ? 'debit-icon' : ($hasCredit ? 'credit-icon' : 'neutral-icon');
    ?>
    <div class="m-line-card">
      <div class="m-line-head">
        <div class="acct-cell">
          <div class="acct-icon <?= $iconClass ?>"><?= strtoupper(substr($line['account_name'] ?? 'A', 0, 1)) ?></div>
          <span class="acct-name"><?= htmlspecialchars($line['account_name'] ?? '') ?></span>
        </div>
        <span style="font-size:.7rem;color:var(--ink-light);">Line <?= $idx + 1 ?></span>
      </div>
      <?php if ($hasDebit): ?>
      <div class="m-line-row">
        <span class="m-lbl">Debit</span>
        <span class="m-val debit-amt">₱<?= number_format(floatval($line['debit']), 2) ?></span>
      </div>
      <?php endif; ?>
      <?php if ($hasCredit): ?>
      <div class="m-line-row">
        <span class="m-lbl">Credit</span>
        <span class="m-val credit-amt">₱<?= number_format(floatval($line['credit']), 2) ?></span>
      </div>
      <?php endif; ?>
      <?php if (!empty($line['description'])): ?>
      <div class="m-line-row" style="align-items:flex-start;">
        <span class="m-lbl">Note</span>
        <span class="m-val desc-text" style="font-size:.78rem;text-align:right;">
          <?= nl2br(htmlspecialchars($line['description'])) ?>
        </span>
      </div>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>

    <!-- Mobile totals -->
    <div class="totals-summary">
      <div class="totals-item">
        <div class="totals-item-label">Total Debit</div>
        <div class="totals-item-val debit-color">₱<?= number_format($total_debit, 2) ?></div>
      </div>
      <div class="totals-divider"></div>
      <div class="totals-item">
        <div class="totals-item-label">Total Credit</div>
        <div class="totals-item-val credit-color">₱<?= number_format($total_credit, 2) ?></div>
      </div>
      <div class="totals-divider"></div>
      <div class="totals-item">
        <div class="totals-item-label">Status</div>
        <div class="totals-item-val" style="font-size:.9rem;margin-top:4px;">
          <span class="<?= $is_balanced ? 'badge-balanced' : 'badge-unbal' ?>">
            <?= $is_balanced ? 'Balanced' : 'Unbalanced' ?>
          </span>
        </div>
      </div>
    </div>
  </div>

  <!-- Action Buttons (bottom) -->
  <div class="head-actions no-print" style="margin-left:0;margin-top:4px;display:flex;gap:8px;flex-wrap:wrap;">
    <a href="journal_entry.php" class="btn-back">
      <i class="bi bi-arrow-left"></i> Back to Journal
    </a>
    <button onclick="window.print()" class="btn-print">
      <i class="bi bi-printer"></i> Print Entry
    </button>
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
</script>
</body>
</html>