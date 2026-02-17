<?php
session_start();
include '../../SQL/config.php';

/* ================================
   PAGINATION + SEARCH SETTINGS
================================ */
$limit = 10;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

/* ================================
   FETCH PAYMENTS
================================ */
$payments_sql = "
SELECT
    pp.payment_id,
    pp.amount,
    pp.payment_method,
    pp.paid_at,
    pp.remarks,
    br.billing_id,
    br.patient_id,
    pi.patient_id AS pi_patient_id,
    pi.fname,
    pi.mname,
    pi.lname
FROM paymongo_payments pp
LEFT JOIN billing_records br ON pp.billing_id = br.billing_id
LEFT JOIN patientinfo pi ON br.patient_id = pi.patient_id
";

if (!empty($search)) {
    $payments_sql .= " WHERE 
        pi.fname LIKE '%$search%' OR
        pi.lname LIKE '%$search%' OR
        pp.payment_method LIKE '%$search%' OR
        pp.payment_id LIKE '%$search%'";
}

$payments_sql .= " ORDER BY pp.paid_at DESC LIMIT $limit OFFSET $offset";
$payments = $conn->query($payments_sql)->fetch_all(MYSQLI_ASSOC);

/* ================================
   FETCH RECEIPTS
================================ */
$receipts_sql = "
SELECT
    pr.receipt_id,
    pr.status,
    pr.payment_method,
    br.transaction_id,
    pr.created_at AS receipt_created,
    br.billing_id,
    br.patient_id,
    pi.patient_id AS pi_patient_id,
    pi.fname,
    pi.mname,
    pi.lname,
    br.billing_date,
    br.grand_total,
    br.insurance_covered
FROM billing_records br
INNER JOIN (
    SELECT billing_id, MAX(receipt_id) AS latest_receipt_id
    FROM patient_receipt
    GROUP BY billing_id
) latest ON latest.billing_id = br.billing_id
INNER JOIN patient_receipt pr ON pr.receipt_id = latest.latest_receipt_id
LEFT JOIN patientinfo pi ON pi.patient_id = br.patient_id
";

if (!empty($search)) {
    $receipts_sql .= " WHERE 
        pi.fname LIKE '%$search%' OR
        pi.lname LIKE '%$search%' OR
        pr.payment_method LIKE '%$search%' OR
        pr.receipt_id LIKE '%$search%'";
}

$receipts_sql .= " ORDER BY br.billing_date DESC LIMIT $limit OFFSET $offset";
$receipts = $conn->query($receipts_sql)->fetch_all(MYSQLI_ASSOC);

$total_rows = count($payments) + count($receipts);
$total_pages = ceil($total_rows / $limit);

/* ================================
   HELPER FUNCTION
================================ */
function getPatientName($fname, $mname, $lname, $patient_id = null) {
    $full_name = trim($fname . ' ' . $mname . ' ' . $lname);
    if (!empty($full_name)) return $full_name;
    if (!empty($patient_id)) return "Unknown Patient (ID: {$patient_id})";
    return "Unknown Patient";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Journal Management Module</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

<style>
/* =========================
   LAYOUT FIX FOR SIDEBAR
========================= */

.content-wrapper {
    margin-left: 250px; /* Same as sidebar width */
    padding: 30px;
    transition: margin-left 0.3s ease;
}

/* When sidebar is closed */
.sidebar.closed ~ .content-wrapper {
    margin-left: 0;
}

/* Responsive behavior */
@media (max-width: 768px) {
    .content-wrapper {
        margin-left: 0;
        padding: 15px;
    }
}

/* =========================
   UI STYLING
========================= */

.container-box {
    background: white;
    border-radius: 15px;
    padding: 25px;
}

.table thead {
    background-color: #007bff;
    color: white;
}

.debit { color: green; font-weight: bold; }
.credit { color: red; font-weight: bold; }
.entry-amount { text-align: right; font-weight: 500; }
.reference-info { white-space: pre-line; font-size: 0.85rem; }
.unknown-patient {
    background-color: #fff3cd;
    color: #856404;
    padding: 5px 8px;
    border-radius: 4px;
    font-weight: 500;
}

.btn-view {
    background-color: #17a2b8;
    color: white;
    padding: 5px 10px;
    border-radius: 4px;
    font-size: 12px;
    text-decoration: none;
}

.btn-view:hover {
    background-color: #138496;
    color: white;
}

/* Prevent overflow */
.table-responsive {
    overflow-x: auto;
}
</style>
</head>

<body>

<?php include 'billing_sidebar.php'; ?>

<div class="content-wrapper">
<div class="container-fluid">
<div class="container-box">

<h2>Journal Entries - Payments</h2>
<p class="text-muted">All payment transactions recorded in the billing system</p>

<!-- SEARCH -->
<form method="GET" class="row g-2 mb-3">
    <div class="col-12 col-md-4">
        <input type="text" name="search" class="form-control"
               placeholder="Search patient, method, reference..."
               value="<?= htmlspecialchars($search) ?>">
    </div>
    <div class="col-12 col-md-2">
        <button type="submit" class="btn btn-primary w-100">Search</button>
    </div>
</form>

<div class="table-responsive">
<table class="table table-bordered table-striped align-middle">
<thead>
<tr>
<th>Date</th>
<th>Debit</th>
<th>Credit</th>
<th>Amount</th>
<th>Description</th>
<th>Reference</th>
<th>Action</th>
</tr>
</thead>
<tbody>

<?php foreach ($payments as $p): 
$full_name = getPatientName($p['fname'], $p['mname'], $p['lname'], $p['patient_id']);
?>
<tr>
<td><?= date('Y-m-d H:i:s', strtotime($p['paid_at'])) ?></td>
<td class="debit">Cash / Bank</td>
<td class="credit">Patient Receivable</td>
<td class="entry-amount">₱<?= number_format($p['amount'],2) ?></td>
<td class="reference-info">
<?= nl2br(htmlspecialchars("Payment received from $full_name\nMethod: {$p['payment_method']}")) ?>
</td>
<td><?= $p['payment_id'] ?></td>
<td>
<a href="journal_entry_line.php?payment_id=<?= $p['payment_id'] ?>" class="btn-view">View</a>
</td>
</tr>
<?php endforeach; ?>

<?php foreach ($receipts as $r): 
$full_name = getPatientName($r['fname'], $r['mname'], $r['lname'], $r['patient_id']);
$status_badge = $r['status'] == 'Posted'
    ? '<span class="badge bg-success">Posted</span>'
    : '<span class="badge bg-warning text-dark">Draft</span>';
?>
<tr>
<td><?= date('Y-m-d H:i:s', strtotime($r['receipt_created'])) ?></td>
<td class="debit">Cash / Bank</td>
<td class="credit">Patient Receivable</td>
<td class="entry-amount">₱<?= number_format($r['grand_total'],2) ?></td>
<td class="reference-info">
<?= $status_badge ?><br>
<?= nl2br(htmlspecialchars("Payment received from $full_name\nMethod: {$r['payment_method']}")) ?>
</td>
<td><?= $r['receipt_id'] ?></td>
<td>
<a href="journal_entry_line.php?receipt_id=<?= $r['receipt_id'] ?>" class="btn-view">View</a>
</td>
</tr>
<?php endforeach; ?>

</tbody>
</table>
</div>

</div>
</div>
</div>

</body>
</html>
