<?php
/*
 * ═══════════════════════════════════════════════════════════
 *  ONE-TIME DATABASE FIX — run this once in phpMyAdmin to
 *  remove the unused vendor_id column:
 *
 *     ALTER TABLE return_requests DROP COLUMN vendor_id;
 *
 *  After running that, this file will work without errors.
 * ═══════════════════════════════════════════════════════════
 */
session_start();
include '../../SQL/config.php';

// Always throw exceptions so a failed query never silently
// skips the redirect (which is what caused duplicate rows).
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$user_id = $_SESSION['user_id'] ?? null;

// Inventory items with stock remaining
$inventoryStmt = $pdo->query("SELECT * FROM inventory WHERE quantity > 0 ORDER BY item_name ASC");
$items = $inventoryStmt->fetchAll(PDO::FETCH_ASSOC);

// ═══════════════════════════════════════════════════════════
//  POST  —  PRG (Post / Redirect / Get) pattern
//
//  A one-time session token is destroyed the instant the POST
//  is processed, so any replay (refresh, back-button, double-
//  click, network retry) hits an empty token and is redirected
//  harmlessly — no second row is ever inserted.
// ═══════════════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // 1. Token check ─────────────────────────────────────────
    $submittedToken = $_POST['submit_token'] ?? '';
    $sessionToken   = $_SESSION['submit_token'] ?? '';

    if (empty($submittedToken) || $submittedToken !== $sessionToken) {
        header('Location: return_damage.php?status=already_submitted');
        exit;
    }
    unset($_SESSION['submit_token']); // destroy immediately — one use only

    // 2. Validate ────────────────────────────────────────────
    $inventory_id = (int)($_POST['inventory_id'] ?? 0);
    $quantity     = (int)($_POST['quantity']     ?? 0);
    $reason       = trim($_POST['reason']        ?? '');

    $checkStmt = $pdo->prepare("SELECT quantity FROM inventory WHERE id = ?");
    $checkStmt->execute([$inventory_id]);
    $itemData = $checkStmt->fetch(PDO::FETCH_ASSOC);

    if (!$itemData) {
        $_SESSION['form_error'] = "Invalid item selected.";
        header('Location: return_damage.php'); exit;
    }
    if ($quantity < 1) {
        $_SESSION['form_error'] = "Quantity must be at least 1.";
        header('Location: return_damage.php'); exit;
    }
    if ($quantity > $itemData['quantity']) {
        $_SESSION['form_error'] = "Quantity cannot exceed available stock ({$itemData['quantity']}).";
        header('Location: return_damage.php'); exit;
    }
    if (empty($reason)) {
        $_SESSION['form_error'] = "Please provide a reason for the return.";
        header('Location: return_damage.php'); exit;
    }

    // 3. Optional photo upload ────────────────────────────────
    $photoPath = null;
    if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = 'uploads/returns/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
        $ext       = pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION);
        $photoPath = $uploadDir . uniqid() . '.' . $ext;
        if (!move_uploaded_file($_FILES['photo']['tmp_name'], $photoPath)) {
            $_SESSION['form_error'] = "Failed to upload photo. Please check folder permissions.";
            header('Location: return_damage.php'); exit;
        }
    }

    // 4. Insert — NO vendor_id ────────────────────────────────
    try {
        $stmt = $pdo->prepare("
            INSERT INTO return_requests
                (inventory_id, requested_by, quantity, reason, photo, status, requested_at, updated_at)
            VALUES (?, ?, ?, ?, ?, 'Pending', NOW(), NOW())
        ");
        $stmt->execute([$inventory_id, $user_id, $quantity, $reason, $photoPath]);
    } catch (Exception $e) {
        $_SESSION['form_error'] = "Database error — could not save request. Please try again.";
        if ($photoPath && file_exists($photoPath)) unlink($photoPath);
        header('Location: return_damage.php'); exit;
    }

    // 5. PRG redirect ─────────────────────────────────────────
    $_SESSION['form_success'] = "Return request submitted successfully. Awaiting review.";
    header('Location: return_damage.php?tab=all');
    exit;
}

