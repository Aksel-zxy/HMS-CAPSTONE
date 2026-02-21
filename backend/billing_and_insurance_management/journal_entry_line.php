<?php
session_start();
include '../../SQL/config.php';

/* =========================
   Determine which entry to load
   Supports: ?entry_id=  ?payment_id=  ?receipt_id=  ?expense_id=
========================= */
$entry_id   = isset($_GET['entry_id'])   ? intval($_GET['entry_id'])   : 0;
$payment_id = $_GET['payment_id']        ?? null;
$receipt_id = isset($_GET['receipt_id']) ? intval($_GET['receipt_id']) : null;
$expense_id = isset($_GET['expense_id']) ? intval($_GET['expense_id']) : null;

$payment      = null;
$receipt      = null;
$expense      = null;
$entry        = null;
$lines        = [];
$total_debit  = 0;
$total_credit = 0;
$entry_date   = null;
$page_title   = 'Journal Entry Details';
$entry_type   = 'default'; // 'payment' | 'receipt' | 'expense' | 'entry'

/* ══════════════════════════════════
   LOAD BY entry_id
══════════════════════════════════ */
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

    foreach ($lines as $l) {
        $total_debit  += floatval($l['debit']  ?? 0);
        $total_credit += floatval($l['credit'] ?? 0);
    }
    $entry_date = $entry['entry_date'] ?? null;
    $page_title = 'Journal Entry #' . $entry_id;
    $entry_type = 'entry';

