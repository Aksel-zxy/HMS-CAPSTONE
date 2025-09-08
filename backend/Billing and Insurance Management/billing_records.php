<?php
// Start session and include configuration at the very top
session_start();
include '../../SQL/config.php';

// Include the class files
require_once 'classincludes/billing_records_class.php';

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

// Instantiate the billing_records class
$billing = new billing_records($conn);

// Process any patients without billing records
if (isset($_GET['process_patients']) && $_GET['process_patients'] == 'true') {
    $processed = $billing->processAllPatientsWithoutBilling();
    if ($processed > 0) {
        $message = "Successfully created billing records for $processed patients.";
        $message_type = "success";
    } else {
        $message = "Billing Record is Up to Date. No new patients found.";
        $message_type = "info";
    }
}

// Check if there are patients without billing records
$patientsWithoutBilling = $billing->getPatientsWithoutBillingRecords();
$patientsWithoutBillingCount = $patientsWithoutBilling ? $patientsWithoutBilling->num_rows : 0;

// Get all billing records
$records = $billing->getAllBillingRecords();

// Helper function to calculate total amount from diagnostic results for a patient
function getTotalAmountForPatient($conn, $patient_id) {
    $total = 0;
    // Get diagnostic results for patient
    $sql_diag = "SELECT result FROM dl_results WHERE patientID = ?";
    $stmt_diag = $conn->prepare($sql_diag);
    $stmt_diag->bind_param('i', $patient_id);
    $stmt_diag->execute();
    $result_diag = $stmt_diag->get_result();
    while ($row = $result_diag->fetch_assoc()) {
        $service_name = $row['result'];
        $service_stmt = $conn->prepare("SELECT price FROM dl_services WHERE serviceName = ?");
        $service_stmt->bind_param("s", $service_name);
        $service_stmt->execute();
        $service_result = $service_stmt->get_result();
        if ($service_row = $service_result->fetch_assoc()) {
            $total += floatval($service_row['price']);
        }
    }
    return $total;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HMS | Billing and Insurance Management</title>
    <link rel="shortcut icon" href="assets/image/favicon.ico" type="image/x-icon">
    <link rel="stylesheet" href="assets/CSS/bootstrap.min.css">
    <link rel="stylesheet" href="../assets/CSS/billingrecord.css">
    <link rel="stylesheet" href="assets/CSS/super.css">
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
                        <span class="username ml-1 me-2"><?php echo $user['fname']; ?> <?php echo $user['lname']; ?></span>
                        <button class="btn dropdown-toggle" type="button" id="dropdownMenuButton" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-person-circle"></i>
                        </button>
                        <ul class="dropdown-menu" aria-labelledby="dropdownMenuButton" style="min-width: 200px; padding: 10px; border-radius: 5px; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); background-color: #fff; color: #333;">
                            <li style="margin-bottom: 8px; font-size: 14px; color: #555;">
                                <span>Welcome <strong style="color: #007bff;"><?php echo $user['lname']; ?></strong>!</span>
                            </li>
                            <li>
                                <a class="dropdown-item" href="../logout.php" style="font-size: 14px; color: #007bff; text-decoration: none; padding: 8px 12px; border-radius: 4px; transition: background-color 0.3s ease;">
                                    Logout
                                </a>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
            <!-- START CODING HERE -->
            <div class="container-fluid">
                <h1 class="text-center" style="font-size:2.3rem; font-weight:700; letter-spacing:1px; margin-bottom:1.5rem; color:#2c3e50;">Billing Records</h1>
                
                <?php
                if (isset($message)) {
                    $alert_class = $message_type == 'success' ? 'alert-success' : ($message_type == 'info' ? 'alert-info' : 'alert-warning');
                    echo "<div class='alert-box $alert_class'>$message</div>";
                }
                
                if ($patientsWithoutBillingCount > 0) {
                    echo '<div class="alert alert-warning">';
                    echo 'There are ' . $patientsWithoutBillingCount . ' patients without billing records. ';
                    echo '<a href="billing_records.php?process_patients=true" class="alert-link">Click here to generate billing IDs for them</a>.';
                    echo '</div>';
                }
                ?>
                
                <div class="action-buttons">
                    <a href="billing_records.php?process_patients=true" class="minimal-btn">Check for New Patients</a>
                </div>

                <div class="row">
                    <div class="col-md-12">
                        <div class="table-responsive">
                            <table class="table billing-records-table">
                                <thead>
                                    <tr>
                                        <th>Billing ID</th>
                                        <th>Patient ID</th>
                                        <th>Patient Name</th>
                                        <th>Billing Date</th>
                                        <th>Total Amount</th>
                                        <th>Insurance Covered</th>
                                        <th>Out of Pocket</th>
                                        <th>Status</th>
                                        <th>Payment Method</th>
                                        <th>Transaction ID</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    if ($records && $records->num_rows > 0) {
                                        while ($row = $records->fetch_assoc()) {
                                            echo "<tr>";
                                            echo "<td>" . (isset($row['billing_id']) ? $row['billing_id'] : 'N/A') . "</td>";
                                            echo "<td>" . (isset($row['patient_id']) ? $row['patient_id'] : 'N/A') . "</td>";
                                            echo "<td>" . (isset($row['fname']) ? $row['fname'] . ' ' . $row['lname'] : 'N/A') . "</td>";
                                            echo "<td>" . (isset($row['billing_date']) ? $row['billing_date'] : 'N/A') . "</td>";

                                            // Calculate total amount from dl_services prices
                                            $patient_id = isset($row['patient_id']) ? $row['patient_id'] : 0;
                                            $total_amount = getTotalAmountForPatient($conn, $patient_id);
                                            echo "<td>₱" . number_format($total_amount, 2) . "</td>";

                                            $insurance_covered = isset($row['insurance_covered_amount']) ? $row['insurance_covered_amount'] : 0;
                                            echo "<td>₱" . number_format($insurance_covered, 2) . "</td>";

                                            $out_of_pocket = max(0, $total_amount - $insurance_covered);
                                            echo "<td>₱" . number_format($out_of_pocket, 2) . "</td>";

                                            $badgeClass = 'minimal-badge ';
                                            $status = isset($row['status']) ? $row['status'] : 'pending';
                                            switch($status) {
                                                case 'Paid':
                                                    $badgeClass .= 'bg-success';
                                                    break;
                                                case 'pending':
                                                    $badgeClass .= 'bg-warning text-dark';
                                                    break;
                                                case 'Partially Paid':
                                                    $badgeClass .= 'bg-info';
                                                    break;
                                                default:
                                                    $badgeClass .= 'bg-secondary';
                                            }
                                            
                                            echo "<td><span class='" . $badgeClass . "'>" . $status . "</span></td>";
                                            echo "<td>" . (isset($row['payment_method']) ? $row['payment_method'] : 'N/A') . "</td>";
                                            echo "<td>" . (isset($row['transaction_id']) ? $row['transaction_id'] : 'N/A') . "</td>";
                                            echo "<td><a href='billing_summary.php?patient_id=" . (isset($row['patient_id']) ? $row['patient_id'] : '') . "' class='minimal-btn'>Generate Summary</a></td>";
                                            echo "</tr>";
                                        }
                                    } else {
                                        echo "<tr><td colspan='11' class='text-center'>No billing records found</td></tr>";
                                    }
                                    ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
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
    <script src="assets/Bootstrap/all.min.js"></script>
    <script src="assets/Bootstrap/bootstrap.bundle.min.js"></script>
    <script src="assets/Bootstrap/fontawesome.min.js"></script>
    <script src="assets/Bootstrap/jq.js"></script>
</body>
</html>