// ═══════════════════════════════════════════════════════════
//  GET  —  flash messages + fresh token
// ═══════════════════════════════════════════════════════════
$success = null;
$error   = null;

if (isset($_SESSION['form_success'])) { $success = $_SESSION['form_success']; unset($_SESSION['form_success']); }
if (isset($_SESSION['form_error']))   { $error   = $_SESSION['form_error'];   unset($_SESSION['form_error']);   }

$_SESSION['submit_token'] = bin2hex(random_bytes(16));
$submitToken = $_SESSION['submit_token'];
$activeTab   = (($_GET['tab'] ?? 'new') === 'all') ? 'all' : 'new';

// Fetch all requests — subqueries with LIMIT 1 guarantee exactly
// one row per return_request even if inventory/users have duplicate keys.
$requestStmt = $pdo->prepare("
    SELECT
        rr.id,
        rr.inventory_id,
        rr.requested_by,
        rr.quantity,
        rr.reason,
        rr.photo,
        rr.status,
        rr.requested_at,
        (SELECT i.item_name FROM inventory i
         WHERE i.id = rr.inventory_id LIMIT 1) AS item_name,
        (SELECT u.username FROM users u
         WHERE u.user_id = rr.requested_by LIMIT 1) AS username
    FROM return_requests rr
    GROUP BY rr.id
    ORDER BY rr.id DESC
");
$requestStmt->execute();
$requests = $requestStmt->fetchAll(PDO::FETCH_ASSOC);

$statPending  = count(array_filter($requests, fn($r) => strtolower($r['status']) === 'pending'));
$statApproved = count(array_filter($requests, fn($r) => strtolower($r['status']) === 'approved'));
$statRejected = count(array_filter($requests, fn($r) => strtolower($r['status']) === 'rejected'));
$statReturned = count(array_filter($requests, fn($r) => strtolower($r['status']) === 'returned'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Return &amp; Damage Requests — Inventory</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
<style>
:root {
    --bg:            #f0f2f7;
    --surface:       #ffffff;
    --surface-2:     #f7f8fc;
    --border:        #e4e7ef;
    --text-primary:  #1a2035;
    --text-sec:      #5a6079;
    --text-muted:    #9ba3bd;
    --accent:        #00acc1;
    --accent-light:  rgba(0,172,193,.08);
    --accent-mid:    rgba(0,172,193,.18);
    --success:       #16a34a;
    --success-light: #f0fdf4;
    --success-mid:   #86efac;
    --warning:       #d97706;
    --warning-light: #fffbeb;
    --warning-mid:   #fcd34d;
    --danger:        #e05555;
    --danger-light:  rgba(224,85,85,.08);
    --danger-mid:    rgba(224,85,85,.25);
    --info:          #2980b9;
    --info-light:    #ecf6fb;
    --info-mid:      #93c6e0;
    --sidebar-w:     250px;
    --radius-sm:     6px;
    --radius:        10px;
    --radius-lg:     16px;
    --shadow-sm:     0 1px 3px rgba(0,0,0,.06);
    --shadow:        0 4px 16px rgba(0,0,0,.07);
    --transition:    .2s cubic-bezier(.4,0,.2,1);
}
*,*::before,*::after { box-sizing: border-box; }
body { background:var(--bg); font-family:'Nunito',sans-serif; color:var(--text-primary); font-size:14px; line-height:1.6; -webkit-font-smoothing:antialiased; }

/* Layout */
.sidebar-area { position:fixed; left:0; top:0; bottom:0; width:var(--sidebar-w); z-index:100; }
.main-content  { margin-left:var(--sidebar-w); padding:36px 36px 72px; min-height:100vh; }

/* Page header */
.page-header { display:flex; align-items:center; justify-content:space-between; margin-bottom:28px; padding-bottom:22px; border-bottom:1.5px solid var(--border); }
.page-header-left { display:flex; align-items:center; gap:16px; }
.page-icon-wrap { width:48px; height:48px; background:linear-gradient(135deg,var(--danger),#c0392b); border-radius:var(--radius); display:flex; align-items:center; justify-content:center; color:#fff; font-size:1.3rem; box-shadow:0 4px 14px rgba(224,85,85,.35); flex-shrink:0; }
.page-eyebrow { font-size:11px; font-weight:700; letter-spacing:.1em; text-transform:uppercase; color:var(--danger); margin-bottom:3px; }
.page-title { font-size:1.5rem; font-weight:800; color:var(--text-primary); margin:0; letter-spacing:-.3px; }
.page-date .date-label { color:var(--text-muted); font-weight:600; display:block; font-size:12.5px; text-align:right; }
.page-date .date-val   { font-weight:800; color:var(--text-primary); font-size:14px; text-align:right; display:block; }

/* Stats */
.stats-row { display:grid; grid-template-columns:repeat(4,1fr); gap:16px; margin-bottom:28px; }
.stat-card { background:var(--surface); border:1px solid var(--border); border-radius:var(--radius); padding:16px 20px; display:flex; align-items:center; gap:14px; box-shadow:var(--shadow-sm); transition:box-shadow var(--transition),transform var(--transition); }
.stat-card:hover { box-shadow:var(--shadow); transform:translateY(-2px); }
.stat-icon-box { width:42px; height:42px; border-radius:var(--radius-sm); display:flex; align-items:center; justify-content:center; font-size:1.1rem; flex-shrink:0; }
.si-amber { background:var(--warning-light); color:var(--warning); }
.si-green { background:var(--success-light); color:var(--success); }
.si-red   { background:var(--danger-light);  color:var(--danger); }
.si-blue  { background:var(--info-light);    color:var(--info); }
.stat-info .stat-label { font-size:11.5px; color:var(--text-muted); font-weight:700; letter-spacing:.03em; }
.stat-info .stat-value { font-size:1.65rem; font-weight:800; line-height:1.1; color:var(--text-primary); }

/* Tabs */
.tab-nav { display:flex; gap:4px; border-bottom:2px solid var(--border); margin-bottom:24px; }
.tab-btn { display:flex; align-items:center; gap:8px; padding:10px 22px; font-size:13.5px; font-weight:700; color:var(--text-muted); background:transparent; border:none; border-bottom:2px solid transparent; margin-bottom:-2px; cursor:pointer; font-family:'Nunito',sans-serif; transition:color var(--transition),border-color var(--transition); }
.tab-btn:hover { color:var(--text-primary); }
.tab-btn.active { color:var(--danger); border-bottom-color:var(--danger); }
.tab-count-pill { background:var(--surface-2); border:1px solid var(--border); border-radius:20px; font-size:11px; padding:1px 8px; font-weight:700; color:var(--text-muted); }
.tab-btn.active .tab-count-pill { background:var(--danger-light); border-color:var(--danger-mid); color:var(--danger); }
.tab-panel { display:none; }
.tab-panel.active { display:block; }

/* Card */
.card-shell { background:var(--surface); border:1px solid var(--border); border-radius:var(--radius-lg); box-shadow:var(--shadow); overflow:hidden; }
.card-head { padding:18px 24px; border-bottom:1px solid var(--border); display:flex; align-items:center; justify-content:space-between; }
.card-head h6 { margin:0; font-size:14px; font-weight:800; color:var(--text-primary); display:flex; align-items:center; gap:9px; }
.card-head h6 i { color:var(--danger); }
.card-body-pad { padding:28px; }

/* Form */
.form-section-label { font-size:10.5px; font-weight:800; letter-spacing:.1em; text-transform:uppercase; color:var(--text-muted); margin-bottom:16px; padding-bottom:9px; border-bottom:1px solid var(--border); display:flex; align-items:center; gap:6px; }
.form-section-label i { font-size:.85rem; color:var(--accent); }
.field-group { margin-bottom:22px; }
.field-label { display:flex; align-items:center; justify-content:space-between; font-size:13px; font-weight:700; color:var(--text-sec); margin-bottom:7px; }
.field-label .req { color:var(--danger); margin-left:2px; font-size:.85em; }
.field-label .optional-tag { font-size:10.5px; font-weight:600; color:var(--text-muted); background:var(--surface-2); border:1px solid var(--border); border-radius:4px; padding:1px 7px; text-transform:uppercase; letter-spacing:.04em; }
.form-ctrl,.form-sel { font-family:'Nunito',sans-serif; font-size:13.5px; font-weight:600; border:1.5px solid var(--border); border-radius:var(--radius-sm); background:var(--surface-2); color:var(--text-primary); padding:10px 14px; width:100%; transition:border-color var(--transition),box-shadow var(--transition),background var(--transition); outline:none; }
.form-ctrl:focus,.form-sel:focus { border-color:var(--accent); box-shadow:0 0 0 3px var(--accent-mid); background:var(--surface); }
textarea.form-ctrl { resize:vertical; min-height:110px; }
.field-hint { font-size:12px; color:var(--text-muted); margin-top:5px; font-weight:600; display:flex; align-items:center; gap:4px; }
.stock-bar { display:inline-flex; align-items:center; gap:6px; background:var(--accent-light); border:1px solid var(--accent-mid); color:var(--accent); border-radius:var(--radius-sm); padding:3px 10px; font-size:12px; font-weight:700; margin-top:6px; }

/* Upload */
.upload-zone { border:2px dashed var(--border); border-radius:var(--radius); padding:26px 20px; text-align:center; cursor:pointer; transition:border-color var(--transition),background var(--transition); position:relative; background:var(--surface-2); }
.upload-zone:hover { border-color:var(--accent); background:var(--accent-light); }
.upload-zone.has-file { border-color:var(--success); background:var(--success-light); }
.upload-zone input[type="file"] { position:absolute; inset:0; opacity:0; cursor:pointer; width:100%; height:100%; }
.upload-icon { font-size:2rem; color:var(--text-muted); margin-bottom:8px; line-height:1; }
.upload-title { font-size:13.5px; font-weight:700; color:var(--text-sec); }
.upload-sub { font-size:12px; color:var(--text-muted); margin-top:3px; font-weight:600; }
.upload-file-name { display:none; margin-top:10px; font-size:12.5px; font-weight:700; color:var(--success); align-items:center; gap:5px; }
.upload-file-name.show { display:inline-flex; }

/* Submit button */
.btn-submit-req { display:inline-flex; align-items:center; gap:9px; padding:12px 28px; background:linear-gradient(135deg,var(--danger),#c0392b); color:#fff; font-size:14px; font-weight:800; font-family:'Nunito',sans-serif; border:none; border-radius:var(--radius-sm); cursor:pointer; transition:all var(--transition); box-shadow:0 4px 14px rgba(224,85,85,.35); letter-spacing:-.2px; }
.btn-submit-req:hover:not(:disabled) { transform:translateY(-2px); box-shadow:0 8px 22px rgba(224,85,85,.45); }
.btn-submit-req:disabled { opacity:.6; cursor:not-allowed; transform:none !important; }

/* Alerts */
.alert-strip { display:flex; align-items:flex-start; gap:10px; padding:13px 18px; border-radius:var(--radius); font-size:13.5px; font-weight:600; margin-bottom:22px; border:1.5px solid; animation:slideDown .25s ease; }
@keyframes slideDown { from{opacity:0;transform:translateY(-6px)} to{opacity:1;transform:none} }
.alert-strip i { flex-shrink:0; font-size:15px; margin-top:1px; }
.alert-ok   { background:var(--success-light); border-color:var(--success-mid); color:var(--success); }
.alert-err  { background:var(--danger-light);  border-color:var(--danger-mid);  color:var(--danger); }
.alert-warn { background:var(--warning-light); border-color:var(--warning-mid); color:var(--warning); }

/* Table */
.requests-table { width:100%; border-collapse:collapse; }
.requests-table thead tr { background:var(--surface-2); }
.requests-table thead th { padding:11px 16px; font-size:10.5px; font-weight:800; letter-spacing:.08em; text-transform:uppercase; color:var(--text-muted); border-bottom:1.5px solid var(--border); white-space:nowrap; text-align:left; }
.requests-table tbody tr { border-bottom:1px solid var(--border); transition:background var(--transition); animation:fadeRow .22s ease both; }
.requests-table tbody tr:last-child { border-bottom:none; }
.requests-table tbody tr:hover { background:#f5f7ff; }
.requests-table td { padding:13px 16px; font-size:13.5px; color:var(--text-primary); vertical-align:middle; }
@keyframes fadeRow { from{opacity:0;transform:translateY(4px)} to{opacity:1;transform:none} }
<?php foreach(range(1,30) as $i): ?>
.requests-table tbody tr:nth-child(<?= $i ?>) { animation-delay:<?= ($i-1)*0.025 ?>s; }
<?php endforeach; ?>

.id-mono { font-family:monospace; font-size:12px; font-weight:700; background:var(--surface-2); border:1px solid var(--border); border-radius:var(--radius-sm); padding:3px 8px; color:var(--text-sec); }
.item-strong { font-weight:700; }
.user-cell { display:flex; align-items:center; gap:8px; }
.user-avatar { width:28px; height:28px; border-radius:50%; background:var(--accent-light); border:1.5px solid var(--accent-mid); color:var(--accent); font-size:11px; font-weight:800; display:flex; align-items:center; justify-content:center; flex-shrink:0; }
.qty-tag { font-weight:800; background:var(--surface-2); border:1px solid var(--border); border-radius:var(--radius-sm); padding:2px 9px; font-size:12.5px; }
.reason-clip { max-width:200px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; color:var(--text-sec); font-size:13px; font-weight:600; cursor:help; }
.thumb-img { width:48px; height:48px; object-fit:cover; border-radius:var(--radius-sm); border:1px solid var(--border); cursor:pointer; transition:transform var(--transition),box-shadow var(--transition); }
.thumb-img:hover { transform:scale(1.1); box-shadow:var(--shadow); }
.no-photo-tag { color:var(--text-muted); font-size:12px; font-weight:600; display:flex; align-items:center; gap:4px; }
.date-mono { font-size:12px; color:var(--text-sec); font-weight:700; font-family:monospace; white-space:nowrap; }
.status-pill { display:inline-flex; align-items:center; gap:5px; padding:4px 12px; border-radius:100px; font-size:12px; font-weight:800; white-space:nowrap; letter-spacing:.02em; }
.sp-pending  { background:var(--warning-light); color:var(--warning); }
.sp-approved { background:var(--success-light); color:var(--success); }
.sp-rejected { background:var(--danger-light);  color:var(--danger); }
.sp-returned { background:var(--info-light);    color:var(--info); }
.sp-unknown  { background:var(--surface-2);     color:var(--text-muted); }
.rec-badge { font-size:12px; background:var(--danger-light); color:var(--danger); border-radius:100px; padding:3px 12px; font-weight:800; }
.empty-wrap { text-align:center; padding:64px 20px; }
.empty-circle { width:64px; height:64px; background:var(--surface-2); border-radius:50%; display:inline-flex; align-items:center; justify-content:center; font-size:1.7rem; margin-bottom:16px; border:1px solid var(--border); color:var(--text-muted); }

/* Lightbox */
.lb-overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,.82); z-index:9999; align-items:center; justify-content:center; backdrop-filter:blur(4px); }
.lb-overlay.open { display:flex; }
.lb-overlay img { max-width:90vw; max-height:85vh; border-radius:var(--radius); box-shadow:0 24px 64px rgba(0,0,0,.6); }
.lb-close { position:absolute; top:20px; right:26px; font-size:2rem; color:#fff; cursor:pointer; width:38px; height:38px; display:flex; align-items:center; justify-content:center; background:rgba(255,255,255,.12); border-radius:50%; transition:opacity var(--transition); }
.lb-close:hover { opacity:.7; }

@media(max-width:1024px) { .main-content { margin-left:0; padding:76px 20px 60px; } .stats-row { grid-template-columns:repeat(2,1fr); } }
@media(max-width:640px)  { .stats-row { grid-template-columns:1fr 1fr; gap:12px; } .main-content { padding:72px 14px 48px; } .page-header { flex-direction:column; align-items:flex-start; gap:10px; } .card-body-pad { padding:18px; } }
</style>
</head>
<body>

<div class="sidebar-area"><?php include 'inventory_sidebar.php'; ?></div>

<div class="main-content">

    <!-- Page Header -->
    <div class="page-header">
        <div class="page-header-left">
            <div class="page-icon-wrap"><i class="bi bi-arrow-return-left"></i></div>
            <div>
                <div class="page-eyebrow">Equipment &amp; Medicine Stock</div>
                <h1 class="page-title">Return &amp; Damage Requests</h1>
            </div>
        </div>
        <div class="page-date">
            <span class="date-label">Today</span>
            <span class="date-val"><?= date('F d, Y') ?></span>
        </div>
    </div>

    <!-- Alerts -->
    <?php if ($success): ?>
    <div class="alert-strip alert-ok">
        <i class="bi bi-check-circle-fill"></i>
        <span><?= htmlspecialchars($success) ?></span>
    </div>
    <?php elseif ($error): ?>
    <div class="alert-strip alert-err">
        <i class="bi bi-exclamation-triangle-fill"></i>
        <span><?= htmlspecialchars($error) ?></span>
    </div>
    <?php elseif (isset($_GET['status']) && $_GET['status'] === 'already_submitted'): ?>
    <div class="alert-strip alert-warn">
        <i class="bi bi-exclamation-circle-fill"></i>
        <span>This request was already submitted. Please do not refresh or go back after submitting.</span>
    </div>
    <?php endif; ?>

    <!-- Stats -->
    <div class="stats-row">
        <div class="stat-card">
            <div class="stat-icon-box si-amber"><i class="bi bi-hourglass-split"></i></div>
            <div class="stat-info"><div class="stat-label">Pending</div><div class="stat-value"><?= $statPending ?></div></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon-box si-green"><i class="bi bi-check-circle-fill"></i></div>
            <div class="stat-info"><div class="stat-label">Approved</div><div class="stat-value"><?= $statApproved ?></div></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon-box si-red"><i class="bi bi-x-circle-fill"></i></div>
            <div class="stat-info"><div class="stat-label">Rejected</div><div class="stat-value"><?= $statRejected ?></div></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon-box si-blue"><i class="bi bi-arrow-repeat"></i></div>
            <div class="stat-info"><div class="stat-label">Returned</div><div class="stat-value"><?= $statReturned ?></div></div>
        </div>
    </div>

    <!-- Tabs -->
    <div class="tab-nav">
        <button class="tab-btn <?= $activeTab === 'new' ? 'active' : '' ?>" data-tab="new-request">
            <i class="bi bi-plus-circle-fill"></i> New Request
        </button>
        <button class="tab-btn <?= $activeTab === 'all' ? 'active' : '' ?>" data-tab="all-requests">
            <i class="bi bi-list-ul"></i> All Requests
            <span class="tab-count-pill"><?= count($requests) ?></span>
        </button>
    </div>

    <!-- ════ NEW REQUEST ════ -->
    <div class="tab-panel <?= $activeTab === 'new' ? 'active' : '' ?>" id="tab-new-request">
        <div class="card-shell">
            <div class="card-head">
                <h6><i class="bi bi-file-earmark-plus"></i> Submit a Return or Damage Request</h6>
            </div>
            <div class="card-body-pad">
                <form method="post" enctype="multipart/form-data" id="returnForm">

                    <!-- One-time anti-duplicate token -->
                    <input type="hidden" name="submit_token" value="<?= htmlspecialchars($submitToken) ?>">

                    <div class="form-section-label">
                        <i class="bi bi-box-seam"></i> Item Details
                    </div>

                    <div class="row g-4 mb-1">
                        <div class="col-md-7">
                            <div class="field-group">
                                <div class="field-label">
                                    Select Item <span class="req">*</span>
                                </div>
                                <select name="inventory_id" id="inventory_id" class="form-sel" required>
                                    <option value="">— Choose an inventory item —</option>
                                    <?php foreach ($items as $item): ?>
                                    <option value="<?= $item['id'] ?>"
                                            data-available="<?= $item['quantity'] ?>">
                                        <?= htmlspecialchars($item['item_name']) ?>
                                        (<?= $item['quantity'] ?> available)
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                                <div id="stock-indicator" style="display:none;">
                                    <span class="stock-bar">
                                        <i class="bi bi-info-circle"></i>
                                        <span id="stock-text">Available: 0</span>
                                    </span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-5">
                            <div class="field-group">
                                <div class="field-label">
                                    Quantity to Return <span class="req">*</span>
                                </div>
                                <input type="number" name="quantity" id="quantity"
                                       class="form-ctrl" min="1" required placeholder="Enter quantity">
                                <div class="field-hint" id="qty-hint">
                                    <i class="bi bi-info-circle"></i>
                                    Select an item to see available stock
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="form-section-label" style="margin-top:8px;">
                        <i class="bi bi-card-text"></i> Return Information
                    </div>

                    <div class="field-group">
                        <div class="field-label">Reason for Return <span class="req">*</span></div>
                        <textarea name="reason" id="reason" class="form-ctrl"
                                  placeholder="Describe why this item is being returned — e.g., damaged packaging, defective unit, expired product, excess stock…"
                                  required></textarea>
                    </div>

                    <div class="field-group">
                        <div class="field-label">
                            Photo Evidence
                            <span class="optional-tag">Optional</span>
                        </div>
                        <div class="upload-zone" id="uploadZone">
                            <input type="file" name="photo" id="photo" accept="image/*">
                            <div class="upload-icon"><i class="bi bi-cloud-arrow-up"></i></div>
                            <div class="upload-title">Click to upload or drag &amp; drop</div>
                            <div class="upload-sub">PNG, JPG, JPEG · Max 5 MB · Not required</div>
                            <div class="upload-file-name" id="fileNameDisplay">
                                <i class="bi bi-check-circle-fill"></i>
                                <span id="fileNameText"></span>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex align-items-center gap-3 pt-2">
                        <button type="submit" class="btn-submit-req" id="submitBtn">
                            <i class="bi bi-send-fill"></i> Submit Return Request
                        </button>
                        <span style="font-size:12.5px;color:var(--text-muted);font-weight:600;">
                            <i class="bi bi-shield-check" style="color:var(--accent);"></i>
                            Request will be reviewed by the admin
                        </span>
                    </div>

                </form>
            </div>
        </div>
    </div>

    <!-- ════ ALL REQUESTS ════ -->
    <div class="tab-panel <?= $activeTab === 'all' ? 'active' : '' ?>" id="tab-all-requests">
        <div class="card-shell">
            <div class="card-head">
                <h6><i class="bi bi-list-ul"></i> All Return Requests</h6>
                <span class="rec-badge">
                    <?= count($requests) ?> record<?= count($requests) !== 1 ? 's' : '' ?>
                </span>
            </div>
            <div style="overflow-x:auto;">
                <table class="requests-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Item</th>
                            <th>Requested By</th>
                            <th>Qty</th>
                            <th>Reason</th>
                            <th>Photo</th>
                            <th>Status</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($requests)): ?>
                        <tr>
                            <td colspan="8" style="padding:0;border:none;">
                                <div class="empty-wrap">
                                    <div class="empty-circle"><i class="bi bi-inbox"></i></div>
                                    <p style="font-weight:800;font-size:15px;color:var(--text-sec);margin-bottom:4px;">No requests yet</p>
                                    <p style="color:var(--text-muted);font-size:13px;">Submit a new return or damage request to get started.</p>
                                </div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($requests as $req):
                            $s = strtolower($req['status']);
                            $map = [
                                'pending'  => ['sp-pending',  'bi-hourglass-split',       'Pending'],
                                'approved' => ['sp-approved', 'bi-check-circle-fill',      'Approved'],
                                'rejected' => ['sp-rejected', 'bi-x-circle-fill',          'Rejected'],
                                'returned' => ['sp-returned', 'bi-arrow-counterclockwise', 'Returned'],
                            ];
                            [$cls, $icon, $lbl] = $map[$s] ?? ['sp-unknown', 'bi-question-circle', 'Unknown'];
                        ?>
                        <tr>
                            <td><span class="id-mono">#<?= str_pad($req['id'], 4, '0', STR_PAD_LEFT) ?></span></td>
                            <td><span class="item-strong"><?= htmlspecialchars($req['item_name']) ?></span></td>
                            <td>
                                <div class="user-cell">
                                    <div class="user-avatar"><?= strtoupper(substr($req['username'], 0, 1)) ?></div>
                                    <span style="font-weight:700;"><?= htmlspecialchars($req['username']) ?></span>
                                </div>
                            </td>
                            <td><span class="qty-tag"><?= $req['quantity'] ?></span></td>
                            <td>
                                <div class="reason-clip" title="<?= htmlspecialchars($req['reason']) ?>">
                                    <?= htmlspecialchars($req['reason']) ?>
                                </div>
                            </td>
                            <td>
                                <?php if (!empty($req['photo'])): ?>
                                    <img src="<?= htmlspecialchars($req['photo']) ?>"
                                         alt="Photo" class="thumb-img"
                                         onclick="openLightbox(this.src)">
                                <?php else: ?>
                                    <span class="no-photo-tag"><i class="bi bi-image"></i> None</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="status-pill <?= $cls ?>">
                                    <i class="bi <?= $icon ?>"></i> <?= $lbl ?>
                                </span>
                            </td>
                            <td>
                                <span class="date-mono">
                                    <?= isset($req['requested_at']) ? date('Y-m-d', strtotime($req['requested_at'])) : 'N/A' ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div><!-- /main-content -->

<!-- Lightbox -->
<div class="lb-overlay" id="lightbox" onclick="closeLightbox()">
    <span class="lb-close" onclick="closeLightbox()"><i class="bi bi-x"></i></span>
    <img id="lbImg" src="" alt="Photo Preview" onclick="event.stopPropagation()">
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
/* ── TABS ── */
document.querySelectorAll('.tab-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
        document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
        btn.classList.add('active');
        document.getElementById('tab-' + btn.dataset.tab).classList.add('active');
    });
});

/* ── PREVENT DOUBLE SUBMIT ── */
document.getElementById('returnForm').addEventListener('submit', function () {
    const btn = document.getElementById('submitBtn');
    btn.disabled  = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status"></span> Submitting…';
});

/* ── ITEM SELECT ── */
document.getElementById('inventory_id').addEventListener('change', function () {
    const opt       = this.options[this.selectedIndex];
    const available = parseInt(opt.dataset.available || 0);
    const qtyInput  = document.getElementById('quantity');
    const hint      = document.getElementById('qty-hint');
    const indicator = document.getElementById('stock-indicator');
    const stockText = document.getElementById('stock-text');
    qtyInput.value = '';
    qtyInput.max   = available;
    if (available > 0) {
        stockText.textContent   = `Available: ${available} unit${available === 1 ? '' : 's'}`;
        indicator.style.display = 'block';
        hint.innerHTML = `<i class="bi bi-info-circle"></i> Max returnable: <strong>${available}</strong> unit${available === 1 ? '' : 's'}`;
    } else {
        indicator.style.display = 'none';
        hint.innerHTML = '<i class="bi bi-info-circle"></i> Select an item to see available stock';
    }
});

/* ── QUANTITY clamp ── */
document.getElementById('quantity').addEventListener('input', function () {
    const max = parseInt(this.max) || 0;
    const val = parseInt(this.value) || 0;
    if (max > 0 && val > max) {
        this.value = max;
        this.style.borderColor = 'var(--danger)';
        setTimeout(() => this.style.borderColor = '', 1200);
    }
});

/* ── FILE UPLOAD feedback ── */
document.getElementById('photo').addEventListener('change', function () {
    const zone    = document.getElementById('uploadZone');
    const display = document.getElementById('fileNameDisplay');
    const nameEl  = document.getElementById('fileNameText');
    if (this.files && this.files[0]) {
        nameEl.textContent = this.files[0].name;
        display.classList.add('show');
        zone.classList.add('has-file');
    } else {
        display.classList.remove('show');
        zone.classList.remove('has-file');
    }
});

/* ── LIGHTBOX ── */
function openLightbox(src) {
    document.getElementById('lbImg').src = src;
    document.getElementById('lightbox').classList.add('open');
}
function closeLightbox() { document.getElementById('lightbox').classList.remove('open'); }
document.addEventListener('keydown', e => { if (e.key === 'Escape') closeLightbox(); });
</script>
</body>
</html>