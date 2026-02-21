<?php
include '../../../SQL/config.php';
if (!isset($_SESSION['doctor']) || $_SESSION['doctor'] !== true) {
    header('Location: ' . BASE_URL . 'backend/login.php');
    exit();
}
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    echo "User ID is not set in session.";
    exit();
}
$query = "SELECT * FROM users WHERE user_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
if (!$user) {
    echo "No user found.";
    exit();
}
$stats_sql = "SELECT COUNT(*) as total, AVG(average_score) as site_avg FROM evaluations";
$stats_res = $conn->query($stats_sql)->fetch_assoc();

$top_sql = "SELECT n.first_name, n.last_name, AVG(e.average_score) as avg_score 
                FROM evaluations e 
                JOIN hr_employees n ON e.evaluatee_id = n.employee_id 
                GROUP BY e.evaluatee_id 
                ORDER BY avg_score DESC LIMIT 1";
$top_res = $conn->query($top_sql)->fetch_assoc();

$list_sql = "SELECT 
                    e.*,
                    doc.first_name AS doc_fname, doc.last_name AS doc_lname,
                    nurse.first_name AS nurse_fname, nurse.last_name AS nurse_lname
                FROM evaluations e
                JOIN hr_employees doc ON e.evaluator_id = doc.employee_id
                JOIN hr_employees nurse ON e.evaluatee_id = nurse.employee_id
                ORDER BY e.evaluation_date DESC";
