<?php
include '../../../SQL/config.php';

if (!isset($_SESSION['profession']) || $_SESSION['profession'] !== 'Laboratorist') {
    header('Location: login.php');
    exit();
}

if (!isset($_SESSION['employee_id'])) {
    echo "User ID is not set in session.";
    exit();
}

$employee_id = $_SESSION['employee_id'];

// Fetch logged-in employee info
$query = "SELECT * FROM hr_employees WHERE employee_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $employee_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if (!$user) {
    echo "No user found.";
    exit();
}

// ── STAT CARDS (today, scoped to this laboratorist) ──────────────────────────
$today = date('Y-m-d');

$sql_stats = "SELECT
    COUNT(*) AS total,
    SUM(status = 'Processing') AS processing,
    SUM(status = 'Completed')  AS completed,
    SUM(status = 'Cancelled')  AS cancelled
FROM dl_schedule
WHERE employee_id = ? AND DATE(scheduleDate) = ?";
$stmt2 = $conn->prepare($sql_stats);
$stmt2->bind_param("is", $employee_id, $today);
$stmt2->execute();
$stats = $stmt2->get_result()->fetch_assoc();
$stats['total']      = $stats['total']      ?? 0;
$stats['processing'] = $stats['processing'] ?? 0;
$stats['completed']  = $stats['completed']  ?? 0;
$stats['cancelled']  = $stats['cancelled']  ?? 0;

// ── TODAY'S EXAM QUEUE (all statuses, this laboratorist) ─────────────────────
$sql_queue = "SELECT s.scheduleID, s.patientID, s.scheduleDate, s.scheduleTime,
                     s.serviceName, s.status,
                     p.fname, p.lname
              FROM dl_schedule s
              JOIN patientinfo p ON s.patientID = p.patient_id
              WHERE s.employee_id = ? AND DATE(s.scheduleDate) = ?
              ORDER BY s.scheduleTime ASC";
$stmt3 = $conn->prepare($sql_queue);
$stmt3->bind_param("is", $employee_id, $today);
$stmt3->execute();
$queue_result = $stmt3->get_result();
$queue_rows = [];
while ($r = $queue_result->fetch_assoc()) {
    $queue_rows[] = $r;
}

// ── WEEKLY COMPLETED TESTS (last 7 days, this laboratorist) ──────────────────
$sql_weekly = "SELECT DATE(scheduleDate) AS day, COUNT(*) AS cnt
               FROM dl_schedule
               WHERE employee_id = ?
                 AND status = 'Completed'
                 AND DATE(scheduleDate) BETWEEN DATE_SUB(CURDATE(), INTERVAL 6 DAY) AND CURDATE()
               GROUP BY DATE(scheduleDate)";
$stmt4 = $conn->prepare($sql_weekly);
$stmt4->bind_param("i", $employee_id);
$stmt4->execute();
$weekly_raw = $stmt4->get_result();

$weekly_map = [];
while ($wr = $weekly_raw->fetch_assoc()) {
    $weekly_map[$wr['day']] = (int)$wr['cnt'];
}
$chart_labels = [];
$chart_data   = [];
for ($i = 6; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-$i days"));
    $chart_labels[] = date('D M j', strtotime($d));
    $chart_data[]   = $weekly_map[$d] ?? 0;
}

// ── PENDING (Processing) COUNT NOT YET SUBMITTED ─────────────────────────────
$sql_pending = "SELECT COUNT(*) AS pending
                FROM dl_schedule s
                WHERE s.employee_id = ?
                  AND s.status = 'Processing'
                  AND NOT EXISTS (SELECT 1 FROM dl_lab_cbc  WHERE scheduleID = s.scheduleID)
                  AND NOT EXISTS (SELECT 1 FROM dl_lab_xray WHERE scheduleID = s.scheduleID)
                  AND NOT EXISTS (SELECT 1 FROM dl_lab_mri  WHERE scheduleID = s.scheduleID)
                  AND NOT EXISTS (SELECT 1 FROM dl_lab_ct   WHERE scheduleID = s.scheduleID)";
