<?php
session_start(); // Ensure session is started
include '../../../SQL/config.php';

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

// 1. Handle Form Submission FIRST (Before fetching data)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_employee_license'])) {
    $emp_id = $_POST['employee_id'];
    $l_type = $_POST['license_type'];
    $l_num  = $_POST['license_number'];
    $l_exp  = $_POST['license_expiry'];

    $sql = "UPDATE hr_employees SET license_type = ?, license_number = ?, license_expiry = ? WHERE employee_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sssi", $l_type, $l_num, $l_exp, $emp_id);

    if ($stmt->execute()) {
        // Redirect to refresh the page and show updated data
        header("Location: license_management.php?msg=success&view_docs=" . $emp_id);
        exit();
    }
}

// 2. Fetch all Doctors and Nurses for the main table
$emp_query = "
    SELECT employee_id, first_name, last_name, profession, license_type, license_number, license_expiry 
    FROM hr_employees 
    WHERE profession IN ('Doctor', 'Nurse')
    ORDER BY last_name ASC
";
$employees = $conn->query($emp_query);

// 3. If modal is triggered, fetch employee details AND their documents
$modal_docs = [];
$emp_data = null;
$modal_emp_id = $_GET['view_docs'] ?? null;

if ($modal_emp_id) {
    // Fetch individual employee license details
    $emp_stmt = $conn->prepare("SELECT first_name, last_name, license_type, license_number, license_expiry FROM hr_employees WHERE employee_id = ?");
    $emp_stmt->bind_param("i", $modal_emp_id);
    $emp_stmt->execute();
    $emp_data = $emp_stmt->get_result()->fetch_assoc();

    // Fetch associated files
    $doc_query = "SELECT document_id, document_type, file_blob, uploaded_at FROM hr_employees_documents WHERE employee_id = ? ORDER BY uploaded_at DESC";
    $stmt = $conn->prepare($doc_query);
    $stmt->bind_param("i", $modal_emp_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $modal_docs[] = $row;
    }
}
// Check if a filter is set, default to 'All'
$filter = $_GET['profession_filter'] ?? 'All';

// Base Query
$query_parts = "FROM hr_employees WHERE profession IN ('Doctor', 'Nurse')";

// Add specific filter if not 'All'
if ($filter === 'Doctor') {
    $query_parts .= " AND profession = 'Doctor'";
} elseif ($filter === 'Nurse') {
    $query_parts .= " AND profession = 'Nurse'";
}

