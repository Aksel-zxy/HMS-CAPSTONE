<?php
session_start();
include '../../SQL/config.php';

// ✅ Use MySQLi-compatible class
class InsuranceRequestLogs {
    private $conn;

    public function __construct($db) {
        $this->conn = $db;
    }

    public function getAllRequests() {
        $sql = "
            SELECT 
                ir.request_id,
                CONCAT(p.fname, ' ', p.lname) AS full_name,
                ir.insurance_number,
                ir.insurance_type,
                ir.notes,
                il.insurance_covered,
                il.status
            FROM insurance_request ir
            JOIN patientinfo p ON ir.patient_id = p.patient_id
            LEFT JOIN insurance_request il ON ir.request_id = il.request_id
            ORDER BY ir.request_id DESC
        ";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        $result = $stmt->get_result();

        $requests = [];
        while ($row = $result->fetch_assoc()) {
            $requests[] = $row;
        }

        return $requests;
    }
}

// ✅ Session check
if (!isset($_SESSION['billing']) || $_SESSION['billing'] !== true) {
    header('Location: login.php');
    exit();
}

$logs = new InsuranceRequestLogs($conn);
$requests = $logs->getAllRequests();

$success_message = '';
if (isset($_GET['success']) && $_GET['success'] == 1) {
    $success_message = "Insurance request submitted successfully.";
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
                <a href="billing_dashboard.php" class="sidebar-link" data-bs-toggle="#" data-bs-target="#"
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
            </li>
        </aside>

        <!-- Main Content -->
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
                        <button class="btn dropdown-toggle" type="button" id="dropdownMenuButton" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-person-circle"></i>
                        </button>
                        <ul class="dropdown-menu" aria-labelledby="dropdownMenuButton">
                            <li><span>Welcome <strong style="color: #060c13ff;"><?php echo $_SESSION['user']['lname'] ?? 'User'; ?></strong>!</span></li>
                            <li><a class="dropdown-item" href="../logout.php">Logout</a></li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- Page Content -->
            <div class="center-wrapper">
                <div class="container" style="max-width: 1000px;">
                    <h2>Insurance Request Logs</h2>
                    <?php if (!empty($success_message)): ?>
                        <div class="alert-success">
                            <?= htmlspecialchars($success_message) ?>
                        </div>
                    <?php endif; ?>
                    <div style="text-align:center;margin-bottom:18px;">
                        <a href="insurance_request.php">Back to Request Form</a>
                    </div>
                    <div class="request-table hospital-billing-table">
                        <h3>Submitted Insurance Requests</h3>
                        <?php if (!empty($requests)): ?>
                        <table>
                            <tr>
                                <th>Request ID</th>
                                <th>Name</th>
                                <th>Insurance Number</th>
                                <th>Insurance Type</th>
                                <th>Insurance Covered</th>
                                <th>Notes</th>
                                <th>Status</th>
                            </tr>
                            <?php foreach ($requests as $req): ?>
                            <tr>
                                <td><?= htmlspecialchars($req['request_id']) ?></td>
                                <td><?= htmlspecialchars($req['full_name']) ?></td>
                                <td><?= htmlspecialchars($req['insurance_number']) ?></td>
                                <td><?= htmlspecialchars($req['insurance_type']) ?></td>
                                <td><?= htmlspecialchars($req['insurance_covered'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($req['notes']) ?></td>
                                <td><?= ucfirst(htmlspecialchars($req['status'] ?? 'Pending')) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </table>
                        <?php else: ?>
                            <div style="margin-top:16px;text-align:center;">No insurance requests found.</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
