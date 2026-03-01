<?php
session_start();
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

// Filters
$filter_date = $_GET['filter_date'] ?? date('Y-m-d');
$filter_patient = $_GET['filter_patient'] ?? '';
$filter_shift = $_GET['filter_shift'] ?? '';

// Build Query
$reports_sql = "
    SELECT r.*, 
           p.fname, p.lname, 
           e.first_name, e.last_name, e.profession 
    FROM daily_medical_reports r
    JOIN patientinfo p ON r.patient_id = p.patient_id
    JOIN hr_employees e ON r.employee_id = e.employee_id
    WHERE r.report_date = ?
";
$params = [$filter_date];
$types = 's';

if (!empty($filter_patient)) {
    $reports_sql .= " AND r.patient_id = ?";
    $params[] = $filter_patient;
    $types .= 'i';
}

if (!empty($filter_shift)) {
    $reports_sql .= " AND r.shift = ?";
    $params[] = $filter_shift;
    $types .= 's';
}

$reports_sql .= " ORDER BY r.created_at DESC";

$stmt = $conn->prepare($reports_sql);
if ($types) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$reports_res = $stmt->get_result();

// Get filter dropdown data
$admitted_patients = $conn->query("SELECT patient_id, fname, lname FROM patientinfo");

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HMS | Daily Medical Reports</title>
    <link rel="shortcut icon" href="../assets/image/favicon.ico" type="image/x-icon">
    <link rel="stylesheet" href="../assets/CSS/bootstrap.min.css">
    <link rel="stylesheet" href="../assets/CSS/super.css">
    <link rel="stylesheet" href="../assets/CSS/shift_scheduling.css">
    <style>
        .report-card {
            transition: transform 0.2sease, box-shadow 0.2s ease;
            border: none;
            border-radius: 12px;
            overflow: hidden;
            background: #ffffff;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
        }
        .report-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 15px rgba(0, 0, 0, 0.1);
        }
        .report-header {
            background: linear-gradient(135deg, #0d6efd, #0b5ed7);
            color: #fff;
            padding: 12px 20px;
        }
        .status-badge {
            font-size: 0.8rem;
            padding: 5px 12px;
            border-radius: 20px;
        }
    </style>
</head>

<body>
    <div class="d-flex">
        <!----- Sidebar ----->
        <aside id="sidebar" class="sidebar-toggle">
            <div class="sidebar-logo mt-3">
                <img src="../assets/image/logo-dark.png" width="90px" height="20px">
            </div>
            <div class="menu-title">Navigation</div>
            
            <li class="sidebar-item">
                <a href="../doctor_dashboard.php" class="sidebar-link" data-bs-toggle="#" data-bs-target="#" aria-expanded="false" aria-controls="auth">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-cast" viewBox="0 0 16 16">
                        <path d="m7.646 9.354-3.792 3.792a.5.5 0 0 0 .353.854h7.586a.5.5 0 0 0 .354-.854L8.354 9.354a.5.5 0 0 0-.708 0" />
                        <path d="M11.414 11H14.5a.5.5 0 0 0 .5-.5v-7a.5.5 0 0 0-.5-.5h-13a.5.5 0 0 0-.5.5v7a.5.5 0 0 0 .5.5h3.086l-1 1H1.5A1.5 1.5 0 0 1 0 10.5v-7A1.5 1.5 0 0 1 1.5 2h13A1.5 1.5 0 0 1 16 3.5v7a1.5 1.5 0 0 1-1.5 1.5h-2.086z" />
                    </svg>
                    <span style="font-size: 18px;">Dashboard</span>
                </a>
            </li>

            <li class="sidebar-item">
                <a href="#" class="sidebar-link collapsed has-dropdown" data-bs-toggle="collapse" data-bs-target="#schedule" aria-expanded="true" aria-controls="auth">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-cast" viewBox="0 0 640 512">
                        <path d="M320 16a104 104 0 1 1 0 208 104 104 0 1 1 0-208zM96 88a72 72 0 1 1 0 144 72 72 0 1 1 0-144zM0 416c0-70.7 57.3-128 128-128 12.8 0 25.2 1.9 36.9 5.4-32.9 36.8-52.9 85.4-52.9 138.6l0 16c0 11.4 2.4 22.2 6.7 32L32 480c-17.7 0-32-14.3-32-32l0-32zm521.3 64c4.3-9.8 6.7-20.6 6.7-32l0-16c0-53.2-20-101.8-52.9-138.6 11.7-3.5 24.1-5.4 36.9-5.4 70.7 0 128 57.3 128 128l0 32c0 17.7-14.3 32-32 32l-86.7 0zM472 160a72 72 0 1 1 144 0 72 72 0 1 1 -144 0zM160 432c0-88.4 71.6-160 160-160s160 71.6 160 160l0 16c0 17.7-14.3 32-32 32l-256 0c-17.7 0-32-14.3-32-32l0-16z" />
                    </svg>
                    <span style="font-size: 18px;">Scheduling Shifts and Duties</span>
                </a>
                <ul id="schedule" class="sidebar-dropdown list-unstyled collapse show" data-bs-parent="#sidebar">
                    <li class="sidebar-item"><a href="doctor_shift_scheduling.php" class="sidebar-link">Doctor Shift Scheduling</a></li>
                    <li class="sidebar-item"><a href="nurse_shift_scheduling.php" class="sidebar-link">Nurse Shift Scheduling</a></li>
                    <li class="sidebar-item"><a href="duty_assignment.php" class="sidebar-link">Duty Assignment</a></li>
                    <li class="sidebar-item"><a href="schedule_calendar.php" class="sidebar-link">Schedule Calendar</a></li>
                    <li class="sidebar-item"><a href="daily_reports_viewer.php" class="sidebar-link current">Daily Reports Viewer</a></li>
                </ul>
            </li>

            <!-- Additional sidebar omitted for brevity but standard logic applied -->
        </aside>
        <!----- End of Sidebar ----->

        <!----- Main Content ----->
        <div class="main">
            <div class="topbar">
                <div class="toggle">
                    <button class="toggler-btn" type="button">
                        <svg xmlns="http://www.w3.org/2000/svg" width="30px" height="30px" fill="currentColor" class="bi bi-list-ul" viewBox="0 0 16 16">
                            <path fill-rule="evenodd" d="M5 11.5a.5.5 0 0 1 .5-.5h9a.5.5 0 0 1 0 1h-9a.5.5 0 0 1-.5-.5m0-4a.5.5 0 0 1 .5-.5h9a.5.5 0 0 1 0 1h-9a.5.5 0 0 1-.5-.5m0-4a.5.5 0 0 1 .5-.5h9a.5.5 0 0 1 0 1h-9a.5.5 0 0 1-.5-.5m-3 1a1 1 0 1 0 0-2 1 1 0 0 0 0 2m0 4a1 1 0 1 0 0-2 1 1 0 0 0 0 2m0 4a1 1 0 1 0 0-2 1 1 0 0 0 0 2" />
                        </svg>
                    </button>
                </div>
                <div class="logo">
                     <div class="dropdown d-flex align-items-center">
                        <span class="username ml-1 me-2"><?php echo htmlspecialchars($user['fname'] . ' ' . $user['lname']); ?></span>
                        <button class="btn dropdown-toggle" type="button" id="dropdownMenuButton" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-person-circle"></i>
                        </button>
                        <ul class="dropdown-menu" aria-labelledby="dropdownMenuButton">
                            <li><a class="dropdown-item" href="../../logout.php">Logout</a></li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- Page Content -->
            <div class="container-fluid p-4">
                <div class="d-flex justify-content-between align-items-center mb-4 border-bottom border-primary pb-2">
                    <h2 class="text-primary fw-bold mb-0">📊 Daily Medical Reports</h2>
                </div>

                <!-- Filters -->
                <div class="card shadow-sm border-0 rounded-4 mb-4">
                    <div class="card-body bg-light rounded-4">
                        <form method="GET" class="row g-3 align-items-end">
                            <div class="col-md-3">
                                <label class="form-label text-muted fw-bold small">Report Date</label>
                                <input type="date" class="form-control" name="filter_date" value="<?= htmlspecialchars($filter_date) ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label text-muted fw-bold small">Patient</label>
                                <select class="form-select" name="filter_patient">
                                    <option value="">All Patients</option>
                                    <?php if ($admitted_patients): ?>
                                        <?php while($p = $admitted_patients->fetch_assoc()): ?>
                                            <option value="<?= $p['patient_id'] ?>" <?= $filter_patient == $p['patient_id'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($p['fname'] . ' ' . $p['lname']) ?>
                                            </option>
                                        <?php endwhile; ?>
                                    <?php endif; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label text-muted fw-bold small">Shift</label>
                                <select class="form-select" name="filter_shift">
                                    <option value="">All Shifts</option>
                                    <option value="Doctor" <?= $filter_shift == 'Doctor' ? 'selected' : '' ?>>Doctor</option>
                                    <option value="Shift 1" <?= $filter_shift == 'Shift 1' ? 'selected' : '' ?>>Shift 1 (08:00 AM - 04:00 PM)</option>
                                    <option value="Shift 2" <?= $filter_shift == 'Shift 2' ? 'selected' : '' ?>>Shift 2 (04:00 PM - 12:00 AM)</option>
                                    <option value="Shift 3" <?= $filter_shift == 'Shift 3' ? 'selected' : '' ?>>Shift 3 (12:00 AM - 08:00 AM)</option>
                                </select>
                            </div>
                            <div class="col-md-3 text-end">
                                <button type="submit" class="btn btn-primary px-4 fw-bold rounded-pill w-100">Filter Reports</button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Reports Grid -->
                <div class="row">
                    <?php if ($reports_res && $reports_res->num_rows > 0): ?>
                        <?php while ($report = $reports_res->fetch_assoc()): 
                            
                            $status_color = 'bg-secondary';
                            $status_text = strtolower($report['patient_status']);
                            if (strpos($status_text, 'stable') !== false || strpos($status_text, 'improving') !== false) {
                                $status_color = 'bg-success';
                            } elseif (strpos($status_text, 'critical') !== false || strpos($status_text, 'worse') !== false) {
                                $status_color = 'bg-danger';
                            } elseif (strpos($status_text, 'monitoring') !== false || strpos($status_text, 'discomfort') !== false) {
                                $status_color = 'bg-warning text-dark';
                            }
                        ?>
                        <div class="col-lg-6 mb-4">
                            <div class="card report-card h-100">
                                <div class="report-header d-flex justify-content-between align-items-center">
                                    <div>
                                        <h5 class="mb-0 fw-bold">Patient: <?= htmlspecialchars($report['fname'] . ' ' . $report['lname']) ?></h5>
                                        <small class="text-light opacity-75">Date: <?= date('M d, Y', strtotime($report['report_date'])) ?></small>
                                    </div>
                                    <span class="badge status-badge <?= $status_color ?>"><?= htmlspecialchars($report['patient_status']) ?></span>
                                </div>
                                <div class="card-body p-4">
                                    <div class="row mb-3">
                                        <div class="col-6">
                                            <p class="text-muted small mb-1 fw-bold">Reported By</p>
                                            <p class="mb-0 text-dark">
                                                <?= $report['profession'] === 'Doctor' ? 'Dr. ' : '' ?>
                                                <?= htmlspecialchars($report['first_name'] . ' ' . $report['last_name']) ?>
                                                <span class="badge bg-light text-primary border ms-1"><?= htmlspecialchars($report['shift']) ?></span>
                                            </p>
                                        </div>
                                        <div class="col-6 text-end">
                                            <p class="text-muted small mb-1 fw-bold">Logged At</p>
                                            <p class="mb-0 text-dark"><?= date('h:i A', strtotime($report['created_at'])) ?></p>
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <p class="text-primary small mb-1 fw-bold border-bottom border-primary pb-1 d-inline-block">Interventions & Observations</p>
                                        <div class="bg-light p-3 rounded-3" style="font-size: 0.9rem;">
                                            <?= nl2br(htmlspecialchars($report['interventions'])) ?>
                                        </div>
                                    </div>

                                    <div>
                                        <p class="text-success small mb-1 fw-bold border-bottom border-success pb-1 d-inline-block">Tasks Completed</p>
                                        <div class="bg-light p-3 rounded-3" style="font-size: 0.9rem;">
                                            <?= nl2br(htmlspecialchars($report['tasks_done'])) ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="col-12 py-5 text-center">
                            <div class="text-muted fs-4 mb-2">📋</div>
                            <h5 class="text-muted">No reports found for the selected filters.</h5>
                        </div>
                    <?php endif; ?>
                </div>

            </div>
        </div>
    </div>

    <script src="../assets/Bootstrap/all.min.js"></script>
    <script src="../assets/Bootstrap/bootstrap.bundle.min.js"></script>
    <script src="../assets/Bootstrap/jq.js"></script>
    <script>
        document.querySelector(".toggler-btn").addEventListener("click", function() {
            document.querySelector("#sidebar").classList.toggle("collapsed");
        });
    </script>
</body>
</html>
