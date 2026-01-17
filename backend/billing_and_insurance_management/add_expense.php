<?php
include '../../SQL/config.php'; // assume session_start() is in config.php

$showModal = false; // flag to control modal display
$modalMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $expense_name = $_POST['expense_name'];
    $category = $_POST['category'];
    $amount = floatval($_POST['amount']);
    $notes = $_POST['notes'];
    $expense_date = $_POST['expense_date'];
    $recorded_by = $_SESSION['username'] ?? 'System';

    if ($amount <= 0) {
        $modalMessage = "Expense amount must be positive.";
        $showModal = true;
    } else {
        // Insert expense into expense_logs
        $sql = "INSERT INTO expense_logs (expense_name, category, description, amount, expense_date, recorded_by, notes) 
                VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssisss", $expense_name, $category, $expense_name, $amount, $expense_date, $recorded_by, $notes);
        $stmt->execute();
        $expense_id = $stmt->insert_id;

        // CREATE JOURNAL ENTRY
        $conn->begin_transaction();
        try {
            $ref = "EXP-" . $expense_id;

            // Insert into journal_entries with description and module
            $sqlEntry = "INSERT INTO journal_entries 
                (entry_date, description, module, reference_type, reference_id, reference, status, created_by) 
                VALUES (NOW(), ?, ?, 'Expense', ?, ?, 'Posted', ?)";
            $stmt = $conn->prepare($sqlEntry);
            $stmt->bind_param("ssiss", $expense_name, $category, $expense_id, $ref, $recorded_by);
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
            $modalMessage = "Expense added and journal entry created successfully!";
            $showModal = true;
        } catch (Exception $e) {
            $conn->rollback();
            $modalMessage = "Error posting journal: " . $e->getMessage();
            $showModal = true;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Add Expense</title>
  <link rel="stylesheet" href="assets/CSS/bootstrap.min.css">
  <script src="assets/JS/bootstrap.bundle.min.js"></script>
</head>
<body class="p-4 bg-light">

<div class="main-sidebar">
  <?php include 'billing_sidebar.php'; ?>
</div>

<div class="container bg-white p-4 rounded shadow">
  <h2 class="mb-4">Add New Expense</h2>

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

<!-- Feedback Modal -->
<div class="modal fade" id="feedbackModal" tabindex="-1" aria-labelledby="feedbackModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="feedbackModalLabel">Notification</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <?= htmlspecialchars($modalMessage) ?>
      </div>
      <div class="modal-footer">
        <a href="expense_logs.php" class="btn btn-primary">View Expenses</a>
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<?php if ($showModal): ?>
<script>
  var feedbackModal = new bootstrap.Modal(document.getElementById('feedbackModal'));
  feedbackModal.show();
</script>
<?php endif; ?>

</body>
</html>
