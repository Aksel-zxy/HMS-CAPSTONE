<?php
include '../../SQL/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/* ===============================
   SEARCH
================================ */
$search       = '';
$search_param = '';

if (!empty($_GET['search'])) {
    $search       = trim($_GET['search']);
    $search_param = "%$search%";
}

/* ===============================
   MAIN QUERY
================================ */
$sql = "
SELECT
    pr.receipt_id,
    pr.status,
    pr.payment_method,
    br.transaction_id,
    pr.created_at AS receipt_created,
    pi.patient_id,
    pi.fname, pi.mname, pi.lname,
    br.billing_id,
    br.billing_date,
    br.grand_total,
    br.insurance_covered
FROM billing_records br
INNER JOIN (
    SELECT billing_id, MAX(receipt_id) AS latest_receipt_id
    FROM patient_receipt
    GROUP BY billing_id
) latest ON latest.billing_id = br.billing_id
INNER JOIN patient_receipt pr  ON pr.receipt_id = latest.latest_receipt_id
INNER JOIN patientinfo pi      ON pi.patient_id  = br.patient_id
";

if ($search_param) {
    $sql .= " WHERE pi.fname LIKE ? OR pi.lname LIKE ? OR br.transaction_id LIKE ?";
}

$sql .= " ORDER BY br.billing_date DESC";

$stmt = $conn->prepare($sql);
if ($search_param) {
    $stmt->bind_param("sss", $search_param, $search_param, $search_param);
}
$stmt->execute();
$result = $stmt->get_result();

