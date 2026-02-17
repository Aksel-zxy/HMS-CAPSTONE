<?php
session_start();
include '../../SQL/config.php';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $expense_name = $_POST['expense_name'];
    $category = $_POST['category'];
    $amount = $_POST['amount'];
    $expense_date = $_POST['expense_date'];
    $notes = $_POST['notes'];
    $created_by = $_SESSION['username'] ?? 'Unknown';

    // Prepare insert statement without expense_id (auto_increment)
    $sql = "INSERT INTO expense_logs (expense_name, category, amount, expense_date, notes, created_by)
            VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssdsss", $expense_name, $category, $amount, $expense_date, $notes, $created_by);

    if ($stmt->execute()) {
        $success = "Expense added successfully!";
    } else {
        $error = "Error adding expense: " . $stmt->error;
    }

    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Add Expense</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" type="text/css" href="assets/css/billing_sidebar.css">

<style>
/* ==============================
   Responsive layout
============================== */
.content-wrapper {
    margin-left: 250px; /* same as sidebar width */
    transition: margin-left 0.3s ease;
    padding: 20px;
}

.sidebar.closed ~ .content-wrapper {
    margin-left: 0;
}

.form-container {
    background-color: white;
    border-radius: 30px;
    padding: 30px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    max-width: 800px;
    margin: 80px auto 20px auto;
}

@media (max-width: 768px) {
    .content-wrapper {
        margin-left: 0;
        padding: 15px;
    }

    .form-container {
        margin: 20px auto;
        padding: 20px;
    }

    .d-flex.flex-wrap {
        flex-direction: column !important;
    }
}
</style>

</head>
<body class="bg-light">

<div class="main-sidebar">
    <?php include 'billing_sidebar.php'; ?>
</div>

<div class="content-wrapper">
    <div class="form-container">
        <h1 class="mb-4">Add New Expense</h1>

        <?php if (!empty($success)): ?>
            <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
        <?php elseif (!empty($error)): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="mb-3">
                <label for="expense_name" class="form-label">Expense Name</label>
                <input type="text" class="form-control" id="expense_name" name="expense_name" required>
            </div>

            <div class="mb-3">
                <label for="category" class="form-label">Category</label>
                <input type="text" class="form-control" id="category" name="category">
            </div>

            <div class="mb-3">
                <label for="amount" class="form-label">Amount (₱)</label>
                <input type="number" step="0.01" class="form-control" id="amount" name="amount" required>
            </div>

            <div class="mb-3">
                <label for="expense_date" class="form-label">Date</label>
                <input type="date" class="form-control" id="expense_date" name="expense_date" required>
            </div>

            <div class="mb-3">
                <label for="notes" class="form-label">Notes</label>
                <textarea class="form-control" id="notes" name="notes"></textarea>
            </div>

            <div class="d-flex flex-wrap gap-2">
                <button type="submit" class="btn btn-primary">Add Expense</button>
                <a href="billing_dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
            </div>
        </form>
    </div>
</div>

</body>
</html>
