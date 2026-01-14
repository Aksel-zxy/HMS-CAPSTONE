<?php
include '../../../../SQL/config.php';

if (!isset($_SESSION['profession']) || $_SESSION['profession'] !== 'Doctor') {
    header('Location: login.php');
    exit();
}

if (!isset($_SESSION['employee_id'])) {
    echo "User ID is not set in session.";
    exit();
}

// 1. Fetch user details
$query = "SELECT * FROM hr_employees WHERE employee_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $_SESSION['employee_id']);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if (!$user) {
    echo "No user found.";
    exit();
}

// 2. Fetch Rooms Lookup List
$rooms_lookup = [];
$room_query = "SELECT room_id, room_name FROM rooms_table";
$room_result = $conn->query($room_query);

if ($room_result) {
    while ($r_row = $room_result->fetch_assoc()) {
        $rooms_lookup[$r_row['room_id']] = $r_row['room_name'];
    }
}
// 3. Fetch Nurse Schedules
$nurse_lookup = [];

$nurse_sql = "SELECT s.*, e.first_name, e.last_name 
              FROM shift_scheduling s
              JOIN hr_employees e ON s.employee_id = e.employee_id
              WHERE e.profession = 'Nurse'";

$nurse_result = $conn->query($nurse_sql);

if ($nurse_result) {
    while ($n_row = $nurse_result->fetch_assoc()) {
        $week = $n_row['week_start'];
        $nurse_name = $n_row['first_name'] . ' ' . $n_row['last_name'];
        $day_prefixes = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'];

        foreach ($day_prefixes as $d_prefix) {
            $r_id = $n_row[$d_prefix . '_room_id'];
            if (!empty($r_id)) {
                $nurse_lookup[$week][$d_prefix][$r_id][] = $nurse_name;
            }
        }
    }
}
// 4. Fetch schedule for the logged-in doctor
$modal_schedules = [];
$employee_id = $_SESSION['employee_id'];
// NOTE: You might want to remove "ORDER BY week_start ASC" or change to DESC if you want newest weeks first
$stmt = $conn->prepare("SELECT * FROM shift_scheduling WHERE employee_id = ? ORDER BY week_start ASC");
$stmt->bind_param("i", $employee_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $modal_schedules[] = $row;
}

// Define days of the week
$days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HMS | Doctor User Panel</title>
    <link rel="shortcut icon" href="../../assets/image/favicon.ico" type="image/x-icon">
    <link rel="stylesheet" href="../../assets/CSS/bootstrap.min.css">
    <link rel="stylesheet" href="../../assets/CSS/super.css">
    <link rel="stylesheet" href="../../assets/CSS/my_schedule.css">
    <style>
        .profile-icon-circle {
            width: 90px;
            height: 90px;
            background-color: #f8f9fa;
            border: 2px solid #0d6efd;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto;
            color: #0d6efd;
            transition: all 0.3s ease;
        }

        .profile-trigger:hover .profile-icon-circle {
            background-color: #0d6efd;
            color: #ffffff;
            transform: scale(1.05);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
        }
    </style>
</head>

