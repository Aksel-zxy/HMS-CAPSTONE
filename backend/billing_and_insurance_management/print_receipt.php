<?php
session_start();
include '../../SQL/config.php';

/* ===============================
   VALIDATE RECEIPT
================================ */
$receipt_id = isset($_GET['receipt_id']) ? intval($_GET['receipt_id']) : 0;
if ($receipt_id <= 0) die("Invalid receipt ID.");

/* ===============================
   FETCH RECEIPT + PATIENT + BILLING
================================ */
$stmt = $conn->prepare("
    SELECT
        pr.*,
        pi.fname, pi.mname, pi.lname,
        pi.phone_number, pi.address, pi.attending_doctor,
        pi.dob, pi.gender, pi.admission_type,
        br.total_amount    AS billing_total,
        br.grand_total     AS billing_grand,
        br.status          AS billing_status,
        br.billing_date,
        br.transaction_id  AS billing_txn
    FROM patient_receipt pr
    INNER JOIN patientinfo pi ON pr.patient_id = pi.patient_id
    LEFT  JOIN billing_records br ON pr.billing_id = br.billing_id
    WHERE pr.receipt_id = ?
");
$stmt->bind_param("i", $receipt_id);
$stmt->execute();
$billing = $stmt->get_result()->fetch_assoc();
if (!$billing) die("Receipt not found.");

$billing_id = (int)$billing['billing_id'];
$patient_id = (int)$billing['patient_id'];

$full_name = trim(
    $billing['fname'] . ' ' .
    (!empty($billing['mname']) ? $billing['mname'] . ' ' : '') .
    $billing['lname']
);

$contact = $billing['phone_number'] ?? 'N/A';
$is_inpatient = in_array($billing['admission_type'] ?? '', ['Inpatient','Confinement','Emergency','Surgery']);

/* ===============================
   BUILD BILLING ITEMS
   Strategy: reconstruct from all original source tables
   using the same logic as billing_items.php, then fall back
   to billing_items rows for any remainder.
================================ */
$billing_items = [];

/* ── 1. ROOM CHARGE (from patient_billing) ── */
$pb_stmt = $conn->prepare("
    SELECT pb.room_type_id, pb.room_total, pb.hours_stay,
           brt.name AS room_name, brt.price_per_day, brt.capacity,
           ir.room_no, ir.admission_date, ir.discharge_date
    FROM patient_billing pb
    LEFT JOIN billing_room_types brt ON brt.id = pb.room_type_id
    LEFT JOIN inpatient_registration ir ON ir.patient_id = pb.patient_id
    WHERE pb.patient_id = ?
    ORDER BY pb.billing_id DESC LIMIT 1
");
if ($pb_stmt) {
    $pb_stmt->bind_param("i", $patient_id);
    $pb_stmt->execute();
    $pb = $pb_stmt->get_result()->fetch_assoc();
    if ($pb && (float)($pb['room_total'] ?? 0) > 0) {
        $hours = round((float)($pb['hours_stay'] ?? 0), 1);
        $days  = $hours > 0 ? ceil($hours / 24) : 1;
        $desc_parts = [];
        if (!empty($pb['capacity']))       $desc_parts[] = $pb['capacity'];
        if (!empty($pb['price_per_day']))  $desc_parts[] = '₱' . number_format($pb['price_per_day'], 2) . '/day';
        if ($days > 0)                     $desc_parts[] = $days . ' day' . ($days > 1 ? 's' : '') . ' (' . $hours . ' hrs)';
        if (!empty($pb['admission_date'])) $desc_parts[] = date('M d, Y', strtotime($pb['admission_date'])) . ' → ' . (!empty($pb['discharge_date']) ? date('M d, Y', strtotime($pb['discharge_date'])) : 'ongoing');
        $room_label = ($pb['room_name'] ?? 'Room Accommodation');
        if (!empty($pb['room_no'])) $room_label .= ' — Rm ' . $pb['room_no'];
        $billing_items[] = [
            'category'    => 'room',
            'serviceName' => $room_label,
            'description' => implode(' · ', $desc_parts),
            'quantity'    => 1,
            'unit_price'  => (float)$pb['room_total'],
            'total_price' => (float)$pb['room_total'],
        ];
    }
}

/* ── 2. LAB RESULTS ── */
$ls = $conn->prepare("
    SELECT dr.resultID, dr.resultDate, dr.result AS serviceName,
           ds.price, ds.description AS svc_desc
    FROM dl_results dr
    LEFT JOIN dl_services ds ON LOWER(TRIM(ds.serviceName)) = LOWER(TRIM(dr.result))
    WHERE dr.patientID = ?
      AND dr.status IN ('Completed','Delivered')
    ORDER BY dr.resultDate ASC
");
if ($ls) {
    $ls->bind_param("i", $patient_id);
    $ls->execute();
    foreach ($ls->get_result()->fetch_all(MYSQLI_ASSOC) as $row) {
        $price = (float)($row['price'] ?? 0);
        /* fallback: match by first keyword */
        if ($price == 0 && !empty($row['serviceName'])) {
            $kw = strtolower(trim(preg_replace('/\s*\(.*?\)/', '', $row['serviceName'])));
            $kw = trim(strtok($kw, ' '));
            if (strlen($kw) >= 2) {
                $kws = $conn->prepare("SELECT price FROM dl_services WHERE LOWER(serviceName) LIKE ? LIMIT 1");
                $like = '%' . $kw . '%';
                $kws->bind_param("s", $like);
                $kws->execute();
                $kwr = $kws->get_result()->fetch_assoc();
                if ($kwr) $price = (float)$kwr['price'];
            }
        }
        $billing_items[] = [
            'category'    => 'laboratory',
            'serviceName' => trim($row['serviceName']) ?: 'Laboratory Service',
            'description' => 'Lab Result — ' . date('M d, Y', strtotime($row['resultDate'])),
            'quantity'    => 1,
            'unit_price'  => $price,
            'total_price' => $price,
        ];
    }
}

/* ── 3. PROCEDURES (DNM) ── */
$ds = @$conn->prepare("
    SELECT dnmr.record_id, dnmr.procedure_name, dnmr.amount, dnmr.created_at,
           dpl.price AS catalog_price
    FROM dnm_records dnmr
    LEFT JOIN dnm_procedure_list dpl
        ON LOWER(TRIM(dpl.procedure_name)) = LOWER(TRIM(dnmr.procedure_name))
        AND dpl.status = 'Active'
    WHERE dnmr.duty_id IN (
        SELECT da.duty_id
        FROM dl_results dr
        JOIN dl_schedule dsch ON dsch.scheduleID = dr.scheduleID
        JOIN duty_assignments da ON da.appointment_id = dsch.scheduleID
        WHERE dr.patientID = ?
    )
    ORDER BY dnmr.created_at ASC
");
if ($ds) {
    $ds->bind_param("i", $patient_id);
    $ds->execute();
    foreach ($ds->get_result()->fetch_all(MYSQLI_ASSOC) as $row) {
        $price = (float)($row['amount'] ?? 0);
        if ($price == 0) $price = (float)($row['catalog_price'] ?? 0);
        $billing_items[] = [
            'category'    => 'service',
            'serviceName' => $row['procedure_name'] ?: 'Procedure',
            'description' => 'Procedure — ' . date('M d, Y', strtotime($row['created_at'])),
            'quantity'    => 1,
            'unit_price'  => $price,
            'total_price' => $price,
        ];
    }
}

/* ── 4. DISPENSED MEDICINES (Pharmacy) ── */
$rs = $conn->prepare("
    SELECT ppi.item_id, ppi.med_id,
           ppi.dosage AS rx_dosage, ppi.frequency,
           ppi.quantity_dispensed, ppi.unit_price, ppi.total_price,
           pi2.med_name, pi2.dosage AS inv_dosage,
           pi2.unit_price AS inv_unit_price
    FROM pharmacy_prescription pp
    JOIN pharmacy_prescription_items ppi ON ppi.prescription_id = pp.prescription_id
    JOIN pharmacy_inventory pi2 ON pi2.med_id = ppi.med_id
    WHERE pp.patient_id = ?
      AND pp.payment_type = 'post_discharged'
      AND pp.status = 'Dispensed'
    ORDER BY ppi.item_id ASC
");
if ($rs) {
    $rs->bind_param("i", $patient_id);
    $rs->execute();
    foreach ($rs->get_result()->fetch_all(MYSQLI_ASSOC) as $row) {
        $dose     = trim(($row['rx_dosage'] ?? $row['inv_dosage'] ?? '') . ' ' . ($row['frequency'] ?? ''));
        $unit     = (float)($row['unit_price'] ?: $row['inv_unit_price'] ?: 0);
        $qty      = (int)($row['quantity_dispensed'] ?: 1);
        $rx_total = (float)$row['total_price'];
        if ($rx_total == 0) $rx_total = round($unit * $qty, 2);
        $billing_items[] = [
            'category'    => 'medicine',
            'serviceName' => $row['med_name'] . ($dose ? ' (' . trim($dose) . ')' : ''),
            'description' => 'Dispensed Medicine — Qty: ' . $qty . ' × ₱' . number_format($unit, 2),
            'quantity'    => $qty,
            'unit_price'  => $unit,
            'total_price' => $rx_total,
        ];
    }
}

/* ── 5. MANUALLY ADDED INPATIENT SERVICES (billing_services) ── */
$bis = $conn->prepare("
    SELECT bi.quantity, bi.unit_price, bi.total_price,
           bs.name, bs.category, bs.unit
    FROM billing_items bi
    JOIN billing_services bs ON bs.service_id = bi.service_id
    WHERE bi.billing_id = ?
      AND bi.finalized = 1
    ORDER BY bi.item_id ASC
");
if ($bis) {
    $bis->bind_param("i", $billing_id);
    $bis->execute();
    foreach ($bis->get_result()->fetch_all(MYSQLI_ASSOC) as $row) {
        $billing_items[] = [
            'category'    => 'service',
            'serviceName' => $row['name'] ?: 'Hospital Service',
            'description' => ucfirst(strtolower($row['category'] ?? 'Service')) . ' — ' . ($row['unit'] ?? 'unit'),
            'quantity'    => (int)($row['quantity'] ?: 1),
            'unit_price'  => (float)$row['unit_price'],
            'total_price' => (float)$row['total_price'],
        ];
    }
}

/* ── 6. FALLBACK: any billing_items rows NOT already covered above ──
   For items that don't join to billing_services (older records, manual adds, etc.)
   we attempt a multi-table name lookup. ── */
$fallback_stmt = $conn->prepare("
    SELECT bi.item_id, bi.quantity, bi.unit_price, bi.total_price, bi.service_id
    FROM billing_items bi
    LEFT JOIN billing_services bs ON bs.service_id = bi.service_id
    WHERE bi.billing_id = ?
      AND bi.finalized = 1
      AND bs.service_id IS NULL
    ORDER BY bi.item_id ASC
");
if ($fallback_stmt) {
    $fallback_stmt->bind_param("i", $billing_id);
    $fallback_stmt->execute();
    foreach ($fallback_stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $row) {
        $name = null;
        $desc = '';
        $unit_price = (float)$row['unit_price'];

        /* Try dl_services by service_id */
        $try1 = $conn->prepare("SELECT serviceName, description FROM dl_services WHERE serviceID = ? LIMIT 1");
        if ($try1) {
            $try1->bind_param("i", $row['service_id']);
            $try1->execute();
            $r1 = $try1->get_result()->fetch_assoc();
            if ($r1) { $name = $r1['serviceName']; $desc = $r1['description'] ?? ''; }
        }

        /* Try dnm_procedure_list by procedure_id */
        if (!$name) {
            $try2 = $conn->prepare("SELECT procedure_name AS serviceName FROM dnm_procedure_list WHERE procedure_id = ? LIMIT 1");
            if ($try2) {
                $try2->bind_param("i", $row['service_id']);
                $try2->execute();
                $r2 = $try2->get_result()->fetch_assoc();
                if ($r2) { $name = $r2['serviceName']; $desc = 'Procedure'; }
            }
        }

        /* Try pharmacy_prescription_items by item_id */
        if (!$name) {
            $try3 = $conn->prepare("
                SELECT CONCAT(pi2.med_name, IF(ppi.dosage != '', CONCAT(' (', ppi.dosage, ')'), '')) AS serviceName,
                       CONCAT('Dispensed — Qty: ', ppi.quantity_dispensed, ' × ₱', FORMAT(ppi.unit_price, 2)) AS svc_desc
                FROM pharmacy_prescription_items ppi
                JOIN pharmacy_inventory pi2 ON pi2.med_id = ppi.med_id
                WHERE ppi.item_id = ? LIMIT 1
            ");
            if ($try3) {
                $try3->bind_param("i", $row['service_id']);
                $try3->execute();
                $r3 = $try3->get_result()->fetch_assoc();
                if ($r3) { $name = $r3['serviceName']; $desc = $r3['svc_desc']; }
            }
        }

        /* Try billing_room_types by id */
        if (!$name) {
            $try4 = $conn->prepare("SELECT name AS serviceName, capacity FROM billing_room_types WHERE id = ? LIMIT 1");
            if ($try4) {
                $try4->bind_param("i", $row['service_id']);
                $try4->execute();
                $r4 = $try4->get_result()->fetch_assoc();
                if ($r4) { $name = 'Room: ' . $r4['serviceName']; $desc = 'Accommodation — ' . ($r4['capacity'] ?? ''); }
            }
        }

        /* Last resort: price-based match from dl_services */
        if (!$name && $unit_price > 0) {
            $try5 = $conn->prepare("SELECT serviceName, description FROM dl_services WHERE price = ? LIMIT 1");
            if ($try5) {
                $try5->bind_param("d", $unit_price);
                $try5->execute();
                $r5 = $try5->get_result()->fetch_assoc();
                if ($r5) { $name = $r5['serviceName']; $desc = $r5['description'] ?? ''; }
            }
        }

        if (!$name) $name = 'Hospital Service';

        $billing_items[] = [
            'category'    => 'service',
            'serviceName' => $name,
            'description' => $desc,
            'quantity'    => (int)($row['quantity'] ?: 1),
            'unit_price'  => $unit_price,
            'total_price' => (float)$row['total_price'],
        ];
    }
}

/* ── Deduplicate: remove items with identical serviceName + total_price
   that may have been picked up by both the source queries and fallback ── */
$seen = [];
$deduped = [];
foreach ($billing_items as $item) {
    $key = strtolower(trim($item['serviceName'])) . '|' . $item['total_price'];
    if (!isset($seen[$key])) {
        $seen[$key] = true;
        $deduped[] = $item;
    }
}
$billing_items = $deduped;

/* ===============================
   TOTALS — prefer patient_receipt values
================================ */
$total_charges  = array_sum(array_column($billing_items, 'total_price'));
$subtotal       = (float)($billing['total_charges'] ?? 0) > 0
                    ? (float)$billing['total_charges']
                    : ((float)($billing['billing_total'] ?? 0) > 0
                        ? (float)$billing['billing_total']
                        : $total_charges);
$total_discount = (float)($billing['total_discount'] ?? 0);
$insurance_cov  = (float)($billing['insurance_covered'] ?? 0);
$grand_total    = (float)($billing['grand_total'] ?? $billing['billing_grand'] ?? $subtotal);
$out_of_pocket  = (float)($billing['total_out_of_pocket'] ?? max(0, $grand_total - $insurance_cov));
$is_pwd         = (int)($billing['is_pwd'] ?? 0);

$billing_status = $billing['billing_status'] ?? $billing['status'] ?? 'Pending';
$is_paid        = ($billing_status === 'Paid');

$billing_date_raw = $billing['billing_date'] ?? $billing['created_at'] ?? date('Y-m-d');
$billing_date_fmt = date('F d, Y', strtotime($billing_date_raw));

/* Group items by category for display */
$items_by_cat = [
    'room'       => [],
    'laboratory' => [],
    'service'    => [],
    'medicine'   => [],
];
foreach ($billing_items as $item) {
    $cat = $item['category'] ?? 'service';
    if (!isset($items_by_cat[$cat])) $cat = 'service';
    $items_by_cat[$cat][] = $item;
}

/* ===============================
   ATTENDING DOCTOR
================================ */
$doctor_name    = 'N/A';
$doctor_contact = 'N/A';
$doctor_spec    = 'N/A';
if (!empty($billing['attending_doctor'])) {
    $dstmt = $conn->prepare("SELECT * FROM hr_employees WHERE employee_id = ?");
    $dstmt->bind_param("i", $billing['attending_doctor']);
    $dstmt->execute();
    $doctor = $dstmt->get_result()->fetch_assoc();
    if ($doctor) {
        $dn = trim(($doctor['first_name'] ?? '') . ' ' . ($doctor['middle_name'] ?? '') . ' ' . ($doctor['last_name'] ?? ''));
        if (!empty($doctor['suffix_name'])) $dn .= ', ' . $doctor['suffix_name'];
        $doctor_name    = trim($dn) ?: 'N/A';
        $doctor_contact = $doctor['contact_number'] ?? 'N/A';
        $doctor_spec    = $doctor['specialization'] ?? 'N/A';
    }
}

/* ===============================
   INSURANCE
================================ */
$insurance = null;
$istmt = $conn->prepare("
    SELECT * FROM patient_insurance
    WHERE full_name = ? AND status = 'Active'
    LIMIT 1
");
$istmt->bind_param("s", $full_name);
$istmt->execute();
$insurance = $istmt->get_result()->fetch_assoc();

/* Transaction ref */
$txn_ref = $billing['transaction_id'] ?? $billing['billing_txn'] ?? '—';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Receipt #<?= str_pad($receipt_id, 6, '0', STR_PAD_LEFT) ?> — Patient Billing</title>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;1,9..40,300&display=swap" rel="stylesheet">
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
:root {
    --navy: #0d2b45; --navy-mid: #1a4a6e; --teal: #0e7c7b; --teal-light: #e6f4f4;
    --gold: #c9954c; --gold-light: #fdf4e7;
    --green: #1a7a4a; --green-bg: #e8f6ee;
    --red: #b03a2e; --red-bg: #fdecea;
    --purple: #5b21b6; --purple-bg: #f5f3ff;
    --gray-100: #f7f8fa; --gray-200: #eef0f3; --gray-300: #d6dae0;
    --gray-500: #8a9099; --gray-700: #4a5261; --gray-900: #1c222d;
    --white: #ffffff;
    --shadow-lg: 0 16px 48px rgba(13,43,69,.16);
    --radius: 12px; --radius-sm: 6px;
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

/* ACTION BAR */
.action-bar {
    max-width: 920px; margin: 0 auto 20px;
    display: flex; align-items: center; justify-content: space-between; gap: 12px;
}
.action-bar .back-link {
    display: inline-flex; align-items: center; gap: 6px;
    color: var(--gray-700); text-decoration: none; font-size: 13px; font-weight: 600;
    padding: 8px 14px; border: 1.5px solid var(--gray-300); border-radius: var(--radius-sm);
    transition: all .15s;
}
.action-bar .back-link:hover { border-color: var(--navy); color: var(--navy); background: #f0f4f8; }
.btn-print {
    display: inline-flex; align-items: center; gap: 8px;
    padding: 10px 22px; background: var(--navy); color: var(--white);
    border: none; border-radius: var(--radius-sm);
    font-family: 'DM Sans', sans-serif; font-size: 13px; font-weight: 600;
    letter-spacing: .4px; cursor: pointer; transition: background .2s, transform .15s;
}
.btn-print:hover { background: var(--navy-mid); transform: translateY(-1px); }

/* INVOICE CARD */
.invoice-card {
    max-width: 920px; margin: 0 auto;
    background: var(--white); border-radius: var(--radius);
    box-shadow: var(--shadow-lg); overflow: hidden;
}

/* HEADER */
.invoice-header {
    background: var(--navy);
    padding: 36px 44px 30px;
    display: flex; align-items: flex-start;
    justify-content: space-between; gap: 24px;
    position: relative; overflow: hidden;
}
.invoice-header::before {
    content:''; position:absolute; top:-60px; right:-60px;
    width:220px; height:220px; border-radius:50%;
    background:rgba(14,124,123,.18); pointer-events:none;
}
.invoice-header::after {
    content:''; position:absolute; bottom:-40px; left:30%;
    width:140px; height:140px; border-radius:50%;
    background:rgba(201,149,76,.10); pointer-events:none;
}
.hospital-name    { font-family:'Playfair Display',serif; font-size:26px; font-weight:700; color:var(--white); letter-spacing:.3px; line-height:1.2; }
.hospital-tagline { font-size:12px; color:rgba(255,255,255,.55); letter-spacing:1.5px; text-transform:uppercase; margin-top:4px; }
.hospital-address { font-size:12px; color:rgba(255,255,255,.6); margin-top:10px; max-width:240px; line-height:1.5; }
.invoice-meta-block { text-align:right; flex-shrink:0; }
.invoice-label  { font-size:11px; color:rgba(255,255,255,.5); text-transform:uppercase; letter-spacing:1.4px; }
.invoice-number { font-family:'Playfair Display',serif; font-size:28px; color:var(--gold); letter-spacing:1px; margin:2px 0 10px; }
.date-chip { display:inline-block; padding:5px 14px; border:1px solid rgba(255,255,255,.2); border-radius:50px; font-size:12px; color:rgba(255,255,255,.75); }
.txn-chip  { display:inline-block; padding:3px 12px; border:1px solid rgba(255,255,255,.12); border-radius:50px; font-size:10.5px; color:rgba(255,255,255,.45); margin-top:6px; font-family:monospace; letter-spacing:.3px; }

/* STATUS BANNER */
.status-banner { padding:13px 44px; display:flex; align-items:center; gap:10px; font-size:13px; font-weight:600; letter-spacing:.3px; }
.status-banner.paid    { background:var(--green-bg); color:var(--green); border-bottom:1px solid #b8e3cc; }
.status-banner.unpaid  { background:var(--red-bg);   color:var(--red);   border-bottom:1px solid #f5c6c1; }
.status-banner svg     { width:18px; height:18px; flex-shrink:0; }

/* BODY */
.invoice-body { padding: 36px 44px; }

/* INFO GRID */
.info-grid {
    display:grid; grid-template-columns:1fr 1fr; gap:0;
    border:1px solid var(--gray-200); border-radius:var(--radius);
    overflow:hidden; margin-bottom:36px;
}
.info-panel { padding:24px 28px; }
.info-panel:first-child { border-right:1px solid var(--gray-200); }
.info-panel-title {
    font-size:10px; font-weight:600; letter-spacing:1.8px;
    text-transform:uppercase; color:var(--teal);
    margin-bottom:16px; padding-bottom:10px;
    border-bottom:2px solid var(--teal-light);
}
.info-row { display:flex; gap:10px; margin-bottom:9px; align-items:flex-start; }
.info-row:last-child { margin-bottom:0; }
.info-key {
    font-size:11px; font-weight:600; color:var(--gray-500);
    text-transform:uppercase; letter-spacing:.8px;
    min-width:110px; padding-top:1px; flex-shrink:0;
}
.info-val { font-size:13.5px; color:var(--gray-900); font-weight:500; line-height:1.4; }
.discount-badge { display:inline-flex; align-items:center; gap:4px; background:#d1fae5; color:#065f46; padding:3px 10px; border-radius:50px; font-size:12px; font-weight:700; }
.insurance-badge { display:inline-flex; align-items:center; gap:5px; background:var(--teal-light); color:var(--teal); padding:3px 10px; border-radius:50px; font-size:12px; font-weight:600; }

/* SECTION HEADERS in items table */
.cat-section-row td {
    padding: 9px 16px;
    font-size: 10px; font-weight: 700; letter-spacing: 1.6px;
    text-transform: uppercase;
    border-top: 2px solid var(--gray-200);
    border-bottom: 1px solid var(--gray-200);
}
.cat-room      td { background: #f5f3ff; color: var(--purple); }
.cat-lab       td { background: #f0fdf9; color: #047857; }
.cat-service   td { background: #faf5ff; color: #6d28d9; }
.cat-medicine  td { background: #eff6ff; color: #1d4ed8; }

/* ITEMS TABLE */
.section-label { font-size:10px; font-weight:600; letter-spacing:1.8px; text-transform:uppercase; color:var(--teal); margin-bottom:14px; }
.services-table {
    width:100%; border-collapse:separate; border-spacing:0;
    border:1px solid var(--gray-200); border-radius:var(--radius);
    overflow:hidden; margin-bottom:28px;
}
.services-table thead tr { background:var(--navy); }
.services-table thead th { padding:13px 16px; font-size:10.5px; font-weight:600; color:rgba(255,255,255,.75); text-transform:uppercase; letter-spacing:1.2px; white-space:nowrap; }
.services-table thead th:last-child { text-align:right; }
.services-table tbody tr:hover { background:var(--teal-light); }
.services-table td { padding:13px 16px; border-bottom:1px solid var(--gray-200); vertical-align:top; }
.services-table tbody tr:last-child td { border-bottom:none; }
.service-name { font-weight:600; color:var(--gray-900); font-size:13.5px; }
.service-desc { font-size:12px; color:var(--gray-500); margin-top:2px; }
.td-center { text-align:center; }
.td-right  { text-align:right; }
.td-amount { text-align:right; font-weight:700; color:var(--navy); }
.no-items td { text-align:center; padding:40px; color:var(--gray-500); font-style:italic; }
.cat-total { float:right; font-weight:800; font-size:11px; }

/* TOTALS */
.totals-row { display:flex; justify-content:flex-end; margin-bottom:32px; }
.totals-table { min-width:320px; border:1px solid var(--gray-200); border-radius:var(--radius); overflow:hidden; }
.t-row { display:flex; justify-content:space-between; align-items:center; padding:12px 20px; border-bottom:1px solid var(--gray-200); font-size:13.5px; }
.t-row:last-child { border-bottom:none; }
.t-label { color:var(--gray-700); font-weight:400; }
.t-val   { font-weight:700; color:var(--gray-900); }
.t-row.discount-row .t-label { color:#065f46; font-weight:600; }
.t-row.discount-row .t-val   { color:#065f46; font-weight:700; }
.t-row.insurance-row .t-label { color:var(--teal); }
.t-row.insurance-row .t-val   { color:var(--teal); font-weight:700; }
.t-row.total-due { background:var(--navy); padding:16px 20px; }
.t-row.total-due .t-label { font-size:13px; font-weight:600; color:rgba(255,255,255,.8); letter-spacing:.5px; text-transform:uppercase; }
.t-row.total-due .t-val   { font-family:'Playfair Display',serif; font-size:22px; color:var(--gold); letter-spacing:.5px; }

/* FOOTER */
.invoice-footer {
    border-top:1px solid var(--gray-200); padding:28px 44px;
    display:flex; align-items:center; justify-content:space-between; gap:20px;
    background:var(--gray-100);
}
.footer-note { font-size:12.5px; color:var(--gray-500); max-width:480px; line-height:1.6; }
.footer-note strong { color:var(--gray-700); }
.stamp-circle {
    width:80px; height:80px; border-radius:50%;
    border:2px dashed var(--gray-300);
    display:flex; flex-direction:column; align-items:center; justify-content:center;
    font-size:9px; font-weight:700; letter-spacing:.8px; text-transform:uppercase;
    color:var(--gray-300); line-height:1.3;
}
.stamp-circle.paid-stamp { border-color:var(--green); color:var(--green); background:var(--green-bg); font-size:11px; }

/* Left-border accent colors per category row */
.row-room     { border-left: 3px solid #a78bfa; }
.row-lab      { border-left: 3px solid #6ee7b7; }
.row-service  { border-left: 3px solid #c4b5fd; }
.row-medicine { border-left: 3px solid #93c5fd; }

/* PRINT */
@media print {
    *{ -webkit-print-color-adjust:exact !important; print-color-adjust:exact !important; }
    @page { size:A4; margin:14mm 12mm; }
    html,body { background:#fff !important; padding:0 !important; margin:0 !important; font-size:12px !important; }
    .action-bar { display:none !important; }
    .invoice-card { box-shadow:none !important; border-radius:0 !important; max-width:100% !important; margin:0 !important; }
    .invoice-header { background:#0d2b45 !important; padding:24px 28px 20px !important; }
    .invoice-header::before,.invoice-header::after { display:none !important; }
    .hospital-name { color:#fff !important; }
    .hospital-tagline,.hospital-address { color:rgba(255,255,255,.75) !important; }
    .invoice-label { color:rgba(255,255,255,.65) !important; }
    .invoice-number { color:#c9954c !important; }
    .date-chip,.txn-chip { color:rgba(255,255,255,.8) !important; border-color:rgba(255,255,255,.25) !important; }
    .status-banner.paid   { background:#e8f6ee !important; color:#1a7a4a !important; }
    .status-banner.unpaid { background:#fdecea !important; color:#b03a2e !important; }
    .info-panel-title { color:#0e7c7b !important; }
    .services-table thead tr { background:#0d2b45 !important; }
    .services-table thead th { color:rgba(255,255,255,.85) !important; }
    .services-table tbody tr:nth-child(even) { background:#f7f8fa !important; }
    .t-row.total-due { background:#0d2b45 !important; }
    .t-row.total-due .t-label { color:rgba(255,255,255,.85) !important; }
    .t-row.total-due .t-val { color:#c9954c !important; }
    .invoice-footer { background:#f7f8fa !important; }
    .discount-badge { background:#d1fae5 !important; color:#065f46 !important; }
    .stamp-circle.paid-stamp { border-color:#1a7a4a !important; color:#1a7a4a !important; background:#e8f6ee !important; }
    .cat-room    td { background:#f5f3ff !important; color:#5b21b6 !important; }
    .cat-lab     td { background:#f0fdf9 !important; color:#047857 !important; }
    .cat-service td { background:#faf5ff !important; color:#6d28d9 !important; }
    .cat-medicine td { background:#eff6ff !important; color:#1d4ed8 !important; }
    .invoice-header,.info-grid,.services-table,.totals-row,.invoice-footer { page-break-inside:avoid; break-inside:avoid; }
}
</style>
</head>
<body>

<!-- ACTION BAR -->
<div class="action-bar">
    <a href="javascript:history.back()" class="back-link">
        ← Back
    </a>
    <button class="btn-print" onclick="window.print()">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" style="width:15px;height:15px;">
            <polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/>
        </svg>
        Print Receipt
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
            <div class="invoice-label">Receipt Number</div>
            <div class="invoice-number">REC-<?= str_pad($receipt_id, 6, '0', STR_PAD_LEFT) ?></div>
            <div class="date-chip">📅 <?= htmlspecialchars($billing_date_fmt) ?></div>
            <div class="txn-chip"><?= htmlspecialchars($txn_ref) ?></div>
        </div>
    </div>

    <!-- STATUS BANNER -->
    <?php if ($is_paid): ?>
    <div class="status-banner paid">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
        PAID — Payment received. Thank you!
    </div>
    <?php else: ?>
    <div class="status-banner unpaid">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
        OUTSTANDING — Amount Due: ₱<?= number_format($out_of_pocket > 0 ? $out_of_pocket : $grand_total, 2) ?>
    </div>
    <?php endif; ?>

    <!-- BODY -->
    <div class="invoice-body">

        <!-- PATIENT & BILLING INFO -->
        <div class="info-grid">
            <div class="info-panel">
                <div class="info-panel-title">Patient Information</div>
                <div class="info-row">
                    <span class="info-key">Full Name</span>
                    <span class="info-val"><?= htmlspecialchars($full_name) ?></span>
                </div>
                <div class="info-row">
                    <span class="info-key">Patient ID</span>
                    <span class="info-val" style="font-family:monospace;font-size:12px;color:var(--gray-500);">#<?= str_pad($patient_id, 8, '0', STR_PAD_LEFT) ?></span>
                </div>
                <div class="info-row">
                    <span class="info-key">Contact</span>
                    <span class="info-val"><?= htmlspecialchars($contact ?: 'N/A') ?></span>
                </div>
                <div class="info-row">
                    <span class="info-key">Address</span>
                    <span class="info-val"><?= htmlspecialchars($billing['address'] ?: 'N/A') ?></span>
                </div>
                <?php if ($is_inpatient): ?>
                <div class="info-row">
                    <span class="info-key">Admission</span>
                    <span class="info-val"><?= htmlspecialchars($billing['admission_type']) ?></span>
                </div>
                <?php endif; ?>
                <?php if ($is_pwd): ?>
                <div class="info-row">
                    <span class="info-key">Discount</span>
                    <span class="info-val"><span class="discount-badge">✓ PWD / Senior — 20% off</span></span>
                </div>
                <?php endif; ?>
                <?php if ($insurance): ?>
                <div class="info-row">
                    <span class="info-key">Insurance</span>
                    <span class="info-val"><span class="insurance-badge">✦ <?= htmlspecialchars($insurance['insurance_company']) ?></span></span>
                </div>
                <div class="info-row">
                    <span class="info-key">Ins. No.</span>
                    <span class="info-val"><?= htmlspecialchars($insurance['insurance_number'] ?? '—') ?></span>
                </div>
                <?php endif; ?>
            </div>

            <div class="info-panel">
                <div class="info-panel-title">Billing Details</div>
                <div class="info-row">
                    <span class="info-key">Billing ID</span>
                    <span class="info-val" style="font-family:monospace;font-size:12px;color:var(--gray-500);">#<?= str_pad($billing_id, 8, '0', STR_PAD_LEFT) ?></span>
                </div>
                <div class="info-row">
                    <span class="info-key">Bill Date</span>
                    <span class="info-val"><?= htmlspecialchars($billing_date_fmt) ?></span>
                </div>
                <div class="info-row">
                    <span class="info-key">Status</span>
                    <span class="info-val">
                        <span style="display:inline-flex;align-items:center;gap:4px;padding:3px 10px;border-radius:999px;font-size:12px;font-weight:700;background:<?= $is_paid?'#d1fae5':'#fef3c7' ?>;color:<?= $is_paid?'#065f46':'#92400e' ?>;">
                            <?= $is_paid ? '✓ Paid' : '⏳ Pending' ?>
                        </span>
                    </span>
                </div>
                <div class="info-row">
                    <span class="info-key">Doctor</span>
                    <span class="info-val"><?= htmlspecialchars($doctor_name) ?></span>
                </div>
                <?php if ($doctor_spec !== 'N/A'): ?>
                <div class="info-row">
                    <span class="info-key">Specialization</span>
                    <span class="info-val"><?= htmlspecialchars($doctor_spec) ?></span>
                </div>
                <?php endif; ?>
                <div class="info-row">
                    <span class="info-key">Payment Ref</span>
                    <span class="info-val" style="font-family:monospace;font-size:11px;color:var(--gray-500);"><?= htmlspecialchars($txn_ref) ?></span>
                </div>
            </div>
        </div>

        <!-- ITEMS TABLE (grouped by category) -->
        <div class="section-label">Services &amp; Charges</div>
        <table class="services-table">
            <thead>
                <tr>
                    <th style="width:36%;">Service / Item</th>
                    <th>Details</th>
                    <th class="td-center" style="width:7%">Qty</th>
                    <th class="td-right" style="width:13%">Unit Price</th>
                    <th class="td-right" style="width:13%">Amount</th>
                </tr>
            </thead>
            <tbody>
            <?php
            $any_items = false;

            /* ── Room ── */
            if (!empty($items_by_cat['room'])):
                $any_items = true;
                $cat_sum = array_sum(array_column($items_by_cat['room'], 'total_price'));
            ?>
            <tr class="cat-section-row cat-room">
                <td colspan="5">🏥 Accommodation / Room Charges <span class="cat-total">₱<?= number_format($cat_sum, 2) ?></span></td>
            </tr>
            <?php foreach ($items_by_cat['room'] as $item): ?>
            <tr class="row-room">
                <td><div class="service-name"><?= htmlspecialchars($item['serviceName']) ?></div></td>
                <td><div class="service-desc"><?= htmlspecialchars($item['description'] ?: '—') ?></div></td>
                <td class="td-center"><?= (int)$item['quantity'] ?></td>
                <td class="td-right">₱<?= number_format($item['unit_price'], 2) ?></td>
                <td class="td-amount">₱<?= number_format($item['total_price'], 2) ?></td>
            </tr>
            <?php endforeach; endif; ?>

            <?php
            /* ── Laboratory ── */
            if (!empty($items_by_cat['laboratory'])):
                $any_items = true;
                $cat_sum = array_sum(array_column($items_by_cat['laboratory'], 'total_price'));
            ?>
            <tr class="cat-section-row cat-lab">
                <td colspan="5">🧪 Laboratory Results <span class="cat-total">₱<?= number_format($cat_sum, 2) ?></span></td>
            </tr>
            <?php foreach ($items_by_cat['laboratory'] as $item): ?>
            <tr class="row-lab">
                <td><div class="service-name"><?= htmlspecialchars($item['serviceName']) ?></div></td>
                <td><div class="service-desc"><?= htmlspecialchars($item['description'] ?: '—') ?></div></td>
                <td class="td-center"><?= (int)$item['quantity'] ?></td>
                <td class="td-right">₱<?= number_format($item['unit_price'], 2) ?></td>
                <td class="td-amount">₱<?= number_format($item['total_price'], 2) ?></td>
            </tr>
            <?php endforeach; endif; ?>

            <?php
            /* ── Services / Procedures ── */
            if (!empty($items_by_cat['service'])):
                $any_items = true;
                $cat_sum = array_sum(array_column($items_by_cat['service'], 'total_price'));
            ?>
            <tr class="cat-section-row cat-service">
                <td colspan="5">🩺 Procedures &amp; Services <span class="cat-total">₱<?= number_format($cat_sum, 2) ?></span></td>
            </tr>
            <?php foreach ($items_by_cat['service'] as $item): ?>
            <tr class="row-service">
                <td><div class="service-name"><?= htmlspecialchars($item['serviceName']) ?></div></td>
                <td><div class="service-desc"><?= htmlspecialchars($item['description'] ?: '—') ?></div></td>
                <td class="td-center"><?= (int)$item['quantity'] ?></td>
                <td class="td-right">₱<?= number_format($item['unit_price'], 2) ?></td>
                <td class="td-amount">₱<?= number_format($item['total_price'], 2) ?></td>
            </tr>
            <?php endforeach; endif; ?>

            <?php
            /* ── Medicines ── */
            if (!empty($items_by_cat['medicine'])):
                $any_items = true;
                $cat_sum = array_sum(array_column($items_by_cat['medicine'], 'total_price'));
            ?>
            <tr class="cat-section-row cat-medicine">
                <td colspan="5">💊 Medicines <span class="cat-total">₱<?= number_format($cat_sum, 2) ?></span></td>
            </tr>
            <?php foreach ($items_by_cat['medicine'] as $item): ?>
            <tr class="row-medicine">
                <td><div class="service-name"><?= htmlspecialchars($item['serviceName']) ?></div></td>
                <td><div class="service-desc"><?= htmlspecialchars($item['description'] ?: '—') ?></div></td>
                <td class="td-center"><?= (int)$item['quantity'] ?></td>
                <td class="td-right">₱<?= number_format($item['unit_price'], 2) ?></td>
                <td class="td-amount">₱<?= number_format($item['total_price'], 2) ?></td>
            </tr>
            <?php endforeach; endif; ?>

            <?php if (!$any_items): ?>
            <tr class="no-items"><td colspan="5">No billing items found for this receipt.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>

        <!-- TOTALS BLOCK -->
        <div class="totals-row">
            <div class="totals-table">
                <div class="t-row">
                    <span class="t-label">Subtotal</span>
                    <span class="t-val">₱<?= number_format($subtotal, 2) ?></span>
                </div>
                <?php if ($total_discount > 0): ?>
                <div class="t-row discount-row">
                    <span class="t-label">🏷 <?= $is_pwd ? 'PWD / Senior' : '' ?> Discount (20%)</span>
                    <span class="t-val">−₱<?= number_format($total_discount, 2) ?></span>
                </div>
                <?php endif; ?>
                <?php if ($insurance_cov > 0): ?>
                <div class="t-row insurance-row">
                    <span class="t-label">🛡 Insurance Coverage</span>
                    <span class="t-val">−₱<?= number_format($insurance_cov, 2) ?></span>
                </div>
                <?php endif; ?>
                <div class="t-row total-due">
                    <span class="t-label"><?= ($insurance_cov > 0) ? 'Out of Pocket' : 'Total Due' ?></span>
                    <span class="t-val">₱<?= number_format($out_of_pocket > 0 ? $out_of_pocket : $grand_total, 2) ?></span>
                </div>
            </div>
        </div>

    </div><!-- /.invoice-body -->

    <!-- FOOTER -->
    <div class="invoice-footer">
        <div class="footer-note">
            <strong>Thank you for choosing our hospital.</strong><br>
            Please present this receipt at the cashier when settling your balance. For inquiries,
            contact our Billing &amp; Finance Department at <strong>(02) 8000-0000</strong>
            or email <strong>billing@hospital.com</strong>.
        </div>
        <div style="text-align:center;flex-shrink:0;">
            <div class="stamp-circle <?= $is_paid ? 'paid-stamp' : '' ?>">
                <?= $is_paid ? '✓<br>PAID' : 'OFFICIAL<br>RECEIPT' ?>
            </div>
        </div>
    </div>

</div><!-- /.invoice-card -->
</body>
</html>