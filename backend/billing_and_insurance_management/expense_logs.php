<?php
session_start();
include '../../SQL/config.php';

// Fetch all expenses
$sql = "SELECT * FROM expense_logs ORDER BY expense_date DESC";
$result = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Expense Logs</title>
  <link rel="stylesheet" href="assets/CSS/bootstrap.min.css">
  <link rel="stylesheet" type="text/css" href="assets/css/billing_sidebar.css">
</head>
<body class="p-4 bg-light">

<div class="main-sidebar">
  <?php include 'billing_sidebar.php'; ?>
</div>

<div class="container bg-white p-4 rounded shadow">
  <h2 class="mb-4">Expense Logs</h2>

  <table class="table table-bordered table-striped">
    <thead class="table-dark">
      <tr>
        <th>#</th>
        <th>Expense Name</th>
        <th>Category</th>
        <th class="text-end">Amount (₱)</th>
        <th>Date</th>
        <th>Notes</th>
        <th>Logged By</th>
      </tr>
    </thead>
    <tbody>
    <?php if ($result && $result->num_rows > 0): ?>
      <?php while ($row = $result->fetch_assoc()): ?>
        <tr>
          <td><?= $row['expense_id'] ?></td>
          <td><?= htmlspecialchars($row['expense_name']) ?></td>
          <td><?= htmlspecialchars($row['category']) ?></td>
          <td class="text-end">₱<?= number_format($row['amount'], 2) ?></td>
          <td><?= date("M d, Y h:i A", strtotime($row['expense_date'])) ?></td>
          <td><?= htmlspecialchars($row['notes']) ?></td>
          <td><?= htmlspecialchars($row['created_by']) ?></td>
        </tr>
      <?php endwhile; ?>
    <?php else: ?>
      <tr>
        <td colspan="7" class="text-center">No expense logs found.</td>
      </tr>
    <?php endif; ?>
    </tbody>
  </table>

  <div class="mt-3">
    <a href="add_expense.php" class="btn btn-primary">Add New Expense</a>
    <a href="billing_dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
  </div>
</div>

</body>
</html>
