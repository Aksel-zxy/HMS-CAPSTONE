<?php
session_start();
include '../../SQL/config.php';

/* =========================
   Validate entry_id
========================= */
$entry_id = isset($_GET['entry_id']) ? intval($_GET['entry_id']) : 0;
if ($entry_id <= 0) {
    header("Location: journal_entry.php");
    exit;
}

/* =========================
   Fetch Journal Entry
========================= */
$stmt = $conn->prepare("SELECT * FROM journal_entries WHERE entry_id = ?");
$stmt->bind_param("i", $entry_id);
$stmt->execute();
$entry = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$entry) {
    header("Location: journal_entry.php");
    exit;
}

/* =========================
   Fetch Journal Entry Lines
========================= */
$stmt = $conn->prepare("SELECT * FROM journal_entry_lines WHERE entry_id = ?");
$stmt->bind_param("i", $entry_id);
$stmt->execute();
$lines = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

/* =========================
   Calculate Totals Safely
========================= */
$total_debit = 0;
$total_credit = 0;
foreach ($lines as $line) {
    $total_debit += floatval($line['debit'] ?? 0);
    $total_credit += floatval($line['credit'] ?? 0);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Journal Entry Lines - Entry #<?= htmlspecialchars($entry['entry_id'] ?? '') ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" type="text/css" href="assets/css/billing_sidebar.css">
<link rel="stylesheet" type="text/css" href="assets/CSS/journal_entry_lines_container.css">

<style>
body { background-color: #f8f9fa; }
.container-wrapper { 
    background-color: white; 
    border-radius: 30px; 
    padding: 30px; 
    box-shadow: 0 2px 8px rgba(0,0,0,0.1); 
    margin-top: 80px; 
    margin-left: 100px;
}
.entry-info { display: flex; gap: 20px; margin-bottom: 15px; flex-wrap: wrap; }
.info-item .label { font-weight: bold; }
.badge.posted { background-color: #28a745; }
.badge.draft { background-color: #ffc107; color: #000; }
.table-responsive { margin-bottom: 20px; }
th.amount-col, td.amount-col { text-align: right; }
tr.total { font-weight: bold; background-color: #f8f9fa; }
.actions { margin-top: 20px; display: flex; gap: 10px; flex-wrap: wrap; }
.actions a, .actions button { padding: 8px 16px; border-radius: 5px; border: none; cursor: pointer; text-decoration: none; font-size: 14px; }
.btn-success { background-color: #28a745; color: white; }
.btn-secondary { background-color: #6c757d; color: white; }
.btn-success:hover { background-color: #218838; }
.btn-secondary:hover { background-color: #5a6268; }
.entry-details { margin-top: 30px; padding-top: 20px; border-top: 1px solid #dee2e6; }
.details-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 15px; margin-top: 15px; }
.detail-item { padding: 10px; }
.detail-item .label { font-weight: 600; display: block; margin-bottom: 5px; }
.detail-item .value { color: #555; }
/* Correct Print Styling */
@media print {
    .main-sidebar, .actions { display: none !important; }
    body { background: white !important; }
    .container-wrapper { margin: 0 !important; padding: 0 !important; box-shadow: none !important; border-radius: 0 !important; }
}
</style>
</head>

<body class="p-4 bg-light">

<div class="main-sidebar">
<?php include 'billing_sidebar.php'; ?>
</div>

<div class="container">
<div class="container-wrapper">

<h1 class="mb-4">
    Journal Entry Lines - Entry #<?= htmlspecialchars($entry['entry_id'] ?? '') ?>
</h1>

<div class="entry-info">
    <div class="info-item">
        <span class="label">Date:</span>
        <span class="value"><?= htmlspecialchars($entry['entry_date'] ?? '') ?></span>
    </div>
    <div class="info-item">
        <span class="label">Status:</span>
        <span class="badge <?= strtolower($entry['status'] ?? '') ?>">
            <?= htmlspecialchars($entry['status'] ?? '') ?>
        </span>
    </div>
    <div class="info-item">
        <span class="label">Reference:</span>
        <span class="value"><?= htmlspecialchars($entry['reference'] ?? '') ?></span>
    </div>
</div>

<div class="table-responsive">
<table class="table table-bordered table-striped align-middle">
<thead>
<tr>
<th>Account</th>
<th class="amount-col">Debit</th>
<th class="amount-col">Credit</th>
<th>Description</th>
</tr>
</thead>
<tbody>
<?php if (!empty($lines)): ?>
    <?php foreach ($lines as $line): ?>
        <tr>
            <td><?= htmlspecialchars($line['account_name'] ?? '') ?></td>
            <td class="amount-col"><?= !empty($line['debit']) ? number_format($line['debit'],2) : '' ?></td>
            <td class="amount-col"><?= !empty($line['credit']) ? number_format($line['credit'],2) : '' ?></td>
            <td><?= htmlspecialchars($line['description'] ?? '') ?></td>
        </tr>
    <?php endforeach; ?>
<?php else: ?>
    <tr>
        <td colspan="4" class="text-center">No lines found for this entry.</td>
    </tr>
<?php endif; ?>
</tbody>
<tfoot>
<tr class="total">
<th>TOTAL</th>
<th class="amount-col"><?= number_format($total_debit,2) ?></th>
<th class="amount-col"><?= number_format($total_credit,2) ?></th>
<th></th>
</tr>
</tfoot>
</table>
</div>

<div class="actions">
<?php if (($entry['status'] ?? '') === 'Draft'): ?>
<a href="post_journal_entry.php?id=<?= $entry['entry_id'] ?? '' ?>" class="btn-success" onclick="return confirm('Post this entry? This action cannot be undone.');">
Post Entry
</a>
<?php endif; ?>
<button id="print-entry" class="btn-secondary">Print</button>
<a href="journal_entry.php" class="btn-secondary">Back</a>
</div>

<div class="entry-details">
<h2>Entry Details</h2>
<div class="details-grid">
    <div class="detail-item">
        <span class="label">Created By:</span>
        <span class="value"><?= htmlspecialchars($entry['created_by'] ?? '') ?></span>
    </div>
    <div class="detail-item">
        <span class="label">Created Date:</span>
        <span class="value"><?= htmlspecialchars($entry['created_at'] ?? '') ?></span>
    </div>
    <div class="detail-item">
        <span class="label">Last Modified:</span>
        <span class="value"><?= htmlspecialchars($entry['updated_at'] ?? '') ?></span>
    </div>
    <div class="detail-item">
        <span class="label">Module:</span>
        <span class="value"><?= htmlspecialchars(ucfirst($entry['module'] ?? '')) ?></span>
    </div>
</div>
</div>

</div>
<div class="main-sidebar">
<?php include 'billing_sidebar.php'; ?>
</div>
</div>

<script>
document.getElementById('print-entry').addEventListener('click', function() {
    window.print();
});
</script>
</body>
</html>
