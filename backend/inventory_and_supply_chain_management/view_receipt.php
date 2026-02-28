<?php
session_start();
include '../../SQL/config.php';

$request_id = isset($_GET['request_id']) ? (int)$_GET['request_id'] : 0;

$stmt = $pdo->prepare("SELECT * FROM department_request WHERE id=? LIMIT 1");
$stmt->execute([$request_id]);
$request = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$request) {
    echo "<h3 style='text-align:center;margin-top:60px;color:#b91c1c;'>Request not found!</h3>";
    exit;
}

$stmtItems = $pdo->prepare("SELECT * FROM department_request_items WHERE request_id=? ORDER BY id ASC");
$stmtItems->execute([$request_id]);
$items = $stmtItems->fetchAll(PDO::FETCH_ASSOC);

// Fetch all delivery logs for this request
$stmtLogs = $pdo->prepare("SELECT * FROM department_request_receive_log WHERE request_id=? ORDER BY item_id ASC, received_at ASC");
$stmtLogs->execute([$request_id]);
$allLogs = $stmtLogs->fetchAll(PDO::FETCH_ASSOC);

// Group logs by item_id
$logsByItem = [];
foreach ($allLogs as $log) {
    $logsByItem[$log['item_id']][] = $log;
}

$grand_total        = 0.00;
$total_approved_all = 0;
$total_received_all = 0;
foreach ($items as $item) {
    $grand_total        += (float)($item['price'] ?? 0);
    $total_approved_all += (int)($item['approved_quantity'] ?? 0);
    $total_received_all += (int)($item['received_quantity'] ?? 0);
}

$delivery_date = !empty($request['delivered_at'])
    ? date('F d, Y', strtotime($request['delivered_at']))
    : (!empty($request['purchased_at'])
        ? date('F d, Y', strtotime($request['purchased_at']))
        : date('F d, Y'));

$status     = strtolower($request['status']);
$receipt_no = 'DR-' . str_pad($request['id'], 6, '0', STR_PAD_LEFT);

$ordinals = ['1st','2nd','3rd','4th','5th','6th','7th','8th','9th','10th'];

// ── Supplier name ──
// Tries: dedicated supplier_name column → supplier_id join → payment_type fallback
$supplierName = '';
if (!empty($request['supplier_name'])) {
    $supplierName = $request['supplier_name'];
} elseif (!empty($request['supplier_id'])) {
    // Adjust table/column names to match your schema
    $sStmt = $pdo->prepare("SELECT name FROM suppliers WHERE id = ? LIMIT 1");
    $sStmt->execute([$request['supplier_id']]);
    $supplierName = $sStmt->fetchColumn() ?: '';
}
// Final fallback: label as supplier portal
if ($supplierName === '') {
    $supplierName = !empty($request['payment_type']) ? 'Supplier Portal' : 'N/A';
}

// ── Logged-in user (receiver) — fetched from users table via session user_id ──
$loggedInUser = '';
if (!empty($_SESSION['user_id'])) {
    $uStmt = $pdo->prepare("SELECT fname, mname, lname FROM users WHERE user_id = ? LIMIT 1");
    $uStmt->execute([$_SESSION['user_id']]);
    $uRow = $uStmt->fetch(PDO::FETCH_ASSOC);
    if ($uRow) {
        $loggedInUser = trim(
            $uRow['fname'] . ' ' .
            (!empty($uRow['mname']) ? $uRow['mname'] . ' ' : '') .
            $uRow['lname']
        );
    }
}

// Last receiver from delivery logs (used as fallback for signature block)
$lastReceiver = '';
if (!empty($allLogs)) {
    $lastReceiver = end($allLogs)['received_by'] ?? '';
}
// Prefer logged-in user for the "Received By" signature; fall back to log data
$receivedBySignature = $loggedInUser ?: $lastReceiver;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Delivery Receipt <?= $receipt_no ?></title>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700;900&family=DM+Sans:ital,wght@0,300;0,400;0,500;0,600;1,400&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
<style>
:root {
  --ink:        #111827;
  --ink-mid:    #374151;
  --ink-light:  #6b7280;
  --accent:     #1d4ed8;
  --success:    #16a34a;
  --warning:    #d97706;
  --danger:     #dc2626;
  --border:     #e5e7eb;
  --border-dark:#d1d5db;
  --page:       #f3f4f6;
  --surface:    #ffffff;
  --surface-2:  #f9fafb;
}
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

body {
  font-family: 'DM Sans', sans-serif;
  background: var(--page);
  color: var(--ink);
  padding: 36px 20px 60px;
}
.wrap { max-width: 860px; margin: 0 auto; }

