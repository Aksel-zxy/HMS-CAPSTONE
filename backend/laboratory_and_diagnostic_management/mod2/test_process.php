<?php
session_start();
include '../../../SQL/config.php';
require_once "oop2/upd_stats.php";
require_once "../mod1/oop/fetchdetails.php";
if (!isset($_SESSION['labtech']) || $_SESSION['labtech'] !== true) {
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
$patient = new Patient($conn);
$allPatients = $patient->getAllPatients();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HMS | Laboratory and Diagnostic Management</title>
    <link rel="shortcut icon" href="../assets/image/favicon.ico" type="image/x-icon">
    <link rel="stylesheet" href="../assets/CSS/bootstrap.min.css">
    <link rel="stylesheet" href="../assets/CSS/super.css">
</head>

<body>
    <div class="d-flex">
        
        <aside id="sidebar" class="sidebar-toggle">
            <div class="sidebar-logo mt-3">
                <img src="../assets/image/logo-dark.png" width="90px" height="20px">
            </div>
            <div class="menu-title">Navigation</div>
            
            <li class="sidebar-item">
                <a href="../labtech_dashboard.php" class="sidebar-link" data-bs-toggle="#" data-bs-target="#"
                    aria-expanded="false" aria-controls="auth">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-cast" viewBox="0 0 16 16">
                        <path d="m7.646 9.354-3.792 3.792a.5.5 0 0 0 .353.854h7.586a.5.5 0 0 0 .354-.854L8.354 9.354a.5.5 0 0 0-.708 0" />
                        <path d="M11.414 11H14.5a.5.5 0 0 0 .5-.5v-7a.5.5 0 0 0-.5-.5h-13a.5.5 0 0 0-.5.5v7a.5.5 0 0 0 .5.5h3.086l-1 1H1.5A1.5 1.5 0 0 1 0 10.5v-7A1.5 1.5 0 0 1 1.5 2h13A1.5 1.5 0 0 1 16 3.5v7a1.5 1.5 0 0 1-1.5 1.5h-2.086z" />
                    </svg>
                    <span style="font-size: 18px;">Dashboard</span>
                </a>
            </li>
            <li class="sidebar-item">
                <a href="#" class="sidebar-link collapsed has-dropdown" data-bs-toggle="collapse" data-bs-target="#labtech"
                    aria-expanded="true" aria-controls="auth">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-building"
                        viewBox="0 0 16 16" style="margin-bottom: 7px;">
                        <path
                            d="M4 2.5a.5.5 0
             0 1 .5-.5h1a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5h-1a.5.5 0 0 1-.5-.5zm3 0a.5.5 0 0 1 .5-.5h1a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5h-1a.5.5 0 0 1-.5-.5zm3.5-.5a.5.5 0 0 0-.5.5v1a.5.5 0 0 0 .5.5h1a.5.5 0 0 0 .5-.5v-1a.5.5 0 0 0-.5-.5zM4 5.5a.5.5 0 0 1 .5-.5h1a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5h-1a.5.5 0 0 1-.5-.5zM7.5 5a.5.5 0 0 0-.5.5v1a.5.5 0 0 0 .5.5h1a.5.5 0 0 0 .5-.5v-1a.5.5 0 0 0-.5-.5zm2.5.5a.5.5 0 0 1 .5-.5h1a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5h-1a.5.5 0 0 1-.5-.5zM4.5 8a.5.5 0 0 0-.5.5v1a.5.5 0 0 0 .5.5h1a.5.5 0 0 0 .5-.5v-1a.5.5 0 0 0-.5-.5zm2.5.5a.5.5 0 0 1 .5-.5h1a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5h-1a.5.5 0 0 1-.5-.5zm3.5-.5a.5.5 0 0 0-.5.5v1a.5.5 0 0 0 .5.5h1a.5.5 0 0 0 .5-.5v-1a.5.5 0 0 0-.5-.5z" />
                        <path
                            d="M2 1a1 1 0 0 1 1-1h10a1 1 0 0 1 1 1v14a1 1 0 0 1-1 1H3a1 1 0 0 1-1-1zm11 0H3v14h3v-2.5a.5.5 0 0 1 .5-.5h3a.5.5 0 0 1 .5.5V15h3z" />
                    </svg>
                    <span style="font-size: 18px;">Test Booking and Scheduling</span>
                </a>

                <ul id="labtech" class="sidebar-dropdown list-unstyled collapse" data-bs-parent="#sidebar">
                    <li class="sidebar-item">
                        <a href="../mod1/doctor_referral.php" class="sidebar-link">Doctor Referral</a>
                    </li>
                    <li class="sidebar-item">
                        <a href="../mod1/cas.php" class="sidebar-link">Calendar & Appointment Slot</a>
                    </li>
                    <li class="sidebar-item">
                        <a href="../mod1/room_available.php" class="sidebar-link">Room Overview</a>
                    </li>
                </ul>
            </li>
            <li class="sidebar-item">
                <a href="#" class="sidebar-link collapsed has-dropdown" data-bs-toggle="collapse" data-bs-target="#sample"
                    aria-expanded="true" aria-controls="auth">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-collection" viewBox="0 0 16 16">
                        <path d="M2.5 3.5a.5.5 0 0 1 0-1h11a.5.5 0 0 1 0 1h-11zm2-2a.5.5 0 0 1 0-1h7a.5.5 0 0 1 0 1h-7zM0 13a1.5 1.5 0 0 0 1.5 1.5h13A1.5 1.5 0 0 0 16 13V6a1.5 1.5 0 0 0-1.5-1.5h-13A1.5 1.5 0 0 0 0 6v7zm1.5.5A.5.5 0 0 1 1 13V6a.5.5 0 0 1 .5-.5h13a.5.5 0 0 1 .5.5v7a.5.5 0 0 1-.5.5h-13z" />
                    </svg>
                    <span style="font-size: 18px;">Sample Collection & Tracking</span>
                </a>
                <ul id="sample" class="sidebar-dropdown list-unstyled collapse" data-bs-parent="#sidebar">
                    <li class="sidebar-item">
                        <a href="test_process.php" class="sidebar-link">Sample Process</a>
                    </li>
                    <li class="sidebar-item">
                        <a href="sps.php" class="sidebar-link">Sample Processing Status</a>
                    </li>
                    <li class="sidebar-item">
                        <a href="audit.php" class="sidebar-link">Audit Trail</a>
                    </li>
                </ul>
            </li>
            <li class="sidebar-item">
                <a href="#" class="sidebar-link collapsed has-dropdown" data-bs-toggle="collapse" data-bs-target="#report"
                    aria-expanded="true" aria-controls="auth">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-file-earmark-text" viewBox="0 0 16 16">
                        <path d="M5.5 7a.5.5 0 0 0 0 1h5a.5.5 0 0 0 0-1h-5zM5 9.5a.5.5 0 0 1 .5-.5h5a.5.5 0 0 1 0 1h-5a.5.5 0 0 1-.5-.5zm0 2a.5.5 0 0 1 .5-.5h2a.5.5 0 0 1 0 1h-2a.5.5 0 0 1-.5-.5z" />
                        <path d="M9.5 0H4a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2V4.5L9.5 0zm0 1v2A1.5 1.5 0 0 0 11 4.5h2V14a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1h5.5z" />
                    </svg>
                    <span style="font-size: 18px;">Report Generation & Delivery</span>
                </a>
                <ul id="report" class="sidebar-dropdown list-unstyled collapse" data-bs-parent="#sidebar">
                    <li class="sidebar-item">
                        <a href="../mod3/results.php" class="sidebar-link">Test Results</a>
                    </li>
                    <li class="sidebar-item">
                        <a href="../mod3/result_deliveries.php" class="sidebar-link">Result Deliveries</a>
                    </li>
                    <li class="sidebar-item">
                        <a href="../mod3/operation_report.php" class="sidebar-link">Laboratory Report</a>
                    </li>
                </ul>
            </li>
            <li class="sidebar-item">
                <a href="#" class="sidebar-link collapsed has-dropdown" data-bs-toggle="collapse" data-bs-target="#equipment"
                    aria-expanded="true" aria-controls="auth">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-tools" viewBox="0 0 16 16">
                        <path d="M1 0 0 1l2.2 3.081a1 1 0 0 0 .815.419h.07a1 1 0 0 1 .708.293l2.675 2.675-2.617 2.654A3.003 3.003 0 0 0 0 13a3 3 0 1 0 5.878-.851l2.654-2.617.968.968-.305.914a1 1 0 0 0 .242 1.023l3.27 3.27a.997.997 0 0 0 1.414 0l1.586-1.586a.997.997 0 0 0 0-1.414l-3.27-3.27a1 1 0 0 0-1.023-.242L10.5 9.5l-.96-.96 2.68-2.643A3.005 3.005 0 0 0 16 3c0-.269-.035-.53-.102-.777l-2.14 2.141L12 4l-.364-1.757L13.777.102a3 3 0 0 0-3.675 3.68L7.462 6.46 4.793 3.793a1 1 0 0 1-.293-.707v-.071a1 1 0 0 0-.419-.814L1 0Zm9.646 10.646a.5.5 0 0 1 .708 0l2.914 2.915a.5.5 0 0 1-.707.707l-2.915-2.914a.5.5 0 0 1 0-.708ZM3 11l.471.242.529.026.287.445.445.287.026.529L5 13l-.242.471-.026.529-.445.287-.287.445-.529.026L3 15l-.471-.242L2 14.732l-.287-.445L1.268 14l-.026-.529L1 13l.242-.471.026-.529.445-.287.287-.445.529-.026L3 11Z" />
                    </svg>
                    <span style="font-size: 18px;">Equipment Maintenance</span>
                </a>
                <ul id="equipment" class="sidebar-dropdown list-unstyled collapse" data-bs-parent="#sidebar">
                    <li class="sidebar-item">
                        <a href="../mod4/lab_equip.php" class="sidebar-link">Laboratory Equipment </a>
                    </li>
                    <li class="sidebar-item">
                        <a href="../mod4/maintenance.php" class="sidebar-link">Maintenance Schedule</a>
                    </li>
                </ul>
            </li>
            <li class="sidebar-item">
                <a href="../configuration_page/price.php" class="sidebar-link collapsed has-dropdown" data-bs-toggle="collapse" data-bs-target="#configuration"
                    aria-expanded="true" aria-controls="auth">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-tools" viewBox="0 0 16 16">
                        <path d="M1 0 0 1l2.2 3.081a1 1 0 0 0 .815.419h.07a1 1 0 0 1 .708.293l2.675 2.675-2.617 2.654A3.003 3.003 0 0 0 0 13a3 3 0 1 0 5.878-.851l2.654-2.617.968.968-.305.914a1 1 0 0 0 .242 1.023l3.27 3.27a.997.997 0 0 0 1.414 0l1.586-1.586a.997.997 0 0 0 0-1.414l-3.27-3.27a1 1 0 0 0-1.023-.242L10.5 9.5l-.96-.96 2.68-2.643A3.005 3.005 0 0 0 16 3c0-.269-.035-.53-.102-.777l-2.14 2.141L12 4l-.364-1.757L13.777.102a3 3 0 0 0-3.675 3.68L7.462 6.46 4.793 3.793a1 1 0 0 1-.293-.707v-.071a1 1 0 0 0-.419-.814L1 0Zm9.646 10.646a.5.5 0 0 1 .708 0l2.914 2.915a.5.5 0 0 1-.707.707l-2.915-2.914a.5.5 0 0 1 0-.708ZM3 11l.471.242.529.026.287.445.445.287.026.529L5 13l-.242.471-.026.529-.445.287-.287.445-.529.026L3 15l-.471-.242L2 14.732l-.287-.445L1.268 14l-.026-.529L1 13l.242-.471.026-.529.445-.287.287-.445.529-.026L3 11Z" />
                    </svg>
                    <span style="font-size: 18px;">Configuration</span>
                </a>
                <ul id="configuration" class="sidebar-dropdown list-unstyled collapse" data-bs-parent="#sidebar">
                    <li class="sidebar-item">
                        <a href="../configuration_page/price.php" class="sidebar-link">Laboratory Price Configuration</a>
                    </li>
                </ul>
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
                        <span class="username ml-1 me-2"><?php echo $user['fname']; ?> <?php echo $user['lname']; ?></span>
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
            
            <div style="width:95%; margin:20px auto; padding:15px; background:#f8f9fa; border-radius:10px; box-shadow:0 2px 6px rgba(0,0,0,0.08);">
                <h2 style="font-family:Arial, sans-serif; color:#0d6efd; margin-bottom:20px; border-bottom:2px solid #0d6efd; padding-bottom:8px;">
                    🧾 Sample Processing
                </h2>
                <div class="col-md-3 mb-3">
                    <input type="text" id="searchInput" class="form-control"
                        style="width:300px; border-radius:20px; padding:8px 15px;"
                        placeholder="🔍 Search patient, test, or status...">
                </div>
                <div style="height:700px; overflow-y:auto; border-radius:8px; box-shadow: inset 0 0 5px rgba(0,0,0,0.05);">
                    <table style="width:100%; border-collapse:collapse; font-family:Arial, sans-serif; font-size:14px; background:#fff;">
                        <thead style="background:#f1f5f9; border-bottom:2px solid #dee2e6; text-align:left; position:sticky; top:0; z-index:1;">
                            <tr>
                                <th style="padding:12px;text-align:center;">Patient ID</th>
                                <th style="padding:12px;text-align:center;">Patient Name</th>
                                <th style="padding:12px;text-align:center;">Test Names</th>
                                <th style="padding:12px;text-align:center;">Status</th>
                                <th style="padding:12px;text-align:center;">Completed Time</th>
                                <th style="padding:12px;text-align:center;">Result</th>
                                <th style="padding:12px;text-align:center;">AI Impression</th>
                                <th style="padding:12px;text-align:center;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $patientsGrouped = [];
                            foreach ($allPatients as $p) {
                                $pid   = $p['patient_id'];
                                $name  = $p['fname'] . ' ' . $p['lname'];
                                $apptId = $p['appointment_id'];

                                $schedQuery = $conn->prepare("
                                    SELECT s.status, s.scheduleID, s.scheduleDate, s.scheduleTime, s.serviceName, s.completed_at
                                    FROM dl_schedule s
                                    WHERE s.appointment_id = ?
                                    AND NOT EXISTS (
                                        SELECT 1 
                                        FROM dl_result_schedules rs
                                        WHERE rs.scheduleID = s.scheduleID
                                    )
                                    ORDER BY s.scheduleID ASC
                                ");
                                $schedQuery->bind_param("i", $apptId);
                                $schedQuery->execute();
                                $schedResult = $schedQuery->get_result();

                                while ($schedRow = $schedResult->fetch_assoc()) {
                                    if ($schedRow['status'] === 'Scheduled' || $schedRow['status'] === 'Cancelled') continue;

                                    $patientsGrouped[$pid]['name'] = $name;
                                    $patientsGrouped[$pid]['tests'][] = [
                                        'scheduleID'   => $schedRow['scheduleID'],
                                        'date'         => $schedRow['scheduleDate'],
                                        'time'         => $schedRow['scheduleTime'],
                                        'serviceName'  => $schedRow['serviceName'],
                                        'status'       => $schedRow['status'],
                                        'completed_at' => $schedRow['completed_at'],
                                    ];
                                }
                                $schedQuery->close();
                            }

                            if (empty($patientsGrouped)) {
                                echo "<tr><td colspan='8' style='padding:20px; text-align:center; color:gray; font-style:italic;'>No Schedule</td></tr>";
                            } else {
                                foreach ($patientsGrouped as $pid => $info):
                                    $tests = $info['tests'];
                                    $statuses = array_column($tests, 'status');
                                    $allCompleted = count(array_unique($statuses)) === 1 && $statuses[0] === 'Completed';
                                    $testNames = array_column($tests, 'serviceName');
                                    $testList = implode(", ", $testNames);
                                    $completedTimes = array_filter(array_column($tests, 'completed_at'));

                                    if (!empty($completedTimes)) {
                                        $latestCompleted = max($completedTimes);
                                        $dateTimeDisplay = date("Y-m-d | H:i", strtotime($latestCompleted));
                                    } else {
                                        $dateTimeDisplay = "N/A";
                                    }

                                    
                                    $aiImpression = $allCompleted
                                        ? "AI Impression: All results appear stable."
                                        : "AI Impression: Awaiting completion of tests.";
                            ?>
                                    <tr onmouseover="this.style.background='#f9fbfd';" onmouseout="this.style.background='';">
                                        <td style="padding:12px;text-align:center;"><?= htmlspecialchars($pid) ?></td>
                                        <td style="padding:12px;text-align:center;"><?= htmlspecialchars($info['name']) ?></td>
                                        <td style="padding:12px;text-align:center;"><?= htmlspecialchars($testList) ?></td>
                                        <td style="padding:12px;text-align:center;">
                                            <?php if ($allCompleted): ?>
                                                <span style="background:#d4edda; color:#155724; padding:4px 12px; border-radius:16px; font-weight:500;">
                                                    Completed
                                                </span>
                                            <?php else: ?>
                                                <span style="background:#cce5ff; color:#004085; padding:4px 12px; border-radius:16px; font-weight:500;">
                                                    Progressing
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td style="padding:12px;text-align:center;"><?= htmlspecialchars($dateTimeDisplay) ?></td>
                                        <td style="padding:12px; text-align:center;">
                                            <button class="btn btn-success view-result-btn"
                                                data-bs-toggle="modal"
                                                data-bs-target="#viewResultModal"
                                                data-results='<?= json_encode($tests) ?>'>
                                                View Result
                                            </button>
                                        </td>
                                        <td style="padding:12px; text-align:center;">
                                            <button class="btn btn-warning ai-impression-btn"
                                                data-bs-toggle="modal"
                                                data-bs-target="#aiImpressionModal"
                                                data-impression="<?= htmlspecialchars($aiImpression) ?>">
                                                View Impression
                                            </button>
                                        </td>
                                        <td style="padding:12px; text-align:center;">
                                            <?php if ($allCompleted): ?>
                                                
                                                <button class="btn btn-primary add-remarks-btn"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#remarksModal"
                                                    data-patientid="<?= htmlspecialchars($pid) ?>"
                                                    data-scheduleids='<?= json_encode(array_column($tests, "scheduleID")) ?>'
                                                    data-testlist="<?= htmlspecialchars($testList) ?>">
                                                    Save Result
                                                </button>
                                            <?php else: ?>
                                                <span style="color:gray; font-style:italic; margin-left:8px;">
                                                    Still Processing...
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                            <?php endforeach;
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <div class="modal fade" id="viewResultModal" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header bg-success text-white">
                            <h5 class="modal-title" id="modalTitle">Laboratory Result</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body" id="resultContent">
                            <p class="text-center text-muted">Loading result...</p>
                        </div>
                        <div class="modal-footer d-flex justify-content-between">
                            <button id="prevResult" class="btn btn-outline-success">⟨ Prev</button>
                            <button id="nextResult" class="btn btn-outline-success">Next ⟩</button>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal fade" id="aiImpressionModal" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered modal-lg">
                    <div class="modal-content">
                        <div class="modal-header" style="background: linear-gradient(90deg, #4facfe, #00f2fe); color: #fff;">
                            <h5 class="modal-title">AI Impression</h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body" id="aiImpressionText" style="background:#f0f8ff; padding:20px; border-radius:8px; font-family: 'Arial', sans-serif; font-size:14px; color:#333;">
                            Loading...
                        </div>
                        <div class="modal-footer d-flex justify-content-end">
                            <button type="button" class="btn btn-outline-primary" data-bs-dismiss="modal">Close</button>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal fade" id="remarksModal" tabindex="-1" aria-labelledby="remarksModalLabel" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content">
                        <form action="save_result.php" method="POST">
                            <div class="modal-header">
                                <h5 class="modal-title" id="remarksModalLabel">Add Remarks Before Saving</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <input type="hidden" name="patientID" id="remarksPatientID">
                                <input type="hidden" name="scheduleIDs" id="remarksScheduleIDs">
                                <input type="hidden" name="result" id="remarksTestList">
                                <input type="hidden" name="status" value="Completed">

                                <div class="mb-3">
                                    <label for="remarksText" class="form-label">Remarks</label>
                                    <textarea name="remarks" id="remarksText" class="form-control" rows="4" placeholder="Enter remarks..."></textarea>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                <button type="submit" class="btn btn-primary">Save Result</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        
        <script src="../assets/javascript/test_process.js"></script>
        <script src="../assets/Bootstrap/all.min.js"></script>
        <script src="../assets/Bootstrap/bootstrap.bundle.min.js"></script>
        <script src="../assets/Bootstrap/fontawesome.min.js"></script>
        <script src="../assets/Bootstrap/jq.js"></script>
</body>

</html>