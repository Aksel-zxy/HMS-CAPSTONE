<?php
session_start();
include '../../SQL/config.php';

// Fetch billing per receipt and aggregate all items
$sqlBilling = "
SELECT 
    pr.receipt_id,
    GROUP_CONCAT(bi.item_description SEPARATOR ', ') AS items,
    pr.grand_total,
    pr.created_at
FROM patient_receipt pr
LEFT JOIN billing_items bi ON bi.billing_id = pr.billing_id
GROUP BY pr.receipt_id, pr.grand_total, pr.created_at
ORDER BY pr.created_at DESC
";
$billing = $conn->query($sqlBilling);

// Auto-classify accounts
function classifyAccount($name) {
    $name = strtolower($name);

    if (strpos($name, 'scan') !== false || strpos($name, 'laboratory') !== false || strpos($name, 'cbc') !== false) {
        return "Revenue"; 
    } elseif (strpos($name, 'insurance') !== false) {
        return "Asset"; 
    } elseif (strpos($name, 'loan') !== false || strpos($name, 'payable') !== false) {
        return "Liability";
    }

    return "Revenue"; 
}

// Totals
$totals = [
    "Asset" => 0,
    "Liability" => 0,
    "Revenue" => 0,
    "Expense" => 0
];

// Collect unified records
$records = [];

// Billing → Revenue/Asset/Liability
if ($billing && $billing->num_rows > 0) {
    while ($row = $billing->fetch_assoc()) {
        $type = classifyAccount($row['items']);
        $totals[$type] += $row['grand_total'];

        $records[] = [
            'id' => $row['receipt_id'],
            'name' => $row['items'],
            'amount' => $row['grand_total'],
            'date' => $row['created_at'],
            'type' => $type
        ];
    }
}

// Sort records by date (newest first)
usort($records, function($a, $b){
    return strtotime($b['date'] ?? '1970-01-01') - strtotime($a['date'] ?? '1970-01-01');
});
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Journal Accounts</title>
<link rel="stylesheet" href="assets/CSS/bootstrap.min.css">
<style>
.badge { padding: 5px 10px; border-radius: 20px; font-size: 12px; }
.asset { background-color: #e8f5e9; color: #2e7d32; }
.liability { background-color: #ffebee; color: #c62828; }
.revenue { background-color: #e3f2fd; color: #1565c0; }
.expense { background-color: #fff8e1; color: #f57f17; }
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
<th>Name / Items</th>
<th>Type</th>
<th class="text-end">Amount (₱)</th>
<th>Date</th>
</tr>
</thead>
<tbody>
<?php if(count($records) > 0): ?>
<?php foreach($records as $rec): ?>
<tr>
<td><?= $rec['id'] ?></td>
<td><?= htmlspecialchars($rec['name']) ?></td>
<td><span class="badge <?= strtolower($rec['type']) ?>"><?= $rec['type'] ?></span></td>
<td class="text-end">₱<?= number_format($rec['amount'], 2) ?></td>
<td><?= $rec['date'] ? date("M d, Y h:i A", strtotime($rec['date'])) : '-' ?></td>
</tr>
<?php endforeach; ?>
<?php else: ?>
<tr>
<td colspan="5" class="text-center">No records found.</td>
</tr>
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