<body>
    <div class="d-flex">
        <aside id="sidebar" class="sidebar-toggle">
            <div class="sidebar-logo mt-3">
                <img src="../../assets/image/logo-dark.png" width="90px" height="20px">
            </div>
            <div class="text-center my-4">
                <a href="#" class="d-inline-block text-decoration-none profile-trigger" data-bs-toggle="modal" data-bs-target="#profileModal">

                    <div class="profile-icon-circle shadow-sm">
                        <div style="font-size: 30px; font-weight: bold;">
                            <?php
                            // Get first letter of First Name + First letter of Last Name
                            $initials = substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1);
                            echo strtoupper($initials);
                            ?>
                        </div>
                    </div>

                    <div class="mt-2 text-primary fw-bold" style="font-size: 14px;"><?php echo $user['first_name']; ?> <?php echo $user['last_name']; ?></div>
                </a>
            </div>
            <div class="menu-title">Navigation</div>
            <li class="sidebar-item">
                <a href="my_doctor_schedule.php" class="sidebar-link" data-bs-toggle="#" data-bs-target="#"
                    aria-expanded="false" aria-controls="auth">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 640 640">
                        <path d="M224 64C241.7 64 256 78.3 256 96L256 128L384 128L384 96C384 78.3 398.3 64 416 64C433.7 64 448 78.3 448 96L448 128L480 128C515.3 128 544 156.7 544 192L544 480C544 515.3 515.3 544 480 544L160 544C124.7 544 96 515.3 96 480L96 192C96 156.7 124.7 128 160 128L192 128L192 96C192 78.3 206.3 64 224 64zM160 304L160 336C160 344.8 167.2 352 176 352L208 352C216.8 352 224 344.8 224 336L224 304C224 295.2 216.8 288 208 288L176 288C167.2 288 160 295.2 160 304zM288 304L288 336C288 344.8 295.2 352 304 352L336 352C344.8 352 352 344.8 352 336L352 304C352 295.2 344.8 288 336 288L304 288C295.2 288 288 295.2 288 304zM432 288C423.2 288 416 295.2 416 304L416 336C416 344.8 423.2 352 432 352L464 352C472.8 352 480 344.8 480 336L480 304C480 295.2 472.8 288 464 288L432 288zM160 432L160 464C160 472.8 167.2 480 176 480L208 480C216.8 480 224 472.8 224 464L224 432C224 423.2 216.8 416 208 416L176 416C167.2 416 160 423.2 160 432zM304 416C295.2 416 288 423.2 288 432L288 464C288 472.8 295.2 480 304 480L336 480C344.8 480 352 472.8 352 464L352 432C352 423.2 344.8 416 336 416L304 416zM416 432L416 464C416 472.8 423.2 480 432 480L464 480C472.8 480 480 472.8 480 464L480 432C480 423.2 472.8 416 464 416L432 416C423.2 416 416 423.2 416 432z" />
                    </svg>
                    <span style="font-size: 18px;">My Schedule</span>
                </a>
            </li>
            <li class="sidebar-item">
                <a href="doctor_duty.php" class="sidebar-link" data-bs-toggle="#" data-bs-target="#"
                    aria-expanded="false" aria-controls="auth">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 640 640">
                        <path d="M160 96C160 78.3 174.3 64 192 64L448 64C465.7 64 480 78.3 480 96C480 113.7 465.7 128 448 128L418.5 128L428.8 262.1C465.9 283.3 494.6 318.5 507 361.8L510.8 375.2C513.6 384.9 511.6 395.2 505.6 403.3C499.6 411.4 490 416 480 416L160 416C150 416 140.5 411.3 134.5 403.3C128.5 395.3 126.5 384.9 129.3 375.2L133 361.8C145.4 318.5 174 283.3 211.2 262.1L221.5 128L192 128C174.3 128 160 113.7 160 96zM288 464L352 464L352 576C352 593.7 337.7 608 320 608C302.3 608 288 593.7 288 576L288 464z" />
                    </svg>
                    <span style="font-size: 18px;">Patient Management</span>
                </a>
            </li>
            <li class="sidebar-item">
                <a href="#" class="sidebar-link" data-bs-toggle="#" data-bs-target="#"
                    aria-expanded="false" aria-controls="auth">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 640 640">
                        <path d="M32 160C32 124.7 60.7 96 96 96L544 96C579.3 96 608 124.7 608 160L32 160zM32 208L608 208L608 480C608 515.3 579.3 544 544 544L96 544C60.7 544 32 515.3 32 480L32 208zM279.3 480C299.5 480 314.6 460.6 301.7 445C287 427.3 264.8 416 240 416L176 416C151.2 416 129 427.3 114.3 445C101.4 460.6 116.5 480 136.7 480L279.2 480zM208 376C238.9 376 264 350.9 264 320C264 289.1 238.9 264 208 264C177.1 264 152 289.1 152 320C152 350.9 177.1 376 208 376zM392 272C378.7 272 368 282.7 368 296C368 309.3 378.7 320 392 320L504 320C517.3 320 528 309.3 528 296C528 282.7 517.3 272 504 272L392 272zM392 368C378.7 368 368 378.7 368 392C368 405.3 378.7 416 392 416L504 416C517.3 416 528 405.3 528 392C528 378.7 517.3 368 504 368L392 368z" />
                    </svg>
                    <span style="font-size: 18px;">License & Compliance Viewer</span>
                </a>
            </li>
            <li class="sidebar-item">
                <a href="#" class="sidebar-link" data-bs-toggle="#" data-bs-target="#"
                    aria-expanded="false" aria-controls="auth">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 640 640">
                        <path d="M128 128C128 92.7 156.7 64 192 64L341.5 64C358.5 64 374.8 70.7 386.8 82.7L493.3 189.3C505.3 201.3 512 217.6 512 234.6L512 512C512 547.3 483.3 576 448 576L192 576C156.7 576 128 547.3 128 512L128 128zM336 122.5L336 216C336 229.3 346.7 240 360 240L453.5 240L336 122.5zM337 327C327.6 317.6 312.4 317.6 303.1 327L239.1 391C229.7 400.4 229.7 415.6 239.1 424.9C248.5 434.2 263.7 434.3 273 424.9L296 401.9L296 488C296 501.3 306.7 512 320 512C333.3 512 344 501.3 344 488L344 401.9L367 424.9C376.4 434.3 391.6 434.3 400.9 424.9C410.2 415.5 410.3 400.3 400.9 391L336.9 327z" />
                    </svg>
                    <span style="font-size: 18px;">Upload Renewal Documents</span>
                </a>
            </li>
            <li class="sidebar-item">
                <a href="#" class="sidebar-link" data-bs-toggle="#" data-bs-target="#"
                    aria-expanded="false" aria-controls="auth">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 640 640">
                        <path d="M320 64C302.3 64 288 78.3 288 96L288 99.2C215 114 160 178.6 160 256L160 277.7C160 325.8 143.6 372.5 113.6 410.1L103.8 422.3C98.7 428.6 96 436.4 96 444.5C96 464.1 111.9 480 131.5 480L508.4 480C528 480 543.9 464.1 543.9 444.5C543.9 436.4 541.2 428.6 536.1 422.3L526.3 410.1C496.4 372.5 480 325.8 480 277.7L480 256C480 178.6 425 114 352 99.2L352 96C352 78.3 337.7 64 320 64zM258 528C265.1 555.6 290.2 576 320 576C349.8 576 374.9 555.6 382 528L258 528z" />
                    </svg>
                    <span style="font-size: 18px;">Notification Alerts</span>
                </a>
            </li>
        </aside>
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
                        <span class="username ml-1 me-2"><?php echo $user['first_name']; ?> <?php echo $user['last_name']; ?></span><button class="btn dropdown-toggle" type="button" id="dropdownMenuButton" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-person-circle"></i>
                        </button>
                        <ul class="dropdown-menu" aria-labelledby="dropdownMenuButton" style="min-width: 200px; padding: 10px; border-radius: 5px; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); background-color: #fff; color: #333;">
                            <li style="margin-bottom: 8px; font-size: 14px; color: #555;">
                                <span>Welcome <strong style="color: #007bff;"><?php echo $user['last_name']; ?></strong>!</span>
                            </li>
                            <li>
                                <a class="dropdown-item" href="../../../logout.php" style="font-size: 14px; color: #007bff; text-decoration: none; padding: 8px 12px; border-radius: 4px; transition: background-color 0.3s ease;">
                                    Logout
                                </a>
                            </li>
                        </ul>

                    </div>
                </div>
            </div>
            <div class="container-fluid">
                <h2 class="schedule-title">🧑‍⚕️My Schedule</h2>

                <?php if (!empty($modal_schedules)): ?>

                    <div class="schedule-list-container" style="max-height: 75vh; overflow-y: auto; padding-right: 5px;">

                        <?php foreach ($modal_schedules as $modal_schedule): ?>

                            <?php
                            // --- THIS IS THE NEW LOGIC TO HIDE PAST WEEKS ---
                            $week_start = $modal_schedule['week_start'];
                            // Calculate the Sunday of that week (Start + 6 days)
                            $week_end_date = date('Y-m-d', strtotime($week_start . ' + 6 days'));
                            // Get today's date
                            $today = date('Y-m-d');

                            // If today is greater than the end of the week, skip this loop iteration
                            if ($today > $week_end_date) {
                                continue;
                            }
                            // ------------------------------------------------
                            ?>

                            <div class="mb-4 border rounded p-3 schedule-list-view">
                                <h6 class="schedule-date">Week: <?= htmlspecialchars($modal_schedule['week_start']) ?></h6>
                                <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                                    <table class="table table-bordered bg-white schedule-calendar-table mb-0">
                                        <thead style="position: sticky; top: 0; background-color: #ffffff; z-index: 1; box-shadow: 0 2px 2px -1px rgba(0,0,0,0.1);">
                                            <tr class="schedule-table-header">
                                                <th>Day</th>
                                                <th>Start Time</th>
                                                <th>End Time</th>
                                                <th>Status</th>
                                                <th>Room</th>
                                                <th>Nurse on Duty</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($days as $day): ?>
                                                <?php $prefix = strtolower(substr($day, 0, 3)); ?>
                                                <tr>
                                                    <td><?= $day ?></td>
                                                    <td>
                                                        <?php if (in_array(($modal_schedule[$prefix . '_status'] ?? ''), ['Off Duty', 'Leave', 'Sick'])): ?>---<?php else: ?><?= htmlspecialchars($modal_schedule[$prefix . '_start'] ?? '') ?><?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <?php if (in_array(($modal_schedule[$prefix . '_status'] ?? ''), ['Off Duty', 'Leave', 'Sick'])): ?>---<?php else: ?><?= htmlspecialchars($modal_schedule[$prefix . '_end'] ?? '') ?><?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <?= htmlspecialchars($modal_schedule[$prefix . '_status'] ?? '') ?>
                                                    </td>
                                                    <td>
                                                        <?php
                                                        $room_col = $prefix . '_room_id';
                                                        $current_room_id = $modal_schedule[$room_col] ?? null; // Saved ID for next step

                                                        if (in_array(($modal_schedule[$prefix . '_status'] ?? ''), ['Off Duty', 'Leave', 'Sick'])) {
                                                            echo '---';
                                                        } else {
                                                            echo htmlspecialchars($rooms_lookup[$current_room_id] ?? '---');
                                                        }
                                                        ?>
                                                    </td>

                                                    <td>
                                                        <?php
                                                        // Logic: Check if there is a nurse in this room, on this day, in this week
                                                        $current_week = $modal_schedule['week_start'];

                                                        // Only check if you (the doctor) are actually assigned a room
                                                        if (!empty($current_room_id) && !in_array(($modal_schedule[$prefix . '_status'] ?? ''), ['Off Duty', 'Leave', 'Sick'])) {

                                                            // Check our lookup array
                                                            if (isset($nurse_lookup[$current_week][$prefix][$current_room_id])) {
                                                                // Implode handles cases where 2 nurses might be in the same room
                                                                echo implode(', ', $nurse_lookup[$current_week][$prefix][$current_room_id]);
                                                            } else {
                                                                echo '<span class="text-muted">None</span>';
                                                            }
                                                        } else {
                                                            echo '---';
                                                        }
                                                        ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        <?php endforeach; ?>

                    </div> <?php else: ?>
                    <div class="alert alert-info mt-4">No schedule found for you.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    </div>
    <?php include 'doctor_profile.php'; ?>
    <script>
        const toggler = document.querySelector(".toggler-btn");
        toggler.addEventListener("click", function() {
            document.querySelector("#sidebar").classList.toggle("collapsed");
        });
    </script>
    <script src="../../assets/Bootstrap/all.min.js"></script>
    <script src="../../assets/Bootstrap/bootstrap.bundle.min.js"></script>
    <script src="../../assets/Bootstrap/fontawesome.min.js"></script>
    <script src="../../assets/Bootstrap/jq.js"></script>
</body>

</html>