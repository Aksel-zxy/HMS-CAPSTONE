<?php
// ============================================================
//  HOSPITAL ADMISSIONS & BILLING ENGINE  —  Core Logic
// ============================================================

session_start();

// ─── In-memory "database" for this session ────────────────
if (!isset($_SESSION['admissions']))   $_SESSION['admissions']   = [];
if (!isset($_SESSION['audit_log']))    $_SESSION['audit_log']    = [];
if (!isset($_SESSION['charges_log']))  $_SESSION['charges_log']  = [];

// ─── Room catalogue ───────────────────────────────────────
$ROOMS = [
    ['room_id'=>'R-101','room_type'=>'Private',    'hourly_rate'=>25.00,'capacity'=>1,'status'=>'available'],
    ['room_id'=>'R-102','room_type'=>'Private',    'hourly_rate'=>25.00,'capacity'=>1,'status'=>'available'],
    ['room_id'=>'R-201','room_type'=>'Shared',     'hourly_rate'=>12.00,'capacity'=>2,'status'=>'available'],
    ['room_id'=>'R-202','room_type'=>'Shared',     'hourly_rate'=>12.00,'capacity'=>2,'status'=>'available'],
    ['room_id'=>'R-301','room_type'=>'ICU',        'hourly_rate'=>85.00,'capacity'=>1,'status'=>'available'],
    ['room_id'=>'R-302','room_type'=>'ICU',        'hourly_rate'=>85.00,'capacity'=>1,'status'=>'available'],
    ['room_id'=>'R-401','room_type'=>'General Ward','hourly_rate'=>8.00, 'capacity'=>4,'status'=>'available'],
    ['room_id'=>'R-402','room_type'=>'General Ward','hourly_rate'=>8.00, 'capacity'=>4,'status'=>'available'],
    ['room_id'=>'R-501','room_type'=>'Recovery',   'hourly_rate'=>35.00,'capacity'=>1,'status'=>'available'],
];

// Mark rooms already in use
foreach ($_SESSION['admissions'] as $adm) {
    if (empty($adm['discharge_datetime'])) {
        foreach ($ROOMS as &$r) {
            if ($r['room_id'] === $adm['assigned_room']['room_id']) {
                $r['status'] = 'occupied';
            }
        }
    }
}
unset($r);

// ─── Helpers ──────────────────────────────────────────────
function uuid4(): string {
    return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0,0xffff),mt_rand(0,0xffff),mt_rand(0,0xffff),
        mt_rand(0,0x0fff)|0x4000,mt_rand(0,0x3fff)|0x8000,
        mt_rand(0,0xffff),mt_rand(0,0xffff),mt_rand(0,0xffff));
}

function now_iso(): string {
    return (new DateTime())->format(DateTime::ATOM);
}

function fmt_money(float $n): string {
    return '$'.number_format($n, 2);
}

function hours_between(string $start, string $end): float {
    $s = new DateTime($start);
    $e = new DateTime($end);
    $diff = $e->getTimestamp() - $s->getTimestamp();
    return max(0, round($diff / 3600, 2));
}

function audit(string $action, string $detail = ''): void {
    $_SESSION['audit_log'][] = ['ts'=>now_iso(),'action'=>$action,'detail'=>$detail];
}

function find_room(array $rooms, string $preferred_type, string $skip_id = ''): ?array {
    foreach ($rooms as $r) {
        if ($r['status']==='available' && strtolower($r['room_type'])===strtolower($preferred_type) && $r['room_id']!==$skip_id)
            return $r;
    }
    return null;
}

// ─── Action Router ────────────────────────────────────────
$action  = $_POST['action'] ?? '';
$message = '';
$msgType = 'success';

// ── ADMIT ──────────────────────────────────────────────────
if ($action === 'admit') {
    $errors = [];

    $pid   = trim($_POST['patient_id']   ?? '');
    $pname = trim($_POST['patient_name'] ?? '');
    $dob   = trim($_POST['date_of_birth']?? '');
    $gender= trim($_POST['gender']       ?? '');
    $phone = trim($_POST['phone']        ?? '');
    $email = trim($_POST['email']        ?? '');
    $addr  = trim($_POST['address']      ?? '');
    $ins   = trim($_POST['insurance_provider'] ?? '');
    $pol   = trim($_POST['policy_number']      ?? '');
    $adm_dt= trim($_POST['admission_datetime'] ?? '');
    $dept  = trim($_POST['admitting_department'] ?? '');
    $diag  = trim($_POST['initial_diagnosis']    ?? '');
    $rtype = trim($_POST['preferred_room_type']  ?? 'General Ward');
    $phys  = trim($_POST['attending_physician_id']?? '');
    $plan  = trim($_POST['anticipated_treatment_plan']?? '');

    if (!$pid)    $errors[] = 'Patient ID is required.';
    if (!$pname)  $errors[] = 'Patient name is required.';
    if (!$dob || !DateTime::createFromFormat('Y-m-d', $dob))
        $errors[] = 'Date of birth must be YYYY-MM-DD.';
    if (!$gender) $errors[] = 'Gender is required.';
    if (!$adm_dt) $errors[] = 'Admission date-time is required.';
    else {
        try { $dt = new DateTime($adm_dt); }
        catch (Exception $e) { $errors[] = 'Admission date-time must be ISO 8601.'; }
    }
    if (!$dept)   $errors[] = 'Admitting department is required.';
    if (!$diag)   $errors[] = 'Initial diagnosis is required.';

    // Duplicate patient check (only one active admission per patient)
    foreach ($_SESSION['admissions'] as $a) {
        if ($a['patient_id']==$pid && empty($a['discharge_datetime'])) {
            $errors[] = "Patient $pid already has an active admission (#{$a['admission_id']}).";
        }
    }

    if ($errors) {
        $message = implode('<br>', $errors);
        $msgType = 'error';
    } else {
        // Find room
        $room = find_room($ROOMS, $rtype);
        $fallback_note = '';
        if (!$room) {
            // Try any available room
            foreach ($ROOMS as $r) {
                if ($r['status']==='available') { $room=$r; break; }
            }
            if ($room) $fallback_note = "Preferred room type '$rtype' unavailable; assigned {$room['room_type']} instead.";
            else { $message='No rooms currently available.'; $msgType='error'; goto done; }
        }

        $adm_id = 'ADM-'.strtoupper(substr(uuid4(),0,8));
        $record = [
            'admission_id'          => $adm_id,
            'patient_id'            => $pid,
            'patient_name'          => $pname,
            'date_of_birth'         => $dob,
            'gender'                => $gender,
            'contact_info'          => ['phone'=>$phone,'email'=>$email,'address'=>$addr],
            'insurance_provider'    => $ins,
            'policy_number'         => $pol ? str_repeat('*',strlen($pol)-4).substr($pol,-4) : '',
            'admission_datetime'    => (new DateTime($adm_dt))->format(DateTime::ATOM),
            'admitting_department'  => $dept,
            'initial_diagnosis'     => $diag,
            'attending_physician_id'=> $phys,
            'anticipated_treatment_plan'=> $plan,
            'assigned_room'         => ['room_id'=>$room['room_id'],'room_type'=>$room['room_type'],'hourly_rate'=>$room['hourly_rate']],
            'discharge_datetime'    => null,
            'services'              => [],
            'medications'           => [],
            'other_fees'            => [],
            'billing_adjustments'   => [],
            'fallback_note'         => $fallback_note,
        ];
        $_SESSION['admissions'][$adm_id] = $record;
        audit('ADMISSION_CREATED', "Patient $pid admitted as $adm_id to room {$room['room_id']}");
        if ($fallback_note) audit('ROOM_FALLBACK', $fallback_note);
        $message = "Patient admitted successfully. Admission ID: <strong>$adm_id</strong>" . ($fallback_note ? "<br><em>⚠ $fallback_note</em>" : '');
    }
    done:;
}

