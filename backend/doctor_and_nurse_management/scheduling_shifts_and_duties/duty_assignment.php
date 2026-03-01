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

// Fetch all duties and group them by staff_id
$duties_query = "SELECT * FROM duty_assignments ORDER BY created_at DESC";
$all_duties_res = $conn->query($duties_query);
$duties_by_staff = [];

while ($d = $all_duties_res->fetch_assoc()) {
    if (!empty($d['doctor_id'])) {
        $duty = $d;
        $duty['shift_time'] = 'Attending Doctor';
        $duties_by_staff[$d['doctor_id']][] = $duty;
    }
    if (!empty($d['shift1_nurse_id'])) {
        $duty = $d;
        $duty['shift_time'] = '08:00 AM – 04:00 PM';
        $duties_by_staff[$d['shift1_nurse_id']][] = $duty;
    }
    if (!empty($d['shift2_nurse_id'])) {
        $duty = $d;
        $duty['shift_time'] = '04:00 PM – 12:00 AM';
        $duties_by_staff[$d['shift2_nurse_id']][] = $duty;
    }
    if (!empty($d['shift3_nurse_id'])) {
        $duty = $d;
        $duty['shift_time'] = '12:00 AM – 08:00 AM';
        $duties_by_staff[$d['shift3_nurse_id']][] = $duty;
    }
    // Legacy support for older generic assignments
    if (!empty($d['nurse_assistant']) && $d['nurse_assistant'] != $d['shift1_nurse_id'] && $d['nurse_assistant'] != $d['shift2_nurse_id'] && $d['nurse_assistant'] != $d['shift3_nurse_id']) {
        $duty = $d;
        $duty['shift_time'] = 'Assigned Nurse';
        $duties_by_staff[$d['nurse_assistant']][] = $duty;
    }
}

// Fetch Doctors
$query = "SELECT e.employee_id, e.first_name, e.last_name, e.profession FROM hr_employees e 
          WHERE e.profession IN ('Doctor', 'Nurse')";
$doctors_result = $conn->query($query);

// Fetch details for the assignment modal
$admitted_patients = $conn->query("SELECT patient_id, fname, lname FROM patientinfo");
$modal_doctors = $conn->query("SELECT employee_id, first_name, last_name FROM hr_employees WHERE profession = 'Doctor'");
$modal_nurses_res = $conn->query("SELECT employee_id, first_name, last_name FROM hr_employees WHERE profession = 'Nurse'");
$nurse_list = [];
if ($modal_nurses_res) {
    while($n = $modal_nurses_res->fetch_assoc()) $nurse_list[] = $n;
}

// Fetch available beds
$available_beds = $conn->query("SELECT bed_id, bed_number, ward, room_number FROM p_beds WHERE status = 'Available'");

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assign_duty'])) {
    $p_id = $_POST['patient_id'];
    $d_id = $_POST['doctor_id'];
    $n1_id = $_POST['shift1_nurse_id'];
    $n2_id = $_POST['shift2_nurse_id'];
    $n3_id = $_POST['shift3_nurse_id'];
    $bed_id = !empty($_POST['bed_id']) ? $_POST['bed_id'] : 0;
    $procedure = !empty($_POST['procedure']) ? $_POST['procedure'] : 'General Care';

    $insert_qry = "INSERT INTO duty_assignments 
        (patient_id, doctor_id, shift1_nurse_id, shift2_nurse_id, shift3_nurse_id, bed_id, `procedure`, status, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, 'Pending', NOW())";
    
    $ins_stmt = $conn->prepare($insert_qry);
    $ins_stmt->bind_param("iiiiiis", $p_id, $d_id, $n1_id, $n2_id, $n3_id, $bed_id, $procedure);
    
    if ($ins_stmt->execute()) {
        echo "<script>alert('Duty assigned successfully!'); window.location.href='duty_assignment.php';</script>";
        exit;
    } else {
        echo "<script>alert('Error assigning duty: " . $conn->error . "');</script>";
    }
}

