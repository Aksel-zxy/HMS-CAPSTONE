<?php
session_start();
include '../../../SQL/config.php';

if (!isset($_SESSION['doctor']) || $_SESSION['doctor'] !== true) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$user_query = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
$user_query->bind_param("i", $user_id);
$user_query->execute();
$user = $user_query->get_result()->fetch_assoc();

$doctor_id = $_GET['doctor_id'] ?? '';
$nurse_id = $_GET['nurse_id'] ?? '';
$department_filter = $_GET['department'] ?? '';
$current_monday = $_GET['week_start'] ?? date('Y-m-d', strtotime('monday this week'));

$doctor_options = $conn->query("SELECT employee_id, first_name, last_name FROM hr_employees WHERE profession='Doctor'")->fetch_all(MYSQLI_ASSOC);
$nurse_options = $conn->query("SELECT employee_id, first_name, last_name FROM hr_employees WHERE profession='Nurse'")->fetch_all(MYSQLI_ASSOC);
$dept_options = $conn->query("SELECT DISTINCT department FROM hr_employees WHERE department IS NOT NULL AND department != ''")->fetch_all(MYSQLI_ASSOC);

$query = "SELECT s.*, e.first_name, e.last_name, e.profession, e.department, e.role 
          FROM shift_scheduling s 
          JOIN hr_employees e ON s.employee_id = e.employee_id 
          WHERE s.week_start = ?";

if ($doctor_id) $query .= " AND e.employee_id = '" . $conn->real_escape_string($doctor_id) . "'";
if ($nurse_id) $query .= " AND e.employee_id = '" . $conn->real_escape_string($nurse_id) . "'";
if ($department_filter) $query .= " AND e.department = '" . $conn->real_escape_string($department_filter) . "'";
$query .= " ORDER BY e.profession ASC";

$stmt = $conn->prepare($query);
$stmt->bind_param("s", $current_monday);
$stmt->execute();
$result = $stmt->get_result();

$events = [];
$table_data = [];
$days = ['mon' => 0, 'tue' => 1, 'wed' => 2, 'thu' => 3, 'fri' => 4, 'sat' => 5, 'sun' => 6];

while ($row = $result->fetch_assoc()) {
    $table_data[] = $row;
    foreach ($days as $prefix => $offset) {
        $start = $row[$prefix . "_start"];
        $end = $row[$prefix . "_end"];
        $status = $row[$prefix . "_status"];

        if (!empty($start) && !empty($end) && $status !== 'Off') {
            $current_date = date('Y-m-d', strtotime("$current_monday +$offset days"));
            $events[] = [
                'title' => $row['first_name'] . ' ' . $row['last_name'],
                'start' => $current_date . 'T' . $start,
                'end' => $current_date . 'T' . $end,
                'backgroundColor' => ($row['profession'] == 'Doctor' ? '#0d6efd' : '#198754'),
                'borderColor' => 'transparent',
                'extendedProps' => [
                    'dept' => $row['department'],
                    'role' => $row['role'],
                    'status' => $status
                ]
            ];
        }
    }
}

$prev_week = date('Y-m-d', strtotime("$current_monday -7 days"));
$next_week = date('Y-m-d', strtotime("$current_monday +7 days"));
$today_week = date('Y-m-d', strtotime('monday this week'));
$week_end = date('Y-m-d', strtotime("$current_monday +6 days"));
$display_range = date('M d', strtotime($current_monday)) . " — " . date('M d, Y', strtotime($week_end));
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HMS | Doctor and Nurse Management</title>
    <link rel="shortcut icon" href="../assets/image/favicon.ico" type="image/x-icon">
    <link rel="stylesheet" href="../assets/CSS/bootstrap.min.css">
    <link rel="stylesheet" href="../assets/CSS/super.css">
    <link rel="stylesheet" href="../assets/CSS/schedule_calendar.css">
    <link rel="stylesheet" href="cal.css">
    <link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.css" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.js"></script>
</head>

