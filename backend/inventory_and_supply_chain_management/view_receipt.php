<?php
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

$grand_total = 0.00;
foreach ($items as $item) {
    $grand_total += isset($item['price']) ? (float)$item['price'] : 0;
}

$delivery_date = !empty($request['delivered_at'])
    ? date('F d, Y', strtotime($request['delivered_at']))
    : (!empty($request['purchased_at'])
        ? date('F d, Y', strtotime($request['purchased_at']))
        : date('F d, Y'));

$status     = strtolower($request['status']);
$receipt_no = 'DR-' . str_pad($request['id'], 6, '0', STR_PAD_LEFT);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Delivery Receipt <?= $receipt_no ?></title>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700;900&family=DM+Sans:ital,wght@0,300;0,400;0,500;0,600;1,400&display=swap" rel="stylesheet">
<style>
:root {
  --ink:       #111827;
  --ink-mid:   #374151;
  --ink-light: #6b7280;
  --accent:    #1d4ed8;
  --border:    #e5e7eb;
  --page:      #f3f4f6;
  --surface:   #ffffff;
}
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

body {
  font-family: 'DM Sans', sans-serif;
  background: var(--page);
  color: var(--ink);
  padding: 36px 20px 60px;
}

.wrap { max-width: 760px; margin: 0 auto; }

