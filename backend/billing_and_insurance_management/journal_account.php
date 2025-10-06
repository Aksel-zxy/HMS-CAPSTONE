<?php
session_start();
include '../../SQL/config.php';

/**
 * ✅ Step 1: Check available columns in expense_logs
 * (Prevents "Unknown column" errors)
 */
$columns = [];
$res = $conn->query("SHOW COLUMNS FROM expense_logs");
if ($res) {
    while ($col = $res->fetch_assoc()) {
        $columns[] = $col['Field'];
    }
}

// Detect correct expense column names dynamically
$col_name  = in_array('expense_name', $columns) ? 'expense_name' : (in_array('description', $columns) ? 'description' : (in_array('item_name', $columns) ? 'item_name' : 'category'));
$col_amt   = in_array('amount', $columns) ? 'amount' : (in_array('total', $columns) ? 'total' : 'value');
$col_date  = in_array('expense_date', $columns) ? 'expense_date' : (in_array('date', $columns) ? 'date' : 'created_at');
$col_cat   = in_array('category', $columns) ? 'category' : '';
$col_note  = in_array('notes', $columns) ? 'notes' : '';

/**
 * ✅ Step 2: Fetch billing (income side)
 */
$sqlBilling = "
    SELECT 
        item_id, 
        item_description, 
        total_price, 
        NOW() AS created_at  -- substitute for missing timestamp
    FROM billing_items
    ORDER BY item_id DESC
";
$billing = $conn->query($sqlBilling);

/**
 * ✅ Step 3: Fetch expense logs safely
 */
$sqlExpense = "SELECT expense_id, $col_name AS expense_name, $col_amt AS amount, $col_date AS expense_date" .
              ($col_cat ? ", $col_cat AS category" : "") .
              ($col_note ? ", $col_note AS notes" : "") .
              " FROM expense_logs ORDER BY $col_date DESC";
$expenses = $conn->query($sqlExpense);

/**
 * ✅ Step 4: Account classification logic
 */
function classifyAccount($name, $isExpense = false) {
    $name = strtolower($name);
    if ($isExpense) return "Expense";

    if (strpos($name, 'scan') !== false || strpos($name, 'lab') !== false || strpos($name, 'test') !== false)
        return "Revenue";
    if (strpos($name, 'insurance') !== false)
        return "Asset";
    if (strpos($name, 'loan') !== false || strpos($name, 'payable') !== false)
        return "Liability";
    return "Revenue";
}

/**
 * ✅ Step 5: Initialize totals and records
 */
$totals = ["Asset" => 0, "Liability" => 0, "Revenue" => 0, "Expense" => 0];
$records = [];

/**
 * ✅ Step 6: Process Billing Items
 */
if ($billing && $billing->num_rows > 0) {
    while ($row = $billing->fetch_assoc()) {
        $type = classifyAccount($row['item_description']);
        $totals[$type] += $row['total_price'];
        $records[] = [
            'id' => $row['item_id'],
            'name' => $row['item_description'],
            'amount' => $row['total_price'],
            'date' => $row['created_at'],
            'type' => $type
        ];
    }
}

/**
 * ✅ Step 7: Process Expense Logs
 */
if ($expenses && $expenses->num_rows > 0) {
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

/**
 * ✅ Step 8: Sort by date (newest first)
 */
usort($records, function ($a, $b) {
    return strtotime($b['date']) - strtotime($a['date']);
});
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Journal Accounts</title>
  <link rel="stylesheet" href="assets/CSS/bootstrap.min.css">
  <style>
    body { background: #f5f5f5; }
    .badge { padding: 5px 10px; border-radius: 20px; font-size: 12px; }
    .asset { background-color: #e8f5e9; color: #2e7d32; }
    .liability { background-color: #ffebee; color: #c62828; }
    .revenue { background-color: #e3f2fd; color: #1565c0; }
    .expense { background-color: #fff8e1; color: #f57f17; }
  </style>
</head>
<body class="p-4">

<div class="main-sidebar">
  <?php include 'billing_sidebar.php'; ?>
</div>

<div class="container bg-white p-4 rounded shadow">
  <h1 class="mb-4">Journal Accounts</h1>

  <table class="table table-bordered table-striped">
    <thead class="table-dark">
      <tr>
        <th>ID</th>
        <th>Description</th>
        <th>Type</th>
        <th class="text-end">Amount (₱)</th>
        <th>Date</th>
      </tr>
    </thead>
    <tbody>
    <?php if (count($records) > 0): ?>
      <?php foreach ($records as $rec): ?>
        <tr>
          <td><?= $rec['id'] ?></td>
          <td><?= htmlspecialchars($rec['name']) ?></td>
          <td><span class="badge <?= strtolower($rec['type']) ?>"><?= $rec['type'] ?></span></td>
          <td class="text-end">₱<?= number_format($rec['amount'], 2) ?></td>
          <td><?= date("M d, Y h:i A", strtotime($rec['date'])) ?></td>
        </tr>
      <?php endforeach; ?>
    <?php else: ?>
      <tr><td colspan="5" class="text-center">No records found.</td></tr>
    <?php endif; ?>
    </tbody>
  </table>

  <!-- Summary Section -->
  <div class="row mt-4">
    <?php foreach ($totals as $key => $value): ?>
    <div class="col-md-3">
      <div class="card p-3 text-center">
        <h5>Total <?= $key ?>s</h5>
        <p class="fw-bold">₱<?= number_format($value, 2) ?></p>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</div>
</body>
</html>
