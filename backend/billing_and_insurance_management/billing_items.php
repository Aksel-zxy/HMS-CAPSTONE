<?php
session_start();
include '../../SQL/config.php';

$patient_id = isset($_GET['patient_id']) ? intval($_GET['patient_id']) : 0;

/* =========================================================
   PATIENT SELECTION PAGE
========================================================= */
if ($patient_id <= 0) {

    $sql = "
        SELECT DISTINCT p.patient_id,
               CONCAT(p.fname, ' ', IFNULL(p.mname, ''), ' ', p.lname) AS full_name
        FROM patientinfo p
        LEFT JOIN dl_results dr 
            ON p.patient_id = dr.patientID AND dr.status='Completed'
        LEFT JOIN dnm_records dnr
            ON p.patient_id = dnr.duty_id
        LEFT JOIN pharmacy_prescription pp
            ON p.patient_id = pp.patient_id 
            AND pp.payment_type = 'post_discharged'
            AND pp.status = 'Dispensed'
            AND pp.billing_status = 'pending'
        WHERE (dr.patientID IS NOT NULL OR dnr.record_id IS NOT NULL OR pp.prescription_id IS NOT NULL)
          AND p.patient_id NOT IN (
                SELECT DISTINCT patient_id 
                FROM billing_items 
                WHERE finalized = 1
          )
        ORDER BY p.lname ASC, p.fname ASC
    ";

    $patients = $conn->query($sql);
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
        <title>Select Patient for Billing</title>
        <link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display&family=DM+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
        <link rel="stylesheet" href="assets/CSS/bootstrap.min.css">
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
              --radius:       14px;
              --shadow:       0 2px 20px rgba(11,29,58,.08);
              --ff-head:      'DM Serif Display', serif;
              --ff-body:      'DM Sans', sans-serif;
            }

            *, *::before, *::after { box-sizing: border-box; }

            body {
              font-family: var(--ff-body);
              background: var(--surface);
              color: var(--ink);
              margin: 0;
              padding: 0;
            }

            /* Content shifts right — NOT body */
            .cw {
              margin-left: var(--sidebar-w);
              padding: 60px 28px 60px;
              transition: margin-left .3s ease-in-out;
            }
            .cw.sidebar-collapsed { margin-left: 0; }

            /* ── Page card ── */
            .page-card {
              background: var(--card);
              border: 1px solid var(--border);
              border-radius: var(--radius);
              box-shadow: var(--shadow);
              overflow: hidden;
            }
            .page-card-header {
              background: var(--navy);
              padding: 18px 24px;
              display: flex;
              align-items: center;
              gap: 12px;
            }
            .page-card-header h2 {
              font-family: var(--ff-head);
              font-size: 1.25rem;
              color: #fff;
              margin: 0;
            }
            .page-card-header i { color: rgba(255,255,255,.7); font-size: 1.2rem; }
            .page-card-body { padding: 24px; }

            /* ── Table ── */
            .sel-table { width: 100%; border-collapse: collapse; font-size: .9rem; }
            .sel-table thead th {
              background: #f8fafc;
              color: var(--ink-light);
              font-size: .7rem;
              font-weight: 700;
              text-transform: uppercase;
              letter-spacing: .6px;
              padding: 12px 16px;
              border-bottom: 2px solid var(--border);
              text-align: left;
            }
            .sel-table thead th:last-child { text-align: right; }
            .sel-table tbody tr { border-bottom: 1px solid var(--border); transition: background .12s; }
            .sel-table tbody tr:last-child { border-bottom: none; }
            .sel-table tbody tr:hover { background: #f7faff; }
            .sel-table tbody td { padding: 13px 16px; vertical-align: middle; }
            .sel-table tbody td:last-child { text-align: right; }

            /* Patient avatar + name */
            .pat-cell { display: flex; align-items: center; gap: 10px; }
            .pat-avatar {
              width: 36px; height: 36px;
              border-radius: 50%;
              background: linear-gradient(135deg, var(--navy), var(--accent));
              color: #fff;
              display: flex; align-items: center; justify-content: center;
              font-size: .78rem; font-weight: 700; flex-shrink: 0;
            }
            .pat-name { font-weight: 600; color: var(--navy); }

            .btn-manage {
              background: var(--accent);
              color: #fff;
              border: none;
              border-radius: 8px;
              padding: 6px 16px;
              font-size: .82rem;
              font-weight: 600;
              font-family: var(--ff-body);
              text-decoration: none;
              display: inline-flex;
              align-items: center;
              gap: 5px;
              transition: background .15s, transform .1s;
            }
            .btn-manage:hover { background: #1d4ed8; color: #fff; transform: translateY(-1px); }

            .empty-row td {
              text-align: center;
              padding: 48px 16px;
              color: var(--ink-light);
              font-size: .9rem;
            }

            /* ── Responsive ── */
            @media (max-width: 768px) {
              .cw { margin-left: var(--sidebar-w-sm); padding: 60px 14px 50px; }
              .cw.sidebar-collapsed { margin-left: 0; }
              .page-card-header h2 { font-size: 1.05rem; }
            }
            @media (max-width: 480px) {
              .cw { margin-left: 0 !important; padding: 56px 10px 40px; }
              .sel-table { font-size: .82rem; }
              .page-card-body { padding: 14px; }
            }
            @supports (padding: env(safe-area-inset-bottom)) {
              .cw { padding-bottom: calc(60px + env(safe-area-inset-bottom)); }
            }
        </style>
    </head>
    <body>

    <?php include 'billing_sidebar.php'; ?>

    <div class="cw" id="mainCw">
        <div class="page-card">
            <div class="page-card-header">
                <i class="bi bi-people"></i>
                <h2>Select Patient for Billing</h2>
            </div>
            <div class="page-card-body">
                <div style="overflow-x:auto;">
                    <table class="sel-table">
                        <thead>
                            <tr>
                                <th>Patient Name</th>
                                <th style="text-align:right;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if ($patients && $patients->num_rows > 0): ?>
                            <?php while ($row = $patients->fetch_assoc()):
                                $initials = strtoupper(substr(trim($row['full_name']), 0, 1));
                            ?>
                                <tr>
                                    <td>
                                        <div class="pat-cell">
                                            <div class="pat-avatar"><?= $initials ?></div>
                                            <span class="pat-name"><?= htmlspecialchars($row['full_name']); ?></span>
                                        </div>
                                    </td>
                                    <td>
                                        <a href="billing_items.php?patient_id=<?= $row['patient_id']; ?>" class="btn-manage">
                                            Manage Billing
                                        </a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr class="empty-row">
                                <td colspan="2">No patients with unbilled completed services.</td>
                            </tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script>
    (function () {
        const sidebar = document.getElementById('mySidebar');
        const cw      = document.getElementById('mainCw');
        if (!sidebar || !cw) return;
        function sync() {
            cw.classList.toggle('sidebar-collapsed', sidebar.classList.contains('closed'));
        }
        new MutationObserver(sync).observe(sidebar, { attributes: true, attributeFilter: ['class'] });
        document.getElementById('sidebarToggle')?.addEventListener('click', () => requestAnimationFrame(sync));
        sync();
    })();
    </script>
    </body>
    </html>
    <?php
    exit;
}

/* =========================================================
   LOAD PATIENT INFO
========================================================= */
$stmt = $conn->prepare("SELECT * FROM patientinfo WHERE patient_id=?");
$stmt->bind_param("i", $patient_id);
$stmt->execute();
$patient = $stmt->get_result()->fetch_assoc();
if (!$patient) die("Patient not found");

/* =========================================================
   AGE COMPUTATION
========================================================= */
$age = 0;
if (!empty($patient['dob']) && $patient['dob'] != '0000-00-00') {
    $birth = new DateTime($patient['dob']);
    $today = new DateTime();
    $age = $today->diff($birth)->y;
}

/* =========================================================
   INITIALIZE BILLING CART
========================================================= */
if (!isset($_SESSION['billing_cart'][$patient_id])) {
    $_SESSION['billing_cart'][$patient_id] = [];

    /* ---- Load Lab Services ---- */
    $stmt = $conn->prepare("SELECT result FROM dl_results WHERE patientID=? AND status='Completed'");
    $stmt->bind_param("i", $patient_id);
    $stmt->execute();
    $res = $stmt->get_result();

    while ($r = $res->fetch_assoc()) {
        $services = array_map('trim', explode(",", $r['result']));
        foreach ($services as $srvName) {
            if ($srvName == '') continue;
            $stmt2 = $conn->prepare("SELECT serviceID, serviceName, description, price FROM dl_services WHERE serviceName=? LIMIT 1");
            $stmt2->bind_param("s", $srvName);
            $stmt2->execute();
            $srv = $stmt2->get_result()->fetch_assoc();
            if ($srv) $_SESSION['billing_cart'][$patient_id][] = $srv;
        }
    }

    /* ---- Load DNM Procedures ---- */
    $stmt = $conn->prepare("SELECT procedure_name, amount FROM dnm_records WHERE duty_id=?");
    $stmt->bind_param("i", $patient_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $existingNames = array_column($_SESSION['billing_cart'][$patient_id], 'serviceName');

    while ($row = $res->fetch_assoc()) {
        if (in_array($row['procedure_name'], $existingNames)) continue;
        $_SESSION['billing_cart'][$patient_id][] = [
            'serviceID'   => 'DNM-' . md5($row['procedure_name']),
            'serviceName' => $row['procedure_name'],
            'description' => 'Doctor / Nurse Management Procedure',
            'price'       => $row['amount']
        ];
    }

    /* ---- Load Pharmacy Prescriptions (Post-Discharged only) ---- */
    $stmt = $conn->prepare("
        SELECT ppi.item_id, ppi.med_id, ppi.dosage, ppi.frequency,
               ppi.quantity_dispensed, ppi.unit_price, ppi.total_price
        FROM pharmacy_prescription pp
        JOIN pharmacy_prescription_items ppi ON pp.prescription_id = ppi.prescription_id
        WHERE pp.patient_id = ? 
          AND pp.payment_type = 'post_discharged'
          AND pp.status = 'Dispensed'
          AND pp.billing_status = 'pending'
    ");
    $stmt->bind_param("i", $patient_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $existingNames = array_column($_SESSION['billing_cart'][$patient_id], 'serviceName');

    while ($row = $res->fetch_assoc()) {
        $stmt2 = $conn->prepare("SELECT medicine_name FROM medicines WHERE med_id = ? LIMIT 1");
        $stmt2->bind_param("i", $row['med_id']);
        $stmt2->execute();
        $med = $stmt2->get_result()->fetch_assoc();
        $medName = $med ? $med['medicine_name'] : 'Medicine #' . $row['med_id'];
        $label = $medName . ' (' . $row['dosage'] . ', ' . $row['frequency'] . ')';
        if (in_array($label, $existingNames)) continue;
        $_SESSION['billing_cart'][$patient_id][] = [
            'serviceID'   => 'RX-' . $row['item_id'],
            'serviceName' => $label,
            'description' => 'Pharmacy — Qty: ' . $row['quantity_dispensed'] . ' x ₱' . number_format($row['unit_price'], 2),
            'price'       => $row['total_price']
        ];
        $existingNames[] = $label;
    }
}

/* =========================================================
   ADD / DELETE SERVICES & PWD TOGGLE
========================================================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_service'])) {
    $service_id = intval($_POST['service_id']);
    if ($service_id > 0) {
        $stmt = $conn->prepare("SELECT serviceID, serviceName, description, price FROM dl_services WHERE serviceID=? LIMIT 1");
        $stmt->bind_param("i", $service_id);
        $stmt->execute();
        $srv = $stmt->get_result()->fetch_assoc();
        if ($srv) $_SESSION['billing_cart'][$patient_id][] = $srv;
    }
    header("Location: billing_items.php?patient_id=$patient_id");
    exit;
}

if (isset($_GET['delete'])) {
    $index = intval($_GET['delete']);
    if (isset($_SESSION['billing_cart'][$patient_id][$index])) {
        unset($_SESSION['billing_cart'][$patient_id][$index]);
        $_SESSION['billing_cart'][$patient_id] = array_values($_SESSION['billing_cart'][$patient_id]);
    }
    header("Location: billing_items.php?patient_id=$patient_id");
    exit;
}

if (isset($_GET['toggle_pwd'])) {
    $_SESSION['is_pwd'][$patient_id] = $_GET['toggle_pwd'] == 1 ? 1 : 0;
    header("Location: billing_items.php?patient_id=$patient_id");
    exit;
}

/* =========================================================
   BILL COMPUTATION
========================================================= */
$cart       = $_SESSION['billing_cart'][$patient_id];
$subtotal   = array_sum(array_column($cart, 'price'));
$is_pwd     = $_SESSION['is_pwd'][$patient_id] ?? ($patient['is_pwd'] ?? 0);
$is_senior  = $age >= 60;
$discount   = ($is_pwd || $is_senior) ? $subtotal * 0.20 : 0;
$grand_total = $subtotal - $discount;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<title>Billing Items</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display&family=DM+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<link rel="stylesheet" href="assets/CSS/bootstrap.min.css">
<link rel="stylesheet" href="assets/css/billing_sidebar.css">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

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
  --success:      #059669;
  --danger:       #dc2626;
  --radius:       14px;
  --shadow:       0 2px 20px rgba(11,29,58,.08);
  --shadow-lg:    0 8px 40px rgba(11,29,58,.14);
  --ff-head:      'DM Serif Display', serif;
  --ff-body:      'DM Sans', sans-serif;
}

*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

body {
  font-family: var(--ff-body);
  background: var(--surface);
  color: var(--ink);
  margin: 0;
}

/* ── Content wrapper — margin goes HERE, not on body ── */
.cw {
  margin-left: var(--sidebar-w);
  padding: 60px 28px 60px;
  transition: margin-left .3s ease-in-out;
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
  width: 50px; height: 50px;
  background: linear-gradient(135deg, var(--navy), var(--accent));
  border-radius: 13px;
  display: flex; align-items: center; justify-content: center;
  color: #fff; font-size: 1.35rem;
  box-shadow: 0 6px 18px rgba(11,29,58,.2);
  flex-shrink: 0;
}
.page-head h2 {
  font-family: var(--ff-head);
  font-size: clamp(1.2rem, 2.5vw, 1.7rem);
  color: var(--navy);
  margin: 0;
}
.page-head p { font-size: .82rem; color: var(--ink-light); margin: 3px 0 0; }

/* ── Card ── */
.billing-card {
  background: var(--card);
  border: 1px solid var(--border);
  border-radius: var(--radius);
  box-shadow: var(--shadow);
  overflow: hidden;
  margin-bottom: 20px;
}
.billing-card-header {
  background: var(--navy);
  padding: 14px 20px;
  color: rgba(255,255,255,.8);
  font-size: .72rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: .7px;
  display: flex;
  align-items: center;
  gap: 8px;
}
.billing-card-body { padding: 20px; }

/* ── PWD / Senior Toggle ── */
.discount-toggle {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 12px 16px;
  background: #f0fdf4;
  border: 1.5px solid #bbf7d0;
  border-radius: 10px;
  margin-bottom: 20px;
  font-size: .88rem;
  color: #065f46;
  font-weight: 600;
}
.discount-toggle.is-senior {
  background: #eff6ff;
  border-color: #bfdbfe;
  color: #1d4ed8;
}
.discount-toggle input[type="checkbox"] {
  width: 18px; height: 18px;
  accent-color: var(--success);
  cursor: pointer;
  flex-shrink: 0;
}
.discount-badge {
  margin-left: auto;
  background: #059669;
  color: #fff;
  border-radius: 999px;
  padding: 3px 12px;
  font-size: .72rem;
  font-weight: 700;
}

/* ── Add Service Form ── */
.add-service-form {
  display: flex;
  gap: 10px;
  margin-bottom: 20px;
  flex-wrap: wrap;
}
.add-service-form select {
  flex: 1 1 260px;
  padding: 9px 14px;
  border: 1.5px solid var(--border);
  border-radius: 9px;
  font-family: var(--ff-body);
  font-size: .88rem;
  color: var(--ink);
  background: var(--card);
  outline: none;
  transition: border-color .2s, box-shadow .2s;
  min-width: 0;
}
.add-service-form select:focus {
  border-color: var(--accent);
  box-shadow: 0 0 0 3px rgba(37,99,235,.12);
}
.btn-add {
  padding: 9px 22px;
  background: var(--accent);
  color: #fff;
  border: none;
  border-radius: 9px;
  font-family: var(--ff-body);
  font-size: .88rem;
  font-weight: 700;
  cursor: pointer;
  white-space: nowrap;
  display: inline-flex;
  align-items: center;
  gap: 6px;
  transition: background .15s, transform .1s;
}
.btn-add:hover { background: #1d4ed8; transform: translateY(-1px); }

/* ── Services Table ── */
.srv-table {
  width: 100%;
  border-collapse: collapse;
  font-size: .88rem;
}
.srv-table thead th {
  background: #f8fafc;
  color: var(--ink-light);
  font-size: .7rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: .6px;
  padding: 11px 14px;
  border-bottom: 2px solid var(--border);
  text-align: left;
  white-space: nowrap;
}
.srv-table thead th.th-price  { text-align: right; }
.srv-table thead th.th-action { text-align: center; }

.srv-table tbody tr {
  border-bottom: 1px solid var(--border);
  transition: background .12s;
}
.srv-table tbody tr:last-child { border-bottom: none; }
.srv-table tbody tr:hover { background: #f7faff; }

.srv-table tbody td {
  padding: 12px 14px;
  vertical-align: middle;
}
.srv-table tbody td.td-price  { text-align: right; font-weight: 600; color: var(--success); }
.srv-table tbody td.td-action { text-align: center; }

.srv-name { font-weight: 600; color: var(--navy); font-size: .88rem; }
.srv-desc { font-size: .76rem; color: var(--ink-light); margin-top: 2px; }

/* Source badge */
.src-badge {
  display: inline-block;
  padding: 2px 8px;
  border-radius: 999px;
  font-size: .68rem;
  font-weight: 700;
  margin-right: 4px;
}
.src-lab  { background: #dbeafe; color: #1d4ed8; }
.src-dnm  { background: #ede9fe; color: #5b21b6; }
.src-rx   { background: #d1fae5; color: #065f46; }

.btn-del {
  background: #fff1f2;
  color: var(--danger);
  border: 1.5px solid #fecdd3;
  border-radius: 7px;
  padding: 5px 12px;
  font-size: .78rem;
  font-weight: 700;
  font-family: var(--ff-body);
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  gap: 4px;
  text-decoration: none;
  transition: all .15s;
}
.btn-del:hover { background: var(--danger); color: #fff; border-color: var(--danger); }

.empty-state {
  text-align: center;
  padding: 40px 16px;
  color: var(--ink-light);
}
.empty-state i { font-size: 2rem; display: block; margin-bottom: 8px; opacity: .35; }

/* ── Totals Panel ── */
.totals-panel {
  background: var(--card);
  border: 1px solid var(--border);
  border-radius: var(--radius);
  box-shadow: var(--shadow);
  padding: 20px 24px;
  margin-bottom: 20px;
}
.totals-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 8px 0;
  font-size: .9rem;
  border-bottom: 1px solid var(--border);
  gap: 12px;
}
.totals-row:last-child { border-bottom: none; }
.totals-label { color: var(--ink-light); font-weight: 500; }
.totals-val   { font-weight: 600; color: var(--ink); }
.totals-row.grand { padding-top: 14px; }
.totals-row.grand .totals-label { font-size: 1rem; font-weight: 700; color: var(--navy); }
.totals-row.grand .totals-val   { font-size: 1.15rem; font-weight: 700; color: var(--navy); }
.discount-val { color: var(--danger) !important; }

/* ── Action Buttons ── */
.actions-bar {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 12px;
  flex-wrap: wrap;
}
.btn-back {
  padding: 10px 22px;
  background: var(--card);
  color: var(--ink-light);
  border: 1.5px solid var(--border);
  border-radius: 9px;
  font-family: var(--ff-body);
  font-size: .88rem;
  font-weight: 600;
  cursor: pointer;
  text-decoration: none;
  display: inline-flex;
  align-items: center;
  gap: 6px;
  transition: all .15s;
}
.btn-back:hover { border-color: var(--accent); color: var(--accent); background: #eff6ff; }

.btn-finalize {
  padding: 10px 26px;
  background: var(--success);
  color: #fff;
  border: none;
  border-radius: 9px;
  font-family: var(--ff-body);
  font-size: .88rem;
  font-weight: 700;
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  gap: 6px;
  transition: background .15s, transform .1s;
  box-shadow: 0 4px 14px rgba(5,150,105,.3);
}
.btn-finalize:hover { background: #047857; transform: translateY(-1px); }

/* ── Mobile card view for services ── */
.srv-mobile { display: none; }
.srv-m-card {
  background: var(--card);
  border: 1px solid var(--border);
  border-radius: 11px;
  padding: 14px;
  margin-bottom: 10px;
  box-shadow: 0 1px 6px rgba(11,29,58,.06);
}
.srv-m-top {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 10px;
  margin-bottom: 8px;
}
.srv-m-price {
  font-weight: 700;
  color: var(--success);
  font-size: .95rem;
  white-space: nowrap;
}
.srv-m-desc { font-size: .78rem; color: var(--ink-light); margin-bottom: 10px; }

/* ── Responsive ── */
@media (max-width: 768px) {
  .cw { margin-left: var(--sidebar-w-sm); padding: 60px 14px 50px; }
  .cw.sidebar-collapsed { margin-left: 0; }
  .srv-table  { display: none; }
  .srv-mobile { display: block; }
  .page-head h2 { font-size: 1.2rem; }
  .page-head-icon { width: 42px; height: 42px; font-size: 1.2rem; }
  .totals-panel { padding: 16px; }
  .billing-card-body { padding: 14px; }
}

@media (max-width: 480px) {
  .cw { margin-left: 0 !important; padding: 56px 10px 40px; }
  .add-service-form { flex-direction: column; }
  .add-service-form select, .btn-add { width: 100%; justify-content: center; }
  .actions-bar { flex-direction: column-reverse; }
  .btn-back, .btn-finalize { width: 100%; justify-content: center; }
}

@supports (padding: env(safe-area-inset-bottom)) {
  .cw { padding-bottom: calc(60px + env(safe-area-inset-bottom)); }
}
</style>

<script>
function togglePWD(checkbox) {
    let val = checkbox.checked ? 1 : 0;
    window.location.href = "billing_items.php?patient_id=<?= $patient_id ?>&toggle_pwd=" + val;
}
function finalizeBilling() {
    Swal.fire({
        title: 'Finalize Billing?',
        html: 'Grand Total: <strong>₱<?= number_format($grand_total, 2) ?></strong><br>This action cannot be undone.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#059669',
        cancelButtonColor: '#64748b',
        confirmButtonText: 'Yes, Finalize',
        cancelButtonText: 'Cancel'
    }).then((result) => {
        if (result.isConfirmed) {
            fetch('finalize_billing.php?patient_id=<?= $patient_id ?>')
            .then(r => r.text())
            .then(() => {
                Swal.fire({
                    icon: 'success',
                    title: 'Billing Finalized!',
                    html: 'Grand Total: <strong>₱<?= number_format($grand_total, 2) ?></strong>',
                    confirmButtonColor: '#059669',
                    confirmButtonText: 'OK'
                }).then(() => { window.location.href = 'billing_items.php'; });
            }).catch(() => {
                Swal.fire({ icon: 'error', title: 'Error', text: 'An error occurred while finalizing billing.' });
            });
        }
    });
}
</script>
</head>
<body>

<?php include 'billing_sidebar.php'; ?>

<div class="cw" id="mainCw">

    <!-- Page Header -->
    <div class="page-head">
        <div class="page-head-icon"><i class="bi bi-receipt-cutoff"></i></div>
        <div>
            <h2>Billing Items</h2>
            <p><?= htmlspecialchars($patient['fname'] . ' ' . $patient['lname']) ?> &mdash; <?= $is_senior ? 'Senior Citizen' : 'Patient' ?></p>
        </div>
    </div>

    <!-- PWD / Senior Toggle -->
    <?php if ($is_senior): ?>
    <div class="discount-toggle is-senior">
        <i class="bi bi-person-check-fill" style="font-size:1.1rem;"></i>
        Senior Citizen — 20% discount applied automatically
        <span class="discount-badge" style="background:#1d4ed8;">SC Discount</span>
    </div>
    <?php else: ?>
    <div class="discount-toggle">
        <input type="checkbox" id="pwdCheck" <?= $is_pwd ? 'checked' : '' ?> onchange="togglePWD(this)">
        <label for="pwdCheck" style="cursor:pointer;">Patient is PWD (20% discount)</label>
        <?php if ($is_pwd): ?>
            <span class="discount-badge">PWD Discount</span>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Add Service Form -->
    <div class="billing-card">
        <div class="billing-card-header">
            <i class="bi bi-plus-circle"></i> Add Service
        </div>
        <div class="billing-card-body">
            <form method="POST" class="add-service-form">
                <select name="service_id">
                    <option value="">— Select a service to add —</option>
                    <?php
                    $cart_ids = array_filter(array_column($cart, 'serviceID'), 'is_numeric');
                    $cart_ids = array_values($cart_ids);
                    if ($cart_ids) {
                        $ph = implode(',', array_fill(0, count($cart_ids), '?'));
                        $sql = "SELECT * FROM dl_services WHERE serviceID NOT IN ($ph) ORDER BY serviceName ASC";
                        $stmt = $conn->prepare($sql);
                        $stmt->bind_param(str_repeat('i', count($cart_ids)), ...$cart_ids);
                        $stmt->execute();
                        $res = $stmt->get_result();
                    } else {
                        $res = $conn->query("SELECT * FROM dl_services ORDER BY serviceName ASC");
                    }
                    while ($srv = $res->fetch_assoc()):
                    ?>
                    <option value="<?= $srv['serviceID'] ?>">
                        <?= htmlspecialchars($srv['serviceName']) ?> — ₱<?= number_format($srv['price'], 2) ?>
                    </option>
                    <?php endwhile; ?>
                </select>
                <button type="submit" name="add_service" class="btn-add">
                    <i class="bi bi-plus-lg"></i> Add
                </button>
            </form>
        </div>
    </div>

    <!-- Services Table — Desktop -->
    <div class="billing-card">
        <div class="billing-card-header">
            <i class="bi bi-list-check"></i> Services &amp; Charges
            <span style="margin-left:auto;font-weight:400;opacity:.7;"><?= count($cart) ?> item<?= count($cart) !== 1 ? 's' : '' ?></span>
        </div>
        <div class="billing-card-body" style="padding:0;">
            <div style="overflow-x:auto;">
                <table class="srv-table">
                    <thead>
                        <tr>
                            <th>Service</th>
                            <th>Description</th>
                            <th class="th-price">Price</th>
                            <th class="th-action">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($cart)): ?>
                        <tr>
                            <td colspan="4">
                                <div class="empty-state">
                                    <i class="bi bi-inbox"></i>
                                    No services added yet.
                                </div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($cart as $i => $srv):
                            // Detect source for badge
                            $srcClass = 'src-lab';
                            $srcLabel = 'Lab';
                            if (strpos($srv['serviceID'], 'DNM-') === 0) { $srcClass = 'src-dnm'; $srcLabel = 'DNM'; }
                            elseif (strpos($srv['serviceID'], 'RX-') === 0)  { $srcClass = 'src-rx';  $srcLabel = 'RX'; }
                        ?>
                        <tr>
                            <td>
                                <span class="src-badge <?= $srcClass ?>"><?= $srcLabel ?></span>
                                <span class="srv-name"><?= htmlspecialchars($srv['serviceName']) ?></span>
                            </td>
                            <td><span class="srv-desc"><?= htmlspecialchars($srv['description']) ?></span></td>
                            <td class="td-price">₱<?= number_format($srv['price'], 2) ?></td>
                            <td class="td-action">
                                <a href="billing_items.php?patient_id=<?= $patient_id ?>&delete=<?= $i ?>"
                                   class="btn-del"
                                   onclick="return confirm('Remove this service?')">
                                    <i class="bi bi-trash"></i> Remove
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Mobile card list -->
            <div class="srv-mobile" style="padding:14px;">
                <?php if (empty($cart)): ?>
                    <div class="empty-state"><i class="bi bi-inbox"></i> No services added yet.</div>
                <?php else: ?>
                    <?php foreach ($cart as $i => $srv):
                        $srcClass = 'src-lab'; $srcLabel = 'Lab';
                        if (strpos($srv['serviceID'], 'DNM-') === 0) { $srcClass = 'src-dnm'; $srcLabel = 'DNM'; }
                        elseif (strpos($srv['serviceID'], 'RX-') === 0) { $srcClass = 'src-rx'; $srcLabel = 'RX'; }
                    ?>
                    <div class="srv-m-card">
                        <div class="srv-m-top">
                            <div>
                                <span class="src-badge <?= $srcClass ?>"><?= $srcLabel ?></span>
                                <span class="srv-name"><?= htmlspecialchars($srv['serviceName']) ?></span>
                            </div>
                            <span class="srv-m-price">₱<?= number_format($srv['price'], 2) ?></span>
                        </div>
                        <div class="srv-m-desc"><?= htmlspecialchars($srv['description']) ?></div>
                        <a href="billing_items.php?patient_id=<?= $patient_id ?>&delete=<?= $i ?>"
                           class="btn-del"
                           onclick="return confirm('Remove this service?')"
                           style="width:100%;justify-content:center;">
                            <i class="bi bi-trash"></i> Remove
                        </a>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Totals Panel -->
    <div class="totals-panel">
        <div class="totals-row">
            <span class="totals-label">Subtotal</span>
            <span class="totals-val">₱<?= number_format($subtotal, 2) ?></span>
        </div>
        <?php if ($discount > 0): ?>
        <div class="totals-row">
            <span class="totals-label"><?= $is_senior ? 'Senior Citizen' : 'PWD' ?> Discount (20%)</span>
            <span class="totals-val discount-val">−₱<?= number_format($discount, 2) ?></span>
        </div>
        <?php endif; ?>
        <div class="totals-row grand">
            <span class="totals-label">Grand Total</span>
            <span class="totals-val">₱<?= number_format($grand_total, 2) ?></span>
        </div>
    </div>

    <!-- Action Buttons -->
    <div class="actions-bar">
        <a href="billing_items.php" class="btn-back">
            <i class="bi bi-arrow-left"></i> Back
        </a>
        <button type="button" class="btn-finalize" onclick="finalizeBilling()">
            <i class="bi bi-check-circle"></i> Finalize Billing
        </button>
    </div>

</div><!-- /cw -->

<script>
/* ── Sync .cw margin with sidebar open/close state ── */
(function () {
    const sidebar = document.getElementById('mySidebar');
    const cw      = document.getElementById('mainCw');
    if (!sidebar || !cw) return;
    function sync() {
        cw.classList.toggle('sidebar-collapsed', sidebar.classList.contains('closed'));
    }
    new MutationObserver(sync).observe(sidebar, { attributes: true, attributeFilter: ['class'] });
    document.getElementById('sidebarToggle')?.addEventListener('click', () => requestAnimationFrame(sync));
    sync();
})();
</script>
</body>
</html>