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
   FETCH PAYMONGO PAYMENTS
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
<title>Journal Management Module</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
body { background-color: #f8f9fa; }
.container-wrapper { background-color: white; border-radius: 20px; padding: 30px; margin-top: 50px; }
.table thead { background-color: #007bff; color: white; }
.debit { color: green; font-weight: bold; }
.credit { color: red; font-weight: bold; }
.btn-view { background-color: #17a2b8; color: white; padding: 6px 12px; border-radius: 4px; text-decoration: none; font-size: 12px; }
.btn-view:hover { background-color: #138496; }
.reference-info { white-space: pre-line; font-size: 0.85em; }
.unknown-patient { background-color: #fff3cd; color: #856404; padding: 8px; border-radius: 4px; font-weight: 500; }
.entry-amount { text-align: right; font-weight: 500; }
.pagination .page-link { border-radius: 6px; }
</style>
</head>
<body class="p-4">

<div class="main-sidebar">
<?php include 'billing_sidebar.php'; ?>
</div>

<div class="container container-wrapper">
<h2>Journal Entries - Payments</h2>
<p class="text-muted">All payment transactions recorded in the billing system</p>

<!-- SEARCH BAR -->
<form method="GET" class="row mb-3">
    <div class="col-md-4">
        <input type="text" name="search" class="form-control"
               placeholder="Search patient, method, reference..."
               value="<?= htmlspecialchars($search) ?>">
    </div>
    <div class="col-md-2">
        <button type="submit" class="btn btn-primary">Search</button>
    </div>
</form>

<table class="table table-bordered table-striped">
<thead>
<tr>
<th>Date</th>
<th>Debit Account</th>
<th>Credit Account</th>
<th>Amount</th>
<th>Description</th>
<th>Reference</th>
<th>Action</th>
</tr>
</thead>
<tbody>

<?php foreach ($payments as $p): 
$full_name = getPatientName($p['fname'] ?? '', $p['mname'] ?? '', $p['lname'] ?? '', $p['patient_id'] ?? null);
$is_unknown = strpos($full_name, 'Unknown') === 0;
$method = $p['payment_method'] ?: 'CASH';
$remarks = $p['remarks'] ?: "Billing #" . ($p['billing_id'] ?? 'N/A');
$description = "Payment received from {$full_name}\nMethod: {$method}\nRemarks: {$remarks}";
?>
<tr <?= $is_unknown ? 'style="background-color:#fff3cd;"' : '' ?>>
<td><?= date('Y-m-d H:i:s', strtotime($p['paid_at'])) ?></td>
<td class="debit">Cash / Bank</td>
<td class="credit">Patient Receivable</td>
<td class="entry-amount">₱<?= number_format($p['amount'],2) ?></td>
<td class="reference-info">
<?php if ($is_unknown): ?>
<span class="unknown-patient">⚠️ <?= htmlspecialchars($full_name) ?></span><br>
<?php endif; ?>
<?= nl2br(htmlspecialchars($description)) ?>
</td>
<td><?= htmlspecialchars($p['payment_id']) ?></td>
<td>
<a href="journal_entry_line.php?payment_id=<?= urlencode($p['payment_id']) ?>" class="btn-view">View</a>
</td>
</tr>
<?php endforeach; ?>

<?php foreach ($receipts as $r): 
$full_name = getPatientName($r['fname'] ?? '', $r['mname'] ?? '', $r['lname'] ?? '', $r['patient_id'] ?? null);
$is_unknown = strpos($full_name, 'Unknown') === 0;
$method = $r['payment_method'] ?: 'CASH';
$status_badge = $r['status'] == 'Posted'
    ? '<span class="badge bg-success">Posted</span>'
    : '<span class="badge bg-warning text-dark">Draft</span>';
$description = "Payment received from {$full_name}\nMethod: {$method}\nBilling #" . ($r['billing_id'] ?? 'N/A');
?>
<tr <?= $is_unknown ? 'style="background-color:#fff3cd;"' : '' ?>>
<td><?= date('Y-m-d H:i:s', strtotime($r['receipt_created'])) ?></td>
<td class="debit">Cash / Bank</td>
<td class="credit">Patient Receivable</td>
<td class="entry-amount">₱<?= number_format($r['grand_total'],2) ?></td>
<td class="reference-info">
<?= $status_badge ?><br>
<?php if ($is_unknown): ?>
<span class="unknown-patient">⚠️ <?= htmlspecialchars($full_name) ?></span><br>
<?php endif; ?>
<?= nl2br(htmlspecialchars($description)) ?>
</td>
<td><?= htmlspecialchars($r['receipt_id']) ?></td>
<td>
<a href="journal_entry_line.php?receipt_id=<?= urlencode($r['receipt_id']) ?>" class="btn-view">View</a>
</td>
</tr>
<?php endforeach; ?>

<?php if (empty($payments) && empty($receipts)): ?>
<tr>
<td colspan="7" class="text-center text-muted py-4">
No payment entries found.
</td>
</tr>
<?php endif; ?>

</tbody>
</table>

<!-- PAGINATION -->
<?php if ($total_pages > 1): ?>
<nav>
<ul class="pagination justify-content-center mt-4">

<?php if ($page > 1): ?>
<li class="page-item">
<a class="page-link" href="?page=<?= $page-1 ?>&search=<?= urlencode($search) ?>">Previous</a>
</li>
<?php endif; ?>

<?php for ($i=1; $i<=$total_pages; $i++): ?>
<li class="page-item <?= $i==$page ? 'active':'' ?>">
<a class="page-link" href="?page=<?= $i ?>&search=<?= urlencode($search) ?>">
<?= $i ?>
</a>
</li>
<?php endfor; ?>

<?php if ($page < $total_pages): ?>
<li class="page-item">
<a class="page-link" href="?page=<?= $page+1 ?>&search=<?= urlencode($search) ?>">Next</a>
</li>
<?php endif; ?>

</ul>
</nav>
<?php endif; ?>

</div>

</body>
</html>