$stmt5 = $conn->prepare($sql_pending);
$stmt5->bind_param("i", $employee_id);
$stmt5->execute();
$pending_count = $stmt5->get_result()->fetch_assoc()['pending'] ?? 0;
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HMS | Laboratorist Dashboard</title>
    <link rel="shortcut icon" href="../assets/image/favicon.ico" type="image/x-icon">
    <link rel="stylesheet" href="../assets/CSS/bootstrap.min.css">
    <link rel="stylesheet" href="../assets/CSS/user.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        /* ── Dashboard-specific styles ───────────────────────────────────── */
        .dash-wrapper {
            padding: 24px 28px;
        }

        .dash-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 28px;
        }

        .dash-header h1 {
            font-size: 1.55rem;
            font-weight: 700;
            color: #1a2340;
            margin: 0;
        }

        .dash-header .badge-date {
            background: #e8f0fe;
            color: #3b5bdb;
            border-radius: 20px;
            padding: 4px 14px;
            font-size: .78rem;
            font-weight: 600;
        }

        /* Stats cards */
        .stat-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 18px;
            margin-bottom: 28px;
        }

        .stat-card {
            background: #fff;
            border-radius: 14px;
            padding: 22px 20px;
            display: flex;
            align-items: center;
            gap: 16px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, .06);
            transition: transform .2s, box-shadow .2s;
        }

        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(0, 0, 0, .1);
        }

        .stat-icon {
            width: 52px;
            height: 52px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            flex-shrink: 0;
        }

        .stat-icon.blue {
            background: #e8f0fe;
        }

        .stat-icon.yellow {
            background: #fff8e1;
        }

        .stat-icon.green {
            background: #e8f5e9;
        }

        .stat-icon.red {
            background: #fce4ec;
        }

        .stat-info .val {
            font-size: 1.9rem;
            font-weight: 800;
            line-height: 1;
            color: #1a2340;
        }

        .stat-info .lbl {
            font-size: .78rem;
            color: #8a95b0;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: .04em;
            margin-top: 4px;
        }

        /* Content grid */
        .content-grid {
            display: grid;
            grid-template-columns: 3fr 2fr;
            gap: 22px;
        }

        @media (max-width: 900px) {
            .content-grid {
                grid-template-columns: 1fr;
            }
        }

        .panel {
            background: #fff;
            border-radius: 14px;
            padding: 22px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, .06);
        }

        .panel-title {
            font-size: 1rem;
            font-weight: 700;
            color: #1a2340;
            margin-bottom: 4px;
        }

        .panel-sub {
            font-size: .78rem;
            color: #8a95b0;
            margin-bottom: 16px;
        }

        /* Queue table */
        .queue-table {
            width: 100%;
            border-collapse: collapse;
            font-size: .85rem;
        }

        .queue-table thead th {
            background: #f4f6fb;
            color: #5b6987;
            font-weight: 700;
            padding: 9px 12px;
            text-align: left;
            border-bottom: 1px solid #eaecf5;
            font-size: .75rem;
            text-transform: uppercase;
            letter-spacing: .04em;
        }

        .queue-table tbody tr {
            border-bottom: 1px solid #f0f2f8;
            transition: background .15s;
        }

        .queue-table tbody tr:hover {
            background: #f9fafe;
        }

        .queue-table tbody td {
            padding: 10px 12px;
            color: #3d4a6b;
            vertical-align: middle;
        }

        .queue-scroll {
            max-height: 340px;
            overflow-y: auto;
            border-radius: 8px;
        }

        .badge-status {
            padding: 3px 10px;
            border-radius: 20px;
            font-size: .72rem;
            font-weight: 700;
            display: inline-block;
        }

        .badge-status.processing {
            background: #fff8e1;
            color: #f59e0b;
        }

        .badge-status.completed {
            background: #e8f5e9;
            color: #22c55e;
        }

        .badge-status.cancelled {
            background: #fce4ec;
            color: #ef4444;
        }

        .badge-status.pending {
            background: #e8f0fe;
            color: #3b82f6;
        }

        .btn-process {
            font-size: .75rem;
            padding: 4px 12px;
            border-radius: 6px;
            background: #3b5bdb;
            color: #fff;
            border: none;
            text-decoration: none;
            transition: background .2s;
            display: inline-block;
        }

        .btn-process:hover {
            background: #2f4bc9;
            color: #fff;
        }

        /* Quick links */
        .quick-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
            margin-top: 22px;
        }

        .quick-card {
            background: #f4f6fb;
            border-radius: 12px;
            padding: 16px 14px;
            text-align: center;
            text-decoration: none;
            transition: background .2s, transform .2s;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 8px;
        }

        .quick-card:hover {
            background: #e8edf9;
            transform: translateY(-2px);
        }

        .quick-card .qicon {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            background: #fff;
        }

        .quick-card span {
            font-size: .78rem;
            font-weight: 600;
            color: #3d4a6b;
        }

        /* Pending alert banner */
        .pending-banner {
            background: linear-gradient(135deg, #fff8e1 0%, #fffde7 100%);
            border-left: 4px solid #f59e0b;
            border-radius: 10px;
            padding: 14px 18px;
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 24px;
        }

        .pending-banner .pb-icon {
            font-size: 24px;
        }

        .pending-banner .pb-text {
            flex: 1;
        }

        .pending-banner .pb-text strong {
            color: #92400e;
            font-size: .9rem;
            display: block;
        }

        .pending-banner .pb-text small {
            color: #a16207;
            font-size: .78rem;
        }

        .pending-banner .btn-go {
            background: #f59e0b;
            color: #fff;
            border: none;
            border-radius: 8px;
            padding: 8px 16px;
            font-size: .8rem;
            font-weight: 700;
            text-decoration: none;
            transition: background .2s;
        }

        .pending-banner .btn-go:hover {
            background: #d97706;
            color: #fff;
        }

        .d-none {
            display: none !important;
        }
    </style>
</head>

<body>
    <div class="d-flex">

        <!-- ── SIDEBAR ─────────────────────────────────────────────── -->
        <aside id="sidebar" class="sidebar-toggle">
            <div class="sidebar-logo mt-3">
                <img src="../assets/image/logo-dark.png" width="90px" height="20px">
            </div>
            <div class="menu-title">Navigation</div>

            <li class="sidebar-item">
                <a href="user_lab.php" class="sidebar-link">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-cast" viewBox="0 0 16 16">
                        <path d="m7.646 9.354-3.792 3.792a.5.5 0 0 0 .353.854h7.586a.5.5 0 0 0 .354-.854L8.354 9.354a.5.5 0 0 0-.708 0" />
                        <path d="M11.414 11H14.5a.5.5 0 0 0 .5-.5v-7a.5.5 0 0 0-.5-.5h-13a.5.5 0 0 0-.5.5v7a.5.5 0 0 0 .5.5h3.086l-1 1H1.5A1.5 1.5 0 0 1 0 10.5v-7A1.5 1.5 0 0 1 1.5 2h13A1.5 1.5 0 0 1 16 3.5v7a1.5 1.5 0 0 1-1.5 1.5h-2.086z" />
                    </svg>
                    <span style="font-size:18px;">Dashboard</span>
                </a>
            </li>

            <li class="sidebar-item">
                <a href="sample_processing.php" class="sidebar-link">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-person-fill-up" viewBox="0 0 16 16">
                        <path d="M12.5 16a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7m.354-5.854 1.5 1.5a.5.5 0 0 1-.708.708L13 11.707V14.5a.5.5 0 0 1-1 0v-2.793l-.646.647a.5.5 0 0 1-.708-.708l1.5-1.5a.5.5 0 0 1 .708 0M11 5a3 3 0 1 1-6 0 3 3 0 0 1 6 0" />
                        <path d="M2 13c0 1 1 1 1 1h5.256A4.5 4.5 0 0 1 8 12.5a4.5 4.5 0 0 1 1.544-3.393Q8.844 9.002 8 9c-5 0-6 3-6 4" />
                    </svg>
                    <span style="font-size:18px;">Sample Process</span>
                </a>
            </li>

            <li class="sidebar-item">
                <a href="leave_request.php" class="sidebar-link">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-person-walking" viewBox="0 0 16 16">
                        <path d="M9.5 1.5a1.5 1.5 0 1 1-3 0 1.5 1.5 0 0 1 3 0M6.44 3.752A.75.75 0 0 1 7 3.5h1.445c.742 0 1.32.643 1.243 1.38l-.43 4.083a1.8 1.8 0 0 1-.088.395l-.318.906.213.242a.8.8 0 0 1 .114.175l2 4.25a.75.75 0 1 1-1.357.638l-1.956-4.154-1.68-1.921A.75.75 0 0 1 6 8.96l.138-2.613-.435.489-.464 2.786a.75.75 0 1 1-1.48-.246l.5-3a.75.75 0 0 1 .18-.375l2-2.25Z" />
                        <path d="M6.25 11.745v-1.418l1.204 1.375.261.524a.8.8 0 0 1-.12.231l-2.5 3.25a.75.75 0 1 1-1.19-.914zm4.22-4.215-.494-.494.205-1.843.006-.067 1.124 1.124h1.44a.75.75 0 0 1 0 1.5H11a.75.75 0 0 1-.531-.22Z" />
                    </svg>
                    <span style="font-size:18px;">Leave Request</span>
                </a>
            </li>

            <li class="sidebar-item">
                <a href="payslip_viewing.php" class="sidebar-link">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-file-earmark-text-fill" viewBox="0 0 16 16">
                        <path d="M9.293 0H4a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2V4.707A1 1 0 0 0 13.707 4L10 .293A1 1 0 0 0 9.293 0M9.5 3.5v-2l3 3h-2a1 1 0 0 1-1-1M4.5 9a.5.5 0 0 1 0-1h7a.5.5 0 0 1 0 1zM4 10.5a.5.5 0 0 1 .5-.5h7a.5.5 0 0 1 0 1h-7a.5.5 0 0 1-.5-.5m.5 2.5a.5.5 0 0 1 0-1h4a.5.5 0 0 1 0 1z" />
                    </svg>
                    <span style="font-size:18px;">Payslip Viewing</span>
                </a>
            </li>
        </aside>

        <!-- ── MAIN ─────────────────────────────────────────────────── -->
        <div class="main">

            <!-- Topbar -->
            <div class="topbar">
                <div class="toggle">
                    <button class="toggler-btn" type="button">
                        <svg xmlns="http://www.w3.org/2000/svg" width="30px" height="30px" fill="currentColor" class="bi bi-list-ul" viewBox="0 0 16 16">
                            <path fill-rule="evenodd" d="M5 11.5a.5.5 0 0 1 .5-.5h9a.5.5 0 0 1 0 1h-9a.5.5 0 0 1-.5-.5m0-4a.5.5 0 0 1 .5-.5h9a.5.5 0 0 1 0 1h-9a.5.5 0 0 1-.5-.5m0-4a.5.5 0 0 1 .5-.5h9a.5.5 0 0 1 0 1h-9a.5.5 0 0 1-.5-.5m-3 1a1 1 0 1 0 0-2 1 1 0 0 0 0 2m0 4a1 1 0 1 0 0-2 1 1 0 0 0 0 2m0 4a1 1 0 1 0 0-2 1 1 0 0 0 0 2" />
                        </svg>
                    </button>
                </div>
                <div class="logo">
                    <div class="dropdown d-flex align-items-center">
                        <span class="username ml-1 me-2"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></span>
                        <button class="btn dropdown-toggle" type="button" id="dropdownMenuButton" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-person-circle"></i>
                        </button>
                        <ul class="dropdown-menu" aria-labelledby="dropdownMenuButton" style="min-width:200px;padding:10px;border-radius:5px;box-shadow:0 4px 6px rgba(0,0,0,.1);background:#fff;color:#333;">
                            <li style="margin-bottom:8px;font-size:14px;color:#555;">
                                <span>Welcome <strong style="color:#007bff;"><?php echo htmlspecialchars($user['last_name']); ?></strong>!</span>
                            </li>
                            <li>
                                <a class="dropdown-item" href="../../logout.php" style="font-size:14px;color:#007bff;text-decoration:none;padding:8px 12px;border-radius:4px;transition:background .3s;">Logout</a>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- Dashboard body -->
            <div class="dash-wrapper">

                <!-- Header -->
                <div class="dash-header">
                    <h1>🧪 Laboratorist Dashboard</h1>
                    <span class="badge-date"><?php echo date('l, F j, Y'); ?></span>
                </div>

                <!-- Pending-work alert banner -->
                <?php if ($pending_count > 0): ?>
                    <div class="pending-banner">
                        <span class="pb-icon">⚠️</span>
                        <div class="pb-text">
                            <strong><?php echo $pending_count; ?> exam<?php echo $pending_count > 1 ? 's' : ''; ?> still need<?php echo $pending_count === 1 ? 's' : ''; ?> results entered</strong>
                            <small>You have unsubmitted lab results for today's processing queue.</small>
                        </div>
                        <a href="sample_processing.php" class="btn-go">Process Now →</a>
                    </div>
                <?php endif; ?>

                <!-- Stat cards -->
                <div class="stat-grid">
                    <div class="stat-card">
                        <div class="stat-icon blue">📋</div>
                        <div class="stat-info">
                            <div class="val"><?php echo $stats['total']; ?></div>
                            <div class="lbl">Today's Exams</div>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon yellow">⚙️</div>
                        <div class="stat-info">
                            <div class="val"><?php echo $stats['processing']; ?></div>
                            <div class="lbl">In Progress</div>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon green">✅</div>
                        <div class="stat-info">
                            <div class="val"><?php echo $stats['completed']; ?></div>
                            <div class="lbl">Completed</div>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon red">❌</div>
                        <div class="stat-info">
                            <div class="val"><?php echo $stats['cancelled']; ?></div>
                            <div class="lbl">Cancelled</div>
                        </div>
                    </div>
                </div>

                <!-- Main content grid -->
                <div class="content-grid">

                    <!-- LEFT: Today's Queue -->
                    <div class="panel">
                        <div class="panel-title">📅 Today's Exam Queue</div>
                        <div class="panel-sub">All exams scheduled for you today — sorted by time</div>

                        <div class="queue-scroll">
                            <table class="queue-table">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Patient</th>
                                        <th>Test Type</th>
                                        <th>Time</th>
                                        <th>Status</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($queue_rows)): ?>
                                        <tr>
                                            <td colspan="6" style="text-align:center;padding:30px;color:#aab2cc;font-style:italic;">
                                                No exams scheduled for today.
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($queue_rows as $i => $row):
                                            $s = strtolower($row['status']);
                                            if ($s === 'processing')      $badge = 'processing';
                                            elseif ($s === 'completed')   $badge = 'completed';
                                            elseif ($s === 'cancelled')   $badge = 'cancelled';
                                            else                          $badge = 'pending';
                                        ?>
                                            <tr>
                                                <td><?php echo $i + 1; ?></td>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($row['fname'] . ' ' . $row['lname']); ?></strong><br>
                                                    <small style="color:#aab2cc;">#<?php echo $row['patientID']; ?></small>
                                                </td>
                                                <td><?php echo htmlspecialchars($row['serviceName']); ?></td>
                                                <td><?php echo htmlspecialchars(date('h:i A', strtotime($row['scheduleTime']))); ?></td>
                                                <td>
                                                    <span class="badge-status <?php echo $badge; ?>">
                                                        <?php echo htmlspecialchars($row['status']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php if ($s === 'processing'): ?>
                                                        <a href="sample_processing.php" class="btn-process">Process</a>
                                                    <?php else: ?>
                                                        <span style="color:#c0c8dd;font-size:.75rem;">—</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- RIGHT: Chart + Quick Links -->
                    <div style="display:flex;flex-direction:column;gap:22px;">

                        <!-- Weekly chart -->
                        <div class="panel">
                            <div class="panel-title">📊 Weekly Completed Exams</div>
                            <div class="panel-sub">Last 7 days — your completed exams</div>
                            <canvas id="weeklyChart" style="max-height:220px;"></canvas>
                        </div>

                        <!-- Quick links -->
                        <div class="panel">
                            <div class="panel-title">⚡ Quick Actions</div>
                            <div class="panel-sub">Jump to key sections</div>
                            <div class="quick-grid">
                                <a href="sample_processing.php" class="quick-card">
                                    <div class="qicon">🧫</div>
                                    <span>Sample Process</span>
                                </a>
                                <a href="leave_request.php" class="quick-card">
                                    <div class="qicon">🏖️</div>
                                    <span>Leave Request</span>
                                </a>
                                <a href="payslip_viewing.php" class="quick-card">
                                    <div class="qicon">💳</div>
                                    <span>Payslip</span>
                                </a>
                                <!-- <a href="#" class="quick-card" onclick="window.print()">
                                    <div class="qicon">🖨️</div>
                                    <span>Print Page</span>
                                </a> -->
                            </div>
                        </div>

                    </div>
                </div><!-- /content-grid -->

            </div><!-- /dash-wrapper -->

        </div><!-- /main -->
    </div>

    <script>
        // Sidebar toggle
        document.querySelector(".toggler-btn").addEventListener("click", function() {
            document.querySelector("#sidebar").classList.toggle("collapsed");
        });

        // Weekly chart
        const ctx = document.getElementById('weeklyChart').getContext('2d');
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($chart_labels); ?>,
                datasets: [{
                    label: 'Completed Exams',
                    data: <?php echo json_encode($chart_data); ?>,
                    backgroundColor: 'rgba(59, 91, 219, 0.15)',
                    borderColor: '#3b5bdb',
                    borderWidth: 2,
                    borderRadius: 6,
                    borderSkipped: false,
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        callbacks: {
                            label: ctx => ` ${ctx.parsed.y} exam${ctx.parsed.y !== 1 ? 's' : ''}`
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1,
                            precision: 0
                        },
                        grid: {
                            color: '#f0f2f8'
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        }
                    }
                }
            }
        });
    </script>

    <script src="../assets/Bootstrap/all.min.js"></script>
    <script src="../assets/Bootstrap/bootstrap.bundle.min.js"></script>
    <script src="../assets/Bootstrap/fontawesome.min.js"></script>
    <script src="../assets/Bootstrap/jq.js"></script>
</body>

</html>