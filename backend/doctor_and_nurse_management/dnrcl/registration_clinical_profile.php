<?php
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

// Get filter value for profession
$filter_profession = $_GET['profession'] ?? '';

// Build WHERE clause for profession filter
$where = "WHERE profession IN ('Doctor', 'Nurse')";
if ($filter_profession !== '') {
    $where .= " AND profession = '" . $conn->real_escape_string($filter_profession) . "'";
}

// Fetch filtered employees
$employees = $conn->query("
    SELECT employee_id, first_name, middle_name, last_name, role, department, specialization, profession, status
    FROM hr_employees
    $where
");

// Handle Create/Update Clinical Profile
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $employee_id = $_POST['employee_id'];
    $clinical_status = $_POST['clinical_status'];
    $preferred_shift = $_POST['preferred_shift'];
    $max_hours = $_POST['max_hours_per_week'];

    // Check if profile exists
    $check = $conn->query("SELECT * FROM clinical_profiles WHERE employee_id = '$employee_id' ");
    if ($check->num_rows > 0) {
        // Update existing profile
        $conn->query("
            UPDATE clinical_profiles 
            SET clinical_status='$clinical_status',
                preferred_shift='$preferred_shift',
                max_hours_per_week='$max_hours',
                last_updated=NOW()
            WHERE employee_id='$employee_id'
        ");
    } else {
        // Insert new profile
        $conn->query("
            INSERT INTO clinical_profiles (employee_id, clinical_status, preferred_shift, max_hours_per_week, onboarding_date, last_updated)
            VALUES ('$employee_id', '$clinical_status', '$preferred_shift', '$max_hours', NOW(), NOW())
        ");
    }

    // After save, redirect to this page (not clinical_profiles_admin.php)
    header("Location: registration_clinical_profile.php?success=1");
    exit;
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
    <link rel="stylesheet" href="../assets/CSS/clinical_profile.css">
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
            <li class="sidebar-item">
                <a href="../repair_request.php" class="sidebar-link collapsed has-dropdown" data-bs-toggle="#" data-bs-target="#request_repair"
                    aria-expanded="true" aria-controls="auth">
                     <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-tools" viewBox="0 0 16 16">
                        <path d="M1 0 0 1l2.2 3.081a1 1 0 0 0 .815.419h.07a1 1 0 0 1 .708.293l2.675 2.675-2.617 2.654A3.003 3.003 0 0 0 0 13a3 3 0 1 0 5.878-.851l2.654-2.617.968.968-.305.914a1 1 0 0 0 .242 1.023l3.27 3.27a.997.997 0 0 0 1.414 0l1.586-1.586a.997.997 0 0 0 0-1.414l-3.27-3.27a1 1 0 0 0-1.023-.242L10.5 9.5l-.96-.96 2.68-2.643A3.005 3.005 0 0 0 16 3c0-.269-.035-.53-.102-.777l-2.14 2.141L12 4l-.364-1.757L13.777.102a3 3 0 0 0-3.675 3.68L7.462 6.46 4.793 3.793a1 1 0 0 1-.293-.707v-.071a1 1 0 0 0-.419-.814L1 0Zm9.646 10.646a.5.5 0 0 1 .708 0l2.914 2.915a.5.5 0 0 1-.707.707l-2.915-2.914a.5.5 0 0 1 0-.708ZM3 11l.471.242.529.026.287.445.445.287.026.529L5 13l-.242.471-.026.529-.445.287-.287.445-.529.026L3 15l-.471-.242L2 14.732l-.287-.445L1.268 14l-.026-.529L1 13l.242-.471.026-.529.445-.287.287-.445.529-.026L3 11Z" />
                    </svg>
                    <span style="font-size: 18px;">Request Repair</span>
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

                <h2 style="font-family:Arial, sans-serif; color:#0d6efd; margin-bottom:20px; border-bottom:2px solid #0d6efd; padding-bottom:8px;">🧑‍💻Doctor & Nurse Clinical Profile Management</h2>
                <?php if (isset($_GET['success'])): ?>
                    <div class="alert alert-success">Profile saved successfully!</div>
                <?php endif; ?>

                <!-- Filter/Search Form for Profession -->
                <form method="GET" class="mb-3 d-flex gap-3 align-items-end">
                    <div>
                        <label for="profession" class="form-label mb-0">Profession:</label>
                        <select name="profession" id="profession" class="form-select">
                            <option value="">All</option>
                            <option value="Doctor" <?= $filter_profession == 'Doctor' ? 'selected' : '' ?>>Doctor</option>
                            <option value="Nurse" <?= $filter_profession == 'Nurse' ? 'selected' : '' ?>>Nurse</option>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary">Filter</button>
                    <?php if ($filter_profession): ?>
                        <a href="registration_clinical_profile.php" class="btn btn-secondary">Reset</a>
                    <?php endif; ?>
                </form>

                <table class="table table-bordered table-striped">
                    <thead>
                        <tr>
                            <th>Employee</th>
                            <th>Role</th>
                            <th>Department</th>
                            <th>Specialization</th>
                            <th>Clinical Status</th>
                            <th>Preferred Shift</th>
                            <th>Max Hours/Week</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = $employees->fetch_assoc()):
                            $empId = $row['employee_id'];
                            $profile = $conn->query("SELECT * FROM clinical_profiles WHERE employee_id='$empId'")->fetch_assoc();
                        ?>
                            <tr>
                                <td><?= $row['first_name'] . " " . $row['last_name']; ?></td>
                                <td><?= $row['role']; ?></td>
                                <td><?= $row['department']; ?></td>
                                <td><?= $row['specialization']; ?></td>
                                <td><?= $profile['clinical_status'] ?? 'Not Registered'; ?></td>
                                <td><?= $profile['preferred_shift'] ?? '-'; ?></td>
                                <td><?= $profile['max_hours_per_week'] ?? '-'; ?></td>
                                <td>
                                    <!-- Button trigger modal -->
                                    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#editModal<?= $empId ?>">Manage</button>
                                </td>
                            </tr>

                            <!-- Modal -->
                            <div class="modal fade" id="editModal<?= $empId ?>" tabindex="-1">
                                <div class="modal-dialog">
                                    <div class="modal-content">
                                        <form method="POST">
                                            <div class="modal-header">
                                                <h5 class="modal-title">Manage Profile - <?= $row['first_name'] . " " . $row['last_name']; ?></h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <div class="modal-body">
                                                <input type="hidden" name="employee_id" value="<?= $empId ?>">

                                                <div class="mb-3">
                                                    <label>Clinical Status</label>
                                                    <select name="clinical_status" class="form-select" required>
                                                        <option value="Active" <?= ($profile['clinical_status'] ?? '') == 'Active' ? 'selected' : '' ?>>Active</option>
                                                        <option value="Inactive" <?= ($profile['clinical_status'] ?? '') == 'Inactive' ? 'selected' : '' ?>>Inactive</option>
                                                        <option value="Suspended" <?= ($profile['clinical_status'] ?? '') == 'Suspended' ? 'selected' : '' ?>>Suspended</option>
                                                    </select>
                                                </div>

                                                <div class="mb-3">
                                                    <label>Preferred Shift</label>
                                                    <select name="preferred_shift" class="form-select">
                                                        <option value="">-- Select --</option>
                                                        <option value="Day" <?= ($profile['preferred_shift'] ?? '') == 'Day' ? 'selected' : '' ?>>Day</option>
                                                        <option value="Night" <?= ($profile['preferred_shift'] ?? '') == 'Night' ? 'selected' : '' ?>>Night</option>
                                                        <option value="Rotating" <?= ($profile['preferred_shift'] ?? '') == 'Rotating' ? 'selected' : '' ?>>Rotating</option>
                                                        <option value="Flexible" <?= ($profile['preferred_shift'] ?? '') == 'Flexible' ? 'selected' : '' ?>>Flexible</option>
                                                    </select>
                                                </div>

                                                <div class="mb-3">
                                                    <label>Max Hours/Week</label>
                                                    <input type="number" name="max_hours_per_week" class="form-control" value="<?= $profile['max_hours_per_week'] ?? '' ?>">
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="submit" class="btn btn-success">Save</button>
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>

                        <?php endwhile; ?>
                    </tbody>
                </table>
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