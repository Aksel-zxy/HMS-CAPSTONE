<?php
include '../../SQL/config.php';
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/vendor/autoload.php';
use GuzzleHttp\Client;

/* ===============================
   PAYMONGO CONFIG
================================ */
define('PAYMONGO_SECRET_KEY', 'sk_test_akT1ZW6za7m6FC9S9VqYNiVV');
define('PAYMONGO_API_BASE', 'https://api.paymongo.com/v1/');

/* ===============================
   PAYMONGO CLIENT
================================ */
$paymongoClient = new Client([
    'base_uri' => PAYMONGO_API_BASE,
    'headers' => [
        'Accept'        => 'application/json',
        'Content-Type'  => 'application/json',
        'Authorization' => 'Basic ' . base64_encode(PAYMONGO_SECRET_KEY . ':'),
    ],
    'timeout' => 8
]);

/* ===============================
   GET PATIENT
================================ */
$patient_id = (int)($_GET['patient_id'] ?? 0);

$stmt = $conn->prepare("
    SELECT patient_id,
           CONCAT(fname,' ',IFNULL(mname,''),' ',lname) AS full_name
    FROM patientinfo
    WHERE patient_id = ?
");
$stmt->bind_param("i", $patient_id);
$stmt->execute();
$patient = $stmt->get_result()->fetch_assoc();
if (!$patient) die("Patient not found");

/* ===============================
   LATEST BILLING
================================ */
$stmt = $conn->prepare("
    SELECT *
    FROM billing_records
    WHERE patient_id = ?
    ORDER BY billing_id DESC
    LIMIT 1
");
$stmt->bind_param("i", $patient_id);
$stmt->execute();
$billing = $stmt->get_result()->fetch_assoc();
if (!$billing) die("No billing record found");

$billing_id     = $billing['billing_id'];
$status         = $billing['status'];
$transaction_id = $billing['transaction_id'];
$existing_link  = $billing['paymongo_link_id'];

/* ===============================
   FETCH PATIENT RECEIPT
================================ */
$receipt = null;
$stmt = $conn->prepare("
    SELECT *
    FROM patient_receipt
    WHERE billing_id = ? AND patient_id = ?
    LIMIT 1
");
$stmt->bind_param("ii", $billing_id, $patient_id);
$stmt->execute();
$receipt = $stmt->get_result()->fetch_assoc();

/* ===============================
   LOCK IF PAID
================================ */
if ($status === 'Paid') {
    header("Location: patient_billing.php");
    exit;
}

/* ===============================
   GET BILLING ITEMS
================================ */
$items    = [];
$subtotal = 0;

$stmt = $conn->prepare("
    SELECT bi.total_price, ds.serviceName, ds.description
    FROM billing_items bi
    LEFT JOIN dl_services ds ON bi.service_id = ds.serviceID
    WHERE bi.billing_id = ?
");
$stmt->bind_param("i", $billing_id);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    $items[]   = $row;
    $subtotal += (float)$row['total_price'];
}

/* ===============================
   CALCULATE AMOUNTS
================================ */
$pwd_discount      = 0;
$insurance_covered = 0;
$grand_total       = $subtotal;
$out_of_pocket     = $subtotal;

if ($receipt) {
    $pwd_discount      = (float)($receipt['total_discount']      ?? 0);
    $insurance_covered = (float)($receipt['insurance_covered']   ?? 0);
    $grand_total       = (float)($receipt['grand_total']         ?? $subtotal - $pwd_discount - $insurance_covered);
    $out_of_pocket     = (float)($receipt['total_out_of_pocket'] ?? $grand_total);
} else {
    $pwd_discount      = (float)($billing['total_discount']    ?? 0);
    $insurance_covered = (float)($billing['insurance_covered'] ?? 0);
    $grand_total       = (float)($billing['grand_total']       ?? $subtotal - $pwd_discount - $insurance_covered);
    $out_of_pocket     = (float)($billing['out_of_pocket']     ?? $grand_total);
}

if ($out_of_pocket < 0) $out_of_pocket = 0;
if ($grand_total   < 0) $grand_total   = 0;

/* ===============================
   AUTO-MARK AS PAID IF FULLY COVERED
================================ */
if ($grand_total <= 0 && $status !== 'Paid') {
    $conn->prepare("UPDATE billing_records SET status='Paid' WHERE billing_id=?")
         ->bind_param("i", $billing_id) || null;
    $s = $conn->prepare("UPDATE billing_records SET status='Paid' WHERE billing_id=?");
    $s->bind_param("i", $billing_id);
    $s->execute();

    $s2 = $conn->prepare("UPDATE patient_receipt SET status='Paid' WHERE billing_id=? AND patient_id=?");
    $s2->bind_param("ii", $billing_id, $patient_id);
    $s2->execute();

    $status = 'Paid';
}

/* ===============================
   HANDLE CASH PAYMENT
   FIX: include total_charges in INSERT
================================ */
if (isset($_POST['payment_method']) && $_POST['payment_method'] === 'cash' && $grand_total > 0) {
    $cash_reference = 'CASH-' . time();
    $desc           = "Billing #$billing_id - Cash Payment";

    $s = $conn->prepare("
        INSERT INTO paymongo_payments
            (payment_id, amount, status, payment_method, remarks)
        VALUES (?, ?, 'Paid', 'CASH', ?)
    ");
    $s->bind_param("sds", $cash_reference, $grand_total, $desc);
    $s->execute();

    $s2 = $conn->prepare("UPDATE billing_records SET status='Paid', payment_method='Cash' WHERE billing_id=?");
    $s2->bind_param("i", $billing_id);
    $s2->execute();

    $s3 = $conn->prepare("UPDATE patient_receipt SET status='Paid', payment_reference=? WHERE billing_id=? AND patient_id=?");
    $s3->bind_param("sii", $cash_reference, $billing_id, $patient_id);
    $s3->execute();

    header("Location: patient_billing.php?success=cash_payment");
    exit;
}

/* ===============================
   CREATE / REUSE PAYMONGO LINK
   FIX: include total_charges in INSERT
================================ */
$payLinkUrl = null;

if ($grand_total > 0) {

    if ($existing_link) {
        $payLinkUrl = "https://checkout.paymongo.com/links/$existing_link";
    } else {
        $payload = [
            'data' => [
                'attributes' => [
                    'amount'      => (int) round($grand_total * 100),
                    'currency'    => 'PHP',
                    'description' => "Hospital Billing #{$billing_id}",
                    'remarks'     => "TXN:{$transaction_id}"
                ]
            ]
        ];

        $response = $paymongoClient->post('links', ['json' => $payload]);
        $body     = json_decode($response->getBody(), true);

        $payLinkUrl = $body['data']['attributes']['checkout_url'] ?? null;
        $link_id    = $body['data']['id'] ?? null;

        if ($link_id) {
            // FIX: removed non-existent total_charges column
            $s = $conn->prepare("
                INSERT IGNORE INTO paymongo_payments
                    (payment_id, amount, status, payment_method, remarks)
                VALUES (?, ?, 'Pending', 'PAYLINK', ?)
            ");
            $desc = "Billing #$billing_id";
            $s->bind_param("sds", $link_id, $grand_total, $desc);
            $s->execute();

            $s2 = $conn->prepare("
                UPDATE billing_records
                SET paymongo_link_id=?, paymongo_reference_number=?
                WHERE billing_id=?
            ");
            $s2->bind_param("ssi", $link_id, $transaction_id, $billing_id);
            $s2->execute();

            $s3 = $conn->prepare("
                INSERT INTO patient_receipt
                    (patient_id, billing_id, status, paymongo_reference, payment_reference)
                VALUES (?, ?, 'Pending', ?, ?)
                ON DUPLICATE KEY UPDATE paymongo_reference=VALUES(paymongo_reference)
            ");
            $s3->bind_param("iiss", $patient_id, $billing_id, $link_id, $transaction_id);
            $s3->execute();
        }
    }
}

/* ===============================
   PAYMENT HISTORY
================================ */
$history = [];
$s = $conn->prepare("
    SELECT payment_id, amount, status, payment_method, paid_at
    FROM paymongo_payments
    WHERE remarks LIKE ?
    ORDER BY updated_at DESC
");
$like = "%$billing_id%";
$s->bind_param("s", $like);
$s->execute();
$history = $s->get_result()->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Billing Summary — #<?= htmlspecialchars($transaction_id) ?></title>
<link href="https://fonts.googleapis.com/css2?family=Lora:wght@500;600;700&family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">

<style>
/* ── RESET ── */
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

:root {
    --slate:        #0f2339;
    --slate-mid:    #1d3a5f;
    --slate-light:  #e8eef6;
    --emerald:      #0a6b5e;
    --emerald-bg:   #e6f4f1;
    --amber:        #b86e1f;
    --amber-bg:     #fdf0e0;
    --crimson:      #9b2335;
    --crimson-bg:   #fceaec;
    --sky:          #0369a1;
    --sky-bg:       #e0f2fe;
    --gray-50:      #f8f9fb;
    --gray-100:     #f1f3f6;
    --gray-200:     #e2e6ec;
    --gray-400:     #9ba5b4;
    --gray-600:     #5a6475;
    --gray-800:     #2d3748;
    --white:        #ffffff;
    --shadow:       0 4px 20px rgba(15,35,57,.10);
    --shadow-lg:    0 12px 40px rgba(15,35,57,.14);
    --radius:       14px;
    --radius-sm:    8px;
}

body {
    font-family: 'Plus Jakarta Sans', sans-serif;
    background: linear-gradient(135deg, #e8eef6 0%, #f1f3f6 60%, #e8f4f1 100%);
    min-height: 100vh;
    color: var(--gray-800);
    font-size: 14px;
    line-height: 1.6;
}

/* ── LAYOUT ── */
.page-wrapper {
    display: flex;
    min-height: 100vh;
}

.sidebar-col {
    width: 260px;
    flex-shrink: 0;
    background: var(--slate);
}

.main-col {
    flex: 1;
    padding: 36px 32px 60px;
    max-width: 900px;
}

/* ── PAGE HEADER ── */
.page-header {
    margin-bottom: 28px;
}

.breadcrumb-trail {
    font-size: 12px;
    color: var(--gray-400);
    margin-bottom: 8px;
    display: flex;
    align-items: center;
    gap: 6px;
}
.breadcrumb-trail i { font-size: 10px; }

.page-title {
    font-family: 'Lora', serif;
    font-size: 28px;
    font-weight: 700;
    color: var(--slate);
    letter-spacing: -.3px;
}
.page-title span {
    color: var(--emerald);
}

.page-subtitle {
    font-size: 13.5px;
    color: var(--gray-400);
    margin-top: 4px;
}

/* ── PATIENT STRIP ── */
.patient-strip {
    background: var(--white);
    border-radius: var(--radius);
    padding: 18px 24px;
    display: flex;
    align-items: center;
    gap: 16px;
    margin-bottom: 24px;
    box-shadow: var(--shadow);
    border-left: 4px solid var(--slate);
}

.patient-avatar {
    width: 48px; height: 48px;
    border-radius: 50%;
    background: var(--slate-light);
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    font-size: 20px;
    color: var(--slate-mid);
}

.patient-meta .name {
    font-weight: 700;
    font-size: 16px;
    color: var(--slate);
}
.patient-meta .txn {
    font-size: 12px;
    color: var(--gray-400);
    font-family: monospace;
    letter-spacing: .5px;
}

/* ── STATUS ALERT ── */
.status-alert {
    border-radius: var(--radius);
    padding: 16px 22px;
    display: flex;
    align-items: center;
    gap: 14px;
    margin-bottom: 24px;
    font-weight: 600;
    font-size: 14px;
}
.status-alert.fully-covered {
    background: var(--emerald-bg);
    color: var(--emerald);
    border: 1px solid #aedcd6;
}
.status-alert i { font-size: 24px; flex-shrink: 0; }
.status-alert .msg { line-height: 1.4; }
.status-alert .msg small { font-weight: 400; color: inherit; opacity: .75; display:block; margin-top:2px; }

/* ── CARD ── */
.card {
    background: var(--white);
    border-radius: var(--radius);
    box-shadow: var(--shadow);
    margin-bottom: 22px;
    overflow: hidden;
}

.card-head {
    padding: 16px 24px;
    border-bottom: 1px solid var(--gray-100);
    display: flex;
    align-items: center;
    gap: 10px;
}
.card-head .icon {
    width: 34px; height: 34px;
    border-radius: var(--radius-sm);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 16px;
    flex-shrink: 0;
}
.card-head .icon.slate  { background: var(--slate-light); color: var(--slate); }
.card-head .icon.emerald { background: var(--emerald-bg); color: var(--emerald); }
.card-head .icon.amber  { background: var(--amber-bg);   color: var(--amber);  }

.card-head-title {
    font-family: 'Lora', serif;
    font-weight: 600;
    font-size: 15px;
    color: var(--slate);
}
.card-head-sub {
    font-size: 12px;
    color: var(--gray-400);
    margin-top: 1px;
}

.card-body { padding: 0; }

/* ── SERVICES TABLE ── */
.services-table {
    width: 100%;
    border-collapse: collapse;
}
.services-table thead th {
    padding: 12px 20px;
    font-size: 10.5px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 1.2px;
    color: var(--gray-400);
    background: var(--gray-50);
    border-bottom: 1px solid var(--gray-200);
    white-space: nowrap;
}
.services-table thead th:last-child { text-align: right; }

.services-table tbody td {
    padding: 14px 20px;
    border-bottom: 1px solid var(--gray-100);
    vertical-align: top;
}
.services-table tbody tr:last-child td { border-bottom: none; }
.services-table tbody tr:hover { background: var(--gray-50); }

.service-name { font-weight: 600; color: var(--slate); font-size: 13.5px; }
.service-desc { font-size: 12px; color: var(--gray-400); margin-top: 2px; }
.td-right { text-align: right; }
.td-amount { text-align: right; font-weight: 600; color: var(--slate); }

/* ── TOTALS ── */
.totals-block {
    padding: 6px 20px 20px;
}

.total-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 10px 16px;
    border-radius: var(--radius-sm);
    margin-bottom: 6px;
    font-size: 13.5px;
}
.total-row.subtotal  { color: var(--gray-600); }
.total-row.discount  { background: var(--amber-bg);   color: var(--amber);   font-weight: 600; }
.total-row.insurance { background: var(--sky-bg);     color: var(--sky);     font-weight: 600; }
.total-row.grand     {
    background: var(--slate);
    color: var(--white);
    font-weight: 700;
    font-size: 16px;
    margin-top: 10px;
}
.total-row.grand .t-amount {
    font-family: 'Lora', serif;
    font-size: 22px;
    color: #f0c060;
}

/* ── PAYMENT METHODS ── */
.payment-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
    padding: 24px;
}

.pay-card {
    border: 2px solid var(--gray-200);
    border-radius: var(--radius);
    padding: 24px 20px;
    text-align: center;
    text-decoration: none;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 10px;
    transition: all .22s ease;
    cursor: pointer;
    background: var(--white);
}
.pay-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 28px rgba(15,35,57,.12);
    text-decoration: none;
}

.pay-card.online {
    border-color: var(--slate-mid);
    background: linear-gradient(160deg, var(--slate) 0%, var(--slate-mid) 100%);
    color: var(--white);
}
.pay-card.online:hover { border-color: var(--slate); }

.pay-card.cash {
    border-color: var(--emerald);
}
.pay-card.cash:hover { background: var(--emerald-bg); }

.pay-icon {
    width: 54px; height: 54px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
}
.online .pay-icon  { background: rgba(255,255,255,.15); color: var(--white); }
.cash   .pay-icon  { background: var(--emerald-bg); color: var(--emerald); }

.pay-title {
    font-family: 'Lora', serif;
    font-weight: 600;
    font-size: 16px;
}
.online .pay-title { color: var(--white); }
.cash   .pay-title { color: var(--slate); }

.pay-amount {
    font-size: 22px;
    font-family: 'Lora', serif;
    font-weight: 700;
}
.online .pay-amount { color: #f0c060; }
.cash   .pay-amount { color: var(--emerald); }

.pay-sub {
    font-size: 11.5px;
    line-height: 1.5;
}
.online .pay-sub { color: rgba(255,255,255,.6); }
.cash   .pay-sub { color: var(--gray-400); }

.pay-card form { width: 100%; }
.pay-card.cash button {
    background: none;
    border: none;
    width: 100%;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 10px;
    cursor: pointer;
    padding: 0;
}

/* ── HISTORY TABLE ── */
.history-table {
    width: 100%;
    border-collapse: collapse;
}
.history-table thead th {
    padding: 11px 20px;
    font-size: 10.5px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 1.1px;
    color: var(--gray-400);
    background: var(--gray-50);
    border-bottom: 1px solid var(--gray-200);
}
.history-table td {
    padding: 13px 20px;
    border-bottom: 1px solid var(--gray-100);
    font-size: 13px;
    vertical-align: middle;
}
.history-table tbody tr:last-child td { border-bottom: none; }
.history-table tbody tr:hover { background: var(--gray-50); }

.ref-code {
    font-family: monospace;
    font-size: 12px;
    color: var(--gray-600);
    background: var(--gray-100);
    padding: 2px 8px;
    border-radius: 4px;
}

.badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 3px 10px;
    border-radius: 50px;
    font-size: 11px;
    font-weight: 700;
    letter-spacing: .3px;
}
.badge.paid     { background: var(--emerald-bg); color: var(--emerald); }
.badge.pending  { background: var(--amber-bg);   color: var(--amber);   }
.badge.cash-m   { background: var(--gray-100);   color: var(--gray-600); }
.badge.online-m { background: var(--sky-bg);     color: var(--sky);     }

.empty-row td {
    text-align: center;
    padding: 40px;
    color: var(--gray-400);
    font-style: italic;
}

/* ── ANIMATIONS ── */
@keyframes fadeUp {
    from { opacity: 0; transform: translateY(18px); }
    to   { opacity: 1; transform: translateY(0);    }
}
.card          { animation: fadeUp .4s ease both; }
.card:nth-child(2) { animation-delay: .06s; }
.card:nth-child(3) { animation-delay: .12s; }
.card:nth-child(4) { animation-delay: .18s; }
.patient-strip { animation: fadeUp .3s ease both; }
.status-alert  { animation: fadeUp .35s ease both; }
</style>
</head>

<body>
<div class="page-wrapper">

    <!-- SIDEBAR -->
    <div class="sidebar-col">
        <?php include 'billing_sidebar.php'; ?>
    </div>

    <!-- MAIN -->
    <div class="main-col">

        <!-- PAGE HEADER -->
        <div class="page-header">
            <div class="breadcrumb-trail">
                <span>Billing</span>
                <i class="bi bi-chevron-right"></i>
                <span>Summary</span>
            </div>
            <div class="page-title">Billing <span>Summary</span></div>
            <div class="page-subtitle">Review charges, discounts, and complete your payment below.</div>
        </div>

        <!-- PATIENT STRIP -->
        <div class="patient-strip">
            <div class="patient-avatar"><i class="bi bi-person-fill"></i></div>
            <div class="patient-meta">
                <div class="name"><?= htmlspecialchars(trim($patient['full_name'])) ?></div>
                <div class="txn">TXN# <?= htmlspecialchars($transaction_id) ?> &nbsp;·&nbsp; Billing #<?= str_pad($billing_id, 6, '0', STR_PAD_LEFT) ?></div>
            </div>
        </div>

        <!-- FULLY COVERED BANNER -->
        <?php if ($grand_total <= 0): ?>
        <div class="status-alert fully-covered">
            <i class="bi bi-shield-check-fill"></i>
            <div class="msg">
                This bill is fully covered — no payment required.
                <small>Insurance and discounts have settled the entire balance.</small>
            </div>
        </div>
        <?php endif; ?>

        <!-- SERVICES CARD -->
        <div class="card">
            <div class="card-head">
                <div class="icon slate"><i class="bi bi-clipboard2-pulse"></i></div>
                <div>
                    <div class="card-head-title">Services &amp; Charges</div>
                    <div class="card-head-sub"><?= count($items) ?> item<?= count($items) !== 1 ? 's' : '' ?> billed</div>
                </div>
            </div>
            <div class="card-body">
                <table class="services-table">
                    <thead>
                        <tr>
                            <th>Service</th>
                            <th>Description</th>
                            <th class="td-right">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($items): foreach ($items as $item): ?>
                        <tr>
                            <td><div class="service-name"><?= htmlspecialchars($item['serviceName']) ?></div></td>
                            <td><div class="service-desc"><?= htmlspecialchars($item['description'] ?: '—') ?></div></td>
                            <td class="td-amount">₱ <?= number_format($item['total_price'], 2) ?></td>
                        </tr>
                        <?php endforeach; else: ?>
                        <tr class="empty-row"><td colspan="3">No services found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>

                <!-- TOTALS -->
                <div class="totals-block">
                    <div class="total-row subtotal">
                        <span>Subtotal</span>
                        <span>₱ <?= number_format($subtotal, 2) ?></span>
                    </div>

                    <?php if ($pwd_discount > 0): ?>
                    <div class="total-row discount">
                        <span><i class="bi bi-tag-fill"></i> &nbsp;PWD / Senior Discount (20%)</span>
                        <span>− ₱ <?= number_format($pwd_discount, 2) ?></span>
                    </div>
                    <?php endif; ?>

                    <?php if ($insurance_covered > 0): ?>
                    <div class="total-row insurance">
                        <span><i class="bi bi-shield-plus-fill"></i> &nbsp;Insurance Coverage</span>
                        <span>− ₱ <?= number_format($insurance_covered, 2) ?></span>
                    </div>
                    <?php endif; ?>

                    <div class="total-row grand">
                        <span>Total Amount Due</span>
                        <span class="t-amount">₱ <?= number_format($grand_total, 2) ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- PAYMENT METHODS CARD -->
        <?php if ($grand_total > 0 && $payLinkUrl): ?>
        <div class="card">
            <div class="card-head">
                <div class="icon emerald"><i class="bi bi-wallet2"></i></div>
                <div>
                    <div class="card-head-title">Select Payment Method</div>
                    <div class="card-head-sub">Choose how you'd like to settle this bill</div>
                </div>
            </div>
            <div class="card-body">
                <div class="payment-grid">

                    <!-- ONLINE -->
                    <a href="<?= htmlspecialchars($payLinkUrl) ?>" target="_blank" class="pay-card online">
                        <div class="pay-icon"><i class="bi bi-credit-card-2-front-fill"></i></div>
                        <div class="pay-title">Pay Online</div>
                        <div class="pay-amount">₱ <?= number_format($grand_total, 2) ?></div>
                        <div class="pay-sub">Card &nbsp;·&nbsp; GCash &nbsp;·&nbsp; GrabPay<br>Bank Transfer</div>
                    </a>

                    <!-- CASH -->
                    <div class="pay-card cash">
                        <form method="POST"
                              onsubmit="return confirm('Confirm cash payment of ₱<?= number_format($grand_total, 2) ?>?');">
                            <input type="hidden" name="payment_method" value="cash">
                            <button type="submit">
                                <div class="pay-icon"><i class="bi bi-cash-coin"></i></div>
                                <div class="pay-title">Pay with Cash</div>
                                <div class="pay-amount">₱ <?= number_format($grand_total, 2) ?></div>
                                <div class="pay-sub" style="color:var(--gray-400);">
                                    Present at the cashier<br>or billing office
                                </div>
                            </button>
                        </form>
                    </div>

                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- PAYMENT HISTORY CARD -->
        <div class="card">
            <div class="card-head">
                <div class="icon amber"><i class="bi bi-clock-history"></i></div>
                <div>
                    <div class="card-head-title">Payment History</div>
                    <div class="card-head-sub">All transactions linked to this billing record</div>
                </div>
            </div>
            <div class="card-body">
                <table class="history-table">
                    <thead>
                        <tr>
                            <th>Reference</th>
                            <th>Amount</th>
                            <th>Method</th>
                            <th>Status</th>
                            <th>Paid At</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($history): foreach ($history as $h): ?>
                        <tr>
                            <td><span class="ref-code"><?= htmlspecialchars($h['payment_id']) ?></span></td>
                            <td><strong>₱ <?= number_format($h['amount'], 2) ?></strong></td>
                            <td>
                                <?php if (strpos($h['payment_id'], 'CASH-') === 0): ?>
                                    <span class="badge cash-m"><i class="bi bi-cash"></i> Cash</span>
                                <?php else: ?>
                                    <span class="badge online-m"><i class="bi bi-globe"></i> Online</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($h['status'] === 'Paid'): ?>
                                    <span class="badge paid"><i class="bi bi-check-circle-fill"></i> Paid</span>
                                <?php else: ?>
                                    <span class="badge pending"><i class="bi bi-hourglass-split"></i> Pending</span>
                                <?php endif; ?>
                            </td>
                            <td><?= $h['paid_at'] ? htmlspecialchars(date('M d, Y  H:i', strtotime($h['paid_at']))) : '<span style="color:var(--gray-400)">—</span>' ?></td>
                        </tr>
                        <?php endforeach; else: ?>
                        <tr class="empty-row"><td colspan="5">No payment transactions recorded yet.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div><!-- /.main-col -->
</div><!-- /.page-wrapper -->
</body>
</html>