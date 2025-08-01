<?php
session_start();
include '../../../SQL/config.php';
require_once 'report_deliveries_class.php';
require_once 'resultitems_class.php';


//log in 
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



//main content
$labdiagnostic_reportdeliveries = new labdiagnostic_reportdeliveries($conn);

// Get all test‑results for listing
$reportDeliveries = $labdiagnostic_reportdeliveries->getAll()->get_result()->fetch_all(MYSQLI_ASSOC);

//get dropdown
$resultItems = $conn->query("SELECT item_id FROM labdiagnostic_resultitems
ORDER BY item_id ASC")->fetch_all(MYSQLI_ASSOC);
$testResults = $conn->query("SELECT result_id FROM labdiagnostic_testresults
ORDER BY result_id  ASC")->fetch_all(MYSQLI_ASSOC);

// Handle form submission
$message = '';
$error = '';
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    unset($_SESSION['message']);
}
if (isset($_SESSION['error'])) {
    $error = $_SESSION['error'];
    unset($_SESSION['error']);
}

$reportDeliveryToEdit = null;
$testResultDetails = null; // Details for the currently selected test result

// Check if a specific delivery needs to be edited or a new one for a specific result_id
if (isset($_GET['delivery_id']) && !empty($_GET['delivery_id'])) {
    $labdiagnostic_reportdeliveries->delivery_id = $_GET['delivery_id'];
    $reportDeliveryToEdit = $labdiagnostic_reportdeliveries->getSingleDelivery();
    if ($reportDeliveryToEdit) {
        $testResultDetails = $labdiagnostic_reportdeliveries->getTestResultDetails($reportDeliveryToEdit['result_id']);
    }
} elseif (isset($_GET['result_id']) && !empty($_GET['result_id'])) {
    // If only result_id is provided, prepare for a new delivery linked to this result
    $testResultDetails = $labdiagnostic_reportdeliveries->getTestResultDetails($_GET['result_id']);
}


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $labdiagnostic_reportdeliveries->result_id = $_POST['result_id'] ?? null;
        $labdiagnostic_reportdeliveries->method = $_POST['method'] ?? null;
        $labdiagnostic_reportdeliveries->recipient = $_POST['recipient'] ?? null;
        $labdiagnostic_reportdeliveries->status = $_POST['status'] ?? 'pending';
        $labdiagnostic_reportdeliveries->attempts = $_POST['attempts'] ?? 0;
        $labdiagnostic_reportdeliveries->delivery_id = $_POST['delivery_id'] ?? null;

        switch ($_POST['action']) {
            case 'create':
                if ($labdiagnostic_reportdeliveries->create()) {
                    $_SESSION['message'] = "Report delivery created successfully!";
                } else {
                    $_SESSION['error'] = "Unable to create report delivery.";
                }
                break;

            case 'update':
                if ($labdiagnostic_reportdeliveries->update()) {
                    $_SESSION['message'] = "Report delivery updated successfully!";
                } else {
                    $_SESSION['error'] = "Unable to update report delivery.";
                }
                break;

            case 'delete':
                if ($labdiagnostic_reportdeliveries->delete()) {
                    $_SESSION['message'] = "Report delivery deleted successfully.";
                } else {
                    $_SESSION['error'] = "Unable to delete report delivery.";
                }
                break;

            case 'send':
                $delivery = $labdiagnostic_reportdeliveries->getSingleDelivery(); // Fetch current delivery details for sending
                if ($delivery) {
                    $trd = $labdiagnostic_reportdeliveries->getTestResultDetails($delivery['result_id']);
                    $rits = $labdiagnostic_reportdeliveries->getResultItemsByResultId($delivery['result_id']);

                    $success = false;
                    $labdiagnostic_reportdeliveries->attempts = $delivery['attempts'] + 1;
                    $labdiagnostic_reportdeliveries->delivery_id = $delivery['delivery_id'];
                    $labdiagnostic_reportdeliveries->method = $delivery['method'];
                    $labdiagnostic_reportdeliveries->recipient = $delivery['recipient'];

                    if ($delivery['method'] == 'email') {
                        $success = $labdiagnostic_reportdeliveries->sendEmailReport($trd, $rits, $delivery['recipient']);
                    } else {
                        // Simulate other methods as successful
                        $success = true;
                    }

                    $labdiagnostic_reportdeliveries->status = $success ? 'sent' : 'failed';

                    if ($labdiagnostic_reportdeliveries->update()) {
                        $_SESSION['message'] = "Report sent successfully!";
                    } else {
                        $_SESSION['error'] = "Unable to update delivery status after sending.";
                    }
                } else {
                    $_SESSION['error'] = "Report delivery not found for sending.";
                }
                break;
        }
        header('Location: report_deliveries.php' . (isset($_POST['result_id']) ? '?result_id=' . urlencode($_POST['result_id']) : ''));
        exit();
    }
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
    <link rel="stylesheet" href="../assets/CSS/labtestform.css">
    <link rel="stylesheet" href="../assets/CSS/report_deliveries.css">
    