/* Toolbar */
.toolbar {
  display: flex;
  justify-content: flex-end;
  gap: 10px;
  margin-bottom: 20px;
}
.btn {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 9px 18px;
  border-radius: 8px;
  font-family: 'DM Sans', sans-serif;
  font-size: 0.86rem;
  font-weight: 600;
  cursor: pointer;
  border: none;
  text-decoration: none;
  transition: .15s;
}
.btn-primary  { background: var(--accent); color: #fff; }
.btn-primary:hover { background: #1e40af; }
.btn-secondary { background: #fff; color: var(--ink-mid); border: 1px solid var(--border); }
.btn-secondary:hover { background: #f9fafb; }

/* Card */
.receipt {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 12px;
  box-shadow: 0 4px 24px rgba(0,0,0,.07);
  overflow: hidden;
}

/* Dark header */
.receipt-top {
  background: var(--ink);
  color: #fff;
  padding: 30px 40px 26px;
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 20px;
}
.org-name {
  font-family: 'Playfair Display', serif;
  font-size: 1.3rem;
  font-weight: 900;
  line-height: 1.2;
}
.org-tagline {
  font-size: 0.7rem;
  color: rgba(255,255,255,.4);
  text-transform: uppercase;
  letter-spacing: .7px;
  margin-top: 5px;
}
.doc-block { text-align: right; }
.doc-block .lbl {
  font-size: 0.67rem;
  color: rgba(255,255,255,.38);
  text-transform: uppercase;
  letter-spacing: .7px;
}
.doc-block .doc-type {
  font-family: 'Playfair Display', serif;
  font-size: 1.2rem;
  font-weight: 700;
  margin-top: 2px;
}
.doc-block .doc-num {
  font-size: 0.8rem;
  color: rgba(255,255,255,.5);
  margin-top: 4px;
}

/* Info bar */
.info-bar {
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 10px;
  padding: 12px 40px;
  background: #f9fafb;
  border-bottom: 1px solid var(--border);
}
.info-bar .field {
  font-size: 0.82rem;
  color: var(--ink-mid);
}
.info-bar .field strong { color: var(--ink); font-weight: 600; }

.pill {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 3px 11px;
  border-radius: 999px;
  font-size: 0.74rem;
  font-weight: 600;
  text-transform: capitalize;
}
.pill-delivered { background: #d1fae5; color: #065f46; }
.pill-default   { background: #dbeafe; color: #1d4ed8; }

/* Body */
.receipt-body { padding: 30px 40px 38px; }

.sec-label {
  font-size: 0.68rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: .8px;
  color: var(--ink-light);
  padding-bottom: 8px;
  border-bottom: 1px solid var(--border);
  margin-bottom: 0;
}

/* Table */
.items-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.89rem;
}
.items-table thead th {
  padding: 9px 12px;
  background: #f9fafb;
  font-size: 0.71rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: .55px;
  color: var(--ink-light);
  border-bottom: 1px solid var(--border);
  text-align: left;
}
.items-table thead th:last-child { text-align: right; }
.items-table thead th:first-child { width: 42px; text-align: center; }

.items-table tbody td {
  padding: 11px 12px;
  border-bottom: 1px solid var(--border);
  vertical-align: middle;
}
.items-table tbody tr:last-child td { border-bottom: none; }
.items-table tbody td:first-child { text-align: center; color: var(--ink-light); font-size: 0.8rem; }
.items-table tbody td:last-child  { text-align: right; font-weight: 500; }

.no-items td {
  text-align: center;
  padding: 28px;
  color: var(--ink-light);
  font-style: italic;
  font-size: 0.88rem;
}

/* Totals */
.totals-wrap {
  display: flex;
  justify-content: flex-end;
  padding-top: 14px;
  border-top: 1px solid var(--border);
}
.totals-tbl { width: 256px; font-size: 0.87rem; border-collapse: collapse; }
.totals-tbl td { padding: 4px 0; color: var(--ink-mid); }
.totals-tbl td:last-child { text-align: right; }
.totals-tbl .sep td { border-top: 1px solid var(--border); padding-top: 10px; margin-top: 4px; }
.totals-tbl .grand td {
  font-size: 1rem;
  font-weight: 700;
  color: var(--ink);
  border-top: 1.5px solid var(--ink);
  padding-top: 10px;
}
.totals-tbl .grand td:last-child { color: var(--accent); }

/* Signatures */
.signatures {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 16px;
  margin-top: 40px;
}
.sig-block { text-align: center; }
.sig-space { height: 46px; }
.sig-line  { border-top: 1px solid var(--ink); margin: 0 8px; }
.sig-lbl   { font-size: 0.72rem; color: var(--ink-light); margin-top: 6px; text-transform: uppercase; letter-spacing: .5px; }

/* Footer */
.footer-note {
  text-align: center;
  font-size: 0.73rem;
  color: var(--ink-light);
  margin-top: 26px;
  padding-top: 18px;
  border-top: 1px dashed var(--border);
  line-height: 1.65;
}

/* ══ PRINT ══════════════════════════════ */
@media print {
  @page { size: A4; margin: 16mm 15mm; }

  body {
    background: #fff !important;
    padding: 0 !important;
    -webkit-print-color-adjust: exact;
    print-color-adjust: exact;
  }

  .toolbar { display: none !important; }
  .wrap { max-width: 100%; }

  .receipt {
    border: none !important;
    border-radius: 0 !important;
    box-shadow: none !important;
  }

  .receipt-top {
    background: #111827 !important;
    color: #fff !important;
    -webkit-print-color-adjust: exact;
    print-color-adjust: exact;
    padding: 20px 32px 18px !important;
  }

  .info-bar  { padding: 10px 32px !important; }
  .receipt-body { padding: 22px 32px 30px !important; }

  .items-table thead th { background: #f3f4f6 !important; }
  .items-table tbody tr:hover { background: transparent; }

  .signatures  { page-break-inside: avoid; }
  .footer-note { page-break-inside: avoid; }
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
        <svg style="vertical-align:-3px;margin-right:3px;" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
        Date Delivered: <strong><?= $delivery_date ?></strong>
      </div>
      <div class="field">
        Department: <strong><?= htmlspecialchars($request['department']) ?></strong>
      </div>
      <span class="pill <?= $status === 'delivered' ? 'pill-delivered' : 'pill-default' ?>">
        <?= $status === 'delivered' ? '✓ ' : '' ?><?= ucfirst(htmlspecialchars($request['status'])) ?>
      </span>
    </div>

    <!-- Body -->
    <div class="receipt-body">

      <div class="sec-label">Delivered Items</div>

      <table class="items-table">
        <thead>
          <tr>
            <th>#</th>
            <th>Item Name</th>
            <th>Amount (₱)</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($items)): ?>
            <tr class="no-items"><td colspan="3">No items found for this request.</td></tr>
          <?php else: ?>
            <?php foreach($items as $idx => $item):
              $price = isset($item['price']) ? (float)$item['price'] : 0;
            ?>
            <tr>
              <td><?= $idx + 1 ?></td>
              <td><?= htmlspecialchars($item['item_name']) ?></td>
              <td>₱ <?= number_format($price, 2) ?></td>
            </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>

      <div class="totals-wrap">
        <table class="totals-tbl">
          <tr>
            <td>Subtotal</td>
            <td>₱ <?= number_format($grand_total, 2) ?></td>
          </tr>
          <tr>
            <td>Tax / Other Charges</td>
            <td>₱ 0.00</td>
          </tr>
          <tr class="grand">
            <td>Grand Total</td>
            <td>₱ <?= number_format($grand_total, 2) ?></td>
          </tr>
        </table>
      </div>

      <div class="signatures">
        <div class="sig-block">
          <div class="sig-space"></div>
          <div class="sig-line"></div>
          <div class="sig-lbl">Prepared By</div>
        </div>
        <div class="sig-block">
          <div class="sig-space"></div>
          <div class="sig-line"></div>
          <div class="sig-lbl">Received By</div>
        </div>
        <div class="sig-block">
          <div class="sig-space"></div>
          <div class="sig-line"></div>
          <div class="sig-lbl">Date</div>
        </div>
      </div>

      <p class="footer-note">
        This is an official delivery receipt issued by the Hospital Inventory &amp; Supply Chain Management System.<br>
        Please retain a copy for your department records.
      </p>

    </div>
  </div>

</div>
</body>
</html>