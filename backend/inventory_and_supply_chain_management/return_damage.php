<?php
session_start();
include '../../SQL/config.php';

$user_id = $_SESSION['user_id'] ?? null;

// Fetch inventory items (quantity > 0)
$inventoryStmt = $pdo->query("SELECT * FROM inventory WHERE quantity > 0 ORDER BY item_name ASC");
$items = $inventoryStmt->fetchAll(PDO::FETCH_ASSOC);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $inventory_id = (int)$_POST['inventory_id'];
    $quantity     = (int)$_POST['quantity'];
    $reason       = trim($_POST['reason']);

    $checkStmt = $pdo->prepare("SELECT quantity FROM inventory WHERE id = ?");
    $checkStmt->execute([$inventory_id]);
    $itemData = $checkStmt->fetch(PDO::FETCH_ASSOC);

    if (!$itemData) {
        $error = "Invalid item selected.";
    } elseif ($quantity < 1) {
        $error = "Quantity must be at least 1.";
    } elseif ($quantity > $itemData['quantity']) {
        $error = "Quantity cannot exceed available stock ({$itemData['quantity']}).";
    } else {
        $photoPath = null;
        if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = 'uploads/returns/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
            $ext = pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION);
            $photoPath = $uploadDir . uniqid() . '.' . $ext;
            if (!move_uploaded_file($_FILES['photo']['tmp_name'], $photoPath)) {
                $error = "Failed to upload photo. Please check folder permissions.";
            }
        }

        if (!isset($error)) {
            $reasonLower = strtolower($reason);
            if (strpos($reasonLower, 'damage') !== false || strpos($reasonLower, 'defective') !== false || strlen($reason) > 5) {
                $status = 'Approved';
            } else {
                $status = 'Rejected';
            }

            $stmt = $pdo->prepare("
                INSERT INTO return_requests (inventory_id, requested_by, quantity, reason, photo, status, requested_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())
            ");
            $stmt->execute([$inventory_id, $user_id, $quantity, $reason, $photoPath, $status]);
            $success = "Return request submitted and automatically marked as <strong>{$status}</strong>.";
        }
    }
}

