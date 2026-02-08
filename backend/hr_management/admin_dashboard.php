<?php
require '../../SQL/config.php';
require_once 'classes/Auth.php';
require_once 'classes/User.php';
require_once 'classes/Employee.php';
require_once 'classes/LeaveNotification.php';
require_once 'classes/Dashboard.php';
include 'includes/FooterComponent.php';

Auth::checkHR();

$conn = $conn;

$userId = Auth::getUserId();
if (!$userId) die("User ID not set.");

$userObj = new User($conn);
$user = $userObj->getById($userId);
if (!$user) die("User not found.");

$dashboard = new Dashboard($conn);
$leaveNotif = new LeaveNotification($conn);

// For cards
$employee = new Employee($conn);
$totalDoctors    = $employee->countByProfession('Doctor');
$totalNurses     = $employee->countByProfession('Nurse');
$totalPharma     = $employee->countByProfession('Pharmacist');
$totalAccountant = $employee->countByProfession('Accountant');
$totalLab        = $employee->countByProfession('Laboratorist');

// Get all employees for dropdown
$employees = $dashboard->getAllEmployees();
$employeeId = $employees[0]['employee_id'] ?? 0; // default first employee


// Get attendance summary for default employee
$attendanceSummary = $dashboard->getEmployeeAttendanceSummary($employeeId);

// Pending leave count
$pendingCount = $leaveNotif->getPendingLeaveCount();
?>


