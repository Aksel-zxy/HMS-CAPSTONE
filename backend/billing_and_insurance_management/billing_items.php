<?php
session_start();
include '../../SQL/config.php';

$patient_id = isset($_GET['patient_id']) ? intval($_GET['patient_id']) : 0;

/* =========================================================
   PATIENT SELECTION PAGE
   Only show patients with post_discharged + pending billing
========================================================= */
if ($patient_id <= 0) {

    $sql = "
        SELECT DISTINCT
            p.patient_id,
            CONCAT(p.fname, ' ', IFNULL(NULLIF(p.mname,''),''), ' ', p.lname) AS full_name,
            COUNT(DISTINCT pp.prescription_id) AS rx_count
        FROM patientinfo p
        INNER JOIN pharmacy_prescription pp
            ON pp.patient_id     = p.patient_id
           AND pp.payment_type   = 'post_discharged'
           AND pp.status         = 'Dispensed'
           AND pp.billing_status = 'pending'
        GROUP BY p.patient_id
        ORDER BY p.lname ASC, p.fname ASC
    ";
    $patients = $conn->query($sql);
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Post-Discharged Billing</title>
    <link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display&family=DM+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/CSS/bootstrap.min.css">
    <link rel="stylesheet" href="assets/css/billing_sidebar.css">
    <style>
    :root {
        --sidebar-w: 250px;
        --navy:      #0b1d3a;
        --accent:    #2563eb;
        --ink:       #1e293b;
        --ink-light: #64748b;
        --border:    #e2e8f0;
        --surface:   #f1f5f9;
        --card:      #ffffff;
        --radius:    14px;
        --shadow:    0 2px 20px rgba(11,29,58,.08);
        --ff-head:   'DM Serif Display', serif;
        --ff-body:   'DM Sans', sans-serif;
    }
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: var(--ff-body); background: var(--surface); color: var(--ink); }
    .cw { margin-left: var(--sidebar-w); padding: 48px 28px 60px; transition: margin-left .3s; }
    .cw.sidebar-collapsed { margin-left: 0; }
    .page-head { margin-bottom: 24px; }
    .page-head h2 { font-family: var(--ff-head); font-size: 1.7rem; color: var(--navy); }
    .page-head p  { font-size: .85rem; color: var(--ink-light); margin-top: 4px; }
    .card { background: var(--card); border: 1px solid var(--border); border-radius: var(--radius); box-shadow: var(--shadow); overflow: hidden; }
    .card-header { background: var(--navy); padding: 14px 22px; display: flex; align-items: center; gap: 10px; }
    .card-header h5 { font-family: var(--ff-head); color: #fff; margin: 0; font-size: 1rem; }
    .card-header i { color: rgba(255,255,255,.6); }
    .tbl { width: 100%; border-collapse: collapse; }
    .tbl thead th { background: #f8fafc; color: var(--ink-light); font-size: .7rem; font-weight: 700; text-transform: uppercase; letter-spacing: .6px; padding: 11px 18px; border-bottom: 2px solid var(--border); text-align: left; }
    .tbl tbody tr { border-bottom: 1px solid var(--border); transition: background .12s; }
    .tbl tbody tr:last-child { border-bottom: none; }
    .tbl tbody tr:hover { background: #f7faff; }
    .tbl td { padding: 13px 18px; vertical-align: middle; font-size: .88rem; }
    .pat-cell { display: flex; align-items: center; gap: 10px; }
    .pat-av   { width: 38px; height: 38px; border-radius: 50%; background: linear-gradient(135deg,var(--navy),var(--accent)); color:#fff; display:flex; align-items:center; justify-content:center; font-size:.8rem; font-weight:700; flex-shrink:0; }
    .pat-name { font-weight: 600; color: var(--navy); }
    .rx-badge { display:inline-flex; align-items:center; gap:4px; background:#dbeafe; color:#1d4ed8; border-radius:999px; padding:3px 10px; font-size:.72rem; font-weight:700; }
    .btn-billing { display:inline-flex; align-items:center; gap:5px; background:var(--accent); color:#fff; border:none; border-radius:8px; padding:7px 18px; font-size:.82rem; font-weight:700; font-family:var(--ff-body); text-decoration:none; cursor:pointer; transition:background .15s,transform .1s; }
    .btn-billing:hover { background:#1d4ed8; color:#fff; transform:translateY(-1px); }
    .empty-state { text-align:center; padding:56px 16px; color:var(--ink-light); }
    .empty-state i { font-size:2.5rem; display:block; margin-bottom:12px; opacity:.3; }
    @media(max-width:768px){ .cw{ margin-left:200px; padding:56px 14px; } }
    @media(max-width:480px){ .cw{ margin-left:0!important; padding:52px 10px; } }
    </style>
    </head>
    <body>
    <?php include 'billing_sidebar.php'; ?>
    <div class="cw" id="mainCw">
        <div class="page-head">
            <h2>Post-Discharged Billing</h2>
            <p>Patients with dispensed prescriptions awaiting billing settlement.</p>
        </div>
        <div class="card">
            <div class="card-header">
                <i class="bi bi-people-fill"></i>
                <h5>Patients Ready for Billing</h5>
            </div>
            <div style="overflow-x:auto;">
                <table class="tbl">
                    <thead>
                        <tr>
                            <th>Patient</th>
                            <th>Prescriptions</th>
                            <th style="text-align:right;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if ($patients && $patients->num_rows > 0):
                        while ($row = $patients->fetch_assoc()):
                            $initials = strtoupper(substr(trim($row['full_name']), 0, 1));
                    ?>
                        <tr>
                            <td>
                                <div class="pat-cell">
                                    <div class="pat-av"><?= $initials ?></div>
                                    <span class="pat-name"><?= htmlspecialchars(trim($row['full_name'])) ?></span>
                                </div>
                            </td>
                            <td>
                                <span class="rx-badge">
                                    <i class="bi bi-capsule"></i>
                                    <?= $row['rx_count'] ?> Rx pending
                                </span>
                            </td>
                            <td style="text-align:right;">
                                <a href="billing_items.php?patient_id=<?= $row['patient_id'] ?>" class="btn-billing">
                                    <i class="bi bi-receipt"></i> Manage Billing
                                </a>
                            </td>
                        </tr>
                    <?php endwhile; else: ?>
                        <tr>
                            <td colspan="3">
                                <div class="empty-state">
                                    <i class="bi bi-inbox"></i>
                                    No patients with pending post-discharged prescriptions.
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <script>
    (function(){
        const sb = document.getElementById('mySidebar');
        const cw = document.getElementById('mainCw');
        if(!sb||!cw) return;
        function sync(){ cw.classList.toggle('sidebar-collapsed', sb.classList.contains('closed')); }
        new MutationObserver(sync).observe(sb,{attributes:true,attributeFilter:['class']});
        document.getElementById('sidebarToggle')?.addEventListener('click',()=>requestAnimationFrame(sync));
        sync();
    })();
    </script>
    </body>
    </html>
    <?php
    exit;
}

/* =========================================================
   LOAD PATIENT
========================================================= */
$stmt = $conn->prepare("SELECT * FROM patientinfo WHERE patient_id=?");
$stmt->bind_param("i", $patient_id);
$stmt->execute();
$patient = $stmt->get_result()->fetch_assoc();
if (!$patient) die("Patient not found.");

/* =========================================================
   AGE / SENIOR CHECK
========================================================= */
$age = 0;
if (!empty($patient['dob']) && $patient['dob'] !== '0000-00-00') {
    $age = (new DateTime())->diff(new DateTime($patient['dob']))->y;
}
$is_senior = $age >= 60;

/* =========================================================
   PWD TOGGLE
========================================================= */
if (isset($_GET['toggle_pwd'])) {
    $_SESSION['is_pwd'][$patient_id] = (int)$_GET['toggle_pwd'];
    header("Location: billing_items.php?patient_id=$patient_id");
    exit;
}
$is_pwd = $_SESSION['is_pwd'][$patient_id] ?? (int)($patient['is_pwd'] ?? 0);

/* =========================================================
   INITIALIZE SESSION CART
   Seed from post_discharged dispensed prescriptions
   using pharmacy_inventory for medicine names (med_name column)
========================================================= */
if (!isset($_SESSION['billing_cart'][$patient_id])) {
    $_SESSION['billing_cart'][$patient_id] = [];

    $stmt = $conn->prepare("
        SELECT
            ppi.item_id,
            ppi.med_id,
            ppi.dosage,
            ppi.frequency,
            ppi.quantity_dispensed,
            ppi.unit_price,
            ppi.total_price,
            pi2.med_name
        FROM pharmacy_prescription pp
        JOIN pharmacy_prescription_items ppi ON pp.prescription_id = ppi.prescription_id
        JOIN pharmacy_inventory pi2          ON pi2.med_id = ppi.med_id
        WHERE pp.patient_id     = ?
          AND pp.payment_type   = 'post_discharged'
          AND pp.status         = 'Dispensed'
          AND pp.billing_status = 'pending'
    ");
    $stmt->bind_param("i", $patient_id);
    $stmt->execute();
    $res = $stmt->get_result();

    while ($row = $res->fetch_assoc()) {
        $dosage_str = trim(($row['dosage'] ?? '') . ' ' . ($row['frequency'] ?? ''));
        $label      = $row['med_name'] . ($dosage_str ? ' (' . trim($dosage_str) . ')' : '');

        $_SESSION['billing_cart'][$patient_id][] = [
            'cart_key'    => 'RX-' . $row['item_id'],
            'med_id'      => $row['med_id'],
            'serviceName' => $label,
            'description' => 'Dispensed — Qty: ' . $row['quantity_dispensed']
                           . ' × ₱' . number_format($row['unit_price'], 2),
            'price'       => (float)$row['total_price'],
            'source'      => 'rx',
        ];
    }
}

/* =========================================================
   ADD EXTRA MEDICINE FROM pharmacy_inventory
========================================================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_medicine'])) {
    $med_id = (int)$_POST['med_id'];
    $qty    = max(1, (int)($_POST['qty'] ?? 1));

    if ($med_id > 0) {
        $stmt = $conn->prepare("SELECT med_id, med_name, dosage, unit_price FROM pharmacy_inventory WHERE med_id=? LIMIT 1");
        $stmt->bind_param("i", $med_id);
        $stmt->execute();
        $med = $stmt->get_result()->fetch_assoc();

        if ($med) {
            $_SESSION['billing_cart'][$patient_id][] = [
                'cart_key'    => 'ADD-' . $med_id . '-' . time(),
                'med_id'      => $med['med_id'],
                'serviceName' => $med['med_name'] . (!empty($med['dosage']) ? ' (' . $med['dosage'] . ')' : ''),
                'description' => 'Additional — Qty: ' . $qty . ' × ₱' . number_format($med['unit_price'], 2),
                'price'       => round($med['unit_price'] * $qty, 2),
                'source'      => 'add',
            ];
        }
    }
    header("Location: billing_items.php?patient_id=$patient_id");
    exit;
}

/* =========================================================
   DELETE (only manually-added items)
========================================================= */
if (isset($_GET['delete'])) {
    $idx = (int)$_GET['delete'];
    if (isset($_SESSION['billing_cart'][$patient_id][$idx])
        && $_SESSION['billing_cart'][$patient_id][$idx]['source'] === 'add') {
        unset($_SESSION['billing_cart'][$patient_id][$idx]);
        $_SESSION['billing_cart'][$patient_id] = array_values($_SESSION['billing_cart'][$patient_id]);
    }
    header("Location: billing_items.php?patient_id=$patient_id");
    exit;
}

/* =========================================================
   FINALIZE
========================================================= */
if (isset($_GET['finalize']) && $_GET['finalize'] == 1) {
    $subtotal_f = array_sum(array_column($_SESSION['billing_cart'][$patient_id], 'price'));
    $discount_f = ($is_pwd || $is_senior) ? $subtotal_f * 0.20 : 0;
    $grand_f    = $subtotal_f - $discount_f;
    $txn        = 'TXN-' . strtoupper(uniqid());

    $stmt = $conn->prepare("
        INSERT INTO billing_records (patient_id, billing_date, total_amount, grand_total, status, transaction_id)
        VALUES (?, NOW(), ?, ?, 'Pending', ?)
    ");
    $stmt->bind_param("idds", $patient_id, $subtotal_f, $grand_f, $txn);
    $stmt->execute();
    $new_billing_id = $conn->insert_id;

    foreach ($_SESSION['billing_cart'][$patient_id] as $item) {
        $s = $conn->prepare("
            INSERT INTO billing_items (billing_id, patient_id, quantity, unit_price, total_price, finalized)
            VALUES (?, ?, 1, ?, ?, 1)
        ");
        $s->bind_param("iidd", $new_billing_id, $patient_id, $item['price'], $item['price']);
        $s->execute();
    }

    $up = $conn->prepare("
        UPDATE pharmacy_prescription
        SET billing_status = 'billed'
        WHERE patient_id = ? AND payment_type = 'post_discharged' AND billing_status = 'pending'
    ");
    $up->bind_param("i", $patient_id);
    $up->execute();

    unset($_SESSION['billing_cart'][$patient_id]);

    header("Location: billing_items.php?success=1");
    exit;
}

/* =========================================================
   CART TOTALS
========================================================= */
$cart        = $_SESSION['billing_cart'][$patient_id];
$subtotal    = array_sum(array_column($cart, 'price'));
$discount    = ($is_pwd || $is_senior) ? $subtotal * 0.20 : 0;
$grand_total = $subtotal - $discount;

/* =========================================================
   MEDICINE DROPDOWN — pharmacy_inventory
   Exclude med_ids already seeded from dispensed RX
========================================================= */
$seeded_med_ids = array_unique(array_column(
    array_filter($cart, fn($c) => $c['source'] === 'rx'),
    'med_id'
));

if ($seeded_med_ids) {
    $ph   = implode(',', array_fill(0, count($seeded_med_ids), '?'));
    $stmt = $conn->prepare("
        SELECT med_id, med_name, dosage, unit_price
        FROM pharmacy_inventory
        WHERE med_id NOT IN ($ph)
        ORDER BY med_name ASC
    ");
    $stmt->bind_param(str_repeat('i', count($seeded_med_ids)), ...$seeded_med_ids);
    $stmt->execute();
    $med_list = $stmt->get_result();
} else {
    $med_list = $conn->query("SELECT med_id, med_name, dosage, unit_price FROM pharmacy_inventory ORDER BY med_name ASC");
}
$medicines = [];
while ($m = $med_list->fetch_assoc()) $medicines[] = $m;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<title>Billing Items — <?= htmlspecialchars($patient['fname'].' '.$patient['lname']) ?></title>
<link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display&family=DM+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<link rel="stylesheet" href="assets/CSS/bootstrap.min.css">
<link rel="stylesheet" href="assets/css/billing_sidebar.css">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<style>
:root {
    --sidebar-w:  250px;
    --navy:       #0b1d3a;
    --accent:     #2563eb;
    --success:    #059669;
    --danger:     #dc2626;
    --ink:        #1e293b;
    --ink-light:  #64748b;
    --border:     #e2e8f0;
    --surface:    #f1f5f9;
    --card:       #ffffff;
    --radius:     14px;
    --shadow:     0 2px 20px rgba(11,29,58,.08);
    --ff-head:    'DM Serif Display', serif;
    --ff-body:    'DM Sans', sans-serif;
}
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: var(--ff-body); background: var(--surface); color: var(--ink); }

.cw { margin-left: var(--sidebar-w); padding: 48px 28px 80px; transition: margin-left .3s; }
.cw.sidebar-collapsed { margin-left: 0; }

/* ── Page Header ── */
.page-head { display:flex; align-items:center; gap:14px; margin-bottom:26px; }
.head-icon { width:52px; height:52px; background:linear-gradient(135deg,var(--navy),var(--accent)); border-radius:13px; display:flex; align-items:center; justify-content:center; color:#fff; font-size:1.4rem; box-shadow:0 6px 18px rgba(11,29,58,.2); flex-shrink:0; }
.page-head h2 { font-family:var(--ff-head); font-size:clamp(1.2rem,2.5vw,1.75rem); color:var(--navy); margin:0; }
.page-head p  { font-size:.82rem; color:var(--ink-light); margin-top:3px; }

/* ── Alert ── */
.alert-ok { background:#f0fdf4; border:1.5px solid #86efac; border-radius:10px; padding:13px 18px; display:flex; align-items:center; gap:10px; font-weight:600; color:var(--success); margin-bottom:20px; }

/* ── Card ── */
.bcard { background:var(--card); border:1px solid var(--border); border-radius:var(--radius); box-shadow:var(--shadow); overflow:hidden; margin-bottom:22px; }
.bcard-head { background:var(--navy); padding:13px 20px; display:flex; align-items:center; gap:8px; color:rgba(255,255,255,.8); font-size:.72rem; font-weight:700; text-transform:uppercase; letter-spacing:.7px; }
.bcard-body { padding:20px; }

/* ── PWD Toggle ── */
.discount-toggle { display:flex; align-items:center; gap:10px; padding:12px 16px; background:#f0fdf4; border:1.5px solid #bbf7d0; border-radius:10px; margin-bottom:20px; font-size:.88rem; color:#065f46; font-weight:600; }
.discount-toggle.senior { background:#eff6ff; border-color:#bfdbfe; color:#1d4ed8; }
.discount-toggle input[type="checkbox"] { width:18px; height:18px; accent-color:var(--success); cursor:pointer; flex-shrink:0; }
.d-badge { margin-left:auto; border-radius:999px; padding:3px 12px; font-size:.72rem; font-weight:700; color:#fff; background:#059669; }

/* ── Add Medicine Form ── */
.add-form { display:grid; grid-template-columns:1fr 100px auto; gap:10px; align-items:end; }
.add-form label { font-size:.74rem; font-weight:700; color:var(--ink-light); text-transform:uppercase; letter-spacing:.5px; display:block; margin-bottom:5px; }
.add-form select,
.add-form input[type="number"] { width:100%; padding:9px 13px; border:1.5px solid var(--border); border-radius:9px; font-family:var(--ff-body); font-size:.88rem; color:var(--ink); background:var(--card); outline:none; transition:border-color .2s,box-shadow .2s; }
.add-form select:focus,
.add-form input:focus { border-color:var(--accent); box-shadow:0 0 0 3px rgba(37,99,235,.1); }
.btn-add { padding:9px 20px; background:var(--accent); color:#fff; border:none; border-radius:9px; font-family:var(--ff-body); font-size:.88rem; font-weight:700; cursor:pointer; display:inline-flex; align-items:center; gap:6px; white-space:nowrap; transition:background .15s,transform .1s; height:40px; }
.btn-add:hover { background:#1d4ed8; transform:translateY(-1px); }

/* ── Items Table ── */
.itbl { width:100%; border-collapse:collapse; font-size:.88rem; }
.itbl thead th { background:#f8fafc; color:var(--ink-light); font-size:.7rem; font-weight:700; text-transform:uppercase; letter-spacing:.6px; padding:11px 16px; border-bottom:2px solid var(--border); text-align:left; white-space:nowrap; }
.itbl thead th.r { text-align:right; }
.itbl thead th.c { text-align:center; }
.itbl tbody tr { border-bottom:1px solid var(--border); transition:background .12s; }
.itbl tbody tr:last-child { border-bottom:none; }
.itbl tbody tr:hover { background:#f7faff; }
.itbl td { padding:12px 16px; vertical-align:middle; }
.itbl td.r { text-align:right; font-weight:600; color:var(--success); }
.itbl td.c { text-align:center; }

.med-name { font-weight:600; color:var(--navy); }
.med-desc { font-size:.75rem; color:var(--ink-light); margin-top:2px; }

.tag { display:inline-flex; align-items:center; gap:4px; padding:2px 9px; border-radius:999px; font-size:.68rem; font-weight:700; margin-right:4px; }
.tag-rx  { background:#dbeafe; color:#1d4ed8; }
.tag-add { background:#fef9c3; color:#854d0e; }

.btn-del { background:#fff1f2; color:var(--danger); border:1.5px solid #fecdd3; border-radius:7px; padding:5px 12px; font-size:.78rem; font-weight:700; font-family:var(--ff-body); cursor:pointer; display:inline-flex; align-items:center; gap:4px; text-decoration:none; transition:all .15s; }
.btn-del:hover { background:var(--danger); color:#fff; border-color:var(--danger); }
.lock-icon { color:var(--ink-light); font-size:.85rem; }

.empty-state { text-align:center; padding:40px 16px; color:var(--ink-light); }
.empty-state i { font-size:2rem; display:block; margin-bottom:8px; opacity:.3; }

/* ── Totals ── */
.totals-panel { background:var(--card); border:1px solid var(--border); border-radius:var(--radius); box-shadow:var(--shadow); padding:20px 26px; margin-bottom:22px; }
.t-row { display:flex; justify-content:space-between; align-items:center; padding:9px 0; font-size:.9rem; border-bottom:1px solid var(--border); gap:12px; }
.t-row:last-child { border-bottom:none; }
.t-lbl { color:var(--ink-light); font-weight:500; }
.t-val { font-weight:600; }
.t-row.grand .t-lbl { font-size:1rem; font-weight:700; color:var(--navy); }
.t-row.grand .t-val { font-size:1.2rem; font-weight:700; color:var(--navy); }
.disc-val { color:var(--danger)!important; }

/* ── Actions ── */
.actions-bar { display:flex; justify-content:space-between; align-items:center; gap:12px; flex-wrap:wrap; }
.btn-back { padding:10px 22px; background:var(--card); color:var(--ink-light); border:1.5px solid var(--border); border-radius:9px; font-family:var(--ff-body); font-size:.88rem; font-weight:600; cursor:pointer; text-decoration:none; display:inline-flex; align-items:center; gap:6px; transition:all .15s; }
.btn-back:hover { border-color:var(--accent); color:var(--accent); background:#eff6ff; }
.btn-finalize { padding:10px 28px; background:var(--success); color:#fff; border:none; border-radius:9px; font-family:var(--ff-body); font-size:.88rem; font-weight:700; cursor:pointer; display:inline-flex; align-items:center; gap:6px; transition:background .15s,transform .1s; box-shadow:0 4px 14px rgba(5,150,105,.3); }
.btn-finalize:hover { background:#047857; transform:translateY(-1px); }

/* ── Mobile ── */
.itbl-mobile { display:none; }
.m-card { background:var(--card); border:1px solid var(--border); border-radius:11px; padding:14px; margin-bottom:10px; box-shadow:0 1px 6px rgba(11,29,58,.06); }
.m-top  { display:flex; justify-content:space-between; align-items:flex-start; gap:10px; margin-bottom:6px; }
.m-price { font-weight:700; color:var(--success); white-space:nowrap; }
.m-desc  { font-size:.76rem; color:var(--ink-light); margin-bottom:10px; }

@media(max-width:900px){ .add-form{ grid-template-columns:1fr 80px auto; } }
@media(max-width:768px){
    .cw{ margin-left:200px; padding:56px 14px 60px; }
    .itbl{ display:none; }
    .itbl-mobile{ display:block; }
    .add-form{ grid-template-columns:1fr 70px; }
    .add-form .btn-wrap{ grid-column:1/-1; }
    .btn-add{ width:100%; justify-content:center; }
}
@media(max-width:480px){
    .cw{ margin-left:0!important; padding:52px 10px 60px; }
    .actions-bar{ flex-direction:column-reverse; }
    .btn-back,.btn-finalize{ width:100%; justify-content:center; }
}
@supports(padding:env(safe-area-inset-bottom)){
    .cw{ padding-bottom:calc(60px + env(safe-area-inset-bottom)); }
}
</style>
</head>
<body>

<?php include 'billing_sidebar.php'; ?>

<div class="cw" id="mainCw">

    <?php if (isset($_GET['success'])): ?>
    <div class="alert-ok">
        <i class="bi bi-check-circle-fill" style="font-size:1.3rem;"></i>
        Billing finalized successfully!
    </div>
    <?php endif; ?>

    <!-- Page Header -->
    <div class="page-head">
        <div class="head-icon"><i class="bi bi-receipt-cutoff"></i></div>
        <div>
            <h2>Billing Items</h2>
            <p><?= htmlspecialchars(trim($patient['fname'].' '.$patient['lname'])) ?>
               &mdash; Post-Discharged Prescription Billing</p>
        </div>
    </div>

    <!-- PWD / Senior -->
    <?php if ($is_senior): ?>
    <div class="discount-toggle senior">
        <i class="bi bi-person-check-fill" style="font-size:1.1rem;"></i>
        Senior Citizen (60+) — 20% discount applied automatically
        <span class="d-badge" style="background:#1d4ed8;">SC Discount</span>
    </div>
    <?php else: ?>
    <div class="discount-toggle">
        <input type="checkbox" id="pwdChk" <?= $is_pwd ? 'checked' : '' ?>
               onchange="window.location='billing_items.php?patient_id=<?= $patient_id ?>&toggle_pwd='+(this.checked?1:0)">
        <label for="pwdChk" style="cursor:pointer;">Mark as PWD (applies 20% discount)</label>
        <?php if ($is_pwd): ?><span class="d-badge">PWD Active</span><?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Add Extra Medicine -->
    <div class="bcard">
        <div class="bcard-head">
            <i class="bi bi-plus-circle-fill"></i>
            Add Extra Medicine from Inventory
        </div>
        <div class="bcard-body">
            <?php if ($medicines): ?>
            <form method="POST" class="add-form">
                <div>
                    <label>Medicine</label>
                    <select name="med_id" required>
                        <option value="">— Select medicine from inventory —</option>
                        <?php foreach ($medicines as $m): ?>
                        <option value="<?= $m['med_id'] ?>">
                            <?= htmlspecialchars($m['med_name']) ?>
                            <?= !empty($m['dosage']) ? '(' . htmlspecialchars($m['dosage']) . ')' : '' ?>
                            — ₱<?= number_format($m['unit_price'], 2) ?>/unit
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label>Qty</label>
                    <input type="number" name="qty" value="1" min="1" max="999">
                </div>
                <div class="btn-wrap" style="margin-top:20px;">
                    <button type="submit" name="add_medicine" class="btn-add">
                        <i class="bi bi-plus-lg"></i> Add
                    </button>
                </div>
            </form>
            <?php else: ?>
            <p style="color:var(--ink-light);font-size:.88rem;">No additional medicines available in inventory.</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Items -->
    <div class="bcard">
        <div class="bcard-head">
            <i class="bi bi-list-check"></i>
            Medicines to Bill
            <span style="margin-left:auto;font-weight:400;opacity:.7;"><?= count($cart) ?> item<?= count($cart)!==1?'s':'' ?></span>
        </div>
        <div class="bcard-body" style="padding:0;">

            <!-- Desktop Table -->
            <div style="overflow-x:auto;">
                <table class="itbl">
                    <thead>
                        <tr>
                            <th>Medicine</th>
                            <th>Details</th>
                            <th class="r">Amount</th>
                            <th class="c">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($cart)): ?>
                        <tr><td colspan="4"><div class="empty-state"><i class="bi bi-inbox"></i>No medicines added yet.</div></td></tr>
                    <?php else:
                        foreach ($cart as $idx => $item): ?>
                        <tr>
                            <td>
                                <?php if ($item['source']==='rx'): ?>
                                    <span class="tag tag-rx"><i class="bi bi-capsule"></i> Dispensed</span>
                                <?php else: ?>
                                    <span class="tag tag-add"><i class="bi bi-plus-circle"></i> Added</span>
                                <?php endif; ?>
                                <span class="med-name"><?= htmlspecialchars($item['serviceName']) ?></span>
                            </td>
                            <td><span class="med-desc"><?= htmlspecialchars($item['description']) ?></span></td>
                            <td class="r">₱<?= number_format($item['price'], 2) ?></td>
                            <td class="c">
                                <?php if ($item['source']==='add'): ?>
                                <a href="billing_items.php?patient_id=<?= $patient_id ?>&delete=<?= $idx ?>"
                                   class="btn-del" onclick="return confirm('Remove this item?')">
                                    <i class="bi bi-trash"></i> Remove
                                </a>
                                <?php else: ?>
                                <i class="bi bi-lock-fill lock-icon" title="Dispensed — cannot remove"></i>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Mobile Cards -->
            <div class="itbl-mobile" style="padding:14px;">
                <?php if (empty($cart)): ?>
                <div class="empty-state"><i class="bi bi-inbox"></i>No medicines added yet.</div>
                <?php else: foreach ($cart as $idx => $item): ?>
                <div class="m-card">
                    <div class="m-top">
                        <div>
                            <?php if ($item['source']==='rx'): ?>
                                <span class="tag tag-rx"><i class="bi bi-capsule"></i> Dispensed</span>
                            <?php else: ?>
                                <span class="tag tag-add"><i class="bi bi-plus-circle"></i> Added</span>
                            <?php endif; ?>
                            <span class="med-name"><?= htmlspecialchars($item['serviceName']) ?></span>
                        </div>
                        <span class="m-price">₱<?= number_format($item['price'],2) ?></span>
                    </div>
                    <div class="m-desc"><?= htmlspecialchars($item['description']) ?></div>
                    <?php if ($item['source']==='add'): ?>
                    <a href="billing_items.php?patient_id=<?= $patient_id ?>&delete=<?= $idx ?>"
                       class="btn-del" onclick="return confirm('Remove this item?')"
                       style="width:100%;justify-content:center;">
                        <i class="bi bi-trash"></i> Remove
                    </a>
                    <?php else: ?>
                    <span style="font-size:.75rem;color:var(--ink-light);">
                        <i class="bi bi-lock-fill"></i> Dispensed item — locked
                    </span>
                    <?php endif; ?>
                </div>
                <?php endforeach; endif; ?>
            </div>
        </div>
    </div>

    <!-- Totals -->
    <div class="totals-panel">
        <div class="t-row">
            <span class="t-lbl">Subtotal</span>
            <span class="t-val">₱<?= number_format($subtotal, 2) ?></span>
        </div>
        <?php if ($discount > 0): ?>
        <div class="t-row">
            <span class="t-lbl"><?= $is_senior ? 'Senior Citizen' : 'PWD' ?> Discount (20%)</span>
            <span class="t-val disc-val">−₱<?= number_format($discount, 2) ?></span>
        </div>
        <?php endif; ?>
        <div class="t-row grand">
            <span class="t-lbl">Grand Total</span>
            <span class="t-val">₱<?= number_format($grand_total, 2) ?></span>
        </div>
    </div>

    <!-- Actions -->
    <div class="actions-bar">
        <a href="billing_items.php" class="btn-back"><i class="bi bi-arrow-left"></i> Back</a>
        <?php if (!empty($cart)): ?>
        <button class="btn-finalize" onclick="confirmFinalize()">
            <i class="bi bi-check-circle-fill"></i> Finalize Billing
        </button>
        <?php endif; ?>
    </div>

</div>

<script>
function confirmFinalize() {
    Swal.fire({
        title: 'Finalize Billing?',
        html: `Grand Total: <strong>₱<?= number_format($grand_total, 2) ?></strong><br>
               <small style="color:#64748b;">Prescriptions will be marked as billed. This cannot be undone.</small>`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#059669',
        cancelButtonColor:  '#64748b',
        confirmButtonText:  'Yes, Finalize',
        cancelButtonText:   'Cancel'
    }).then(r => {
        if (r.isConfirmed)
            window.location.href = 'billing_items.php?patient_id=<?= $patient_id ?>&finalize=1';
    });
}
(function(){
    const sb = document.getElementById('mySidebar');
    const cw = document.getElementById('mainCw');
    if(!sb||!cw) return;
    function sync(){ cw.classList.toggle('sidebar-collapsed', sb.classList.contains('closed')); }
    new MutationObserver(sync).observe(sb,{attributes:true,attributeFilter:['class']});
    document.getElementById('sidebarToggle')?.addEventListener('click',()=>requestAnimationFrame(sync));
    sync();
})();
</script>
</body>
</html>