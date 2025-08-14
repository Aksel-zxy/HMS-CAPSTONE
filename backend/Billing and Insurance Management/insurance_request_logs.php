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
<div class="container">
    <h2>Insurance Requests</h2>
    <table>
        <tr>
            <th>ID</th>
            <th>Patient ID</th>
            <th>Billing ID</th>
            <th>Insurance Type</th>
            <th>Coverage Covered</th>
            <th>Notes</th>
            <th>Status</th>
        </tr>
        <?php if (!empty($requests) && is_array($requests)): ?>
            <?php foreach ($requests as $req): ?>
            <tr>
                <td><?= htmlspecialchars($req['request_id']) ?></td>
                <td><?= htmlspecialchars($req['patient_id']) ?></td>
                <td><?= htmlspecialchars($req['billing_id']) ?></td>
                <td><?= htmlspecialchars($req['insurance_type']) ?></td>
                <td><?= htmlspecialchars($req['coverage_covered']) ?></td>
                <td><?= htmlspecialchars($req['notes']) ?></td>
                <td class="status-<?= htmlspecialchars($req['status']) ?>">
                    <?= ucfirst($req['status']) ?>
                </td>
            </tr>
            <?php endforeach; ?>
        <?php else: ?>
            <tr><td colspan="7" style="text-align:center;">No insurance requests found.</td></tr>
        <?php endif; ?>
    </table>
</div>
</body>
</html>
