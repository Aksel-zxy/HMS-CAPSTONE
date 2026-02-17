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
            'date' => date('Y-m-d H:i:s'),
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
        $amount = floatval($row['debit']) - floatval($row['credit']);
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
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Journal Accounts</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" type="text/css" href="assets/css/billing_sidebar.css">

<style>
/* ==============================
   Layout for sidebar push + content
============================== */
.content-wrapper {
    margin-left: 250px; /* sidebar width */
    padding: 20px;
    transition: margin-left 0.3s ease;
}

.sidebar.closed ~ .content-wrapper {
    margin-left: 0;
}

/* Container styling */
.container-wrapper {
    background-color: white; 
    border-radius: 30px; 
    padding: 30px; 
    box-shadow: 0 2px 8px rgba(0,0,0,0.1); 
    margin-top: 80px;
}

/* Stats cards */
.stats-card { 
    background-color: white; 
    border-radius: 12px; 
    padding: 20px; 
    box-shadow: 0 1px 4px rgba(0,0,0,0.1); 
    border: 1px solid #dee2e6; 
    text-align: center;
}
.stats-card h5 { font-size: 14px; color: #666; margin-bottom: 10px; font-weight: 600; }
.stats-card .amount { font-size: 24px; font-weight: bold; color: #333; }

/* Badges */
.badge { padding: 5px 10px; border-radius: 20px; font-size: 12px; }
.badge.asset { background-color: #d4edda; color: #155724; }
.badge.liability { background-color: #f8d7da; color: #721c24; }
.badge.revenue { background-color: #d1ecf1; color: #0c5460; }
.badge.expense { background-color: #fff3cd; color: #856404; }

/* Responsive adjustments */
@media (max-width: 768px) {
    .content-wrapper { margin-left: 0; padding: 15px; }
    .container-wrapper { margin: 20px auto; padding: 20px; }
    .stats-card { margin-bottom: 15px; }
}
</style>
</head>
<body class="bg-light">

<div class="main-sidebar">
    <?php include 'billing_sidebar.php'; ?>
</div>

<div class="content-wrapper">
    <div class="container-wrapper">
        <h1 class="mb-4">Journal Accounts</h1>

        <div class="table-responsive">
            <table class="table table-bordered table-striped align-middle text-left">
                <thead class="table-white">
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Type</th>
                        <th>Amount (₱)</th>
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
                            <td>₱<?= number_format($rec['amount'],2) ?></td>
                            <td><?= date("M d, Y h:i A", strtotime($rec['date'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="5" class="text-center">No records found.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="row mt-4 g-3">
            <div class="col-6 col-md-3">
                <div class="stats-card">
                    <h5>Total Assets</h5>
                    <div class="amount">₱<?= number_format($totals['Asset'],2) ?></div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="stats-card">
                    <h5>Total Liabilities</h5>
                    <div class="amount">₱<?= number_format($totals['Liability'],2) ?></div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="stats-card">
                    <h5>Total Revenue</h5>
                    <div class="amount">₱<?= number_format($totals['Revenue'],2) ?></div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="stats-card">
                    <h5>Total Expenses</h5>
                    <div class="amount">₱<?= number_format($totals['Expense'],2) ?></div>
                </div>
            </div>
        </div>
    </div>
</div>

</body>
</html>
