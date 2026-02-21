<?php
session_start();
include '../../SQL/config.php';

error_reporting(E_ALL);
ini_set('display_errors', 1);

// 🔐 Ensure login
if (!isset($_SESSION['user_id'])) {
    if (isset($_GET['ajax']) && $_GET['ajax'] === 'items') {
        header('Content-Type: application/json');
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized']);
        exit();
    }
    header("Location: ../../login.php");
    exit();
}

$user_id = (int)$_SESSION['user_id'];

/* =====================================================
   🔁 AJAX — Fetch Request Items
=====================================================*/
if (isset($_GET['ajax']) && $_GET['ajax'] === 'items') {
    header('Content-Type: application/json');

    $request_id = (int)($_GET['id'] ?? 0);
    if ($request_id <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid request ID']);
        exit();
    }

    try {
        $check = $pdo->prepare("SELECT id FROM department_request WHERE id = ? AND user_id = ? LIMIT 1");
        $check->execute([$request_id, $user_id]);
        if (!$check->fetch()) {
            http_response_code(403);
            echo json_encode(['error' => 'Access denied — this request does not belong to you.']);
            exit();
        }
        $stmt = $pdo->prepare("
            SELECT item_name, description, unit, quantity, pcs_per_box, total_pcs
            FROM department_request_items
            WHERE request_id = ?
            ORDER BY id ASC
        ");
        $stmt->execute([$request_id]);
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Server error: ' . $e->getMessage()]);
    }
    exit();
}

/* =====================================================
   👤 Fetch user info
=====================================================*/
$user_stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ? LIMIT 1");
$user_stmt->execute([$user_id]);
$user = $user_stmt->fetch(PDO::FETCH_ASSOC);
if (!$user) die("User not found.");

$full_name     = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
$department    = $user['department']    ?? 'Unknown Department';
$department_id = $user['department_id'] ?? 0;
$request_date  = date('F d, Y');

/* =====================================================
   📤 HANDLE FORM SUBMISSION
=====================================================*/
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo->beginTransaction();
        $items       = $_POST['items'] ?? [];
        $valid_items = array_filter($items, fn($i) => !empty(trim($i['name'] ?? '')));
        if (count($valid_items) === 0) throw new Exception("Please add at least one item before submitting.");
        $stmt = $pdo->prepare("
            INSERT INTO department_request (user_id, department, department_id, month, total_items, status)
            VALUES (?, ?, ?, ?, ?, 'Pending')
        ");
        $stmt->execute([$user_id, $department, $department_id, date('Y-m-d'), count($valid_items)]);
        $request_id = $pdo->lastInsertId();
        $item_stmt = $pdo->prepare("
            INSERT INTO department_request_items (request_id, item_name, description, unit, quantity, pcs_per_box, total_pcs)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        foreach ($valid_items as $item) {
            $quantity  = (int)($item['quantity']    ?? 0);
            $pcs_box   = (int)($item['pcs_per_box'] ?? 1);
            $total_pcs = (int)($item['total_pcs']   ?? 0);
            $item_stmt->execute([$request_id, $item['name'], $item['description'] ?? '', $item['unit'] ?? '', $quantity, $pcs_box, $total_pcs]);
        }
        $pdo->commit();
        $success = "Purchase request successfully submitted!";
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = $e->getMessage();
    }
}

/* =====================================================
   🔎 FETCH USER REQUESTS
=====================================================*/
$request_stmt = $pdo->prepare("SELECT * FROM department_request WHERE user_id = ? ORDER BY created_at DESC");
$request_stmt->execute([$user_id]);
$my_requests = $request_stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Purchase Request — HMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        :root {
            --sidebar-w:    250px;
            --sidebar-w-md: 200px;
            --sidebar-w-xs: 0px;
            --accent:    #00acc1;
            --navy:      #0b1d3a;
            --surface:   #F5F6F7;
            --card:      #ffffff;
            --border:    #e0e6f0;
            --text:      #374151;
            --muted:     #6e768e;
            --radius:    12px;
            --shadow:    0 2px 16px rgba(11,29,58,.08);
            --shadow-md: 0 4px 24px rgba(11,29,58,.12);
        }

        *, *::before, *::after { box-sizing: border-box; }
        html { scroll-behavior: smooth; }

        body {
            font-family: "Nunito", "Segoe UI", Arial, sans-serif;
            background: var(--surface);
            color: var(--text);
            margin: 0;
            margin-left: var(--sidebar-w);
            transition: margin-left 0.3s ease-in-out;
            min-height: 100vh;
            overflow-x: hidden;
        }

        .page-wrap {
            padding: 70px 1.75rem 3rem 1.75rem;
            max-width: 1400px;
            margin: 0 auto;
        }

        /* ── PAGE HEADER ── */
        .page-header {
            display: flex;
            align-items: center;
            gap: .75rem;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
        }

        /* ── BACK BUTTON ── */
        .btn-back {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 7px 16px;
            background: var(--card);
            color: var(--navy);
            border: 1.5px solid var(--border);
            border-radius: 9px;
            font-size: .83rem;
            font-weight: 700;
            font-family: inherit;
            cursor: pointer;
            text-decoration: none;
            transition: background .18s, color .18s, border-color .18s, transform .18s;
            box-shadow: var(--shadow);
            flex-shrink: 0;
            line-height: 1;
        }
        .btn-back:hover {
            background: var(--navy);
            color: #fff;
            border-color: var(--navy);
            transform: translateX(-2px);
        }
        .btn-back i { font-size: 1rem; }

        .page-header-icon {
            width: 46px; height: 46px;
            background: linear-gradient(135deg, var(--accent), #0088a3);
            border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            color: #fff;
            font-size: 1.3rem;
            flex-shrink: 0;
            box-shadow: 0 4px 12px rgba(0,172,193,.35);
        }
        .page-header h2 {
            font-size: clamp(1.15rem, 3vw, 1.55rem);
            font-weight: 800;
            color: var(--navy);
            margin: 0;
        }
        .page-header .date-chip {
            margin-left: auto;
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 999px;
            padding: 5px 14px;
            font-size: .8rem;
            color: var(--muted);
            font-weight: 600;
            white-space: nowrap;
            box-shadow: var(--shadow);
        }

        /* ── ALERTS ── */
        .alert { border-radius: var(--radius); font-size: .9rem; border: none; padding: .85rem 1.2rem; }
        .alert-success { background: #e6faf5; color: #0d6e52; border-left: 4px solid #00c9a7; }
        .alert-danger  { background: #fff0f3; color: #7a0020; border-left: 4px solid #ff4d6d; }

        /* ── TABS ── */
        .nav-tabs {
            border-bottom: 2px solid var(--border);
            gap: 4px; flex-wrap: nowrap;
            overflow-x: auto; -webkit-overflow-scrolling: touch; scrollbar-width: none;
        }
        .nav-tabs::-webkit-scrollbar { display: none; }
        .nav-tabs .nav-link {
            border: none; border-bottom: 3px solid transparent; border-radius: 0;
            color: var(--muted); font-weight: 700; font-size: .88rem;
            padding: .7rem 1.1rem; white-space: nowrap;
            transition: color .2s, border-color .2s; margin-bottom: -2px;
        }
        .nav-tabs .nav-link:hover { color: var(--navy); }
        .nav-tabs .nav-link.active { color: var(--accent); border-bottom-color: var(--accent); background: transparent; }

        /* ── CARD ── */
        .pr-card {
            background: var(--card); border: 1px solid var(--border);
            border-radius: var(--radius); box-shadow: var(--shadow);
            padding: 1.75rem; margin-top: 1rem;
        }

        /* ── INFO BOX ── */
        .info-box {
            display: flex; flex-wrap: wrap; gap: 1rem;
            background: #eef6ff; border: 1px solid #c5d8ff;
            border-radius: 10px; padding: .9rem 1.2rem;
            font-size: .88rem; color: #1a4d8c; margin-bottom: 1.5rem;
        }
        .info-box-item { display: flex; align-items: center; gap: .4rem; }
        .info-box-item strong { font-weight: 700; }

        /* ── FORM CONTROLS ── */
        .form-control, .form-select {
            border: 1.5px solid var(--border); border-radius: 8px;
            font-size: .85rem; color: var(--text); background: #fafcff;
            min-height: 38px; transition: border-color .2s, box-shadow .2s;
        }
        .form-control:focus, .form-select:focus {
            border-color: var(--accent); box-shadow: 0 0 0 3px rgba(0,172,193,.12);
            background: #fff; outline: none;
        }
        input[readonly].form-control { background: #f0f4fb; color: var(--muted); }

        /* ── ITEMS TABLE ── */
        .items-table-wrap { overflow-x: auto; -webkit-overflow-scrolling: touch; border-radius: 10px; border: 1px solid var(--border); }
        .items-table { width: 100%; min-width: 680px; border-collapse: collapse; font-size: .85rem; }
        .items-table thead th {
            background: var(--navy); color: rgba(255,255,255,.8);
            font-size: .7rem; font-weight: 700; text-transform: uppercase; letter-spacing: .6px;
            padding: .75rem 1rem; white-space: nowrap; text-align: center;
        }
        .items-table thead th:first-child { border-radius: 10px 0 0 0; }
        .items-table thead th:last-child  { border-radius: 0 10px 0 0; }
        .items-table tbody tr { border-bottom: 1px solid var(--border); transition: background .15s; }
        .items-table tbody tr:hover { background: #f7faff; }
        .items-table tbody tr:last-child { border-bottom: none; }
        .items-table tbody td { padding: .6rem .8rem; vertical-align: middle; text-align: center; }
        .items-table tbody td:first-child,
        .items-table tbody td:nth-child(2) { text-align: left; }

        /* ── BUTTONS ── */
        .btn-add {
            background: #eef6ff; color: var(--accent);
            border: 1.5px dashed var(--accent); border-radius: 10px;
            padding: .55rem 1.4rem; font-size: .88rem; font-weight: 700;
            transition: all .2s; cursor: pointer; min-height: 40px;
            display: inline-flex; align-items: center; gap: .35rem;
        }
        .btn-add:hover { background: #d8eeff; }

        .btn-submit-pr {
            background: linear-gradient(135deg, var(--accent), #0088a3);
            color: #fff; border: none; border-radius: 10px;
            padding: .7rem 2.2rem; font-size: .95rem; font-weight: 700;
            box-shadow: 0 4px 14px rgba(0,172,193,.35);
            transition: transform .2s, box-shadow .2s; min-height: 44px;
            cursor: pointer; display: inline-flex; align-items: center; gap: .4rem;
        }
        .btn-submit-pr:hover { transform: translateY(-2px); box-shadow: 0 8px 22px rgba(0,172,193,.45); color: #fff; }

        .btn-remove-row {
            background: #fff0f3; border: 1.5px solid #ffb3c1; color: #c0392b;
            border-radius: 7px; padding: 3px 9px; font-size: .8rem; font-weight: 700;
            cursor: pointer; transition: all .15s; line-height: 1.6; min-height: 30px;
        }
        .btn-remove-row:hover { background: #ff4d6d; color: #fff; border-color: #ff4d6d; }

        /* ── MY REQUESTS TABLE ── */
        .req-table-wrap { overflow-x: auto; -webkit-overflow-scrolling: touch; border-radius: 10px; border: 1px solid var(--border); }
        .req-table { width: 100%; min-width: 500px; border-collapse: collapse; font-size: .87rem; }
        .req-table thead th {
            background: var(--navy); color: rgba(255,255,255,.8);
            font-size: .7rem; font-weight: 700; text-transform: uppercase; letter-spacing: .6px;
            padding: .8rem 1rem; text-align: center; white-space: nowrap;
        }
        .req-table thead th:first-child { border-radius: 10px 0 0 0; }
        .req-table thead th:last-child  { border-radius: 0 10px 0 0; }
        .req-table tbody tr { border-bottom: 1px solid var(--border); transition: background .15s; }
        .req-table tbody tr:hover { background: #f7faff; }
        .req-table tbody tr:last-child { border-bottom: none; }
        .req-table tbody td { padding: .8rem 1rem; vertical-align: middle; text-align: center; }

        .req-mobile-list { display: none; }
        .req-mobile-card { background: var(--card); border: 1px solid var(--border); border-radius: 10px; padding: 1rem; margin-bottom: .65rem; box-shadow: var(--shadow); }
        .rmc-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: .6rem; gap: .5rem; flex-wrap: wrap; }
        .rmc-id { font-family: monospace; font-size: .78rem; font-weight: 700; background: #f0f4fb; border: 1px solid var(--border); border-radius: 6px; padding: 2px 8px; color: var(--navy); }
        .rmc-row { display: flex; justify-content: space-between; font-size: .82rem; margin-bottom: .3rem; gap: .5rem; }
        .rmc-label { color: var(--muted); font-weight: 600; font-size: .7rem; text-transform: uppercase; }
        .rmc-val   { font-weight: 600; color: var(--text); }

        .badge-pending  { background:#fff8e6; color:#a05a00; border:1.5px solid #ffd700;  border-radius:999px; padding:3px 10px; font-size:.73rem; font-weight:700; white-space:nowrap; }
        .badge-approved { background:#e6faf5; color:#0d6e52; border:1.5px solid #5cd6b0;  border-radius:999px; padding:3px 10px; font-size:.73rem; font-weight:700; white-space:nowrap; }
        .badge-rejected { background:#fff0f3; color:#8b0020; border:1.5px solid #ff4d6d;  border-radius:999px; padding:3px 10px; font-size:.73rem; font-weight:700; white-space:nowrap; }

        .btn-view { background: #eef6ff; color: var(--accent); border: 1.5px solid #c5d8ff; border-radius: 7px; padding: 4px 12px; font-size: .8rem; font-weight: 700; cursor: pointer; transition: all .15s; }
        .btn-view:hover { background: var(--accent); color: #fff; border-color: var(--accent); }

        .empty-state { text-align: center; padding: 3rem 1rem; color: var(--muted); }
        .empty-state i { font-size: 2.5rem; margin-bottom: .75rem; opacity: .4; display: block; }

        /* ── MODAL ── */
        .modal-content { border-radius: var(--radius); border: none; box-shadow: var(--shadow-md); }
        .modal-header  { background: var(--navy); color: #fff; border-radius: var(--radius) var(--radius) 0 0; padding: 1rem 1.4rem; }
        .modal-header .modal-title { font-weight: 700; font-size: 1rem; }
        .modal-header .btn-close   { filter: invert(1); }
        .modal-table thead th { background: #f4f8ff; color: var(--navy); font-size: .72rem; text-transform: uppercase; letter-spacing: .5px; }

        @supports (padding: env(safe-area-inset-bottom)) {
            .page-wrap { padding-bottom: calc(3rem + env(safe-area-inset-bottom)); }
        }

        @media (max-width: 991px) {
            .page-wrap { padding: 65px 1.25rem 2rem 1.25rem; }
            .pr-card { padding: 1.25rem; }
        }
        @media (max-width: 768px) {
            body { margin-left: var(--sidebar-w-md); }
            .page-wrap { padding: 62px 1rem 2rem 1rem; }
            .pr-card { padding: 1rem; }
            .page-header h2 { font-size: 1.15rem; }
            .page-header .date-chip { display: none; }
            .req-table-desktop { display: none !important; }
            .req-mobile-list   { display: block; }
            .btn-submit-pr { width: 100%; justify-content: center; }
            .btn-add       { width: 100%; justify-content: center; }
            .info-box { flex-direction: column; gap: .5rem; }
            .nav-tabs .nav-link { font-size: .84rem; padding: .65rem .85rem; }
        }
        @media (max-width: 480px) {
            body { margin-left: 0; }
            .page-wrap { padding: 58px .75rem 2rem .75rem; }
            .pr-card { padding: .85rem; }
            .page-header { gap: .5rem; }
            .page-header-icon { width: 38px; height: 38px; font-size: 1.1rem; border-radius: 10px; }
            .page-header h2 { font-size: 1.05rem; }
            .btn-back span { display: none; } /* icon only on tiny screens */
            .nav-tabs .nav-link { font-size: .78rem; padding: .6rem .65rem; }
            .items-table { min-width: 580px; font-size: .8rem; }
            .items-table tbody td { padding: .5rem .6rem; }
            .form-control, .form-select { font-size: .82rem; min-height: 36px; }
            .info-box { font-size: .82rem; padding: .75rem 1rem; }
            .rmc-row { font-size: .79rem; }
        }
    </style>
</head>
<body>

<!-- SIDEBAR -->
<?php include 'inventory_sidebar.php'; ?>

<div class="page-wrap">

    <!-- PAGE HEADER -->
    <div class="page-header">

        <!-- ← BACK BUTTON -->
        <button class="btn-back" onclick="history.back()" title="Go back to previous page">
            <i class="bi bi-arrow-left-circle-fill"></i>
            <span>Back</span>
        </button>

        <div class="page-header-icon"><i class="bi bi-cart3"></i></div>
        <h2>Purchase Requests</h2>
        <span class="date-chip"><i class="bi bi-calendar3 me-1"></i><?= $request_date ?></span>
    </div>

    <!-- ALERTS -->
    <?php if (isset($success)): ?>
        <div class="alert alert-success mb-3" id="successAlert">
            <i class="bi bi-check-circle-fill me-2"></i><?= htmlspecialchars($success) ?>
        </div>
    <?php elseif (isset($error)): ?>
        <div class="alert alert-danger mb-3">
            <i class="bi bi-x-circle-fill me-2"></i><?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <!-- TABS -->
    <ul class="nav nav-tabs" role="tablist">
        <li class="nav-item">
            <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#form" role="tab">
                <i class="bi bi-plus-circle me-1"></i> Request Form
            </button>
        </li>
        <li class="nav-item">
            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#my-requests" role="tab">
                <i class="bi bi-list-check me-1"></i> My Requests
                <?php if (!empty($my_requests)): ?>
                    <span style="background:#00acc1;color:#fff;border-radius:999px;font-size:.63rem;padding:2px 7px;font-weight:800;margin-left:4px;">
                        <?= count($my_requests) ?>
                    </span>
                <?php endif; ?>
            </button>
        </li>
    </ul>

    <div class="tab-content">

        <!-- TAB 1 — REQUEST FORM -->
        <div class="tab-pane fade show active" id="form" role="tabpanel">
            <div class="pr-card">

                <div class="info-box">
                    <div class="info-box-item">
                        <i class="bi bi-building"></i>
                        <strong>Department:</strong>
                        <?= htmlspecialchars($department) ?>
                    </div>
                    <div class="info-box-item">
                        <i class="bi bi-person"></i>
                        <strong>Requestor:</strong>
                        <?= htmlspecialchars($full_name) ?>
                    </div>
                    <div class="info-box-item">
                        <i class="bi bi-calendar3"></i>
                        <strong>Date:</strong>
                        <?= $request_date ?>
                    </div>
                </div>

                <form method="POST" id="requestForm">
                    <div class="items-table-wrap mb-3">
                        <table class="items-table">
                            <thead>
                                <tr>
                                    <th style="width:22%; text-align:left;">Item Name</th>
                                    <th style="width:22%; text-align:left;">Description</th>
                                    <th style="width:13%;">Unit</th>
                                    <th style="width:10%;">Qty</th>
                                    <th style="width:11%;">Pcs / Box</th>
                                    <th style="width:11%;">Total Pcs</th>
                                    <th style="width:11%;">Action</th>
                                </tr>
                            </thead>
                            <tbody id="itemBody">
                                <tr>
                                    <td><input type="text"   name="items[0][name]"        class="form-control form-control-sm" placeholder="Item name" required></td>
                                    <td><input type="text"   name="items[0][description]"  class="form-control form-control-sm" placeholder="Optional"></td>
                                    <td>
                                        <select name="items[0][unit]" class="form-select form-select-sm unit">
                                            <option value="pcs">Per Piece</option>
                                            <option value="box">Per Box</option>
                                        </select>
                                    </td>
                                    <td><input type="number" name="items[0][quantity]"    class="form-control form-control-sm quantity"    value="1" min="1"></td>
                                    <td><input type="number" name="items[0][pcs_per_box]" class="form-control form-control-sm pcs-per-box" value="1" min="1" disabled></td>
                                    <td><input type="number" name="items[0][total_pcs]"   class="form-control form-control-sm total-pcs"   value="1" readonly></td>
                                    <td><button type="button" class="btn-remove-row btn-remove">✕</button></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="d-flex justify-content-center mb-4">
                        <button type="button" id="addRowBtn" class="btn-add">
                            <i class="bi bi-plus-circle"></i> Add Item
                        </button>
                    </div>

                    <div class="d-flex justify-content-center">
                        <button type="submit" class="btn-submit-pr">
                            <i class="bi bi-send"></i> Submit Request
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- TAB 2 — MY REQUESTS -->
        <div class="tab-pane fade" id="my-requests" role="tabpanel">
            <div class="pr-card">

                <?php if (empty($my_requests)): ?>
                    <div class="empty-state">
                        <i class="bi bi-inbox"></i>
                        <p style="font-weight:600;">No purchase requests yet.</p>
                        <p style="font-size:.85rem;">Submit your first request using the form tab.</p>
                    </div>

                <?php else: ?>

                    <!-- Desktop Table -->
                    <div class="req-table-wrap req-table-desktop">
                        <table class="req-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Department</th>
                                    <th>Total Items</th>
                                    <th>Status</th>
                                    <th>Date Submitted</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($my_requests as $req): ?>
                                    <?php
                                        $badge = match($req['status']) {
                                            'Approved' => '<span class="badge-approved">✅ Approved</span>',
                                            'Rejected' => '<span class="badge-rejected">❌ Rejected</span>',
                                            default    => '<span class="badge-pending">⏳ Pending</span>',
                                        };
                                    ?>
                                    <tr>
                                        <td><code style="font-size:.78rem;">#<?= htmlspecialchars($req['id']) ?></code></td>
                                        <td><?= htmlspecialchars($req['department'] ?? $department) ?></td>
                                        <td><?= htmlspecialchars($req['total_items']) ?></td>
                                        <td><?= $badge ?></td>
                                        <td style="font-size:.8rem;color:var(--muted);">
                                            <?= date('M d, Y', strtotime($req['created_at'])) ?>
                                            <br><small><?= date('h:i A', strtotime($req['created_at'])) ?></small>
                                        </td>
                                        <td>
                                            <button class="btn-view btn-view-items" data-id="<?= (int)$req['id'] ?>">
                                                <i class="bi bi-eye me-1"></i>View
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Mobile Cards -->
                    <div class="req-mobile-list">
                        <?php foreach ($my_requests as $req): ?>
                            <?php
                                $badge = match($req['status']) {
                                    'Approved' => '<span class="badge-approved">✅ Approved</span>',
                                    'Rejected' => '<span class="badge-rejected">❌ Rejected</span>',
                                    default    => '<span class="badge-pending">⏳ Pending</span>',
                                };
                            ?>
                            <div class="req-mobile-card">
                                <div class="rmc-header">
                                    <span class="rmc-id">#<?= htmlspecialchars($req['id']) ?></span>
                                    <?= $badge ?>
                                </div>
                                <div class="rmc-row">
                                    <span class="rmc-label">Department</span>
                                    <span class="rmc-val"><?= htmlspecialchars($req['department'] ?? $department) ?></span>
                                </div>
                                <div class="rmc-row">
                                    <span class="rmc-label">Total Items</span>
                                    <span class="rmc-val"><?= htmlspecialchars($req['total_items']) ?></span>
                                </div>
                                <div class="rmc-row">
                                    <span class="rmc-label">Submitted</span>
                                    <span class="rmc-val" style="font-size:.78rem;">
                                        <?= date('M d, Y · h:i A', strtotime($req['created_at'])) ?>
                                    </span>
                                </div>
                                <div class="mt-2">
                                    <button class="btn-view btn-view-items w-100" data-id="<?= (int)$req['id'] ?>">
                                        <i class="bi bi-eye me-1"></i> View Items
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                <?php endif; ?>
            </div>
        </div>

    </div><!-- end tab-content -->
</div><!-- end page-wrap -->

<!-- MODAL — View Items -->
<div class="modal fade" id="viewItemsModal" tabindex="-1" aria-labelledby="viewItemsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="viewItemsModalLabel">
                    <i class="bi bi-list-ul me-2"></i>Request Items
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div style="overflow-x:auto;">
                    <table class="table table-bordered modal-table mb-0">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Item Name</th>
                                <th>Description</th>
                                <th>Unit</th>
                                <th>Qty</th>
                                <th>Pcs/Box</th>
                                <th>Total Pcs</th>
                            </tr>
                        </thead>
                        <tbody id="modalItemBody">
                            <tr><td colspan="7" class="text-center text-muted py-3">Loading…</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
/* ── SIDEBAR MARGIN SYNC ── */
(function syncSidebarMargin() {
    const sidebar = document.querySelector('.sidebar');
    if (!sidebar) return;
    function getWidth() {
        if (window.innerWidth <= 480) return 0;
        if (window.innerWidth <= 768) return 200;
        return 250;
    }
    function applyMargin() {
        const isClosed = sidebar.classList.contains('closed') || sidebar.style.display === 'none';
        document.body.style.marginLeft = (isClosed ? 0 : getWidth()) + 'px';
    }
    applyMargin();
    new MutationObserver(applyMargin).observe(sidebar, { attributes: true, attributeFilter: ['class', 'style'] });
    window.addEventListener('resize', applyMargin);
})();

/* ── ITEMS TABLE — Add / Remove / Calculate ── */
let itemIndex = 1;
const itemBody = document.getElementById('itemBody');

document.getElementById('addRowBtn').addEventListener('click', () => {
    const template = itemBody.querySelector('tr').cloneNode(true);
    template.querySelectorAll('input, select').forEach(el => {
        el.name = el.name.replace(/\[\d+\]/, `[${itemIndex}]`);
        if (el.type === 'number') el.value = 1;
        if (el.type === 'text')   el.value = '';
        if (el.tagName === 'SELECT') el.selectedIndex = 0;
        if (el.classList.contains('pcs-per-box')) el.disabled = true;
    });
    template.querySelector('.total-pcs').value = 1;
    itemBody.appendChild(template);
    itemIndex++;
});

itemBody.addEventListener('click', e => {
    if (e.target.classList.contains('btn-remove')) {
        if (itemBody.querySelectorAll('tr').length > 1) {
            e.target.closest('tr').remove();
        } else {
            alert('At least one item is required.');
        }
    }
});

itemBody.addEventListener('input', e => {
    const row = e.target.closest('tr');
    if (!row) return;
    const unit      = row.querySelector('.unit').value;
    const qty       = parseFloat(row.querySelector('.quantity').value) || 0;
    const pcsBox    = row.querySelector('.pcs-per-box');
    const pcsPerBox = parseFloat(pcsBox.value) || 1;
    pcsBox.disabled = (unit !== 'box');
    row.querySelector('.total-pcs').value = unit === 'box' ? qty * pcsPerBox : qty;
});

itemBody.addEventListener('change', e => {
    if (e.target.classList.contains('unit')) {
        const row    = e.target.closest('tr');
        const pcsBox = row.querySelector('.pcs-per-box');
        const qty    = parseFloat(row.querySelector('.quantity').value) || 0;
        pcsBox.disabled = (e.target.value !== 'box');
        row.querySelector('.total-pcs').value =
            e.target.value === 'box' ? qty * (parseFloat(pcsBox.value) || 1) : qty;
    }
});

/* ── VIEW ITEMS MODAL — AJAX ── */
const viewModal = new bootstrap.Modal(document.getElementById('viewItemsModal'));

document.addEventListener('click', e => {
    const btn = e.target.closest('.btn-view-items');
    if (!btn) return;

    const modalBody = document.getElementById('modalItemBody');
    modalBody.innerHTML = '<tr><td colspan="7" class="text-center text-muted py-3">Loading…</td></tr>';
    viewModal.show();

    fetch('purchase_request.php?ajax=items&id=' + btn.dataset.id)
        .then(res => res.text().then(text => {
            try {
                const data = JSON.parse(text);
                return { ok: res.ok, status: res.status, data };
            } catch {
                return { ok: false, status: res.status, parseError: true, raw: text };
            }
        }))
        .then(({ ok, status, data, parseError, raw }) => {
            if (parseError) {
                const plain = raw.replace(/<[^>]*>/g, '').trim().substring(0, 300);
                modalBody.innerHTML = `<tr><td colspan="7" class="text-center text-danger py-3">
                    <strong>Server error (HTTP ${status}):</strong><br>
                    <code style="font-size:.78rem;white-space:pre-wrap;">${plain}</code>
                </td></tr>`;
                return;
            }
            if (!ok || data.error) {
                modalBody.innerHTML = `<tr><td colspan="7" class="text-center text-danger py-3">
                    ⚠ ${data.error ?? 'Unknown error (HTTP ' + status + ')'}
                </td></tr>`;
                return;
            }
            if (!Array.isArray(data) || data.length === 0) {
                modalBody.innerHTML = '<tr><td colspan="7" class="text-center text-muted py-3">No items found.</td></tr>';
                return;
            }
            const esc = s => String(s ?? '—').replace(/</g,'&lt;').replace(/>/g,'&gt;');
            modalBody.innerHTML = data.map((item, i) => `
                <tr>
                    <td>${i + 1}</td>
                    <td>${esc(item.item_name)}</td>
                    <td>${esc(item.description)}</td>
                    <td>${esc(item.unit)}</td>
                    <td class="text-center">${esc(item.quantity)}</td>
                    <td class="text-center">${esc(item.pcs_per_box)}</td>
                    <td class="text-center">${esc(item.total_pcs)}</td>
                </tr>`).join('');
        })
        .catch(err => {
            modalBody.innerHTML = `<tr><td colspan="7" class="text-center text-danger py-3">
                ⚠ Network error: <code style="font-size:.78rem;">${err.message}</code>
            </td></tr>`;
        });
});

/* ── AUTO-DISMISS SUCCESS ALERT ── */
const successAlert = document.getElementById('successAlert');
if (successAlert) {
    setTimeout(() => {
        successAlert.style.transition = 'opacity .6s';
        successAlert.style.opacity = '0';
        setTimeout(() => successAlert.remove(), 650);
    }, 5000);
}
</script>
</body>
</html>