<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HMS | HR Management</title>
    <link rel="shortcut icon" href="assets/image/favicon.ico" type="image/x-icon">
    <link rel="stylesheet" href="assets/CSS/bootstrap.min.css">
    <link rel="stylesheet" href="assets/CSS/super.css">
    <link rel="stylesheet" href="assets/CSS/admin_dashboard.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

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
                <a href="admin_dashboard.php" class="sidebar-link" data-bs-toggle="#" data-bs-target="#"
                    aria-expanded="false" aria-controls="auth">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-cast" viewBox="0 0 16 16">
                        <path d="m7.646 9.354-3.792 3.792a.5.5 0 0 0 .353.854h7.586a.5.5 0 0 0 .354-.854L8.354 9.354a.5.5 0 0 0-.708 0" />
                        <path d="M11.414 11H14.5a.5.5 0 0 0 .5-.5v-7a.5.5 0 0 0-.5-.5h-13a.5.5 0 0 0-.5.5v7a.5.5 0 0 0 .5.5h3.086l-1 1H1.5A1.5 1.5 0 0 1 0 10.5v-7A1.5 1.5 0 0 1 1.5 2h13A1.5 1.5 0 0 1 16 3.5v7a1.5 1.5 0 0 1-1.5 1.5h-2.086z" />
                    </svg>
                    <span style="font-size: 18px;">Dashboard</span>
                </a>
            </li>

            <li class="sidebar-item">
                <a href="#" class="sidebar-link collapsed has-dropdown" data-bs-toggle="collapse" data-bs-target="#gerald" aria-expanded="true" aria-controls="auth">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-person-fill-add" viewBox="0 0 16 16">
                        <path d="M12.5 16a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7m.5-5v1h1a.5.5 0 0 1 0 1h-1v1a.5.5 0 0 1-1 0v-1h-1a.5.5 0 0 1 0-1h1v-1a.5.5 0 0 1 1 0m-2-6a3 3 0 1 1-6 0 3 3 0 0 1 6 0"/>
                        <path d="M2 13c0 1 1 1 1 1h5.256A4.5 4.5 0 0 1 8 12.5a4.5 4.5 0 0 1 1.544-3.393Q8.844 9.002 8 9c-5 0-6 3-6 4"/>
                    </svg>
                    <span style="font-size: 18px;">Recruitment & Onboarding Management</span>
                </a>

                <ul id="gerald" class="sidebar-dropdown list-unstyled collapse" data-bs-parent="#sidebar">
                    <li class="sidebar-item">
                        <a href="recruitment_onboarding_module/job_management.php" class="sidebar-link">Job Management</a>
                    </li>
                    <li class="sidebar-item">
                        <a href="recruitment_onboarding_module/applicant_management.php" class="sidebar-link">Applicant Management</a>
                    </li>
                    <li class="sidebar-item">
                        <a href="recruitment_onboarding_module/onboarding.php" class="sidebar-link">Onboarding</a>
                    </li>
                </ul>
            </li>

            <li class="sidebar-item">
                <a href="#" class="sidebar-link collapsed has-dropdown" data-bs-toggle="collapse" data-bs-target="#geraldd" aria-expanded="true" aria-controls="auth">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-person-vcard" viewBox="0 0 16 16" style="margin-bottom: 6px;">
                        <path d="M5 8a2 2 0 1 0 0-4 2 2 0 0 0 0 4m4-2.5a.5.5 0 0 1 .5-.5h4a.5.5 0 0 1 0 1h-4a.5.5 0 0 1-.5-.5M9 8a.5.5 0 0 1 .5-.5h4a.5.5 0 0 1 0 1h-4A.5.5 0 0 1 9 8m1 2.5a.5.5 0 0 1 .5-.5h3a.5.5 0 0 1 0 1h-3a.5.5 0 0 1-.5-.5" />
                        <path d="M2 2a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V4a2 2 0 0 0-2-2zM1 4a1 1 0 0 1 1-1h12a1 1 0 0 1 1 1v8a1 1 0 0 1-1 1H8.96q.04-.245.04-.5C9 10.567 7.21 9 5 9c-2.086 0-3.8 1.398-3.984 3.181A1 1 0 0 1 1 12z" />
                    </svg>
                    <span style="font-size: 18px;">Time & Attendance</span>
                </a>

                <ul id="geraldd" class="sidebar-dropdown list-unstyled collapse" data-bs-parent="#sidebar">
                    <li class="sidebar-item">
                        <a href="time_attendance_module/clock-in_clock-out.php" class="sidebar-link">Clock-In/Clock-Out</a>
                    </li>
                    <li class="sidebar-item">
                        <a href="time_attendance_module/daily_attendance_records.php" class="sidebar-link">Daily Attendance Records</a>
                    </li>
                    <li class="sidebar-item">
                        <a href="time_attendance_module/attendance_reports.php" class="sidebar-link">Attendance Reports</a>
                    </li>
                </ul>
            </li>

            <li class="sidebar-item">
                <a href="#" class="sidebar-link collapsed has-dropdown" data-bs-toggle="collapse" data-bs-target="#geralddd" aria-expanded="true" aria-controls="auth">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-box-arrow-right" viewBox="0 0 16 16">
                        <path fill-rule="evenodd" d="M10 12.5a.5.5 0 0 1-.5.5h-8a.5.5 0 0 1-.5-.5v-9a.5.5 0 0 1 .5-.5h8a.5.5 0 0 1 .5.5v2a.5.5 0 0 0 1 0v-2A1.5 1.5 0 0 0 9.5 2h-8A1.5 1.5 0 0 0 0 3.5v9A1.5 1.5 0 0 0 1.5 14h8a1.5 1.5 0 0 0 1.5-1.5v-2a.5.5 0 0 0-1 0z"/>
                        <path fill-rule="evenodd" d="M15.854 8.354a.5.5 0 0 0 0-.708l-3-3a.5.5 0 0 0-.708.708L14.293 7.5H5.5a.5.5 0 0 0 0 1h8.793l-2.147 2.146a.5.5 0 0 0 .708.708z"/>
                    </svg>
                    <span style="font-size: 18px;">Leave Management</span>
                    <?php if ($pendingCount > 0): ?>
                        <span class="badge bg-danger rounded-pill"><?= $pendingCount ?></span>
                    <?php endif; ?>
                </a>

                <ul id="geralddd" class="sidebar-dropdown list-unstyled collapse" data-bs-parent="#sidebar">
                    <li class="sidebar-item">
                        <a href="leave_management_module/leave_application.php" class="sidebar-link">Leave Application</a>
                    </li>
                    <li class="sidebar-item">
                        <a href="leave_management_module/leave_approval.php" class="sidebar-link d-flex justify-content-between align-items-center">
                            Leave Approval
                            <?php if ($pendingCount > 0): ?>
                                <span class="badge bg-danger rounded-pill"><?= $pendingCount ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                    <li class="sidebar-item">
                        <a href="leave_management_module/leave_credit_management.php" class="sidebar-link">Leave Credit Management</a>
                    </li>
                    <li class="sidebar-item">
                        <a href="leave_management_module/leave_reports.php" class="sidebar-link">Leave Reports</a>
                    </li>
                </ul>
            </li>

            <li class="sidebar-item">
                <a href="#" class="sidebar-link collapsed has-dropdown" data-bs-toggle="collapse" data-bs-target="#geraldddd" aria-expanded="true" aria-controls="auth">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-cash-stack" viewBox="0 0 16 16">
                        <path d="M1 3a1 1 0 0 1 1-1h12a1 1 0 0 1 1 1zm7 8a2 2 0 1 0 0-4 2 2 0 0 0 0 4"/>
                        <path d="M0 5a1 1 0 0 1 1-1h14a1 1 0 0 1 1 1v8a1 1 0 0 1-1 1H1a1 1 0 0 1-1-1zm3 0a2 2 0 0 1-2 2v4a2 2 0 0 1 2 2h10a2 2 0 0 1 2-2V7a2 2 0 0 1-2-2z"/>
                    </svg>
                    <span style="font-size: 18px;">Payroll & Compensation Benifits</span>
                </a>

                <ul id="geraldddd" class="sidebar-dropdown list-unstyled collapse" data-bs-parent="#sidebar">
                    <li class="sidebar-item">
                        <a href="payroll_compensation_benifits_module/salary_computation.php" class="sidebar-link">Salary Computation</a>
                    </li>
                    <li class="sidebar-item">
                        <a href="payroll_compensation_benifits_module/compensation_benifits.php" class="sidebar-link">Compensation & Benifits</a>
                    </li>
                    <li class="sidebar-item">
                        <a href="payroll_compensation_benifits_module/payroll_reports.php" class="sidebar-link">Payroll Reports</a>
                    </li>
                </ul>
            </li>

            <li class="sidebar-item">
                <a href="department_budget_approval/department_budget_approval.php" class="sidebar-link" data-bs-toggle="#" data-bs-target="#"
                    aria-expanded="false" aria-controls="auth">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-cash-coin" viewBox="0 0 16 16">
                        <path fill-rule="evenodd" d="M11 15a4 4 0 1 0 0-8 4 4 0 0 0 0 8m5-4a5 5 0 1 1-10 0 5 5 0 0 1 10 0"/>
                        <path d="M9.438 11.944c.047.596.518 1.06 1.363 1.116v.44h.375v-.443c.875-.061 1.386-.529 1.386-1.207 0-.618-.39-.936-1.09-1.1l-.296-.07v-1.2c.376.043.614.248.671.532h.658c-.047-.575-.54-1.024-1.329-1.073V8.5h-.375v.45c-.747.073-1.255.522-1.255 1.158 0 .562.378.92 1.007 1.066l.248.061v1.272c-.384-.058-.639-.27-.696-.563h-.668zm1.36-1.354c-.369-.085-.569-.26-.569-.522 0-.294.216-.514.572-.578v1.1zm.432.746c.449.104.655.272.655.569 0 .339-.257.571-.709.614v-1.195z"/>
                        <path d="M1 0a1 1 0 0 0-1 1v8a1 1 0 0 0 1 1h4.083q.088-.517.258-1H3a2 2 0 0 0-2-2V3a2 2 0 0 0 2-2h10a2 2 0 0 0 2 2v3.528c.38.34.717.728 1 1.154V1a1 1 0 0 0-1-1z"/>
                        <path d="M9.998 5.083 10 5a2 2 0 1 0-3.132 1.65 6 6 0 0 1 3.13-1.567"/>
                    </svg>
                    <span style="font-size: 18px;">Department Budget Approval</span>
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
            <!-- ----- Card-List of Employees ----- -->
            <div class="row">
                <h5 class="row-title">Number of Active Employees per Profession</h5>
                <div class="card">
                    <a href="recruitment_onboarding_module/list_of_doctors.php">
                        <div class="card-body">
                            <h5 class="card-title">Doctor</h5>
                            <p class="card-text"><strong><?php echo $totalDoctors; ?></strong> active doctors.</p>
                        </div>
                    </a>
                </div>

                <div class="card">
                    <a href="recruitment_onboarding_module/list_of_nurses.php">
                        <div class="card-body">
                            <h5 class="card-title">Nurse</h5>
                            <p class="card-text"><strong><?php echo $totalNurses; ?></strong> active nurses.</p>
                        </div>
                    </a>
                </div>

                <div class="card">
                    <a href="recruitment_onboarding_module/list_of_pharmacists.php">
                        <div class="card-body">
                            <h5 class="card-title">Pharmacist</h5>
                            <p class="card-text"><strong><?php echo $totalPharma; ?></strong> active pharmacist.</p>
                        </div>
                    </a>
                </div>

                <div class="card">
                    <a href="recruitment_onboarding_module/list_of_accountants.php">
                        <div class="card-body">
                            <h5 class="card-title">Accountant</h5>
                            <p class="card-text"><strong><?php echo $totalAccountant; ?></strong> active accountant.</p>
                        </div>
                    </a>
                </div>

                <div class="card">
                    <a href="recruitment_onboarding_module/list_of_laboratorist.php">
                        <div class="card-body">
                            <h5 class="card-title">Laboratorist</h5>
                            <p class="card-text"><strong><?php echo $totalLab; ?></strong> active laboratorist.</p>
                        </div>
                    </a>
                </div>

            </div>

            <div class="dashboard-attendance-container">
                <!-- ----- Attendance Summary Per Day ----- -->
                <div class="dashboard-attendanceperday-card">
                    <div class="dashboard-attendanceperday-body">
                        <h5 class="dashboard-attendanceperday-title">Daily Attendance Overview</h5>

                        <input 
                            type="date" 
                            id="attendanceDate" 
                            class="form-control"
                            value="<?php echo date('Y-m-d'); ?>"
                        >

                        <canvas id="dashboardAttendancePerDayChart"></canvas>
                    </div>
                </div>

                <!-- ----- Attendance Summary Per Employee ----- -->
                <div class="dashboard-attendance-card">
                    <div class="dashboard-attendance-body">
                        <h5 class="dashboard-attendance-title">Employee Attendance Summary</h5>
                        <div class="filters-row">

                            <!-- Employee -->
                            <div class="filter-container">
                                <label for="employee_id">Employee</label>
                                <select name="employee_id" id="employee_id" required>
                                    <option value="">----- Select Employee -----</option>
                                    <?php foreach ($employees as $emp): ?>
                                        <option value="<?= $emp['employee_id']; ?>">
                                            <?= htmlspecialchars($emp['full_name'] ?? ''); ?> (ID: <?= $emp['employee_id']; ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- Month -->
                            <div class="filter-container">
                                <label for="month">Month</label>
                                <select id="month">
                                    <?php
                                    $months = [
                                        1=>'January',2=>'February',3=>'March',
                                        4=>'April',5=>'May',6=>'June',
                                        7=>'July',8=>'August',9=>'September',
                                        10=>'October',11=>'November',12=>'December'
                                    ];
                                    $currentMonth = date('n');
                                    foreach ($months as $num => $name):
                                    ?>
                                        <option value="<?= $num ?>" <?= $num == $currentMonth ? 'selected' : '' ?>>
                                            <?= $name ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- Year -->
                            <div class="filter-container">
                                <label for="year">Year</label>
                                <select id="year">
                                    <?php
                                    $currentYear = date('Y');
                                    for ($y = $currentYear; $y >= $currentYear - 5; $y--):
                                    ?>
                                        <option value="<?= $y ?>"><?= $y ?></option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                        </div>
                        <canvas id="dashboardAttendanceChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- ---------- Pending Leave Card ---------- -->
            <div class="dashboard-leave-card">
                <div class="dashboard-leave-body">
                    <h5 class="dashboard-leave-title">Pending Leave Requests</h5>

                    <div class="leave-content">
                        <!-- Chart Column -->
                        <div class="leave-chart">
                            <canvas id="dashboardLeaveChart"></canvas>
                        </div>

                        <!-- Table Column -->
                        <div class="leave-table">
                            <div class="table-responsive">
                                <table class="table table-bordered" id="pendingLeaveTable">
                                    <thead>
                                        <tr>
                                            <th>Employee</th>
                                            <th>Leave Type</th>
                                            <th>Duration</th>
                                            <th>Dates</th>
                                            <th>Reason</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        $pendingLeaves = $dashboard->getPendingLeaveDetails();

                                        if (empty($pendingLeaves)) :
                                        ?>
                                            <tr>
                                                <td colspan="5" class="text-center">No leave applications found.</td>
                                            </tr>
                                        <?php
                                        else:
                                            foreach ($pendingLeaves as $leave):
                                                $start = new DateTime($leave['leave_start_date']);
                                                $end   = new DateTime($leave['leave_end_date']);
                                                $interval = $start->diff($end)->days + 1;

                                                if ($leave['leave_duration'] === 'Half Day') {
                                                    $interval = 0.5;
                                                }
                                        ?>
                                            <tr>
                                                <td><?= htmlspecialchars($leave['full_name']) ?></td>
                                                <td><?= htmlspecialchars($leave['leave_type']) ?></td>
                                                <td><?= $interval ?> day<?= $interval != 1 ? 's' : '' ?></td>
                                                <td><?= htmlspecialchars($leave['leave_start_date']) ?> - <?= htmlspecialchars($leave['leave_end_date']) ?></td>
                                                <td><?= htmlspecialchars($leave['leave_reason']) ?></td>
                                            </tr>
                                        <?php
                                            endforeach;
                                        endif;
                                        ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ---------- Dashboard Payroll Per Month, 1st Half and 2nd Half ---------- -->
            <div class="dashboard-payroll-card">
                <div class="dashboard-payroll-body">
                    <h5 class="dashboard-payroll-title">Payroll Overview (Full Month vs 1st Half vs 2nd Half)</h5>

                    <div class="year-select-container">
                        <label for="year_select">Select Year:</label>
                        <select id="year_select">
                            <option value="2026" selected>2026</option>
                            <option value="2025">2025</option>
                            <option value="2024">2024</option>
                        </select>
                    </div>

                    <canvas id="payrollLineChart"></canvas>
                </div>
            </div>

            <!-- END CODING HERE -->
        </div>
        <!----- End of Main Content ----->
    </div>

    <!----- Footer Content ----->
    <?php FooterComponent::render();?>

    <!----- End of Footer Content ----->

    <script>
        window.addEventListener("load", function () {
            setTimeout(() => {
                document.getElementById("loading-screen").style.display = "none";
                document.body.classList.add("show-cards");

                // ----- STAGGER RENDER CHARTS -----
                setTimeout(renderPendingLeaveChart, 200);   // Pending Leave
                setTimeout(() => loadAttendanceChart(document.getElementById("attendanceDate").value), 400); // Daily Doughnut
                setTimeout(() => fetchEmployeeAttendance(document.getElementById("employee_id").value), 600); // Employee Bar

            }, 2000);
        });

        const toggler = document.querySelector(".toggler-btn");
        toggler.addEventListener("click", function() {
            document.querySelector("#sidebar").classList.toggle("collapsed");
        });

        // ---------- FORCE CANVAS READY ----------
        function forceCanvasReady(canvas, defaultHeight=300){
            canvas.style.display = "block";
            if (!canvas.height) canvas.height = defaultHeight;
            if (!canvas.width) canvas.width = canvas.parentElement.offsetWidth || 400;
            canvas.offsetHeight; // force reflow
        }

        // ---------------- Pending Leave Bar Chart ----------------
        let pendingLeaveChart;
        function renderPendingLeaveChart() {
            const canvas = document.getElementById("dashboardLeaveChart");
            forceCanvasReady(canvas);
            const ctx = canvas.getContext("2d");

            fetch("get_pending_leave.php")
                .then(res => res.json())
                .then(data => {
                    const labels = data.map(d => d.department_name);
                    const counts = data.map(d => Number(d.total_pending));

                    const departmentColors = {
                        "Anesthesiology & Pain Management": "#FF6384",
                        "Cardiology (Heart & Vascular System)": "#36A2EB",
                        "Dermatology (Skin, Hair, & Nails)": "#FFCE56",
                        "Ear, Nose, and Throat (ENT)": "#4BC0C0",
                        "Emergency Department (ER)": "#9966FF",
                        "Gastroenterology (Digestive System & Liver)": "#FF9F40",
                        "Geriatrics & Palliative Care": "#C9CBCF",
                        "Infectious Diseases & Immunology": "#8DD1E1",
                        "Internal Medicine (General & Subspecialties)": "#FFB6C1",
                        "Nephrology (Kidneys & Dialysis)": "#FFD700",
                        "Neurology & Neurosurgery (Brain & Nervous System)": "#20B2AA",
                        "Obstetrics & Gynecology (OB-GYN)": "#FF7F50",
                        "Oncology (Cancer Treatment)": "#87CEFA",
                        "Ophthalmology (Eye Care)": "#DA70D6",
                        "Orthopedics (Bones, Joints, and Muscles)": "#98FB98",
                        "Pediatrics (Child Healthcare)": "#FFA07A",
                        "Psychiatry & Mental Health": "#A0522D",
                        "Pulmonology (Lungs & Respiratory System)": "#B0E0E6",
                        "Rehabilitation & Physical Therapy": "#F08080",
                        "Surgery (General & Subspecialties)": "#4682B4",
                        "Geriatrics & Palliative Care (Elderly & Terminal Care)": "#C0C0C0",
                        "Pharmacy": "#FF69B4",
                        "Laboratory Department": "#7FFF00",
                        "Billing": "#00CED1",
                        "Insurance": "#FF4500",
                        "Expenses": "#8A2BE2"
                    };

                    const bgColors = labels.map(l => departmentColors[l] || "#36A2EB");

                    if(pendingLeaveChart){
                        pendingLeaveChart.data.labels = labels;
                        pendingLeaveChart.data.datasets[0].data = counts;
                        pendingLeaveChart.data.datasets[0].backgroundColor = bgColors;
                        pendingLeaveChart.update({duration:1200,easing:'easeOutQuart'});
                    } else {
                        pendingLeaveChart = new Chart(ctx, {
                            type: "bar",
                            data: { labels, datasets:[{
                                label: "Pending Leave Requests",
                                data: counts,
                                backgroundColor: bgColors,
                                borderColor: bgColors,
                                borderWidth: 1,
                                borderRadius: 6,
                                base: 0
                            }]},
                            options: {
                                responsive:true,
                                maintainAspectRatio:false,
                                indexAxis: "x",
                                animation:{ duration:1200, easing:"easeOutQuart" },
                                scales:{ y:{ beginAtZero:true, min:0, ticks:{ stepSize:1 } } },
                                plugins:{ legend:{ display:false }, tooltip:{ enabled:true } }
                            }
                        });
                    }
                })
                .catch(err=>console.error("Pending Leave Chart error:", err));
        }

        // ---------------- Daily Doughnut Chart ----------------
        let attendanceChart;

        const centerTextPlugin = {
            id: 'centerText',
            afterDraw(chart) {
                const { ctx, chartArea: { width, height } } = chart;

                // Compute total employees dynamically (count Half Day / Half Leave as 0.5)
                const totalEmployees = chart.data.labels.reduce((sum, label, idx) => {
                    let value = chart.data.datasets[0].data[idx];
                    if(label === 'Half Day' || label === 'On Leave (Half Day)' || label === 'Absent (Half Day)') {
                        value = value * 1;
                    }
                    return sum + value;
                }, 0);

                ctx.save();
                ctx.font = 'bold 24px Arial';
                ctx.fillStyle = '#333';
                ctx.textAlign = 'center';
                ctx.textBaseline = 'middle';

                // Total employees sa gitna
                ctx.fillText(totalEmployees, width / 2, height / 2);

                // Label sa ilalim ng number
                ctx.font = '12px Arial';
                ctx.fillText('Employees', width / 2, height / 2 + 25);

                ctx.restore();
            }
        };

        // Define all statuses and their colors
        const allStatuses = [
            'Present', 'Late', 'Undertime', 'Overtime',
            'Half Day', 'On Leave', 'On Leave (Half Day)',
            'Absent', 'Absent (Half Day)'
        ];

        const statusColors = [
            'green', 'red', 'orange', 'blue',
            'lightgreen', 'gray', 'darkgray',
            'black', 'dimgray'
        ];

        function forceCanvasReady(canvas) {
            canvas.style.display = 'none';
            canvas.offsetHeight; // force reflow
            canvas.style.display = 'block';
        }

        function loadAttendanceChart(date) {
            const canvas = document.getElementById('dashboardAttendancePerDayChart');
            forceCanvasReady(canvas);
            const ctx = canvas.getContext('2d');

            fetch(`get_daily_attendance.php?date=${date}`)
                .then(res => res.json())
                .then(data => {
                    // Map all statuses to data, default 0
                    const chartData = allStatuses.map(s => data[s] || 0);

                    if (attendanceChart) {
                        attendanceChart.data.labels = allStatuses;
                        attendanceChart.data.datasets[0].data = chartData;
                        attendanceChart.data.datasets[0].backgroundColor = statusColors;
                        attendanceChart.update({ duration: 1200, easing: 'easeOutCubic' });
                    } else {
                        attendanceChart = new Chart(ctx, {
                            type: 'doughnut',
                            data: {
                                labels: allStatuses,
                                datasets: [{ 
                                    data: chartData, 
                                    backgroundColor: 
                                    statusColors, 
                                    borderColor: '#fff', 
                                    borderWidth: 2 }]
                            },
                            plugins: [centerTextPlugin],
                            options: {
                                responsive: true,
                                maintainAspectRatio: false, 
                                cutout: '65%',
                                animation: { animateRotate: true, duration: 1200, easing: 'easeOutCubic' },
                                plugins: { legend: { position: 'right' } }
                            }
                        });
                    }
                })
                .catch(err => console.error("Daily Doughnut Chart error:", err));
        }

        // Default date load
        document.addEventListener("DOMContentLoaded", function() {
            const dateInput = document.getElementById("attendanceDate");
            loadAttendanceChart(dateInput.value);
            dateInput.addEventListener("change", function() { loadAttendanceChart(this.value); });
        });

        // ---------------- Employee Bar Chart ----------------
        let empChart;

        function fetchEmployeeAttendance() {
            const employeeId = document.getElementById('employee_id').value;
            const month      = document.getElementById('month').value;
            const year       = document.getElementById('year').value;

            if (!employeeId) return;

            const canvas = document.getElementById('dashboardAttendanceChart');
            forceCanvasReady(canvas);
            const ctx = canvas.getContext('2d');

            fetch(`get_attendance_summary.php?employee_id=${employeeId}&month=${month}&year=${year}`)
                .then(res => res.json())
                .then(data => {
                    const values = allStatuses.map(s => data[s] || 0);

                    if (empChart) {
                        empChart.data.datasets[0].data = values;
                        empChart.update({ duration: 800, easing: 'easeOutQuart' });
                    } else {
                        empChart = new Chart(ctx, {
                            type: 'bar',
                            data: {
                                labels: allStatuses,
                                datasets: [{
                                    label: 'Days',
                                    data: values,
                                    backgroundColor: statusColors,
                                    borderRadius: 6
                                }]
                            },
                            options: {
                                responsive: true,
                                scales: {
                                    y: {
                                        beginAtZero: true,
                                        ticks: { stepSize: 1 }
                                    }
                                },
                                plugins: {
                                    legend: { display: false }
                                }
                            }
                        });
                    }
                })
                .catch(err => console.error("Employee Chart error:", err));
        }

        // ---------------- Event Listeners ----------------
        document.addEventListener("DOMContentLoaded", function () {
            ['employee_id', 'month', 'year'].forEach(id => {
                document.getElementById(id).addEventListener('change', fetchEmployeeAttendance);
            });
        });

        // ---------- Dashboard Payroll Per Month, 1st Half and 2nd Half ---------- 
        let payrollChart;

        // Function to fetch data and render chart
        function renderPayrollChart(year) {
            fetch(`get_payroll_lines.php?year=${year}`)
                .then(res => res.json())
                .then(data => {
                    const labels = data.map(item => item.month);
                    const fullMonth = data.map(item => item.full_month);
                    const firstHalf = data.map(item => item.first_half);
                    const secondHalf = data.map(item => item.second_half);

                    const ctx = document.getElementById('payrollLineChart').getContext('2d');

                    if (payrollChart) {
                        payrollChart.data.labels = labels;
                        payrollChart.data.datasets[0].data = fullMonth;
                        payrollChart.data.datasets[1].data = firstHalf;
                        payrollChart.data.datasets[2].data = secondHalf;
                        payrollChart.update();
                    } else {
                        payrollChart = new Chart(ctx, {
                            type: 'line',
                            data: {
                                labels: labels,
                                datasets: [
                                    {
                                        label: 'Full Month',
                                        data: fullMonth,
                                        borderColor: 'rgba(54, 162, 235, 1)',
                                        backgroundColor: 'rgba(54, 162, 235, 0.2)',
                                        fill: true,
                                        tension: 0.4
                                    },
                                    {
                                        label: '1st Half',
                                        data: firstHalf,
                                        borderColor: 'rgba(75, 192, 192, 1)',
                                        backgroundColor: 'rgba(75, 192, 192, 0.2)',
                                        fill: false,
                                        tension: 0.4
                                    },
                                    {
                                        label: '2nd Half',
                                        data: secondHalf,
                                        borderColor: 'rgba(255, 206, 86, 1)',
                                        backgroundColor: 'rgba(255, 206, 86, 0.2)',
                                        fill: false,
                                        tension: 0.4
                                    }
                                ]
                            },
                            options: {
                                responsive: true,
                                plugins: {
                                    title: {
                                        display: true,
                                        text: `Payroll Overview (All Employees) - ${year}`
                                    },
                                    tooltip: {
                                        mode: 'index',
                                        intersect: false,
                                        callbacks: {
                                            label: function(context){
                                                return '₱' + context.raw.toLocaleString();
                                            }
                                        }
                                    },
                                    legend: {
                                        display: true
                                    }
                                },
                                scales: {
                                    y: {
                                        beginAtZero: true,
                                        ticks: {
                                            callback: value => '₱' + value.toLocaleString()
                                        }
                                    }
                                }
                            }
                        });
                    }
                });
        }

        // Initial render with current year
        renderPayrollChart(document.getElementById('year_select').value);

        // Event listener for year change
        document.getElementById('year_select').addEventListener('change', function() {
            renderPayrollChart(this.value);
        });

    </script>
    <script src="assets/Bootstrap/all.min.js"></script>
    <script src="assets/Bootstrap/bootstrap.bundle.min.js"></script>
    <script src="assets/Bootstrap/fontawesome.min.js"></script>
    <script src="assets/Bootstrap/jq.js"></script>
</body>

</html>