/* ══════════════════════════════════
   LOAD BY payment_id
══════════════════════════════════ */
} elseif ($payment_id) {
    $stmt = $conn->prepare("
        SELECT pp.*,
               br.payment_date, br.billing_date,
               COALESCE(pi.fname,'') AS fname,
               COALESCE(pi.mname,'') AS mname,
               COALESCE(pi.lname,'') AS lname
        FROM paymongo_payments pp
        LEFT JOIN billing_records br ON pp.billing_id = br.billing_id
        LEFT JOIN patientinfo pi      ON br.patient_id = pi.patient_id
        WHERE pp.payment_id = ?
    ");
    $stmt->bind_param("s", $payment_id);
    $stmt->execute();
    $payment = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$payment) { header("Location: journal_entry.php"); exit; }

    $parts     = array_filter([trim($payment['fname']), trim($payment['mname']), trim($payment['lname'])]);
    $full_name = implode(' ', $parts) ?: 'Unknown Patient';
    $amount    = floatval($payment['amount']);
    $entry_date = $payment['payment_date'] ?? $payment['billing_date'] ?? $payment['paid_at'] ?? null;
    $desc_pay   = "Payment received from {$full_name}\nMethod: {$payment['payment_method']}\nRemarks: " . ($payment['remarks'] ?? '—');

    $lines[] = ['account_name' => 'Cash / Bank',        'debit' => $amount, 'credit' => 0,      'description' => $desc_pay];
    $lines[] = ['account_name' => 'Patient Receivable', 'debit' => 0,       'credit' => $amount,'description' => 'Settlement of patient account'];
    $total_debit  = $amount;
    $total_credit = $amount;
    $page_title   = 'Payment Entry — ' . $payment_id;
    $entry_type   = 'payment';

/* ══════════════════════════════════
   LOAD BY receipt_id
══════════════════════════════════ */
} elseif ($receipt_id) {
    $stmt = $conn->prepare("
        SELECT pr.*,
               br.grand_total, br.billing_date, br.payment_date,
               br.transaction_id, br.insurance_covered,
               COALESCE(pi.fname,'') AS fname,
               COALESCE(pi.mname,'') AS mname,
               COALESCE(pi.lname,'') AS lname
        FROM patient_receipt pr
        LEFT JOIN billing_records br ON pr.billing_id = br.billing_id
        LEFT JOIN patientinfo pi      ON pi.patient_id = br.patient_id
        WHERE pr.receipt_id = ?
    ");
    $stmt->bind_param("i", $receipt_id);
    $stmt->execute();
    $receipt = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$receipt) { header("Location: journal_entry.php"); exit; }

    $parts     = array_filter([trim($receipt['fname']), trim($receipt['mname']), trim($receipt['lname'])]);
    $full_name = implode(' ', $parts) ?: 'Unknown Patient';
    $amount    = floatval($receipt['grand_total'] ?? 0);
    $ins       = floatval($receipt['insurance_covered'] ?? 0);
    $method    = $receipt['payment_method'] ?? 'N/A';
    $entry_date = $receipt['payment_date'] ?? $receipt['billing_date'] ?? $receipt['created_at'] ?? null;

    $lines[] = ['account_name' => 'Cash / Bank',        'debit' => $amount, 'credit' => 0,      'description' => "Receipt for {$full_name}\nMethod: {$method}"];
    $lines[] = ['account_name' => 'Patient Receivable', 'debit' => 0,       'credit' => $amount,'description' => 'Settlement of billing record'];
    if ($ins > 0) {
        $lines[] = ['account_name' => 'Insurance Receivable', 'debit' => 0, 'credit' => $ins,   'description' => 'Insurance coverage applied'];
    }
    $total_debit  = $amount;
    $total_credit = $amount + $ins;
    $page_title   = 'Receipt Entry #' . $receipt_id;
    $entry_type   = 'receipt';

/* ══════════════════════════════════
   LOAD BY expense_id  ← NEW
══════════════════════════════════ */
} elseif ($expense_id) {
    $stmt = $conn->prepare("
        SELECT expense_id, expense_name, category, description,
               amount, expense_date, recorded_by, notes, created_by
        FROM expense_logs
        WHERE expense_id = ?
    ");
    $stmt->bind_param("i", $expense_id);
    $stmt->execute();
    $expense = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$expense) { header("Location: journal_entry.php"); exit; }

    $amount     = floatval($expense['amount']);
    $entry_date = $expense['expense_date'] ?? null;
    $cat        = $expense['category']    ?: 'General';
    $rec_by     = $expense['recorded_by'] ?: $expense['created_by'] ?: 'System';
    $note       = $expense['notes'] ? "\nNotes: " . $expense['notes'] : '';
    $desc_exp   = "Expense: {$expense['expense_name']}\nCategory: {$cat}\nRecorded by: {$rec_by}" . $note;
    if (!empty($expense['description'])) {
        $desc_exp .= "\nDetails: " . $expense['description'];
    }

    /* Double-entry for an expense:
       DEBIT  Expense Account (increases expense)
       CREDIT Cash / Bank     (decreases asset)       */
    $lines[] = [
        'account_name' => $cat . ' Expense',
        'debit'        => $amount,
        'credit'       => 0,
        'description'  => $desc_exp,
    ];
    $lines[] = [
        'account_name' => 'Cash / Bank',
        'debit'        => 0,
        'credit'       => $amount,
        'description'  => "Payment for {$expense['expense_name']}",
    ];

    $total_debit  = $amount;
    $total_credit = $amount;
    $page_title   = 'Expense Entry #' . $expense_id;
    $entry_type   = 'expense';

} else {
    header("Location: journal_entry.php");
    exit;
}

$is_balanced = abs($total_debit - $total_credit) < 0.01;

/* ── Header colour per type ── */
$hdr_gradient = match($entry_type) {
    'expense' => 'linear-gradient(135deg, #7c2d12, #c2410c)',
    'receipt' => 'linear-gradient(135deg, #1e3a8a, #2563eb)',
    default   => 'linear-gradient(135deg, #0b1d3a, #1e3a5f)',
};
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
:root {
  --sidebar-w:250px; --sidebar-w-sm:200px;
  --navy:#0b1d3a; --ink:#1e293b; --ink-l:#64748b;
  --border:#e2e8f0; --surf:#f1f5f9; --card:#fff;
  --accent:#2563eb; --green:#059669; --red:#dc2626; --orange:#ea580c;
  --radius:14px;
  --shadow:0 2px 20px rgba(11,29,58,.08);
  --shadow-lg:0 8px 40px rgba(11,29,58,.14);
  --fh:'DM Serif Display',serif; --fb:'DM Sans',sans-serif; --tr:.3s ease-in-out;
}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
body{font-family:var(--fb);background:var(--surf);color:var(--ink);margin:0}

