<?php
include '../../SQL/config.php';

// ✅ Handle approve/decline form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_id'])) {
    $request_id = intval($_POST['request_id']);
    $status = $_POST['status'];
    $covered_amount = $status === 'Approved' ? floatval($_POST['covered_amount']) : 0.0;
    $response_date = date('Y-m-d H:i:s');

    $stmt = $conn->prepare("
        UPDATE insurance_requests
        SET status = ?, covered_amount = ?, response_date = ?
        WHERE request_id = ?
    ");
    $stmt->bind_param("sdsi", $status, $covered_amount, $response_date, $request_id);
    $stmt->execute();

    echo "<script>alert('Insurance request updated successfully.');window.location='insurance_approval.php';</script>";
    exit;
}

// ✅ Fetch all insurance requests with patient info
$sql = "
    SELECT 
        ir.*, 
        p.fname, p.mname, p.lname, 
        p.address, p.dob, p.phone_number
    FROM insurance_requests ir
    JOIN patientinfo p ON ir.patient_id = p.patient_id
    ORDER BY ir.request_date DESC
";
$result = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Insurance Approval</title>
<link rel="stylesheet" href="assets/CSS/bootstrap.min.css">
<link rel="stylesheet" type="text/css" href="assets/css/billing_sidebar.css">
<style>
.table td, .table th { vertical-align: middle; }
.badge-status { font-size: 0.9em; }
.btn-group-sm .btn { margin-bottom: 3px; }
</style>
</head>
<body class="p-4 bg-light">
<div class="container bg-white p-4 rounded shadow">
    <h2 class="mb-4">Insurance Requests</h2>
    <table class="table table-bordered table-striped align-middle">
        <thead class="table-dark">
            <tr>
                <th>Name</th>
                <th>DOB</th>
                <th>Address</th>
                <th>Contact</th>
                <th>Insurance Company</th>
                <th>Insurance Number</th>
                <th>Relationship to Insured</th>
                <th>Request Date</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if($result && $result->num_rows > 0): ?>
                <?php while($row = $result->fetch_assoc()): ?>
                    <?php 
                        $disable_form = in_array($row['status'], ['Approved','Declined']);
                        $full_name = htmlspecialchars($row['fname'].' '.(!empty($row['mname'])?$row['mname'].' ':'').$row['lname']);
                        $dob = htmlspecialchars($row['dob']);
                        $address = htmlspecialchars($row['address']);
                        $contact = htmlspecialchars($row['phone_number']);
                        $relationship = htmlspecialchars($row['relationship_to_insured'] ?? '');
                    ?>

                    <tr>
                        <td><?= $full_name ?></td>
                        <td><?= $dob ?></td>
                        <td><?= $address ?></td>
                        <td><?= $contact ?></td>
                        <td><?= htmlspecialchars($row['insurance_company']); ?></td>
                        <td><?= htmlspecialchars($row['insurance_number']); ?></td>
                        <td><?= $relationship ?></td>
                        <td><?= htmlspecialchars($row['request_date']); ?></td>
                        <td>
                            <?php if ($row['status'] === 'Approved'): ?>
                                <span class="badge bg-success badge-status">Approved</span>
                            <?php elseif ($row['status'] === 'Declined'): ?>
                                <span class="badge bg-danger badge-status">Declined</span>
                            <?php else: ?>
                                <span class="badge bg-warning text-dark badge-status">Pending</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="btn-group btn-group-sm d-flex flex-column align-items-start">
                                <?php
                                // ✅ Check for existing receipt or total bill
                                $stmtReceipt = $conn->prepare("SELECT receipt_id FROM patient_receipt WHERE patient_id=? ORDER BY receipt_id DESC LIMIT 1");
                                $stmtReceipt->bind_param("i", $row['patient_id']);
                                $stmtReceipt->execute();
                                $receiptRes = $stmtReceipt->get_result()->fetch_assoc();
                                $receipt_id = $receiptRes['receipt_id'] ?? 0;

                                // ✅ Check if patient has lab results (for total_bill preview)
                                $stmtCheck = $conn->prepare("SELECT COUNT(*) AS total FROM dl_results WHERE patientID=?");
                                $stmtCheck->bind_param("i", $row['patient_id']);
                                $stmtCheck->execute();
                                $res = $stmtCheck->get_result()->fetch_assoc();
                                $hasResults = $res['total'] > 0;
                                ?>

                                <!-- ✅ View Bill Button -->
                                <?php if($receipt_id): ?>
                                    <a href="print_receipt.php?receipt_id=<?= $receipt_id ?>" 
                                       target="_blank" 
                                       class="btn btn-info w-100 mb-1">
                                        View Final Bill
                                    </a>
                                <?php elseif($hasResults): ?>
                                    <a href="total_bill.php?patient_id=<?= $row['patient_id'] ?>" 
                                       target="_blank" 
                                       class="btn btn-secondary w-100 mb-1">
                                        View Total Bill
                                    </a>
                                <?php else: ?>
                                    <button class="btn btn-secondary w-100 mb-1" disabled>No Bill Available</button>
                                <?php endif; ?>

                                <!-- ✅ Approval Form -->
                                <form method="POST" class="w-100">
                                    <input type="hidden" name="request_id" value="<?= $row['request_id'] ?>">
                                    <div class="d-flex align-items-center flex-wrap gap-1">
                                        <select name="status" class="form-select form-select-sm approve-status flex-grow-1" data-request="<?= $row['request_id'] ?>" <?= $disable_form?'disabled':'' ?>>
                                            <option value="Pending" <?= $row['status']=='Pending'?'selected':'' ?>>Pending</option>
                                            <option value="Approved" <?= $row['status']=='Approved'?'selected':'' ?>>Approved</option>
                                            <option value="Declined" <?= $row['status']=='Declined'?'selected':'' ?>>Declined</option>
                                        </select>
                                        <input type="number" 
                                               name="covered_amount" 
                                               class="form-control form-control-sm covered-amount flex-grow-1" 
                                               step="0.01" 
                                               data-request="<?= $row['request_id'] ?>"
                                               value="<?= number_format($row['covered_amount'],2,'.',''); ?>" 
                                               <?= $disable_form || $row['status']=='Declined'?'readonly':'' ?>>
                                        <button type="submit" class="btn btn-success btn-sm" <?= $disable_form?'disabled':'' ?>>Save</button>
                                    </div>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr><td colspan="10" class="text-center">No insurance requests found.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// ✅ Enable/disable covered_amount based on status selection
document.querySelectorAll('.approve-status').forEach(select => {
    select.addEventListener('change', function() {
        const reqId = this.dataset.request;
        const coveredInput = document.querySelector('.covered-amount[data-request="'+reqId+'"]') || this.closest('form').querySelector('.covered-amount');
        if (this.value === 'Declined') {
            coveredInput.value = 0;
            coveredInput.readOnly = true;
        } else {
            coveredInput.readOnly = false;
        }
    });
});
</script>
</body>
</html>
