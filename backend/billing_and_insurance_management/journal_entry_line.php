<?php
session_start();
include '../../SQL/config.php';

/* =========================
   Determine which entry to load
========================= */
$entry_id = isset($_GET['entry_id']) ? intval($_GET['entry_id']) : 0;
$payment_id = $_GET['payment_id'] ?? null;
$payment = null;
$entry = null;
$lines = [];
$total_debit = 0;
$total_credit = 0;

if ($entry_id > 0) {
    $stmt = $conn->prepare("SELECT * FROM journal_entries WHERE entry_id = ?");
    $stmt->bind_param("i", $entry_id);
    $stmt->execute();
    $entry = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$entry) {
        header("Location: journal_entry.php");
        exit;
    }

    $stmt = $conn->prepare("SELECT * FROM journal_entry_lines WHERE entry_id = ?");
    $stmt->bind_param("i", $entry_id);
    $stmt->execute();
    $lines = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    foreach ($lines as $line) {
        $total_debit += floatval($line['debit'] ?? 0);
        $total_credit += floatval($line['credit'] ?? 0);
    }

} elseif ($payment_id) {

    $stmt = $conn->prepare("
        SELECT pp.*, pi.fname, pi.mname, pi.lname
        FROM paymongo_payments pp
        LEFT JOIN patientinfo pi ON pp.patient_id = pi.patient_id
        WHERE pp.payment_id = ?
    ");
    $stmt->bind_param("s", $payment_id);
    $stmt->execute();
    $payment = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$payment) {
        header("Location: journal_entry.php");
        exit;
    }

    $full_name = trim($payment['fname'].' '.$payment['mname'].' '.$payment['lname']);
    $description = "Payment received from {$full_name}\nMethod: {$payment['payment_method']}\nRemarks: ".($payment['remarks'] ?? '');
    $amount = floatval($payment['amount']);
    $paid_at = $payment['paid_at'] ?? 'N/A';

    $lines[] = [
        'account_name' => 'Cash / Bank',
        'debit' => $amount,
        'credit' => 0,
        'description' => $description
    ];
    $lines[] = [
        'account_name' => 'Patient Receivable',
        'debit' => 0,
        'credit' => $amount,
        'description' => 'Settlement of patient account'
    ];

    $total_debit = $amount;
    $total_credit = $amount;

} else {
    header("Location: journal_entry.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Journal Entry Details</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

<style>
body { 
    background-color: #f8f9fa; 
}

/* Sidebar push layout */
.content-wrapper {
    margin-left: 250px; /* same as sidebar width */
    padding: 20px;
    transition: margin-left 0.3s ease;
}

.sidebar.closed ~ .content-wrapper {
    margin-left: 0;
}

/* Main container */
.container-wrapper { 
    background-color: white; 
    padding: 30px; 
    margin: 50px auto; 
    border-radius: 15px; 
    max-width: 900px; 
}

/* Debit / Credit styling */
.debit { color: green; font-weight: bold; }
.credit { color: red; font-weight: bold; }

/* Reference / description */
.reference-info { white-space: pre-line; font-size: 0.9em; }

/* Action buttons */
.actions { margin-top: 20px; display: flex; gap: 10px; flex-wrap: wrap; }
.btn-secondary { background-color: #6c757d; color: white; padding: 8px 16px; border-radius: 5px; text-decoration: none; border: none; }
.btn-secondary:hover { background-color: #5a6268; }

/* Table responsiveness */
.table-responsive { overflow-x: auto; }

/* ================= PRINT SETTINGS ================= */
@media print {
    .main-sidebar { display: none !important; }
    .actions { display: none !important; }
    body { background: white !important; }
    .container-wrapper { margin: 0 !important; max-width: 100% !important; border-radius: 0 !important; box-shadow: none !important; padding: 0 !important; }
    table { font-size: 12px; }
    h2 { margin-top: 0; }
}

/* ================= RESPONSIVE ================= */
@media (max-width: 768px) {
    .content-wrapper { margin-left: 0; padding: 15px; }
    .container-wrapper { padding: 20px; margin: 20px auto; }
    .actions { flex-direction: column; gap: 8px; }
}
</style>

</head>
<body>

<!-- SIDEBAR -->
<div class="main-sidebar">
<?php include 'billing_sidebar.php'; ?>
</div>

<!-- CONTENT -->
<div class="content-wrapper">
<div class="container container-wrapper">

<h2>Journal Entry Details</h2>

<div class="mb-3">
<strong>Date:</strong> <?= htmlspecialchars($entry['entry_date'] ?? $paid_at ?? '') ?><br>
<strong>Reference:</strong> <?= htmlspecialchars($entry['reference'] ?? $payment['payment_id'] ?? '') ?><br>
<strong>Status:</strong> <?= htmlspecialchars($entry['status'] ?? 'Posted') ?><br>
<strong>Module:</strong> <?= htmlspecialchars(ucfirst($entry['module'] ?? 'billing')) ?><br>
<strong>Created By:</strong> <?= htmlspecialchars($entry['created_by'] ?? 'System') ?><br>
</div>

<div class="table-responsive">
<table class="table table-bordered">
<thead>
<tr>
<th>Account</th>
<th class="text-end">Debit</th>
<th class="text-end">Credit</th>
<th>Description</th>
</tr>
</thead>
<tbody>
<?php foreach ($lines as $line): ?>
<tr>
<td><?= htmlspecialchars($line['account_name'] ?? '') ?></td>
<td class="text-end"><?= !empty($line['debit']) ? number_format($line['debit'],2) : '' ?></td>
<td class="text-end"><?= !empty($line['credit']) ? number_format($line['credit'],2) : '' ?></td>
<td class="reference-info"><?= nl2br(htmlspecialchars($line['description'] ?? '')) ?></td>
</tr>
<?php endforeach; ?>
</tbody>
<tfoot>
<tr class="fw-bold">
<th>TOTAL</th>
<th class="text-end"><?= number_format($total_debit,2) ?></th>
<th class="text-end"><?= number_format($total_credit,2) ?></th>
<th></th>
</tr>
</tfoot>
</table>
</div>

<!-- ACTION BUTTONS -->
<div class="actions">
<a href="journal_entry.php" class="btn-secondary">Back to Journal</a>
<button onclick="window.print()" class="btn-secondary">Print</button>
</div>

</div>
</div>
</body>
</html>
