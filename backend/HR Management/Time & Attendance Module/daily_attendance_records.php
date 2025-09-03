<?php
require '../../../SQL/config.php';
include '../includes/FooterComponent.php';
require '../classes/Auth.php';
require '../classes/User.php';
require '../classes/LeaveNotification.php';

Auth::checkHR();

$conn = $conn;

$userId = Auth::getUserId();
if (!$userId) {
    die("User ID not set.");
}

$userModel = new User($conn);
$leaveNotif = new LeaveNotification($conn);

$userObj = new User($conn);
$user = $userObj->getById($userId);
if (!$user) {
    die("User not found.");
}

$pendingCount = $leaveNotif->getPendingLeaveCount();

// Standard working hours
$standard_start = '08:00:00';
$standard_end   = '17:00:00';

// Get date from GET parameter, default to today
$date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');

// Previous and next day for navigation
$prev_day = date('Y-m-d', strtotime($date .' -1 day'));
$next_day = date('Y-m-d', strtotime($date .' +1 day'));

// Fetch attendance records for selected date
$sql = "SELECT 
    employee_id,
    attendance_date,
    time_in,
    time_out,
    working_hours,
    late_minutes,
    undertime_minutes,
    overtime_minutes,
    status
FROM hr_daily_attendance
WHERE attendance_date = '$date'
ORDER BY employee_id ASC
";


$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HMS | HR Management</title>
    <link rel="shortcut icon" href="../assets/image/favicon.ico" type="image/x-icon">
    <link rel="stylesheet" href="../assets/CSS/bootstrap.min.css">
    <link rel="stylesheet" href="../assets/CSS/super.css">
    <link rel="stylesheet" href="css/daily_attendance_records.css">
</head>

