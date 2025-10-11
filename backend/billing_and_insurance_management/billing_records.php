<?php
include '../../SQL/config.php';

// ✅ Start session only if not already active
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ✅ Handle marking payment as Paid
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_paid'])) {
    $receipt_id = intval($_POST['receipt_id']);
    $stmt = $conn->prepare("UPDATE patient_receipt SET status = 'Paid' WHERE receipt_id = ?");
    $stmt->bind_param("i", $receipt_id);
    $stmt->execute();
    echo "<script>alert('Payment marked as Paid.');window.location='billing_records.php';</script>";
    exit;
}

// ✅ Search functionality
$search = '';
if (isset($_GET['search']) && !empty($_GET['search'])) {
    $search = $_GET['search'];
    $search_param = "%$search%";
    $sql = "
        SELECT pr.*, br.total_amount, br.out_of_pocket, br.grand_total AS billing_grand_total, 
               pi.fname, pi.mname, pi.lname
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
        SELECT pr.*, br.total_amount, br.out_of_pocket, br.grand_total AS billing_grand_total, 
               pi.fname, pi.mname, pi.lname
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
</head>
<body class="p-4 bg-light">



<div class="container">
    <h3 class="mb-4">🧾 Patient Billing Records</h3>

    <!-- Search form -->
    <form class="row mb-4" method="GET">
        <div class="col-md-6">
            <input type="text" name="search" class="form-control" placeholder="Search by patient name or transaction ID" value="<?= htmlspecialchars($search) ?>">
        </div>
        <div class="col-md-3">
            <button type="submit" class="btn btn-primary me-2">Search</button>
            <a href="billing_records.php" class="btn btn-secondary">Reset</a>
        </div>
    </form>

    <!-- Billing table -->
    <div class="table-responsive">
        <table class="table table-bordered table-striped align-middle text-center">
            <thead class="table-dark">
                <tr>
                    <th>Patient Name</th>
                    <th>Billing Date</th>
                    <th>Total Amount</th>
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
                            $full_name = $row['fname'] . ' ' . (!empty($row['mname']) ? $row['mname'].' ' : '') . $row['lname'];
                            $total = $row['grand_total'] ?: $row['billing_grand_total'] ?: $row['total_amount'];
                            $grand_total = number_format($total, 2);
                        ?>
                        <tr>
                            <td><?= htmlspecialchars($full_name) ?></td>
                            <td><?= htmlspecialchars($row['billing_date']) ?></td>
                            <td>₱<?= $grand_total ?></td>
                            <td>
                                <?php if ($row['status'] === 'Pending'): ?>
                                    <span class="badge bg-warning text-dark">Pending</span>
                                <?php else: ?>
                                    <span class="badge bg-success">Paid</span>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($row['payment_method'] ?: 'Unpaid') ?></td>
                            <td><?= htmlspecialchars($row['transaction_id'] ?: '-') ?></td>
                            <td>
                                <a href="print_receipt.php?receipt_id=<?= $row['receipt_id'] ?>" 
                                   class="btn btn-info btn-sm" target="_blank">Print</a>

                                <?php if ($row['status'] === 'Pending'): ?>
                                    <form method="POST" style="display:inline-block;" 
                                          onsubmit="return confirm('Mark this payment as Paid?');">
                                        <input type="hidden" name="receipt_id" value="<?= $row['receipt_id'] ?>">
                                        <button type="submit" name="mark_paid" class="btn btn-success btn-sm">Mark Paid</button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7" class="text-center">No billing records found.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

<div class="main-sidebar">
<?php include 'billing_sidebar.php'; ?>
</div>

</div>

</body>
</html>