// Fetch all return requests
$requestStmt = $pdo->prepare("
    SELECT rr.id, rr.inventory_id, rr.requested_by, rr.quantity, rr.reason, rr.photo, rr.status, rr.requested_at,
           i.item_name, u.username
    FROM return_requests rr
    JOIN inventory i ON rr.inventory_id = i.id
    JOIN users u ON rr.requested_by = u.user_id
    ORDER BY rr.id DESC
");
$requestStmt->execute();
$requests = $requestStmt->fetchAll(PDO::FETCH_ASSOC);

// Stats
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
<title>Return & Damage Requests — Inventory</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;1,9..40,400&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">

<style>
:root {
    --bg:           #f0f2f7;
    --surface:      #ffffff;
    --surface-2:    #f7f8fc;
    --border:       #e4e7ef;
    --border-dark:  #d0d4e0;
    --text-primary:   #141824;
    --text-secondary: #5a6079;
    --text-muted:     #9ba3bd;
    --accent:       #2563eb;
    --accent-light: #eff4ff;
    --accent-mid:   #93b4fb;
    --success:      #16a34a;
    --success-light:#f0fdf4;
    --success-mid:  #86efac;
    --warning:      #d97706;
    --warning-light:#fffbeb;
    --warning-mid:  #fcd34d;
    --danger:       #dc2626;
    --danger-light: #fef2f2;
    --danger-mid:   #fca5a5;
    --info:         #0891b2;
    --info-light:   #ecfeff;
    --info-mid:     #a5f3fc;
    --purple:       #7c3aed;
    --purple-light: #f5f3ff;
    --purple-mid:   #c4b5fd;
    --radius-sm:    6px;
    --radius:       10px;
    --radius-lg:    16px;
    --shadow-sm:    0 1px 3px rgba(0,0,0,.06), 0 1px 2px rgba(0,0,0,.04);
    --shadow:       0 4px 16px rgba(0,0,0,.07), 0 1px 4px rgba(0,0,0,.04);
    --shadow-lg:    0 12px 40px rgba(0,0,0,.10), 0 4px 12px rgba(0,0,0,.06);
}

*, *::before, *::after { box-sizing: border-box; }

body {
    background: var(--bg);
    font-family: 'DM Sans', sans-serif;
    color: var(--text-primary);
    font-size: 14px;
    line-height: 1.6;
    -webkit-font-smoothing: antialiased;
}

/* ── SIDEBAR ── */
.main-sidebar { position: fixed; left: 0; top: 0; bottom: 0; width: 260px; z-index: 100; }

/* ── LAYOUT ── */
.main-content {
    margin-left: 260px;
    padding: 36px 36px 60px;
    min-height: 100vh;
}

/* ── PAGE HEADER ── */
.page-header {
    display: flex;
    align-items: flex-end;
    justify-content: space-between;
    margin-bottom: 28px;
    padding-bottom: 20px;
    border-bottom: 1px solid var(--border);
}
.page-eyebrow {
    font-size: 11px;
    font-weight: 600;
    letter-spacing: .08em;
    text-transform: uppercase;
    color: var(--danger);
    margin-bottom: 4px;
}
.page-header h1 {
    font-size: 24px;
    font-weight: 700;
    margin: 0;
    display: flex;
    align-items: center;
    gap: 10px;
}
.page-icon {
    width: 38px; height: 38px;
    background: var(--danger-light);
    border-radius: var(--radius);
    display: flex; align-items: center; justify-content: center;
    color: var(--danger);
    font-size: 18px;
    flex-shrink: 0;
}

/* ── STATS BAR ── */
.stats-bar {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 16px;
    margin-bottom: 24px;
}
.stat-card {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    padding: 15px 18px;
    display: flex;
    align-items: center;
    gap: 13px;
    box-shadow: var(--shadow-sm);
    transition: box-shadow .2s, transform .2s;
}
.stat-card:hover { box-shadow: var(--shadow); transform: translateY(-1px); }
.stat-icon {
    width: 40px; height: 40px;
    border-radius: var(--radius-sm);
    display: flex; align-items: center; justify-content: center;
    font-size: 18px;
    flex-shrink: 0;
}
.si-yellow { background: var(--warning-light); color: var(--warning); }
.si-green  { background: var(--success-light); color: var(--success); }
.si-red    { background: var(--danger-light);  color: var(--danger);  }
.si-blue   { background: var(--info-light);    color: var(--info);    }
.stat-label { font-size: 11.5px; color: var(--text-muted); font-weight: 500; letter-spacing: .03em; }
.stat-value { font-size: 22px; font-weight: 700; line-height: 1.2; }

/* ── TABS ── */
.page-tabs {
    display: flex;
    gap: 4px;
    margin-bottom: 22px;
    border-bottom: 2px solid var(--border);
    padding-bottom: 0;
}
.page-tab {
    padding: 9px 20px;
    font-size: 13.5px;
    font-weight: 500;
    color: var(--text-muted);
    background: transparent;
    border: none;
    border-bottom: 2px solid transparent;
    margin-bottom: -2px;
    cursor: pointer;
    display: flex; align-items: center; gap: 7px;
    transition: color .15s, border-color .15s;
    font-family: 'DM Sans', sans-serif;
    text-decoration: none;
}
.page-tab:hover { color: var(--text-primary); }
.page-tab.active {
    color: var(--danger);
    border-bottom-color: var(--danger);
    font-weight: 600;
}
.tab-badge {
    background: var(--surface-2);
    border: 1px solid var(--border);
    border-radius: 20px;
    font-size: 11px;
    padding: 1px 7px;
    font-weight: 600;
    color: var(--text-muted);
}
.page-tab.active .tab-badge {
    background: var(--danger-light);
    border-color: var(--danger-mid);
    color: var(--danger);
}

/* ── CARD ── */
.content-card {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
    box-shadow: var(--shadow);
    overflow: hidden;
}
.content-card-header {
    padding: 18px 24px;
    border-bottom: 1px solid var(--border);
    display: flex; align-items: center; justify-content: space-between;
}
.content-card-header h6 {
    font-size: 14px;
    font-weight: 600;
    color: var(--text-primary);
    margin: 0;
    display: flex; align-items: center; gap: 8px;
}
.content-card-body { padding: 28px; }

/* ── FORM ── */
.form-section-title {
    font-size: 11px;
    font-weight: 600;
    letter-spacing: .08em;
    text-transform: uppercase;
    color: var(--text-muted);
    margin-bottom: 16px;
    padding-bottom: 8px;
    border-bottom: 1px solid var(--border);
}
.form-group { margin-bottom: 20px; }
.form-label {
    display: block;
    font-size: 13px;
    font-weight: 600;
    color: var(--text-secondary);
    margin-bottom: 6px;
}
.form-label span.req { color: var(--danger); margin-left: 2px; }
.form-control, .form-select {
    font-family: 'DM Sans', sans-serif;
    font-size: 13.5px;
    border: 1.5px solid var(--border);
    border-radius: var(--radius-sm);
    background: var(--surface-2);
    color: var(--text-primary);
    padding: 9px 13px;
    width: 100%;
    transition: border-color .2s, box-shadow .2s, background .2s;
}
.form-control:focus, .form-select:focus {
    border-color: var(--danger);
    box-shadow: 0 0 0 3px rgba(220,38,38,.1);
    background: var(--surface);
    outline: none;
}
textarea.form-control { resize: vertical; min-height: 100px; }
.form-hint {
    font-size: 12px;
    color: var(--text-muted);
    margin-top: 5px;
    display: flex; align-items: center; gap: 4px;
}

/* File upload */
.file-upload-zone {
    border: 2px dashed var(--border);
    border-radius: var(--radius);
    padding: 28px 20px;
    text-align: center;
    cursor: pointer;
    transition: border-color .2s, background .2s;
    position: relative;
}
.file-upload-zone:hover { border-color: var(--danger); background: var(--danger-light); }
.file-upload-zone input[type="file"] {
    position: absolute; inset: 0; opacity: 0; cursor: pointer; width: 100%; height: 100%;
}
.file-upload-icon { font-size: 28px; color: var(--text-muted); margin-bottom: 8px; }
.file-upload-label { font-size: 13.5px; color: var(--text-secondary); font-weight: 500; }
.file-upload-sub { font-size: 12px; color: var(--text-muted); margin-top: 3px; }
#file-name-display {
    font-size: 12px; color: var(--success); margin-top: 8px; font-weight: 500;
    display: none;
}

