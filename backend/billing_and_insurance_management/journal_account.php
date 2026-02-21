<?php
session_start();
include '../../SQL/config.php';

/* ── Account Classification ── */
function classifyAccount($name, $isExpense = false) {
    $n = strtolower($name);
    if ($isExpense) return "Expense";
    if (strpos($n, 'cash') !== false || strpos($n, 'receivable') !== false || strpos($n, 'bank') !== false) return "Asset";
    if (strpos($n, 'payable') !== false || strpos($n, 'loan') !== false) return "Liability";
    if (strpos($n, 'revenue') !== false || strpos($n, 'income') !== false || strpos($n, 'service') !== false) return "Revenue";
    if (strpos($n, 'expense') !== false || strpos($n, 'food') !== false || strpos($n, 'supplies') !== false) return "Expense";
    return "Revenue";
}

/*
 * Double-Entry Accounting Rules:
 *   Asset    → increases with DEBIT,  decreases with CREDIT
 *   Liability→ increases with CREDIT, decreases with DEBIT
 *   Revenue  → increases with CREDIT, decreases with DEBIT
 *   Expense  → increases with DEBIT,  decreases with CREDIT
 *   Equity   → increases with CREDIT, decreases with DEBIT
 */
function getDebitCredit($type, $amount) {
    $abs = abs(floatval($amount));
    switch ($type) {
        case 'Asset':
        case 'Expense':
            return ['debit' => $abs, 'credit' => 0.00];
        case 'Liability':
        case 'Revenue':
        case 'Equity':
        default:
            return ['debit' => 0.00, 'credit' => $abs];
    }
}

/* ── Collect Records ── */
$totals  = ["Asset" => 0, "Liability" => 0, "Revenue" => 0, "Expense" => 0];
$records = [];
$entryCounter = 1;

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
        $dc   = getDebitCredit($type, $row['total_price']);
        $totals[$type] += $row['total_price'];
        $records[] = [
            'entry_no' => 'JE-' . str_pad($entryCounter++, 5, '0', STR_PAD_LEFT),
            'id'       => $row['item_id'],
            'ref'      => 'BIL-' . str_pad($row['billing_id'], 6, '0', STR_PAD_LEFT),
            'name'     => $row['serviceName'],
            'amount'   => $row['total_price'],
            'debit'    => $dc['debit'],
            'credit'   => $dc['credit'],
            'date'     => date('Y-m-d'),
            'type'     => $type,
            'source'   => 'Billing',
            'desc'     => 'Finalized billing item — ' . $row['serviceName'],
        ];
    }
}

// 2. Payments received
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
    while ($row = $payments->fetch_assoc()) {
        $amount = floatval($row['paid_amount']) > 0
                    ? floatval($row['paid_amount'])
                    : (floatval($row['grand_total']) > 0
                        ? floatval($row['grand_total'])
                        : floatval($row['total_amount']));
        if ($amount <= 0) continue;
        $date = (!empty($row['payment_date']) && $row['payment_date'] !== '0000-00-00 00:00:00')
                    ? $row['payment_date']
                    : ($row['billing_date'] ?? date('Y-m-d'));
        $type = "Revenue";
        $dc   = getDebitCredit($type, $amount);
        $totals[$type] += $amount;
        $records[] = [
            'entry_no' => 'JE-' . str_pad($entryCounter++, 5, '0', STR_PAD_LEFT),
            'id'       => $row['billing_id'],
            'ref'      => 'PAY-' . str_pad($row['billing_id'], 6, '0', STR_PAD_LEFT),
            'name'     => $row['payment_label'],
            'amount'   => $amount,
            'debit'    => $dc['debit'],
            'credit'   => $dc['credit'],
            'date'     => $date,
            'type'     => $type,
            'source'   => 'Payment',
            'desc'     => 'Payment received — ' . ($row['payment_method'] ?? 'Cash'),
        ];
    }
}

// 3. Expenses
$expenses = $conn->query("SELECT * FROM expense_logs ORDER BY expense_date DESC");
if ($expenses) {
    while ($row = $expenses->fetch_assoc()) {
        $type = classifyAccount($row['expense_name'], true);
        $dc   = getDebitCredit($type, $row['amount']);
        $totals[$type] += $row['amount'];
        $records[] = [
            'entry_no' => 'JE-' . str_pad($entryCounter++, 5, '0', STR_PAD_LEFT),
            'id'       => $row['expense_id'],
            'ref'      => 'EXP-' . str_pad($row['expense_id'], 6, '0', STR_PAD_LEFT),
            'name'     => $row['expense_name'],
            'amount'   => $row['amount'],
            'debit'    => $dc['debit'],
            'credit'   => $dc['credit'],
            'date'     => $row['expense_date'],
            'type'     => $type,
            'source'   => 'Expense',
            'desc'     => 'Operating expense recorded',
        ];
    }
}