// ── ADD SERVICE / MEDICATION / FEE ────────────────────────
if ($action === 'add_charge') {
    $adm_id    = trim($_POST['adm_id']   ?? '');
    $charge_type= trim($_POST['charge_type']?? '');
    if (!isset($_SESSION['admissions'][$adm_id])) {
        $message='Admission not found.'; $msgType='error';
    } elseif (!empty($_SESSION['admissions'][$adm_id]['discharge_datetime'])) {
        $message='Cannot add charges to a discharged admission.'; $msgType='error';
    } else {
        if ($charge_type==='service') {
            $_SESSION['admissions'][$adm_id]['services'][] = [
                'service_name' => trim($_POST['service_name']?? ''),
                'description'  => trim($_POST['svc_description']?? ''),
                'quantity'     => (int)($_POST['quantity']??1),
                'unit_price'   => (float)($_POST['unit_price']??0),
                'total_price'  => (int)($_POST['quantity']??1)*(float)($_POST['unit_price']??0),
            ];
            audit('SERVICE_ADDED', "$adm_id: ".trim($_POST['service_name']??''));
        } elseif ($charge_type==='medication') {
            $_SESSION['admissions'][$adm_id]['medications'][] = [
                'name'               => trim($_POST['med_name']??''),
                'dosage'             => trim($_POST['dosage']??''),
                'administration_time'=> trim($_POST['admin_time']??now_iso()),
                'price'              => (float)($_POST['med_price']??0),
            ];
            audit('MEDICATION_ADDED', "$adm_id: ".trim($_POST['med_name']??''));
        } elseif ($charge_type==='fee') {
            $_SESSION['admissions'][$adm_id]['other_fees'][] = [
                'item'   => trim($_POST['fee_item']??''),
                'amount' => (float)($_POST['fee_amount']??0),
            ];
            audit('FEE_ADDED', "$adm_id: ".trim($_POST['fee_item']??''));
        }
        $message = 'Charge recorded successfully.';
    }
}

// ── DISCHARGE ─────────────────────────────────────────────
if ($action === 'discharge') {
    $adm_id  = trim($_POST['adm_id'] ?? '');
    $dis_dt  = trim($_POST['discharge_datetime'] ?? '');
    $fin_diag= trim($_POST['final_diagnosis'] ?? '');
    $treat   = trim($_POST['treatments_given']?? '');
    $meds_gvn= trim($_POST['medications_administered']?? '');
    $followup= trim($_POST['follow_up_instructions']?? '');
    $discount= (float)($_POST['discount_pct']??0);
    $ins_cov = (float)($_POST['insurance_coverage_pct']??0);

    if (!isset($_SESSION['admissions'][$adm_id])) {
        $message='Admission not found.'; $msgType='error';
    } elseif (!empty($_SESSION['admissions'][$adm_id]['discharge_datetime'])) {
        $message='Patient already discharged.'; $msgType='error';
    } elseif (!$dis_dt) {
        $message='Discharge date-time is required.'; $msgType='error';
    } else {
        $adm = &$_SESSION['admissions'][$adm_id];
        try {
            $dis_obj = new DateTime($dis_dt);
            $adm_obj = new DateTime($adm['admission_datetime']);
            if ($dis_obj <= $adm_obj) { $message='Discharge must be after admission.'; $msgType='error'; goto done2; }
        } catch(Exception $e) { $message='Invalid discharge date-time.'; $msgType='error'; goto done2; }

        $adm['discharge_datetime']         = $dis_obj->format(DateTime::ATOM);
        $adm['final_diagnosis']            = $fin_diag;
        $adm['treatments_given']           = $treat;
        $adm['medications_administered']   = $meds_gvn;
        $adm['follow_up_instructions']     = $followup;
        $adm['billing_adjustments']['discount_pct']      = $discount;
        $adm['billing_adjustments']['insurance_coverage_pct'] = $ins_cov;
        audit('DISCHARGE_PROCESSED', "$adm_id discharged at ".$adm['discharge_datetime']);
        audit('BILLING_GENERATED',   "$adm_id billing finalised");
        $message = "Patient discharged. Bill generated for <strong>$adm_id</strong>.";
        done2:;
    }
}

// ── CLEAR ALL ─────────────────────────────────────────────
if ($action === 'clear_all') {
    $_SESSION['admissions'] = [];
    $_SESSION['audit_log']  = [];
    $message = 'All session data cleared.';
}

// ─── Bill Calculator ──────────────────────────────────────
function compute_bill(array $adm): array {
    $hours = $adm['discharge_datetime']
        ? hours_between($adm['admission_datetime'], $adm['discharge_datetime'])
        : hours_between($adm['admission_datetime'], now_iso());

    $room_charge = round($hours * $adm['assigned_room']['hourly_rate'], 2);
    $svc_total   = array_sum(array_column($adm['services'],   'total_price'));
    $med_total   = array_sum(array_column($adm['medications'],'price'));
    $fee_total   = array_sum(array_column($adm['other_fees'], 'amount'));

    $subtotal    = $room_charge + $svc_total + $med_total + $fee_total;
    $disc_pct    = (float)($adm['billing_adjustments']['discount_pct']  ?? 0);
    $ins_pct     = (float)($adm['billing_adjustments']['insurance_coverage_pct'] ?? 0);
    $discount_amt= round($subtotal * $disc_pct  / 100, 2);
    $after_disc  = $subtotal - $discount_amt;
    $ins_amt     = round($after_disc * $ins_pct / 100, 2);
    $due         = round($after_disc - $ins_amt, 2);

    return compact('hours','room_charge','svc_total','med_total','fee_total',
                   'subtotal','discount_amt','ins_amt','due');
}

