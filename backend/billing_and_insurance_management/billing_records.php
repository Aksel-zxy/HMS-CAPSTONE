<?php
include '../../SQL/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/* ===============================
   SEARCH
================================ */
$search = '';
$search_param = '';

if (!empty($_GET['search'])) {
    $search = trim($_GET['search']);
    $search_param = "%$search%";
}

/* ===============================
   MAIN QUERY (DEDUPED)
   - ONE ROW PER BILLING
   - LATEST RECEIPT ONLY
================================ */
$sql = "
SELECT
    pr.receipt_id,
    pr.status,
    pr.payment_method,
    br.transaction_id,
    pr.created_at AS receipt_created,

    pi.patient_id,
    pi.fname,
    pi.mname,
    pi.lname,

    br.billing_id,
    br.billing_date,
    br.grand_total,
    br.insurance_covered

FROM billing_records br

INNER JOIN (
    SELECT billing_id, MAX(receipt_id) AS latest_receipt_id
    FROM patient_receipt
    GROUP BY billing_id
) latest ON latest.billing_id = br.billing_id

INNER JOIN patient_receipt pr
    ON pr.receipt_id = latest.latest_receipt_id

INNER JOIN patientinfo pi
    ON pi.patient_id = br.patient_id
";

if ($search_param) {
    $sql .= "
    WHERE
        pi.fname LIKE ?
        OR pi.lname LIKE ?
        OR br.transaction_id LIKE ?
    ";
}

$sql .= " ORDER BY br.billing_date DESC";

$stmt = $conn->prepare($sql);

if ($search_param) {
    $stmt->bind_param("sss", $search_param, $search_param, $search_param);
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
<link rel="stylesheet" href="assets/css/billing_sidebar.css">
</head>

<body class="p-4 bg-light">

<div class="container">

<div style="
    background-color:white;
    border-radius:30px;
    padding:30px;
    box-shadow:0 2px 8px rgba(0,0,0,0.1);
    margin-top:80px;
    margin-left:100px;
">

<h1 class="mb-4">Patient Billing Records</h1>

<form class="row mb-4" method="GET">
    <div class="col-md-6">
        <input type="text"
               name="search"
               class="form-control"
               placeholder="Search by patient name or transaction ID"
               value="<?= htmlspecialchars($search) ?>">
    </div>
    <div class="col-md-3">
        <button class="btn btn-primary me-2">Search</button>
        <a href="billing_records.php" class="btn btn-secondary">Reset</a>
    </div>
</form>

<div class="table-responsive">
<table class="table table-bordered table-striped align-middle">
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
<?php while ($row = $result->fetch_assoc()): ?>

<?php
$full_name = trim(
    $row['fname'] . ' ' .
    (!empty($row['mname']) ? $row['mname'] . ' ' : '') .
    $row['lname']
);

$status = strtolower($row['status']);
$transaction_id = $row['transaction_id'] ?: '-';
$payment_method = $row['payment_method'] ?: 'Unpaid';
?>

<tr>
    <td><?= htmlspecialchars($full_name) ?></td>
    <td><?= htmlspecialchars($row['billing_date']) ?></td>
    <td>₱<?= number_format((float)$row['grand_total'], 2) ?></td>
    <td>₱<?= number_format((float)$row['insurance_covered'], 2) ?></td>

    <td>
        <?php if ($status === 'paid'): ?>
            <span class="badge bg-success">Paid</span>
        <?php else: ?>
            <span class="badge bg-warning text-dark">Pending</span>
        <?php endif; ?>
    </td>

    <td><?= htmlspecialchars($payment_method) ?></td>
    <td><?= htmlspecialchars($transaction_id) ?></td>

    <td>
        <?php if (!empty($row['receipt_id'])): ?>
            <a href="print_receipt.php?receipt_id=<?= $row['receipt_id'] ?>"
               target="_blank"
               class="btn btn-info btn-sm">
               Print
            </a>
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
