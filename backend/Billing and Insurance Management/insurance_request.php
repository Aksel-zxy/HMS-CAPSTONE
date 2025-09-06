<?php
session_start();
include '../../SQL/config.php';
require_once 'classincludes/insurance_request_class.php';
require_once 'classincludes/billing_summary_class.php';

$InsuranceRequest = new InsuranceRequest($conn);
$patient = $InsuranceRequest->insurance();


if (!isset($_SESSION['billing']) || $_SESSION['billing'] !== true) {
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

// Instantiate InsuranceRequest with $conn
$insuranceRequest = new InsuranceRequest($conn);

// Track if requests should be shown
$show_requests = false;
$success_message = '';
$error_message = '';

// Handle form submission for new request
if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['create_request'], $_POST['patient_id'], $_POST['insurance_number'], $_POST['insurance_type'])
) {
    // All required POST fields are set
    $patient_id = $_POST['patient_id'];
    $insurance_number = $_POST['insurance_number'];
    $insurance_type = $_POST['insurance_type'];
    $notes = $_POST['notes'] ?? ''; // notes is optional

    // Validate patient_id exists
    $check_stmt = $conn->prepare("SELECT patient_id FROM patientinfo WHERE patient_id = ?");
    $check_stmt->bind_param("i", $patient_id);
    $check_stmt->execute();
    $check_stmt->store_result();

    if ($check_stmt->num_rows > 0) {
        $insuranceRequest->create($patient_id, $insurance_number, $insurance_type, $notes);

        header("Location: insurance_request_logs.php?success=1");
        exit();
    } else {
        $error_message = "Patient ID does not exist. Please enter a valid Patient ID.";
    }

    $check_stmt->close();
}


// Handle status update
if (isset($_GET['action'], $_GET['id']) && in_array($_GET['action'], ['approve', 'decline'])) {
    $status = $_GET['action'] === 'approve' ? 'approved' : 'declined';
    $insuranceRequest->updateStatus($_GET['id'], $status);
    $show_requests = true;
    // Optionally, you can redirect or just show the table
    header("Location: insurance_request.php?show=1");
    exit;
}

// Show requests if requested via GET (after status update)
if (isset($_GET['show']) && $_GET['show'] == 1) {
    $show_requests = true;
}

