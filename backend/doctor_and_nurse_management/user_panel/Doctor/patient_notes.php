<?php
session_start();
include '../../../../SQL/config.php';

// Authentication check
if (!isset($_SESSION['profession']) || !in_array($_SESSION['profession'], ['Doctor', 'Nurse'])) {
    header('Location: ../../login.php');
    exit();
}

$employee_id = $_SESSION['employee_id'];
$profession = $_SESSION['profession']; // 'Doctor' or 'Nurse'
$patient_id = $_GET['patient_id'] ?? null;

// Fetch Employee Info
$stmt = $conn->prepare("SELECT first_name, last_name, profession FROM hr_employees WHERE employee_id = ?");
$stmt->bind_param("i", $employee_id);
$stmt->execute();
$employee_info = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$patient_id) {
    die("No patient selected.");
}

// Fetch Patient Info
$stmt = $conn->prepare("SELECT fname, lname FROM patientinfo WHERE patient_id = ?");
$stmt->bind_param("i", $patient_id);
$stmt->execute();
$patient_info = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$patient_info) {
    die("Patient not found.");
}

// Handle Form Submission
$success_msg = "";
$error_msg = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_note'])) {
    $interventions = htmlspecialchars(trim($_POST['interventions']));
    $tasks_done = htmlspecialchars(trim($_POST['tasks_done']));
    $patient_status = htmlspecialchars(trim($_POST['patient_status']));

    if (empty($interventions) && empty($tasks_done) && empty($patient_status)) {
        $error_msg = "Please fill in at least one field to submit a note.";
    } else {
        // If it's a Nurse recording it, we can loosely tag the shift here as 'Shift 1/2/3' based on time, or just generic 'Shift' 
        // For simplicity we will log it as the profession (Doctor/Nurse) but the table ENUM allows: 'Doctor', 'Shift 1', 'Shift 2', 'Shift 3'.
        // If nurse, we try to map their shift by hour dynamically:
        $insert_shift = 'Doctor';
        if ($profession === 'Nurse') {
            date_default_timezone_set('Asia/Manila');
            $h = (int)date('G');
            if ($h >= 8 && $h < 16) $insert_shift = 'Shift 1';
            elseif ($h >= 16) $insert_shift = 'Shift 2';
            else $insert_shift = 'Shift 3';
        }

        $insert_stmt = $conn->prepare("INSERT INTO daily_medical_reports (patient_id, employee_id, shift, report_date, interventions, tasks_done, patient_status, created_at) VALUES (?, ?, ?, CURRENT_DATE(), ?, ?, ?, NOW())");
        $insert_stmt->bind_param("iissss", $patient_id, $employee_id, $insert_shift, $interventions, $tasks_done, $patient_status);
        
        if ($insert_stmt->execute()) {
            $success_msg = "Patient note successfully saved! It is now visible to the Admin and other Medical Staff.";
        } else {
            $error_msg = "Error generating report: " . $conn->error;
        }
        $insert_stmt->close();
    }
}

// Fetch Existing Notes for Timeline
$notes_query = "
    SELECT r.*, e.first_name, e.last_name, e.profession 
    FROM daily_medical_reports r
    JOIN hr_employees e ON r.employee_id = e.employee_id
    WHERE r.patient_id = ?
    ORDER BY r.created_at DESC
