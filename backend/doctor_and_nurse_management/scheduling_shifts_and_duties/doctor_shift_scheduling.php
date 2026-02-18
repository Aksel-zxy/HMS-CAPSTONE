<?php
include '../../../SQL/config.php';
require_once '../class/DoctorShiftScheduling.php';

$doctorSched = new DoctorShiftScheduling($conn);
$user = $doctorSched->user;
$days = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun']; // Static array for the loop
$doctors = $doctorSched->doctors;
$rooms_query = $conn->query("SELECT room_id, room_name FROM rooms_table ORDER BY room_name ASC");
$rooms_list = $rooms_query->fetch_all(MYSQLI_ASSOC);

// Helper for validation
$role_map = [];
foreach ($doctors as $doc) {
    $role_map[$doc['employee_id']] = $doc['role'];
}

// 1. Fetch Rooms List for dropdowns (Used in both Create and Edit)
$rooms_query = $conn->query("SELECT room_id, room_name FROM rooms_table ORDER BY room_name ASC");
$rooms_list = $rooms_query->fetch_all(MYSQLI_ASSOC);

include '../../../SQL/config.php';
require_once '../class/DoctorShiftScheduling.php';

$doctorSched = new DoctorShiftScheduling($conn);
$doctors = $doctorSched->doctors;
$days = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];

// 1. Create a Role Map (Helper for validation)
$role_map = [];
foreach ($doctors as $doc) {
    $role_map[$doc['employee_id']] = $doc['role'];
}

// Handle schedule form submission (CREATE)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_schedule'])) {
    $employee_id = $_POST['employee_id'];
    $week_start  = $_POST['week_start'];
    $doctor_role = $role_map[$employee_id] ?? '';

    // 1. DUPLICATE CHECK (Remains the same)
    $check_stmt = $conn->prepare("SELECT schedule_id FROM shift_scheduling WHERE employee_id = ? AND week_start = ? LIMIT 1");
    $check_stmt->bind_param("is", $employee_id, $week_start);
    $check_stmt->execute();
    if ($check_stmt->get_result()->num_rows > 0) {
        header("Location: doctor_shift_scheduling.php?error=" . urlencode("Schedule already exists for this week."));
        exit();
    }
    $check_stmt->close();

    $days_list = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'];

    // 2. INITIALIZE BIND VALUES 
    // Notice: We removed $unique_id here.
    $bind_values = [$employee_id, $week_start];

    foreach ($days_list as $d) {
        $status = $_POST[$d . '_status'] ?? 'Off Duty';
        $start = ($status === 'On Duty') ? ($_POST[$d . '_start'] ?: null) : null;
        $end   = ($status === 'On Duty') ? ($_POST[$d . '_end'] ?: null) : null;
        $room  = ($status === 'On Duty' && !empty($_POST[$d . '_room_id'])) ? (int)$_POST[$d . '_room_id'] : null;

        // ... (Your Resident Validation logic here) ...

        array_push($bind_values, $start, $end, $status, $room);
    }

    $bind_values[] = date('Y-m-d H:i:s'); // created_at

    // 3. UPDATED SQL (31 columns, 31 placeholders)
    $sql = "INSERT INTO shift_scheduling (
    employee_id, week_start, 
    mon_start, mon_end, mon_status, mon_room_id, 
    tue_start, tue_end, tue_status, tue_room_id, 
    wed_start, wed_end, wed_status, wed_room_id, 
    thu_start, thu_end, thu_status, thu_room_id, 
    fri_start, fri_end, fri_status, fri_room_id, 
    sat_start, sat_end, sat_status, sat_room_id, 
    sun_start, sun_end, sun_status, sun_room_id, 
    created_at
) VALUES (" . implode(',', array_fill(0, 31, '?')) . ")";

    $stmt = $conn->prepare($sql);

    // 4. UPDATED TYPES
    // 'i' for employee_id, 's' for week_start, 7 * 'sssi' for days, 's' for timestamp = 31 chars
    $types = "is" . str_repeat("sssi", 7) . "s";
    $stmt->bind_param($types, ...$bind_values);

    if ($stmt->execute()) {
        header("Location: doctor_shift_scheduling.php?success=1");
        exit();
    }
}
// Handle schedule update (EDIT)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_schedule'])) {
    $schedule_id = $_POST['schedule_id'];
    $employee_id = $_POST['employee_id'];
    $week_start  = $_POST['week_start'];

    $params = [];
    $types = '';
    $fields = '';

    foreach (['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'] as $day) {
        $fields .= "{$day}_start = ?, {$day}_end = ?, {$day}_status = ?, {$day}_room_id = ?, ";
        $params[] = $_POST[$day . '_start'] ?: null;
        $params[] = $_POST[$day . '_end'] ?: null;
        $params[] = $_POST[$day . '_status'] ?: 'Off Duty';
        $params[] = !empty($_POST[$day . '_room_id']) ? (int)$_POST[$day . '_room_id'] : null;
        $types .= 'sssi';
    }

    $fields .= "week_start = ?";
    $params[] = $week_start;
    $types .= 's';

    $params[] = $schedule_id;
    $types .= 's';

    $sql = "UPDATE shift_scheduling SET $fields WHERE schedule_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);

    if ($stmt->execute()) {
        header("Location: doctor_shift_scheduling.php?view_sched_id=" . urlencode($employee_id) . "&success=update");
        exit();
    }
}

