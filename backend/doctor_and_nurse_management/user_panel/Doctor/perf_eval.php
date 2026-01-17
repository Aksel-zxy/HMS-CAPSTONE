<?php
// START SESSION AND INCLUDE CONFIG
// Ensure this matches your actual file path
include '../../../../SQL/config.php';

// --- 1. AUTHENTICATION & USER DATA ---
if (!isset($_SESSION['profession']) || $_SESSION['profession'] !== 'Doctor') {
    header('Location: login.php');
    exit();
}

if (!isset($_SESSION['employee_id'])) {
    echo "User ID is not set in session.";
    exit();
}

// Fetch Logged-in Doctor's Profile Data
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

// --- CONFIGURATION ---
$evaluator_id = $_SESSION['employee_id'];
$current_date = date('Y-m-d');
$show_form    = false; // Default to false until we find a valid nurse
$assigned_nurse = null;
$error_message = "";

// --- 2. FIND ASSIGNED NURSE (Room Logic) ---
date_default_timezone_set('Asia/Manila');

// Determine which column to check based on today's day (e.g., 'mon_room_id')
$day_prefix = strtolower(date('D'));
$room_col   = $day_prefix . '_room_id';
$valid_cols = ['mon_room_id', 'tue_room_id', 'wed_room_id', 'thu_room_id', 'fri_room_id', 'sat_room_id', 'sun_room_id'];

if (in_array($room_col, $valid_cols)) {

    // A. Find Doctor's Room for Today
    $doc_sql = "SELECT $room_col as room_id 
                FROM shift_scheduling 
                WHERE employee_id = ? 
                AND week_start <= ? 
                ORDER BY week_start DESC LIMIT 1";

    $stmt = $conn->prepare($doc_sql);
    $stmt->bind_param("is", $evaluator_id, $current_date);
    $stmt->execute();
    $doc_res = $stmt->get_result();
    $doc_schedule = $doc_res->fetch_assoc();

    if ($doc_schedule && !empty($doc_schedule['room_id'])) {
        $shared_room_id = $doc_schedule['room_id'];

        // B. Find the Nurse in that SAME Room
        $nurse_sql = "SELECT e.employee_id, e.first_name, e.last_name 
                      FROM shift_scheduling s
                      JOIN hr_employees e ON s.employee_id = e.employee_id
                      WHERE s.week_start <= ? 
                      AND s.$room_col = ? 
                      AND e.profession = 'Nurse' 
                      ORDER BY s.week_start DESC 
                      LIMIT 1";

        $stmt = $conn->prepare($nurse_sql);
        $stmt->bind_param("si", $current_date, $shared_room_id);
        $stmt->execute();
        $nurse_res = $stmt->get_result();

        if ($nurse_res->num_rows > 0) {
            $assigned_nurse = $nurse_res->fetch_assoc();
        } else {
            // Doctor has a room, but no nurse is there
            $error_message = "You are in Room #$shared_room_id, but no Nurse is assigned there today.";
        }
    } else {
        // Doctor is not scheduled today
        $error_message = "You do not have a room assignment in the schedule for today ($day_prefix).";
    }
} else {
    $error_message = "System Error: Invalid day configuration.";
}

// --- 3. CHECK IF ALREADY EVALUATED ---
if ($assigned_nurse) {
    $target_nurse_id = $assigned_nurse['employee_id'];

    // Check table 'evaluations'
    $check_sql = "SELECT evaluation_id 
                  FROM evaluations 
                  WHERE evaluator_id = ? 
                  AND evaluatee_id = ? 
                  AND DATE(evaluation_date) = ?";

    $stmt = $conn->prepare($check_sql);
    $stmt->bind_param("iis", $evaluator_id, $target_nurse_id, $current_date);
    $stmt->execute();
    $check_result = $stmt->get_result();

    if ($check_result->num_rows > 0) {
        // ALREADY DONE
        $error_message = "You have already evaluated Nurse " . htmlspecialchars($assigned_nurse['first_name']) . " " . htmlspecialchars($assigned_nurse['last_name']) . " today.";
        $show_form = false;
    } else {
        // ALLOWED
        $show_form = true;
    }
}

