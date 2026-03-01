<?php
include '../../SQL/config.php';

$addError = '';

/* ── Fetch companies ── */
$companyRows = [];
$res = $conn->query("SELECT * FROM insurance_company ORDER BY company_name ASC");
while ($r = $res->fetch_assoc()) $companyRows[] = $r;

/* ── Fetch promos ── */
$promoRows = [];
$res = $conn->query("
    SELECT ip.*, ic.company_name
    FROM insurance_promo ip
    JOIN insurance_company ic ON ip.insurance_company_id = ic.insurance_company_id
    ORDER BY ic.company_name, ip.promo_name ASC
");
while ($r = $res->fetch_assoc()) $promoRows[] = $r;

/* ── Handle UPDATE STATUS ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_status') {
    $id     = intval($_POST['insurance_id'] ?? 0);
    $status = trim($_POST['new_status'] ?? '');
    $allowed = ['Pending', 'Active', 'Inactive'];
    if ($id > 0 && in_array($status, $allowed)) {
        $stmt = $conn->prepare("UPDATE patient_insurance SET status = ? WHERE patient_insurance_id = ?");
        $stmt->bind_param('si', $status, $id);
        $stmt->execute();
        $stmt->close();
        header('Location: ' . $_SERVER['PHP_SELF'] . '?updated=1');
        exit;
    }
}

/* ── Handle ADD ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_insurance') {
    $full_name         = trim($_POST['full_name'] ?? '');
    $relationship      = trim($_POST['relationship_to_insured'] ?? '');
    $insurance_number  = trim($_POST['insurance_number'] ?? '');
    $insurance_company = trim($_POST['insurance_company'] ?? '');
    $promo_name        = trim($_POST['promo_name'] ?? '');
    $discount_value    = trim($_POST['discount_value'] ?? '');
    $discount_type     = trim($_POST['discount_type'] ?? 'Percentage');
    $status            = trim($_POST['status'] ?? 'Pending');

    if (!$full_name || !$relationship || !$insurance_number || !$insurance_company || $discount_value === '' || !$promo_name) {
        $addError = 'Please fill in all required fields.';
    } else {
        $stmt = $conn->prepare("INSERT INTO patient_insurance
            (full_name, relationship_to_insured, insurance_number, insurance_company, promo_name, discount_value, discount_type, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param('sssssdss',
            $full_name, $relationship, $insurance_number,
            $insurance_company, $promo_name, $discount_value,
            $discount_type, $status
        );
        if ($stmt->execute()) {
            header('Location: ' . $_SERVER['PHP_SELF'] . '?added=1');
            exit;
        } else {
            $addError = 'Database error: ' . htmlspecialchars($conn->error);
        }
        $stmt->close();
    }
}

/* ── Load records ── */
$list = $conn->query("SELECT * FROM patient_insurance ORDER BY created_at DESC");
$rows = [];
while ($row = $list->fetch_assoc()) $rows[] = $row;
$total = count($rows);

/* ── Helpers ── */
function cardGradient($company) {
    return match ($company) {
        'PhilHealth'  => ['#0d2b6e', '#1a56c4', '#4a90d9'],
        'Maxicare'    => ['#0a6b5e', '#0f9b8e', '#38ef7d'],
        'Medicard'    => ['#4a0080', '#8e2de2', '#c470f0'],
        'Intellicare' => ['#b35a00', '#f7971e', '#ffd200'],
        default       => ['#1a1a2e', '#16213e', '#0f3460'],
    };
}
function cardAccent($company) {
    return match ($company) {
        'PhilHealth'  => 'rgba(74,144,217,.4)',
        'Maxicare'    => 'rgba(56,239,125,.35)',
        'Medicard'    => 'rgba(196,112,240,.4)',
        'Intellicare' => 'rgba(255,210,0,.4)',
        default       => 'rgba(255,255,255,.15)',
    };
}
function companyIcon($company) {
    return match ($company) {
        'PhilHealth'  => 'bi-hospital',
        'Maxicare'    => 'bi-heart-pulse',
        'Medicard'    => 'bi-shield-heart',
        'Intellicare' => 'bi-shield-plus',
        default       => 'bi-shield-check',
    };
}
function badgeClass($company) {
    return match ($company) {
        'PhilHealth'  => 'badge-philhealth',
        'Maxicare'    => 'badge-maxicare',
        'Medicard'    => 'badge-medicard',
        'Intellicare' => 'badge-intellicare',
        default       => 'badge-default',
    };
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<title>Patient Insurance — HMS</title>
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@400;600;700&family=Figtree:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<link rel="stylesheet" href="assets/css/billing_sidebar.css">
<style>
:root {
  --sidebar-w:    250px;
  --sidebar-w-sm: 200px;
  --navy:    #0c1e3c;
  --ink:     #1c2b3a;
  --ink-2:   #3d5068;
  --ink-3:   #7b92aa;
  --ink-4:   #b0c2d4;
  --border:  #dde6f0;
  --border-2:#c8d8e8;
  --surface: #eef2f7;
  --surface-2:#f7f9fc;
  --paper:   #ffffff;
  --blue:    #1a56db;
  --blue-2:  #3b82f6;
  --teal:    #0d9488;
  --green:   #059669;
  --amber:   #d97706;
  --red:     #dc2626;
  --radius:    12px;
  --radius-lg: 16px;
  --radius-xl: 22px;
  --shadow-xs: 0 1px 3px rgba(12,30,60,.06);
  --shadow-sm: 0 2px 8px rgba(12,30,60,.08);
  --shadow:    0 4px 20px rgba(12,30,60,.1);
  --shadow-lg: 0 12px 48px rgba(12,30,60,.16);
  --shadow-xl: 0 24px 80px rgba(12,30,60,.22);
  --ff-head: 'Cormorant Garamond', Georgia, serif;
  --ff-body: 'Figtree', system-ui, sans-serif;
  --ff-mono: 'JetBrains Mono', 'Courier New', monospace;
  --tr: .25s ease;
}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
body{font-family:var(--ff-body);background:var(--surface);color:var(--ink);min-height:100vh;}

.cw{margin-left:var(--sidebar-w);padding:32px 40px 72px;transition:margin-left var(--tr);display:flex;flex-direction:column;align-items:center;}
.cw.sidebar-collapsed{margin-left:0;}
.cw-inner{width:100%;max-width:1200px;}

/* MASTHEAD */
.masthead{background:var(--paper);border:1px solid var(--border);border-radius:var(--radius-xl);box-shadow:var(--shadow);padding:28px 36px;margin-bottom:22px;position:relative;overflow:hidden;}
.masthead::before{content:'';position:absolute;top:0;left:0;right:0;height:4px;background:linear-gradient(90deg,var(--navy) 0%,var(--blue) 50%,var(--teal) 100%);}
.masthead::after{content:'';position:absolute;top:-80px;right:-60px;width:280px;height:280px;border-radius:50%;background:radial-gradient(circle,rgba(26,86,219,.06) 0%,transparent 70%);pointer-events:none;}
.masthead-inner{display:flex;align-items:center;justify-content:space-between;gap:20px;flex-wrap:wrap;}
.masthead-left{display:flex;align-items:center;gap:18px;}
.masthead-icon{width:60px;height:60px;background:linear-gradient(135deg,var(--navy),var(--blue));border-radius:var(--radius-lg);display:flex;align-items:center;justify-content:center;color:#fff;font-size:1.6rem;box-shadow:0 6px 20px rgba(26,86,219,.35);flex-shrink:0;}
.masthead-title{font-family:var(--ff-head);font-size:2rem;font-weight:700;color:var(--navy);letter-spacing:-.01em;line-height:1;}
.masthead-sub{font-size:.78rem;color:var(--ink-3);margin-top:5px;text-transform:uppercase;letter-spacing:.08em;font-weight:500;}
.masthead-right{display:flex;align-items:center;gap:16px;flex-wrap:wrap;}
.masthead-stats{display:flex;gap:20px;flex-wrap:wrap;align-items:center;}
.stat-chip{display:flex;flex-direction:column;align-items:center;background:var(--surface-2);border:1px solid var(--border);border-radius:var(--radius);padding:10px 20px;min-width:90px;}
.stat-chip-val{font-family:var(--ff-head);font-size:1.6rem;font-weight:700;color:var(--navy);line-height:1;}
.stat-chip-lbl{font-size:.65rem;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:var(--ink-3);margin-top:3px;}
.btn-add-insurance{display:inline-flex;align-items:center;gap:8px;padding:12px 22px;background:linear-gradient(135deg,var(--navy),var(--blue));color:#fff;border:none;border-radius:var(--radius-lg);font-family:var(--ff-body);font-size:.86rem;font-weight:700;cursor:pointer;text-decoration:none;box-shadow:0 6px 20px rgba(26,86,219,.35);transition:all .2s;white-space:nowrap;}
.btn-add-insurance:hover{background:linear-gradient(135deg,var(--blue),var(--teal));transform:translateY(-2px);box-shadow:0 10px 28px rgba(26,86,219,.45);color:#fff;}

/* TOOLBAR */
.toolbar{display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin-bottom:16px;}
.search-wrap{position:relative;flex:1 1 240px;max-width:340px;}
.search-wrap .si{position:absolute;left:13px;top:50%;transform:translateY(-50%);color:var(--ink-4);font-size:.9rem;pointer-events:none;}
.search-input{width:100%;padding:10px 14px 10px 36px;border:1.5px solid var(--border-2);border-radius:var(--radius);font-family:var(--ff-body);font-size:.86rem;color:var(--ink);background:var(--paper);outline:none;transition:border-color var(--tr),box-shadow var(--tr);box-shadow:var(--shadow-xs);}
.search-input:focus{border-color:var(--blue-2);box-shadow:0 0 0 3px rgba(59,130,246,.12);}
.filter-tabs{display:flex;gap:4px;flex-wrap:wrap;}
.ftab{padding:8px 14px;border-radius:var(--radius);font-family:var(--ff-body);font-size:.76rem;font-weight:600;border:1.5px solid var(--border-2);background:var(--paper);color:var(--ink-2);cursor:pointer;transition:all .15s;display:inline-flex;align-items:center;gap:5px;white-space:nowrap;}
.ftab:hover{background:var(--surface-2);border-color:var(--ink-3);}
.ftab.active{background:var(--navy);color:#fff;border-color:var(--navy);box-shadow:var(--shadow-sm);}

/* TABLE */
.table-wrap{background:var(--paper);border:1px solid var(--border);border-radius:var(--radius-xl);box-shadow:var(--shadow-sm);overflow:hidden;}
.ins-table{width:100%;border-collapse:collapse;}
.ins-table thead tr{background:var(--navy);}
.ins-table thead th{font-family:var(--ff-body);font-size:.68rem;font-weight:700;text-transform:uppercase;letter-spacing:.1em;color:rgba(255,255,255,.65);padding:14px 18px;text-align:left;white-space:nowrap;border:none;}
.ins-table tbody tr{border-bottom:1px solid var(--border);transition:background .15s;animation:rowFade .35s ease both;}
.ins-table tbody tr:last-child{border-bottom:none;}
.ins-table tbody tr:hover{background:var(--surface-2);}
.ins-table tbody td{padding:13px 18px;font-size:.85rem;color:var(--ink);vertical-align:middle;}

.company-badge{display:inline-flex;align-items:center;gap:6px;padding:4px 11px;border-radius:999px;font-size:.72rem;font-weight:700;white-space:nowrap;}
.badge-philhealth {background:#dbeafe;color:#1d4ed8;}
.badge-maxicare   {background:#ccfbf1;color:#0f766e;}
.badge-medicard   {background:#ede9fe;color:#6d28d9;}
.badge-intellicare{background:#fef3c7;color:#b45309;}
.badge-default    {background:var(--surface);color:var(--ink-2);}

.status-badge{display:inline-flex;align-items:center;gap:5px;padding:4px 10px;border-radius:999px;font-size:.72rem;font-weight:700;}
.status-badge.active  {background:#d1fae5;color:#065f46;}
.status-badge.inactive{background:#fee2e2;color:#991b1b;}
.status-badge.pending {background:#fef3c7;color:#92400e;}
.status-dot{width:6px;height:6px;border-radius:50%;flex-shrink:0;}
.status-badge.active   .status-dot{background:#10b981;box-shadow:0 0 5px #10b981;}
.status-badge.inactive .status-dot{background:#f87171;}
.status-badge.pending  .status-dot{background:#fbbf24;}

.ins-number{font-family:var(--ff-mono);font-size:.78rem;color:var(--ink-2);letter-spacing:1px;}
.patient-name{font-weight:700;color:var(--navy);}
.patient-rel{font-size:.74rem;color:var(--ink-3);margin-top:2px;}
.discount-val{font-weight:700;color:var(--green);}
.discount-type-tag{font-size:.68rem;color:var(--ink-4);margin-top:1px;}

/* Action buttons cell */
.action-cell{display:flex;gap:6px;align-items:center;justify-content:center;flex-wrap:wrap;}
.btn-view{display:inline-flex;align-items:center;gap:5px;padding:6px 13px;background:linear-gradient(135deg,var(--navy),var(--blue));color:#fff;border:none;border-radius:var(--radius);font-family:var(--ff-body);font-size:.75rem;font-weight:700;cursor:pointer;transition:all .15s;box-shadow:0 3px 10px rgba(26,86,219,.22);white-space:nowrap;text-decoration:none;}
.btn-view:hover{background:linear-gradient(135deg,var(--blue),var(--teal));transform:translateY(-1px);color:#fff;}
.btn-status{display:inline-flex;align-items:center;gap:5px;padding:6px 13px;background:var(--paper);color:var(--ink-2);border:1.5px solid var(--border-2);border-radius:var(--radius);font-family:var(--ff-body);font-size:.75rem;font-weight:700;cursor:pointer;transition:all .15s;white-space:nowrap;}
.btn-status:hover{background:var(--surface-2);border-color:var(--ink-3);color:var(--ink);}
.btn-status i{font-size:.8rem;}

.empty-row td{text-align:center;padding:72px 20px;color:var(--ink-3);}
.empty-icon {font-size:2.4rem;display:block;margin-bottom:12px;opacity:.25;}
.empty-title{font-family:var(--ff-head);font-size:1.3rem;color:var(--ink-2);margin-bottom:4px;}
.empty-sub  {font-size:.83rem;}
.row-count{padding:12px 20px;background:var(--surface-2);border-top:1px solid var(--border);font-size:.76rem;color:var(--ink-3);text-align:right;font-weight:500;}

/* TOASTS */
.toast-wrap{position:fixed;top:24px;right:24px;z-index:9999;display:flex;flex-direction:column;gap:10px;}
.toast-pop{background:#fff;border-radius:var(--radius-lg);padding:14px 20px;display:flex;align-items:center;gap:12px;box-shadow:var(--shadow-lg);animation:slideInRight .35s ease,fadeOut .4s ease 3.5s forwards;min-width:280px;}
.toast-pop.green{border:1.5px solid #bbf7d0;}
.toast-pop.blue {border:1.5px solid #bfdbfe;}
.toast-icon{width:36px;height:36px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:1.1rem;flex-shrink:0;}
.toast-pop.green .toast-icon{background:#d1fae5;color:var(--green);}
.toast-pop.blue  .toast-icon{background:#eff6ff;color:var(--blue);}
.toast-text strong{display:block;font-size:.88rem;color:var(--ink);}
.toast-text span{font-size:.78rem;color:var(--ink-3);}

/* MODAL BASE */
.modal-content{border-radius:var(--radius-xl);border:none;box-shadow:var(--shadow-xl);overflow:hidden;}
.modal-header{background:var(--navy);color:#fff;padding:18px 24px;border-bottom:none;display:flex;align-items:center;gap:10px;}
.modal-header-icon{width:36px;height:36px;border-radius:8px;background:rgba(255,255,255,.15);display:flex;align-items:center;justify-content:center;font-size:1rem;}
.modal-title{font-family:var(--ff-head);font-size:1.15rem;font-weight:700;color:#fff;flex:1;}
.modal-sub{font-size:.73rem;color:rgba(255,255,255,.55);margin-top:1px;}
.modal-header .btn-close{filter:invert(1) brightness(1.5);}
.modal-body{padding:28px 24px 24px;background:#f4f7fb;}

/* ── STATUS UPDATE MODAL ── */
.status-modal .modal-header{background:linear-gradient(135deg,#1a1a2e,#3d2a6e);}
.status-modal .modal-body{background:var(--paper);padding:28px;}

.patient-card-mini{background:linear-gradient(135deg,var(--surface-2),#eff6ff);border:1px solid var(--border);border-radius:var(--radius-lg);padding:16px 20px;margin-bottom:24px;display:flex;align-items:center;gap:14px;}
.patient-card-mini-avatar{width:46px;height:46px;border-radius:50%;background:linear-gradient(135deg,var(--navy),var(--blue));display:flex;align-items:center;justify-content:center;color:#fff;font-size:1.1rem;flex-shrink:0;}
.patient-card-mini-name{font-weight:700;font-size:.95rem;color:var(--navy);margin-bottom:2px;}
.patient-card-mini-meta{font-size:.76rem;color:var(--ink-3);}

.status-section-label{font-size:.7rem;font-weight:800;text-transform:uppercase;letter-spacing:.1em;color:var(--ink-4);margin-bottom:14px;display:flex;align-items:center;gap:6px;}
.status-section-label i{color:var(--blue-2);}

.status-cards{display:grid;grid-template-columns:repeat(3,1fr);gap:10px;margin-bottom:24px;}
.status-card{position:relative;border:2px solid var(--border-2);border-radius:var(--radius-lg);padding:16px 12px;cursor:pointer;transition:all .2s;background:var(--surface-2);text-align:center;}
.status-card input[type="radio"]{position:absolute;opacity:0;width:0;height:0;}
.status-card-icon{width:42px;height:42px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:1.1rem;margin:0 auto 10px;transition:all .2s;}
.status-card-label{font-size:.8rem;font-weight:700;display:block;margin-bottom:4px;transition:color .2s;}
.status-card-desc{font-size:.68rem;color:var(--ink-4);line-height:1.3;}

/* Pending */
.status-card[data-val="Pending"]:hover,
.status-card[data-val="Pending"].selected{border-color:var(--amber);background:#fefce8;}
.status-card[data-val="Pending"]:hover .status-card-icon,
.status-card[data-val="Pending"].selected .status-card-icon{background:#fef3c7;color:var(--amber);}
.status-card[data-val="Pending"]:hover .status-card-label,
.status-card[data-val="Pending"].selected .status-card-label{color:var(--amber);}
.status-card[data-val="Pending"].selected{box-shadow:0 0 0 3px rgba(217,119,6,.2);}

/* Active */
.status-card[data-val="Active"]:hover,
.status-card[data-val="Active"].selected{border-color:var(--green);background:#f0fdf4;}
.status-card[data-val="Active"]:hover .status-card-icon,
.status-card[data-val="Active"].selected .status-card-icon{background:#d1fae5;color:var(--green);}
.status-card[data-val="Active"]:hover .status-card-label,
.status-card[data-val="Active"].selected .status-card-label{color:var(--green);}
.status-card[data-val="Active"].selected{box-shadow:0 0 0 3px rgba(5,150,105,.2);}

/* Inactive */
.status-card[data-val="Inactive"]:hover,
.status-card[data-val="Inactive"].selected{border-color:var(--red);background:#fff5f5;}
.status-card[data-val="Inactive"]:hover .status-card-icon,
.status-card[data-val="Inactive"].selected .status-card-icon{background:#fee2e2;color:var(--red);}
.status-card[data-val="Inactive"]:hover .status-card-label,
.status-card[data-val="Inactive"].selected .status-card-label{color:var(--red);}
.status-card[data-val="Inactive"].selected{box-shadow:0 0 0 3px rgba(220,38,38,.2);}

/* Default icon backgrounds */
.status-card[data-val="Pending"]  .status-card-icon{background:#fef3c7;color:var(--amber);}
.status-card[data-val="Active"]   .status-card-icon{background:#d1fae5;color:var(--green);}
.status-card[data-val="Inactive"] .status-card-icon{background:#fee2e2;color:var(--red);}

/* Checkmark badge */
.status-card::after{content:'\F26A';font-family:'bootstrap-icons';position:absolute;top:8px;right:10px;font-size:.9rem;opacity:0;transition:opacity .2s;}
.status-card[data-val="Pending"].selected::after{opacity:1;color:var(--amber);}
.status-card[data-val="Active"].selected::after{opacity:1;color:var(--green);}
.status-card[data-val="Inactive"].selected::after{opacity:1;color:var(--red);}

.status-update-note{background:var(--surface-2);border:1px solid var(--border);border-radius:var(--radius);padding:10px 14px;font-size:.78rem;color:var(--ink-3);display:flex;align-items:center;gap:8px;}
.status-update-note i{color:var(--blue-2);font-size:.9rem;flex-shrink:0;}

.status-modal-footer{padding:16px 28px 20px;border-top:1px solid var(--border);display:flex;gap:10px;justify-content:flex-end;background:var(--surface-2);}

/* ADD MODAL */
.add-modal .modal-body{background:var(--paper);padding:28px 28px 24px;}
.add-modal .modal-header{background:linear-gradient(135deg,var(--navy),var(--blue));}
.form-section{margin-bottom:20px;}
.form-section-title{font-size:.7rem;font-weight:800;text-transform:uppercase;letter-spacing:.1em;color:var(--ink-4);margin-bottom:12px;padding-bottom:8px;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:6px;}
.form-section-title i{color:var(--blue-2);}
.form-row{display:grid;gap:14px;margin-bottom:14px;}
.form-row.cols-2{grid-template-columns:1fr 1fr;}
.form-group{display:flex;flex-direction:column;gap:5px;}
.form-label{font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:var(--ink-2);}
.form-label .req{color:var(--red);margin-left:2px;}
.form-control-custom{padding:10px 13px;border:1.5px solid var(--border-2);border-radius:var(--radius);font-family:var(--ff-body);font-size:.86rem;color:var(--ink);background:var(--surface-2);outline:none;transition:border-color .2s,box-shadow .2s,background .2s;width:100%;appearance:none;-webkit-appearance:none;}
.form-control-custom:focus{border-color:var(--blue-2);box-shadow:0 0 0 3px rgba(59,130,246,.1);background:var(--paper);}
select.form-control-custom{cursor:pointer;background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%237b92aa' stroke-width='2.5'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right 12px center;padding-right:36px;}
select.form-control-custom:disabled{opacity:.5;cursor:not-allowed;background-color:var(--surface);}
.form-control-custom[readonly]{background:linear-gradient(135deg,#f0f7ff,#f7f9fc);border-color:var(--blue-2);color:var(--navy);font-family:var(--ff-mono);font-size:.88rem;letter-spacing:1.5px;font-weight:600;cursor:default;}
.ins-number-wrap{position:relative;}
.ins-number-wrap .form-control-custom{padding-right:48px;}
.btn-regen{position:absolute;right:8px;top:50%;transform:translateY(-50%);width:32px;height:32px;border-radius:8px;border:none;background:var(--blue-2);color:#fff;cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:.85rem;transition:all .2s;}
.btn-regen:hover{background:var(--blue);}
.btn-regen:hover i{transform:rotate(180deg);}
.btn-regen i{transition:transform .35s;}
.ins-num-badge{display:inline-flex;align-items:center;gap:5px;font-size:.67rem;color:var(--blue-2);font-weight:700;margin-top:4px;background:#eff6ff;padding:2px 8px;border-radius:999px;border:1px solid #bfdbfe;width:fit-content;}
.promo-summary{background:linear-gradient(135deg,#f0fdf4,#eff6ff);border:1.5px solid #bbf7d0;border-radius:var(--radius);padding:14px 16px;margin-top:10px;display:none;flex-direction:column;gap:0;}
.promo-summary.show{display:flex;}
.promo-summary-title{font-size:.65rem;font-weight:800;text-transform:uppercase;letter-spacing:.1em;color:var(--green);margin-bottom:10px;display:flex;align-items:center;gap:5px;}
.promo-summary-grid{display:grid;grid-template-columns:1fr 1fr 1fr;gap:10px;}
.promo-summary-item{display:flex;flex-direction:column;gap:2px;}
.promo-summary-label{font-size:.62rem;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:var(--ink-4);}
.promo-summary-val{font-size:.86rem;font-weight:700;color:var(--ink);}
.promo-summary-val.green{color:var(--green);}
.add-status-selector{display:flex;gap:8px;}
.add-status-opt{flex:1;padding:10px 8px;border-radius:var(--radius);border:2px solid var(--border-2);background:var(--surface-2);font-family:var(--ff-body);font-size:.8rem;font-weight:600;cursor:pointer;transition:all .15s;color:var(--ink-2);display:flex;align-items:center;justify-content:center;gap:5px;text-align:center;}
.add-status-opt[data-status="Active"].selected  {border-color:var(--green);background:#d1fae5;color:var(--green);}
.add-status-opt[data-status="Inactive"].selected{border-color:var(--red);background:#fee2e2;color:var(--red);}
.add-status-opt[data-status="Pending"].selected {border-color:var(--amber);background:#fef3c7;color:var(--amber);}
.add-modal-footer{padding:16px 28px 20px;border-top:1px solid var(--border);display:flex;gap:10px;justify-content:flex-end;background:var(--surface-2);}
.btn-cancel{padding:10px 22px;border-radius:var(--radius);border:1.5px solid var(--border-2);background:var(--paper);font-family:var(--ff-body);font-size:.86rem;font-weight:600;color:var(--ink-2);cursor:pointer;transition:all .15s;}
.btn-cancel:hover{background:var(--surface);border-color:var(--ink-3);}
.btn-submit{padding:10px 26px;border-radius:var(--radius);border:none;background:linear-gradient(135deg,var(--navy),var(--blue));color:#fff;font-family:var(--ff-body);font-size:.86rem;font-weight:700;cursor:pointer;transition:all .2s;box-shadow:0 4px 14px rgba(26,86,219,.3);display:inline-flex;align-items:center;gap:7px;}
.btn-submit:hover{background:linear-gradient(135deg,var(--blue),var(--teal));transform:translateY(-1px);}
.btn-submit-status{padding:10px 26px;border-radius:var(--radius);border:none;color:#fff;font-family:var(--ff-body);font-size:.86rem;font-weight:700;cursor:pointer;transition:all .2s;display:inline-flex;align-items:center;gap:7px;background:linear-gradient(135deg,#1a1a2e,#3d2a6e);box-shadow:0 4px 14px rgba(61,42,110,.35);}
.btn-submit-status:hover{transform:translateY(-1px);opacity:.92;}
.error-alert{background:#fee2e2;border:1.5px solid #fecaca;border-radius:var(--radius);padding:12px 16px;color:var(--red);font-size:.84rem;font-weight:600;display:flex;align-items:center;gap:8px;margin-bottom:18px;}

/* CARD VIEWER */
.card-stage{display:flex;justify-content:center;perspective:1000px;margin-bottom:22px;}
.ins-card{width:360px;max-width:100%;height:220px;border-radius:20px;padding:22px 26px;color:#fff;position:relative;overflow:hidden;box-shadow:0 24px 64px rgba(0,0,0,.45),0 4px 12px rgba(0,0,0,.2);transform-style:preserve-3d;transition:transform .5s cubic-bezier(.23,1,.32,1);}
.ins-card:hover{transform:rotateY(6deg) rotateX(-3deg) scale(1.03);}
.ins-card .deco1{position:absolute;top:-70px;right:-50px;width:220px;height:220px;border-radius:50%;background:rgba(255,255,255,.08);pointer-events:none;}
.ins-card .deco2{position:absolute;bottom:-90px;left:-50px;width:240px;height:240px;border-radius:50%;background:rgba(255,255,255,.05);pointer-events:none;}
.ins-card .deco3{position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);width:180px;height:180px;border-radius:50%;background:rgba(255,255,255,.03);pointer-events:none;}
.ic-top{display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;position:relative;z-index:1;}
.ic-company{font-size:.65rem;font-weight:700;letter-spacing:.15em;text-transform:uppercase;opacity:.85;}
.ic-logo{display:flex;gap:4px;}
.ic-logo span{width:22px;height:22px;border-radius:50%;opacity:.6;}
.ic-chip{width:44px;height:34px;background:linear-gradient(135deg,#c9952a,#f5d475,#c9952a);border-radius:6px;margin-bottom:14px;position:relative;z-index:1;box-shadow:0 2px 10px rgba(0,0,0,.3);display:flex;align-items:center;justify-content:center;}
.ic-chip::before{content:'';position:absolute;width:62%;height:55%;border:1.5px solid rgba(0,0,0,.18);border-radius:4px;}
.ic-chip::after{content:'';position:absolute;top:50%;left:0;right:0;height:1px;background:rgba(0,0,0,.12);}
.ic-number{font-family:var(--ff-mono);font-size:1.02rem;letter-spacing:3px;margin-bottom:16px;position:relative;z-index:1;text-shadow:0 1px 6px rgba(0,0,0,.3);}
.ic-bottom{position:relative;z-index:1;display:flex;justify-content:space-between;align-items:flex-end;}
.ic-holder-label{font-size:.55rem;letter-spacing:.1em;text-transform:uppercase;opacity:.55;margin-bottom:3px;}
.ic-holder-name{font-family:var(--ff-head);font-size:1.1rem;font-weight:700;letter-spacing:.03em;line-height:1;}
.ic-promo{text-align:right;}
.ic-promo-label{font-size:.55rem;text-transform:uppercase;letter-spacing:.08em;opacity:.6;margin-bottom:2px;}
.ic-discount{font-family:var(--ff-mono);font-size:.95rem;font-weight:700;background:rgba(255,255,255,.2);backdrop-filter:blur(4px);padding:2px 8px;border-radius:5px;border:1px solid rgba(255,255,255,.25);}
.detail-grid{display:grid;grid-template-columns:1fr 1fr 1fr;gap:10px;margin-top:6px;}
.detail-cell{background:var(--paper);border:1px solid var(--border);border-radius:var(--radius);padding:12px 14px;text-align:center;box-shadow:var(--shadow-xs);}
.detail-cell-label{font-size:.62rem;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:var(--ink-4);margin-bottom:4px;}
.detail-cell-val{font-size:.85rem;font-weight:700;color:var(--ink);}
.detail-cell-val.status-active  {color:var(--green);}
.detail-cell-val.status-inactive{color:var(--red);}
.detail-cell-val.status-pending {color:var(--amber);}

@keyframes rowFade{from{opacity:0;transform:translateY(8px);}to{opacity:1;transform:translateY(0);}}
@keyframes slideInRight{from{opacity:0;transform:translateX(60px);}to{opacity:1;transform:translateX(0);}}
@keyframes fadeOut{to{opacity:0;pointer-events:none;}}

@media(max-width:900px){
  .cw{margin-left:var(--sidebar-w-sm);padding:60px 16px 50px;}
  .cw.sidebar-collapsed{margin-left:0;}
  .masthead{padding:20px;}
  .masthead-title{font-size:1.5rem;}
  .detail-grid,.promo-summary-grid{grid-template-columns:1fr 1fr;}
  .form-row.cols-2{grid-template-columns:1fr;}
  .status-cards{grid-template-columns:1fr;}
  .ins-table thead th:nth-child(5),.ins-table tbody td:nth-child(5){display:none;}
}
@media(max-width:600px){
  .cw{margin-left:0!important;padding:56px 10px 40px;}
  .masthead-stats{display:none;}
  .toolbar{flex-direction:column;align-items:stretch;}
  .search-wrap{max-width:100%;}
  .btn-add-insurance span{display:none;}
  .ins-table thead th:nth-child(3),.ins-table tbody td:nth-child(3),
  .ins-table thead th:nth-child(5),.ins-table tbody td:nth-child(5){display:none;}
  .action-cell{flex-direction:column;}
  .add-status-selector{flex-direction:column;}
  .promo-summary-grid{grid-template-columns:1fr;}
}
@supports(padding:env(safe-area-inset-bottom)){.cw{padding-bottom:calc(72px + env(safe-area-inset-bottom));}}
</style>
</head>
<body>

<?php include 'billing_sidebar.php'; ?>

<!-- Toasts -->
<div class="toast-wrap">
<?php if (isset($_GET['added'])): ?>
<div class="toast-pop green" id="toastAdded">
  <div class="toast-icon"><i class="bi bi-check-lg"></i></div>
  <div class="toast-text">
    <strong>Insurance Added</strong>
    <span>New patient insurance record saved.</span>
  </div>
</div>
<?php endif; ?>
<?php if (isset($_GET['updated'])): ?>
<div class="toast-pop blue" id="toastUpdated">
  <div class="toast-icon"><i class="bi bi-arrow-repeat"></i></div>
  <div class="toast-text">
    <strong>Status Updated</strong>
    <span>Insurance status has been changed successfully.</span>
  </div>
</div>
<?php endif; ?>
</div>

<div class="cw" id="mainCw">
<div class="cw-inner">

  <!-- MASTHEAD -->
  <div class="masthead">
    <div class="masthead-inner">
      <div class="masthead-left">
        <div class="masthead-icon"><i class="bi bi-shield-check"></i></div>
        <div>
          <div class="masthead-title">Patient Insurance</div>
          <div class="masthead-sub">HMS · Insurance Registry · Coverage Management</div>
        </div>
      </div>
      <div class="masthead-right">
        <div class="masthead-stats">
          <div class="stat-chip">
            <div class="stat-chip-val"><?= $total ?></div>
            <div class="stat-chip-lbl">Total Records</div>
          </div>
          <div class="stat-chip">
            <div class="stat-chip-val"><?= count(array_filter($rows, fn($r) => strtolower($r['status']) === 'active')) ?></div>
            <div class="stat-chip-lbl">Active</div>
          </div>
          <div class="stat-chip">
            <div class="stat-chip-val"><?= count(array_filter($rows, fn($r) => strtolower($r['status']) === 'pending')) ?></div>
            <div class="stat-chip-lbl">Pending</div>
          </div>
          <div class="stat-chip">
            <div class="stat-chip-val"><?= count(array_unique(array_column($rows, 'insurance_company'))) ?></div>
            <div class="stat-chip-lbl">Companies</div>
          </div>
        </div>
        <button class="btn-add-insurance" data-bs-toggle="modal" data-bs-target="#addInsuranceModal">
          <i class="bi bi-plus-circle-fill"></i>
          <span>Add Insurance</span>
        </button>
      </div>
    </div>
  </div>

  <!-- TOOLBAR -->
  <div class="toolbar">
    <div class="search-wrap">
      <i class="bi bi-search si"></i>
      <input type="text" class="search-input" id="searchInput" placeholder="Search name, company, insurance number…">
    </div>
    <div class="filter-tabs">
      <button class="ftab active" data-filter="all" onclick="filterRows(this,'all')"><i class="bi bi-list-ul"></i> All</button>
      <?php foreach ($companyRows as $co): ?>
      <button class="ftab"
        data-filter="<?= strtolower(str_replace(' ','', $co['company_name'])) ?>"
        onclick="filterRows(this,'<?= strtolower(str_replace(' ','', $co['company_name'])) ?>')">
        <i class="bi <?= companyIcon($co['company_name']) ?>"></i>
        <?= htmlspecialchars($co['company_name']) ?>
      </button>
      <?php endforeach; ?>
      <button class="ftab" data-filter-status="pending"  onclick="filterByStatus(this,'pending')"><i class="bi bi-clock"></i> Pending</button>
      <button class="ftab" data-filter-status="active"   onclick="filterByStatus(this,'active')"><i class="bi bi-check-circle"></i> Active</button>
      <button class="ftab" data-filter-status="inactive" onclick="filterByStatus(this,'inactive')"><i class="bi bi-x-circle"></i> Inactive</button>
    </div>
  </div>

  <!-- TABLE -->
  <div class="table-wrap">
    <table class="ins-table">
      <thead>
        <tr>
          <th>#</th>
          <th>Patient</th>
          <th>Insurance Number</th>
          <th>Company</th>
          <th>Promo / Plan</th>
          <th>Discount</th>
          <th>Status</th>
          <th style="text-align:center;">Actions</th>
        </tr>
      </thead>
      <tbody id="tableBody">
        <?php if (empty($rows)): ?>
          <tr class="empty-row">
            <td colspan="8">
              <i class="bi bi-shield-x empty-icon"></i>
              <div class="empty-title">No Insurance Records</div>
              <div class="empty-sub">Click "Add Insurance" to register a new record.</div>
            </td>
          </tr>
        <?php else: ?>
          <?php foreach ($rows as $i => $row):
            $co     = $row['insurance_company'];
            $coKey  = strtolower(str_replace(' ','', $co));
            $status = strtolower($row['status'] ?? 'pending');
            $discount = $row['discount_type'] === 'Percentage'
              ? htmlspecialchars($row['discount_value']) . '%'
              : '₱' . number_format($row['discount_value'], 2);
            $modalId       = 'modal_' . intval($row['patient_insurance_id']);
            $statusModalId = 'statusModal_' . intval($row['patient_insurance_id']);
            $icon  = companyIcon($co);
            $badge = badgeClass($co);
          ?>
          <tr class="record-row"
              data-company="<?= $coKey ?>"
              data-status-key="<?= $status ?>"
              data-search="<?= htmlspecialchars(strtolower($row['full_name'].' '.$co.' '.$row['insurance_number'])) ?>"
              style="animation-delay:<?= $i * 0.04 ?>s">
            <td style="color:var(--ink-4);font-size:.8rem;font-weight:600;"><?= $i + 1 ?></td>
            <td>
              <div class="patient-name"><?= htmlspecialchars($row['full_name']) ?></div>
              <div class="patient-rel"><i class="bi bi-people" style="font-size:.7rem;"></i> <?= htmlspecialchars($row['relationship_to_insured']) ?></div>
            </td>
            <td><span class="ins-number"><?= htmlspecialchars($row['insurance_number']) ?></span></td>
            <td>
              <span class="company-badge <?= $badge ?>">
                <i class="bi <?= $icon ?>"></i> <?= htmlspecialchars($co) ?>
              </span>
            </td>
            <td style="color:var(--ink-2);font-size:.84rem;">
              <?= $row['promo_name'] ? htmlspecialchars($row['promo_name']) : '<span style="color:var(--ink-4);">—</span>' ?>
            </td>
            <td>
              <div class="discount-val"><?= $discount ?></div>
              <div class="discount-type-tag"><?= htmlspecialchars($row['discount_type']) ?></div>
            </td>
            <td>
              <span class="status-badge <?= $status ?>">
                <span class="status-dot"></span>
                <?= ucfirst($status) ?>
              </span>
            </td>
            <td>
              <div class="action-cell">
                <button class="btn-view" data-bs-toggle="modal" data-bs-target="#<?= $modalId ?>">
                  <i class="bi bi-credit-card-2-front"></i> View Card
                </button>
                <button class="btn-status" data-bs-toggle="modal" data-bs-target="#<?= $statusModalId ?>">
                  <i class="bi bi-pencil-square"></i> Status
                </button>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
    <?php if (!empty($rows)): ?>
    <div class="row-count" id="rowCount">
      Showing <strong><?= $total ?></strong> record<?= $total !== 1 ? 's' : '' ?>
    </div>
    <?php endif; ?>
  </div>

</div>
</div>

<!-- ═══════════════════════════════
     ADD INSURANCE MODAL
═══════════════════════════════ -->
<div class="modal fade add-modal" id="addInsuranceModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <div class="modal-header-icon"><i class="bi bi-plus-circle"></i></div>
        <div>
          <div class="modal-title">Add Patient Insurance</div>
          <div class="modal-sub">Register a new insurance record for a patient</div>
        </div>
        <button type="button" class="btn-close ms-auto" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST" action="" id="addInsuranceForm" novalidate>
        <input type="hidden" name="action"        value="add_insurance">
        <input type="hidden" name="discount_type" id="hiddenDiscountType" value="<?= htmlspecialchars($_POST['discount_type'] ?? 'Percentage') ?>">
        <input type="hidden" name="status"        id="hiddenStatus"       value="<?= htmlspecialchars($_POST['status'] ?? 'Pending') ?>">
        <div class="modal-body">
          <?php if ($addError): ?>
          <div class="error-alert"><i class="bi bi-exclamation-triangle-fill"></i><?= htmlspecialchars($addError) ?></div>
          <?php endif; ?>

          <!-- Patient Info -->
          <div class="form-section">
            <div class="form-section-title"><i class="bi bi-person-vcard"></i> Patient Information</div>
            <div class="form-row">
              <div class="form-group">
                <label class="form-label">Full Name <span class="req">*</span></label>
                <input type="text" name="full_name" class="form-control-custom" placeholder="e.g. Juan Dela Cruz" value="<?= htmlspecialchars($_POST['full_name'] ?? '') ?>" required>
              </div>
            </div>
            <div class="form-row cols-2">
              <div class="form-group">
                <label class="form-label">Relationship to Insured <span class="req">*</span></label>
                <select name="relationship_to_insured" class="form-control-custom" required>
                  <option value="" disabled <?= empty($_POST['relationship_to_insured']) ? 'selected' : '' ?>>Select relationship…</option>
                  <?php foreach (['Self','Spouse','Child','Parent','Sibling','Dependent','Other'] as $rel): ?>
                  <option value="<?= $rel ?>" <?= ($_POST['relationship_to_insured'] ?? '') === $rel ? 'selected' : '' ?>><?= $rel ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="form-group">
                <label class="form-label">Insurance Number</label>
                <div class="ins-number-wrap">
                  <input type="text" name="insurance_number" id="insuranceNumberInput" class="form-control-custom" readonly placeholder="Select a company to auto-generate…" value="<?= htmlspecialchars($_POST['insurance_number'] ?? '') ?>">
                  <button type="button" class="btn-regen" id="regenBtn" title="Regenerate" style="display:none;" onclick="regenNumber()">
                    <i class="bi bi-arrow-clockwise"></i>
                  </button>
                </div>
                <span class="ins-num-badge" id="insNumBadge" style="display:none;"><i class="bi bi-magic"></i> Auto-generated · click ↺ to refresh</span>
              </div>
            </div>
          </div>

          <!-- Company & Promo -->
          <div class="form-section">
            <div class="form-section-title"><i class="bi bi-building-check"></i> Insurance Company &amp; Plan</div>
            <div class="form-row cols-2">
              <div class="form-group">
                <label class="form-label">Insurance Company <span class="req">*</span></label>
                <select name="insurance_company" id="companySelect" class="form-control-custom" required>
                  <option value="" disabled <?= empty($_POST['insurance_company']) ? 'selected' : '' ?>>Select company…</option>
                  <?php foreach ($companyRows as $co): ?>
                  <option value="<?= htmlspecialchars($co['company_name']) ?>" data-id="<?= $co['insurance_company_id'] ?>"
                    data-prefix="<?= match($co['company_name']){'PhilHealth'=>'PH','Maxicare'=>'MC','Medicard'=>'MD','Intellicare'=>'IC',default=>'IN'} ?>"
                    <?= ($_POST['insurance_company'] ?? '') === $co['company_name'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($co['company_name']) ?>
                  </option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="form-group">
                <label class="form-label">Promo / Plan <span class="req">*</span></label>
                <select name="promo_name" id="promoSelect" class="form-control-custom" required disabled>
                  <option value="" disabled selected>Select company first…</option>
                </select>
              </div>
            </div>
            <div class="promo-summary" id="promoSummary">
              <div class="promo-summary-title"><i class="bi bi-check-circle-fill"></i> Plan Details</div>
              <div class="promo-summary-grid">
                <div class="promo-summary-item"><span class="promo-summary-label">Type</span><span class="promo-summary-val" id="psType">—</span></div>
                <div class="promo-summary-item"><span class="promo-summary-label">Discount</span><span class="promo-summary-val green" id="psValue">—</span></div>
                <div class="promo-summary-item"><span class="promo-summary-label">Company</span><span class="promo-summary-val" id="psCompany">—</span></div>
              </div>
            </div>
          </div>

          <!-- Discount -->
          <div class="form-section">
            <div class="form-section-title"><i class="bi bi-tag"></i> Discount Details</div>
            <div class="form-row cols-2">
              <div class="form-group">
                <label class="form-label">Discount Type <span class="req">*</span></label>
                <select id="discountTypeSelect" class="form-control-custom" disabled>
                  <option value="Percentage" <?= ($_POST['discount_type'] ?? 'Percentage') === 'Percentage' ? 'selected' : '' ?>>Percentage (%)</option>
                  <option value="Fixed" <?= ($_POST['discount_type'] ?? '') === 'Fixed' ? 'selected' : '' ?>>Fixed Amount (₱)</option>
                </select>
              </div>
              <div class="form-group">
                <label class="form-label">Discount Value <span class="req">*</span></label>
                <select name="discount_value" id="discountValueSelect" class="form-control-custom" required disabled>
                  <option value="" disabled selected>Select promo first…</option>
                </select>
              </div>
            </div>
          </div>

          <!-- Status -->
          <div class="form-section" style="margin-bottom:0;">
            <div class="form-section-title"><i class="bi bi-toggle-on"></i> Status</div>
            <div class="add-status-selector">
              <?php $savedStatus = $_POST['status'] ?? 'Pending';
              $statusIcons = ['Pending'=>'bi-clock','Active'=>'bi-check-circle-fill','Inactive'=>'bi-x-circle'];
              foreach (['Pending','Active','Inactive'] as $s): ?>
              <button type="button" class="add-status-opt <?= $s === $savedStatus ? 'selected' : '' ?>" data-status="<?= $s ?>" onclick="selectAddStatus(this,'<?= $s ?>')">
                <i class="bi <?= $statusIcons[$s] ?>"></i> <?= $s ?>
              </button>
              <?php endforeach; ?>
            </div>
          </div>
        </div>
        <div class="add-modal-footer">
          <button type="button" class="btn-cancel" data-bs-dismiss="modal"><i class="bi bi-x-lg"></i> Cancel</button>
          <button type="submit" class="btn-submit" id="submitBtn"><i class="bi bi-shield-plus"></i> Save Insurance Record</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- ═══════════════════════════════════════
     STATUS UPDATE + CARD VIEWER MODALS
═══════════════════════════════════════ -->
<?php foreach ($rows as $row):
  $colors  = cardGradient($row['insurance_company']);
  $accent  = cardAccent($row['insurance_company']);
  $co      = $row['insurance_company'];
  $id      = intval($row['patient_insurance_id']);
  $modalId = 'modal_' . $id;
  $statusModalId = 'statusModal_' . $id;
  $status  = strtolower($row['status'] ?? 'pending');
  $discount = $row['discount_type'] === 'Percentage'
    ? htmlspecialchars($row['discount_value']) . '%'
    : '₱' . number_format($row['discount_value'], 2);
  $numParts = implode(' ', str_split(preg_replace('/\s+/', '', $row['insurance_number']), 4));
  $icon  = companyIcon($co);
  $currentStatus = ucfirst($status);
?>

<!-- STATUS UPDATE MODAL for record <?= $id ?> -->
<div class="modal fade status-modal" id="<?= $statusModalId ?>" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" style="max-width:480px;">
    <div class="modal-content">
      <div class="modal-header">
        <div class="modal-header-icon"><i class="bi bi-pencil-square"></i></div>
        <div>
          <div class="modal-title">Update Insurance Status</div>
          <div class="modal-sub">Change coverage status for this patient</div>
        </div>
        <button type="button" class="btn-close ms-auto" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST" action="">
        <input type="hidden" name="action"       value="update_status">
        <input type="hidden" name="insurance_id" value="<?= $id ?>">
        <input type="hidden" name="new_status"   id="newStatusHidden_<?= $id ?>" value="<?= $currentStatus ?>">
        <div class="modal-body" style="background:var(--paper);padding:28px;">

          <!-- Patient mini card -->
          <div class="patient-card-mini">
            <div class="patient-card-mini-avatar"><i class="bi bi-person-fill"></i></div>
            <div>
              <div class="patient-card-mini-name"><?= htmlspecialchars($row['full_name']) ?></div>
              <div class="patient-card-mini-meta">
                <span class="company-badge <?= badgeClass($co) ?>" style="font-size:.68rem;padding:2px 8px;">
                  <i class="bi <?= $icon ?>"></i> <?= htmlspecialchars($co) ?>
                </span>
                &nbsp;·&nbsp;
                <span style="font-family:var(--ff-mono);font-size:.74rem;"><?= htmlspecialchars($row['insurance_number']) ?></span>
              </div>
            </div>
            <div style="margin-left:auto;">
              <span class="status-badge <?= $status ?>"><span class="status-dot"></span><?= $currentStatus ?></span>
            </div>
          </div>

          <!-- Status cards -->
          <div class="status-section-label"><i class="bi bi-arrow-repeat"></i> Select New Status</div>
          <div class="status-cards">

            <label class="status-card <?= $currentStatus === 'Pending' ? 'selected' : '' ?>" data-val="Pending"
                   onclick="selectNewStatus(<?= $id ?>,'Pending',this)">
              <input type="radio" name="_status_radio_<?= $id ?>" value="Pending" <?= $currentStatus === 'Pending' ? 'checked' : '' ?>>
              <div class="status-card-icon"><i class="bi bi-clock-fill"></i></div>
              <span class="status-card-label">Pending</span>
              <span class="status-card-desc">Awaiting review or approval</span>
            </label>

            <label class="status-card <?= $currentStatus === 'Active' ? 'selected' : '' ?>" data-val="Active"
                   onclick="selectNewStatus(<?= $id ?>,'Active',this)">
              <input type="radio" name="_status_radio_<?= $id ?>" value="Active" <?= $currentStatus === 'Active' ? 'checked' : '' ?>>
              <div class="status-card-icon"><i class="bi bi-check-circle-fill"></i></div>
              <span class="status-card-label">Active</span>
              <span class="status-card-desc">Coverage is valid and in use</span>
            </label>

            <label class="status-card <?= $currentStatus === 'Inactive' ? 'selected' : '' ?>" data-val="Inactive"
                   onclick="selectNewStatus(<?= $id ?>,'Inactive',this)">
              <input type="radio" name="_status_radio_<?= $id ?>" value="Inactive" <?= $currentStatus === 'Inactive' ? 'checked' : '' ?>>
              <div class="status-card-icon"><i class="bi bi-x-circle-fill"></i></div>
              <span class="status-card-label">Inactive</span>
              <span class="status-card-desc">Coverage has expired or been revoked</span>
            </label>

          </div>

          <div class="status-update-note">
            <i class="bi bi-info-circle-fill"></i>
            This will immediately update the patient's insurance status in the system.
          </div>
        </div>
        <div class="status-modal-footer">
          <button type="button" class="btn-cancel" data-bs-dismiss="modal"><i class="bi bi-x-lg"></i> Cancel</button>
          <button type="submit" class="btn-submit-status" id="statusSubmitBtn_<?= $id ?>">
            <i class="bi bi-check2-circle"></i> Update Status
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- CARD VIEWER MODAL for record <?= $id ?> -->
<div class="modal fade" id="<?= $modalId ?>" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" style="max-width:440px;">
    <div class="modal-content">
      <div class="modal-header">
        <div class="modal-header-icon"><i class="bi <?= $icon ?>"></i></div>
        <div>
          <div class="modal-title"><?= htmlspecialchars($row['full_name']) ?></div>
          <div class="modal-sub"><?= htmlspecialchars($co) ?> · Insurance Card</div>
        </div>
        <button type="button" class="btn-close ms-auto" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="card-stage">
          <div class="ins-card" style="background:linear-gradient(150deg,<?= $colors[0] ?>,<?= $colors[1] ?> 52%,<?= $colors[2] ?>);">
            <div class="deco1"></div><div class="deco2"></div><div class="deco3"></div>
            <div class="ic-top">
              <div class="ic-company"><?= htmlspecialchars($co) ?></div>
              <div class="ic-logo">
                <span style="background:<?= $accent ?>;border:1.5px solid rgba(255,255,255,.3);"></span>
                <span style="background:rgba(255,255,255,.22);border:1.5px solid rgba(255,255,255,.3);"></span>
              </div>
            </div>
            <div class="ic-chip"></div>
            <div class="ic-number"><?= $numParts ?></div>
            <div class="ic-bottom">
              <div>
                <div class="ic-holder-label">Card Holder</div>
                <div class="ic-holder-name"><?= htmlspecialchars(strtoupper($row['full_name'])) ?></div>
              </div>
              <div class="ic-promo">
                <div class="ic-promo-label"><?= htmlspecialchars($row['promo_name'] ?: 'No Plan') ?></div>
                <div class="ic-discount"><?= $discount ?></div>
              </div>
            </div>
          </div>
        </div>
        <div class="detail-grid">
          <div class="detail-cell">
            <div class="detail-cell-label">Relationship</div>
            <div class="detail-cell-val"><?= htmlspecialchars($row['relationship_to_insured']) ?></div>
          </div>
          <div class="detail-cell">
            <div class="detail-cell-label">Discount Type</div>
            <div class="detail-cell-val"><?= htmlspecialchars($row['discount_type']) ?></div>
          </div>
          <div class="detail-cell">
            <div class="detail-cell-label">Status</div>
            <div class="detail-cell-val status-<?= $status ?>"><?= ucfirst($status) ?></div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<?php endforeach; ?>

<script>
const allPromos   = <?= json_encode(array_values($promoRows)) ?>;
const prefixMap   = { PhilHealth:'PH', Maxicare:'MC', Medicard:'MD', Intellicare:'IC' };

/* ── Insurance number generator ── */
function generateNumber(prefix) {
  const year = new Date().getFullYear();
  const rand = String(Math.floor(Math.random() * 100000)).padStart(5,'0');
  return prefix + '-' + year + '-' + rand;
}
function regenNumber() {
  const co = document.getElementById('companySelect').value;
  if (!co) return;
  document.getElementById('insuranceNumberInput').value = generateNumber(prefixMap[co] || 'IN');
}

/* ── Company → number + promos ── */
document.getElementById('companySelect').addEventListener('change', function () {
  const name   = this.value;
  const prefix = prefixMap[name] || 'IN';
  document.getElementById('insuranceNumberInput').value = generateNumber(prefix);
  document.getElementById('regenBtn').style.display     = 'flex';
  document.getElementById('insNumBadge').style.display  = 'inline-flex';

  const ps = document.getElementById('promoSelect');
  ps.innerHTML = '<option value="" disabled selected>Select a plan…</option>';
  ps.disabled  = false;
  allPromos.filter(p => p.company_name === name).forEach(p => {
    const o = document.createElement('option');
    o.value = p.promo_name;
    o.textContent = p.promo_name;
    o.dataset.discountType  = p.discount_type;
    o.dataset.discountValue = p.discount_value;
    ps.appendChild(o);
  });
  clearPromoSummary(); clearDiscountFields();
});

/* ── Promo → discount ── */
document.getElementById('promoSelect').addEventListener('change', function () {
  const opt  = this.options[this.selectedIndex];
  if (!opt.value || !opt.dataset.discountType) { clearPromoSummary(); clearDiscountFields(); return; }
  const type  = opt.dataset.discountType;
  const value = parseFloat(opt.dataset.discountValue);
  const co    = document.getElementById('companySelect').value;

  document.getElementById('psType').textContent    = type;
  document.getElementById('psValue').textContent   = type === 'Percentage' ? value + '%' : '₱' + value.toFixed(2);
  document.getElementById('psCompany').textContent = co;
  document.getElementById('promoSummary').classList.add('show');

  const dtSel = document.getElementById('discountTypeSelect');
  dtSel.value = type; dtSel.disabled = false;
  document.getElementById('hiddenDiscountType').value = type;

  const coPromos = allPromos.filter(p => p.company_name === co);
  const dvSel    = document.getElementById('discountValueSelect');
  dvSel.innerHTML = '';
  dvSel.disabled  = false;
  coPromos.forEach(p => {
    const o = document.createElement('option');
    o.value = p.discount_value;
    o.textContent = (p.discount_type === 'Percentage')
      ? p.discount_value + '% — ' + p.promo_name
      : '₱' + parseFloat(p.discount_value).toFixed(2) + ' — ' + p.promo_name;
    o.dataset.discountType = p.discount_type;
    if (p.promo_name === opt.value) o.selected = true;
    dvSel.appendChild(o);
  });
});

document.getElementById('discountValueSelect').addEventListener('change', function () {
  const opt = this.options[this.selectedIndex];
  if (opt.dataset.discountType) {
    document.getElementById('discountTypeSelect').value = opt.dataset.discountType;
    document.getElementById('hiddenDiscountType').value = opt.dataset.discountType;
  }
});

function clearPromoSummary() { document.getElementById('promoSummary').classList.remove('show'); }
function clearDiscountFields() {
  const dvSel = document.getElementById('discountValueSelect');
  dvSel.innerHTML = '<option value="" disabled selected>Select promo first…</option>';
  dvSel.disabled = true;
  document.getElementById('discountTypeSelect').value   = 'Percentage';
  document.getElementById('discountTypeSelect').disabled = true;
  document.getElementById('hiddenDiscountType').value   = 'Percentage';
}

/* ── Add form status ── */
function selectAddStatus(btn, status) {
  document.querySelectorAll('.add-status-opt').forEach(b => b.classList.remove('selected'));
  btn.classList.add('selected');
  document.getElementById('hiddenStatus').value = status;
}

/* ── STATUS UPDATE: select card ── */
function selectNewStatus(id, status, cardEl) {
  const container = cardEl.closest('.status-cards');
  container.querySelectorAll('.status-card').forEach(c => c.classList.remove('selected'));
  cardEl.classList.add('selected');
  document.getElementById('newStatusHidden_' + id).value = status;

  // Update submit button color based on selection
  const btn = document.getElementById('statusSubmitBtn_' + id);
  const colors = { Pending:'linear-gradient(135deg,#78350f,#d97706)', Active:'linear-gradient(135deg,#065f46,#059669)', Inactive:'linear-gradient(135deg,#7f1d1d,#dc2626)' };
  btn.style.background = colors[status] || 'linear-gradient(135deg,#1a1a2e,#3d2a6e)';
}

/* ── Submit guard (add form) ── */
document.getElementById('addInsuranceForm').addEventListener('submit', function () {
  const btn = document.getElementById('submitBtn');
  btn.innerHTML = '<i class="bi bi-hourglass-split"></i> Saving…';
  btn.disabled  = true;
});

/* ── Search + filter (company) ── */
let activeFilter = 'all';
let activeStatusFilter = '';
function filterRows(btn, filter) {
  document.querySelectorAll('.ftab').forEach(b => b.classList.remove('active'));
  btn.classList.add('active');
  activeFilter = filter;
  activeStatusFilter = '';
  applyFilters();
}
function filterByStatus(btn, statusFilter) {
  document.querySelectorAll('.ftab').forEach(b => b.classList.remove('active'));
  btn.classList.add('active');
  activeFilter = 'all';
  activeStatusFilter = statusFilter;
  applyFilters();
}
document.getElementById('searchInput').addEventListener('input', applyFilters);
function applyFilters() {
  const q = document.getElementById('searchInput').value.toLowerCase().trim();
  let visible = 0;
  document.querySelectorAll('.record-row').forEach(row => {
    const matchCo     = activeFilter === 'all' || row.dataset.company === activeFilter;
    const matchStatus = !activeStatusFilter || row.dataset.statusKey === activeStatusFilter;
    const matchSearch = !q || row.dataset.search.includes(q);
    const show = matchCo && matchStatus && matchSearch;
    row.style.display = show ? '' : 'none';
    if (show) visible++;
  });
  const el = document.getElementById('rowCount');
  if (el) el.innerHTML = `Showing <strong>${visible}</strong> record${visible !== 1 ? 's' : ''}`;
}

/* ── Sidebar sync ── */
(function () {
  const sidebar = document.getElementById('mySidebar');
  const cw      = document.getElementById('mainCw');
  if (!sidebar || !cw) return;
  const sync = () => cw.classList.toggle('sidebar-collapsed', sidebar.classList.contains('closed'));
  new MutationObserver(sync).observe(sidebar, { attributes: true, attributeFilter: ['class'] });
  document.getElementById('sidebarToggle')?.addEventListener('click', () => requestAnimationFrame(sync));
  sync();
})();

/* ── Toast auto-dismiss ── */
document.querySelectorAll('.toast-pop').forEach(t => setTimeout(() => t.remove(), 4000));

/* ── Re-open add modal on error ── */
<?php if ($addError): ?>
(function () {
  new bootstrap.Modal(document.getElementById('addInsuranceModal')).show();
  const coSel = document.getElementById('companySelect');
  if (coSel.value) {
    coSel.dispatchEvent(new Event('change'));
    const savedPromo = <?= json_encode($_POST['promo_name'] ?? '') ?>;
    if (savedPromo) setTimeout(() => {
      const ps = document.getElementById('promoSelect');
      ps.value = savedPromo;
      ps.dispatchEvent(new Event('change'));
      const savedDv = <?= json_encode($_POST['discount_value'] ?? '') ?>;
      if (savedDv) document.getElementById('discountValueSelect').value = savedDv;
    }, 30);
    const savedNum = <?= json_encode($_POST['insurance_number'] ?? '') ?>;
    if (savedNum) document.getElementById('insuranceNumberInput').value = savedNum;
    document.getElementById('regenBtn').style.display    = 'flex';
    document.getElementById('insNumBadge').style.display = 'inline-flex';
  }
  const savedStatus = <?= json_encode($_POST['status'] ?? 'Pending') ?>;
  const sBtn = document.querySelector(`.add-status-opt[data-status="${savedStatus}"]`);
  if (sBtn) selectAddStatus(sBtn, savedStatus);
})();
<?php endif; ?>
</script>
</body>
</html>