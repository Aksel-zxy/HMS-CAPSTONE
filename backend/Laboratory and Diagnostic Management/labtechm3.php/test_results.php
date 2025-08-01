<?php
session_start();
include '../../../SQL/config.php';
require_once 'testresult_class.php';
require_once '../labtechm1.php/appointment_class.php';

if (!isset($_SESSION['labtech']) || $_SESSION['labtech'] !== true) {
    header('Location: login.php'); // Redirect to login if not logged in
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


// main content
$labdiagnostic_testresults = new labdiagnostic_testresults($conn);

// Get all test‑results for listing
$testResults = $labdiagnostic_testresults->getAll()->get_result()->fetch_all(MYSQLI_ASSOC);

// Get dropdown data
$appointment = $conn->query("SELECT appointment_id FROM labdiagnostic_appointments
ORDER BY appointment_id ASC")->fetch_all(MYSQLI_ASSOC);
$staff = $conn->query("SELECT staff_id, name FROM staff ORDER BY name")->fetch_all(MYSQLI_ASSOC);

// handle form submission
$message = '';
$error   = '';
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    unset($_SESSION['message']);
}
if (isset($_SESSION['error'])) {
    $error = $_SESSION['error'];
    unset($_SESSION['error']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (isset($_POST['create'])) {

        $labdiagnostic_testresults->appointment_id = $_POST['appointment_id'];
        $labdiagnostic_testresults->performed_by = $_POST['performed_by'];
        $labdiagnostic_testresults->verified_by = $_POST['verified_by'];
        $labdiagnostic_testresults->result_date = date('Y-m-d H:i:s');
        $labdiagnostic_testresults->status = $_POST['status'];
        $labdiagnostic_testresults->conclusion = $_POST['conclusion'];

        if ($labdiagnostic_testresults->create()) {
            $_SESSION['message'] = "Test result created successfully.";
            // refresh list
            $testResults = $labdiagnostic_testresults->getAll()->get_result()->fetch_all(MYSQLI_ASSOC);
        } else {
            $_SESSION['error'] = "Unable to create test result.";
        }
        header('Location: test_results.php?result_id=' . urlencode($result_id));
        exit();
    }

    elseif (isset($_POST['update'])) {

        $labdiagnostic_testresults->result_id = $_POST['result_id'];
        $labdiagnostic_testresults->status = $_POST['status'];
        $labdiagnostic_testresults->conclusion = $_POST['conclusion'];
        $labdiagnostic_testresults->performed_by = $_POST['performed_by'] ?? null;
        $labdiagnostic_testresults->verified_by = $_POST['verified_by'] ?? null;
        $labdiagnostic_testresults->verification_date = $_POST['verification_date'] ?? null;

        if ($labdiagnostic_testresults->update()) {
            $_SESSION['message'] = "Test result updated successfully.";
            $testResults = $labdiagnostic_testresults->getAll()->get_result()->fetch_all(MYSQLI_ASSOC);
        } else {
            $_SESSION['error'] = "Unable to update test result.";
        }
        header('Location: test_results.php?result_id=' . urlencode($result_id));
        exit();
    }

    elseif (isset($_POST['delete'])) {
        $labdiagnostic_testresults->result_id = $_POST['result_id'];

        if ($labdiagnostic_testresults->delete()) {
            $message = "Test result deleted successfully.";
            $testResults = $labdiagnostic_testresults->getAll()->get_result()->fetch_all(MYSQLI_ASSOC);
        } else {
            $error = "Unable to delete test result.";
        }
    }
}

// Get all appointments for listing (if not already refreshed above)
if (!isset($testresults)) {
    $testresults = $labdiagnostic_testresults->getAll()->get_result()->fetch_all(MYSQLI_ASSOC);
}

// dashboard stats
$statusResult = $labdiagnostic_testresults->getCountByStatus()->get_result();
$statusStats = [
    'pending'   => 0,
    'completed' => 0,
    'verified'  => 0,
    'rejected'  => 0
];

if ($statusResult) {
foreach ($statusResult->fetch_all(MYSQLI_ASSOC) as $row) {
    $statusStats[$row['status']] = $row['count'];
}
$statusResult->free();
}

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
    <link rel="stylesheet" href="../assets/CSS/appointment.css">
    
</head>
<style>
    </style>
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
                        <a href="../test.php" class="sidebar-link">Test Available</a>
                    </li>
                    <li class="sidebar-item">
                        <a href="../../Laboratory and Diagnostic Management/labtechm1.php/appointment.php" class="sidebar-link">Appointment</a>
                    </li>
                </ul>
            </li>

<!-- Module 2: Sample Collection & Tracking -->
<li class="sidebar-item">
    <a href="#" class="sidebar-link collapsed has-dropdown" data-bs-toggle="collapse" data-bs-target="#sample"
        aria-expanded="true" aria-controls="auth">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-collection" viewBox="0 0 16 16">
            <path d="M2.5 3.5a.5.5 0 0 1 0-1h11a.5.5 0 0 1 0 1h-11zm2-2a.5.5 0 0 1 0-1h7a.5.5 0 0 1 0 1h-7zM0 13a1.5 1.5 0 0 0 1.5 1.5h13A1.5 1.5 0 0 0 16 13V6a1.5 1.5 0 0 0-1.5-1.5h-13A1.5 1.5 0 0 0 0 6v7zm1.5.5A.5.5 0 0 1 1 13V6a.5.5 0 0 1 .5-.5h13a.5.5 0 0 1 .5.5v7a.5.5 0 0 1-.5.5h-13z"/>
        </svg>
        <span style="font-size: 18px;">Sample Collection & Tracking</span>
    </a>
    <ul id="sample" class="sidebar-dropdown list-unstyled collapse" data-bs-parent="#sidebar">
        <li class="sidebar-item">
            <a href="../Sample/collection_log.php" class="sidebar-link">Collection Log</a>
        </li>
        <li class="sidebar-item">
            <a href="../Sample/sample_tracking.php" class="sidebar-link">Sample Tracking</a>
        </li>
        <li class="sidebar-item">
            <a href="../Sample/collection_team.php" class="sidebar-link">Collection Team</a>
        </li>
    </ul>
</li>

<!-- Module 3: Report Generation & Delivery -->
<li class="sidebar-item">
    <a href="#" class="sidebar-link collapsed has-dropdown" data-bs-toggle="collapse" data-bs-target="#report"
        aria-expanded="true" aria-controls="auth">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-file-earmark-text" viewBox="0 0 16 16">
            <path d="M5.5 7a.5.5 0 0 0 0 1h5a.5.5 0 0 0 0-1h-5zM5 9.5a.5.5 0 0 1 .5-.5h5a.5.5 0 0 1 0 1h-5a.5.5 0 0 1-.5-.5zm0 2a.5.5 0 0 1 .5-.5h2a.5.5 0 0 1 0 1h-2a.5.5 0 0 1-.5-.5z"/>
            <path d="M9.5 0H4a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2V4.5L9.5 0zm0 1v2A1.5 1.5 0 0 0 11 4.5h2V14a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1h5.5z"/>
        </svg>
        <span style="font-size: 18px;">Report Generation & Delivery</span>
    </a>
    <ul id="report" class="sidebar-dropdown list-unstyled collapse" data-bs-parent="#sidebar">
        <li class="sidebar-item">
            <a href="../../Laboratory and Diagnostic Management/labtechm3.php/test_results.php" class="sidebar-link">Test Results</a>
        </li>
        <li class="sidebar-item">
            <a href="../../Laboratory and Diagnostic Management/labtechm3.php/result_items.php" class="sidebar-link">Result Items</a>
        </li>
        <li class="sidebar-item">
            <a href="../../Laboratory and Diagnostic Management/labtechm3.php/report_deliveries.php" class="sidebar-link">Delivery Methods</a>
        </li>
    </ul>
</li>

<!-- Module 4: Equipment Maintenance -->
<li class="sidebar-item">
    <a href="#" class="sidebar-link collapsed has-dropdown" data-bs-toggle="collapse" data-bs-target="#equipment"
        aria-expanded="true" aria-controls="auth">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-tools" viewBox="0 0 16 16">
            <path d="M1 0 0 1l2.2 3.081a1 1 0 0 0 .815.419h.07a1 1 0 0 1 .708.293l2.675 2.675-2.617 2.654A3.003 3.003 0 0 0 0 13a3 3 0 1 0 5.878-.851l2.654-2.617.968.968-.305.914a1 1 0 0 0 .242 1.023l3.27 3.27a.997.997 0 0 0 1.414 0l1.586-1.586a.997.997 0 0 0 0-1.414l-3.27-3.27a1 1 0 0 0-1.023-.242L10.5 9.5l-.96-.96 2.68-2.643A3.005 3.005 0 0 0 16 3c0-.269-.035-.53-.102-.777l-2.14 2.141L12 4l-.364-1.757L13.777.102a3 3 0 0 0-3.675 3.68L7.462 6.46 4.793 3.793a1 1 0 0 1-.293-.707v-.071a1 1 0 0 0-.419-.814L1 0Zm9.646 10.646a.5.5 0 0 1 .708 0l2.914 2.915a.5.5 0 0 1-.707.707l-2.915-2.914a.5.5 0 0 1 0-.708ZM3 11l.471.242.529.026.287.445.445.287.026.529L5 13l-.242.471-.026.529-.445.287-.287.445-.529.026L3 15l-.471-.242L2 14.732l-.287-.445L1.268 14l-.026-.529L1 13l.242-.471.026-.529.445-.287.287-.445.529-.026L3 11Z"/>
        </svg>
        <span style="font-size: 18px;">Equipment Maintenance</span>
    </a>
    <ul id="equipment" class="sidebar-dropdown list-unstyled collapse" data-bs-parent="#sidebar">
        <li class="sidebar-item">
            <a href="../Equipment/equipment_list.php" class="sidebar-link">Equipment Inventory</a>
        </li>
        <li class="sidebar-item">
            <a href="../Equipment/maintenance_schedule.php" class="sidebar-link">Maintenance Schedule</a>
        </li>
        <li class="sidebar-item">
            <a href="../Equipment/service_log.php" class="sidebar-link">Service Log</a>
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
                    </div>
                </div>
            </div>
            <!-- START CODING HERE -->
            <div class="container">
                <h1>Test Results</h1>

            <?php if (!empty($message)): ?>
                <div class="alert success"><?php echo $message; ?></div>
            <?php endif; ?>
                
            <?php if (!empty($error)): ?>
                <div class="alert error"><?php echo $error; ?></div>
            <?php endif; ?>

            <div class="dashboard-ngani">
                <div class="stats-container">

                    <div class="stat-card">
                        <h3>Pending</h3>
                        <div class="stat-value"><?php echo $statusStats['pending']  ?? 0; ?> </div>
                    </div>

                    <div class="stat-card">
                        <h3>Completed</h3>
                        <div class="stat-value"><?php echo $statusStats['completed']  ?? 0; ?> </div>
                    </div>

                    <div class="stat-card">
                        <h3>Verified</h3>
                        <div class="stat-value"><?php echo $statusStats['verified']  ?? 0; ?> </div>
                    </div>

                    <div class="stat-card">
                        <h3>Rejected</h3>
                        <div class="stat-value"><?php echo $statusStats['rejected']  ?? 0; ?> </div>
                    </div>
             </div>
       </div>

       <div class="tabs">
            <button class="tab-btn active" data-tab="list">View Results</button>
            <button class="tab-btn" data-tab="new">New Results</button>
       </div>

       <div class="tab-content">
        <!--View Results-->
        <div class="tab-pane active" id="list">
            <div class="card">
                <h2>Test Results List</h2>
                    <div class="table-responsive">
                        <table id="resultTable">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Patient</th>
                                    <th>Test</th>
                                    <th>Performed by</th>
                                    <th>Result date</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                        <tbody>
                        <!--backend of table here-->
                        <?php if (!empty($testResults)): ?>
                            <?php foreach ($testResults as $row): ?>
                        <tr data-result-id="<?php echo htmlspecialchars($row['result_id']); ?>"
                            data-appointment-id="<?php echo htmlspecialchars($row['appointment_id'] ?? ''); ?>"
                            data-performed-by-id="<?php echo htmlspecialchars($row['performed_by'] ?? ''); ?>"
                            data-verified-by-id="<?php echo htmlspecialchars($row['verified_by'] ?? ''); ?>"
                            data-patient-name="<?php echo htmlspecialchars($row['patient_name'] ?? ''); ?>"
                            data-test-name="<?php echo htmlspecialchars($row['test_name'] ?? ''); ?>"
                            data-performer-name="<?php echo htmlspecialchars($row['performer_name'] ?? ''); ?>"
                            data-result-date="<?php echo date('Y-m-d H:i:s', strtotime($row['result_date'])); ?>"
                            data-verification-date="<?php echo htmlspecialchars($row['verification_date'] ?? ''); ?>"
                            data-status="<?php echo htmlspecialchars($row['status'] ?? ''); ?>"
                            data-conclusion="<?php echo htmlspecialchars($row['conclusion'] ?? ''); ?>">
   
                            <td><?php echo $row['result_id']; ?></td>    
                            <td><?php echo $row['patient_name'] ?? 'Unknown'; ?></td>
                            <td><?php echo $row['test_name'] ?? 'Unknown'; ?></td>
                            <td><?php echo $row['performer_name'] ?? 'N/A'; ?></td>
                            <td><?php echo date('M d, Y h:i A', strtotime($row['result_date'])); ?></td>
                            <td>
                            <span class="status-badge status-<?php echo $row['status']; ?>">
                                <?php echo ucfirst($row['status']); ?>
                            </span>
                            </td>
                            <td>
                            <button class="btn-view" data-id="<?php echo $row['result_id']; ?>">View</button>
                            <button class="btn-edit" data-id="<?php echo $row['result_id']; ?>">Edit</button>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="result_id" value="<?php echo $row['result_id']; ?>">
                                <button type="submit" name="delete" class="btn-delete" onclick="return confirm('Are you sure you want to delete this test result?')">Delete</button>
                            </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php else: ?>
                        <tr>
                            <td colspan="7" style="text-align: center;">No test results found.</td>
                        </tr>
                        <?php endif; ?>
       
                        </tbody>
                    </table>
                </div> 
            </div>
        </div>
    </div>

        <!--new/edit form result tab-->
        <div class="tab-pane" id="new">
            <div class="card">
                <h2>Add New Test Result</h2>
                <form id="testresultForm" method="POST">
                    <input type="hidden" id="result_id" name="result_id">

            <div class="form-group">
                <label for="appointment_id">Appointment:</label>
                <select id="appointment_id" name="appointment_id" required>
                    <option value="">Select Appointment</option>
                    <?php foreach ($appointment as $appointment): ?>
                    <option value="<?php echo $appointment['appointment_id']; ?>">
                    <?php echo $appointment['appointment_id']; ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="performed_by">Performed By:</label>
                <select id="performed_by" name="performed_by" required>
                    <option value="">Select Staff</option>
                    <?php foreach ($staff as $s): ?>
                    <option value="<?php echo $s['staff_id']; ?>">
                    <?php echo $s['name']; ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="verified_by">verified By:</label>
                <select id="verified_by" name="verified_by">
                    <option value="">Select Staff</option>
                    <?php foreach ($staff as $v): ?>
                    <option value="<?php echo $v['staff_id']; ?>">
                    <?php echo $v['name']; ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="status">Status:</label>
                <select id="status" name="status" required>
                    <option value="pending">Pending</option>
                    <option value="completed">Completed</option>
                    <option value="verified">Verified</option>
                    <option value="rejected">Rejected</option>
                </select>
            </div>

            <div class="form-group">
                <label for="conclusion">Conclusion:</label>
                <textarea id="conclusion" name="conclusion" rows="2"></textarea>
            </div>

            <div class="form-buttons">
                <button type="submit" name="create" id="createBtn">Add Test Result</button>
                <button type="submit" name="update" id="updateBtn" style="display:none;">Update Test Result</button>
                <button type="button" id="cancelBtn" style="display:none;">Cancel</button>
            </div>
        </form>
    </div>
</div>
 
        <!--viewing modal-->
        <div id="resultModal" class="modal">
            <div class="modal-content">
                <span class="close">&times;</span>
                <h2>Test Result Details</h2>
                <div id="resultDetails">
                    <!--loaded here-->
                </div>
            </div>
        </div>
    </div>
    <script src="../assets/Bootstrap/testResult.js"></script>


  <!----- End of Main Content ----->

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