?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>MediCore — Hospital Admissions & Billing</title>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;700;900&family=DM+Sans:wght@300;400;500;600&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
<style>
/* ─── DESIGN SYSTEM ─────────────────────────────────── */
:root {
  --navy:      #0a1628;
  --navy-mid:  #132240;
  --navy-light:#1e3560;
  --sky:       #2176ff;
  --sky-light: #5a9eff;
  --teal:      #00c9b1;
  --gold:      #f5c842;
  --danger:    #ff4d6d;
  --success:   #00d68f;
  --warn:      #ffaa00;
  --text:      #e8edf5;
  --text-muted:#8da0bb;
  --border:    rgba(255,255,255,.08);
  --card:      rgba(255,255,255,.04);
  --card-hover:rgba(255,255,255,.07);
  --radius:    14px;
  --radius-sm: 8px;
  --shadow:    0 8px 32px rgba(0,0,0,.45);
  --transition:.22s cubic-bezier(.4,0,.2,1);
}

*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
html{scroll-behavior:smooth}
body{
  font-family:'DM Sans',sans-serif;
  background:var(--navy);
  color:var(--text);
  min-height:100vh;
  line-height:1.6;
  overflow-x:hidden;
}

/* ─── GRID TEXTURE ───────────────────────────────────── */
body::before{
  content:'';
  position:fixed;inset:0;
  background-image:
    linear-gradient(rgba(33,118,255,.04) 1px,transparent 1px),
    linear-gradient(90deg,rgba(33,118,255,.04) 1px,transparent 1px);
  background-size:40px 40px;
  pointer-events:none;z-index:0;
}

/* ─── GLOW ORBS ──────────────────────────────────────── */
.glow-orb{position:fixed;border-radius:50%;filter:blur(100px);pointer-events:none;z-index:0;opacity:.18}
.glow-orb-1{width:600px;height:600px;background:var(--sky);top:-200px;right:-150px;}
.glow-orb-2{width:500px;height:500px;background:var(--teal);bottom:-150px;left:-100px;}

/* ─── LAYOUT ─────────────────────────────────────────── */
.wrap{position:relative;z-index:1;max-width:1380px;margin:0 auto;padding:0 24px 60px;}

/* ─── HEADER ─────────────────────────────────────────── */
header{
  display:flex;align-items:center;justify-content:space-between;
  padding:32px 0 28px;
  border-bottom:1px solid var(--border);
  margin-bottom:36px;
}
.logo{display:flex;align-items:center;gap:14px}
.logo-icon{
  width:48px;height:48px;border-radius:12px;
  background:linear-gradient(135deg,var(--sky),var(--teal));
  display:flex;align-items:center;justify-content:center;
  font-size:22px;box-shadow:0 4px 20px rgba(33,118,255,.4);
}
.logo-text h1{
  font-family:'Playfair Display',serif;
  font-size:1.6rem;font-weight:900;
  background:linear-gradient(90deg,var(--text),var(--sky-light));
  -webkit-background-clip:text;-webkit-text-fill-color:transparent;
  line-height:1.1;
}
.logo-text span{font-size:.75rem;color:var(--text-muted);letter-spacing:.12em;text-transform:uppercase}
.header-meta{text-align:right;font-size:.8rem;color:var(--text-muted)}
.header-meta strong{color:var(--teal);display:block;font-size:.9rem}

/* ─── TABS ───────────────────────────────────────────── */
.tabs{display:flex;gap:6px;margin-bottom:28px;background:rgba(0,0,0,.25);
       border-radius:12px;padding:6px;width:fit-content;border:1px solid var(--border);}
.tab-btn{
  padding:9px 22px;border:none;background:transparent;color:var(--text-muted);
  border-radius:8px;cursor:pointer;font-family:'DM Sans',sans-serif;font-size:.875rem;font-weight:500;
  transition:var(--transition);white-space:nowrap;
}
.tab-btn:hover{color:var(--text);background:var(--card)}
.tab-btn.active{background:linear-gradient(135deg,var(--sky),var(--navy-light));color:#fff;
                 box-shadow:0 2px 12px rgba(33,118,255,.4);}
.tab-panel{display:none}.tab-panel.active{display:block}

