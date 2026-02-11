<?php
session_start();
include '../../SQL/config.php';

// --- AUTH ---
if (!isset($_SESSION['user_id'])) die("Login required.");

// --- Fetch Inventory Totals ---
$inventory_stmt = $pdo->query("
    SELECT 
        COUNT(*) AS total_items,
        COALESCE(SUM(quantity),0) AS total_quantity,
        COALESCE(SUM(quantity * price),0) AS total_value
    FROM inventory
");
$inventory_data = $inventory_stmt->fetch(PDO::FETCH_ASSOC);

// --- Fetch Inventory by Item Type for Graph ---
$item_type_stmt = $pdo->query("
    SELECT 
        COALESCE(item_type,'Uncategorized') AS item_type,
        COUNT(*) AS item_count,
        COALESCE(SUM(quantity),0) AS total_quantity,
        COALESCE(SUM(quantity * price),0) AS total_value
    FROM inventory
    GROUP BY item_type
    ORDER BY total_value DESC
");
$item_type_data = $item_type_stmt->fetchAll(PDO::FETCH_ASSOC);

// Prepare data for charts
$item_types = [];
$total_values = [];
$total_quantities = [];
foreach ($item_type_data as $i) {
    $item_types[] = $i['item_type'];
    $total_values[] = (float)$i['total_value'];
    $total_quantities[] = (float)$i['total_quantity'];
}

// Convert to JSON for Chart.js
$item_types_json = json_encode($item_types);
$values_json = json_encode($total_values);
$quantities_json = json_encode($total_quantities);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Hospital Assets Dashboard</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<link rel="stylesheet" href="assets/CSS/inventory_dashboard.css">
<link rel="stylesheet" href="assets/CSS/dashboard.css">
</head>
<body>

<div class="main-sidebar">
    <?php include 'inventory_sidebar.php'; ?>
</div>

<div class="main-content">
  <div class="container-fluid py-4">
    <h2 class="text-center mb-4">🏥 Hospital Assets Dashboard</h2>

    <!-- Summary Row -->
    <div class="row g-4 mb-4">
      <div class="col-md-3">
        <div class="card text-center bg-primary text-white">
          <div class="card-body">
            <h5>Total Inventory Items</h5>
            <h3><?= number_format($inventory_data['total_items']) ?></h3>
          </div>
        </div>
      </div>
      <div class="col-md-3">
        <div class="card text-center bg-success text-white">
          <div class="card-body">
            <h5>Total Quantity</h5>
            <h3><?= number_format($inventory_data['total_quantity']) ?></h3>
          </div>
        </div>
      </div>
      <div class="col-md-3">
        <div class="card text-center bg-warning text-dark">
          <div class="card-body">
            <h5>Total Asset Value</h5>
            <h3>₱<?= number_format($inventory_data['total_value'],2) ?></h3>
          </div>
        </div>
      </div>
    </div>

    <!-- Charts Row -->
    <div class="row g-4 mb-5">
      <div class="col-md-6">
        <div class="card">
          <div class="card-header bg-light">
            <h5 class="mb-0 text-center">Asset Value by Item Type</h5>
          </div>
          <div class="card-body">
            <canvas id="assetValueChart" height="250"></canvas>
          </div>
        </div>
      </div>

      <div class="col-md-6">
        <div class="card">
          <div class="card-header bg-light">
            <h5 class="mb-0 text-center">Inventory Quantity by Item Type</h5>
          </div>
          <div class="card-body">
            <canvas id="assetQuantityChart" height="250"></canvas>
          </div>
        </div>
      </div>
    </div>

    <!-- Table Section -->
    <div class="row">
      <div class="col-12">
        <div class="card">
          <div class="card-header bg-dark text-white">
            <h5 class="mb-0">Inventory Assets Overview by Item Type</h5>
          </div>
          <div class="card-body p-0">
            <table class="table table-striped table-bordered mb-0 text-center">
              <thead class="table-light">
                <tr>
                  <th>Item Type</th>
                  <th>Number of Items</th>
                  <th>Total Quantity</th>
                  <th>Total Value</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($item_type_data as $i): ?>
                  <tr>
                    <td><?= htmlspecialchars($i['item_type']) ?></td>
                    <td><?= number_format($i['item_count']) ?></td>
                    <td><?= number_format($i['total_quantity']) ?></td>
                    <td class="fw-bold text-success">₱<?= number_format($i['total_value'],2) ?></td>
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
const itemTypes = <?= $item_types_json ?>;
const values = <?= $values_json ?>;
const quantities = <?= $quantities_json ?>;

// BAR CHART — Asset Value by Item Type
new Chart(document.getElementById('assetValueChart'), {
  type: 'bar',
  data: {
    labels: itemTypes,
    datasets: [{
      label: 'Asset Value (₱)',
      data: values,
      backgroundColor: 'rgba(54, 162, 235, 0.7)'
    }]
  },
  options: {
    responsive: true,
    plugins: {
      legend: { display: false },
    },
    scales: {
      y: {
        beginAtZero: true,
        ticks: {
          callback: value => '₱' + value.toLocaleString()
        }
      }
    }
  }
});

// BAR CHART — Quantity by Item Type
new Chart(document.getElementById('assetQuantityChart'), {
  type: 'bar',
  data: {
    labels: itemTypes,
    datasets: [{
      label: 'Quantity',
      data: quantities,
      backgroundColor: 'rgba(255, 159, 64, 0.7)'
    }]
  },
  options: {
    responsive: true,
    plugins: {
      legend: { display: false },
    },
    scales: {
      y: { beginAtZero: true }
    }
  }
});
</script>

</body>
</html>
