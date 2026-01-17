<?php
$txn = $_GET['txn'] ?? 'UNKNOWN';
$amount = $_GET['amount'] ?? 0;
$method = $_GET['method'] ?? 'Unknown';
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Simulated Payment - <?= htmlspecialchars($method) ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light p-5">
<div class="container bg-white shadow rounded p-4">
  <h2>Pay with <?= htmlspecialchars($method) ?></h2>
  <p><strong>Transaction ID:</strong> <?= htmlspecialchars($txn) ?></p>
  <p><strong>Amount:</strong> ₱<?= number_format($amount, 2) ?></p>
  
  <form action="webhook.php" method="POST">
    <input type="hidden" name="transaction_id" value="<?= htmlspecialchars($txn) ?>">
    <input type="hidden" name="status" value="Paid">
    <div class="d-flex gap-2">
        <button type="submit" class="btn btn-success">✅ Confirm Payment</button>
        <a href="billing_summary.php" class="btn btn-danger">❌ Cancel</a>
    </div>
  </form>
</div>
</body>
</html>