/* Toolbar */
.toolbar { display: flex; justify-content: flex-end; gap: 10px; margin-bottom: 20px; }
.btn { display: inline-flex; align-items: center; gap: 6px; padding: 9px 18px; border-radius: 8px; font-family: 'DM Sans', sans-serif; font-size: 0.86rem; font-weight: 600; cursor: pointer; border: none; text-decoration: none; transition: .15s; }
.btn-primary { background: var(--accent); color: #fff; }
.btn-primary:hover { background: #1e40af; }
.btn-secondary { background: #fff; color: var(--ink-mid); border: 1px solid var(--border); }
.btn-secondary:hover { background: var(--surface-2); }

/* Card */
.receipt { background: var(--surface); border: 1px solid var(--border); border-radius: 12px; box-shadow: 0 4px 24px rgba(0,0,0,.07); overflow: hidden; }

/* Dark header */
.receipt-top { background: var(--ink); color: #fff; padding: 30px 40px 26px; display: flex; justify-content: space-between; align-items: flex-start; gap: 20px; }
.org-name { font-family: 'Playfair Display', serif; font-size: 1.3rem; font-weight: 900; line-height: 1.2; }
.org-tagline { font-size: 0.7rem; color: rgba(255,255,255,.4); text-transform: uppercase; letter-spacing: .7px; margin-top: 5px; }
.doc-block { text-align: right; }
.doc-block .lbl { font-size: 0.67rem; color: rgba(255,255,255,.38); text-transform: uppercase; letter-spacing: .7px; }
.doc-block .doc-type { font-family: 'Playfair Display', serif; font-size: 1.2rem; font-weight: 700; margin-top: 2px; }
.doc-block .doc-num { font-size: 0.8rem; color: rgba(255,255,255,.5); margin-top: 4px; }

/* Info bar */
.info-bar { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; padding: 12px 40px; background: var(--surface-2); border-bottom: 1px solid var(--border); }
.info-bar .field { font-size: 0.82rem; color: var(--ink-mid); }
.info-bar .field strong { color: var(--ink); font-weight: 600; }
.pill { display: inline-flex; align-items: center; gap: 4px; padding: 3px 11px; border-radius: 999px; font-size: 0.74rem; font-weight: 600; }
.pill-completed { background: #d1fae5; color: #065f46; }
.pill-partial   { background: #fef3c7; color: #92400e; }
.pill-default   { background: #dbeafe; color: #1d4ed8; }

/* ── Supplier / Receiver meta strip ── */
.party-bar {
  display: flex;
  justify-content: space-between;
  align-items: stretch;
  flex-wrap: wrap;
  gap: 0;
  border-bottom: 1px solid var(--border);
}
.party-cell {
  flex: 1 1 200px;
  padding: 14px 40px;
  display: flex;
  flex-direction: column;
  gap: 3px;
}
.party-cell + .party-cell {
  border-left: 1px solid var(--border);
}
.party-label {
  font-size: 0.65rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: .7px;
  color: var(--ink-light);
}
.party-value {
  font-size: 0.92rem;
  font-weight: 700;
  color: var(--ink);
  display: flex;
  align-items: center;
  gap: 6px;
}
.party-value .party-icon {
  width: 22px;
  height: 22px;
  border-radius: 50%;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  font-size: 0.7rem;
  flex-shrink: 0;
}
.party-icon-supplier { background: #dbeafe; color: #1d4ed8; }
.party-icon-receiver { background: #d1fae5; color: #065f46; }
.party-sub {
  font-size: 0.72rem;
  color: var(--ink-light);
}

/* Body */
.receipt-body { padding: 32px 40px 40px; }

/* ─── Per-item block ─── */
.item-block {
  margin-bottom: 28px;
  border: 1px solid var(--border);
  border-radius: 10px;
  overflow: hidden;
}
.item-block:last-of-type { margin-bottom: 0; }

/* Item header row */
.item-header {
  display: grid;
  grid-template-columns: 1fr auto auto auto;
  align-items: center;
  gap: 24px;
  padding: 14px 18px;
  background: var(--surface-2);
  border-bottom: 1px solid var(--border);
}
.item-name-main {
  font-size: 0.97rem;
  font-weight: 700;
  color: var(--ink);
}
.item-meta-col {
  text-align: right;
}
.item-meta-col .meta-label {
  font-size: 0.66rem;
  text-transform: uppercase;
  letter-spacing: .55px;
  color: var(--ink-light);
  font-weight: 600;
  margin-bottom: 2px;
}
.item-meta-col .meta-value {
  font-family: 'DM Mono', monospace;
  font-size: 0.9rem;
  font-weight: 700;
  color: var(--ink);
}
.item-meta-col .meta-value.green  { color: var(--success); }
.item-meta-col .meta-value.orange { color: var(--warning); }
.item-meta-col .meta-value.red    { color: var(--danger); }

/* Status chip on item */
.item-chip {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  padding: 4px 12px;
  border-radius: 99px;
  font-size: 0.73rem;
  font-weight: 700;
}
.chip-full    { background: #d1fae5; color: #065f46; }
.chip-partial { background: #fef3c7; color: #92400e; }
.chip-none    { background: #fee2e2; color: #991b1b; }

/* Price line */
.item-price-line {
  display: flex;
  justify-content: flex-end;
  align-items: center;
  gap: 8px;
  padding: 8px 18px;
  background: var(--surface);
  border-bottom: 1px solid var(--border);
  font-size: 0.84rem;
  color: var(--ink-mid);
}
.item-price-line .price-val {
  font-family: 'DM Mono', monospace;
  font-weight: 700;
  font-size: 0.92rem;
  color: var(--accent);
}

/* Delivery log table inside item block */
.delivery-log-wrap { padding: 0; }
.delivery-log-title {
  padding: 10px 18px 8px;
  font-size: 0.67rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: .7px;
  color: var(--ink-light);
  border-bottom: 1px solid var(--border);
  background: #fafafa;
}
.delivery-log-table {
  width: 100%;
  border-collapse: collapse;
}
.delivery-log-table thead th {
  padding: 8px 18px;
  font-size: 0.68rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: .5px;
  color: var(--ink-light);
  border-bottom: 1px solid var(--border);
  background: var(--surface-2);
  text-align: left;
}
.delivery-log-table thead th.right { text-align: right; }
.delivery-log-table tbody td {
  padding: 11px 18px;
  font-size: 0.875rem;
  border-bottom: 1px solid #f3f4f6;
  vertical-align: middle;
}
.delivery-log-table tbody tr:last-child td { border-bottom: none; }
.delivery-log-table tbody tr:hover { background: #f9fbff; }

.ordinal-badge {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 42px;
  padding: 3px 10px;
  border-radius: 99px;
  font-size: 0.72rem;
  font-weight: 700;
  letter-spacing: .03em;
}
.ord-1 { background: #dbeafe; color: #1e40af; }
.ord-2 { background: #d1fae5; color: #065f46; }
.ord-3 { background: #fef3c7; color: #92400e; }
.ord-n { background: #f3f4f6; color: #374151; }

.log-datetime {
  font-size: 0.88rem;
  color: var(--ink);
  font-weight: 500;
}
.log-datetime .log-time {
  font-size: 0.78rem;
  color: var(--ink-light);
  margin-left: 6px;
}
.log-qty {
  font-family: 'DM Mono', monospace;
  font-size: 0.9rem;
  font-weight: 700;
  color: var(--success);
}
.log-receiver {
  font-weight: 600;
  color: var(--ink);
  display: flex;
  align-items: center;
  gap: 5px;
}
.log-receiver::before {
  content: '';
  width: 6px; height: 6px;
  border-radius: 50%;
  background: var(--success);
  flex-shrink: 0;
}

/* No deliveries placeholder */
.no-log-row td {
  text-align: center;
  padding: 20px;
  font-size: 0.83rem;
  color: var(--ink-light);
  font-style: italic;
}

/* ─── Totals ─── */
.totals-section {
  margin-top: 30px;
  padding-top: 24px;
  border-top: 2px solid var(--border-dark);
  display: flex;
  justify-content: flex-end;
}
.totals-tbl { width: 270px; font-size: 0.88rem; border-collapse: collapse; }
.totals-tbl td { padding: 5px 0; color: var(--ink-mid); }
.totals-tbl td:last-child { text-align: right; }
.totals-tbl .grand td { font-size: 1.02rem; font-weight: 700; color: var(--ink); border-top: 1.5px solid var(--ink); padding-top: 10px; }
.totals-tbl .grand td:last-child { color: var(--accent); }

/* Signatures */
.signatures { display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; margin-top: 40px; }
.sig-block { text-align: center; }
.sig-space { height: 46px; }
.sig-prefill { height: 46px; display: flex; align-items: flex-end; justify-content: center; padding-bottom: 6px; font-weight: 700; font-size: 0.88rem; color: var(--ink); }
.sig-line { border-top: 1px solid var(--ink); margin: 0 8px; }
.sig-lbl { font-size: 0.72rem; color: var(--ink-light); margin-top: 6px; text-transform: uppercase; letter-spacing: .5px; }

/* Footer */
.footer-note { text-align: center; font-size: 0.73rem; color: var(--ink-light); margin-top: 26px; padding-top: 18px; border-top: 1px dashed var(--border); line-height: 1.65; }

/* ══ PRINT ══ */
@media print {
  @page { size: A4; margin: 14mm 14mm; }
  body { background: #fff !important; padding: 0 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
  .toolbar { display: none !important; }
  .wrap { max-width: 100%; }
  .receipt { border: none !important; border-radius: 0 !important; box-shadow: none !important; }
  .receipt-top { background: #111827 !important; color: #fff !important; padding: 18px 30px 16px !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
  .info-bar, .party-bar { padding-left: 30px !important; padding-right: 30px !important; }
  .party-cell { padding: 12px 30px !important; }
  .receipt-body { padding: 22px 30px 30px !important; }
  .item-block { page-break-inside: avoid; }
  .signatures { page-break-inside: avoid; }
}
</style>
</head>
<body>
<div class="wrap">

  <div class="toolbar">
    <a href="order_receive.php" class="btn btn-secondary">
      <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path d="M19 12H5M12 5l-7 7 7 7"/></svg>
      Back
    </a>
    <button onclick="window.print()" class="btn btn-primary">
      <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
      Print Receipt
    </button>
  </div>

  <div class="receipt">

    <!-- Header -->
    <div class="receipt-top">
      <div>
        <div class="org-name">Hospital Inventory<br>&amp; Supply Chain</div>
        <div class="org-tagline">Procurement Management System</div>
      </div>
      <div class="doc-block">
        <div class="lbl">Document</div>
        <div class="doc-type">Delivery Receipt</div>
        <div class="doc-num"><?= $receipt_no ?></div>
      </div>
    </div>

    <!-- Info bar -->
    <div class="info-bar">
      <div class="field">
        Date: <strong><?= $delivery_date ?></strong>
      </div>
      <div class="field">
        Department: <strong><?= htmlspecialchars($request['department']) ?></strong>
      </div>
      <div class="field">
        Total Items: <strong><?= count($items) ?></strong>
      </div>
      <div class="field">
        Received: <strong><?= $total_received_all ?> / <?= $total_approved_all ?> units</strong>
      </div>
      <?php
        $pillClass = 'pill-default';
        if ($status === 'completed')  $pillClass = 'pill-completed';
        elseif ($status === 'receiving') $pillClass = 'pill-partial';
      ?>
      <span class="pill <?= $pillClass ?>">
        <?= $status === 'completed' ? '✓ Completed' : ($status === 'receiving' ? '⟳ Partial' : ucfirst(htmlspecialchars($request['status']))) ?>
      </span>
    </div>



    <!-- Body -->
    <div class="receipt-body">

      <?php foreach ($items as $idx => $item):
        $approvedQty  = (int)($item['approved_quantity'] ?? 0);
        $receivedQty  = (int)($item['received_quantity'] ?? 0);
        $remainingQty = $approvedQty - $receivedQty;
        $price        = (float)($item['price'] ?? 0);
        $unit         = htmlspecialchars(ucfirst($item['unit'] ?? 'pcs'));
        $iid          = $item['id'];
        $logs         = $logsByItem[$iid] ?? [];

        $isFullyDone = ($receivedQty >= $approvedQty && $approvedQty > 0);
        $isPartial   = ($receivedQty > 0 && $receivedQty < $approvedQty);
        $isNone      = ($receivedQty === 0);

        if ($isFullyDone)   { $chipClass = 'chip-full';    $chipText = '✓ Fully Received'; }
        elseif ($isPartial) { $chipClass = 'chip-partial'; $chipText = '⟳ Partial'; }
        else                { $chipClass = 'chip-none';    $chipText = 'Not Yet Received'; }
      ?>
      <div class="item-block">

        <!-- Item header -->
        <div class="item-header">
          <div>
            <div class="item-name-main"><?= htmlspecialchars($item['item_name']) ?></div>
          </div>

          <div class="item-meta-col">
            <div class="meta-label">Approved Qty</div>
            <div class="meta-value"><?= $approvedQty ?> <span style="font-size:0.75rem;font-weight:500;color:var(--ink-light);"><?= $unit ?></span></div>
          </div>

          <div class="item-meta-col">
            <div class="meta-label">Total Received</div>
            <div class="meta-value <?= $isFullyDone ? 'green' : ($isPartial ? 'orange' : 'red') ?>">
              <?= $receivedQty ?> <span style="font-size:0.75rem;font-weight:500;color:var(--ink-light);"><?= $unit ?></span>
            </div>
          </div>

          <?php if ($remainingQty > 0): ?>
          <div class="item-meta-col">
            <div class="meta-label">Remaining</div>
            <div class="meta-value red"><?= $remainingQty ?></div>
          </div>
          <?php endif; ?>

          <div>
            <span class="item-chip <?= $chipClass ?>"><?= $chipText ?></span>
          </div>
        </div>

        <!-- Price line -->
        <div class="item-price-line">
          <span style="font-size:0.78rem;color:var(--ink-light);">Item Price</span>
          <span class="price-val">₱ <?= number_format($price, 2) ?></span>
        </div>

        <!-- Delivery log -->
        <div class="delivery-log-wrap">
          <div class="delivery-log-title">Delivery History</div>
          <table class="delivery-log-table">
            <thead>
              <tr>
                <th style="width:90px;">Delivery</th>
                <th>Date &amp; Time</th>
                <th class="right">Received Qty</th>
                <th>Received By</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($logs)): ?>
                <tr class="no-log-row"><td colspan="4">No deliveries recorded yet for this item.</td></tr>
              <?php else: ?>
                <?php foreach ($logs as $li => $log):
                  $ord      = $ordinals[$li] ?? (($li + 1) . 'th');
                  $ordClass = ($li === 0) ? 'ord-1' : (($li === 1) ? 'ord-2' : (($li === 2) ? 'ord-3' : 'ord-n'));
                ?>
                <tr>
                  <td>
                    <span class="ordinal-badge <?= $ordClass ?>"><?= $ord ?> delivered</span>
                  </td>
                  <td>
                    <span class="log-datetime">
                      <?= date('M d, Y', strtotime($log['received_at'])) ?>
                      <span class="log-time"><?= date('h:i A', strtotime($log['received_at'])) ?></span>
                    </span>
                  </td>
                  <td style="text-align:right;">
                    <span class="log-qty">+<?= (int)$log['received_qty'] ?></span>
                    <span style="font-size:0.78rem;color:var(--ink-light);margin-left:3px;"><?= $unit ?></span>
                  </td>
                  <td>
                    <span class="log-receiver"><?= htmlspecialchars($log['received_by']) ?></span>
                  </td>
                </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>

      </div><!-- /item-block -->
      <?php endforeach; ?>

      <!-- Totals -->
      <div class="totals-section">
        <table class="totals-tbl">
          <tr><td>Subtotal</td><td>₱ <?= number_format($grand_total, 2) ?></td></tr>
          <tr><td>Tax / Other Charges</td><td>₱ 0.00</td></tr>
          <tr class="grand"><td>Grand Total</td><td>₱ <?= number_format($grand_total, 2) ?></td></tr>
        </table>
      </div>

      <!-- Signatures -->
      <div class="signatures">
        <div class="sig-block">
          <div class="sig-prefill">Hospital Supplier Org</div>
          <div class="sig-line"></div>
          <div class="sig-lbl">Prepared By</div>
        </div>
        <div class="sig-block">
          <div class="sig-prefill"><?= htmlspecialchars($receivedBySignature) ?></div>
          <div class="sig-line"></div>
          <div class="sig-lbl">Received By</div>
        </div>
        <div class="sig-block">
          <?php if (!empty($request['delivered_at'])): ?>
          <div class="sig-prefill" style="font-size:0.82rem;"><?= date('F d, Y', strtotime($request['delivered_at'])) ?></div>
          <?php else: ?>
          <div class="sig-space"></div>
          <?php endif; ?>
          <div class="sig-line"></div>
          <div class="sig-lbl">Date Completed</div>
        </div>
      </div>

      <p class="footer-note">
        This is an official delivery receipt issued by the Hospital Inventory &amp; Supply Chain Management System.<br>
        Supplier: <strong><?= htmlspecialchars($supplierName) ?></strong>
        &nbsp;·&nbsp; Received by: <strong><?= htmlspecialchars($receivedBySignature ?: 'N/A') ?></strong>
        &nbsp;·&nbsp; <?= $receipt_no ?> &nbsp;·&nbsp; Generated <?= date('F d, Y h:i A') ?>
      </p>

    </div><!-- /receipt-body -->
  </div><!-- /receipt -->
</div><!-- /wrap -->
</body>
</html>