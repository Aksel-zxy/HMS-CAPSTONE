<?php
session_start();
include '../../SQL/config.php';

/* ===============================
   VALIDATE RECEIPT
================================ */
$receipt_id = isset($_GET['receipt_id']) ? intval($_GET['receipt_id']) : 0;
if ($receipt_id <= 0) {
    die("Invalid receipt ID.");
}

/* ===============================
   FETCH RECEIPT + PATIENT
================================ */
$stmt = $conn->prepare("
    SELECT pr.*, 
           pi.fname, pi.mname, pi.lname,
           pi.phone_number, pi.address, pi.attending_doctor,
           br.total_amount, br.insurance_covered AS billing_insurance,
           br.out_of_pocket AS billing_out_of_pocket, br.grand_total AS billing_grand_total,
           br.status AS billing_status,
           br.billing_date
    FROM patient_receipt pr
    INNER JOIN patientinfo pi ON pr.patient_id = pi.patient_id
    LEFT JOIN billing_records br ON pr.billing_id = br.billing_id
    WHERE pr.receipt_id = ?
");
$stmt->bind_param("i", $receipt_id);
$stmt->execute();
$billing = $stmt->get_result()->fetch_assoc();

if (!$billing) {
    die("Receipt not found.");
}

$billing_id    = (int)$billing['billing_id'];
$patient_id    = (int)$billing['patient_id'];

$full_name = trim(
    $billing['fname'].' '.
    (!empty($billing['mname']) ? $billing['mname'].' ' : '').
    $billing['lname']
);

/* ===============================
   INSURANCE
================================ */
$stmt = $conn->prepare("
    SELECT * FROM patient_insurance
    WHERE full_name = ? AND status='Active'
    LIMIT 1
");
$stmt->bind_param("s", $full_name);
$stmt->execute();
$insurance = $stmt->get_result()->fetch_assoc();

/* ===============================
   DOCTOR
================================ */
$doctor = null;
if (!empty($billing['attending_doctor'])) {
    $stmt = $conn->prepare("SELECT * FROM hr_employees WHERE employee_id=?");
    $stmt->bind_param("i", $billing['attending_doctor']);
    $stmt->execute();
    $doctor = $stmt->get_result()->fetch_assoc();
}

/* ===============================
   BILLING ITEMS
================================ */
$billing_items  = [];
$total_charges  = 0;

$stmt = $conn->prepare("
    SELECT 
        bi.quantity,
        bi.unit_price,
        bi.total_price,
        ds.serviceName,
        ds.description
    FROM billing_items bi
    LEFT JOIN dl_services ds ON bi.service_id = ds.serviceID
    WHERE bi.billing_id = ?
");
$stmt->bind_param("i", $billing_id);
$stmt->execute();
$res = $stmt->get_result();

while ($row = $res->fetch_assoc()) {
    $billing_items[] = $row;
    $total_charges  += floatval($row['total_price']);
}

/* ===============================
   TOTALS
================================ */
$insurance_covered    = floatval($billing['insurance_covered'] ?? $billing['billing_insurance'] ?? 0);
$total_out_of_pocket  = max(0, $total_charges - $insurance_covered);

$is_paid         = $billing['billing_status'] === 'Paid';
$is_fully_covered = ($total_out_of_pocket <= 0 && $is_paid);

/* ===============================
   DOCTOR NAME
================================ */
$doctor_name = 'N/A';
if ($doctor) {
    $doc = $doctor['first_name'];
    if (!empty($doctor['middle_name'])) $doc .= ' '.$doctor['middle_name'];
    if (!empty($doctor['last_name']))   $doc .= ' '.$doctor['last_name'];
    if (!empty($doctor['suffix_name'])) $doc .= ', '.$doctor['suffix_name'];
    $doctor_name = $doc;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Invoice #INV-<?= $receipt_id ?> — Patient Billing</title>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;1,9..40,300&display=swap" rel="stylesheet">

<style>
/* ── RESET & BASE ── */
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

:root {
    --navy:       #0d2b45;
    --navy-mid:   #1a4a6e;
    --teal:       #0e7c7b;
    --teal-light: #e6f4f4;
    --gold:       #c9954c;
    --gold-light: #fdf4e7;
    --green:      #1a7a4a;
    --green-bg:   #e8f6ee;
    --red:        #b03a2e;
    --red-bg:     #fdecea;
    --gray-100:   #f7f8fa;
    --gray-200:   #eef0f3;
    --gray-300:   #d6dae0;
    --gray-500:   #8a9099;
    --gray-700:   #4a5261;
    --gray-900:   #1c222d;
    --white:      #ffffff;
    --shadow-sm:  0 2px 8px rgba(13,43,69,.08);
    --shadow-md:  0 6px 24px rgba(13,43,69,.12);
    --shadow-lg:  0 16px 48px rgba(13,43,69,.16);
    --radius:     12px;
    --radius-sm:  6px;
}

body {
    font-family: 'DM Sans', sans-serif;
    background: var(--gray-100);
    color: var(--gray-900);
    min-height: 100vh;
    padding: 40px 20px 80px;
    font-size: 14px;
    line-height: 1.6;
}

/* ── ACTION BAR ── */
.action-bar {
    max-width: 860px;
    margin: 0 auto 20px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
}

.action-bar .hospital-ref {
    font-family: 'Playfair Display', serif;
    font-size: 18px;
    color: var(--navy);
    letter-spacing: .5px;
}

.btn-print {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 10px 22px;
    background: var(--navy);
    color: var(--white);
    border: none;
    border-radius: var(--radius-sm);
    font-family: 'DM Sans', sans-serif;
    font-size: 13px;
    font-weight: 600;
    letter-spacing: .4px;
    cursor: pointer;
    transition: background .2s, transform .15s;
}
.btn-print:hover { background: var(--navy-mid); transform: translateY(-1px); }
.btn-print svg   { width: 15px; height: 15px; }

/* ── INVOICE CARD ── */
.invoice-card {
    max-width: 860px;
    margin: 0 auto;
    background: var(--white);
    border-radius: var(--radius);
    box-shadow: var(--shadow-lg);
    overflow: hidden;
}

/* ── HEADER ── */
.invoice-header {
    background: var(--navy);
    padding: 36px 44px 30px;
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 24px;
    position: relative;
    overflow: hidden;
}

.invoice-header::before {
    content: '';
    position: absolute;
    top: -60px; right: -60px;
    width: 220px; height: 220px;
    border-radius: 50%;
    background: rgba(14,124,123,.18);
    pointer-events: none;
}
.invoice-header::after {
    content: '';
    position: absolute;
    bottom: -40px; left: 30%;
    width: 140px; height: 140px;
    border-radius: 50%;
    background: rgba(201,149,76,.10);
    pointer-events: none;
}

.hospital-branding .hospital-name {
    font-family: 'Playfair Display', serif;
    font-size: 26px;
    font-weight: 700;
    color: var(--white);
    letter-spacing: .3px;
    line-height: 1.2;
}
.hospital-branding .hospital-tagline {
    font-size: 12px;
    color: rgba(255,255,255,.55);
    letter-spacing: 1.5px;
    text-transform: uppercase;
    margin-top: 4px;
}
.hospital-branding .hospital-address {
    font-size: 12px;
    color: rgba(255,255,255,.6);
    margin-top: 10px;
    max-width: 240px;
    line-height: 1.5;
}

.invoice-meta-block { text-align: right; flex-shrink: 0; }
.invoice-meta-block .invoice-label {
    font-size: 11px;
    color: rgba(255,255,255,.5);
    text-transform: uppercase;
    letter-spacing: 1.4px;
}
.invoice-meta-block .invoice-number {
    font-family: 'Playfair Display', serif;
    font-size: 28px;
    color: var(--gold);
    letter-spacing: 1px;
    margin: 2px 0 14px;
}
.invoice-meta-block .date-chip {
    display: inline-block;
    padding: 5px 14px;
    border: 1px solid rgba(255,255,255,.2);
    border-radius: 50px;
    font-size: 12px;
    color: rgba(255,255,255,.75);
}

/* ── STATUS BANNER ── */
.status-banner {
    padding: 13px 44px;
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 13px;
    font-weight: 600;
    letter-spacing: .3px;
}
.status-banner.paid     { background: var(--green-bg); color: var(--green); border-bottom: 1px solid #b8e3cc; }
.status-banner.unpaid   { background: var(--red-bg);   color: var(--red);   border-bottom: 1px solid #f5c6c1; }
.status-banner.partial  { background: var(--gold-light); color: #7a5c1e; border-bottom: 1px solid #e5c99a; }
.status-banner svg      { width: 18px; height: 18px; flex-shrink: 0; }

/* ── BODY SECTIONS ── */
.invoice-body { padding: 36px 44px; }

/* ── INFO GRID ── */
.info-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 0;
    border: 1px solid var(--gray-200);
    border-radius: var(--radius);
    overflow: hidden;
    margin-bottom: 36px;
}

.info-panel {
    padding: 24px 28px;
}
.info-panel:first-child { border-right: 1px solid var(--gray-200); }

.info-panel-title {
    font-size: 10px;
    font-weight: 600;
    letter-spacing: 1.8px;
    text-transform: uppercase;
    color: var(--teal);
    margin-bottom: 16px;
    padding-bottom: 10px;
    border-bottom: 2px solid var(--teal-light);
}

.info-row {
    display: flex;
    gap: 10px;
    margin-bottom: 9px;
    align-items: flex-start;
}
.info-row:last-child { margin-bottom: 0; }

.info-key {
    font-size: 11px;
    font-weight: 600;
    color: var(--gray-500);
    text-transform: uppercase;
    letter-spacing: .8px;
    min-width: 110px;
    padding-top: 1px;
    flex-shrink: 0;
}

.info-val {
    font-size: 13.5px;
    color: var(--gray-900);
    font-weight: 500;
    line-height: 1.4;
}

.insurance-badge {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    background: var(--teal-light);
    color: var(--teal);
    padding: 3px 10px;
    border-radius: 50px;
    font-size: 12px;
    font-weight: 600;
}

/* ── SERVICES TABLE ── */
.section-label {
    font-size: 10px;
    font-weight: 600;
    letter-spacing: 1.8px;
    text-transform: uppercase;
    color: var(--teal);
    margin-bottom: 14px;
}

.services-table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
    border: 1px solid var(--gray-200);
    border-radius: var(--radius);
    overflow: hidden;
    margin-bottom: 28px;
}

.services-table thead tr {
    background: var(--navy);
}
.services-table thead th {
    padding: 13px 16px;
    font-size: 10.5px;
    font-weight: 600;
    color: rgba(255,255,255,.75);
    text-transform: uppercase;
    letter-spacing: 1.2px;
    white-space: nowrap;
}
.services-table thead th:first-child { border-radius: var(--radius) 0 0 0; }
.services-table thead th:last-child  { border-radius: 0 var(--radius) 0 0; text-align: right; }

.services-table tbody tr {
    transition: background .15s;
}
.services-table tbody tr:nth-child(even) { background: var(--gray-100); }
.services-table tbody tr:hover           { background: var(--teal-light); }

.services-table td {
    padding: 13px 16px;
    border-bottom: 1px solid var(--gray-200);
    vertical-align: top;
}
.services-table tbody tr:last-child td { border-bottom: none; }

.service-name {
    font-weight: 600;
    color: var(--gray-900);
    font-size: 13.5px;
}
.service-desc {
    font-size: 12px;
    color: var(--gray-500);
    margin-top: 2px;
}
.td-center  { text-align: center; }
.td-right   { text-align: right; }
.td-amount  { text-align: right; font-weight: 600; color: var(--navy); }

.no-items td {
    text-align: center;
    padding: 40px;
    color: var(--gray-500);
    font-style: italic;
}

/* ── TOTALS BLOCK ── */
.totals-row {
    display: flex;
    justify-content: flex-end;
    margin-bottom: 32px;
}

.totals-table {
    min-width: 300px;
    border: 1px solid var(--gray-200);
    border-radius: var(--radius);
    overflow: hidden;
}

.totals-table .t-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px 20px;
    border-bottom: 1px solid var(--gray-200);
    font-size: 13.5px;
}
.totals-table .t-row:last-child { border-bottom: none; }

.totals-table .t-label { color: var(--gray-700); font-weight: 400; }
.totals-table .t-val   { font-weight: 600; color: var(--gray-900); }

.totals-table .t-row.insurance .t-label { color: var(--teal); }
.totals-table .t-row.insurance .t-val   { color: var(--teal); }

.totals-table .t-row.total-due {
    background: var(--navy);
    padding: 16px 20px;
}
.totals-table .t-row.total-due .t-label {
    font-size: 13px;
    font-weight: 600;
    color: rgba(255,255,255,.8);
    letter-spacing: .5px;
    text-transform: uppercase;
}
.totals-table .t-row.total-due .t-val {
    font-family: 'Playfair Display', serif;
    font-size: 22px;
    color: var(--gold);
    letter-spacing: .5px;
}

/* ── FOOTER NOTE ── */
.invoice-footer {
    border-top: 1px solid var(--gray-200);
    padding: 28px 44px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 20px;
    background: var(--gray-100);
}

.footer-note {
    font-size: 12.5px;
    color: var(--gray-500);
    max-width: 480px;
    line-height: 1.6;
}
.footer-note strong { color: var(--gray-700); }

.footer-stamp {
    text-align: center;
    flex-shrink: 0;
}
.stamp-circle {
    width: 80px; height: 80px;
    border-radius: 50%;
    border: 2px dashed var(--gray-300);
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    font-size: 9px;
    font-weight: 700;
    letter-spacing: .8px;
    text-transform: uppercase;
    color: var(--gray-300);
    line-height: 1.3;
}
.stamp-circle.paid-stamp {
    border-color: var(--green);
    color: var(--green);
    background: var(--green-bg);
    font-size: 11px;
}

/* ── PRINT ── */
@media print {
    /* Force all browsers to render background colors & images */
    * {
        -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
        color-adjust: exact !important;
    }

    @page {
        size: A4;
        margin: 14mm 12mm;
    }

    html, body {
        background: #ffffff !important;
        padding: 0 !important;
        margin: 0 !important;
        font-size: 12px !important;
    }

    .action-bar { display: none !important; }

    .invoice-card {
        box-shadow: none !important;
        border-radius: 0 !important;
        max-width: 100% !important;
        margin: 0 !important;
    }

    /* ── Header — force navy bg ── */
    .invoice-header {
        background: #0d2b45 !important;
        padding: 24px 28px 20px !important;
    }
    .invoice-header::before,
    .invoice-header::after { display: none !important; }

    .hospital-name  { color: #ffffff !important; }
    .hospital-tagline,
    .hospital-address { color: rgba(255,255,255,.75) !important; }
    .invoice-label  { color: rgba(255,255,255,.65) !important; }
    .invoice-number { color: #c9954c !important; }
    .date-chip      { color: rgba(255,255,255,.8) !important; border-color: rgba(255,255,255,.25) !important; }

    /* ── Status banners ── */
    .status-banner.paid    { background: #e8f6ee !important; color: #1a7a4a !important; }
    .status-banner.unpaid  { background: #fdecea !important; color: #b03a2e !important; }
    .status-banner.partial { background: #fdf4e7 !important; color: #7a5c1e !important; }

    /* ── Info panels ── */
    .info-grid { border: 1px solid #d6dae0 !important; }
    .info-panel-title { color: #0e7c7b !important; border-bottom-color: #e6f4f4 !important; }
    .insurance-badge  { background: #e6f4f4 !important; color: #0e7c7b !important; }

    /* ── Table header ── */
    .services-table thead tr   { background: #0d2b45 !important; }
    .services-table thead th   { color: rgba(255,255,255,.85) !important; }
    .services-table tbody tr:nth-child(even) { background: #f7f8fa !important; }
    .services-table tbody tr:hover           { background: transparent !important; }

    /* ── Totals ── */
    .totals-table .t-row.insurance .t-label,
    .totals-table .t-row.insurance .t-val { color: #0e7c7b !important; }

    .totals-table .t-row.total-due {
        background: #0d2b45 !important;
    }
    .totals-table .t-row.total-due .t-label { color: rgba(255,255,255,.85) !important; }
    .totals-table .t-row.total-due .t-val   { color: #c9954c !important; }

    /* ── Footer ── */
    .invoice-footer { background: #f7f8fa !important; }

    /* ── Stamp ── */
    .stamp-circle             { border-color: #d6dae0 !important; color: #d6dae0 !important; }
    .stamp-circle.paid-stamp  { border-color: #1a7a4a !important; color: #1a7a4a !important; background: #e8f6ee !important; }

    /* ── Section label ── */
    .section-label { color: #0e7c7b !important; }

    /* ── Avoid page breaks inside key sections ── */
    .invoice-header, .info-grid, .services-table, .totals-row, .invoice-footer {
        page-break-inside: avoid;
        break-inside: avoid;
    }
}
</style>
</head>

<body>

<!-- ACTION BAR -->
<div class="action-bar">
    <span class="hospital-ref">Patient Billing System</span>
    <button class="btn-print" onclick="window.print()">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
            <polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/>
            <rect x="6" y="14" width="12" height="8"/>
        </svg>
        Print Invoice
    </button>
</div>

<!-- INVOICE CARD -->
<div class="invoice-card">

    <!-- HEADER -->
    <div class="invoice-header">
        <div class="hospital-branding">
            <div class="hospital-name">General Hospital</div>
            <div class="hospital-tagline">Medical Billing &amp; Finance Department</div>
            <div class="hospital-address">
                123 Healthcare Avenue, Medical District<br>
                Tel: (02) 8000-0000 &nbsp;|&nbsp; Fax: (02) 8000-0001
            </div>
        </div>

        <div class="invoice-meta-block">
            <div class="invoice-label">Invoice Number</div>
            <div class="invoice-number">INV-<?= str_pad($receipt_id, 6, '0', STR_PAD_LEFT) ?></div>
            <div class="date-chip">
                📅 <?= htmlspecialchars(date('F d, Y', strtotime($billing['billing_date']))) ?>
            </div>
        </div>
    </div>

    <!-- STATUS BANNER -->
    <?php
    if ($is_fully_covered):
    ?>
    <div class="status-banner paid">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>
        </svg>
        FULLY PAID — This invoice has been settled in full.
    </div>
    <?php elseif ($is_paid): ?>
    <div class="status-banner paid">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>
        </svg>
        PAID — Payment received. Thank you!
    </div>
    <?php else: ?>
    <div class="status-banner unpaid">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>
        </svg>
        OUTSTANDING — Balance due: ₱ <?= number_format($total_out_of_pocket, 2) ?>
    </div>
    <?php endif; ?>

    <!-- BODY -->
    <div class="invoice-body">

        <!-- PATIENT & DOCTOR INFO -->
        <div class="info-grid">

            <!-- PATIENT -->
            <div class="info-panel">
                <div class="info-panel-title">Patient Information</div>

                <div class="info-row">
                    <span class="info-key">Full Name</span>
                    <span class="info-val"><?= htmlspecialchars($full_name) ?></span>
                </div>
                <div class="info-row">
                    <span class="info-key">Contact</span>
                    <span class="info-val"><?= htmlspecialchars($billing['phone_number'] ?: 'N/A') ?></span>
                </div>
                <div class="info-row">
                    <span class="info-key">Address</span>
                    <span class="info-val"><?= htmlspecialchars($billing['address'] ?: 'N/A') ?></span>
                </div>

                <?php if ($insurance): ?>
                <div class="info-row">
                    <span class="info-key">Insurance</span>
                    <span class="info-val">
                        <span class="insurance-badge">
                            ✦ <?= htmlspecialchars($insurance['insurance_company']) ?>
                        </span><br>
                        <small style="color:var(--gray-500);margin-top:4px;display:block;">
                            <?= htmlspecialchars($insurance['promo_name']) ?>
                        </small>
                    </span>
                </div>
                <div class="info-row">
                    <span class="info-key">Ins. No.</span>
                    <span class="info-val"><?= htmlspecialchars($insurance['insurance_number']) ?></span>
                </div>
                <?php endif; ?>
            </div>

            <!-- DOCTOR -->
            <div class="info-panel">
                <div class="info-panel-title">Attending Physician</div>

                <div class="info-row">
                    <span class="info-key">Doctor</span>
                    <span class="info-val"><?= htmlspecialchars($doctor_name) ?></span>
                </div>
                <div class="info-row">
                    <span class="info-key">Contact</span>
                    <span class="info-val"><?= htmlspecialchars($doctor['contact_number'] ?? 'N/A') ?></span>
                </div>
                <div class="info-row">
                    <span class="info-key">Specialization</span>
                    <span class="info-val"><?= htmlspecialchars($doctor['specialization'] ?? 'N/A') ?></span>
                </div>
                <div class="info-row">
                    <span class="info-key">Billing ID</span>
                    <span class="info-val" style="font-family:monospace;font-size:12px;color:var(--gray-500);">
                        #<?= str_pad($billing_id, 8, '0', STR_PAD_LEFT) ?>
                    </span>
                </div>
                <div class="info-row">
                    <span class="info-key">Patient ID</span>
                    <span class="info-val" style="font-family:monospace;font-size:12px;color:var(--gray-500);">
                        #<?= str_pad($patient_id, 8, '0', STR_PAD_LEFT) ?>
                    </span>
                </div>
            </div>
        </div>

        <!-- SERVICES TABLE -->
        <div class="section-label">Services &amp; Charges</div>

        <table class="services-table">
            <thead>
                <tr>
                    <th style="width:30%">Service</th>
                    <th>Description</th>
                    <th class="td-center" style="width:8%">Qty</th>
                    <th class="td-right" style="width:14%">Unit Price</th>
                    <th class="td-right" style="width:14%">Amount</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($billing_items): ?>
                    <?php foreach ($billing_items as $item): ?>
                    <tr>
                        <td>
                            <div class="service-name"><?= htmlspecialchars($item['serviceName']) ?></div>
                        </td>
                        <td>
                            <div class="service-desc"><?= htmlspecialchars($item['description'] ?: '—') ?></div>
                        </td>
                        <td class="td-center"><?= (int)$item['quantity'] ?></td>
                        <td class="td-right">₱ <?= number_format($item['unit_price'], 2) ?></td>
                        <td class="td-amount">₱ <?= number_format($item['total_price'], 2) ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr class="no-items">
                        <td colspan="5">No billing items found for this receipt.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>

        <!-- TOTALS -->
        <div class="totals-row">
            <div class="totals-table">
                <div class="t-row">
                    <span class="t-label">Subtotal</span>
                    <span class="t-val">₱ <?= number_format($total_charges, 2) ?></span>
                </div>
                <div class="t-row insurance">
                    <span class="t-label">Insurance Coverage</span>
                    <span class="t-val">− ₱ <?= number_format($insurance_covered, 2) ?></span>
                </div>
                <div class="t-row total-due">
                    <span class="t-label">Total Due</span>
                    <span class="t-val">₱ <?= number_format($total_out_of_pocket, 2) ?></span>
                </div>
            </div>
        </div>

    </div><!-- /.invoice-body -->

    <!-- FOOTER -->
    <div class="invoice-footer">
        <div class="footer-note">
            <strong>Thank you for choosing our hospital.</strong><br>
            Please present this invoice at the cashier when settling your balance. For inquiries regarding 
            this bill, please contact our Billing &amp; Finance Department at <strong>(02) 8000-0000</strong> 
            or email <strong>billing@hospital.com</strong>.
        </div>

        <div class="footer-stamp">
            <div class="stamp-circle <?= $is_paid ? 'paid-stamp' : '' ?>">
                <?php if ($is_paid): ?>
                    ✓<br>PAID
                <?php else: ?>
                    OFFICIAL<br>RECEIPT
                <?php endif; ?>
            </div>
        </div>
    </div>

</div><!-- /.invoice-card -->

</body>
</html>