$emp_query = "SELECT employee_id, first_name, last_name, profession, license_type, license_number, license_expiry " . $query_parts . " ORDER BY last_name ASC";
$employees = $conn->query($emp_query);
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
    <link rel="stylesheet" href="../assets/CSS/license_management.css">

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
                        <a href="registration_clinical_profile.php" class="sidebar-link">Registration & Clinical Profile Management</a>
                    </li>
                    <li class="sidebar-item">
                        <a href="license_management.php" class="sidebar-link">License Management</a>
                    </li>
                    <li class="sidebar-item">
                        <a href="compliance.php" class="sidebar-link">Compliance Monitoring Dashboard</a>
                    </li>
                    <li class="sidebar-item">
                        <a href="notif_alert.php" class="sidebar-link">Notifications & Alerts</a>
                    </li>
                    <li class="sidebar-item">
                        <a href="audit_log.php" class="sidebar-link">Compliance Audit Log</a>
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
                <div class="card-header bg-white py-3 border-bottom">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <h2 class="mb-0 text-primary fw-bold">
                            🪪 License Management
                        </h2>

                        <form method="GET" class="d-flex align-items-center gap-2">
                            <label class="text-secondary small fw-bold text-uppercase mb-0">Show:</label>
                            <select name="profession_filter" class="form-select form-select-sm border-secondary shadow-none" onchange="this.form.submit()" style="width: 150px;">
                                <option value="All" <?= ($filter ?? 'All') == 'All' ? 'selected' : '' ?>>All Staff</option>
                                <option value="Doctor" <?= ($filter ?? '') == 'Doctor' ? 'selected' : '' ?>>Doctors Only</option>
                                <option value="Nurse" <?= ($filter ?? '') == 'Nurse' ? 'selected' : '' ?>>Nurses Only</option>
                            </select>
                        </form>
                    </div>
                </div>

                <table class="table table-bordered table-striped align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Name</th>
                            <th>Profession</th>
                            <th>License Type</th>
                            <th>License Number</th>
                            <th>Expiry Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (isset($employees) && $employees->num_rows > 0): ?>
                            <?php while ($row = $employees->fetch_assoc()):
                                $empId = $row['employee_id'];
                                $is_expired = (!empty($row['license_expiry']) && strtotime($row['license_expiry']) < time());
                            ?>
                                <tr <?= $is_expired ? 'class="table-danger"' : '' ?>>
                                    <td><?= htmlspecialchars($row['first_name'] . " " . $row['last_name']); ?></td>
                                    <td>
                                        <span class="badge <?= $row['profession'] == 'Doctor' ? 'bg-info' : 'bg-success' ?>">
                                            <?= htmlspecialchars($row['profession']); ?>
                                        </span>
                                    </td>
                                    <td><?= htmlspecialchars($row['license_type'] ?? 'N/A'); ?></td>
                                    <td><?= htmlspecialchars($row['license_number'] ?? 'N/A'); ?></td>
                                    <td>
                                        <span class="<?= $is_expired ? 'text-danger fw-bold' : '' ?>">
                                            <?= htmlspecialchars($row['license_expiry'] ?? 'N/A'); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="?view_docs=<?= $empId ?>&profession_filter=<?= $filter ?? 'All' ?>" class="btn btn-primary btn-sm">Manage</a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">No records found for "<?= htmlspecialchars($filter ?? 'All') ?>".</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>

                <?php if (isset($modal_emp_id) && $modal_emp_id):
                    // Note: $emp_data and $modal_docs should be fetched in your PHP logic section
                ?>
                    <div class="modal fade show" id="docsModal" tabindex="-1" style="display:block; background:rgba(0,0,0,0.5);" aria-modal="true" role="dialog">
                        <div class="modal-dialog modal-lg">
                            <div class="modal-content shadow-lg">
                                <div class="modal-header bg-primary text-white">
                                    <h5 class="modal-title">Edit License: <?= htmlspecialchars($emp_data['first_name'] . ' ' . $emp_data['last_name']) ?></h5>
                                    <a href="?profession_filter=<?= $filter ?? 'All' ?>" class="btn-close btn-close-white"></a>
                                </div>
                                <div class="modal-body">
                                    <form method="POST" action="?profession_filter=<?= $filter ?? 'All' ?>" class="mb-4 p-3 border rounded bg-light">
                                        <input type="hidden" name="employee_id" value="<?= $modal_emp_id ?>">
                                        <div class="row g-3">
                                            <div class="col-md-4">
                                                <label class="fw-bold small">License Type</label>
                                                <input type="text" class="form-control bg-light" value="<?= htmlspecialchars($emp_data['license_type'] ?? 'N/A') ?>" readonly>
                                            </div>
                                            <div class="col-md-4">
                                                <label class="fw-bold small">License Number</label>
                                                <input type="text" class="form-control bg-light" value="<?= htmlspecialchars($emp_data['license_number'] ?? 'N/A') ?>" readonly>
                                            </div>
                                            <div class="col-md-4">
                                                <label class="fw-bold small text-primary">Expiry Date</label>
                                                <input type="date" name="license_expiry" class="form-control border-primary" value="<?= htmlspecialchars($emp_data['license_expiry'] ?? '') ?>" required>
                                            </div>
                                        </div>
                                        <div class="text-end mt-3">
                                            <button type="submit" name="update_employee_license" class="btn btn-success">Save Changes</button>
                                        </div>
                                    </form>

                                    <hr>

                                    <h6 class="mb-3"><i class="bi bi-file-earmark-pdf"></i> Attached Scanned Documents</h6>
                                    <div class="table-responsive">
                                        <table class="table table-sm table-hover">
                                            <thead class="table-light">
                                                <tr>
                                                    <th>Document Type</th>
                                                    <th>Uploaded At</th>
                                                    <th class="text-center">Action</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php if (!empty($modal_docs)): ?>
                                                    <?php foreach ($modal_docs as $doc): ?>
                                                        <tr>
                                                            <td class="align-middle"><?= htmlspecialchars($doc['document_type']); ?></td>
                                                            <td class="align-middle"><?= htmlspecialchars($doc['uploaded_at']); ?></td>
                                                            <td class="text-center">
                                                                <a href="view_document.php?id=<?= $doc['document_id']; ?>" target="_blank" class="btn btn-outline-info btn-sm">View File</a>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                <?php else: ?>
                                                    <tr>
                                                        <td colspan="3" class="text-center text-muted">No files attached to this profile.</td>
                                                    </tr>
                                                <?php endif; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <a href="?profession_filter=<?= $filter ?? 'All' ?>" class="btn btn-secondary">Close</a>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
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
    <script src="../assets/Bootstrap/all.min.js"></script>
    <script src="../assets/Bootstrap/bootstrap.bundle.min.js"></script>
    <script src="../assets/Bootstrap/fontawesome.min.js"></script>
    <script src="../assets/Bootstrap/jq.js"></script>
</body>

</html>