/* Pre-fetch all rows */
$rows         = [];
$total_paid   = 0;
$total_pend   = 0;
$total_rev    = 0;
while ($r = $result->fetch_assoc()) {
    $rows[] = $r;
    if (strtolower($r['status']) === 'paid') {
        $total_paid++;
        $total_rev += (float)$r['grand_total'];
    } else {
        $total_pend++;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<title>Billing Records — HMS</title>

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

/* ── Content wrapper — margin here, not body ── */
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
.stat-icon.green  { background: #d1fae5; color: #065f46; }
.stat-icon.amber  { background: #fef3c7; color: #92400e; }
.stat-num { font-size: 1.35rem; font-weight: 700; color: var(--navy); line-height: 1; }
.stat-lbl { font-size: .7rem; color: var(--ink-light); font-weight: 600;
            text-transform: uppercase; letter-spacing: .5px; margin-top: 2px; }

/* ── Search bar ── */
.search-card {
  background: var(--card);
  border: 1px solid var(--border);
  border-radius: var(--radius);
  box-shadow: var(--shadow);
  padding: 18px 20px;
  margin-bottom: 20px;
  display: flex;
  gap: 10px;
  flex-wrap: wrap;
  align-items: center;
}
.search-field {
  position: relative;
  flex: 1 1 260px;
}
.search-field i {
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
  color: var(--ink); background: var(--surface);
  outline: none;
  transition: border-color .2s, box-shadow .2s;
}
.search-input:focus {
  border-color: var(--accent);
  box-shadow: 0 0 0 3px rgba(37,99,235,.12);
  background: var(--card);
}
.btn-search {
  padding: 9px 20px;
  background: var(--accent); color: #fff;
  border: none; border-radius: 9px;
  font-family: var(--ff-body); font-size: .87rem; font-weight: 700;
  cursor: pointer; display: inline-flex; align-items: center; gap: 6px;
  transition: background .15s, transform .1s;
  white-space: nowrap;
}
.btn-search:hover { background: #1d4ed8; transform: translateY(-1px); }
.btn-reset {
  padding: 9px 18px;
  background: var(--card); color: var(--ink-light);
  border: 1.5px solid var(--border); border-radius: 9px;
  font-family: var(--ff-body); font-size: .87rem; font-weight: 600;
  cursor: pointer; text-decoration: none;
  display: inline-flex; align-items: center; gap: 6px;
  transition: all .15s; white-space: nowrap;
}
.btn-reset:hover { border-color: var(--accent); color: var(--accent); background: #eff6ff; }

/* ── Table Card ── */
.table-card {
  background: var(--card);
  border: 1px solid var(--border);
  border-radius: var(--radius);
  box-shadow: var(--shadow);
  overflow: hidden;
}

/* ── Desktop Table ── */
.rec-table { width: 100%; border-collapse: collapse; font-size: .87rem; }
.rec-table thead th {
  background: var(--navy);
  color: rgba(255,255,255,.75);
  font-size: .69rem; font-weight: 700;
  text-transform: uppercase; letter-spacing: .65px;
  padding: 13px 16px;
  text-align: left; white-space: nowrap;
}
.rec-table thead th:last-child { text-align: center; }
.rec-table tbody tr {
  border-bottom: 1px solid var(--border);
  transition: background .12s;
}
.rec-table tbody tr:last-child { border-bottom: none; }
.rec-table tbody tr:hover { background: #f7faff; }
.rec-table tbody td { padding: 13px 16px; vertical-align: middle; }
.rec-table tbody td:last-child { text-align: center; }

/* Patient cell */
.pat-cell { display: flex; align-items: center; gap: 10px; }
.pat-avatar {
  width: 36px; height: 36px; border-radius: 50%;
  background: linear-gradient(135deg, var(--navy), var(--accent));
  color: #fff; font-size: .78rem; font-weight: 700;
  display: flex; align-items: center; justify-content: center;
  flex-shrink: 0;
}
.pat-name { font-weight: 600; color: var(--navy); font-size: .88rem; }

/* Date */
.date-val { color: var(--ink-light); font-size: .83rem; }

/* Amount */
.amount-val { font-weight: 700; color: var(--navy); }
.ins-val    { font-size: .82rem; color: var(--ink-light); }

/* Status badge */
.badge-status {
  display: inline-flex; align-items: center; gap: 4px;
  padding: 4px 12px; border-radius: 999px;
  font-size: .72rem; font-weight: 700;
}
.badge-paid    { background: #d1fae5; color: #065f46; }
.badge-pending { background: #fef3c7; color: #92400e; }

/* Method */
.method-val { font-size: .83rem; color: var(--ink); font-weight: 500; }

/* Transaction ID */
.txn-mono {
  font-family: 'Courier New', monospace;
  font-size: .78rem; color: var(--ink-light);
  background: #f1f5f9; border-radius: 6px;
  padding: 2px 8px; display: inline-block;
  max-width: 140px; overflow: hidden;
  text-overflow: ellipsis; white-space: nowrap;
}

/* Print button */
.btn-print {
  display: inline-flex; align-items: center; gap: 5px;
  padding: 5px 14px; border-radius: 8px;
  background: #eff6ff; color: var(--accent);
  border: 1.5px solid #bfdbfe;
  font-family: var(--ff-body); font-size: .78rem; font-weight: 700;
  text-decoration: none; transition: all .15s;
}
.btn-print:hover { background: var(--accent); color: #fff; border-color: var(--accent); }

/* Empty row */
.empty-row td {
  text-align: center; padding: 56px 16px;
  color: var(--ink-light);
}
.empty-row i { font-size: 2.2rem; display: block; margin-bottom: 10px; opacity: .3; }

/* ── Mobile Cards ── */
.mobile-cards { display: none; }
.m-rec-card {
  background: var(--card);
  border: 1px solid var(--border);
  border-radius: var(--radius);
  box-shadow: var(--shadow);
  padding: 16px;
  margin-bottom: 12px;
}
.m-rec-head {
  display: flex; justify-content: space-between;
  align-items: flex-start; gap: 10px; margin-bottom: 12px;
}
.m-rec-row {
  display: flex; justify-content: space-between;
  align-items: center; padding: 7px 0;
  border-bottom: 1px solid var(--border);
  font-size: .83rem; gap: 8px;
}
.m-rec-row:last-of-type { border-bottom: none; }
.m-lbl { color: var(--ink-light); font-weight: 600; font-size: .7rem;
         text-transform: uppercase; letter-spacing: .5px; flex-shrink: 0; }
.m-val { font-weight: 500; text-align: right; color: var(--ink); }
.m-actions { margin-top: 12px; }
.m-actions .btn-print { width: 100%; justify-content: center; }

/* ── Responsive ── */
@media (max-width: 768px) {
  .cw { margin-left: var(--sidebar-w-sm); padding: 60px 14px 50px; }
  .cw.sidebar-collapsed { margin-left: 0; }
  .table-card  { display: none; }
  .mobile-cards { display: block; }
  .search-card { padding: 14px; }
  .page-head h1 { font-size: 1.3rem; }
  .page-head-icon { width: 44px; height: 44px; font-size: 1.2rem; border-radius: 11px; }
}

@media (max-width: 600px) {
  .stats-row { grid-template-columns: 1fr 1fr; }
}

@media (max-width: 480px) {
  .cw { margin-left: 0 !important; padding: 56px 10px 40px; }
  .search-card { flex-direction: column; }
  .search-field, .btn-search, .btn-reset { width: 100%; }
  .btn-search, .btn-reset { justify-content: center; }
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
    <div class="page-head-icon"><i class="bi bi-journal-text"></i></div>
    <div>
      <h1>Billing Records</h1>
      <p>Complete history of all patient billing transactions</p>
    </div>
  </div>

  <!-- Stats -->
  <div class="stats-row">
    <div class="stat-card">
      <div class="stat-icon blue"><i class="bi bi-receipt"></i></div>
      <div>
        <div class="stat-num"><?= count($rows) ?></div>
        <div class="stat-lbl">Total Records</div>
      </div>
    </div>
    <div class="stat-card">
      <div class="stat-icon green"><i class="bi bi-check-circle"></i></div>
      <div>
        <div class="stat-num"><?= $total_paid ?></div>
        <div class="stat-lbl">Paid</div>
      </div>
    </div>
    <div class="stat-card">
      <div class="stat-icon amber"><i class="bi bi-clock"></i></div>
      <div>
        <div class="stat-num"><?= $total_pend ?></div>
        <div class="stat-lbl">Pending</div>
      </div>
    </div>
    <div class="stat-card">
      <div class="stat-icon blue"><i class="bi bi-currency-exchange"></i></div>
      <div>
        <div class="stat-num">₱<?= number_format($total_rev, 0) ?></div>
        <div class="stat-lbl">Total Revenue</div>
      </div>
    </div>
  </div>

  <!-- Search -->
  <form class="search-card" method="GET" action="billing_records.php">
    <div class="search-field">
      <i class="bi bi-search"></i>
      <input type="text"
             name="search"
             class="search-input"
             placeholder="Search patient name or transaction ID…"
             value="<?= htmlspecialchars($search) ?>">
    </div>
    <button type="submit" class="btn-search">
      <i class="bi bi-search"></i> Search
    </button>
    <a href="billing_records.php" class="btn-reset">
      <i class="bi bi-x-circle"></i> Reset
    </a>
    <?php if ($search): ?>
      <span style="font-size:.82rem;color:var(--ink-light);align-self:center;">
        <?= count($rows) ?> result<?= count($rows) !== 1 ? 's' : '' ?> for
        "<strong><?= htmlspecialchars($search) ?></strong>"
      </span>
    <?php endif; ?>
  </form>

  <!-- ── Desktop Table ── -->
  <div class="table-card">
    <div style="overflow-x:auto;">
      <table class="rec-table" id="recTable">
        <thead>
          <tr>
            <th>Patient</th>
            <th>Billing Date</th>
            <th>Total Amount</th>
            <th>Insurance</th>
            <th>Status</th>
            <th>Payment Method</th>
            <th>Transaction ID</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody>
        <?php if (empty($rows)): ?>
          <tr class="empty-row">
            <td colspan="8">
              <i class="bi bi-inbox"></i>
              <?= $search ? 'No records match your search.' : 'No billing records found.' ?>
            </td>
          </tr>
        <?php else: ?>
          <?php foreach ($rows as $row):
            $full_name      = trim($row['fname'] . ' ' . (!empty($row['mname']) ? $row['mname'] . ' ' : '') . $row['lname']);
            $initials       = strtoupper(substr($full_name, 0, 1));
            $status         = strtolower($row['status']);
            $transaction_id = $row['transaction_id'] ?: '—';
            $payment_method = $row['payment_method'] ?: 'Unpaid';
            $billing_date   = $row['billing_date'] ? date('M d, Y', strtotime($row['billing_date'])) : '—';
          ?>
          <tr>
            <td>
              <div class="pat-cell">
                <div class="pat-avatar"><?= $initials ?></div>
                <span class="pat-name"><?= htmlspecialchars($full_name) ?></span>
              </div>
            </td>
            <td><span class="date-val"><?= htmlspecialchars($billing_date) ?></span></td>
            <td><span class="amount-val">₱<?= number_format((float)$row['grand_total'], 2) ?></span></td>
            <td><span class="ins-val">₱<?= number_format((float)$row['insurance_covered'], 2) ?></span></td>
            <td>
              <span class="badge-status <?= $status === 'paid' ? 'badge-paid' : 'badge-pending' ?>">
                <i class="bi bi-<?= $status === 'paid' ? 'check-circle' : 'clock' ?>"></i>
                <?= ucfirst(htmlspecialchars($row['status'])) ?>
              </span>
            </td>
            <td><span class="method-val"><?= htmlspecialchars($payment_method) ?></span></td>
            <td>
              <span class="txn-mono" title="<?= htmlspecialchars($transaction_id) ?>">
                <?= htmlspecialchars($transaction_id) ?>
              </span>
            </td>
            <td>
              <?php if (!empty($row['receipt_id'])): ?>
                <a href="print_receipt.php?receipt_id=<?= $row['receipt_id'] ?>"
                   target="_blank"
                   class="btn-print">
                  <i class="bi bi-printer"></i> Print
                </a>
              <?php else: ?>
                <span style="color:var(--ink-light);font-size:.8rem;">N/A</span>
              <?php endif; ?>
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
    <?php if (empty($rows)): ?>
      <div style="text-align:center;padding:40px;color:var(--ink-light);">
        <i class="bi bi-inbox" style="font-size:2.2rem;display:block;margin-bottom:10px;opacity:.3;"></i>
        <?= $search ? 'No records match your search.' : 'No billing records found.' ?>
      </div>
    <?php else: ?>
      <?php foreach ($rows as $row):
        $full_name      = trim($row['fname'] . ' ' . (!empty($row['mname']) ? $row['mname'] . ' ' : '') . $row['lname']);
        $initials       = strtoupper(substr($full_name, 0, 1));
        $status         = strtolower($row['status']);
        $transaction_id = $row['transaction_id'] ?: '—';
        $payment_method = $row['payment_method'] ?: 'Unpaid';
        $billing_date   = $row['billing_date'] ? date('M d, Y', strtotime($row['billing_date'])) : '—';
      ?>
      <div class="m-rec-card mobile-row">
        <div class="m-rec-head">
          <div class="pat-cell">
            <div class="pat-avatar"><?= $initials ?></div>
            <div class="pat-name"><?= htmlspecialchars($full_name) ?></div>
          </div>
          <span class="badge-status <?= $status === 'paid' ? 'badge-paid' : 'badge-pending' ?>">
            <?= ucfirst(htmlspecialchars($row['status'])) ?>
          </span>
        </div>
        <div class="m-rec-row">
          <span class="m-lbl">Date</span>
          <span class="m-val"><?= htmlspecialchars($billing_date) ?></span>
        </div>
        <div class="m-rec-row">
          <span class="m-lbl">Total</span>
          <span class="m-val amount-val">₱<?= number_format((float)$row['grand_total'], 2) ?></span>
        </div>
        <div class="m-rec-row">
          <span class="m-lbl">Insurance</span>
          <span class="m-val">₱<?= number_format((float)$row['insurance_covered'], 2) ?></span>
        </div>
        <div class="m-rec-row">
          <span class="m-lbl">Method</span>
          <span class="m-val"><?= htmlspecialchars($payment_method) ?></span>
        </div>
        <div class="m-rec-row">
          <span class="m-lbl">Txn ID</span>
          <span class="m-val" style="font-family:monospace;font-size:.78rem;">
            <?= htmlspecialchars($transaction_id) ?>
          </span>
        </div>
        <?php if (!empty($row['receipt_id'])): ?>
        <div class="m-actions">
          <a href="print_receipt.php?receipt_id=<?= $row['receipt_id'] ?>"
             target="_blank"
             class="btn-print">
            <i class="bi bi-printer"></i> Print Receipt
          </a>
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
</script>
</body>
</html>