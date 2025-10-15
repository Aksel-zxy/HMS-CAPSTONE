<?php
session_start();
include '../../SQL/config.php';

// Auto-classify accounts based on name
function classifyAccount($name, $isExpense = false) {
    $name = strtolower($name);

    if ($isExpense) return "Expense";

    if (strpos($name, 'cash') !== false || strpos($name, 'receivable') !== false || strpos($name, 'bank') !== false) {
        return "Asset";
    } elseif (strpos($name, 'payable') !== false || strpos($name, 'loan') !== false) {
        return "Liability";
    } elseif (strpos($name, 'revenue') !== false || strpos($name, 'income') !== false || strpos($name, 'service') !== false) {
        return "Revenue";
    } elseif (strpos($name, 'expense') !== false || strpos($name, 'food') !== false || strpos($name, 'supplies') !== false) {
        return "Expense";
    }
    return "Revenue"; // Default
}

// Totals
$totals = ["Asset"=>0, "Liability"=>0, "Revenue"=>0, "Expense"=>0];
$records = [];

// --- 1️⃣ Fetch billing items ---
$sqlBilling = "SELECT bi.item_id, bi.total_price, bi.billing_id, ds.serviceName 
               FROM billing_items bi 
               JOIN dl_services ds ON bi.service_id = ds.serviceID
               WHERE bi.finalized = 1
               ORDER BY bi.item_id DESC";
$billing = $conn->query($sqlBilling);
if ($billing && $billing->num_rows>0) {
    while ($row = $billing->fetch_assoc()) {
        $type = classifyAccount($row['serviceName']);
        $totals[$type] += $row['total_price'];
        $records[] = [
            'id' => $row['item_id'],
            'name' => $row['serviceName'],
            'amount' => $row['total_price'],
            'date' => date('Y-m-d H:i:s'), // approximate, use created_at if available
            'type' => $type
        ];
    }
}

// --- 2️⃣ Fetch expenses ---
$sqlExpense = "SELECT * FROM expense_logs ORDER BY expense_date DESC";
$expenses = $conn->query($sqlExpense);
if ($expenses && $expenses->num_rows>0) {
    while ($row = $expenses->fetch_assoc()) {
        $type = classifyAccount($row['expense_name'], true);
        $totals[$type] += $row['amount'];
        $records[] = [
            'id' => $row['expense_id'],
            'name' => $row['expense_name'],
            'amount' => $row['amount'],
            'date' => $row['expense_date'],
            'type' => $type
        ];
    }
}

// --- 3️⃣ Fetch journal entry lines ---
$sqlLines = "SELECT jel.line_id, jel.entry_id, jel.account_name, jel.debit, jel.credit, je.entry_date 
             FROM journal_entry_lines jel
             JOIN journal_entries je ON jel.entry_id = je.entry_id
             ORDER BY jel.line_id DESC";
$lines = $conn->query($sqlLines);
if ($lines && $lines->num_rows>0) {
    while ($row = $lines->fetch_assoc()) {
        $type = classifyAccount($row['account_name']);
        $amount = floatval($row['debit']) - floatval($row['credit']); // Debit increases Asset/Expense, Credit increases Liability/Revenue
        $totals[$type] += abs($amount);
        $records[] = [
            'id' => $row['line_id'],
            'name' => $row['account_name'],
            'amount' => $amount,
            'date' => $row['entry_date'],
            'type' => $type
        ];
    }
}

// Sort records by date (newest first)
usort($records, fn($a,$b)=> strtotime($b['date']) - strtotime($a['date']));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Journal Accounts</title>
<link rel="stylesheet" href="assets/CSS/bootstrap.min.css">
<link rel="stylesheet" type="text/css" href="assets/css/billing_sidebar.css">
<style>
.badge { padding:5px 10px; border-radius:20px; font-size:12px; }
.asset { background:#e8f5e9; color:#2e7d32; }
.liability { background:#ffebee; color:#c62828; }
.revenue { background:#e3f2fd; color:#1565c0; }
.expense { background:#fff8e1; color:#f57f17; }
</style>
</head>
<body class="p-4 bg-light">

<div class="main-sidebar">
<?php include 'billing_sidebar.php'; ?>
</div>

<div class="container bg-white p-4 rounded shadow">
<h1 class="mb-4">Journal Accounts</h1>

<table class="table table-bordered table-striped">
<thead class="table-dark">
<tr>
<th>ID</th>
<th>Name</th>
<th>Type</th>
<th class="text-end">Amount (₱)</th>
<th>Date</th>
</tr>
</thead>
<tbody>
<?php if(count($records)>0): ?>
<?php foreach($records as $rec): ?>
<tr>
<td><?= $rec['id'] ?></td>
<td><?= htmlspecialchars($rec['name']) ?></td>
<td><span class="badge <?= strtolower($rec['type']) ?>"><?= $rec['type'] ?></span></td>
<td class="text-end">₱<?= number_format($rec['amount'],2) ?></td>
<td><?= date("M d, Y h:i A", strtotime($rec['date'])) ?></td>
</tr>
<?php endforeach; ?>
<?php else: ?>
<tr><td colspan="5" class="text-center">No records found.</td></tr>
<?php endif; ?>
</tbody>
</table>

<div class="row mt-4">
<div class="col-md-3"><div class="card p-3"><h5>Total Assets</h5><p class="fw-bold">₱<?= number_format($totals['Asset'],2) ?></p></div></div>
<div class="col-md-3"><div class="card p-3"><h5>Total Liabilities</h5><p class="fw-bold">₱<?= number_format($totals['Liability'],2) ?></p></div></div>
<div class="col-md-3"><div class="card p-3"><h5>Total Revenue</h5><p class="fw-bold">₱<?= number_format($totals['Revenue'],2) ?></p></div></div>
<div class="col-md-3"><div class="card p-3"><h5>Total Expenses</h5><p class="fw-bold">₱<?= number_format($totals['Expense'],2) ?></p></div></div>
</div>

</div>
</body>
</html>
