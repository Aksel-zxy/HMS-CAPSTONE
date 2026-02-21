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
   FETCH PAYMENTS (Paid only)
================================ */
$search_clause_pay = $search
    ? "AND (
        pi.fname LIKE '$like'
        OR pi.lname LIKE '$like'
        OR CONCAT(COALESCE(pi.fname,''),' ',COALESCE(pi.lname,'')) LIKE '$like'
        OR pp.payment_method LIKE '$like'
        OR pp.payment_id LIKE '$like'
      )"
    : "";

$payments_sql = "
    SELECT
        pp.payment_id, pp.amount, pp.payment_method, pp.paid_at, pp.remarks,
        br.billing_id, br.patient_id, br.transaction_id, br.billing_date,
        COALESCE(pi.fname,'') AS fname,
        COALESCE(pi.mname,'') AS mname,
        COALESCE(pi.lname,'') AS lname
    FROM paymongo_payments pp
    INNER JOIN billing_records br ON pp.billing_id = br.billing_id AND br.status = 'Paid'
    LEFT JOIN patientinfo pi ON pi.patient_id = br.patient_id
    WHERE 1=1 $search_clause_pay
    ORDER BY pp.paid_at DESC
    LIMIT $limit OFFSET $offset
";
$pay_res  = $conn->query($payments_sql);
$payments = $pay_res ? $pay_res->fetch_all(MYSQLI_ASSOC) : [];

/* ================================
   FETCH RECEIPTS (Paid billing only)
================================ */
$search_clause_rec = $search
    ? "AND (
        pi.fname LIKE '$like'
        OR pi.lname LIKE '$like'
        OR CONCAT(COALESCE(pi.fname,''),' ',COALESCE(pi.lname,'')) LIKE '$like'
        OR pr.payment_method LIKE '$like'
        OR pr.receipt_id LIKE '$like'
      )"
    : "";

$receipts_sql = "
    SELECT
        pr.receipt_id, pr.status, pr.payment_method,
        pr.created_at AS receipt_created,
        br.billing_id, br.patient_id, br.billing_date,
        br.grand_total, br.insurance_covered, br.transaction_id,
        COALESCE(pi.fname,'') AS fname,
        COALESCE(pi.mname,'') AS mname,
        COALESCE(pi.lname,'') AS lname
    FROM billing_records br
    INNER JOIN (
        SELECT billing_id, MAX(receipt_id) AS latest_receipt_id
        FROM patient_receipt GROUP BY billing_id
    ) latest ON latest.billing_id = br.billing_id
    INNER JOIN patient_receipt pr ON pr.receipt_id = latest.latest_receipt_id
    LEFT JOIN patientinfo pi ON pi.patient_id = br.patient_id
    WHERE br.status = 'Paid' $search_clause_rec
    ORDER BY br.billing_date DESC
    LIMIT $limit OFFSET $offset
";
$rec_res  = $conn->query($receipts_sql);
$receipts = $rec_res ? $rec_res->fetch_all(MYSQLI_ASSOC) : [];

/* ================================
   FETCH EXPENSES from expense_logs
================================ */
$search_clause_exp = $search
    ? "AND (
        el.expense_name LIKE '$like'
        OR el.category   LIKE '$like'
        OR el.description LIKE '$like'
        OR el.recorded_by LIKE '$like'
        OR el.created_by  LIKE '$like'
      )"
    : "";

$expenses_sql = "
    SELECT
        el.expense_id, el.expense_name, el.category,
        el.description, el.amount, el.expense_date,
        el.recorded_by, el.notes, el.created_by
    FROM expense_logs el
    WHERE 1=1 $search_clause_exp
    ORDER BY el.expense_date DESC, el.expense_id DESC
    LIMIT $limit OFFSET $offset
";
$exp_res  = $conn->query($expenses_sql);
$expenses = $exp_res ? $exp_res->fetch_all(MYSQLI_ASSOC) : [];

/* ================================
   TOTALS / STATS
================================ */
$total_payment_amount = array_sum(array_column($payments, 'amount'));
$total_receipt_amount = array_sum(array_column($receipts, 'grand_total'));
$total_expense_amount = array_sum(array_column($expenses, 'amount'));
$total_entries        = count($payments) + count($receipts) + count($expenses);