/* ─── MESSAGE BAR ────────────────────────────────────── */
.msg{
  padding:14px 20px;border-radius:var(--radius-sm);margin-bottom:24px;
  font-size:.9rem;line-height:1.5;border-left:4px solid;
  animation:slideIn .3s ease;
}
.msg.success{background:rgba(0,214,143,.1);border-color:var(--success);color:#a0ffd9}
.msg.error  {background:rgba(255,77,109,.1);border-color:var(--danger); color:#ffb3c0}
@keyframes slideIn{from{opacity:0;transform:translateY(-8px)}to{opacity:1;transform:translateY(0)}}

/* ─── CARDS ──────────────────────────────────────────── */
.card{
  background:var(--card);border:1px solid var(--border);
  border-radius:var(--radius);padding:28px;margin-bottom:24px;
  transition:var(--transition);
}
.card:hover{background:var(--card-hover);border-color:rgba(33,118,255,.2)}
.card-title{
  font-family:'Playfair Display',serif;font-size:1.1rem;font-weight:700;
  color:var(--text);margin-bottom:20px;display:flex;align-items:center;gap:10px;
  padding-bottom:14px;border-bottom:1px solid var(--border);
}
.card-title .icon{
  width:32px;height:32px;border-radius:8px;display:flex;align-items:center;justify-content:center;
  font-size:15px;flex-shrink:0;
}
.icon-blue {background:rgba(33,118,255,.18);color:var(--sky)}
.icon-teal {background:rgba(0,201,177,.18);color:var(--teal)}
.icon-gold {background:rgba(245,200,66,.18);color:var(--gold)}
.icon-red  {background:rgba(255,77,109,.18);color:var(--danger)}

/* ─── FORM GRID ──────────────────────────────────────── */
.form-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:16px}
.form-grid.cols-2{grid-template-columns:repeat(2,1fr)}
.form-grid.cols-3{grid-template-columns:repeat(3,1fr)}
.form-group{display:flex;flex-direction:column;gap:6px}
.form-group.full{grid-column:1/-1}
label{font-size:.8rem;font-weight:600;color:var(--text-muted);letter-spacing:.04em;text-transform:uppercase}
input,select,textarea{
  background:rgba(255,255,255,.06);border:1px solid var(--border);
  border-radius:var(--radius-sm);padding:10px 14px;
  color:var(--text);font-family:'DM Sans',sans-serif;font-size:.9rem;
  transition:var(--transition);outline:none;width:100%;
}
input:focus,select:focus,textarea:focus{
  border-color:var(--sky);background:rgba(33,118,255,.08);
  box-shadow:0 0 0 3px rgba(33,118,255,.15);
}
input::placeholder,textarea::placeholder{color:var(--text-muted)}
select option{background:var(--navy-mid);color:var(--text)}
textarea{resize:vertical;min-height:80px}

/* ─── BUTTONS ────────────────────────────────────────── */
.btn{
  display:inline-flex;align-items:center;gap:8px;
  padding:11px 24px;border:none;border-radius:var(--radius-sm);
  font-family:'DM Sans',sans-serif;font-size:.875rem;font-weight:600;
  cursor:pointer;transition:var(--transition);text-decoration:none;white-space:nowrap;
}
.btn-primary{
  background:linear-gradient(135deg,var(--sky),#1558d6);color:#fff;
  box-shadow:0 4px 16px rgba(33,118,255,.35);
}
.btn-primary:hover{transform:translateY(-2px);box-shadow:0 6px 24px rgba(33,118,255,.5)}
.btn-teal{background:linear-gradient(135deg,var(--teal),#00a192);color:var(--navy);
           box-shadow:0 4px 16px rgba(0,201,177,.3);}
.btn-teal:hover{transform:translateY(-2px);box-shadow:0 6px 24px rgba(0,201,177,.45)}
.btn-danger{background:linear-gradient(135deg,var(--danger),#cc2244);color:#fff;
             box-shadow:0 4px 16px rgba(255,77,109,.3);}
.btn-danger:hover{transform:translateY(-2px)}
.btn-ghost{background:transparent;border:1px solid var(--border);color:var(--text-muted)}
.btn-ghost:hover{border-color:var(--sky);color:var(--sky);background:rgba(33,118,255,.08)}
.btn-sm{padding:7px 16px;font-size:.8rem}

/* ─── STATS ROW ──────────────────────────────────────── */
.stats-row{display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:16px;margin-bottom:28px}
.stat-card{
  background:var(--card);border:1px solid var(--border);border-radius:var(--radius);
  padding:20px;text-align:center;transition:var(--transition);
}
.stat-card:hover{border-color:rgba(33,118,255,.3);transform:translateY(-2px)}
.stat-num{font-family:'Playfair Display',serif;font-size:2rem;font-weight:900;
           background:linear-gradient(135deg,var(--sky),var(--teal));
           -webkit-background-clip:text;-webkit-text-fill-color:transparent;line-height:1}
.stat-label{font-size:.75rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:.08em;margin-top:4px}

/* ─── TABLE ──────────────────────────────────────────── */
.table-wrap{overflow-x:auto;border-radius:var(--radius);border:1px solid var(--border)}
table{width:100%;border-collapse:collapse}
thead th{
  background:rgba(33,118,255,.12);padding:12px 16px;
  font-size:.75rem;font-weight:700;color:var(--text-muted);
  text-transform:uppercase;letter-spacing:.08em;text-align:left;
}
tbody td{padding:12px 16px;font-size:.875rem;border-top:1px solid var(--border);vertical-align:middle}
tbody tr:hover{background:var(--card-hover)}
.badge{
  display:inline-flex;align-items:center;padding:3px 10px;border-radius:999px;
  font-size:.72rem;font-weight:700;letter-spacing:.04em;text-transform:uppercase;
}
.badge-active  {background:rgba(0,214,143,.15);color:var(--success)}
.badge-discharged{background:rgba(138,153,179,.15);color:var(--text-muted)}
.badge-icu     {background:rgba(255,77,109,.18);color:var(--danger)}
.badge-private {background:rgba(33,118,255,.18);color:var(--sky)}
.badge-shared  {background:rgba(245,200,66,.18);color:var(--gold)}
.badge-general {background:rgba(0,201,177,.18);color:var(--teal)}
.badge-recovery{background:rgba(100,200,255,.18);color:#6dcff6}

/* ─── BILLING PANEL ──────────────────────────────────── */
.bill-wrap{background:var(--navy-mid);border:1px solid var(--border);border-radius:var(--radius);overflow:hidden}
.bill-header{
  background:linear-gradient(135deg,var(--navy-light),var(--sky) 180%);
  padding:28px 32px;position:relative;overflow:hidden;
}
.bill-header::after{
  content:'';position:absolute;top:-40%;right:-10%;
  width:300px;height:300px;background:rgba(255,255,255,.05);
  border-radius:50%;
}
.bill-header h2{font-family:'Playfair Display',serif;font-size:1.4rem;margin-bottom:4px}
.bill-header p{font-size:.82rem;opacity:.75}
.bill-body{padding:28px 32px}
.bill-line{display:flex;justify-content:space-between;align-items:center;
            padding:10px 0;border-bottom:1px solid var(--border);font-size:.875rem}
.bill-line:last-child{border:none}
.bill-line .lbl{color:var(--text-muted)}
.bill-line .val{font-family:'DM Mono',monospace;font-weight:500}
.bill-total{
  display:flex;justify-content:space-between;align-items:center;
  margin-top:16px;padding:16px 20px;
  background:linear-gradient(90deg,rgba(33,118,255,.12),rgba(0,201,177,.08));
  border-radius:var(--radius-sm);border:1px solid rgba(33,118,255,.2);
}
.bill-total .lbl{font-weight:700;font-size:1rem}
.bill-total .val{
  font-family:'Playfair Display',serif;font-size:1.5rem;font-weight:900;
  color:var(--teal);
}
.bill-section-title{
  font-size:.7rem;text-transform:uppercase;letter-spacing:.1em;
  color:var(--sky);font-weight:700;margin:18px 0 8px;
  display:flex;align-items:center;gap:8px;
}
.bill-section-title::after{content:'';flex:1;height:1px;background:rgba(33,118,255,.2)}

/* ─── ROOM GRID ──────────────────────────────────────── */
.room-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:12px}
.room-card{
  background:var(--card);border:1px solid var(--border);border-radius:var(--radius-sm);
  padding:14px;text-align:center;transition:var(--transition);
}
.room-card.occupied{opacity:.45;cursor:not-allowed}
.room-card.available{cursor:default}
.room-card.available:hover{border-color:var(--teal);transform:translateY(-2px)}
.room-id{font-family:'DM Mono',monospace;font-size:.85rem;font-weight:500;color:var(--sky)}
.room-type-txt{font-size:.75rem;color:var(--text-muted);margin:3px 0}
.room-rate{font-size:.8rem;color:var(--teal);font-family:'DM Mono',monospace}
.room-status{margin-top:6px}

/* ─── AUDIT LOG ──────────────────────────────────────── */
.audit-entry{
  display:flex;gap:14px;padding:10px 0;border-bottom:1px solid var(--border);
  font-size:.82rem;
}
.audit-entry:last-child{border:none}
.audit-ts{color:var(--text-muted);font-family:'DM Mono',monospace;white-space:nowrap;font-size:.75rem}
.audit-action{color:var(--sky);font-weight:600;white-space:nowrap}
.audit-detail{color:var(--text)}

/* ─── SECTION DIVIDER ─────────────────────────────────── */
.section-header{
  display:flex;align-items:center;justify-content:space-between;
  margin-bottom:20px;
}
.section-title{
  font-family:'Playfair Display',serif;font-size:1.3rem;font-weight:700;
}

/* ─── ACCORDION ──────────────────────────────────────── */
details.adm-detail{
  background:var(--card);border:1px solid var(--border);border-radius:var(--radius);
  margin-bottom:12px;overflow:hidden;transition:var(--transition);
}
details.adm-detail[open]{border-color:rgba(33,118,255,.3)}
details.adm-detail > summary{
  list-style:none;cursor:pointer;padding:18px 24px;
  display:flex;align-items:center;gap:14px;user-select:none;
}
details.adm-detail > summary::-webkit-details-marker{display:none}
details.adm-detail > summary::after{
  content:'▸';margin-left:auto;color:var(--text-muted);
  transition:var(--transition);
}
details.adm-detail[open] > summary::after{transform:rotate(90deg);color:var(--sky)}
.adm-detail-body{padding:0 24px 24px;border-top:1px solid var(--border)}

/* ─── RESPONSIVE ─────────────────────────────────────── */
@media(max-width:768px){
  .form-grid{grid-template-columns:1fr}
  .form-grid.cols-2,.form-grid.cols-3{grid-template-columns:1fr}
  .stats-row{grid-template-columns:1fr 1fr}
  header{flex-direction:column;gap:12px;text-align:center}
  .tabs{width:100%;flex-wrap:wrap}
  .bill-body,.bill-header{padding:20px}
}

/* ─── UTILITIES ──────────────────────────────────────── */
.flex{display:flex}.gap-2{gap:8px}.gap-3{gap:12px}.gap-4{gap:16px}
.mt-2{margin-top:8px}.mt-3{margin-top:12px}.mt-4{margin-top:16px}
.text-muted{color:var(--text-muted)}.text-sm{font-size:.8rem}
.mono{font-family:'DM Mono',monospace}
.text-teal{color:var(--teal)}.text-sky{color:var(--sky)}
.text-gold{color:var(--gold)}.text-danger{color:var(--danger)}
.text-success{color:var(--success)}
.w-full{width:100%}
hr.divider{border:none;border-top:1px solid var(--border);margin:20px 0}

/* ─── PRINT ──────────────────────────────────────────── */
@media print{
  body{background:#fff;color:#000}
  .tabs,.btn,.glow-orb,body::before,header .btn,form{display:none!important}
  .bill-wrap{border:1px solid #ccc;break-inside:avoid}
  .bill-header{background:#1e3560;color:#fff;-webkit-print-color-adjust:exact}
}
</style>
</head>
<body>
<div class="glow-orb glow-orb-1"></div>
<div class="glow-orb glow-orb-2"></div>

<div class="wrap">

<!-- ─── HEADER ─────────────────────────────────────────── -->
<header>
  <div class="logo">
    <div class="logo-icon">🏥</div>
    <div class="logo-text">
      <h1>MediCore HMS</h1>
      <span>Hospital Management System</span>
    </div>
  </div>
  <div class="header-meta">
    <strong><?= date('D, d M Y — H:i') ?></strong>
    Admissions &amp; Billing Engine v2.0
  </div>
</header>

<?php if ($message): ?>
<div class="msg <?= $msgType ?>"><?= $message ?></div>
<?php endif; ?>

<!-- ─── STATS ──────────────────────────────────────────── -->
<?php
$total_adm   = count($_SESSION['admissions']);
$active_adm  = count(array_filter($_SESSION['admissions'], fn($a)=>empty($a['discharge_datetime'])));
$discharged  = $total_adm - $active_adm;
$avail_rooms = count(array_filter($ROOMS, fn($r)=>$r['status']==='available'));
?>
<div class="stats-row">
  <div class="stat-card">
    <div class="stat-num"><?= $total_adm ?></div>
    <div class="stat-label">Total Admissions</div>
  </div>
  <div class="stat-card">
    <div class="stat-num"><?= $active_adm ?></div>
    <div class="stat-label">Active Patients</div>
  </div>
  <div class="stat-card">
    <div class="stat-num"><?= $discharged ?></div>
    <div class="stat-label">Discharged</div>
  </div>
  <div class="stat-card">
    <div class="stat-num"><?= $avail_rooms ?></div>
    <div class="stat-label">Rooms Available</div>
  </div>
  <div class="stat-card">
    <div class="stat-num"><?= count($ROOMS) ?></div>
    <div class="stat-label">Total Rooms</div>
  </div>
</div>

<!-- ─── TABS ───────────────────────────────────────────── -->
<div class="tabs">
  <button class="tab-btn active" onclick="switchTab('admit')">🛏 Admit Patient</button>
  <button class="tab-btn"       onclick="switchTab('admissions')">📋 Admissions</button>
  <button class="tab-btn"       onclick="switchTab('charges')">💉 Add Charges</button>
  <button class="tab-btn"       onclick="switchTab('discharge')">🚪 Discharge</button>
  <button class="tab-btn"       onclick="switchTab('rooms')">🏠 Rooms</button>
  <button class="tab-btn"       onclick="switchTab('audit')">📜 Audit Log</button>
</div>

<!-- ════════════════════════════════════════════════════════
     TAB 1 — ADMIT PATIENT
════════════════════════════════════════════════════════ -->
<div id="tab-admit" class="tab-panel active">
<form method="POST">
<input type="hidden" name="action" value="admit">
<div class="card">
  <div class="card-title"><span class="icon icon-blue">👤</span> Patient Demographics</div>
  <div class="form-grid">
    <div class="form-group">
      <label>Patient ID *</label>
      <input name="patient_id" required placeholder="e.g. PAT-0042">
    </div>
    <div class="form-group">
      <label>Full Name *</label>
      <input name="patient_name" required placeholder="First Last">
    </div>
    <div class="form-group">
      <label>Date of Birth *</label>
      <input name="date_of_birth" type="date" required>
    </div>
    <div class="form-group">
      <label>Gender *</label>
      <select name="gender" required>
        <option value="">Select…</option>
        <option value="M">Male</option>
        <option value="F">Female</option>
        <option value="Other">Other</option>
      </select>
    </div>
    <div class="form-group">
      <label>Phone</label>
      <input name="phone" placeholder="+1 555 000 0000">
    </div>
    <div class="form-group">
      <label>Email</label>
      <input name="email" type="email" placeholder="patient@email.com">
    </div>
    <div class="form-group full">
      <label>Address</label>
      <input name="address" placeholder="Street, City, State, ZIP">
    </div>
    <div class="form-group">
      <label>Insurance Provider</label>
      <input name="insurance_provider" placeholder="e.g. BlueCross">
    </div>
    <div class="form-group">
      <label>Policy Number</label>
      <input name="policy_number" placeholder="Policy # (masked in logs)">
    </div>
  </div>
</div>

<div class="card">
  <div class="card-title"><span class="icon icon-teal">🏥</span> Admission Details</div>
  <div class="form-grid">
    <div class="form-group">
      <label>Admission Date &amp; Time *</label>
      <input name="admission_datetime" type="datetime-local" required
             value="<?= date('Y-m-d\TH:i') ?>">
    </div>
    <div class="form-group">
      <label>Admitting Department *</label>
      <select name="admitting_department" required>
        <option value="">Select…</option>
        <?php foreach(['Emergency','General Medicine','Surgery','Cardiology','Neurology','Pediatrics','Orthopaedics','Oncology','Obstetrics','ICU'] as $d): ?>
        <option><?= $d ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-group">
      <label>Preferred Room Type</label>
      <select name="preferred_room_type">
        <?php foreach(['Private','Shared','ICU','General Ward','Recovery'] as $rt): ?>
        <option><?= $rt ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-group">
      <label>Attending Physician ID</label>
      <input name="attending_physician_id" placeholder="e.g. DR-001">
    </div>
    <div class="form-group full">
      <label>Initial Diagnosis *</label>
      <textarea name="initial_diagnosis" required placeholder="Brief presenting condition…"></textarea>
    </div>
    <div class="form-group full">
      <label>Anticipated Treatment Plan</label>
      <textarea name="anticipated_treatment_plan" placeholder="Proposed interventions, observations…"></textarea>
    </div>
  </div>
</div>

<div class="flex gap-3">
  <button class="btn btn-primary" type="submit">✅ Confirm Admission</button>
  <button class="btn btn-ghost"   type="reset">↩ Clear Form</button>
</div>
</form>
</div>

<!-- ════════════════════════════════════════════════════════
     TAB 2 — ADMISSIONS LIST
════════════════════════════════════════════════════════ -->
<div id="tab-admissions" class="tab-panel">
<div class="section-header">
  <div class="section-title">All Admissions</div>
  <?php if (!empty($_SESSION['admissions'])): ?>
  <form method="POST" onsubmit="return confirm('Clear ALL session data?')">
    <input type="hidden" name="action" value="clear_all">
    <button class="btn btn-danger btn-sm">🗑 Clear All</button>
  </form>
  <?php endif; ?>
</div>

<?php if (empty($_SESSION['admissions'])): ?>
<div class="card" style="text-align:center;padding:48px">
  <div style="font-size:3rem;margin-bottom:12px">📭</div>
  <div class="text-muted">No admissions recorded yet.</div>
</div>
<?php else: ?>
<?php foreach (array_reverse($_SESSION['admissions'], true) as $adm_id => $adm):
  $discharged = !empty($adm['discharge_datetime']);
  $bill = compute_bill($adm);
?>
<details class="adm-detail">
  <summary>
    <div>
      <div class="mono text-sky" style="font-size:.9rem"><?= htmlspecialchars($adm_id) ?></div>
      <div style="font-size:.85rem;color:var(--text-muted)"><?= htmlspecialchars($adm['patient_name']) ?> &middot; <?= htmlspecialchars($adm['admitting_department']) ?></div>
    </div>
    <div style="display:flex;gap:8px;align-items:center;margin-left:auto;margin-right:16px">
      <?php
        $rt = $adm['assigned_room']['room_type'];
        $bc = match(true){
          str_contains($rt,'ICU')     =>'badge-icu',
          str_contains($rt,'Private') =>'badge-private',
          str_contains($rt,'Shared')  =>'badge-shared',
          str_contains($rt,'Recovery')=>'badge-recovery',
          default                     =>'badge-general'
        };
      ?>
      <span class="badge <?= $bc ?>"><?= $rt ?></span>
      <span class="badge <?= $discharged?'badge-discharged':'badge-active' ?>">
        <?= $discharged ? 'Discharged' : 'Active' ?>
      </span>
    </div>
  </summary>
  <div class="adm-detail-body">
    <div class="form-grid mt-3" style="margin-bottom:20px">
      <div><span class="text-muted text-sm">DOB</span><div><?= htmlspecialchars($adm['date_of_birth']) ?></div></div>
      <div><span class="text-muted text-sm">Gender</span><div><?= htmlspecialchars($adm['gender']) ?></div></div>
      <div><span class="text-muted text-sm">Phone</span><div><?= htmlspecialchars($adm['contact_info']['phone'] ?: '—') ?></div></div>
      <div><span class="text-muted text-sm">Email</span><div><?= htmlspecialchars($adm['contact_info']['email'] ?: '—') ?></div></div>
      <div><span class="text-muted text-sm">Insurance</span><div><?= htmlspecialchars($adm['insurance_provider'] ?: '—') ?></div></div>
      <div><span class="text-muted text-sm">Policy #</span><div class="mono"><?= htmlspecialchars($adm['policy_number'] ?: '—') ?></div></div>
      <div><span class="text-muted text-sm">Physician</span><div><?= htmlspecialchars($adm['attending_physician_id'] ?: '—') ?></div></div>
      <div><span class="text-muted text-sm">Room</span><div class="mono"><?= htmlspecialchars($adm['assigned_room']['room_id']) ?></div></div>
      <div><span class="text-muted text-sm">Admitted</span><div><?= htmlspecialchars($adm['admission_datetime']) ?></div></div>
      <?php if ($discharged): ?>
      <div><span class="text-muted text-sm">Discharged</span><div><?= htmlspecialchars($adm['discharge_datetime']) ?></div></div>
      <?php endif; ?>
      <div><span class="text-muted text-sm">Stay (hrs)</span><div class="text-teal"><?= $bill['hours'] ?> h</div></div>
    </div>
    <div><span class="text-muted text-sm">Initial Diagnosis</span><p style="margin-top:4px"><?= nl2br(htmlspecialchars($adm['initial_diagnosis'])) ?></p></div>
    <?php if ($discharged && !empty($adm['final_diagnosis'])): ?>
    <hr class="divider">
    <div><span class="text-muted text-sm">Final Diagnosis</span><p style="margin-top:4px"><?= nl2br(htmlspecialchars($adm['final_diagnosis'])) ?></p></div>
    <div class="mt-3"><span class="text-muted text-sm">Follow-up</span><p style="margin-top:4px"><?= nl2br(htmlspecialchars($adm['follow_up_instructions'])) ?></p></div>
    <?php endif; ?>

    <?php if ($adm['fallback_note'] ?? ''): ?>
    <div class="msg" style="background:rgba(255,170,0,.1);border-color:var(--warn);color:#ffd580;margin-top:16px">
      ⚠ <?= htmlspecialchars($adm['fallback_note']) ?>
    </div>
    <?php endif; ?>

    <!-- INLINE BILL PREVIEW -->
    <?php if ($discharged): ?>
    <hr class="divider">
    <div class="bill-wrap mt-3">
      <div class="bill-header">
        <h2>🧾 Final Invoice — <?= htmlspecialchars($adm_id) ?></h2>
        <p>Generated: <?= date('d M Y H:i') ?> &nbsp;|&nbsp; Patient: <?= htmlspecialchars($adm['patient_name']) ?></p>
      </div>
      <div class="bill-body">
        <div class="bill-section-title">Room Charges</div>
        <div class="bill-line">
          <span class="lbl"><?= $adm['assigned_room']['room_type'] ?> Room (<?= $adm['assigned_room']['room_id'] ?>) × <?= $bill['hours'] ?> hrs @ <?= fmt_money($adm['assigned_room']['hourly_rate']) ?>/hr</span>
          <span class="val"><?= fmt_money($bill['room_charge']) ?></span>
        </div>

        <?php if ($adm['services']): ?>
        <div class="bill-section-title">Services</div>
        <?php foreach ($adm['services'] as $s): ?>
        <div class="bill-line">
          <span class="lbl"><?= htmlspecialchars($s['service_name']) ?> (×<?= $s['quantity'] ?> @ <?= fmt_money($s['unit_price']) ?>)</span>
          <span class="val"><?= fmt_money($s['total_price']) ?></span>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>

        <?php if ($adm['medications']): ?>
        <div class="bill-section-title">Medications</div>
        <?php foreach ($adm['medications'] as $m): ?>
        <div class="bill-line">
          <span class="lbl"><?= htmlspecialchars($m['name']) ?> <?= htmlspecialchars($m['dosage']) ?></span>
          <span class="val"><?= fmt_money($m['price']) ?></span>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>

        <?php if ($adm['other_fees']): ?>
        <div class="bill-section-title">Other Fees</div>
        <?php foreach ($adm['other_fees'] as $f): ?>
        <div class="bill-line">
          <span class="lbl"><?= htmlspecialchars($f['item']) ?></span>
          <span class="val"><?= fmt_money($f['amount']) ?></span>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>

        <hr class="divider">
        <div class="bill-line"><span class="lbl">Subtotal</span><span class="val"><?= fmt_money($bill['subtotal']) ?></span></div>
        <?php if ($bill['discount_amt']>0): ?>
        <div class="bill-line"><span class="lbl text-gold">Discount (<?= $adm['billing_adjustments']['discount_pct'] ?>%)</span><span class="val text-gold">-<?= fmt_money($bill['discount_amt']) ?></span></div>
        <?php endif; ?>
        <?php if ($bill['ins_amt']>0): ?>
        <div class="bill-line"><span class="lbl text-sky">Insurance Coverage (<?= $adm['billing_adjustments']['insurance_coverage_pct'] ?>%)</span><span class="val text-sky">-<?= fmt_money($bill['ins_amt']) ?></span></div>
        <?php endif; ?>
        <div class="bill-total mt-4">
          <span class="lbl">Amount Due by Patient</span>
          <span class="val"><?= fmt_money($bill['due']) ?></span>
        </div>
      </div>
    </div>
    <?php else: ?>
    <!-- Running estimate for active patients -->
    <hr class="divider">
    <div style="display:flex;align-items:center;justify-content:space-between;background:rgba(33,118,255,.08);border:1px solid rgba(33,118,255,.18);border-radius:var(--radius-sm);padding:14px 18px;margin-top:12px">
      <span class="text-muted text-sm">⏱ Running Estimate (<?= $bill['hours'] ?> hrs)</span>
      <span class="text-teal" style="font-family:'Playfair Display',serif;font-size:1.2rem;font-weight:700"><?= fmt_money($bill['room_charge'] + $bill['svc_total'] + $bill['med_total'] + $bill['fee_total']) ?></span>
    </div>
    <?php endif; ?>
  </div>
</details>
<?php endforeach; ?>
<?php endif; ?>
</div>

<!-- ════════════════════════════════════════════════════════
     TAB 3 — ADD CHARGES
════════════════════════════════════════════════════════ -->
<div id="tab-charges" class="tab-panel">
<div class="section-title" style="margin-bottom:20px">Add Charges to Admission</div>

<?php $active_adms = array_filter($_SESSION['admissions'], fn($a)=>empty($a['discharge_datetime']));
if (empty($active_adms)): ?>
<div class="card" style="text-align:center;padding:48px">
  <div style="font-size:3rem;margin-bottom:12px">💊</div>
  <div class="text-muted">No active admissions to charge.</div>
</div>
<?php else: ?>

<!-- SERVICE CHARGE -->
<div class="card">
  <div class="card-title"><span class="icon icon-blue">🔬</span> Add Service / Procedure</div>
  <form method="POST">
    <input type="hidden" name="action"      value="add_charge">
    <input type="hidden" name="charge_type" value="service">
    <div class="form-grid cols-3">
      <div class="form-group">
        <label>Admission</label>
        <select name="adm_id" required>
          <option value="">Select…</option>
          <?php foreach ($active_adms as $id=>$a): ?>
          <option value="<?= $id ?>"><?= $id ?> – <?= htmlspecialchars($a['patient_name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label>Service Name</label>
        <input name="service_name" required placeholder="e.g. X-Ray">
      </div>
      <div class="form-group">
        <label>Description</label>
        <input name="svc_description" placeholder="Brief description">
      </div>
      <div class="form-group">
        <label>Quantity</label>
        <input name="quantity" type="number" min="1" value="1" required>
      </div>
      <div class="form-group">
        <label>Unit Price (USD)</label>
        <input name="unit_price" type="number" step="0.01" min="0" required placeholder="0.00">
      </div>
    </div>
    <button class="btn btn-primary mt-3">➕ Add Service</button>
  </form>
</div>

<!-- MEDICATION -->
<div class="card">
  <div class="card-title"><span class="icon icon-teal">💊</span> Add Medication</div>
  <form method="POST">
    <input type="hidden" name="action"      value="add_charge">
    <input type="hidden" name="charge_type" value="medication">
    <div class="form-grid cols-3">
      <div class="form-group">
        <label>Admission</label>
        <select name="adm_id" required>
          <option value="">Select…</option>
          <?php foreach ($active_adms as $id=>$a): ?>
          <option value="<?= $id ?>"><?= $id ?> – <?= htmlspecialchars($a['patient_name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label>Medication Name</label>
        <input name="med_name" required placeholder="e.g. Amoxicillin">
      </div>
      <div class="form-group">
        <label>Dosage</label>
        <input name="dosage" placeholder="e.g. 500mg oral">
      </div>
      <div class="form-group">
        <label>Administration Time</label>
        <input name="admin_time" type="datetime-local" value="<?= date('Y-m-d\TH:i') ?>">
      </div>
      <div class="form-group">
        <label>Price (USD)</label>
        <input name="med_price" type="number" step="0.01" min="0" required placeholder="0.00">
      </div>
    </div>
    <button class="btn btn-teal mt-3">➕ Add Medication</button>
  </form>
</div>

<!-- OTHER FEE -->
<div class="card">
  <div class="card-title"><span class="icon icon-gold">💰</span> Add Miscellaneous Fee</div>
  <form method="POST">
    <input type="hidden" name="action"      value="add_charge">
    <input type="hidden" name="charge_type" value="fee">
    <div class="form-grid cols-3">
      <div class="form-group">
        <label>Admission</label>
        <select name="adm_id" required>
          <option value="">Select…</option>
          <?php foreach ($active_adms as $id=>$a): ?>
          <option value="<?= $id ?>"><?= $id ?> – <?= htmlspecialchars($a['patient_name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label>Item Description</label>
        <input name="fee_item" required placeholder="e.g. Equipment Rental">
      </div>
      <div class="form-group">
        <label>Amount (USD)</label>
        <input name="fee_amount" type="number" step="0.01" min="0" required placeholder="0.00">
      </div>
    </div>
    <button class="btn btn-ghost mt-3">➕ Add Fee</button>
  </form>
</div>

<?php endif; ?>
</div>

<!-- ════════════════════════════════════════════════════════
     TAB 4 — DISCHARGE
════════════════════════════════════════════════════════ -->
<div id="tab-discharge" class="tab-panel">
<div class="section-title" style="margin-bottom:20px">Discharge Patient</div>

<?php if (empty($active_adms ?? []) && empty(array_filter($_SESSION['admissions'], fn($a)=>empty($a['discharge_datetime'])))): ?>
<div class="card" style="text-align:center;padding:48px">
  <div style="font-size:3rem;margin-bottom:12px">🚪</div>
  <div class="text-muted">No active admissions to discharge.</div>
</div>
<?php else:
  $active_for_discharge = array_filter($_SESSION['admissions'], fn($a)=>empty($a['discharge_datetime']));
?>
<form method="POST">
<input type="hidden" name="action" value="discharge">
<div class="card">
  <div class="card-title"><span class="icon icon-red">🚪</span> Discharge Details</div>
  <div class="form-grid">
    <div class="form-group">
      <label>Select Admission *</label>
      <select name="adm_id" required>
        <option value="">Select…</option>
        <?php foreach ($active_for_discharge as $id=>$a): ?>
        <option value="<?= $id ?>"><?= $id ?> – <?= htmlspecialchars($a['patient_name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-group">
      <label>Discharge Date &amp; Time *</label>
      <input name="discharge_datetime" type="datetime-local" required value="<?= date('Y-m-d\TH:i') ?>">
    </div>
    <div class="form-group full">
      <label>Final Diagnosis</label>
      <textarea name="final_diagnosis" placeholder="Summary of diagnosis upon discharge…"></textarea>
    </div>
    <div class="form-group full">
      <label>Treatments Given</label>
      <textarea name="treatments_given" placeholder="List of procedures/treatments performed…"></textarea>
    </div>
    <div class="form-group full">
      <label>Medications Administered</label>
      <textarea name="medications_administered" placeholder="Medications with dosage and schedule…"></textarea>
    </div>
    <div class="form-group full">
      <label>Follow-up Instructions</label>
      <textarea name="follow_up_instructions" placeholder="Post-discharge care, next appointment, restrictions…"></textarea>
    </div>
  </div>
</div>

<div class="card">
  <div class="card-title"><span class="icon icon-gold">⚖️</span> Billing Adjustments</div>
  <div class="form-grid cols-2">
    <div class="form-group">
      <label>Discount (%)</label>
      <input name="discount_pct" type="number" min="0" max="100" step="0.01" value="0" placeholder="0">
    </div>
    <div class="form-group">
      <label>Insurance Coverage (%)</label>
      <input name="insurance_coverage_pct" type="number" min="0" max="100" step="0.01" value="0" placeholder="0">
    </div>
  </div>
</div>

<button class="btn btn-danger">🚪 Process Discharge &amp; Generate Bill</button>
</form>
<?php endif; ?>
</div>

<!-- ════════════════════════════════════════════════════════
     TAB 5 — ROOMS
════════════════════════════════════════════════════════ -->
<div id="tab-rooms" class="tab-panel">
<div class="section-title" style="margin-bottom:20px">Room Availability</div>
<div class="room-grid">
<?php foreach ($ROOMS as $r):
  $isOcc = $r['status']==='occupied';
  $rtBadge = match(true){
    str_contains($r['room_type'],'ICU')     =>'badge-icu',
    str_contains($r['room_type'],'Private') =>'badge-private',
    str_contains($r['room_type'],'Shared')  =>'badge-shared',
    str_contains($r['room_type'],'Recovery')=>'badge-recovery',
    default                                  =>'badge-general'
  };
?>
<div class="room-card <?= $isOcc?'occupied':'available' ?>">
  <div class="room-id"><?= $r['room_id'] ?></div>
  <div class="room-type-txt"><?= $r['room_type'] ?></div>
  <div class="room-rate"><?= fmt_money($r['hourly_rate']) ?>/hr</div>
  <div class="room-status">
    <span class="badge <?= $isOcc ? 'badge-discharged' : 'badge-active' ?>">
      <?= $isOcc ? '🔴 Occupied' : '🟢 Available' ?>
    </span>
  </div>
  <div style="font-size:.72rem;color:var(--text-muted);margin-top:6px">Cap: <?= $r['capacity'] ?></div>
</div>
<?php endforeach; ?>
</div>

<div class="card mt-4">
  <div class="card-title"><span class="icon icon-gold">💲</span> Pricing Reference</div>
  <div class="table-wrap">
    <table>
      <thead><tr><th>Room Type</th><th>Hourly Rate</th><th>Daily Est.</th><th>Weekly Est.</th></tr></thead>
      <tbody>
        <?php
        $pricing = [
            'General Ward'=>8.00,'Shared'=>12.00,'Private'=>25.00,
            'Recovery'=>35.00,'ICU'=>85.00
        ];
        foreach($pricing as $type=>$rate):?>
        <tr>
          <td><?= $type ?></td>
          <td class="mono"><?= fmt_money($rate) ?></td>
          <td class="mono"><?= fmt_money($rate*24) ?></td>
          <td class="mono"><?= fmt_money($rate*24*7) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
</div>

<!-- ════════════════════════════════════════════════════════
     TAB 6 — AUDIT LOG
════════════════════════════════════════════════════════ -->
<div id="tab-audit" class="tab-panel">
<div class="section-title" style="margin-bottom:20px">System Audit Trail</div>
<?php if (empty($_SESSION['audit_log'])): ?>
<div class="card" style="text-align:center;padding:48px">
  <div style="font-size:3rem;margin-bottom:12px">📜</div>
  <div class="text-muted">No audit entries yet.</div>
</div>
<?php else: ?>
<div class="card">
  <?php foreach(array_reverse($_SESSION['audit_log']) as $entry): ?>
  <div class="audit-entry">
    <span class="audit-ts"><?= substr($entry['ts'],0,19) ?></span>
    <span class="audit-action"><?= htmlspecialchars($entry['action']) ?></span>
    <span class="audit-detail"><?= htmlspecialchars($entry['detail']) ?></span>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>
</div>

</div><!-- /.wrap -->

<script>
function switchTab(name) {
  document.querySelectorAll('.tab-btn').forEach((b,i) => b.classList.remove('active'));
  document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
  const panels = ['admit','admissions','charges','discharge','rooms','audit'];
  const idx = panels.indexOf(name);
  document.querySelectorAll('.tab-btn')[idx].classList.add('active');
  document.getElementById('tab-'+name).classList.add('active');
  window.scrollTo({top:0,behavior:'smooth'});
}

// Auto-switch to admissions after submit if message present
<?php if ($message && in_array($action,['admit'])): ?>
switchTab('admissions');
<?php elseif ($message && in_array($action,['discharge'])): ?>
switchTab('admissions');
<?php endif; ?>
</script>
</body>
</html>