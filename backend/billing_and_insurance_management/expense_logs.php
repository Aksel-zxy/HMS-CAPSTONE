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
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" type="text/css" href="assets/css/billing_sidebar.css">
</head>
<body class="p-4 bg-light">

<div class="container">
<div style="background-color: white; border-radius: 30px; padding: 30px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); margin-top: 80px; margin-left: 100px;">
  <h1 class="mb-4">Expense Logs</h1>

  <div class="table-responsive">
    <table class="table table-bordered table-striped align-middle text-left">
      <thead class="table-white">
        <tr>
          <th>#</th>
          <th>Expense Name</th>
          <th>Category</th>
          <th>Amount (₱)</th>
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
            <td>₱<?= number_format($row['amount'], 2) ?></td>
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
  </div>

  <div class="mt-4 d-flex gap-2">
    <a href="add_expense.php" class="btn btn-primary">Add New Expense</a>
    <a href="billing_dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
  </div>
</div>

<div class="main-sidebar">
  <?php include 'billing_sidebar.php'; ?>
</div>
</div>

</body>
</html>
