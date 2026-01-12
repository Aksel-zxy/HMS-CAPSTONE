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
// ==========================================
//  CRUD LOGIC
// ==========================================

// 1. ADD QUESTION
if (isset($_POST['add_question'])) {
    $cat = $_POST['category'];
    $crit = $_POST['criteria'];
    $desc = $_POST['description'];

    $stmt = $conn->prepare("INSERT INTO evaluation_questions (category, criteria, description) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $cat, $crit, $desc);
    $stmt->execute();
    header("Location: criteria.php?msg=added");
    exit();
}

// 2. UPDATE QUESTION
if (isset($_POST['update_question'])) {
    $id = $_POST['question_id'];
    $cat = $_POST['category'];
    $crit = $_POST['criteria'];
    $desc = $_POST['description'];

    $stmt = $conn->prepare("UPDATE evaluation_questions SET category=?, criteria=?, description=? WHERE question_id=?");
    $stmt->bind_param("sssi", $cat, $crit, $desc, $id);
    $stmt->execute();
    header("Location: criteria.php?msg=updated");
    exit();
}

// 3. DELETE QUESTION
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    $conn->query("DELETE FROM evaluation_questions WHERE question_id=$id");
    header("Location: criteria.php?msg=deleted");
    exit();
}

// FETCH ALL QUESTIONS
$questions = $conn->query("SELECT * FROM evaluation_questions ORDER BY category DESC, question_id ASC");
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
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
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

                <h2 style="font-family:Arial, sans-serif; color:#0d6efd; margin-bottom:20px; border-bottom:2px solid #0d6efd; padding-bottom:8px;">
                    📋 Evaluation Criteria Management
                </h2>
                <?php if (isset($_GET['msg'])): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php
                        if ($_GET['msg'] == 'deleted') echo "Criteria successfully deleted.";
                        if ($_GET['msg'] == 'updated') echo "Criteria successfully updated.";
                        if ($_GET['msg'] == 'added') echo "New criteria successfully added.";
                        ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
                <div class="d-flex justify-content-end mb-3">
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addModal">
                        <i class="bi bi-plus-lg me-1"></i> Add New Criteria
                    </button>
                </div>

                <div class="card shadow-sm border-0">
                    <div class="card-body p-0">
                        <div class="table-responsive" style="max-height: 600px; overflow-y: auto;">

                            <table class="table table-hover align-middle mb-0">
                                <thead class="bg-light text-secondary small text-uppercase" style="position: sticky; top: 0; z-index: 1;">
                                    <tr>
                                        <th class="ps-4 py-3 bg-light">Category</th>
                                        <th class="py-3 bg-light">Criteria Title</th>
                                        <th class="py-3 bg-light">Description (Helper Text)</th>
                                        <th class="text-end pe-4 py-3 bg-light">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($row = $questions->fetch_assoc()):
                                        // Badge Color Logic
                                        $badgeColor = match ($row['category']) {
                                            'Clinical' => 'bg-info text-dark',
                                            'Communication' => 'bg-warning text-dark',
                                            'Professionalism' => 'bg-success',
                                            default => 'bg-secondary'
                                        };
                                    ?>
                                        <tr class="align-middle border-bottom hover-shadow" style="background-color: #fff; transition: all 0.2s;">

                                            <td class="ps-4 py-4">
                                                <span class="badge rounded-pill <?= $badgeColor ?> px-3 py-2 shadow-sm">
                                                    <?= htmlspecialchars($row['category']) ?>
                                                </span>
                                            </td>

                                            <td class="py-4">
                                                <span class="fw-bold text-dark fs-6 d-block mb-1">
                                                    <?= htmlspecialchars($row['criteria']) ?>
                                                </span>
                                            </td>

                                            <td class="py-4" style="max-width: 350px;">
                                                <span class="text-secondary small d-block" style="line-height: 1.5;">
                                                    <?= htmlspecialchars($row['description']) ?>
                                                </span>
                                            </td>

                                            <td class="text-end pe-4 py-4">
                                                <div class="d-flex justify-content-end gap-2">
                                                    <button class="btn btn-sm btn-outline-primary shadow-sm px-3"
                                                        data-bs-toggle="modal"
                                                        data-bs-target="#editModal<?= $row['question_id'] ?>"
                                                        title="Edit Criteria">
                                                        <i class="bi bi-pencil-fill"></i>
                                                    </button>

                                                    <a href="?delete=<?= $row['question_id'] ?>"
                                                        class="btn btn-sm btn-outline-danger shadow-sm px-3"
                                                        onclick="return confirm('Are you sure you want to delete this criteria?')"
                                                        title="Delete Criteria">
                                                        <i class="bi bi-trash-fill"></i>
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>

                                        <div class="modal fade" id="editModal<?= $row['question_id'] ?>" tabindex="-1">
                                            <div class="modal-dialog">
                                                <div class="modal-content">
                                                    <form method="POST">
                                                        <div class="modal-header">
                                                            <h5 class="modal-title fw-bold">Edit Criteria</h5>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                        </div>
                                                        <div class="modal-body">
                                                            <input type="hidden" name="question_id" value="<?= $row['question_id'] ?>">

                                                            <div class="mb-3">
                                                                <label class="form-label fw-bold small text-uppercase text-secondary">Category</label>
                                                                <select name="category" class="form-select">
                                                                    <option value="Clinical" <?= $row['category'] == 'Clinical' ? 'selected' : '' ?>>Clinical Competence</option>
                                                                    <option value="Communication" <?= $row['category'] == 'Communication' ? 'selected' : '' ?>>Communication</option>
                                                                    <option value="Professionalism" <?= $row['category'] == 'Professionalism' ? 'selected' : '' ?>>Professionalism</option>
                                                                </select>
                                                            </div>
                                                            <div class="mb-3">
                                                                <label class="form-label fw-bold small text-uppercase text-secondary">Criteria Title</label>
                                                                <input type="text" name="criteria" class="form-control" value="<?= htmlspecialchars($row['criteria']) ?>" required>
                                                            </div>
                                                            <div class="mb-3">
                                                                <label class="form-label fw-bold small text-uppercase text-secondary">Description</label>
                                                                <textarea name="description" class="form-control" rows="3"><?= htmlspecialchars($row['description']) ?></textarea>
                                                            </div>
                                                        </div>
                                                        <div class="modal-footer bg-light">
                                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                                            <button type="submit" name="update_question" class="btn btn-primary">Save Changes</button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="modal fade" id="addModal" tabindex="-1">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <form method="POST">
                                <div class="modal-header bg-primary text-white">
                                    <h5 class="modal-title fw-bold"><i class="bi bi-plus-circle me-2"></i>Add New Criteria</h5>
                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                </div>
                                <div class="modal-body">
                                    <div class="mb-3">
                                        <label class="form-label fw-bold small text-uppercase text-secondary">Category</label>
                                        <select name="category" class="form-select" required>
                                            <option value="Clinical">Clinical Competence</option>
                                            <option value="Communication">Communication</option>
                                            <option value="Professionalism">Professionalism</option>
                                        </select>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label fw-bold small text-uppercase text-secondary">Criteria Title</label>
                                        <input type="text" name="criteria" class="form-control" placeholder="e.g., Assessment Accuracy" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label fw-bold small text-uppercase text-secondary">Description</label>
                                        <textarea name="description" class="form-control" rows="3" placeholder="e.g., Does the nurse accurately assess..."></textarea>
                                    </div>
                                </div>
                                <div class="modal-footer bg-light">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                    <button type="submit" name="add_question" class="btn btn-primary px-4">Add Criteria</button>
                                </div>
                            </form>
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