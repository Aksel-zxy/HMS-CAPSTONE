<?php
require_once 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $expense_name = $_POST['expense_name'];
    $category = $_POST['category'];
    $amount = floatval($_POST['amount']);
    $notes = $_POST['notes'];
    $created_by = $_SESSION['username'] ?? 'System';

    if ($amount <= 0) {
        die("Expense amount must be positive.");
    }

    // Insert expense
    $sql = "INSERT INTO expense_logs (expense_name, category, amount, expense_date, notes, created_by) 
            VALUES (?, ?, ?, NOW(), ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssiss", $expense_name, $category, $amount, $notes, $created_by);
    $stmt->execute();
    $expense_id = $stmt->insert_id;

    // -------------------------
    // CREATE JOURNAL ENTRY
    // -------------------------
    $conn->begin_transaction();

    try {
        $sqlEntry = "INSERT INTO journal_entries (entry_date, reference, status, created_by) 
                     VALUES (NOW(), ?, 'Posted', ?)";
        $stmt = $conn->prepare($sqlEntry);
        $ref = "EXP-" . $expense_id;
        $stmt->bind_param("ss", $ref, $created_by);
        $stmt->execute();
        $entry_id = $stmt->insert_id;

        // Debit Expense
        $sqlLine = "INSERT INTO journal_entry_lines (entry_id, account_name, debit, credit, description) 
                    VALUES (?, ?, ?, 0, ?)";
        $stmt = $conn->prepare($sqlLine);
        $stmt->bind_param("isds", $entry_id, $category, $amount, $expense_name);
        $stmt->execute();

        // Credit Cash
        $sqlLine = "INSERT INTO journal_entry_lines (entry_id, account_name, debit, credit, description) 
                    VALUES (?, 'Cash', 0, ?, ?)";
        $stmt = $conn->prepare($sqlLine);
        $stmt->bind_param("ids", $entry_id, $amount, $expense_name);
        $stmt->execute();

        $conn->commit();
    } catch (Exception $e) {
        $conn->rollback();
        die("Error posting journal: " . $e->getMessage());
    }

    echo "Expense added and journal entry created.";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Add Expense</title>
  <link rel="stylesheet" href="assets/CSS/bootstrap.min.css">
</head>
<body class="p-4 bg-light">

<div class="main-sidebar">
  <?php include 'billing_sidebar.php'; ?>
</div>

<div class="container bg-white p-4 rounded shadow">
  <h2 class="mb-4">Add New Expense</h2>

  <?php if (!empty($error)): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <form method="POST">
    <div class="mb-3">
      <label class="form-label">Expense Name *</label>
      <input type="text" name="expense_name" class="form-control" required>
    </div>

    <div class="mb-3">
      <label class="form-label">Category *</label>
      <input type="text" name="category" class="form-control" required placeholder="e.g. Supplies, Utilities, Salaries">
    </div>

    <div class="mb-3">
      <label class="form-label">Amount (₱) *</label>
      <input type="number" step="0.01" min="0.01" name="amount" class="form-control" required>
    </div>

    <div class="mb-3">
      <label class="form-label">Expense Date *</label>
      <input type="datetime-local" name="expense_date" class="form-control" required value="<?= date('Y-m-d\TH:i') ?>">
    </div>

    <div class="mb-3">
      <label class="form-label">Notes</label>
      <textarea name="notes" class="form-control" rows="3"></textarea>
    </div>

    <div class="d-flex justify-content-between">
      <a href="expense_logs.php" class="btn btn-secondary">Cancel</a>
      <button type="submit" class="btn btn-success">Save Expense</button>
    </div>
  </form>
</div>

</body>
</html>