/* Submit button */
.btn-submit {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 11px 26px;
    background: var(--danger);
    color: #fff;
    font-size: 14px;
    font-weight: 600;
    font-family: 'DM Sans', sans-serif;
    border: none;
    border-radius: var(--radius-sm);
    cursor: pointer;
    transition: all .15s;
    box-shadow: 0 2px 8px rgba(220,38,38,.25);
}
.btn-submit:hover { background: #b91c1c; transform: translateY(-1px); box-shadow: 0 4px 14px rgba(220,38,38,.35); }

/* Alerts */
.page-alert {
    padding: 13px 18px;
    border-radius: var(--radius);
    font-size: 13.5px;
    margin-bottom: 20px;
    display: flex; align-items: flex-start; gap: 10px;
    border: 1px solid;
}
.page-alert i { flex-shrink: 0; font-size: 15px; margin-top: 1px; }
.alert-success-custom { background: var(--success-light); border-color: var(--success-mid); color: var(--success); }
.alert-danger-custom  { background: var(--danger-light);  border-color: var(--danger-mid);  color: var(--danger); }

/* ── TABLE ── */
.data-table { width: 100%; border-collapse: collapse; }
.data-table thead tr { background: var(--surface-2); }
.data-table thead th {
    padding: 12px 16px;
    font-size: 11px;
    font-weight: 600;
    letter-spacing: .07em;
    text-transform: uppercase;
    color: var(--text-muted);
    border-bottom: 1px solid var(--border);
    white-space: nowrap;
    text-align: left;
}
.data-table tbody tr {
    border-bottom: 1px solid var(--border);
    transition: background .15s;
}
.data-table tbody tr:last-child { border-bottom: none; }
.data-table tbody tr:hover { background: #f7f9ff; }
.data-table td {
    padding: 13px 16px;
    font-size: 13.5px;
    color: var(--text-primary);
    vertical-align: middle;
}

/* ID badge */
.id-badge {
    font-family: 'DM Mono', monospace;
    font-size: 12px;
    font-weight: 500;
    color: var(--text-secondary);
    background: var(--surface-2);
    border: 1px solid var(--border);
    border-radius: var(--radius-sm);
    padding: 3px 8px;
    display: inline-block;
}
.item-name { font-weight: 600; }
.username-cell {
    display: flex; align-items: center; gap: 7px;
}
.username-avatar {
    width: 26px; height: 26px;
    border-radius: 50%;
    background: var(--accent-light);
    color: var(--accent);
    font-size: 11px;
    font-weight: 700;
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0;
}
.reason-cell {
    max-width: 200px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    color: var(--text-secondary);
    font-size: 13px;
}
.photo-thumb {
    width: 52px; height: 52px;
    object-fit: cover;
    border-radius: var(--radius-sm);
    border: 1px solid var(--border);
    cursor: pointer;
    transition: transform .15s, box-shadow .15s;
}
.photo-thumb:hover { transform: scale(1.08); box-shadow: var(--shadow); }
.no-photo {
    color: var(--text-muted);
    font-size: 12px;
    display: flex; align-items: center; gap: 4px;
}

/* Status badges */
.status-badge {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 4px 11px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
    white-space: nowrap;
}
.badge-pending  { background: var(--warning-light); color: var(--warning); }
.badge-approved { background: var(--success-light); color: var(--success); }
.badge-rejected { background: var(--danger-light);  color: var(--danger);  }
.badge-returned { background: var(--info-light);    color: var(--info);    }
.badge-unknown  { background: var(--surface-2);     color: var(--text-muted); }

.date-cell { font-size: 12.5px; color: var(--text-secondary); white-space: nowrap; font-family: 'DM Mono', monospace; }

/* Empty state */
.empty-state {
    text-align: center;
    padding: 60px 24px;
    color: var(--text-muted);
}
.empty-state .empty-icon {
    width: 60px; height: 60px;
    background: var(--surface-2);
    border-radius: 50%;
    display: inline-flex; align-items: center; justify-content: center;
    font-size: 26px;
    margin-bottom: 14px;
    border: 1px solid var(--border);
}

/* Photo lightbox */
.lightbox-overlay {
    display: none;
    position: fixed; inset: 0;
    background: rgba(0,0,0,.8);
    z-index: 9999;
    align-items: center; justify-content: center;
}
.lightbox-overlay.show { display: flex; }
.lightbox-overlay img {
    max-width: 90vw; max-height: 85vh;
    border-radius: var(--radius);
    box-shadow: var(--shadow-lg);
}
.lightbox-close {
    position: absolute; top: 20px; right: 24px;
    font-size: 30px; color: #fff; cursor: pointer;
    line-height: 1;
    transition: opacity .15s;
}
.lightbox-close:hover { opacity: .7; }

/* Row animation */
@keyframes fadeSlideIn {
    from { opacity: 0; transform: translateY(5px); }
    to   { opacity: 1; transform: translateY(0); }
}
.data-table tbody tr { animation: fadeSlideIn .22s ease both; }
<?php foreach(range(1,30) as $i): ?>
.data-table tbody tr:nth-child(<?= $i ?>) { animation-delay: <?= ($i-1)*0.025 ?>s; }
<?php endforeach; ?>

/* Tab content panels */
.tab-panel { display: none; }
.tab-panel.active { display: block; }

/* Qty badge */
.qty-pill {
    font-family: 'DM Mono', monospace;
    font-size: 13px;
    font-weight: 600;
    background: var(--surface-2);
    border: 1px solid var(--border);
    border-radius: var(--radius-sm);
    padding: 2px 9px;
    color: var(--text-primary);
}

/* record count */
.record-count {
    font-size: 12px;
    background: var(--danger-light);
    color: var(--danger);
    border-radius: 20px;
    padding: 2px 10px;
    font-weight: 600;
}
</style>
</head>
<body>

<div class="main-sidebar"><?php include 'inventory_sidebar.php'; ?></div>

<div class="main-content">

    <!-- Page Header -->
    <div class="page-header">
        <div>
            <div class="page-eyebrow">Inventory Management</div>
            <h1>
                <span class="page-icon"><i class="bi bi-arrow-return-left"></i></span>
                Return &amp; Damage Requests
            </h1>
        </div>
        <div style="text-align:right;">
            <span style="font-size:12px; color:var(--text-muted);">Today</span>
            <strong style="display:block; font-size:14px;"><?= date('F d, Y') ?></strong>
        </div>
    </div>

    <!-- Alerts -->
    <?php if (isset($success)): ?>
    <div class="page-alert alert-success-custom">
        <i class="bi bi-check-circle-fill"></i>
        <span><?= $success ?></span>
    </div>
    <?php elseif (isset($error)): ?>
    <div class="page-alert alert-danger-custom">
        <i class="bi bi-exclamation-triangle-fill"></i>
        <span><?= htmlspecialchars($error) ?></span>
    </div>
    <?php endif; ?>

    <!-- Stats Bar -->
    <div class="stats-bar">
        <div class="stat-card">
            <div class="stat-icon si-yellow"><i class="bi bi-hourglass-split"></i></div>
            <div>
                <div class="stat-label">Pending</div>
                <div class="stat-value"><?= $statPending ?></div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon si-green"><i class="bi bi-check-circle-fill"></i></div>
            <div>
                <div class="stat-label">Approved</div>
                <div class="stat-value"><?= $statApproved ?></div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon si-red"><i class="bi bi-x-circle-fill"></i></div>
            <div>
                <div class="stat-label">Rejected</div>
                <div class="stat-value"><?= $statRejected ?></div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon si-blue"><i class="bi bi-arrow-repeat"></i></div>
            <div>
                <div class="stat-label">Returned</div>
                <div class="stat-value"><?= $statReturned ?></div>
            </div>
        </div>
    </div>

    <!-- Tabs -->
    <div class="page-tabs">
        <button class="page-tab active" data-tab="new-request">
            <i class="bi bi-plus-circle"></i> New Request
        </button>
        <button class="page-tab" data-tab="all-requests">
            <i class="bi bi-list-ul"></i> All Requests
            <span class="tab-badge"><?= count($requests) ?></span>
        </button>
    </div>

    <!-- ═══ TAB: NEW REQUEST ═══ -->
    <div class="tab-panel active" id="tab-new-request">
        <div class="content-card">
            <div class="content-card-header">
                <h6><i class="bi bi-file-earmark-plus" style="color:var(--danger);"></i> Submit a Return or Damage Request</h6>
            </div>
            <div class="content-card-body">
                <form method="post" enctype="multipart/form-data">

                    <div class="form-section-title">Item Details</div>

                    <div class="row g-4 mb-2">
                        <div class="col-md-7">
                            <div class="form-group">
                                <label class="form-label" for="inventory_id">Select Item <span class="req">*</span></label>
                                <select name="inventory_id" id="inventory_id" class="form-select" required>
                                    <option value="">— Choose an inventory item —</option>
                                    <?php foreach ($items as $item): ?>
                                        <option value="<?= $item['id'] ?>" data-available="<?= $item['quantity'] ?>">
                                            <?= htmlspecialchars($item['item_name']) ?> &nbsp;(<?= $item['quantity'] ?> available)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-5">
                            <div class="form-group">
                                <label class="form-label" for="quantity">Quantity to Return <span class="req">*</span></label>
                                <input type="number" name="quantity" id="quantity"
                                       class="form-control" min="1" required
                                       placeholder="0">
                                <div class="form-hint" id="availableQty">
                                    <i class="bi bi-info-circle"></i>
                                    <span>Select an item first</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="form-section-title" style="margin-top:8px;">Return Information</div>

                    <div class="form-group">
                        <label class="form-label" for="reason">Reason for Return <span class="req">*</span></label>
                        <textarea name="reason" id="reason" class="form-control"
                                  placeholder="Describe why this item is being returned (e.g., damaged, defective, excess stock)…" required></textarea>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Photo Evidence <span class="req">*</span></label>
                        <div class="file-upload-zone" id="dropZone">
                            <input type="file" name="photo" id="photo" accept="image/*">
                            <div class="file-upload-icon"><i class="bi bi-cloud-arrow-up"></i></div>
                            <div class="file-upload-label">Click to upload or drag &amp; drop</div>
                            <div class="file-upload-sub">PNG, JPG, JPEG · Max 5MB</div>
                            <div id="file-name-display"><i class="bi bi-check-circle-fill"></i> <span id="file-name-text"></span></div>
                        </div>
                    </div>

                    <div style="padding-top:8px;">
                        <button type="submit" class="btn-submit">
                            <i class="bi bi-send-fill"></i> Submit Return Request
                        </button>
                    </div>

                </form>
            </div>
        </div>
    </div>

    <!-- ═══ TAB: ALL REQUESTS ═══ -->
    <div class="tab-panel" id="tab-all-requests">
        <div class="content-card">
            <div class="content-card-header">
                <h6><i class="bi bi-list-ul" style="color:var(--danger);"></i> All Return Requests</h6>
                <span class="record-count"><?= count($requests) ?> record<?= count($requests) !== 1 ? 's' : '' ?></span>
            </div>
            <div style="overflow-x:auto;">
            <table class="data-table">
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
                <td colspan="8" style="padding:0; border:none;">
                    <div class="empty-state">
                        <div class="empty-icon"><i class="bi bi-inbox"></i></div>
                        <p><strong style="display:block; font-size:15px; color:var(--text-secondary); margin-bottom:4px;">No requests yet</strong>
                        Submit a new return or damage request to get started.</p>
                    </div>
                </td>
            </tr>
            <?php else: ?>
            <?php foreach ($requests as $req): ?>
            <tr>
                <td><span class="id-badge">#<?= str_pad($req['id'], 4, '0', STR_PAD_LEFT) ?></span></td>
                <td>
                    <span class="item-name"><?= htmlspecialchars($req['item_name']) ?></span>
                </td>
                <td>
                    <div class="username-cell">
                        <div class="username-avatar"><?= strtoupper(substr($req['username'], 0, 1)) ?></div>
                        <span><?= htmlspecialchars($req['username']) ?></span>
                    </div>
                </td>
                <td><span class="qty-pill"><?= $req['quantity'] ?></span></td>
                <td>
                    <div class="reason-cell" title="<?= htmlspecialchars($req['reason']) ?>">
                        <?= htmlspecialchars($req['reason']) ?>
                    </div>
                </td>
                <td>
                    <?php if ($req['photo']): ?>
                        <img src="<?= htmlspecialchars($req['photo']) ?>" alt="Photo"
                             class="photo-thumb" onclick="openLightbox(this.src)">
                    <?php else: ?>
                        <span class="no-photo"><i class="bi bi-image-alt"></i> N/A</span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php
                    $s = strtolower($req['status']);
                    $badgeMap = [
                        'pending'  => ['badge-pending',  'bi-hourglass-split',    'Pending'],
                        'approved' => ['badge-approved', 'bi-check-circle-fill',  'Approved'],
                        'rejected' => ['badge-rejected', 'bi-x-circle-fill',      'Rejected'],
                        'returned' => ['badge-returned', 'bi-arrow-counterclockwise','Returned'],
                    ];
                    [$cls, $icon, $label] = $badgeMap[$s] ?? ['badge-unknown','bi-question-circle','Unknown'];
                    echo "<span class=\"status-badge {$cls}\"><i class=\"bi {$icon}\"></i> {$label}</span>";
                    ?>
                </td>
                <td>
                    <span class="date-cell">
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

<!-- Photo Lightbox -->
<div class="lightbox-overlay" id="lightbox" onclick="closeLightbox()">
    <span class="lightbox-close" onclick="closeLightbox()">&times;</span>
    <img id="lightboxImg" src="" alt="Photo Preview">
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
/* ── TABS ── */
document.querySelectorAll('.page-tab').forEach(tab => {
    tab.addEventListener('click', () => {
        document.querySelectorAll('.page-tab').forEach(t => t.classList.remove('active'));
        document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
        tab.classList.add('active');
        document.getElementById('tab-' + tab.dataset.tab).classList.add('active');
    });
});

/* Open requests tab if there was a submission (auto-navigate to all-requests to see result) */
<?php if (isset($success)): ?>
document.querySelector('[data-tab="all-requests"]').click();
<?php endif; ?>

/* ── ITEM SELECT ── */
document.getElementById('inventory_id').addEventListener('change', function () {
    const available = this.options[this.selectedIndex].dataset.available || 0;
    const qtyInput  = document.getElementById('quantity');
    const hint      = document.getElementById('availableQty');
    qtyInput.value  = '';
    qtyInput.max    = available;
    hint.innerHTML  = `<i class="bi bi-info-circle"></i> <span>Available stock: <strong>${available}</strong> unit${available == 1 ? '' : 's'}</span>`;
});

document.getElementById('quantity').addEventListener('input', function () {
    const max = parseInt(this.max) || 0;
    const val = parseInt(this.value) || 0;
    if (val > max) {
        this.value = max;
        this.style.borderColor = 'var(--danger)';
        setTimeout(() => this.style.borderColor = '', 1000);
    }
});

/* ── FILE UPLOAD ── */
document.getElementById('photo').addEventListener('change', function () {
    const display = document.getElementById('file-name-display');
    const nameEl  = document.getElementById('file-name-text');
    if (this.files && this.files[0]) {
        nameEl.textContent = this.files[0].name;
        display.style.display = 'block';
    }
});

/* ── LIGHTBOX ── */
function openLightbox(src) {
    document.getElementById('lightboxImg').src = src;
    document.getElementById('lightbox').classList.add('show');
}
function closeLightbox() {
    document.getElementById('lightbox').classList.remove('show');
}
document.addEventListener('keydown', e => { if (e.key === 'Escape') closeLightbox(); });
</script>
</body>
</html>