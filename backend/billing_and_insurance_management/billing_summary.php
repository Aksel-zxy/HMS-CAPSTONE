<?php
include '../../SQL/config.php';
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/vendor/autoload.php';
use GuzzleHttp\Client;

/* =====================================================
   PAYMONGO CONFIG
===================================================== */
define('PAYMONGO_SECRET_KEY', 'sk_test_akT1ZW6za7m6FC9S9VqYNiVV');
define('PAYMONGO_API_BASE',   'https://api.paymongo.com/v1/');

$paymongoClient = new Client([
    'base_uri' => PAYMONGO_API_BASE,
    'headers'  => [
        'Accept'        => 'application/json',
        'Content-Type'  => 'application/json',
        'Authorization' => 'Basic ' . base64_encode(PAYMONGO_SECRET_KEY . ':'),
    ],
    'timeout' => 8,
    'verify'  => false,
]);

/* =====================================================
   RESOLVE billing_id + patient_id FROM URL
===================================================== */
$billing_id = (int)($_GET['billing_id'] ?? 0);
$patient_id = (int)($_GET['patient_id'] ?? 0);

if (!$billing_id && $patient_id) {
    $s = $conn->prepare("
        SELECT billing_id FROM billing_records
        WHERE patient_id = ? AND status NOT IN ('Paid','Cancelled')
        ORDER BY billing_id DESC LIMIT 1
    ");
    $s->bind_param("i", $patient_id);
    $s->execute();
    $row = $s->get_result()->fetch_assoc();
    if ($row) $billing_id = (int)$row['billing_id'];
}

if (!$billing_id) die("No billing record found.");

/* =====================================================
   FETCH BILLING RECORD
===================================================== */
$stmt = $conn->prepare("
    SELECT br.*, pi.patient_id AS pid,
           CONCAT(pi.fname,' ',IFNULL(NULLIF(TRIM(pi.mname),''),''),' ',pi.lname) AS full_name,
           pi.phone_number, pi.address
    FROM billing_records br
    INNER JOIN patientinfo pi ON pi.patient_id = br.patient_id
    WHERE br.billing_id = ?
");
$stmt->bind_param("i", $billing_id);
$stmt->execute();
$billing = $stmt->get_result()->fetch_assoc();
if (!$billing) die("Billing record #$billing_id not found.");

$patient_id     = (int)$billing['pid'];
$status         = $billing['status'];
$transaction_id = $billing['transaction_id'];
$existing_link  = $billing['paymongo_link_id'];
$patient_name   = trim($billing['full_name']);

/* =====================================================
   CHECK PAYMONGO FOR PAID STATUS IF LINK EXISTS
===================================================== */
if ($existing_link && $status !== 'Paid') {
    try {
        $linkResp   = $paymongoClient->get("links/{$existing_link}");
        $linkData   = json_decode($linkResp->getBody(), true);
        $linkStatus = $linkData['data']['attributes']['status'] ?? null;

        if ($linkStatus === 'paid') {
            $s = $conn->prepare("UPDATE billing_records SET status='Paid', payment_method='PAYMONGO', payment_date=NOW(), paymongo_link_id=? WHERE billing_id=?");
            $s->bind_param("si", $existing_link, $billing_id); $s->execute();

            $s2 = $conn->prepare("UPDATE patient_receipt SET status='Paid', payment_reference=?, paymongo_reference=? WHERE billing_id=?");
            $s2->bind_param("ssi", $existing_link, $existing_link, $billing_id); $s2->execute();

            $status = 'Paid';
        }
    } catch (Exception $e) {
        error_log("PayMongo link check error: " . $e->getMessage());
    }
}

/* =====================================================
   FETCH PATIENT RECEIPT
===================================================== */
$receipt = null;
$stmt = $conn->prepare("SELECT * FROM patient_receipt WHERE billing_id = ? LIMIT 1");
$stmt->bind_param("i", $billing_id);
$stmt->execute();
$receipt = $stmt->get_result()->fetch_assoc();

/* =====================================================
   FETCH BILLING ITEMS
===================================================== */
$items    = [];
$subtotal = 0;

$stmt = $conn->prepare("
    SELECT bi.item_id, bi.quantity, bi.unit_price, bi.total_price
    FROM billing_items bi
    WHERE bi.billing_id = ?
    ORDER BY bi.item_id ASC
");
$stmt->bind_param("i", $billing_id);
$stmt->execute();
$raw_items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

foreach ($raw_items as $item) {
    $name = 'Billing Item';
    $desc = '';

    $ns = $conn->prepare("SELECT serviceName, description FROM dl_services WHERE price = ? LIMIT 1");
    $ns->bind_param("d", $item['unit_price']);
    $ns->execute();
    $svc = $ns->get_result()->fetch_assoc();
    if ($svc) { $name = $svc['serviceName']; $desc = $svc['description'] ?? ''; }

    if (!$svc) {
        $np = $conn->prepare("SELECT procedure_name AS serviceName FROM dnm_procedure_list WHERE price = ? LIMIT 1");
        $np->bind_param("d", $item['unit_price']);
        $np->execute();
        $prc = $np->get_result()->fetch_assoc();
        if ($prc) { $name = $prc['serviceName']; $desc = 'Procedure'; }
    }

    if (!$svc && !isset($prc)) {
        $nm = $conn->prepare("SELECT med_name AS serviceName, dosage FROM pharmacy_inventory WHERE unit_price = ? LIMIT 1");
        $nm->bind_param("d", $item['unit_price']);
        $nm->execute();
        $med = $nm->get_result()->fetch_assoc();
        if ($med) {
            $name = $med['serviceName'] . ($med['dosage'] ? ' ('.$med['dosage'].')' : '');
            $desc = 'Medicine';
        }
    }

    $items[]   = array_merge($item, ['serviceName' => $name, 'description' => $desc]);
    $subtotal += (float)$item['total_price'];
}

/* =====================================================
   CALCULATE AMOUNTS
===================================================== */
$pwd_discount      = 0;
$insurance_covered = 0;
$grand_total       = $subtotal;
$out_of_pocket     = $subtotal;

if ($receipt) {
    $pwd_discount      = (float)($receipt['total_discount']      ?? 0);
    $insurance_covered = (float)($receipt['insurance_covered']   ?? 0);
    $grand_total       = (float)($receipt['grand_total']         ?? ($subtotal - $pwd_discount - $insurance_covered));
    $out_of_pocket     = (float)($receipt['total_out_of_pocket'] ?? $grand_total);
} else {
    $insurance_covered = (float)($billing['insurance_covered'] ?? 0);
    $grand_total       = (float)($billing['grand_total']       ?? $subtotal);
    $out_of_pocket     = $grand_total;
}
if ($out_of_pocket < 0) $out_of_pocket = 0;
if ($grand_total   < 0) $grand_total   = 0;

/* =====================================================
   AUTO-MARK PAID IF FULLY COVERED BY INSURANCE
   (grand_total = 0 means insurance/discount covers everything)
===================================================== */
$auto_paid_by_insurance = false;
if ($grand_total <= 0 && $status !== 'Paid') {
    $ins_ref = 'INS-COVERED-' . $billing_id;

    $s = $conn->prepare("
        UPDATE billing_records
        SET status='Paid', payment_method='Insurance', payment_date=NOW(), paid_amount=?
        WHERE billing_id=?
    ");
    $covered_amount = $subtotal; // the full amount was covered
    $s->bind_param("di", $covered_amount, $billing_id);
    $s->execute();

    $s2 = $conn->prepare("
        UPDATE patient_receipt
        SET status='Paid', payment_method='Insurance', payment_reference=?
        WHERE billing_id=?
    ");
    $s2->bind_param("si", $ins_ref, $billing_id);
    $s2->execute();

    $status = 'Paid';
    $auto_paid_by_insurance = true;
}

/* =====================================================
   HANDLE CASH PAYMENT
===================================================== */
if (isset($_POST['payment_method']) && $_POST['payment_method'] === 'cash' && $grand_total > 0) {
    $cash_ref = 'CASH-' . time();
    $desc = "Billing #{$billing_id} — Cash Payment — {$patient_name}";

    $s = $conn->prepare("
        INSERT INTO paymongo_payments (payment_id, amount, status, payment_method, remarks, billing_id, patient_id, paid_at)
        VALUES (?, ?, 'Paid', 'CASH', ?, ?, ?, NOW())
    ");
    $s->bind_param("sdsii", $cash_ref, $grand_total, $desc, $billing_id, $patient_id);
    $s->execute();

    $s2 = $conn->prepare("UPDATE billing_records SET status='Paid', payment_method='Cash', payment_date=NOW(), paymongo_payment_id=?, paid_amount=? WHERE billing_id=?");
    $s2->bind_param("sdi", $cash_ref, $grand_total, $billing_id); $s2->execute();

    $s3 = $conn->prepare("UPDATE patient_receipt SET status='Paid', payment_method='Cash', payment_reference=? WHERE billing_id=?");
    $s3->bind_param("si", $cash_ref, $billing_id); $s3->execute();

    header("Location: patient_billing.php?success=cash_payment"); exit;
}

/* =====================================================
   CREATE / REUSE PAYMONGO LINK
===================================================== */
$payLinkUrl = null;

if ($grand_total > 0 && $status !== 'Paid') {
    if ($existing_link) {
        try {
            $linkResp   = $paymongoClient->get("links/{$existing_link}");
            $linkData   = json_decode($linkResp->getBody(), true);
            $payLinkUrl = $linkData['data']['attributes']['checkout_url'] ?? "https://checkout.paymongo.com/links/{$existing_link}";
        } catch (Exception $e) {
            $payLinkUrl = "https://checkout.paymongo.com/links/{$existing_link}";
        }
    } else {
        try {
            $payload = ['data' => ['attributes' => [
                'amount'      => (int)round($grand_total * 100),
                'currency'    => 'PHP',
                'description' => "Hospital Bill #{$billing_id}",
                'remarks'     => "Billing #{$billing_id} TXN:{$transaction_id}",
            ]]];

            $response   = $paymongoClient->post('links', ['json' => $payload]);
            $body       = json_decode($response->getBody(), true);
            $payLinkUrl = $body['data']['attributes']['checkout_url'] ?? null;
            $link_id    = $body['data']['id'] ?? null;

            if ($link_id) {
                $remarks = "Billing #{$billing_id} TXN:{$transaction_id}";

                $s = $conn->prepare("
                    INSERT IGNORE INTO paymongo_payments
                        (payment_id, amount, status, payment_method, remarks, billing_id, patient_id)
                    VALUES (?, ?, 'Pending', 'PAYLINK', ?, ?, ?)
                ");
                $s->bind_param("sdsii", $link_id, $grand_total, $remarks, $billing_id, $patient_id);
                $s->execute();

                $s2 = $conn->prepare("
                    UPDATE billing_records
                    SET paymongo_link_id = ?, paymongo_reference_number = ?
                    WHERE billing_id = ?
                ");
                $s2->bind_param("ssi", $link_id, $transaction_id, $billing_id); $s2->execute();

                $s3 = $conn->prepare("
                    INSERT INTO patient_receipt (patient_id, billing_id, status, paymongo_reference, payment_reference)
                    VALUES (?, ?, 'Pending', ?, ?)
                    ON DUPLICATE KEY UPDATE paymongo_reference = VALUES(paymongo_reference)
                ");
                $s3->bind_param("iiss", $patient_id, $billing_id, $link_id, $transaction_id); $s3->execute();
            }
        } catch (Exception $e) {
            error_log("PayMongo error: " . $e->getMessage());
        }
    }
}

/* =====================================================
   PAYMENT HISTORY
===================================================== */
$history = [];
$s = $conn->prepare("
    SELECT payment_id, amount, status, payment_method, paid_at
    FROM paymongo_payments
    WHERE billing_id = ?
       OR remarks LIKE ?
    ORDER BY paid_at DESC
");
$like = "%Billing #$billing_id%";
$s->bind_param("is", $billing_id, $like); $s->execute();
$history = $s->get_result()->fetch_all(MYSQLI_ASSOC);

/* Receipt ID for view link */
$receipt_id = $receipt['receipt_id'] ?? null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Billing Summary — #<?= htmlspecialchars($transaction_id) ?></title>
<link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display&family=DM+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
:root {
    --navy:       #0b1d3a;
    --navy-mid:   #1a3a6e;
    --teal:       #0e7c7b;
    --teal-bg:    #e6f4f4;
    --gold:       #c9954c;
    --gold-light: #fdf4e7;
    --green:      #059669;
    --green-bg:   #d1fae5;
    --amber:      #d97706;
    --amber-bg:   #fef3c7;
    --red:        #dc2626;
    --red-bg:     #fee2e2;
    --sky:        #0284c7;
    --sky-bg:     #e0f2fe;
    --gray-50:    #f8fafc;
    --gray-100:   #f1f5f9;
    --gray-200:   #e2e8f0;
    --gray-400:   #94a3b8;
    --gray-600:   #475569;
    --gray-800:   #1e293b;
    --white:      #ffffff;
    --shadow:     0 2px 16px rgba(11,29,58,.08);
    --shadow-md:  0 6px 28px rgba(11,29,58,.12);
    --shadow-lg:  0 16px 48px rgba(11,29,58,.16);
    --radius:     14px;
    --radius-sm:  8px;
    --ff-head:    'DM Serif Display', serif;
    --ff-body:    'DM Sans', sans-serif;
}
body {
    font-family: var(--ff-body);
    background: linear-gradient(145deg, #e8eef8 0%, #f1f5f9 55%, #e6f4f1 100%);
    min-height: 100vh;
    color: var(--gray-800);
    font-size: 14px;
    line-height: 1.6;
}
.cw {
    margin-left: 250px;
    padding: 40px 32px 80px;
    transition: margin-left .3s ease;
}
.cw.sidebar-collapsed { margin-left: 0; }
.billing-center { max-width: 760px; margin: 0 auto; }

/* TOP NAV */
.top-nav {
    display: flex; align-items: center; justify-content: space-between;
    margin-bottom: 28px; flex-wrap: wrap; gap: 12px;
}
.btn-back {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 8px 16px; background: var(--white); color: var(--gray-600);
    border: 1.5px solid var(--gray-200); border-radius: var(--radius-sm);
    font-family: var(--ff-body); font-size: .83rem; font-weight: 600;
    text-decoration: none; transition: all .15s;
}
.btn-back:hover { border-color: var(--navy); color: var(--navy); background: #f0f4f8; }
.page-title-block { text-align: center; flex: 1; }
.page-title-block .title { font-family: var(--ff-head); font-size: 1.75rem; color: var(--navy); line-height: 1.1; }
.page-title-block .subtitle { font-size: .83rem; color: var(--gray-400); margin-top: 3px; }
.btn-view-receipt {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 8px 16px; background: var(--navy); color: var(--white);
    border: none; border-radius: var(--radius-sm);
    font-family: var(--ff-body); font-size: .83rem; font-weight: 600;
    text-decoration: none; transition: all .15s;
}
.btn-view-receipt:hover { background: var(--navy-mid); }

/* PATIENT BANNER */
.patient-banner {
    background: var(--navy); border-radius: var(--radius);
    padding: 20px 28px; display: flex; align-items: center; gap: 16px;
    margin-bottom: 24px; box-shadow: var(--shadow-md);
    position: relative; overflow: hidden;
}
.patient-banner::after {
    content: ''; position: absolute; right: -30px; top: -30px;
    width: 120px; height: 120px; border-radius: 50%;
    background: rgba(14,124,123,.18); pointer-events: none;
}
.pat-av {
    width: 50px; height: 50px; border-radius: 50%;
    background: rgba(255,255,255,.12); display: flex; align-items: center;
    justify-content: center; font-size: 1.4rem; color: rgba(255,255,255,.85);
    flex-shrink: 0; border: 2px solid rgba(255,255,255,.2);
}
.pat-info .name { font-family: var(--ff-head); font-size: 1.1rem; color: var(--white); }
.pat-info .meta { font-size: .78rem; color: rgba(255,255,255,.5); margin-top: 2px; font-family: monospace; letter-spacing: .4px; }
.pat-badge {
    margin-left: auto; padding: 5px 14px; border-radius: 999px;
    font-size: .75rem; font-weight: 700; letter-spacing: .3px;
}
.pat-badge.pending { background: var(--amber-bg); color: var(--amber); }
.pat-badge.paid    { background: var(--green-bg); color: var(--green); }

/* ── PAID / INSURANCE COVERED BANNER ── */
.fully-paid-banner {
    background: linear-gradient(135deg, #059669 0%, #10b981 100%);
    border-radius: var(--radius);
    padding: 28px 32px;
    display: flex; align-items: flex-start; gap: 18px;
    margin-bottom: 24px;
    box-shadow: 0 8px 32px rgba(5,150,105,.25);
    color: #fff;
    position: relative; overflow: hidden;
}
.fully-paid-banner::before {
    content: '';
    position: absolute; right: -20px; bottom: -20px;
    width: 140px; height: 140px; border-radius: 50%;
    background: rgba(255,255,255,.08);
}
.paid-banner-icon {
    width: 56px; height: 56px; border-radius: 50%;
    background: rgba(255,255,255,.2);
    display: flex; align-items: center; justify-content: center;
    font-size: 1.8rem; flex-shrink: 0;
}
.paid-banner-title {
    font-family: var(--ff-head); font-size: 1.4rem;
    color: #fff; line-height: 1.1; margin-bottom: 6px;
}
.paid-banner-sub { font-size: .85rem; color: rgba(255,255,255,.8); line-height: 1.5; }
.paid-banner-badge {
    margin-left: auto; flex-shrink: 0;
    background: rgba(255,255,255,.2); padding: 6px 16px;
    border-radius: 999px; font-size: .8rem; font-weight: 700;
    color: #fff; white-space: nowrap;
    border: 1.5px solid rgba(255,255,255,.3);
}
.btn-back-billing {
    display: inline-flex; align-items: center; gap: 6px;
    margin-top: 14px; padding: 9px 20px;
    background: rgba(255,255,255,.2); color: #fff;
    border: 1.5px solid rgba(255,255,255,.35);
    border-radius: var(--radius-sm); font-family: var(--ff-body);
    font-size: .85rem; font-weight: 700; text-decoration: none;
    transition: all .15s;
}
.btn-back-billing:hover { background: rgba(255,255,255,.3); color: #fff; }

/* CARD */
.card {
    background: var(--white); border-radius: var(--radius);
    box-shadow: var(--shadow); margin-bottom: 20px;
    overflow: hidden; border: 1px solid var(--gray-200);
    animation: slideUp .35s ease both;
}
.card:nth-child(2) { animation-delay: .05s; }
.card:nth-child(3) { animation-delay: .10s; }
.card:nth-child(4) { animation-delay: .15s; }
@keyframes slideUp {
    from { opacity: 0; transform: translateY(16px); }
    to   { opacity: 1; transform: translateY(0); }
}
.card-header {
    padding: 16px 24px; border-bottom: 1px solid var(--gray-100);
    display: flex; align-items: center; gap: 12px; background: var(--gray-50);
}
.card-icon {
    width: 36px; height: 36px; border-radius: var(--radius-sm);
    display: flex; align-items: center; justify-content: center;
    font-size: 16px; flex-shrink: 0;
}
.icon-navy  { background: var(--navy);     color: var(--white); }
.icon-teal  { background: var(--teal-bg);  color: var(--teal);  }
.icon-amber { background: var(--amber-bg); color: var(--amber); }
.icon-green { background: var(--green-bg); color: var(--green); }
.card-title { font-family: var(--ff-head); font-size: 1rem; color: var(--navy); }
.card-sub   { font-size: .75rem; color: var(--gray-400); margin-top: 1px; }

/* ITEMS TABLE */
.items-table { width: 100%; border-collapse: collapse; }
.items-table thead th {
    padding: 11px 20px; font-size: 10px; font-weight: 700;
    text-transform: uppercase; letter-spacing: 1.2px; color: var(--gray-400);
    text-align: left; border-bottom: 1px solid var(--gray-200); white-space: nowrap;
}
.items-table thead th:last-child { text-align: right; }
.items-table tbody td { padding: 13px 20px; border-bottom: 1px solid var(--gray-100); vertical-align: top; }
.items-table tbody tr:last-child td { border-bottom: none; }
.items-table tbody tr:hover { background: var(--gray-50); }
.item-name { font-weight: 600; color: var(--navy); font-size: 13.5px; }
.item-desc { font-size: 11.5px; color: var(--gray-400); margin-top: 2px; }
.td-r { text-align: right; font-weight: 700; color: var(--navy); }
.td-c { text-align: center; color: var(--gray-600); }

/* TOTALS */
.totals-wrap { padding: 8px 20px 20px; }
.tot-row {
    display: flex; justify-content: space-between; align-items: center;
    padding: 10px 14px; border-radius: var(--radius-sm); margin-bottom: 5px; font-size: 13.5px;
}
.tot-row.sub   { color: var(--gray-600); }
.tot-row.disc  { background: var(--amber-bg); color: var(--amber); font-weight: 600; }
.tot-row.ins   { background: var(--sky-bg);   color: var(--sky);   font-weight: 600; }
.tot-row.grand { background: var(--navy); color: var(--white); font-weight: 700; font-size: 1rem; margin-top: 10px; border-radius: var(--radius-sm); }
.tot-row.grand .tot-amt { font-family: var(--ff-head); font-size: 1.4rem; color: var(--gold); }
.tot-row.covered { background: var(--green-bg); color: var(--green); font-weight: 700; font-size: 1rem; margin-top: 10px; border-radius: var(--radius-sm); }

/* PAYMENT METHODS */
.pay-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 18px; padding: 24px; }
.pay-option {
    border: 2px solid var(--gray-200); border-radius: var(--radius);
    padding: 28px 20px; text-align: center; display: flex;
    flex-direction: column; align-items: center; gap: 10px;
    transition: all .2s ease; cursor: pointer; background: var(--white);
    text-decoration: none; color: inherit;
}
.pay-option:hover { transform: translateY(-3px); box-shadow: var(--shadow-md); text-decoration: none; }
.pay-option.online {
    background: linear-gradient(150deg, var(--navy) 0%, var(--navy-mid) 100%);
    border-color: var(--navy); color: var(--white);
}
.pay-option.online:hover { border-color: #2563eb; box-shadow: 0 8px 28px rgba(11,29,58,.25); }
.pay-option.cash { border-color: var(--green); }
.pay-option.cash:hover { background: var(--green-bg); }
.pay-ico { width: 58px; height: 58px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; }
.online .pay-ico { background: rgba(255,255,255,.12); color: var(--white); }
.cash   .pay-ico { background: var(--green-bg);       color: var(--green); }
.pay-label  { font-family: var(--ff-head); font-size: 1.05rem; }
.online .pay-label { color: var(--white); }
.cash   .pay-label { color: var(--navy); }
.pay-amt { font-family: var(--ff-head); font-size: 1.5rem; font-weight: 700; }
.online .pay-amt { color: var(--gold); }
.cash   .pay-amt { color: var(--green); }
.pay-note { font-size: 11.5px; line-height: 1.5; }
.online .pay-note { color: rgba(255,255,255,.55); }
.cash   .pay-note { color: var(--gray-400); }
.pay-option.cash form { width: 100%; }
.pay-option.cash button {
    background: none; border: none; width: 100%;
    display: flex; flex-direction: column; align-items: center; gap: 10px;
    cursor: pointer; padding: 0;
}

/* HISTORY TABLE */
.hist-table { width: 100%; border-collapse: collapse; }
.hist-table thead th {
    padding: 11px 20px; font-size: 10px; font-weight: 700;
    text-transform: uppercase; letter-spacing: 1px; color: var(--gray-400);
    border-bottom: 1px solid var(--gray-200); white-space: nowrap;
}
.hist-table td { padding: 13px 20px; border-bottom: 1px solid var(--gray-100); font-size: 13px; vertical-align: middle; }
.hist-table tbody tr:last-child td { border-bottom: none; }
.hist-table tbody tr:hover { background: var(--gray-50); }
.ref-chip { font-family: monospace; font-size: 11.5px; color: var(--gray-600); background: var(--gray-100); padding: 2px 8px; border-radius: 4px; }
.hbadge { display: inline-flex; align-items: center; gap: 4px; padding: 3px 10px; border-radius: 999px; font-size: 11px; font-weight: 700; }
.hbadge.paid     { background: var(--green-bg); color: var(--green); }
.hbadge.pending  { background: var(--amber-bg); color: var(--amber); }
.hbadge.cash-m   { background: var(--gray-100); color: var(--gray-600); }
.hbadge.online-m { background: var(--sky-bg);   color: var(--sky); }
.hbadge.ins-m    { background: #dbeafe;          color: #1d4ed8; }
.empty-hist td   { text-align: center; padding: 36px; color: var(--gray-400); font-style: italic; }

/* Auto-sync notice */
.sync-notice {
    background: var(--sky-bg); border: 1px solid #bae6fd; border-radius: var(--radius-sm);
    padding: 10px 16px; font-size: .82rem; color: var(--sky);
    display: flex; align-items: center; gap: 8px; margin-bottom: 20px;
}

@media(max-width: 768px) {
    .cw { margin-left: 0; padding: 24px 14px 60px; }
    .pay-grid { grid-template-columns: 1fr; }
    .top-nav { flex-direction: column; align-items: stretch; text-align: center; }
    .fully-paid-banner { flex-direction: column; }
    .paid-banner-badge { margin-left: 0; }
}
@media(max-width: 480px) {
    .billing-center { padding: 0; }
}
</style>
</head>
<body>

<?php include 'billing_sidebar.php'; ?>

<div class="cw" id="mainCw">
<div class="billing-center">

    <!-- TOP NAV -->
    <div class="top-nav">
        <a href="patient_billing.php" class="btn-back">
            <i class="bi bi-arrow-left"></i> Back to Billing
        </a>
        <div class="page-title-block">
            <div class="title">Billing Summary</div>
            <div class="subtitle">Review charges and complete payment</div>
        </div>
        <?php if ($receipt_id): ?>
        <a href="print_receipt.php?receipt_id=<?= (int)$receipt_id ?>" target="_blank" class="btn-view-receipt">
            <i class="bi bi-receipt"></i> View Receipt
        </a>
        <?php else: ?>
        <div style="width:120px;"></div>
        <?php endif; ?>
    </div>

    <!-- PATIENT BANNER -->
    <div class="patient-banner">
        <div class="pat-av"><i class="bi bi-person-fill"></i></div>
        <div class="pat-info">
            <div class="name"><?= htmlspecialchars($patient_name) ?></div>
            <div class="meta">
                Billing #<?= str_pad($billing_id, 6, '0', STR_PAD_LEFT) ?>
                &nbsp;·&nbsp;
                TXN <?= htmlspecialchars($transaction_id ?? '—') ?>
            </div>
        </div>
        <span class="pat-badge <?= $status === 'Paid' ? 'paid' : 'pending' ?>">
            <?= $status === 'Paid' ? '✓ Paid' : '⏳ Pending' ?>
        </span>
    </div>

    <?php if ($existing_link && $status !== 'Paid'): ?>
    <div class="sync-notice">
        <i class="bi bi-arrow-repeat"></i>
        Payment link active. After paying online, return to
        <a href="patient_billing.php" style="color:inherit;font-weight:700;">Patient Billing</a>
        and click <strong>Sync Payments</strong> to update status.
    </div>
    <?php endif; ?>

    <!-- ── INSURANCE FULLY PAID BANNER ── -->
    <?php if ($auto_paid_by_insurance): ?>
    <div class="fully-paid-banner">
        <div class="paid-banner-icon"><i class="bi bi-shield-fill-check"></i></div>
        <div style="flex:1;">
            <div class="paid-banner-title">Bill Fully Covered by Insurance</div>
            <div class="paid-banner-sub">
                This patient's bill has been fully covered by their insurance plan.<br>
                No additional payment is required. The billing record has been automatically marked as <strong>Paid</strong>
                and removed from the pending billing queue.
            </div>
            <a href="patient_billing.php" class="btn-back-billing">
                <i class="bi bi-arrow-left"></i> Return to Patient Billing
            </a>
        </div>
        <span class="paid-banner-badge"><i class="bi bi-check-circle-fill"></i> Auto-Settled</span>
    </div>
    <?php elseif ($status === 'Paid' && $grand_total <= 0): ?>
    <!-- Already paid + fully covered (revisit) -->
    <div class="fully-paid-banner">
        <div class="paid-banner-icon"><i class="bi bi-shield-fill-check"></i></div>
        <div style="flex:1;">
            <div class="paid-banner-title">Bill Fully Covered by Insurance</div>
            <div class="paid-banner-sub">
                This bill has already been settled. Insurance covered the entire amount — no payment needed.
            </div>
            <a href="patient_billing.php" class="btn-back-billing">
                <i class="bi bi-arrow-left"></i> Return to Patient Billing
            </a>
        </div>
        <span class="paid-banner-badge"><i class="bi bi-check-circle-fill"></i> Settled</span>
    </div>
    <?php elseif ($status === 'Paid'): ?>
    <!-- Paid by cash or online -->
    <div class="fully-paid-banner" style="background: linear-gradient(135deg, #1d4ed8 0%, #2563eb 100%); box-shadow: 0 8px 32px rgba(29,78,216,.25);">
        <div class="paid-banner-icon"><i class="bi bi-check-circle-fill"></i></div>
        <div style="flex:1;">
            <div class="paid-banner-title">Payment Completed</div>
            <div class="paid-banner-sub">
                This billing has already been paid and settled. No further action is required.
            </div>
            <a href="patient_billing.php" class="btn-back-billing">
                <i class="bi bi-arrow-left"></i> Return to Patient Billing
            </a>
        </div>
        <span class="paid-banner-badge"><i class="bi bi-check-circle-fill"></i> Paid</span>
    </div>
    <?php endif; ?>

    <!-- SERVICES CARD -->
    <div class="card">
        <div class="card-header">
            <div class="card-icon icon-navy"><i class="bi bi-clipboard2-pulse"></i></div>
            <div>
                <div class="card-title">Services &amp; Charges</div>
                <div class="card-sub"><?= count($items) ?> item<?= count($items) !== 1 ? 's' : '' ?> billed</div>
            </div>
        </div>
        <table class="items-table">
            <thead>
                <tr>
                    <th>Service / Item</th>
                    <th>Description</th>
                    <th class="td-c">Qty</th>
                    <th style="text-align:right;">Amount</th>
                </tr>
            </thead>
            <tbody>
            <?php if ($items): foreach ($items as $item): ?>
            <tr>
                <td><div class="item-name"><?= htmlspecialchars($item['serviceName']) ?></div></td>
                <td><div class="item-desc"><?= htmlspecialchars($item['description'] ?: '—') ?></div></td>
                <td class="td-c"><?= (int)($item['quantity'] ?? 1) ?></td>
                <td class="td-r">₱<?= number_format($item['total_price'], 2) ?></td>
            </tr>
            <?php endforeach; else: ?>
            <tr><td colspan="4" style="text-align:center;padding:32px;color:var(--gray-400);font-style:italic;">No billing items found.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>

        <!-- TOTALS -->
        <div class="totals-wrap">
            <div class="tot-row sub">
                <span>Subtotal</span>
                <span>₱<?= number_format($subtotal, 2) ?></span>
            </div>
            <?php if ($pwd_discount > 0): ?>
            <div class="tot-row disc">
                <span><i class="bi bi-tag-fill"></i> &nbsp;PWD / Senior Discount (20%)</span>
                <span>−₱<?= number_format($pwd_discount, 2) ?></span>
            </div>
            <?php endif; ?>
            <?php if ($insurance_covered > 0): ?>
            <div class="tot-row ins">
                <span><i class="bi bi-shield-plus-fill"></i> &nbsp;Insurance Coverage</span>
                <span>−₱<?= number_format($insurance_covered, 2) ?></span>
            </div>
            <?php endif; ?>

            <?php if ($grand_total <= 0): ?>
            <div class="tot-row covered">
                <span><i class="bi bi-shield-check-fill"></i> &nbsp;Fully Covered — No Payment Due</span>
                <span>₱0.00</span>
            </div>
            <?php else: ?>
            <div class="tot-row grand">
                <span>Total Amount Due</span>
                <span class="tot-amt">₱<?= number_format($grand_total, 2) ?></span>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- PAYMENT METHODS — only show if NOT paid and amount > 0 -->
    <?php if ($grand_total > 0 && $status !== 'Paid'): ?>
    <div class="card">
        <div class="card-header">
            <div class="card-icon icon-teal"><i class="bi bi-wallet2"></i></div>
            <div>
                <div class="card-title">Select Payment Method</div>
                <div class="card-sub">Choose how you'd like to settle this bill</div>
            </div>
        </div>
        <div class="pay-grid">

            <!-- ONLINE -->
            <?php if ($payLinkUrl): ?>
            <a href="<?= htmlspecialchars($payLinkUrl) ?>" target="_blank" class="pay-option online">
                <div class="pay-ico"><i class="bi bi-credit-card-2-front-fill"></i></div>
                <div class="pay-label">Pay Online</div>
                <div class="pay-amt">₱<?= number_format($grand_total, 2) ?></div>
                <div class="pay-note">GCash &nbsp;·&nbsp; Card &nbsp;·&nbsp; GrabPay<br>Maya &nbsp;·&nbsp; Bank Transfer</div>
            </a>
            <?php else: ?>
            <div class="pay-option online" style="opacity:.5;cursor:not-allowed;">
                <div class="pay-ico"><i class="bi bi-credit-card-2-front-fill"></i></div>
                <div class="pay-label">Pay Online</div>
                <div class="pay-note">Payment link unavailable.<br>Please use cash payment.</div>
            </div>
            <?php endif; ?>

            <!-- CASH -->
            <div class="pay-option cash">
                <form method="POST"
                      onsubmit="return confirm('Confirm cash payment of ₱<?= number_format($grand_total, 2) ?> for <?= htmlspecialchars(addslashes($patient_name)) ?>?');">
                    <input type="hidden" name="payment_method" value="cash">
                    <button type="submit">
                        <div class="pay-ico"><i class="bi bi-cash-coin"></i></div>
                        <div class="pay-label">Pay with Cash</div>
                        <div class="pay-amt">₱<?= number_format($grand_total, 2) ?></div>
                        <div class="pay-note">Present at the cashier<br>or billing office</div>
                    </button>
                </form>
            </div>

        </div>
    </div>
    <?php endif; ?>

    <!-- PAYMENT HISTORY CARD -->
    <div class="card">
        <div class="card-header">
            <div class="card-icon icon-amber"><i class="bi bi-clock-history"></i></div>
            <div>
                <div class="card-title">Payment History</div>
                <div class="card-sub">All transactions for Billing #<?= str_pad($billing_id, 6, '0', STR_PAD_LEFT) ?></div>
            </div>
        </div>
        <table class="hist-table">
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
                <td><span class="ref-chip"><?= htmlspecialchars($h['payment_id']) ?></span></td>
                <td><strong>₱<?= number_format($h['amount'], 2) ?></strong></td>
                <td>
                    <?php if (strpos($h['payment_id'], 'CASH-') === 0): ?>
                        <span class="hbadge cash-m"><i class="bi bi-cash"></i> Cash</span>
                    <?php elseif (strpos($h['payment_id'], 'INS-') === 0): ?>
                        <span class="hbadge ins-m"><i class="bi bi-shield-check"></i> Insurance</span>
                    <?php else: ?>
                        <span class="hbadge online-m"><i class="bi bi-globe"></i> Online</span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($h['status'] === 'Paid'): ?>
                        <span class="hbadge paid"><i class="bi bi-check-circle-fill"></i> Paid</span>
                    <?php else: ?>
                        <span class="hbadge pending"><i class="bi bi-hourglass-split"></i> Pending</span>
                    <?php endif; ?>
                </td>
                <td style="color:var(--gray-600);">
                    <?= $h['paid_at'] ? htmlspecialchars(date('M d, Y  H:i', strtotime($h['paid_at']))) : '<span style="color:var(--gray-400)">—</span>' ?>
                </td>
            </tr>
            <?php endforeach; else: ?>
            <?php if ($auto_paid_by_insurance || ($status === 'Paid' && $grand_total <= 0)): ?>
            <tr>
                <td><span class="ref-chip">INS-COVERED-<?= $billing_id ?></span></td>
                <td><strong>₱<?= number_format($subtotal, 2) ?></strong></td>
                <td><span class="hbadge ins-m"><i class="bi bi-shield-check"></i> Insurance</span></td>
                <td><span class="hbadge paid"><i class="bi bi-check-circle-fill"></i> Paid</span></td>
                <td style="color:var(--gray-600);"><?= date('M d, Y  H:i') ?></td>
            </tr>
            <?php else: ?>
            <tr class="empty-hist"><td colspan="5">No payment transactions recorded yet.</td></tr>
            <?php endif; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

</div><!-- /.billing-center -->
</div><!-- /.cw -->

<script>
/* Sidebar sync */
(function(){
    const sb = document.getElementById('mySidebar');
    const cw = document.getElementById('mainCw');
    if (!sb || !cw) return;
    function sync(){ cw.classList.toggle('sidebar-collapsed', sb.classList.contains('closed')); }
    new MutationObserver(sync).observe(sb, {attributes:true, attributeFilter:['class']});
    document.getElementById('sidebarToggle')?.addEventListener('click', () => requestAnimationFrame(sync));
    sync();
})();
</script>
</body>
</html>