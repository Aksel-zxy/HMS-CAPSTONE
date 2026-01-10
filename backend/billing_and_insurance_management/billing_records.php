<?php
include '../../SQL/config.php';

// Start session only if not already active
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Search functionality
$search = '';
$search_param = '';
if (isset($_GET['search']) && !empty($_GET['search'])) {
    $search = trim($_GET['search']);
    $search_param = "%$search%";
}

// Query: join patient_receipt and patientinfo; get latest billing info
if ($search_param) {
    $sql = "
        SELECT pr.*, pi.fname, pi.mname, pi.lname, br.total_amount, br.out_of_pocket, br.grand_total AS billing_grand_total, br.billing_date AS br_billing_date
        FROM patient_receipt pr
        JOIN patientinfo pi ON pr.patient_id = pi.patient_id
        LEFT JOIN billing_records br ON pr.billing_id = br.billing_id
        WHERE pi.fname LIKE ? OR pi.lname LIKE ? OR pr.transaction_id LIKE ?
        ORDER BY pr.created_at DESC
    ";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sss", $search_param, $search_param, $search_param);
} else {
    $sql = "
        SELECT pr.*, pi.fname, pi.mname, pi.lname, br.total_amount, br.out_of_pocket, br.grand_total AS billing_grand_total, br.billing_date AS br_billing_date
        FROM patient_receipt pr
        JOIN patientinfo pi ON pr.patient_id = pi.patient_id
        LEFT JOIN billing_records br ON pr.billing_id = br.billing_id
        ORDER BY pr.created_at DESC
    ";
    $stmt = $conn->prepare($sql);
}

$stmt->execute();
$result = $stmt->get_result();
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Billing Records</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" type="text/css" href="assets/css/billing_sidebar.css">
</head>
<body class="p-4 bg-light">

<div class="container">
<div style="background-color: white; border-radius: 30px; padding: 30px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); margin-top: 80px; margin-left: 100px;">
    <h1 class="mb-4">Patient Billing Records</h1>

    <form class="row mb-4" method="GET">
        <div class="col-md-6">
            <input type="text" name="search" class="form-control" placeholder="Search by patient name or transaction ID" value="<?= htmlspecialchars($search) ?>">
        </div>
        <div class="col-md-3">
            <button type="submit" class="btn btn-primary me-2">Search</button>
            <a href="billing_records.php" class="btn btn-secondary">Reset</a>
        </div>
    </form>

    <div class="table-responsive">
        <table class="table table-bordered table-striped align-middle text-left">
            <thead class="table-dark">
                <tr>
                    <th>Patient Name</th>
                    <th>Billing Date</th>
                    <th>Total Amount</th>
                    <th>Insurance Covered</th>
                    <th>Status</th>
                    <th>Payment Method</th>
                    <th>Transaction ID</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
            <?php if ($result->num_rows > 0): ?>
                <?php while($row = $result->fetch_assoc()): ?>
                    <?php
                        // Patient full name
                        $full_name = trim($row['fname'] . ' ' . (!empty($row['mname']) ? $row['mname'].' ' : '') . $row['lname']);
                        
                        // Billing date fallback
                        $billing_date = $row['billing_date'] ?? $row['br_billing_date'] ?? 'N/A';
                        
                        // Grand total fallback
                        $grand_total = $row['grand_total'] ?? $row['billing_grand_total'] ?? $row['total_amount'] ?? 0;
                        $grand_total_fmt = number_format((float)$grand_total, 2);

                        // Insurance covered
                        $insurance_covered = $row['insurance_covered'] ?? 0;
                        $insurance_covered_fmt = number_format((float)$insurance_covered, 2);

                        // Status
                        $status = $row['status'] ?? 'Pending';
                        $payment_method = $row['payment_method'] ?? 'Unpaid';
                        $transaction_id = $row['transaction_id'] ?? '-';
                        $receipt_id = $row['receipt_id'] ?? 0;
                    ?>
                    <tr>
                        <td><?= htmlspecialchars($full_name) ?></td>
                        <td><?= htmlspecialchars($billing_date) ?></td>
                        <td>₱<?= $grand_total_fmt ?></td>
                        <td>₱<?= $insurance_covered_fmt ?></td>
                        <td>
                            <?php if (strtolower($status) === 'paid'): ?>
                                <span class="badge bg-success">Paid</span>
                            <?php else: ?>
                                <span class="badge bg-warning text-dark">Pending</span>
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($payment_method) ?></td>
                        <td><?= htmlspecialchars($transaction_id) ?></td>
                        <td>
                            <?php if (!empty($receipt_id) && $receipt_id > 0): ?>
                                <a href="patient_receipt.php?receipt_id=<?= $receipt_id ?>" target="_blank" class="btn btn-info btn-sm">Print</a>
                            <?php else: ?>
                                <span class="text-muted">N/A</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr>
                    <td colspan="8" class="text-center">No billing records found.</td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="main-sidebar">
    <?php include 'billing_sidebar.php'; ?>
</div>
</div>
</body>
</html>