// Handle schedule delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_schedule'])) {
    $schedule_id = $_POST['schedule_id'];
    $stmt = $conn->prepare("DELETE FROM shift_scheduling WHERE schedule_id = ?");
    $stmt->bind_param("s", $schedule_id);
    $stmt->execute();
}

// Fetch all schedules for modal view (with LEFT JOIN for room names)
$modal_schedules = [];
$edit_sched_id = $_GET['edit_sched_id'] ?? null;
if (isset($_GET['view_sched_id'])) {
    $view_id = $_GET['view_sched_id'];
    // We JOIN rooms_table for every day to get names instead of IDs
    $sql = "SELECT s.*, 
            r1.room_name as mon_room_name, r2.room_name as tue_room_name, 
            r3.room_name as wed_room_name, r4.room_name as thu_room_name, 
            r5.room_name as fri_room_name, r6.room_name as sat_room_name, 
            r7.room_name as sun_room_name
            FROM shift_scheduling s
            LEFT JOIN rooms_table r1 ON s.mon_room_id = r1.room_id
            LEFT JOIN rooms_table r2 ON s.tue_room_id = r2.room_id
            LEFT JOIN rooms_table r3 ON s.wed_room_id = r3.room_id
            LEFT JOIN rooms_table r4 ON s.thu_room_id = r4.room_id
            LEFT JOIN rooms_table r5 ON s.fri_room_id = r5.room_id
            LEFT JOIN rooms_table r6 ON s.sat_room_id = r6.room_id
            LEFT JOIN rooms_table r7 ON s.sun_room_id = r7.room_id
            WHERE s.employee_id = ? ORDER BY s.week_start ASC";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $view_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $modal_schedules[] = $row;
    }
}
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
    <link rel="stylesheet" href="../assets/CSS/shift_scheduling.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
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
                        <a href="../dnrcl/compliance.php" class="sidebar-link">Compliance Monitoring Dashboard</a>
                    </li>
                    <li class="sidebar-item">
                        <a href="../dnrcl/notif_alert.php" class="sidebar-link">Notifications & Alerts</a>
                    </li>
                    <li class="sidebar-item">
                        <a href="../dnrcl/audit_log.php" class="sidebar-link">Compliance Audit Log</a>
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
                        <a href="../evaluation/doc_feedback.php" class="sidebar-link">View Nurse Evaluation</a>
                    </li>
                    <li class="sidebar-item">
                        <a href="../evaluation/analytics.php" class="sidebar-link">Evaluation Report & Analytics</a>
                    </li>
                    <li class="sidebar-item">
                        <a href="../evaluation/criteria.php" class="sidebar-link">Manage Evaluation Criteria</a>
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
            <div class="container-fluid">
                <h2 style="font-family:Arial, sans-serif; color:#0d6efd; margin-bottom:20px; border-bottom:2px solid #0d6efd; padding-bottom:8px;">🧑‍⚕️Doctor Shift Scheduling</h2>
                <div class="card shadow-sm rounded mb-4">
                    <div class="card-body bg-white rounded">
                        <?php if (isset($_GET['success'])): ?>
                            <div class="alert alert-success">Schedule saved successfully!</div>
                        <?php elseif (!empty($error)): ?>
                            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                        <?php endif; ?>
                        <form method="POST" class="mb-4">
                            <div class="row g-3 mb-4">
                                <div class="col-md-4">
                                    <label for="employee_id">Select Doctor</label>
                                    <select name="employee_id" id="doctor_select" class="form-select" required>
                                        <option value="">-- Choose Doctor --</option>
                                        <?php foreach ($doctors as $doc): ?>
                                            <option value="<?= $doc['employee_id'] ?>"><?= $doc['first_name'] ?> <?= $doc['last_name'] ?> (<?= $doc['role'] ?>)</option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label for="week_start" class="form-label">Week Starting</label>
                                    <input type="date" name="week_start" id="week_start" class="form-control" required>
                                    <div id="availability_status" class="small mt-1"></div>
                                </div>
                            </div>

                            <table class="table table-bordered table-striped shift-table">
                                <thead>
                                    <tr class="shift-table-header">
                                        <th>Day</th>
                                        <th>Start Time</th>
                                        <th>End Time</th>
                                        <th>Room</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($days as $day): ?>
                                        <?php $prefix = strtolower(substr($day, 0, 3)); ?>
                                        <tr>
                                            <td><?= $day ?></td>
                                            <td>
                                                <input type="time" name="<?= $prefix ?>_start" class="form-control start-time-input">
                                            </td>
                                            <td>
                                                <input type="time" name="<?= $prefix ?>_end" class="form-control end-time-input">
                                            </td>
                                            <td>
                                                <select name="<?= $prefix ?>_room_id" class="form-select">
                                                    <option value="">-- Select Room --</option>
                                                    <?php foreach ($rooms_list as $room): ?>
                                                        <option value="<?= $room['room_id'] ?>"><?= htmlspecialchars($room['room_name']) ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </td>
                                            <td>
                                                <select name="<?= $prefix ?>_status" class="form-select" required>
                                                    <option value="">-- Select Status --</option>
                                                    <option value="On Duty">On Duty</option>
                                                    <option value="Off Duty">Off Duty</option>
                                                    <option value="Leave">Leave</option>
                                                    <option value="Sick">Sick</option>
                                                </select>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                            <button type="submit" name="save_schedule" class="btn btn-success mt-3">Save Schedule</button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- List of Doctors -->
            <div class="container-fluid ">
                <h2 style="font-family:Arial, sans-serif; color:#0d6efd; margin-bottom:20px; border-bottom:2px solid #0d6efd; padding-bottom:8px;">📃List of Doctors</h2>
                <?php if (!empty($success)): ?>
                    <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
                <?php elseif (!empty($error)): ?>
                    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>
                <div class="card shadow-sm rounded mb-4">
                    <div class="card-header">Doctors List</div>
                    <div class="card-body bg-white rounded">
                        <table class="table table-bordered table-striped doctors-list-table rounded shadow-sm">
                            <thead>
                                <tr>
                                    <th>Employee ID</th>
                                    <th>First Name</th>
                                    <th>Middle Name</th>
                                    <th>Last Name</th>
                                    <th>Role</th>
                                    <th>Profession</th>
                                    <th>Department</th>
                                    <th>Action</th>
                                    <th>Download</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($doctors as $doc): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($doc['employee_id']?? '') ?></td>
                                        <td><?= htmlspecialchars($doc['first_name']?? '') ?></td>
                                        <td><?= htmlspecialchars($doc['middle_name']?? '') ?></td>
                                        <td><?= htmlspecialchars($doc['last_name']?? '') ?></td>
                                        <td><?= htmlspecialchars($doc['role']?? '') ?></td>
                                        <td><?= htmlspecialchars($doc['profession']?? '') ?></td>
                                        <td><?= htmlspecialchars($doc['department']?? '') ?></td>
                                        <td>
                                            <form method="get" style="display:inline;">
                                                <input type="hidden" name="view_sched_id" value="<?= htmlspecialchars($doc['employee_id']) ?>">
                                                <button type="submit" class="btn btn-sm btn-info">View Schedule</button>
                                            </form>
                                        </td>
                                        <td>
                                            <form action="doctor_download_schedule.php" method="get" target="_blank">
                                                <input type="hidden" name="employee_id" value="<?= htmlspecialchars($doc['employee_id']) ?>">
                                                <button type="submit" class="btn btn-success">Download as PDF</button>
                                            </form>
                                        </td>

                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php if (isset($_GET['view_sched_id'])): ?>
                    <div class="modal fade show" id="scheduleModal" tabindex="-1" style="display:block; background: rgba(0,0,0,0.5);">
                        <div class="modal-dialog modal-xl modal-dialog-centered">
                            <div class="modal-content shadow-lg border-0" style="max-height: 90vh;">
                                <div class="modal-header bg-primary text-white">
                                    <h5 class="modal-title">
                                        <i class="fas fa-calendar-alt me-2"></i>
                                        Schedules for Doctor ID: <?= htmlspecialchars($view_id) ?>
                                    </h5>
                                    <a href="doctor_shift_scheduling.php" class="btn-close btn-close-white"></a>
                                </div>

                                <div class="modal-body p-4" style="overflow-y: auto; background-color: #f8f9fa;">
                                    <?php if (!empty($modal_schedules)): ?>
                                        <?php foreach ($modal_schedules as $modal_schedule):
                                            $is_editing = ($edit_sched_id == $modal_schedule['schedule_id']);
                                        ?>
                                            <form method="POST" class="mb-4 border rounded bg-white shadow-sm overflow-hidden">
                                                <input type="hidden" name="schedule_id" value="<?= htmlspecialchars($modal_schedule['schedule_id']) ?>">
                                                <input type="hidden" name="employee_id" value="<?= htmlspecialchars($modal_schedule['employee_id']) ?>">

                                                <div class="d-flex justify-content-between align-items-center bg-light p-3 border-bottom">
                                                    <div>
                                                        <span class="text-muted small text-uppercase fw-bold">Week Starting</span>
                                                        <?php if ($is_editing): ?>
                                                            <input type="date" name="week_start" class="form-control form-control-sm mt-1" value="<?= $modal_schedule['week_start'] ?>">
                                                        <?php else: ?>
                                                            <h6 class="mb-0 mt-1 fw-bold text-dark"><?= date('M d, Y', strtotime($modal_schedule['week_start'])) ?></h6>
                                                        <?php endif; ?>
                                                    </div>

                                                    <div class="btn-group">
                                                        <?php if (!$is_editing): ?>
                                                            <a href="?view_sched_id=<?= $view_id ?>&edit_sched_id=<?= $modal_schedule['schedule_id'] ?>" class="btn btn-outline-warning btn-sm">
                                                                <i class="fas fa-edit"></i> Edit
                                                            </a>
                                                            <button type="submit" name="delete_schedule" class="btn btn-outline-danger btn-sm" onclick="return confirm('Are you sure?')">
                                                                <i class="fas fa-trash"></i>
                                                            </button>
                                                        <?php else: ?>
                                                            <button type="submit" name="update_schedule" class="btn btn-success btn-sm px-3">
                                                                <i class="fas fa-save"></i> Save Changes
                                                            </button>
                                                            <a href="?view_sched_id=<?= $view_id ?>" class="btn btn-secondary btn-sm">Cancel</a>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>

                                                <div class="table-responsive">
                                                    <table class="table table-hover align-middle mb-0">
                                                        <thead class="table-light small text-uppercase sticky-top" style="top: 0; z-index: 10;">
                                                            <tr>
                                                                <th style="width: 15%;">Day</th>
                                                                <th>Start Time</th>
                                                                <th>End Time</th>
                                                                <th>Status</th>
                                                                <th>Assigned Room</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            <?php foreach (['Monday' => 'mon', 'Tuesday' => 'tue', 'Wednesday' => 'wed', 'Thursday' => 'thu', 'Friday' => 'fri', 'Saturday' => 'sat', 'Sunday' => 'sun'] as $dayName => $prefix):
                                                                $status = $modal_schedule[$prefix . '_status'] ?? 'Off Duty';
                                                                $is_off = in_array($status, ['Off Duty', 'Leave', 'Sick']);
                                                            ?>
                                                                <tr>
                                                                    <td class="fw-bold"><?= $dayName ?></td>
                                                                    <td>
                                                                        <?php if ($is_editing): ?>
                                                                            <input type="time" name="<?= $prefix ?>_start" class="form-control form-control-sm" value="<?= $modal_schedule[$prefix . '_start'] ?>">
                                                                        <?php else: ?>
                                                                            <span class="<?= $is_off ? 'text-muted italic' : '' ?>"><?= $is_off ? '---' : ($modal_schedule[$prefix . '_start'] ?: '---') ?></span>
                                                                        <?php endif; ?>
                                                                    </td>
                                                                    <td>
                                                                        <?php if ($is_editing): ?>
                                                                            <input type="time" name="<?= $prefix ?>_end" class="form-control form-control-sm" value="<?= $modal_schedule[$prefix . '_end'] ?>">
                                                                        <?php else: ?>
                                                                            <span class="<?= $is_off ? 'text-muted italic' : '' ?>"><?= $is_off ? '---' : ($modal_schedule[$prefix . '_end'] ?: '---') ?></span>
                                                                        <?php endif; ?>
                                                                    </td>
                                                                    <td>
                                                                        <?php if ($is_editing): ?>
                                                                            <select name="<?= $prefix ?>_status" class="form-select form-select-sm">
                                                                                <?php foreach (['On Duty', 'Off Duty', 'Leave', 'Sick'] as $st): ?>
                                                                                    <option value="<?= $st ?>" <?= $status == $st ? 'selected' : '' ?>><?= $st ?></option>
                                                                                <?php endforeach; ?>
                                                                            </select>
                                                                        <?php else: ?>
                                                                            <?php $badgeClass = ($status == 'On Duty') ? 'bg-success' : (($status == 'Off Duty') ? 'bg-secondary' : 'bg-warning text-dark'); ?>
                                                                            <span class="badge rounded-pill <?= $badgeClass ?>"><?= $status ?></span>
                                                                        <?php endif; ?>
                                                                    </td>
                                                                    <td>
                                                                        <?php if ($is_editing): ?>
                                                                            <select name="<?= $prefix ?>_room_id" class="form-select form-select-sm">
                                                                                <option value="">-- Select Room --</option>
                                                                                <?php foreach ($rooms_list as $room): ?>
                                                                                    <option value="<?= $room['room_id'] ?>" <?= ($modal_schedule[$prefix . '_room_id'] == $room['room_id']) ? 'selected' : '' ?>>
                                                                                        <?= htmlspecialchars($room['room_name']) ?>
                                                                                    </option>
                                                                                <?php endforeach; ?>
                                                                            </select>
                                                                        <?php else: ?>
                                                                            <span class="fw-semibold text-primary">
                                                                                <?= ($status === 'On Duty') ? htmlspecialchars($modal_schedule[$prefix . '_room_name'] ?? '---') : '---'; ?>
                                                                            </span>
                                                                        <?php endif; ?>
                                                                    </td>
                                                                </tr>
                                                            <?php endforeach; ?>
                                                        </tbody>
                                                    </table>
                                                </div>
                                            </form>
                                        <?php endforeach; ?>

                                    <?php else: ?>
                                        <div class="text-center py-5">
                                            <i class="fas fa-calendar-times fa-4x text-muted mb-3"></i>
                                            <h4 class="text-muted">No Schedule Found</h4>
                                            <p>This doctor currently has no shifts assigned to them.</p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="modal-footer bg-light">
                                    <a href="doctor_shift_scheduling.php" class="btn btn-primary px-4">Close View</a>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
            <!-- END CODING HERE -->
            <!----- End of Main Content ----->
        </div>
        <script>
            const toggler = document.querySelector(".toggler-btn");
            toggler.addEventListener("click", function() {
                document.querySelector("#sidebar").classList.toggle("collapsed");
            });
            document.addEventListener('change', function(e) {
                // Check if the changed element is a start-time input
                if (e.target.classList.contains('start-time-input')) {
                    const startTime = e.target.value; // Format is "HH:MM"

                    if (startTime) {
                        // Get the corresponding end-time input in the same table row
                        const row = e.target.closest('tr');
                        const endTimeInput = row.querySelector('.end-time-input');

                        // Split hours and minutes
                        let [hours, minutes] = startTime.split(':').map(Number);

                        // Add 8 hours
                        let endHours = hours + 8;

                        // Handle wrap-around (if it goes past midnight)
                        if (endHours >= 24) {
                            endHours = endHours - 24;
                        }

                        // Format back to HH:MM string (adding leading zero if needed)
                        const formattedHours = String(endHours).padStart(2, '0');
                        const formattedMinutes = String(minutes).padStart(2, '0');

                        endTimeInput.value = `${formattedHours}:${formattedMinutes}`;
                    }
                }
            });
            document.addEventListener('DOMContentLoaded', function() {
                const doctorSelect = document.getElementById('doctor_select');
                const dateInput = document.getElementById('week_start');
                const statusDiv = document.getElementById('availability_status');
                const saveBtn = document.querySelector('button[name="save_schedule"]');

                function checkAvailability() {
                    const empId = doctorSelect.value;
                    const weekDate = dateInput.value;

                    // Only run if both fields have values
                    if (empId && weekDate) {
                        statusDiv.innerHTML = '<span class="text-muted"><i class="fas fa-spinner fa-spin"></i> Checking...</span>';

                        fetch('check_sched.php', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/x-www-form-urlencoded'
                                },
                                body: `employee_id=${empId}&week_start=${weekDate}`
                            })
                            .then(response => {
                                if (!response.ok) throw new Error('File not found or Server Error');
                                return response.text();
                            })
                            .then(data => {
                                const result = data.trim();
                                if (result === 'exists') {
                                    statusDiv.innerHTML = '<span class="text-danger">❌ Week already scheduled for this doctor!</span>';
                                    saveBtn.disabled = true; // STOPS YOU FROM SUBMITTING

                                    Swal.fire({
                                        icon: 'error',
                                        title: 'Duplicate Detected',
                                        text: 'This doctor already has a schedule for the chosen week.',
                                        confirmButtonColor: '#d33'
                                    });
                                    dateInput.value = ''; // Clears the date so they can't submit
                                } else {
                                    statusDiv.innerHTML = '<span class="text-success">✅ Week available.</span>';
                                    saveBtn.disabled = false;
                                }
                            })
                            .catch(error => {
                                console.error('Error:', error);
                                statusDiv.innerHTML = '<span class="text-warning">⚠️ Cannot connect to availability checker.</span>';
                            });
                    }
                }

                // Trigger instantly when either field changes
                doctorSelect.addEventListener('change', checkAvailability);
                dateInput.addEventListener('change', checkAvailability);
            });
        </script>
        <script type="text/javascript">
            const urlParams = new URLSearchParams(window.location.search);
            const errorMsg = urlParams.get('error');
            const successMsg = urlParams.get('success');

            if (errorMsg) {
                Swal.fire({
                    icon: 'error',
                    title: 'Validation Error',
                    text: decodeURIComponent(errorMsg),
                    confirmButtonColor: '#0d6efd'
                }).then((result) => {
                    // Reloads the page without the error parameters in the URL
                    window.location.href = 'doctor_shift_scheduling.php';
                });
            }

            if (successMsg) {
                Swal.fire({
                    icon: 'success',
                    title: 'Success!',
                    text: 'Schedule saved successfully.',
                    timer: 2000,
                    showConfirmButton: true,
                    confirmButtonColor: '#0d6efd'
                }).then(() => {
                    // Reloads the page to show the fresh list
                    window.location.href = 'doctor_shift_scheduling.php';
                });
            }
        </script>
        <script src="../assets/Bootstrap/all.min.js"></script>
        <script src="../assets/Bootstrap/bootstrap.bundle.min.js"></script>
        <script src="../assets/Bootstrap/fontawesome.min.js"></script>
        <script src="../assets/Bootstrap/jq.js"></script>
</body>

</html>