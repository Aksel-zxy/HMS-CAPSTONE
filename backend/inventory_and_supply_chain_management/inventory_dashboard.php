<?php
session_start();
require 'db.php';

// --- AUTH ---
if (!isset($_SESSION['user_id'])) die("Login required.");

// --- Fetch Total Order Requests ---
$order_stmt = $pdo->query("SELECT COUNT(*) FROM purchase_requests");
$total_orders = $order_stmt->fetchColumn();

// --- Fetch Total Approved Budget (All Departments) ---
$budget_stmt = $pdo->query("SELECT COALESCE(SUM(approved_amount),0) FROM department_budgets WHERE status='Approved'");
$total_approved_budget = $budget_stmt->fetchColumn();

// --- Fetch Total Spent (All Departments) ---
$spent_stmt = $pdo->query("SELECT COALESCE(SUM(total_price),0) FROM purchase_requests WHERE status IN ('Approved','Completed')");
$total_spent = $spent_stmt->fetchColumn();

// --- Remaining Budget ---
$remaining_budget = max($total_approved_budget - $total_spent, 0);

// --- Fetch Budget Per Department ---
$dept_stmt = $pdo->query("
    SELECT 
        u.department,
        COALESCE(SUM(b.approved_amount),0) AS approved_budget,
        (
            SELECT COALESCE(SUM(pr.total_price),0)
            FROM purchase_requests pr
            WHERE pr.user_id = u.user_id AND pr.status IN ('Approved','Completed')
        ) AS spent_amount
    FROM users u
    LEFT JOIN department_budgets b ON b.user_id = u.user_id AND b.status='Approved'
    WHERE u.department IS NOT NULL AND u.department != ''
    GROUP BY u.department
");
$dept_data = $dept_stmt->fetchAll(PDO::FETCH_ASSOC);

// Prepare data for charts
$departments = [];
$approved_budgets = [];
$spent_budgets = [];
foreach ($dept_data as $d) {
    $departments[] = $d['department'];
    $approved_budgets[] = (float)$d['approved_budget'];
    $spent_budgets[] = (float)$d['spent_amount'];
}

// Convert to JSON for Chart.js
$departments_json = json_encode($departments);
$approved_json = json_encode($approved_budgets);
$spent_json = json_encode($spent_budgets);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Inventory Dashboard</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<link rel="stylesheet" href="assets/css/dashboard.css">
<style>

</style>
</head>
<body>

<div class="main-sidebar">
    <?php include 'inventory_sidebar.php'; ?>
</div>

<div class="main-content">
  <div class="container-fluid py-4">
    <h2 class="text-center mb-4">📊 Inventory & Budget Dashboard</h2>

    <!-- Summary Row -->
    <div class="row g-4 mb-4">
      <div class="col-md-3">
        <div class="card text-center bg-primary text-white">
          <div class="card-body">
            <h5>Total Order Requests</h5>
            <h3><?= number_format($total_orders) ?></h3>
          </div>
        </div>
      </div>
      <div class="col-md-3">
        <div class="card text-center bg-success text-white">
          <div class="card-body">
            <h5>Total Approved Budget</h5>
            <h3>₱<?= number_format($total_approved_budget,2) ?></h3>
          </div>
        </div>
      </div>
      <div class="col-md-3">
        <div class="card text-center bg-warning text-dark">
          <div class="card-body">
            <h5>Total Spent</h5>
            <h3>₱<?= number_format($total_spent,2) ?></h3>
          </div>
        </div>
      </div>
      <div class="col-md-3">
        <div class="card text-center bg-info text-white">
          <div class="card-body">
            <h5>Remaining Budget</h5>
            <h3>₱<?= number_format($remaining_budget,2) ?></h3>
          </div>
        </div>
      </div>
    </div>

    <!-- Charts Row -->
    <div class="row g-4">
      <div class="col-md-6">
        <div class="card">
          <div class="card-header bg-light">
            <h5 class="mb-0 text-center">Budget Distribution by Department</h5>
          </div>
          <div class="card-body">
            <canvas id="budgetPieChart" height="250"></canvas>
          </div>
        </div>
      </div>

      <div class="col-md-6">
        <div class="card">
          <div class="card-header bg-light">
            <h5 class="mb-0 text-center">Allocated vs Spent (per Department)</h5>
          </div>
          <div class="card-body">
            <canvas id="budgetBarChart" height="250"></canvas>
          </div>
        </div>
      </div>
    </div>

    <!-- Table Section -->
    <div class="row mt-5">
      <div class="col-12">
        <div class="card">
          <div class="card-header bg-dark text-white">
            <h5 class="mb-0">Department Budget Overview</h5>
          </div>
          <div class="card-body p-0">
            <table class="table table-striped table-bordered mb-0 text-center">
              <thead class="table-light">
                <tr>
                  <th>Department</th>
                  <th>Approved Budget</th>
                  <th>Total Spent</th>
                  <th>Remaining</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($dept_data as $d): 
                  $remaining = max($d['approved_budget'] - $d['spent_amount'], 0);
                ?>
                  <tr>
                    <td><?= htmlspecialchars($d['department']) ?></td>
                    <td>₱<?= number_format($d['approved_budget'],2) ?></td>
                    <td>₱<?= number_format($d['spent_amount'],2) ?></td>
                    <td class="fw-bold text-success">₱<?= number_format($remaining,2) ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
const departments = <?= $departments_json ?>;
const approvedBudgets = <?= $approved_json ?>;
const spentBudgets = <?= $spent_json ?>;

// PIE CHART — Budget per Department
new Chart(document.getElementById('budgetPieChart'), {
  type: 'pie',
  data: {
    labels: departments,
    datasets: [{
      data: approvedBudgets,
      backgroundColor: [
        '#007bff','#28a745','#ffc107','#dc3545','#17a2b8','#6f42c1','#20c997'
      ],
    }]
  },
  options: {
    plugins: {
      legend: { position: 'bottom' }
    }
  }
});

// BAR CHART — Allocated vs Spent
new Chart(document.getElementById('budgetBarChart'), {
  type: 'bar',
  data: {
    labels: departments,
    datasets: [
      {
        label: 'Approved Budget',
        data: approvedBudgets,
        backgroundColor: 'rgba(54, 162, 235, 0.7)',
      },
      {
        label: 'Spent',
        data: spentBudgets,
        backgroundColor: 'rgba(255, 99, 132, 0.7)',
      }
    ]
  },
  options: {
    responsive: true,
    plugins: {
      legend: { position: 'top' },
    },
    scales: {
      y: { beginAtZero: true, ticks: { callback: value => '₱' + value.toLocaleString() } }
    }
  }
});
</script>

</body>
</html>
