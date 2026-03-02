<?php
// ============================================================
//  HOSPITAL INPATIENT BILLING SYSTEM v4
//  inpatient_billing.php
//  Workflow:
//  Step 1: Register — Demographics + Choose Admission Service Type
//  Step 2: Inpatient List — View all admitted patients
//  Step 3: Chart — Add medicines, supplies, services + set/change room
//  Step 4: Finalize & Discharge — Generate final SOA
// ============================================================
include '../../SQL/config.php';

/* ── helpers ── */
function money(float $n): string { return '₱' . number_format($n, 2); }
function safe(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
function age($dob): string {
    if (!$dob) return '—';
    $diff = (new DateTime())->diff(new DateTime($dob));
    return $diff->y . ' yrs';
}
function regNo(PDO $pdo): string {
    $y   = date('Y');
    $row = $pdo->query("SELECT COUNT(*) FROM inpatient_registration WHERE YEAR(created_at)=$y")->fetchColumn();
    return 'IPR-' . $y . '-' . str_pad((int)$row + 1, 4, '0', STR_PAD_LEFT);
}
function billNo(PDO $pdo): string {
    $y   = date('Y');
    $row = $pdo->query("SELECT COUNT(*) FROM patient_billing WHERE YEAR(created_at)=$y")->fetchColumn();
    return 'BILL-' . $y . '-' . str_pad((int)$row + 1, 4, '0', STR_PAD_LEFT);
}

/* ─────────────────────────────────────────────────
   LOAD MASTER DATA
───────────────────────────────────────────────── */
$roomTypes   = $pdo->query("SELECT * FROM billing_room_types WHERE is_active=1 ORDER BY price_per_day")->fetchAll(PDO::FETCH_ASSOC);
$allServices = $pdo->query("SELECT * FROM billing_services WHERE is_active=1 ORDER BY category,name")->fetchAll(PDO::FETCH_ASSOC);

$svcByType = ['Service' => [], 'Medicine' => [], 'Supply' => []];
foreach ($allServices as $s) { $svcByType[$s['item_type']][] = $s; }

// Group services by their category for filtering
$svcCategories = array_unique(array_column(
    array_filter($allServices, fn($s) => $s['item_type'] === 'Service'), 'category'
));

/* ─────────────────────────────────────────────────
   HANDLE POST ACTIONS
───────────────────────────────────────────────── */
$action    = $_POST['action'] ?? '';
$flashMsg  = '';
$flashType = 'ok';

/* ══ STEP 1: REGISTER NEW PATIENT ══ */
if ($action === 'register_patient') {
    try {
        $rno     = regNo($pdo);
        $admDate = !empty($_POST['admission_date']) ? $_POST['admission_date'] : date('Y-m-d H:i:s');
        $roomId  = !empty($_POST['room_type_id']) && $_POST['room_type_id'] != '0' ? (int)$_POST['room_type_id'] : null;

        $pdo->prepare("INSERT INTO inpatient_registration
            (registration_no, last_name, first_name, middle_name, date_of_birth, gender,
             civil_status, nationality, religion, address, contact_no, email,
             philhealth_no, sss_gsis_no,
             emergency_contact_name, emergency_contact_relation, emergency_contact_no,
             admission_type, admission_date, room_type_id, room_no,
             attending_physician, chief_complaint, diagnosis, status, billing_status, registered_by)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,'Admitted','Pending','admin')")
        ->execute([
            $rno,
            trim($_POST['last_name']   ?? ''),
            trim($_POST['first_name']  ?? ''),
            trim($_POST['middle_name'] ?? ''),
            !empty($_POST['date_of_birth']) ? $_POST['date_of_birth'] : null,
            $_POST['gender']       ?? null,
            $_POST['civil_status'] ?? 'Single',
            $_POST['nationality']  ?? 'Filipino',
            trim($_POST['religion']    ?? ''),
            trim($_POST['address']     ?? ''),
            trim($_POST['contact_no']  ?? ''),
            trim($_POST['email']       ?? ''),
            trim($_POST['philhealth_no'] ?? ''),
            trim($_POST['sss_gsis_no']   ?? ''),
            trim($_POST['emergency_contact_name']     ?? ''),
            trim($_POST['emergency_contact_relation'] ?? ''),
            trim($_POST['emergency_contact_no']       ?? ''),
            $_POST['admission_type'] ?? 'Confinement',
            $admDate,
            $roomId,
            trim($_POST['room_no']            ?? ''),
            trim($_POST['attending_physician'] ?? ''),
            trim($_POST['chief_complaint']     ?? ''),
            trim($_POST['diagnosis']           ?? ''),
        ]);
        $newPid = $pdo->lastInsertId();
        header("Location: inpatient_billing.php?view=patients&registered=1&new_pid=$newPid");
        exit;
    } catch (Exception $e) {
        $flashMsg  = 'Registration failed: ' . $e->getMessage();
        $flashType = 'err';
    }
}

/* ══ UPDATE ROOM TYPE DURING CONFINEMENT ══ */
if ($action === 'update_room') {
    $pid    = (int)($_POST['patient_id'] ?? 0);
    $roomId = !empty($_POST['room_type_id']) && $_POST['room_type_id'] != '0' ? (int)$_POST['room_type_id'] : null;
    $roomNo = trim($_POST['room_no'] ?? '');
    $pdo->prepare("UPDATE inpatient_registration SET room_type_id=?, room_no=? WHERE patient_id=?")
        ->execute([$roomId, $roomNo, $pid]);
    // Also update the draft billing if it exists
    $pdo->prepare("UPDATE patient_billing SET room_type_id=? WHERE patient_id=? AND finalized=0")
        ->execute([$roomId, $pid]);
    header("Location: inpatient_billing.php?view=chart&pid=$pid&room_updated=1");
    exit;
}

/* ══ ADD ORDERS TO PATIENT CHART ══ */
if ($action === 'add_orders') {
    try {
        $pdo->beginTransaction();
        $pid = (int)($_POST['patient_id'] ?? 0);

        // Ensure draft billing record exists
        $draftBill = $pdo->prepare("SELECT billing_id FROM patient_billing WHERE patient_id=? AND finalized=0 ORDER BY created_at DESC LIMIT 1");
        $draftBill->execute([$pid]);
        $billingId = $draftBill->fetchColumn();

        if (!$billingId) {
            $bn = billNo($pdo);
            $patRow = $pdo->prepare("SELECT room_type_id FROM inpatient_registration WHERE patient_id=?");
            $patRow->execute([$pid]);
            $patData = $patRow->fetch(PDO::FETCH_ASSOC);

            $pdo->prepare("INSERT INTO patient_billing
                (patient_id, bill_number, billing_date, room_type_id, hours_stay, room_total,
                 services_total, medicines_total, supplies_total, gross_total,
                 discount_pct, discount_amount, philhealth_deduct, amount_due,
                 payment_status, finalized, notes)
                VALUES (?,?,NOW(),?,0,0,0,0,0,0,0,0,0,0,'Pending',0,'')")
            ->execute([$pid, $bn, $patData['room_type_id'] ?? null]);
            $billingId = $pdo->lastInsertId();
        }

        // Insert line items
        $serviceIds   = $_POST['service_ids']   ?? [];
        $serviceQtys  = $_POST['service_qtys']  ?? [];

        foreach ($serviceIds as $idx => $sid) {
            $sid = (int)$sid;
            if (!$sid) continue;
            $qty = max(1, (int)($serviceQtys[$idx] ?? 1));

            $sRow = $pdo->prepare("SELECT base_price, item_type, name FROM billing_services WHERE service_id=?");
            $sRow->execute([$sid]);
            $sv = $sRow->fetch(PDO::FETCH_ASSOC);
            if (!$sv) continue;

            $unitPrice = (float)$sv['base_price'];
            $lineTotal = $unitPrice * $qty;

            $pdo->prepare("INSERT INTO billing_items (billing_id, patient_id, service_id, quantity, unit_price, total_price, finalized)
                VALUES (?,?,?,?,?,?,0)")
            ->execute([$billingId, $pid, $sid, $qty, $unitPrice, $lineTotal]);
        }

        // Recalculate totals
        recalcBilling($pdo, $billingId, $pid);

        $pdo->commit();
        header("Location: inpatient_billing.php?view=chart&pid=$pid&added=1");
        exit;
    } catch (Exception $e) {
        $pdo->rollBack();
        $flashMsg  = 'Order error: ' . $e->getMessage();
        $flashType = 'err';
    }
}

/* ══ REMOVE A BILLING ITEM ══ */
if ($action === 'remove_item') {
    $itemId    = (int)($_POST['item_id']    ?? 0);
    $pid       = (int)($_POST['patient_id'] ?? 0);
    // Get billing_id before deleting
    $bidQ = $pdo->prepare("SELECT billing_id FROM billing_items WHERE item_id=?");
    $bidQ->execute([$itemId]);
    $bid = (int)$bidQ->fetchColumn();
    $pdo->prepare("DELETE FROM billing_items WHERE item_id=?")->execute([$itemId]);
    if ($bid) recalcBilling($pdo, $bid, $pid);
    header("Location: inpatient_billing.php?view=chart&pid=$pid&removed=1");
    exit;
}

/* ══ FINALIZE BILLING & DISCHARGE ══ */
if ($action === 'finalize_bill') {
    try {
        $pdo->beginTransaction();
        $pid           = (int)($_POST['patient_id']     ?? 0);
        $discPct       = (float)($_POST['discount_pct'] ?? 0);
        $philDeduct    = (float)($_POST['philhealth_deduct'] ?? 0);
        $seniorDisc    = !empty($_POST['senior_discount']) ? 1 : 0;
        $payStatus     = $_POST['payment_status']  ?? 'Pending';
        $paymentMethod = $_POST['payment_method']  ?? 'cash';
        // Ensure payment status aligns with selected method when appropriate
        if ($paymentMethod === 'cash') $payStatus = 'Paid';
        if ($paymentMethod === 'online' && empty($payStatus)) $payStatus = 'Pending';
        $dischargeDate = !empty($_POST['discharge_date']) ? $_POST['discharge_date'] : date('Y-m-d H:i:s');
        $dischargeType = $_POST['discharge_type']  ?? 'Recovered';
        $finalNotes    = trim($_POST['notes']       ?? '');

        // Get draft bill + patient info
        $dBill = $pdo->prepare("SELECT pb.*, ir.admission_date, ir.room_type_id,
            brt.price_per_hour, brt.price_per_day
            FROM patient_billing pb
            JOIN inpatient_registration ir ON ir.patient_id=pb.patient_id
            LEFT JOIN billing_room_types brt ON brt.id=ir.room_type_id
            WHERE pb.patient_id=? AND pb.finalized=0 ORDER BY pb.created_at DESC LIMIT 1");
        $dBill->execute([$pid]);
        $draftData = $dBill->fetch(PDO::FETCH_ASSOC);

        if (!$draftData) {
            // Create blank bill if none
            $bn = billNo($pdo);
            $pi = $pdo->prepare("SELECT room_type_id FROM inpatient_registration WHERE patient_id=?");
            $pi->execute([$pid]);
            $piData = $pi->fetch(PDO::FETCH_ASSOC);
            $pdo->prepare("INSERT INTO patient_billing (patient_id, bill_number, billing_date, room_type_id, hours_stay, room_total, services_total, medicines_total, supplies_total, gross_total, discount_pct, discount_amount, philhealth_deduct, amount_due, payment_status, finalized, notes) VALUES (?,?,NOW(),?,0,0,0,0,0,0,0,0,0,0,'Pending',0,'')")
            ->execute([$pid, $bn, $piData['room_type_id'] ?? null]);
            $billingId = $pdo->lastInsertId();
            // Re-fetch
            $dBill->execute([$pid]);
            $draftData = $dBill->fetch(PDO::FETCH_ASSOC);
            $draftData = $draftData ?: ['billing_id' => $billingId, 'admission_date' => date('Y-m-d H:i:s'), 'room_type_id' => null, 'price_per_hour' => 0, 'price_per_day' => 0];
        }

        $billingId = $draftData['billing_id'];

        // Compute final room charge
        $admDt     = new DateTime($draftData['admission_date'] ?? date('Y-m-d H:i:s'));
        $dischDt   = new DateTime($dischargeDate);
        $diff      = $admDt->diff($dischDt);
        $hoursStay = max(0, round($diff->days * 24 + $diff->h + $diff->i / 60, 2));

        $roomTotal = 0;
        if ($draftData['room_type_id'] && $hoursStay > 0) {
            $roomTotal = $hoursStay >= 24
                ? ceil($hoursStay / 24) * (float)($draftData['price_per_day'] ?? 0)
                : $hoursStay * (float)($draftData['price_per_hour'] ?? 0);
        }

        // Item totals
        $t3 = $pdo->prepare("SELECT
            SUM(CASE WHEN bs.item_type='Service'  THEN bi.total_price ELSE 0 END) AS svc,
            SUM(CASE WHEN bs.item_type='Medicine' THEN bi.total_price ELSE 0 END) AS med,
            SUM(CASE WHEN bs.item_type='Supply'   THEN bi.total_price ELSE 0 END) AS sup
            FROM billing_items bi JOIN billing_services bs ON bs.service_id=bi.service_id
            WHERE bi.billing_id=?");
        $t3->execute([$billingId]);
        $tv3 = $t3->fetch(PDO::FETCH_ASSOC);

        $svcT  = (float)($tv3['svc'] ?? 0);
        $medT  = (float)($tv3['med'] ?? 0);
        $supT  = (float)($tv3['sup'] ?? 0);
        $gross = $roomTotal + $svcT + $medT + $supT;

        $seniorDiscAmt  = $seniorDisc ? round($gross * 0.20, 2) : 0;
        $discountAmount = round($gross * $discPct / 100, 2) + $seniorDiscAmt;
        $amountDue      = max(0, $gross - $discountAmount - $philDeduct);
        $finalDiscPct   = $discPct + ($seniorDisc ? 20 : 0);

        $pdo->prepare("UPDATE patient_billing SET
            hours_stay=?, room_total=?, services_total=?, medicines_total=?, supplies_total=?,
            gross_total=?, discount_pct=?, discount_amount=?, philhealth_deduct=?,
            amount_due=?, payment_status=?, finalized=1, notes=?
            WHERE billing_id=?")
        ->execute([$hoursStay, $roomTotal, $svcT, $medT, $supT,
            $gross, $finalDiscPct, $discountAmount, $philDeduct,
            $amountDue, $payStatus, $finalNotes, $billingId]);

        $pdo->prepare("UPDATE billing_items SET finalized=1 WHERE billing_id=?")->execute([$billingId]);

        $pdo->prepare("UPDATE inpatient_registration SET
            status='Discharged', discharge_date=?, discharge_type=?, billing_status=?
            WHERE patient_id=?")
        ->execute([$dischargeDate, $dischargeType, $payStatus, $pid]);

        // Create or update payment records so the billing_summary page can pick them up
        $txn = 'TXN-' . strtoupper(uniqid());
        // billing_records: create or update row keyed by billing_id
        $br = $pdo->prepare("SELECT billing_id FROM billing_records WHERE billing_id=? LIMIT 1");
        $br->execute([$billingId]);
        $brExists = (bool)$br->fetchColumn();
        $brStatus = $paymentMethod === 'cash' ? 'Paid' : 'Pending';
        if ($brExists) {
            $pdo->prepare("UPDATE billing_records SET total_amount=?, grand_total=?, status=?, transaction_id=? WHERE billing_id=?")
                ->execute([$gross, $amountDue, $brStatus, $txn, $billingId]);
        } else {
            $pdo->prepare("INSERT INTO billing_records (billing_id, patient_id, billing_date, total_amount, grand_total, status, transaction_id) VALUES (?,?,?,?,?,?,?)")
                ->execute([$billingId, $pid, date('Y-m-d H:i:s'), $gross, $amountDue, $brStatus, $txn]);
        }

        // patient_receipt: create or update
        $pr = $pdo->prepare("SELECT receipt_id FROM patient_receipt WHERE billing_id=? LIMIT 1");
        $pr->execute([$billingId]);
        $prExists = (bool)$pr->fetchColumn();
        if ($prExists) {
            $pdo->prepare("UPDATE patient_receipt SET total_charges=?, total_vat=0, total_discount=?, total_out_of_pocket=?, grand_total=?, status=?, transaction_id=?, is_pwd=0 WHERE billing_id=?")
                ->execute([$gross, $discountAmount, $amountDue, $amountDue, $brStatus, $txn, $billingId]);
        } else {
            $pdo->prepare("INSERT INTO patient_receipt (patient_id,billing_id,total_charges,total_vat,total_discount,total_out_of_pocket,grand_total,status,transaction_id,is_pwd) VALUES (?,?,?,?,?,?,?,?,?,?)")
                ->execute([$pid, $billingId, $gross, 0, $discountAmount, $amountDue, $amountDue, $brStatus, $txn, 0]);
        }

        $pdo->commit();

        // ── REDIRECT BASED ON PAYMENT METHOD ──
        if ($paymentMethod === 'online') {
            // Go to billing_summary for online payment link creation
            header("Location: billing_summary.php?billing_id=$billingId&patient_id=$pid");
            exit;
        } elseif ($paymentMethod === 'cash') {
            // Go to billing_summary so cashier can confirm & process cash payment
            header("Location: billing_summary.php?billing_id=$billingId&patient_id=$pid&from=inpatient&cash=1");
            exit;
        }
        // Fallback: show receipt
        header("Location: inpatient_billing.php?view=receipt&billing_id=$billingId&finalized=1");
        exit;

    } catch (Exception $e) {
        $pdo->rollBack();
        $flashMsg  = 'Finalization error: ' . $e->getMessage();
        $flashType = 'err';
    }
}

/* ══ UPDATE PATIENT STATUS ══ */
if ($action === 'update_status') {
    $pid    = (int)($_POST['patient_id'] ?? 0);
    $status = $_POST['status'] ?? 'Admitted';
    $disc   = !empty($_POST['discharge_date']) ? $_POST['discharge_date'] : null;
    $pdo->prepare("UPDATE inpatient_registration SET status=?, discharge_date=? WHERE patient_id=?")->execute([$status, $disc, $pid]);
    header("Location: inpatient_billing.php?view=patients&updated=1");
    exit;
}

/* ══ QUICK CASH PAYMENT FOR ALREADY-FINALIZED BILLS ══ */
if ($action === 'quick_cash_payment') {
    $pid       = (int)($_POST['patient_id'] ?? 0);
    $billingId = (int)($_POST['billing_id'] ?? 0);
    if ($pid && $billingId) {
        header("Location: billing_summary.php?billing_id=$billingId&patient_id=$pid&from=inpatient&cash=1");
        exit;
    }
    header("Location: inpatient_billing.php?view=patients&err=no_bill");
    exit;
}

/* ── Recalculate billing totals helper ── */
function recalcBilling(PDO $pdo, int $billingId, int $pid): void {
    $t = $pdo->prepare("SELECT
        SUM(CASE WHEN bs.item_type='Service'  THEN bi.total_price ELSE 0 END) AS svc,
        SUM(CASE WHEN bs.item_type='Medicine' THEN bi.total_price ELSE 0 END) AS med,
        SUM(CASE WHEN bs.item_type='Supply'   THEN bi.total_price ELSE 0 END) AS sup
        FROM billing_items bi JOIN billing_services bs ON bs.service_id=bi.service_id
        WHERE bi.billing_id=?");
    $t->execute([$billingId]);
    $tv = $t->fetch(PDO::FETCH_ASSOC);

    // Room estimate
    $rtRow = $pdo->prepare("SELECT brt.price_per_hour, brt.price_per_day, ir.admission_date, ir.room_type_id
        FROM inpatient_registration ir
        LEFT JOIN billing_room_types brt ON brt.id=ir.room_type_id
        WHERE ir.patient_id=?");
    $rtRow->execute([$pid]);
    $rtData    = $rtRow->fetch(PDO::FETCH_ASSOC);
    $roomTotal = 0; $hoursStay = 0;
    if ($rtData && $rtData['admission_date'] && $rtData['room_type_id']) {
        $admDt     = new DateTime($rtData['admission_date']);
        $now       = new DateTime();
        $hoursStay = round($admDt->diff($now)->days * 24 + $admDt->diff($now)->h + $admDt->diff($now)->i / 60, 2);
        $roomTotal = $hoursStay >= 24
            ? ceil($hoursStay / 24) * (float)$rtData['price_per_day']
            : $hoursStay * (float)$rtData['price_per_hour'];
    }
    $gross = $roomTotal + (float)($tv['svc']??0) + (float)($tv['med']??0) + (float)($tv['sup']??0);
    $pdo->prepare("UPDATE patient_billing SET
        hours_stay=?, room_total=?, services_total=?, medicines_total=?, supplies_total=?,
        gross_total=?, amount_due=? WHERE billing_id=?")
    ->execute([$hoursStay, $roomTotal, $tv['svc']??0, $tv['med']??0, $tv['sup']??0, $gross, $gross, $billingId]);
}

/* ─────────────────────────────────────────────────
   LOAD PAGE DATA
───────────────────────────────────────────────── */
$patients = $pdo->query("SELECT ir.*, brt.name AS room_name
    FROM inpatient_registration ir
    LEFT JOIN billing_room_types brt ON brt.id = ir.room_type_id
    ORDER BY ir.created_at DESC LIMIT 500")->fetchAll(PDO::FETCH_ASSOC);

// Fetch finalized billing IDs for discharged patients so we can build cash pay buttons
$finalBillingMap = [];
try {
    $fbRows = $pdo->query("SELECT patient_id, billing_id, payment_status, amount_due FROM patient_billing WHERE finalized=1 ORDER BY updated_at DESC")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($fbRows as $fb) {
        // Keep the most recent per patient
        if (!isset($finalBillingMap[$fb['patient_id']])) {
            $finalBillingMap[$fb['patient_id']] = $fb;
        }
    }
} catch (Exception $e) { /* ignore */ }

$pid            = (int)($_GET['pid'] ?? 0);
$currentPatient = null;
$draftBilling   = null;
$draftItems     = [];

if ($pid > 0) {
    $s = $pdo->prepare("SELECT ir.*, brt.name AS room_name, brt.price_per_hour, brt.price_per_day
        FROM inpatient_registration ir
        LEFT JOIN billing_room_types brt ON brt.id=ir.room_type_id
        WHERE ir.patient_id=?");
    $s->execute([$pid]);
    $currentPatient = $s->fetch(PDO::FETCH_ASSOC);

    if ($currentPatient) {
        $db = $pdo->prepare("SELECT pb.*, brt.name AS room_name FROM patient_billing pb
            LEFT JOIN billing_room_types brt ON brt.id=pb.room_type_id
            WHERE pb.patient_id=? AND pb.finalized=0 ORDER BY pb.created_at DESC LIMIT 1");
        $db->execute([$pid]);
        $draftBilling = $db->fetch(PDO::FETCH_ASSOC);

        if ($draftBilling) {
            $di = $pdo->prepare("SELECT bi.*, bs.name AS service_name, bs.category, bs.item_type, bs.unit
                FROM billing_items bi
                JOIN billing_services bs ON bs.service_id=bi.service_id
                WHERE bi.billing_id=? ORDER BY bi.added_at ASC");
            $di->execute([$draftBilling['billing_id']]);
            $draftItems = $di->fetchAll(PDO::FETCH_ASSOC);
        }
    }
}

// For receipt
$billingId   = (int)($_GET['billing_id'] ?? 0);
$viewBilling = null;
$viewItems   = [];
if ($billingId > 0) {
    $bs = $pdo->prepare("SELECT pb.*, brt.name AS room_name, brt.price_per_hour, brt.price_per_day,
        ir.last_name, ir.first_name, ir.middle_name, ir.date_of_birth, ir.gender,
        ir.philhealth_no, ir.sss_gsis_no, ir.address, ir.contact_no, ir.registration_no,
        ir.admission_type AS reg_admission_type, ir.admission_date,
        ir.attending_physician, ir.chief_complaint, ir.diagnosis,
        ir.discharge_date, ir.discharge_type, ir.room_no, ir.civil_status
        FROM patient_billing pb
        LEFT JOIN billing_room_types brt ON brt.id=pb.room_type_id
        LEFT JOIN inpatient_registration ir ON ir.patient_id=pb.patient_id
        WHERE pb.billing_id=?");
    $bs->execute([$billingId]);
    $viewBilling = $bs->fetch(PDO::FETCH_ASSOC);

    if ($viewBilling) {
        $bi = $pdo->prepare("SELECT bi.*, bs.name AS service_name, bs.category, bs.item_type, bs.unit
            FROM billing_items bi
            JOIN billing_services bs ON bs.service_id=bi.service_id
            WHERE bi.billing_id=?
            ORDER BY bs.item_type, bs.category");
        $bi->execute([$billingId]);
        $viewItems = $bi->fetchAll(PDO::FETCH_ASSOC);
    }
}

$currentView = $_GET['view'] ?? 'registration';

// Flash messages from redirects
if (!empty($_GET['registered']))   { $flashMsg = 'Patient registered successfully! They are now in the Inpatient List.'; $flashType = 'ok'; }
if (!empty($_GET['added']))        { $flashMsg = 'Orders added to patient chart.'; $flashType = 'ok'; }
if (!empty($_GET['removed']))      { $flashMsg = 'Item removed from chart.'; $flashType = 'ok'; }
if (!empty($_GET['finalized']))    { $flashMsg = 'Billing finalized. Receipt generated.'; $flashType = 'ok'; }
if (!empty($_GET['updated']))      { $flashMsg = 'Patient status updated.'; $flashType = 'ok'; }
if (!empty($_GET['room_updated'])) { $flashMsg = 'Room assignment updated.'; $flashType = 'ok'; }

$totalAdmitted   = count(array_filter($patients, fn($p) => $p['status'] === 'Admitted'));
$totalDischarged = count(array_filter($patients, fn($p) => $p['status'] === 'Discharged'));
$totalPending    = count(array_filter($patients, fn($p) => $p['billing_status'] === 'Pending'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Inpatient Billing — HMS</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<style>
:root {
    --navy:   #0b1f3a; --navy2:  #142c50; --navy3: #1e3d6e;
    --teal:   #0d9488; --teal-l: #99f6e4; --teal-xl:#f0fdfa;
    --green:  #059669; --green-l:#d1fae5;
    --amber:  #d97706; --amber-l:#fef3c7;
    --red:    #dc2626; --red-l:  #fee2e2;
    --blue:   #2563eb; --blue-l: #dbeafe;
    --purple: #7c3aed; --purple-l:#ede9fe;
    --orange: #ea580c; --orange-l:#ffedd5;
    --s50:#f8fafc;--s100:#f1f5f9;--s200:#e2e8f0;--s300:#cbd5e1;
    --s400:#94a3b8;--s500:#64748b;--s600:#475569;--s700:#334155;
    --s800:#1e293b;--s900:#0f172a;
    --font:'Plus Jakarta Sans',sans-serif;
    --mono:'JetBrains Mono',monospace;
    --r:8px;--rlg:14px;--rxl:18px;
    --sh-sm:0 1px 4px rgba(0,0,0,.06);
    --sh:0 4px 20px rgba(0,0,0,.09);
    --topbar-h:56px;--sidebar-w:260px;
    --tx:.16s cubic-bezier(.4,0,.2,1);
}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
html{font-size:14px;}
body{font-family:var(--font);background:#eef2f7;color:var(--s800);min-height:100vh;display:flex;flex-direction:column;}
::-webkit-scrollbar{width:4px;height:4px;}::-webkit-scrollbar-thumb{background:var(--s300);border-radius:10px;}

/* ── TOPBAR ── */
.topbar{background:var(--navy);border-bottom:2px solid var(--teal);height:var(--topbar-h);padding:0 22px;display:flex;align-items:center;justify-content:space-between;position:fixed;top:0;left:0;right:0;z-index:300;box-shadow:0 4px 30px rgba(0,0,0,.3);}
.tb-brand{display:flex;align-items:center;gap:10px;text-decoration:none;}
.tb-logo{width:32px;height:32px;background:var(--teal);border-radius:8px;display:flex;align-items:center;justify-content:center;color:#fff;font-weight:800;font-size:1rem;}
.tb-text strong{display:block;color:#fff;font-size:.88rem;font-weight:700;}
.tb-text span{font-size:.58rem;color:rgba(255,255,255,.32);text-transform:uppercase;letter-spacing:.7px;}
.tb-right{display:flex;align-items:center;gap:10px;}
.tb-chip{background:rgba(13,148,136,.2);border:1px solid rgba(13,148,136,.35);color:var(--teal-l);border-radius:100px;padding:3px 10px;font-size:.64rem;font-weight:700;text-transform:uppercase;letter-spacing:.4px;}
.back-btn{background:rgba(255,255,255,.07);border:1px solid rgba(255,255,255,.1);color:rgba(255,255,255,.65);border-radius:var(--r);padding:5px 12px;font-size:.72rem;font-weight:600;text-decoration:none;display:inline-flex;align-items:center;gap:4px;transition:all .2s;font-family:var(--font);}
.back-btn:hover{background:rgba(255,255,255,.14);color:#fff;}

/* ── LAYOUT ── */
.layout{display:flex;margin-top:var(--topbar-h);min-height:calc(100vh - var(--topbar-h));}

/* ── SIDEBAR ── */
.sidebar{width:var(--sidebar-w);background:var(--navy2);flex-shrink:0;position:fixed;top:var(--topbar-h);left:0;bottom:0;overflow-y:auto;z-index:200;border-right:1px solid rgba(255,255,255,.05);display:flex;flex-direction:column;}
.sb-section{padding:14px 10px 6px;}
.sb-label{font-size:.56rem;font-weight:800;text-transform:uppercase;letter-spacing:.12em;color:rgba(255,255,255,.22);padding:0 8px;margin-bottom:5px;}
.sb-nav{display:flex;flex-direction:column;gap:1px;}
.sb-item{display:flex;align-items:center;gap:10px;padding:9px 10px;border-radius:var(--r);color:rgba(255,255,255,.48);font-size:.8rem;font-weight:600;cursor:pointer;border:1px solid transparent;transition:all var(--tx);background:transparent;width:100%;text-align:left;font-family:var(--font);text-decoration:none;}
.sb-item:hover{background:rgba(255,255,255,.05);color:rgba(255,255,255,.8);}
.sb-item.active{background:rgba(13,148,136,.14);border-color:rgba(13,148,136,.28);color:#fff;}
.sb-item.active .sb-icon{background:rgba(13,148,136,.25);color:var(--teal-l);}
.sb-icon{width:28px;height:28px;border-radius:6px;display:flex;align-items:center;justify-content:center;background:rgba(255,255,255,.05);color:rgba(255,255,255,.38);font-size:.82rem;flex-shrink:0;transition:all var(--tx);}
.sb-text{flex:1;line-height:1.3;}
.sb-name{display:block;}
.sb-sub{display:block;font-size:.61rem;font-weight:400;color:rgba(255,255,255,.28);margin-top:1px;}
.sb-item.active .sb-sub{color:rgba(255,255,255,.42);}
.sb-badge{font-family:var(--mono);font-size:.62rem;font-weight:700;min-width:20px;height:18px;border-radius:100px;display:flex;align-items:center;justify-content:center;padding:0 5px;flex-shrink:0;}
.badge-teal{background:rgba(13,148,136,.25);color:var(--teal-l);}
.sb-hr{height:1px;background:rgba(255,255,255,.06);margin:6px 10px;}
.sb-step{display:flex;align-items:flex-start;gap:8px;padding:7px 10px;font-size:.74rem;color:rgba(255,255,255,.38);}
.step-num{width:20px;height:20px;border-radius:50%;border:1.5px solid rgba(255,255,255,.18);display:flex;align-items:center;justify-content:center;font-size:.6rem;font-weight:800;flex-shrink:0;margin-top:1px;}
.step-num.done{background:var(--teal);border-color:var(--teal);color:#fff;}
.step-num.current{background:var(--navy3);border-color:var(--teal);color:var(--teal-l);}
.step-txt strong{display:block;color:rgba(255,255,255,.65);}
.step-txt span{font-size:.61rem;color:rgba(255,255,255,.28);}
.sb-foot{margin-top:auto;padding:12px 10px;border-top:1px solid rgba(255,255,255,.05);}
.sb-stats{background:rgba(13,148,136,.07);border:1px solid rgba(13,148,136,.15);border-radius:var(--rlg);padding:11px 12px;}
.sb-stat-lbl{font-size:.58rem;color:rgba(255,255,255,.28);text-transform:uppercase;letter-spacing:.08em;margin-bottom:7px;}
.sb-stat-row{display:flex;justify-content:space-between;font-size:.73rem;color:rgba(255,255,255,.4);padding:2px 0;}
.sb-stat-row strong{color:#fff;font-family:var(--mono);}

/* ── MAIN ── */
.main{flex:1;margin-left:var(--sidebar-w);padding:24px;}
.ph{margin-bottom:20px;display:flex;align-items:flex-start;justify-content:space-between;flex-wrap:wrap;gap:10px;}
.ph h1{font-size:1.3rem;font-weight:800;color:var(--s900);letter-spacing:-.3px;margin-bottom:2px;}
.ph p{color:var(--s500);font-size:.82rem;}

/* ── FLASH ── */
.flash{display:flex;align-items:flex-start;gap:9px;padding:11px 16px;border-radius:var(--rlg);margin-bottom:18px;font-size:.82rem;font-weight:600;animation:fadeIn .3s ease;}
@keyframes fadeIn{from{opacity:0;transform:translateY(-4px)}to{opacity:1;transform:none}}
.flash-ok{background:var(--green-l);color:#064e3b;border:1px solid #6ee7b7;}
.flash-err{background:var(--red-l);color:#7f1d1d;border:1px solid #fca5a5;}

/* ── CARD ── */
.card{background:#fff;border:1px solid var(--s200);border-radius:var(--rxl);overflow:hidden;margin-bottom:16px;box-shadow:var(--sh-sm);}
.card-hd{padding:13px 18px;display:flex;align-items:center;gap:10px;border-bottom:1px solid var(--s100);background:var(--s50);}
.card-icon{width:30px;height:30px;border-radius:7px;display:flex;align-items:center;justify-content:center;font-size:.84rem;flex-shrink:0;}
.ci-teal{background:var(--teal-xl);color:var(--teal);}
.ci-green{background:var(--green-l);color:var(--green);}
.ci-amber{background:var(--amber-l);color:var(--amber);}
.ci-red{background:var(--red-l);color:var(--red);}
.ci-blue{background:var(--blue-l);color:var(--blue);}
.ci-purple{background:var(--purple-l);color:var(--purple);}
.ci-navy{background:rgba(11,31,58,.1);color:var(--navy);}
.card-hd-text h3{font-size:.87rem;font-weight:800;color:var(--s800);}
.card-hd-text p{font-size:.7rem;color:var(--s400);margin-top:1px;}
.card-hd-badge{margin-left:auto;font-size:.67rem;font-weight:700;padding:2px 9px;border-radius:100px;}
.card-body{padding:18px;}

/* ── FORM ── */
.fg2{display:grid;grid-template-columns:1fr 1fr;gap:12px;}
.fg3{display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;}
.fg4{display:grid;grid-template-columns:1fr 1fr 1fr 1fr;gap:11px;}
.field{display:flex;flex-direction:column;gap:3px;}
.field label{font-size:.6rem;font-weight:800;text-transform:uppercase;letter-spacing:.08em;color:var(--s500);}
.field label .req{color:var(--red);}
.field input,.field select,.field textarea{padding:8px 10px;border:1.5px solid var(--s200);border-radius:var(--r);font-family:var(--font);font-size:.82rem;color:var(--s800);background:#fff;transition:all var(--tx);width:100%;}
.field input:focus,.field select:focus,.field textarea:focus{outline:none;border-color:var(--teal);box-shadow:0 0 0 3px rgba(13,148,136,.1);}
.field textarea{resize:vertical;min-height:60px;}
.field input[readonly]{background:var(--s50);color:var(--s600);}
.hint{font-size:.62rem;color:var(--s400);margin-top:2px;}

/* ── ADMISSION TYPE CARDS ── */
.adm-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:10px;margin-bottom:8px;}
.adm-card{position:relative;}
.adm-card input{position:absolute;opacity:0;width:0;height:0;}
.adm-lbl{display:flex;flex-direction:column;align-items:center;justify-content:center;gap:8px;padding:18px 10px;border:2px solid var(--s200);border-radius:var(--rlg);background:#fff;cursor:pointer;transition:all .18s;min-height:105px;text-align:center;}
.adm-lbl:hover{border-color:var(--s300);background:var(--s50);}
.adm-card input:checked + .adm-lbl{border-color:var(--teal);background:var(--teal-xl);box-shadow:0 0 0 3px rgba(13,148,136,.13);}
.adm-icon{width:40px;height:40px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:1.15rem;transition:transform .18s;}
.ai-em{background:#fef2f2;color:#ef4444;}
.ai-co{background:#eff6ff;color:#3b82f6;}
.ai-su{background:#fff7ed;color:#f97316;}
.ai-ck{background:var(--teal-xl);color:var(--teal);}
.adm-name{font-size:.82rem;font-weight:800;color:var(--s700);}
.adm-desc{font-size:.63rem;color:var(--s400);}
.adm-card input:checked + .adm-lbl .adm-name{color:var(--teal);}
.adm-card input:checked + .adm-lbl .adm-icon{transform:scale(1.08);}

/* ── ROOM GRID ── */
.room-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(175px,1fr));gap:9px;}
.room-card{position:relative;}
.room-card input{position:absolute;opacity:0;width:0;height:0;}
.room-lbl{padding:12px 14px;border:1.5px solid var(--s200);border-radius:var(--rlg);background:#fff;cursor:pointer;transition:all var(--tx);}
.room-lbl:hover{border-color:var(--s300);}
.room-card input:checked + .room-lbl{border-color:var(--teal);background:var(--teal-xl);box-shadow:0 0 0 2px rgba(13,148,136,.12);}
.room-name{font-size:.8rem;font-weight:800;color:var(--s800);}
.room-cap{font-size:.63rem;color:var(--s400);margin-top:1px;}
.room-rates{display:flex;justify-content:space-between;margin-top:7px;font-size:.68rem;}
.room-rates span{color:var(--s500);}
.room-rates strong{font-family:var(--mono);color:var(--teal);}
.room-card input:checked + .room-lbl .room-name{color:var(--teal);}

/* ── STATUS PILLS ── */
.pill{display:inline-flex;align-items:center;gap:3px;border-radius:100px;padding:2px 9px;font-size:.65rem;font-weight:800;white-space:nowrap;}
.pill-admitted{background:#dbeafe;color:#1e40af;}
.pill-discharged{background:var(--green-l);color:#065f46;}
.pill-transferred{background:var(--amber-l);color:#92400e;}
.pill-hama{background:var(--orange-l);color:#9a3412;}
.pill-deceased{background:var(--red-l);color:#7f1d1d;}
.pill-pending{background:var(--amber-l);color:#92400e;}
.pill-partial{background:var(--purple-l);color:#5b21b6;}
.pill-paid{background:var(--green-l);color:#065f46;}
.pill-em{background:#fef2f2;color:#b91c1c;}
.pill-co{background:#eff6ff;color:#1d4ed8;}
.pill-su{background:#fff7ed;color:#c2410c;}
.pill-ck{background:var(--teal-xl);color:var(--teal);}

/* ── STAT STRIP ── */
.stat-strip{display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-bottom:20px;}
.stat-card{background:#fff;border:1px solid var(--s200);border-radius:var(--rlg);padding:13px 16px;display:flex;align-items:center;gap:12px;box-shadow:var(--sh-sm);}
.stat-ico{width:34px;height:34px;border-radius:var(--r);display:flex;align-items:center;justify-content:center;font-size:.9rem;flex-shrink:0;}
.stat-info .lbl{font-size:.6rem;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:var(--s400);margin-bottom:2px;}
.stat-info .val{font-size:1.45rem;font-weight:800;color:var(--s800);line-height:1;font-family:var(--mono);}

/* ── TABLE ── */
.tbl-wrap{background:#fff;border:1px solid var(--s200);border-radius:var(--rxl);overflow:hidden;box-shadow:var(--sh-sm);}
.ptbl{width:100%;border-collapse:collapse;}
.ptbl thead{background:var(--navy);}
.ptbl thead th{padding:10px 14px;font-size:.61rem;font-weight:800;text-transform:uppercase;letter-spacing:.08em;color:rgba(255,255,255,.4);text-align:left;white-space:nowrap;}
.ptbl tbody tr{border-bottom:1px solid var(--s100);transition:background .12s;}
.ptbl tbody tr:last-child{border-bottom:none;}
.ptbl tbody tr:hover{background:var(--s50);}
.ptbl tbody td{padding:10px 14px;font-size:.81rem;color:var(--s700);vertical-align:middle;}
.reg-no{font-family:var(--mono);font-size:.71rem;font-weight:700;background:var(--navy);color:rgba(255,255,255,.75);border-radius:4px;padding:3px 7px;}

/* ── BUTTONS ── */
.btn{display:inline-flex;align-items:center;gap:4px;border-radius:var(--r);padding:6px 13px;font-size:.75rem;font-weight:700;cursor:pointer;border:none;font-family:var(--font);text-decoration:none;transition:all .18s;white-space:nowrap;}
.btn-primary{background:var(--teal);color:#fff;}
.btn-primary:hover{filter:brightness(1.1);transform:translateY(-1px);}
.btn-dark{background:var(--navy);color:#fff;}
.btn-dark:hover{background:var(--navy3);}
.btn-outline{background:#fff;color:var(--s600);border:1.5px solid var(--s200);}
.btn-outline:hover{background:var(--s50);}
.btn-danger{background:var(--red-l);color:var(--red);border:1px solid #fca5a5;}
.btn-danger:hover{background:var(--red);color:#fff;}
.btn-success{background:var(--green);color:#fff;}
.btn-success:hover{filter:brightness(1.1);}
.btn-amber{background:var(--amber-l);color:var(--amber);border:1px solid #fcd34d;}
.btn-amber:hover{background:var(--amber);color:#fff;}
.btn-cash{background:var(--green-l);color:var(--green);border:1px solid #6ee7b7;}
.btn-cash:hover{background:var(--green);color:#fff;}
.btn-lg{padding:10px 22px;font-size:.84rem;border-radius:var(--rlg);}
.btn-icon{width:28px;height:28px;padding:0;justify-content:center;border-radius:6px;}

/* ── TOOLBAR ── */
.toolbar{display:flex;align-items:center;gap:10px;margin-bottom:14px;flex-wrap:wrap;}
.search-box{flex:1;min-width:200px;position:relative;}
.search-box i{position:absolute;left:9px;top:50%;transform:translateY(-50%);color:var(--s400);pointer-events:none;font-size:.8rem;}
.search-box input{width:100%;padding:7px 10px 7px 29px;border:1.5px solid var(--s200);border-radius:var(--r);font-family:var(--font);font-size:.81rem;color:var(--s700);}
.search-box input:focus{outline:none;border-color:var(--teal);}
.filter-sel{padding:7px 10px;border:1.5px solid var(--s200);border-radius:var(--r);font-family:var(--font);font-size:.8rem;color:var(--s700);background:#fff;min-width:120px;}

/* ── PATIENT BAR ── */
.pat-bar{background:var(--navy);border-radius:var(--rxl);padding:15px 20px;margin-bottom:18px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;}
.pat-bar-left{display:flex;align-items:center;gap:12px;}
.pat-avatar{width:42px;height:42px;border-radius:50%;background:rgba(13,148,136,.3);color:var(--teal-l);display:flex;align-items:center;justify-content:center;font-size:1rem;font-weight:800;flex-shrink:0;border:2px solid rgba(13,148,136,.4);}
.pat-name{color:#fff;font-size:1rem;font-weight:800;}
.pat-meta{display:flex;align-items:center;gap:6px;flex-wrap:wrap;margin-top:4px;}
.pat-chip{background:rgba(255,255,255,.1);color:rgba(255,255,255,.65);border-radius:4px;padding:2px 8px;font-size:.67rem;font-weight:600;}
.pat-bar-right{text-align:right;}
.pat-reg{font-family:var(--mono);font-size:.82rem;font-weight:700;color:var(--teal-l);}
.pat-adm-date{font-size:.7rem;color:rgba(255,255,255,.38);margin-top:2px;}

/* ── ORDERS TABLE ── */
.ord-wrap{border:1px solid var(--s200);border-radius:var(--rlg);overflow:hidden;}
.ord-tbl{width:100%;border-collapse:collapse;}
.ord-tbl thead th{background:var(--s100);color:var(--s500);font-size:.6rem;font-weight:800;text-transform:uppercase;letter-spacing:.07em;padding:7px 11px;border-bottom:1px solid var(--s200);text-align:left;}
.ord-tbl tbody td{padding:8px 11px;border-bottom:1px solid var(--s50);font-size:.8rem;color:var(--s700);vertical-align:middle;}
.ord-tbl tbody tr:last-child td{border-bottom:none;}
.ord-tbl tbody tr:hover{background:var(--s50);}
.qty-inp{width:60px;padding:4px 6px;border:1.5px solid var(--s200);border-radius:5px;font-family:var(--mono);font-size:.77rem;text-align:center;background:#fff;color:var(--s800);}
.qty-inp:focus{outline:none;border-color:var(--teal);}
.lt-val{font-family:var(--mono);font-size:.8rem;font-weight:700;color:var(--teal);}
.type-tag{display:inline-flex;align-items:center;gap:3px;border-radius:100px;padding:2px 7px;font-size:.63rem;font-weight:700;}
.tt-svc{background:#dbeafe;color:#1e40af;}
.tt-med{background:var(--green-l);color:#065f46;}
.tt-sup{background:var(--purple-l);color:#5b21b6;}
.empty-state{text-align:center;padding:28px;color:var(--s400);font-size:.8rem;}
.empty-state i{font-size:1.5rem;display:block;margin-bottom:6px;color:var(--s300);}

/* ── SERVICE SELECTOR PANEL ── */
.orders-panel{background:var(--s50);border:1.5px solid var(--s200);border-radius:var(--rlg);overflow:hidden;margin-bottom:12px;}
.op-head{display:flex;align-items:center;justify-content:space-between;padding:10px 14px;background:#fff;border-bottom:1px solid var(--s100);cursor:pointer;user-select:none;}
.op-head-l{display:flex;align-items:center;gap:8px;}
.op-label{font-size:.79rem;font-weight:800;color:var(--s700);}
.op-count{font-family:var(--mono);font-size:.66rem;font-weight:700;background:var(--teal-xl);color:var(--teal);border-radius:100px;padding:2px 8px;}
.op-tog{color:var(--s400);font-size:.76rem;transition:transform .18s;}
.op-tog.open{transform:rotate(180deg);}
.op-body{display:none;padding:13px 14px;}
.op-body.open{display:block;}
.svc-search{position:relative;margin-bottom:9px;}
.svc-search i{position:absolute;left:9px;top:50%;transform:translateY(-50%);color:var(--s400);font-size:.78rem;}
.svc-search input{width:100%;padding:7px 10px 7px 29px;border:1.5px solid var(--s200);border-radius:var(--r);font-family:var(--font);font-size:.8rem;}
.svc-search input:focus{outline:none;border-color:var(--teal);}
.cat-pills{display:flex;gap:4px;flex-wrap:wrap;margin-bottom:9px;}
.cat-pill{padding:3px 10px;border-radius:100px;font-size:.69rem;font-weight:700;cursor:pointer;border:1.5px solid var(--s200);background:#fff;color:var(--s500);transition:all .12s;font-family:var(--font);}
.cat-pill.active{background:var(--navy);color:#fff;border-color:var(--navy);}
.svc-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(175px,1fr));gap:6px;max-height:260px;overflow-y:auto;padding:2px;}
.svc-chip{display:flex;flex-direction:column;gap:2px;padding:8px 10px;background:#fff;border:1.5px solid var(--s200);border-radius:var(--rlg);cursor:pointer;transition:all .12s;position:relative;}
.svc-chip:hover{border-color:var(--teal);background:var(--teal-xl);}
.svc-chip.sel{border-color:var(--teal);background:var(--teal-xl);}
.svc-chip-name{font-size:.77rem;font-weight:700;color:var(--s800);line-height:1.3;padding-right:14px;}
.svc-chip-cat{font-size:.62rem;color:var(--s400);}
.svc-chip-price{font-family:var(--mono);font-size:.77rem;font-weight:600;color:var(--teal);}
.svc-chk{position:absolute;top:7px;right:8px;color:var(--teal);font-size:.72rem;display:none;}
.svc-chip.sel .svc-chk{display:block;}

/* ── ROOM CHANGE CARD ── */
.room-change-bar{background:var(--s50);border:1.5px solid var(--s200);border-radius:var(--rxl);padding:15px 18px;margin-bottom:16px;}
.room-change-bar .rch-hd{display:flex;align-items:center;justify-content:space-between;margin-bottom:10px;}
.room-change-bar .rch-hd h4{font-size:.82rem;font-weight:800;color:var(--s800);display:flex;align-items:center;gap:6px;}
.rch-current{font-size:.75rem;color:var(--s500);background:#fff;border:1px solid var(--s200);border-radius:var(--r);padding:4px 10px;}

/* ── LIVE SUMMARY ── */
.live-sum{background:var(--navy);border-radius:var(--rxl);overflow:hidden;margin-bottom:18px;}
.ls-hd{padding:13px 18px 10px;border-bottom:1px solid rgba(255,255,255,.07);display:flex;align-items:center;justify-content:space-between;}
.ls-hd h3{font-size:.88rem;font-weight:800;color:#fff;display:flex;align-items:center;gap:6px;}
.live-dot{width:7px;height:7px;border-radius:50%;background:var(--teal-l);animation:pulse 2s infinite;display:inline-block;}
@keyframes pulse{0%,100%{opacity:1}50%{opacity:.4}}
.ls-body{padding:16px 18px;}
.ls-row{display:flex;justify-content:space-between;align-items:center;padding:5px 0;font-size:.8rem;border-bottom:1px solid rgba(255,255,255,.05);}
.ls-row:last-child{border-bottom:none;}
.ls-lbl{color:rgba(255,255,255,.45);display:flex;align-items:center;gap:5px;}
.ls-val{font-family:var(--mono);font-weight:600;color:rgba(255,255,255,.72);}
.ls-div{height:1px;background:rgba(255,255,255,.1);margin:6px 0;}
.ls-total{display:flex;justify-content:space-between;align-items:center;padding:8px 0 2px;}
.ls-total-lbl{font-size:.72rem;font-weight:700;color:rgba(255,255,255,.42);text-transform:uppercase;letter-spacing:.06em;}
.ls-total-val{font-family:var(--mono);font-size:1.4rem;font-weight:700;color:#fff;}
.ls-note{font-size:.68rem;color:rgba(255,255,255,.3);padding-top:4px;font-style:italic;}

/* ── SUB BAR ── */
.sub-bar{background:#fff;border:1px solid var(--s200);border-radius:var(--rxl);padding:14px 20px;display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;box-shadow:var(--sh);position:sticky;bottom:16px;z-index:10;}
.sub-info strong{display:block;font-size:.85rem;font-weight:800;color:var(--s800);margin-bottom:1px;}
.sub-info small{color:var(--s400);font-size:.71rem;}

/* ── CASH PAYMENT PANEL ── */
.cash-pay-panel{background:linear-gradient(135deg,#ecfdf5 0%,#d1fae5 100%);border:2px solid #6ee7b7;border-radius:var(--rxl);padding:16px 20px;margin-top:16px;display:flex;align-items:center;justify-content:space-between;gap:14px;flex-wrap:wrap;}
.cpp-left{display:flex;align-items:center;gap:12px;}
.cpp-ico{width:42px;height:42px;border-radius:50%;background:var(--green);color:#fff;display:flex;align-items:center;justify-content:center;font-size:1.1rem;flex-shrink:0;}
.cpp-title{font-size:.88rem;font-weight:800;color:#064e3b;}
.cpp-sub{font-size:.73rem;color:#047857;margin-top:1px;}
.cpp-amt{font-family:var(--mono);font-size:1.2rem;font-weight:800;color:var(--green);}

/* ── PAYMENT METHOD SELECTOR in Finalize Modal ── */
.pay-method-grid{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-top:8px;}
.pay-method-opt{border:2px solid var(--s200);border-radius:var(--rlg);padding:13px 14px;cursor:pointer;transition:all .15s;display:flex;align-items:center;gap:10px;background:#fff;}
.pay-method-opt:hover{border-color:var(--s300);}
.pay-method-opt input[type=radio]{accent-color:var(--teal);width:15px;height:15px;flex-shrink:0;}
.pay-method-opt.opt-cash{border-color:#6ee7b7;background:var(--green-l);}
.pay-method-opt.opt-cash .pay-method-label{color:#065f46;}
.pay-method-opt.opt-online{border-color:#93c5fd;background:var(--blue-l);}
.pay-method-opt.opt-online .pay-method-label{color:#1e40af;}
.pay-method-ico{width:28px;height:28px;border-radius:7px;display:flex;align-items:center;justify-content:center;font-size:.88rem;flex-shrink:0;}
.pmi-cash{background:var(--green);color:#fff;}
.pmi-online{background:var(--blue);color:#fff;}
.pay-method-label{font-size:.8rem;font-weight:800;}
.pay-method-desc{font-size:.65rem;color:var(--s400);margin-top:1px;}

/* ── RECEIPT ── */
.receipt-wrap{max-width:820px;margin:0 auto;}
.receipt{background:#fff;border:1px solid var(--s200);border-radius:var(--rxl);overflow:hidden;box-shadow:var(--sh);}
.rcp-hd{background:var(--navy);padding:24px 28px;display:flex;align-items:flex-start;justify-content:space-between;gap:16px;}
.rcp-hosp h2{font-size:1.2rem;font-weight:800;color:#fff;letter-spacing:-.25px;margin-bottom:2px;}
.rcp-hosp span{font-size:.7rem;color:rgba(255,255,255,.32);}
.rcp-billno{font-family:var(--mono);font-size:.9rem;font-weight:700;color:var(--teal-l);}
.rcp-date{font-size:.68rem;color:rgba(255,255,255,.32);margin-top:2px;text-align:right;}
.rcp-patient{display:grid;grid-template-columns:repeat(4,1fr);border-bottom:1px solid var(--s100);}
.rcp-cell{padding:11px 16px;border-right:1px solid var(--s100);}
.rcp-cell:last-child{border-right:none;}
.rcp-lbl{font-size:.58rem;font-weight:800;text-transform:uppercase;letter-spacing:.08em;color:var(--s400);margin-bottom:2px;}
.rcp-val{font-size:.82rem;font-weight:700;color:var(--s800);}
.rcp-body{padding:20px 26px;}
.rcp-sec{font-size:.62rem;font-weight:800;text-transform:uppercase;letter-spacing:.1em;color:var(--s400);margin:14px 0 7px;display:flex;align-items:center;gap:5px;}
.rcp-sec::after{content:'';flex:1;height:1px;background:var(--s100);}
.rcp-sec:first-child{margin-top:0;}
.rtbl{width:100%;border-collapse:collapse;}
.rtbl th{font-size:.6rem;font-weight:800;text-transform:uppercase;letter-spacing:.06em;color:var(--s400);padding:5px 9px;border-bottom:1.5px solid var(--s200);text-align:left;}
.rtbl th:last-child{text-align:right;}
.rtbl td{padding:7px 9px;border-bottom:1px solid var(--s50);font-size:.8rem;color:var(--s700);}
.rtbl td:last-child{text-align:right;font-family:var(--mono);font-weight:600;color:var(--teal);}
.rtbl tbody tr:last-child td{border-bottom:none;}
.rcp-totals{background:var(--s50);border:1px solid var(--s200);border-radius:var(--rlg);padding:14px 17px;margin-top:14px;}
.rt-row{display:flex;justify-content:space-between;align-items:center;padding:3px 0;font-size:.81rem;color:var(--s600);}
.rt-row span:last-child{font-family:var(--mono);font-weight:600;color:var(--s700);}
.rt-div{border-top:1.5px solid var(--s200);margin:6px 0;padding-top:6px;}
.rt-grand{background:var(--navy);border-radius:var(--rlg);padding:12px 15px;margin-top:9px;display:flex;justify-content:space-between;align-items:center;}
.rt-grand-lbl{color:rgba(255,255,255,.5);font-size:.75rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;}
.rt-grand-val{font-family:var(--mono);font-size:1.4rem;font-weight:700;color:#fff;}
.rcp-ft{padding:12px 26px 18px;border-top:1px solid var(--s100);display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:9px;}
.discharge-strip{background:var(--green-l);border:1px solid #6ee7b7;border-radius:var(--rlg);padding:10px 16px;margin-bottom:14px;display:flex;align-items:center;gap:10px;flex-wrap:wrap;}
.ds-item{font-size:.78rem;color:#064e3b;}
.ds-item strong{font-weight:800;}
.ds-sep{color:#6ee7b7;}

/* ── MODAL ── */
.modal-bg{display:none;position:fixed;inset:0;background:rgba(11,31,58,.65);z-index:500;align-items:center;justify-content:center;backdrop-filter:blur(3px);padding:20px;}
.modal-bg.open{display:flex;}
.modal-box{background:#fff;border-radius:var(--rxl);width:100%;max-width:400px;overflow:hidden;box-shadow:0 24px 60px rgba(0,0,0,.3);animation:mIn .2s cubic-bezier(.34,1.56,.64,1);}
@keyframes mIn{from{opacity:0;transform:scale(.94) translateY(6px)}to{opacity:1;transform:none}}
.mhd{background:var(--navy);padding:14px 18px;display:flex;align-items:center;justify-content:space-between;}
.mhd h3{color:#fff;font-size:.88rem;font-weight:800;display:flex;align-items:center;gap:6px;}
.mhd-x{width:26px;height:26px;border-radius:50%;background:rgba(255,255,255,.1);border:1px solid rgba(255,255,255,.14);color:rgba(255,255,255,.6);display:flex;align-items:center;justify-content:center;cursor:pointer;font-size:.84rem;transition:all .15s;}
.mhd-x:hover{background:rgba(255,255,255,.2);color:#fff;}
.mbd{padding:17px 18px;}
.mft{padding:11px 18px;background:var(--s50);border-top:1px solid var(--s200);display:flex;justify-content:flex-end;gap:7px;}
.warn-box{background:var(--amber-l);border:1px solid #fcd34d;border-radius:var(--r);padding:9px 12px;font-size:.78rem;color:#92400e;margin-bottom:14px;display:flex;align-items:flex-start;gap:7px;}
.fin-check{display:flex;align-items:center;gap:6px;font-size:.8rem;color:var(--s700);cursor:pointer;margin-bottom:11px;}
.fin-check input{width:16px;height:16px;accent-color:var(--teal);}

/* ── CASH CONFIRM MODAL ── */
#cashConfirmModal .modal-box{max-width:360px;}
.cash-modal-body{text-align:center;padding:24px 20px;}
.cash-modal-icon{width:58px;height:58px;border-radius:50%;background:var(--green-l);color:var(--green);display:flex;align-items:center;justify-content:center;font-size:1.6rem;margin:0 auto 14px;}
.cash-modal-title{font-size:1rem;font-weight:800;color:var(--s900);margin-bottom:6px;}
.cash-modal-amt{font-family:var(--mono);font-size:1.6rem;font-weight:800;color:var(--green);margin:10px 0;}
.cash-modal-sub{font-size:.79rem;color:var(--s500);}

.d-none{display:none!important;}
@media(max-width:900px){.adm-grid{grid-template-columns:1fr 1fr;}.stat-strip{grid-template-columns:1fr 1fr;}.rcp-patient{grid-template-columns:1fr 1fr;}.pay-method-grid{grid-template-columns:1fr;}}
@media(max-width:768px){.main{margin-left:0;padding:14px;}.fg2,.fg3,.fg4{grid-template-columns:1fr;}.sub-bar{flex-direction:column;}.btn-lg{width:100%;justify-content:center;}}
@media print{.topbar,.sidebar,.ph,.toolbar,.sub-bar,.orders-panel,.room-change-bar{display:none!important;}.main{margin-left:0;padding:0;}body{background:#fff;}.receipt-wrap{max-width:100%;}}
</style>
</head>
<body>

<header class="topbar">
    <a href="#" class="tb-brand">
        <div class="tb-logo"><i class="bi bi-hospital"></i></div>
        <div class="tb-text"><strong>HMS · Inpatient Billing</strong><span>Hospital Management System</span></div>
    </a>
    <div class="tb-right">
        <span style="font-size:.7rem;color:rgba(255,255,255,.3);display:flex;align-items:center;gap:4px;"><i class="bi bi-calendar3"></i><?= date('F j, Y') ?></span>
        <span class="tb-chip"><i class="bi bi-shield-check" style="margin-right:3px;"></i>Billing Officer</span>
        <a href="#" class="back-btn"><i class="bi bi-arrow-left"></i> Dashboard</a>
    </div>
</header>

<div class="layout">

<!-- SIDEBAR -->
<aside class="sidebar">
    <div class="sb-section">
        <div class="sb-label">Workflow</div>
        <nav class="sb-nav">
            <button class="sb-item <?= $currentView==='registration'?'active':'' ?>" onclick="switchView('registration')">
                <div class="sb-icon"><i class="bi bi-person-plus-fill"></i></div>
                <div class="sb-text"><span class="sb-name">1. Register Patient</span><span class="sb-sub">Demographics &amp; service type</span></div>
            </button>
            <button class="sb-item <?= $currentView==='patients'?'active':'' ?>" onclick="switchView('patients')">
                <div class="sb-icon"><i class="bi bi-clipboard-pulse"></i></div>
                <div class="sb-text"><span class="sb-name">2. Inpatient List</span><span class="sb-sub">View all admitted patients</span></div>
                <?php if ($totalAdmitted > 0): ?><span class="sb-badge badge-teal"><?= $totalAdmitted ?></span><?php endif; ?>
            </button>
            <button class="sb-item <?= $currentView==='chart'?'active':'' ?>" onclick="switchView('chart')">
                <div class="sb-icon"><i class="bi bi-file-earmark-medical"></i></div>
                <div class="sb-text"><span class="sb-name">3. Patient Chart</span><span class="sb-sub">Add meds, services, room</span></div>
            </button>
            <button class="sb-item <?= $currentView==='receipt'?'active':'' ?>" onclick="switchView('receipt')">
                <div class="sb-icon"><i class="bi bi-receipt-cutoff"></i></div>
                <div class="sb-text"><span class="sb-name">4. Receipt / SOA</span><span class="sb-sub">Final billing statement</span></div>
            </button>
        </nav>
    </div>

    <div class="sb-hr"></div>
    <div class="sb-section">
        <div class="sb-label">Process Flow</div>
        <?php
        $steps = [
            ['registration', 'Registration', 'Demographics & type'],
            ['patients',     'Inpatient List', 'Assign room & physician'],
            ['chart',        'Add Charges', 'Medicines, services, supplies'],
            ['receipt',      'Finalize & Discharge', 'Generate SOA'],
        ];
        $stepViews = array_column($steps, 0);
        $cvIdx     = array_search($currentView, $stepViews);
        foreach ($steps as $i => [$sv, $sn, $ss]):
            $done    = $cvIdx > $i;
            $current = $cvIdx === $i;
        ?>
        <div class="sb-step">
            <div class="step-num <?= $done?'done':($current?'current':'') ?>">
                <?= $done ? '<i class="bi bi-check"></i>' : ($i + 1) ?>
            </div>
            <div class="step-txt"><strong><?= $sn ?></strong><span><?= $ss ?></span></div>
        </div>
        <?php endforeach; ?>
    </div>

    <div class="sb-hr"></div>
    <div class="sb-section">
        <div class="sb-label">Masters</div>
        <nav class="sb-nav">
            <a href="billing_masters_admin.php?page=rooms"    class="sb-item"><div class="sb-icon"><i class="bi bi-door-open"></i></div><span>Room Types</span></a>
            <a href="billing_masters_admin.php?page=services" class="sb-item"><div class="sb-icon"><i class="bi bi-grid-3x3-gap"></i></div><span>Services / Items</span></a>
        </nav>
    </div>

    <div class="sb-foot">
        <div class="sb-stats">
            <div class="sb-stat-lbl">Census</div>
            <div class="sb-stat-row"><span><i class="bi bi-person-check" style="color:#60a5fa;margin-right:3px;"></i>Admitted</span><strong><?= $totalAdmitted ?></strong></div>
            <div class="sb-stat-row"><span><i class="bi bi-door-open-fill" style="color:#34d399;margin-right:3px;"></i>Discharged</span><strong><?= $totalDischarged ?></strong></div>
            <div class="sb-stat-row"><span><i class="bi bi-clock-fill" style="color:#fbbf24;margin-right:3px;"></i>Unpaid</span><strong><?= $totalPending ?></strong></div>
        </div>
    </div>
</aside>

<main class="main">

<?php if ($flashMsg): ?>
<div class="flash flash-<?= $flashType ?>"><i class="bi bi-<?= $flashType==='ok'?'check-circle-fill':'exclamation-triangle-fill' ?>"></i> <?= safe($flashMsg) ?></div>
<?php endif; ?>

<!-- ══════════════════════════════════════════
     VIEW 1: REGISTRATION
══════════════════════════════════════════ -->
<div id="view-registration" class="<?= $currentView!=='registration'?'d-none':'' ?>">
<div class="ph">
    <div>
        <h1><i class="bi bi-person-plus-fill" style="color:var(--teal);margin-right:7px;"></i>Patient Registration</h1>
        <p>Step 1 — Register patient demographics and choose admission service type. Room assignment can be set now or updated from the chart.</p>
    </div>
</div>

<form method="post" id="regForm">
<input type="hidden" name="action" value="register_patient">

<!-- PERSONAL INFO -->
<div class="card">
    <div class="card-hd">
        <div class="card-icon ci-teal"><i class="bi bi-person-vcard-fill"></i></div>
        <div class="card-hd-text"><h3>Personal Information</h3><p>Patient demographics and contact details</p></div>
    </div>
    <div class="card-body">
        <div class="fg3" style="margin-bottom:12px;">
            <div class="field"><label>Last Name <span class="req">*</span></label><input type="text" name="last_name" required placeholder="dela Cruz" autofocus></div>
            <div class="field"><label>First Name <span class="req">*</span></label><input type="text" name="first_name" required placeholder="Juan"></div>
            <div class="field"><label>Middle Name</label><input type="text" name="middle_name" placeholder="Santos"></div>
        </div>
        <div class="fg4" style="margin-bottom:12px;">
            <div class="field"><label>Date of Birth <span class="req">*</span></label><input type="date" name="date_of_birth" id="dob" onchange="calcAge()" required></div>
            <div class="field"><label>Age</label><input type="text" id="ageDisplay" readonly placeholder="Auto-computed"></div>
            <div class="field"><label>Gender <span class="req">*</span></label>
                <select name="gender" required><option value="">— Select —</option><option>Male</option><option>Female</option><option>Other</option></select>
            </div>
            <div class="field"><label>Civil Status</label>
                <select name="civil_status"><option>Single</option><option>Married</option><option>Widowed</option><option>Separated</option></select>
            </div>
        </div>
        <div class="fg3" style="margin-bottom:12px;">
            <div class="field"><label>Nationality</label><input type="text" name="nationality" value="Filipino"></div>
            <div class="field"><label>Religion</label><input type="text" name="religion" placeholder="Catholic"></div>
            <div class="field"><label>Contact No.</label><input type="text" name="contact_no" placeholder="09XXXXXXXXX"></div>
        </div>
        <div class="fg2">
            <div class="field"><label>Email Address</label><input type="email" name="email" placeholder="patient@email.com"></div>
            <div class="field"><label>Complete Address <span class="req">*</span></label><input type="text" name="address" required placeholder="House No., Street, Barangay, City, Province"></div>
        </div>
    </div>
</div>

<!-- INSURANCE -->
<div class="card">
    <div class="card-hd">
        <div class="card-icon ci-green"><i class="bi bi-shield-plus"></i></div>
        <div class="card-hd-text"><h3>Insurance &amp; Government IDs</h3><p>PhilHealth and SSS/GSIS numbers</p></div>
    </div>
    <div class="card-body">
        <div class="fg2">
            <div class="field"><label>PhilHealth No.</label><input type="text" name="philhealth_no" placeholder="XX-000000000-0"></div>
            <div class="field"><label>SSS / GSIS No.</label><input type="text" name="sss_gsis_no" placeholder="XX-XXXXXXX-X"></div>
        </div>
    </div>
</div>

<!-- EMERGENCY CONTACT -->
<div class="card">
    <div class="card-hd">
        <div class="card-icon ci-red"><i class="bi bi-telephone-plus-fill"></i></div>
        <div class="card-hd-text"><h3>Emergency Contact / Next of Kin</h3></div>
    </div>
    <div class="card-body">
        <div class="fg3">
            <div class="field"><label>Full Name <span class="req">*</span></label><input type="text" name="emergency_contact_name" required placeholder="Full Name"></div>
            <div class="field"><label>Relationship</label><input type="text" name="emergency_contact_relation" placeholder="Spouse / Parent / Child"></div>
            <div class="field"><label>Contact No. <span class="req">*</span></label><input type="text" name="emergency_contact_no" required placeholder="09XXXXXXXXX"></div>
        </div>
    </div>
</div>

<!-- ADMISSION TYPE (REQUIRED) -->
<div class="card">
    <div class="card-hd">
        <div class="card-icon ci-amber"><i class="bi bi-clipboard2-pulse-fill"></i></div>
        <div class="card-hd-text"><h3>Admission / Service Type</h3><p>Choose the primary reason for this admission</p></div>
        <span class="card-hd-badge" style="background:#fef3c7;color:#92400e;" id="admTypeSelected">Not selected</span>
    </div>
    <div class="card-body">
        <div class="adm-grid">
            <label class="adm-card">
                <input type="radio" name="admission_type" value="Emergency" required onchange="onAdmTypeChange(this)">
                <div class="adm-lbl">
                    <div class="adm-icon ai-em"><i class="bi bi-lightning-charge-fill"></i></div>
                    <div class="adm-name">Emergency</div>
                    <div class="adm-desc">ER / Trauma</div>
                </div>
            </label>
            <label class="adm-card">
                <input type="radio" name="admission_type" value="Confinement" onchange="onAdmTypeChange(this)">
                <div class="adm-lbl">
                    <div class="adm-icon ai-co"><i class="bi bi-house-heart-fill"></i></div>
                    <div class="adm-name">Confinement</div>
                    <div class="adm-desc">Regular / Observation</div>
                </div>
            </label>
            <label class="adm-card">
                <input type="radio" name="admission_type" value="Surgery" onchange="onAdmTypeChange(this)">
                <div class="adm-lbl">
                    <div class="adm-icon ai-su"><i class="bi bi-scissors"></i></div>
                    <div class="adm-name">Surgery</div>
                    <div class="adm-desc">Elective / Operative</div>
                </div>
            </label>
            <label class="adm-card">
                <input type="radio" name="admission_type" value="CheckUp" onchange="onAdmTypeChange(this)">
                <div class="adm-lbl">
                    <div class="adm-icon ai-ck"><i class="bi bi-stethoscope"></i></div>
                    <div class="adm-name">Check-Up</div>
                    <div class="adm-desc">Day procedure / OPD</div>
                </div>
            </label>
        </div>
        <div style="font-size:.67rem;color:var(--s400);margin-top:6px;"><i class="bi bi-info-circle"></i> The service type determines which default services are suggested in the chart. All types can still add any service, medicine, or supply.</div>
    </div>
</div>

<!-- ADMISSION DETAILS -->
<div class="card">
    <div class="card-hd">
        <div class="card-icon ci-navy"><i class="bi bi-calendar-check-fill"></i></div>
        <div class="card-hd-text"><h3>Admission Details</h3><p>Date/time, room, and clinical info</p></div>
    </div>
    <div class="card-body">
        <div class="fg3" style="margin-bottom:14px;">
            <div class="field">
                <label>Admission Date &amp; Time <span class="req">*</span></label>
                <input type="datetime-local" name="admission_date" value="<?= date('Y-m-d\TH:i') ?>" required>
                <div class="hint">Auto-set to now. Adjust if needed.</div>
            </div>
            <div class="field"><label>Room / Bed No.</label><input type="text" name="room_no" placeholder="e.g. 204-B"></div>
            <div class="field"><label>Attending Physician</label><input type="text" name="attending_physician" placeholder="Dr. Juan dela Cruz"></div>
        </div>

        <!-- Room Type -->
        <div style="margin-bottom:14px;">
            <div style="font-size:.6rem;font-weight:800;text-transform:uppercase;letter-spacing:.08em;color:var(--s500);margin-bottom:8px;">Ward / Room Type <span style="color:var(--s400);font-weight:400;text-transform:none;letter-spacing:0;">(optional — can be assigned later in the chart)</span></div>
            <div class="room-grid">
                <label class="room-card">
                    <input type="radio" name="room_type_id" value="0" checked>
                    <div class="room-lbl">
                        <div class="room-name">ER / No Room Yet</div>
                        <div class="room-cap">Assign room later in chart</div>
                    </div>
                </label>
                <?php foreach ($roomTypes as $rt): ?>
                <label class="room-card">
                    <input type="radio" name="room_type_id" value="<?= $rt['id'] ?>">
                    <div class="room-lbl">
                        <div class="room-name"><?= safe($rt['name']) ?></div>
                        <div class="room-cap"><?= safe($rt['capacity']) ?></div>
                        <div class="room-rates"><span>Hourly</span><strong><?= money($rt['price_per_hour']) ?></strong></div>
                        <div class="room-rates"><span>Daily</span><strong><?= money($rt['price_per_day']) ?></strong></div>
                    </div>
                </label>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="fg2">
            <div class="field"><label>Chief Complaint</label><textarea name="chief_complaint" placeholder="Patient's chief complaint…"></textarea></div>
            <div class="field"><label>Admitting Diagnosis</label><textarea name="diagnosis" placeholder="Working impression or diagnosis…"></textarea></div>
        </div>
    </div>
</div>

<div class="sub-bar">
    <div class="sub-info">
        <strong><i class="bi bi-person-plus" style="margin-right:4px;color:var(--teal);"></i>Register &amp; Admit Patient</strong>
        <small>After registration, patient appears in Inpatient List. Add charges anytime from the chart.</small>
    </div>
    <button type="submit" class="btn btn-primary btn-lg"><i class="bi bi-check2-circle"></i> Register Patient</button>
</div>
</form>
</div><!-- /registration -->

<!-- ══════════════════════════════════════════
     VIEW 2: INPATIENT LIST
══════════════════════════════════════════ -->
<div id="view-patients" class="<?= $currentView!=='patients'?'d-none':'' ?>">
<div class="ph">
    <div><h1><i class="bi bi-clipboard-pulse" style="color:var(--teal);margin-right:7px;"></i>Inpatient List</h1>
    <p>Step 2 — All registered patients. Click <strong>Open Chart</strong> to add charges, set room, or finalize billing. Use <strong>Pay Cash</strong> on discharged patients with pending bills.</p></div>
    <button class="btn btn-primary" onclick="switchView('registration')"><i class="bi bi-person-plus"></i> New Registration</button>
</div>

<div class="stat-strip">
    <div class="stat-card"><div class="stat-ico" style="background:#dbeafe;color:#1d4ed8;"><i class="bi bi-person-check-fill"></i></div><div class="stat-info"><div class="lbl">Admitted</div><div class="val"><?= $totalAdmitted ?></div></div></div>
    <div class="stat-card"><div class="stat-ico" style="background:var(--green-l);color:var(--green);"><i class="bi bi-door-open-fill"></i></div><div class="stat-info"><div class="lbl">Discharged</div><div class="val"><?= $totalDischarged ?></div></div></div>
    <div class="stat-card"><div class="stat-ico" style="background:var(--amber-l);color:var(--amber);"><i class="bi bi-clock-fill"></i></div><div class="stat-info"><div class="lbl">Pending Bills</div><div class="val"><?= $totalPending ?></div></div></div>
    <div class="stat-card"><div class="stat-ico" style="background:var(--s100);color:var(--s600);"><i class="bi bi-people"></i></div><div class="stat-info"><div class="lbl">Total Records</div><div class="val"><?= count($patients) ?></div></div></div>
</div>

<div class="toolbar">
    <div class="search-box"><i class="bi bi-search"></i><input type="text" placeholder="Search by name or registration no…" oninput="filterPt(this.value)" id="ptSearch"></div>
    <select class="filter-sel" onchange="filterPtStatus(this.value)"><option value="">All Statuses</option><option>Admitted</option><option>Discharged</option><option>Transferred</option><option>HAMA</option><option>Deceased</option></select>
    <select class="filter-sel" onchange="filterPtType(this.value)"><option value="">All Types</option><option>Emergency</option><option>Confinement</option><option>Surgery</option><option>CheckUp</option></select>
</div>

<div class="tbl-wrap">
<table class="ptbl" id="ptTable">
    <thead><tr><th>Reg. No.</th><th>Patient</th><th>Type</th><th>Room</th><th>Physician</th><th>Admitted</th><th>LOS</th><th>Status</th><th>Billing</th><th>Actions</th></tr></thead>
    <tbody>
    <?php if (empty($patients)): ?>
    <tr><td colspan="10"><div class="empty-state"><i class="bi bi-person-x"></i>No patients yet. <a href="?view=registration" style="color:var(--teal);font-weight:700;">Register one →</a></div></td></tr>
    <?php else:
    foreach ($patients as $pt):
        $admt   = strtolower($pt['admission_type']);
        $admPill = ['emergency'=>'pill-em','confinement'=>'pill-co','surgery'=>'pill-su','checkup'=>'pill-ck'][$admt] ?? 'pill-ck';
        $admIcon = ['emergency'=>'bi-lightning-charge-fill','confinement'=>'bi-house-heart-fill','surgery'=>'bi-scissors','checkup'=>'bi-stethoscope'][$admt] ?? 'bi-stethoscope';
        $stl    = strtolower($pt['status']);
        $stPill = ['admitted'=>'pill-admitted','discharged'=>'pill-discharged','transferred'=>'pill-transferred','hama'=>'pill-hama','deceased'=>'pill-deceased'][$stl] ?? '';
        $btl    = strtolower($pt['billing_status']);
        $btPill = ['pending'=>'pill-pending','partial'=>'pill-partial','paid'=>'pill-paid'][$btl] ?? '';
        $admDt  = new DateTime($pt['admission_date']);
        $diffH  = $admDt->diff(new DateTime());
        $los    = $pt['status'] === 'Admitted'
            ? ($diffH->days > 0 ? $diffH->days.'d '.$diffH->h.'h' : $diffH->h.'h '.$diffH->i.'m')
            : '—';

        // Get finalized bill data for this patient (for cash pay button)
        $ptFinalBill = $finalBillingMap[$pt['patient_id']] ?? null;
        $hasPendingBill = $ptFinalBill && strtolower($ptFinalBill['payment_status']) !== 'paid';
    ?>
    <tr class="pt-row"
        data-search="<?= strtolower($pt['registration_no'].' '.$pt['last_name'].' '.$pt['first_name']) ?>"
        data-status="<?= $stl ?>" data-admtype="<?= $admt ?>">
        <td><span class="reg-no"><?= safe($pt['registration_no']) ?></span></td>
        <td>
            <div style="font-weight:800;color:var(--s900);font-size:.83rem;"><?= safe($pt['last_name'].', '.$pt['first_name'].(!empty($pt['middle_name'])?' '.$pt['middle_name'][0].'.':'')) ?></div>
            <div style="font-size:.7rem;color:var(--s400);margin-top:1px;"><?= $pt['date_of_birth'] ? age($pt['date_of_birth']).' · '.($pt['gender']??'') : '' ?></div>
        </td>
        <td><span class="pill <?= $admPill ?>"><i class="bi <?= $admIcon ?>"></i> <?= safe($pt['admission_type']) ?></span></td>
        <td style="font-size:.79rem;">
            <?= $pt['room_name'] ? safe($pt['room_name']) : '<span style="color:var(--s300);">No Room</span>' ?>
            <?= $pt['room_no'] ? '<br><span style="font-size:.67rem;color:var(--s400);">Rm '.safe($pt['room_no']).'</span>' : '' ?>
        </td>
        <td style="font-size:.78rem;color:var(--s600);"><?= $pt['attending_physician'] ? 'Dr. '.safe($pt['attending_physician']) : '<span style="color:var(--s300);">—</span>' ?></td>
        <td style="font-size:.77rem;color:var(--s500);"><?= date('M j, Y', strtotime($pt['admission_date'])) ?><br><span style="font-size:.7rem;font-family:var(--mono);"><?= date('g:i A', strtotime($pt['admission_date'])) ?></span></td>
        <td style="font-family:var(--mono);font-size:.77rem;color:<?= $pt['status']==='Admitted'?'var(--teal)':'var(--s400)' ?>;"><?= $los ?></td>
        <td><span class="pill <?= $stPill ?>"><?= safe($pt['status']) ?></span></td>
        <td>
            <span class="pill <?= $btPill ?>"><?= safe($pt['billing_status']) ?></span>
            <?php if ($ptFinalBill): ?>
            <div style="font-family:var(--mono);font-size:.67rem;color:var(--s400);margin-top:3px;"><?= money((float)$ptFinalBill['amount_due']) ?></div>
            <?php endif; ?>
        </td>
        <td>
            <div style="display:flex;gap:4px;flex-wrap:wrap;">
                <?php if ($pt['status'] === 'Admitted'): ?>
                <a href="?view=chart&pid=<?= $pt['patient_id'] ?>" class="btn btn-primary" style="font-size:.71rem;padding:4px 10px;"><i class="bi bi-clipboard-heart"></i> Open Chart</a>
                <?php else:
                    $finBill = $pdo->prepare("SELECT billing_id FROM patient_billing WHERE patient_id=? AND finalized=1 ORDER BY updated_at DESC LIMIT 1");
                    $finBill->execute([$pt['patient_id']]);
                    $fBid = $finBill->fetchColumn();
                    if ($fBid): ?>
                    <a href="?view=receipt&billing_id=<?= $fBid ?>" class="btn btn-dark" style="font-size:.71rem;padding:4px 10px;"><i class="bi bi-receipt"></i> SOA</a>
                    <?php endif; ?>
                    <?php if ($hasPendingBill && $ptFinalBill): ?>
                    <!-- Cash Pay Button → billing_summary.php -->
                    <a href="billing_summary.php?billing_id=<?= (int)$ptFinalBill['billing_id'] ?>&patient_id=<?= (int)$pt['patient_id'] ?>&from=inpatient&cash=1"
                       class="btn btn-cash" style="font-size:.71rem;padding:4px 10px;"
                       title="Process cash payment for this bill">
                        <i class="bi bi-cash-coin"></i> Pay Cash
                    </a>
                    <?php endif; ?>
                <?php endif; ?>
                <button class="btn btn-outline btn-icon" onclick="openStatusModal(<?= $pt['patient_id'] ?>,'<?= safe($pt['status']) ?>')" title="Update Status"><i class="bi bi-pencil-square"></i></button>
            </div>
        </td>
    </tr>
    <?php endforeach; endif; ?>
    </tbody>
</table>
<div id="pt-empty" style="display:none;padding:40px;" class="empty-state"><i class="bi bi-search"></i>No patients match your filter.</div>
</div>
</div><!-- /patients -->

<!-- ══════════════════════════════════════════
     VIEW 3: PATIENT CHART
══════════════════════════════════════════ -->
<div id="view-chart" class="<?= $currentView!=='chart'?'d-none':'' ?>">

<?php if (!$currentPatient): ?>
<div class="ph"><div><h1><i class="bi bi-file-earmark-medical" style="color:var(--teal);margin-right:7px;"></i>Patient Chart</h1><p>Select a patient from the Inpatient List.</p></div></div>
<div class="empty-state" style="padding:60px 20px;">
    <i class="bi bi-person-lines-fill" style="font-size:2.5rem;"></i>
    <div style="font-size:.95rem;font-weight:800;color:var(--s700);margin-bottom:5px;margin-top:10px;">No Patient Selected</div>
    <p>Go to <strong>Inpatient List</strong> and click <strong>Open Chart</strong> on an admitted patient.</p>
    <button class="btn btn-primary" onclick="switchView('patients')" style="margin-top:14px;"><i class="bi bi-clipboard-pulse"></i> Inpatient List</button>
</div>
<?php else:
    $liveAdmDt = new DateTime($currentPatient['admission_date']);
    $liveHours = round($liveAdmDt->diff(new DateTime())->days * 24 + $liveAdmDt->diff(new DateTime())->h + $liveAdmDt->diff(new DateTime())->i / 60, 2);
    $liveRoom  = 0;
    if ($currentPatient['room_type_id'] && $liveHours > 0) {
        $liveRoom = $liveHours >= 24
            ? ceil($liveHours / 24) * (float)$currentPatient['price_per_day']
            : $liveHours * (float)$currentPatient['price_per_hour'];
    }
    $liveSvc = 0; $liveMed = 0; $liveSup = 0;
    foreach ($draftItems as $di) {
        switch ($di['item_type']) {
            case 'Service':  $liveSvc += $di['total_price']; break;
            case 'Medicine': $liveMed += $di['total_price']; break;
            case 'Supply':   $liveSup += $di['total_price']; break;
        }
    }
    $liveGross = $liveRoom + $liveSvc + $liveMed + $liveSup;
?>

<div class="ph">
    <div>
        <h1><i class="bi bi-clipboard-heart" style="color:var(--teal);margin-right:7px;"></i>Patient Chart</h1>
        <p>Step 3 — Add medicines, services, and supplies. You can also change the room type below. Finalize when ready to discharge.</p>
    </div>
    <div style="display:flex;gap:7px;">
        <button class="btn btn-outline" onclick="switchView('patients')"><i class="bi bi-arrow-left"></i> Back to List</button>
        <button class="btn btn-success" onclick="openFinalizeModal()"><i class="bi bi-check-circle-fill"></i> Finalize &amp; Discharge</button>
    </div>
</div>

<!-- Patient Bar -->
<div class="pat-bar">
    <div class="pat-bar-left">
        <div class="pat-avatar"><?= strtoupper(substr($currentPatient['first_name'],0,1)) ?></div>
        <div>
            <div class="pat-name"><?= safe($currentPatient['last_name'].', '.$currentPatient['first_name'].' '.($currentPatient['middle_name']??'')) ?></div>
            <div class="pat-meta">
                <?php
                $admtC  = strtolower($currentPatient['admission_type']);
                $admPC  = ['emergency'=>['pill-em','bi-lightning-charge-fill'],'confinement'=>['pill-co','bi-house-heart-fill'],'surgery'=>['pill-su','bi-scissors'],'checkup'=>['pill-ck','bi-stethoscope']][$admtC] ?? ['pill-ck','bi-stethoscope'];
                ?>
                <span class="pill <?= $admPC[0] ?>" style="font-size:.65rem;"><i class="bi <?= $admPC[1] ?>"></i> <?= safe($currentPatient['admission_type']) ?></span>
                <?php if ($currentPatient['room_name']): ?><span class="pat-chip"><?= safe($currentPatient['room_name']) ?><?= $currentPatient['room_no'] ? ' — Rm '.$currentPatient['room_no'] : '' ?></span><?php else: ?><span class="pat-chip" style="background:rgba(217,119,6,.2);color:#fbbf24;">No Room Assigned</span><?php endif; ?>
                <?php if ($currentPatient['attending_physician']): ?><span class="pat-chip">Dr. <?= safe($currentPatient['attending_physician']) ?></span><?php endif; ?>
                <span class="pat-chip">Admitted: <?= date('M j, Y g:i A', strtotime($currentPatient['admission_date'])) ?></span>
                <span class="pat-chip" style="background:rgba(13,148,136,.2);color:var(--teal-l);">LOS: <?= $liveHours < 24 ? $liveHours.'h' : ceil($liveHours/24).'d' ?></span>
            </div>
        </div>
    </div>
    <div class="pat-bar-right">
        <div class="pat-reg"><?= safe($currentPatient['registration_no']) ?></div>
        <div class="pat-adm-date">DOB: <?= $currentPatient['date_of_birth'] ? date('M j, Y', strtotime($currentPatient['date_of_birth'])).' ('.age($currentPatient['date_of_birth']).')' : '—' ?></div>
    </div>
</div>

<!-- ── ROOM CHANGE ── -->
<div class="room-change-bar">
    <div class="rch-hd">
        <h4><i class="bi bi-door-open-fill" style="color:var(--teal);"></i> Room / Ward Assignment</h4>
        <span class="rch-current">
            Current: <strong><?= $currentPatient['room_name'] ? safe($currentPatient['room_name']).' ('.(($currentPatient['room_no'])?'Rm '.safe($currentPatient['room_no']):'no bed assigned').')' : 'None (ER / No Room)' ?></strong>
        </span>
    </div>
    <form method="post" id="roomForm">
    <input type="hidden" name="action" value="update_room">
    <input type="hidden" name="patient_id" value="<?= $currentPatient['patient_id'] ?>">
    <div class="room-grid" style="margin-bottom:10px;">
        <label class="room-card">
            <input type="radio" name="room_type_id" value="0" <?= !$currentPatient['room_type_id'] ? 'checked' : '' ?>>
            <div class="room-lbl">
                <div class="room-name">ER / No Room</div>
                <div class="room-cap">No room charge</div>
            </div>
        </label>
        <?php foreach ($roomTypes as $rt): ?>
        <label class="room-card">
            <input type="radio" name="room_type_id" value="<?= $rt['id'] ?>" <?= $currentPatient['room_type_id'] == $rt['id'] ? 'checked' : '' ?>>
            <div class="room-lbl">
                <div class="room-name"><?= safe($rt['name']) ?></div>
                <div class="room-cap"><?= safe($rt['capacity']) ?></div>
                <div class="room-rates"><span>Hourly</span><strong><?= money($rt['price_per_hour']) ?></strong></div>
                <div class="room-rates"><span>Daily</span><strong><?= money($rt['price_per_day']) ?></strong></div>
            </div>
        </label>
        <?php endforeach; ?>
    </div>
    <div style="display:flex;align-items:center;gap:10px;">
        <div class="field" style="flex:0 0 200px;">
            <label>Room / Bed No.</label>
            <input type="text" name="room_no" value="<?= safe($currentPatient['room_no']??'') ?>" placeholder="e.g. 204-B">
        </div>
        <button type="submit" class="btn btn-amber" style="margin-top:16px;"><i class="bi bi-save"></i> Update Room</button>
    </div>
    </form>
</div>

<!-- ── ADD ORDERS FORM ── -->
<form method="post" id="ordersForm">
<input type="hidden" name="action" value="add_orders">
<input type="hidden" name="patient_id" value="<?= $currentPatient['patient_id'] ?>">

<!-- SERVICES -->
<div class="card">
    <div class="card-hd">
        <div class="card-icon ci-blue"><i class="bi bi-clipboard-heart-fill"></i></div>
        <div class="card-hd-text"><h3>Hospital Services &amp; Procedures</h3><p>Lab, imaging, ER, surgery, consultation</p></div>
        <span class="card-hd-badge" style="background:var(--blue-l);color:var(--blue);" id="svc-cnt-badge">0 selected</span>
    </div>
    <div class="card-body">
        <div class="orders-panel">
            <div class="op-head" onclick="togglePanel('svc')">
                <div class="op-head-l"><i class="bi bi-plus-circle" style="color:var(--teal);font-size:.9rem;"></i><span class="op-label">Browse &amp; Select Services</span><span class="op-count" id="svc-cnt">0</span></div>
                <i class="bi bi-chevron-down op-tog" id="svc-tog"></i>
            </div>
            <div class="op-body" id="svc-body">
                <div class="svc-search"><i class="bi bi-search"></i><input type="text" placeholder="Search services, procedures, labs…" oninput="filterChips('svc',this.value)"></div>
                <div class="cat-pills" id="svc-cats">
                    <button type="button" class="cat-pill active" onclick="setChipCat('svc','',this)">All</button>
                    <?php foreach ($svcCategories as $cat): ?>
                    <button type="button" class="cat-pill" onclick="setChipCat('svc','<?= safe($cat) ?>',this)"><?= safe($cat) ?></button>
                    <?php endforeach; ?>
                </div>
                <div class="svc-grid" id="svc-grid">
                    <?php foreach ($svcByType['Service'] as $sv): ?>
                    <div class="svc-chip" data-id="<?= $sv['service_id'] ?>" data-name="<?= safe($sv['name']) ?>" data-price="<?= $sv['base_price'] ?>" data-cat="<?= safe($sv['category']) ?>" onclick="toggleChip(this,'svc')">
                        <i class="bi bi-check-circle-fill svc-chk"></i>
                        <div class="svc-chip-name"><?= safe($sv['name']) ?></div>
                        <div class="svc-chip-cat"><?= safe($sv['category']) ?> · <?= safe($sv['unit']) ?></div>
                        <div class="svc-chip-price"><?= money($sv['base_price']) ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <div class="ord-wrap"><table class="ord-tbl">
            <thead><tr><th>Service</th><th>Category</th><th>Qty</th><th style="text-align:right">Unit Price</th><th style="text-align:right">Total</th><th></th></tr></thead>
            <tbody id="svc-tbody"><tr id="svc-empty-row"><td colspan="6"><div class="empty-state"><i class="bi bi-clipboard-plus"></i>No services added. Click browse above.</div></td></tr></tbody>
        </table></div>
    </div>
</div>

<!-- MEDICINES -->
<div class="card">
    <div class="card-hd">
        <div class="card-icon ci-green"><i class="bi bi-capsule-pill"></i></div>
        <div class="card-hd-text"><h3>Medicines &amp; IV Fluids</h3><p>Medications, antibiotics, analgesics, IV solutions</p></div>
        <span class="card-hd-badge" style="background:var(--green-l);color:var(--green);" id="med-cnt-badge">0 selected</span>
    </div>
    <div class="card-body">
        <div class="orders-panel">
            <div class="op-head" onclick="togglePanel('med')">
                <div class="op-head-l"><i class="bi bi-plus-circle" style="color:var(--teal);font-size:.9rem;"></i><span class="op-label">Browse &amp; Select Medicines</span><span class="op-count" id="med-cnt">0</span></div>
                <i class="bi bi-chevron-down op-tog" id="med-tog"></i>
            </div>
            <div class="op-body" id="med-body">
                <div class="svc-search"><i class="bi bi-search"></i><input type="text" placeholder="Search medicines, IV fluids…" oninput="filterChips('med',this.value)"></div>
                <div class="svc-grid" id="med-grid">
                    <?php foreach ($svcByType['Medicine'] as $mv): ?>
                    <div class="svc-chip" data-id="<?= $mv['service_id'] ?>" data-name="<?= safe($mv['name']) ?>" data-price="<?= $mv['base_price'] ?>" data-cat="<?= safe($mv['category']) ?>" onclick="toggleChip(this,'med')">
                        <i class="bi bi-check-circle-fill svc-chk"></i>
                        <div class="svc-chip-name"><?= safe($mv['name']) ?></div>
                        <div class="svc-chip-cat"><?= safe($mv['category']) ?> · <?= safe($mv['unit']) ?></div>
                        <div class="svc-chip-price"><?= money($mv['base_price']) ?> / <?= safe($mv['unit']) ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <div class="ord-wrap"><table class="ord-tbl">
            <thead><tr><th>Medicine</th><th>Category</th><th>Qty</th><th style="text-align:right">Unit Price</th><th style="text-align:right">Total</th><th></th></tr></thead>
            <tbody id="med-tbody"><tr id="med-empty-row"><td colspan="6"><div class="empty-state"><i class="bi bi-capsule"></i>No medicines added. Click browse above.</div></td></tr></tbody>
        </table></div>
    </div>
</div>

<!-- SUPPLIES -->
<div class="card">
    <div class="card-hd">
        <div class="card-icon ci-purple"><i class="bi bi-box-seam-fill"></i></div>
        <div class="card-hd-text"><h3>Medical Supply Items</h3><p>Surgical supplies, consumables, equipment</p></div>
        <span class="card-hd-badge" style="background:var(--purple-l);color:var(--purple);" id="sup-cnt-badge">0 selected</span>
    </div>
    <div class="card-body">
        <div class="orders-panel">
            <div class="op-head" onclick="togglePanel('sup')">
                <div class="op-head-l"><i class="bi bi-plus-circle" style="color:var(--teal);font-size:.9rem;"></i><span class="op-label">Browse &amp; Select Supplies</span><span class="op-count" id="sup-cnt">0</span></div>
                <i class="bi bi-chevron-down op-tog" id="sup-tog"></i>
            </div>
            <div class="op-body" id="sup-body">
                <div class="svc-search"><i class="bi bi-search"></i><input type="text" placeholder="Search supply items…" oninput="filterChips('sup',this.value)"></div>
                <div class="cat-pills" id="sup-cats">
                    <button type="button" class="cat-pill active" onclick="setChipCat('sup','',this)">All</button>
                    <?php foreach(array_unique(array_column(array_filter($allServices,fn($s)=>$s['item_type']==='Supply'),'category')) as $cat): ?>
                    <button type="button" class="cat-pill" onclick="setChipCat('sup','<?= safe($cat) ?>',this)"><?= safe($cat) ?></button>
                    <?php endforeach; ?>
                </div>
                <div class="svc-grid" id="sup-grid">
                    <?php foreach ($svcByType['Supply'] as $sv): ?>
                    <div class="svc-chip" data-id="<?= $sv['service_id'] ?>" data-name="<?= safe($sv['name']) ?>" data-price="<?= $sv['base_price'] ?>" data-cat="<?= safe($sv['category']) ?>" onclick="toggleChip(this,'sup')">
                        <i class="bi bi-check-circle-fill svc-chk"></i>
                        <div class="svc-chip-name"><?= safe($sv['name']) ?></div>
                        <div class="svc-chip-cat"><?= safe($sv['category']) ?></div>
                        <div class="svc-chip-price"><?= money($sv['base_price']) ?> / <?= safe($sv['unit']) ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <div class="ord-wrap"><table class="ord-tbl">
            <thead><tr><th>Supply Item</th><th>Category</th><th>Qty</th><th style="text-align:right">Unit Price</th><th style="text-align:right">Total</th><th></th></tr></thead>
            <tbody id="sup-tbody"><tr id="sup-empty-row"><td colspan="6"><div class="empty-state"><i class="bi bi-boxes"></i>No supplies added. Click browse above.</div></td></tr></tbody>
        </table></div>
    </div>
</div>

<div class="sub-bar">
    <div class="sub-info">
        <strong><i class="bi bi-plus-circle" style="margin-right:4px;color:var(--teal);"></i>Save New Orders to Chart</strong>
        <small>Orders are appended to existing chart entries. You can add more anytime before finalization.</small>
    </div>
    <div style="display:flex;gap:8px;">
        <button type="submit" class="btn btn-primary btn-lg" id="addOrdersBtn"><i class="bi bi-plus-lg"></i> Add to Chart</button>
        <button type="button" class="btn btn-success btn-lg" onclick="openFinalizeModal()"><i class="bi bi-check-circle-fill"></i> Finalize &amp; Discharge</button>
    </div>
</div>
</form>

<!-- ── CURRENT CHART ITEMS ── -->
<?php if (!empty($draftItems)): ?>
<div class="card" style="margin-top:16px;">
    <div class="card-hd">
        <div class="card-icon" style="background:#fef3c7;color:#92400e;"><i class="bi bi-list-check"></i></div>
        <div class="card-hd-text"><h3>Current Chart Orders</h3><p>All charges currently on this patient's bill — click Remove to delete an entry</p></div>
        <span class="card-hd-badge" style="background:var(--amber-l);color:var(--amber);"><?= count($draftItems) ?> items</span>
    </div>
    <div class="card-body">
        <div class="ord-wrap">
        <table class="ord-tbl">
            <thead><tr><th>Item</th><th>Type</th><th>Category</th><th>Qty</th><th style="text-align:right">Unit Price</th><th style="text-align:right">Total</th><th>Added</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($draftItems as $di):
                $tClass = ['Service'=>'tt-svc','Medicine'=>'tt-med','Supply'=>'tt-sup'][$di['item_type']] ?? '';
            ?>
            <tr>
                <td style="font-weight:700;"><?= safe($di['service_name']) ?></td>
                <td><span class="type-tag <?= $tClass ?>"><?= safe($di['item_type']) ?></span></td>
                <td style="font-size:.77rem;color:var(--s500);"><?= safe($di['category']) ?></td>
                <td style="font-family:var(--mono);"><?= $di['quantity'] ?></td>
                <td style="text-align:right;font-family:var(--mono);font-size:.79rem;"><?= money($di['unit_price']) ?></td>
                <td style="text-align:right;" class="lt-val"><?= money($di['total_price']) ?></td>
                <td style="font-size:.7rem;color:var(--s400);"><?= isset($di['added_at']) ? date('M j, g:i A', strtotime($di['added_at'])) : '—' ?></td>
                <td>
                    <form method="post" style="display:inline;" onsubmit="return confirm('Remove this item from the chart?')">
                        <input type="hidden" name="action" value="remove_item">
                        <input type="hidden" name="item_id" value="<?= $di['item_id'] ?>">
                        <input type="hidden" name="patient_id" value="<?= $currentPatient['patient_id'] ?>">
                        <button type="submit" class="btn btn-danger" style="font-size:.68rem;padding:3px 8px;"><i class="bi bi-x-circle"></i> Remove</button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- ── LIVE SUMMARY ── -->
<div class="live-sum" style="margin-top:16px;">
    <div class="ls-hd">
        <h3><span class="live-dot"></span> Running Balance</h3>
        <span style="font-size:.68rem;color:rgba(255,255,255,.3);">Room charges are estimated — finalized at discharge</span>
    </div>
    <div class="ls-body">
        <div class="ls-row">
            <span class="ls-lbl"><i class="bi bi-door-open"></i> Room / Ward
                <?= $currentPatient['room_name'] ? '('.safe($currentPatient['room_name']).')' : '(No Room)' ?>
            </span>
            <span class="ls-val"><?= money($liveRoom) ?></span>
        </div>
        <div class="ls-row"><span class="ls-lbl"><i class="bi bi-clipboard-heart"></i> Services</span><span class="ls-val"><?= money($liveSvc) ?></span></div>
        <div class="ls-row"><span class="ls-lbl"><i class="bi bi-capsule-pill"></i> Medicines</span><span class="ls-val"><?= money($liveMed) ?></span></div>
        <div class="ls-row"><span class="ls-lbl"><i class="bi bi-box-seam"></i> Supplies</span><span class="ls-val"><?= money($liveSup) ?></span></div>
        <div class="ls-div"></div>
        <div class="ls-total"><div class="ls-total-lbl">Estimated Gross Total</div><div class="ls-total-val"><?= money($liveGross) ?></div></div>
        <div class="ls-note">* Room will be recalculated at finalization based on actual discharge date/time.</div>
    </div>
</div>

<?php endif; ?>
</div><!-- /chart -->

<!-- ══════════════════════════════════════════
     VIEW 4: RECEIPT / SOA
══════════════════════════════════════════ -->
<div id="view-receipt" class="<?= $currentView!=='receipt'?'d-none':'' ?>">

<?php if ($viewBilling): ?>
<div class="receipt-wrap">

<!-- ── CASH PAYMENT PANEL (shown when billing is pending) ── -->
<?php if (strtolower($viewBilling['payment_status']) !== 'paid'): ?>
<div class="cash-pay-panel">
    <div class="cpp-left">
        <div class="cpp-ico"><i class="bi bi-cash-coin"></i></div>
        <div>
            <div class="cpp-title">Payment Pending</div>
            <div class="cpp-sub">This bill has not been paid yet. Use the button to process cash or online payment.</div>
        </div>
    </div>
    <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
        <div class="cpp-amt"><?= money((float)$viewBilling['amount_due']) ?></div>
        <a href="billing_summary.php?billing_id=<?= $billingId ?>&patient_id=<?= (int)($viewBilling['patient_id'] ?? 0) ?>&from=inpatient&cash=1"
           class="btn btn-success btn-lg">
            <i class="bi bi-cash-coin"></i> Pay Now (Cash / Online)
        </a>
    </div>
</div>
<?php else: ?>
<div style="background:var(--green-l);border:1.5px solid #6ee7b7;border-radius:var(--rxl);padding:13px 20px;margin-bottom:14px;display:flex;align-items:center;gap:10px;">
    <i class="bi bi-check-circle-fill" style="color:var(--green);font-size:1.1rem;"></i>
    <span style="font-size:.83rem;font-weight:700;color:#065f46;">Bill fully paid — no outstanding balance.</span>
</div>
<?php endif; ?>

<div class="receipt">
    <div class="rcp-hd">
        <div class="rcp-hosp">
            <h2><i class="bi bi-hospital" style="margin-right:6px;color:var(--teal-l);"></i>General Hospital</h2>
            <span>Official Statement of Account — Inpatient Billing</span>
        </div>
        <div>
            <div class="rcp-billno"><?= safe($viewBilling['bill_number']) ?></div>
            <div class="rcp-date">Finalized: <?= date('F j, Y g:i A', strtotime($viewBilling['updated_at'] ?? $viewBilling['billing_date'])) ?></div>
        </div>
    </div>

    <div class="rcp-patient">
        <div class="rcp-cell">
            <div class="rcp-lbl">Patient Name</div>
            <div class="rcp-val"><?= safe($viewBilling['last_name'].', '.$viewBilling['first_name'].' '.($viewBilling['middle_name']??'')) ?></div>
        </div>
        <div class="rcp-cell">
            <div class="rcp-lbl">Registration No.</div>
            <div class="rcp-val" style="font-family:var(--mono);font-size:.78rem;"><?= safe($viewBilling['registration_no']) ?></div>
        </div>
        <div class="rcp-cell">
            <div class="rcp-lbl">Admission Type</div>
            <div class="rcp-val">
                <?php $admT=strtolower($viewBilling['reg_admission_type']??'');$admPC2=['emergency'=>'pill-em','confinement'=>'pill-co','surgery'=>'pill-su','checkup'=>'pill-ck'][$admT]??'pill-ck'; ?>
                <span class="pill <?= $admPC2 ?>" style="font-size:.67rem;"><?= safe($viewBilling['reg_admission_type']??'—') ?></span>
            </div>
        </div>
        <div class="rcp-cell">
            <div class="rcp-lbl">Payment Status</div>
            <div class="rcp-val">
                <?php $ps=strtolower($viewBilling['payment_status']);$psm=['pending'=>'pill-pending','partial'=>'pill-partial','paid'=>'pill-paid'][$ps]??''; ?>
                <span class="pill <?= $psm ?>"><?= safe($viewBilling['payment_status']) ?></span>
            </div>
        </div>
    </div>

    <div class="rcp-body">
        <?php if ($viewBilling['discharge_date']): ?>
        <div class="discharge-strip">
            <div class="ds-item"><strong>Admitted:</strong> <?= date('M j, Y g:i A', strtotime($viewBilling['admission_date'])) ?></div>
            <span class="ds-sep">·</span>
            <div class="ds-item"><strong>Discharged:</strong> <?= date('M j, Y g:i A', strtotime($viewBilling['discharge_date'])) ?></div>
            <span class="ds-sep">·</span>
            <div class="ds-item"><strong>LOS:</strong> <?= number_format($viewBilling['hours_stay'],1) ?> hrs (<?= ceil($viewBilling['hours_stay']/24) ?> day<?= ceil($viewBilling['hours_stay']/24)!=1?'s':'' ?>)</div>
            <span class="ds-sep">·</span>
            <div class="ds-item"><strong>Type:</strong> <?= safe($viewBilling['discharge_type']??'—') ?></div>
            <?php if ($viewBilling['attending_physician']): ?><span class="ds-sep">·</span><div class="ds-item"><strong>Dr.</strong> <?= safe($viewBilling['attending_physician']) ?></div><?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- Room -->
        <?php if ($viewBilling['room_total'] > 0): ?>
        <div class="rcp-sec"><i class="bi bi-door-open"></i> Room &amp; Ward Charges</div>
        <table class="rtbl"><thead><tr><th>Room Type</th><th>Rate</th><th>Duration</th><th>Total</th></tr></thead>
        <tbody><tr>
            <td style="font-weight:700;"><?= safe($viewBilling['room_name']??'Room') ?></td>
            <td><?= $viewBilling['hours_stay']>=24 ? money($viewBilling['price_per_day']).'/day' : money($viewBilling['price_per_hour']).'/hr' ?></td>
            <td><?= number_format($viewBilling['hours_stay'],1) ?> hrs<?= $viewBilling['hours_stay']>=24?' ('.ceil($viewBilling['hours_stay']/24).' day'.(ceil($viewBilling['hours_stay']/24)!=1?'s':'').')':'' ?></td>
            <td><?= money($viewBilling['room_total']) ?></td>
        </tr></tbody></table>
        <?php endif; ?>

        <?php
        $byType=['Service'=>[],'Medicine'=>[],'Supply'=>[]];
        foreach ($viewItems as $li) { $byType[$li['item_type']][]=$li; }
        $typeInfo=['Service'=>['bi-clipboard-heart','Hospital Services &amp; Procedures'],'Medicine'=>['bi-capsule-pill','Medicines &amp; IV Fluids'],'Supply'=>['bi-box-seam','Medical Supply Items']];
        foreach ($typeInfo as $t=>[$ico,$lbl]):
            if (empty($byType[$t])) continue;
        ?>
        <div class="rcp-sec"><i class="bi <?= $ico ?>"></i> <?= $lbl ?></div>
        <table class="rtbl">
            <thead><tr><th>Item</th><th>Category</th><th>Qty</th><th>Unit Price</th><th>Total</th></tr></thead>
            <tbody>
            <?php foreach ($byType[$t] as $li): ?>
            <tr>
                <td style="font-weight:600;"><?= safe($li['service_name']) ?></td>
                <td style="font-size:.74rem;color:var(--s500);"><?= safe($li['category']) ?></td>
                <td><?= $li['quantity'] ?></td>
                <td><?= money($li['unit_price']) ?></td>
                <td><?= money($li['total_price']) ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endforeach; ?>

        <div class="rcp-totals">
            <div class="rt-row"><span>Room / Ward Charges</span><span><?= money($viewBilling['room_total']) ?></span></div>
            <div class="rt-row"><span>Services &amp; Procedures</span><span><?= money($viewBilling['services_total']) ?></span></div>
            <div class="rt-row"><span>Medicines &amp; IV Fluids</span><span><?= money($viewBilling['medicines_total']) ?></span></div>
            <div class="rt-row"><span>Medical Supplies</span><span><?= money($viewBilling['supplies_total']) ?></span></div>
            <div class="rt-div">
            <div class="rt-row" style="font-weight:800;font-size:.88rem;"><span>Gross Total</span><span><?= money($viewBilling['gross_total']) ?></span></div>
            </div>
            <?php if ($viewBilling['discount_amount'] > 0): ?>
            <div class="rt-row"><span>Discount (<?= $viewBilling['discount_pct'] ?>%)</span><span style="color:var(--green);">— <?= money($viewBilling['discount_amount']) ?></span></div>
            <?php endif; ?>
            <?php if ($viewBilling['philhealth_deduct'] > 0): ?>
            <div class="rt-row"><span>PhilHealth Deduction</span><span style="color:var(--green);">— <?= money($viewBilling['philhealth_deduct']) ?></span></div>
            <?php endif; ?>
            <div class="rt-grand"><div class="rt-grand-lbl">Total Amount Due</div><div class="rt-grand-val"><?= money($viewBilling['amount_due']) ?></div></div>
        </div>

        <?php if ($viewBilling['notes']): ?>
        <p style="margin-top:12px;font-size:.77rem;color:var(--s500);"><strong>Notes:</strong> <?= safe($viewBilling['notes']) ?></p>
        <?php endif; ?>

        <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:24px;margin-top:30px;padding-top:14px;border-top:1px solid var(--s100);">
            <?php foreach(['Billing Officer','Attending Physician','Patient / Guardian'] as $sig): ?>
            <div style="text-align:center;">
                <div style="border-top:1.5px solid var(--s300);padding-top:6px;margin-top:32px;font-size:.7rem;color:var(--s500);font-weight:700;"><?= $sig ?></div>
                <?php if ($sig === 'Attending Physician' && $viewBilling['attending_physician']): ?>
                <div style="font-size:.68rem;color:var(--s400);">Dr. <?= safe($viewBilling['attending_physician']) ?></div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="rcp-ft">
        <div style="font-size:.72rem;color:var(--s400);">PhilHealth: <?= safe($viewBilling['philhealth_no']??'—') ?> &nbsp;|&nbsp; Room: <?= safe($viewBilling['room_name']??'N/A') ?> &nbsp;|&nbsp; Patient ID: #<?= $viewBilling['patient_id']??'—' ?></div>
        <div style="display:flex;gap:7px;flex-wrap:wrap;">
            <button class="btn btn-primary" onclick="window.print()"><i class="bi bi-printer"></i> Print SOA</button>
            <?php if (strtolower($viewBilling['payment_status']) !== 'paid'): ?>
            <a href="billing_summary.php?billing_id=<?= $billingId ?>&patient_id=<?= (int)($viewBilling['patient_id'] ?? 0) ?>&from=inpatient&cash=1"
               class="btn btn-cash">
                <i class="bi bi-cash-coin"></i> Process Payment
            </a>
            <?php endif; ?>
            <a href="?view=patients" class="btn btn-dark"><i class="bi bi-people"></i> Patient List</a>
        </div>
    </div>
</div>
</div>

<?php else: ?>
<!-- All Bills -->
<div class="ph"><div><h1><i class="bi bi-receipt-cutoff" style="color:var(--teal);margin-right:7px;"></i>Billing Records</h1><p>All finalized statements of account.</p></div></div>
<?php
try {
    $allBills = $pdo->query("SELECT pb.*, ir.last_name, ir.first_name, ir.registration_no, brt.name AS room_name
        FROM patient_billing pb
        LEFT JOIN inpatient_registration ir ON ir.patient_id=pb.patient_id
        LEFT JOIN billing_room_types brt ON brt.id=pb.room_type_id
        WHERE pb.finalized=1 ORDER BY pb.updated_at DESC LIMIT 200")->fetchAll(PDO::FETCH_ASSOC);
} catch(Exception $e){ $allBills=[]; }
?>
<div class="toolbar">
    <div class="search-box"><i class="bi bi-search"></i><input type="text" placeholder="Search bill # or patient…" oninput="filterBills(this.value)"></div>
    <select class="filter-sel" onchange="filterBillStatus(this.value)"><option value="">All</option><option>Pending</option><option>Partial</option><option>Paid</option></select>
</div>
<div class="tbl-wrap">
<table class="ptbl" id="billsTbl">
    <thead><tr><th>Bill #</th><th>Patient</th><th>Room</th><th>Gross</th><th>Amount Due</th><th>Status</th><th>Finalized</th><th>Actions</th></tr></thead>
    <tbody>
    <?php if (empty($allBills)): ?>
    <tr><td colspan="8"><div class="empty-state"><i class="bi bi-file-earmark-x"></i>No finalized bills yet.</div></td></tr>
    <?php else: foreach ($allBills as $b):
        $bsl=$b['payment_status']??'';$bsPill=['Pending'=>'pill-pending','Partial'=>'pill-partial','Paid'=>'pill-paid'][$bsl]??'';
        $isPending = strtolower($bsl) !== 'paid';
    ?>
    <tr class="bill-tr" data-search="<?= strtolower($b['bill_number'].' '.$b['last_name'].' '.$b['first_name']) ?>" data-bst="<?= strtolower($bsl) ?>">
        <td><span class="reg-no"><?= safe($b['bill_number']) ?></span></td>
        <td style="font-weight:700;"><?= safe($b['last_name'].', '.$b['first_name']) ?></td>
        <td style="font-size:.79rem;"><?= safe($b['room_name']??'—') ?></td>
        <td style="font-family:var(--mono);font-size:.8rem;"><?= money($b['gross_total']) ?></td>
        <td style="font-family:var(--mono);font-size:.86rem;font-weight:800;color:var(--teal);"><?= money($b['amount_due']) ?></td>
        <td><span class="pill <?= $bsPill ?>"><?= safe($bsl) ?></span></td>
        <td style="font-size:.77rem;color:var(--s500);"><?= isset($b['updated_at'])&&$b['updated_at'] ? date('M j, Y g:i A',strtotime($b['updated_at'])) : '—' ?></td>
        <td>
            <div style="display:flex;gap:4px;flex-wrap:wrap;">
                <a href="?view=receipt&billing_id=<?= $b['billing_id'] ?>" class="btn btn-dark" style="font-size:.71rem;padding:4px 10px;"><i class="bi bi-eye"></i> View SOA</a>
                <?php if ($isPending): ?>
                <a href="billing_summary.php?billing_id=<?= (int)$b['billing_id'] ?>&patient_id=<?= (int)($b['patient_id']??0) ?>&from=inpatient&cash=1"
                   class="btn btn-cash" style="font-size:.71rem;padding:4px 10px;"
                   title="Process cash or online payment">
                    <i class="bi bi-cash-coin"></i> Pay
                </a>
                <?php endif; ?>
            </div>
        </td>
    </tr>
    <?php endforeach; endif; ?>
    </tbody>
</table>
<div id="bills-empty" style="display:none;padding:40px;" class="empty-state"><i class="bi bi-search"></i>No bills match.</div>
</div>
<?php endif; ?>
</div><!-- /receipt -->

</main>
</div><!-- /layout -->

<!-- STATUS MODAL -->
<div class="modal-bg" id="statusModal">
<div class="modal-box">
    <div class="mhd"><h3><i class="bi bi-pencil-square"></i> Update Patient Status</h3><div class="mhd-x" onclick="closeModal('statusModal')"><i class="bi bi-x"></i></div></div>
    <form method="post">
    <input type="hidden" name="action" value="update_status">
    <input type="hidden" name="patient_id" id="modal-pid">
    <div class="mbd">
        <div class="field" style="margin-bottom:11px;"><label>Status</label>
            <select name="status" id="modal-status">
                <option>Admitted</option><option>Discharged</option><option>Transferred</option><option>HAMA</option><option>Deceased</option>
            </select>
        </div>
        <div class="field"><label>Discharge Date &amp; Time</label><input type="datetime-local" name="discharge_date"></div>
    </div>
    <div class="mft"><button type="button" class="btn btn-outline" onclick="closeModal('statusModal')">Cancel</button><button type="submit" class="btn btn-primary">Update</button></div>
    </form>
</div>
</div>

<!-- FINALIZE MODAL — Updated with improved payment method selector -->
<div class="modal-bg" id="finalizeModal">
<div class="modal-box" style="max-width:520px;">
    <div class="mhd"><h3><i class="bi bi-check-circle-fill" style="color:var(--teal-l);"></i> Finalize Billing &amp; Discharge</h3><div class="mhd-x" onclick="closeModal('finalizeModal')"><i class="bi bi-x"></i></div></div>
    <form method="post" id="finalizeForm">
    <input type="hidden" name="action" value="finalize_bill">
    <input type="hidden" name="patient_id" value="<?= $pid ?>">
    <div class="mbd">
        <div class="warn-box"><i class="bi bi-exclamation-triangle-fill" style="flex-shrink:0;margin-top:1px;"></i><div><strong>This action cannot be undone.</strong> Room charges will be computed from admission to the discharge time below.</div></div>
        <div class="field" style="margin-bottom:11px;"><label>Discharge Date &amp; Time <span class="req">*</span></label><input type="datetime-local" name="discharge_date" value="<?= date('Y-m-d\TH:i') ?>" required></div>
        <div class="fg2" style="margin-bottom:11px;">
            <div class="field"><label>Discharge Type</label>
                <select name="discharge_type"><option>Recovered</option><option>Improved</option><option>Unimproved</option><option>HAMA (Against Medical Advice)</option><option>Transferred</option><option>Expired</option></select>
            </div>
            <div class="field"><label>Payment Status</label>
                    <select name="payment_status" id="payStatusSel"><option>Pending</option><option>Partial</option><option>Paid</option></select>
            </div>
        </div>
        <div class="fg2" style="margin-bottom:11px;">
            <div class="field"><label>Discount %</label><input type="number" name="discount_pct" value="0" min="0" max="100" step="0.5"></div>
            <div class="field"><label>PhilHealth Deduction ₱</label><input type="number" name="philhealth_deduct" value="0" min="0" step="0.01"></div>
        </div>
        <label class="fin-check"><input type="checkbox" name="senior_discount" value="1"> Apply 20% Senior Citizen / PWD Discount</label>
        <div class="field" style="margin-bottom:12px;"><label>Final Notes</label><textarea name="notes" rows="2" placeholder="Discharge instructions, payment notes…"></textarea></div>

        <!-- ── PAYMENT METHOD — improved card selector ── -->
        <div>
            <div style="font-size:.6rem;font-weight:800;text-transform:uppercase;letter-spacing:.08em;color:var(--s500);margin-bottom:6px;">Payment Method</div>
            <div class="pay-method-grid">
                <label class="pay-method-opt opt-cash" id="pmOptCash">
                    <input type="radio" name="payment_method" value="cash" checked onchange="onPayMethodChange('cash')">
                    <div class="pay-method-ico pmi-cash"><i class="bi bi-cash-coin"></i></div>
                    <div>
                        <div class="pay-method-label">Cash Payment</div>
                        <div class="pay-method-desc">Process at cashier via Billing Summary</div>
                    </div>
                </label>
                <label class="pay-method-opt opt-online" id="pmOptOnline">
                    <input type="radio" name="payment_method" value="online" onchange="onPayMethodChange('online')">
                    <div class="pay-method-ico pmi-online"><i class="bi bi-credit-card-2-front-fill"></i></div>
                    <div>
                        <div class="pay-method-label">Online Payment</div>
                        <div class="pay-method-desc">GCash, Maya, Card, GrabPay</div>
                    </div>
                </label>
            </div>
            <div id="payMethodNote" style="font-size:.67rem;color:var(--s500);margin-top:7px;padding:7px 10px;background:var(--green-l);border-radius:var(--r);border:1px solid #6ee7b7;">
                <i class="bi bi-info-circle" style="color:var(--green);"></i>
                <strong style="color:#065f46;">Cash:</strong> <span style="color:#047857;">You will be redirected to Billing Summary to confirm and complete the cash payment at the cashier.</span>
            </div>
        </div>
    </div>
    <div class="mft">
        <button type="button" class="btn btn-outline" onclick="closeModal('finalizeModal')">Cancel</button>
        <button type="submit" class="btn btn-success" id="finalizeSubmitBtn">
            <i class="bi bi-check-circle-fill"></i>
            <span id="finalizeSubmitLabel">Finalize &amp; Go to Cash Payment</span>
        </button>
    </div>
    </form>
</div>
</div>

<script>
/* ── VIEW SWITCHER ── */
function switchView(v) {
    ['registration','patients','chart','receipt'].forEach(id => {
        document.getElementById('view-'+id)?.classList.add('d-none');
    });
    document.querySelectorAll('.sb-item').forEach(b => b.classList.remove('active'));
    document.getElementById('view-'+v)?.classList.remove('d-none');
    document.querySelector(`.sb-item[onclick*="'${v}'"]`)?.classList.add('active');
    history.replaceState(null,'','?view='+v);
}

/* ── AGE CALC ── */
function calcAge() {
    const dob  = document.getElementById('dob')?.value;
    const disp = document.getElementById('ageDisplay');
    if (!dob || !disp) return;
    const bd  = new Date(dob), now = new Date();
    let age   = now.getFullYear() - bd.getFullYear();
    const m   = now.getMonth() - bd.getMonth();
    if (m < 0 || (m===0 && now.getDate() < bd.getDate())) age--;
    disp.value = age >= 0 ? age + ' years old' : '';
}

/* ── ADMISSION TYPE BADGE ── */
function onAdmTypeChange(inp) {
    const badge = document.getElementById('admTypeSelected');
    if (badge) badge.textContent = inp.value + ' selected';
}

/* ── PAYMENT METHOD CHANGE HANDLER ── */
function onPayMethodChange(method) {
    const noteEl  = document.getElementById('payMethodNote');
    const btnLbl  = document.getElementById('finalizeSubmitLabel');
    const paySelEl = document.getElementById('payStatusSel');

    if (method === 'cash') {
        noteEl.style.background = 'var(--green-l)';
        noteEl.style.borderColor = '#6ee7b7';
        noteEl.innerHTML = '<i class="bi bi-info-circle" style="color:var(--green);"></i> <strong style="color:#065f46;">Cash:</strong> <span style="color:#047857;">You will be redirected to Billing Summary to confirm and complete the cash payment at the cashier.</span>';
        btnLbl.innerHTML = 'Finalize &amp; Go to Cash Payment';
        if (paySelEl) paySelEl.value = 'Paid';
    } else {
        noteEl.style.background = 'var(--blue-l)';
        noteEl.style.borderColor = '#93c5fd';
        noteEl.innerHTML = '<i class="bi bi-info-circle" style="color:var(--blue);"></i> <strong style="color:#1e40af;">Online:</strong> <span style="color:#1d4ed8;">A PayMongo payment link will be generated. Patient can pay via GCash, Maya, Card, or GrabPay.</span>';
        btnLbl.innerHTML = 'Finalize &amp; Create Payment Link';
        if (paySelEl) paySelEl.value = 'Pending';
    }
}

/* ── PANEL TOGGLE ── */
function togglePanel(type) {
    const body = document.getElementById(type+'-body');
    const tog  = document.getElementById(type+'-tog');
    const open = body.classList.toggle('open');
    if (tog) tog.classList.toggle('open', open);
}

/* ── MONEY FORMAT ── */
function fm(n) { return '₱' + parseFloat(n||0).toLocaleString('en-PH',{minimumFractionDigits:2,maximumFractionDigits:2}); }

/* ── CHIP STATE ── */
const sel = {svc:{}, med:{}, sup:{}};

function toggleChip(el, type) {
    const id    = el.dataset.id;
    const name  = el.dataset.name;
    const price = parseFloat(el.dataset.price||0);
    const cat   = el.dataset.cat;
    if (sel[type][id]) {
        el.classList.remove('sel');
        delete sel[type][id];
        removeRow(type, id);
    } else {
        el.classList.add('sel');
        sel[type][id] = {id,name,price,cat,qty:1};
        addRow(type, id, name, price, cat);
    }
    updateBadge(type);
}

function addRow(type, id, name, price, cat) {
    const tbody = document.getElementById(type+'-tbody');
    const empty = document.getElementById(type+'-empty-row');
    if (empty) empty.style.display = 'none';
    const tclss = {svc:'tt-svc',med:'tt-med',sup:'tt-sup'}[type];
    const tr = document.createElement('tr');
    tr.id = type+'-r-'+id;
    tr.innerHTML = `
        <td style="font-weight:700;">${esc(name)}</td>
        <td><span class="type-tag ${tclss}">${esc(cat)}</span></td>
        <td><input type="number" class="qty-inp" name="service_qtys[]" value="1" min="1" max="9999"
            oninput="updQty('${type}','${id}','${price}',this)"></td>
        <td style="text-align:right;font-family:var(--mono);font-size:.79rem;">${fm(price)}</td>
        <td class="lt-val" id="${type}-lt-${id}" style="text-align:right">${fm(price)}</td>
        <td>
            <input type="hidden" name="service_ids[]" value="${id}">
            <button type="button" class="btn btn-danger btn-icon" onclick="removeItem('${type}','${id}')"><i class="bi bi-x"></i></button>
        </td>`;
    tbody.appendChild(tr);
}

function removeRow(type, id) {
    document.getElementById(type+'-r-'+id)?.remove();
    const tbody = document.getElementById(type+'-tbody');
    if (!tbody.querySelector('tr[id]:not([id$="-empty-row"])')) {
        const e = document.getElementById(type+'-empty-row');
        if (e) e.style.display = '';
    }
}

function removeItem(type, id) {
    const chip = document.querySelector(`#${type}-grid .svc-chip[data-id="${id}"]`);
    if (chip) chip.classList.remove('sel');
    delete sel[type][id];
    removeRow(type, id);
    updateBadge(type);
}

function updQty(type, id, price, inp) {
    const qty = Math.max(1, parseInt(inp.value)||1);
    inp.value = qty;
    if (sel[type][id]) sel[type][id].qty = qty;
    const lt = document.getElementById(type+'-lt-'+id);
    if (lt) lt.textContent = fm(parseFloat(price)*qty);
}

function updateBadge(type) {
    const n = Object.keys(sel[type]).length;
    const cntEl   = document.getElementById(type+'-cnt');
    const badgeEl = document.getElementById(type+'-cnt-badge');
    if (cntEl)   cntEl.textContent   = n;
    if (badgeEl) badgeEl.textContent = n+' selected';
}

/* ── CHIP FILTER ── */
const chipCats = {svc:'', sup:''};

function filterChips(type, q) {
    q = q.toLowerCase();
    const cat = chipCats[type] || '';
    document.querySelectorAll(`#${type}-grid .svc-chip`).forEach(c => {
        const nm  = c.dataset.name.toLowerCase().includes(q);
        const ct  = !cat || c.dataset.cat === cat;
        c.style.display = nm && ct ? '' : 'none';
    });
}

function setChipCat(type, cat, btn) {
    chipCats[type] = cat;
    document.querySelectorAll(`#${type}-cats .cat-pill`).forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    const inp = document.querySelector(`#${type}-body .svc-search input`);
    filterChips(type, inp?.value || '');
}

/* ── PATIENT TABLE FILTER ── */
let ptStatusF='', ptTypeF='';
function filterPt(q) {
    q = q.toLowerCase(); let vis=0;
    document.querySelectorAll('.pt-row').forEach(r => {
        const m = r.dataset.search.includes(q) && (!ptStatusF||r.dataset.status===ptStatusF) && (!ptTypeF||r.dataset.admtype===ptTypeF);
        r.style.display = m ? '' : 'none';
        if (m) vis++;
    });
    const e=document.getElementById('pt-empty');
    if(e) e.style.display = vis===0 ? 'block':'none';
}
function filterPtStatus(v){ptStatusF=v.toLowerCase();filterPt(document.getElementById('ptSearch')?.value||'');}
function filterPtType(v){ptTypeF=v.toLowerCase();filterPt(document.getElementById('ptSearch')?.value||'');}

/* ── BILLS FILTER ── */
let billStatusF='';
function filterBills(q) {
    q=q.toLowerCase(); let vis=0;
    document.querySelectorAll('.bill-tr').forEach(r=>{
        const m=r.dataset.search.includes(q)&&(!billStatusF||r.dataset.bst===billStatusF);
        r.style.display=m?'':'none'; if(m) vis++;
    });
    const e=document.getElementById('bills-empty');
    if(e) e.style.display=vis===0?'block':'none';
}
function filterBillStatus(v){billStatusF=v.toLowerCase();filterBills('');}

/* ── MODALS ── */
function openStatusModal(pid, status) {
    document.getElementById('modal-pid').value    = pid;
    document.getElementById('modal-status').value = status;
    document.getElementById('statusModal').classList.add('open');
}
function openFinalizeModal() {
    document.getElementById('finalizeModal').classList.add('open');
}
function closeModal(id) {
    document.getElementById(id).classList.remove('open');
}
document.querySelectorAll('.modal-bg').forEach(bg => bg.addEventListener('click', e => {
    if (e.target===bg) bg.classList.remove('open');
}));
document.addEventListener('keydown', e => {
    if (e.key==='Escape') document.querySelectorAll('.modal-bg').forEach(b=>b.classList.remove('open'));
});

/* ── PREVENT EMPTY SUBMIT ── */
document.getElementById('addOrdersBtn')?.addEventListener('click', function(e) {
    const total = Object.values(sel).reduce((a,t)=>a+Object.keys(t).length,0);
    if (total === 0) {
        e.preventDefault();
        alert('Please select at least one service, medicine, or supply to add to the chart.');
    }
});

/* ── UTIL ── */
function esc(str){ const d=document.createElement('div'); d.textContent=str||''; return d.innerHTML; }

/* ── INIT ── */
(function(){
    const v = '<?= safe($currentView) ?>';
    document.querySelectorAll('.sb-item').forEach(b=>{
        if(b.getAttribute('onclick')?.includes(`'${v}'`)) b.classList.add('active');
    });
    // Initialise payment method note
    onPayMethodChange('cash');
})();
</script>
</body>
</html>