// Fetch all requests only if needed
$requests = [];
if ($show_requests) {
    $requests = $insuranceRequest->getAll();
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Insurance Requests</title>
    <link rel="shortcut icon" href="assets/image/favicon.ico" type="image/x-icon">
    <link rel="stylesheet" href="assets/CSS/bootstrap.min.css">
    <link rel="stylesheet" href="assets/CSS/super.css">
    <link rel="stylesheet" type="text/css" href="../assets/CSS/billingandinsurance.css">
</head>
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
                <a href="admin_dashboard.php" class="sidebar-link" data-bs-toggle="#" data-bs-target="#"
                    aria-expanded="false" aria-controls="auth">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-cast" viewBox="0 0 16 16">
                        <path d="m7.646 9.354-3.792 3.792a.5.5 0 0 0 .353.854h7.586a.5.5 0 0 0 .354-.854L8.354 9.354a.5.5 0 0 0-.708 0" />
                        <path d="M11.414 11H14.5a.5.5 0 0 0 .5-.5v-7a.5.5 0 0 0-.5-.5h-13a.5.5 0 0 0-.5.5v7a.5.5 0 0 0 .5.5h3.086l-1 1H1.5A1.5 1.5 0 0 1 0 10.5v-7A1.5 1.5 0 0 1 1.5 2h13A1.5 1.5 0 0 1 16 3.5v7a1.5 1.5 0 0 1-1.5 1.5h-2.086z" />
                    </svg>
                    <span style="font-size: 18px;">Dashboard</span>
                </a>
            </li>

            <li class="sidebar-item">
                <a href="#" class="sidebar-link collapsed has-dropdown" data-bs-toggle="collapse" data-bs-target="#gerald"
                    aria-expanded="true" aria-controls="auth">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-person-vcard"
                        viewBox="0 0 16 16" style="margin-bottom: 6px;">
                        <path
                            d="M5 8a2 2 0 1 0 0-4 2 2 0 0 0 0 4m4-2.5a.5.5 0 0 1 .5-.5h4a.5.5 0 0 1 0 1h-4a.5.5 0 0 1-.5-.5M9 8a.5.5 0 0 1 .5-.5h4a.5.5 0 0 1 0 1h-4A.5.5 0 0 1 9 8m1 2.5a.5.5 0 0 1 .5-.5h3a.5.5 0 0 1 0 1h-3a.5.5 0 0 1-.5-.5" />
                        <path
                            d="M2 2a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V4a2 2 0 0 0-2-2zM1 4a1 1 0 0 1 1-1h12a1 1 0 0 1 1 1v8a1 1 0 0 1-1 1H8.96q.04-.245.04-.5C9 10.567 7.21 9 5 9c-2.086 0-3.8 1.398-3.984 3.181A1 1 0 0 1 1 12z" />
                    </svg>
                    <span style="font-size: 18px;">Billing Management</span>
                </a>

                <ul id="gerald" class="sidebar-dropdown list-unstyled collapse" data-bs-parent="#sidebar">
                    <li class="sidebar-item">
                        <a href="billing_records.php" class="sidebar-link">Billing Records</a>
                    </li>
                    <li class="sidebar-item">
                        <a href="billing_items.php" class="sidebar-link">Billing Items</a>
                    </li>
                    <li class="sidebar-item">
                        <a href="expense_logs.php" class="sidebar-link">Expense Logs</a>
                    </li>
                </ul>
            </li>

            <li class="sidebar-item">
                <a href="#" class="sidebar-link collapsed has-dropdown" data-bs-toggle="collapse" data-bs-target="#gerald"
                    aria-expanded="true" aria-controls="auth">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-person-vcard"
                        viewBox="0 0 16 16" style="margin-bottom: 6px;">
                        <path
                            d="M5 8a2 2 0 1 0 0-4 2 2 0 0 0 0 4m4-2.5a.5.5 0 0 1 .5-.5h4a.5.5 0 0 1 0 1h-4a.5.5 0 0 1-.5-.5M9 8a.5.5 0 0 1 .5-.5h4a.5.5 0 0 1 0 1h-4A.5.5 0 0 1 9 8m1 2.5a.5.5 0 0 1 .5-.5h3a.5.5 0 0 1 0 1h-3a.5.5 0 0 1-.5-.5" />
                        <path
                            d="M2 2a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V4a2 2 0 0 0-2-2zM1 4a1 1 0 0 1 1-1h12a1 1 0 0 1 1 1v8a1 1 0 0 1-1 1H8.96q.04-.245.04-.5C9 10.567 7.21 9 5 9c-2.086 0-3.8 1.398-3.984 3.181A1 1 0 0 1 1 12z" />
                    </svg>
                    <span style="font-size: 18px;">Journal</span>
                </a>

                <ul id="gerald" class="sidebar-dropdown list-unstyled collapse" data-bs-parent="#sidebar">
                    <li class="sidebar-item">
                        <a href="journal_account.php" class="sidebar-link">Journal Account</a>
                    </li>
                    <li class="sidebar-item">
                        <a href="journal_entry.php" class="sidebar-link">Journal Entry</a>
                    </li>
                    <li class="sidebar-item">
                        <a href="journal_entry_line.php" class="sidebar-link">Journal Entry Line</a>
                    </li>
                </ul>
            </li>

            <li class="sidebar-item">
                <a href="insurance_request.php" class="sidebar-link" data-bs-toggle="#" data-bs-target="#"
                    aria-expanded="false" aria-controls="auth">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-cast" viewBox="0 0 16 16">
                        <path d="m7.646 9.354-3.792 3.792a.5.5 0 0 0 .353.854h7.586a.5.5 0 0 0 .354-.854L8.354 9.354a.5.5 0 0 0-.708 0" />
                        <path d="M11.414 11H14.5a.5.5 0 0 0 .5-.5v-7a.5.5 0 0 0-.5-.5h-13a.5.5 0 0 0-.5.5v7a.5.5 0 0 0 .5.5h3.086l-1 1H1.5A1.5 1.5 0 0 1 0 10.5v-7A1.5 1.5 0 0 1 1.5 2h13A1.5 1.5 0 0 1 16 3.5v7a1.5 1.5 0 0 1-1.5 1.5h-2.086z" />
                    </svg>
                    <span style="font-size: 18px;">Insurance Request</span>
                </a>
            </li>
        </aside>
         <div class="center-wrapper">
            <div class="container">
                <h2>Insurance Requests Form</h2>
                <div class="form-card">
                    <form method="post">
                        <div class="col-sm-9">
                                    <label>Patient Name</label>
                                    <select class="form-select" style="width: 133%;" name="patient_id" required>
                                        <option value="">   </option>
                                       <?php
                                        if (!empty($patient)) {
                                            foreach ($patient as $row) {
                                                $fullName = htmlspecialchars($row['full_name']);
                                                echo "<option value='{$row['patient_id']}'>$fullName</option>";
                                            }
                                        } else {
                                            echo "<option value=''>No patient available</option>";
                                        }

                                        ?>

                                    </select>
                                </div>
                                    
                        
                        <label for="insurance_number">Insurance Number:</label>
                        <input type="number" name="insurance_number" id="insurance_number" required>        
                        
                        <label for="insurance_type">Insurance Type:</label>
                        <select name="insurance_type" id="insurance_type" required>
                            <option value="">Select Insurance Type</option>
                            <option value="PhilHealth">PhilHealth</option>
                            <option value="Maxicare">Maxicare</option>
                            <option value="Intellicare">Intellicare</option>
                            <option value="MediCard Philippines">MediCard Philippines</option>
                            <option value="PhilCare">PhilCare</option>
                            <option value="Pacific Cross">Pacific Cross</option>
                            <option value="AIA Philippines">AIA Philippines</option>
                            <option value="Sun Life of Canada">Sun Life of Canada</option>
                        </select>
                        
                        <label for="notes">Notes:</label>
                        <textarea name="notes" id="notes" rows="3"></textarea>
                        
                        <button type="submit" name="create_request">Submit Request</button>
                    </form>
                </div>
                
                <div class="text-center">
                    <a href="insurance_request_logs.php" class="logs-link">View Insurance Request Logs</a>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>