.cw{margin-left:var(--sidebar-w);padding:60px 28px 60px;transition:margin-left var(--tr);max-width:calc(1000px + var(--sidebar-w))}
.cw.sidebar-collapsed{margin-left:0}

/* Page header */
.page-head{display:flex;align-items:center;gap:14px;margin-bottom:24px;flex-wrap:wrap}
.ph-icon{width:52px;height:52px;border-radius:14px;display:flex;align-items:center;justify-content:center;color:#fff;font-size:1.4rem;box-shadow:0 6px 18px rgba(11,29,58,.2);flex-shrink:0}
.ph-icon.pay-icon{background:linear-gradient(135deg,var(--navy),var(--accent))}
.ph-icon.exp-icon{background:linear-gradient(135deg,#7c2d12,var(--orange))}
.ph-icon.rec-icon{background:linear-gradient(135deg,#1e3a8a,var(--accent))}
.page-head h1{font-family:var(--fh);font-size:clamp(1.2rem,2.5vw,1.7rem);color:var(--navy);margin:0;line-height:1.1}
.page-head p{font-size:.82rem;color:var(--ink-l);margin:3px 0 0}
.head-actions{margin-left:auto;display:flex;gap:8px;flex-wrap:wrap}

/* Buttons */
.btn-back{padding:9px 18px;background:var(--card);color:var(--ink-l);border:1.5px solid var(--border);border-radius:9px;font-family:var(--fb);font-size:.87rem;font-weight:600;cursor:pointer;text-decoration:none;display:inline-flex;align-items:center;gap:6px;transition:all .15s}
.btn-back:hover{border-color:var(--accent);color:var(--accent);background:#eff6ff}
.btn-print{padding:9px 18px;background:var(--navy);color:#fff;border:none;border-radius:9px;font-family:var(--fb);font-size:.87rem;font-weight:700;cursor:pointer;display:inline-flex;align-items:center;gap:6px;transition:background .15s}
.btn-print:hover{background:#1e3a6e}
.btn-print.exp-print{background:linear-gradient(135deg,#7c2d12,#c2410c)}
.btn-print.exp-print:hover{background:#7c2d12}

/* Meta card */
.meta-card{background:var(--card);border:1px solid var(--border);border-radius:var(--radius);box-shadow:var(--shadow);overflow:hidden;margin-bottom:20px}
.meta-hdr{padding:13px 20px;color:rgba(255,255,255,.85);font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.7px;display:flex;align-items:center;gap:8px}
.meta-body{padding:20px 24px;display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:16px 24px}
.meta-lbl{font-size:.69rem;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:var(--ink-l);margin-bottom:4px}
.meta-val{font-size:.9rem;font-weight:600;color:var(--navy);display:flex;align-items:center;gap:6px}
.meta-val.muted{color:var(--ink-l);font-weight:500}

/* Expense meta card accent */
.meta-card.exp-card{border-color:#fed7aa}
.meta-card.exp-card .meta-hdr{background:linear-gradient(135deg,#7c2d12,#c2410c)}

/* Info box for expense-specific fields */
.info-box{background:#fff7ed;border:1px solid #fed7aa;border-radius:10px;padding:14px 18px;margin-bottom:20px;font-size:.87rem;color:#7c2d12}
.info-box strong{display:block;margin-bottom:4px;font-size:.8rem;text-transform:uppercase;letter-spacing:.5px;opacity:.7}
.info-box p{margin:0;line-height:1.6}

/* Badges */
.badge-posted{background:#d1fae5;color:#065f46;border-radius:999px;padding:3px 12px;font-size:.72rem;font-weight:700}
.badge-draft{background:#fef3c7;color:#92400e;border-radius:999px;padding:3px 12px;font-size:.72rem;font-weight:700}
.badge-balanced{background:#d1fae5;color:#065f46;border-radius:999px;padding:3px 12px;font-size:.72rem;font-weight:700}
.badge-unbal{background:#fee2e2;color:#991b1b;border-radius:999px;padding:3px 12px;font-size:.72rem;font-weight:700}
.badge-expense{background:#ffedd5;color:#7c2d12;border-radius:999px;padding:3px 12px;font-size:.72rem;font-weight:700}
.cat-badge{display:inline-flex;align-items:center;gap:5px;background:#ffedd5;border:1px solid #fed7aa;border-radius:8px;padding:4px 12px;font-size:.78rem;font-weight:700;color:#c2410c}

/* Entry lines card */
.entry-card{background:var(--card);border:1px solid var(--border);border-radius:var(--radius);box-shadow:var(--shadow);overflow:hidden;margin-bottom:20px}
.entry-hdr{padding:13px 20px;color:rgba(255,255,255,.85);font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.7px;display:flex;align-items:center;gap:8px}

/* Entry table */
.et{width:100%;border-collapse:collapse;font-size:.88rem}
.et thead th{background:#f8fafc;color:var(--ink-l);font-size:.69rem;font-weight:700;text-transform:uppercase;letter-spacing:.6px;padding:11px 16px;border-bottom:2px solid var(--border);text-align:left}
.et thead th.ra{text-align:right}
.et tbody tr{border-bottom:1px solid var(--border);transition:background .12s}
.et tbody tr:last-child{border-bottom:none}
.et tbody tr:hover{background:#f7faff}
.et tbody td{padding:14px 16px;vertical-align:top}
.et tbody td.ra{text-align:right;font-variant-numeric:tabular-nums}

/* Account cell */
.acct-cell{display:flex;align-items:center;gap:10px}
.acct-icon{width:34px;height:34px;border-radius:9px;flex-shrink:0;display:flex;align-items:center;justify-content:center;font-size:.82rem;font-weight:700;color:#fff}
.ai-dr{background:linear-gradient(135deg,#059669,#34d399)}
.ai-cr{background:linear-gradient(135deg,#dc2626,#f87171)}
.ai-exp-dr{background:linear-gradient(135deg,#7c2d12,#ea580c)}
.ai-exp-cr{background:linear-gradient(135deg,#92400e,#d97706)}
.ai-neu{background:linear-gradient(135deg,#2563eb,#60a5fa)}
.acct-name{font-weight:700;color:var(--navy);font-size:.9rem}

.dr-amt{font-weight:700;color:#059669;font-size:.92rem}
.cr-amt{font-weight:700;color:#dc2626;font-size:.92rem}
.exp-dr-amt{font-weight:700;color:#7c2d12;font-size:.92rem}
.exp-cr-amt{font-weight:700;color:#92400e;font-size:.92rem}
.empty-amt{color:var(--border);font-size:.82rem}
.desc-text{font-size:.8rem;color:var(--ink-l);white-space:pre-line;line-height:1.6}

/* Tfoot */
.et tfoot tr{background:#f8fafc}
.et tfoot td{padding:12px 16px;border-top:2px solid var(--border);font-weight:700;font-size:.9rem;color:var(--navy)}
.et tfoot td.ra{text-align:right}
.et tfoot td.tl{color:var(--ink-l);font-size:.72rem;text-transform:uppercase;letter-spacing:.6px}

/* Balance bar */
.bal-bar{display:flex;align-items:center;gap:12px;padding:14px 20px;border-top:1px solid var(--border);background:#f8fafc;flex-wrap:wrap}

/* Totals summary */
.totals-sum{background:var(--card);border:1px solid var(--border);border-radius:var(--radius);box-shadow:var(--shadow);padding:20px 24px;margin-bottom:20px;display:flex;gap:20px;flex-wrap:wrap;justify-content:flex-end;align-items:center}
.ti{text-align:right}
.ti-lbl{font-size:.69rem;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:var(--ink-l)}
.ti-val{font-size:1.2rem;font-weight:700;color:var(--navy);margin-top:2px}
.ti-val.dc{color:#059669}.ti-val.cc{color:#dc2626}
.ti-val.exp-dc{color:#7c2d12}.ti-val.exp-cc{color:#92400e}
.tdiv{width:1px;background:var(--border);align-self:stretch}

/* Mobile line cards */
.mob-lines{display:none}
.ml-card{background:var(--card);border:1px solid var(--border);border-radius:11px;padding:14px;margin-bottom:10px;box-shadow:0 1px 6px rgba(11,29,58,.05)}
.ml-head{display:flex;justify-content:space-between;align-items:flex-start;gap:10px;margin-bottom:10px}
.ml-row{display:flex;justify-content:space-between;align-items:center;padding:6px 0;border-bottom:1px solid var(--border);font-size:.82rem;gap:8px}
.ml-row:last-of-type{border-bottom:none}
.mll{color:var(--ink-l);font-weight:600;font-size:.69rem;text-transform:uppercase;letter-spacing:.5px;flex-shrink:0}
.mlv{font-weight:500;color:var(--ink);text-align:right}

/* Print */
@media print {
  body>*:not(.cw){display:none!important}
  #mySidebar,.billing-sidebar,.sidebar,nav,aside,[id*=sidebar],[class*=sidebar],#sidebarToggle,
  .head-actions,.no-print,.btn-back,.btn-print,.mob-lines{display:none!important}
  .cw{margin-left:0!important;padding:20px!important;max-width:100%!important;width:100%!important}
  .meta-card,.entry-card{box-shadow:none!important;border:1px solid #ccc!important;break-inside:avoid}
  .meta-hdr,.entry-hdr{-webkit-print-color-adjust:exact;print-color-adjust:exact}
  body{background:#fff!important}
  .et{font-size:12px}
}

/* Responsive */
@media(max-width:768px){
  .cw{margin-left:var(--sidebar-w-sm);padding:60px 14px 50px}
  .cw.sidebar-collapsed{margin-left:0}
  .entry-card>div:first-of-type{display:none}
  .mob-lines{display:block}
  .meta-body{grid-template-columns:1fr 1fr}
  .head-actions{margin-left:0;width:100%}
  .btn-back,.btn-print{width:100%;justify-content:center}
  .totals-sum{justify-content:center}
}
@media(max-width:480px){
  .cw{margin-left:0!important;padding:56px 10px 40px}
  .meta-body{grid-template-columns:1fr;gap:12px}
  .ph-icon{width:44px;height:44px;font-size:1.2rem;border-radius:11px}
}
@supports(padding:env(safe-area-inset-bottom)){.cw{padding-bottom:calc(60px + env(safe-area-inset-bottom))}}
</style>
</head>
<body>
<?php include 'billing_sidebar.php'; ?>
<div class="cw" id="mainCw">

  <!-- Page Header -->
  <div class="page-head">
    <?php
      $icon_class = match($entry_type) {
        'expense' => 'exp-icon',
        'receipt' => 'rec-icon',
        default   => 'pay-icon',
      };
      $icon_bi = match($entry_type) {
        'expense' => 'bi-bag-x-fill',
        'receipt' => 'bi-file-earmark-check-fill',
        default   => 'bi-journal-check',
      };
    ?>
    <div class="ph-icon <?= $icon_class ?>"><i class="bi <?= $icon_bi ?>"></i></div>
    <div>
      <h1><?= htmlspecialchars($page_title) ?></h1>
      <p>
        <?= match($entry_type) {
          'expense' => 'Expense journal entry — double-entry accounting record',
          'receipt' => 'Billing receipt journal entry',
          'payment' => 'Payment journal entry — double-entry accounting record',
          default   => 'Double-entry accounting record',
        } ?>
      </p>
    </div>
    <div class="head-actions no-print">
      <a href="journal_entry.php" class="btn-back"><i class="bi bi-arrow-left"></i> Back</a>
      <button onclick="window.print()" class="btn-print <?= $entry_type==='expense'?'exp-print':'' ?>">
        <i class="bi bi-printer"></i> Print
      </button>
    </div>
  </div>

  <!-- Meta Info -->
  <div class="meta-card <?= $entry_type==='expense'?'exp-card':'' ?>">
    <div class="meta-hdr" style="background:<?= $hdr_gradient ?>">
      <i class="bi bi-info-circle"></i> Entry Information
    </div>
    <div class="meta-body">

      <!-- Date -->
      <div>
        <div class="meta-lbl"><?= $entry_type==='expense' ? 'Expense Date' : 'Payment Date' ?></div>
        <div class="meta-val">
          <i class="bi bi-calendar-check" style="opacity:.5;font-size:.85rem"></i>
          <?php
            if ($entry_date && $entry_date !== '0000-00-00' && $entry_date !== '0000-00-00 00:00:00') {
                // expense_date is DATE; others may be DATETIME
                $ts = strtotime($entry_date);
                echo $entry_type === 'expense' ? date('F d, Y', $ts) : date('F d, Y h:i A', $ts);
            } else {
                echo '<span style="color:var(--ink-l);font-weight:400">Not recorded</span>';
            }
          ?>
        </div>
      </div>

      <!-- Reference / ID -->
      <div>
        <div class="meta-lbl">Reference</div>
        <div class="meta-val" style="font-family:monospace;font-size:.85rem">
          <?php
            if ($entry_type === 'expense')
                echo 'EXP-' . str_pad($expense['expense_id'], 6, '0', STR_PAD_LEFT);
            elseif ($entry_type === 'payment')
                echo htmlspecialchars($payment['payment_id']);
            elseif ($entry_type === 'receipt')
                echo '#' . $receipt_id;
            else
                echo htmlspecialchars($entry['reference'] ?? '—');
          ?>
        </div>
      </div>

      <!-- Status -->
      <div>
        <div class="meta-lbl">Status</div>
        <div class="meta-val">
          <?php if ($entry_type === 'expense'): ?>
            <span class="badge-expense"><i class="bi bi-bag-x"></i> Expense Posted</span>
          <?php else: ?>
            <?php $st = $entry['status'] ?? 'Posted'; ?>
            <span class="<?= strtolower($st)==='posted'?'badge-posted':'badge-draft' ?>"><?= htmlspecialchars($st) ?></span>
          <?php endif; ?>
        </div>
      </div>

      <!-- Module -->
      <div>
        <div class="meta-lbl">Module</div>
        <div class="meta-val muted">
          <?= match($entry_type) {
            'expense' => 'Expense Management',
            'payment' => 'Billing / Payments',
            'receipt' => 'Billing / Receipts',
            default   => ucfirst($entry['module'] ?? 'Billing'),
          } ?>
        </div>
      </div>

      <!-- Expense-specific: Category -->
      <?php if ($entry_type === 'expense' && !empty($expense['category'])): ?>
      <div>
        <div class="meta-lbl">Category</div>
        <div class="meta-val">
          <span class="cat-badge"><i class="bi bi-tag"></i> <?= htmlspecialchars($expense['category']) ?></span>
        </div>
      </div>
      <?php endif; ?>

      <!-- Expense-specific: Recorded By -->
      <?php if ($entry_type === 'expense'): ?>
      <div>
        <div class="meta-lbl">Recorded By</div>
        <div class="meta-val muted">
          <i class="bi bi-person" style="opacity:.5;font-size:.85rem"></i>
          <?= htmlspecialchars($expense['recorded_by'] ?: $expense['created_by'] ?: 'System') ?>
        </div>
      </div>
      <?php else: ?>
      <!-- Created By (non-expense) -->
      <div>
        <div class="meta-lbl">Created By</div>
        <div class="meta-val muted">
          <i class="bi bi-person" style="opacity:.5;font-size:.85rem"></i>
          <?= htmlspecialchars($entry['created_by'] ?? 'System') ?>
        </div>
      </div>
      <?php endif; ?>

      <!-- Balance -->
      <div>
        <div class="meta-lbl">Balance</div>
        <div class="meta-val">
          <span class="<?= $is_balanced?'badge-balanced':'badge-unbal' ?>">
            <i class="bi bi-<?= $is_balanced?'check-circle':'exclamation-triangle' ?>"></i>
            <?= $is_balanced ? 'Balanced' : 'Unbalanced' ?>
          </span>
        </div>
      </div>

    </div>
  </div>

  <!-- Expense notes info box -->
  <?php if ($entry_type === 'expense' && !empty($expense['notes'])): ?>
  <div class="info-box">
    <strong><i class="bi bi-sticky"></i> Notes</strong>
    <p><?= nl2br(htmlspecialchars($expense['notes'])) ?></p>
  </div>
  <?php endif; ?>

  <!-- Expense description info box -->
  <?php if ($entry_type === 'expense' && !empty($expense['description'])): ?>
  <div class="info-box">
    <strong><i class="bi bi-card-text"></i> Description</strong>
    <p><?= nl2br(htmlspecialchars($expense['description'])) ?></p>
  </div>
  <?php endif; ?>

  <!-- Entry Lines (Desktop) -->
  <div class="entry-card">
    <div class="entry-hdr" style="background:<?= $hdr_gradient ?>">
      <i class="bi bi-table"></i> Journal Entry Lines
      <span style="margin-left:auto;font-weight:400;opacity:.7">
        <?= count($lines) ?> line<?= count($lines)!==1?'s':'' ?>
      </span>
    </div>
    <div style="overflow-x:auto">
      <table class="et">
        <thead><tr>
          <th style="width:30px">#</th>
          <th>Account</th>
          <th class="ra">Debit (₱)</th>
          <th class="ra">Credit (₱)</th>
          <th>Description</th>
        </tr></thead>
        <tbody>
        <?php foreach ($lines as $idx => $line):
          $hd = floatval($line['debit']  ?? 0) > 0;
          $hc = floatval($line['credit'] ?? 0) > 0;
          if ($entry_type === 'expense') {
              $ic = $hd ? 'ai-exp-dr' : ($hc ? 'ai-exp-cr' : 'ai-neu');
              $da = 'exp-dr-amt'; $ca = 'exp-cr-amt';
          } else {
              $ic = $hd ? 'ai-dr' : ($hc ? 'ai-cr' : 'ai-neu');
              $da = 'dr-amt'; $ca = 'cr-amt';
          }
          $ini = strtoupper(substr($line['account_name'] ?? 'A', 0, 1));
        ?>
          <tr>
            <td style="color:var(--ink-l);font-size:.8rem"><?= $idx+1 ?></td>
            <td><div class="acct-cell">
              <div class="acct-icon <?= $ic ?>"><?= $ini ?></div>
              <span class="acct-name"><?= htmlspecialchars($line['account_name'] ?? '') ?></span>
            </div></td>
            <td class="ra"><?php if ($hd): ?><span class="<?= $da ?>"><?= number_format(floatval($line['debit']),2) ?></span><?php else: ?><span class="empty-amt">—</span><?php endif; ?></td>
            <td class="ra"><?php if ($hc): ?><span class="<?= $ca ?>"><?= number_format(floatval($line['credit']),2) ?></span><?php else: ?><span class="empty-amt">—</span><?php endif; ?></td>
            <td><span class="desc-text"><?= nl2br(htmlspecialchars($line['description'] ?? '—')) ?></span></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot>
          <tr>
            <td colspan="2" class="tl">TOTALS</td>
            <td class="ra <?= $entry_type==='expense'?'exp-dr-amt':'dr-amt' ?>">₱<?= number_format($total_debit,2) ?></td>
            <td class="ra <?= $entry_type==='expense'?'exp-cr-amt':'cr-amt' ?>">₱<?= number_format($total_credit,2) ?></td>
            <td>
              <?php if ($is_balanced): ?>
                <span class="badge-balanced"><i class="bi bi-check-circle"></i> Balanced</span>
              <?php else: ?>
                <span class="badge-unbal"><i class="bi bi-exclamation-triangle"></i> Diff: ₱<?= number_format(abs($total_debit-$total_credit),2) ?></span>
              <?php endif; ?>
            </td>
          </tr>
        </tfoot>
      </table>
    </div>
    <!-- Balance bar -->
    <div class="bal-bar">
      <?php if ($is_balanced): ?>
        <i class="bi bi-check-circle-fill" style="color:#059669;font-size:1.1rem"></i>
        <span style="font-size:.85rem;font-weight:600;color:#059669">This entry is balanced — debits equal credits.</span>
      <?php else: ?>
        <i class="bi bi-exclamation-triangle-fill" style="color:#dc2626;font-size:1.1rem"></i>
        <span style="font-size:.85rem;font-weight:600;color:#dc2626">Unbalanced — difference of ₱<?= number_format(abs($total_debit-$total_credit),2) ?>.</span>
      <?php endif; ?>
    </div>
  </div>

  <!-- Mobile Line Cards -->
  <div class="mob-lines">
    <?php foreach ($lines as $idx => $line):
      $hd = floatval($line['debit']  ?? 0) > 0;
      $hc = floatval($line['credit'] ?? 0) > 0;
      if ($entry_type === 'expense') {
          $ic = $hd ? 'ai-exp-dr' : ($hc ? 'ai-exp-cr' : 'ai-neu');
          $da = 'exp-dr-amt'; $ca = 'exp-cr-amt';
      } else {
          $ic = $hd ? 'ai-dr' : ($hc ? 'ai-cr' : 'ai-neu');
          $da = 'dr-amt'; $ca = 'cr-amt';
      }
    ?>
    <div class="ml-card">
      <div class="ml-head">
        <div class="acct-cell">
          <div class="acct-icon <?= $ic ?>"><?= strtoupper(substr($line['account_name']??'A',0,1)) ?></div>
          <span class="acct-name"><?= htmlspecialchars($line['account_name']??'') ?></span>
        </div>
        <span style="font-size:.7rem;color:var(--ink-l)">Line <?= $idx+1 ?></span>
      </div>
      <?php if ($hd): ?>
      <div class="ml-row"><span class="mll">Debit</span><span class="mlv <?= $da ?>">₱<?= number_format(floatval($line['debit']),2) ?></span></div>
      <?php endif; ?>
      <?php if ($hc): ?>
      <div class="ml-row"><span class="mll">Credit</span><span class="mlv <?= $ca ?>">₱<?= number_format(floatval($line['credit']),2) ?></span></div>
      <?php endif; ?>
      <?php if (!empty($line['description'])): ?>
      <div class="ml-row" style="align-items:flex-start">
        <span class="mll">Note</span>
        <span class="mlv desc-text" style="font-size:.78rem;text-align:right"><?= nl2br(htmlspecialchars($line['description'])) ?></span>
      </div>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>

    <!-- Mobile totals -->
    <div class="totals-sum">
      <div class="ti">
        <div class="ti-lbl">Total Debit</div>
        <div class="ti-val <?= $entry_type==='expense'?'exp-dc':'dc' ?>">₱<?= number_format($total_debit,2) ?></div>
      </div>
      <div class="tdiv"></div>
      <div class="ti">
        <div class="ti-lbl">Total Credit</div>
        <div class="ti-val <?= $entry_type==='expense'?'exp-cc':'cc' ?>">₱<?= number_format($total_credit,2) ?></div>
      </div>
      <div class="tdiv"></div>
      <div class="ti">
        <div class="ti-lbl">Status</div>
        <div class="ti-val" style="font-size:.9rem;margin-top:4px">
          <span class="<?= $is_balanced?'badge-balanced':'badge-unbal' ?>"><?= $is_balanced?'Balanced':'Unbalanced' ?></span>
        </div>
      </div>
    </div>
  </div>

  <!-- Bottom actions -->
  <div class="head-actions no-print" style="margin-left:0;margin-top:4px;display:flex;gap:8px;flex-wrap:wrap">
    <a href="journal_entry.php" class="btn-back"><i class="bi bi-arrow-left"></i> Back to Journal</a>
    <button onclick="window.print()" class="btn-print <?= $entry_type==='expense'?'exp-print':'' ?>">
      <i class="bi bi-printer"></i> Print Entry
    </button>
  </div>

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