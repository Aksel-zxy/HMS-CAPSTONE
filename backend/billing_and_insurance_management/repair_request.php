<?php
session_start();
include '../../SQL/config.php';

error_reporting(E_ALL);
ini_set('display_errors', 1);

// 🔐 Ensure login
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// 👤 Fetch logged-in user info
$user_stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ? LIMIT 1");
$user_stmt->execute([$user_id]);
$user = $user_stmt->fetch(PDO::FETCH_ASSOC);

// Build full name from users table columns: fname, mname, lname
$logged_user_name = trim(
    ($user['fname']  ?? '') . ' ' .
    ($user['mname']  ?? '') . ' ' .
    ($user['lname']  ?? '')
);
$logged_user_name = preg_replace('/\s+/', ' ', $logged_user_name);
if (empty($logged_user_name)) {
    $logged_user_name = $user['username'] ?? 'Unknown User';
}
$logged_user_dept = $user['department'] ?? '';

// ===============================
// Auto-generate ticket number
// ===============================
function generateTicketNo($pdo) {
    $prefix = 'TKT-' . date('Ymd') . '-';
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM repair_requests WHERE ticket_no LIKE ?");
    $stmt->execute([$prefix . '%']);
    $count = (int)$stmt->fetchColumn() + 1;
    return $prefix . str_pad($count, 4, '0', STR_PAD_LEFT);
}

// ===============================
// Handle new repair request submission
// ===============================
$success_msg = '';
$error_msg   = '';
$active_tab  = 'tickets';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_request'])) {
    $active_tab = 'new';
    $user_name = $logged_user_name;
    $equipment = trim($_POST['equipment']   ?? '');
    $issue     = trim($_POST['issue']       ?? '');
    $location  = trim($_POST['location']    ?? '');
    $priority  = trim($_POST['priority']    ?? 'Low');
    $ticket_no = generateTicketNo($pdo);

    $conf = min(100, (int)(strlen($issue) / 3) + match($priority) {
        'Critical' => 30, 'High' => 20, 'Medium' => 10, default => 5
    });

    if ($user_name && $equipment && $issue && $location) {
        $stmt = $pdo->prepare("
            INSERT INTO repair_requests
                (ticket_no, user_id, user_name, equipment, issue, location, priority, status, confidence_score)
            VALUES (?, ?, ?, ?, ?, ?, ?, 'Open', ?)
        ");
        $stmt->execute([$ticket_no, $user_id, $user_name, $equipment, $issue, $location, $priority, $conf]);
        $success_msg = "Repair request submitted! Your ticket number is <strong>{$ticket_no}</strong>.";
        $active_tab  = 'tickets';
    } else {
        $error_msg = "Please fill in all required fields.";
    }
}

// ===============================
// Fetch all repair requests (with filters)
// ===============================
$filter_status   = $_GET['status']   ?? 'all';
$filter_priority = $_GET['priority'] ?? 'all';
$search          = trim($_GET['search'] ?? '');

if (!empty($_GET)) $active_tab = 'tickets';

$where  = ["user_id = ?"];
$params = [$user_id];

if ($filter_status !== 'all') {
    $where[]  = "status = ?";
    $params[] = $filter_status;
}
if ($filter_priority !== 'all') {
    $where[]  = "priority = ?";
    $params[] = $filter_priority;
}
if ($search !== '') {
    $where[]  = "(ticket_no LIKE ? OR equipment LIKE ? OR location LIKE ?)";
    $like     = "%{$search}%";
    array_push($params, $like, $like, $like);
}

