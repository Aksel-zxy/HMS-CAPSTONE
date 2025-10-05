<?php
session_start();
require 'db.php';

// Only allow admins (role=7 in your system) to request budgets
if (!isset($_SESSION['user_id'])) die("Login required.");
$user_id = $_SESSION['user_id'];

$stmt = $pdo->prepare("SELECT role,fname,lname FROM users WHERE user_id=?");
$stmt->execute([$user_id]);
$currentUser = $stmt->fetch(PDO::FETCH_ASSOC);
if ($currentUser['role'] != 7) die("You are not authorized to request budgets.");

// Handle form submission (Request New Budget)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_budget'])) {
    $dept_user_id = $_POST['dept_user_id'];
    $month = date('Y-m', strtotime($_POST['month'])); // ✅ always YYYY-MM
    $amount = floatval($_POST['amount']);

    $check = $pdo->prepare("SELECT * FROM department_budgets WHERE user_id=? AND month=?");
    $check->execute([$dept_user_id, $month]);
    $existing = $check->fetch(PDO::FETCH_ASSOC);

    if ($existing) {
        if ($existing['status'] === 'Pending') {
            $update = $pdo->prepare("UPDATE department_budgets 
                                     SET requested_amount=? 
                                     WHERE budget_id=?");
            $update->execute([$amount, $existing['budget_id']]);
            $msg = "Budget request updated successfully.";
        } else {
            $msg = "Budget already approved/rejected for this month.";
        }
    } else {
        $insert = $pdo->prepare("
            INSERT INTO department_budgets 
            (user_id, month, allocated_budget, requested_amount, approved_amount, status, request_date)
            VALUES (?, ?, 0.00, ?, 0.00, 'Pending', NOW())
        ");
        $insert->execute([$dept_user_id, $month, $amount]);
        $msg = "Budget request submitted successfully.";
    }
}

// Fetch all department users with non-blank department
$users_stmt = $pdo->prepare("
    SELECT user_id,fname,lname,department 
    FROM users 
    WHERE role != 0 AND department IS NOT NULL AND department != ''
");
$users_stmt->execute();
$departments = $users_stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch current month budgets
$current_month = date('Y-m');
$current_month_text = strtoupper(date('F Y'));
$budgets_stmt = $pdo->prepare("
    SELECT b.*, u.fname, u.lname, u.department 
    FROM department_budgets b 
    JOIN users u ON u.user_id = b.user_id 
    WHERE b.month = ?
");
$budgets_stmt->execute([$current_month]);
$budgets = $budgets_stmt->fetchAll(PDO::FETCH_ASSOC);

// Totals
$total_requested = array_sum(array_column($budgets, 'requested_amount'));
$total_allocated = array_sum(array_column($budgets, 'allocated_budget'));
$total_approved = array_sum(array_column($budgets, 'approved_amount'));

// Track Budgets filter
$status_filter = $_GET['status'] ?? '';
$month_filter = $_GET['month'] ?? date('Y-m');

$track_query = "
    SELECT b.*, u.fname, u.lname, u.department 
    FROM department_budgets b 
    JOIN users u ON u.user_id = b.user_id 
    WHERE 1=1
";
$params = [];
if ($status_filter) {
    $track_query .= " AND b.status = ?";
    $params[] = $status_filter;
}
if ($month_filter) {
    $track_query .= " AND b.month = ?";
    $params[] = $month_filter; // ✅ match YYYY-MM
}

$track_stmt = $pdo->prepare($track_query);
$track_stmt->execute($params);
$tracked_budgets = $track_stmt->fetchAll(PDO::FETCH_ASSOC);

// Min month for input
$min_month = date('Y-m');
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Department Budget Management</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<div class="main-sidebar">
    <?php include 'inventory_sidebar.php'; ?>
</div>

<div class="container py-5">
<h2 class="mb-4">Department Budget Management</h2>

<?php if (isset($msg)): ?>
<div class="alert alert-success"><?= htmlspecialchars($msg) ?></div>
<?php endif; ?>

<!-- Tabs -->
<ul class="nav nav-tabs mb-4" id="budgetTabs" role="tablist">
  <li class="nav-item" role="presentation">
    <button class="nav-link active" id="request-tab" data-bs-toggle="tab" data-bs-target="#request" type="button">Request New Budget</button>
  </li>
  <li class="nav-item" role="presentation">
    <button class="nav-link" id="track-tab" data-bs-toggle="tab" data-bs-target="#track" type="button">Track Budgets</button>
  </li>
</ul>

<div class="tab-content">
  <!-- Request New Budget Tab -->
  <div class="tab-pane fade show active" id="request">
    <div class="card mb-4">
      <div class="card-body">
        <form method="POST">
          <input type="hidden" name="request_budget" value="1">
          <div class="row g-2 align-items-end">
            <div class="col-md-4">
              <label>Department</label>
              <select name="dept_user_id" class="form-select" required>
                <option value="">Select Department</option>
                <?php foreach ($departments as $d): ?>
                  <option value="<?= $d['user_id'] ?>"><?= htmlspecialchars($d['department']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-3">
              <label>Month</label>
              <input type="month" name="month" class="form-control" required min="<?= $min_month ?>">
            </div>
            <div class="col-md-3">
              <label>Amount (₱)</label>
              <input type="number" name="amount" class="form-control" step="0.01" required>
            </div>
            <div class="col-md-2 d-flex">
              <button type="submit" class="btn btn-primary w-100">Request Budget</button>
            </div>
          </div>
        </form>
      </div>
    </div>

    <!-- Department Budget Table -->
    <div class="card">
      <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><?= $current_month_text ?> Department Budget</h5>
        <h5 class="mb-0 text-primary">Total Approved: ₱<?= number_format($total_approved, 2) ?></h5>
      </div>
      <div class="card-body p-0">
        <table class="table table-bordered table-striped mb-0">
          <thead class="table-light">
            <tr>
              <th>Department</th>
              <th>Approved Budget</th>
              <th>Total Requested</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($departments as $d): 
              $dept_budget = array_filter($budgets, fn($b) => $b['department'] == $d['department']);
              $approved = array_sum(array_column($dept_budget, 'approved_amount'));
              $requested = array_sum(array_column($dept_budget, 'requested_amount'));
            ?>
              <tr>
                <td><?= htmlspecialchars($d['department']) ?></td>
                <td>₱<?= number_format($approved, 2) ?></td>
                <td>₱<?= number_format($requested, 2) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
          <tfoot class="table-secondary">
            <tr>
              <th>Total</th>
              <th>₱<?= number_format($total_approved, 2) ?></th>
              <th>₱<?= number_format($total_requested, 2) ?></th>
            </tr>
          </tfoot>
        </table>
      </div>
    </div>
  </div>

  <!-- Track Budgets Tab -->
  <div class="tab-pane fade" id="track">
    <div class="card mb-4">
      <div class="card-body">
        <form method="GET" class="row g-2 mb-3">
          <div class="col-md-4">
            <label>Status</label>
            <select name="status" class="form-select">
              <option value="">All</option>
              <option value="Pending" <?= $status_filter=='Pending'?'selected':'' ?>>Pending</option>
              <option value="Approved" <?= $status_filter=='Approved'?'selected':'' ?>>Approved</option>
              <option value="Declined" <?= $status_filter=='Declined'?'selected':'' ?>>Declined</option>
            </select>
          </div>
          <div class="col-md-3">
            <label>Month</label>
            <input type="month" name="month" class="form-control" value="<?= $month_filter ?>">
          </div>
          <div class="col-md-2 d-flex align-items-end">
            <button type="submit" class="btn btn-secondary w-100">Filter</button>
          </div>
        </form>

        <table class="table table-bordered table-striped bg-white">
          <thead>
            <tr>
              <th>Department</th>
              <th>Requested Amount</th>
              <th>Allocated Budget</th>
              <th>Approved Amount</th>
              <th>Status</th>
              <th>Request Date</th>
            </tr>
          </thead>
          <tbody>
            <?php if (count($tracked_budgets) > 0): ?>
              <?php foreach ($tracked_budgets as $b): ?>
                <tr>
                  <td><?= htmlspecialchars($b['department']) ?></td>
                  <td>₱<?= number_format($b['requested_amount'],2) ?></td>
                  <td>₱<?= number_format($b['allocated_budget'],2) ?></td>
                  <td>₱<?= number_format($b['approved_amount'],2) ?></td>
                  <td><?= htmlspecialchars($b['status']) ?></td>
                  <td><?= $b['request_date'] ?></td>
                </tr>
              <?php endforeach; ?>
            <?php else: ?>
              <tr>
                <td colspan="6" class="text-center text-muted">No budget requests found.</td>
              </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Keep active tab after reload
document.addEventListener("DOMContentLoaded", function () {
  const hash = window.location.hash;
  if (hash) {
    const tab = document.querySelector(`button[data-bs-target="${hash}"]`);
    if (tab) {
      new bootstrap.Tab(tab).show();
    }
  }

  const tabs = document.querySelectorAll('button[data-bs-toggle="tab"]');
  tabs.forEach(tab => {
    tab.addEventListener("shown.bs.tab", function (e) {
      history.replaceState(null, null, e.target.getAttribute("data-bs-target"));
    });
  });
});
</script>
</body>
</html>