<body>
    <div class="d-flex">
        <!----- Sidebar ----->
        <aside id="sidebar" class="sidebar-toggle">

            <div class="sidebar-logo mt-3">
                <img src="../assets/image/logo-dark.png" width="90px" height="20px">
            </div>

            <div class="menu-title">Navigation</div>

            <!----- Sidebar Navigation ----->

            <li class="sidebar-item">
                <a href="../doctor_dashboard.php" class="sidebar-link" data-bs-toggle="#" data-bs-target="#"
                    aria-expanded="false" aria-controls="auth">
                    <svg x..mlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-cast" viewBox="0 0 16 16">
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
                        <a href="doctor_shift_scheduling.php" class="sidebar-link">Doctor Shift Scheduling</a>
                    </li>
                    <li class="sidebar-item">
                        <a href="nurse_shift_scheduling.php" class="sidebar-link">Nurse Shift Scheduling</a>
                    </li>
                    <li class="sidebar-item">
                        <a href="duty_assignment.php" class="sidebar-link">Duty Assignment</a>
                    </li>
                    <li class="sidebar-item">
                        <a href="schedule_calendar.php" class="sidebar-link">Schedule Calendar</a>
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
                        <a href="../dnrcl/registration_clinical_profile.php" class="sidebar-link">Registration & Clinical Profile Management</a>
                    </li>
                    <li class="sidebar-item">
                        <a href="../dnrcl/license_management.php" class="sidebar-link">License Management</a>
                    </li>
                    <li class="sidebar-item">
                        <a href="duty_assignment.php" class="sidebar-link">Compliance Monitoring Dashboard</a>
                    </li>
                    <li class="sidebar-item">
                        <a href="../Employee/admin.php" class="sidebar-link">Notifications & Alerts</a>
                    </li>
                    <li class="sidebar-item">
                        <a href="../Employee/admin.php" class="sidebar-link">Compliance Audit Log</a>
                    </li>
                </ul>
            </li>

            <li class="sidebar-item">
                <a href="doctor_dashboard.php" class="sidebar-link" data-bs-toggle="#" data-bs-target="#"
                    aria-expanded="false" aria-controls="auth">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-cast" viewBox="0 0 640 640"><!--!Font Awesome Free v7.0.0 by @fontawesome - https://fontawesome.com License - https://fontawesome.com/license/free Copyright 2025 Fonticons, Inc.-->
                        <path d="M96 96C113.7 96 128 110.3 128 128L128 464C128 472.8 135.2 480 144 480L544 480C561.7 480 576 494.3 576 512C576 529.7 561.7 544 544 544L144 544C99.8 544 64 508.2 64 464L64 128C64 110.3 78.3 96 96 96zM208 288C225.7 288 240 302.3 240 320L240 384C240 401.7 225.7 416 208 416C190.3 416 176 401.7 176 384L176 320C176 302.3 190.3 288 208 288zM352 224L352 384C352 401.7 337.7 416 320 416C302.3 416 288 401.7 288 384L288 224C288 206.3 302.3 192 320 192C337.7 192 352 206.3 352 224zM432 256C449.7 256 464 270.3 464 288L464 384C464 401.7 449.7 416 432 416C414.3 416 400 401.7 400 384L400 288C400 270.3 414.3 256 432 256zM576 160L576 384C576 401.7 561.7 416 544 416C526.3 416 512 401.7 512 384L512 160C512 142.3 526.3 128 544 128C561.7 128 576 142.3 576 160z" />
                    </svg>
                    <span style="font-size: 18px;">Performance and Evaluation</span>
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
                        <span class="username ml-1 me-2"><?php echo $user['fname']; ?> <?php echo $user['lname']; ?></span>
                        <button class="btn dropdown-toggle" type="button" id="dropdownMenuButton" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-person-circle"></i>
                        </button>
                        <ul class="dropdown-menu" aria-labelledby="dropdownMenuButton">
                            <li>
                                <span>Welcome <strong style="color: #007bff;"><?php echo $user['lname']; ?></strong>!</span>
                            </li>
                            <li>
                                <a class="dropdown-item" href="../../logout.php">
                                    Logout
                                </a>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
            <!-- START CODING HERE -->
            <div class="container-fluid">
                <div class="main p-4">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <div>
                            <h2 class="fw-bold mb-0" style="color: #2d3748;">Schedule</h2>
                            <p class="text-muted small">Viewing hospital shifts for the selected week</p>
                        </div>

                        <div class="d-flex gap-3 align-items-center">
                            <div class="btn-group shadow-sm bg-white rounded-3">
                                <a href="?week_start=<?php echo $prev_week; ?>" class="btn btn-outline-secondary border-0 py-2 px-3">
                                    <i class="bi bi-chevron-left"></i>
                                </a>
                                <a href="?week_start=<?php echo $today_week; ?>" class="btn btn-outline-secondary border-0 py-2 px-3 fw-bold small">
                                    Today
                                </a>
                                <a href="?week_start=<?php echo $next_week; ?>" class="btn btn-outline-secondary border-0 py-2 px-3">
                                    <i class="bi bi-chevron-right"></i>
                                </a>
                            </div>

                            <div class="bg-white px-4 py-2 rounded-pill shadow-sm small border fw-bold text-secondary">
                                <i class="bi bi-calendar3 me-2 text-primary"></i>
                                <?php echo $display_range; ?>
                            </div>

                            <button class="btn btn-dark rounded-circle shadow p-2" style="width: 40px; height: 40px;">
                                <i class="bi bi-sliders"></i>
                            </button>
                            <button class="btn btn-primary rounded-circle shadow p-2" style="width: 40px; height: 40px;">
                                <i class="bi bi-plus-lg"></i>
                            </button>
                        </div>
                    </div>

                    <div class="schedule-container shadow-sm border-0">
                        <table class="schedule-table">
                            <thead>
                                <tr>
                                    <th style="text-align: left; padding-left: 30px; width: 250px;">Employee Name</th>
                                    <th>Mon</th>
                                    <th>Tue</th>
                                    <th>Wed</th>
                                    <th>Thu</th>
                                    <th>Fri</th>
                                    <th>Sat</th>
                                    <th>Sun</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                // Query to join Employee details with their specific schedule
                                $query = "SELECT e.first_name, e.last_name, e.profession, e.role, s.* FROM hr_employees e 
                          INNER JOIN shift_scheduling s ON e.employee_id = s.employee_id 
                          ORDER BY e.profession ASC";

                                $result = $conn->query($query);

                                if ($result->num_rows > 0):
                                    while ($row = $result->fetch_assoc()):
                                        // Determine styling based on profession
                                        $is_doctor = ($row['profession'] == 'Doctor');
                                        $card_style = $is_doctor ? 'shift-doctor' : 'shift-nurse';
                                        $initials = substr($row['first_name'], 0, 1) . substr($row['last_name'], 0, 1);
                                ?>
                                        <tr class="bg-white">
                                            <td class="employee-cell px-4">
                                                <div class="avatar d-flex align-items-center justify-content-center fw-bold <?php echo $is_doctor ? 'bg-primary text-white' : 'bg-success text-white'; ?>" style="font-size: 0.8rem;">
                                                    <?php echo $initials; ?>
                                                </div>
                                                <div>
                                                    <div class="fw-bold text-dark mb-0" style="font-size: 0.85rem;">
                                                        <?php echo $row['first_name'] . ' ' . $row['last_name']; ?>
                                                    </div>
                                                    <div class="text-muted" style="font-size: 0.7rem;"><?php echo $row['role']; ?></div>
                                                </div>
                                            </td>

                                            <?php
                                            $days_of_week = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'];
                                            foreach ($days_of_week as $day):
                                                $start = $row[$day . '_start'];
                                                $end = $row[$day . '_end'];
                                                $status = $row[$day . '_status'];

                                                // Check if staff is on duty or off
                                                $is_on_duty = ($status !== 'Off' && !empty($start));
                                            ?>
                                                <td>
                                                    <?php if ($is_on_duty): ?>
                                                        <div class="shift-card <?php echo $card_style; ?> shadow-sm">
                                                            <div class="fw-bold" style="letter-spacing: -0.3px;">
                                                                <?php echo date("g:i a", strtotime($start)); ?> - <?php echo date("g:i a", strtotime($end)); ?>
                                                            </div>
                                                            <div class="small mt-1" style="font-size: 0.65rem; font-weight: 500;">
                                                                <?php echo $row['profession']; ?>
                                                            </div>
                                                            <div class="break-info mt-1 text-muted">
                                                                <i class="bi bi-clock-history me-1"></i> 30m break
                                                            </div>
                                                        </div>
                                                    <?php else: ?>
                                                        <div class="text-center py-3">
                                                            <span class="badge rounded-pill bg-light text-muted fw-normal" style="font-size: 0.65rem; border: 1px dashed #ddd;">OFF</span>
                                                        </div>
                                                    <?php endif; ?>
                                                </td>
                                            <?php endforeach; ?>
                                        </tr>
                                    <?php
                                    endwhile;
                                else:
                                    ?>
                                    <tr>
                                        <td colspan="8" class="text-center py-5 text-muted">No schedules found in database.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <!-- END CODING HERE -->
        </div>
        <!----- End of Main Content ----->
    </div>
    <script>
        const toggler = document.querySelector(".toggler-btn");
        toggler.addEventListener("click", function() {
            document.querySelector("#sidebar").classList.toggle("collapsed");
        });
    </script>
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.js"></script>
    <script src="../assets/Bootstrap/all.min.js"></script>
    <script src="../assets/Bootstrap/bootstrap.bundle.min.js"></script>
    <script src="../assets/Bootstrap/fontawesome.min.js"></script>
    <script src="../assets/Bootstrap/jq.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const calendarEl = document.getElementById('calendar');
            const calendar = new FullCalendar.Calendar(calendarEl, {
                initialView: 'timeGridWeek',
                height: 650, // Slightly smaller height
                contentHeight: 'auto',
                aspectRatio: 1.8,
                headerToolbar: {
                    left: 'prev,next today',
                    center: 'title',
                    right: 'timeGridWeek,dayGridMonth'
                },
                allDaySlot: false,
                events: <?php echo json_encode($events); ?>,
                eventClick: function(info) {
                    const p = info.event.extendedProps;
                    document.getElementById('modalHeader').style.background = info.event.backgroundColor;
                    document.getElementById('modalDetails').innerHTML = `
                <p class="mb-1"><strong>Staff:</strong> ${info.event.title}</p>
                <p class="mb-1"><strong>Dept:</strong> ${p.dept}</p>
                <p class="mb-1"><strong>Time:</strong> ${info.event.start.toLocaleTimeString([], {hour:'2-digit', minute:'2-digit'})}</p>
                <p class="mb-0"><strong>Status:</strong> <span class="badge bg-light text-dark">${p.status}</span></p>
            `;
                    new bootstrap.Modal(document.getElementById('shiftModal')).show();
                }
            });
            calendar.render();

            document.querySelector(".toggler-btn").addEventListener("click", () => {
                document.querySelector("#sidebar").classList.toggle("collapsed");
                setTimeout(() => calendar.updateSize(), 300); // Important: refresh calendar size after sidebar animation
            });
        });
    </script>
</body>

</html>