// 4. Journal entry lines
$lines = $conn->query("
    SELECT jel.line_id, jel.entry_id, jel.account_name, jel.debit, jel.credit, je.entry_date
    FROM journal_entry_lines jel
    JOIN journal_entries je ON jel.entry_id = je.entry_id
    ORDER BY jel.line_id DESC
");
if ($lines) {
    while ($row = $lines->fetch_assoc()) {
        $type   = classifyAccount($row['account_name']);
        $debit  = floatval($row['debit']);
        $credit = floatval($row['credit']);
        $amount = $debit - $credit;
        $totals[$type] += abs($amount);
        $records[] = [
            'entry_no' => 'JE-' . str_pad($entryCounter++, 5, '0', STR_PAD_LEFT),
            'id'       => $row['line_id'],
            'ref'      => 'JNL-' . str_pad($row['entry_id'], 6, '0', STR_PAD_LEFT),
            'name'     => $row['account_name'],
            'amount'   => $amount,
            'debit'    => $debit,
            'credit'   => $credit,
            'date'     => $row['entry_date'],
            'type'     => $type,
            'source'   => 'Journal',
            'desc'     => 'Manual journal entry',
        ];
    }
}

// Sort newest first
usort($records, fn($a, $b) => strtotime($b['date']) - strtotime($a['date']));

// Running balance & grand totals
$grandDebit  = array_sum(array_column($records, 'debit'));
$grandCredit = array_sum(array_column($records, 'credit'));
$isBalanced  = abs($grandDebit - $grandCredit) < 0.01;

/* ── Pagination ── */
$limit        = 20;
$totalRecords = count($records);
$totalPages   = max(1, ceil($totalRecords / $limit));
$page         = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$page         = max(1, min($page, $totalPages));
$startIdx     = ($page - 1) * $limit;
$pageRecords  = array_slice($records, $startIdx, $limit);

/* ── Net Position ── */
$net = $totals['Asset'] + $totals['Revenue'] - $totals['Liability'] - $totals['Expense'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<title>General Journal — HMS Accounting</title>

<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700&family=IBM+Plex+Mono:wght@400;500;600&family=IBM+Plex+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<link rel="stylesheet" href="assets/css/billing_sidebar.css">

<style>
/* ══════════════════════════════════════════════
   DESIGN TOKENS — Ledger / Accounting Aesthetic
   Clean, authoritative, document-grade UI
═══════════════════════════════════════════════ */
:root {
  --sidebar-w:     250px;
  --sidebar-w-sm:  200px;

  /* Palette */
  --ink:           #0f172a;
  --ink-2:         #1e293b;
  --ink-3:         #475569;
  --ink-4:         #94a3b8;
  --rule:          #e2e8f0;
  --rule-heavy:    #cbd5e1;
  --surface:       #f7f8fa;
  --paper:         #ffffff;
  --paper-warm:    #fdfcfb;

  /* Account colors */
  --asset:         #0369a1;
  --asset-bg:      #e0f2fe;
  --liability:     #9f1239;
  --liability-bg:  #ffe4e6;
  --revenue:       #166534;
  --revenue-bg:    #dcfce7;
  --expense:       #92400e;
  --expense-bg:    #fef3c7;

  /* Debit / Credit */
  --debit-col:     #1d4ed8;
  --credit-col:    #059669;

  /* Accent */
  --brand:         #1e3a5f;
  --brand-light:   #2563eb;

  --radius:        6px;
  --radius-lg:     10px;
  --shadow-sm:     0 1px 3px rgba(15,23,42,.06), 0 1px 2px rgba(15,23,42,.04);
  --shadow:        0 4px 16px rgba(15,23,42,.08), 0 1px 4px rgba(15,23,42,.05);
  --shadow-lg:     0 12px 40px rgba(15,23,42,.12);

  --ff-head:   'Playfair Display', Georgia, serif;
  --ff-mono:   'IBM Plex Mono', 'Courier New', monospace;
  --ff-body:   'IBM Plex Sans', system-ui, sans-serif;
  --tr:        .2s ease;
}

*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

body {
  font-family: var(--ff-body);
  background: var(--surface);
  color: var(--ink);
  font-size: 14px;
  line-height: 1.5;
}

/* ══ Layout ══ */
.cw {
  margin-left: var(--sidebar-w);
  padding: 28px 32px 64px;
  transition: margin-left var(--tr);
  min-height: 100vh;
}
.cw.sidebar-collapsed { margin-left: 0; }

/* ══ Page Header ══ */
.ledger-masthead {
  background: var(--paper);
  border: 1px solid var(--rule);
  border-radius: var(--radius-lg);
  box-shadow: var(--shadow);
  padding: 28px 32px 24px;
  margin-bottom: 20px;
  position: relative;
  overflow: hidden;
}
.ledger-masthead::before {
  content: '';
  position: absolute; top: 0; left: 0; right: 0; height: 4px;
  background: linear-gradient(90deg, var(--brand) 0%, var(--brand-light) 100%);
}
.masthead-top {
  display: flex; align-items: flex-start; justify-content: space-between;
  gap: 20px; flex-wrap: wrap;
}
.masthead-title-group { display: flex; align-items: center; gap: 16px; }
.masthead-icon {
  width: 56px; height: 56px;
  background: var(--brand);
  border-radius: var(--radius-lg);
  display: flex; align-items: center; justify-content: center;
  color: #fff; font-size: 1.5rem;
  box-shadow: 0 4px 12px rgba(30,58,95,.3);
  flex-shrink: 0;
}
.masthead-title {
  font-family: var(--ff-head);
  font-size: 1.75rem; font-weight: 700;
  color: var(--brand); letter-spacing: -.02em; line-height: 1;
}
.masthead-sub {
  font-size: .78rem; color: var(--ink-3); margin-top: 5px;
  text-transform: uppercase; letter-spacing: .08em; font-weight: 500;
}
.masthead-meta {
  text-align: right; font-size: .78rem; color: var(--ink-3); line-height: 1.7;
}
.masthead-meta strong { color: var(--ink); font-weight: 600; }

/* Divider rule */
.ledger-rule {
  border: none;
  border-top: 2px solid var(--brand);
  margin: 16px 0 0;
  opacity: .15;
}

/* ══ Summary Cards ══ */
.summary-strip {
  display: grid;
  grid-template-columns: repeat(5, 1fr);
  gap: 12px;
  margin-bottom: 20px;
}
@media (max-width: 1100px) { .summary-strip { grid-template-columns: repeat(3, 1fr); } }
@media (max-width: 700px)  { .summary-strip { grid-template-columns: 1fr 1fr; } }

.sumcard {
  background: var(--paper);
  border: 1px solid var(--rule);
  border-radius: var(--radius-lg);
  box-shadow: var(--shadow-sm);
  padding: 16px 18px;
  position: relative; overflow: hidden;
  transition: box-shadow var(--tr), transform var(--tr);
}
.sumcard:hover { box-shadow: var(--shadow); transform: translateY(-1px); }
.sumcard::after {
  content: '';
  position: absolute; top: 0; left: 0; bottom: 0; width: 4px;
  border-radius: var(--radius-lg) 0 0 var(--radius-lg);
}
.sumcard.asset::after    { background: var(--asset); }
.sumcard.liability::after { background: var(--liability); }
.sumcard.revenue::after  { background: var(--revenue); }
.sumcard.expense::after  { background: var(--expense); }
.sumcard.net::after      { background: linear-gradient(180deg, var(--brand), var(--brand-light)); }

.sumcard-label {
  font-size: .68rem; font-weight: 700;
  text-transform: uppercase; letter-spacing: .08em;
  color: var(--ink-3); margin-bottom: 6px;
  display: flex; align-items: center; gap: 5px;
}
.sumcard-label i { font-size: .8rem; }
.sumcard.asset    .sumcard-label i { color: var(--asset); }
.sumcard.liability .sumcard-label i { color: var(--liability); }
.sumcard.revenue  .sumcard-label i { color: var(--revenue); }
.sumcard.expense  .sumcard-label i { color: var(--expense); }
.sumcard.net      .sumcard-label i { color: var(--brand-light); }

.sumcard-amount {
  font-family: var(--ff-mono);
  font-size: 1.25rem; font-weight: 600;
  letter-spacing: -.01em; line-height: 1;
}
.sumcard.asset    .sumcard-amount { color: var(--asset); }
.sumcard.liability .sumcard-amount { color: var(--liability); }
.sumcard.revenue  .sumcard-amount { color: var(--revenue); }
.sumcard.expense  .sumcard-amount { color: var(--expense); }
.sumcard.net      .sumcard-amount { color: var(--brand); }

/* ══ Trial Balance Banner ══ */
.trial-balance {
  background: var(--paper);
  border: 1px solid var(--rule);
  border-radius: var(--radius-lg);
  box-shadow: var(--shadow-sm);
  padding: 14px 20px;
  margin-bottom: 20px;
  display: flex; align-items: center; gap: 16px; flex-wrap: wrap;
}
.tb-label {
  font-size: .72rem; font-weight: 700; text-transform: uppercase;
  letter-spacing: .08em; color: var(--ink-3);
}
.tb-totals {
  display: flex; gap: 24px; flex: 1; flex-wrap: wrap;
}
.tb-item {
  display: flex; align-items: baseline; gap: 8px;
}
.tb-item-label {
  font-size: .72rem; font-weight: 700; text-transform: uppercase;
  letter-spacing: .06em; color: var(--ink-3);
}
.tb-item-amount {
  font-family: var(--ff-mono);
  font-size: 1rem; font-weight: 600;
}
.tb-item.debit  .tb-item-amount { color: var(--debit-col); }
.tb-item.credit .tb-item-amount { color: var(--credit-col); }

.balance-badge {
  display: inline-flex; align-items: center; gap: 6px;
  padding: 5px 12px; border-radius: 999px;
  font-size: .72rem; font-weight: 700;
  text-transform: uppercase; letter-spacing: .05em;
  margin-left: auto;
}
.balance-badge.balanced   { background: #dcfce7; color: #166534; }
.balance-badge.unbalanced { background: #fee2e2; color: #991b1b; }

/* ══ Toolbar ══ */
.ledger-toolbar {
  background: var(--paper);
  border: 1px solid var(--rule);
  border-radius: var(--radius-lg);
  box-shadow: var(--shadow-sm);
  padding: 12px 16px;
  margin-bottom: 16px;
  display: flex; gap: 10px; align-items: center; flex-wrap: wrap;
}
.search-wrap {
  position: relative; flex: 1 1 220px; max-width: 300px;
}
.search-wrap i.search-icon {
  position: absolute; left: 11px; top: 50%;
  transform: translateY(-50%); color: var(--ink-4);
  font-size: .85rem; pointer-events: none;
}
.search-input {
  width: 100%; padding: 8px 12px 8px 32px;
  border: 1.5px solid var(--rule-heavy);
  border-radius: var(--radius);
  font-family: var(--ff-body); font-size: .83rem;
  color: var(--ink); background: var(--surface);
  outline: none; transition: border-color var(--tr), box-shadow var(--tr);
}
.search-input:focus {
  border-color: var(--brand-light);
  box-shadow: 0 0 0 3px rgba(37,99,235,.1);
  background: var(--paper);
}

.filter-group { display: flex; gap: 4px; flex-wrap: wrap; }
.fchip {
  padding: 6px 13px; border-radius: var(--radius);
  font-family: var(--ff-body); font-size: .73rem; font-weight: 600;
  border: 1.5px solid var(--rule-heavy);
  background: var(--paper); color: var(--ink-3);
  cursor: pointer; transition: all .15s; white-space: nowrap;
  display: inline-flex; align-items: center; gap: 4px;
}
.fchip:hover { background: var(--surface); color: var(--ink); }
.fchip.active          { background: var(--brand); color: #fff; border-color: var(--brand); }
.fchip.active.asset    { background: var(--asset);    border-color: var(--asset); }
.fchip.active.liability { background: var(--liability); border-color: var(--liability); }
.fchip.active.revenue  { background: var(--revenue);  border-color: var(--revenue); }
.fchip.active.expense  { background: var(--expense);  border-color: var(--expense); }

/* ══ Journal Table ══ */
.journal-wrap {
  background: var(--paper-warm);
  border: 1px solid var(--rule);
  border-radius: var(--radius-lg);
  box-shadow: var(--shadow);
  overflow: hidden;
  margin-bottom: 16px;
}

/* Table header bar */
.journal-header {
  background: var(--brand);
  padding: 12px 20px;
  display: flex; align-items: center; gap: 10px;
  color: rgba(255,255,255,.9);
  font-size: .72rem; font-weight: 700;
  text-transform: uppercase; letter-spacing: .08em;
}
.journal-header .jh-right {
  margin-left: auto; opacity: .7; font-weight: 400;
}

.jnl-table {
  width: 100%; border-collapse: collapse;
  font-family: var(--ff-body); font-size: .84rem;
}

/* Head */
.jnl-table thead tr.col-heads th {
  background: #f1f5f9;
  color: var(--ink-3); font-size: .67rem; font-weight: 700;
  text-transform: uppercase; letter-spacing: .07em;
  padding: 9px 14px;
  border-bottom: 2px solid var(--rule-heavy);
  white-space: nowrap; text-align: left;
}
.jnl-table thead tr.col-heads th.num { text-align: right; }
.jnl-table thead tr.col-heads th.center { text-align: center; }

/* Debit/Credit column headers */
.th-debit  { color: var(--debit-col)  !important; }
.th-credit { color: var(--credit-col) !important; }

/* Body rows */
.jnl-table tbody tr.jnl-row {
  border-bottom: 1px solid var(--rule);
  transition: background var(--tr);
  cursor: default;
}
.jnl-table tbody tr.jnl-row:last-child { border-bottom: none; }
.jnl-table tbody tr.jnl-row:hover { background: #f0f7ff; }

.jnl-table tbody td { padding: 10px 14px; vertical-align: middle; }

/* ── Col: Entry No ── */
td.td-entry {
  font-family: var(--ff-mono); font-size: .74rem;
  color: var(--ink-3); white-space: nowrap; width: 100px;
}

/* ── Col: Date ── */
td.td-date {
  font-family: var(--ff-mono); font-size: .78rem;
  color: var(--ink-2); white-space: nowrap; width: 110px;
}

/* ── Col: Ref ── */
td.td-ref {
  font-family: var(--ff-mono); font-size: .72rem;
  color: var(--ink-3); white-space: nowrap; width: 110px;
}

/* ── Col: Particulars (Account Name) ── */
td.td-particulars { min-width: 220px; }
.particulars-main {
  font-weight: 600; color: var(--ink); font-size: .86rem;
}
.particulars-desc {
  font-size: .72rem; color: var(--ink-4); margin-top: 2px;
}

/* ── Col: Type ── */
td.td-type { width: 100px; }
.acct-badge {
  display: inline-flex; align-items: center; gap: 4px;
  padding: 3px 9px; border-radius: 4px;
  font-size: .68rem; font-weight: 700;
  text-transform: uppercase; letter-spacing: .04em;
}
.acct-asset     { background: var(--asset-bg);     color: var(--asset); }
.acct-liability { background: var(--liability-bg); color: var(--liability); }
.acct-revenue   { background: var(--revenue-bg);   color: var(--revenue); }
.acct-expense   { background: var(--expense-bg);   color: var(--expense); }

/* ── Col: Source ── */
td.td-source { width: 90px; }
.src-pill {
  display: inline-block; padding: 2px 8px;
  border-radius: 999px; font-size: .66rem; font-weight: 700;
  text-transform: uppercase; letter-spacing: .04em;
  border: 1px solid currentColor;
}
.src-billing { color: #6d28d9; }
.src-expense { color: #b45309; }
.src-journal { color: #0369a1; }
.src-payment { color: #059669; }

/* ── Col: Debit / Credit ── */
td.td-debit, td.td-credit {
  font-family: var(--ff-mono);
  text-align: right; white-space: nowrap;
  width: 130px; font-size: .85rem;
}
td.td-debit  .money { color: var(--debit-col); font-weight: 600; }
td.td-credit .money { color: var(--credit-col); font-weight: 600; }
td.td-debit  .dash, td.td-credit .dash { color: var(--ink-4); font-size: .78rem; }

/* ── Totals Row ── */
.jnl-table tfoot tr.totals-row td {
  background: #f8fafc;
  border-top: 2px solid var(--brand);
  padding: 11px 14px;
  font-family: var(--ff-mono);
  font-size: .84rem; font-weight: 700;
}
.totals-row .totals-label {
  font-family: var(--ff-body);
  font-size: .72rem; text-transform: uppercase;
  letter-spacing: .07em; font-weight: 700;
  color: var(--ink-3);
}
.totals-row .total-debit  { color: var(--debit-col);  text-align: right; }
.totals-row .total-credit { color: var(--credit-col); text-align: right; }

/* Empty state */
.empty-row td {
  text-align: center; padding: 60px 16px;
  color: var(--ink-4);
}
.empty-row .empty-icon {
  font-size: 2.5rem; display: block; margin-bottom: 10px;
  opacity: .25;
}

/* ══ Pagination ══ */
.pagination-bar {
  background: #f8fafc;
  border-top: 1px solid var(--rule);
  padding: 12px 20px;
  display: flex; justify-content: space-between;
  align-items: center; flex-wrap: wrap; gap: 10px;
}
.pag-info { font-size: .78rem; color: var(--ink-3); }
.pag-info strong { color: var(--ink); }
.pag-btns { display: flex; gap: 3px; flex-wrap: wrap; }
.pag-btn {
  width: 32px; height: 32px;
  display: flex; align-items: center; justify-content: center;
  border: 1.5px solid var(--rule-heavy); border-radius: var(--radius);
  background: var(--paper); color: var(--ink-3);
  font-family: var(--ff-mono); font-size: .78rem; font-weight: 600;
  text-decoration: none; cursor: pointer; transition: all .15s;
}
.pag-btn:hover { border-color: var(--brand-light); color: var(--brand-light); background: #eff6ff; }
.pag-btn.active { background: var(--brand); color: #fff; border-color: var(--brand); }
.pag-btn.disabled { opacity: .35; pointer-events: none; }
.pag-btn.wide { width: auto; padding: 0 10px; font-family: var(--ff-body); }

/* ══ Mobile Cards ══ */
.mobile-cards { display: none; }
.m-card {
  background: var(--paper);
  border: 1px solid var(--rule);
  border-radius: var(--radius-lg);
  box-shadow: var(--shadow-sm);
  padding: 14px;
  margin-bottom: 10px;
  position: relative;
  border-left: 4px solid transparent;
}
.m-card.asset    { border-left-color: var(--asset); }
.m-card.liability { border-left-color: var(--liability); }
.m-card.revenue  { border-left-color: var(--revenue); }
.m-card.expense  { border-left-color: var(--expense); }

.m-card-top {
  display: flex; justify-content: space-between;
  align-items: flex-start; gap: 8px; margin-bottom: 10px;
}
.m-card-name { font-weight: 700; font-size: .88rem; color: var(--ink); }
.m-card-meta { margin-top: 2px; display: flex; gap: 5px; flex-wrap: wrap; }
.m-card-grid {
  display: grid; grid-template-columns: 1fr 1fr;
  gap: 6px 12px; font-size: .78rem;
}
.m-field-label { font-size: .65rem; font-weight: 700; text-transform: uppercase;
                 letter-spacing: .06em; color: var(--ink-3); margin-bottom: 1px; }
.m-field-val { font-family: var(--ff-mono); color: var(--ink); }
.m-field-val.debit  { color: var(--debit-col); font-weight: 600; }
.m-field-val.credit { color: var(--credit-col); font-weight: 600; }

/* ══ Responsive ══ */
@media (max-width: 900px) {
  .cw { margin-left: var(--sidebar-w-sm); padding: 60px 14px 50px; }
  .cw.sidebar-collapsed { margin-left: 0; }
  .journal-wrap { display: none; }
  .mobile-cards { display: block; }
  .ledger-masthead { padding: 20px 18px 16px; }
}
@media (max-width: 600px) {
  .cw { margin-left: 0 !important; padding: 56px 10px 40px; }
  .summary-strip { grid-template-columns: 1fr 1fr; }
  .masthead-meta { display: none; }
  .trial-balance { flex-direction: column; align-items: flex-start; }
  .balance-badge { margin-left: 0; }
}
@supports (padding: env(safe-area-inset-bottom)) {
  .cw { padding-bottom: calc(64px + env(safe-area-inset-bottom)); }
}

/* ══ Print styles ══ */
@media print {
  .cw { margin-left: 0 !important; padding: 0; }
  .ledger-toolbar, .pagination-bar, .pag-btns { display: none !important; }
  .journal-wrap { box-shadow: none; border: 1px solid #ccc; }
  .mobile-cards { display: none !important; }
  body { font-size: 11px; }
}
</style>
</head>
<body>

<?php include 'billing_sidebar.php'; ?>

<div class="cw" id="mainCw">

  <!-- ══ Masthead ══ -->
  <div class="ledger-masthead">
    <div class="masthead-top">
      <div class="masthead-title-group">
        <div class="masthead-icon"><i class="bi bi-journal-text"></i></div>
        <div>
          <div class="masthead-title">General Journal</div>
          <div class="masthead-sub">
            HMS Accounting &nbsp;·&nbsp; All Accounts Ledger &nbsp;·&nbsp;
            Double-Entry Bookkeeping
          </div>
        </div>
      </div>
      <div class="masthead-meta">
        <div>Prepared by: <strong>System</strong></div>
        <div>Date: <strong><?= date('F d, Y') ?></strong></div>
        <div>Total Entries: <strong><?= $totalRecords ?></strong></div>
      </div>
    </div>
    <hr class="ledger-rule">
  </div>

  <!-- ══ Summary Cards ══ -->
  <div class="summary-strip">
    <div class="sumcard asset">
      <div class="sumcard-label"><i class="bi bi-building"></i> Total Assets</div>
      <div class="sumcard-amount">₱<?= number_format($totals['Asset'], 2) ?></div>
    </div>
    <div class="sumcard liability">
      <div class="sumcard-label"><i class="bi bi-credit-card"></i> Total Liabilities</div>
      <div class="sumcard-amount">₱<?= number_format($totals['Liability'], 2) ?></div>
    </div>
    <div class="sumcard revenue">
      <div class="sumcard-label"><i class="bi bi-graph-up-arrow"></i> Total Revenue</div>
      <div class="sumcard-amount">₱<?= number_format($totals['Revenue'], 2) ?></div>
    </div>
    <div class="sumcard expense">
      <div class="sumcard-label"><i class="bi bi-wallet2"></i> Total Expenses</div>
      <div class="sumcard-amount">₱<?= number_format($totals['Expense'], 2) ?></div>
    </div>
    <div class="sumcard net">
      <div class="sumcard-label"><i class="bi bi-calculator-fill"></i> Net Position</div>
      <div class="sumcard-amount" style="color:<?= $net >= 0 ? 'var(--revenue)' : 'var(--liability)' ?>">
        <?= $net < 0 ? '(' : '' ?>₱<?= number_format(abs($net), 2) ?><?= $net < 0 ? ')' : '' ?>
      </div>
    </div>
  </div>

  <!-- ══ Trial Balance Banner ══ -->
  <div class="trial-balance">
    <div class="tb-label"><i class="bi bi-scales"></i> Trial Balance</div>
    <div class="tb-totals">
      <div class="tb-item debit">
        <span class="tb-item-label">Total Debits</span>
        <span class="tb-item-amount">₱<?= number_format($grandDebit, 2) ?></span>
      </div>
      <div class="tb-item credit">
        <span class="tb-item-label">Total Credits</span>
        <span class="tb-item-amount">₱<?= number_format($grandCredit, 2) ?></span>
      </div>
      <div class="tb-item">
        <span class="tb-item-label">Difference</span>
        <span class="tb-item-amount" style="color:var(--ink);">
          ₱<?= number_format(abs($grandDebit - $grandCredit), 2) ?>
        </span>
      </div>
    </div>
    <span class="balance-badge <?= $isBalanced ? 'balanced' : 'unbalanced' ?>">
      <i class="bi bi-<?= $isBalanced ? 'check-circle-fill' : 'exclamation-triangle-fill' ?>"></i>
      <?= $isBalanced ? 'Balanced' : 'Unbalanced' ?>
    </span>
  </div>

  <!-- ══ Toolbar ══ -->
  <div class="ledger-toolbar">
    <div class="search-wrap">
      <i class="bi bi-search search-icon"></i>
      <input type="text" class="search-input" id="searchInput" placeholder="Search accounts, references…">
    </div>
    <div class="filter-group" id="filterGroup">
      <button class="fchip active" data-filter="all" onclick="filterType(this,'all')">
        <i class="bi bi-list-ul"></i> All
      </button>
      <button class="fchip" data-filter="asset" onclick="filterType(this,'asset')">
        <i class="bi bi-building"></i> Asset
      </button>
      <button class="fchip" data-filter="liability" onclick="filterType(this,'liability')">
        <i class="bi bi-credit-card"></i> Liability
      </button>
      <button class="fchip" data-filter="revenue" onclick="filterType(this,'revenue')">
        <i class="bi bi-graph-up"></i> Revenue
      </button>
      <button class="fchip" data-filter="expense" onclick="filterType(this,'expense')">
        <i class="bi bi-wallet2"></i> Expense
      </button>
    </div>
    <button class="fchip" onclick="window.print()" style="margin-left:auto;">
      <i class="bi bi-printer"></i> Print
    </button>
  </div>

  <!-- ══ Desktop Journal Table ══ -->
  <div class="journal-wrap">
    <div class="journal-header">
      <i class="bi bi-journal-bookmark-fill"></i>
      Account Ledger — General Journal
      <span class="jh-right">
        Page <?= $page ?> of <?= $totalPages ?> &nbsp;·&nbsp;
        Showing <?= count($pageRecords) ?> of <?= $totalRecords ?> records
      </span>
    </div>

    <div style="overflow-x:auto;">
      <table class="jnl-table" id="jnlTable">
        <thead>
          <tr class="col-heads">
            <th>Entry No.</th>
            <th>Date</th>
            <th>Reference</th>
            <th>Particulars / Account Name</th>
            <th class="center">Type</th>
            <th class="center">Source</th>
            <th class="num th-debit">Debit (Dr)</th>
            <th class="num th-credit">Credit (Cr)</th>
          </tr>
        </thead>
        <tbody id="jnlBody">
        <?php if (empty($pageRecords)): ?>
          <tr class="empty-row">
            <td colspan="8">
              <i class="bi bi-inbox empty-icon"></i>
              No journal entries found.
            </td>
          </tr>
        <?php else: ?>
          <?php foreach ($pageRecords as $i => $rec):
            $typeKey  = strtolower($rec['type']);
            $srcKey   = strtolower($rec['source']);
            $srcClass = 'src-' . $srcKey;
            $hasDebit  = $rec['debit']  > 0;
            $hasCredit = $rec['credit'] > 0;
          ?>
          <tr class="jnl-row" data-type="<?= $typeKey ?>" data-search="<?= htmlspecialchars(strtolower($rec['name'] . ' ' . $rec['ref'] . ' ' . $rec['source'])) ?>">
            <td class="td-entry"><?= $rec['entry_no'] ?></td>
            <td class="td-date"><?= date('M d, Y', strtotime($rec['date'])) ?></td>
            <td class="td-ref"><?= $rec['ref'] ?></td>
            <td class="td-particulars">
              <div class="particulars-main"><?= htmlspecialchars($rec['name']) ?></div>
              <div class="particulars-desc"><?= htmlspecialchars($rec['desc']) ?></div>
            </td>
            <td class="td-type" style="text-align:center;">
              <span class="acct-badge acct-<?= $typeKey ?>">
                <?= $rec['type'] ?>
              </span>
            </td>
            <td class="td-source" style="text-align:center;">
              <span class="src-pill <?= $srcClass ?>">
                <?= $rec['source'] ?>
              </span>
            </td>
            <td class="td-debit">
              <?php if ($hasDebit): ?>
                <span class="money">₱<?= number_format($rec['debit'], 2) ?></span>
              <?php else: ?>
                <span class="dash">—</span>
              <?php endif; ?>
            </td>
            <td class="td-credit">
              <?php if ($hasCredit): ?>
                <span class="money">₱<?= number_format($rec['credit'], 2) ?></span>
              <?php else: ?>
                <span class="dash">—</span>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
        <tfoot>
          <tr class="totals-row">
            <td colspan="6" class="totals-label">
              <i class="bi bi-sigma"></i>
              Page Totals — <?= count($pageRecords) ?> Entries
            </td>
            <td class="total-debit">
              ₱<?= number_format(array_sum(array_column($pageRecords, 'debit')), 2) ?>
            </td>
            <td class="total-credit">
              ₱<?= number_format(array_sum(array_column($pageRecords, 'credit')), 2) ?>
            </td>
          </tr>
          <tr class="totals-row" style="background:#f0f7ff;">
            <td colspan="6" class="totals-label" style="color:var(--brand);">
              <i class="bi bi-sigma"></i>
              Grand Totals — All <?= $totalRecords ?> Entries
            </td>
            <td class="total-debit" style="font-size:.92rem; border-top:1px solid var(--debit-col);">
              ₱<?= number_format($grandDebit, 2) ?>
            </td>
            <td class="total-credit" style="font-size:.92rem; border-top:1px solid var(--credit-col);">
              ₱<?= number_format($grandCredit, 2) ?>
            </td>
          </tr>
        </tfoot>
      </table>
    </div>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
    <div class="pagination-bar">
      <span class="pag-info">
        Records <strong><?= $startIdx + 1 ?>–<?= min($startIdx + $limit, $totalRecords) ?></strong>
        of <strong><?= $totalRecords ?></strong>
      </span>
      <div class="pag-btns">
        <a href="?page=1" class="pag-btn wide <?= $page <= 1 ? 'disabled' : '' ?>">
          <i class="bi bi-chevron-double-left"></i>
        </a>
        <a href="?page=<?= $page - 1 ?>" class="pag-btn wide <?= $page <= 1 ? 'disabled' : '' ?>">
          <i class="bi bi-chevron-left"></i>
        </a>
        <?php
          $rng = 2;
          $sp  = max(1, $page - $rng);
          $ep  = min($totalPages, $page + $rng);
          if ($sp > 1) echo '<span class="pag-btn disabled">…</span>';
          for ($i = $sp; $i <= $ep; $i++):
        ?>
          <a href="?page=<?= $i ?>" class="pag-btn <?= $i === $page ? 'active' : '' ?>"><?= $i ?></a>
        <?php endfor;
          if ($ep < $totalPages) echo '<span class="pag-btn disabled">…</span>';
        ?>
        <a href="?page=<?= $page + 1 ?>" class="pag-btn wide <?= $page >= $totalPages ? 'disabled' : '' ?>">
          <i class="bi bi-chevron-right"></i>
        </a>
        <a href="?page=<?= $totalPages ?>" class="pag-btn wide <?= $page >= $totalPages ? 'disabled' : '' ?>">
          <i class="bi bi-chevron-double-right"></i>
        </a>
      </div>
    </div>
    <?php endif; ?>
  </div><!-- /journal-wrap -->

  <!-- ══ Mobile Cards ══ -->
  <div class="mobile-cards" id="mobileCards">
    <?php if (empty($pageRecords)): ?>
      <div style="text-align:center;padding:50px 20px;color:var(--ink-4);">
        <i class="bi bi-inbox" style="font-size:2rem;display:block;margin-bottom:10px;opacity:.25;"></i>
        No journal entries found.
      </div>
    <?php else: ?>
      <?php foreach ($pageRecords as $rec):
        $typeKey = strtolower($rec['type']);
        $srcKey  = strtolower($rec['source']);
      ?>
      <div class="m-card <?= $typeKey ?> mobile-row" data-type="<?= $typeKey ?>"
           data-search="<?= htmlspecialchars(strtolower($rec['name'] . ' ' . $rec['ref'] . ' ' . $rec['source'])) ?>">
        <div class="m-card-top">
          <div>
            <div class="m-card-name"><?= htmlspecialchars($rec['name']) ?></div>
            <div class="m-card-meta">
              <span class="acct-badge acct-<?= $typeKey ?>"><?= $rec['type'] ?></span>
              <span class="src-pill src-<?= $srcKey ?>"><?= $rec['source'] ?></span>
            </div>
          </div>
          <div style="font-family:var(--ff-mono);font-size:.72rem;color:var(--ink-3);text-align:right;flex-shrink:0;">
            <?= $rec['entry_no'] ?>
          </div>
        </div>
        <div class="m-card-grid">
          <div>
            <div class="m-field-label">Date</div>
            <div class="m-field-val"><?= date('M d, Y', strtotime($rec['date'])) ?></div>
          </div>
          <div>
            <div class="m-field-label">Reference</div>
            <div class="m-field-val"><?= $rec['ref'] ?></div>
          </div>
          <div>
            <div class="m-field-label">Debit (Dr)</div>
            <div class="m-field-val debit">
              <?= $rec['debit'] > 0 ? '₱' . number_format($rec['debit'], 2) : '—' ?>
            </div>
          </div>
          <div>
            <div class="m-field-label">Credit (Cr)</div>
            <div class="m-field-val credit">
              <?= $rec['credit'] > 0 ? '₱' . number_format($rec['credit'], 2) : '—' ?>
            </div>
          </div>
        </div>
      </div>
      <?php endforeach; ?>

      <?php if ($totalPages > 1): ?>
      <div style="display:flex;justify-content:space-between;align-items:center;margin-top:14px;gap:10px;">
        <span style="font-size:.78rem;color:var(--ink-3);">Page <?= $page ?> / <?= $totalPages ?></span>
        <div style="display:flex;gap:6px;">
          <?php if ($page > 1): ?>
            <a href="?page=<?= $page - 1 ?>" class="pag-btn wide"><i class="bi bi-chevron-left"></i> Prev</a>
          <?php endif; ?>
          <?php if ($page < $totalPages): ?>
            <a href="?page=<?= $page + 1 ?>" class="pag-btn wide">Next <i class="bi bi-chevron-right"></i></a>
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
    const sync = () => cw.classList.toggle('sidebar-collapsed', sidebar.classList.contains('closed'));
    new MutationObserver(sync).observe(sidebar, { attributes: true, attributeFilter: ['class'] });
    document.getElementById('sidebarToggle')?.addEventListener('click', () => requestAnimationFrame(sync));
    sync();
})();

/* ── Filter chips ── */
let activeFilter = 'all';
function filterType(btn, type) {
    document.querySelectorAll('.fchip[data-filter]').forEach(c => {
        c.classList.remove('active', 'asset', 'liability', 'revenue', 'expense');
    });
    btn.classList.add('active');
    if (type !== 'all') btn.classList.add(type);
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
        const matchQ    = !q || row.dataset.search.includes(q) ||
                          row.textContent.toLowerCase().includes(q);
        row.style.display = (matchType && matchQ) ? '' : 'none';
    });

    // Mobile cards
    document.querySelectorAll('#mobileCards .mobile-row').forEach(card => {
        const matchType = activeFilter === 'all' || card.dataset.type === activeFilter;
        const matchQ    = !q || card.dataset.search.includes(q) ||
                          card.textContent.toLowerCase().includes(q);
        card.style.display = (matchType && matchQ) ? '' : 'none';
    });
}
</script>
</body>
</html>