</head>
<style>

    .print-btn {
        display: inline-block;
        margin: 10px;
        padding: 10px 20px;
        border: none;
        cursor: pointer;
        font-size: 16px;
        border-radius: 5px;
        transition: background-color 0.3s;
        background-color:green; 
        color:white;    
    }
    
    .print-btn:hover {
        background-color:red;
    }

    .button {
        text-align:center;
        margin-top: 20px;
    }

    @media print {
        .print-btn {
            display:none;
        }
    }
    </style>
<body>
    <div class="d-flex">
        <!----- Sidebar ----->
        <aside id="sidebar" class="sidebar-toggle">

            <div class="sidebar-logo mt-3">
                <img src="assets/image/logo-dark.png" width="90px" height="20px">
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
                        <a href="../../Laboratory and Diagnostic Management/test.php" class="sidebar-link">Test Available</a>
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
            <a href="../../Laboratory and Diagnostic Management/labtechm3.php/result_items.php" class="sidebar-link">Result items</a>
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
                        </ul>

                    </div>
                </div>
            </div>
            <!-- START CODING HERE -->
           <div class="container">
                <h1>Manage Report Deliveries</h1>

                <?php if ($message): ?>
                    <div class="alert success">
                        <?php echo $message; ?>
                    </div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="alert error">
                        <?php echo $error; ?>
                    </div>
                <?php endif; ?>

                <?php if ($testResultDetails): ?>
                <div class="test-info form-container">
                    <h2>Test Result Information for Delivery</h2>
                    <div class="info-grid">
                        <div class="info-item">
                            <span class="label">Patient:</span>
                            <span class="value"><?php echo htmlspecialchars($testResultDetails['patient_name']); ?></span>
                        </div>
                        <div class="info-item">
                            <span class="label">Email:</span>
                            <span class="value"><?php echo htmlspecialchars($testResultDetails['patient_email']); ?></span>
                        </div>
                        <div class="info-item">
                            <span class="label">Phone:</span>
                            <span class="value"><?php echo htmlspecialchars($testResultDetails['patient_phone']); ?></span>
                        </div>
                        <div class="info-item">
                            <span class="label">Result Date:</span>
                            <span class="value"><?php echo htmlspecialchars(date('M d, Y', strtotime($testResultDetails['result_date']))); ?></span>
                        </div>
                        <div class="info-item">
                            <span class="label">Status:</span>
                            <span class="value"><?php echo htmlspecialchars(ucfirst($testResultDetails['status'])); ?></span>
                        </div>
                         <div class="info-item">
                            <span class="label">Result ID:</span>
                            <span class="value"><?php echo htmlspecialchars($testResultDetails['result_id']); ?></span>
                        </div>
                    </div>
                </div>

                
                <?php endif; ?>

                <div class="form-container">
                    <h2><?php echo $reportDeliveryToEdit ? 'Edit Report Delivery' : 'Add New Report Delivery'; ?></h2>
                    <form id="reportDeliveryForm" method="post" action="report_deliveries.php">
                        <input type="hidden" name="action" id="action" value="<?php echo $reportDeliveryToEdit ? 'update' : 'create'; ?>">
                        <input type="hidden" name="delivery_id" id="delivery_id" value="<?php echo $reportDeliveryToEdit ? $reportDeliveryToEdit['delivery_id'] : ''; ?>">
                        <input type="hidden" name="attempts" id="attempts" value="<?php echo $reportDeliveryToEdit ? $reportDeliveryToEdit['attempts'] : '0'; ?>">

                        <!-- <div class="form-group">
                            <label for="result_id">Result ID:</label>
                            <input type="number" id="result_id" name="result_id" class="form-control"
                                value="<?php echo $reportDeliveryToEdit ? $reportDeliveryToEdit['result_id'] : ($testResultDetails ? $testResultDetails['result_id'] : ''); ?>"
                                required <?php echo ($reportDeliveryToEdit || $testResultDetails) ? 'readonly' : ''; ?>>
                        </div> -->

                <div class="form-group">
                <label for="result_id">Result ID:</label>
                <select id="result_id" name="result_id" required>
                    <option value="">Select Result</option>
                    <?php foreach ($testResults as $testResult): ?>
                    <option value="<?php echo $testResult['result_id']; ?>">
                    <?php echo $testResult['result_id']; ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>

                        <div class="form-group">
                            <label for="method">Delivery Method:</label>
                            <select name="method" id="method" class="form-control" required>
                                <option value="">Select Method</option>
                                <option value="email" <?php echo ($reportDeliveryToEdit && $reportDeliveryToEdit['method'] == 'email') ? 'selected' : ''; ?>>Email</option>
                                <option value="printed" <?php echo ($reportDeliveryToEdit && $reportDeliveryToEdit['method'] == 'printed') ? 'selected' : ''; ?>>Printed</option>   
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="recipient">Recipient:</label>
                            <input type="text" id="recipient" name="recipient" class="form-control" placeholder="Enter recipient information"
                                value="<?php echo $reportDeliveryToEdit ? htmlspecialchars($reportDeliveryToEdit['recipient']) : ''; ?>" required>
                        </div>

                        <div class="form-group" id="statusGroup" style="<?php echo $reportDeliveryToEdit ? '' : 'display:none;'; ?>">
                            <label for="status">Status:</label>
                            <select name="status" id="status" class="form-control">
                                <option value="pending" <?php echo ($reportDeliveryToEdit && $reportDeliveryToEdit['status'] == 'pending') ? 'selected' : ''; ?>>Pending</option>
                                <option value="sent" <?php echo ($reportDeliveryToEdit && $reportDeliveryToEdit['status'] == 'sent') ? 'selected' : ''; ?>>Sent</option>
                                <option value="delivered" <?php echo ($reportDeliveryToEdit && $reportDeliveryToEdit['status'] == 'delivered') ? 'selected' : ''; ?>>Delivered</option>
                                <option value="failed" <?php echo ($reportDeliveryToEdit && $reportDeliveryToEdit['status'] == 'failed') ? 'selected' : ''; ?>>Failed</option>
                            </select>
                        </div>

                        <div class="form-buttons">
                            <button type="submit" id="createBtn" class="btn btn-primary" style="<?php echo $reportDeliveryToEdit ? 'display:none;' : 'display:inline-block;'; ?>">Create Delivery</button>
                            <button type="submit" id="updateBtn" class="btn btn-warning" style="<?php echo $reportDeliveryToEdit ? 'display:inline-block;' : 'display:none;'; ?>">Update Delivery</button>
                            <button type="button" id="cancelBtn" class="btn btn-secondary" style="<?php echo $reportDeliveryToEdit ? 'display:inline-block;' : 'display:none;'; ?>">Cancel</button>
                        </div>
                    </form>
                </div>

                <div class="results-container form-container">
                    <h2>All Report Deliveries</h2>
                    <table class="table table-striped table-bordered">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Result ID</th>
                                <th>Patient Name</th>
                                <th>Method</th>
                                <th>Recipient</th>
                                <th>Delivery Date</th>
                                <th>Status</th>
                                <th>Attempts</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($reportDeliveries)): ?>
                                <?php foreach ($reportDeliveries as $delivery): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($delivery['delivery_id']); ?></td>
                                        <td><?php echo htmlspecialchars($delivery['result_id']); ?></td>
                                        <td><?php echo htmlspecialchars($delivery['patient_name']); ?></td>
                                        <td><span class="method-badge <?php echo strtolower($delivery['method']); ?>"><?php echo htmlspecialchars(ucfirst($delivery['method'])); ?></span></td>
                                        <td><?php echo htmlspecialchars($delivery['recipient']); ?></td>
                                        <td><?php echo $delivery['delivery_datetime'] ? htmlspecialchars(date('M d, Y H:i', strtotime($delivery['delivery_datetime']))) : 'Not sent'; ?></td>
                                        <td><span class="status-badge <?php echo strtolower($delivery['status']); ?>"><?php echo htmlspecialchars(ucfirst($delivery['status'])); ?></span></td>
                                        <td><?php echo htmlspecialchars($delivery['attempts']); ?></td>
                                        <td>
                                            <button class="btn-view" data-delivery-id="<?php echo $delivery['delivery_id']; ?>" data-result-id="<?php echo $delivery['result_id']; ?>">Preview</button>
                                            <button class="btn-edit" data-delivery-id="<?php echo $delivery['delivery_id']; ?>">Edit</button>
                                            <form method="POST" class="inline-form" onsubmit="return confirm('Are you sure you want to delete this delivery?');">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="delivery_id" value="<?php echo $delivery['delivery_id']; ?>">
                                                <input type="hidden" name="result_id" value="<?php echo $delivery['result_id']; ?>">
                                                <button type="submit" class="btn-danger">Delete</button>
                                            </form>
                                            <?php if ($delivery['status'] == 'pending' || $delivery['status'] == 'failed'): ?>
                                                <form method="POST" class="inline-form">
                                                    <input type="hidden" name="action" value="send">
                                                    <input type="hidden" name="delivery_id" value="<?php echo $delivery['delivery_id']; ?>">
                                                    <input type="hidden" name="result_id" value="<?php echo $delivery['result_id']; ?>">
                                                    <button type="submit" class="btn-send">Send</button>
                                                </form>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="9">No report deliveries found.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

            </div>
            </div>

        <div id="reportPreviewModal" class="modal">
            <div class="modal-content">
                <span class="close">&times;</span>
                <div id="reportPreviewContent">
                    <div class="report-header">
                        <div class="report-logo">
                            <h3>Hospital name</h3>
                            <p>Address</p>
                            <p>contact number</p>
                        </div>
                        <div class="report-title">
                            <h2>Laboratory and diagnostic report </h2>
                        </div>
                    </div>
                    <div id="previewDetails"></div>
                    <h3>Result Items</h3>
                    <div id="previewResultItems"></div>
                    <h3>Conclusion</h3>
                    <div id="previewConclusion"></div>
                    <div class="report-footer">
                        <div class="signature">
                            <div class="signature-line"></div>
                            <p>Performed by: <span id="previewPerformer"></span></p>
                        </div>
                        <div class="signature">
                            <div class="signature-line"></div>
                            <p>Verified by: <span id="previewVerifier"></span></p>
                        </div>
                </div>
            </div>
             <div class="button">
                            <button onclick="window.print()" class="print-btn">Print</button>                          
            </div>
        </div>

    <script>
        const toggler = document.querySelector(".toggler-btn");
        toggler.addEventListener("click", function() {
            document.querySelector("#sidebar").classList.toggle("collapsed");
        });

        document.addEventListener('DOMContentLoaded', function() {
            const reportDeliveryForm = document.getElementById('reportDeliveryForm');
            const createBtn = document.getElementById('createBtn');
            const updateBtn = document.getElementById('updateBtn');
            const cancelBtn = document.getElementById('cancelBtn');
            const deliveryIdInput = document.getElementById('delivery_id');
            const resultIdInput = document.getElementById('result_id');
            const methodSelect = document.getElementById('method');
            const recipientInput = document.getElementById('recipient');
            const statusGroup = document.getElementById('statusGroup');
            const statusSelect = document.getElementById('status');
            const attemptsInput = document.getElementById('attempts');

            const reportPreviewModal = document.getElementById('reportPreviewModal');
            const closeBtn = reportPreviewModal.querySelector('.close');
            const previewDetailsDiv = document.getElementById('previewDetails');
            const previewResultItemsDiv = document.getElementById('previewResultItems');
            const previewConclusionDiv = document.getElementById('previewConclusion');
            const previewPerformerSpan = document.getElementById('previewPerformer');
            const previewVerifierSpan = document.getElementById('previewVerifier');

            // Function to reset form to create mode
            function resetForm() {
                reportDeliveryForm.reset();
                deliveryIdInput.value = '';
                document.getElementById('action').value = 'create';
                attemptsInput.value = '0'; // Reset attempts for new
                createBtn.style.display = 'inline-block';
                updateBtn.style.display = 'none';
                cancelBtn.style.display = 'none';
                statusGroup.style.display = 'none'; // Hide status for new creation
                resultIdInput.removeAttribute('readonly'); // Allow editing result_id for new entry
                recipientInput.value = ''; // Clear recipient when resetting form
                statusSelect.value = 'pending'; // Reset status to pending
            }

            // Populate form if editing existing delivery
            <?php if ($reportDeliveryToEdit): ?>
                document.getElementById('action').value = 'update';
                deliveryIdInput.value = '<?php echo $reportDeliveryToEdit['delivery_id']; ?>';
                resultIdInput.value = '<?php echo $reportDeliveryToEdit['result_id']; ?>';
                methodSelect.value = '<?php echo htmlspecialchars($reportDeliveryToEdit['method']); ?>';
                recipientInput.value = '<?php echo htmlspecialchars($reportDeliveryToEdit['recipient']); ?>';
                statusSelect.value = '<?php echo htmlspecialchars($reportDeliveryToEdit['status']); ?>';
                attemptsInput.value = '<?php echo $reportDeliveryToEdit['attempts']; ?>';
                createBtn.style.display = 'none';
                updateBtn.style.display = 'inline-block';
                cancelBtn.style.display = 'inline-block';
                statusGroup.style.display = 'block';
                resultIdInput.setAttribute('readonly', true); // Prevent changing result_id when editing existing delivery
            <?php elseif ($testResultDetails): ?>
                 // If only result_id is in URL, auto-fill it for new delivery
                resultIdInput.value = '<?php echo $testResultDetails['result_id']; ?>';
                // Also pre-fill recipient based on default method (email)
                if (methodSelect.value === 'email') {
                    recipientInput.value = '<?php echo $testResultDetails['patient_email']; ?>';
                } else if (methodSelect.value === 'printed') {
                    recipientInput.value = '<?php echo $testResultDetails['patient_name']; ?>';
                }
            <?php endif; ?>

            // Handle edit button click
            document.querySelectorAll('.btn-edit').forEach(button => {
                button.addEventListener('click', function() {
                    const deliveryId = this.dataset.deliveryId;
                    window.location.href = `report_deliveries.php?delivery_id=${deliveryId}`;
                });
            });

            // Handle cancel button click
            cancelBtn.addEventListener('click', function() {
                resetForm();
                window.history.pushState({}, document.title, "report_deliveries.php"); // Clean URL
            });

            // Auto-fill recipient based on method selection, similar to report_deliveries try.php
            methodSelect.addEventListener('change', function() {
                const selectedMethod = this.value;
                // Only auto-fill if recipient is empty or if it was previously auto-filled based on another method
                // and the user hasn't typed anything specific.
                // For simplicity, we'll always try to re-fill on change if current recipient matches patient data.
                <?php if ($testResultDetails): ?>
                    const patientEmail = '<?php echo $testResultDetails['patient_email'] ?? ''; ?>';
                    const patientId = '<?php echo $testResultDetails['patient_id'] ?? ''; ?>';
                    const patientName = '<?php echo $testResultDetails['patient_name'] ?? ''; ?>';
                    const patientPhone = '<?php echo $testResultDetails['patient_phone'] ?? ''; ?>';

                    if (selectedMethod === 'email') {
                        if (recipientInput.value === '' || recipientInput.value === patientId || recipientInput.value === patientName || recipientInput.value === patientPhone) {
                            recipientInput.value = patientEmail;
                        }
                    }else if (selectedMethod === 'printed') {
                         if (recipientInput.value === '' || recipientInput.value === patientEmail || recipientInput.value === patientId || recipientInput.value === patientPhone) {
                            recipientInput.value = patientName;
                        }                   
                    }
                <?php else: ?>
                    // If no testResultDetails, clear recipient on method change or show general placeholder
                    recipientInput.value = '';
                    recipientInput.placeholder = `Enter recipient for ${selectedMethod}`;
                <?php endif; ?>
            });


            // Report Preview Modal JavaScript
            document.querySelectorAll('.btn-view').forEach(button => {
                button.addEventListener('click', function() {
                    const deliveryId = this.dataset.deliveryId;
                    const resultId = this.dataset.resultId;
                    // Fetch full details for the report to display in modal
                    fetch(`fetch_report_details.php?delivery_id=${deliveryId}&result_id=${resultId}`)
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                const testResult = data.testResult;
                                const resultItems = data.resultItems;

                                previewDetailsDiv.innerHTML = `
                                    <div class="report-details-grid">
                                        <div><strong>Patient:</strong> ${testResult.patient_name}</div>
                                        <div><strong>Result ID:</strong> ${testResult.result_id}</div>
                                        <div><strong>Test Date:</strong> ${testResult.result_date ? new Date(testResult.result_date).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' }) : 'N/A'}</div>
                                        <div><strong>Sample Type:</strong> ${testResult.sample_type || 'N/A'}</div>
                                        <div><strong>Collection Date:</strong> ${testResult.collection_date ? new Date(testResult.collection_date).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' }) : 'N/A'}</div>
                                    </div>
                                `;

                                if (resultItems.length > 0) {
                                    let itemsHtml = '<table class="report-table"><thead><tr><th>Parameter</th><th>Result</th><th>Normal Range</th><th>Units</th><th>Flag</th></tr></thead><tbody>';
                                    resultItems.forEach(item => {
                                        itemsHtml += `
                                            <tr>
                                                <td>${item.parameter}</td>
                                                <td>${item.result_value}</td>
                                                <td>${item.normal_range}</td>
                                                <td>${item.units}</td>
                                                <td><span class="flag-badge ${item.flag ? item.flag.toLowerCase() : ''}">${item.flag ? item.flag.charAt(0).toUpperCase() + item.flag.slice(1) : 'N/A'}</span></td>
                                            </tr>
                                        `;
                                    });
                                    itemsHtml += '</tbody></table>';
                                    previewResultItemsDiv.innerHTML = itemsHtml;
                                } else {
                                    previewResultItemsDiv.innerHTML = '<p class="no-results">No result items found for this test.</p>';
                                }

                                previewConclusionDiv.innerHTML = testResult.conclusion ? `<p>${testResult.conclusion.replace(/\n/g, '<br>')}</p>` : '<p>No conclusion provided.</p>';
                                previewPerformerSpan.textContent = testResult.performer_name || 'N/A';
                                previewVerifierSpan.textContent = testResult.verifier_name || 'N/A';

                                reportPreviewModal.style.display = 'block';
                            } else {
                                alert('Failed to load report details: ' + data.message);
                            }
                        })
                        .catch(error => {
                            console.error('Error fetching report details:', error);
                            alert('Failed to load report details. Please try again.');
                        });
                });
            });

            closeBtn.addEventListener('click', function() {
                reportPreviewModal.style.display = 'none';
            });

            window.addEventListener('click', function(event) {
                if (event.target == reportPreviewModal) {
                    reportPreviewModal.style.display = 'none';
                }
            });
        });
    </script>


            
 
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