<?php
require_once 'db.php';

// Handle approve/decline form submission
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

    echo "<script>alert('Insurance request updated.');window.location='insurance_approval.php';</script>";
    exit;
}

// Fetch all insurance requests with patient info
$sql = "
    SELECT ir.*, p.fname, p.mname, p.lname
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
<style>
.table td, .table th { vertical-align: middle; }
</style>
</head>
<body class="p-4">
<div class="container">
    <h2>Insurance Requests</h2>
    <table class="table table-bordered table-striped">
        <thead class="table-dark">
            <tr>
                <th>Name</th>
                <th>Insurance Company</th>
                <th>Insurance Number</th>
                <th>Request Date</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php if($result && $result->num_rows > 0): ?>
                <?php while($row = $result->fetch_assoc()): ?>
                    <?php 
                        $disable_form = in_array($row['status'], ['Approved','Declined']);
                        $full_name = htmlspecialchars($row['fname'].' '.(!empty($row['mname'])?$row['mname'].' ':'').$row['lname']);
                    ?>
                    <tr>
                        <td><?= $full_name ?></td>
                        <td><?= htmlspecialchars($row['insurance_company']); ?></td>
                        <td><?= htmlspecialchars($row['insurance_number']); ?></td>
                        <td><?= htmlspecialchars($row['request_date']); ?></td>
                        <td>
                            <?php
                            // Get latest receipt_id for this patient
                            $stmtReceipt = $conn->prepare("SELECT receipt_id FROM patient_receipt WHERE patient_id=? ORDER BY receipt_id DESC LIMIT 1");
                            $stmtReceipt->bind_param("i", $row['patient_id']);
                            $stmtReceipt->execute();
                            $receiptRes = $stmtReceipt->get_result()->fetch_assoc();
                            $receipt_id = $receiptRes['receipt_id'] ?? 0;
                            ?>

                            <!-- Direct View Receipt Link -->
                            <?php if($receipt_id): ?>
                                <a href="print_receipt.php?receipt_id=<?= $receipt_id ?>" target="_blank" class="btn btn-sm btn-info">View Receipt</a>
                            <?php else: ?>
                                <span class="text-muted">No finalized receipt</span>
                            <?php endif; ?>

                            <!-- Approval Form -->
                            <form method="POST" class="d-inline-block ms-2">
                                <input type="hidden" name="request_id" value="<?= $row['request_id'] ?>">
                                <select name="status" class="form-select form-select-sm d-inline w-auto approve-status" data-request="<?= $row['request_id'] ?>" <?= $disable_form?'disabled':'' ?>>
                                    <option value="Pending" <?= $row['status']=='Pending'?'selected':'' ?>>Pending</option>
                                    <option value="Approved" <?= $row['status']=='Approved'?'selected':'' ?>>Approved</option>
                                    <option value="Declined" <?= $row['status']=='Declined'?'selected':'' ?>>Declined</option>
                                </select>
                                <input type="number" name="covered_amount" class="form-control form-control-sm d-inline w-auto covered-amount" step="0.01" value="<?= number_format($row['covered_amount'],2,'.',''); ?>" <?= $disable_form || $row['status']=='Declined'?'readonly':'' ?>>
                                <button type="submit" class="btn btn-sm btn-success" <?= $disable_form?'disabled':'' ?>>Save</button>
                            </form>
                        </td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr><td colspan="5" class="text-center">No insurance requests found.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Enable/disable covered_amount based on status selection
document.querySelectorAll('.approve-status').forEach(select => {
    select.addEventListener('change', function() {
        const reqId = this.dataset.request;
        const coveredInput = document.querySelector('.covered-amount[data-request="'+reqId+'"]') || this.closest('form').querySelector('.covered-amount');
        if(this.value === 'Declined') {
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
