<?php
session_start();
include '../../SQL/config.php';
require_once 'classincludes/insurance_request_class.php';
require_once 'classincludes/billing_summary_class.php';

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
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_request'])) {
    // Validate patient_id exists
    $patient_id = $_POST['patient_id'];
    $check_stmt = $conn->prepare("SELECT patient_id FROM patientinfo WHERE patient_id = ?");
    $check_stmt->bind_param("i", $patient_id);
    $check_stmt->execute();
    $check_stmt->store_result();
    if ($check_stmt->num_rows > 0) {
        $insuranceRequest->create(
            $_POST['patient_id'],
            $_POST['billing_id'],
            $_POST['insurance_type'],
            $_POST['notes']
        );
        $success_message = "Insurance request submitted successfully.";
        $show_requests = true;
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
    <title>Insurance Requests</title>
    <style>
        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            background: #eaf3fa;
            margin: 0;
            padding: 0;
        }
        .center-wrapper {
            width: 100vw;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .container {
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 6px 32px rgba(33, 150, 243, 0.13);
            padding: 38px 32px 32px 32px;
            max-width: 540px;
            width: 100%;
            margin: 32px 0;
        }
        h2 {
            text-align: center;
            font-size: 2.1rem;
            font-weight: 700;
            color: #1976d2;
            margin-bottom: 28px;
            letter-spacing: 1px;
        }
        .form-card {
            background: #f4f8fb;
            border-radius: 10px;
            padding: 28px 24px 18px 24px;
            margin-bottom: 24px;
            box-shadow: 0 1px 4px rgba(33,150,243,0.07);
        }
        .form-card label {
            display: block;
            margin-bottom: 7px;
            color: #34495e;
            font-weight: 500;
            font-size: 15px;
        }
        .form-card input[type="number"],
        .form-card input[type="text"],
        .form-card textarea {
            width: 100%;
            padding: 10px 12px;
            margin-bottom: 18px;
            border: 1px solid #b0c4d6;
            border-radius: 5px;
            font-size: 15px;
            background: #fff;
            transition: border 0.2s;
        }
        .form-card input:focus,
        .form-card textarea:focus {
            border: 1.5px solid #1976d2;
            outline: none;
        }
        .form-card button {
            background: linear-gradient(90deg, #1976d2 80%, #42a5f5 100%);
            color: #fff;
            border: none;
            padding: 12px 0;
            border-radius: 5px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            width: 100%;
            transition: background 0.2s;
            margin-top: 8px;
            box-shadow: 0 2px 8px rgba(33,150,243,0.07);
        }
        .form-card button:hover {
            background: #125ea7;
        }
        .alert-success {
            background: #e8f5e9;
            color: #388e3c;
            padding: 10px 16px;
            border-radius: 4px;
            margin-bottom: 16px;
            font-size: 15px;
            text-align: center;
            border-left: 4px solid #43a047;
        }
        .alert-error {
            background: #ffebee;
            color: #c62828;
            padding: 10px 16px;
            border-radius: 4px;
            margin-bottom: 16px;
            font-size: 15px;
            text-align: center;
            border-left: 4px solid #e53935;
        }
        .hospital-billing-table {
            background: #f8fafc;
            border-radius: 14px;
            box-shadow: 0 4px 24px rgba(33, 150, 243, 0.08);
            padding: 28px 12px 24px 12px;
            margin-top: 36px;
            border-left: 6px solid #1976d2;
        }
        .hospital-billing-table h3 {
            color: #1976d2;
            font-size: 1.3rem;
            font-weight: 700;
            margin-bottom: 18px;
            text-align: left;
            letter-spacing: 1px;
        }
        .hospital-billing-table table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            background: #fff;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 1px 8px rgba(33, 150, 243, 0.07);
        }
        .hospital-billing-table th, .hospital-billing-table td {
            padding: 14px 12px;
            font-size: 15px;
            border-bottom: 1px solid #e3eaf1;
        }
        .hospital-billing-table th {
            background: linear-gradient(90deg, #1976d2 80%, #42a5f5 100%);
            color: #fff;
            font-weight: 700;
            font-size: 16px;
            border-bottom: 2px solid #1565c0;
            letter-spacing: 0.5px;
        }
        .hospital-billing-table tr:nth-child(even) td {
            background: #f4f8fb;
        }
        .hospital-billing-table tr:hover td {
            background: #e3f0fc;
            transition: background 0.2s;
        }
        .hospital-billing-table td {
            color: #2d3e50;
            vertical-align: middle;
        }
        .hospital-billing-table td:last-child {
            text-align: center;
        }
        .hospital-billing-table a {
            font-weight: 600;
            text-decoration: none;
            padding: 4px 10px;
            border-radius: 4px;
            transition: background 0.2s, color 0.2s;
        }
        .hospital-billing-table a[style*="green"] {
            background: #e8f5e9;
            color: #388e3c !important;
        }
        .hospital-billing-table a[style*="red"] {
            background: #ffebee;
            color: #c62828 !important;
        }
        .hospital-billing-table span[style*="gray"] {
            color: #888 !important;
            font-style: italic;
        }
        @media (max-width: 700px) {
            .container { padding: 8px; }
            .form-card { padding: 10px; }
            .hospital-billing-table { padding: 8px; }
            .hospital-billing-table table, .hospital-billing-table thead, .hospital-billing-table tbody, .hospital-billing-table th, .hospital-billing-table td, .hospital-billing-table tr {
                display: block;
            }
            .hospital-billing-table th {
                position: absolute;
                left: -9999px;
            }
            .hospital-billing-table tr {
                margin-bottom: 18px;
                border-radius: 8px;
                box-shadow: 0 1px 4px rgba(33, 150, 243, 0.07);
                background: #fff;
            }
            .hospital-billing-table td {
                border: none;
                position: relative;
                padding-left: 52%;
                min-height: 38px;
                font-size: 15px;
            }
            .hospital-billing-table td:before {
                position: absolute;
                left: 12px;
                width: 45%;
                white-space: nowrap;
                font-weight: bold;
                color: #1976d2;
                font-size: 14px;
            }
            .hospital-billing-table td:nth-of-type(1):before { content: "ID"; }
            .hospital-billing-table td:nth-of-type(2):before { content: "Patient ID"; }
            .hospital-billing-table td:nth-of-type(3):before { content: "Billing ID"; }
            .hospital-billing-table td:nth-of-type(4):before { content: "Insurance Type"; }
            .hospital-billing-table td:nth-of-type(5):before { content: "Notes"; }
            .hospital-billing-table td:nth-of-type(6):before { content: "Status"; }
            .hospital-billing-table td:nth-of-type(7):before { content: "Action"; }
        }
    </style>
</head>
<body>
<div class="center-wrapper">
    <div class="container">
        <h2>Insurance Requests Form</h2>
        <div class="form-card">
            <?php if (!empty($success_message)): ?>
                <div class="alert-success">
                    <?= htmlspecialchars($success_message) ?>
                </div>
            <?php endif; ?>
            <?php if (!empty($error_message)): ?>
                <div class="alert-error">
                    <?= htmlspecialchars($error_message) ?>
                </div>
            <?php endif; ?>
            <form method="post">
                <label for="patient_id">Patient ID:</label>
                <input type="number" name="patient_id" id="patient_id" required>

                <label for="billing_id">Billing ID:</label>
                <input type="number" name="billing_id" id="billing_id" required>

                <label for="insurance_type">Insurance Type:</label>
                <input type="text" name="insurance_type" id="insurance_type" required>

                <label for="notes">Notes:</label>
                <textarea name="notes" id="notes" rows="2"></textarea>

                <button type="submit" name="create_request">Submit Request</button>
            </form>
        </div>
        <?php if ($show_requests && !empty($requests)): ?>
            <div class="request-table hospital-billing-table">
                <h3>Submitted Insurance Requests</h3>
                <table>
                    <tr>
                        <th>ID</th>
                        <th>Patient ID</th>
                        <th>Billing ID</th>
                        <th>Insurance Type</th>
                        <th>Notes</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                    <?php foreach ($requests as $req): ?>
                    <tr>
                        <td><?= htmlspecialchars($req['request_id']) ?></td>
                        <td><?= htmlspecialchars($req['patient_id']) ?></td>
                        <td><?= htmlspecialchars($req['billing_id']) ?></td>
                        <td><?= htmlspecialchars($req['insurance_type']) ?></td>
                        <td><?= htmlspecialchars($req['notes']) ?></td>
                        <td><?= ucfirst(htmlspecialchars($req['status'])) ?></td>
                        <td>
                            <?php if ($req['status'] === 'pending'): ?>
                                <a href="?action=approve&id=<?= urlencode($req['request_id']) ?>" style="color:green;">Approve</a> | 
                                <a href="?action=decline&id=<?= urlencode($req['request_id']) ?>" style="color:red;">Decline</a>
                            <?php else: ?>
                                <span style="color:gray;">Pending</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </table>
            </div>
        <?php elseif ($show_requests): ?>
            <div style="margin-top:16px;text-align:center;">No insurance requests found.</div>
        <?php endif; ?>
    </div>
</div>
</body>
</html>