// Handle Duty Re-assignment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reassign_duty'])) {
    $duty_id = $_POST['duty_id'];
    $d_id = $_POST['doctor_id'];
    $n1_id = $_POST['shift1_nurse_id'];
    $n2_id = $_POST['shift2_nurse_id'];
    $n3_id = $_POST['shift3_nurse_id'];

    $upd_qry = "UPDATE duty_assignments 
                SET doctor_id = ?, shift1_nurse_id = ?, shift2_nurse_id = ?, shift3_nurse_id = ? 
                WHERE duty_id = ?";
    
    $upd_stmt = $conn->prepare($upd_qry);
    $upd_stmt->bind_param("iiiii", $d_id, $n1_id, $n2_id, $n3_id, $duty_id);
    
    if ($upd_stmt->execute()) {
        echo "<script>alert('Duty re-assigned successfully!'); window.location.href='duty_assignment.php';</script>";
        exit;
    } else {
        echo "<script>alert('Error re-assigning duty: " . $conn->error . "');</script>";
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
                <div class="d-flex justify-content-between align-items-center mb-4 border-bottom border-primary pb-2">
                    <h2 class="text-primary mb-0" style="font-family:Arial, sans-serif;">
                        👨‍⚕️ Medical Staff Duty Overview
                    </h2>
                    <button class="btn btn-success rounded-pill px-4 shadow-sm fw-bold" data-bs-toggle="modal" data-bs-target="#assignDutyModal">
                        <i class="fas fa-plus-circle me-2"></i> Assign Duty
                    </button>
                </div>

                <div class="row" id="doctorContainer">
                    <?php while ($doc = $doctors_result->fetch_assoc()):
                        $doc_id = $doc['employee_id'];
                        $my_duties = isset($duties_by_staff[$doc_id]) ? $duties_by_staff[$doc_id] : [];
                    ?>
                        <div class="col-md-4 mb-4 doctor-card" data-name="<?php echo strtolower($doc['first_name'] . ' ' . $doc['last_name']); ?>">
                            <div class="card shadow-sm h-100 text-center p-3" style="border-radius:15px; border:none;">
                                <div class="mb-2">
                                    <img src="https://ui-avatars.com/api/?name=<?php echo $doc['first_name'] . '+' . $doc['last_name']; ?>&background=0d6efd&color=fff"
                                        class="rounded-circle" width="60">
                                </div>
                                <h5 class="mb-0">
                                    <?php
                                    $prefix = ($doc['profession'] === 'Doctor') ? 'Dr. ' : '';
                                    echo $prefix . htmlspecialchars($doc['first_name'] . " " . $doc['last_name']);
                                    ?>
                                </h5>
                                <p class="text-muted small"><?php echo htmlspecialchars($doc['profession']); ?></p>
                                <div class="badge bg-light text-primary border mb-3">
                                    <?php echo count($my_duties); ?> Active Duties
                                </div>
                                <button class="btn btn-primary w-100 rounded-pill view-duties-btn"
                                    data-bs-toggle="modal"
                                    data-bs-target="#dutyModal"
                                    data-staff-name="<?php echo htmlspecialchars(($doc['profession'] === 'Doctor' ? 'Dr. ' : 'Nurse ') . $doc['first_name'] . ' ' . $doc['last_name']); ?>"
                                    data-duties='<?php echo htmlspecialchars(json_encode($my_duties), ENT_QUOTES, 'UTF-8'); ?>'>
                                    View Duties
                                </button>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            </div>
            <div class="modal fade" id="dutyModal" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-lg modal-dialog-centered">
                    <div class="modal-content" style="border-radius:15px; border:none;">
                        <div class="modal-header bg-primary text-white" style="border-radius:15px 15px 0 0;">
                            <h5 class="modal-title" id="modalDoctorName">Doctor's Duties</h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body" id="modalDutyList">
                            <div class="text-center p-4">
                                <p class="text-muted">Loading assigned duties...</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Assign Duty Modal -->
            <div class="modal fade" id="assignDutyModal" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content" style="border-radius:15px; border:none; box-shadow: 0 10px 30px rgba(0,0,0,0.1);">
                        <div class="modal-header bg-success text-white" style="border-radius:15px 15px 0 0;">
                            <h5 class="modal-title fw-bold"><i class="fas fa-user-md me-2"></i>Assign In-Patient Duty</h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <form method="POST" action="">
                            <div class="modal-body p-4 text-start">
                                <input type="hidden" name="assign_duty" value="1">
                                
                                <div class="mb-3">
                                    <label class="form-label text-muted small fw-bold"><i class="fas fa-bed me-1"></i>Select Admitted Patient</label>
                                    <select class="form-select" name="patient_id" required>
                                        <option value="" disabled selected>-- Choose Patient --</option>
                                        <?php if ($admitted_patients): ?>
                                            <?php while($p = $admitted_patients->fetch_assoc()): ?>
                                                <option value="<?= $p['patient_id'] ?>"><?= htmlspecialchars($p['fname'] . ' ' . $p['lname']) ?></option>
                                            <?php endwhile; ?>
                                        <?php endif; ?>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label text-muted small fw-bold"><i class="fas fa-user-nurse me-1"></i>Select Attending Doctor</label>
                                    <select class="form-select" name="doctor_id" required>
                                        <option value="" disabled selected>-- Choose Doctor --</option>
                                        <?php if ($modal_doctors): ?>
                                            <?php while($d = $modal_doctors->fetch_assoc()): ?>
                                                <option value="<?= $d['employee_id'] ?>">Dr. <?= htmlspecialchars($d['first_name'] . ' ' . $d['last_name']) ?></option>
                                            <?php endwhile; ?>
                                        <?php endif; ?>
                                    </select>
                                </div>
                                
                                <h6 class="text-success mt-4 mb-3 border-bottom pb-2 fw-bold"><i class="fas fa-clock me-2"></i>Nurse Shift Assignments</h6>
                                
                                <div class="mb-3">
                                    <label class="form-label text-muted small fw-bold text-primary">Shift 1 (08:00 AM – 04:00 PM)</label>
                                    <select class="form-select border-primary" name="shift1_nurse_id" required>
                                        <option value="" disabled selected>-- Select Nurse --</option>
                                        <?php foreach($nurse_list as $n): ?>
                                            <option value="<?= $n['employee_id'] ?>"><?= htmlspecialchars($n['first_name'] . ' ' . $n['last_name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label text-muted small fw-bold text-warning">Shift 2 (04:00 PM – 12:00 AM)</label>
                                    <select class="form-select border-warning" name="shift2_nurse_id" required>
                                        <option value="" disabled selected>-- Select Nurse --</option>
                                        <?php foreach($nurse_list as $n): ?>
                                            <option value="<?= $n['employee_id'] ?>"><?= htmlspecialchars($n['first_name'] . ' ' . $n['last_name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label text-muted small fw-bold text-info">Shift 3 (12:00 AM – 08:00 AM)</label>
                                    <select class="form-select border-info" name="shift3_nurse_id" required>
                                        <option value="" disabled selected>-- Select Nurse --</option>
                                        <?php foreach($nurse_list as $n): ?>
                                            <option value="<?= $n['employee_id'] ?>"><?= htmlspecialchars($n['first_name'] . ' ' . $n['last_name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label text-muted small fw-bold"><i class="fas fa-bed me-1"></i>Select Bed (Optional)</label>
                                        <select class="form-select" name="bed_id">
                                            <option value="" selected>-- No Bed Assigned --</option>
                                            <?php if ($available_beds): ?>
                                                <?php while($bed = $available_beds->fetch_assoc()): ?>
                                                    <option value="<?= $bed['bed_id'] ?>">Bed <?= htmlspecialchars($bed['bed_number']) ?> (<?= htmlspecialchars($bed['ward'] . ' - ' . $bed['room_number']) ?>)</option>
                                                <?php endwhile; ?>
                                            <?php endif; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label text-muted small fw-bold">Procedure focus (Optional)</label>
                                        <input type="text" class="form-control" name="procedure" placeholder="e.g. General Care">
                                    </div>
                                </div>
                            </div>
                            <div class="modal-footer bg-light" style="border-radius:0 0 15px 15px;">
                                <button type="button" class="btn btn-secondary rounded-pill" data-bs-dismiss="modal">Cancel</button>
                                <button type="submit" class="btn btn-success fw-bold rounded-pill px-4">Save Assignment</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Re-assign Duty Modal -->
            <div class="modal fade" id="reassignDutyModal" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content" style="border-radius:15px; border:none; box-shadow: 0 10px 30px rgba(0,0,0,0.1);">
                        <div class="modal-header bg-warning text-dark" style="border-radius:15px 15px 0 0;">
                            <h5 class="modal-title fw-bold"><i class="fas fa-edit me-2"></i>Edit / Re-assign Duty</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <form method="POST" action="">
                            <div class="modal-body p-4 text-start">
                                <input type="hidden" name="reassign_duty" value="1">
                                <input type="hidden" name="duty_id" id="reassign_duty_id" value="">
                                
                                <div class="mb-3">
                                    <label class="form-label text-muted small fw-bold"><i class="fas fa-user-nurse me-1"></i>Update Attending Doctor</label>
                                    <select class="form-select" name="doctor_id" id="reassign_doctor_id" required>
                                        <option value="" disabled>-- Choose Doctor --</option>
                                        <?php if ($modal_doctors): ?>
                                            <?php $modal_doctors->data_seek(0); while($d = $modal_doctors->fetch_assoc()): ?>
                                                <option value="<?= $d['employee_id'] ?>">Dr. <?= htmlspecialchars($d['first_name'] . ' ' . $d['last_name']) ?></option>
                                            <?php endwhile; ?>
                                        <?php endif; ?>
                                    </select>
                                </div>
                                
                                <h6 class="text-success mt-4 mb-3 border-bottom pb-2 fw-bold"><i class="fas fa-clock me-2"></i>Update Nurse Shift Assignments</h6>
                                
                                <div class="mb-3">
                                    <label class="form-label text-muted small fw-bold text-primary">Shift 1 (08:00 AM – 04:00 PM)</label>
                                    <select class="form-select border-primary" name="shift1_nurse_id" id="reassign_shift1" required>
                                        <option value="" disabled>-- Select Nurse --</option>
                                        <?php foreach($nurse_list as $n): ?>
                                            <option value="<?= $n['employee_id'] ?>"><?= htmlspecialchars($n['first_name'] . ' ' . $n['last_name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label text-muted small fw-bold text-warning">Shift 2 (04:00 PM – 12:00 AM)</label>
                                    <select class="form-select border-warning" name="shift2_nurse_id" id="reassign_shift2" required>
                                        <option value="" disabled>-- Select Nurse --</option>
                                        <?php foreach($nurse_list as $n): ?>
                                            <option value="<?= $n['employee_id'] ?>"><?= htmlspecialchars($n['first_name'] . ' ' . $n['last_name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label text-muted small fw-bold text-info">Shift 3 (12:00 AM – 08:00 AM)</label>
                                    <select class="form-select border-info" name="shift3_nurse_id" id="reassign_shift3" required>
                                        <option value="" disabled>-- Select Nurse --</option>
                                        <?php foreach($nurse_list as $n): ?>
                                            <option value="<?= $n['employee_id'] ?>"><?= htmlspecialchars($n['first_name'] . ' ' . $n['last_name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="modal-footer bg-light" style="border-radius:0 0 15px 15px;">
                                <button type="button" class="btn btn-secondary rounded-pill" data-bs-dismiss="modal">Cancel</button>
                                <button type="submit" class="btn btn-warning fw-bold rounded-pill px-4 text-dark">Save Changes</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            <!-- END CODING HERE -->
            <!----- End of Main Content ----->
        </div>
        <script>
            const toggler = document.querySelector(".toggler-btn");
            toggler.addEventListener("click", function() {
                document.querySelector("#sidebar").classList.toggle("collapsed");
            });

            // FIX: Check if the element exists before adding the listener
            const searchInput = document.getElementById('dutySearchInput');
            if (searchInput) {
                searchInput.addEventListener('keyup', function() {
                    let filter = this.value.toLowerCase();
                    let rows = document.querySelectorAll('tbody tr');
                    rows.forEach(row => {
                        let text = row.innerText.toLowerCase();
                        row.style.display = text.includes(filter) ? '' : 'none';
                    });
                });
            }

            document.addEventListener('DOMContentLoaded', function() {
                const dutyModal = document.getElementById('dutyModal');

                // Added a safety check here as well
                if (dutyModal) {
                    dutyModal.addEventListener('show.bs.modal', function(event) {
                        const button = event.relatedTarget;
                        const staffName = button.getAttribute('data-staff-name');
                        const dutiesJSON = button.getAttribute('data-duties');

                        const titleElement = document.getElementById('modalDoctorName');
                        const bodyElement = document.getElementById('modalDutyList');

                        titleElement.textContent = "Assignments: " + staffName;

                        try {
                            const duties = JSON.parse(dutiesJSON);

                            if (!duties || duties.length === 0) {
                                bodyElement.innerHTML = '<div class="alert alert-info text-center">No active duties assigned.</div>';
                                return;
                            }

                            let html = `
                        <table class="table table-hover align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>Procedure</th>
                                    <th>Shift schedule</th>
                                    <th>Bed</th>
                                    <th>Status</th>
                                    <th>Date</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>`;

                            duties.forEach(duty => {
                                const statusBadge = duty.status === 'Pending' ? 'bg-warning text-dark' : 'bg-success';
                                // Handle potential nulls in date
                                const dateObj = duty.created_at ? new Date(duty.created_at) : new Date();
                                const shiftLabel = duty.shift_time ? duty.shift_time : 'N/A';

                                html += `
                            <tr>
                                <td><strong>${duty.procedure}</strong><br><small class="text-muted">${duty.notes || ''}</small></td>
                                <td><span class="badge bg-info text-dark">${shiftLabel}</span></td>
                                <td><span class="badge bg-secondary">Bed ${duty.bed_id}</span></td>
                                <td><span class="badge ${statusBadge}">${duty.status}</span></td>
                                <td>${dateObj.toLocaleDateString()}</td>
                                <td>
                                    <button class="btn btn-warning btn-sm" onclick="openReassignModal(${duty.duty_id}, ${duty.doctor_id || 0}, ${duty.shift1_nurse_id || 0}, ${duty.shift2_nurse_id || 0}, ${duty.shift3_nurse_id || 0})">
                                        <i class="fas fa-edit"></i> Edit
                                    </button>
                                </td>
                            </tr>`;
                            });

                            html += `</tbody></table>`;
                            bodyElement.innerHTML = html;

                        } catch (e) {
                            console.error("JSON Error:", e);
                            bodyElement.innerHTML = '<div class="alert alert-danger">Error: Could not load data. Check console (F12).</div>';
                        }
                    });
                }
            });

            function openReassignModal(dutyId, doctorId, shift1Id, shift2Id, shift3Id) {
                // Close the current modal
                const currentModal = bootstrap.Modal.getInstance(document.getElementById('dutyModal'));
                if (currentModal) {
                    currentModal.hide();
                }

                // Pre-fill values
                document.getElementById('reassign_duty_id').value = dutyId;
                
                if (doctorId > 0) document.getElementById('reassign_doctor_id').value = doctorId;
                if (shift1Id > 0) document.getElementById('reassign_shift1').value = shift1Id;
                if (shift2Id > 0) document.getElementById('reassign_shift2').value = shift2Id;
                if (shift3Id > 0) document.getElementById('reassign_shift3').value = shift3Id;

                // Show new modal
                const reassignModal = new bootstrap.Modal(document.getElementById('reassignDutyModal'));
                reassignModal.show();
            }
        </script>
        <script src="../assets/Bootstrap/all.min.js"></script>
        <script src="../assets/Bootstrap/bootstrap.bundle.min.js"></script>
        <script src="../assets/Bootstrap/fontawesome.min.js"></script>
        <script src="../assets/Bootstrap/jq.js"></script>
</body>

</html>