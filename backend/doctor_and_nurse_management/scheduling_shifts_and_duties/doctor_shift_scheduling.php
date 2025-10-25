<?php
include '../../../SQL/config.php';
require_once '../class/DoctorShiftScheduling.php';

$doctorSched = new DoctorShiftScheduling($conn);
$user = $doctorSched->user;
$days = $doctorSched->days;
$doctors = $doctorSched->doctors;
$professions = $doctorSched->professions;
$departments = $doctorSched->departments;

// Handle schedule form submission (CREATE)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_schedule'])) {
    $employee_id = $_POST['employee_id'];
    $week_start = $_POST['week_start'];
    $created_at = date('Y-m-d H:i:s');
    $schedule_id = uniqid('sched_');

    // Check if employee_id is not empty and exists in hr_employees
    $check_stmt = $conn->prepare("SELECT employee_id FROM hr_employees WHERE employee_id = ?");
    $check_stmt->bind_param("i", $employee_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();

    if (empty($employee_id) || $check_result->num_rows === 0) {
        $error = "Selected employee ID does not exist or is empty. Please choose a valid Doctor.";
    } else {
        // Collect start, end, and status for each day
        $mon_start = $_POST['mon_start'] ?? null;
        $mon_end   = $_POST['mon_end'] ?? null;
        $mon_status = $_POST['mon_status'] ?? null;
        $tue_start = $_POST['tue_start'] ?? null;
        $tue_end   = $_POST['tue_end'] ?? null;
        $tue_status = $_POST['tue_status'] ?? null;
        $wed_start = $_POST['wed_start'] ?? null;
        $wed_end   = $_POST['wed_end'] ?? null;
        $wed_status = $_POST['wed_status'] ?? null;
        $thu_start = $_POST['thu_start'] ?? null;
        $thu_end   = $_POST['thu_end'] ?? null;
        $thu_status = $_POST['thu_status'] ?? null;
        $fri_start = $_POST['fri_start'] ?? null;
        $fri_end   = $_POST['fri_end'] ?? null;
        $fri_status = $_POST['fri_status'] ?? null;
        $sat_start = $_POST['sat_start'] ?? null;
        $sat_end   = $_POST['sat_end'] ?? null;
        $sat_status = $_POST['sat_status'] ?? null;
        $sun_start = $_POST['sun_start'] ?? null;
        $sun_end   = $_POST['sun_end'] ?? null;
        $sun_status = $_POST['sun_status'] ?? null;

        $stmt = $conn->prepare(
            "INSERT INTO shift_scheduling 
            (employee_id, schedule_id, week_start, mon_start, mon_end, mon_status, tue_start, tue_end, tue_status, wed_start, wed_end, wed_status, thu_start, thu_end, thu_status, fri_start, fri_end, fri_status, sat_start, sat_end, sat_status, sun_start, sun_end, sun_status, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->bind_param(
            "sssssssssssssssssssssssss",
            $employee_id, $schedule_id, $week_start,
            $mon_start, $mon_end, $mon_status,
            $tue_start, $tue_end, $tue_status,
            $wed_start, $wed_end, $wed_status,
            $thu_start, $thu_end, $thu_status,
            $fri_start, $fri_end, $fri_status,
            $sat_start, $sat_end, $sat_status,
            $sun_start, $sun_end, $sun_status,
            $created_at
        );

        if ($stmt->execute()) {
            header("Location: doctor_shift_scheduling.php?success=1");
            exit();
        } else {
            $error = "Error saving schedule: " . $stmt->error;
        }
    }
}

// Handle schedule update (EDIT)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_schedule'])) {
    $schedule_id = $_POST['schedule_id'];
    $employee_id = $_POST['employee_id'];
    $week_start = $_POST['week_start'];
    $created_at = date('Y-m-d H:i:s');
    $params = [];
    $types = '';
    $fields = '';
    foreach ($days as $day) {
        $prefix = strtolower(substr($day, 0, 3));
        $fields .= "{$prefix}_start = ?, {$prefix}_end = ?, {$prefix}_status = ?, ";
        $params[] = $_POST[$prefix . '_start'] ?? null;
        $params[] = $_POST[$prefix . '_end'] ?? null;
        $params[] = $_POST[$prefix . '_status'] ?? null;
        $types .= 'sss';
    }
    $fields .= "week_start = ?, created_at = ?";
    $params[] = $week_start;
    $params[] = $created_at;
    $types .= 'ss';
    $params[] = $schedule_id;
    $types .= 's';

    $sql = "UPDATE shift_scheduling SET $fields WHERE schedule_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $success = "Schedule updated successfully!";
    header("Location: doctor_shift_scheduling.php?view_sched_id=" . urlencode($employee_id));
    exit();
}

// Handle schedule delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_schedule'])) {
    $schedule_id = $_POST['schedule_id'];
    $stmt = $conn->prepare("DELETE FROM shift_scheduling WHERE schedule_id = ?");
    $stmt->bind_param("s", $schedule_id);
    $stmt->execute();
    $success = "Schedule deleted successfully!";
}

