<?php
include '../../SQL/config.php';

class DoctorDashboard
{
    public $conn;
    public $user;

    public function __construct($conn)
    {
        $this->conn = $conn;
        $this->authenticate();
        $this->fetchUser();
    }

    private function authenticate()
    {
        if (!isset($_SESSION['doctor']) || $_SESSION['doctor'] !== true) {
            header('Location: login.php');
            exit();
        }
        if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
            echo "User ID is not set in session.";
            exit();
        }
    }

    private function fetchUser()
    {
        $query = "SELECT * FROM users WHERE user_id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("i", $_SESSION['user_id']);
        $stmt->execute();
        $result = $stmt->get_result();
        $this->user = $result->fetch_assoc();
        if (!$this->user) {
            echo "No user found.";
            exit();
        }
    }
}

$dashboard = new DoctorDashboard($conn);
$user = $dashboard->user;

$query = "
    SELECT role, COUNT(*) AS total
    FROM hr_employees
    WHERE role IN ('Resident Doctor', 'Chief Resident', 'Non-Resident Doctor', 'Staff Nurse', 'Head Nurse')
    GROUP BY role
";
$result = $conn->query($query);

$doctorCount = 0;
$nurseCount  = 0;

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $role = strtolower($row['role']);
        if (strpos($role, 'doctor') !== false || strpos($role, 'resident') !== false) {
            $doctorCount += $row['total'];
        } elseif (strpos($role, 'nurse') !== false) {
            $nurseCount += $row['total'];
        }
    }
}

// Fetch a top doctor placeholder
$topDoctorQuery = "SELECT first_name, last_name FROM hr_employees WHERE profession = 'Doctor' LIMIT 1";
$topDoctorResult = $conn->query($topDoctorQuery);
$topDoctor = $topDoctorResult->fetch_assoc();
$topDoctorName = $topDoctor ? $topDoctor['first_name'] . ' ' . $topDoctor['last_name'] : 'N/A';
$topDoctorInitials = $topDoctor ? strtoupper(substr($topDoctor['first_name'], 0, 1) . substr($topDoctor['last_name'], 0, 1)) : 'NA';

// Fetch a top nurse placeholder
$topNurseQuery = "SELECT first_name, last_name FROM hr_employees WHERE profession = 'Nurse' LIMIT 1";
$topNurseResult = $conn->query($topNurseQuery);
$topNurse = $topNurseResult->fetch_assoc();
$topNurseName = $topNurse ? $topNurse['first_name'] . ' ' . $topNurse['last_name'] : 'N/A';
$topNurseInitials = $topNurse ? strtoupper(substr($topNurse['first_name'], 0, 1) . substr($topNurse['last_name'], 0, 1)) : 'NA';

// Fetch Attendance Reports
$dailyReportsQuery = "SELECT * FROM daily_attendance_report ORDER BY reportDate DESC LIMIT 10";
$dailyReports = $conn->query($dailyReportsQuery);

$monthlyReportsQuery = "SELECT * FROM month_attendance_report ORDER BY year DESC, month DESC LIMIT 10";
$monthlyReports = $conn->query($monthlyReportsQuery);

$yearlyReportsQuery = "SELECT * FROM year_attendance_report ORDER BY year DESC LIMIT 5";
$yearlyReports = $conn->query($yearlyReportsQuery);

// Fetch Detailed Personnel Logs (Doctors & Nurses)
$detailedLogsQuery = "
    SELECT a.*, e.first_name, e.last_name, e.role, e.profession 
    FROM hr_daily_attendance a
    JOIN hr_employees e ON a.employee_id = e.employee_id
    WHERE e.profession IN ('Doctor', 'Nurse') OR e.role IN ('Resident Doctor', 'Chief Resident', 'Non-Resident Doctor', 'Staff Nurse', 'Head Nurse')
    ORDER BY a.attendance_date DESC, a.time_in DESC
    LIMIT 20