$whereSQL = 'WHERE ' . implode(' AND ', $where);
$stmt = $pdo->prepare("SELECT * FROM repair_requests {$whereSQL} ORDER BY created_at DESC");
$stmt->execute($params);
$requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Stats
$stmt_stats = $pdo->prepare("
    SELECT
        COUNT(*) AS total,
        SUM(status = 'Open') AS open_count,
        SUM(status = 'In Progress') AS inprog_count,
        SUM(status = 'Completed') AS done_count,
        SUM(priority = 'Critical') AS critical_count
    FROM repair_requests
    WHERE user_id = ?
");
$stmt_stats->execute([$user_id]);
$stats = $stmt_stats->fetch(PDO::FETCH_ASSOC);

// Fetch distinct departments
$dept_stmt = $pdo->query("
    SELECT DISTINCT department
    FROM users
    WHERE department IS NOT NULL AND department != ''
    ORDER BY department ASC
");
$departments = $dept_stmt->fetchAll(PDO::FETCH_COLUMN);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Repair Request System — HMS Capstone</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@400;600&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        /* ══════════════════════════════════════════
           DESIGN TOKENS / CSS VARIABLES
        ══════════════════════════════════════════ */
        :root {
            --navy:      #0b1d3a;
            --navy-mid:  #122654;
            --navy-soft: #1a3567;
            --accent:    #2d8bff;
            --accent2:   #00c9a7;
            --gold:      #f4b942;
            --danger:    #ff4d6d;
            --warning:   #ff9f43;
            --surface:   #f0f4fb;
            --card:      #ffffff;
            --border:    #dce6f5;
            --text:      #1a2640;
            --muted:     #7a8fb5;
            --radius:    14px;
            --shadow:    0 4px 24px rgba(11,29,58,.10);
            --shadow-lg: 0 12px 48px rgba(11,29,58,.16);

            /* Responsive spacing */
            --page-px: 2rem;
            --hero-py: 2.5rem;
            --card-p:  1.4rem;
            --tab-body-p: 2rem;
        }

        /* ══════════════════════════════════════════
           BASE RESET
        ══════════════════════════════════════════ */
        *, *::before, *::after { box-sizing: border-box; }

        html { scroll-behavior: smooth; }

        body {
            font-family: 'Outfit', sans-serif;
            background: #F5F6F7; /* matches billing_sidebar.php body background */
            color: var(--text);
            min-height: 100vh;
            -webkit-tap-highlight-color: transparent;
            overflow-x: hidden;
            /* Push content right to clear the 250px fixed sidebar */
            margin-left: 250px;
            transition: margin-left 0.3s ease-in-out;
        }

        /* When sidebar is toggled closed via billing_sidebar.php JS */
        body.sidebar-collapsed {
            margin-left: 0;
        }


        .main-sidebar { z-index: 1000; }

        .page-wrap {
            /* top: clear the sidebar-toggle button (≈50px) + a bit of breathing room */
            padding: 60px var(--page-px) 3rem var(--page-px);
            max-width: 1400px;
            margin: 0 auto;
        }

        /* ══════════════════════════════════════════
           HERO
        ══════════════════════════════════════════ */
        .page-hero {
            background: linear-gradient(135deg, var(--navy) 0%, var(--navy-soft) 60%, #1e4d8c 100%);
            /* Full rounded corners — hero is within the content area, not flush to viewport */
            border-radius: var(--radius);
            padding: var(--hero-py) var(--page-px) 2rem;
            margin-bottom: 1.5rem;
            position: relative;
            overflow: hidden;
        }
        .page-hero::before {
            content: '';
            position: absolute;
            inset: 0;
            background: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.03'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
            pointer-events: none;
        }
        .page-hero::after {
            content: '';
            position: absolute;
            right: -80px; top: -80px;
            width: 340px; height: 340px;
            background: radial-gradient(circle, rgba(45,139,255,.18) 0%, transparent 70%);
            border-radius: 50%;
            pointer-events: none;
        }

        .hero-content { position: relative; z-index: 1; }

        .hero-left { flex: 1 1 auto; min-width: 0; }
        .hero-right { flex-shrink: 0; }

        .hero-icon {
            width: 48px; height: 48px;
            background: rgba(45,139,255,.22);
            border: 1.5px solid rgba(45,139,255,.4);
            border-radius: 14px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.4rem;
            color: #a8d4ff;
            margin-bottom: .8rem;
        }
        .page-hero h1 {
            font-size: clamp(1.3rem, 4vw, 1.9rem);
            font-weight: 800;
            color: #fff;
            margin: 0 0 .3rem;
            letter-spacing: -.5px;
            line-height: 1.2;
        }
        .page-hero p {
            color: rgba(255,255,255,.6);
            font-size: clamp(.82rem, 2vw, .93rem);
            margin: 0;
            line-height: 1.5;
        }
        .hero-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: rgba(255,255,255,.1);
            border: 1px solid rgba(255,255,255,.15);
            border-radius: 999px;
            padding: 5px 12px;
            font-size: .75rem;
            color: rgba(255,255,255,.8);
            font-weight: 500;
            white-space: nowrap;
        }

        /* User pill in hero */
        .hero-user-pill {
            display: inline-flex;
            align-items: center;
            gap: .5rem;
            background: rgba(255,255,255,.15);
            border: 1px solid rgba(255,255,255,.25);
            border-radius: 999px;
            padding: 6px 14px 6px 6px;
            font-size: .8rem;
            color: rgba(255,255,255,.9);
            font-weight: 600;
        }
        .hero-user-pill-avatar {
            width: 28px; height: 28px;
            background: linear-gradient(135deg, var(--accent), #1a6fd4);
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-size: .72rem;
            font-weight: 800;
            color: #fff;
            flex-shrink: 0;
        }
        .hero-user-pill-info {
            display: flex;
            flex-direction: column;
            line-height: 1.25;
        }

        /* ══════════════════════════════════════════
           STAT CARDS
        ══════════════════════════════════════════ */
        .stat-grid {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        .stat-card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 1rem var(--card-p);
            box-shadow: var(--shadow);
            display: flex;
            align-items: center;
            gap: .85rem;
            transition: transform .2s, box-shadow .2s;
            position: relative;
            overflow: hidden;
            min-width: 0;
        }
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 3px;
            border-radius: var(--radius) var(--radius) 0 0;
        }
        .stat-card.total::before  { background: var(--accent); }
        .stat-card.open::before   { background: var(--gold); }
        .stat-card.inprog::before { background: var(--accent); }
        .stat-card.done::before   { background: var(--accent2); }
        .stat-card.crit::before   { background: var(--danger); }
        .stat-card:hover { transform: translateY(-3px); box-shadow: var(--shadow-lg); }

        .stat-icon {
            width: 42px; height: 42px;
            border-radius: 11px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.2rem;
            flex-shrink: 0;
        }
        .stat-card.total .stat-icon  { background:#eef4ff; color:var(--accent); }
        .stat-card.open .stat-icon   { background:#fff8e6; color:var(--gold); }
        .stat-card.inprog .stat-icon { background:#e8f3ff; color:var(--accent); }
        .stat-card.done .stat-icon   { background:#e6faf5; color:var(--accent2); }
        .stat-card.crit .stat-icon   { background:#fff0f3; color:var(--danger); }

        .stat-num {
            font-size: clamp(1.4rem, 3vw, 1.75rem);
            font-weight: 800;
            color: var(--navy);
            line-height: 1;
            font-family: 'JetBrains Mono', monospace;
        }
        .stat-lbl {
            font-size: .72rem;
            color: var(--muted);
            font-weight: 500;
            margin-top: 3px;
            text-transform: uppercase;
            letter-spacing: .5px;
            white-space: nowrap;
        }

        /* ══════════════════════════════════════════
           ALERT BANNERS
        ══════════════════════════════════════════ */
        .alert-success-custom {
            background: linear-gradient(135deg, #e6faf5, #f0fff8);
            border: 1.5px solid #5cd6b0;
            border-radius: var(--radius);
            color: #0d5e45;
            padding: 1rem 1.2rem;
            font-size: .88rem;
            display: flex;
            align-items: flex-start;
            gap: .75rem;
            margin-bottom: 1.2rem;
        }
        .alert-error-custom {
            background: #fff0f3;
            border: 1.5px solid #ff4d6d;
            border-radius: var(--radius);
            color: #7a0020;
            padding: 1rem 1.2rem;
            font-size: .88rem;
            display: flex;
            align-items: flex-start;
            gap: .75rem;
            margin-bottom: 1.2rem;
        }

        /* ══════════════════════════════════════════
           TAB CONTAINER
        ══════════════════════════════════════════ */
        .tab-container {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            overflow: hidden;
        }

        /* ── Tab Nav ── */
        .tab-nav {
            display: flex;
            background: #f4f8ff;
            border-bottom: 2px solid var(--border);
            padding: 0 1rem;
            gap: 0;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            scrollbar-width: none;
        }
        .tab-nav::-webkit-scrollbar { display: none; }

        .tab-btn {
            display: flex;
            align-items: center;
            gap: .5rem;
            padding: .9rem 1.1rem;
            border: none;
            background: transparent;
            font-family: 'Outfit', sans-serif;
            font-size: .88rem;
            font-weight: 600;
            color: var(--muted);
            cursor: pointer;
            border-bottom: 3px solid transparent;
            margin-bottom: -2px;
            transition: color .2s, border-color .2s;
            white-space: nowrap;
            flex-shrink: 0;
            -webkit-user-select: none;
            user-select: none;
        }
        .tab-btn .t-icon {
            width: 28px; height: 28px;
            border-radius: 7px;
            display: flex; align-items: center; justify-content: center;
            font-size: .84rem;
            background: #e8edf8;
            color: var(--muted);
            transition: background .2s, color .2s;
            flex-shrink: 0;
        }
        .tab-btn:hover { color: var(--navy); }
        .tab-btn:hover .t-icon { background: #dce8ff; color: var(--accent); }
        .tab-btn.active { color: var(--accent); border-bottom-color: var(--accent); }
        .tab-btn.active .t-icon { background: #dce8ff; color: var(--accent); }

        .tab-count {
            background: var(--danger);
            color: #fff;
            font-size: .62rem;
            font-weight: 800;
            border-radius: 999px;
            padding: 2px 7px;
            line-height: 1.4;
            font-family: 'JetBrains Mono', monospace;
        }
        .tab-btn.active .tab-count { background: var(--accent); }

        /* ── Tab Panes ── */
        .tab-pane { display: none; }
        .tab-pane.active { display: block; }
        .tab-body { padding: var(--tab-body-p); }

        /* ══════════════════════════════════════════
           FORM STYLES
        ══════════════════════════════════════════ */
        .form-label {
            font-size: .78rem;
            font-weight: 700;
            color: var(--navy);
            text-transform: uppercase;
            letter-spacing: .5px;
            margin-bottom: .4rem;
        }
        .form-control, .form-select {
            border: 1.5px solid var(--border);
            border-radius: 10px;
            font-size: .9rem;
            padding: .65rem 1rem;
            color: var(--text);
            background: #fafcff;
            transition: border-color .2s, box-shadow .2s;
            font-family: 'Outfit', sans-serif;
            width: 100%;
            /* Better touch target */
            min-height: 44px;
        }
        .form-control:focus, .form-select:focus {
            border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(45,139,255,.12);
            background: #fff;
            outline: none;
        }
        textarea.form-control { resize: vertical; min-height: 110px; }

        .form-section-title {
            font-size: .7rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: var(--muted);
            padding-bottom: .6rem;
            border-bottom: 1.5px dashed var(--border);
            margin-bottom: 1.2rem;
            display: flex;
            align-items: center;
            gap: .5rem;
        }

        /* Priority radio cards */
        .priority-group {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: .65rem;
        }
        .priority-item input[type="radio"] { display: none; }
        .priority-label {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 5px;
            padding: .75rem .4rem;
            border: 2px solid var(--border);
            border-radius: 12px;
            cursor: pointer;
            font-size: .8rem;
            font-weight: 600;
            transition: all .18s;
            background: #fafcff;
            text-align: center;
            min-height: 80px;
            justify-content: center;
            /* Better touch target */
            -webkit-user-select: none;
            user-select: none;
        }
        .priority-label .pi-icon { font-size: 1.3rem; }
        .priority-item.p-low    input:checked + .priority-label { border-color:#28c76f; color:#1a7a42; background:#f0fff6; }
        .priority-item.p-medium input:checked + .priority-label { border-color:#ff9f43; color:#a05a00; background:#fff8ee; }
        .priority-item.p-high   input:checked + .priority-label { border-color:#ff6b6b; color:#a02020; background:#fff3f3; }
        .priority-item.p-crit   input:checked + .priority-label { border-color:#ff4d6d; color:#8b0020; background:#fff0f3; box-shadow:0 0 0 3px rgba(255,77,109,.1); }

        /* Equipment chips */
        .equip-type-group {
            display: flex;
            flex-wrap: wrap;
            gap: .4rem;
            margin-bottom: .6rem;
        }
        .equip-chip {
            background: var(--surface);
            border: 1.5px solid var(--border);
            border-radius: 8px;
            padding: 5px 11px;
            font-size: .77rem;
            font-weight: 600;
            cursor: pointer;
            color: var(--muted);
            transition: all .15s;
            -webkit-user-select: none;
            user-select: none;
            /* Touch-friendly */
            min-height: 34px;
            display: flex;
            align-items: center;
        }
        .equip-chip:hover, .equip-chip.active {
            background: #eef4ff;
            border-color: var(--accent);
            color: var(--accent);
        }

        /* Buttons */
        .btn-submit {
            background: linear-gradient(135deg, var(--accent) 0%, #1a6fd4 100%);
            color: #fff;
            border: none;
            border-radius: 12px;
            padding: .8rem 1.8rem;
            font-size: .92rem;
            font-weight: 700;
            font-family: 'Outfit', sans-serif;
            transition: transform .2s, box-shadow .2s;
            box-shadow: 0 6px 20px rgba(45,139,255,.35);
            min-height: 46px;
            cursor: pointer;
        }
        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 28px rgba(45,139,255,.45);
            color: #fff;
        }
        .btn-clear {
            background: #f0f4fb;
            color: var(--muted);
            border: 1.5px solid var(--border);
            border-radius: 12px;
            padding: .78rem 1.2rem;
            font-size: .88rem;
            font-weight: 600;
            font-family: 'Outfit', sans-serif;
            transition: all .2s;
            min-height: 46px;
            cursor: pointer;
        }
        .btn-clear:hover { background: var(--border); color: var(--text); }

        /* ══════════════════════════════════════════
           FILTER BAR
        ══════════════════════════════════════════ */
        .filter-bar {
            background: #f7faff;
            border: 1.5px solid var(--border);
            border-radius: 12px;
            padding: 1rem;
            margin-bottom: 1.2rem;
            display: flex;
            gap: .75rem;
            flex-wrap: wrap;
            align-items: flex-end;
        }
        .filter-bar .form-control,
        .filter-bar .form-select { font-size: .85rem; padding: .5rem .9rem; min-height: 42px; }
        .filter-bar label { font-size: .73rem; }

        .filter-search-wrap { flex: 2 1 200px; min-width: 0; }
        .filter-status-wrap  { flex: 1 1 130px; min-width: 0; }
        .filter-prio-wrap    { flex: 1 1 120px; min-width: 0; }
        .filter-actions      { display: flex; gap: .5rem; align-items: flex-end; flex-shrink: 0; }

        .btn-filter {
            background: var(--navy);
            color: #fff;
            border: none;
            border-radius: 10px;
            padding: .55rem 1.1rem;
            font-size: .84rem;
            font-weight: 600;
            font-family: 'Outfit', sans-serif;
            transition: background .2s;
            min-height: 42px;
            cursor: pointer;
            white-space: nowrap;
        }
        .btn-filter:hover { background: var(--navy-soft); color: #fff; }

        .btn-reset-filter {
            background: #fff;
            color: var(--muted);
            border: 1.5px solid var(--border);
            border-radius: 10px;
            padding: .52rem .9rem;
            font-size: .84rem;
            font-weight: 600;
            font-family: 'Outfit', sans-serif;
            transition: all .2s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            min-height: 42px;
            white-space: nowrap;
        }
        .btn-reset-filter:hover { background: var(--border); color: var(--text); }

        /* ══════════════════════════════════════════
           INPUT GROUP (search)
        ══════════════════════════════════════════ */
        .input-group-search {
            display: flex;
            align-items: stretch;
        }
        .input-group-search .search-icon-wrap {
            background: #fff;
            border: 1.5px solid var(--border);
            border-right: none;
            border-radius: 10px 0 0 10px;
            padding: 0 .8rem;
            display: flex;
            align-items: center;
            color: var(--muted);
            flex-shrink: 0;
        }
        .input-group-search .form-control {
            border-left: none;
            border-radius: 0 10px 10px 0;
        }
        .input-group-search .form-control:focus {
            border-left: none;
        }

        /* ══════════════════════════════════════════
           TABLE
        ══════════════════════════════════════════ */
        .table-scroll-wrap {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            border-radius: 12px;
            border: 1px solid var(--border);
        }
        .requests-table {
            width: 100%;
            min-width: 700px;
            border-collapse: separate;
            border-spacing: 0;
            font-size: .875rem;
        }
        .requests-table thead th {
            background: var(--navy);
            color: rgba(255,255,255,.72);
            font-size: .68rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .8px;
            padding: .85rem 1rem;
            border: none;
            white-space: nowrap;
        }
        .requests-table thead th:first-child { border-radius: 10px 0 0 0; }
        .requests-table thead th:last-child  { border-radius: 0 10px 0 0; }
        .requests-table tbody tr {
            border-bottom: 1px solid var(--border);
            transition: background .15s;
        }
        .requests-table tbody tr:hover { background: #f4f8ff; }
        .requests-table tbody tr:last-child { border-bottom: none; }
        .requests-table tbody td {
            padding: .85rem 1rem;
            vertical-align: middle;
        }

        /* Mobile card view for table rows */
        .mobile-ticket-list { display: none; }
        .mobile-ticket-card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 1rem;
            margin-bottom: .75rem;
            box-shadow: var(--shadow);
        }
        .mtc-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: .5rem;
            margin-bottom: .75rem;
            flex-wrap: wrap;
        }
        .mtc-body { display: flex; flex-direction: column; gap: .45rem; }
        .mtc-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: .5rem;
            font-size: .83rem;
        }
        .mtc-label {
            font-size: .7rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .5px;
            color: var(--muted);
            flex-shrink: 0;
        }
        .mtc-value { text-align: right; word-break: break-word; }
        .mtc-issue {
            background: var(--surface);
            border-radius: 8px;
            padding: .5rem .7rem;
            font-size: .8rem;
            color: var(--text);
            margin-top: .4rem;
            line-height: 1.5;
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        /* Ticket chip */
        .ticket-chip {
            font-family: 'JetBrains Mono', monospace;
            font-size: .72rem;
            font-weight: 600;
            background: var(--surface);
            border: 1.5px solid var(--border);
            color: var(--navy-soft);
            padding: 3px 9px;
            border-radius: 6px;
            white-space: nowrap;
        }

        /* Status badge */
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 4px 10px;
            border-radius: 999px;
            font-size: .72rem;
            font-weight: 700;
            white-space: nowrap;
        }
        .status-badge.open   { background:#fff8e6; color:#a05a00; border:1.5px solid #ffd700; }
        .status-badge.inprog { background:#e8f3ff; color:#1a4d8c; border:1.5px solid #90bfff; }
        .status-badge.done   { background:#e6faf5; color:#0d6e52; border:1.5px solid #5cd6b0; }

        /* Priority badge */
        .prio-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 3px 9px;
            border-radius: 6px;
            font-size: .7rem;
            font-weight: 700;
            white-space: nowrap;
        }
        .prio-badge.low      { background:#f0fff6; color:#1a7a42; }
        .prio-badge.medium   { background:#fff8ee; color:#a05a00; }
        .prio-badge.high     { background:#fff3f3; color:#a02020; }
        .prio-badge.critical { background:#fff0f3; color:#8b0020; }

        /* Confidence bar */
        .conf-bar-wrap { min-width: 72px; }
        .conf-bar {
            height: 5px;
            border-radius: 99px;
            background: #e2e9f5;
            overflow: hidden;
        }
        .conf-bar-fill {
            height: 100%;
            border-radius: 99px;
            background: linear-gradient(90deg, #2d8bff, #00c9a7);
        }
        .conf-num {
            font-size: .68rem;
            color: var(--muted);
            font-family: 'JetBrains Mono', monospace;
            margin-top: 2px;
        }

        /* Empty state */
        .empty-state {
            padding: 3.5rem 1.5rem;
            text-align: center;
        }
        .empty-state .es-icon { font-size: 3rem; color: #cdd8ee; margin-bottom: .8rem; }
        .empty-state h6 { color: var(--muted); font-weight: 600; margin-bottom: .3rem; }
        .empty-state p  { color: var(--muted); font-size: .86rem; margin: 0; }

        .records-label {
            font-size: .8rem;
            color: var(--muted);
            font-weight: 500;
        }

        /* ══════════════════════════════════════════
           REQUESTOR DISPLAY CARD
        ══════════════════════════════════════════ */
        .requestor-display {
            display: flex;
            align-items: center;
            gap: .8rem;
            background: linear-gradient(135deg, #f0f6ff, #e8f0ff);
            border: 1.5px solid #c5d8ff;
            border-radius: 12px;
            padding: .75rem 1rem;
        }
        .requestor-avatar {
            width: 40px; height: 40px;
            background: linear-gradient(135deg, var(--accent), #1a6fd4);
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-size: .95rem;
            font-weight: 800;
            color: #fff;
            flex-shrink: 0;
            box-shadow: 0 3px 10px rgba(45,139,255,.35);
        }
        .requestor-name {
            font-size: .92rem;
            font-weight: 700;
            color: var(--navy);
            line-height: 1.2;
        }
        .requestor-meta {
            font-size: .73rem;
            color: var(--muted);
            margin-top: 3px;
            font-weight: 500;
        }

        /* ══════════════════════════════════════════
           FORM ACTIONS
        ══════════════════════════════════════════ */
        .form-actions {
            display: flex;
            justify-content: flex-end;
            gap: .75rem;
            align-items: center;
            padding-top: 1.2rem;
            border-top: 1.5px dashed var(--border);
            flex-wrap: wrap;
        }

        /* ══════════════════════════════════════════
           RESPONSIVE — TABLET (≤ 991px)
           Sidebar still visible at 250px
        ══════════════════════════════════════════ */
        @media (max-width: 991px) {
            :root {
                --page-px: 1.25rem;
                --hero-py: 2rem;
                --tab-body-p: 1.5rem;
            }

            .stat-grid {
                grid-template-columns: repeat(3, 1fr);
            }

            .priority-group {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        /* ══════════════════════════════════════════
           RESPONSIVE — LARGE MOBILE (≤ 767px)
           billing_sidebar.php sidebar shrinks to 200px
        ══════════════════════════════════════════ */
        @media (max-width: 768px) {
            body {
                /* Match sidebar width at this breakpoint: 200px */
                margin-left: 200px;
            }
            body.sidebar-collapsed {
                margin-left: 0;
            }

            :root {
                --page-px: 1rem;
                --hero-py: 1.5rem;
                --tab-body-p: 1rem;
                --card-p: 1rem;
            }

            /* Hero stacks vertically */
            .hero-content {
                flex-direction: column !important;
                align-items: flex-start !important;
                gap: 1rem !important;
            }
            .hero-right {
                width: 100%;
                display: flex;
                flex-direction: row !important;
                align-items: center;
                gap: .5rem;
                flex-wrap: wrap;
            }
            .hero-right .d-flex.flex-column {
                flex-direction: row !important;
                flex-wrap: wrap;
                gap: .5rem;
            }
            .page-hero h1 { font-size: 1.35rem; }

            /* Stat grid: 2 columns */
            .stat-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: .65rem;
            }
            .stat-card:last-child {
                grid-column: span 2;
                max-width: 50%;
                margin: 0 auto;
                width: 100%;
            }
            .stat-num { font-size: 1.5rem; }

            .tab-btn { padding: .75rem .7rem; font-size: .8rem; gap: .35rem; }
            .tab-btn .t-icon { width: 24px; height: 24px; font-size: .78rem; }

            /* Hide desktop table, show mobile cards */
            .table-desktop { display: none !important; }
            .mobile-ticket-list { display: block; }

            /* Filter bar stacks */
            .filter-bar { flex-direction: column; gap: .6rem; }
            .filter-search-wrap,
            .filter-status-wrap,
            .filter-prio-wrap { flex: 1 1 100%; }
            .filter-actions { width: 100%; justify-content: stretch; }
            .btn-filter, .btn-reset-filter { flex: 1; justify-content: center; }

            /* Form actions stack */
            .form-actions { flex-direction: column; align-items: stretch; }
            .form-actions .btn-submit,
            .form-actions .btn-clear { width: 100%; justify-content: center; text-align: center; }

            /* Priority group */
            .priority-group { grid-template-columns: repeat(2, 1fr); }
            .priority-label { min-height: 72px; font-size: .78rem; padding: .65rem .3rem; }

            .equip-chip { font-size: .74rem; padding: 4px 9px; }
            .requestor-display { flex-wrap: wrap; }
        }

        /* ══════════════════════════════════════════
           RESPONSIVE — SMALL MOBILE (≤ 479px)
           Sidebar is typically closed/overlayed at this size.
           billing_sidebar.php: toggle at top:10px, smaller padding
        ══════════════════════════════════════════ */
        @media (max-width: 479px) {
            :root { --page-px: .75rem; }

            /* Sidebar likely closed on very small screens; start with 0 margin
               and let the JS toggle handle it */
            body {
                margin-left: 0;
            }

            .page-wrap {
                /* toggle button smaller on ≤480px: top:10px, so keep ~50px clearance */
                padding-top: 55px;
            }

            .page-hero h1 { font-size: 1.2rem; }
            .page-hero p { font-size: .8rem; }
            .hero-icon { width: 42px; height: 42px; font-size: 1.2rem; border-radius: 12px; }

            .stat-grid { gap: .5rem; }
            .stat-card { padding: .8rem .8rem; gap: .65rem; }
            .stat-icon { width: 36px; height: 36px; font-size: 1rem; border-radius: 9px; }
            .stat-num { font-size: 1.3rem; }
            .stat-lbl { font-size: .66rem; }

            /* Tab text hidden — icon only on very small screens */
            .tab-btn > span:not(.t-icon):not(.tab-count) { display: none; }

            .form-control, .form-select { font-size: .86rem; padding: .6rem .85rem; }
            textarea.form-control { min-height: 90px; }

            .priority-label { min-height: 65px; }
            .priority-label small { display: none; }

            .mtc-header { flex-direction: column; gap: .4rem; }
            .mtc-row { font-size: .78rem; }
        }

        /* ══════════════════════════════════════════
           SAFE AREA (iPhone notch/home indicator)
        ══════════════════════════════════════════ */
        @supports (padding: env(safe-area-inset-bottom)) {
            .page-wrap {
                padding-bottom: calc(3rem + env(safe-area-inset-bottom));
            }
        }
    </style>
</head>
<body>

<div class="main-sidebar">
    <?php include 'billing_sidebar.php'; ?>
</div>

<div class="page-wrap">

    <!-- ══════════════════════════
         HERO
    ══════════════════════════ -->
    <div class="page-hero">
        <div class="hero-content d-flex justify-content-between align-items-start gap-3">
            <!-- Left -->
            <div class="hero-left">
                <div class="hero-icon"><i class="bi bi-tools"></i></div>
                <h1>Repair Request System</h1>
                <p>Submit and track equipment &amp; network repair requests across hospital departments.</p>
            </div>
            <!-- Right -->
            <div class="hero-right d-flex flex-column align-items-end gap-2">
                <span class="hero-badge">
                    <i class="bi bi-circle-fill text-success" style="font-size:.45rem"></i>&nbsp;System Online
                </span>
    
                </span>
                <span class="hero-badge"><i class="bi bi-calendar3"></i>&nbsp;<?= date('M d, Y') ?></span>
            </div>
        </div>
    </div>

    <!-- ══════════════════════════
         STAT CARDS
    ══════════════════════════ -->
    <div class="stat-grid">
        <div class="stat-card total">
            <div class="stat-icon"><i class="bi bi-ticket-detailed"></i></div>
            <div>
                <div class="stat-num"><?= $stats['total'] ?? 0 ?></div>
                <div class="stat-lbl">My Tickets</div>
            </div>
        </div>
        <div class="stat-card open">
            <div class="stat-icon"><i class="bi bi-hourglass-split"></i></div>
            <div>
                <div class="stat-num"><?= $stats['open_count'] ?? 0 ?></div>
                <div class="stat-lbl">Open</div>
            </div>
        </div>
        <div class="stat-card inprog">
            <div class="stat-icon"><i class="bi bi-gear-wide-connected"></i></div>
            <div>
                <div class="stat-num"><?= $stats['inprog_count'] ?? 0 ?></div>
                <div class="stat-lbl">In Progress</div>
            </div>
        </div>
        <div class="stat-card done">
            <div class="stat-icon"><i class="bi bi-check-circle"></i></div>
            <div>
                <div class="stat-num"><?= $stats['done_count'] ?? 0 ?></div>
                <div class="stat-lbl">Completed</div>
            </div>
        </div>
        <div class="stat-card crit">
            <div class="stat-icon"><i class="bi bi-exclamation-triangle"></i></div>
            <div>
                <div class="stat-num"><?= $stats['critical_count'] ?? 0 ?></div>
                <div class="stat-lbl">Critical</div>
            </div>
        </div>
    </div>

    <!-- ══════════════════════════
         ALERT MESSAGES
    ══════════════════════════ -->
    <?php if ($success_msg): ?>
        <div class="alert-success-custom" id="successAlert">
            <i class="bi bi-check-circle-fill fs-5 flex-shrink-0"></i>
            <span><?= $success_msg ?></span>
        </div>
    <?php elseif ($error_msg): ?>
        <div class="alert-error-custom">
            <i class="bi bi-x-circle-fill fs-5 flex-shrink-0"></i>
            <span><?= htmlspecialchars($error_msg) ?></span>
        </div>
    <?php endif; ?>

    <!-- ══════════════════════════
         TAB CONTAINER
    ══════════════════════════ -->
    <div class="tab-container">

        <!-- Tab Navigation -->
        <div class="tab-nav" role="tablist">
            <button class="tab-btn <?= $active_tab === 'new' ? 'active' : '' ?>"
                    onclick="switchTab('new', this)" type="button"
                    role="tab" aria-selected="<?= $active_tab === 'new' ? 'true' : 'false' ?>">
                <span class="t-icon"><i class="bi bi-plus-circle"></i></span>
                <span>New Request</span>
            </button>
            <button class="tab-btn <?= $active_tab === 'tickets' ? 'active' : '' ?>"
                    onclick="switchTab('tickets', this)" type="button"
                    role="tab" aria-selected="<?= $active_tab === 'tickets' ? 'true' : 'false' ?>">
                <span class="t-icon"><i class="bi bi-list-check"></i></span>
                <span>Repair Tickets</span>
                <?php if (($stats['total'] ?? 0) > 0): ?>
                    <span class="tab-count"><?= $stats['total'] ?></span>
                <?php endif; ?>
            </button>
        </div>

        <!-- ═══════════════════════════
             TAB 1 — NEW REQUEST
        ════════════════════════════ -->
        <div class="tab-pane <?= $active_tab === 'new' ? 'active' : '' ?>" id="tab-new" role="tabpanel">
            <div class="tab-body">
                <form method="POST" action="" id="repairForm" novalidate>
                    <input type="hidden" name="submit_request" value="1">

                    <!-- Section: Requestor -->
                    <div class="form-section-title">
                        <i class="bi bi-person-badge"></i> Requestor Information
                    </div>
                    <div class="row g-3 mb-4">
                        <div class="col-12 col-md-6">
                            <label class="form-label"><i class="bi bi-person me-1"></i>Requestor Name</label>
                            <div class="requestor-display">
                                <div class="requestor-avatar">
                                    <?= strtoupper(substr($logged_user_name, 0, 1)) ?>
                                </div>
                                <div style="min-width:0;">
                                    <div class="requestor-name"><?= htmlspecialchars($logged_user_name) ?></div>
                                    <div class="requestor-meta">
                                        <i class="bi bi-person-badge me-1"></i>ID: <?= $user_id ?>
                                        <?php if (!empty($user['role'])): ?>
                                            &nbsp;·&nbsp;<i class="bi bi-shield me-1"></i><?= htmlspecialchars($user['role']) ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <input type="hidden" name="user_id"   value="<?= $user_id ?>">
                            <input type="hidden" name="user_name" value="<?= htmlspecialchars($logged_user_name) ?>">
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label"><i class="bi bi-building me-1"></i>Department / Location <span class="text-danger">*</span></label>
                            <select name="location" class="form-select" required>
                                <option value="" disabled <?= empty($_POST['location']) && empty($logged_user_dept) ? 'selected' : '' ?>>— Select Department —</option>
                                <?php foreach ($departments as $dept): ?>
                                    <?php
                                        $sel = '';
                                        if (!empty($_POST['location']) && $_POST['location'] === $dept) $sel = 'selected';
                                        elseif (empty($_POST['location']) && $logged_user_dept === $dept) $sel = 'selected';
                                    ?>
                                    <option value="<?= htmlspecialchars($dept) ?>" <?= $sel ?>><?= htmlspecialchars($dept) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <!-- Section: Equipment & Issue -->
                    <div class="form-section-title">
                        <i class="bi bi-cpu"></i> Equipment &amp; Issue Details
                    </div>
                    <div class="row g-3 mb-4">
                        <div class="col-12">
                            <label class="form-label"><i class="bi bi-tools me-1"></i>Equipment / Asset <span class="text-danger">*</span></label>
                            <div class="equip-type-group" id="equipChips">
                                <?php
                                $equipTypes = [
                                    'Computer / Desktop','Laptop','Printer','Network / Wi-Fi',
                                    'Medical Monitor','Ventilator','IV Pump','Centrifuge',
                                    'Server / Switch','CCTV / Camera','Telephone','UPS / Power',
                                ];
                                foreach ($equipTypes as $et): ?>
                                    <span class="equip-chip"
                                          onclick="fillEquip(this, '<?= htmlspecialchars($et, ENT_QUOTES) ?>')"><?= htmlspecialchars($et) ?></span>
                                <?php endforeach; ?>
                            </div>
                            <input type="text" name="equipment" id="equipInput" class="form-control"
                                   placeholder="Tap a chip above or type equipment name"
                                   value="<?= htmlspecialchars($_POST['equipment'] ?? '') ?>" required
                                   autocomplete="off">
                        </div>
                        <div class="col-12">
                            <label class="form-label"><i class="bi bi-chat-text me-1"></i>Issue Description <span class="text-danger">*</span></label>
                            <textarea name="issue" class="form-control" rows="4"
                                      placeholder="Describe the problem in detail — what happened, when it started, any error messages seen, etc."
                                      required><?= htmlspecialchars($_POST['issue'] ?? '') ?></textarea>
                            <div class="d-flex justify-content-end mt-1">
                                <small class="text-muted" id="issueCount">0 characters</small>
                            </div>
                        </div>
                    </div>

                    <!-- Section: Priority -->
                    <div class="form-section-title">
                        <i class="bi bi-flag"></i> Priority Level
                    </div>
                    <div class="mb-4">
                        <div class="priority-group">
                            <div class="priority-item p-low">
                                <input type="radio" name="priority" id="prio_low" value="Low"
                                    <?= (($_POST['priority'] ?? 'Low') === 'Low') ? 'checked' : '' ?>>
                                <label class="priority-label" for="prio_low">
                                    <span class="pi-icon">🟢</span>
                                    <span>Low</span>
                                    <small style="font-weight:400;font-size:.7rem;opacity:.7">Non-urgent</small>
                                </label>
                            </div>
                            <div class="priority-item p-medium">
                                <input type="radio" name="priority" id="prio_med" value="Medium"
                                    <?= (($_POST['priority'] ?? '') === 'Medium') ? 'checked' : '' ?>>
                                <label class="priority-label" for="prio_med">
                                    <span class="pi-icon">🟡</span>
                                    <span>Medium</span>
                                    <small style="font-weight:400;font-size:.7rem;opacity:.7">Within 48 hrs</small>
                                </label>
                            </div>
                            <div class="priority-item p-high">
                                <input type="radio" name="priority" id="prio_high" value="High"
                                    <?= (($_POST['priority'] ?? '') === 'High') ? 'checked' : '' ?>>
                                <label class="priority-label" for="prio_high">
                                    <span class="pi-icon">🔴</span>
                                    <span>High</span>
                                    <small style="font-weight:400;font-size:.7rem;opacity:.7">Within 24 hrs</small>
                                </label>
                            </div>
                            <div class="priority-item p-crit">
                                <input type="radio" name="priority" id="prio_crit" value="Critical"
                                    <?= (($_POST['priority'] ?? '') === 'Critical') ? 'checked' : '' ?>>
                                <label class="priority-label" for="prio_crit">
                                    <span class="pi-icon">🚨</span>
                                    <span>Critical</span>
                                    <small style="font-weight:400;font-size:.7rem;opacity:.7">Immediate</small>
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- Form Actions -->
                    <div class="form-actions">
                        <button type="reset" class="btn-clear btn" onclick="clearChips()">
                            <i class="bi bi-arrow-counterclockwise me-1"></i> Clear Form
                        </button>
                        <button type="submit" class="btn-submit btn">
                            <i class="bi bi-send me-2"></i> Submit Repair Request
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- ═══════════════════════════
             TAB 2 — REPAIR TICKETS
        ════════════════════════════ -->
        <div class="tab-pane <?= $active_tab === 'tickets' ? 'active' : '' ?>" id="tab-tickets" role="tabpanel">
            <div class="tab-body">

                <!-- Filter Bar -->
                <form method="GET" action="">
                    <input type="hidden" name="tab" value="tickets">
                    <div class="filter-bar">
                        <div class="filter-search-wrap">
                            <label class="form-label">Search</label>
                            <div class="input-group-search">
                                <span class="search-icon-wrap"><i class="bi bi-search"></i></span>
                                <input type="text" name="search" class="form-control"
                                       placeholder="Ticket no, equipment, location…"
                                       value="<?= htmlspecialchars($search) ?>"
                                       autocomplete="off">
                            </div>
                        </div>
                        <div class="filter-status-wrap">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-select">
                                <option value="all"         <?= $filter_status === 'all'          ? 'selected' : '' ?>>All Statuses</option>
                                <option value="Open"        <?= $filter_status === 'Open'         ? 'selected' : '' ?>>Open</option>
                                <option value="In Progress" <?= $filter_status === 'In Progress'  ? 'selected' : '' ?>>In Progress</option>
                                <option value="Completed"   <?= $filter_status === 'Completed'    ? 'selected' : '' ?>>Completed</option>
                            </select>
                        </div>
                        <div class="filter-prio-wrap">
                            <label class="form-label">Priority</label>
                            <select name="priority" class="form-select">
                                <option value="all"      <?= $filter_priority === 'all'       ? 'selected' : '' ?>>All Priorities</option>
                                <option value="Low"      <?= $filter_priority === 'Low'       ? 'selected' : '' ?>>Low</option>
                                <option value="Medium"   <?= $filter_priority === 'Medium'    ? 'selected' : '' ?>>Medium</option>
                                <option value="High"     <?= $filter_priority === 'High'      ? 'selected' : '' ?>>High</option>
                                <option value="Critical" <?= $filter_priority === 'Critical'  ? 'selected' : '' ?>>Critical</option>
                            </select>
                        </div>
                        <div class="filter-actions">
                            <button type="submit" class="btn-filter btn">
                                <i class="bi bi-funnel me-1"></i>Filter
                            </button>
                            <a href="<?= $_SERVER['PHP_SELF'] ?>" class="btn-reset-filter">
                                <i class="bi bi-x-lg"></i>&nbsp;Reset
                            </a>
                        </div>
                    </div>
                </form>

                <!-- Records count -->
                <div class="d-flex justify-content-between align-items-center mb-2 px-1">
                    <span class="records-label">
                        <i class="bi bi-table me-1"></i>
                        <?= count($requests) ?> ticket<?= count($requests) !== 1 ? 's' : '' ?> found
                    </span>
                </div>

                <?php if (empty($requests)): ?>
                    <div class="empty-state">
                        <div class="es-icon"><i class="bi bi-inbox"></i></div>
                        <h6>No Repair Requests Found</h6>
                        <p>You haven't submitted any repair tickets yet, or none match your current filter.</p>
                    </div>

                <?php else: ?>

                    <!-- ── DESKTOP TABLE (hidden on mobile) ── -->
                    <div class="table-scroll-wrap table-desktop">
                        <table class="requests-table">
                            <thead>
                                <tr>
                                    <th><i class="bi bi-hash me-1"></i>Ticket</th>
                                    <th><i class="bi bi-building me-1"></i>Department</th>
                                    <th><i class="bi bi-cpu me-1"></i>Equipment</th>
                                    <th><i class="bi bi-chat-dots me-1"></i>Issue</th>
                                    <th><i class="bi bi-flag me-1"></i>Priority</th>
                                    <th><i class="bi bi-circle me-1"></i>Status</th>
                                    <th><i class="bi bi-bar-chart me-1"></i>Conf.</th>
                                    <th><i class="bi bi-clock me-1"></i>Submitted</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($requests as $req): ?>
                                    <?php
                                        $statusClass = match($req['status']) {
                                            'Open'        => 'open',
                                            'In Progress' => 'inprog',
                                            'Completed'   => 'done',
                                            default       => 'open'
                                        };
                                        $prioClass = match($req['priority']) {
                                            'Low'      => 'low',
                                            'Medium'   => 'medium',
                                            'High'     => 'high',
                                            'Critical' => 'critical',
                                            default    => 'low'
                                        };
                                        $statusIcon = match($req['status']) {
                                            'Open'        => '⏳',
                                            'In Progress' => '⚙️',
                                            'Completed'   => '✅',
                                            default       => '⏳'
                                        };
                                        $prioIcon = match($req['priority']) {
                                            'Low'      => '🟢',
                                            'Medium'   => '🟡',
                                            'High'     => '🔴',
                                            'Critical' => '🚨',
                                            default    => '🟢'
                                        };
                                        $conf = intval($req['confidence_score'] ?? 0);
                                    ?>
                                    <tr>
                                        <td><span class="ticket-chip"><?= htmlspecialchars($req['ticket_no'] ?? '—') ?></span></td>
                                        <td style="font-size:.82rem; color:var(--muted); max-width:150px;">
                                            <?= htmlspecialchars($req['location']) ?>
                                        </td>
                                        <td style="font-weight:600; color:var(--navy-soft); font-size:.87rem;">
                                            <?= htmlspecialchars($req['equipment']) ?>
                                        </td>
                                        <td style="max-width:200px;">
                                            <span style="display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;font-size:.82rem;">
                                                <?= htmlspecialchars($req['issue']) ?>
                                            </span>
                                            <?php if (!empty($req['remarks'])): ?>
                                                <small class="d-block mt-1" style="color:var(--muted); font-size:.7rem;">
                                                    <i class="bi bi-chat-left-text me-1"></i><?= htmlspecialchars(substr($req['remarks'], 0, 55)) ?>…
                                                </small>
                                            <?php endif; ?>
                                        </td>
                                        <td><span class="prio-badge <?= $prioClass ?>"><?= $prioIcon ?> <?= htmlspecialchars($req['priority']) ?></span></td>
                                        <td><span class="status-badge <?= $statusClass ?>"><?= $statusIcon ?> <?= htmlspecialchars($req['status']) ?></span></td>
                                        <td>
                                            <div class="conf-bar-wrap">
                                                <div class="conf-bar"><div class="conf-bar-fill" style="width:<?= $conf ?>%"></div></div>
                                                <div class="conf-num"><?= $conf ?>%</div>
                                            </div>
                                        </td>
                                        <td style="white-space:nowrap; font-size:.78rem; color:var(--muted);">
                                            <i class="bi bi-calendar3 me-1"></i><?= date('M d, Y', strtotime($req['created_at'])) ?>
                                            <br><small><?= date('h:i A', strtotime($req['created_at'])) ?></small>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- ── MOBILE CARDS (shown on mobile) ── -->
                    <div class="mobile-ticket-list">
                        <?php foreach ($requests as $req): ?>
                            <?php
                                $statusClass = match($req['status']) { 'Open'=>'open','In Progress'=>'inprog','Completed'=>'done',default=>'open' };
                                $prioClass   = match($req['priority']) { 'Low'=>'low','Medium'=>'medium','High'=>'high','Critical'=>'critical',default=>'low' };
                                $statusIcon  = match($req['status']) { 'Open'=>'⏳','In Progress'=>'⚙️','Completed'=>'✅',default=>'⏳' };
                                $prioIcon    = match($req['priority']) { 'Low'=>'🟢','Medium'=>'🟡','High'=>'🔴','Critical'=>'🚨',default=>'🟢' };
                                $conf        = intval($req['confidence_score'] ?? 0);
                            ?>
                            <div class="mobile-ticket-card">
                                <div class="mtc-header">
                                    <span class="ticket-chip"><?= htmlspecialchars($req['ticket_no'] ?? '—') ?></span>
                                    <div class="d-flex gap-2 flex-wrap">
                                        <span class="prio-badge <?= $prioClass ?>"><?= $prioIcon ?> <?= htmlspecialchars($req['priority']) ?></span>
                                        <span class="status-badge <?= $statusClass ?>"><?= $statusIcon ?> <?= htmlspecialchars($req['status']) ?></span>
                                    </div>
                                </div>
                                <div class="mtc-body">
                                    <div class="mtc-row">
                                        <span class="mtc-label"><i class="bi bi-cpu me-1"></i>Equipment</span>
                                        <span class="mtc-value" style="font-weight:600;color:var(--navy-soft);"><?= htmlspecialchars($req['equipment']) ?></span>
                                    </div>
                                    <div class="mtc-row">
                                        <span class="mtc-label"><i class="bi bi-building me-1"></i>Department</span>
                                        <span class="mtc-value" style="color:var(--muted);"><?= htmlspecialchars($req['location']) ?></span>
                                    </div>
                                    <div class="mtc-row">
                                        <span class="mtc-label"><i class="bi bi-clock me-1"></i>Submitted</span>
                                        <span class="mtc-value" style="color:var(--muted);"><?= date('M d, Y · h:i A', strtotime($req['created_at'])) ?></span>
                                    </div>
                                    <div>
                                        <div class="mtc-label mb-1"><i class="bi bi-chat-dots me-1"></i>Issue</div>
                                        <div class="mtc-issue"><?= htmlspecialchars($req['issue']) ?></div>
                                    </div>
                                    <div class="mtc-row">
                                        <span class="mtc-label"><i class="bi bi-bar-chart me-1"></i>Confidence</span>
                                        <div class="d-flex align-items-center gap-2">
                                            <div class="conf-bar" style="width:70px;"><div class="conf-bar-fill" style="width:<?= $conf ?>%"></div></div>
                                            <span class="conf-num"><?= $conf ?>%</span>
                                        </div>
                                    </div>
                                    <?php if (!empty($req['remarks'])): ?>
                                    <div class="mtc-row">
                                        <span class="mtc-label"><i class="bi bi-chat-left-text me-1"></i>Remarks</span>
                                        <span class="mtc-value" style="color:var(--muted);font-size:.78rem;"><?= htmlspecialchars(substr($req['remarks'], 0, 80)) ?><?= strlen($req['remarks']) > 80 ? '…' : '' ?></span>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                <?php endif; ?>

            </div><!-- end tab-body -->
        </div><!-- end tab-tickets -->

    </div><!-- end tab-container -->

</div><!-- end page-wrap -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
// ── Tab switching ──
function switchTab(tabId, btn) {
    document.querySelectorAll('.tab-pane').forEach(p => p.classList.remove('active'));
    document.querySelectorAll('.tab-btn').forEach(b => {
        b.classList.remove('active');
        b.setAttribute('aria-selected', 'false');
    });
    document.getElementById('tab-' + tabId).classList.add('active');
    btn.classList.add('active');
    btn.setAttribute('aria-selected', 'true');

    // Smooth scroll to top of tab container on mobile
    if (window.innerWidth < 768) {
        const container = document.querySelector('.tab-container');
        if (container) {
            const y = container.getBoundingClientRect().top + window.scrollY - 70;
            window.scrollTo({ top: y, behavior: 'smooth' });
        }
    }
}

// ── Equipment chip fill ──
function fillEquip(el, name) {
    document.getElementById('equipInput').value = name;
    document.querySelectorAll('.equip-chip').forEach(c => c.classList.remove('active'));
    el.classList.add('active');
}

// ── Clear chips on form reset ──
function clearChips() {
    document.querySelectorAll('.equip-chip').forEach(c => c.classList.remove('active'));
    const ic = document.getElementById('issueCount');
    if (ic) ic.textContent = '0 characters';
}

// ── Issue character counter ──
const issueTA = document.querySelector('textarea[name="issue"]');
const issueCount = document.getElementById('issueCount');
if (issueTA && issueCount) {
    const update = () => issueCount.textContent = issueTA.value.length + ' characters';
    issueTA.addEventListener('input', update);
    update();
}

// ── Highlight active chip on page reload ──
(function() {
    const val = document.getElementById('equipInput')?.value?.trim();
    if (val) {
        document.querySelectorAll('.equip-chip').forEach(c => {
            if (c.textContent.trim() === val) c.classList.add('active');
        });
    }
})();

// ── Auto-dismiss success alert after 6s ──
const successAlert = document.getElementById('successAlert');
if (successAlert) {
    setTimeout(() => {
        successAlert.style.transition = 'opacity .6s, max-height .6s, margin .6s, padding .6s';
        successAlert.style.opacity = '0';
        successAlert.style.maxHeight = '0';
        successAlert.style.marginBottom = '0';
        successAlert.style.padding = '0';
        setTimeout(() => successAlert.remove(), 650);
    }, 6000);
}


(function syncSidebarMargin() {
    const sidebar = document.getElementById('mySidebar');
    const toggleBtn = document.getElementById('sidebarToggle');
    if (!sidebar) return;

  
    function getSidebarWidth() {
        if (window.innerWidth <= 480)  return 0;   // hidden by default on very small
        if (window.innerWidth <= 768)  return 200; // 200px on tablet/large mobile
        return 250;                                  // 250px default
    }

    function applyMargin() {
        const isClosed = sidebar.classList.contains('closed');
        const width    = isClosed ? 0 : getSidebarWidth();
        document.body.style.marginLeft = width + 'px';
    }

    // Initial state
    applyMargin();

    // Watch for sidebar class changes triggered by billing_sidebar.php's JS
    const observer = new MutationObserver(applyMargin);
    observer.observe(sidebar, { attributes: true, attributeFilter: ['class'] });

    // Also update on window resize (sidebar width breakpoint changes)
    window.addEventListener('resize', applyMargin);
})();
</script>
</body>
</html>