/* ================================
   ACCURATE PAGINATION (full row counts)
================================ */
$cnt_pay = (int)($conn->query("
    SELECT COUNT(*) FROM paymongo_payments pp
    INNER JOIN billing_records br ON pp.billing_id=br.billing_id AND br.status='Paid'
    LEFT JOIN patientinfo pi ON pi.patient_id=br.patient_id
    WHERE 1=1 $search_clause_pay
")->fetch_row()[0] ?? 0);

$cnt_rec = (int)($conn->query("
    SELECT COUNT(*) FROM billing_records br
    INNER JOIN (SELECT billing_id,MAX(receipt_id) AS lid FROM patient_receipt GROUP BY billing_id) lx ON lx.billing_id=br.billing_id
    INNER JOIN patient_receipt pr ON pr.receipt_id=lx.lid
    LEFT JOIN patientinfo pi ON pi.patient_id=br.patient_id
    WHERE br.status='Paid' $search_clause_rec
")->fetch_row()[0] ?? 0);

$cnt_exp = (int)($conn->query("
    SELECT COUNT(*) FROM expense_logs el WHERE 1=1 $search_clause_exp
")->fetch_row()[0] ?? 0);

$total_rows  = $cnt_pay + $cnt_rec + $cnt_exp;
$total_pages = max(1, ceil($total_rows / $limit));

/* ================================
   HELPER
================================ */
function getPatientName(string $f, string $m, string $l, $pid = null): string {
    $p = array_filter([trim($f), trim($m), trim($l)]);
    $n = implode(' ', $p);
    return $n !== '' ? $n : ($pid ? "Patient #$pid" : 'Unknown Patient');
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
:root {
  --sidebar-w:250px; --sidebar-w-sm:200px;
  --navy:#0b1d3a; --ink:#1e293b; --ink-l:#64748b;
  --border:#e2e8f0; --surf:#f1f5f9; --card:#fff;
  --accent:#2563eb; --green:#059669; --red:#dc2626; --orange:#ea580c;
  --radius:14px; --shadow:0 2px 20px rgba(11,29,58,.08);
  --fh:'DM Serif Display',serif; --fb:'DM Sans',sans-serif; --tr:.3s ease-in-out;
}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
body{font-family:var(--fb);background:var(--surf);color:var(--ink);margin:0}

.cw{margin-left:var(--sidebar-w);padding:60px 28px 60px;transition:margin-left var(--tr)}
.cw.sidebar-collapsed{margin-left:0}

/* Page header */
.page-head{display:flex;align-items:center;gap:14px;margin-bottom:24px;flex-wrap:wrap}
.ph-icon{width:52px;height:52px;background:linear-gradient(135deg,var(--navy),var(--accent));border-radius:14px;display:flex;align-items:center;justify-content:center;color:#fff;font-size:1.4rem;box-shadow:0 6px 18px rgba(11,29,58,.2);flex-shrink:0}
.page-head h1{font-family:var(--fh);font-size:clamp(1.3rem,3vw,1.85rem);color:var(--navy);margin:0;line-height:1.1}
.page-head p{font-size:.82rem;color:var(--ink-l);margin:3px 0 0}

/* Stats */
.stats-row{display:grid;grid-template-columns:repeat(auto-fit,minmax(145px,1fr));gap:14px;margin-bottom:24px}
.stat-card{background:var(--card);border:1px solid var(--border);border-radius:var(--radius);padding:16px 18px;box-shadow:var(--shadow);display:flex;align-items:center;gap:14px}
.stat-icon{width:44px;height:44px;border-radius:11px;display:flex;align-items:center;justify-content:center;font-size:1.15rem;flex-shrink:0}
.si-blue{background:#dbeafe;color:#1d4ed8}.si-green{background:#d1fae5;color:#065f46}
.si-purple{background:#ede9fe;color:#5b21b6}.si-orange{background:#ffedd5;color:#c2410c}
.stat-num{font-size:1.2rem;font-weight:700;color:var(--navy);line-height:1}
.stat-lbl{font-size:.7rem;color:var(--ink-l);font-weight:600;text-transform:uppercase;letter-spacing:.5px;margin-top:2px}

/* Search */
.search-card{background:var(--card);border:1px solid var(--border);border-radius:var(--radius);box-shadow:var(--shadow);padding:16px 20px;margin-bottom:20px;display:flex;gap:10px;flex-wrap:wrap;align-items:center}
.sf{position:relative;flex:1 1 240px}
.sf i{position:absolute;left:13px;top:50%;transform:translateY(-50%);color:var(--ink-l);font-size:.9rem;pointer-events:none}
.si{width:100%;padding:9px 14px 9px 36px;border:1.5px solid var(--border);border-radius:9px;font-family:var(--fb);font-size:.87rem;color:var(--ink);background:var(--surf);outline:none;transition:border-color .2s,box-shadow .2s}
.si:focus{border-color:var(--accent);box-shadow:0 0 0 3px rgba(37,99,235,.12);background:var(--card)}
.btn-srch{padding:9px 20px;background:var(--accent);color:#fff;border:none;border-radius:9px;font-family:var(--fb);font-size:.87rem;font-weight:700;cursor:pointer;display:inline-flex;align-items:center;gap:6px;transition:background .15s;white-space:nowrap}
.btn-srch:hover{background:#1d4ed8}
.btn-rst{padding:9px 16px;background:var(--card);color:var(--ink-l);border:1.5px solid var(--border);border-radius:9px;font-family:var(--fb);font-size:.87rem;font-weight:600;cursor:pointer;text-decoration:none;display:inline-flex;align-items:center;gap:6px;transition:all .15s;white-space:nowrap}
.btn-rst:hover{border-color:var(--accent);color:var(--accent)}

/* Section label */
.sec-lbl{display:flex;align-items:center;gap:10px;font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.7px;color:var(--ink-l);margin:20px 0 10px}
.sec-lbl::after{content:'';flex:1;height:1px;background:var(--border)}

/* Table card */
.tbl-card{background:var(--card);border:1px solid var(--border);border-radius:var(--radius);box-shadow:var(--shadow);overflow:hidden;margin-bottom:24px}
.tbl-hdr{background:var(--navy);padding:13px 20px;color:rgba(255,255,255,.8);font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.7px;display:flex;align-items:center;gap:8px}
.tbl-hdr.exp-hdr{background:linear-gradient(135deg,#7c2d12,#c2410c)}

/* Table */
.jt{width:100%;border-collapse:collapse;font-size:.87rem}
.jt thead th{background:#f8fafc;color:var(--ink-l);font-size:.69rem;font-weight:700;text-transform:uppercase;letter-spacing:.6px;padding:11px 16px;border-bottom:2px solid var(--border);text-align:left;white-space:nowrap}
.jt thead th.ra{text-align:right}.jt thead th.ca{text-align:center}
.jt tbody tr{border-bottom:1px solid var(--border);transition:background .12s}
.jt tbody tr:last-child{border-bottom:none}
.jt tbody tr:hover{background:#f7faff}
.jt tbody td{padding:13px 16px;vertical-align:middle}
.jt tbody td.ra{text-align:right}.jt tbody td.ca{text-align:center}

/* Cells */
.pat-cell{display:flex;align-items:center;gap:10px}
.avatar{width:34px;height:34px;border-radius:50%;flex-shrink:0;background:linear-gradient(135deg,var(--navy),var(--accent));color:#fff;font-size:.76rem;font-weight:700;display:flex;align-items:center;justify-content:center}
.avatar.exp-av{background:linear-gradient(135deg,#7c2d12,var(--orange))}
.pat-name{font-weight:600;color:var(--navy);font-size:.88rem}
.pat-sub{font-size:.73rem;color:var(--ink-l);margin-top:1px;max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}

.dr-pill{display:inline-flex;align-items:center;gap:5px;padding:4px 10px;border-radius:999px;background:#d1fae5;color:#065f46;font-size:.74rem;font-weight:700;white-space:nowrap}
.cr-pill{display:inline-flex;align-items:center;gap:5px;padding:4px 10px;border-radius:999px;background:#fee2e2;color:#991b1b;font-size:.74rem;font-weight:700;white-space:nowrap}
.exp-dr-pill{display:inline-flex;align-items:center;gap:5px;padding:4px 10px;border-radius:999px;background:#ffedd5;color:#7c2d12;font-size:.74rem;font-weight:700;white-space:nowrap}
.exp-cr-pill{display:inline-flex;align-items:center;gap:5px;padding:4px 10px;border-radius:999px;background:#fef3c7;color:#92400e;font-size:.74rem;font-weight:700;white-space:nowrap}

.amt{font-weight:700;color:var(--navy);font-size:.92rem}
.amt.exp-amt{color:var(--orange)}
.dv{font-size:.82rem;color:var(--ink);font-weight:500}
.dt{font-size:.72rem;color:var(--ink-l);opacity:.7}
.mono-ref{font-family:'Courier New',monospace;font-size:.78rem;color:var(--ink-l);background:#f1f5f9;border-radius:6px;padding:2px 8px;display:inline-block;max-width:130px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.method-tag{display:inline-flex;align-items:center;gap:4px;background:#f1f5f9;border:1px solid var(--border);border-radius:6px;padding:2px 8px;font-size:.75rem;font-weight:600;color:var(--ink-l)}
.cat-tag{display:inline-flex;align-items:center;gap:4px;background:#ffedd5;border:1px solid #fed7aa;border-radius:6px;padding:2px 8px;font-size:.72rem;font-weight:600;color:#c2410c}
.badge-posted{background:#d1fae5;color:#065f46;border-radius:999px;padding:3px 10px;font-size:.71rem;font-weight:700}
.badge-draft{background:#fef3c7;color:#92400e;border-radius:999px;padding:3px 10px;font-size:.71rem;font-weight:700}

.btn-view{display:inline-flex;align-items:center;gap:5px;padding:5px 14px;border-radius:8px;background:#eff6ff;color:var(--accent);border:1.5px solid #bfdbfe;font-family:var(--fb);font-size:.78rem;font-weight:700;text-decoration:none;transition:all .15s}
.btn-view:hover{background:var(--accent);color:#fff;border-color:var(--accent)}
.btn-view.exp-btn{background:#fff7ed;color:var(--orange);border-color:#fed7aa}
.btn-view.exp-btn:hover{background:var(--orange);color:#fff;border-color:var(--orange)}
.empty-row td{text-align:center;padding:40px 16px;color:var(--ink-l)}
.empty-row i{font-size:1.8rem;display:block;margin-bottom:8px;opacity:.3}

/* Pagination */
.pag-wrap{display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;padding:14px 20px;border-top:1px solid var(--border);background:#f8fafc}
.pag-info{font-size:.82rem;color:var(--ink-l)}
.pag-btns{display:flex;gap:4px}
.pb{width:34px;height:34px;display:flex;align-items:center;justify-content:center;border:1.5px solid var(--border);border-radius:8px;background:var(--card);color:var(--ink-l);font-size:.82rem;font-weight:600;text-decoration:none;transition:all .15s}
.pb:hover{border-color:var(--accent);color:var(--accent);background:#eff6ff}
.pb.active{background:var(--navy);color:#fff;border-color:var(--navy)}
.pb.off{opacity:.4;pointer-events:none}
.pb.wide{width:auto;padding:0 12px}

/* Source tags */
.src{display:inline-block;padding:2px 8px;border-radius:999px;font-size:.68rem;font-weight:700}
.src-pay{background:#ede9fe;color:#5b21b6}
.src-rec{background:#dbeafe;color:#1d4ed8}
.src-exp{background:#ffedd5;color:#7c2d12}

/* Mobile cards */
.mob-cards{display:none}
.mc{background:var(--card);border:1px solid var(--border);border-radius:var(--radius);box-shadow:var(--shadow);padding:16px;margin-bottom:10px}
.mc.exp-mc{border-left:3px solid var(--orange)}
.mc-head{display:flex;justify-content:space-between;align-items:flex-start;gap:10px;margin-bottom:12px}
.mc-row{display:flex;justify-content:space-between;align-items:center;padding:6px 0;border-bottom:1px solid var(--border);font-size:.82rem;gap:8px}
.mc-row:last-of-type{border-bottom:none}
.ml{color:var(--ink-l);font-weight:600;font-size:.69rem;text-transform:uppercase;letter-spacing:.5px;flex-shrink:0}
.mv{font-weight:500;color:var(--ink);text-align:right}
.mc-actions{margin-top:12px}
.mc-actions .btn-view{width:100%;justify-content:center}

/* Responsive */
@media(max-width:768px){
  .cw{margin-left:var(--sidebar-w-sm);padding:60px 14px 50px}
  .cw.sidebar-collapsed{margin-left:0}
  .tbl-card{display:none}.mob-cards{display:block}
  .stats-row{grid-template-columns:repeat(2,1fr)}
}
@media(max-width:480px){
  .cw{margin-left:0!important;padding:56px 10px 40px}
  .search-card{flex-direction:column}
  .sf,.btn-srch,.btn-rst{width:100%;justify-content:center}
}
@supports(padding:env(safe-area-inset-bottom)){.cw{padding-bottom:calc(60px + env(safe-area-inset-bottom))}}
</style>
</head>
<body>
<?php include 'billing_sidebar.php'; ?>
<div class="cw" id="mainCw">

  <!-- Header -->
  <div class="page-head">
    <div class="ph-icon"><i class="bi bi-journal-bookmark-fill"></i></div>
    <div>
      <h1>Journal Entry</h1>
      <p>Payments, receipts, and expense transactions</p>
    </div>
    <div style="margin-left:auto">
      <span style="font-size:.82rem;color:var(--ink-l)">Page <?= $page ?> of <?= $total_pages ?></span>
    </div>
  </div>

  <!-- Stats -->
  <div class="stats-row">
    <div class="stat-card">
      <div class="stat-icon si-blue"><i class="bi bi-receipt"></i></div>
      <div><div class="stat-num"><?= $total_entries ?></div><div class="stat-lbl">Entries (page)</div></div>
    </div>
    <div class="stat-card">
      <div class="stat-icon si-green"><i class="bi bi-currency-exchange"></i></div>
      <div><div class="stat-num">₱<?= number_format($total_payment_amount,0) ?></div><div class="stat-lbl">Payments</div></div>
    </div>
    <div class="stat-card">
      <div class="stat-icon si-purple"><i class="bi bi-file-earmark-text"></i></div>
      <div><div class="stat-num">₱<?= number_format($total_receipt_amount,0) ?></div><div class="stat-lbl">Receipts</div></div>
    </div>
    <div class="stat-card">
      <div class="stat-icon si-orange"><i class="bi bi-bag-x"></i></div>
      <div><div class="stat-num">₱<?= number_format($total_expense_amount,0) ?></div><div class="stat-lbl">Expenses</div></div>
    </div>
  </div>

  <!-- Search -->
  <form method="GET" class="search-card" action="journal_entry.php">
    <div class="sf">
      <i class="bi bi-search"></i>
      <input type="text" name="search" class="si"
             placeholder="Search patient, method, expense name, category…"
             value="<?= htmlspecialchars($search) ?>">
    </div>
    <button type="submit" class="btn-srch"><i class="bi bi-search"></i> Search</button>
    <a href="journal_entry.php" class="btn-rst"><i class="bi bi-x-circle"></i> Reset</a>
    <?php if ($search): ?>
      <span style="font-size:.82rem;color:var(--ink-l);align-self:center">
        Results for "<strong><?= htmlspecialchars($search) ?></strong>"
      </span>
    <?php endif; ?>
  </form>

  <?php if (empty($payments) && empty($receipts) && empty($expenses)): ?>
  <!-- Empty state -->
  <div class="tbl-card">
    <div style="text-align:center;padding:56px 16px;color:var(--ink-l)">
      <i class="bi bi-inbox" style="font-size:2.2rem;display:block;margin-bottom:10px;opacity:.3"></i>
      <?= $search ? 'No entries match your search.' : 'No journal entries found.' ?>
    </div>
  </div>
  <?php endif; ?>

  <!-- ══════ PAYMENTS ══════ -->
  <?php if (!empty($payments)): ?>
  <div class="sec-lbl">
    <i class="bi bi-credit-card-2-front"></i> Payment Transactions
    <span style="background:#ede9fe;color:#5b21b6;padding:2px 10px;border-radius:999px;font-size:.7rem;margin-left:4px">
      <?= count($payments) ?> record<?= count($payments)!==1?'s':'' ?>
    </span>
  </div>

  <!-- Desktop -->
  <div class="tbl-card">
    <div class="tbl-hdr"><i class="bi bi-table"></i> Payments Ledger</div>
    <div style="overflow-x:auto">
      <table class="jt">
        <thead><tr>
          <th>Date &amp; Time</th><th>Patient</th><th>Debit</th>
          <th>Credit</th><th class="ra">Amount</th><th>Reference</th><th class="ca">Action</th>
        </tr></thead>
        <tbody>
        <?php foreach ($payments as $p):
          $fn = getPatientName($p['fname'],$p['mname'],$p['lname'],$p['patient_id']);
          $av = strtoupper(substr(trim($p['fname']?:$fn),0,1));
        ?>
          <tr>
            <td><div class="dv"><?= date('M d, Y',strtotime($p['billing_date'])) ?></div>
                <div class="dt"><?= date('h:i A',strtotime($p['billing_date'])) ?></div></td>
            <td><div class="pat-cell"><div class="avatar"><?= $av ?></div>
              <div><div class="pat-name"><?= htmlspecialchars($fn) ?></div>
                   <div class="pat-sub"><span class="method-tag"><i class="bi bi-credit-card"></i> <?= htmlspecialchars($p['payment_method']) ?></span></div>
              </div></div></td>
            <td><span class="dr-pill"><i class="bi bi-arrow-down-circle"></i> Cash / Bank</span></td>
            <td><span class="cr-pill"><i class="bi bi-arrow-up-circle"></i> Receivable</span></td>
            <td class="ra"><span class="amt">₱<?= number_format($p['amount'],2) ?></span></td>
            <td><span class="mono-ref" title="<?= htmlspecialchars($p['payment_id']) ?>"><?= htmlspecialchars($p['payment_id']) ?></span></td>
            <td class="ca"><a href="journal_entry_line.php?payment_id=<?= urlencode($p['payment_id']) ?>" class="btn-view"><i class="bi bi-eye"></i> View</a></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Mobile -->
  <div class="mob-cards">
    <?php foreach ($payments as $p):
      $fn = getPatientName($p['fname'],$p['mname'],$p['lname'],$p['patient_id']);
      $av = strtoupper(substr(trim($p['fname']?:$fn),0,1));
    ?>
    <div class="mc">
      <div class="mc-head">
        <div class="pat-cell"><div class="avatar"><?= $av ?></div>
          <div><div class="pat-name"><?= htmlspecialchars($fn) ?></div><span class="src src-pay">Payment</span></div>
        </div>
        <span class="amt">₱<?= number_format($p['amount'],2) ?></span>
      </div>
      <div class="mc-row"><span class="ml">Date</span><span class="mv"><?= date('M d, Y h:i A',strtotime($p['billing_date'])) ?></span></div>
      <div class="mc-row"><span class="ml">Debit</span><span class="mv"><span class="dr-pill">Cash / Bank</span></span></div>
      <div class="mc-row"><span class="ml">Credit</span><span class="mv"><span class="cr-pill">Receivable</span></span></div>
      <div class="mc-row"><span class="ml">Method</span><span class="mv"><?= htmlspecialchars($p['payment_method']) ?></span></div>
      <div class="mc-actions"><a href="journal_entry_line.php?payment_id=<?= urlencode($p['payment_id']) ?>" class="btn-view"><i class="bi bi-eye"></i> View Entry</a></div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <!-- ══════ RECEIPTS ══════ -->
  <?php if (!empty($receipts)): ?>
  <div class="sec-lbl">
    <i class="bi bi-file-earmark-text"></i> Billing Receipts
    <span style="background:#dbeafe;color:#1d4ed8;padding:2px 10px;border-radius:999px;font-size:.7rem;margin-left:4px">
      <?= count($receipts) ?> record<?= count($receipts)!==1?'s':'' ?>
    </span>
  </div>

  <!-- Desktop -->
  <div class="tbl-card">
    <div class="tbl-hdr"><i class="bi bi-table"></i> Receipts Ledger</div>
    <div style="overflow-x:auto">
      <table class="jt">
        <thead><tr>
          <th>Date &amp; Time</th><th>Patient</th><th>Debit</th>
          <th>Credit</th><th class="ra">Total</th><th>Status</th><th>Receipt ID</th><th class="ca">Action</th>
        </tr></thead>
        <tbody>
        <?php foreach ($receipts as $r):
          $fn = getPatientName($r['fname'],$r['mname'],$r['lname'],$r['patient_id']);
          $av = strtoupper(substr(trim($r['fname']?:$fn),0,1));
          $posted = strtolower($r['status'])==='posted';
        ?>
          <tr>
            <td><div class="dv"><?= date('M d, Y',strtotime($r['billing_date'])) ?></div>
                <div class="dt"><?= date('h:i A',strtotime($r['billing_date'])) ?></div></td>
            <td><div class="pat-cell"><div class="avatar"><?= $av ?></div>
              <div><div class="pat-name"><?= htmlspecialchars($fn) ?></div>
                   <div class="pat-sub"><span class="method-tag"><i class="bi bi-credit-card"></i> <?= htmlspecialchars($r['payment_method']?:'Unpaid') ?></span></div>
              </div></div></td>
            <td><span class="dr-pill"><i class="bi bi-arrow-down-circle"></i> Cash / Bank</span></td>
            <td><span class="cr-pill"><i class="bi bi-arrow-up-circle"></i> Receivable</span></td>
            <td class="ra"><span class="amt">₱<?= number_format($r['grand_total'],2) ?></span></td>
            <td><span class="<?= $posted?'badge-posted':'badge-draft' ?>"><?= $posted?'Posted':'Draft' ?></span></td>
            <td><span class="mono-ref">#<?= $r['receipt_id'] ?></span></td>
            <td class="ca"><a href="journal_entry_line.php?receipt_id=<?= urlencode($r['receipt_id']) ?>" class="btn-view"><i class="bi bi-eye"></i> View</a></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Mobile -->
  <div class="mob-cards">
    <?php foreach ($receipts as $r):
      $fn = getPatientName($r['fname'],$r['mname'],$r['lname'],$r['patient_id']);
      $av = strtoupper(substr(trim($r['fname']?:$fn),0,1));
      $posted = strtolower($r['status'])==='posted';
    ?>
    <div class="mc">
      <div class="mc-head">
        <div class="pat-cell"><div class="avatar"><?= $av ?></div>
          <div><div class="pat-name"><?= htmlspecialchars($fn) ?></div><span class="src src-rec">Receipt</span></div>
        </div>
        <span class="amt">₱<?= number_format($r['grand_total'],2) ?></span>
      </div>
      <div class="mc-row"><span class="ml">Date</span><span class="mv"><?= date('M d, Y',strtotime($r['billing_date'])) ?></span></div>
      <div class="mc-row"><span class="ml">Status</span><span class="mv"><span class="<?= $posted?'badge-posted':'badge-draft' ?>"><?= $posted?'Posted':'Draft' ?></span></span></div>
      <div class="mc-row"><span class="ml">Receipt ID</span><span class="mv">#<?= $r['receipt_id'] ?></span></div>
      <div class="mc-actions"><a href="journal_entry_line.php?receipt_id=<?= urlencode($r['receipt_id']) ?>" class="btn-view"><i class="bi bi-eye"></i> View Entry</a></div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <!-- ══════ EXPENSES ══════ -->
  <?php if (!empty($expenses)): ?>
  <div class="sec-lbl">
    <i class="bi bi-bag-x"></i> Expense Transactions
    <span style="background:#ffedd5;color:#7c2d12;padding:2px 10px;border-radius:999px;font-size:.7rem;margin-left:4px">
      <?= count($expenses) ?> record<?= count($expenses)!==1?'s':'' ?>
    </span>
  </div>

  <!-- Desktop -->
  <div class="tbl-card">
    <div class="tbl-hdr exp-hdr"><i class="bi bi-table"></i> Expenses Ledger</div>
    <div style="overflow-x:auto">
      <table class="jt">
        <thead><tr>
          <th>Date</th><th>Expense</th><th>Debit</th><th>Credit</th>
          <th class="ra">Amount</th><th>Category</th><th>Recorded By</th><th class="ca">Action</th>
        </tr></thead>
        <tbody>
        <?php foreach ($expenses as $e):
          $av = strtoupper(substr($e['expense_name'],0,1));
        ?>
          <tr>
            <td><div class="dv"><?= date('M d, Y',strtotime($e['expense_date'])) ?></div></td>
            <td><div class="pat-cell"><div class="avatar exp-av"><?= $av ?></div>
              <div><div class="pat-name"><?= htmlspecialchars($e['expense_name']) ?></div>
                   <?php if (!empty($e['description'])): ?>
                   <div class="pat-sub"><?= htmlspecialchars($e['description']) ?></div>
                   <?php endif; ?>
              </div></div></td>
            <td><span class="exp-dr-pill"><i class="bi bi-arrow-down-circle"></i> Expense A/C</span></td>
            <td><span class="exp-cr-pill"><i class="bi bi-arrow-up-circle"></i> Cash / Bank</span></td>
            <td class="ra"><span class="amt exp-amt">₱<?= number_format($e['amount'],2) ?></span></td>
            <td>
              <?php if (!empty($e['category'])): ?>
                <span class="cat-tag"><i class="bi bi-tag"></i> <?= htmlspecialchars($e['category']) ?></span>
              <?php else: ?>
                <span style="color:var(--ink-l);font-size:.8rem">—</span>
              <?php endif; ?>
            </td>
            <td style="font-size:.82rem;color:var(--ink-l)"><?= htmlspecialchars($e['recorded_by']?:$e['created_by']?:'—') ?></td>
            <td class="ca"><a href="journal_entry_line.php?expense_id=<?= urlencode($e['expense_id']) ?>" class="btn-view exp-btn"><i class="bi bi-eye"></i> View</a></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Mobile -->
  <div class="mob-cards">
    <?php foreach ($expenses as $e):
      $av = strtoupper(substr($e['expense_name'],0,1));
    ?>
    <div class="mc exp-mc">
      <div class="mc-head">
        <div class="pat-cell"><div class="avatar exp-av"><?= $av ?></div>
          <div><div class="pat-name"><?= htmlspecialchars($e['expense_name']) ?></div><span class="src src-exp">Expense</span></div>
        </div>
        <span class="amt exp-amt">₱<?= number_format($e['amount'],2) ?></span>
      </div>
      <div class="mc-row"><span class="ml">Date</span><span class="mv"><?= date('M d, Y',strtotime($e['expense_date'])) ?></span></div>
      <div class="mc-row"><span class="ml">Debit</span><span class="mv"><span class="exp-dr-pill">Expense A/C</span></span></div>
      <div class="mc-row"><span class="ml">Credit</span><span class="mv"><span class="exp-cr-pill">Cash / Bank</span></span></div>
      <?php if (!empty($e['category'])): ?>
      <div class="mc-row"><span class="ml">Category</span><span class="mv"><span class="cat-tag"><?= htmlspecialchars($e['category']) ?></span></span></div>
      <?php endif; ?>
      <div class="mc-row"><span class="ml">Recorded By</span><span class="mv"><?= htmlspecialchars($e['recorded_by']?:$e['created_by']?:'—') ?></span></div>
      <div class="mc-actions"><a href="journal_entry_line.php?expense_id=<?= urlencode($e['expense_id']) ?>" class="btn-view exp-btn"><i class="bi bi-eye"></i> View Entry</a></div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <!-- Pagination -->
  <?php if ($total_pages > 1): ?>
  <div class="tbl-card" style="margin-top:0">
    <div class="pag-wrap">
      <span class="pag-info">Page <?= $page ?> of <?= $total_pages ?> &nbsp;·&nbsp; <?= number_format($total_rows) ?> total records</span>
      <div class="pag-btns">
        <a href="?page=<?= $page-1 ?>&search=<?= urlencode($search) ?>" class="pb wide <?= $page<=1?'off':'' ?>">
          <i class="bi bi-chevron-left"></i> Prev
        </a>
        <?php
          $sp=max(1,$page-2); $ep=min($total_pages,$page+2);
          if($sp>1) echo '<span class="pb off">…</span>';
          for($i=$sp;$i<=$ep;$i++):
        ?><a href="?page=<?= $i ?>&search=<?= urlencode($search) ?>" class="pb <?= $i===$page?'active':'' ?>"><?= $i ?></a><?php
          endfor;
          if($ep<$total_pages) echo '<span class="pb off">…</span>';
        ?>
        <a href="?page=<?= $page+1 ?>&search=<?= urlencode($search) ?>" class="pb wide <?= $page>=$total_pages?'off':'' ?>">
          Next <i class="bi bi-chevron-right"></i>
        </a>
      </div>
    </div>
  </div>
  <?php endif; ?>

</div>
<script>
(function(){
  const sb=document.getElementById('mySidebar');
  const cw=document.getElementById('mainCw');
  if(!sb||!cw)return;
  function sync(){cw.classList.toggle('sidebar-collapsed',sb.classList.contains('closed'));}
  new MutationObserver(sync).observe(sb,{attributes:true,attributeFilter:['class']});
  document.getElementById('sidebarToggle')?.addEventListener('click',()=>requestAnimationFrame(sync));
  sync();
})();
</script>
</body>
</html>