";
$detailedLogs = $conn->query($detailedLogsQuery);

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HMS | Doctor and Nurse Management</title>
    <link rel="shortcut icon" href="assets/image/favicon.ico" type="image/x-icon">
    <link rel="stylesheet" href="assets/CSS/bootstrap.min.css">
    <link rel="stylesheet" href="assets/CSS/super.css">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Outfit', sans-serif;
            background-color: #f4f7fa;
        }
        .dashboard-wrapper {
            animation: fadeIn 0.6s ease-in-out;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        /* Premium Cards */
        .glass-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.04);
            transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
        }
        .glass-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.08);
        }

        /* Vibrant Gradients */
        .gradient-primary {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            color: white;
        }
        .gradient-success {
            background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);
            color: #1a4a38;
        }
        .gradient-warning {
            background: linear-gradient(135deg, #f6d365 0%, #fda085 100%);
        }
        .gradient-info {
            background: linear-gradient(135deg, #a18cd1 0%, #fbc2eb 100%);
        }

        /* Stat Numbers */
        .stat-number {
            font-size: 3.5rem;
            font-weight: 800;
            line-height: 1;
            letter-spacing: -1px;
        }

        /* Icon Wrapper */
        .icon-wrapper {
            width: 55px;
            height: 55px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 16px;
            font-size: 24px;
            background: rgba(255,255,255,0.2);
            backdrop-filter: blur(5px);
        }

        /* Progress Bars */
        .progress-thin {
            height: 8px;
            border-radius: 10px;
            background-color: #edf2f9;
            overflow: visible;
        }
        .progress-bar-glow-primary {
            background: linear-gradient(90deg, #4facfe, #00f2fe);
            box-shadow: 0 0 10px rgba(0, 242, 254, 0.5);
            border-radius: 10px;
            position: relative;
        }
        .progress-bar-glow-primary::after {
            content: '';
            position: absolute;
            right: -6px;
            top: -3px;
            width: 14px;
            height: 14px;
            background: #fff;
            border: 3px solid #00f2fe;
            border-radius: 50%;
            box-shadow: 0 0 8px rgba(0,242,254,0.8);
        }

        .progress-bar-glow-success {
            background: linear-gradient(90deg, #43e97b, #38f9d7);
            box-shadow: 0 0 10px rgba(56, 249, 215, 0.5);
            border-radius: 10px;
            position: relative;
        }
        .progress-bar-glow-success::after {
            content: '';
            position: absolute;
            right: -6px;
            top: -3px;
            width: 14px;
            height: 14px;
            background: #fff;
            border: 3px solid #38f9d7;
            border-radius: 50%;
            box-shadow: 0 0 8px rgba(56,249,215,0.8);
        }

        /* Buttons */
        .btn-modern {
            padding: 12px 28px;
            border-radius: 50px;
            font-weight: 600;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            font-size: 0.85rem;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
            z-index: 1;
            border: none;
        }
        .btn-modern::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0; bottom: 0;
            z-index: -1;
            transition: opacity 0.3s ease;
            opacity: 0;
        }
        .btn-modern:hover::before {
            opacity: 1;
        }
        .btn-modern:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.15);
            color: white;
        }
        .btn-modern-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        .btn-modern-primary::before {
            background: linear-gradient(135deg, #764ba2 0%, #667eea 100%);
        }
        .btn-modern-success {
            background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
            color: white;
        }
        .btn-modern-success::before {
            background: linear-gradient(135deg, #38ef7d 0%, #11998e 100%);
        }

        /* Avatar */
        .avatar-circle {
            width: 56px;
            height: 56px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            font-weight: 700;
            color: white;
            box-shadow: 0 8px 15px rgba(0,0,0,0.1);
            border: 3px solid white;
        }
    </style>
</head>

<body>
    <div class="d-flex">
        <!----- Sidebar ----->
        <aside id="sidebar" class="sidebar-toggle">

            <div class="sidebar-logo mt-3">
                <img src="assets/image/logo-dark.png" width="90px" height="20px">
            </div>

            <div class="menu-title">Navigation</div>

            <!----- Sidebar Navigation ----->

            <li class="sidebar-item">
                <a href="doctor_dashboard.php" class="sidebar-link" data-bs-toggle="#" data-bs-target="#"
                    aria-expanded="false" aria-controls="auth">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-cast" viewBox="0 0 16 16">
                        <path d="m7.646 9.354-3.792 3.792a.5.5 0 0 0 .353.854h7.586a.5.5 0 0 0 .354-.854L8.354 9.354a.5.5 0 0 0-.708 0" />
                        <path d="M11.414 11H14.5a.5.5 0 0 0 .5-.5v-7a.5.5 0 0 0-.5-.5h-13a.5.5 0 0 0-.5.5v7a.5.5 0 0 0 .5.5h3.086l-1 1H1.5A1.5 1.5 0 0 1 0 10.5v-7A1.5 1.5 0 0 1 1.5 2h13A1.5 1.5 0 0 1 16 3.5v7a1.5 1.5 0 0 1-1.5 1.5h-2.086z" />
                    </svg>
                    <span style="font-size: 18px;">Dashboard</span>
                </a>
            </li>

            <li class="sidebar-item">
                <a href="#" class="sidebar-link collapsed has-dropdown" data-bs-toggle="collapse" data-bs-target="#schedule"
                    aria-expanded="true" aria-controls="auth">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-cast" viewBox="0 0 640 512"><!--!Font Awesome Free v7.0.0 by @fontawesome - https://fontawesome.com License - https://fontawesome.com/license/free Copyright 2025 Fonticons, Inc.-->
                        <path d="M320 16a104 104 0 1 1 0 208 104 104 0 1 1 0-208zM96 88a72 72 0 1 1 0 144 72 72 0 1 1 0-144zM0 416c0-70.7 57.3-128 128-128 12.8 0 25.2 1.9 36.9 5.4-32.9 36.8-52.9 85.4-52.9 138.6l0 16c0 11.4 2.4 22.2 6.7 32L32 480c-17.7 0-32-14.3-32-32l0-32zm521.3 64c4.3-9.8 6.7-20.6 6.7-32l0-16c0-53.2-20-101.8-52.9-138.6 11.7-3.5 24.1-5.4 36.9-5.4 70.7 0 128 57.3 128 128l0 32c0 17.7-14.3 32-32 32l-86.7 0zM472 160a72 72 0 1 1 144 0 72 72 0 1 1 -144 0zM160 432c0-88.4 71.6-160 160-160s160 71.6 160 160l0 16c0 17.7-14.3 32-32 32l-256 0c-17.7 0-32-14.3-32-32l0-16z" />
                    </svg>
                    <span style="font-size: 18px;">Scheduling Shifts and Duties</span>
                </a>

                <ul id="schedule" class="sidebar-dropdown list-unstyled collapse" data-bs-parent="#sidebar">
                    <li class="sidebar-item">
                        <a href="scheduling_shifts_and_duties/doctor_shift_scheduling.php" class="sidebar-link">Doctor Shift Scheduling</a>
                    </li>
                    <li class="sidebar-item">
                        <a href="scheduling_shifts_and_duties/nurse_shift_scheduling.php" class="sidebar-link">Nurse Shift Scheduling</a>
                    </li>
                    <li class="sidebar-item">
                        <a href="scheduling_shifts_and_duties/duty_assignment.php" class="sidebar-link">Duty Assignment</a>
                    </li>
                    <li class="sidebar-item">
                        <a href="scheduling_shifts_and_duties/schedule_calendar.php" class="sidebar-link">Schedule Calendar</a>
                    </li>
                </ul>
            </li>

            <li class="sidebar-item">
                <a href="#" class="sidebar-link collapsed has-dropdown" data-bs-toggle="collapse" data-bs-target="#license"
                    aria-expanded="true" aria-controls="auth">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-cast" viewBox="0 0 640 640"><!--!Font Awesome Free v7.0.0 by @fontawesome - https://fontawesome.com License - https://fontawesome.com/license/free Copyright 2025 Fonticons, Inc.-->
                        <path d="M80 480L80 224L560 224L560 480C560 488.8 552.8 496 544 496L352 496C352 451.8 316.2 416 272 416L208 416C163.8 416 128 451.8 128 496L96 496C87.2 496 80 488.8 80 480zM96 96C60.7 96 32 124.7 32 160L32 480C32 515.3 60.7 544 96 544L544 544C579.3 544 608 515.3 608 480L608 160C608 124.7 579.3 96 544 96L96 96zM240 376C270.9 376 296 350.9 296 320C296 289.1 270.9 264 240 264C209.1 264 184 289.1 184 320C184 350.9 209.1 376 240 376zM408 272C394.7 272 384 282.7 384 296C384 309.3 394.7 320 408 320L488 320C501.3 320 512 309.3 512 296C512 282.7 501.3 272 488 272L408 272zM408 368C394.7 368 384 378.7 384 392C384 405.3 394.7 416 408 416L488 416C501.3 416 512 405.3 512 392C512 378.7 501.3 368 488 368L408 368z" />
                    </svg>
                    <span style="font-size: 18px;">Doctor & Nurse Registration & Compliance Licensing</span>
                </a>

                <ul id="license" class="sidebar-dropdown list-unstyled collapse" data-bs-parent="#sidebar">
                    <li class="sidebar-item">
                        <a href="dnrcl/registration_clinical_profile.php" class="sidebar-link">Registration & Clinical Profile Management</a>
                    </li>
                    <li class="sidebar-item">
                        <a href="dnrcl/license_management.php" class="sidebar-link">License Management</a>
                    </li>
                    <li class="sidebar-item">
                        <a href="dnrcl/compliance.php" class="sidebar-link">Compliance Monitoring Dashboard</a>
                    </li>
                    <li class="sidebar-item">
                        <a href="dnrcl/notif_alert.php" class="sidebar-link">Notifications & Alerts</a>
                    </li>
                    <li class="sidebar-item">
                        <a href="dnrcl/audit_log.php" class="sidebar-link">Compliance Audit Log</a>
                    </li>
                </ul>
            </li>

            <li class="sidebar-item">
                <a href="#" class="sidebar-link collapsed has-dropdown" data-bs-toggle="collapse" data-bs-target="#evaluation"
                    aria-expanded="true" aria-controls="auth">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-cast" viewBox="0 0 640 640"><!--!Font Awesome Free v7.0.0 by @fontawesome - https://fontawesome.com License - https://fontawesome.com/license/free Copyright 2025 Fonticons, Inc.-->
                        <path d="M96 96C113.7 96 128 110.3 128 128L128 464C128 472.8 135.2 480 144 480L544 480C561.7 480 576 494.3 576 512C576 529.7 561.7 544 544 544L144 544C99.8 544 64 508.2 64 464L64 128C64 110.3 78.3 96 96 96zM208 288C225.7 288 240 302.3 240 320L240 384C240 401.7 225.7 416 208 416C190.3 416 176 401.7 176 384L176 320C176 302.3 190.3 288 208 288zM352 224L352 384C352 401.7 337.7 416 320 416C302.3 416 288 401.7 288 384L288 224C288 206.3 302.3 192 320 192C337.7 192 352 206.3 352 224zM432 256C449.7 256 464 270.3 464 288L464 384C464 401.7 449.7 416 432 416C414.3 416 400 401.7 400 384L400 288C400 270.3 414.3 256 432 256zM576 160L576 384C576 401.7 561.7 416 544 416C526.3 416 512 401.7 512 384L512 160C512 142.3 526.3 128 544 128C561.7 128 576 142.3 576 160z" />
                    </svg>
                    <span style="font-size: 18px;">Performance and Evaluation</span>
                </a>

                <ul id="evaluation" class="sidebar-dropdown list-unstyled collapse" data-bs-parent="#sidebar">
                    <li class="sidebar-item">
                        <a href="evaluation/doc_feedback.php" class="sidebar-link">View Nurse Evaluation</a>
                    </li>
                    <li class="sidebar-item">
                        <a href="evaluation/analytics.php" class="sidebar-link">Evaluation Report & Analytics</a>
                    </li>
                    <li class="sidebar-item">
                        <a href="evaluation/criteria.php" class="sidebar-link">Manage Evaluation Criteria</a>
                    </li>
                </ul>
            </li>
            <li class="sidebar-item">
                <a href="repair_request.php" class="sidebar-link collapsed has-dropdown" data-bs-toggle="#" data-bs-target="#request_repair"
                    aria-expanded="true" aria-controls="auth">
                     <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-cast" viewBox="0 0 640 640"><!--!Font Awesome Free v7.2.0 by @fontawesome - https://fontawesome.com License - https://fontawesome.com/license/free Copyright 2026 Fonticons, Inc.-->
                        <path d="M160 80c0-35.3 28.7-64 64-64s64 28.7 64 64l0 48-128 0 0-48zm-48 48l-64 0c-26.5 0-48 21.5-48 48L0 384c0 53 43 96 96 96l256 0c53 0 96-43 96-96l0-208c0-26.5-21.5-48-48-48l-64 0 0-48c0-61.9-50.1-112-112-112S112 18.1 112 80l0 48zm24 48a24 24 0 1 1 0 48 24 24 0 1 1 0-48zm152 24a24 24 0 1 1 48 0 24 24 0 1 1 -48 0z"/>
                    </svg>
                    <span style="font-size: 18px;">Purchase Request</span>
                </a>
            </li>
        </aside>

        <!----- End of Sidebar ----->
        <!----- Main Content ----->
        <div class="main">
            <div class="topbar">
                <div class="toggle">
                    <button class="toggler-btn" type="button">
                        <svg xmlns="http://www.w3.org/2000/svg" width="30px" height="30px" fill="currentColor" class="bi bi-list-ul"
                            viewBox="0 0 16 16">
                            <path fill-rule="evenodd"
                                d="M5 11.5a.5.5 0 0 1 .5-.5h9a.5.5 0 0 1 0 1h-9a.5.5 0 0 1-.5-.5m0-4a.5.5 0 0 1 .5-.5h9a.5.5 0 0 1 0 1h-9a.5.5 0 0 1-.5-.5m0-4a.5.5 0 0 1 .5-.5h9a.5.5 0 0 1 0 1h-9a.5.5 0 0 1-.5-.5m-3 1a1 1 0 1 0 0-2 1 1 0 0 0 0 2m0 4a1 1 0 1 0 0-2 1 1 0 0 0 0 2m0 4a1 1 0 1 0 0-2 1 1 0 0 0 0 2" />
                        </svg>
                    </button>
                </div>
                <div class="logo">
                    <div class="dropdown d-flex align-items-center">
                        <span class="username ml-1 me-2"><?php echo $user['fname']; ?> <?php echo $user['lname']; ?></span><!-- Display the logged-in user's name -->
                        <button class="btn dropdown-toggle" type="button" id="dropdownMenuButton" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-person-circle"></i>
                        </button>
                        <ul class="dropdown-menu" aria-labelledby="dropdownMenuButton" style="min-width: 200px; padding: 10px; border-radius: 5px; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); background-color: #fff; color: #333;">
                            <li style="margin-bottom: 8px; font-size: 14px; color: #555;">
                                <span>Welcome <strong style="color: #007bff;"><?php echo $user['lname']; ?></strong>!</span>
                            </li>
                            <li>
                                <a class="dropdown-item" href="../logout.php" style="font-size: 14px; color: #007bff; text-decoration: none; padding: 8px 12px; border-radius: 4px; transition: background-color 0.3s ease;">
                                    Logout
                                </a>
                            </li>
                        </ul>

                    </div>
                </div>
            </div>
            <!-- START CODING HERE -->
            <div class="dashboard-wrapper mt-4 px-3">
                
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h2 class="fw-bolder text-dark mb-1" style="letter-spacing: -0.5px;">Dashboard Overview</h2>
                        <p class="text-muted mb-0">Welcome back, here's what's happening today.</p>
                    </div>
                    <div class="text-end">
                        <span class="badge bg-white text-dark border px-3 py-2 rounded-pill shadow-sm"><i class="fas fa-calendar-day text-primary me-2"></i> <?php echo date('F j, Y'); ?></span>
                    </div>
                </div>

                <!-- Summary & Leaderboard Row -->
                <div class="row g-4 mb-4">
                    <!-- Total Doctors -->
                    <div class="col-md-3">
                        <div class="card glass-card gradient-primary border-0 h-100 rounded-4 p-4 position-relative overflow-hidden">
                            <div class="position-absolute opacity-25" style="bottom: -20px; right: -20px; font-size: 140px; transform: rotate(-15deg);">
                                <i class="fas fa-user-md"></i>
                            </div>
                            <div class="d-flex justify-content-between align-items-start mb-4 position-relative z-1">
                                <span class="badge bg-white text-primary rounded-pill px-3 py-1 fw-bold border-0 shadow-sm">Doctors</span>
                                <div class="icon-wrapper">
                                    <i class="fas fa-stethoscope"></i>
                                </div>
                            </div>
                            <h3 class="stat-number mb-1 position-relative z-1"><?php echo $doctorCount; ?></h3>
                            <p class="mb-0 text-white-50 fw-medium position-relative z-1" style="font-size: 14px;">Total Active Professionals</p>
                        </div>
                    </div>
                    <!-- Total Nurses -->
                    <div class="col-md-3">
                        <div class="card glass-card gradient-success border-0 h-100 rounded-4 p-4 position-relative overflow-hidden">
                            <div class="position-absolute opacity-25" style="bottom: -15px; right: -15px; font-size: 130px; transform: rotate(10deg);">
                                <i class="fas fa-user-nurse text-white"></i>
                            </div>
                            <div class="d-flex justify-content-between align-items-start mb-4 position-relative z-1">
                                <span class="badge bg-white text-success rounded-pill px-3 py-1 fw-bold border-0 shadow-sm">Nurses</span>
                                <div class="icon-wrapper">
                                    <i class="fas fa-heartbeat text-white"></i>
                                </div>
                            </div>
                            <h3 class="stat-number text-dark mb-1 position-relative z-1"><?php echo $nurseCount; ?></h3>
                            <p class="mb-0 opacity-75 text-dark fw-medium position-relative z-1" style="font-size: 14px;">Total Dedicated Staff</p>
                        </div>
                    </div>
                    <!-- Top Doctor -->
                    <div class="col-md-3">
                        <div class="card glass-card border-0 h-100 rounded-4 p-4 text-center">
                            <div class="d-flex justify-content-center mb-3">
                                <div class="avatar-circle gradient-warning">
                                    <?php echo $topDoctorInitials; ?>
                                </div>
                            </div>
                            <span class="badge bg-light text-warning border border-warning px-3 py-1 rounded-pill mb-2"><i class="fas fa-star me-1"></i>Top Doctor</span>
                            <h5 class="fw-bolder mb-1 text-dark text-truncate">Dr. <?php echo htmlspecialchars($topDoctor['last_name'] ?? 'N/A'); ?></h5>
                            <div class="d-flex justify-content-center align-items-center gap-2 mt-2">
                                <span class="text-success fw-bold p-1 bg-success bg-opacity-10 rounded px-2" style="font-size: 12px;"><i class="fas fa-arrow-up me-1"></i>98% Success</span>
                            </div>
                        </div>
                    </div>
                    <!-- Top Nurse -->
                    <div class="col-md-3">
                        <div class="card glass-card border-0 h-100 rounded-4 p-4 text-center">
                            <div class="d-flex justify-content-center mb-3">
                                <div class="avatar-circle gradient-info">
                                    <?php echo $topNurseInitials; ?>
                                </div>
                            </div>
                            <span class="badge bg-light text-info border border-info px-3 py-1 rounded-pill mb-2"><i class="fas fa-award me-1"></i>Top Nurse</span>
                            <h5 class="fw-bolder mb-1 text-dark text-truncate">Nurse <?php echo htmlspecialchars($topNurse['first_name'] ?? 'N/A'); ?></h5>
                            <div class="d-flex justify-content-center align-items-center gap-2 mt-2">
                                <span class="text-success fw-bold p-1 bg-success bg-opacity-10 rounded px-2" style="font-size: 12px;"><i class="fas fa-arrow-up me-1"></i>95% Complete</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Charts & Attendance Row -->
                <div class="row g-4 mb-4">
                    <!-- Task Analytics -->
                    <div class="col-lg-8">
                        <div class="card glass-card border-0 rounded-4 h-100">
                            <div class="card-header bg-transparent border-0 pt-4 pb-0 px-4 d-flex justify-content-between align-items-center">
                                <div>
                                    <h5 class="fw-bolder text-dark mb-0">Task Analytics</h5>
                                    <small class="text-muted">Completion vs Pending Tasks</small>
                                </div>
                                <select class="form-select form-select-sm border-0 bg-light fw-medium w-auto shadow-sm rounded-pill px-3">
                                    <option>This Week</option>
                                    <option>This Month</option>
                                    <option>This Year</option>
                                </select>
                            </div>
                            <div class="card-body px-4 pb-4 position-relative mt-2">
                                <canvas id="taskChart" style="max-height: 290px; width: 100%;"></canvas>
                            </div>
                        </div>
                    </div>

                    <!-- Attendance Tracker -->
                    <div class="col-lg-4">
                        <div class="card glass-card border-0 rounded-4 h-100">
                            <div class="card-header bg-transparent border-0 pt-4 pb-0 px-4 d-flex justify-content-between align-items-center">
                                <div>
                                    <h5 class="fw-bolder text-dark mb-0">Live Attendance</h5>
                                    <small class="text-muted">Today's active personnel</small>
                                </div>
                                <button class="btn btn-sm btn-light border shadow-sm rounded-circle d-flex align-items-center justify-content-center" style="width: 32px; height: 32px;" title="Refresh Data"><i class="fas fa-sync-alt text-secondary"></i></button>
                            </div>
                            <div class="card-body px-4 pt-4 d-flex flex-column justify-content-around">
                                <!-- Doctors Attendance -->
                                <div class="mb-4">
                                    <div class="d-flex justify-content-between align-items-end mb-3">
                                        <div class="d-flex align-items-center gap-2">
                                            <div class="icon-wrapper bg-primary bg-opacity-10 text-primary" style="width: 35px; height: 35px; font-size: 14px;">
                                                <i class="fas fa-user-md"></i>
                                            </div>
                                            <span class="fw-bold text-dark h6 mb-0">Doctors</span>
                                        </div>
                                        <h4 class="fw-bolder text-primary mb-0">85%</h4>
                                    </div>
                                    <div class="progress progress-thin">
                                        <div class="progress-bar progress-bar-glow-primary" role="progressbar" style="width: 85%" aria-valuenow="85" aria-valuemin="0" aria-valuemax="100"></div>
                                    </div>
                                    <div class="d-flex justify-content-between mt-2">
                                        <small class="text-muted fw-medium">Active: <span class="text-dark fw-bold"><?php echo floor($doctorCount * 0.85); ?></span></small>
                                        <small class="text-muted fw-medium">Total: <span class="text-dark fw-bold"><?php echo $doctorCount; ?></span></small>
                                    </div>
                                </div>

                                <!-- Nurses Attendance -->
                                <div>
                                    <div class="d-flex justify-content-between align-items-end mb-3">
                                        <div class="d-flex align-items-center gap-2">
                                            <div class="icon-wrapper bg-success bg-opacity-10 text-success" style="width: 35px; height: 35px; font-size: 14px;">
                                                <i class="fas fa-user-nurse"></i>
                                            </div>
                                            <span class="fw-bold text-dark h6 mb-0">Nurses</span>
                                        </div>
                                        <h4 class="fw-bolder text-success mb-0">92%</h4>
                                    </div>
                                    <div class="progress progress-thin">
                                        <div class="progress-bar progress-bar-glow-success" role="progressbar" style="width: 92%" aria-valuenow="92" aria-valuemin="0" aria-valuemax="100"></div>
                                    </div>
                                    <div class="d-flex justify-content-between mt-2">
                                        <small class="text-muted fw-medium">Active: <span class="text-dark fw-bold"><?php echo floor($nurseCount * 0.92); ?></span></small>
                                        <small class="text-muted fw-medium">Total: <span class="text-dark fw-bold"><?php echo $nurseCount; ?></span></small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="row mb-5">
                    <div class="col-12">
                        <div class="card glass-card border-0 rounded-4 p-4 text-center d-flex flex-column align-items-center justify-content-center bg-white shadow-sm position-relative overflow-hidden">
                            <i class="fas fa-users fa-5x position-absolute opacity-10" style="bottom: -20px; right: 20px;"></i>
                            <h4 class="fw-bolder text-dark mb-2 position-relative z-1">Need to request a replacement?</h4>
                            <p class="text-muted mb-4 max-w-500 mx-auto position-relative z-1">Fill out a replacement request form to swiftly find cover for missing or required medical personnel.</p>
                            <div class="d-flex flex-wrap justify-content-center gap-3 position-relative z-1">
                                <button type="button" class="btn btn-modern btn-modern-primary d-flex align-items-center gap-2" onclick="openModal('doctorsModal')">
                                    <i class="fas fa-stethoscope"></i> Request Doctor
                                </button>
                                <button type="button" class="btn btn-modern btn-modern-success d-flex align-items-center gap-2" onclick="openModal('nursesModal')">
                                    <i class="fas fa-heartbeat"></i> Request Nurse
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Attendance Logs & Reports -->
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="card glass-card border-0 rounded-4 shadow-sm overflow-hidden">
                            <div class="card-header bg-white border-0 pt-4 pb-3 px-4 d-flex justify-content-between align-items-center border-bottom">
                                <div>
                                    <h4 class="fw-bolder text-dark mb-0">Attendance Logs & Reports</h4>
                                    <p class="text-muted mb-0 small">Review daily, monthly, and yearly personnel attendance metrics.</p>
                                </div>
                            </div>
                            <div class="card-body p-0">
                                <ul class="nav nav-tabs nav-tabs-modern px-4 pt-3 border-bottom" id="attendanceTab" role="tablist">
                                    <li class="nav-item" role="presentation">
                                        <button class="nav-link active fw-semibold pb-3" id="daily-tab" data-bs-toggle="tab" data-bs-target="#daily" type="button" role="tab" aria-controls="daily" aria-selected="true"><i class="fas fa-calendar-day me-2"></i>Daily Overview</button>
                                    </li>
                                    <li class="nav-item" role="presentation">
                                        <button class="nav-link fw-semibold pb-3" id="detailed-tab" data-bs-toggle="tab" data-bs-target="#detailed" type="button" role="tab" aria-controls="detailed" aria-selected="false"><i class="fas fa-users me-2"></i>Detailed Employee Logs</button>
                                    </li>
                                    <li class="nav-item" role="presentation">
                                        <button class="nav-link fw-semibold pb-3" id="monthly-tab" data-bs-toggle="tab" data-bs-target="#monthly" type="button" role="tab" aria-controls="monthly" aria-selected="false"><i class="fas fa-calendar-alt me-2"></i>Monthly Overview</button>
                                    </li>
                                    <li class="nav-item" role="presentation">
                                        <button class="nav-link fw-semibold pb-3" id="yearly-tab" data-bs-toggle="tab" data-bs-target="#yearly" type="button" role="tab" aria-controls="yearly" aria-selected="false"><i class="fas fa-calendar me-2"></i>Yearly Overview</button>
                                    </li>
                                </ul>
                                <div class="tab-content" id="attendanceTabContent">
                                    <!-- Daily Overview -->
                                    <div class="tab-pane fade show active" id="daily" role="tabpanel" aria-labelledby="daily-tab">
                                        <div class="table-responsive">
                                            <table class="table table-hover align-middle mb-0">
                                                <thead class="bg-light">
                                                    <tr>
                                                        <th class="text-secondary fw-semibold ps-4">Date</th>
                                                        <th class="text-secondary fw-semibold text-center">Present</th>
                                                        <th class="text-secondary fw-semibold text-center">Absent</th>
                                                        <th class="text-secondary fw-semibold text-center">Late</th>
                                                        <th class="text-secondary fw-semibold text-center">Leave</th>
                                                        <th class="text-secondary fw-semibold text-center">Undertime</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php if ($dailyReports && $dailyReports->num_rows > 0): ?>
                                                        <?php while ($row = $dailyReports->fetch_assoc()): ?>
                                                            <tr>
                                                                <td class="ps-4 fw-medium text-dark"><i class="far fa-calendar-check text-primary me-2"></i><?php echo date('M j, Y', strtotime($row['reportDate'])); ?></td>
                                                                <td class="text-center"><span class="badge bg-success bg-opacity-10 text-success rounded-pill px-3 py-1"><?php echo $row['present']; ?></span></td>
                                                                <td class="text-center"><span class="badge bg-danger bg-opacity-10 text-danger rounded-pill px-3 py-1"><?php echo $row['absent']; ?></span></td>
                                                                <td class="text-center"><span class="badge bg-warning bg-opacity-10 text-warning rounded-pill px-3 py-1"><?php echo $row['late']; ?></span></td>
                                                                <td class="text-center"><span class="badge bg-info bg-opacity-10 text-info rounded-pill px-3 py-1"><?php echo $row['leave']; ?></span></td>
                                                                <td class="text-center"><span class="badge bg-secondary bg-opacity-10 text-secondary rounded-pill px-3 py-1"><?php echo $row['underTime']; ?></span></td>
                                                            </tr>
                                                        <?php endwhile; ?>
                                                    <?php else: ?>
                                                        <tr><td colspan="6" class="text-center py-4 text-muted">No daily attendance reports found.</td></tr>
                                                    <?php endif; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                    <!-- Detailed Personnel Logs -->
                                    <div class="tab-pane fade" id="detailed" role="tabpanel" aria-labelledby="detailed-tab">
                                        <div class="table-responsive">
                                            <table class="table table-hover align-middle mb-0">
                                                <thead class="bg-light">
                                                    <tr>
                                                        <th class="text-secondary fw-semibold ps-4">Personnel</th>
                                                        <th class="text-secondary fw-semibold">Role</th>
                                                        <th class="text-secondary fw-semibold">Date</th>
                                                        <th class="text-secondary fw-semibold">Time In</th>
                                                        <th class="text-secondary fw-semibold">Time Out</th>
                                                        <th class="text-secondary fw-semibold">Status</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php if ($detailedLogs && $detailedLogs->num_rows > 0): ?>
                                                        <?php while ($row = $detailedLogs->fetch_assoc()): ?>
                                                            <tr>
                                                                <td class="ps-4 fw-bold text-dark">
                                                                    <div class="d-flex align-items-center">
                                                                        <div class="avatar-sm rounded-circle text-white d-flex align-items-center justify-content-center me-3" style="width: 35px; height: 35px; background: <?php echo ($row['profession'] == 'Doctor') ? 'var(--bs-primary)' : 'var(--bs-success)'; ?>;">
                                                                            <?php echo strtoupper(substr($row['first_name'], 0, 1) . substr($row['last_name'], 0, 1)); ?>
                                                                        </div>
                                                                        <div>
                                                                            <span class="d-block text-dark"><?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?></span>
                                                                        </div>
                                                                    </div>
                                                                </td>
                                                                <td><span class="badge bg-light text-dark border"><?php echo htmlspecialchars($row['role']); ?></span></td>
                                                                <td><?php echo date('M j, Y', strtotime($row['attendance_date'])); ?></td>
                                                                <td><span class="fw-medium text-dark"><i class="far fa-clock text-muted me-1"></i><?php echo $row['time_in'] ? date('h:i A', strtotime($row['time_in'])) : '--:--'; ?></span></td>
                                                                <td><span class="fw-medium text-dark"><i class="far fa-clock text-muted me-1"></i><?php echo $row['time_out'] ? date('h:i A', strtotime($row['time_out'])) : '--:--'; ?></span></td>
                                                                <td>
                                                                    <?php
                                                                        $statusClass = 'bg-secondary';
                                                                        switch ($row['status']) {
                                                                            case 'Present': $statusClass = 'bg-success'; break;
                                                                            case 'Late': $statusClass = 'bg-warning text-dark'; break;
                                                                            case 'Absent': $statusClass = 'bg-danger'; break;
                                                                            case 'On Leave': $statusClass = 'bg-info text-dark'; break;
                                                                            case 'Undertime': $statusClass = 'bg-secondary'; break;
                                                                        }
                                                                    ?>
                                                                    <span class="badge <?php echo $statusClass; ?> rounded-pill px-3"><?php echo htmlspecialchars($row['status']); ?></span>
                                                                </td>
                                                            </tr>
                                                        <?php endwhile; ?>
                                                    <?php else: ?>
                                                        <tr><td colspan="6" class="text-center py-4 text-muted">No personnel records found.</td></tr>
                                                    <?php endif; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                    <!-- Monthly Overview -->
                                    <div class="tab-pane fade" id="monthly" role="tabpanel" aria-labelledby="monthly-tab">
                                        <div class="table-responsive">
                                            <table class="table table-hover align-middle mb-0">
                                                <thead class="bg-light">
                                                    <tr>
                                                        <th class="text-secondary fw-semibold ps-4">Month/Year</th>
                                                        <th class="text-secondary fw-semibold text-center">Present</th>
                                                        <th class="text-secondary fw-semibold text-center">Absent</th>
                                                        <th class="text-secondary fw-semibold text-center">Late</th>
                                                        <th class="text-secondary fw-semibold text-center">Leave</th>
                                                        <th class="text-secondary fw-semibold text-center">Undertime</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php if ($monthlyReports && $monthlyReports->num_rows > 0): ?>
                                                        <?php while ($row = $monthlyReports->fetch_assoc()): ?>
                                                            <?php $monthName = date("F", mktime(0, 0, 0, $row['month'], 10)); ?>
                                                            <tr>
                                                                <td class="ps-4 fw-bold text-dark text-primary"><?php echo $monthName . ' ' . $row['year']; ?></td>
                                                                <td class="text-center"><span class="badge bg-success bg-opacity-10 text-success rounded-pill px-3 py-1"><?php echo $row['present']; ?></span></td>
                                                                <td class="text-center"><span class="badge bg-danger bg-opacity-10 text-danger rounded-pill px-3 py-1"><?php echo $row['absent']; ?></span></td>
                                                                <td class="text-center"><span class="badge bg-warning bg-opacity-10 text-warning rounded-pill px-3 py-1"><?php echo $row['late']; ?></span></td>
                                                                <td class="text-center"><span class="badge bg-info bg-opacity-10 text-info rounded-pill px-3 py-1"><?php echo $row['leave_count']; ?></span></td>
                                                                <td class="text-center"><span class="badge bg-secondary bg-opacity-10 text-secondary rounded-pill px-3 py-1"><?php echo $row['undertime']; ?></span></td>
                                                            </tr>
                                                        <?php endwhile; ?>
                                                    <?php else: ?>
                                                        <tr><td colspan="6" class="text-center py-4 text-muted">No monthly attendance reports found.</td></tr>
                                                    <?php endif; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                    <!-- Yearly Overview -->
                                    <div class="tab-pane fade" id="yearly" role="tabpanel" aria-labelledby="yearly-tab">
                                        <div class="table-responsive">
                                            <table class="table table-hover align-middle mb-0">
                                                <thead class="bg-light">
                                                    <tr>
                                                        <th class="text-secondary fw-semibold ps-4">Year</th>
                                                        <th class="text-secondary fw-semibold text-center">Attendance Rate</th>
                                                        <th class="text-secondary fw-semibold text-center">Total Present</th>
                                                        <th class="text-secondary fw-semibold text-center">Total Absent</th>
                                                        <th class="text-secondary fw-semibold text-center">Total Late</th>
                                                        <th class="text-secondary fw-semibold text-center">Total Leave</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php if ($yearlyReports && $yearlyReports->num_rows > 0): ?>
                                                        <?php while ($row = $yearlyReports->fetch_assoc()): ?>
                                                            <tr>
                                                                <td class="ps-4 fw-bolder text-dark fs-5"><?php echo $row['year']; ?></td>
                                                                <td class="text-center">
                                                                    <div class="d-flex align-items-center justify-content-center gap-2">
                                                                        <div class="progress flex-grow-1" style="height: 8px; max-width: 100px;">
                                                                            <div class="progress-bar bg-success" role="progressbar" style="width: <?php echo $row['attendanceRate']; ?>%;" aria-valuenow="<?php echo $row['attendanceRate']; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                                                        </div>
                                                                        <span class="fw-bold text-success"><?php echo $row['attendanceRate']; ?>%</span>
                                                                    </div>
                                                                </td>
                                                                <td class="text-center"><span class="fw-bold text-dark"><?php echo $row['present']; ?></span></td>
                                                                <td class="text-center"><span class="fw-bold text-danger"><?php echo $row['absent']; ?></span></td>
                                                                <td class="text-center"><span class="fw-bold text-warning"><?php echo $row['late']; ?></span></td>
                                                                <td class="text-center"><span class="fw-bold text-info"><?php echo $row['leave_count']; ?></span></td>
                                                            </tr>
                                                        <?php endwhile; ?>
                                                    <?php else: ?>
                                                        <tr><td colspan="6" class="text-center py-4 text-muted">No yearly attendance reports found.</td></tr>
                                                    <?php endif; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>

            <!-- Doctors Modal -->
            <div id="doctorsModal" class="bastabubukas">
                <div class="lalagyanannya">
                    <bttn class="close-btn" onclick="closeModal('doctorsModal')">X</bttn>
                    <center>
                        <h3 style="font-weight: bold;">Doctor Replacement Request</h3> 
                    </center>
                    <br />

                    <form action="submit_replacement_request.php" method="POST">
                        <input type="hidden" name="profession" value="Doctor" required>

                        <!-- Department / Subspecialty Dropdown -->
                        <label>Department / Subspecialty</label>
                        <select id="department" name="department" required>
                            <option value="">--- Select Department ---</option>
                            <option value="Anesthesiology & Pain Management">Anesthesiology & Pain Management</option>
                            <option value="Cardiology (Heart & Vascular System)">Cardiology (Heart & Vascular System)</option>
                            <option value="Dermatology (Skin, Hair, & Nails)">Dermatology (Skin, Hair, & Nails)</option>
                            <option value="Ear, Nose, and Throat (ENT)">Ear, Nose, and Throat (ENT)</option>
                            <option value="Emergency Department (ER)">Emergency Department (ER)</option>
                            <option value="Gastroenterology (Digestive System & Liver)">Gastroenterology (Digestive System & Liver)</option>
                            <option value="Geriatrics & Palliative Care (Elderly & Terminal Care)">Geriatrics & Palliative Care</option>
                            <option value="Infectious Diseases & Immunology">Infectious Diseases & Immunology</option>
                            <option value="Internal Medicine (General & Subspecialties)">Internal Medicine</option>
                            <option value="Nephrology (Kidneys & Dialysis)">Nephrology</option>
                            <option value="Neurology & Neurosurgery (Brain & Nervous System)">Neurology & Neurosurgery</option>
                            <option value="Obstetrics & Gynecology (OB-GYN)">Obstetrics & Gynecology (OB-GYN)</option>
                            <option value="Oncology (Cancer Treatment)">Oncology</option>
                            <option value="Ophthalmology (Eye Care)">Ophthalmology</option>
                            <option value="Orthopedics (Bones, Joints, and Muscles)">Orthopedics</option>
                            <option value="Pediatrics (Child Healthcare)">Pediatrics</option>
                            <option value="Psychiatry & Mental Health">Psychiatry & Mental Health</option>
                            <option value="Pulmonology (Lungs & Respiratory System)">Pulmonology</option>
                            <option value="Rehabilitation & Physical Therapy">Rehabilitation & Physical Therapy</option>
                            <option value="Surgery (General & Subspecialties)">Surgery</option>
                        </select>

                        <!-- Specialization Dropdown -->
                        <label>Specialist to Replace</label>
                        <select id="specialization" name="position" required>
                            <option value="">--- Select Specialization ---</option>
                        </select>

                        <!-- Other Specialization (hidden initially) -->
                        <input type="text" id="otherSpecialization" name="other_specialization" placeholder="Specify Other Specialist" style="display:none;">

                        <label>Leaving Employee Name</label>
                        <input type="text" name="leaving_employee_name">

                        <label>Leaving Employee ID</label>
                        <input type="text" name="leaving_employee_id">

                        <label>Reason for Leaving</label>
                        <textarea name="reason_for_leaving"></textarea>

                        <label>Requested By</label>
                        <input type="text" name="requested_by" required>

                        <button type="submit">Submit Request</button>
                    </form>
                </div>
            </div>

            <!-- Nurses Modal -->
            <div id="nursesModal" class="bastabubukas">
                <div class="lalagyanannya">
                    <bttn class="close-btn" onclick="closeModal('nursesModal')">X</bttn>
                    <center>
                        <h3 style="font-weight: bold;">Nurse Replacement Request</h3> 
                    </center>
                    <br />

                    <form action="submit_replacement_request.php" method="POST">
                        <input type="hidden" name="profession" value="Nurse" required>

                        <!-- Department / Subspecialty Dropdown -->
                        <label>Department / Subspecialty</label>
                        <select id="nurseDepartment" name="department" required>
                            <option value="">--- Select Department ---</option>
                            <option value="Anesthesiology & Pain Management">Anesthesiology & Pain Management</option>
                            <option value="Cardiology (Heart & Vascular System)">Cardiology (Heart & Vascular System)</option>
                            <option value="Dermatology (Skin, Hair, & Nails)">Dermatology (Skin, Hair, & Nails)</option>
                            <option value="Ear, Nose, and Throat (ENT)">Ear, Nose, and Throat (ENT)</option>
                            <option value="Emergency Department (ER)">Emergency Department (ER)</option>
                            <option value="Gastroenterology (Digestive System & Liver)">Gastroenterology (Digestive System & Liver)</option>
                            <option value="Geriatrics & Palliative Care (Elderly & Terminal Care)">Geriatrics & Palliative Care</option>
                            <option value="Infectious Diseases & Immunology">Infectious Diseases & Immunology</option>
                            <option value="Internal Medicine (General & Subspecialties)">Internal Medicine</option>
                            <option value="Nephrology (Kidneys & Dialysis)">Nephrology</option>
                            <option value="Neurology & Neurosurgery (Brain & Nervous System)">Neurology & Neurosurgery</option>
                            <option value="Obstetrics & Gynecology (OB-GYN)">Obstetrics & Gynecology (OB-GYN)</option>
                            <option value="Oncology (Cancer Treatment)">Oncology</option>
                            <option value="Ophthalmology (Eye Care)">Ophthalmology</option>
                            <option value="Orthopedics (Bones, Joints, and Muscles)">Orthopedics</option>
                            <option value="Pediatrics (Child Healthcare)">Pediatrics</option>
                            <option value="Psychiatry & Mental Health">Psychiatry & Mental Health</option>
                            <option value="Pulmonology (Lungs & Respiratory System)">Pulmonology</option>
                            <option value="Rehabilitation & Physical Therapy">Rehabilitation & Physical Therapy</option>
                            <option value="Surgery (General & Subspecialties)">Surgery</option>
                        </select>

                        <!-- Specialization Dropdown -->
                        <label>Nurse Type to Replace</label>
                        <select id="nurseSpecialization" name="position" required>
                            <option value="">--- Select Specialization ---</option>
                        </select>

                        <!-- Other Specialization (hidden initially) -->
                        <input type="text" id="otherNurseSpecialization" name="other_specialization" placeholder="Specify Other Nurse Type" style="display:none;">

                        <label>Leaving Employee Name</label>
                        <input type="text" name="leaving_employee_name">

                        <label>Leaving Employee ID</label>
                        <input type="text" name="leaving_employee_id">

                        <label>Reason for Leaving</label>
                        <textarea name="reason_for_leaving"></textarea>

                        <label>Requested By</label>
                        <input type="text" name="requested_by" required>

                        <button type="submit">Submit Request</button>
                    </form>
                </div>
            </div>


            <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    const ctx = document.getElementById('taskChart').getContext('2d');
                    
                    // Create gradient for completed tasks
                    let gradientCompleted = ctx.createLinearGradient(0, 0, 0, 400);
                    gradientCompleted.addColorStop(0, 'rgba(13, 110, 253, 0.9)');
                    gradientCompleted.addColorStop(1, 'rgba(13, 110, 253, 0.4)');

                    // Create gradient for pending tasks
                    let gradientPending = ctx.createLinearGradient(0, 0, 0, 400);
                    gradientPending.addColorStop(0, 'rgba(25, 135, 84, 0.9)');
                    gradientPending.addColorStop(1, 'rgba(25, 135, 84, 0.4)');

                    new Chart(ctx, {
                        type: 'bar',
                        data: {
                            labels: ['Doctors', 'Nurses'],
                            datasets: [
                                {
                                    label: 'Completed Tasks',
                                    data: [120, 350],
                                    backgroundColor: gradientCompleted,
                                    borderRadius: 8,
                                    barPercentage: 0.5,
                                    categoryPercentage: 0.8
                                },
                                {
                                    label: 'Pending Tasks',
                                    data: [35, 80],
                                    backgroundColor: gradientPending,
                                    borderRadius: 8,
                                    barPercentage: 0.5,
                                    categoryPercentage: 0.8
                                }
                            ]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: {
                                    position: 'top',
                                    align: 'end',
                                    labels: {
                                        usePointStyle: true,
                                        padding: 20,
                                        font: {
                                            family: "'Inter', sans-serif",
                                            size: 13,
                                            weight: '500'
                                        }
                                    }
                                },
                                tooltip: {
                                    backgroundColor: 'rgba(255, 255, 255, 0.95)',
                                    titleColor: '#333',
                                    bodyColor: '#666',
                                    borderColor: 'rgba(0,0,0,0.05)',
                                    borderWidth: 1,
                                    padding: 15,
                                    boxPadding: 6,
                                    titleFont: { size: 14, weight: 'bold' },
                                    bodyFont: { size: 13 },
                                    cornerRadius: 10,
                                    displayColors: true,
                                    elevation: 5
                                }
                            },
                            scales: {
                                y: {
                                    beginAtZero: true,
                                    grid: {
                                        color: 'rgba(0, 0, 0, 0.04)',
                                        drawBorder: false,
                                        borderDash: [5, 5]
                                    },
                                    ticks: {
                                        font: { size: 12, family: "'Inter', sans-serif" },
                                        color: '#888',
                                        padding: 10
                                    }
                                },
                                x: {
                                    grid: {
                                        display: false,
                                        drawBorder: false
                                    },
                                    ticks: {
                                        font: { size: 14, weight: '600', family: "'Inter', sans-serif" },
                                        color: '#444',
                                        padding: 10
                                    }
                                }
                            },
                            animation: {
                                duration: 1500,
                                easing: 'easeOutQuart'
                            }
                        }
                    });
                });
            </script>
            <!-- END CODING HERE -->
        </div>
        <!----- End of Main Content ----->
    </div>
    <script>
        const toggler = document.querySelector(".toggler-btn");
        toggler.addEventListener("click", function() {
            document.querySelector("#sidebar").classList.toggle("collapsed");
        });

        function openModal(id) {
            document.getElementById(id).style.display = "flex";
        }
        function closeModal(id) {
            document.getElementById(id).style.display = "none";
        }

        window.onclick = function(event) {
            const modals = ['doctorsModal','nursesModal','pharmacyModal','laboratoryModal','accountingModal'];
            modals.forEach(id => {
                const modal = document.getElementById(id);
                if(event.target == modal) closeModal(id);
            });
        }

        <!-- JavaScript For Doctors -->
        const specializations = {
            "Anesthesiology & Pain Management": ["Anesthesiologist"],
            "Cardiology (Heart & Vascular System)": ["Cardiologist"],
            "Dermatology (Skin, Hair, & Nails)": ["Dermatologist"],
            "Ear, Nose, and Throat (ENT)": ["ENT Specialist (Otolaryngologist)"],
            "Emergency Department (ER)": ["Emergency Medicine Physician"],
            "Gastroenterology (Digestive System & Liver)": ["Gastroenterologist"],
            "Geriatrics & Palliative Care (Elderly & Terminal Care)": ["Internal Medicine Physician (Elder Care)", "General Practitioner (Elder Care)"],
            "Infectious Diseases & Immunology": ["Infectious Disease Specialist"],
            "Internal Medicine (General & Subspecialties)": ["Internal Medicine Physician", "General Practitioner"],
            "Nephrology (Kidneys & Dialysis)": ["Nephrologist"],
            "Neurology & Neurosurgery (Brain & Nervous System)": ["Neurologist", "Neurosurgeon"],
            "Obstetrics & Gynecology (OB-GYN)": ["Gynecologist / Obstetrician (OB-GYN)"],
            "Oncology (Cancer Treatment)": ["Oncologist"],
            "Ophthalmology (Eye Care)": ["Ophthalmologist"],
            "Orthopedics (Bones, Joints, and Muscles)": ["Orthopedic Surgeon"],
            "Pediatrics (Child Healthcare)": ["Pediatrician"],
            "Psychiatry & Mental Health": ["Psychiatrist"],
            "Pulmonology (Lungs & Respiratory System)": ["Pulmonologist"],
            "Rehabilitation & Physical Therapy": ["Rehabilitation Medicine Specialist"],
            "Surgery (General & Subspecialties)": ["General Surgeon", "Plastic Surgeon", "Vascular Surgeon"]
        };

        document.getElementById("department").addEventListener("change", function() {
            const dept = this.value;
            const specializationSelect = document.getElementById("specialization");
            const otherInput = document.getElementById("otherSpecialization");
            
            specializationSelect.innerHTML = '<option value="">--- Select Specialization ---</option>';
            
            if (specializations[dept]) {
                specializations[dept].forEach(function(spec) {
                    const opt = document.createElement("option");
                    opt.value = spec;
                    opt.textContent = spec;
                    specializationSelect.appendChild(opt);
                });
                const optOthers = document.createElement("option");
                optOthers.value = "Others";
                optOthers.textContent = "Others";
                specializationSelect.appendChild(optOthers);
            }
            otherInput.style.display = "none";
        });

        document.getElementById("specialization").addEventListener("change", function() {
            const otherInput = document.getElementById("otherSpecialization");
            if (this.value === "Others") {
                otherInput.style.display = "block";
            } else {
                otherInput.style.display = "none";
            }
        });

        // <!-- JavaScript For Nurse -->
        const nurseSpecializations = {
            "Anesthesiology & Pain Management": ["Anesthesia Nurse", "Pain Management Nurse"],
            "Cardiology (Heart & Vascular System)": ["Cardiac Nurse", "CCU Nurse"],
            "Dermatology (Skin, Hair, & Nails)": ["Dermatology Nurse", "Aesthetic Nurse"],
            "Ear, Nose, and Throat (ENT)": ["ENT Nurse"],
            "Emergency Department (ER)": ["ER Nurse", "Trauma Nurse"],
            "Gastroenterology (Digestive System & Liver)": ["GI Nurse", "Endoscopy Nurse"],
            "Geriatrics & Palliative Care (Elderly & Terminal Care)": ["Geriatric Nurse", "Palliative Care Nurse", "Hospice Nurse"],
            "Infectious Diseases & Immunology": ["Infection Control Nurse", "Immunology Nurse"],
            "Internal Medicine (General & Subspecialties)": ["General Medicine Nurse", "Medical Ward Nurse"],
            "Nephrology (Kidneys & Dialysis)": ["Dialysis Nurse", "Renal Nurse"],
            "Neurology & Neurosurgery (Brain & Nervous System)": ["Neuro Nurse", "Neuroscience Nurse"],
            "Obstetrics & Gynecology (OB-GYN)": ["OB Nurse", "Labor & Delivery Nurse", "Antenatal Care Nurse"],
            "Oncology (Cancer Treatment)": ["Oncology Nurse", "Chemotherapy Nurse"],
            "Ophthalmology (Eye Care)": ["Ophthalmic Nurse"],
            "Orthopedics (Bones, Joints, and Muscles)": ["Orthopedic Nurse", "Post-Ortho Surgery Nurse"],
            "Pediatrics (Child Healthcare)": ["Pediatric Nurse", "Pediatric ICU Nurse"],
            "Psychiatry & Mental Health": ["Psychiatric Nurse", "Mental Health Nurse"],
            "Pulmonology (Lungs & Respiratory System)": ["Pulmonary Nurse", "Respiratory Therapy Nurse"],
            "Rehabilitation & Physical Therapy": ["Rehab Nurse", "Physiotherapy Support Nurse"],
            "Surgery (General & Subspecialties)": ["Scrub Nurse", "Circulating Nurse", "Perioperative Nurse"]
        };

        document.getElementById("nurseDepartment").addEventListener("change", function() {
            const dept = this.value;
            const specializationSelect = document.getElementById("nurseSpecialization");
            const otherInput = document.getElementById("otherNurseSpecialization");
            
            specializationSelect.innerHTML = '<option value="">--- Select Specialization ---</option>';
            
            if (nurseSpecializations[dept]) {
                nurseSpecializations[dept].forEach(function(spec) {
                    const opt = document.createElement("option");
                    opt.value = spec;
                    opt.textContent = spec;
                    specializationSelect.appendChild(opt);
                });
                const optOthers = document.createElement("option");
                optOthers.value = "Others";
                optOthers.textContent = "Others";
                specializationSelect.appendChild(optOthers);
            }
            otherInput.style.display = "none";
        });

        document.getElementById("nurseSpecialization").addEventListener("change", function() {
            const otherInput = document.getElementById("otherNurseSpecialization");
            if (this.value === "Others") {
                otherInput.style.display = "block";
            } else {
                otherInput.style.display = "none";
            }
        });

    </script>
    <script src="assets/Bootstrap/all.min.js"></script>
    <script src="assets/Bootstrap/bootstrap.bundle.min.js"></script>
    <script src="assets/Bootstrap/fontawesome.min.js"></script>
    <script src="assets/Bootstrap/jq.js"></script>
</body>

</html>