";
$stmt = $conn->prepare($notes_query);
$stmt->bind_param("i", $patient_id);
$stmt->execute();
$report_history = $stmt->get_result();
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Medical Notes</title>
    <link rel="stylesheet" href="../../assets/CSS/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background-color: #f4f7f6; font-family: 'Inter', sans-serif; }
        .timeline { position: relative; padding: 20px 0; list-style: none; }
        .timeline:before { content: ''; position: absolute; top: 0; bottom: 0; width: 4px; background: #e9ecef; left: 31px; margin: 0; border-radius: 2px; }
        .timeline > li { position: relative; margin-bottom: 25px; min-height: 50px; }
        .timeline > li:before, .timeline > li:after { content: " "; display: table; }
        .timeline > li:after { clear: both; }
        .timeline-badge { color: #fff; width: 50px; height: 50px; line-height: 50px; font-size: 1.4em; text-align: center; position: absolute; top: 16px; left: 8px; border-radius: 50%; z-index: 100; box-shadow: 0 4px 10px rgba(0,0,0,0.1); }
        .timeline-panel { width: calc(100% - 85px); float: right; padding: 20px; position: relative; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); background: #fff; border: 1px solid #f0f0f0; }
        .timeline-panel:before { content: " "; position: absolute; top: 26px; left: -15px; border-width: 15px 15px 15px 0; border-style: solid; border-color: transparent #fff transparent transparent; }
        
        .badge-doctor { background: linear-gradient(135deg, #0d6efd, #0043a8); }
        .badge-nurse { background: linear-gradient(135deg, #198754, #12633d); }
        
        .card-custom { border: none; border-radius: 15px; box-shadow: 0 6px 20px rgba(0,0,0,0.05); }
        .gradient-header { background: linear-gradient(135deg, #0d6efd 0%, #0dcaf0 100%); color: white; border-radius: 15px 15px 0 0; padding: 20px; }
        
        .form-floating label { color: #6c757d; }
        .form-control:focus { border-color: #0d6efd; box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.1); }
    </style>
</head>
<body>

<div class="container py-5">
    
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold mb-0 text-dark"><i class="fas fa-notes-medical text-primary me-2"></i> Patient Notes & Vitals</h2>
            <p class="text-muted mb-0 mt-1">Viewing medical history for <strong><?= htmlspecialchars($patient_info['fname'] . ' ' . $patient_info['lname']) ?></strong></p>
        </div>
        <a href="<?= $profession === 'Nurse' ? '../Nurse/nurse_duty.php' : 'doctor_duty.php' ?>" class="btn btn-outline-secondary rounded-pill px-4 shadow-sm"><i class="fas fa-arrow-left me-2"></i> Back to Duties</a>
    </div>

    <?php if ($success_msg): ?>
        <div class="alert alert-success alert-dismissible fade show shadow-sm" role="alert">
            <i class="fas fa-check-circle me-2"></i> <?= $success_msg ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <?php if ($error_msg): ?>
        <div class="alert alert-danger alert-dismissible fade show shadow-sm" role="alert">
            <i class="fas fa-exclamation-circle me-2"></i> <?= $error_msg ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="row g-4">
        
        <!-- Left Column: Add New Note Form -->
        <div class="col-lg-5">
            <div class="card card-custom h-100 position-sticky" style="top: 20px;">
                <div class="gradient-header text-center">
                    <h5 class="mb-0 fw-bold"><i class="fas fa-pencil-alt me-2"></i> Write New Note</h5>
                    <small class="text-white-50">
                        <?= $profession === 'Doctor' ? 'Dr. ' : 'Nurse ' ?>
                        <?= htmlspecialchars($employee_info['first_name'] . ' ' . $employee_info['last_name']) ?>
                    </small>
                </div>
                <div class="card-body p-4">
                    <form method="POST" action="">
                        <div class="form-floating mb-3">
                            <textarea class="form-control" name="interventions" placeholder="Clinical observations and interventions" style="height: 120px" id="floatingInterventions"></textarea>
                            <label for="floatingInterventions">🩺 Interventions / Observations</label>
                        </div>
                        
                        <div class="form-floating mb-3">
                            <textarea class="form-control" name="tasks_done" placeholder="Tasks completed" style="height: 100px" id="floatingTasks"></textarea>
                            <label for="floatingTasks">✅ Tasks Completed</label>
                        </div>
                        
                        <div class="form-floating mb-4">
                            <input type="text" class="form-control" name="patient_status" placeholder="E.g., Stable, Improving, Critical" id="floatingStatus">
                            <label for="floatingStatus">❤️ Current Status (e.g., Stable, Critical)</label>
                        </div>
                        
                        <button type="submit" name="submit_note" class="btn btn-primary w-100 rounded-pill py-2 shadow fw-bold fs-5">
                            <i class="fas fa-save me-2"></i> Save Note to Record
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Right Column: Timeline History -->
        <div class="col-lg-7">
            <div class="card card-custom p-4 h-100">
                <h5 class="fw-bold mb-4 border-bottom pb-3"><i class="fas fa-history text-secondary me-2"></i> Medical Note History</h5>
                
                <?php if ($report_history && $report_history->num_rows > 0): ?>
                    <ul class="timeline">
                        <?php while ($report = $report_history->fetch_assoc()): 
                            $isDoctor = ($report['profession'] === 'Doctor');
                            $badgeClass = $isDoctor ? 'badge-doctor' : 'badge-nurse';
                            $icon = $isDoctor ? 'fa-user-md' : 'fa-user-nurse';
                            $prefix = $isDoctor ? 'Dr. ' : 'Nurse ';
                        ?>
                            <li>
                                <div class="timeline-badge <?= $badgeClass ?>"><i class="fas <?= $icon ?>"></i></div>
                                <div class="timeline-panel">
                                    <div class="d-flex justify-content-between align-items-center mb-3 border-bottom pb-2">
                                        <h6 class="mb-0 fw-bold text-dark">
                                            <?= htmlspecialchars($prefix . $report['first_name'] . ' ' . $report['last_name']) ?>
                                        </h6>
                                        <small class="text-muted fw-bold">
                                            <i class="far fa-clock me-1"></i> <?= date('M d, Y h:i A', strtotime($report['created_at'])) ?>
                                        </small>
                                    </div>
                                    
                                    <?php if (!empty($report['patient_status'])): ?>
                                        <div class="mb-3">
                                            <span class="badge bg-danger rounded-pill px-3 py-2 shadow-sm border">
                                                Status: <?= htmlspecialchars($report['patient_status']) ?>
                                            </span>
                                        </div>
                                    <?php endif; ?>

                                    <?php if (!empty($report['interventions'])): ?>
                                        <div class="mb-3">
                                            <strong class="text-secondary small text-uppercase">Interventions:</strong>
                                            <p class="mb-0 mt-1 text-dark" style="white-space: pre-wrap;"><?= htmlspecialchars($report['interventions']) ?></p>
                                        </div>
                                    <?php endif; ?>

                                    <?php if (!empty($report['tasks_done'])): ?>
                                        <div>
                                            <strong class="text-secondary small text-uppercase">Tasks Done:</strong>
                                            <p class="mb-0 mt-1 text-dark" style="white-space: pre-wrap;"><?= htmlspecialchars($report['tasks_done']) ?></p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </li>
                        <?php endwhile; ?>
                    </ul>
                <?php else: ?>
                    <div class="text-center py-5">
                        <i class="fas fa-clipboard text-muted" style="font-size: 4rem; opacity: 0.3;"></i>
                        <h5 class="text-muted mt-3">No notes have been recorded for this patient yet.</h5>
                        <p class="text-muted py-2">Use the form on the left to add the first medical note.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
    </div>
</div>

<script src="../../assets/JS/bootstrap.bundle.min.js"></script>
</body>
</html>
