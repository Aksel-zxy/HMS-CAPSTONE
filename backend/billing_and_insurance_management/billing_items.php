<?php
session_start();
include '../../SQL/config.php';

$patient_id = isset($_GET['patient_id']) ? intval($_GET['patient_id']) : 0;

/* =========================================================
   PATIENT LIST PAGE
========================================================= */
if ($patient_id <= 0) {
    $active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'outpatient';

    $sql_outpatient = "
        SELECT
            p.patient_id,
            CONCAT(p.fname,' ',IFNULL(NULLIF(p.mname,''),''),' ',p.lname) AS full_name,
            p.admission_type,
            p.age,
            p.gender,
            COUNT(DISTINCT dr.resultID)        AS lab_count,
            COUNT(DISTINCT dnmr.record_id)     AS dnm_count,
            COUNT(DISTINCT pp.prescription_id) AS rx_count
        FROM patientinfo p
        LEFT JOIN dl_results dr
            ON dr.patientID = p.patient_id
            AND dr.status IN ('Completed', 'Delivered')
        LEFT JOIN dnm_records dnmr
            ON dnmr.doctor_id = p.patient_id
        LEFT JOIN pharmacy_prescription pp
            ON pp.patient_id     = p.patient_id
            AND pp.payment_type   = 'post_discharged'
            AND pp.status         = 'Dispensed'
            AND pp.billing_status = 'pending'
        WHERE p.admission_type NOT IN ('Inpatient','Confinement','Emergency','Surgery')
           OR p.admission_type IS NULL
        GROUP BY p.patient_id
        HAVING lab_count > 0 OR dnm_count > 0 OR rx_count > 0
        ORDER BY p.lname ASC, p.fname ASC";

    $sql_inpatient = "
        SELECT
            p.patient_id,
            CONCAT(p.fname,' ',IFNULL(NULLIF(p.mname,''),''),' ',p.lname) AS full_name,
            p.admission_type,
            p.age,
            p.gender,
            p.address,
            p.attending_doctor,
            p.dob,
            COUNT(DISTINCT br.billing_id) AS existing_bills,
            SUM(CASE WHEN br.status = 'Pending' THEN 1 ELSE 0 END) AS pending_bills
        FROM patientinfo p
        LEFT JOIN billing_records br
            ON br.patient_id = p.patient_id AND br.status NOT IN ('Paid','Cancelled')
        WHERE p.admission_type IN ('Inpatient','Confinement','Emergency','Surgery')
        GROUP BY p.patient_id
        ORDER BY p.lname ASC, p.fname ASC";

    $outpatients = $conn->query($sql_outpatient);
    $inpatients  = $conn->query($sql_inpatient);

    $op_count = $outpatients ? $outpatients->num_rows : 0;
    $ip_count = $inpatients  ? $inpatients->num_rows  : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title>Patient Billing</title>
    <link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display&family=DM+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/CSS/bootstrap.min.css">
    <link rel="stylesheet" href="assets/css/billing_sidebar.css">
    <style>
        :root {
            --sidebar-w: 250px;
            --navy: #0b1d3a;
            --accent: #2563eb;
            --success: #059669;
            --warning: #d97706;
            --ink: #1e293b;
            --ink-light: #64748b;
            --border: #e2e8f0;
            --surface: #f1f5f9;
            --card: #fff;
            --radius: 14px;
            --shadow: 0 2px 20px rgba(11,29,58,.08);
            --ff-head: 'DM Serif Display', serif;
            --ff-body: 'DM Sans', sans-serif;
        }
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: var(--ff-body); background: var(--surface); color: var(--ink); }

        .cw { margin-left: var(--sidebar-w); padding: 48px 28px 60px; transition: margin-left .3s; }
        .cw.sidebar-collapsed { margin-left: 0; }

        .page-head { margin-bottom: 28px; }
        .page-head h2 { font-family: var(--ff-head); font-size: 1.7rem; color: var(--navy); }
        .page-head p  { font-size: .85rem; color: var(--ink-light); margin-top: 4px; }

        .tab-nav { display: flex; gap: 0; border-bottom: 2px solid var(--border); margin-bottom: 20px; }
        .tab-btn { display: inline-flex; align-items: center; gap: 8px; padding: 11px 24px; background: none; border: none; border-bottom: 3px solid transparent; margin-bottom: -2px; font-family: var(--ff-body); font-size: .88rem; font-weight: 600; color: var(--ink-light); cursor: pointer; transition: all .18s; text-decoration: none; white-space: nowrap; }
        .tab-btn:hover { color: var(--accent); }
        .tab-btn.active { color: var(--navy); border-bottom-color: var(--accent); }
        .tab-count { display: inline-flex; align-items: center; justify-content: center; min-width: 22px; height: 22px; padding: 0 6px; background: var(--border); color: var(--ink-light); border-radius: 999px; font-size: .65rem; font-weight: 800; }
        .tab-btn.active .tab-count { background: var(--accent); color: #fff; }
        .tab-btn.active.ip-tab .tab-count { background: #7c3aed; }
        .tab-btn.active.ip-tab { color: var(--navy); border-bottom-color: #7c3aed; }

        .search-bar { display: flex; align-items: center; gap: 10px; margin-bottom: 16px; }
        .search-input-wrap { position: relative; flex: 1; max-width: 360px; }
        .search-input-wrap i { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: var(--ink-light); font-size: .9rem; pointer-events: none; }
        .search-inp { width: 100%; padding: 9px 12px 9px 36px; border: 1.5px solid var(--border); border-radius: 9px; font-family: var(--ff-body); font-size: .87rem; color: var(--ink); background: var(--card); outline: none; }
        .search-inp:focus { border-color: var(--accent); box-shadow: 0 0 0 3px rgba(37,99,235,.1); }

        .card { background: var(--card); border: 1px solid var(--border); border-radius: var(--radius); box-shadow: var(--shadow); overflow: hidden; }
        .card-header { padding: 14px 22px; display: flex; align-items: center; gap: 10px; }
        .card-header.op { background: linear-gradient(90deg, var(--navy), #1e40af); }
        .card-header.ip { background: linear-gradient(90deg, #4c1d95, #7c3aed); }
        .card-header h5 { font-family: var(--ff-head); color: #fff; margin: 0; font-size: 1rem; }
        .card-header span { margin-left: auto; font-size: .72rem; color: rgba(255,255,255,.6); font-weight: 600; }

        .tbl { width: 100%; border-collapse: collapse; }
        .tbl thead th { background: #f8fafc; color: var(--ink-light); font-size: .68rem; font-weight: 700; text-transform: uppercase; letter-spacing: .6px; padding: 11px 18px; border-bottom: 2px solid var(--border); text-align: left; }
        .tbl tbody tr { border-bottom: 1px solid var(--border); transition: background .12s; }
        .tbl tbody tr:hover { background: #f7faff; }
        .tbl td { padding: 13px 18px; vertical-align: middle; font-size: .88rem; }

        .pat-cell { display: flex; align-items: center; gap: 10px; }
        .pat-av { width: 38px; height: 38px; border-radius: 50%; color: #fff; display: flex; align-items: center; justify-content: center; font-size: .8rem; font-weight: 700; flex-shrink: 0; }
        .pat-av.op { background: linear-gradient(135deg, var(--navy), var(--accent)); }
        .pat-av.ip { background: linear-gradient(135deg, #4c1d95, #7c3aed); }
        .pat-name  { font-weight: 600; color: var(--navy); }
        .pat-meta  { font-size: .72rem; color: var(--ink-light); margin-top: 2px; }

        .badges { display: flex; gap: 5px; flex-wrap: wrap; }
        .bdg { display: inline-flex; align-items: center; gap: 4px; border-radius: 999px; padding: 3px 10px; font-size: .7rem; font-weight: 700; }
        .bdg-lab  { background: #d1fae5; color: #065f46; }
        .bdg-dnm  { background: #fdf4ff; color: #7e22ce; }
        .bdg-rx   { background: #dbeafe; color: #1d4ed8; }
        .bdg-pending { background: #fef2f2; color: #b91c1c; }
        .bdg-ok   { background: #f0fdf4; color: #166534; border: 1px solid #bbf7d0; }

        .btn-bill, .btn-inpat { display: inline-flex; align-items: center; gap: 5px; border: none; border-radius: 8px; padding: 7px 16px; font-size: .82rem; font-weight: 700; font-family: var(--ff-body); text-decoration: none; transition: background .15s; white-space: nowrap; }
        .btn-bill  { background: var(--accent); color: #fff; }
        .btn-bill:hover { background: #1d4ed8; color: #fff; }
        .btn-inpat { background: #7c3aed; color: #fff; }
        .btn-inpat:hover { background: #6d28d9; color: #fff; }

        .empty-state { text-align: center; padding: 56px 16px; color: var(--ink-light); }
        .empty-state i { font-size: 2.5rem; display: block; margin-bottom: 12px; opacity: .3; }

        .adm-pill { display: inline-flex; align-items: center; gap: 4px; padding: 2px 9px; border-radius: 999px; font-size: .68rem; font-weight: 700; }
        .adm-confinement { background: #ede9fe; color: #5b21b6; }
        .adm-emergency   { background: #fef2f2; color: #991b1b; }
        .adm-surgery     { background: #fff7ed; color: #c2410c; }
        .adm-checkup     { background: #f0f9ff; color: #075985; }
        .adm-default     { background: #f1f5f9; color: #475569; }

        .tbl-row.hidden { display: none; }

        @media(max-width:768px) { .cw { margin-left: 0; padding: 52px 14px; } .tab-btn { padding: 10px 14px; font-size: .8rem; } }
    </style>
</head>
<body>
<?php include 'billing_sidebar.php'; ?>
<div class="cw" id="mainCw">
    <div class="page-head">
        <h2>Patient Billing</h2>
        <p>Manage billing for outpatient and inpatient admissions.</p>
    </div>

    <div class="tab-nav">
        <a href="?tab=outpatient" class="tab-btn <?= $active_tab === 'outpatient' ? 'active' : '' ?>">
            <i class="bi bi-person-badge"></i> Outpatient
            <span class="tab-count"><?= $op_count ?></span>
        </a>
        <a href="?tab=inpatient" class="tab-btn ip-tab <?= $active_tab === 'inpatient' ? 'active' : '' ?>">
            <i class="bi bi-hospital"></i> Inpatient
            <span class="tab-count"><?= $ip_count ?></span>
        </a>
    </div>

    <div class="search-bar">
        <div class="search-input-wrap">
            <i class="bi bi-search"></i>
            <input type="text" id="searchInput" class="search-inp" placeholder="Search patient name..." oninput="filterTable(this.value)">
        </div>
    </div>

    <?php if ($active_tab === 'outpatient'): ?>
    <div class="card">
        <div class="card-header op">
            <i class="bi bi-people-fill" style="color:rgba(255,255,255,.6);font-size:1.1rem;"></i>
            <h5>Outpatients — Pending Billing Items</h5>
            <span><?= $op_count ?> patient<?= $op_count !== 1 ? 's' : '' ?></span>
        </div>
        <div style="overflow-x:auto;">
            <table class="tbl" id="mainTable">
                <thead><tr><th>Patient</th><th>Pending Items</th><th style="text-align:right;">Action</th></tr></thead>
                <tbody>
                    <?php if ($outpatients && $outpatients->num_rows > 0):
                        while ($row = $outpatients->fetch_assoc()):
                            $ini = strtoupper(substr(trim($row['full_name']), 0, 1)); ?>
                    <tr class="tbl-row">
                        <td>
                            <div class="pat-cell">
                                <div class="pat-av op"><?= $ini ?></div>
                                <div>
                                    <div class="pat-name"><?= htmlspecialchars(trim($row['full_name'])) ?></div>
                                    <div class="pat-meta"><?= htmlspecialchars($row['gender'] ?? '—') ?><?php if (!empty($row['age'])): ?> · <?= $row['age'] ?> yrs<?php endif; ?></div>
                                </div>
                            </div>
                        </td>
                        <td>
                            <div class="badges">
                                <?php if ($row['lab_count'] > 0): ?><span class="bdg bdg-lab"><i class="bi bi-eyedropper"></i> <?= $row['lab_count'] ?> Lab</span><?php endif; ?>
                                <?php if ($row['dnm_count'] > 0): ?><span class="bdg bdg-dnm"><i class="bi bi-clipboard2-pulse"></i> <?= $row['dnm_count'] ?> Procedure</span><?php endif; ?>
                                <?php if ($row['rx_count'] > 0):  ?><span class="bdg bdg-rx"><i class="bi bi-capsule"></i> <?= $row['rx_count'] ?> Rx</span><?php endif; ?>
                            </div>
                        </td>
                        <td style="text-align:right;"><a href="billing_items.php?patient_id=<?= $row['patient_id'] ?>" class="btn-bill"><i class="bi bi-receipt"></i> Create Bill</a></td>
                    </tr>
                    <?php endwhile; else: ?>
                    <tr><td colspan="3"><div class="empty-state"><i class="bi bi-inbox"></i>No outpatients with pending billing items.</div></td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php elseif ($active_tab === 'inpatient'): ?>
    <div class="card">
        <div class="card-header ip">
            <i class="bi bi-hospital-fill" style="color:rgba(255,255,255,.6);font-size:1.1rem;"></i>
            <h5>Inpatients — Admitted Patients</h5>
            <span><?= $ip_count ?> patient<?= $ip_count !== 1 ? 's' : '' ?></span>
        </div>
        <div style="overflow-x:auto;">
            <table class="tbl" id="mainTable">
                <thead><tr><th>Patient</th><th>Admission Type</th><th>Billing Status</th><th style="text-align:right;">Action</th></tr></thead>
                <tbody>
                    <?php if ($inpatients && $inpatients->num_rows > 0):
                        while ($row = $inpatients->fetch_assoc()):
                            $ini = strtoupper(substr(trim($row['full_name']), 0, 1));
                            $adm_type  = strtolower($row['admission_type'] ?? '');
                            $adm_class = match(true) {
                                str_contains($adm_type, 'confin') => 'adm-confinement',
                                str_contains($adm_type, 'emerg')  => 'adm-emergency',
                                str_contains($adm_type, 'surg')   => 'adm-surgery',
                                str_contains($adm_type, 'check')  => 'adm-checkup',
                                default => 'adm-default'
                            };
                            $adm_icon = match(true) {
                                str_contains($adm_type, 'confin') => 'bi-building-fill-add',
                                str_contains($adm_type, 'emerg')  => 'bi-exclamation-octagon-fill',
                                str_contains($adm_type, 'surg')   => 'bi-scissors',
                                default => 'bi-clipboard2-pulse'
                            };
                            $age_display = '—';
                            if (!empty($row['dob']) && $row['dob'] !== '0000-00-00') {
                                $age_display = (new DateTime())->diff(new DateTime($row['dob']))->y . ' yrs';
                            }
                    ?>
                    <tr class="tbl-row">
                        <td>
                            <div class="pat-cell">
                                <div class="pat-av ip"><?= $ini ?></div>
                                <div>
                                    <div class="pat-name"><?= htmlspecialchars(trim($row['full_name'])) ?></div>
                                    <div class="pat-meta">
                                        <?= htmlspecialchars($row['gender'] ?? '—') ?> · <?= $age_display ?>
                                        <?php if (!empty($row['address'])): ?> · <?= htmlspecialchars(substr($row['address'], 0, 35)) . (strlen($row['address']) > 35 ? '…' : '') ?><?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </td>
                        <td><span class="adm-pill <?= $adm_class ?>"><i class="bi <?= $adm_icon ?>"></i> <?= htmlspecialchars(ucfirst($row['admission_type'] ?? '—')) ?></span></td>
                        <td>
                            <?php if ($row['pending_bills'] > 0): ?>
                                <span class="bdg bdg-pending"><i class="bi bi-clock-fill"></i> <?= $row['pending_bills'] ?> Pending</span>
                            <?php elseif ($row['existing_bills'] > 0): ?>
                                <span class="bdg bdg-ok"><i class="bi bi-check-circle-fill"></i> Billed</span>
                            <?php else: ?>
                                <span class="bdg" style="background:#f1f5f9;color:#64748b;"><i class="bi bi-dash-circle"></i> No Bill Yet</span>
                            <?php endif; ?>
                        </td>
                        <td style="text-align:right;">
                            <a href="billing_items.php?patient_id=<?= $row['patient_id'] ?>" class="btn-inpat">
                                <i class="bi bi-receipt-cutoff"></i>
                                <?= $row['pending_bills'] > 0 ? 'Update Bill' : ($row['existing_bills'] > 0 ? 'View/Edit Bill' : 'Create Bill') ?>
                            </a>
                        </td>
                    </tr>
                    <?php endwhile; else: ?>
                    <tr><td colspan="4"><div class="empty-state"><i class="bi bi-hospital"></i>No inpatient admissions found.</div></td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

</div>

<script>
    function filterTable(q) {
        const rows = document.querySelectorAll('#mainTable .tbl-row');
        const term = q.toLowerCase().trim();
        rows.forEach(row => {
            const name = row.querySelector('.pat-name')?.textContent.toLowerCase() || '';
            row.classList.toggle('hidden', term !== '' && !name.includes(term));
        });
    }
    (function() {
        const sb = document.getElementById('mySidebar'), cw = document.getElementById('mainCw');
        if (!sb || !cw) return;
        function sync() { cw.classList.toggle('sidebar-collapsed', sb.classList.contains('closed')); }
        new MutationObserver(sync).observe(sb, { attributes: true, attributeFilter: ['class'] });
        document.getElementById('sidebarToggle')?.addEventListener('click', () => requestAnimationFrame(sync));
        sync();
    })();
</script>
</body>
</html>
<?php exit;
}

/* =========================================================
   LOAD PATIENT
========================================================= */
$stmt = $conn->prepare("SELECT * FROM patientinfo WHERE patient_id=?");
$stmt->bind_param("i", $patient_id);
$stmt->execute();
$patient = $stmt->get_result()->fetch_assoc();
if (!$patient) die("Patient not found.");

$age = 0;
$dob_display = '—';
if (!empty($patient['dob']) && $patient['dob'] !== '0000-00-00') {
    $dobObj      = new DateTime($patient['dob']);
    $age         = (new DateTime())->diff($dobObj)->y;
    $dob_display = $dobObj->format('F d, Y');
}
$is_senior = $age >= 60;

/* PWD TOGGLE */
if (isset($_GET['toggle_pwd'])) {
    $_SESSION['is_pwd'][$patient_id] = (int)$_GET['toggle_pwd'];
    header("Location: billing_items.php?patient_id=$patient_id");
    exit;
}
$is_pwd = $_SESSION['is_pwd'][$patient_id] ?? (int)($patient['is_pwd'] ?? 0);

$is_inpatient = in_array($patient['admission_type'] ?? '', ['Inpatient','Confinement','Emergency','Surgery']);

/* =========================================================
   ROOM TYPES
========================================================= */
$room_types_res = $conn->query("SELECT id, name, description, price_per_hour, price_per_day, capacity FROM billing_room_types WHERE is_active=1 ORDER BY price_per_day ASC");
$room_types = [];
while ($rt = $room_types_res->fetch_assoc()) $room_types[] = $rt;

/* =========================================================
   INPATIENT STAY INFO — load or init from session
========================================================= */
$stay_key = 'inpat_stay_' . $patient_id;
if (!isset($_SESSION[$stay_key])) {
    // Try to load from patient_billing if already saved
    $stay_stmt = $conn->prepare("SELECT pb.room_type_id, pb.hours_stay, pb.room_total, pb.notes,
        ir.admission_date, ir.discharge_date, ir.room_no
        FROM patient_billing pb
        LEFT JOIN inpatient_registration ir ON ir.patient_id = pb.patient_id
        WHERE pb.patient_id = ? ORDER BY pb.billing_id DESC LIMIT 1");
    if ($stay_stmt) {
        $stay_stmt->bind_param("i", $patient_id);
        $stay_stmt->execute();
        $saved_stay = $stay_stmt->get_result()->fetch_assoc();
        if ($saved_stay) {
            $_SESSION[$stay_key] = [
                'room_type_id'   => $saved_stay['room_type_id'] ?? 0,
                'room_no'        => $saved_stay['room_no'] ?? '',
                'admission_date' => $saved_stay['admission_date'] ?? '',
                'discharge_date' => $saved_stay['discharge_date'] ?? '',
                'hours_stay'     => $saved_stay['hours_stay'] ?? 0,
                'room_total'     => $saved_stay['room_total'] ?? 0,
                'is_discharged'  => !empty($saved_stay['discharge_date']),
            ];
        } else {
            $_SESSION[$stay_key] = [
                'room_type_id'   => 0,
                'room_no'        => '',
                'admission_date' => date('Y-m-d\TH:i'),
                'discharge_date' => '',
                'hours_stay'     => 0,
                'room_total'     => 0,
                'is_discharged'  => false,
            ];
        }
    }
}
$stay = &$_SESSION[$stay_key];

/* =========================================================
   SAVE STAY INFO (Save button — not yet discharged)
========================================================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_stay'])) {
    $stay['room_type_id']   = (int)$_POST['room_type_id'];
    $stay['room_no']        = trim($_POST['room_no'] ?? '');
    $stay['admission_date'] = $_POST['admission_date'] ?? '';
    $stay['discharge_date'] = $_POST['discharge_date'] ?? '';
    $stay['is_discharged']  = !empty($stay['discharge_date']);

    // Calculate hours & room total
    $room_total = 0;
    $hours_stay = 0;
    if ($stay['room_type_id'] > 0 && !empty($stay['admission_date'])) {
        foreach ($room_types as $rt) {
            if ($rt['id'] == $stay['room_type_id']) {
                $admit_dt = new DateTime($stay['admission_date']);
                $end_dt   = (!empty($stay['discharge_date'])) ? new DateTime($stay['discharge_date']) : new DateTime();
                $diff_sec = max(0, $end_dt->getTimestamp() - $admit_dt->getTimestamp());
                $hours_stay = round($diff_sec / 3600, 2);
                $days       = ceil($hours_stay / 24);
                $room_total = $days * $rt['price_per_day'];
                break;
            }
        }
    }
    $stay['hours_stay'] = $hours_stay;
    $stay['room_total'] = $room_total;

    header("Location: billing_items.php?patient_id=$patient_id&saved=1");
    exit;
}

/* EXISTING UNPAID BILL */
$stmt = $conn->prepare("SELECT billing_id,status,grand_total FROM billing_records WHERE patient_id=? AND status NOT IN ('Paid') ORDER BY billing_id DESC LIMIT 1");
$stmt->bind_param("i", $patient_id);
$stmt->execute();
$existing_bill = $stmt->get_result()->fetch_assoc();

/* =========================================================
   HANDLE MANUALLY-ADDED EXTRA ITEMS
========================================================= */

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_lab'])) {
    $svc_id = (int)$_POST['lab_service_id'];
    $qty    = max(1, (int)($_POST['lab_qty'] ?? 1));
    if ($svc_id > 0) {
        $s = $conn->prepare("SELECT serviceID,serviceName,price FROM dl_services WHERE serviceID=? LIMIT 1");
        $s->bind_param("i", $svc_id);
        $s->execute();
        $svc = $s->get_result()->fetch_assoc();
        if ($svc) {
            if (!isset($_SESSION['billing_extra'][$patient_id])) $_SESSION['billing_extra'][$patient_id] = [];
            $_SESSION['billing_extra'][$patient_id][] = [
                'cart_key'    => 'XLAB-' . $svc_id . '-' . time(),
                'ref_id'      => $svc_id,
                'med_id'      => null,
                'serviceName' => $svc['serviceName'],
                'description' => 'Added Lab' . ($qty > 1 ? ' × ' . $qty : '') . ' — ₱' . number_format($svc['price'], 2) . '/unit',
                'price'       => round($svc['price'] * $qty, 2),
                'source'      => 'add_lab',
                'category'    => 'laboratory',
            ];
        }
    }
    header("Location: billing_items.php?patient_id=$patient_id"); exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_service'])) {
    $proc_id = (int)$_POST['procedure_id'];
    $qty     = max(1, (int)($_POST['svc_qty'] ?? 1));
    if ($proc_id > 0) {
        $s = $conn->prepare("SELECT procedure_id,procedure_name,price FROM dnm_procedure_list WHERE procedure_id=? AND status='Active' LIMIT 1");
        $s->bind_param("i", $proc_id);
        $s->execute();
        $proc = $s->get_result()->fetch_assoc();
        if ($proc) {
            if (!isset($_SESSION['billing_extra'][$patient_id])) $_SESSION['billing_extra'][$patient_id] = [];
            $_SESSION['billing_extra'][$patient_id][] = [
                'cart_key'    => 'XSVC-' . $proc_id . '-' . time(),
                'ref_id'      => $proc_id,
                'med_id'      => null,
                'serviceName' => $proc['procedure_name'],
                'description' => 'Added' . ($qty > 1 ? ' × ' . $qty : '') . ' — ₱' . number_format($proc['price'], 2) . '/unit',
                'price'       => round($proc['price'] * $qty, 2),
                'source'      => 'add_svc',
                'category'    => 'service',
            ];
        }
    }
    header("Location: billing_items.php?patient_id=$patient_id"); exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_medicine'])) {
    $med_id = (int)$_POST['med_id'];
    $qty    = max(1, (int)($_POST['qty'] ?? 1));
    if ($med_id > 0) {
        $s = $conn->prepare("SELECT med_id,med_name,dosage,unit_price FROM pharmacy_inventory WHERE med_id=? LIMIT 1");
        $s->bind_param("i", $med_id);
        $s->execute();
        $med = $s->get_result()->fetch_assoc();
        if ($med) {
            if (!isset($_SESSION['billing_extra'][$patient_id])) $_SESSION['billing_extra'][$patient_id] = [];
            $_SESSION['billing_extra'][$patient_id][] = [
                'cart_key'    => 'XMED-' . $med_id . '-' . time(),
                'ref_id'      => $med_id,
                'med_id'      => $med['med_id'],
                'serviceName' => $med['med_name'] . (!empty($med['dosage']) ? ' (' . $med['dosage'] . ')' : ''),
                'description' => 'Added Medicine — Qty:' . $qty . ' × ₱' . number_format($med['unit_price'], 2),
                'price'       => round($med['unit_price'] * $qty, 2),
                'source'      => 'add_med',
                'category'    => 'medicine',
            ];
        }
    }
    header("Location: billing_items.php?patient_id=$patient_id"); exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_inpat_service'])) {
    $svc_id = (int)$_POST['inpat_service_id'];
    $qty    = max(1, (int)($_POST['inpat_qty'] ?? 1));
    if ($svc_id > 0) {
        $s = $conn->prepare("SELECT id as service_id,name,base_price,category,unit FROM billing_services WHERE id=? AND is_active=1 LIMIT 1");
        $s->bind_param("i", $svc_id);
        $s->execute();
        $svc = $s->get_result()->fetch_assoc();
        if ($svc) {
            if (!isset($_SESSION['billing_extra'][$patient_id])) $_SESSION['billing_extra'][$patient_id] = [];
            $cat_map = [
                'Surgery' => 'service', 'Confinement' => 'service',
                'Emergency' => 'service', 'CheckUp' => 'service',
                'Procedure' => 'service', 'Laboratory' => 'laboratory',
                'Imaging' => 'laboratory', 'Medicine' => 'medicine',
                'Supply' => 'medicine', 'Other' => 'service',
            ];
            $_SESSION['billing_extra'][$patient_id][] = [
                'cart_key'    => 'XINP-' . $svc_id . '-' . time(),
                'ref_id'      => $svc_id,
                'med_id'      => null,
                'serviceName' => $svc['name'],
                'description' => ucfirst(strtolower($svc['category'])) . ' — Qty:' . $qty . ' × ₱' . number_format($svc['base_price'], 2) . '/' . $svc['unit'],
                'price'       => round($svc['base_price'] * $qty, 2),
                'source'      => 'add_inpat',
                'category'    => $cat_map[$svc['category']] ?? 'service',
            ];
        }
    }
    header("Location: billing_items.php?patient_id=$patient_id"); exit;
}

if (isset($_GET['delete'])) {
    $idx    = (int)$_GET['delete'];
    $extras = $_SESSION['billing_extra'][$patient_id] ?? [];
    if (isset($extras[$idx]) && in_array($extras[$idx]['source'], ['add_lab','add_svc','add_med','add_inpat'])) {
        unset($extras[$idx]);
        $_SESSION['billing_extra'][$patient_id] = array_values($extras);
    }
    header("Location: billing_items.php?patient_id=$patient_id"); exit;
}

if (isset($_GET['reset_cart'])) {
    unset($_SESSION['billing_extra'][$patient_id]);
    header("Location: billing_items.php?patient_id=$patient_id"); exit;
}

/* =========================================================
   PRICE HELPER
========================================================= */
function findLabPrice(string $name, array $svc_map): float {
    $key = strtolower(trim($name));
    if (isset($svc_map[$key])) return $svc_map[$key];
    $kw = strtolower(trim(preg_replace('/\s*\(.*?\)/', '', $name)));
    $kw = trim(strtok($kw, ' '));
    if (strlen($kw) >= 2) {
        foreach ($svc_map as $k => $p) {
            if (strpos($k, $kw) !== false) return $p;
        }
    }
    return 0.0;
}

/* =========================================================
   BUILD AUTO ITEMS
========================================================= */
$auto_items = [];

$svc_map = [];
$svr = $conn->query("SELECT serviceName, price FROM dl_services ORDER BY serviceID");
if ($svr) while ($sv = $svr->fetch_assoc()) $svc_map[strtolower(trim($sv['serviceName']))] = (float)$sv['price'];

$ls = $conn->prepare("SELECT resultID, resultDate, result AS serviceName FROM dl_results WHERE patientID=? AND status IN ('Completed', 'Delivered') ORDER BY resultDate ASC");
if ($ls) {
    $ls->bind_param("i", $patient_id); $ls->execute();
    foreach ($ls->get_result()->fetch_all(MYSQLI_ASSOC) as $row) {
        $sname = trim($row['serviceName']);
        $auto_items[] = [
            'cart_key'    => 'LAB-' . $row['resultID'],
            'ref_id'      => $row['resultID'],
            'med_id'      => null,
            'serviceName' => $sname ?: 'Laboratory Service',
            'description' => 'Lab Result — ' . date('M d, Y', strtotime($row['resultDate'])),
            'price'       => findLabPrice($sname, $svc_map),
            'source'      => 'lab',
            'category'    => 'laboratory',
        ];
    }
}

$ds = @$conn->prepare("SELECT dnmr.record_id, dnmr.procedure_name, dnmr.amount, dnmr.created_at FROM dnm_records dnmr WHERE dnmr.duty_id IN (SELECT da.duty_id FROM dl_results dr JOIN dl_schedule ds ON ds.scheduleID=dr.scheduleID JOIN duty_assignments da ON da.appointment_id=ds.scheduleID WHERE dr.patientID=?) ORDER BY dnmr.created_at ASC");
if ($ds) {
    $ds->bind_param("i", $patient_id); $ds->execute();
    foreach ($ds->get_result()->fetch_all(MYSQLI_ASSOC) as $row) {
        $dnm_price = (float)$row['amount'];
        if ($dnm_price == 0 && !empty($row['procedure_name'])) {
            $ps = $conn->prepare("SELECT price FROM dnm_procedure_list WHERE LOWER(TRIM(procedure_name))=LOWER(TRIM(?)) AND status='Active' LIMIT 1");
            if ($ps) { $ps->bind_param("s", $row['procedure_name']); $ps->execute(); $pr = $ps->get_result()->fetch_assoc(); if ($pr) $dnm_price = (float)$pr['price']; }
        }
        $auto_items[] = [
            'cart_key'    => 'DNM-' . $row['record_id'],
            'ref_id'      => $row['record_id'],
            'med_id'      => null,
            'serviceName' => $row['procedure_name'],
            'description' => 'Procedure — ' . date('M d, Y', strtotime($row['created_at'])),
            'price'       => $dnm_price,
            'source'      => 'dnm',
            'category'    => 'service',
        ];
    }
}

$rs = $conn->prepare("SELECT ppi.item_id, ppi.med_id, ppi.dosage AS rx_dosage, ppi.frequency, ppi.quantity_dispensed, ppi.unit_price, ppi.total_price, pi2.med_name, pi2.dosage AS inv_dosage, pi2.unit_price AS inv_unit_price FROM pharmacy_prescription pp JOIN pharmacy_prescription_items ppi ON ppi.prescription_id=pp.prescription_id JOIN pharmacy_inventory pi2 ON pi2.med_id=ppi.med_id WHERE pp.patient_id=? AND pp.payment_type='post_discharged' AND pp.status='Dispensed' AND pp.billing_status='pending' ORDER BY ppi.item_id ASC");
if ($rs) {
    $rs->bind_param("i", $patient_id); $rs->execute();
    foreach ($rs->get_result()->fetch_all(MYSQLI_ASSOC) as $row) {
        $dose     = trim(($row['rx_dosage'] ?? $row['inv_dosage'] ?? '') . ' ' . ($row['frequency'] ?? ''));
        $rx_total = (float)$row['total_price'];
        if ($rx_total == 0) { $unit = (float)($row['unit_price'] ?: $row['inv_unit_price'] ?: 0); $rx_total = round($unit * (int)($row['quantity_dispensed'] ?: 1), 2); }
        $auto_items[] = [
            'cart_key'    => 'RX-' . $row['item_id'],
            'ref_id'      => $row['item_id'],
            'med_id'      => $row['med_id'],
            'serviceName' => $row['med_name'] . ($dose ? ' (' . $dose . ')' : ''),
            'description' => 'Dispensed — Qty:' . $row['quantity_dispensed'] . ' × ₱' . number_format((float)($row['unit_price'] ?: $row['inv_unit_price']), 2),
            'price'       => $rx_total,
            'source'      => 'rx',
            'category'    => 'medicine',
        ];
    }
}

$extra_items = array_values($_SESSION['billing_extra'][$patient_id] ?? []);
$auto_count  = count($auto_items);
$cart        = array_merge($auto_items, $extra_items);

/* =========================================================
   FINALIZE
========================================================= */
if (isset($_GET['finalize']) && $_GET['finalize'] == 1) {
    $subtotal_f  = array_sum(array_column($cart, 'price'));
    $room_total_f = (float)($stay['room_total'] ?? 0);
    $subtotal_f  += $room_total_f;
    $discount_f  = ($is_pwd || $is_senior) ? round($subtotal_f * 0.20, 2) : 0.00;
    $grand_f     = round($subtotal_f - $discount_f, 2);
    if ($grand_f <= 0) { header("Location: billing_items.php?patient_id=$patient_id&err=zero_total"); exit; }

    $txn = 'TXN-' . strtoupper(uniqid());
    if ($existing_bill) {
        $billing_id = $existing_bill['billing_id'];
        $s = $conn->prepare("UPDATE billing_records SET total_amount=?,grand_total=?,status='Pending',transaction_id=? WHERE billing_id=?");
        $s->bind_param("ddsi", $subtotal_f, $grand_f, $txn, $billing_id); $s->execute();
        $d = $conn->prepare("DELETE FROM billing_items WHERE billing_id=?");
        $d->bind_param("i", $billing_id); $d->execute();
    } else {
        $s = $conn->prepare("INSERT INTO billing_records(patient_id,billing_date,total_amount,grand_total,status,transaction_id) VALUES(?,NOW(),?,?,'Pending',?)");
        $s->bind_param("idds", $patient_id, $subtotal_f, $grand_f, $txn); $s->execute();
        $billing_id = $conn->insert_id;
    }

    // Save room item if applicable
    if ($stay['room_type_id'] > 0 && $room_total_f > 0) {
        $s = $conn->prepare("INSERT INTO billing_items(billing_id,patient_id,service_id,quantity,unit_price,total_price,finalized) VALUES(?,?,?,1,?,?,1)");
        $rt_id = (int)$stay['room_type_id'];
        $s->bind_param("iiidd", $billing_id, $patient_id, $rt_id, $room_total_f, $room_total_f); $s->execute();
    }

    foreach ($cart as $item) {
        $service_id = (int)($item['ref_id'] ?? 0);
        $s = $conn->prepare("INSERT INTO billing_items(billing_id,patient_id,service_id,quantity,unit_price,total_price,finalized) VALUES(?,?,?,1,?,?,1)");
        $s->bind_param("iiidd", $billing_id, $patient_id, $service_id, $item['price'], $item['price']); $s->execute();
    }

    // Update patient_billing header with room + stay info
    $hours_f  = (float)($stay['hours_stay'] ?? 0);
    $rt_id_f  = (int)($stay['room_type_id'] ?? 0);
    $adm_date = $stay['admission_date'] ?? null;
    $dis_date = $stay['discharge_date'] ?? null;

    $chk_pb = $conn->prepare("SELECT billing_id FROM patient_billing WHERE patient_id=? ORDER BY billing_id DESC LIMIT 1");
    $chk_pb->bind_param("i", $patient_id); $chk_pb->execute();
    $existing_pb = $chk_pb->get_result()->fetch_assoc();

    $bill_no  = 'BILL-' . date('Y') . '-' . str_pad($billing_id, 5, '0', STR_PAD_LEFT);
    $svc_tot  = array_sum(array_column(array_filter($cart, fn($c) => $c['category'] === 'service'),    'price'));
    $med_tot  = array_sum(array_column(array_filter($cart, fn($c) => $c['category'] === 'medicine'),   'price'));
    $lab_tot  = array_sum(array_column(array_filter($cart, fn($c) => $c['category'] === 'laboratory'), 'price'));

    if ($existing_pb) {
        $u = $conn->prepare("UPDATE patient_billing SET room_type_id=?,hours_stay=?,room_total=?,services_total=?,medicines_total=?,supplies_total=?,gross_total=?,discount_amount=?,amount_due=?,payment_status='Pending',finalized=1 WHERE billing_id=?");
        $u->bind_param("idddddddddi", $rt_id_f, $hours_f, $room_total_f, $svc_tot, $med_tot, $lab_tot, $grand_f, $discount_f, $grand_f, $existing_pb['billing_id']);
        $u->execute();
    } else {
        $i = $conn->prepare("INSERT INTO patient_billing(patient_id,bill_number,billing_date,room_type_id,hours_stay,room_total,services_total,medicines_total,supplies_total,gross_total,discount_amount,amount_due,payment_status,finalized,created_by) VALUES(?,?,NOW(),?,?,?,?,?,?,?,?,?,'Pending',1,'admin')");
        $i->bind_param("isiddddddddd", $patient_id, $bill_no, $rt_id_f, $hours_f, $room_total_f, $svc_tot, $med_tot, $lab_tot, $grand_f, $discount_f, $grand_f);
        $i->execute();
    }

    // Update inpatient_registration admission/discharge if exists
    $chk_ir = $conn->prepare("SELECT patient_id FROM inpatient_registration WHERE patient_id=? LIMIT 1");
    $chk_ir->bind_param("i", $patient_id); $chk_ir->execute();
    if ($chk_ir->get_result()->fetch_assoc()) {
        $u = $conn->prepare("UPDATE inpatient_registration SET room_type_id=?,admission_date=?,discharge_date=?,billing_status='Pending',status='Discharged' WHERE patient_id=?");
        $u->bind_param("issi", $rt_id_f, $adm_date, $dis_date, $patient_id); $u->execute();
    }

    $pwd_flag = ($is_pwd || $is_senior) ? 1 : 0;
    $chk = $conn->prepare("SELECT receipt_id FROM patient_receipt WHERE billing_id=? LIMIT 1");
    $chk->bind_param("i", $billing_id); $chk->execute();
    $existing_receipt = $chk->get_result()->fetch_assoc();
    if ($existing_receipt) {
        $r = $conn->prepare("UPDATE patient_receipt SET total_charges=?,total_discount=?,grand_total=?,total_out_of_pocket=?,status='Pending',transaction_id=?,is_pwd=? WHERE billing_id=?");
        $r->bind_param("ddddsii", $subtotal_f, $discount_f, $grand_f, $grand_f, $txn, $pwd_flag, $billing_id); $r->execute();
    } else {
        $r = $conn->prepare("INSERT INTO patient_receipt(patient_id,billing_id,total_charges,total_vat,total_discount,total_out_of_pocket,grand_total,status,transaction_id,is_pwd) VALUES(?,?,?,0,?,?,?,'Pending',?,?)");
        $r->bind_param("iiddddsi", $patient_id, $billing_id, $subtotal_f, $discount_f, $grand_f, $grand_f, $txn, $pwd_flag); $r->execute();
    }

    $u = $conn->prepare("UPDATE pharmacy_prescription SET billing_status='billed' WHERE patient_id=? AND payment_type='post_discharged' AND billing_status='pending'");
    $u->bind_param("i", $patient_id); $u->execute();

    unset($_SESSION['billing_extra'][$patient_id]);
    unset($_SESSION[$stay_key]);
    header("Location: patient_billing.php?patient_id=$patient_id"); exit;
}

/* =========================================================
   CART TOTALS & GROUPING
========================================================= */
$subtotal    = array_sum(array_column($cart, 'price'));
$room_total  = (float)($stay['room_total'] ?? 0);
$subtotal_with_room = $subtotal + $room_total;
$discount    = ($is_pwd || $is_senior) ? $subtotal_with_room * 0.20 : 0;
$grand_total = $subtotal_with_room - $discount;

$cat_services   = array_values(array_filter($cart, fn($c) => $c['category'] === 'service'));
$cat_laboratory = array_values(array_filter($cart, fn($c) => $c['category'] === 'laboratory'));
$cat_medicines  = array_values(array_filter($cart, fn($c) => $c['category'] === 'medicine'));

$svc_total = array_sum(array_column($cat_services,   'price'));
$lab_total = array_sum(array_column($cat_laboratory, 'price'));
$med_total = array_sum(array_column($cat_medicines,  'price'));
$can_finalize = (!empty($cart) || $room_total > 0) && $grand_total > 0 && !empty($stay['discharge_date']);

/* Room type lookup for display */
$selected_room = null;
foreach ($room_types as $rt) {
    if ($rt['id'] == $stay['room_type_id']) { $selected_room = $rt; break; }
}

/* Compute live hours for display */
$live_hours = 0;
$live_days  = 0;
if (!empty($stay['admission_date'])) {
    $admit_dt  = new DateTime($stay['admission_date']);
    $end_dt    = !empty($stay['discharge_date']) ? new DateTime($stay['discharge_date']) : new DateTime();
    $diff_sec  = max(0, $end_dt->getTimestamp() - $admit_dt->getTimestamp());
    $live_hours = round($diff_sec / 3600, 2);
    $live_days  = ceil($live_hours / 24);
}

/* Dropdowns */
$used_lab_names = [];
foreach ($cart as $item) if ($item['category'] === 'laboratory') $used_lab_names[] = strtolower(trim($item['serviceName']));
$lab_svc_res = $conn->query("SELECT serviceID,serviceName,price FROM dl_services ORDER BY serviceName");
$lab_services = [];
while ($ls = $lab_svc_res->fetch_assoc()) {
    if (!in_array(strtolower(trim($ls['serviceName'])), $used_lab_names)) $lab_services[] = $ls;
}

$used_proc_names = [];
foreach ($cart as $item) if ($item['category'] === 'service') $used_proc_names[] = strtolower(trim($item['serviceName']));
$proc_res = $conn->query("SELECT procedure_id,procedure_name,price FROM dnm_procedure_list WHERE status='Active' ORDER BY procedure_name");
$procedures = [];
while ($p = $proc_res->fetch_assoc()) {
    if (!in_array(strtolower(trim($p['procedure_name'])), $used_proc_names)) $procedures[] = $p;
}

$seeded_ids = array_unique(array_filter(array_column($cart, 'med_id')));
if ($seeded_ids) {
    $ph   = implode(',', array_fill(0, count($seeded_ids), '?'));
    $stmt = $conn->prepare("SELECT med_id,med_name,dosage,unit_price FROM pharmacy_inventory WHERE med_id NOT IN ($ph) ORDER BY med_name");
    $stmt->bind_param(str_repeat('i', count($seeded_ids)), ...$seeded_ids); $stmt->execute();
    $med_res = $stmt->get_result();
} else {
    $med_res = $conn->query("SELECT med_id,med_name,dosage,unit_price FROM pharmacy_inventory ORDER BY med_name");
}
$medicines = [];
while ($m = $med_res->fetch_assoc()) $medicines[] = $m;

$inpat_services_res = $conn->query("SELECT id as service_id,category,name,base_price,unit FROM billing_services WHERE is_active=1 ORDER BY category,name");
$inpat_services_by_cat = [];
while ($is = $inpat_services_res->fetch_assoc()) $inpat_services_by_cat[$is['category']][] = $is;

$full_name  = trim($patient['fname'] . ' ' . ($patient['mname'] ?? '') . ' ' . $patient['lname']);
$gender     = ucfirst($patient['gender'] ?? '—');
$contact    = $patient['phone_number'] ?? $patient['contact_no'] ?? $patient['phone'] ?? '—';
$address    = $patient['address'] ?? '—';
$patient_no = $patient['patient_id'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0,viewport-fit=cover">
    <title>Patient Bill — <?= htmlspecialchars($full_name) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display&family=DM+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/CSS/bootstrap.min.css">
    <link rel="stylesheet" href="assets/css/billing_sidebar.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        :root {
            --sidebar-w: 250px;
            --navy: #0b1d3a;
            --accent: #2563eb;
            --success: #059669;
            --danger: #dc2626;
            --ink: #1e293b;
            --ink-light: #64748b;
            --border: #e2e8f0;
            --surface: #f1f5f9;
            --card: #fff;
            --radius: 14px;
            --shadow: 0 2px 20px rgba(11,29,58,.08);
            --ff-head: 'DM Serif Display', serif;
            --ff-body: 'DM Sans', sans-serif;
            --c-lab: #065f46; --bg-lab: #d1fae5;
            --c-svc: #7e22ce; --bg-svc: #fdf4ff;
            --c-rx:  #1d4ed8; --bg-rx:  #dbeafe;
            --inpat: #7c3aed;
        }
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: var(--ff-body); background: var(--surface); color: var(--ink); }

        .cw { margin-left: var(--sidebar-w); padding: 44px 28px 80px; transition: margin-left .3s; }
        .cw.sidebar-collapsed { margin-left: 0; }

        /* ── INPATIENT BANNER ── */
        .inpat-banner { display: flex; align-items: center; gap: 12px; padding: 13px 20px; margin-bottom: 16px; background: linear-gradient(135deg, #ede9fe, #faf5ff); border: 1.5px solid #c4b5fd; border-radius: var(--radius); font-size: .87rem; font-weight: 600; color: #4c1d95; flex-wrap: wrap; }
        .inpat-banner i { font-size: 1.2rem; }
        .inpat-type-pill { display: inline-flex; align-items: center; gap: 5px; padding: 3px 12px; background: #7c3aed; color: #fff; border-radius: 999px; font-size: .72rem; font-weight: 800; }

        /* ── STAY INFO CARD ── */
        .stay-card { background: var(--card); border: 1.5px solid #c4b5fd; border-radius: var(--radius); box-shadow: var(--shadow); overflow: hidden; margin-bottom: 16px; }
        .stay-card-head { padding: 13px 20px; background: linear-gradient(135deg, #3b0764, #7c3aed); display: flex; align-items: center; gap: 10px; }
        .stay-card-head h5 { font-family: var(--ff-head); color: #fff; margin: 0; font-size: 1rem; }
        .stay-card-head .sc-badge { margin-left: auto; background: rgba(255,255,255,.2); color: #fff; border-radius: 999px; padding: 3px 11px; font-size: .69rem; font-weight: 700; display: flex; align-items: center; gap: 5px; }
        .stay-card-head .sc-badge.saved { background: #d1fae5; color: #065f46; }
        .stay-form { padding: 18px 20px; }
        .stay-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px,1fr)); gap: 14px; margin-bottom: 16px; }
        .stay-field label { display: block; font-size: .67rem; font-weight: 700; text-transform: uppercase; letter-spacing: .55px; color: var(--ink-light); margin-bottom: 5px; }
        .stay-field input, .stay-field select {
            width: 100%; padding: 9px 12px; border: 1.5px solid var(--border);
            border-radius: 9px; font-family: var(--ff-body); font-size: .87rem;
            color: var(--ink); background: var(--card); outline: none;
        }
        .stay-field input:focus, .stay-field select:focus { border-color: #7c3aed; box-shadow: 0 0 0 3px rgba(124,58,237,.1); }
        .stay-field select { appearance: none; background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%2364748b' d='M6 8L1 3h10z'/%3E%3C/svg%3E"); background-repeat: no-repeat; background-position: right 12px center; padding-right: 32px; cursor: pointer; }

        .stay-preview { background: linear-gradient(135deg, #faf5ff, #f5f3ff); border: 1px solid #ddd6fe; border-radius: 10px; padding: 12px 16px; margin-bottom: 14px; display: flex; flex-wrap: wrap; gap: 14px; align-items: center; }
        .sp-item { text-align: center; }
        .sp-lbl { font-size: .62rem; font-weight: 700; text-transform: uppercase; letter-spacing: .5px; color: #7c3aed; margin-bottom: 2px; }
        .sp-val { font-size: .95rem; font-weight: 800; color: #3b0764; }
        .sp-divider { width: 1px; height: 36px; background: #ddd6fe; flex-shrink: 0; }

        .stay-actions { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; }
        .btn-save-stay { display: inline-flex; align-items: center; gap: 7px; padding: 10px 22px; background: linear-gradient(135deg, #7c3aed, #6d28d9); color: #fff; border: none; border-radius: 10px; font-family: var(--ff-body); font-size: .88rem; font-weight: 700; cursor: pointer; box-shadow: 0 3px 12px rgba(124,58,237,.3); transition: all .15s; }
        .btn-save-stay:hover { background: linear-gradient(135deg, #6d28d9, #5b21b6); transform: translateY(-1px); }
        .stay-save-note { font-size: .75rem; color: var(--ink-light); font-style: italic; }
        .no-discharge-warn { font-size: .75rem; color: #b91c1c; display: flex; align-items: center; gap: 5px; font-weight: 600; }

        .page-head { display: flex; align-items: center; gap: 14px; margin-bottom: 20px; }
        .head-icon { width: 50px; height: 50px; background: linear-gradient(135deg, var(--navy), var(--accent)); border-radius: 13px; display: flex; align-items: center; justify-content: center; color: #fff; font-size: 1.3rem; box-shadow: 0 6px 18px rgba(11,29,58,.2); flex-shrink: 0; }
        .head-icon.ip { background: linear-gradient(135deg, #4c1d95, #7c3aed); }
        .page-head h2 { font-family: var(--ff-head); font-size: clamp(1.2rem,2.5vw,1.7rem); color: var(--navy); margin: 0; }
        .page-head p  { font-size: .82rem; color: var(--ink-light); margin-top: 3px; }

        .pat-card { background: var(--card); border: 1px solid var(--border); border-radius: var(--radius); box-shadow: var(--shadow); overflow: hidden; margin-bottom: 16px; }
        .pat-head { padding: 16px 22px; display: flex; align-items: center; gap: 14px; }
        .pat-head.op { background: linear-gradient(135deg, var(--navy), #1e3a6e); }
        .pat-head.ip { background: linear-gradient(135deg, #3b0764, #7c3aed); }
        .pat-av { width: 54px; height: 54px; border-radius: 50%; background: rgba(255,255,255,.18); border: 2.5px solid rgba(255,255,255,.3); display: flex; align-items: center; justify-content: center; font-family: var(--ff-head); font-size: 1.35rem; color: #fff; flex-shrink: 0; }
        .pat-fullname { font-family: var(--ff-head); font-size: 1.1rem; color: #fff; }
        .pat-pid { font-size: .74rem; color: rgba(255,255,255,.6); margin-top: 3px; }
        .pat-chips { margin-left: auto; display: flex; gap: 6px; flex-wrap: wrap; }
        .chip { display: inline-flex; align-items: center; gap: 4px; border-radius: 999px; padding: 4px 12px; font-size: .71rem; font-weight: 700; white-space: nowrap; }
        .chip-sc  { background: #dbeafe; color: #1d4ed8; }
        .chip-pwd { background: #d1fae5; color: #065f46; }
        .chip-g   { background: rgba(255,255,255,.15); color: #fff; }
        .chip-ip  { background: #c4b5fd; color: #4c1d95; }

        .pat-grid { display: grid; grid-template-columns: repeat(auto-fit,minmax(160px,1fr)); }
        .pat-gi { padding: 12px 22px; border-right: 1px solid var(--border); border-bottom: 1px solid var(--border); }
        .pat-gi:last-child { border-right: none; }
        .gi-lbl { font-size: .67rem; font-weight: 700; text-transform: uppercase; letter-spacing: .5px; color: var(--ink-light); display: flex; align-items: center; gap: 4px; margin-bottom: 3px; }
        .gi-val { font-size: .9rem; font-weight: 600; color: var(--navy); }

        .alert-ok   { background: #f0fdf4; border: 1.5px solid #86efac; border-radius: 10px; padding: 12px 18px; display: flex; align-items: center; gap: 10px; font-weight: 600; color: var(--success); margin-bottom: 16px; }
        .alert-zero { background: #fff7ed; border: 1.5px solid #fed7aa; border-radius: 10px; padding: 12px 18px; display: flex; align-items: center; gap: 10px; font-weight: 600; color: #c2410c; margin-bottom: 16px; }
        .alert-saved { background: #f0f9ff; border: 1.5px solid #7dd3fc; border-radius: 10px; padding: 12px 18px; display: flex; align-items: center; gap: 10px; font-weight: 600; color: #0369a1; margin-bottom: 16px; }

        .bill-banner { display: flex; align-items: center; gap: 10px; background: #fffbeb; border: 1.5px solid #fde68a; border-radius: 10px; padding: 12px 18px; margin-bottom: 16px; font-size: .87rem; font-weight: 600; color: #92400e; flex-wrap: wrap; }
        .bb-acts { margin-left: auto; display: flex; gap: 7px; }
        .bb-acts a { display: inline-flex; align-items: center; gap: 4px; background: #fff; color: #92400e; border: 1.5px solid #fde68a; border-radius: 7px; padding: 4px 12px; font-size: .77rem; font-weight: 700; text-decoration: none; }
        .bb-acts a:hover { background: #fef3c7; }

        .disc-bar { display: flex; align-items: center; gap: 10px; padding: 11px 16px; background: #f0fdf4; border: 1.5px solid #bbf7d0; border-radius: 10px; margin-bottom: 16px; font-size: .87rem; color: #065f46; font-weight: 600; }
        .disc-bar.sc { background: #eff6ff; border-color: #bfdbfe; color: #1d4ed8; }
        .disc-bar input { width: 17px; height: 17px; accent-color: var(--success); cursor: pointer; flex-shrink: 0; }
        .d-badge { margin-left: auto; border-radius: 999px; padding: 3px 11px; font-size: .71rem; font-weight: 700; color: #fff; background: #059669; }

        .billing-layout { display: grid; grid-template-columns: 400px 1fr; gap: 22px; align-items: start; }

        .bcard { background: var(--card); border: 1px solid var(--border); border-radius: var(--radius); box-shadow: var(--shadow); overflow: hidden; }
        .bcard-head { padding: 11px 20px; display: flex; align-items: center; gap: 8px; font-size: .7rem; font-weight: 700; text-transform: uppercase; letter-spacing: .7px; color: rgba(255,255,255,.88); }
        .h-bill { background: linear-gradient(90deg, var(--navy), #1e40af); }

        .add-card { background: var(--card); border: 1px solid var(--border); border-radius: var(--radius); box-shadow: var(--shadow); overflow: hidden; margin-bottom: 14px; }
        .add-card:last-of-type { margin-bottom: 0; }
        .add-card-header { display: flex; align-items: center; gap: 12px; padding: 13px 18px; border-bottom: 1px solid var(--border); }
        .add-card-icon { width: 36px; height: 36px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: .95rem; flex-shrink: 0; }
        .add-card-lab .add-card-icon { background: #d1fae5; color: #047857; }
        .add-card-svc .add-card-icon { background: #ede9fe; color: #6d28d9; }
        .add-card-med .add-card-icon { background: #dbeafe; color: #1d4ed8; }
        .add-card-inp .add-card-icon { background: #f5f3ff; color: #7c3aed; }
        .add-card-lab .add-card-header { background: #f0fdf8; }
        .add-card-svc .add-card-header { background: #faf5ff; }
        .add-card-med .add-card-header { background: #eff6ff; }
        .add-card-inp .add-card-header { background: #f5f3ff; }
        .add-card-title { font-weight: 700; font-size: .88rem; color: var(--navy); line-height: 1.2; }
        .add-card-sub   { font-size: .69rem; color: var(--ink-light); margin-top: 1px; }
        .add-card-body  { padding: 14px 18px; }

        .af-field { margin-bottom: 10px; }
        .af-label { font-size: .67rem; font-weight: 700; text-transform: uppercase; letter-spacing: .55px; color: var(--ink-light); display: block; margin-bottom: 5px; }
        .af-select { width: 100%; padding: 9px 12px; border: 1.5px solid var(--border); border-radius: 9px; font-family: var(--ff-body); font-size: .85rem; color: var(--ink); background: var(--card); outline: none; appearance: none; background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%2364748b' d='M6 8L1 3h10z'/%3E%3C/svg%3E"); background-repeat: no-repeat; background-position: right 12px center; padding-right: 32px; cursor: pointer; }
        .af-select:focus { border-color: var(--accent); box-shadow: 0 0 0 3px rgba(37,99,235,.1); }
        .add-card-lab .af-select:focus { border-color: #059669; box-shadow: 0 0 0 3px rgba(5,150,105,.1); }
        .add-card-svc .af-select:focus { border-color: #7c3aed; box-shadow: 0 0 0 3px rgba(124,58,237,.1); }
        .add-card-inp .af-select:focus { border-color: #7c3aed; box-shadow: 0 0 0 3px rgba(124,58,237,.1); }

        .af-btn { display: flex; width: 100%; justify-content: center; align-items: center; gap: 6px; padding: 10px 16px; border: none; border-radius: 9px; font-family: var(--ff-body); font-size: .87rem; font-weight: 700; cursor: pointer; transition: all .15s; }
        .af-btn-lab { background: #059669; color: #fff; box-shadow: 0 3px 10px rgba(5,150,105,.25); }
        .af-btn-lab:hover { background: #047857; transform: translateY(-1px); }
        .af-btn-svc { background: #7c3aed; color: #fff; box-shadow: 0 3px 10px rgba(124,58,237,.25); }
        .af-btn-svc:hover { background: #6d28d9; transform: translateY(-1px); }
        .af-btn-med { background: var(--accent); color: #fff; box-shadow: 0 3px 10px rgba(37,99,235,.25); }
        .af-btn-med:hover { background: #1d4ed8; transform: translateY(-1px); }
        .af-btn-inp { background: #7c3aed; color: #fff; box-shadow: 0 3px 10px rgba(124,58,237,.25); }
        .af-btn-inp:hover { background: #6d28d9; transform: translateY(-1px); }

        /* ── ROOM BILLING ROW ── */
        .sec-row-room td { padding: 7px 14px; font-size: .65rem; font-weight: 700; text-transform: uppercase; letter-spacing: .8px; background: #faf5ff; color: #5b21b6; border-top: 2px solid var(--border); border-bottom: 1px solid var(--border); }
        .r-room { border-left: 3px solid #a78bfa; }

        .bill-footer { border-top: 2px solid var(--border); background: #fafbfc; }
        .bf-totals-block { padding: 16px 24px; border-bottom: 1px solid var(--border); display: flex; flex-direction: column; gap: 6px; }
        .bf-line { display: flex; justify-content: space-between; align-items: center; }
        .bf-lbl { font-size: .88rem; color: var(--ink-light); font-weight: 500; }
        .bf-amount { font-size: .95rem; font-weight: 700; color: var(--ink); }
        .bf-disc-line .bf-lbl { color: var(--danger); font-size: .84rem; }
        .bf-disc-line .bf-amount { color: var(--danger); font-weight: 700; }
        .bf-divider { border: none; border-top: 1.5px dashed var(--border); margin: 4px 0; }
        .bf-grand-lbl { font-size: 1.05rem; font-weight: 800; color: var(--navy); }
        .bf-grand-amt { font-size: 1.25rem; font-weight: 800; color: var(--success); }
        .bf-grand-amt.zero { color: var(--danger); }

        .bf-actions { display: grid; grid-template-columns: 1fr auto 1fr; align-items: center; padding: 14px 20px; gap: 12px; }
        .bf-act-left  { display: flex; justify-content: flex-start; }
        .bf-act-center { display: flex; justify-content: center; flex-direction: column; align-items: center; gap: 6px; }
        .bf-act-right { display: flex; justify-content: flex-end; gap: 8px; }
        .bf-empty-note { color: var(--ink-light); font-size: .82rem; font-style: italic; }
        .no-discharge-fin { font-size: .72rem; color: #b91c1c; text-align: center; display: flex; align-items: center; gap: 4px; }

        .bf-btn-finalize { display: inline-flex; align-items: center; gap: 8px; padding: 11px 28px; background: var(--success); color: #fff; border: none; border-radius: 10px; font-family: var(--ff-head); font-size: .95rem; font-weight: 700; cursor: pointer; box-shadow: 0 4px 16px rgba(5,150,105,.3); transition: all .15s; white-space: nowrap; }
        .bf-btn-finalize:hover { background: #047857; transform: translateY(-1px); }
        .bf-btn-finalize-disabled { display: inline-flex; align-items: center; gap: 8px; padding: 11px 28px; background: #e2e8f0; color: #94a3b8; border: none; border-radius: 10px; font-family: var(--ff-head); font-size: .95rem; font-weight: 700; cursor: not-allowed; white-space: nowrap; }
        .bf-btn-back { display: inline-flex; align-items: center; gap: 5px; padding: 8px 16px; background: #fff; color: var(--ink-light); border: 1.5px solid var(--border); border-radius: 8px; font-family: var(--ff-body); font-size: .84rem; font-weight: 600; text-decoration: none; transition: all .15s; }
        .bf-btn-back:hover { border-color: var(--accent); color: var(--accent); background: #eff6ff; }
        .bf-btn-reload { display: inline-flex; align-items: center; gap: 5px; padding: 8px 14px; background: #fff; color: var(--ink-light); border: 1.5px solid var(--border); border-radius: 8px; font-family: var(--ff-body); font-size: .84rem; font-weight: 600; text-decoration: none; transition: all .15s; }
        .bf-btn-reload:hover { border-color: var(--danger); color: var(--danger); }
        .bf-btn-view { display: inline-flex; align-items: center; gap: 5px; padding: 8px 14px; background: #fffbeb; color: #92400e; border: 1.5px solid #fde68a; border-radius: 8px; font-family: var(--ff-body); font-size: .84rem; font-weight: 600; text-decoration: none; }
        .bf-btn-view:hover { background: #fef3c7; }

        .af-qty-row { display: flex; align-items: flex-end; gap: 10px; }
        .qty-stepper { display: flex; align-items: center; border: 1.5px solid var(--border); border-radius: 9px; overflow: hidden; height: 38px; }
        .qty-btn { width: 36px; height: 100%; background: #f8fafc; border: none; color: var(--ink); font-size: 1.1rem; font-weight: 700; cursor: pointer; flex-shrink: 0; transition: background .12s; }
        .qty-btn:hover { background: #e2e8f0; }
        .qty-input { width: 54px; height: 100%; border: none; border-left: 1.5px solid var(--border); border-right: 1.5px solid var(--border); text-align: center; font-family: var(--ff-body); font-size: .9rem; font-weight: 700; color: var(--navy); outline: none; }
        .qty-input::-webkit-inner-spin-button, .qty-input::-webkit-outer-spin-button { -webkit-appearance: none; }

        .af-empty { color: var(--ink-light); font-size: .84rem; font-style: italic; text-align: center; padding: 10px 0; }
        .price-zero { color: var(--danger) !important; }
        .zero-badge { display: inline-block; margin-left: 4px; background: #fee2e2; color: #b91c1c; border-radius: 999px; padding: 1px 7px; font-size: .6rem; font-weight: 700; vertical-align: middle; }

        .itbl { width: 100%; border-collapse: collapse; font-size: .85rem; }
        .itbl thead th { background: #f8fafc; color: var(--ink-light); font-size: .65rem; font-weight: 700; text-transform: uppercase; letter-spacing: .6px; padding: 9px 14px; border-bottom: 2px solid var(--border); text-align: left; white-space: nowrap; }
        .itbl thead th.r { text-align: right; }
        .itbl thead th.c { text-align: center; }
        .itbl tbody tr { border-bottom: 1px solid var(--border); transition: background .12s; }
        .itbl tbody tr:last-child { border-bottom: none; }
        .itbl tbody tr:hover:not(.sec-row):not(.sec-row-room) { background: #f7faff; }
        .itbl td { padding: 10px 14px; vertical-align: middle; }
        .itbl td.r { text-align: right; font-weight: 600; color: var(--success); }
        .itbl td.c { text-align: center; }

        .sec-row td { padding: 7px 14px; font-size: .65rem; font-weight: 700; text-transform: uppercase; letter-spacing: .8px; border-top: 2px solid var(--border); border-bottom: 1px solid var(--border); }
        .sec-row.s-svc td { color: var(--c-svc); background: #fdf4ff; }
        .sec-row.s-lab td { color: var(--c-lab); background: #f0fdf9; }
        .sec-row.s-med td { color: var(--c-rx);  background: #eff6ff; }
        .sec-total { float: right; font-weight: 800; }

        .r-lab  { border-left: 3px solid #6ee7b7; }
        .r-dnm  { border-left: 3px solid #c4b5fd; }
        .r-xsvc { border-left: 3px solid #fb923c; }
        .r-rx   { border-left: 3px solid #93c5fd; }
        .r-xmed { border-left: 3px solid #fda4af; }
        .r-xlab { border-left: 3px solid #34d399; }
        .r-xinp { border-left: 3px solid #a78bfa; }
        .r-room { border-left: 3px solid #a78bfa; }

        .item-name { font-weight: 600; color: var(--navy); font-size: .87rem; }
        .item-desc { font-size: .72rem; color: var(--ink-light); margin-top: 2px; }

        .tag { display: inline-flex; align-items: center; gap: 3px; padding: 2px 7px; border-radius: 999px; font-size: .6rem; font-weight: 700; margin-right: 3px; white-space: nowrap; }
        .tag-lab  { background: var(--bg-lab); color: var(--c-lab); }
        .tag-dnm  { background: var(--bg-svc); color: var(--c-svc); }
        .tag-xsvc { background: #ffedd5; color: #c2410c; }
        .tag-rx   { background: var(--bg-rx);  color: var(--c-rx);  }
        .tag-xmed { background: #fce7f3; color: #9d174d; }
        .tag-xlab { background: #d1fae5; color: #065f46; }
        .tag-xinp { background: #ede9fe; color: #5b21b6; }
        .tag-room { background: #ede9fe; color: #5b21b6; }

        .btn-del { background: #fff1f2; color: var(--danger); border: 1.5px solid #fecdd3; border-radius: 7px; padding: 3px 9px; font-size: .72rem; font-weight: 700; font-family: var(--ff-body); cursor: pointer; display: inline-flex; align-items: center; gap: 3px; text-decoration: none; transition: all .15s; }
        .btn-del:hover { background: var(--danger); color: #fff; border-color: var(--danger); }
        .lock-i { color: var(--ink-light); font-size: .82rem; }

        .empty-row td { text-align: center; padding: 28px; color: var(--ink-light); font-style: italic; font-size: .84rem; }
        .item-count { background: rgba(255,255,255,.2); border-radius: 999px; padding: 2px 9px; font-size: .68rem; margin-left: auto; }

        @media(max-width:1100px) { .billing-layout { grid-template-columns: 360px 1fr; } }
        @media(max-width:900px)  { .billing-layout { grid-template-columns: 1fr; } .af-qty-row { flex-direction: column; } .af-btn { width: 100%; } }
        @media(max-width:768px)  { .cw { margin-left: 0; padding: 52px 14px 60px; } .pat-chips { display: none; } }
        @media(max-width:480px)  { .cw { padding: 48px 10px 60px; } .pat-grid { grid-template-columns: 1fr 1fr; } .stay-grid { grid-template-columns: 1fr; } }
        @media(max-width:640px)  { .bf-actions { grid-template-columns: 1fr 1fr; } .bf-act-center { grid-column: 1/-1; order: -1; } .bf-btn-finalize, .bf-btn-finalize-disabled { width: 100%; justify-content: center; } }
    </style>
</head>
<body>
<?php include 'billing_sidebar.php'; ?>
<div class="cw" id="mainCw">

    <?php if (isset($_GET['success'])): ?>
        <div class="alert-ok"><i class="bi bi-check-circle-fill" style="font-size:1.3rem;"></i> Billing finalized successfully!</div>
    <?php endif; ?>
    <?php if (isset($_GET['saved'])): ?>
        <div class="alert-saved"><i class="bi bi-floppy-fill" style="font-size:1.3rem;"></i> Stay information saved. Items will be accumulated until discharge.</div>
    <?php endif; ?>
    <?php if (isset($_GET['err']) && $_GET['err'] === 'zero_total'): ?>
        <div class="alert-zero"><i class="bi bi-exclamation-triangle-fill" style="font-size:1.3rem;"></i> Cannot finalize — the grand total is ₱0.00. Please ensure at least one item has a price.</div>
    <?php endif; ?>

    <div class="page-head">
        <div class="head-icon <?= $is_inpatient ? 'ip' : '' ?>">
            <i class="bi bi-<?= $is_inpatient ? 'hospital-fill' : 'receipt-cutoff' ?>"></i>
        </div>
        <div>
            <h2>Patient Bill</h2>
            <p><?= $is_inpatient ? 'Inpatient Admission — ' . htmlspecialchars($patient['admission_type']) : 'Lab Results, Procedures &amp; Medicines' ?></p>
        </div>
    </div>

    <?php if ($is_inpatient): ?>
    <div class="inpat-banner">
        <i class="bi bi-hospital-fill"></i>
        <span>This is an <strong>inpatient</strong> admission.</span>
        <span class="inpat-type-pill"><i class="bi bi-activity"></i> <?= htmlspecialchars($patient['admission_type']) ?></span>
        <span style="margin-left:auto;font-size:.78rem;font-weight:500;color:#6d28d9;">Set room type &amp; dates below, then <strong>Save</strong> to keep items accumulating until discharge.</span>
    </div>

    <!-- ════════════════════════════════
         STAY INFO CARD (Inpatients only)
    ════════════════════════════════ -->
    <div class="stay-card">
        <div class="stay-card-head">
            <i class="bi bi-door-open-fill" style="color:rgba(255,255,255,.7);font-size:1.1rem;"></i>
            <h5>Room &amp; Stay Information</h5>
            <?php if (!empty($stay['discharge_date'])): ?>
                <span class="sc-badge saved"><i class="bi bi-check-circle-fill"></i> Discharged — Ready to Finalize</span>
            <?php elseif ($stay['room_type_id'] > 0): ?>
                <span class="sc-badge"><i class="bi bi-clock"></i> Currently Admitted</span>
            <?php else: ?>
                <span class="sc-badge"><i class="bi bi-exclamation-circle"></i> Room not set</span>
            <?php endif; ?>
        </div>
        <div class="stay-form">
            <form method="POST" id="stayForm">
                <div class="stay-grid">
                    <!-- Room Type -->
                    <div class="stay-field">
                        <label><i class="bi bi-building"></i> Room Type</label>
                        <select name="room_type_id" id="roomTypeSelect" onchange="updateRoomPreview()" required>
                            <option value="">— Select Room Type —</option>
                            <?php foreach ($room_types as $rt): ?>
                                <option value="<?= $rt['id'] ?>"
                                    data-price-day="<?= $rt['price_per_day'] ?>"
                                    data-price-hour="<?= $rt['price_per_hour'] ?>"
                                    data-name="<?= htmlspecialchars($rt['name']) ?>"
                                    data-cap="<?= htmlspecialchars($rt['capacity']) ?>"
                                    <?= $stay['room_type_id'] == $rt['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($rt['name']) ?> — ₱<?= number_format($rt['price_per_day'], 2) ?>/day (<?= htmlspecialchars($rt['capacity']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <!-- Room No. -->
                    <div class="stay-field">
                        <label><i class="bi bi-hash"></i> Room / Bed No.</label>
                        <input type="text" name="room_no" placeholder="e.g. 204-B" value="<?= htmlspecialchars($stay['room_no'] ?? '') ?>">
                    </div>
                    <!-- Admission Date -->
                    <div class="stay-field">
                        <label><i class="bi bi-calendar-check"></i> Admission Date &amp; Time</label>
                        <input type="datetime-local" name="admission_date" id="admissionDate"
                               value="<?= htmlspecialchars($stay['admission_date'] ?? date('Y-m-d\TH:i')) ?>"
                               onchange="updateRoomPreview()" required>
                    </div>
                    <!-- Discharge Date -->
                    <div class="stay-field">
                        <label><i class="bi bi-calendar-x"></i> Discharge Date &amp; Time <span style="font-size:.6rem;color:#b91c1c;font-weight:700;">(fill to finalize)</span></label>
                        <input type="datetime-local" name="discharge_date" id="dischargeDate"
                               value="<?= htmlspecialchars($stay['discharge_date'] ?? '') ?>"
                               onchange="updateRoomPreview()">
                    </div>
                </div>

                <!-- Live Preview -->
                <div class="stay-preview" id="stayPreview" style="<?= $stay['room_type_id'] > 0 ? '' : 'display:none;' ?>">
                    <div class="sp-item">
                        <div class="sp-lbl">Room Type</div>
                        <div class="sp-val" id="prevRoomName"><?= $selected_room ? htmlspecialchars($selected_room['name']) : '—' ?></div>
                    </div>
                    <div class="sp-divider"></div>
                    <div class="sp-item">
                        <div class="sp-lbl">Duration</div>
                        <div class="sp-val" id="prevHours"><?= $live_hours > 0 ? number_format($live_hours, 1) . ' hrs' : '—' ?></div>
                    </div>
                    <div class="sp-divider"></div>
                    <div class="sp-item">
                        <div class="sp-lbl">Days Billed</div>
                        <div class="sp-val" id="prevDays"><?= $live_days > 0 ? $live_days . ' day' . ($live_days > 1 ? 's' : '') : '—' ?></div>
                    </div>
                    <div class="sp-divider"></div>
                    <div class="sp-item">
                        <div class="sp-lbl">Room Charge</div>
                        <div class="sp-val" id="prevTotal" style="color:#7c3aed;">₱<?= number_format($stay['room_total'] ?? 0, 2) ?></div>
                    </div>
                </div>

                <div class="stay-actions">
                    <button type="submit" name="save_stay" class="btn-save-stay">
                        <i class="bi bi-floppy-fill"></i> Save Stay Info
                    </button>
                    <span class="stay-save-note">
                        <i class="bi bi-info-circle"></i>
                        <?php if (empty($stay['discharge_date'])): ?>
                            Patient is still admitted — save items daily until discharge.
                        <?php else: ?>
                            Discharge date set. You can now <strong>Finalize Billing</strong>.
                        <?php endif; ?>
                    </span>
                    <?php if (empty($stay['discharge_date']) && !empty($stay['admission_date'])): ?>
                        <span class="no-discharge-warn"><i class="bi bi-exclamation-triangle-fill"></i> Set discharge date to enable finalization.</span>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <!-- Patient Info -->
    <div class="pat-card">
        <div class="pat-head <?= $is_inpatient ? 'ip' : 'op' ?>">
            <div class="pat-av"><?= strtoupper(substr(trim($patient['fname']), 0, 1)) ?></div>
            <div>
                <div class="pat-fullname"><?= htmlspecialchars($full_name) ?></div>
                <div class="pat-pid">Patient ID #<?= htmlspecialchars($patient_no) ?></div>
            </div>
            <div class="pat-chips">
                <?php if ($is_inpatient): ?><span class="chip chip-ip"><i class="bi bi-hospital"></i> Inpatient</span><?php endif; ?>
                <?php if ($is_senior):    ?><span class="chip chip-sc"><i class="bi bi-person-check-fill"></i> Senior</span><?php endif; ?>
                <?php if ($is_pwd):       ?><span class="chip chip-pwd"><i class="bi bi-accessibility"></i> PWD</span><?php endif; ?>
                <span class="chip chip-g"><i class="bi bi-<?= strtolower($gender) === 'female' ? 'gender-female' : 'gender-male' ?>"></i> <?= htmlspecialchars($gender) ?></span>
            </div>
        </div>
        <div class="pat-grid">
            <div class="pat-gi"><div class="gi-lbl"><i class="bi bi-cake2"></i> Date of Birth</div><div class="gi-val"><?= htmlspecialchars($dob_display) ?></div></div>
            <div class="pat-gi"><div class="gi-lbl"><i class="bi bi-hourglass-split"></i> Age</div><div class="gi-val"><?= $age > 0 ? $age . ' yrs old' : '—' ?></div></div>
            <div class="pat-gi"><div class="gi-lbl"><i class="bi bi-gender-ambiguous"></i> Gender</div><div class="gi-val"><?= htmlspecialchars($gender) ?></div></div>
            <div class="pat-gi"><div class="gi-lbl"><i class="bi bi-telephone"></i> Contact</div><div class="gi-val"><?= htmlspecialchars($contact) ?></div></div>
            <div class="pat-gi"><div class="gi-lbl"><i class="bi bi-geo-alt"></i> Address</div><div class="gi-val"><?= htmlspecialchars($address) ?></div></div>
            <?php if ($is_inpatient): ?>
            <div class="pat-gi"><div class="gi-lbl"><i class="bi bi-activity"></i> Admission Type</div><div class="gi-val"><?= htmlspecialchars($patient['admission_type']) ?></div></div>
            <?php if ($selected_room): ?><div class="pat-gi"><div class="gi-lbl"><i class="bi bi-building"></i> Room Type</div><div class="gi-val"><?= htmlspecialchars($selected_room['name']) ?></div></div><?php endif; ?>
            <?php if (!empty($stay['room_no'])): ?><div class="pat-gi"><div class="gi-lbl"><i class="bi bi-hash"></i> Room No.</div><div class="gi-val"><?= htmlspecialchars($stay['room_no']) ?></div></div><?php endif; ?>
            <?php if (!empty($stay['admission_date'])): ?><div class="pat-gi"><div class="gi-lbl"><i class="bi bi-calendar-check"></i> Admitted</div><div class="gi-val"><?= date('M d, Y H:i', strtotime($stay['admission_date'])) ?></div></div><?php endif; ?>
            <?php if (!empty($stay['discharge_date'])): ?><div class="pat-gi"><div class="gi-lbl"><i class="bi bi-calendar-x"></i> Discharged</div><div class="gi-val"><?= date('M d, Y H:i', strtotime($stay['discharge_date'])) ?></div></div><?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($existing_bill): ?>
    <div class="bill-banner">
        <i class="bi bi-exclamation-triangle-fill"></i>
        Existing <strong><?= $existing_bill['status'] ?></strong> bill (ID #<?= $existing_bill['billing_id'] ?> — ₱<?= number_format($existing_bill['grand_total'], 2) ?>). Finalizing will <strong>update</strong> it.
        <div class="bb-acts">
            <a href="patient_billing.php?patient_id=<?= $patient_id ?>"><i class="bi bi-eye"></i> View</a>
            <a href="billing_items.php?patient_id=<?= $patient_id ?>&reset_cart=1"><i class="bi bi-x-circle"></i> Clear Extras</a>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($is_senior): ?>
        <div class="disc-bar sc"><i class="bi bi-person-check-fill" style="font-size:1.1rem;"></i> Senior Citizen (60+) — 20% discount applied automatically<span class="d-badge" style="background:#1d4ed8;">SC Discount</span></div>
    <?php else: ?>
        <div class="disc-bar">
            <input type="checkbox" id="pwdChk" <?= $is_pwd ? 'checked' : '' ?> onchange="window.location='billing_items.php?patient_id=<?= $patient_id ?>&toggle_pwd='+(this.checked?1:0)">
            <label for="pwdChk" style="cursor:pointer;">Mark as PWD/Senior (applies 20% discount)</label>
            <?php if ($is_pwd): ?><span class="d-badge">PWD/SC Active</span><?php endif; ?>
        </div>
    <?php endif; ?>

    <div class="billing-layout">

        <!-- LEFT: add forms -->
        <div class="billing-left">

            <?php if ($is_inpatient && !empty($inpat_services_by_cat)): ?>
            <div class="add-card add-card-inp">
                <div class="add-card-header">
                    <div class="add-card-icon"><i class="bi bi-hospital-fill"></i></div>
                    <div>
                        <div class="add-card-title">Add Inpatient Service</div>
                        <div class="add-card-sub">Surgery, procedure, supply charges</div>
                    </div>
                </div>
                <div class="add-card-body">
                    <form method="POST">
                        <div class="af-field">
                            <label class="af-label">Select Service / Charge</label>
                            <select name="inpat_service_id" class="af-select" required>
                                <option value="">— Choose a service —</option>
                                <?php foreach ($inpat_services_by_cat as $cat => $svcs): ?>
                                    <optgroup label="— <?= htmlspecialchars($cat) ?> —">
                                        <?php foreach ($svcs as $is): ?>
                                            <option value="<?= $is['service_id'] ?>"><?= htmlspecialchars($is['name']) ?> — ₱<?= number_format($is['base_price'], 2) ?>/<?= htmlspecialchars($is['unit']) ?></option>
                                        <?php endforeach; ?>
                                    </optgroup>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="af-qty-row">
                            <div class="af-field" style="flex:1;">
                                <label class="af-label">Quantity / Units</label>
                                <div class="qty-stepper">
                                    <button type="button" class="qty-btn" onclick="stepQty(this,-1)">−</button>
                                    <input type="number" name="inpat_qty" value="1" min="1" max="9999" class="qty-input">
                                    <button type="button" class="qty-btn" onclick="stepQty(this,1)">+</button>
                                </div>
                            </div>
                            <button type="submit" name="add_inpat_service" class="af-btn af-btn-inp" style="align-self:flex-end;"><i class="bi bi-plus-circle-fill"></i> Add</button>
                        </div>
                    </form>
                </div>
            </div>
            <?php endif; ?>

            <div class="add-card add-card-lab">
                <div class="add-card-header">
                    <div class="add-card-icon"><i class="bi bi-eyedropper-fill"></i></div>
                    <div><div class="add-card-title">Add Laboratory Service</div><div class="add-card-sub">From dl_services catalog</div></div>
                </div>
                <div class="add-card-body">
                    <?php if ($lab_services): ?>
                    <form method="POST">
                        <div class="af-field">
                            <label class="af-label">Select Lab Service</label>
                            <select name="lab_service_id" class="af-select" required>
                                <option value="">— Choose a service —</option>
                                <?php foreach ($lab_services as $ls): ?><option value="<?= $ls['serviceID'] ?>"><?= htmlspecialchars($ls['serviceName']) ?> — ₱<?= number_format($ls['price'], 2) ?></option><?php endforeach; ?>
                            </select>
                        </div>
                        <button type="submit" name="add_lab" class="af-btn af-btn-lab"><i class="bi bi-plus-circle-fill"></i> Add to Bill</button>
                    </form>
                    <?php else: ?><div class="af-empty"><i class="bi bi-info-circle"></i> No lab services found.</div><?php endif; ?>
                </div>
            </div>

            <div class="add-card add-card-svc">
                <div class="add-card-header">
                    <div class="add-card-icon"><i class="bi bi-clipboard2-plus-fill"></i></div>
                    <div><div class="add-card-title">Add Procedure / Service</div><div class="add-card-sub">From active procedure list</div></div>
                </div>
                <div class="add-card-body">
                    <?php if ($procedures): ?>
                    <form method="POST">
                        <div class="af-field">
                            <label class="af-label">Select Procedure</label>
                            <select name="procedure_id" class="af-select" required>
                                <option value="">— Choose a procedure —</option>
                                <?php foreach ($procedures as $p): ?><option value="<?= $p['procedure_id'] ?>"><?= htmlspecialchars($p['procedure_name']) ?> — ₱<?= number_format($p['price'], 2) ?></option><?php endforeach; ?>
                            </select>
                        </div>
                        <button type="submit" name="add_service" class="af-btn af-btn-svc"><i class="bi bi-plus-circle-fill"></i> Add to Bill</button>
                    </form>
                    <?php else: ?><div class="af-empty"><i class="bi bi-info-circle"></i> No active procedures found.</div><?php endif; ?>
                </div>
            </div>

            <div class="add-card add-card-med">
                <div class="add-card-header">
                    <div class="add-card-icon"><i class="bi bi-capsule-fill"></i></div>
                    <div><div class="add-card-title">Add Medicine</div><div class="add-card-sub">From pharmacy inventory</div></div>
                </div>
                <div class="add-card-body">
                    <?php if ($medicines): ?>
                    <form method="POST">
                        <div class="af-field">
                            <label class="af-label">Select Medicine</label>
                            <select name="med_id" class="af-select" required>
                                <option value="">— Choose a medicine —</option>
                                <?php foreach ($medicines as $m): ?><option value="<?= $m['med_id'] ?>"><?= htmlspecialchars($m['med_name']) ?><?= !empty($m['dosage']) ? ' (' . $m['dosage'] . ')' : '' ?> — ₱<?= number_format($m['unit_price'], 2) ?>/unit</option><?php endforeach; ?>
                            </select>
                        </div>
                        <div class="af-qty-row">
                            <div class="af-field" style="flex:1;">
                                <label class="af-label">Quantity</label>
                                <div class="qty-stepper">
                                    <button type="button" class="qty-btn" onclick="stepQty(this,-1)">−</button>
                                    <input type="number" name="qty" value="1" min="1" max="999" class="qty-input">
                                    <button type="button" class="qty-btn" onclick="stepQty(this,1)">+</button>
                                </div>
                            </div>
                            <button type="submit" name="add_medicine" class="af-btn af-btn-med" style="align-self:flex-end;"><i class="bi bi-plus-circle-fill"></i> Add</button>
                        </div>
                    </form>
                    <?php else: ?><div class="af-empty"><i class="bi bi-info-circle"></i> No medicines available.</div><?php endif; ?>
                </div>
            </div>

        </div><!-- /.billing-left -->

        <!-- RIGHT: bill table -->
        <div class="billing-right">
            <div class="bcard">
                <div class="bcard-head h-bill">
                    <i class="bi bi-clipboard2-check-fill"></i> Patient Bill
                    <span class="item-count"><?= count($cart) + ($room_total > 0 ? 1 : 0) ?> item<?= (count($cart) + ($room_total > 0 ? 1 : 0)) !== 1 ? 's' : '' ?></span>
                </div>
                <div style="overflow-x:auto;">
                    <table class="itbl">
                        <thead>
                            <tr>
                                <th>Item / Service</th>
                                <th>Details</th>
                                <th class="r">Amount</th>
                                <th class="c">Action</th>
                            </tr>
                        </thead>
                        <tbody>

                            <?php if (empty($cart) && $room_total <= 0): ?>
                            <tr><td colspan="4" class="empty-row"><i class="bi bi-inbox" style="font-size:1.4rem;display:block;margin-bottom:6px;opacity:.3;"></i>No billing items found. Add items using the panels on the left.</td></tr>
                            <?php endif; ?>

                            <!-- ROOM CHARGE (inpatient only) -->
                            <?php if ($is_inpatient && $room_total > 0 && $selected_room): ?>
                            <tr class="sec-row-room">
                                <td colspan="4"><i class="bi bi-building"></i> Accommodation / Room Charges <span class="sec-total">₱<?= number_format($room_total, 2) ?></span></td>
                            </tr>
                            <tr class="r-room">
                                <td>
                                    <span class="tag tag-room"><i class="bi bi-building"></i> Room</span>
                                    <div class="item-name"><?= htmlspecialchars($selected_room['name']) ?><?= !empty($stay['room_no']) ? ' — Rm ' . htmlspecialchars($stay['room_no']) : '' ?></div>
                                </td>
                                <td>
                                    <div class="item-desc">
                                        <?= htmlspecialchars($selected_room['capacity']) ?> · ₱<?= number_format($selected_room['price_per_day'], 2) ?>/day
                                        <?php if ($live_days > 0): ?>
                                        · <?= $live_days ?> day<?= $live_days > 1 ? 's' : '' ?> (<?= number_format($live_hours, 1) ?> hrs)
                                        <?php endif; ?>
                                        <?php if (!empty($stay['admission_date'])): ?>
                                        <br><?= date('M d, Y H:i', strtotime($stay['admission_date'])) ?> →
                                        <?= !empty($stay['discharge_date']) ? date('M d, Y H:i', strtotime($stay['discharge_date'])) : '<strong style="color:#b91c1c;">Still admitted</strong>' ?>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="r">₱<?= number_format($room_total, 2) ?></td>
                                <td class="c"><i class="bi bi-lock-fill lock-i" title="Edit in Stay Info card above"></i></td>
                            </tr>
                            <?php elseif ($is_inpatient && empty($stay['room_type_id'])): ?>
                            <tr>
                                <td colspan="4" style="padding:12px 14px;background:#faf5ff;border-left:3px solid #a78bfa;">
                                    <div style="display:flex;align-items:center;gap:8px;font-size:.84rem;color:#6d28d9;font-weight:600;">
                                        <i class="bi bi-building" style="font-size:1rem;"></i>
                                        No room assigned yet — set Room Type in the <strong>Stay Information</strong> card above and click <strong>Save Stay Info</strong>.
                                    </div>
                                </td>
                            </tr>
                            <?php endif; ?>

                            <!-- SERVICES -->
                            <?php if (!empty($cat_services)): ?>
                            <tr class="sec-row s-svc">
                                <td colspan="4"><i class="bi bi-clipboard2-pulse-fill"></i> Doctor / Nurse Procedures &amp; Charges <span class="sec-total">₱<?= number_format($svc_total, 2) ?></span></td>
                            </tr>
                            <?php foreach ($cart as $idx => $item):
                                if ($item['category'] !== 'service') continue;
                                $is_x  = in_array($item['source'], ['add_svc','add_inpat']);
                                $is_ip = ($item['source'] === 'add_inpat');
                            ?>
                            <tr class="<?= $is_ip ? 'r-xinp' : ($is_x ? 'r-xsvc' : 'r-dnm') ?>">
                                <td>
                                    <span class="tag <?= $is_ip ? 'tag-xinp' : ($is_x ? 'tag-xsvc' : 'tag-dnm') ?>">
                                        <i class="bi <?= $is_ip ? 'bi-hospital' : ($is_x ? 'bi-plus-circle' : 'bi-clipboard2-pulse') ?>"></i>
                                        <?= $is_ip ? 'Inpatient' : ($is_x ? 'Added' : 'Procedure') ?>
                                    </span>
                                    <div class="item-name"><?= htmlspecialchars($item['serviceName']) ?></div>
                                </td>
                                <td><div class="item-desc"><?= htmlspecialchars($item['description']) ?></div></td>
                                <td class="r <?= $item['price'] == 0 ? 'price-zero' : '' ?>">₱<?= number_format($item['price'], 2) ?><?php if ($item['price'] == 0): ?><span class="zero-badge">No Price</span><?php endif; ?></td>
                                <td class="c">
                                    <?php if ($is_x): $ei = $idx - $auto_count; ?>
                                        <a href="billing_items.php?patient_id=<?= $patient_id ?>&delete=<?= $ei ?>" class="btn-del" onclick="return confirm('Remove this item?')"><i class="bi bi-trash3"></i> Remove</a>
                                    <?php else: ?><i class="bi bi-lock-fill lock-i" title="Auto-loaded"></i><?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; endif; ?>

                            <!-- LAB RESULTS -->
                            <?php if (!empty($cat_laboratory)): ?>
                            <tr class="sec-row s-lab">
                                <td colspan="4"><i class="bi bi-eyedropper-fill"></i> Laboratory Results <span class="sec-total">₱<?= number_format($lab_total, 2) ?></span></td>
                            </tr>
                            <?php foreach ($cart as $idx => $item):
                                if ($item['category'] !== 'laboratory') continue;
                                $is_x = ($item['source'] === 'add_lab');
                            ?>
                            <tr class="<?= $is_x ? 'r-xlab' : 'r-lab' ?>">
                                <td>
                                    <span class="tag <?= $is_x ? 'tag-xlab' : 'tag-lab' ?>"><i class="bi <?= $is_x ? 'bi-plus-circle' : 'bi-eyedropper' ?>"></i> <?= $is_x ? 'Added' : 'Lab' ?></span>
                                    <div class="item-name"><?= htmlspecialchars($item['serviceName']) ?></div>
                                </td>
                                <td><div class="item-desc"><?= htmlspecialchars($item['description']) ?></div></td>
                                <td class="r <?= $item['price'] == 0 ? 'price-zero' : '' ?>">₱<?= number_format($item['price'], 2) ?><?php if ($item['price'] == 0): ?><span class="zero-badge">No Price</span><?php endif; ?></td>
                                <td class="c">
                                    <?php if ($is_x): $ei = $idx - $auto_count; ?>
                                        <a href="billing_items.php?patient_id=<?= $patient_id ?>&delete=<?= $ei ?>" class="btn-del" onclick="return confirm('Remove this item?')"><i class="bi bi-trash3"></i> Remove</a>
                                    <?php else: ?><i class="bi bi-lock-fill lock-i" title="Auto-loaded from lab results"></i><?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; endif; ?>

                            <!-- MEDICINES -->
                            <?php if (!empty($cat_medicines)): ?>
                            <tr class="sec-row s-med">
                                <td colspan="4"><i class="bi bi-capsule-pill"></i> Medicines <span class="sec-total">₱<?= number_format($med_total, 2) ?></span></td>
                            </tr>
                            <?php foreach ($cart as $idx => $item):
                                if ($item['category'] !== 'medicine') continue;
                                $is_x = ($item['source'] === 'add_med');
                            ?>
                            <tr class="<?= $is_x ? 'r-xmed' : 'r-rx' ?>">
                                <td>
                                    <span class="tag <?= $is_x ? 'tag-xmed' : 'tag-rx' ?>"><i class="bi <?= $is_x ? 'bi-plus-circle' : 'bi-capsule' ?>"></i> <?= $is_x ? 'Added' : 'Dispensed' ?></span>
                                    <div class="item-name"><?= htmlspecialchars($item['serviceName']) ?></div>
                                </td>
                                <td><div class="item-desc"><?= htmlspecialchars($item['description']) ?></div></td>
                                <td class="r <?= $item['price'] == 0 ? 'price-zero' : '' ?>">₱<?= number_format($item['price'], 2) ?><?php if ($item['price'] == 0): ?><span class="zero-badge">No Price</span><?php endif; ?></td>
                                <td class="c">
                                    <?php if ($is_x): $ei = $idx - $auto_count; ?>
                                        <a href="billing_items.php?patient_id=<?= $patient_id ?>&delete=<?= $ei ?>" class="btn-del" onclick="return confirm('Remove this item?')"><i class="bi bi-trash3"></i> Remove</a>
                                    <?php else: ?><i class="bi bi-lock-fill lock-i" title="Dispensed — locked"></i><?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; endif; ?>

                        </tbody>
                    </table>
                </div>

                <!-- BILL FOOTER -->
                <div class="bill-footer">
                    <div class="bf-totals-block">
                        <?php if ($is_inpatient && $room_total > 0): ?>
                        <div class="bf-line">
                            <span class="bf-lbl"><i class="bi bi-building"></i> Room Charges</span>
                            <span class="bf-amount">₱<?= number_format($room_total, 2) ?></span>
                        </div>
                        <?php endif; ?>
                        <div class="bf-line">
                            <span class="bf-lbl">Services &amp; Medicines</span>
                            <span class="bf-amount">₱<?= number_format($subtotal, 2) ?></span>
                        </div>
                        <?php if ($room_total > 0): ?>
                        <div class="bf-line" style="padding-top:4px;border-top:1px dashed var(--border);">
                            <span class="bf-lbl" style="font-weight:600;">Subtotal</span>
                            <span class="bf-amount">₱<?= number_format($subtotal_with_room, 2) ?></span>
                        </div>
                        <?php endif; ?>
                        <?php if ($discount > 0): ?>
                        <div class="bf-line bf-disc-line">
                            <span class="bf-lbl"><i class="bi bi-tag-fill"></i> <?= $is_senior ? 'Senior Citizen' : 'PWD/Senior' ?> Discount (20%)</span>
                            <span class="bf-amount">−₱<?= number_format($discount, 2) ?></span>
                        </div>
                        <?php endif; ?>
                        <div class="bf-divider"></div>
                        <div class="bf-line" style="margin-top:2px;">
                            <span class="bf-grand-lbl">Grand Total</span>
                            <span class="bf-grand-amt <?= $grand_total <= 0 && (!empty($cart) || $room_total > 0) ? 'zero' : '' ?>">₱<?= number_format($grand_total, 2) ?></span>
                        </div>
                        <?php if ($is_inpatient && empty($stay['discharge_date'])): ?>
                        <div style="margin-top:8px;padding:8px 12px;background:#fef3c7;border:1px solid #fde68a;border-radius:8px;font-size:.8rem;color:#92400e;display:flex;align-items:center;gap:6px;">
                            <i class="bi bi-clock-fill"></i>
                            <strong>Patient is still admitted.</strong> Add items using the forms, click <strong>Save Stay Info</strong> to preserve them. Set discharge date when ready to finalize.
                        </div>
                        <?php endif; ?>
                    </div>
                    <div class="bf-actions">
                        <div class="bf-act-left">
                            <a href="billing_items.php?tab=inpatient" class="bf-btn-back"><i class="bi bi-arrow-left"></i> Back</a>
                        </div>
                        <div class="bf-act-center">
                            <?php if ($can_finalize): ?>
                            <button class="bf-btn-finalize" onclick="confirmFinalize()">
                                <i class="bi bi-check-circle-fill"></i> <?= $existing_bill ? 'Update &amp; Finalize' : 'Finalize Billing' ?>
                            </button>
                            <?php elseif ($is_inpatient && empty($stay['discharge_date'])): ?>
                            <button class="bf-btn-finalize-disabled" disabled><i class="bi bi-clock"></i> Awaiting Discharge</button>
                            <span class="no-discharge-fin"><i class="bi bi-exclamation-triangle-fill"></i> Set discharge date to finalize</span>
                            <?php elseif (!empty($cart) && $grand_total <= 0): ?>
                            <button class="bf-btn-finalize-disabled" disabled><i class="bi bi-slash-circle"></i> Total is ₱0.00</button>
                            <?php else: ?>
                            <span class="bf-empty-note"><i class="bi bi-inbox"></i> No items to finalize</span>
                            <?php endif; ?>
                        </div>
                        <div class="bf-act-right">
                            <?php if ($existing_bill): ?>
                            <a href="patient_billing.php?patient_id=<?= $patient_id ?>" class="bf-btn-view"><i class="bi bi-eye"></i> View</a>
                            <?php endif; ?>
                            <a href="billing_items.php?patient_id=<?= $patient_id ?>&reset_cart=1" class="bf-btn-reload" onclick="return confirm('Clear all manually-added extra items?')"><i class="bi bi-x-circle"></i> Clear Extras</a>
                        </div>
                    </div>
                </div>

            </div><!-- /.bcard -->
        </div><!-- /.billing-right -->

    </div><!-- /.billing-layout -->
</div><!-- /.cw -->

<script>
    /* ── Room preview calculator ── */
    function updateRoomPreview() {
        const sel        = document.getElementById('roomTypeSelect');
        const admInp     = document.getElementById('admissionDate');
        const disInp     = document.getElementById('dischargeDate');
        const preview    = document.getElementById('stayPreview');

        if (!sel || !sel.value) { if(preview) preview.style.display = 'none'; return; }

        const opt        = sel.options[sel.selectedIndex];
        const priceDay   = parseFloat(opt.dataset.priceDay) || 0;
        const name       = opt.dataset.name || '—';
        const admVal     = admInp ? admInp.value : '';
        const disVal     = disInp ? disInp.value : '';

        let hours = 0, days = 0, total = 0;
        if (admVal) {
            const admTs = new Date(admVal).getTime();
            const endTs = disVal ? new Date(disVal).getTime() : Date.now();
            const diffMs = Math.max(0, endTs - admTs);
            hours = Math.round(diffMs / 36000) / 100;
            days  = Math.ceil(hours / 24) || 1;
            total = days * priceDay;
        }

        document.getElementById('prevRoomName').textContent = name;
        document.getElementById('prevHours').textContent    = hours > 0 ? hours.toFixed(1) + ' hrs' : '—';
        document.getElementById('prevDays').textContent     = days > 0  ? days + ' day' + (days > 1 ? 's' : '') : '—';
        document.getElementById('prevTotal').textContent    = '₱' + total.toLocaleString('en-PH', {minimumFractionDigits:2, maximumFractionDigits:2});

        if (preview) preview.style.display = 'flex';
    }

    /* Initialize preview on load */
    document.addEventListener('DOMContentLoaded', () => updateRoomPreview());

    /* ── Qty stepper ── */
    function stepQty(btn, delta) {
        const inp = btn.parentElement.querySelector('.qty-input');
        inp.value = Math.max(1, Math.min(9999, (parseInt(inp.value) || 1) + delta));
    }

    /* ── Finalize confirm ── */
    function confirmFinalize() {
        const g = <?= (float)$grand_total ?>;
        if (g <= 0) {
            Swal.fire({ icon: 'warning', title: 'Cannot Finalize', html: 'Grand total is <strong>₱0.00</strong>.', confirmButtonColor: '#0b1d3a' });
            return;
        }
        Swal.fire({
            title: '<?= $existing_bill ? "Update Bill?" : "Finalize Bill?" ?>',
            html: `<div style="text-align:left;font-size:.9rem;line-height:2;">
                <?php if ($room_total > 0): ?><div>🏥 Room / Accommodation: <strong>₱<?= number_format($room_total, 2) ?></strong></div><?php endif; ?>
                <?php if (!empty($cat_services)):  ?><div>🩺 Procedures: <strong>₱<?= number_format($svc_total, 2) ?></strong></div><?php endif; ?>
                <?php if (!empty($cat_laboratory)): ?><div>🧪 Laboratory: <strong>₱<?= number_format($lab_total, 2) ?></strong></div><?php endif; ?>
                <?php if (!empty($cat_medicines)):  ?><div>💊 Medicines: <strong>₱<?= number_format($med_total, 2) ?></strong></div><?php endif; ?>
                <hr style="margin:8px 0;border-color:#e2e8f0;">
                <?php if ($discount > 0): ?><div style="color:#dc2626;">🏷 Discount (<?= $is_senior ? 'Senior' : 'PWD' ?>): −₱<?= number_format($discount, 2) ?></div><?php endif; ?>
                <div style="font-weight:700;font-size:1.05rem;margin-top:4px;">Grand Total: ₱<?= number_format($grand_total, 2) ?></div>
                <small style="color:#64748b;"><?= $existing_bill ? "This will update the existing bill." : "This marks the patient as discharged and cannot be undone." ?></small>
            </div>`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#059669',
            cancelButtonColor: '#64748b',
            confirmButtonText: '<?= $existing_bill ? "Yes, Update" : "Yes, Finalize" ?>',
            cancelButtonText: 'Cancel'
        }).then(r => {
            if (r.isConfirmed) window.location.href = 'billing_items.php?patient_id=<?= $patient_id ?>&finalize=1';
        });
    }

    /* ── Sidebar sync ── */
    (function() {
        const sb = document.getElementById('mySidebar'), cw = document.getElementById('mainCw');
        if (!sb || !cw) return;
        function sync() { cw.classList.toggle('sidebar-collapsed', sb.classList.contains('closed')); }
        new MutationObserver(sync).observe(sb, { attributes: true, attributeFilter: ['class'] });
        document.getElementById('sidebarToggle')?.addEventListener('click', () => requestAnimationFrame(sync));
        sync();
    })();
</script>
</body>
</html>