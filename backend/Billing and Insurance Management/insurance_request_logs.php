<?php
session_start();
include '../../SQL/config.php';
require_once 'classincludes/insurance_requestlogs_class.php';

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
    <title>Insurance Request Logs</title>
    <link rel="stylesheet" type="text/css" href="../assets/CSS/billingandinsurance.css">
</head>
<body>
<div class="center-wrapper">
    <div class="container">
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
                    <th>ID</th>
                    <th>Patient ID</th>
                    <th>Billing ID</th>
                    <th>Insurance Type</th>
                    <th>Notes</th>
                    <th>Status</th>
                </tr>
                <?php foreach ($requests as $req): ?>
                <tr>
                    <td><?= htmlspecialchars($req['request_id']) ?></td>
                    <td><?= htmlspecialchars($req['patient_id']) ?></td>
                    <td><?= htmlspecialchars($req['billing_id']) ?></td>
                    <td><?= htmlspecialchars($req['insurance_type']) ?></td>
                    <td><?= htmlspecialchars($req['notes']) ?></td>
                    <td><?= ucfirst(htmlspecialchars($req['status'])) ?></td>
                </tr>
                <?php endforeach; ?>
            </table>
            <?php else: ?>
                <div style="margin-top:16px;text-align:center;">No insurance requests found.</div>
            <?php endif; ?>
        </div>
    </div>
</div>
</body>
</html>