<body>

    <!----- Full-page Loader ----->
    <div id="loading-screen">
        <div class="loader">
            <div></div>
            <div></div>
            <div></div>
            <div></div>
            <div></div>
        </div>
    </div>

    <div class="d-flex">
        <!----- Sidebar ----->
        <aside id="sidebar" class="sidebar-toggle">

            <div class="sidebar-logo mt-3">
                <img src="../assets/image/logo-dark.png" width="90px" height="20px">
            </div>

            <div class="menu-title">Navigation</div>

            <!----- Sidebar Navigation ----->
        
            <li class="sidebar-item">
                <a href="../admin_dashboard.php" class="sidebar-link" data-bs-toggle="#" data-bs-target="#"
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
                        <a href="../Recruitment & Onboarding Module/job_management.php" class="sidebar-link">Job Management</a>
                    </li>
                    <li class="sidebar-item">
                        <a href="../Recruitment & Onboarding Module/applicant_management.php" class="sidebar-link">Applicant Management</a>
                    </li>
                    <li class="sidebar-item">
                        <a href="../Recruitment & Onboarding Module/onboarding.php" class="sidebar-link">Onboarding</a>
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
                        <a href="clock-in_clock-out.php" class="sidebar-link">Clock-In/Clock-Out</a>
                    </li>
                    <li class="sidebar-item">
                        <a href="daily_attendance_records.php" class="sidebar-link">Daily Attendance Records</a>
                    </li>
                        <a href="attendance_records.php" class="sidebar-link">Attendance Reports</a>
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
                        <a href="../Leave Management Module/leave_application.php" class="sidebar-link">Leave Application</a>
                    </li>
                    <li class="sidebar-item">
                        <a href="../Leave Management Module/leave_approval.php" class="sidebar-link d-flex justify-content-between align-items-center">
                            Leave Approval
                            <?php if ($pendingCount > 0): ?>
                                <span class="badge bg-danger rounded-pill"><?= $pendingCount ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                    <li class="sidebar-item">
                        <a href="../Leave Management Module/leave_credit_management.php" class="sidebar-link">Leave Credit Management</a>
                    </li>
                    <li class="sidebar-item">
                        <a href="../Leave Management Module/leave_reports.php" class="sidebar-link">Leave Reports</a>
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
                        <a href="../Payroll & Compensation Benifits Module/salary_computation.php" class="sidebar-link">Salary Computation</a>
                    </li>
                    <li class="sidebar-item">
                        <a href="../Payroll & Compensation Benifits Module/compensation_benifits.php" class="sidebar-link">Compensation & Benifits</a>
                    </li>
                    <li class="sidebar-item">
                        <a href="../Payroll & Compensation Benifits Module/payroll_reports.php" class="sidebar-link">Payroll Reports</a>
                    </li>
                </ul>
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
                                <a class="dropdown-item" href="../../logout.php" style="font-size: 14px; color: #007bff; text-decoration: none; padding: 8px 12px; border-radius: 4px; transition: background-color 0.3s ease;">
                                    Logout
                                </a>
                            </li>
                        </ul>

                    </div>
                </div>
            </div>
            <!-- START CODING HERE -->
            <div class="attendance">
                <p style="text-align: center; font-size: 35px; font-weight: bold; padding-bottom: 20px; color: black;">Daily Attendance Records (<?php echo $date; ?>)</p>

                <!-- Navigation -->
                <form method="GET" class="attendance-nav" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; max-width: 900px; margin-left: auto; margin-right: auto;">
                    
                    <!-- Previous Day Button -->
                    <button type="submit" class="nav-btn" 
                        onclick="document.getElementById('form-date').value='<?= $prev_day; ?>'">
                        ⬅ Previous Day
                    </button>

                    <!-- Hidden input to store the selected date -->
                    <input type="hidden" name="date" id="form-date" value="<?= $date; ?>">

                    <!-- Calendar Date Picker -->
                    <input type="date" class="date-picker" value="<?= $date; ?>" 
                        onchange="document.getElementById('form-date').value=this.value; this.form.submit();"
                        style="padding: 5px 10px; font-size: 16px;">

                    <!-- Next Day Button -->
                    <button type="submit" class="nav-btn" 
                        onclick="document.getElementById('form-date').value='<?= $next_day; ?>'">
                        Next Day ➡
                    </button>

                </form>
                <br />

                <table id="AttendanceTable">
                    <thead>
                        <tr>
                            <th>Employee ID</th>
                            <th>Date</th>
                            <th>Time In</th>
                            <th>Time Out</th>
                            <th>Hours Worked</th>
                            <th>Late (min)</th>
                            <th>Undertime (min)</th>
                            <th>Overtime (min)</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if($result->num_rows > 0): ?>
                            <?php while($row = $result->fetch_assoc()): ?>
                                <?php
                                    // Determine status to show in badge
                                    if(empty($row['time_in']) && empty($row['time_out']) && $row['status'] != 'On Leave') {
                                        $statusText = 'Absent';
                                    } elseif(isset($row['late_minutes']) && $row['late_minutes'] > 0) {
                                        $statusText = 'Late';
                                    } elseif(isset($row['undertime_minutes']) && $row['undertime_minutes'] > 0) {
                                        $statusText = 'Undertime';
                                    } elseif(isset($row['overtime_minutes']) && $row['overtime_minutes'] > 0) {
                                        $statusText = 'Overtime';
                                    } else {
                                        $statusText = !empty($row['status']) ? $row['status'] : '-';
                                    }

                                    // Generate CSS class dynamically
                                    $statusClass = 'status-' . strtolower(str_replace(' ', '', $statusText));
                                ?>                
                                <tr>
                                    <td><?= !empty($row['employee_id']) ? $row['employee_id'] : '-'; ?></td>
                                    <td><?= !empty($row['attendance_date']) ? $row['attendance_date'] : '-'; ?></td>
                                    <td><?= !empty($row['time_in']) ? $row['time_in'] : '-'; ?></td>
                                    <td><?= !empty($row['time_out']) ? $row['time_out'] : '-'; ?></td>
                                    <td><?= isset($row['working_hours']) ? $row['working_hours'] : '0'; ?></td>
                                    <td><?= isset($row['late_minutes']) ? $row['late_minutes'] : '0'; ?></td>
                                    <td><?= isset($row['undertime_minutes']) ? $row['undertime_minutes'] : '0'; ?></td>
                                    <td><?= isset($row['overtime_minutes']) ? $row['overtime_minutes'] : '0'; ?></td>
                                    <td>
                                        <span class="status-badge <?= $statusClass; ?>"><?= $statusText; ?></span>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="9">No attendance records for this date.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>

                <!-- ----- Pagination Controls ----- -->
                <div id="pagination" class="pagination"></div>

            </div>
            <!-- END CODING HERE -->
        </div>
        <!----- End of Main Content ----->
    </div>

    <!----- Footer Content ----->
    <?php FooterComponent::render();?>

    <!----- End of Footer Content ----->
    
    <script>

        window.addEventListener("load", function(){
            setTimeout(function(){
                document.getElementById("loading-screen").style.display = "none";
            }, 2000);
        });

        const toggler = document.querySelector(".toggler-btn");
        toggler.addEventListener("click", function() {
            document.querySelector("#sidebar").classList.toggle("collapsed");
        });

        document.addEventListener("DOMContentLoaded", function () {
            const table = document.getElementById("AttendanceTable");
            const rows = table.querySelectorAll("tbody tr");
            const pagination = document.getElementById("pagination");

            let rowsPerPage = 10;
            let currentPage = 1;
            let totalPages = Math.ceil(rows.length / rowsPerPage);

            function displayRows() {
                rows.forEach((row, index) => {
                    row.style.display =
                    index >= (currentPage - 1) * rowsPerPage && index < currentPage * rowsPerPage
                    ? ""
                    : "none";
                });
            }

            function updatePagination() {
                pagination.innerHTML = ""; 

                const createButton = (text, page, isDisabled = false, isActive = false) => {
                    const button = document.createElement("button");
                    button.textContent = text;
                    if (isDisabled) button.disabled = true;
                    if (isActive) button.classList.add("active");

                    button.addEventListener("click", function () {
                        currentPage = page;
                        displayRows();
                        updatePagination();
                    });
                    return button;
                };

                pagination.appendChild(createButton("First", 1, currentPage === 1));
                pagination.appendChild(createButton("Previous", currentPage - 1, currentPage === 1));

                for (let i = 1; i <= totalPages; i++) {
                    pagination.appendChild(createButton(i, i, false, i === currentPage));
                }

                pagination.appendChild(createButton("Next", currentPage + 1, currentPage === totalPages));
                pagination.appendChild(createButton("Last", totalPages, currentPage === totalPages));
            }

            displayRows();
            updatePagination();
        });

    </script>
    <script src="../assets/Bootstrap/all.min.js"></script>
    <script src="../assets/Bootstrap/bootstrap.bundle.min.js"></script>
    <script src="../assets/Bootstrap/fontawesome.min.js"></script>
    <script src="../assets/Bootstrap/jq.js"></script>
</body>

</html>