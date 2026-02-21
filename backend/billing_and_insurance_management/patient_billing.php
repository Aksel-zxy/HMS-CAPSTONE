<?php
include '../../SQL/config.php';
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/vendor/autoload.php';
use GuzzleHttp\Client;

/* =====================================================
   PAYMONGO CONFIG
===================================================== */
define('PAYMONGO_SECRET_KEY', 'sk_test_akT1ZW6za7m6FC9S9VqYNiVV');
define('PAYMONGO_PAYMENT_API', 'https://api.paymongo.com/v1/payments');

$client = new Client([
    'headers' => [
        'Accept'        => 'application/json',
        'Authorization' => 'Basic ' . base64_encode(PAYMONGO_SECRET_KEY . ':'),
    ],
    'timeout' => 5
]);

$pending = $conn->query("
    SELECT billing_id, paymongo_payment_id
    FROM billing_records
    WHERE status = 'Pending'
      AND paymongo_payment_id IS NOT NULL
");

while ($row = $pending->fetch_assoc()) {
    try {
        $response = $client->get(PAYMONGO_PAYMENT_API . '/' . $row['paymongo_payment_id']);
        $payment  = json_decode($response->getBody(), true);
        if (
            isset($payment['data']['attributes']['status']) &&
            $payment['data']['attributes']['status'] === 'paid'
        ) {
            $billing_id = (int)$row['billing_id'];
            $stmt = $conn->prepare("UPDATE billing_records SET status='Paid' WHERE billing_id=?");
            $stmt->bind_param("i", $billing_id); $stmt->execute();
            $stmt = $conn->prepare("UPDATE patient_receipt SET status='Paid' WHERE billing_id=?");
            $stmt->bind_param("i", $billing_id); $stmt->execute();
        }
    } catch (Exception $e) { /* Silent fail */ }
}

/* =====================================================
   FETCH PENDING BILLINGS
   Only show patients whose billing_records status is NOT 'Paid'
===================================================== */
$sql = "
SELECT
    p.patient_id,
    CONCAT(p.fname,' ',IFNULL(p.mname,''),' ',p.lname) AS full_name,
    bi.billing_id,
    SUM(bi.total_price) AS total_amount,
    MAX(pr.receipt_id) AS receipt_id,
    MAX(pr.status) AS payment_status,
    MAX(pr.insurance_covered) AS insurance_covered,
    MAX(pr.payment_method) AS payment_method,
    MAX(pr.paymongo_reference) AS paymongo_reference,
    MAX(br.status) AS billing_status
FROM patientinfo p
INNER JOIN billing_items bi ON bi.patient_id = p.patient_id AND bi.finalized = 1
INNER JOIN billing_records br ON br.billing_id = bi.billing_id
LEFT JOIN patient_receipt pr ON pr.billing_id = bi.billing_id
WHERE br.status != 'Paid'
  AND br.status != 'Cancelled'
GROUP BY p.patient_id, bi.billing_id
HAVING (MAX(pr.status) IS NULL OR MAX(pr.status) != 'Paid')
ORDER BY p.lname, p.fname
";

$result = $conn->query($sql);
if (!$result) { error_log("SQL Error: " . $conn->error); }

/* Pre-fetch ALL rows before any HTML */
$rows_cache    = [];
$total_pending = 0;
$total_amount  = 0;
$insured_count = 0;
if ($result) {
    while ($r = $result->fetch_assoc()) $rows_cache[] = $r;
    $total_pending = count($rows_cache);
    $total_amount  = array_sum(array_column($rows_cache, 'total_amount'));
    $insured_count = count(array_filter($rows_cache, fn($r) => ((float)($r['insurance_covered'] ?? 0)) > 0));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<title>Patient Billing — HMS</title>

<link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display&family=DM+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

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
  --info:         #0284c7;
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
.head-actions { margin-left: auto; display: flex; gap: 10px; flex-wrap: wrap; align-items: center; }

.btn-refresh {
  display: inline-flex; align-items: center; gap: 6px;
  padding: 8px 18px;
  background: var(--card);
  color: var(--ink-light);
  border: 1.5px solid var(--border);
  border-radius: 9px;
  font-family: var(--ff-body);
  font-size: .83rem; font-weight: 600;
  cursor: pointer;
  transition: all .15s;
}
.btn-refresh:hover { border-color: var(--accent); color: var(--accent); background: #eff6ff; }
.btn-refresh:disabled { opacity: .6; cursor: not-allowed; }

/* ── Stats Row ── */
.stats-row {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
  gap: 14px;
  margin-bottom: 24px;
}
.stat-card {
  background: var(--card);
  border: 1px solid var(--border);
  border-radius: var(--radius);
  padding: 16px 20px;
  box-shadow: var(--shadow);
  display: flex; align-items: center; gap: 14px;
}
.stat-icon {
  width: 44px; height: 44px;
  border-radius: 11px;
  display: flex; align-items: center; justify-content: center;
  font-size: 1.2rem; flex-shrink: 0;
}
.stat-icon.blue   { background: #dbeafe; color: #1d4ed8; }
.stat-icon.amber  { background: #fef3c7; color: #92400e; }
.stat-icon.green  { background: #d1fae5; color: #065f46; }
.stat-num  { font-size: 1.5rem; font-weight: 700; color: var(--navy); line-height: 1; }
.stat-lbl  { font-size: .72rem; color: var(--ink-light); font-weight: 600;
             text-transform: uppercase; letter-spacing: .5px; margin-top: 2px; }

/* ── Search ── */
.search-wrap {
  position: relative;
  max-width: 340px;
  margin-bottom: 18px;
}
.search-wrap i {
  position: absolute; left: 13px; top: 50%;
  transform: translateY(-50%);
  color: var(--ink-light); font-size: .9rem; pointer-events: none;
}
.search-input {
  width: 100%;
  padding: 9px 14px 9px 36px;
  border: 1.5px solid var(--border);
  border-radius: 9px;
  font-family: var(--ff-body); font-size: .87rem;
  color: var(--ink); background: var(--card);
  outline: none;
  transition: border-color .2s, box-shadow .2s;
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

/* ── Desktop Table ── */
.bill-table { width: 100%; border-collapse: collapse; font-size: .87rem; }
.bill-table thead th {
  background: var(--navy);
  color: rgba(255,255,255,.75);
  font-size: .69rem; font-weight: 700;
  text-transform: uppercase; letter-spacing: .65px;
  padding: 13px 16px;
  text-align: left; white-space: nowrap;
}
.bill-table thead th:last-child { text-align: right; }

.bill-table tbody tr {
  border-bottom: 1px solid var(--border);
  transition: background .12s;
}
.bill-table tbody tr:last-child { border-bottom: none; }
.bill-table tbody tr:hover { background: #f7faff; }
.bill-table tbody td { padding: 14px 16px; vertical-align: middle; }
.bill-table tbody td:last-child { text-align: right; }

/* Patient cell */
.pat-cell { display: flex; align-items: center; gap: 10px; }
.pat-avatar {
  width: 38px; height: 38px; border-radius: 50%;
  background: linear-gradient(135deg, var(--navy), var(--accent));
  color: #fff; font-size: .8rem; font-weight: 700;
  display: flex; align-items: center; justify-content: center;
  flex-shrink: 0;
}
.pat-name  { font-weight: 600; color: var(--navy); font-size: .9rem; }
.pat-id    { font-size: .72rem; color: var(--ink-light); margin-top: 1px; }

.bill-id {
  font-family: 'Courier New', monospace;
  font-size: .8rem; color: var(--ink-light);
  background: #f1f5f9; border-radius: 6px;
  padding: 2px 8px; display: inline-block;
}

/* Status badges */
.badge-status {
  display: inline-flex; align-items: center; gap: 4px;
  padding: 4px 12px; border-radius: 999px;
  font-size: .72rem; font-weight: 700;
}
.badge-paid    { background: #d1fae5; color: #065f46; }
.badge-pending { background: #fef3c7; color: #92400e; }
.badge-ins     { background: #dbeafe; color: #1d4ed8; }
.badge-none    { background: #f1f5f9; color: #64748b; }

.amount-val { font-weight: 700; color: var(--navy); font-size: .95rem; }

/* Action buttons */
.actions-group { display: flex; gap: 6px; justify-content: flex-end; flex-wrap: wrap; }

.btn-act {
  display: inline-flex; align-items: center; gap: 5px;
  padding: 6px 13px; border-radius: 8px;
  font-family: var(--ff-body); font-size: .78rem; font-weight: 700;
  cursor: pointer; border: none; text-decoration: none;
  transition: all .15s; white-space: nowrap;
}
.btn-act:hover { transform: translateY(-1px); }
.btn-view     { background: #f1f5f9; color: var(--ink-light); border: 1.5px solid var(--border); }
.btn-view:hover { background: var(--ink-light); color: #fff; border-color: var(--ink-light); }
.btn-pay      { background: var(--success); color: #fff; }
.btn-pay:hover  { background: #047857; }
.btn-ins      { background: #dbeafe; color: #1d4ed8; border: 1.5px solid #bfdbfe; }
.btn-ins:hover  { background: #1d4ed8; color: #fff; border-color: #1d4ed8; }

/* Empty row */
.empty-row td {
  text-align: center; padding: 56px 16px;
  color: var(--ink-light);
}
.empty-row i { font-size: 2.2rem; display: block; margin-bottom: 10px; opacity: .3; }
.empty-row span { font-size: .9rem; }

/* ── Mobile Cards ── */
.mobile-cards { display: none; }
.m-bill-card {
  background: var(--card);
  border: 1px solid var(--border);
  border-radius: var(--radius);
  box-shadow: var(--shadow);
  padding: 16px;
  margin-bottom: 12px;
}
.m-bill-head {
  display: flex; justify-content: space-between;
  align-items: flex-start; gap: 10px; margin-bottom: 12px;
}
.m-bill-row {
  display: flex; justify-content: space-between;
  align-items: center; padding: 7px 0;
  border-bottom: 1px solid var(--border);
  font-size: .83rem; gap: 8px;
}
.m-bill-row:last-of-type { border-bottom: none; }
.m-lbl { color: var(--ink-light); font-weight: 600; font-size: .7rem;
         text-transform: uppercase; letter-spacing: .5px; flex-shrink: 0; }
.m-val { font-weight: 500; text-align: right; }
.m-actions { margin-top: 12px; display: flex; flex-direction: column; gap: 7px; }
.m-actions .btn-act { width: 100%; justify-content: center; }

/* ── Modal ── */
.modal-content { border-radius: var(--radius); border: none; box-shadow: var(--shadow-lg); overflow: hidden; }
.modal-header { background: var(--navy); color: #fff; padding: 16px 22px; border-bottom: none; }
.modal-header .modal-title { font-family: var(--ff-head); font-size: 1rem; }
.modal-header .btn-close { filter: invert(1); }
.modal-body { padding: 22px; background: #f8fafc; }
.modal-footer { background: var(--card); border-top: 1px solid var(--border); padding: 14px 22px; }

.ins-search-row { display: flex; gap: 8px; margin-bottom: 4px; }
.ins-search-row input {
  flex: 1; padding: 9px 13px;
  border: 1.5px solid var(--border); border-radius: 8px;
  font-family: var(--ff-body); font-size: .87rem; color: var(--ink);
  outline: none; transition: border-color .2s;
}
.ins-search-row input:focus { border-color: var(--accent); }
.btn-search {
  padding: 9px 18px; background: var(--accent); color: #fff;
  border: none; border-radius: 8px; font-family: var(--ff-body);
  font-size: .87rem; font-weight: 700; cursor: pointer;
  transition: background .15s;
}
.btn-search:hover { background: #1d4ed8; }

.ins-info-box {
  background: var(--card); border: 1px solid var(--border);
  border-radius: 10px; padding: 14px 16px; margin-top: 12px;
  font-size: .85rem;
}
.ins-info-box .row-item {
  display: flex; justify-content: space-between;
  padding: 5px 0; border-bottom: 1px solid var(--border);
}
.ins-info-box .row-item:last-child { border-bottom: none; }
.ins-info-box .lbl { color: var(--ink-light); }
.ins-info-box .val { font-weight: 600; color: var(--navy); }

.btn-apply-ins {
  padding: 9px 22px; background: var(--success); color: #fff;
  border: none; border-radius: 8px; font-family: var(--ff-body);
  font-size: .87rem; font-weight: 700; cursor: pointer;
  transition: background .15s;
}
.btn-apply-ins:hover { background: #047857; }

/* ── Responsive ── */
@media (max-width: 768px) {
  .cw { margin-left: var(--sidebar-w-sm); padding: 60px 14px 50px; }
  .cw.sidebar-collapsed { margin-left: 0; }
  .table-card  { display: none; }
  .mobile-cards { display: block; }
  .search-wrap { max-width: 100%; }
  .page-head h1 { font-size: 1.3rem; }
  .page-head-icon { width: 44px; height: 44px; font-size: 1.2rem; border-radius: 11px; }
  .head-actions { margin-left: 0; width: 100%; }
  .stats-row { grid-template-columns: repeat(3, 1fr); gap: 10px; }
  .stat-card { padding: 12px 14px; }
}

@media (max-width: 480px) {
  .cw { margin-left: 0 !important; padding: 56px 10px 40px; }
  .stats-row { grid-template-columns: 1fr 1fr; }
  .ins-search-row { flex-direction: column; }
  .btn-search { width: 100%; }
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
    <div class="page-head-icon"><i class="bi bi-cash-coin"></i></div>
    <div>
      <h1>Patient Billing</h1>
      <p>Pending billings awaiting payment or insurance processing</p>
    </div>
    <div class="head-actions">
      <button class="btn-refresh" id="refreshBtn" onclick="refreshAndSync(this)">
        <i class="bi bi-arrow-clockwise"></i> Sync Payments
      </button>
    </div>
  </div>

  <!-- Stats -->
  <div class="stats-row">
    <div class="stat-card">
      <div class="stat-icon blue"><i class="bi bi-people"></i></div>
      <div>
        <div class="stat-num"><?= $total_pending ?></div>
        <div class="stat-lbl">Pending Bills</div>
      </div>
    </div>
    <div class="stat-card">
      <div class="stat-icon amber"><i class="bi bi-currency-exchange"></i></div>
      <div>
        <div class="stat-num">₱<?= number_format($total_amount, 0) ?></div>
        <div class="stat-lbl">Total Outstanding</div>
      </div>
    </div>
    <div class="stat-card">
      <div class="stat-icon green"><i class="bi bi-shield-check"></i></div>
      <div>
        <div class="stat-num"><?= $insured_count ?></div>
        <div class="stat-lbl">With Insurance</div>
      </div>
    </div>
  </div>

  <!-- Search -->
  <div class="search-wrap">
    <i class="bi bi-search"></i>
    <input type="text" class="search-input" id="searchInput" placeholder="Search patient or billing ID…">
  </div>

  <!-- ── Desktop Table ── -->
  <div class="table-card">
    <div style="overflow-x:auto;">
      <table class="bill-table" id="billTable">
        <thead>
          <tr>
            <th>Patient</th>
            <th>Billing ID</th>
            <th>Insurance</th>
            <th>Status</th>
            <th>Total</th>
            <th style="text-align:right;">Actions</th>
          </tr>
        </thead>
        <tbody>
        <?php if (empty($rows_cache)): ?>
          <tr class="empty-row">
            <td colspan="6">
              <i class="bi bi-check-circle"></i>
              <span>No pending billings — all caught up! 🎉</span>
            </td>
          </tr>
        <?php else: ?>
          <?php foreach ($rows_cache as $row):
            $patient_id        = (int)($row['patient_id'] ?? 0);
            $full_name         = trim($row['full_name'] ?? 'Unknown Patient');
            $billing_id        = (int)($row['billing_id'] ?? 0);
            $total             = (float)($row['total_amount'] ?? 0);
            $receipt_id        = $row['receipt_id'] ?? null;
            $status            = $row['payment_status'] ?? 'Pending';
            $insurance_covered = (float)($row['insurance_covered'] ?? 0);
            $payment_method    = $row['payment_method'] ?? null;
            $insuranceApplied  = ($insurance_covered > 0);
            $initials          = strtoupper(substr($full_name, 0, 1));

            if (!$patient_id || !$billing_id) continue;

            if (!$receipt_id) {
              $stmt = $conn->prepare("INSERT INTO patient_receipt (patient_id, billing_id, status) VALUES (?, ?, 'Pending')");
              if ($stmt) {
                $stmt->bind_param("ii", $patient_id, $billing_id);
                if ($stmt->execute()) {
                  $receipt_id = $conn->insert_id;
                  $row['receipt_id'] = $receipt_id;
                }
                $stmt->close();
              }
            }
          ?>
          <tr class="bill-row">
            <td>
              <div class="pat-cell">
                <div class="pat-avatar"><?= $initials ?></div>
                <div>
                  <div class="pat-name"><?= htmlspecialchars($full_name) ?></div>
                  <div class="pat-id">ID #<?= $patient_id ?></div>
                </div>
              </div>
            </td>
            <td><span class="bill-id">#<?= $billing_id ?></span></td>
            <td>
              <?php if ($insuranceApplied): ?>
                <span class="badge-status badge-ins">
                  <i class="bi bi-shield-check"></i> <?= htmlspecialchars($payment_method) ?>
                </span>
              <?php else: ?>
                <span class="badge-status badge-none">N/A</span>
              <?php endif; ?>
            </td>
            <td>
              <span class="badge-status <?= $status === 'Paid' ? 'badge-paid' : 'badge-pending' ?>">
                <i class="bi bi-<?= $status === 'Paid' ? 'check-circle' : 'clock' ?>"></i>
                <?= htmlspecialchars($status) ?>
              </span>
            </td>
            <td><span class="amount-val">₱<?= number_format($total, 2) ?></span></td>
            <td>
              <div class="actions-group">
                <a href="print_receipt.php?receipt_id=<?= urlencode($receipt_id) ?>" target="_blank" class="btn-act btn-view">
                  <i class="bi bi-receipt"></i> View Bill
                </a>
                <a href="billing_summary.php?patient_id=<?= urlencode($patient_id) ?>" class="btn-act btn-pay">
                  <i class="bi bi-cash-stack"></i> Process
                </a>
                <?php if (!$insuranceApplied): ?>
                <button class="btn-act btn-ins" data-bs-toggle="modal" data-bs-target="#insModal<?= $billing_id ?>">
                  <i class="bi bi-shield-plus"></i> Insurance
                </button>
                <?php endif; ?>
              </div>
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
    <?php if (empty($rows_cache)): ?>
      <div style="text-align:center;padding:40px;color:var(--ink-light);">
        <i class="bi bi-check-circle" style="font-size:2.2rem;display:block;margin-bottom:10px;opacity:.3;"></i>
        No pending billings — all caught up! 🎉
      </div>
    <?php else: ?>
      <?php foreach ($rows_cache as $row):
        $patient_id        = (int)($row['patient_id'] ?? 0);
        $full_name         = trim($row['full_name'] ?? 'Unknown Patient');
        $billing_id        = (int)($row['billing_id'] ?? 0);
        $total             = (float)($row['total_amount'] ?? 0);
        $receipt_id        = $row['receipt_id'] ?? null;
        $status            = $row['payment_status'] ?? 'Pending';
        $insurance_covered = (float)($row['insurance_covered'] ?? 0);
        $payment_method    = $row['payment_method'] ?? null;
        $insuranceApplied  = ($insurance_covered > 0);
        $initials          = strtoupper(substr($full_name, 0, 1));
        if (!$patient_id || !$billing_id) continue;
      ?>
      <div class="m-bill-card mobile-row">
        <div class="m-bill-head">
          <div class="pat-cell">
            <div class="pat-avatar"><?= $initials ?></div>
            <div>
              <div class="pat-name"><?= htmlspecialchars($full_name) ?></div>
              <span class="bill-id">#<?= $billing_id ?></span>
            </div>
          </div>
          <span class="badge-status <?= $status === 'Paid' ? 'badge-paid' : 'badge-pending' ?>">
            <?= htmlspecialchars($status) ?>
          </span>
        </div>
        <div class="m-bill-row">
          <span class="m-lbl">Insurance</span>
          <span class="m-val">
            <?php if ($insuranceApplied): ?>
              <span class="badge-status badge-ins" style="font-size:.7rem;">
                <?= htmlspecialchars($payment_method) ?>
              </span>
            <?php else: ?>
              <span class="badge-status badge-none" style="font-size:.7rem;">N/A</span>
            <?php endif; ?>
          </span>
        </div>
        <div class="m-bill-row">
          <span class="m-lbl">Total</span>
          <span class="m-val amount-val">₱<?= number_format($total, 2) ?></span>
        </div>
        <div class="m-actions">
          <a href="print_receipt.php?receipt_id=<?= urlencode($receipt_id) ?>" target="_blank" class="btn-act btn-view">
            <i class="bi bi-receipt"></i> View Bill
          </a>
          <a href="billing_summary.php?patient_id=<?= urlencode($patient_id) ?>" class="btn-act btn-pay">
            <i class="bi bi-cash-stack"></i> Process Payment
          </a>
          <?php if (!$insuranceApplied): ?>
          <button class="btn-act btn-ins" data-bs-toggle="modal" data-bs-target="#insModal<?= $billing_id ?>">
            <i class="bi bi-shield-plus"></i> Enter Insurance
          </button>
          <?php endif; ?>
        </div>
      </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>

</div><!-- /cw -->

<!-- ── Insurance Modals ── -->
<?php if (!empty($rows_cache)): foreach ($rows_cache as $row):
  $patient_id = (int)($row['patient_id'] ?? 0);
  $billing_id = (int)($row['billing_id'] ?? 0);
  $full_name  = trim($row['full_name'] ?? 'Unknown');
  $insuranceApplied = ((float)($row['insurance_covered'] ?? 0)) > 0;
  if (!$patient_id || !$billing_id || $insuranceApplied) continue;
?>
<div class="modal fade" id="insModal<?= $billing_id ?>" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" style="max-width:440px;">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">
          <i class="bi bi-shield-plus me-2"></i>Apply Insurance
          <small style="font-family:var(--ff-body);font-weight:400;font-size:.8rem;opacity:.7;">
            — <?= htmlspecialchars($full_name) ?>
          </small>
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="patient_id_<?= $billing_id ?>" value="<?= $patient_id ?>">
        <input type="hidden" id="billing_id_<?= $billing_id ?>"  value="<?= $billing_id ?>">

        <label style="font-size:.82rem;font-weight:600;color:var(--ink-light);text-transform:uppercase;letter-spacing:.5px;margin-bottom:7px;display:block;">
          Insurance Number
        </label>
        <div class="ins-search-row">
          <input type="text"
                 id="insurance_number_<?= $billing_id ?>"
                 placeholder="Enter insurance number">
          <button class="btn-search" onclick="previewInsurance(<?= $billing_id ?>)">
            <i class="bi bi-search"></i> Search
          </button>
        </div>

        <div id="insurance_preview_<?= $billing_id ?>"></div>
        <div id="billing_info_<?= $billing_id ?>"></div>
      </div>
      <div class="modal-footer">
        <button type="button"
                class="btn-apply-ins"
                id="applyBtn_<?= $billing_id ?>"
                style="display:none;"
                onclick="applyInsurance(<?= $billing_id ?>)">
          <i class="bi bi-check-circle"></i> Apply Insurance
        </button>
        <button type="button"
                class="btn-act btn-view"
                style="border-radius:8px;"
                data-bs-dismiss="modal">
          Cancel
        </button>
      </div>
    </div>
  </div>
</div>
<?php endforeach; endif; ?>

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

/* ── Live search ── */
document.getElementById('searchInput').addEventListener('input', function () {
    const q = this.value.toLowerCase().trim();
    document.querySelectorAll('#billTable tbody .bill-row').forEach(r => {
        r.style.display = r.textContent.toLowerCase().includes(q) ? '' : 'none';
    });
    document.querySelectorAll('#mobileCards .mobile-row').forEach(c => {
        c.style.display = c.textContent.toLowerCase().includes(q) ? '' : 'none';
    });
});

/* ── Refresh / Sync ── */
async function refreshAndSync(btn) {
    btn.disabled = true;
    const orig = btn.innerHTML;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Syncing…';
    try {
        await fetch('fetch_paid_payments.php?json=1', { method: 'GET', headers: { 'Accept': 'application/json' } });
    } catch (err) { console.error('Sync failed', err); }
    finally { window.location.reload(); }
}

/* ── Insurance Preview ── */
function previewInsurance(id) {
    const insNum = document.getElementById('insurance_number_' + id).value.trim();
    if (!insNum) { alert('Please enter an insurance number.'); return; }

    const fd = new FormData();
    fd.append('action', 'preview');
    fd.append('patient_id', document.getElementById('patient_id_' + id).value);
    fd.append('billing_id', document.getElementById('billing_id_' + id).value);
    fd.append('insurance_number', insNum);

    fetch('apply_insurance.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(res => {
        if (res.status === 'preview') {
            document.getElementById('insurance_preview_' + id).innerHTML = res.insurance_card_html;
            document.getElementById('billing_info_' + id).innerHTML = `
                <div class="ins-info-box">
                    <div class="row-item"><span class="lbl">Insurance Covered</span><span class="val">₱${res.insurance_covered}</span></div>
                    <div class="row-item"><span class="lbl">Out of Pocket</span><span class="val">₱${res.out_of_pocket}</span></div>
                </div>`;
            document.getElementById('applyBtn_' + id).style.display = 'inline-flex';
        } else {
            document.getElementById('applyBtn_' + id).style.display = 'none';
            document.getElementById('insurance_preview_' + id).innerHTML = '';
            document.getElementById('billing_info_' + id).innerHTML =
                `<div style="padding:10px;color:#dc2626;font-size:.85rem;"><i class="bi bi-exclamation-circle me-1"></i>${res.message}</div>`;
        }
    })
    .catch(err => { console.error(err); alert('Error: ' + err.message); });
}

/* ── Apply Insurance ── */
function applyInsurance(id) {
    const fd = new FormData();
    fd.append('action', 'apply');
    fd.append('patient_id', document.getElementById('patient_id_' + id).value);
    fd.append('billing_id', document.getElementById('billing_id_' + id).value);
    fd.append('insurance_number', document.getElementById('insurance_number_' + id).value.trim());

    fetch('apply_insurance.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(res => {
        if (res.status === 'success') {
            const modal = bootstrap.Modal.getInstance(document.getElementById('insModal' + id));
            modal?.hide();
            location.reload();
        } else {
            alert(res.message);
        }
    })
    .catch(err => { console.error(err); alert('Error: ' + err.message); });
}
</script>
</body>
</html>