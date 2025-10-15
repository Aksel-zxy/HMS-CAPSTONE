<?php
include '../../SQL/config.php';

// ✅ Fetch patients who have lab results but no paid receipt yet
$sql = "
SELECT 
    p.patient_id,
    CONCAT(p.fname, ' ', IFNULL(p.mname, ''), ' ', p.lname) AS full_name,
    COALESCE(ir.status, 'N/A') AS insurance_status,
    (
        SELECT pr.status 
        FROM patient_receipt pr 
        WHERE pr.patient_id = p.patient_id
        ORDER BY pr.created_at DESC 
        LIMIT 1
    ) AS payment_status
FROM patientinfo p
LEFT JOIN insurance_requests ir 
    ON p.patient_id = ir.patient_id
    AND ir.request_id = (
        SELECT MAX(request_id) 
        FROM insurance_requests 
        WHERE patient_id = p.patient_id
    )
WHERE EXISTS (
    SELECT 1 
    FROM dl_results dr
    WHERE dr.patientID = p.patient_id
)
AND (
    SELECT pr.status 
    FROM patient_receipt pr 
    WHERE pr.patient_id = p.patient_id
    ORDER BY pr.created_at DESC
    LIMIT 1
) IS NULL OR (
    SELECT pr.status 
    FROM patient_receipt pr 
    WHERE pr.patient_id = p.patient_id
    ORDER BY pr.created_at DESC
    LIMIT 1
) != 'Paid'
ORDER BY p.lname ASC, p.fname ASC
";

$result = $conn->query($sql);
?>


<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Patient Billing</title>
<link rel="stylesheet" href="assets/CSS/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
<link rel="stylesheet" href="assets/CSS/patient_billing.css">
<link rel="stylesheet" type="text/css" href="assets/css/billing_sidebar.css">
</head>
<body>
<div class="dashboard-wrapper">

    <!-- Main Content -->
    <div class="main-content-wrapper" id="mainContent">
        <div class="container-fluid bg-white p-4 rounded shadow">
            <h1 class="mb-4">Patient Billing</h1>
            <table class="table table-bordered table-striped">
                <thead class="table-dark">
                    <tr>
                        <th>Patient Name</th>
                        <th>Insurance Status</th>
                        <th>Remarks</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result && $result->num_rows > 0): ?>
                        <?php while ($row = $result->fetch_assoc()): ?>
                            <?php 
                                $insuranceStatus = $row['insurance_status'] ?? 'N/A';
                                $paymentStatus = $row['payment_status'] ?? 'Pending';
                                $disableBill = ($insuranceStatus === 'Pending') || ($paymentStatus === 'Paid');
                                $showInsuranceButton = ($insuranceStatus === 'N/A');
                                $rowClass = $insuranceStatus === 'Pending' ? 'pending-insurance' : '';
                            ?>
                            <tr class="<?= $rowClass ?>">
                                <td><?= htmlspecialchars($row['full_name']); ?></td>
                                <td>
                                    <?php if ($insuranceStatus === 'Pending'): ?>
                                        <span class="badge bg-warning text-dark">Pending</span>
                                    <?php elseif ($insuranceStatus === 'Approved'): ?>
                                        <span class="badge bg-success">Approved</span>
                                    <?php elseif ($insuranceStatus === 'Rejected'): ?>
                                        <span class="badge bg-danger">Rejected</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">N/A</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($paymentStatus === 'Paid'): ?>
                                        <span class="badge bg-success">Paid</span>
                                    <?php else: ?>
                                        <span class="badge bg-warning text-dark">Pending</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <div class="d-flex justify-content-end gap-2 align-items-center flex-wrap">
                                        <?php if ($disableBill): ?>
                                            <button class="btn btn-success btn-sm" disabled>
                                                <?= $paymentStatus === 'Paid' ? 'Already Paid' : 'Generate Bill'; ?>
                                            </button>
                                            <?php if ($insuranceStatus === 'Pending'): ?>
                                                <i class="bi bi-question-circle-fill tooltip-icon" 
                                                   data-bs-toggle="tooltip" 
                                                   data-bs-placement="top" 
                                                   title="Cannot generate bill until insurance request is resolved."></i>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <a href="billing_summary.php?patient_id=<?= $row['patient_id']; ?>" 
                                               class="btn btn-success btn-sm">
                                               Generate Bill
                                            </a>
                                        <?php endif; ?>

                                        <?php if ($showInsuranceButton): ?>
                                            <button class="btn btn-info btn-sm" data-bs-toggle="modal" data-bs-target="#insuranceModal<?= $row['patient_id'] ?>">
                                                Insurance Request
                                            </button>
                                        <?php endif; ?>
                                    </div>

                                    <!-- Insurance Modal -->
                                    <?php if ($showInsuranceButton): ?>
                                    <div class="modal fade" id="insuranceModal<?= $row['patient_id'] ?>" tabindex="-1">
                                        <div class="modal-dialog">
                                            <form method="POST" action="request_insurance.php" class="modal-content">
                                                <div class="modal-header">
                                                    <h5 class="modal-title">Insurance Request Form</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <div class="mb-3">
                                                        <label>Name:</label>
                                                        <input type="text" value="<?= htmlspecialchars($row['full_name']); ?>" class="form-control" readonly>
                                                    </div>
                                                    <input type="hidden" name="patient_id" value="<?= $row['patient_id'] ?>">
                                                    <div class="mb-3">
                                                        <label>Insurance Company</label>
                                                        <input type="text" name="insurance_company" class="form-control" required>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label>Insurance Number</label>
                                                        <input type="text" name="insurance_number" class="form-control" required>
                                                    </div>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="submit" class="btn btn-primary">Send Request</button>
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="4" class="text-center">No patients with completed results pending billing.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Sidebar -->
    <div class="main-sidebar">
        <?php include 'billing_sidebar.php'; ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.forEach(function (tooltipTriggerEl) {
        new bootstrap.Tooltip(tooltipTriggerEl);
    });
});
</script>
</body>
</html>