// --- 4. FETCH QUESTIONS (Only if form is shown) ---
$grouped_questions = [];
if ($show_form) {
    $questions_query = "SELECT * FROM evaluation_questions ORDER BY category ASC, question_id ASC";
    $result = $conn->query($questions_query);

    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $grouped_questions[$row['category']][] = $row;
        }
    }
}
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
    <link rel="stylesheet" href="notif.css">
    <style>
        /* Rating System UI Improvements */
        .rating-label {
            width: 45px;
            height: 45px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 1.1rem;
            border-radius: 50% !important;
            /* Make them circles */
            margin: 0 5px;
        }

        /* Make the card float nicely */
        .evaluation-card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.05);
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
                <a href="renew.php" class="sidebar-link" data-bs-toggle="#" data-bs-target="#"
                    aria-expanded="false" aria-controls="auth">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 640 640">
                        <path d="M32 160C32 124.7 60.7 96 96 96L544 96C579.3 96 608 124.7 608 160L32 160zM32 208L608 208L608 480C608 515.3 579.3 544 544 544L96 544C60.7 544 32 515.3 32 480L32 208zM279.3 480C299.5 480 314.6 460.6 301.7 445C287 427.3 264.8 416 240 416L176 416C151.2 416 129 427.3 114.3 445C101.4 460.6 116.5 480 136.7 480L279.2 480zM208 376C238.9 376 264 350.9 264 320C264 289.1 238.9 264 208 264C177.1 264 152 289.1 152 320C152 350.9 177.1 376 208 376zM392 272C378.7 272 368 282.7 368 296C368 309.3 378.7 320 392 320L504 320C517.3 320 528 309.3 528 296C528 282.7 517.3 272 504 272L392 272zM392 368C378.7 368 368 378.7 368 392C368 405.3 378.7 416 392 416L504 416C517.3 416 528 405.3 528 392C528 378.7 517.3 368 504 368L392 368z" />
                    </svg>
                    <span style="font-size: 18px;">Compliance Licensing</span>
                </a>
            </li>
            <li class="sidebar-item">
                <a href="perf_eval.php" class="sidebar-link" data-bs-toggle="#" data-bs-target="#"
                    aria-expanded="false" aria-controls="auth">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 640 640">
                        <path d="M128 128C128 92.7 156.7 64 192 64L341.5 64C358.5 64 374.8 70.7 386.8 82.7L493.3 189.3C505.3 201.3 512 217.6 512 234.6L512 512C512 547.3 483.3 576 448 576L192 576C156.7 576 128 547.3 128 512L128 128zM336 122.5L336 216C336 229.3 346.7 240 360 240L453.5 240L336 122.5zM337 327C327.6 317.6 312.4 317.6 303.1 327L239.1 391C229.7 400.4 229.7 415.6 239.1 424.9C248.5 434.2 263.7 434.3 273 424.9L296 401.9L296 488C296 501.3 306.7 512 320 512C333.3 512 344 501.3 344 488L344 401.9L367 424.9C376.4 434.3 391.6 434.3 400.9 424.9C410.2 415.5 410.3 400.3 400.9 391L336.9 327z" />
                    </svg>
                    <span style="font-size: 18px;">Performance and Evaluation</span>
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
                <div class="logo d-flex align-items-center">
                    <div class="notification-wrapper position-relative me-4" style="cursor: pointer;">
                        <div onclick="toggleNotifications()">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-bell-fill" viewBox="0 0 16 16">
                                <path d="M8 16a2 2 0 0 0 2-2H6a2 2 0 0 0 2 2m.995-14.901a1 1 0 1 0-1.99 0A5 5 0 0 0 3 6c0 1.098-.5 6-2 7h14c-1.5-1-2-5.902-2-7 0-2.42-1.72-4.44-4.005-4.901" />
                            </svg>
                            <span id="notification-count" class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="display:none; font-size: 0.6rem;">
                                0
                            </span>
                        </div>

                        <div id="notification-dropdown" class="custom-notify-dropdown hidden">
                            <div class="notify-header">
                                License Alerts
                            </div>
                            <ul id="notification-list">
                                <li class="empty-state">Loading...</li>
                            </ul>
                        </div>
                    </div>
                    <div class="dropdown d-flex align-items-center">
                        <span class="username ml-1 me-2"><?php echo $user['first_name']; ?> <?php echo $user['last_name']; ?></span>
                        <button class="btn dropdown-toggle" type="button" id="dropdownMenuButton" data-bs-toggle="dropdown" aria-expanded="false">
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
            <div class="container-fluid py-4">
                <h2 class="schedule-title">🧑‍⚕️Performance Evaluation</h2>
                <div class="row justify-content-center" style="max-height: 75vh; overflow-y: auto; padding-right: 5px;">
                    <div class="col-lg-10 col-xl-9">

                        <?php if (!empty($error_message)): ?>
                            <div class="alert alert-warning shadow-sm border-0 d-flex align-items-center p-4 mb-5">
                                <i class="bi bi-exclamation-triangle-fill fs-1 me-4 text-warning"></i>
                                <div>
                                    <h4 class="alert-heading fw-bold">Notice</h4>
                                    <p class="mb-0 fs-5"><?= $error_message ?></p>
                                </div>
                            </div>

                        <?php elseif ($show_form && $assigned_nurse): ?>
                            <div class="card border-0 shadow-sm mb-5 bg-white rounded-3">
                                <div class="card-body p-5 text-center">
                                    <div class="mb-4">
                                        <span class="d-inline-block p-3 rounded-circle bg-light text-primary mb-3">
                                            <i class="bi bi-person-check-fill fs-1"></i>
                                        </span>
                                        <h3 class="fw-bold text-dark">Evaluation Pending</h3>
                                        <p class="text-muted fs-5">
                                            You are assigned with <strong class="text-primary">Nurse <?= htmlspecialchars($assigned_nurse['first_name'] . ' ' . $assigned_nurse['last_name']) ?></strong> today.
                                        </p>
                                        <p class="text-muted small">Please submit your daily performance assessment.</p>
                                    </div>
                                    <button type="button" class="btn btn-primary btn-lg px-5 py-3 fw-bold rounded-pill shadow hover-shadow" data-bs-toggle="modal" data-bs-target="#evaluationModal">
                                        <i class="bi bi-pencil-square me-2"></i> Start Evaluation
                                    </button>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-info">System: No schedule or actions available for today.</div>
                        <?php endif; ?>

                        <div class="mb-4 border rounded p-3 bg-white shadow-sm mt-4">
                            <div class="d-flex justify-content-between align-items-center border-bottom pb-3 mb-0">
                                <h6 class="fw-bold text-secondary mb-0">
                                    <i class="bi bi-clock-history me-2"></i>Your Evaluation History
                                </h6>
                            </div>
                            <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                                <table class="table table-hover table-bordered mb-0 align-middle">
                                    <thead class="bg-light sticky-top" style="z-index: 1;">
                                        <tr>
                                            <th>Date</th>
                                            <th>Nurse Name</th>
                                            <th class="text-center">Score</th>
                                            <th class="text-center">Rating</th>
                                            <th style="width: 30%;">Comments</th>
                                            <th style="width: 25%;">AI Feedback</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        $history_query = "SELECT e.*, emp.first_name, emp.last_name 
                                                      FROM evaluations e 
                                                      JOIN hr_employees emp ON e.evaluatee_id = emp.employee_id 
                                                      WHERE e.evaluator_id = ? ORDER BY e.evaluation_date asc";
                                        $stmt_hist = $conn->prepare($history_query);
                                        $stmt_hist->bind_param("i", $_SESSION['employee_id']);
                                        $stmt_hist->execute();
                                        $history_result = $stmt_hist->get_result();

                                        if ($history_result->num_rows > 0):
                                            while ($row = $history_result->fetch_assoc()):
                                                $badge_color = ($row['performance_level'] == 'Excellent') ? 'bg-success' : (($row['performance_level'] == 'Poor') ? 'bg-danger' : 'bg-primary');
                                        ?>
                                                <tr>
                                                    <td><?= date("M d, Y", strtotime($row['evaluation_date'])) ?></td>
                                                    <td class="fw-bold text-primary"><?= htmlspecialchars($row['first_name'] . " " . $row['last_name']) ?></td>
                                                    <td class="text-center fw-bold"><?= $row['average_score'] ?></td>
                                                    <td class="text-center"><span class="badge rounded-pill <?= $badge_color ?>"><?= $row['performance_level'] ?></span></td>
                                                    <td class="small text-muted"><?= htmlspecialchars($row['comments'] ?: "No comments") ?></td>
                                                    <td class="small text-primary fst-italic"><?= htmlspecialchars($row['ai_feedback'] ?: "No AI feedback") ?></td>
                                                </tr>
                                            <?php endwhile;
                                        else: ?>
                                            <tr>
                                                <td colspan="6" class="text-center py-4 text-muted">No evaluations found.</td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php if ($show_form && $assigned_nurse): ?>
                <div class="modal fade" id="evaluationModal" tabindex="-1" aria-labelledby="evalModalLabel" aria-hidden="true">
                    <div class="modal-dialog modal-lg modal-dialog-centered">
                        <div class="modal-content">

                            <form action="submit_evaluation_process.php" method="POST">

                                <div class="modal-header modal-header-primary text-white" style="background-color: #0d6efd;">
                                    <div>
                                        <h5 class="modal-title fw-bold" id="evalModalLabel">
                                            <i class="bi bi-clipboard2-pulse me-2"></i>Evaluating: Nurse <?= htmlspecialchars($assigned_nurse['last_name']) ?>
                                        </h5>
                                        <p class="mb-0 small opacity-75">Room Assignment: Auto-Detected</p>
                                    </div>
                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>

                                <div class="modal-body bg-light">

                                    <input type="hidden" name="evaluatee_id" value="<?= $assigned_nurse['employee_id'] ?>">

                                    <div class="px-2" style="max-height: 60vh; overflow-y: auto; overflow-x: hidden;">

                                        <?php if (!empty($grouped_questions)): ?>
                                            <?php foreach ($grouped_questions as $category => $questions): ?>
                                                <div class="card mb-4 border-0 shadow-sm">
                                                    <div class="card-header bg-white border-bottom sticky-top" style="top: 0; z-index: 10;">
                                                        <h6 class="text-primary fw-bold text-uppercase mb-0"><?= htmlspecialchars($category) ?></h6>
                                                    </div>
                                                    <div class="card-body">
                                                        <?php foreach ($questions as $q): ?>
                                                            <div class="mb-4 border-bottom pb-3">
                                                                <label class="fw-bold text-dark mb-1 d-block"><?= htmlspecialchars($q['criteria']) ?></label>
                                                                <small class="text-muted fst-italic d-block mb-3"><?= htmlspecialchars($q['description']) ?></small>

                                                                <div class="d-flex justify-content-center align-items-center gap-2">
                                                                    <span class="small fw-bold text-danger">Poor</span>

                                                                    <div class="btn-group" role="group">
                                                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                                                            <input type="radio" class="btn-check" name="ratings[<?= $q['question_id'] ?>]" id="m_q_<?= $q['question_id'] ?>_<?= $i ?>" value="<?= $i ?>" required>
                                                                            <label class="btn btn-outline-primary rating-label mx-1 rounded-circle" style="width: 35px; height: 35px; display: flex; align-items: center; justify-content: center;" for="m_q_<?= $q['question_id'] ?>_<?= $i ?>">
                                                                                <?= $i ?>
                                                                            </label>
                                                                        <?php endfor; ?>
                                                                    </div>

                                                                    <span class="small fw-bold text-success">Excellent</span>
                                                                </div>
                                                            </div>
                                                        <?php endforeach; ?>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <div class="alert alert-danger">No questions loaded. Contact Admin.</div>
                                        <?php endif; ?>

                                        <div class="card border-0 shadow-sm mt-3">
                                            <div class="card-body">
                                                <label class="form-label fw-bold">Additional Comments / Summary</label>
                                                <textarea name="comments" class="form-control" rows="3" placeholder="Describe any specific incidents..."></textarea>
                                            </div>
                                        </div>

                                    </div>
                                </div>

                                <div class="modal-footer bg-white">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                    <button type="submit" class="btn btn-primary fw-bold px-4">
                                        <i class="bi bi-send-check me-2"></i> Submit Evaluation
                                    </button>
                                </div>

                            </form>

                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <?php include 'doctor_profile.php'; ?>
        <script>
            const toggler = document.querySelector(".toggler-btn");
            toggler.addEventListener("click", function() {
                document.querySelector("#sidebar").classList.toggle("collapsed");
            });
            document.addEventListener('DOMContentLoaded', function() {
                var myModal = new bootstrap.Modal(document.getElementById('evaluationModal'));
                myModal.show();
            });
        </script>
        <script src="notif.js"></script>
        <script src="../../assets/Bootstrap/all.min.js"></script>
        <script src="../../assets/Bootstrap/bootstrap.bundle.min.js"></script>
        <script src="../../assets/Bootstrap/fontawesome.min.js"></script>
        <script src="../../assets/Bootstrap/jq.js"></script>
</body>

</html>