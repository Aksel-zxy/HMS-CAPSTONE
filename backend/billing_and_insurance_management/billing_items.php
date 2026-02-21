<?php
session_start();
include '../../SQL/config.php';

$patient_id = isset($_GET['patient_id']) ? intval($_GET['patient_id']) : 0;

/* =========================================================
   PATIENT LIST PAGE
========================================================= */
if ($patient_id <= 0) {
    $sql = "
        SELECT
            p.patient_id,
            CONCAT(p.fname,' ',IFNULL(NULLIF(p.mname,''),''),' ',p.lname) AS full_name,
            COUNT(DISTINCT dr.resultID)        AS lab_count,
            COUNT(DISTINCT dnmr.record_id)     AS dnm_count,
            COUNT(DISTINCT pp.prescription_id) AS rx_count
        FROM patientinfo p
        LEFT JOIN dl_results dr
               ON dr.patientID = p.patient_id AND dr.status = 'Completed'
        LEFT JOIN dnm_records dnmr
               ON dnmr.doctor_id = p.patient_id
        LEFT JOIN pharmacy_prescription pp
               ON pp.patient_id     = p.patient_id
              AND pp.payment_type   = 'post_discharged'
              AND pp.status         = 'Dispensed'
              AND pp.billing_status = 'pending'
        GROUP BY p.patient_id
        HAVING lab_count > 0 OR dnm_count > 0 OR rx_count > 0
        ORDER BY p.lname ASC, p.fname ASC
    ";
    $patients = $conn->query($sql);
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
:root{--sidebar-w:250px;--navy:#0b1d3a;--accent:#2563eb;--ink:#1e293b;--ink-light:#64748b;--border:#e2e8f0;--surface:#f1f5f9;--card:#fff;--radius:14px;--shadow:0 2px 20px rgba(11,29,58,.08);--ff-head:'DM Serif Display',serif;--ff-body:'DM Sans',sans-serif;}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
body{font-family:var(--ff-body);background:var(--surface);color:var(--ink);}
.cw{margin-left:var(--sidebar-w);padding:48px 28px 60px;transition:margin-left .3s;}
.cw.sidebar-collapsed{margin-left:0;}
.page-head{margin-bottom:24px;}
.page-head h2{font-family:var(--ff-head);font-size:1.7rem;color:var(--navy);}
.page-head p{font-size:.85rem;color:var(--ink-light);margin-top:4px;}
.card{background:var(--card);border:1px solid var(--border);border-radius:var(--radius);box-shadow:var(--shadow);overflow:hidden;}
.card-header{background:var(--navy);padding:14px 22px;display:flex;align-items:center;gap:10px;}
.card-header h5{font-family:var(--ff-head);color:#fff;margin:0;font-size:1rem;}
.tbl{width:100%;border-collapse:collapse;}
.tbl thead th{background:#f8fafc;color:var(--ink-light);font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.6px;padding:11px 18px;border-bottom:2px solid var(--border);text-align:left;}
.tbl tbody tr{border-bottom:1px solid var(--border);transition:background .12s;}
.tbl tbody tr:hover{background:#f7faff;}
.tbl td{padding:13px 18px;vertical-align:middle;font-size:.88rem;}
.pat-cell{display:flex;align-items:center;gap:10px;}
.pat-av{width:38px;height:38px;border-radius:50%;background:linear-gradient(135deg,var(--navy),var(--accent));color:#fff;display:flex;align-items:center;justify-content:center;font-size:.8rem;font-weight:700;flex-shrink:0;}
.pat-name{font-weight:600;color:var(--navy);}
.badges{display:flex;gap:5px;flex-wrap:wrap;}
.bdg{display:inline-flex;align-items:center;gap:4px;border-radius:999px;padding:3px 10px;font-size:.7rem;font-weight:700;}
.bdg-lab{background:#d1fae5;color:#065f46;}
.bdg-dnm{background:#fdf4ff;color:#7e22ce;}
.bdg-rx{background:#dbeafe;color:#1d4ed8;}
.btn-bill{display:inline-flex;align-items:center;gap:5px;background:var(--accent);color:#fff;border:none;border-radius:8px;padding:7px 16px;font-size:.82rem;font-weight:700;font-family:var(--ff-body);text-decoration:none;transition:background .15s;}
.btn-bill:hover{background:#1d4ed8;color:#fff;}
.empty-state{text-align:center;padding:56px 16px;color:var(--ink-light);}
.empty-state i{font-size:2.5rem;display:block;margin-bottom:12px;opacity:.3;}
@media(max-width:768px){.cw{margin-left:0;padding:52px 14px;}}
</style>
</head>
<body>
<?php include 'billing_sidebar.php'; ?>
<div class="cw" id="mainCw">
    <div class="page-head">
        <h2>Patient Billing</h2>
        <p>Patients with completed lab results, procedures, or pending prescriptions.</p>
    </div>
    <div class="card">
        <div class="card-header">
            <i class="bi bi-people-fill" style="color:rgba(255,255,255,.6);"></i>
            <h5>Patients Ready for Billing</h5>
        </div>
        <div style="overflow-x:auto;">
        <table class="tbl">
            <thead><tr><th>Patient</th><th>Services</th><th style="text-align:right;">Action</th></tr></thead>
            <tbody>
            <?php if ($patients && $patients->num_rows > 0):
                while ($row = $patients->fetch_assoc()):
                    $ini = strtoupper(substr(trim($row['full_name']),0,1));
            ?>
            <tr>
                <td>
                    <div class="pat-cell">
                        <div class="pat-av"><?= $ini ?></div>
                        <span class="pat-name"><?= htmlspecialchars(trim($row['full_name'])) ?></span>
                    </div>
                </td>
                <td>
                    <div class="badges">
                        <?php if ($row['lab_count']>0): ?><span class="bdg bdg-lab"><i class="bi bi-eyedropper"></i> <?=$row['lab_count']?> Lab</span><?php endif; ?>
                        <?php if ($row['dnm_count']>0): ?><span class="bdg bdg-dnm"><i class="bi bi-clipboard2-pulse"></i> <?=$row['dnm_count']?> Procedure</span><?php endif; ?>
                        <?php if ($row['rx_count']>0):  ?><span class="bdg bdg-rx"><i class="bi bi-capsule"></i> <?=$row['rx_count']?> Rx</span><?php endif; ?>
                    </div>
                </td>
                <td style="text-align:right;">
                    <a href="billing_items.php?patient_id=<?=$row['patient_id']?>" class="btn-bill">
                        <i class="bi bi-receipt"></i> Create Bill
                    </a>
                </td>
            </tr>
            <?php endwhile; else: ?>
            <tr><td colspan="3"><div class="empty-state"><i class="bi bi-inbox"></i>No patients with pending billing items.</div></td></tr>
            <?php endif; ?>
            </tbody>
        </table>
        </div>
    </div>
</div>
<script>
(function(){
    const sb=document.getElementById('mySidebar'),cw=document.getElementById('mainCw');
    if(!sb||!cw)return;
    function sync(){cw.classList.toggle('sidebar-collapsed',sb.classList.contains('closed'));}
    new MutationObserver(sync).observe(sb,{attributes:true,attributeFilter:['class']});
    document.getElementById('sidebarToggle')?.addEventListener('click',()=>requestAnimationFrame(sync));
    sync();
})();
</script>
</body></html>
<?php exit; }

/* =========================================================
   LOAD PATIENT
========================================================= */
$stmt = $conn->prepare("SELECT * FROM patientinfo WHERE patient_id=?");
$stmt->bind_param("i",$patient_id);
$stmt->execute();
$patient = $stmt->get_result()->fetch_assoc();
if (!$patient) die("Patient not found.");

/* AGE / SENIOR */
$age = 0; $dob_display = '—';
if (!empty($patient['dob']) && $patient['dob'] !== '0000-00-00') {
    $dobObj      = new DateTime($patient['dob']);
    $age         = (new DateTime())->diff($dobObj)->y;
    $dob_display = $dobObj->format('F d, Y');
}
$is_senior = $age >= 60;

/* PWD TOGGLE */
if (isset($_GET['toggle_pwd'])) {
    $_SESSION['is_pwd'][$patient_id] = (int)$_GET['toggle_pwd'];
    header("Location: billing_items.php?patient_id=$patient_id"); exit;
}
$is_pwd = $_SESSION['is_pwd'][$patient_id] ?? (int)($patient['is_pwd'] ?? 0);

/* EXISTING UNPAID BILL */
$stmt = $conn->prepare("SELECT billing_id,status,grand_total FROM billing_records WHERE patient_id=? AND status NOT IN ('Paid') ORDER BY billing_id DESC LIMIT 1");
$stmt->bind_param("i",$patient_id);
$stmt->execute();
$existing_bill = $stmt->get_result()->fetch_assoc();

/* =========================================================
   INITIALIZE SESSION CART
   Always re-initialize to pick up fresh DB data on every load.
========================================================= */
if (!isset($_SESSION['billing_cart'][$patient_id])) {
    $_SESSION['billing_cart'][$patient_id] = [];

    /* -------------------------------------------------------
       LAB RESULTS
       - Always display dl_results.result as the service name
       - Price lookup: exact match first, then LIKE keyword match
         so "X-Ray (Chest)" gets ₱800, "MRI (Brain)" tries "MRI%"
    ------------------------------------------------------- */
    $lab_stmt = $conn->prepare(
        "SELECT resultID, resultDate, result AS serviceName
          FROM dl_results
          WHERE patientID = ? AND status = 'Completed'
          ORDER BY resultDate ASC"
    );
    if ($lab_stmt) {
        $lab_stmt->bind_param("i", $patient_id);
        $lab_stmt->execute();
        $lab_rows = $lab_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        /* Load all services into memory for matching */
        $svc_map = [];
        $svc_res = $conn->query("SELECT serviceID, serviceName, price FROM dl_services ORDER BY serviceID");
        if ($svc_res) {
            while ($sv = $svc_res->fetch_assoc()) {
                $svc_map[strtolower(trim($sv['serviceName']))] = (float)$sv['price'];
            }
        }

        foreach ($lab_rows as $row) {
            $sname = trim($row['serviceName']);
            $price = 0.0;
            $key   = strtolower($sname);

            /* 1) Exact match */
            if (isset($svc_map[$key])) {
                $price = $svc_map[$key];
            }

            /* 2) Keyword match: strip parenthetical, use first word */
            if ($price == 0 && $sname !== '') {
                $keyword = strtolower(trim(preg_replace('/\s*\(.*?\)/', '', $sname)));
                $keyword = trim(strtok($keyword, ' ')); /* first word only */
                if (strlen($keyword) >= 2) {
                    foreach ($svc_map as $svc_name_lc => $svc_price) {
                        if (strpos($svc_name_lc, $keyword) !== false) {
                            $price = $svc_price;
                            break;
                        }
                    }
                }
            }

            $_SESSION['billing_cart'][$patient_id][] = [
                'cart_key'    => 'LAB-' . $row['resultID'],
                'ref_id'      => $row['resultID'],
                'med_id'      => null,
                'serviceName' => $sname ?: 'Laboratory Service',
                'description' => 'Lab Result — ' . date('M d, Y', strtotime($row['resultDate'])),
                'price'       => $price,
                'source'      => 'lab',
                'category'    => 'laboratory',
            ];
        }
    }
    /* -------------------------------------------------------
       DNM / PROCEDURE RECORDS
    ------------------------------------------------------- */
    $dnm_query = "
        SELECT
            dnmr.record_id,
            dnmr.procedure_name,
            dnmr.amount,
            dnmr.created_at
        FROM dnm_records dnmr
        WHERE dnmr.duty_id IN (
            SELECT da.duty_id
            FROM dl_results dr
            JOIN dl_schedule ds ON ds.scheduleID = dr.scheduleID
            JOIN duty_assignments da ON da.appointment_id = ds.scheduleID
            WHERE dr.patientID = ?
        )
        ORDER BY dnmr.created_at ASC
    ";
    $stmt = @$conn->prepare($dnm_query);
    if ($stmt) {
        $stmt->bind_param("i", $patient_id);
        $stmt->execute();
        foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $row) {
            /* If dnm_records.amount is 0, try price from dnm_procedure_list */
            $dnm_price = (float)$row['amount'];
            if ($dnm_price == 0 && !empty($row['procedure_name'])) {
                $ps = $conn->prepare("SELECT price FROM dnm_procedure_list WHERE LOWER(TRIM(procedure_name)) = LOWER(TRIM(?)) AND status='Active' LIMIT 1");
                if ($ps) {
                    $ps->bind_param("s", $row['procedure_name']);
                    $ps->execute();
                    $pr = $ps->get_result()->fetch_assoc();
                    if ($pr) $dnm_price = (float)$pr['price'];
                }
            }

            $_SESSION['billing_cart'][$patient_id][] = [
                'cart_key'    => 'DNM-'.$row['record_id'],
                'ref_id'      => $row['record_id'],
                'med_id'      => null,
                'serviceName' => $row['procedure_name'],
                'description' => 'Procedure — '.date('M d, Y', strtotime($row['created_at'])),
                'price'       => $dnm_price,
                'source'      => 'dnm',
                'category'    => 'service',
            ];
        }
    }

    /* -------------------------------------------------------
       PHARMACY / DISPENSED MEDICINES
    ------------------------------------------------------- */
    $stmt = $conn->prepare("
        SELECT
            ppi.item_id,
            ppi.med_id,
            ppi.dosage              AS rx_dosage,
            ppi.frequency,
            ppi.quantity_dispensed,
            ppi.unit_price,
            ppi.total_price,
            pi2.med_name,
            pi2.dosage              AS inv_dosage,
            pi2.unit_price          AS inv_unit_price
        FROM pharmacy_prescription pp
        JOIN pharmacy_prescription_items ppi ON ppi.prescription_id = pp.prescription_id
        JOIN pharmacy_inventory          pi2 ON pi2.med_id          = ppi.med_id
        WHERE pp.patient_id     = ?
          AND pp.payment_type   = 'post_discharged'
          AND pp.status         = 'Dispensed'
          AND pp.billing_status = 'pending'
        ORDER BY ppi.item_id ASC
    ");
    $stmt->bind_param("i", $patient_id);
    $stmt->execute();
    foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $row) {
        $dose = trim(($row['rx_dosage'] ?? $row['inv_dosage'] ?? '').' '.($row['frequency'] ?? ''));

        /* Use total_price; if 0 compute from unit_price × qty */
        $rx_total = (float)$row['total_price'];
        if ($rx_total == 0) {
            $unit = (float)($row['unit_price'] ?: $row['inv_unit_price'] ?: 0);
            $qty  = (int)($row['quantity_dispensed'] ?: 1);
            $rx_total = round($unit * $qty, 2);
        }

        $_SESSION['billing_cart'][$patient_id][] = [
            'cart_key'    => 'RX-'.$row['item_id'],
            'ref_id'      => $row['item_id'],
            'med_id'      => $row['med_id'],
            'serviceName' => $row['med_name'].($dose ? ' ('.$dose.')' : ''),
            'description' => 'Dispensed — Qty: '.$row['quantity_dispensed'].' × ₱'.number_format((float)($row['unit_price'] ?: $row['inv_unit_price']),2),
            'price'       => $rx_total,
            'source'      => 'rx',
            'category'    => 'medicine',
        ];
    }
}

/* =========================================================
   ADD EXTRA LABORATORY SERVICE
========================================================= */
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['add_lab'])) {
    $svc_id = (int)$_POST['lab_service_id'];
    $qty    = max(1,(int)($_POST['lab_qty'] ?? 1));
    if ($svc_id > 0) {
        $stmt = $conn->prepare("SELECT serviceID,serviceName,price FROM dl_services WHERE serviceID=? LIMIT 1");
        $stmt->bind_param("i",$svc_id);
        $stmt->execute();
        $svc = $stmt->get_result()->fetch_assoc();
        if ($svc) {
            $_SESSION['billing_cart'][$patient_id][] = [
                'cart_key'    => 'XLAB-'.$svc_id.'-'.time(),
                'ref_id'      => $svc_id,
                'med_id'      => null,
                'serviceName' => $svc['serviceName'],
                'description' => 'Added Lab Service'.($qty>1?' × '.$qty:'').' — ₱'.number_format($svc['price'],2).'/unit',
                'price'       => round($svc['price']*$qty,2),
                'source'      => 'add_lab',
                'category'    => 'laboratory',
            ];
        }
    }
    header("Location: billing_items.php?patient_id=$patient_id"); exit;
}

/* =========================================================
   ADD EXTRA SERVICE
========================================================= */
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['add_service'])) {
    $proc_id = (int)$_POST['procedure_id'];
    $qty     = max(1,(int)($_POST['svc_qty'] ?? 1));
    if ($proc_id > 0) {
        $stmt = $conn->prepare("SELECT procedure_id,procedure_name,price FROM dnm_procedure_list WHERE procedure_id=? AND status='Active' LIMIT 1");
        $stmt->bind_param("i",$proc_id);
        $stmt->execute();
        $proc = $stmt->get_result()->fetch_assoc();
        if ($proc) {
            $_SESSION['billing_cart'][$patient_id][] = [
                'cart_key'    => 'XSVC-'.$proc_id.'-'.time(),
                'ref_id'      => $proc_id,
                'med_id'      => null,
                'serviceName' => $proc['procedure_name'],
                'description' => 'Added'.($qty>1?' × '.$qty:'').' — ₱'.number_format($proc['price'],2).'/unit',
                'price'       => round($proc['price']*$qty,2),
                'source'      => 'add_svc',
                'category'    => 'service',
            ];
        }
    }
    header("Location: billing_items.php?patient_id=$patient_id"); exit;
}

/* =========================================================
   ADD EXTRA MEDICINE
========================================================= */
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['add_medicine'])) {
    $med_id = (int)$_POST['med_id'];
    $qty    = max(1,(int)($_POST['qty'] ?? 1));
    if ($med_id > 0) {
        $stmt = $conn->prepare("SELECT med_id,med_name,dosage,unit_price FROM pharmacy_inventory WHERE med_id=? LIMIT 1");
        $stmt->bind_param("i",$med_id);
        $stmt->execute();
        $med = $stmt->get_result()->fetch_assoc();
        if ($med) {
            $_SESSION['billing_cart'][$patient_id][] = [
                'cart_key'    => 'XMED-'.$med_id.'-'.time(),
                'ref_id'      => $med_id,
                'med_id'      => $med['med_id'],
                'serviceName' => $med['med_name'].(!empty($med['dosage'])?' ('.$med['dosage'].')':''),
                'description' => 'Added Medicine — Qty: '.$qty.' × ₱'.number_format($med['unit_price'],2),
                'price'       => round($med['unit_price']*$qty,2),
                'source'      => 'add_med',
                'category'    => 'medicine',
            ];
        }
    }
    header("Location: billing_items.php?patient_id=$patient_id"); exit;
}

/* =========================================================
   DELETE — only add_svc / add_med / add_lab
========================================================= */
if (isset($_GET['delete'])) {
    $idx = (int)$_GET['delete'];
    if (isset($_SESSION['billing_cart'][$patient_id][$idx])
        && in_array($_SESSION['billing_cart'][$patient_id][$idx]['source'],['add_svc','add_med','add_lab'])) {
        unset($_SESSION['billing_cart'][$patient_id][$idx]);
        $_SESSION['billing_cart'][$patient_id] = array_values($_SESSION['billing_cart'][$patient_id]);
    }
    header("Location: billing_items.php?patient_id=$patient_id"); exit;
}

/* RESET CART */
if (isset($_GET['reset_cart'])) {
    unset($_SESSION['billing_cart'][$patient_id]);
    header("Location: billing_items.php?patient_id=$patient_id"); exit;
}

/* =========================================================
   FINALIZE
========================================================= */
if (isset($_GET['finalize']) && $_GET['finalize']==1) {
    $cart_f     = $_SESSION['billing_cart'][$patient_id];
    $subtotal_f = array_sum(array_column($cart_f,'price'));
    $discount_f = ($is_pwd||$is_senior) ? round($subtotal_f*0.20,2) : 0.00;
    $grand_f    = round($subtotal_f - $discount_f, 2);

    /* Block finalize if grand total is 0 */
    if ($grand_f <= 0) {
        header("Location: billing_items.php?patient_id=$patient_id&err=zero_total"); exit;
    }

    $txn = 'TXN-'.strtoupper(uniqid());

    if ($existing_bill) {
        $billing_id = $existing_bill['billing_id'];
        $s = $conn->prepare("
            UPDATE billing_records
            SET total_amount=?, grand_total=?, status='Pending', transaction_id=?
            WHERE billing_id=?
        ");
        $s->bind_param("ddsi", $subtotal_f, $grand_f, $txn, $billing_id);
        $s->execute();
        $d = $conn->prepare("DELETE FROM billing_items WHERE billing_id=?");
        $d->bind_param("i", $billing_id);
        $d->execute();
    } else {
        $s = $conn->prepare("
            INSERT INTO billing_records
                (patient_id, billing_date, total_amount, grand_total, status, transaction_id)
            VALUES (?, NOW(), ?, ?, 'Pending', ?)
        ");
        $s->bind_param("idds", $patient_id, $subtotal_f, $grand_f, $txn);
        $s->execute();
        $billing_id = $conn->insert_id;
    }

    foreach ($cart_f as $item) {
        $s = $conn->prepare("
            INSERT INTO billing_items (billing_id, patient_id, quantity, unit_price, total_price, finalized)
            VALUES (?, ?, 1, ?, ?, 1)
        ");
        $s->bind_param("iidd", $billing_id, $patient_id, $item['price'], $item['price']);
        $s->execute();
    }

    $pwd_flag = ($is_pwd || $is_senior) ? 1 : 0;
    $chk = $conn->prepare("SELECT receipt_id FROM patient_receipt WHERE billing_id=? LIMIT 1");
    $chk->bind_param("i", $billing_id);
    $chk->execute();
    $existing_receipt = $chk->get_result()->fetch_assoc();

    if ($existing_receipt) {
        $r = $conn->prepare("
            UPDATE patient_receipt
            SET total_charges=?, total_discount=?, grand_total=?,
                total_out_of_pocket=?, status='Pending', transaction_id=?, is_pwd=?
            WHERE billing_id=?
        ");
        $r->bind_param("ddddsii", $subtotal_f, $discount_f, $grand_f, $grand_f, $txn, $pwd_flag, $billing_id);
        $r->execute();
    } else {
        $r = $conn->prepare("
            INSERT INTO patient_receipt
                (patient_id, billing_id, total_charges, total_vat, total_discount,
                 total_out_of_pocket, grand_total, status, transaction_id, is_pwd)
            VALUES (?, ?, ?, 0, ?, ?, ?, 'Pending', ?, ?)
        ");
        $r->bind_param("iiddddsi", $patient_id, $billing_id, $subtotal_f, $discount_f, $grand_f, $grand_f, $txn, $pwd_flag);
        $r->execute();
    }

    $u = $conn->prepare("
        UPDATE pharmacy_prescription
        SET billing_status='billed'
        WHERE patient_id=? AND payment_type='post_discharged' AND billing_status='pending'
    ");
    $u->bind_param("i", $patient_id);
    $u->execute();

    unset($_SESSION['billing_cart'][$patient_id]);
    header("Location: patient_billing.php?patient_id=$patient_id"); exit;
}

/* =========================================================
   CART TOTALS & GROUPING
========================================================= */
$cart        = $_SESSION['billing_cart'][$patient_id];
$subtotal    = array_sum(array_column($cart,'price'));
$discount    = ($is_pwd||$is_senior) ? $subtotal*0.20 : 0;
$grand_total = $subtotal - $discount;

$cat_services   = array_values(array_filter($cart, fn($c)=>$c['category']==='service'));
$cat_laboratory = array_values(array_filter($cart, fn($c)=>$c['category']==='laboratory'));
$cat_medicines  = array_values(array_filter($cart, fn($c)=>$c['category']==='medicine'));

$svc_total = array_sum(array_column($cat_services,  'price'));
$lab_total = array_sum(array_column($cat_laboratory,'price'));
$med_total = array_sum(array_column($cat_medicines, 'price'));

/* =========================================================
   DROPDOWNS
========================================================= */
$lab_svc_res = $conn->query("SELECT serviceID,serviceName,price FROM dl_services ORDER BY serviceName");
$lab_services = [];
while ($ls = $lab_svc_res->fetch_assoc()) $lab_services[] = $ls;

$proc_res   = $conn->query("SELECT procedure_id,procedure_name,price FROM dnm_procedure_list WHERE status='Active' ORDER BY procedure_name");
$procedures = [];
while ($p = $proc_res->fetch_assoc()) $procedures[] = $p;

$seeded_ids = array_unique(array_filter(array_column($cart,'med_id')));
if ($seeded_ids) {
    $ph   = implode(',',array_fill(0,count($seeded_ids),'?'));
    $stmt = $conn->prepare("SELECT med_id,med_name,dosage,unit_price FROM pharmacy_inventory WHERE med_id NOT IN ($ph) ORDER BY med_name");
    $stmt->bind_param(str_repeat('i',count($seeded_ids)),...$seeded_ids);
    $stmt->execute();
    $med_res = $stmt->get_result();
} else {
    $med_res = $conn->query("SELECT med_id,med_name,dosage,unit_price FROM pharmacy_inventory ORDER BY med_name");
}
$medicines = [];
while ($m = $med_res->fetch_assoc()) $medicines[] = $m;

$full_name  = trim($patient['fname'].' '.($patient['mname']??'').' '.$patient['lname']);
$gender     = ucfirst($patient['gender'] ?? '—');
$contact    = $patient['contact_no'] ?? $patient['phone'] ?? '—';
$address    = $patient['address'] ?? '—';
$patient_no = $patient['patient_no'] ?? $patient['patient_id'];

/* ── Is the bill finalizable? ── */
$can_finalize = !empty($cart) && $grand_total > 0;
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
:root{
    --sidebar-w:250px;--navy:#0b1d3a;--accent:#2563eb;
    --success:#059669;--danger:#dc2626;
    --ink:#1e293b;--ink-light:#64748b;--border:#e2e8f0;
    --surface:#f1f5f9;--card:#fff;--radius:14px;
    --shadow:0 2px 20px rgba(11,29,58,.08);
    --ff-head:'DM Serif Display',serif;--ff-body:'DM Sans',sans-serif;
    --c-lab:#065f46;--bg-lab:#d1fae5;
    --c-svc:#7e22ce;--bg-svc:#fdf4ff;
    --c-rx:#1d4ed8;--bg-rx:#dbeafe;
}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
body{font-family:var(--ff-body);background:var(--surface);color:var(--ink);}
.cw{margin-left:var(--sidebar-w);padding:44px 28px 80px;transition:margin-left .3s;}
.cw.sidebar-collapsed{margin-left:0;}

.page-head{display:flex;align-items:center;gap:14px;margin-bottom:20px;}
.head-icon{width:50px;height:50px;background:linear-gradient(135deg,var(--navy),var(--accent));border-radius:13px;display:flex;align-items:center;justify-content:center;color:#fff;font-size:1.3rem;box-shadow:0 6px 18px rgba(11,29,58,.2);flex-shrink:0;}
.page-head h2{font-family:var(--ff-head);font-size:clamp(1.2rem,2.5vw,1.7rem);color:var(--navy);margin:0;}
.page-head p{font-size:.82rem;color:var(--ink-light);margin-top:3px;}

.pat-card{background:var(--card);border:1px solid var(--border);border-radius:var(--radius);box-shadow:var(--shadow);overflow:hidden;margin-bottom:16px;}
.pat-head{background:linear-gradient(135deg,var(--navy),#1e3a6e);padding:16px 22px;display:flex;align-items:center;gap:14px;}
.pat-av{width:54px;height:54px;border-radius:50%;background:rgba(255,255,255,.18);border:2.5px solid rgba(255,255,255,.3);display:flex;align-items:center;justify-content:center;font-family:var(--ff-head);font-size:1.35rem;color:#fff;flex-shrink:0;}
.pat-fullname{font-family:var(--ff-head);font-size:1.1rem;color:#fff;}
.pat-pid{font-size:.74rem;color:rgba(255,255,255,.6);margin-top:3px;}
.pat-chips{margin-left:auto;display:flex;gap:6px;flex-wrap:wrap;}
.chip{display:inline-flex;align-items:center;gap:4px;border-radius:999px;padding:4px 12px;font-size:.71rem;font-weight:700;white-space:nowrap;}
.chip-sc{background:#dbeafe;color:#1d4ed8;}
.chip-pwd{background:#d1fae5;color:#065f46;}
.chip-g{background:rgba(255,255,255,.15);color:#fff;}
.pat-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));}
.pat-gi{padding:12px 22px;border-right:1px solid var(--border);border-bottom:1px solid var(--border);}
.pat-gi:last-child{border-right:none;}
.gi-lbl{font-size:.67rem;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--ink-light);display:flex;align-items:center;gap:4px;margin-bottom:3px;}
.gi-val{font-size:.9rem;font-weight:600;color:var(--navy);}

.alert-ok{background:#f0fdf4;border:1.5px solid #86efac;border-radius:10px;padding:12px 18px;display:flex;align-items:center;gap:10px;font-weight:600;color:var(--success);margin-bottom:16px;}
.alert-zero{background:#fff7ed;border:1.5px solid #fed7aa;border-radius:10px;padding:12px 18px;display:flex;align-items:center;gap:10px;font-weight:600;color:#c2410c;margin-bottom:16px;}

.bill-banner{display:flex;align-items:center;gap:10px;background:#fffbeb;border:1.5px solid #fde68a;border-radius:10px;padding:12px 18px;margin-bottom:16px;font-size:.87rem;font-weight:600;color:#92400e;flex-wrap:wrap;}
.bb-acts{margin-left:auto;display:flex;gap:7px;}
.bb-acts a{display:inline-flex;align-items:center;gap:4px;background:#fff;color:#92400e;border:1.5px solid #fde68a;border-radius:7px;padding:4px 12px;font-size:.77rem;font-weight:700;text-decoration:none;}
.bb-acts a:hover{background:#fef3c7;}

.disc-bar{display:flex;align-items:center;gap:10px;padding:11px 16px;background:#f0fdf4;border:1.5px solid #bbf7d0;border-radius:10px;margin-bottom:16px;font-size:.87rem;color:#065f46;font-weight:600;}
.disc-bar.sc{background:#eff6ff;border-color:#bfdbfe;color:#1d4ed8;}
.disc-bar input{width:17px;height:17px;accent-color:var(--success);cursor:pointer;flex-shrink:0;}
.d-badge{margin-left:auto;border-radius:999px;padding:3px 11px;font-size:.71rem;font-weight:700;color:#fff;background:#059669;}

.billing-layout{display:grid;grid-template-columns:400px 1fr;gap:22px;align-items:start;}

.bcard{background:var(--card);border:1px solid var(--border);border-radius:var(--radius);box-shadow:var(--shadow);overflow:hidden;margin-bottom:16px;}
.bcard-head{padding:11px 20px;display:flex;align-items:center;gap:8px;font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.7px;color:rgba(255,255,255,.88);}
.h-bill{background:linear-gradient(90deg,var(--navy),#1e40af);}
.h-svc{background:#5b21b6;}
.h-med{background:var(--navy);}
.h-lab{background:#047857;}
.bcard-body{padding:18px;}

.add-card{background:var(--card);border:1px solid var(--border);border-radius:var(--radius);box-shadow:var(--shadow);overflow:hidden;margin-bottom:14px;}
.add-card:last-of-type{margin-bottom:0;}
.add-card-header{display:flex;align-items:center;gap:12px;padding:13px 18px;border-bottom:1px solid var(--border);}
.add-card-icon{width:36px;height:36px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:.95rem;flex-shrink:0;}
.add-card-lab .add-card-icon{background:#d1fae5;color:#047857;}
.add-card-svc .add-card-icon{background:#ede9fe;color:#6d28d9;}
.add-card-med .add-card-icon{background:#dbeafe;color:#1d4ed8;}
.add-card-lab .add-card-header{background:#f0fdf8;}
.add-card-svc .add-card-header{background:#faf5ff;}
.add-card-med .add-card-header{background:#eff6ff;}
.add-card-title{font-weight:700;font-size:.88rem;color:var(--navy);line-height:1.2;}
.add-card-sub{font-size:.69rem;color:var(--ink-light);margin-top:1px;}
.add-card-body{padding:14px 18px;}

.af-field{margin-bottom:10px;}
.af-label{font-size:.67rem;font-weight:700;text-transform:uppercase;letter-spacing:.55px;color:var(--ink-light);display:block;margin-bottom:5px;}
.af-select{width:100%;padding:9px 12px;border:1.5px solid var(--border);border-radius:9px;font-family:var(--ff-body);font-size:.85rem;color:var(--ink);background:var(--card);outline:none;appearance:none;background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%2364748b' d='M6 8L1 3h10z'/%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right 12px center;padding-right:32px;transition:border-color .2s,box-shadow .2s;cursor:pointer;}
.af-select:focus{border-color:var(--accent);box-shadow:0 0 0 3px rgba(37,99,235,.1);}
.add-card-lab .af-select:focus{border-color:#059669;box-shadow:0 0 0 3px rgba(5,150,105,.1);}
.add-card-svc .af-select:focus{border-color:#7c3aed;box-shadow:0 0 0 3px rgba(124,58,237,.1);}

.af-btn{display:flex;width:100%;justify-content:center;align-items:center;gap:6px;padding:10px 16px;border:none;border-radius:9px;font-family:var(--ff-body);font-size:.87rem;font-weight:700;cursor:pointer;transition:all .15s;letter-spacing:.2px;}
.af-btn-lab{background:#059669;color:#fff;box-shadow:0 3px 10px rgba(5,150,105,.25);}
.af-btn-lab:hover{background:#047857;transform:translateY(-1px);}
.af-btn-svc{background:#7c3aed;color:#fff;box-shadow:0 3px 10px rgba(124,58,237,.25);}
.af-btn-svc:hover{background:#6d28d9;transform:translateY(-1px);}
.af-btn-med{background:var(--accent);color:#fff;box-shadow:0 3px 10px rgba(37,99,235,.25);}
.af-btn-med:hover{background:#1d4ed8;transform:translateY(-1px);}

.bill-footer{border-top:2px solid var(--border);background:#fafbfc;}
.bf-totals-block{padding:16px 24px;border-bottom:1px solid var(--border);display:flex;flex-direction:column;gap:6px;}
.bf-line{display:flex;justify-content:space-between;align-items:center;}
.bf-lbl{font-size:.88rem;color:var(--ink-light);font-weight:500;}
.bf-amount{font-size:.95rem;font-weight:700;color:var(--ink);}
.bf-disc-line .bf-lbl{color:var(--danger);font-size:.84rem;}
.bf-disc-line .bf-amount{color:var(--danger);font-weight:700;}
.bf-divider{border:none;border-top:1.5px dashed var(--border);margin:4px 0;}
.bf-grand-line{margin-top:2px;}
.bf-grand-lbl{font-size:1.05rem;font-weight:800;color:var(--navy);}
.bf-grand-amt{font-size:1.25rem;font-weight:800;color:var(--success);}
.bf-grand-amt.zero{color:var(--danger);}

.bf-actions{display:grid;grid-template-columns:1fr auto 1fr;align-items:center;padding:14px 20px;gap:12px;}
.bf-act-left{display:flex;justify-content:flex-start;}
.bf-act-center{display:flex;justify-content:center;}
.bf-act-right{display:flex;justify-content:flex-end;gap:8px;}
.bf-empty-note{color:var(--ink-light);font-size:.82rem;font-style:italic;}

.bf-btn-finalize{display:inline-flex;align-items:center;gap:8px;padding:11px 28px;background:var(--success);color:#fff;border:none;border-radius:10px;font-family:var(--ff-head);font-size:.95rem;font-weight:700;cursor:pointer;box-shadow:0 4px 16px rgba(5,150,105,.3);transition:all .15s;white-space:nowrap;}
.bf-btn-finalize:hover{background:#047857;transform:translateY(-1px);box-shadow:0 6px 20px rgba(5,150,105,.35);}
.bf-btn-finalize-disabled{display:inline-flex;align-items:center;gap:8px;padding:11px 28px;background:#e2e8f0;color:#94a3b8;border:none;border-radius:10px;font-family:var(--ff-head);font-size:.95rem;font-weight:700;cursor:not-allowed;white-space:nowrap;box-shadow:none;}

.bf-btn-back{display:inline-flex;align-items:center;gap:5px;padding:8px 16px;background:#fff;color:var(--ink-light);border:1.5px solid var(--border);border-radius:8px;font-family:var(--ff-body);font-size:.84rem;font-weight:600;text-decoration:none;transition:all .15s;}
.bf-btn-back:hover{border-color:var(--accent);color:var(--accent);background:#eff6ff;}
.bf-btn-reload{display:inline-flex;align-items:center;gap:5px;padding:8px 14px;background:#fff;color:var(--ink-light);border:1.5px solid var(--border);border-radius:8px;font-family:var(--ff-body);font-size:.84rem;font-weight:600;text-decoration:none;transition:all .15s;}
.bf-btn-reload:hover{border-color:var(--danger);color:var(--danger);}
.bf-btn-view{display:inline-flex;align-items:center;gap:5px;padding:8px 14px;background:#fffbeb;color:#92400e;border:1.5px solid #fde68a;border-radius:8px;font-family:var(--ff-body);font-size:.84rem;font-weight:600;text-decoration:none;transition:all .15s;}
.bf-btn-view:hover{background:#fef3c7;}

@media(max-width:640px){.bf-actions{grid-template-columns:1fr 1fr;}.bf-act-center{grid-column:1/-1;order:-1;}.bf-btn-finalize,.bf-btn-finalize-disabled{width:100%;justify-content:center;}}
.af-qty-row{display:flex;align-items:flex-end;gap:10px;}
.qty-stepper{display:flex;align-items:center;border:1.5px solid var(--border);border-radius:9px;overflow:hidden;height:38px;}
.qty-btn{width:36px;height:100%;background:#f8fafc;border:none;color:var(--ink);font-size:1.1rem;font-weight:700;cursor:pointer;flex-shrink:0;transition:background .12s;}
.qty-btn:hover{background:#e2e8f0;}
.qty-input{width:54px;height:100%;border:none;border-left:1.5px solid var(--border);border-right:1.5px solid var(--border);text-align:center;font-family:var(--ff-body);font-size:.9rem;font-weight:700;color:var(--navy);outline:none;}
.qty-input::-webkit-inner-spin-button,.qty-input::-webkit-outer-spin-button{-webkit-appearance:none;}

.af-empty{color:var(--ink-light);font-size:.84rem;font-style:italic;text-align:center;padding:10px 0;}

/* ── Item price badge (highlights ₱0.00 items) ── */
.price-zero{color:var(--danger) !important;font-weight:700;}
.zero-badge{display:inline-block;margin-left:4px;background:#fee2e2;color:#b91c1c;border-radius:999px;padding:1px 7px;font-size:.6rem;font-weight:700;vertical-align:middle;}

.itbl{width:100%;border-collapse:collapse;font-size:.85rem;}
.itbl thead th{background:#f8fafc;color:var(--ink-light);font-size:.65rem;font-weight:700;text-transform:uppercase;letter-spacing:.6px;padding:9px 14px;border-bottom:2px solid var(--border);text-align:left;white-space:nowrap;}
.itbl thead th.r{text-align:right;}.itbl thead th.c{text-align:center;}
.itbl tbody tr{border-bottom:1px solid var(--border);transition:background .12s;}
.itbl tbody tr:last-child{border-bottom:none;}
.itbl tbody tr:hover:not(.sec-row){background:#f7faff;}
.itbl td{padding:10px 14px;vertical-align:middle;}
.itbl td.r{text-align:right;font-weight:600;color:var(--success);}
.itbl td.c{text-align:center;}
.sec-row td{padding:7px 14px;font-size:.65rem;font-weight:700;text-transform:uppercase;letter-spacing:.8px;border-top:2px solid var(--border);border-bottom:1px solid var(--border);}
.sec-row.s-svc td{color:var(--c-svc);background:#fdf4ff;}
.sec-row.s-lab td{color:var(--c-lab);background:#f0fdf9;}
.sec-row.s-med td{color:var(--c-rx);background:#eff6ff;}
.sec-total{float:right;font-weight:800;}
.r-lab{border-left:3px solid #6ee7b7;}
.r-dnm{border-left:3px solid #c4b5fd;}
.r-xsvc{border-left:3px solid #fb923c;}
.r-rx{border-left:3px solid #93c5fd;}
.r-xmed{border-left:3px solid #fda4af;}
.r-xlab{border-left:3px solid #34d399;}
.item-name{font-weight:600;color:var(--navy);font-size:.87rem;}
.item-desc{font-size:.72rem;color:var(--ink-light);margin-top:2px;}
.tag{display:inline-flex;align-items:center;gap:3px;padding:2px 7px;border-radius:999px;font-size:.6rem;font-weight:700;margin-right:3px;white-space:nowrap;}
.tag-lab{background:var(--bg-lab);color:var(--c-lab);}
.tag-dnm{background:var(--bg-svc);color:var(--c-svc);}
.tag-xsvc{background:#ffedd5;color:#c2410c;}
.tag-rx{background:var(--bg-rx);color:var(--c-rx);}
.tag-xmed{background:#fce7f3;color:#9d174d;}
.tag-xlab{background:#d1fae5;color:#065f46;}
.btn-del{background:#fff1f2;color:var(--danger);border:1.5px solid #fecdd3;border-radius:7px;padding:3px 9px;font-size:.72rem;font-weight:700;font-family:var(--ff-body);cursor:pointer;display:inline-flex;align-items:center;gap:3px;text-decoration:none;transition:all .15s;}
.btn-del:hover{background:var(--danger);color:#fff;border-color:var(--danger);}
.lock-i{color:var(--ink-light);font-size:.82rem;}
.empty-row td{text-align:center;padding:28px;color:var(--ink-light);font-style:italic;font-size:.84rem;}

.item-count{background:rgba(255,255,255,.2);border-radius:999px;padding:2px 9px;font-size:.68rem;margin-left:auto;}

@media(max-width:1100px){.billing-layout{grid-template-columns:360px 1fr;}}
@media(max-width:900px){.billing-layout{grid-template-columns:1fr;}.af-qty-row{flex-direction:column;}.af-btn{width:100%;}}
@media(max-width:768px){.cw{margin-left:0;padding:52px 14px 60px;}.pat-chips{display:none;}}
@media(max-width:480px){.cw{padding:48px 10px 60px;}.pat-grid{grid-template-columns:1fr 1fr;}}
</style>
</head>
<body>
<?php include 'billing_sidebar.php'; ?>
<div class="cw" id="mainCw">

<?php if (isset($_GET['success'])): ?>
<div class="alert-ok"><i class="bi bi-check-circle-fill" style="font-size:1.3rem;"></i> Billing finalized successfully!</div>
<?php endif; ?>

<?php if (isset($_GET['err']) && $_GET['err'] === 'zero_total'): ?>
<div class="alert-zero"><i class="bi bi-exclamation-triangle-fill" style="font-size:1.3rem;"></i> Cannot finalize — the bill total is ₱0.00. Please add at least one billable item with a price.</div>
<?php endif; ?>

<div class="page-head">
    <div class="head-icon"><i class="bi bi-receipt-cutoff"></i></div>
    <div><h2>Patient Bill</h2><p>Lab Results, Procedures &amp; Medicines</p></div>
</div>

<!-- Patient Info -->
<div class="pat-card">
    <div class="pat-head">
        <div class="pat-av"><?= strtoupper(substr(trim($patient['fname']),0,1)) ?></div>
        <div>
            <div class="pat-fullname"><?= htmlspecialchars($full_name) ?></div>
            <div class="pat-pid">Patient ID #<?= htmlspecialchars($patient_no) ?></div>
        </div>
        <div class="pat-chips">
            <?php if ($is_senior): ?><span class="chip chip-sc"><i class="bi bi-person-check-fill"></i> Senior</span><?php endif; ?>
            <?php if ($is_pwd):    ?><span class="chip chip-pwd"><i class="bi bi-accessibility"></i> PWD</span><?php endif; ?>
            <span class="chip chip-g"><i class="bi bi-<?= strtolower($gender)==='female'?'gender-female':'gender-male' ?>"></i> <?= htmlspecialchars($gender) ?></span>
        </div>
    </div>
    <div class="pat-grid">
        <div class="pat-gi"><div class="gi-lbl"><i class="bi bi-cake2"></i> Date of Birth</div><div class="gi-val"><?= htmlspecialchars($dob_display) ?></div></div>
        <div class="pat-gi"><div class="gi-lbl"><i class="bi bi-hourglass-split"></i> Age</div><div class="gi-val"><?= $age>0?$age.' yrs old':'—' ?></div></div>
        <div class="pat-gi"><div class="gi-lbl"><i class="bi bi-gender-ambiguous"></i> Gender</div><div class="gi-val"><?= htmlspecialchars($gender) ?></div></div>
        <div class="pat-gi"><div class="gi-lbl"><i class="bi bi-telephone"></i> Contact</div><div class="gi-val"><?= htmlspecialchars($contact) ?></div></div>
        <div class="pat-gi"><div class="gi-lbl"><i class="bi bi-geo-alt"></i> Address</div><div class="gi-val"><?= htmlspecialchars($address) ?></div></div>
    </div>
</div>

<?php if ($existing_bill): ?>
<div class="bill-banner">
    <i class="bi bi-exclamation-triangle-fill"></i>
    Existing <strong><?=$existing_bill['status']?></strong> bill (ID #<?=$existing_bill['billing_id']?> — ₱<?=number_format($existing_bill['grand_total'],2)?>). Finalizing will <strong>update</strong> it.
    <div class="bb-acts">
        <a href="patient_billing.php?patient_id=<?=$patient_id?>"><i class="bi bi-eye"></i> View</a>
        <a href="billing_items.php?patient_id=<?=$patient_id?>&reset_cart=1"><i class="bi bi-arrow-clockwise"></i> Reload</a>
    </div>
</div>
<?php endif; ?>

<?php if ($is_senior): ?>
<div class="disc-bar sc"><i class="bi bi-person-check-fill" style="font-size:1.1rem;"></i> Senior Citizen (60+) — 20% discount applied automatically<span class="d-badge" style="background:#1d4ed8;">SC Discount</span></div>
<?php else: ?>
<div class="disc-bar">
    <input type="checkbox" id="pwdChk" <?=$is_pwd?'checked':''?> onchange="window.location='billing_items.php?patient_id=<?=$patient_id?>&toggle_pwd='+(this.checked?1:0)">
    <label for="pwdChk" style="cursor:pointer;">Mark as PWD (applies 20% discount)</label>
    <?php if ($is_pwd): ?><span class="d-badge">PWD Active</span><?php endif; ?>
</div>
<?php endif; ?>

<div class="billing-layout">

    <!-- LEFT COLUMN -->
    <div class="billing-left">

        <!-- ADD LABORATORY SERVICE -->
        <div class="add-card add-card-lab">
            <div class="add-card-header">
                <div class="add-card-icon"><i class="bi bi-eyedropper-fill"></i></div>
                <div>
                    <div class="add-card-title">Add Laboratory Service</div>
                    <div class="add-card-sub">From dl_services catalog</div>
                </div>
            </div>
            <div class="add-card-body">
                <?php if ($lab_services): ?>
                <form method="POST">
                    <div class="af-field">
                        <label class="af-label">Select Lab Service</label>
                        <select name="lab_service_id" class="af-select" required>
                            <option value="">— Choose a service —</option>
                            <?php foreach ($lab_services as $ls): ?>
                            <option value="<?=$ls['serviceID']?>">
                                <?=htmlspecialchars($ls['serviceName'])?> — ₱<?=number_format($ls['price'],2)?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" name="add_lab" class="af-btn af-btn-lab">
                        <i class="bi bi-plus-circle-fill"></i> Add to Bill
                    </button>
                </form>
                <?php else: ?>
                <div class="af-empty"><i class="bi bi-info-circle"></i> No lab services found.</div>
                <?php endif; ?>
            </div>
        </div>

        <!-- ADD EXTRA PROCEDURE -->
        <div class="add-card add-card-svc">
            <div class="add-card-header">
                <div class="add-card-icon"><i class="bi bi-clipboard2-plus-fill"></i></div>
                <div>
                    <div class="add-card-title">Add Procedure / Service</div>
                    <div class="add-card-sub">From active procedure list</div>
                </div>
            </div>
            <div class="add-card-body">
                <?php if ($procedures): ?>
                <form method="POST">
                    <div class="af-field">
                        <label class="af-label">Select Procedure</label>
                        <select name="procedure_id" class="af-select" required>
                            <option value="">— Choose a procedure —</option>
                            <?php foreach ($procedures as $p): ?>
                            <option value="<?=$p['procedure_id']?>">
                                <?=htmlspecialchars($p['procedure_name'])?> — ₱<?=number_format($p['price'],2)?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" name="add_service" class="af-btn af-btn-svc">
                        <i class="bi bi-plus-circle-fill"></i> Add to Bill
                    </button>
                </form>
                <?php else: ?>
                <div class="af-empty"><i class="bi bi-info-circle"></i> No active procedures found.</div>
                <?php endif; ?>
            </div>
        </div>

        <!-- ADD EXTRA MEDICINE -->
        <div class="add-card add-card-med">
            <div class="add-card-header">
                <div class="add-card-icon"><i class="bi bi-capsule-fill"></i></div>
                <div>
                    <div class="add-card-title">Add Medicine</div>
                    <div class="add-card-sub">From pharmacy inventory</div>
                </div>
            </div>
            <div class="add-card-body">
                <?php if ($medicines): ?>
                <form method="POST">
                    <div class="af-field">
                        <label class="af-label">Select Medicine</label>
                        <select name="med_id" class="af-select" required>
                            <option value="">— Choose a medicine —</option>
                            <?php foreach ($medicines as $m): ?>
                            <option value="<?=$m['med_id']?>">
                                <?=htmlspecialchars($m['med_name'])?><?=!empty($m['dosage'])?' ('.$m['dosage'].')':''?> — ₱<?=number_format($m['unit_price'],2)?>/unit
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="af-qty-row">
                        <div class="af-field" style="flex:1;">
                            <label class="af-label">Quantity</label>
                            <div class="qty-stepper">
                                <button type="button" class="qty-btn" onclick="stepQty(this,-1)">−</button>
                                <input type="number" name="qty" value="1" min="1" max="999" class="qty-input" id="medQtyInput">
                                <button type="button" class="qty-btn" onclick="stepQty(this,1)">+</button>
                            </div>
                        </div>
                        <button type="submit" name="add_medicine" class="af-btn af-btn-med" style="align-self:flex-end;">
                            <i class="bi bi-plus-circle-fill"></i> Add to Bill
                        </button>
                    </div>
                </form>
                <?php else: ?>
                <div class="af-empty"><i class="bi bi-info-circle"></i> No medicines available.</div>
                <?php endif; ?>
            </div>
        </div>

    </div><!-- /.billing-left -->


    <!-- RIGHT COLUMN — Bill Table -->
    <div class="billing-right">
        <div class="bcard">
            <div class="bcard-head h-bill">
                <i class="bi bi-clipboard2-check-fill"></i> Patient Bill
                <span class="item-count"><?=count($cart)?> item<?=count($cart)!==1?'s':''?></span>
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
                <?php if (empty($cart)): ?>
                <tr><td colspan="4" class="empty-row"><i class="bi bi-inbox" style="font-size:1.4rem;display:block;margin-bottom:6px;opacity:.3;"></i>No billing items found for this patient.</td></tr>
                <?php endif; ?>

                <!-- SERVICES (DNM + added) -->
                <?php if (!empty($cat_services)): ?>
                <tr class="sec-row s-svc">
                    <td colspan="4">
                        <i class="bi bi-clipboard2-pulse-fill"></i> Doctor / Nurse Procedures
                        <span class="sec-total">₱<?=number_format($svc_total,2)?></span>
                    </td>
                </tr>
                <?php foreach ($cart as $idx => $item):
                    if ($item['category']!=='service') continue;
                    $is_x = $item['source']==='add_svc'; ?>
                <tr class="<?=$is_x?'r-xsvc':'r-dnm'?>">
                    <td>
                        <span class="tag <?=$is_x?'tag-xsvc':'tag-dnm'?>">
                            <i class="bi <?=$is_x?'bi-plus-circle':'bi-clipboard2-pulse'?>"></i>
                            <?=$is_x?'Added':'Procedure'?>
                        </span>
                        <div class="item-name"><?=htmlspecialchars($item['serviceName'])?></div>
                    </td>
                    <td><div class="item-desc"><?=htmlspecialchars($item['description'])?></div></td>
                    <td class="r <?=$item['price']==0?'price-zero':''?>">
                        ₱<?=number_format($item['price'],2)?>
                        <?php if($item['price']==0): ?><span class="zero-badge">No Price</span><?php endif; ?>
                    </td>
                    <td class="c">
                        <?php if ($is_x): ?>
                        <a href="billing_items.php?patient_id=<?=$patient_id?>&delete=<?=$idx?>" class="btn-del" onclick="return confirm('Remove this item?')"><i class="bi bi-trash3"></i> Remove</a>
                        <?php else: ?><i class="bi bi-lock-fill lock-i" title="Auto-loaded"></i><?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; endif; ?>

                <!-- LAB RESULTS -->
                <?php if (!empty($cat_laboratory)): ?>
                <tr class="sec-row s-lab">
                    <td colspan="4">
                        <i class="bi bi-eyedropper-fill"></i> Laboratory Results
                        <span class="sec-total">₱<?=number_format($lab_total,2)?></span>
                    </td>
                </tr>
                <?php foreach ($cart as $idx => $item):
                    if ($item['category']!=='laboratory') continue;
                    $is_x = $item['source']==='add_lab'; ?>
                <tr class="<?=$is_x?'r-xlab':'r-lab'?>">
                    <td>
                        <span class="tag <?=$is_x?'tag-xlab':'tag-lab'?>">
                            <i class="bi <?=$is_x?'bi-plus-circle':'bi-eyedropper'?>"></i>
                            <?=$is_x?'Added':'Lab'?>
                        </span>
                        <div class="item-name"><?=htmlspecialchars($item['serviceName'])?></div>
                    </td>
                    <td><div class="item-desc"><?=htmlspecialchars($item['description'])?></div></td>
                    <td class="r <?=$item['price']==0?'price-zero':''?>">
                        ₱<?=number_format($item['price'],2)?>
                        <?php if($item['price']==0): ?><span class="zero-badge">No Price</span><?php endif; ?>
                    </td>
                    <td class="c">
                        <?php if ($is_x): ?>
                        <a href="billing_items.php?patient_id=<?=$patient_id?>&delete=<?=$idx?>" class="btn-del" onclick="return confirm('Remove this item?')"><i class="bi bi-trash3"></i> Remove</a>
                        <?php else: ?><i class="bi bi-lock-fill lock-i" title="Auto-loaded"></i><?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; endif; ?>

                <!-- MEDICINES -->
                <?php if (!empty($cat_medicines)): ?>
                <tr class="sec-row s-med">
                    <td colspan="4">
                        <i class="bi bi-capsule-pill"></i> Medicines
                        <span class="sec-total">₱<?=number_format($med_total,2)?></span>
                    </td>
                </tr>
                <?php foreach ($cart as $idx => $item):
                    if ($item['category']!=='medicine') continue;
                    $is_x = $item['source']==='add_med'; ?>
                <tr class="<?=$is_x?'r-xmed':'r-rx'?>">
                    <td>
                        <span class="tag <?=$is_x?'tag-xmed':'tag-rx'?>">
                            <i class="bi <?=$is_x?'bi-plus-circle':'bi-capsule'?>"></i>
                            <?=$is_x?'Added':'Dispensed'?>
                        </span>
                        <div class="item-name"><?=htmlspecialchars($item['serviceName'])?></div>
                    </td>
                    <td><div class="item-desc"><?=htmlspecialchars($item['description'])?></div></td>
                    <td class="r <?=$item['price']==0?'price-zero':''?>">
                        ₱<?=number_format($item['price'],2)?>
                        <?php if($item['price']==0): ?><span class="zero-badge">No Price</span><?php endif; ?>
                    </td>
                    <td class="c">
                        <?php if ($is_x): ?>
                        <a href="billing_items.php?patient_id=<?=$patient_id?>&delete=<?=$idx?>" class="btn-del" onclick="return confirm('Remove this item?')"><i class="bi bi-trash3"></i> Remove</a>
                        <?php else: ?><i class="bi bi-lock-fill lock-i" title="Dispensed — locked"></i><?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; endif; ?>

                </tbody>
            </table>
            </div>

            <!-- ── BILL FOOTER ── -->
            <div class="bill-footer">

                <div class="bf-totals-block">
                    <div class="bf-line">
                        <span class="bf-lbl">Subtotal</span>
                        <span class="bf-amount">₱<?=number_format($subtotal,2)?></span>
                    </div>
                    <?php if ($discount > 0): ?>
                    <div class="bf-line bf-disc-line">
                        <span class="bf-lbl"><i class="bi bi-tag-fill"></i> <?=$is_senior?'Senior Citizen':'PWD'?> Discount (20%)</span>
                        <span class="bf-amount">−₱<?=number_format($discount,2)?></span>
                    </div>
                    <?php endif; ?>
                    <div class="bf-divider"></div>
                    <div class="bf-line bf-grand-line">
                        <span class="bf-grand-lbl">Grand Total</span>
                        <span class="bf-grand-amt <?= $grand_total <= 0 && !empty($cart) ? 'zero' : '' ?>">
                            ₱<?=number_format($grand_total,2)?>
                        </span>
                    </div>
                    <?php if (!empty($cart) && $grand_total <= 0): ?>
                    <div style="margin-top:8px;padding:8px 12px;background:#fff7ed;border:1px solid #fed7aa;border-radius:8px;font-size:.8rem;color:#c2410c;display:flex;align-items:center;gap:6px;">
                        <i class="bi bi-exclamation-triangle-fill"></i>
                        All items show ₱0.00. This usually means the service names in <code>dl_results</code> don't match <code>dl_services</code>, or prices are missing. Check your database records.
                    </div>
                    <?php endif; ?>
                </div>

                <div class="bf-actions">
                    <div class="bf-act-left">
                        <a href="billing_items.php" class="bf-btn-back"><i class="bi bi-arrow-left"></i> Back</a>
                    </div>
                    <div class="bf-act-center">
                        <?php if ($can_finalize): ?>
                        <button class="bf-btn-finalize" onclick="confirmFinalize()">
                            <i class="bi bi-check-circle-fill"></i>
                            <?=$existing_bill?'Update &amp; Finalize':'Finalize Billing'?>
                        </button>
                        <?php elseif (!empty($cart) && $grand_total <= 0): ?>
                        <button class="bf-btn-finalize-disabled" disabled title="Grand total is ₱0.00 — nothing to bill">
                            <i class="bi bi-slash-circle"></i> Total is ₱0.00
                        </button>
                        <?php else: ?>
                        <span class="bf-empty-note"><i class="bi bi-inbox"></i> No items to finalize</span>
                        <?php endif; ?>
                    </div>
                    <div class="bf-act-right">
                        <?php if ($existing_bill): ?>
                        <a href="patient_billing.php?patient_id=<?=$patient_id?>" class="bf-btn-view"><i class="bi bi-eye"></i> View</a>
                        <?php endif; ?>
                        <a href="billing_items.php?patient_id=<?=$patient_id?>&reset_cart=1" class="bf-btn-reload" onclick="return confirm('Reload all items from database?')"><i class="bi bi-arrow-clockwise"></i> Reload</a>
                    </div>
                </div>

            </div>

        </div><!-- /.bcard -->
    </div><!-- /.billing-right -->

</div><!-- /.billing-layout -->

</div><!-- /.cw -->

<script>
function stepQty(btn, delta) {
    const input = btn.parentElement.querySelector('.qty-input');
    let v = parseInt(input.value)||1;
    v = Math.max(1, Math.min(999, v + delta));
    input.value = v;
}

function confirmFinalize() {
    const grandTotal = <?= $grand_total ?>;
    if (grandTotal <= 0) {
        Swal.fire({
            icon: 'warning',
            title: 'Cannot Finalize',
            html: 'The grand total is <strong>₱0.00</strong>.<br>Please ensure at least one item has a price greater than zero.',
            confirmButtonColor: '#0b1d3a',
            confirmButtonText: 'Got it',
        });
        return;
    }

    Swal.fire({
        title: '<?=$existing_bill?"Update Bill?":"Finalize Bill?"?>',
        html: `<div style="text-align:left;font-size:.9rem;line-height:2;">
            <?php if(!empty($cat_services)):?><div>🩺 Procedures: <strong>₱<?=number_format($svc_total,2)?></strong></div><?php endif;?>
            <?php if(!empty($cat_laboratory)):?><div>🧪 Laboratory: <strong>₱<?=number_format($lab_total,2)?></strong></div><?php endif;?>
            <?php if(!empty($cat_medicines)):?><div>💊 Medicines: <strong>₱<?=number_format($med_total,2)?></strong></div><?php endif;?>
            <hr style="margin:8px 0;border-color:#e2e8f0;">
            <?php if($discount>0):?><div style="color:#dc2626;">🏷 Discount (<?=$is_senior?'Senior':'PWD'?>): −₱<?=number_format($discount,2)?></div><?php endif;?>
            <div style="font-weight:700;font-size:1.05rem;margin-top:4px;">Grand Total: ₱<?=number_format($grand_total,2)?></div>
            <small style="color:#64748b;"><?=$existing_bill?"This will update the existing bill.":"This cannot be undone."?></small>
        </div>`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#059669',
        cancelButtonColor: '#64748b',
        confirmButtonText: '<?=$existing_bill?"Yes, Update":"Yes, Finalize"?>',
        cancelButtonText: 'Cancel'
    }).then(r => {
        if (r.isConfirmed) window.location.href = 'billing_items.php?patient_id=<?=$patient_id?>&finalize=1';
    });
}

(function(){
    const sb=document.getElementById('mySidebar'),cw=document.getElementById('mainCw');
    if(!sb||!cw)return;
    function sync(){cw.classList.toggle('sidebar-collapsed',sb.classList.contains('closed'));}
    new MutationObserver(sync).observe(sb,{attributes:true,attributeFilter:['class']});
    document.getElementById('sidebarToggle')?.addEventListener('click',()=>requestAnimationFrame(sync));
    sync();
})();
</script>
</body>
</html>