// Fetch all schedules for modal view
$modal_schedules = [];
$edit_sched_id = $_GET['edit_sched_id'] ?? null;
if (isset($_GET['view_sched_id'])) {
    $view_id = $_GET['view_sched_id'];
    $stmt = $conn->prepare("SELECT * FROM shift_scheduling WHERE employee_id = ? ORDER BY week_start DESC");
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
    <script>
    // Validate max 8 hours per day before submitting
    document.addEventListener("DOMContentLoaded", function() {
        var form = document.querySelector('form[method="POST"]');
        if (form) {
            form.addEventListener("submit", function(e) {
                var days = ["mon","tue","wed","thu","fri","sat","sun"];
                for (var i = 0; i < days.length; i++) {
                    var start = form.querySelector('[name="'+days[i]+'_start"]').value;
                    var end = form.querySelector('[name="'+days[i]+'_end"]').value;
                    if (start && end) {
                        var startDate = new Date("1970-01-01T" + start + ":00");
                        var endDate = new Date("1970-01-01T" + end + ":00");
                        var diff = (endDate - startDate) / (1000 * 60 * 60);
                        if (diff > 8) {
                            alert("Maximum shift per day is 8 hours ("+days[i].charAt(0).toUpperCase()+days[i].slice(1)+")");
                            e.preventDefault();
                            return false;
                        }
                        if (diff < 0) {
                            alert("End time must be after start time ("+days[i].charAt(0).toUpperCase()+days[i].slice(1)+")");
                            e.preventDefault();
                            return false;
                        }
                    }
                }
            });
        }
    });
    </script>
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
                   <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-cast" viewBox="0 0 640 512"><!--!Font Awesome Free v7.0.0 by @fontawesome - https://fontawesome.com License - https://fontawesome.com/license/free Copyright 2025 Fonticons, Inc.--><path d="M320 16a104 104 0 1 1 0 208 104 104 0 1 1 0-208zM96 88a72 72 0 1 1 0 144 72 72 0 1 1 0-144zM0 416c0-70.7 57.3-128 128-128 12.8 0 25.2 1.9 36.9 5.4-32.9 36.8-52.9 85.4-52.9 138.6l0 16c0 11.4 2.4 22.2 6.7 32L32 480c-17.7 0-32-14.3-32-32l0-32zm521.3 64c4.3-9.8 6.7-20.6 6.7-32l0-16c0-53.2-20-101.8-52.9-138.6 11.7-3.5 24.1-5.4 36.9-5.4 70.7 0 128 57.3 128 128l0 32c0 17.7-14.3 32-32 32l-86.7 0zM472 160a72 72 0 1 1 144 0 72 72 0 1 1 -144 0zM160 432c0-88.4 71.6-160 160-160s160 71.6 160 160l0 16c0 17.7-14.3 32-32 32l-256 0c-17.7 0-32-14.3-32-32l0-16z"/></svg>
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
                   <svg xmlns="http://www.w3.org/2000/svg"  width="16" height="16" fill="currentColor" class="bi bi-cast" viewBox="0 0 640 640"><!--!Font Awesome Free v7.0.0 by @fontawesome - https://fontawesome.com License - https://fontawesome.com/license/free Copyright 2025 Fonticons, Inc.--><path d="M80 480L80 224L560 224L560 480C560 488.8 552.8 496 544 496L352 496C352 451.8 316.2 416 272 416L208 416C163.8 416 128 451.8 128 496L96 496C87.2 496 80 488.8 80 480zM96 96C60.7 96 32 124.7 32 160L32 480C32 515.3 60.7 544 96 544L544 544C579.3 544 608 515.3 608 480L608 160C608 124.7 579.3 96 544 96L96 96zM240 376C270.9 376 296 350.9 296 320C296 289.1 270.9 264 240 264C209.1 264 184 289.1 184 320C184 350.9 209.1 376 240 376zM408 272C394.7 272 384 282.7 384 296C384 309.3 394.7 320 408 320L488 320C501.3 320 512 309.3 512 296C512 282.7 501.3 272 488 272L408 272zM408 368C394.7 368 384 378.7 384 392C384 405.3 394.7 416 408 416L488 416C501.3 416 512 405.3 512 392C512 378.7 501.3 368 488 368L408 368z"/></svg>
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
                   <svg xmlns="http://www.w3.org/2000/svg"  width="16" height="16" fill="currentColor" class="bi bi-cast" viewBox="0 0 640 640"><!--!Font Awesome Free v7.0.0 by @fontawesome - https://fontawesome.com License - https://fontawesome.com/license/free Copyright 2025 Fonticons, Inc.--><path d="M96 96C113.7 96 128 110.3 128 128L128 464C128 472.8 135.2 480 144 480L544 480C561.7 480 576 494.3 576 512C576 529.7 561.7 544 544 544L144 544C99.8 544 64 508.2 64 464L64 128C64 110.3 78.3 96 96 96zM208 288C225.7 288 240 302.3 240 320L240 384C240 401.7 225.7 416 208 416C190.3 416 176 401.7 176 384L176 320C176 302.3 190.3 288 208 288zM352 224L352 384C352 401.7 337.7 416 320 416C302.3 416 288 401.7 288 384L288 224C288 206.3 302.3 192 320 192C337.7 192 352 206.3 352 224zM432 256C449.7 256 464 270.3 464 288L464 384C464 401.7 449.7 416 432 416C414.3 416 400 401.7 400 384L400 288C400 270.3 414.3 256 432 256zM576 160L576 384C576 401.7 561.7 416 544 416C526.3 416 512 401.7 512 384L512 160C512 142.3 526.3 128 544 128C561.7 128 576 142.3 576 160z"/></svg>
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
                                    <label for="employee_id" class="form-label">Select Doctor</label>
                                    <select name="employee_id" id="employee_id" class="form-select" required>
                                        <option value="">-- Choose a Doctor --</option>
                                        <?php foreach ($doctors as $doc): ?>
                                            <option value="<?= htmlspecialchars($doc['employee_id']) ?>">
                                                <?= htmlspecialchars($doc['employee_id'] . ' - ' . $doc['first_name'] . ' ' . $doc['last_name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label for="week_start" class="form-label">Week Starting</label>
                                    <input type="date" name="week_start" id="week_start" class="form-control" required>
                                </div>
                            </div>
                            <table class="table table-bordered table-striped shift-table ">
                                <thead>
                                    <tr class="shift-table-header">
                                        <th>Day</th>
                                        <th>Start Time</th>
                                        <th>End Time</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($days as $day): ?>
                                        <tr>
                                            <td><?= $day ?></td>
                                            <td><input type="time" name="<?= strtolower(substr($day, 0, 3)) ?>_start" class="form-control"></td>
                                            <td><input type="time" name="<?= strtolower(substr($day, 0, 3)) ?>_end" class="form-control"></td>
                                            <td>
                                                <select name="<?= strtolower(substr($day, 0, 3)) ?>_status" class="form-select" required>
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
                                        <td><?= htmlspecialchars($doc['employee_id']) ?></td>
                                        <td><?= htmlspecialchars($doc['first_name']) ?></td>
                                        <td><?= htmlspecialchars($doc['middle_name']) ?></td>
                                        <td><?= htmlspecialchars($doc['last_name']) ?></td>
                                        <td><?= htmlspecialchars($doc['role']) ?></td>
                                        <td><?= htmlspecialchars($doc['profession']) ?></td>
                                        <td><?= htmlspecialchars($doc['department']) ?></td>
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
            <?php if (!empty($modal_schedules)): ?>
                <div class="modal fade show schedule-modal" id="scheduleModal" tabindex="-1" aria-modal="true" role="dialog" style="display:block;">
                    <div class="modal-dialog modal-xl">
                        <div class="modal-content rounded shadow">
                            <div class="modal-header bg-primary text-white">
                                <h5 class="modal-title">Schedules for Doctor ID: <?= htmlspecialchars($modal_schedules[0]['employee_id']) ?></h5>
                                <a href="doctor_shift_scheduling.php" class="btn-close"></a>
                            </div>
                            <div class="modal-body">
                                <?php foreach ($modal_schedules as $modal_schedule): ?>
                                    <?php $is_editing = ($edit_sched_id == $modal_schedule['schedule_id']); ?>
                                    <form method="POST" class="mb-4 border rounded p-3">
                                        <input type="hidden" name="schedule_id" value="<?= htmlspecialchars($modal_schedule['schedule_id']) ?>">
                                        <input type="hidden" name="employee_id" value="<?= htmlspecialchars($modal_schedule['employee_id']) ?>">
                                        <h6>Week:
                                            <?php if ($is_editing): ?>
                                                <input type="date" name="week_start" class="form-control d-inline-block w-auto"
                                                    value="<?= htmlspecialchars($modal_schedule['week_start']) ?>">
                                            <?php else: ?>
                                                <?= htmlspecialchars($modal_schedule['week_start']) ?>
                                            <?php endif; ?>
                                        </h6>
                                        <table class="table table-bordered bg-white schedule-table">
                                            <thead>
                                                <tr class="schedule-table-header">
                                                    <th>Day</th>
                                                    <th>Start Time</th>
                                                    <th>End Time</th>
                                                    <th>Status</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($days as $day): ?>
                                                    <?php $prefix = strtolower(substr($day, 0, 3)); ?>
                                                    <tr>
                                                        <td><?= $day ?></td>
                                                        <td>
                                                            <?php if (!$is_editing): ?>
                                                                <?php if (in_array(($modal_schedule[$prefix . '_status'] ?? ''), ['Off Duty', 'Leave', 'Sick'])): ?>
                                                                    ---
                                                                <?php else: ?>
                                                                    <?= htmlspecialchars($modal_schedule[$prefix . '_start'] ?? '') ?>
                                                                <?php endif; ?>
                                                            <?php else: ?>
                                                                <input type="time" name="<?= $prefix ?>_start" class="form-control"
                                                                    value="<?= htmlspecialchars($modal_schedule[$prefix . '_start'] ?? '') ?>">
                                                            <?php endif; ?>
                                                        </td>
                                                        <td>
                                                            <?php if (!$is_editing): ?>
                                                                <?php if (in_array(($modal_schedule[$prefix . '_status'] ?? ''), ['Off Duty', 'Leave', 'Sick'])): ?>
                                                                    ---
                                                                <?php else: ?>
                                                                    <?= htmlspecialchars($modal_schedule[$prefix . '_end'] ?? '') ?>
                                                                <?php endif; ?>
                                                            <?php else: ?>
                                                                <input type="time" name="<?= $prefix ?>_end" class="form-control"
                                                                    value="<?= htmlspecialchars($modal_schedule[$prefix . '_end'] ?? '') ?>">
                                                            <?php endif; ?>
                                                        </td>
                                                        <td>
                                                            <?php if (!$is_editing): ?>
                                                                <?= htmlspecialchars($modal_schedule[$prefix . '_status'] ?? '') ?>
                                                            <?php else: ?>
                                                                <select name="<?= $prefix ?>_status" class="form-select">
                                                                    <option value="">-- Select Status --</option>
                                                                    <option value="On Duty" <?= ($modal_schedule[$prefix . '_status'] ?? '') == 'On Duty' ? 'selected' : '' ?>>On Duty</option>
                                                                    <option value="Off Duty" <?= ($modal_schedule[$prefix . '_status'] ?? '') == 'Off Duty' ? 'selected' : '' ?>>Off Duty</option>
                                                                    <option value="Leave" <?= ($modal_schedule[$prefix . '_status'] ?? '') == 'Leave' ? 'selected' : '' ?>>Leave</option>
                                                                    <option value="Sick" <?= ($modal_schedule[$prefix . '_status'] ?? '') == 'Sick' ? 'selected' : '' ?>>Sick</option>
                                                                </select>
                                                            <?php endif; ?>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                        <div class="d-flex gap-2 mt-2">
                                            <?php if ($is_editing): ?>
                                                <button type="submit" name="update_schedule" class="btn btn-success">Save Changes</button>
                                                <a href="?view_sched_id=<?= htmlspecialchars($modal_schedule['employee_id']) ?>" class="btn btn-secondary">Cancel</a>
                                            <?php else: ?>
                                                <a href="?view_sched_id=<?= htmlspecialchars($modal_schedule['employee_id']) ?>&edit_sched_id=<?= htmlspecialchars($modal_schedule['schedule_id']) ?>" class="btn btn-warning">Edit</a>
                                            <?php endif; ?>
                                            <button type="submit" name="delete_schedule" class="btn btn-danger" onclick="return confirm('Are you sure you want to delete this schedule?');">Delete</button>
                                        </div>
                                    </form>
                                <?php endforeach; ?>
                            </div>
                            <div class="modal-footer">
                                <a href="doctor_shift_scheduling.php" class="btn btn-secondary">Close</a>
                            </div>
                        </div>
                    </div>
                </div>
                <script>
                    document.body.classList.add('modal-open');
                </script>
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
    </script>
    <script src="../assets/Bootstrap/all.min.js"></script>
    <script src="../assets/Bootstrap/bootstrap.bundle.min.js"></script>
    <script src="../assets/Bootstrap/fontawesome.min.js"></script>
    <script src="../assets/Bootstrap/jq.js"></script>
</body>

</html>