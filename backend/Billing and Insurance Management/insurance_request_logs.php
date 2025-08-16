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
?>
<!DOCTYPE html>
<html>
<head>
    <title>Insurance Request Logs</title>
    <link rel="stylesheet" href="../assets/CSS/billingandinsurance.css">
</head>
<body>
<div class="center-wrapper">
    <div class="container">
        <h2>Insurance Request Logs</h2>
        <div class="request-table">
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
                <?php if (!empty($requests) && is_array($requests)): ?>
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
                                Pending
                            <?php else: ?>
                                <span style="color:gray;">No action</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="7" style="text-align:center;">No insurance requests found.</td></tr>
                <?php endif; ?>
            </table>
        </div>
    </div>
</div>
</body>
</html>
