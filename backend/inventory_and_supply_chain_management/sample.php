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
   Must be at the very top before any HTML output
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
        // Verify ownership
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
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($items ?: []);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Server error: ' . $e->getMessage()]);
    }
    exit(); // ← CRITICAL: stop here, no HTML output
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

        if (count($valid_items) === 0) {
            throw new Exception("Please add at least one item before submitting.");
        }

        $stmt = $pdo->prepare("
            INSERT INTO department_request
                (user_id, department, department_id, month, total_items, status)
            VALUES (?, ?, ?, ?, ?, 'Pending')
        ");
        $stmt->execute([$user_id, $department, $department_id, date('Y-m-d'), count($valid_items)]);
        $request_id = $pdo->lastInsertId();

        $item_stmt = $pdo->prepare("
            INSERT INTO department_request_items
                (request_id, item_name, description, unit, quantity, pcs_per_box, total_pcs)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        foreach ($valid_items as $item) {
            $item_stmt->execute([
                $request_id,
                trim($item['name']),
                $item['description'] ?? '',
                $item['unit']        ?? 'pcs',
                (int)($item['quantity']    ?? 1),
                (int)($item['pcs_per_box'] ?? 1),
                (int)($item['total_pcs']   ?? 0),
            ]);
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
$request_stmt = $pdo->prepare("
    SELECT * FROM department_request
    WHERE user_id = ?
    ORDER BY created_at DESC
");
$request_stmt->execute([$user_id]);
$my_requests = $request_stmt->fetchAll(PDO::FETCH_ASSOC);

// AJAX URL — same file
$ajax_url = basename($_SERVER['PHP_SELF']);
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
            --sidebar-w-sm: 200px;
            --topbar-h:     56px;
            --accent:       #00acc1;
            --navy:         #0b1d3a;
            --surface:      #F5F6F7;
            --card:         #ffffff;
            --border:       #e0e6f0;
            --text:         #374151;
            --muted:        #6e768e;
            --radius:       12px;
            --shadow:       0 2px 16px rgba(11,29,58,.08);
            --shadow-md:    0 4px 24px rgba(11,29,58,.12);
        }

        *, *::before, *::after { box-sizing: border-box; }
        html { scroll-behavior: smooth; }

        body {
            font-family: "Nunito", "Segoe UI", Arial, sans-serif;
            background: var(--surface);
            color: var(--text);
            margin: 0;
            margin-left: var(--sidebar-w);
            transition: margin-left 0.3s;
            min-height: 100vh;
            overflow-x: hidden;
        }

        /* ── SIDEBAR ── */
        #sidebar {
            width: var(--sidebar-w);
            position: fixed;
            top: 0; left: 0;
            height: 100vh;
            background: var(--navy);
            color: #fff;
            overflow-y: auto;
            overflow-x: hidden;
            z-index: 1000;
            transition: width 0.3s, transform 0.3s;
        }
        #sidebar.collapsed { width: 0; }
        .sidebar-logo { text-align: center; padding: 1.1rem 1rem .5rem; }
        .sidebar-logo img { max-width: 100px; height: auto; }
        .menu-title {
            font-size: .62rem; font-weight: 800; text-transform: uppercase;
            letter-spacing: 1.2px; color: rgba(255,255,255,.35);
            padding: .9rem 1.2rem .3rem; white-space: nowrap;
        }
        .sidebar-item { list-style: none; }
        .sidebar-link {
            display: flex; align-items: center; gap: .65rem;
            padding: .62rem 1.2rem;
            color: rgba(255,255,255,.72);
            text-decoration: none;
            font-size: .87rem; font-weight: 600;
            border-radius: 8px; margin: 2px .5rem;
            transition: background .18s, color .18s;
            white-space: nowrap;
        }
        .sidebar-link:hover, .sidebar-link.active {
            background: rgba(255,255,255,.13);
            color: #fff;
        }
        .sidebar-dropdown { padding-left: .8rem; }
        .sidebar-dropdown .sidebar-link { font-size: .82rem; color: rgba(255,255,255,.58); }
        .sidebar-dropdown .sidebar-link:hover { color: #fff; }

        /* ── TOPBAR ── */
        .topbar {
            position: fixed; top: 0; left: var(--sidebar-w); right: 0;
            height: var(--topbar-h);
            background: var(--card);
            border-bottom: 1px solid var(--border);
            display: flex; align-items: center; justify-content: space-between;
            padding: 0 1.5rem;
            z-index: 999;
            transition: left 0.3s;
            box-shadow: var(--shadow);
        }
        .toggler-btn {
            background: none; border: none; color: var(--navy);
            cursor: pointer; display: flex; align-items: center;
            padding: .35rem; border-radius: 7px; transition: background .18s;
        }
        .toggler-btn:hover { background: var(--surface); }
        .topbar .username { font-weight: 700; color: var(--navy); font-size: .9rem; }

        /* ── PAGE WRAP ── */
        .page-wrap {
            padding: calc(var(--topbar-h) + 20px) 1.75rem 3rem;
            max-width: 1400px;
            margin: 0 auto;
        }

        /* ── PAGE HEADER ── */
        .page-header {
            display: flex; align-items: center; gap: .75rem;
            margin-bottom: 1.5rem; flex-wrap: wrap;
        }
        .btn-back {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 7px 16px;
            background: var(--card); color: var(--navy);
            border: 1.5px solid var(--border); border-radius: 9px;
            font-size: .83rem; font-weight: 700;
            cursor: pointer; text-decoration: none;
            transition: all .18s; box-shadow: var(--shadow); flex-shrink: 0;
        }
        .btn-back:hover { background: var(--navy); color: #fff; border-color: var(--navy); transform: translateX(-2px); }
        .page-header-icon {
            width: 46px; height: 46px;
            background: linear-gradient(135deg, var(--accent), #0088a3);
            border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            color: #fff; font-size: 1.3rem; flex-shrink: 0;
            box-shadow: 0 4px 12px rgba(0,172,193,.35);
        }
        .page-header h2 { font-size: clamp(1.2rem,3vw,1.6rem); font-weight: 800; color: var(--navy); margin: 0; }
        .date-chip {
            margin-left: auto; background: var(--card);
            border: 1px solid var(--border); border-radius: 999px;
            padding: 5px 14px; font-size: .8rem; color: var(--muted);
            font-weight: 600; white-space: nowrap; box-shadow: var(--shadow);
        }

        /* ── ALERTS ── */
        .alert { border-radius: var(--radius); font-size: .9rem; border: none; padding: .85rem 1.2rem; }
        .alert-success { background: #e6faf5; color: #0d6e52; border-left: 4px solid #00c9a7; }
        .alert-danger  { background: #fff0f3; color: #7a0020; border-left: 4px solid #ff4d6d; }

        /* ── TABS ── */
        .nav-tabs {
            border-bottom: 2px solid var(--border);
            gap: 4px; flex-wrap: nowrap;
            overflow-x: auto; scrollbar-width: none;
        }
        .nav-tabs::-webkit-scrollbar { display: none; }
        .nav-tabs .nav-link {
            border: none; border-bottom: 3px solid transparent; border-radius: 0;
            color: var(--muted); font-weight: 700; font-size: .88rem;
            padding: .7rem 1.1rem; white-space: nowrap;
            transition: color .2s, border-color .2s; margin-bottom: -2px;
            background: transparent;
        }
        .nav-tabs .nav-link:hover { color: var(--navy); }
        .nav-tabs .nav-link.active { color: var(--accent); border-bottom-color: var(--accent); }

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
        .items-table-wrap { overflow-x: auto; border-radius: 10px; border: 1px solid var(--border); }
        .items-table { width: 100%; min-width: 680px; border-collapse: collapse; font-size: .85rem; }
        .items-table thead th {
            background: var(--navy); color: rgba(255,255,255,.85);
            font-size: .72rem; font-weight: 700; text-transform: uppercase; letter-spacing: .6px;
            padding: .75rem 1rem; white-space: nowrap; text-align: center;
        }
        .items-table thead th:first-child { border-radius: 10px 0 0 0; }
        .items-table thead th:last-child  { border-radius: 0 10px 0 0; }
        .items-table tbody tr { border-bottom: 1px solid var(--border); }
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
        }
        .btn-add:hover { background: #d8eeff; }

        .btn-submit-pr {
            background: linear-gradient(135deg, var(--accent), #0088a3);
            color: #fff; border: none; border-radius: 10px;
            padding: .7rem 2.2rem; font-size: .95rem; font-weight: 700;
            box-shadow: 0 4px 14px rgba(0,172,193,.35);
            transition: transform .2s, box-shadow .2s; min-height: 44px; cursor: pointer;
        }
        .btn-submit-pr:hover { transform: translateY(-2px); box-shadow: 0 8px 22px rgba(0,172,193,.45); color: #fff; }

        .btn-remove-row {
            background: #fff0f3; border: 1.5px solid #ffb3c1; color: #c0392b;
            border-radius: 7px; padding: 3px 9px; font-size: .8rem; font-weight: 700;
            cursor: pointer; transition: all .15s; line-height: 1.6;
        }
        .btn-remove-row:hover { background: #ff4d6d; color: #fff; border-color: #ff4d6d; }

        /* ── MY REQUESTS TABLE ── */
        .req-table-wrap { overflow-x: auto; border-radius: 10px; border: 1px solid var(--border); }
        .req-table { width: 100%; min-width: 520px; border-collapse: collapse; font-size: .87rem; }
        .req-table thead th {
            background: var(--navy); color: rgba(255,255,255,.85);
            font-size: .72rem; font-weight: 700; text-transform: uppercase; letter-spacing: .6px;
            padding: .8rem 1rem; text-align: center; white-space: nowrap;
        }
        .req-table thead th:first-child { border-radius: 10px 0 0 0; }
        .req-table thead th:last-child  { border-radius: 0 10px 0 0; }
        .req-table tbody tr { border-bottom: 1px solid var(--border); transition: background .15s; }
        .req-table tbody tr:hover { background: #f7faff; }
        .req-table tbody tr:last-child { border-bottom: none; }
        .req-table tbody td { padding: .8rem 1rem; vertical-align: middle; text-align: center; }

        /* Mobile cards */
        .req-mobile-list { display: none; }
        .req-mobile-card {
            background: var(--card); border: 1px solid var(--border);
            border-radius: 10px; padding: 1rem; margin-bottom: .65rem; box-shadow: var(--shadow);
        }
        .rmc-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: .6rem; gap: .5rem; flex-wrap: wrap; }
        .rmc-id { font-family: monospace; font-size: .78rem; font-weight: 700; background: #f0f4fb; border: 1px solid var(--border); border-radius: 6px; padding: 2px 8px; color: var(--navy); }
        .rmc-row { display: flex; justify-content: space-between; font-size: .82rem; margin-bottom: .3rem; }
        .rmc-label { color: var(--muted); font-weight: 600; font-size: .72rem; text-transform: uppercase; }
        .rmc-val { font-weight: 600; color: var(--text); }

        /* Status badges */
        .badge-pending  { background: #fff8e6; color: #a05a00; border: 1.5px solid #ffd700; border-radius: 999px; padding: 3px 10px; font-size: .73rem; font-weight: 700; }
        .badge-approved { background: #e6faf5; color: #0d6e52; border: 1.5px solid #5cd6b0; border-radius: 999px; padding: 3px 10px; font-size: .73rem; font-weight: 700; }
        .badge-rejected { background: #fff0f3; color: #8b0020; border: 1.5px solid #ff4d6d; border-radius: 999px; padding: 3px 10px; font-size: .73rem; font-weight: 700; }

        .btn-view {
            background: #eef6ff; color: var(--accent);
            border: 1.5px solid #c5d8ff; border-radius: 7px;
            padding: 5px 14px; font-size: .82rem; font-weight: 700;
            cursor: pointer; transition: all .15s;
            display: inline-flex; align-items: center; gap: 5px;
        }
        .btn-view:hover { background: var(--accent); color: #fff; border-color: var(--accent); }

        /* Empty state */
        .empty-state { text-align: center; padding: 3rem 1rem; color: var(--muted); }
        .empty-state i { font-size: 2.5rem; margin-bottom: .75rem; opacity: .4; display: block; }

        /* ── MODAL ── */
        .modal-content { border-radius: var(--radius); border: none; box-shadow: var(--shadow-md); }
        .modal-header  { background: var(--navy); color: #fff; border-radius: var(--radius) var(--radius) 0 0; padding: 1rem 1.4rem; }
        .modal-header .modal-title { font-weight: 700; font-size: 1rem; }
        .modal-header .btn-close { filter: invert(1) brightness(2); }
        .modal-table  { font-size: .86rem; margin-bottom: 0; }
        .modal-table thead th {
            background: #f4f8ff; color: var(--navy);
            font-size: .72rem; text-transform: uppercase; letter-spacing: .5px; font-weight: 700;
        }
        .modal-loading { text-align: center; padding: 2.5rem 1rem; color: var(--muted); }
        .modal-loading .spinner-border { color: var(--accent); width: 2rem; height: 2rem; }

        /* ── RESPONSIVE ── */
        @media (max-width: 768px) {
            body { margin-left: var(--sidebar-w-sm); }
            #sidebar { width: var(--sidebar-w-sm); }
            .topbar { left: var(--sidebar-w-sm); }
            .page-wrap { padding: calc(var(--topbar-h) + 10px) 1rem 2rem; }
            .pr-card { padding: 1rem; }
            .date-chip { display: none; }
            .req-table-desktop { display: none !important; }
            .req-mobile-list { display: block; }
            .btn-submit-pr, .btn-add { width: 100%; justify-content: center; }
            .info-box { flex-direction: column; gap: .5rem; }
        }
        @media (max-width: 479px) {
            body { margin-left: 0 !important; }
            #sidebar { transform: translateX(-100%); width: var(--sidebar-w-sm) !important; }
            #sidebar.open { transform: translateX(0); }
            .topbar { left: 0 !important; }
            .page-wrap { padding: calc(var(--topbar-h) + 8px) .75rem 2rem; }
            .pr-card { padding: .85rem; }
            .btn-back span { display: none; }
        }
    </style>
</head>
<body>

<!-- ════════════════════════════════════
     SIDEBAR
════════════════════════════════════ -->
<aside id="sidebar">
    <div class="sidebar-logo">
        <img src="assets/image/logo-dark.png" alt="HMS Logo">
    </div>

    <div class="menu-title">Navigation</div>

    <ul style="padding:0;margin:0;">
        <li class="sidebar-item">
            <a href="patient_dashboard.php" class="sidebar-link">
                <i class="bi bi-speedometer2"></i>
                <span>Dashboard</span>
            </a>
        </li>

        <li class="sidebar-item">
            <a href="#patientLists"
               class="sidebar-link collapsed"
               data-bs-toggle="collapse"
               data-bs-target="#patientLists"
               aria-expanded="false">
                <i class="bi bi-person-vcard"></i>
                <span>Patient Lists</span>
                <i class="bi bi-chevron-down ms-auto" style="font-size:.7rem;"></i>
            </a>
            <ul id="patientLists" class="sidebar-dropdown list-unstyled collapse">
                <li class="sidebar-item">
                    <a href="../patient_management/registered.php" class="sidebar-link">
                        <i class="bi bi-dot"></i> Registered Patient
                    </a>
                </li>
                <li class="sidebar-item">
                    <a href="../patient_management/inpatient.php" class="sidebar-link">
                        <i class="bi bi-dot"></i> Inpatients
                    </a>
                </li>
                <li class="sidebar-item">
                    <a href="../patient_management/outpatient.php" class="sidebar-link">
                        <i class="bi bi-dot"></i> Outpatients
                    </a>
                </li>
            </ul>
        </li>

        <li class="sidebar-item">
            <a href="appointment.php" class="sidebar-link">
                <i class="bi bi-calendar-check"></i>
                <span>Appointment</span>
            </a>
        </li>

        <li class="sidebar-item">
            <a href="bedding.php" class="sidebar-link">
                <i class="bi bi-house-heart"></i>
                <span>Bedding &amp; Linen</span>
            </a>
        </li>

        <li class="sidebar-item">
            <a href="logs.php" class="sidebar-link">
                <i class="bi bi-journal-text"></i>
                <span>Logs</span>
            </a>
        </li>

        <li class="sidebar-item">
            <a href="purchase_request.php" class="sidebar-link active">
                <i class="bi bi-cart3"></i>
                <span>Purchase Request</span>
            </a>
        </li>
    </ul>
</aside>

<!-- ════════════════════════════════════
     TOPBAR
════════════════════════════════════ -->
<div class="topbar" id="topbar">
    <button class="toggler-btn" id="sidebarToggler" type="button">
        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" viewBox="0 0 16 16">
            <path fill-rule="evenodd"
                  d="M5 11.5a.5.5 0 0 1 .5-.5h9a.5.5 0 0 1 0 1h-9a.5.5 0 0 1-.5-.5
                     m0-4a.5.5 0 0 1 .5-.5h9a.5.5 0 0 1 0 1h-9a.5.5 0 0 1-.5-.5
                     m0-4a.5.5 0 0 1 .5-.5h9a.5.5 0 0 1 0 1h-9a.5.5 0 0 1-.5-.5
                     m-3 1a1 1 0 1 0 0-2 1 1 0 0 0 0 2m0 4a1 1 0 1 0 0-2 1 1 0 0 0 0 2
                     m0 4a1 1 0 1 0 0-2 1 1 0 0 0 0 2"/>
        </svg>
    </button>
    <div class="d-flex align-items-center gap-2">
        <span class="username"><?= htmlspecialchars($full_name) ?></span>
        <div class="dropdown">
            <button class="btn p-1 dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                <i class="bi bi-person-circle fs-5" style="color:var(--navy);"></i>
            </button>
            <ul class="dropdown-menu dropdown-menu-end" style="min-width:180px;">
                <li>
                    <span class="dropdown-item-text" style="font-size:.84rem;">
                        Welcome, <strong><?= htmlspecialchars($full_name) ?></strong>
                    </span>
                </li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item" href="../../logout.php">Logout</a></li>
            </ul>
        </div>
    </div>
</div>

<!-- ════════════════════════════════════
     MAIN CONTENT
════════════════════════════════════ -->
<div class="page-wrap">

    <!-- Page Header -->
    <div class="page-header">
        <button class="btn-back" onclick="history.back()">
            <i class="bi bi-arrow-left-circle-fill"></i>
            <span>Back</span>
        </button>
        <div class="page-header-icon"><i class="bi bi-cart3"></i></div>
        <h2>Purchase Requests</h2>
        <span class="date-chip"><i class="bi bi-calendar3 me-1"></i><?= $request_date ?></span>
    </div>

    <!-- Alerts -->
    <?php if (isset($success)): ?>
        <div class="alert alert-success mb-3" id="successAlert">
            <i class="bi bi-check-circle-fill me-2"></i><?= htmlspecialchars($success) ?>
        </div>
    <?php elseif (isset($error)): ?>
        <div class="alert alert-danger mb-3">
            <i class="bi bi-x-circle-fill me-2"></i><?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <!-- Tabs -->
    <ul class="nav nav-tabs" role="tablist">
        <li class="nav-item">
            <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tabForm" role="tab">
                <i class="bi bi-plus-circle me-1"></i> Request Form
            </button>
        </li>
        <li class="nav-item">
            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabRequests" role="tab">
                <i class="bi bi-list-check me-1"></i> My Requests
                <?php if (!empty($my_requests)): ?>
                    <span style="background:var(--accent);color:#fff;border-radius:999px;
                                 font-size:.65rem;padding:2px 7px;font-weight:800;margin-left:4px;">
                        <?= count($my_requests) ?>
                    </span>
                <?php endif; ?>
            </button>
        </li>
    </ul>

    <div class="tab-content">

        <!-- ── TAB 1: FORM ── -->
        <div class="tab-pane fade show active" id="tabForm" role="tabpanel">
            <div class="pr-card">
                <div class="info-box">
                    <div class="info-box-item">
                        <i class="bi bi-building"></i>
                        <strong>Department:</strong>&nbsp;<?= htmlspecialchars($department) ?>
                    </div>
                    <div class="info-box-item">
                        <i class="bi bi-person"></i>
                        <strong>Requestor:</strong>&nbsp;<?= htmlspecialchars($full_name) ?>
                    </div>
                    <div class="info-box-item">
                        <i class="bi bi-calendar3"></i>
                        <strong>Date:</strong>&nbsp;<?= $request_date ?>
                    </div>
                </div>

                <form method="POST" id="requestForm">
                    <div class="items-table-wrap mb-3">
                        <table class="items-table">
                            <thead>
                                <tr>
                                    <th style="width:22%;text-align:left;">Item Name</th>
                                    <th style="width:22%;text-align:left;">Description</th>
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
                            <i class="bi bi-plus-circle me-1"></i> Add Item
                        </button>
                    </div>
                    <div class="d-flex justify-content-center">
                        <button type="submit" class="btn-submit-pr">
                            <i class="bi bi-send me-2"></i> Submit Request
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- ── TAB 2: MY REQUESTS ── -->
        <div class="tab-pane fade" id="tabRequests" role="tabpanel">
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
                            <?php foreach ($my_requests as $req):
                                $status = $req['status'] ?? 'Pending';
                                $badge  = match($status) {
                                    'Approved' => '<span class="badge-approved">✅ Approved</span>',
                                    'Rejected' => '<span class="badge-rejected">❌ Rejected</span>',
                                    default    => '<span class="badge-pending">⏳ Pending</span>',
                                };
                            ?>
                                <tr>
                                    <td><code style="font-size:.78rem;">#<?= (int)$req['id'] ?></code></td>
                                    <td><?= htmlspecialchars($req['department'] ?? $department) ?></td>
                                    <td><?= (int)$req['total_items'] ?></td>
                                    <td><?= $badge ?></td>
                                    <td style="font-size:.8rem;color:var(--muted);">
                                        <?= date('M d, Y', strtotime($req['created_at'])) ?><br>
                                        <small><?= date('h:i A', strtotime($req['created_at'])) ?></small>
                                    </td>
                                    <td>
                                        <!-- data-id carries the request ID for AJAX -->
                                        <button class="btn-view btn-view-items"
                                                type="button"
                                                data-id="<?= (int)$req['id'] ?>">
                                            <i class="bi bi-eye"></i> View
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Mobile Cards -->
                    <div class="req-mobile-list">
                    <?php foreach ($my_requests as $req):
                        $status = $req['status'] ?? 'Pending';
                        $badge  = match($status) {
                            'Approved' => '<span class="badge-approved">✅ Approved</span>',
                            'Rejected' => '<span class="badge-rejected">❌ Rejected</span>',
                            default    => '<span class="badge-pending">⏳ Pending</span>',
                        };
                    ?>
                        <div class="req-mobile-card">
                            <div class="rmc-header">
                                <span class="rmc-id">#<?= (int)$req['id'] ?></span>
                                <?= $badge ?>
                            </div>
                            <div class="rmc-row">
                                <span class="rmc-label">Total Items</span>
                                <span class="rmc-val"><?= (int)$req['total_items'] ?></span>
                            </div>
                            <div class="rmc-row">
                                <span class="rmc-label">Submitted</span>
                                <span class="rmc-val" style="font-size:.78rem;">
                                    <?= date('M d, Y · h:i A', strtotime($req['created_at'])) ?>
                                </span>
                            </div>
                            <div class="mt-2">
                                <button class="btn-view btn-view-items w-100"
                                        type="button"
                                        data-id="<?= (int)$req['id'] ?>">
                                    <i class="bi bi-eye"></i> View Items
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    </div>

                <?php endif; ?>
            </div>
        </div>

    </div><!-- /tab-content -->
</div><!-- /page-wrap -->

<!-- ════════════════════════════════════
     MODAL — View Items
════════════════════════════════════ -->
<div class="modal fade" id="viewItemsModal" tabindex="-1"
     aria-labelledby="viewItemsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="viewItemsModalLabel">
                    <i class="bi bi-list-ul me-2"></i>Request Items
                    <span id="modalReqBadge" style="font-size:.8rem;opacity:.65;margin-left:4px;"></span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0">

                <!-- Loading spinner -->
                <div id="modalLoading" class="modal-loading">
                    <div class="spinner-border" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-2 mb-0" style="font-size:.85rem;">Fetching items…</p>
                </div>

                <!-- Error message -->
                <div id="modalError" class="alert alert-danger m-3" style="display:none;font-size:.87rem;"></div>

                <!-- Items table -->
                <div id="modalTableWrap" style="display:none;overflow-x:auto;">
                    <table class="table table-bordered modal-table">
                        <thead>
                            <tr>
                                <th style="width:40px;text-align:center;">#</th>
                                <th>Item Name</th>
                                <th>Description</th>
                                <th style="text-align:center;">Unit</th>
                                <th style="text-align:center;">Qty</th>
                                <th style="text-align:center;">Pcs/Box</th>
                                <th style="text-align:center;">Total Pcs</th>
                            </tr>
                        </thead>
                        <tbody id="modalItemBody"></tbody>
                    </table>
                </div>

            </div>
        </div>
    </div>
</div>

<!-- ════════════════════════════════════
     SCRIPTS
════════════════════════════════════ -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
/* ─────────────────────────────────────
   SIDEBAR TOGGLE
───────────────────────────────────── */
(function () {
    var sidebar  = document.getElementById('sidebar');
    var topbar   = document.getElementById('topbar');
    var toggler  = document.getElementById('sidebarToggler');
    if (!sidebar || !toggler) return;

    function sideW() {
        if (window.innerWidth <= 479) return 0;
        if (window.innerWidth <= 768) return 200;
        return 250;
    }

    function applyLayout() {
        if (window.innerWidth <= 479) {
            document.body.style.marginLeft = '0px';
            topbar.style.left = '0px';
        } else {
            var sw = sidebar.classList.contains('collapsed') ? 0 : sideW();
            document.body.style.marginLeft = sw + 'px';
            topbar.style.left = sw + 'px';
        }
    }

    toggler.addEventListener('click', function () {
        if (window.innerWidth <= 479) {
            sidebar.classList.toggle('open');
        } else {
            sidebar.classList.toggle('collapsed');
            sidebar.style.width = sidebar.classList.contains('collapsed')
                ? '0px' : sideW() + 'px';
        }
        applyLayout();
    });

    window.addEventListener('resize', applyLayout);
    applyLayout();
}());

/* ─────────────────────────────────────
   ITEMS TABLE — ADD / REMOVE / CALC
───────────────────────────────────── */
var rowIndex = 1;
var itemBody = document.getElementById('itemBody');

document.getElementById('addRowBtn').addEventListener('click', function () {
    var clone = itemBody.querySelector('tr').cloneNode(true);
    clone.querySelectorAll('input, select').forEach(function (el) {
        el.name = el.name.replace(/\[\d+\]/, '[' + rowIndex + ']');
        if (el.type === 'text')      el.value = '';
        if (el.type === 'number')    el.value = 1;
        if (el.tagName === 'SELECT') el.selectedIndex = 0;
        if (el.classList.contains('pcs-per-box')) el.disabled = true;
    });
    clone.querySelector('.total-pcs').value = 1;
    itemBody.appendChild(clone);
    rowIndex++;
});

itemBody.addEventListener('click', function (e) {
    if (!e.target.classList.contains('btn-remove')) return;
    if (itemBody.querySelectorAll('tr').length > 1) {
        e.target.closest('tr').remove();
    } else {
        alert('At least one item is required.');
    }
});

function calcRow(row) {
    var unit      = row.querySelector('.unit').value;
    var qty       = parseFloat(row.querySelector('.quantity').value) || 0;
    var pcsBox    = row.querySelector('.pcs-per-box');
    var pcsPerBox = parseFloat(pcsBox.value) || 1;
    pcsBox.disabled = (unit !== 'box');
    row.querySelector('.total-pcs').value = (unit === 'box') ? qty * pcsPerBox : qty;
}

itemBody.addEventListener('input',  function (e) { var r = e.target.closest('tr'); if (r) calcRow(r); });
itemBody.addEventListener('change', function (e) { var r = e.target.closest('tr'); if (r) calcRow(r); });

/* ─────────────────────────────────────
   VIEW ITEMS MODAL — AJAX
───────────────────────────────────── */
var viewModal    = new bootstrap.Modal(document.getElementById('viewItemsModal'));
var elLoading    = document.getElementById('modalLoading');
var elError      = document.getElementById('modalError');
var elTableWrap  = document.getElementById('modalTableWrap');
var elModalBody  = document.getElementById('modalItemBody');
var elReqBadge   = document.getElementById('modalReqBadge');

// PHP passes the correct filename so the URL is always right
var AJAX_URL = '<?= htmlspecialchars($ajax_url, ENT_QUOTES) ?>';

function esc(s) {
    return String(s == null ? '—' : s)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;');
}

function setModalState(state, msg) {
    elLoading.style.display   = state === 'loading' ? 'block' : 'none';
    elError.style.display     = state === 'error'   ? 'block' : 'none';
    elTableWrap.style.display = state === 'table'   ? 'block' : 'none';
    if (state === 'error') {
        elError.innerHTML = '<i class="bi bi-exclamation-triangle-fill me-1"></i>' + esc(msg);
    }
}

// Use event delegation so it works for both desktop rows and mobile cards
document.addEventListener('click', function (e) {
    var btn = e.target.closest('.btn-view-items');
    if (!btn) return;

    var rid = parseInt(btn.getAttribute('data-id'), 10);
    if (!rid || rid <= 0) {
        console.error('View button missing valid data-id:', btn);
        return;
    }

    // Show modal immediately with loading state
    elModalBody.innerHTML = '';
    elReqBadge.textContent = '(Request #' + rid + ')';
    setModalState('loading');
    viewModal.show();

    var url = AJAX_URL + '?ajax=items&id=' + rid + '&_=' + Date.now();

    fetch(url, {
        method: 'GET',
        credentials: 'same-origin',
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json'
        }
    })
    .then(function (res) {
        return res.text().then(function (raw) {
            var data;
            try {
                data = JSON.parse(raw);
            } catch (_) {
                // Strip HTML tags to show readable PHP error
                var plain = raw.replace(/<[^>]*>/g, '').trim().slice(0, 500);
                throw new Error('Server returned non-JSON (HTTP ' + res.status + '): ' + plain);
            }
            if (!res.ok) {
                throw new Error(data.error || 'HTTP ' + res.status + ' error from server');
            }
            return data;
        });
    })
    .then(function (data) {
        if (!Array.isArray(data)) {
            throw new Error('Unexpected data format received from server.');
        }
        if (data.length === 0) {
            setModalState('error', 'No items found for this request.');
            return;
        }
        var rows = data.map(function (item, i) {
            return '<tr>' +
                '<td style="text-align:center;">' + (i + 1) + '</td>' +
                '<td>' + esc(item.item_name)  + '</td>' +
                '<td>' + esc(item.description) + '</td>' +
                '<td style="text-align:center;">' + esc(item.unit)       + '</td>' +
                '<td style="text-align:center;">' + esc(item.quantity)   + '</td>' +
                '<td style="text-align:center;">' + esc(item.pcs_per_box) + '</td>' +
                '<td style="text-align:center;">' + esc(item.total_pcs)  + '</td>' +
                '</tr>';
        });
        elModalBody.innerHTML = rows.join('');
        setModalState('table');
    })
    .catch(function (err) {
        console.error('View items error:', err);
        setModalState('error', err.message);
    });
});

/* ─────────────────────────────────────
   AUTO-DISMISS SUCCESS ALERT
───────────────────────────────────── */
var sa = document.getElementById('successAlert');
if (sa) {
    setTimeout(function () {
        sa.style.transition = 'opacity .6s';
        sa.style.opacity = '0';
        setTimeout(function () { sa.remove(); }, 650);
    }, 5000);
}
</script>
</body>
</html>