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
