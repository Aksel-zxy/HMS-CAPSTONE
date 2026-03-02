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
                        <a href="../mod3/patient_report.php" class="sidebar-link">Patient Report</a>
                    </li>
                    <li class="sidebar-item">
                        <a href="../mod3/operation_report.php" class="sidebar-link">Laboratory Report</a>
                    </li>
                </ul>
            </li>
            <li class="sidebar-item">
                <a href="#" class="sidebar-link collapsed has-dropdown" data-bs-toggle="collapse" data-bs-target="#equipment"
                    aria-expanded="true" aria-controls="auth">
                     <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-tools" viewBox="0 0 640 640"><!--!Font Awesome Free v7.2.0 by @fontawesome - https://fontawesome.com License - https://fontawesome.com/license/free Copyright 2026 Fonticons, Inc.-->
                        <path d="M259.1 73.5C262.1 58.7 275.2 48 290.4 48L350.2 48C365.4 48 378.5 58.7 381.5 73.5L396 143.5C410.1 149.5 423.3 157.2 435.3 166.3L503.1 143.8C517.5 139 533.3 145 540.9 158.2L570.8 210C578.4 223.2 575.7 239.8 564.3 249.9L511 297.3C511.9 304.7 512.3 312.3 512.3 320C512.3 327.7 511.8 335.3 511 342.7L564.4 390.2C575.8 400.3 578.4 417 570.9 430.1L541 481.9C533.4 495 517.6 501.1 503.2 496.3L435.4 473.8C423.3 482.9 410.1 490.5 396.1 496.6L381.7 566.5C378.6 581.4 365.5 592 350.4 592L290.6 592C275.4 592 262.3 581.3 259.3 566.5L244.9 496.6C230.8 490.6 217.7 482.9 205.6 473.8L137.5 496.3C123.1 501.1 107.3 495.1 99.7 481.9L69.8 430.1C62.2 416.9 64.9 400.3 76.3 390.2L129.7 342.7C128.8 335.3 128.4 327.7 128.4 320C128.4 312.3 128.9 304.7 129.7 297.3L76.3 249.8C64.9 239.7 62.3 223 69.8 209.9L99.7 158.1C107.3 144.9 123.1 138.9 137.5 143.7L205.3 166.2C217.4 157.1 230.6 149.5 244.6 143.4L259.1 73.5zM320.3 400C364.5 399.8 400.2 363.9 400 319.7C399.8 275.5 363.9 239.8 319.7 240C275.5 240.2 239.8 276.1 240 320.3C240.2 364.5 276.1 400.2 320.3 400z"/>
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
                     <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-tools" viewBox="0 0 640 640"><!--!Font Awesome Free v7.2.0 by @fontawesome - https://fontawesome.com License - https://fontawesome.com/license/free Copyright 2026 Fonticons, Inc.-->
                        <path d="M259.1 73.5C262.1 58.7 275.2 48 290.4 48L350.2 48C365.4 48 378.5 58.7 381.5 73.5L396 143.5C410.1 149.5 423.3 157.2 435.3 166.3L503.1 143.8C517.5 139 533.3 145 540.9 158.2L570.8 210C578.4 223.2 575.7 239.8 564.3 249.9L511 297.3C511.9 304.7 512.3 312.3 512.3 320C512.3 327.7 511.8 335.3 511 342.7L564.4 390.2C575.8 400.3 578.4 417 570.9 430.1L541 481.9C533.4 495 517.6 501.1 503.2 496.3L435.4 473.8C423.3 482.9 410.1 490.5 396.1 496.6L381.7 566.5C378.6 581.4 365.5 592 350.4 592L290.6 592C275.4 592 262.3 581.3 259.3 566.5L244.9 496.6C230.8 490.6 217.7 482.9 205.6 473.8L137.5 496.3C123.1 501.1 107.3 495.1 99.7 481.9L69.8 430.1C62.2 416.9 64.9 400.3 76.3 390.2L129.7 342.7C128.8 335.3 128.4 327.7 128.4 320C128.4 312.3 128.9 304.7 129.7 297.3L76.3 249.8C64.9 239.7 62.3 223 69.8 209.9L99.7 158.1C107.3 144.9 123.1 138.9 137.5 143.7L205.3 166.2C217.4 157.1 230.6 149.5 244.6 143.4L259.1 73.5zM320.3 400C364.5 399.8 400.2 363.9 400 319.7C399.8 275.5 363.9 239.8 319.7 240C275.5 240.2 239.8 276.1 240 320.3C240.2 364.5 276.1 400.2 320.3 400z"/>
                    </svg>
                    <span style="font-size: 18px;">Configuration</span>
                </a>
                <ul id="configuration" class="sidebar-dropdown list-unstyled collapse" data-bs-parent="#sidebar">
                    <li class="sidebar-item">
                        <a href="../configuration_page/price.php" class="sidebar-link">Laboratory Price Configuration</a>
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
                    🧪 Sample Processing Status
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
                                <th style="padding:12px; text-align:center;">Patient ID</th>
                                <th style="padding:12px; text-align:center;">Patient Name</th>
                                <th style="padding:12px; text-align:center;">Date | Time</th>
                                <th style="padding:12px; text-align:center;">Test Name</th>
                                <th style="padding:12px; text-align:center;">Status</th>
                                <th style="padding:12px; text-align:center;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $hasAppointments = false;
                            ?>

                            <?php foreach ($allPatients as $p):
                                $counter   = $p['patient_id'];
                                $name      = $p['fname'] . ' ' . $p['lname'];
                                $dateTime  = $p['appointment_date'];
                                $type      = $p['notes'];
                                $status    = $p['status'];
                                $apptID    = $p['appointment_id'];

                                $scheduleID   = null;
                                $scheduleDate = $dateTime ? date('Y-m-d', strtotime($dateTime)) : null;
                                $scheduleTime = $dateTime ? date('H:i:s', strtotime($dateTime)) : null;

                                $schedQuery = $conn->prepare("
        SELECT status, scheduleID, scheduleDate, scheduleTime 
        FROM dl_schedule 
        WHERE appointment_id = ? 
        ORDER BY scheduleID DESC 
        LIMIT 1
    ");
                                $schedQuery->bind_param("i", $apptID);
                                $schedQuery->execute();
                                $schedResult = $schedQuery->get_result();

                                if ($schedRow = $schedResult->fetch_assoc()) {
                                    $status       = $schedRow['status'];
                                    $scheduleID   = $schedRow['scheduleID'];
                                    $scheduleDate = $schedRow['scheduleDate'];
                                    $scheduleTime = $schedRow['scheduleTime'];
                                }
                                $schedQuery->close();

                                
                                if ($status === 'Completed' || $status === 'Scheduled' || $status === 'Cancelled') {
                                    continue;
                                }

                                $hasAppointments = true;
                            ?>
                                <tr onmouseover="this.style.background='#f9fbfd';" onmouseout="this.style.background='';">
                                    <td style="padding:12px; text-align:center;"><?= htmlspecialchars($counter) ?></td>
                                    <td style="padding:12px; text-align:center;"><?= htmlspecialchars($name) ?></td>
                                    <td style="padding:12px; text-align:center;"><?= htmlspecialchars($scheduleDate . ' ' . $scheduleTime) ?></td>
                                    <td style="padding:12px; text-align:center;"><?= htmlspecialchars($type) ?></td>
                                    <td style="padding:12px; text-align:center;">
                                        <?php if ($status === 'Completed'): ?>
                                            <span style="background:#d4edda; color:#155724; padding:4px 12px; border-radius:16px; font-weight:500;">
                                                <?= htmlspecialchars($status) ?>
                                            </span>
                                        <?php elseif ($status === 'Processing'): ?>
                                            <span style="background:#cce5ff; color:#004085; padding:4px 12px; border-radius:16px; font-weight:500;">
                                                <?= htmlspecialchars($status) ?>
                                            </span>
                                        <?php else: ?>
                                            <?= htmlspecialchars($status) ?>
                                        <?php endif; ?>
                                    </td>
                                    <td style="padding:12px; text-align:center;">
                                        <?php if ($status !== 'Completed'): ?>
                                            <button class="btn btn-sm btn-primary edit-btn"
                                                style="padding:6px 12px; border-radius:6px; font-size:13px; background:#0d6efd; border:none; color:#fff; cursor:pointer;"
                                                data-bs-toggle="modal"
                                                data-bs-target="#editScheduleModal"
                                                data-id="<?= $scheduleID ?>"
                                                data-date="<?= $scheduleDate ?>"
                                                data-time="<?= $scheduleTime ?>"
                                                data-status="<?= $status ?>">
                                                Update
                                            </button>

                                            <form method="POST" action="oop2/upd_stats.php" style="display:inline-block; margin-left:6px;" id="cancelForm_<?= $scheduleID ?>">
                                                <input type="hidden" name="scheduleID" value="<?= $scheduleID ?>">
                                                <input type="hidden" name="cancel_reason" id="cancelReasonInput_<?= $scheduleID ?>">
                                                <input type="hidden" name="delete_schedule" value="1">
                                                <button type="button" class="btn btn-danger btn-sm"
                                                    style="padding:6px 12px; border-radius:6px; font-size:13px; cursor:pointer;"
                                                    onclick="askCancelReason(<?= $scheduleID ?>)">Cancel</button>
                                            </form>
                                        <?php else: ?>
                                            <span style="color:gray;">No Actions</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>

                            <?php if (!$hasAppointments): ?>
                                <tr>
                                    <td colspan="6" style="padding:20px; text-align:center; color:gray; font-style:italic;">
                                        No Schedule
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            
            <div class="modal fade" id="editScheduleModal" tabindex="-1" aria-labelledby="editScheduleModalLabel" aria-hidden="true">
                <div class="modal-dialog">
                    <form method="POST" action="oop2/upd_stats.php">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title" id="editScheduleModalLabel">Update Schedule</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">

                                <input type="hidden" name="scheduleID" id="modalScheduleId">

                                <div class="mb-3">
                                    <label for="modalScheduleDate" class="form-label">Schedule Date</label>
                                    <input type="date" class="form-control" id="modalScheduleDate" name="schedule_date" required>
                                </div>

                                <div class="mb-3">
                                    <label for="modalScheduleTime" class="form-label">Schedule Time</label>
                                    <input type="time" class="form-control" id="modalScheduleTime" name="schedule_time" required>
                                </div>

                                <div class="mb-3">
                                    <label for="modalStatus" class="form-label">Status</label>
                                    <select class="form-select" id="modalStatus" name="status" required>
                                        <option value="Completed">Completed</option>
                                        <option value="Processing">Processing</option>
                                    </select>
                                </div>

                                
                                <div class="mb-3" id="cancelReasonBox" style="display:none;">
                                    <label for="cancelReason" class="form-label">Reason for Cancellation</label>
                                    <textarea class="form-control" id="cancelReason" name="cancel_reason" rows="3"></textarea>
                                </div>

                            </div>
                            <div class="modal-footer">
                                <button type="submit" name="update_schedule" class="btn btn-primary">Update</button>
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            
            <script>
                
                document.querySelector(".toggler-btn")?.addEventListener("click", function() {
                    document.querySelector("#sidebar").classList.toggle("collapsed");
                });

                document.querySelectorAll('.edit-btn').forEach(button => {
                    button.addEventListener('click', function() {
                        document.getElementById('modalScheduleId').value = this.dataset.id;
                        document.getElementById('modalScheduleDate').value = this.dataset.date;
                        document.getElementById('modalScheduleTime').value = this.dataset.time;
                        document.getElementById('modalStatus').value = this.dataset.status;
                    });
                });

                function askCancelReason(scheduleID) {
                    const reason = prompt("Please provide a reason for cancellation:");
                    if (reason !== null && reason.trim() !== "") {
                        document.getElementById("cancelReasonInput_" + scheduleID).value = reason;
                        document.querySelector("input[name='scheduleID'][value='" + scheduleID + "']").form.submit();
                    } else {
                        alert("❌ Cancellation aborted. Reason is required.");
                    }
                }
                document.addEventListener("DOMContentLoaded", function() {
                    const searchInput = document.getElementById("searchInput");
                    const tableRows = document.querySelectorAll("tbody tr");

                    searchInput.addEventListener("keyup", function() {
                        let filter = searchInput.value.toLowerCase();

                        tableRows.forEach(row => {
                            let text = row.textContent.toLowerCase();
                            if (text.includes(filter)) {
                                row.style.display = "";
                            } else {
                                row.style.display = "none";
                            }
                        });
                    });
                });
            </script>
            <script src="../assets/Bootstrap/all.min.js"></script>
            <script src="../assets/Bootstrap/bootstrap.bundle.min.js"></script>
            <script src="../assets/Bootstrap/fontawesome.min.js"></script>
            <script src="../assets/Bootstrap/jq.js"></script>
</body>

</html>