$list_result = $conn->query($list_sql);
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
    <link rel="stylesheet" href="analytics.css">
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
                        <a href="../scheduling_shifts_and_duties/doctor_shift_scheduling.php" class="sidebar-link">Doctor Shift Scheduling</a>
                    </li>
                    <li class="sidebar-item">
                        <a href="../scheduling_shifts_and_duties/nurse_shift_scheduling.php" class="sidebar-link">Nurse Shift Scheduling</a>
                    </li>
                    <li class="sidebar-item">
                        <a href="../scheduling_shifts_and_duties/duty_assignment.php" class="sidebar-link">Duty Assignment</a>
                    </li>
                    <li class="sidebar-item">
                        <a href="../scheduling_shifts_and_duties/schedule_calendar.php" class="sidebar-link">Schedule Calendar</a>
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
                        <a href="doc_feedback.php" class="sidebar-link">View Nurse Evaluation</a>
                    </li>
                    <li class="sidebar-item">
                        <a href="analytics.php" class="sidebar-link">Evaluation Report & Analytics</a>
                    </li>
                    <li class="sidebar-item">
                        <a href="criteria.php" class="sidebar-link">Manage Evaluation Criteria</a>
                    </li>
                </ul>
            </li>
            <li class="sidebar-item">
                <a href="../repair_request.php" class="sidebar-link collapsed has-dropdown" data-bs-toggle="#" data-bs-target="#request_repair"
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
                                <a class="dropdown-item" href="../../logout.php" style="font-size: 14px; color: #007bff; text-decoration: none; padding: 8px 12px; border-radius: 4px; transition: background-color 0.3s ease;">
                                    Logout
                                </a>
                            </li>
                        </ul>

                    </div>
                </div>
            </div>
            <!-- START CODING HERE -->
            <div style="width:95%; margin:20px auto; padding:20px;">
                <h2 style="font-family:Arial, sans-serif; color:#0d6efd; margin-bottom:25px; border-bottom:2px solid #0d6efd; padding-bottom:10px;">
                    🧑‍⚕️ Evaluation Report & Analytics
                </h2>

                <?php
                $stats = $conn->query("SELECT COUNT(*) as total, AVG(average_score) as avg FROM evaluations")->fetch_assoc();
                $top = $conn->query("SELECT n.first_name, n.last_name, AVG(e.average_score) as score 
                         FROM evaluations e JOIN hr_employees n ON e.evaluatee_id = n.employee_id 
                         GROUP BY e.evaluatee_id ORDER BY score DESC LIMIT 1")->fetch_assoc();
                ?>

                <div class="row mb-4 g-3">
                    <div class="col-md-4">
                        <div class="p-3 bg-white border rounded shadow-sm d-flex align-items-center justify-content-between">
                            <div><span class="text-muted small text-uppercase fw-bold">Total Reports</span>
                                <h2 class="mb-0 fw-bold text-primary"><?= number_format($stats['total']) ?></h2>
                            </div>
                            <div class="bg-light rounded-circle p-3 text-primary"><i class="bi bi-file-earmark-text fs-4"></i></div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="p-3 bg-white border rounded shadow-sm d-flex align-items-center justify-content-between">
                            <div><span class="text-muted small text-uppercase fw-bold">Avg. Facility Score</span>
                                <h2 class="mb-0 fw-bold text-success"><?= number_format($stats['avg'], 2) ?></h2>
                            </div>
                            <div class="bg-light rounded-circle p-3 text-success"><i class="bi bi-bar-chart fs-4"></i></div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="p-3 bg-white border rounded shadow-sm d-flex align-items-center justify-content-between">
                            <div><span class="text-muted small text-uppercase fw-bold">Top Nurse</span>
                                <h5 class="mb-0 fw-bold text-dark text-truncate" style="max-width: 140px;"><?= $top ? htmlspecialchars($top['first_name'] . ' ' . $top['last_name']) : 'N/A' ?></h5>
                            </div>
                            <div class="bg-light rounded-circle p-3 text-warning"><i class="bi bi-trophy fs-4"></i></div>
                        </div>
                    </div>
                </div>

                <?php
                $sql = "SELECT e.*, 
            doc.last_name AS doc_last_name, doc.first_name AS doc_first_name,
            nurse.first_name AS nurse_fname, nurse.last_name AS nurse_lname
            FROM evaluations e
            JOIN hr_employees doc ON e.evaluator_id = doc.employee_id
            JOIN hr_employees nurse ON e.evaluatee_id = nurse.employee_id
            ORDER BY e.evaluation_date DESC";
                $result = $conn->query($sql);
                ?>

                <div class="card border-0 shadow-sm">
                    <div class="card-body p-0">
                        <div class="table-responsive" style="max-height: 700px; overflow-y: auto;">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="bg-light text-secondary sticky-top" style="z-index: 5;">
                                    <tr>
                                        <th class="ps-4">Date</th>
                                        <th>Evaluator</th>
                                        <th>Evaluatee</th>
                                        <th class="text-center">Score</th>
                                        <th class="text-center">Rating</th>
                                        <th style="width: 20%;">Comments</th>
                                        <th style="width: 15%;">AI Analysis</th>
                                        <th class="text-center">Details</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($result && $result->num_rows > 0): ?>
                                        <?php while ($row = $result->fetch_assoc()):
                                            $bg = 'bg-secondary';
                                            if ($row['performance_level'] === 'Excellent') $bg = 'bg-success';
                                            if ($row['performance_level'] === 'Good') $bg = 'bg-primary';
                                            if ($row['performance_level'] === 'Poor') $bg = 'bg-danger';
                                        ?>
                                            <tr>
                                                <td class="ps-4 small text-muted"><?= date("M d, Y", strtotime($row['evaluation_date'])) ?></td>
                                                <td class="fw-bold text-dark"><i class="bi bi-person-badge me-1 text-muted"></i> Dr. <?= htmlspecialchars($row['doc_last_name']) ?></td>
                                                <td class="text-primary fw-bold">Nurse <?= htmlspecialchars($row['nurse_lname']) ?></td>
                                                <td class="text-center fw-bold fs-5"><?= $row['average_score'] ?></td>
                                                <td class="text-center"><span class="badge rounded-pill <?= $bg ?>"><?= $row['performance_level'] ?></span></td>
                                                <td class="small text-muted text-truncate" style="max-width: 150px;">
                                                    <?= htmlspecialchars($row['comments'] ?: 'No comments.') ?>
                                                </td>

                                                <td>
                                                    <?php if (empty($row['ai_feedback']) || $row['ai_feedback'] == 'Pending Generation...'): ?>

                                                        <form action="trigger_ai_feedback.php" method="POST">
                                                            <input type="hidden" name="evaluation_id" value="<?= $row['evaluation_id'] ?>">
                                                            <button type="submit" class="btn btn-sm btn-outline-success fw-bold shadow-sm" style="font-size: 0.75rem;">
                                                                <i class="bi bi-stars me-1"></i> Generate AI
                                                            </button>
                                                        </form>

                                                    <?php else: ?>
                                                        <div class="d-flex align-items-start small">
                                                            <i class="bi bi-robot text-primary me-2 mt-1"></i>
                                                            <span class="fst-italic text-dark"><?= htmlspecialchars(substr($row['ai_feedback'], 0, 40)) . '...' ?></span>
                                                        </div>
                                                    <?php endif; ?>
                                                </td>

                                                <td class="text-center">
                                                    <button class="btn btn-sm btn-outline-primary shadow-sm" data-bs-toggle="modal" data-bs-target="#modal_<?= $row['evaluation_id'] ?>">
                                                        View
                                                    </button>

                                                    <div class="modal fade" id="modal_<?= $row['evaluation_id'] ?>" tabindex="-1" aria-hidden="true">
                                                        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
                                                            <div class="modal-content">

                                                                <div class="modal-header">
                                                                    <div>
                                                                        <h5 class="modal-title fw-bold text-dark">Performance Evaluation</h5>
                                                                        <p class="mb-0 text-muted small">
                                                                            Evaluatee: <span class="text-primary fw-semibold">Nurse <?= htmlspecialchars($row['nurse_fname'] . ' ' . $row['nurse_lname']) ?></span>
                                                                        </p>
                                                                    </div>
                                                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                                </div>

                                                                <div class="modal-body bg-light p-4">

                                                                    <div class="hero-score-card mb-4">
                                                                        <div class="row align-items-center">
                                                                            <div class="col-6 border-end">
                                                                                <span class="text-uppercase text-muted small fw-bold ls-1">Final Score</span>
                                                                                <div class="mt-1">
                                                                                    <span class="display-4 fw-bold text-dark"><?= $row['average_score'] ?></span>
                                                                                    <span class="text-muted fs-5">/ 5.0</span>
                                                                                </div>
                                                                            </div>
                                                                            <div class="col-6">
                                                                                <span class="text-uppercase text-muted small fw-bold ls-1">Rating</span>
                                                                                <div class="mt-2">
                                                                                    <span class="badge rounded-pill fs-6 px-4 py-2 <?= $bg ?>">
                                                                                        <?= $row['performance_level'] ?>
                                                                                    </span>
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                    </div>

                                                                    <?php
                                                                    $q_sql = "SELECT q.category, q.criteria, a.score 
                          FROM evaluation_answers a 
                          JOIN evaluation_questions q ON a.question_id = q.question_id 
                          WHERE a.evaluation_id = " . $row['evaluation_id'] . "
                          ORDER BY q.category ASC";
                                                                    $q_res = $conn->query($q_sql);
                                                                    $grouped = [];
                                                                    while ($item = $q_res->fetch_assoc()) $grouped[$item['category']][] = $item;
                                                                    ?>

                                                                    <?php if (!empty($grouped)): ?>
                                                                        <div class="px-2">
                                                                            <?php foreach ($grouped as $cat => $items): ?>
                                                                                <span class="category-label"><?= htmlspecialchars($cat) ?></span>

                                                                                <?php foreach ($items as $item):
                                                                                    // Determine color class based on score
                                                                                    $color_class = 'text-score-mid';
                                                                                    if ($item['score'] >= 4) $color_class = 'text-score-high';
                                                                                    if ($item['score'] <= 2) $color_class = 'text-score-low';
                                                                                ?>
                                                                                    <div class="question-card d-flex justify-content-between align-items-center">
                                                                                        <span class="text-dark fw-medium small pe-3">
                                                                                            <?= htmlspecialchars($item['criteria']) ?>
                                                                                        </span>
                                                                                        <div class="score-pill <?= $color_class ?>">
                                                                                            <?= $item['score'] ?>
                                                                                        </div>
                                                                                    </div>
                                                                                <?php endforeach; ?>
                                                                            <?php endforeach; ?>
                                                                        </div>
                                                                    <?php else: ?>
                                                                        <div class="text-center py-5 text-muted">
                                                                            <i class="bi bi-clipboard-x fs-1 mb-2"></i>
                                                                            <p>No criteria details found.</p>
                                                                        </div>
                                                                    <?php endif; ?>

                                                                    <div class="mt-5">
                                                                        <span class="category-label mb-3">Analysis & Feedback</span>

                                                                        <div class="p-3 rounded-3 mb-3 border-0 shadow-sm" style="background-color: #f0f7ff; border-left: 4px solid #0d6efd;">
                                                                            <div class="d-flex align-items-center mb-2">
                                                                                <div class="bg-white p-1 rounded-circle shadow-sm me-2 d-flex justify-content-center align-items-center" style="width: 30px; height: 30px;">
                                                                                    <i class="bi bi-stars text-primary"></i>
                                                                                </div>
                                                                                <h6 class="fw-bold text-primary mb-0">AI Performance Summary</h6>
                                                                            </div>
                                                                            <p class="small text-secondary mb-0 lh-sm">
                                                                                <?= nl2br(htmlspecialchars($row['ai_feedback'] ?: 'AI analysis has not been generated yet.')) ?>
                                                                            </p>
                                                                        </div>

                                                                        <?php if (!empty($row['comments'])): ?>
                                                                            <div class="p-3 bg-white border rounded-3 shadow-sm">
                                                                                <h6 class="fw-bold text-dark small mb-2">
                                                                                    <i class="bi bi-chat-left-quote-fill text-muted me-2"></i>Evaluator's Notes
                                                                                </h6>
                                                                                <p class="small text-muted fst-italic mb-0">
                                                                                    "<?= htmlspecialchars($row['comments']) ?>"
                                                                                </p>
                                                                            </div>
                                                                        <?php endif; ?>
                                                                    </div>

                                                                </div>

                                                                <div class="modal-footer border-top-0 bg-light pb-4 pe-4">
                                                                    <button type="button" class="btn btn-outline-secondary px-4 rounded-pill" data-bs-dismiss="modal">Close</button>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="8" class="text-center py-5 text-muted">No records found.</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
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