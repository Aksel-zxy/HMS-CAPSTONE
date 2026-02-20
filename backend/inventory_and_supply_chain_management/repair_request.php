<?php
include '../../SQL/config.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

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
$active_tab  = 'tickets'; // default tab

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_request'])) {
    $active_tab = 'new'; // stay on form tab on error, switch to tickets on success
    $user_id   = intval($_POST['user_id']   ?? 0);
    $user_name = trim($_POST['user_name']   ?? '');
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
        $active_tab  = 'tickets'; // switch to tickets tab after success
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

// If filter GET params present, show tickets tab
if (!empty($_GET)) $active_tab = 'tickets';

$where  = [];
$params = [];

if ($filter_status !== 'all') {
    $where[]  = "status = ?";
    $params[] = $filter_status;
}
if ($filter_priority !== 'all') {
    $where[]  = "priority = ?";
    $params[] = $filter_priority;
}
if ($search !== '') {
    $where[]  = "(ticket_no LIKE ? OR user_name LIKE ? OR equipment LIKE ? OR location LIKE ?)";
    $like     = "%{$search}%";
    array_push($params, $like, $like, $like, $like);
}

$whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';
$stmt = $pdo->prepare("SELECT * FROM repair_requests {$whereSQL} ORDER BY created_at DESC");
$stmt->execute($params);
$requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Stats
$stmt_stats = $pdo->query("
    SELECT
        COUNT(*) AS total,
        SUM(status = 'Open') AS open_count,
        SUM(status = 'In Progress') AS inprog_count,
        SUM(status = 'Completed') AS done_count,
        SUM(priority = 'Critical') AS critical_count
    FROM repair_requests
");
$stats = $stmt_stats->fetch(PDO::FETCH_ASSOC);

// Hospital departments list
$departments = [
    'Emergency Department',
    'Intensive Care Unit (ICU)',
    'Operating Theater',
    'Radiology & Imaging',
    'Laboratory',
    'Pharmacy',
    'Cardiology',
    'Orthopedics',
    'Pediatrics',
    'Obstetrics & Gynecology',
    'Neurology',
    'Oncology',
    'Nephrology',
    'Pulmonology',
    'General Surgery',
    'Internal Medicine',
    'Outpatient Department (OPD)',
    'Medical Records',
    'Human Resources',
    'Finance & Accounting',
    'Administration',
    'IT Department',
    'Maintenance & Engineering',
    'Nursing Station — Floor 1',
    'Nursing Station — Floor 2',
    'Nursing Station — Floor 3',
    'Inventory and Supply Chain Management',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Repair Request System — HMS Capstone</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@400;600&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
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
        }
        *, *::before, *::after { box-sizing: border-box; }
        body {
            font-family: 'Outfit', sans-serif;
            background: var(--surface);
            color: var(--text);
            min-height: 100vh;
        }
        .main-sidebar { z-index: 100; }

        /* ── PAGE WRAPPER ── */
        .page-wrap {
            padding: 0 2rem 3rem 2rem;
            max-width: 1400px;
            margin: 0 auto;
        }

        /* ── HERO ── */
        .page-hero {
            background: linear-gradient(135deg, var(--navy) 0%, var(--navy-soft) 60%, #1e4d8c 100%);
            border-radius: 0 0 var(--radius) var(--radius);
            padding: 2.5rem 2.5rem 2rem;
            margin-bottom: 2rem;
            position: relative;
            overflow: hidden;
        }
        .page-hero::before {
            content: '';
            position: absolute;
            inset: 0;
            background: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.03'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
        }
        .page-hero::after {
            content: '';
            position: absolute;
            right: -80px; top: -80px;
            width: 340px; height: 340px;
            background: radial-gradient(circle, rgba(45,139,255,.18) 0%, transparent 70%);
            border-radius: 50%;
        }
        .hero-icon {
            width: 56px; height: 56px;
            background: rgba(45,139,255,.22);
            border: 1.5px solid rgba(45,139,255,.4);
            border-radius: 16px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.6rem;
            color: #a8d4ff;
            margin-bottom: 1rem;
        }
        .page-hero h1 {
            font-size: 1.9rem;
            font-weight: 800;
            color: #fff;
            margin: 0 0 .3rem;
            letter-spacing: -.5px;
        }
        .page-hero p {
            color: rgba(255,255,255,.6);
            font-size: .93rem;
            margin: 0;
        }
        .hero-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: rgba(255,255,255,.1);
            border: 1px solid rgba(255,255,255,.15);
            border-radius: 999px;
            padding: 5px 14px;
            font-size: .78rem;
            color: rgba(255,255,255,.8);
            font-weight: 500;
        }

        /* ── STAT CARDS ── */
        .stat-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }
        .stat-card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 1.2rem 1.4rem;
            box-shadow: var(--shadow);
            display: flex;
            align-items: center;
            gap: 1rem;
            transition: transform .2s, box-shadow .2s;
            position: relative;
            overflow: hidden;
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
            width: 46px; height: 46px;
            border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.3rem;
            flex-shrink: 0;
        }
        .stat-card.total .stat-icon  { background:#eef4ff; color:var(--accent); }
        .stat-card.open .stat-icon   { background:#fff8e6; color:var(--gold); }
        .stat-card.inprog .stat-icon { background:#e8f3ff; color:var(--accent); }
        .stat-card.done .stat-icon   { background:#e6faf5; color:var(--accent2); }
        .stat-card.crit .stat-icon   { background:#fff0f3; color:var(--danger); }
        .stat-num {
            font-size: 1.75rem;
            font-weight: 800;
            color: var(--navy);
            line-height: 1;
            font-family: 'JetBrains Mono', monospace;
        }
        .stat-lbl {
            font-size: .78rem;
            color: var(--muted);
            font-weight: 500;
            margin-top: 3px;
            text-transform: uppercase;
            letter-spacing: .5px;
        }

        /* ── ALERT BANNERS ── */
        .alert-success-custom {
            background: linear-gradient(135deg, #e6faf5, #f0fff8);
            border: 1.5px solid #5cd6b0;
            border-radius: var(--radius);
            color: #0d5e45;
            padding: 1rem 1.4rem;
            font-size: .9rem;
            display: flex;
            align-items: center;
            gap: .75rem;
            margin-bottom: 1.2rem;
        }
        .alert-error-custom {
            background: #fff0f3;
            border: 1.5px solid #ff4d6d;
            border-radius: var(--radius);
            color: #7a0020;
            padding: 1rem 1.4rem;
            font-size: .9rem;
            display: flex;
            align-items: center;
            gap: .75rem;
            margin-bottom: 1.2rem;
        }

        /* ── TAB CONTAINER ── */
        .tab-container {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            overflow: hidden;
        }

        /* ── TAB NAV ── */
        .tab-nav {
            display: flex;
            background: #f4f8ff;
            border-bottom: 2px solid var(--border);
            padding: 0 1.5rem;
            gap: 0;
        }
        .tab-btn {
            display: flex;
            align-items: center;
            gap: .55rem;
            padding: 1rem 1.4rem;
            border: none;
            background: transparent;
            font-family: 'Outfit', sans-serif;
            font-size: .9rem;
            font-weight: 600;
            color: var(--muted);
            cursor: pointer;
            border-bottom: 3px solid transparent;
            margin-bottom: -2px;
            transition: color .2s, border-color .2s;
            white-space: nowrap;
            position: relative;
        }
        .tab-btn .t-icon {
            width: 30px; height: 30px;
            border-radius: 8px;
            display: flex; align-items: center; justify-content: center;
            font-size: .88rem;
            background: #e8edf8;
            color: var(--muted);
            transition: background .2s, color .2s;
        }
        .tab-btn:hover { color: var(--navy); }
        .tab-btn:hover .t-icon { background: #dce8ff; color: var(--accent); }
        .tab-btn.active {
            color: var(--accent);
            border-bottom-color: var(--accent);
        }
        .tab-btn.active .t-icon {
            background: #dce8ff;
            color: var(--accent);
        }
        .tab-count {
            background: var(--danger);
            color: #fff;
            font-size: .65rem;
            font-weight: 800;
            border-radius: 999px;
            padding: 2px 7px;
            line-height: 1.4;
            font-family: 'JetBrains Mono', monospace;
            margin-left: 2px;
        }
        .tab-btn.active .tab-count { background: var(--accent); }

        /* ── TAB PANES ── */
        .tab-pane { display: none; }
        .tab-pane.active { display: block; }
        .tab-body { padding: 2rem; }

        /* ── FORM STYLES ── */
        .form-label {
            font-size: .8rem;
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
            padding: .6rem 1rem;
            color: var(--text);
            background: #fafcff;
            transition: border-color .2s, box-shadow .2s;
            font-family: 'Outfit', sans-serif;
        }
        .form-control:focus, .form-select:focus {
            border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(45,139,255,.12);
            background: #fff;
            outline: none;
        }
        textarea.form-control { resize: vertical; min-height: 110px; }

        /* Form section divider */
        .form-section-title {
            font-size: .72rem;
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
        .priority-group { display: flex; gap: .75rem; flex-wrap: wrap; }
        .priority-item { flex: 1; min-width: 110px; }
        .priority-item input[type="radio"] { display: none; }
        .priority-label {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 6px;
            padding: .8rem .5rem;
            border: 2px solid var(--border);
            border-radius: 12px;
            cursor: pointer;
            font-size: .82rem;
            font-weight: 600;
            transition: all .18s;
            background: #fafcff;
            text-align: center;
        }
        .priority-label .pi-icon { font-size: 1.4rem; }
        .priority-item.p-low    input:checked + .priority-label { border-color:#28c76f; color:#1a7a42; background:#f0fff6; }
        .priority-item.p-medium input:checked + .priority-label { border-color:#ff9f43; color:#a05a00; background:#fff8ee; }
        .priority-item.p-high   input:checked + .priority-label { border-color:#ff6b6b; color:#a02020; background:#fff3f3; }
        .priority-item.p-crit   input:checked + .priority-label { border-color:#ff4d6d; color:#8b0020; background:#fff0f3; box-shadow:0 0 0 3px rgba(255,77,109,.1); }

        /* Equipment chips */
        .equip-type-group { display: flex; flex-wrap: wrap; gap: .45rem; margin-bottom: .6rem; }
        .equip-chip {
            background: var(--surface);
            border: 1.5px solid var(--border);
            border-radius: 8px;
            padding: 4px 12px;
            font-size: .78rem;
            font-weight: 600;
            cursor: pointer;
            color: var(--muted);
            transition: all .15s;
            user-select: none;
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
            padding: .75rem 2.5rem;
            font-size: .95rem;
            font-weight: 700;
            font-family: 'Outfit', sans-serif;
            transition: transform .2s, box-shadow .2s;
            box-shadow: 0 6px 20px rgba(45,139,255,.35);
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
            padding: .73rem 1.4rem;
            font-size: .9rem;
            font-weight: 600;
            font-family: 'Outfit', sans-serif;
            transition: all .2s;
        }
        .btn-clear:hover { background: var(--border); color: var(--text); }

        /* ── FILTER BAR ── */
        .filter-bar {
            background: #f7faff;
            border: 1.5px solid var(--border);
            border-radius: 12px;
            padding: 1rem 1.2rem;
            margin-bottom: 1.4rem;
            display: flex;
            gap: .75rem;
            flex-wrap: wrap;
            align-items: flex-end;
        }
        .filter-bar .form-control,
        .filter-bar .form-select { font-size: .85rem; padding: .5rem .9rem; }
        .filter-bar label { font-size: .75rem; }
        .btn-filter {
            background: var(--navy);
            color: #fff;
            border: none;
            border-radius: 10px;
            padding: .52rem 1.2rem;
            font-size: .85rem;
            font-weight: 600;
            font-family: 'Outfit', sans-serif;
            transition: background .2s;
        }
        .btn-filter:hover { background: var(--navy-soft); color: #fff; }
        .btn-reset-filter {
            background: #fff;
            color: var(--muted);
            border: 1.5px solid var(--border);
            border-radius: 10px;
            padding: .5rem 1rem;
            font-size: .85rem;
            font-weight: 600;
            font-family: 'Outfit', sans-serif;
            transition: all .2s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        .btn-reset-filter:hover { background: var(--border); color: var(--text); }

        /* ── TABLE ── */
        .requests-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            font-size: .875rem;
        }
        .requests-table thead th {
            background: var(--navy);
            color: rgba(255,255,255,.72);
            font-size: .71rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .8px;
            padding: .85rem 1.1rem;
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
            padding: .9rem 1.1rem;
            vertical-align: middle;
        }

        /* Ticket chip */
        .ticket-chip {
            font-family: 'JetBrains Mono', monospace;
            font-size: .74rem;
            font-weight: 600;
            background: var(--surface);
            border: 1.5px solid var(--border);
            color: var(--navy-soft);
            padding: 3px 10px;
            border-radius: 6px;
            white-space: nowrap;
        }

        /* Status badge */
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 4px 12px;
            border-radius: 999px;
            font-size: .74rem;
            font-weight: 700;
        }
        .status-badge.open   { background:#fff8e6; color:#a05a00; border:1.5px solid #ffd700; }
        .status-badge.inprog { background:#e8f3ff; color:#1a4d8c; border:1.5px solid #90bfff; }
        .status-badge.done   { background:#e6faf5; color:#0d6e52; border:1.5px solid #5cd6b0; }

        /* Priority badge */
        .prio-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 3px 10px;
            border-radius: 6px;
            font-size: .72rem;
            font-weight: 700;
        }
        .prio-badge.low      { background:#f0fff6; color:#1a7a42; }
        .prio-badge.medium   { background:#fff8ee; color:#a05a00; }
        .prio-badge.high     { background:#fff3f3; color:#a02020; }
        .prio-badge.critical { background:#fff0f3; color:#8b0020; }

        /* Confidence bar */
        .conf-bar-wrap { width: 80px; }
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
            font-size: .7rem;
            color: var(--muted);
            font-family: 'JetBrains Mono', monospace;
            margin-top: 2px;
        }

        /* Empty state */
        .empty-state {
            padding: 4rem 2rem;
            text-align: center;
        }
        .empty-state .es-icon { font-size: 3.5rem; color: #cdd8ee; margin-bottom: 1rem; }
        .empty-state h6 { color: var(--muted); font-weight: 600; }
        .empty-state p  { color: var(--muted); font-size: .88rem; margin: 0; }

        /* Table records label */
        .records-label {
            font-size: .8rem;
            color: var(--muted);
            font-weight: 500;
        }

        @media (max-width: 768px) {
            .page-wrap { padding: 0 1rem 2rem; }
            .stat-grid { grid-template-columns: repeat(2, 1fr); }
            .tab-btn { padding: .8rem .8rem; font-size: .82rem; }
            .tab-body { padding: 1.2rem; }
        }
    </style>
</head>
<body>

<div class="main-sidebar">
    <?php include 'inventory_sidebar.php'; ?>
</div>

<div class="page-wrap">

    <!-- ── HERO ── -->
    <div class="page-hero">
        <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
            <div>
                <div class="hero-icon"><i class="bi bi-tools"></i></div>
                <h1>Repair Request System</h1>
                <p>Submit and track equipment &amp; network repair requests across hospital departments.</p>
            </div>
            <div class="d-flex flex-column align-items-end gap-2">
                <span class="hero-badge"><i class="bi bi-circle-fill text-success" style="font-size:.5rem"></i>&nbsp;System Online</span>
                <span class="hero-badge"><i class="bi bi-calendar3"></i>&nbsp;<?= date('F d, Y') ?></span>
            </div>
        </div>
    </div>

    <!-- ── STAT CARDS ── -->
    <div class="stat-grid">
        <div class="stat-card total">
            <div class="stat-icon"><i class="bi bi-ticket-detailed"></i></div>
            <div>
                <div class="stat-num"><?= $stats['total'] ?? 0 ?></div>
                <div class="stat-lbl">Total Tickets</div>
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

    <!-- ── ALERT MESSAGES ── -->
    <?php if ($success_msg): ?>
        <div class="alert-success-custom" id="successAlert">
            <i class="bi bi-check-circle-fill fs-5"></i>
            <span><?= $success_msg ?></span>
        </div>
    <?php elseif ($error_msg): ?>
        <div class="alert-error-custom">
            <i class="bi bi-x-circle-fill fs-5"></i>
            <span><?= htmlspecialchars($error_msg) ?></span>
        </div>
    <?php endif; ?>

    <!-- ══════════════════════════
         TAB CONTAINER
    ══════════════════════════ -->
    <div class="tab-container">

        <!-- Tab Navigation -->
        <div class="tab-nav">
            <button class="tab-btn <?= $active_tab === 'new' ? 'active' : '' ?>"
                    onclick="switchTab('new', this)" type="button">
                <span class="t-icon"><i class="bi bi-plus-circle"></i></span>
                New Repair Request
            </button>
            <button class="tab-btn <?= $active_tab === 'tickets' ? 'active' : '' ?>"
                    onclick="switchTab('tickets', this)" type="button">
                <span class="t-icon"><i class="bi bi-list-check"></i></span>
                Repair Tickets
                <?php if (($stats['total'] ?? 0) > 0): ?>
                    <span class="tab-count"><?= $stats['total'] ?></span>
                <?php endif; ?>
            </button>
        </div>

        <!-- ═══════════════════
             TAB 1: NEW REQUEST
        ════════════════════ -->
        <div class="tab-pane <?= $active_tab === 'new' ? 'active' : '' ?>" id="tab-new">
            <div class="tab-body">
                <form method="POST" action="" id="repairForm" novalidate>
                    <input type="hidden" name="submit_request" value="1">
                    <input type="hidden" name="user_id" value="<?= $_SESSION['user_id'] ?? 0 ?>">

                    <!-- Section: Requestor Info -->
                    <div class="form-section-title">
                        <i class="bi bi-person-badge"></i> Requestor Information
                    </div>
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label class="form-label"><i class="bi bi-person me-1"></i>Requestor Name <span class="text-danger">*</span></label>
                            <input type="text" name="user_name" class="form-control"
                                   placeholder="e.g. Juan dela Cruz" required
                                   value="<?= htmlspecialchars($_POST['user_name'] ?? '') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label"><i class="bi bi-building me-1"></i>Department / Location <span class="text-danger">*</span></label>
                            <select name="location" class="form-select" required>
                                <option value="" disabled selected>— Select Department —</option>
                                <?php foreach ($departments as $dept): ?>
                                    <option value="<?= htmlspecialchars($dept) ?>"
                                        <?= (($_POST['location'] ?? '') === $dept) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($dept) ?>
                                    </option>
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
                                          onclick="fillEquip(this, '<?= htmlspecialchars($et) ?>')"><?= htmlspecialchars($et) ?></span>
                                <?php endforeach; ?>
                            </div>
                            <input type="text" name="equipment" id="equipInput" class="form-control"
                                   placeholder="Click a chip above or type equipment name"
                                   value="<?= htmlspecialchars($_POST['equipment'] ?? '') ?>" required>
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
                    <div class="row g-3 mb-4">
                        <div class="col-12">
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
                    </div>

                    <!-- Actions -->
                    <div class="d-flex justify-content-end gap-3 align-items-center pt-2"
                         style="border-top: 1.5px dashed var(--border);">
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

        <!-- ═══════════════════════
             TAB 2: REPAIR TICKETS
        ════════════════════════ -->
        <div class="tab-pane <?= $active_tab === 'tickets' ? 'active' : '' ?>" id="tab-tickets">
            <div class="tab-body">

                <!-- Filter Bar -->
                <form method="GET" action="">
                    <input type="hidden" name="tab" value="tickets">
                    <div class="filter-bar">
                        <div style="flex:2; min-width:200px;">
                            <label class="form-label">Search</label>
                            <div class="input-group">
                                <span class="input-group-text bg-white"
                                      style="border:1.5px solid var(--border); border-right:none; border-radius:10px 0 0 10px; padding:.5rem .9rem;">
                                    <i class="bi bi-search" style="color:var(--muted);"></i>
                                </span>
                                <input type="text" name="search" class="form-control"
                                       style="border-left:none; border-radius:0 10px 10px 0;"
                                       placeholder="Ticket no, name, equipment, location…"
                                       value="<?= htmlspecialchars($search) ?>">
                            </div>
                        </div>
                        <div style="min-width:150px;">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-select">
                                <option value="all"         <?= $filter_status === 'all'          ? 'selected' : '' ?>>All Statuses</option>
                                <option value="Open"        <?= $filter_status === 'Open'         ? 'selected' : '' ?>>Open</option>
                                <option value="In Progress" <?= $filter_status === 'In Progress'  ? 'selected' : '' ?>>In Progress</option>
                                <option value="Completed"   <?= $filter_status === 'Completed'    ? 'selected' : '' ?>>Completed</option>
                            </select>
                        </div>
                        <div style="min-width:140px;">
                            <label class="form-label">Priority</label>
                            <select name="priority" class="form-select">
                                <option value="all"      <?= $filter_priority === 'all'       ? 'selected' : '' ?>>All Priorities</option>
                                <option value="Low"      <?= $filter_priority === 'Low'       ? 'selected' : '' ?>>Low</option>
                                <option value="Medium"   <?= $filter_priority === 'Medium'    ? 'selected' : '' ?>>Medium</option>
                                <option value="High"     <?= $filter_priority === 'High'      ? 'selected' : '' ?>>High</option>
                                <option value="Critical" <?= $filter_priority === 'Critical'  ? 'selected' : '' ?>>Critical</option>
                            </select>
                        </div>
                        <div class="d-flex gap-2 align-items-end">
                            <button type="submit" class="btn-filter btn">
                                <i class="bi bi-funnel me-1"></i>Filter
                            </button>
                            <a href="<?= $_SERVER['PHP_SELF'] ?>" class="btn-reset-filter">
                                <i class="bi bi-x-lg"></i> Reset
                            </a>
                        </div>
                    </div>
                </form>

                <!-- Records label -->
                <div class="d-flex justify-content-between align-items-center mb-2 px-1">
                    <span class="records-label">
                        <i class="bi bi-table me-1"></i>
                        <?= count($requests) ?> record<?= count($requests) !== 1 ? 's' : '' ?> found
                    </span>
                </div>

                <!-- Table -->
                <div style="overflow-x:auto; border-radius:12px; border:1px solid var(--border);">
                    <?php if (empty($requests)): ?>
                        <div class="empty-state">
                            <div class="es-icon"><i class="bi bi-inbox"></i></div>
                            <h6>No Repair Requests Found</h6>
                            <p>No tickets match your current search or filter.</p>
                        </div>
                    <?php else: ?>
                        <table class="requests-table">
                            <thead>
                                <tr>
                                    <th><i class="bi bi-hash me-1"></i>Ticket</th>
                                    <th><i class="bi bi-person me-1"></i>Requestor</th>
                                    <th><i class="bi bi-building me-1"></i>Department</th>
                                    <th><i class="bi bi-cpu me-1"></i>Equipment</th>
                                    <th><i class="bi bi-chat-dots me-1"></i>Issue</th>
                                    <th><i class="bi bi-flag me-1"></i>Priority</th>
                                    <th><i class="bi bi-circle me-1"></i>Status</th>
                                    <th><i class="bi bi-bar-chart me-1"></i>Confidence</th>
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
                                        <td>
                                            <span class="ticket-chip"><?= htmlspecialchars($req['ticket_no'] ?? '—') ?></span>
                                        </td>
                                        <td>
                                            <div style="font-weight:600; color:var(--navy); font-size:.88rem;">
                                                <?= htmlspecialchars($req['user_name']) ?>
                                            </div>
                                            <?php if ($req['user_id']): ?>
                                                <small style="color:var(--muted); font-size:.7rem;">ID: <?= $req['user_id'] ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td style="font-size:.83rem; color:var(--muted); max-width:160px;">
                                            <?= htmlspecialchars($req['location']) ?>
                                        </td>
                                        <td style="font-weight:600; color:var(--navy-soft); font-size:.88rem;">
                                            <?= htmlspecialchars($req['equipment']) ?>
                                        </td>
                                        <td style="max-width:200px;">
                                            <span style="display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;font-size:.83rem;">
                                                <?= htmlspecialchars($req['issue']) ?>
                                            </span>
                                            <?php if (!empty($req['remarks'])): ?>
                                                <small class="d-block mt-1" style="color:var(--muted); font-size:.71rem;">
                                                    <i class="bi bi-chat-left-text me-1"></i><?= htmlspecialchars(substr($req['remarks'], 0, 55)) ?>…
                                                </small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="prio-badge <?= $prioClass ?>">
                                                <?= $prioIcon ?> <?= htmlspecialchars($req['priority']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="status-badge <?= $statusClass ?>">
                                                <?= $statusIcon ?> <?= htmlspecialchars($req['status']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="conf-bar-wrap">
                                                <div class="conf-bar">
                                                    <div class="conf-bar-fill" style="width:<?= $conf ?>%"></div>
                                                </div>
                                                <div class="conf-num"><?= $conf ?>%</div>
                                            </div>
                                        </td>
                                        <td style="white-space:nowrap; font-size:.8rem; color:var(--muted);">
                                            <i class="bi bi-calendar3 me-1"></i><?= date('M d, Y', strtotime($req['created_at'])) ?>
                                            <br><small><?= date('h:i A', strtotime($req['created_at'])) ?></small>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>

            </div>
        </div>
        <!-- end tab-tickets -->

    </div><!-- end tab-container -->

</div><!-- end page-wrap -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
// ── Tab switching ──
function switchTab(tabId, btn) {
    document.querySelectorAll('.tab-pane').forEach(p => p.classList.remove('active'));
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    document.getElementById('tab-' + tabId).classList.add('active');
    btn.classList.add('active');
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
    document.getElementById('issueCount').textContent = '0 characters';
}

// ── Issue character counter ──
const issueTA = document.querySelector('textarea[name="issue"]');
const issueCount = document.getElementById('issueCount');
if (issueTA) {
    issueTA.addEventListener('input', () => {
        issueCount.textContent = issueTA.value.length + ' characters';
    });
    // Init on page load (in case of POST back)
    issueCount.textContent = issueTA.value.length + ' characters';
}

// ── Highlight active chip on page reload ──
(function() {
    const val = document.getElementById('equipInput')?.value;
    if (val) {
        document.querySelectorAll('.equip-chip').forEach(c => {
            if (c.textContent.trim() === val) c.classList.add('active');
        });
    }
})();

// ── Auto-dismiss success alert ──
const successAlert = document.getElementById('successAlert');
if (successAlert) {
    setTimeout(() => {
        successAlert.style.transition = 'opacity .6s';
        successAlert.style.opacity = '0';
        setTimeout(() => successAlert.remove(), 600);
    }, 6000);
}
</script>
</body>
</html>