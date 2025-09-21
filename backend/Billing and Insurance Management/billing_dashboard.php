<?php
session_start();
include '../../SQL/config.php';

if (!isset($_SESSION['billing']) || $_SESSION['billing'] !== true) {
    header('Location: login.php'); // Redirect to login if not logged in
    exit();
}

if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    echo "User ID is not set in session.";
    exit();
}

$query = "SELECT * FROM users WHERE user_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if (!$user) {
    echo "No user found.";
    exit();
}

// --- Totals ---
$total_patients = $conn->query("SELECT COUNT(DISTINCT patient_id) AS cnt FROM patient_receipt")->fetch_assoc()['cnt'];
$total_receipts = $conn->query("SELECT COUNT(*) AS cnt FROM patient_receipt")->fetch_assoc()['cnt'];
$total_paid = $conn->query("SELECT SUM(grand_total) AS total FROM patient_receipt WHERE status='Paid'")->fetch_assoc()['total'] ?? 0;
$total_unpaid = $conn->query("SELECT SUM(grand_total) AS total FROM patient_receipt WHERE status!='Paid'")->fetch_assoc()['total'] ?? 0;

// --- Payment Methods Breakdown ---
$payment_methods = [];
$result = $conn->query("SELECT payment_method, COUNT(*) AS count, SUM(grand_total) AS total FROM patient_receipt GROUP BY payment_method");
while ($row = $result->fetch_assoc()) {
    $payment_methods[] = $row;
}

// --- Recent Receipts ---
$recent_receipts = $conn->query("SELECT * FROM patient_receipt ORDER BY created_at DESC LIMIT 5")->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Billing Dashboard</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="assets/CSS/billing_dashboard.css">
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
<div class="dashboard-wrapper">

    <!-- Sidebar -->
    <div class="main-sidebar">
<?php include 'billing_sidebar.php'; ?>
</div>

    <!-- Main content -->
    <div class="main-content-wrapper" id="mainContent">
        <div class="container-fluid">
            <h1 class="mb-4">📊 Billing Dashboard</h1>

            <!-- Summary Cards -->
            <div class="row g-4 mb-4">
                <div class="col-md-3">
                    <div class="card text-bg-primary shadow-sm">
                        <div class="card-body">
                            <h5 class="card-title">Total Patients</h5>
                            <h3><?= number_format($total_patients) ?></h3>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-bg-success shadow-sm">
                        <div class="card-body">
                            <h5 class="card-title">Total Receipts</h5>
                            <h3><?= number_format($total_receipts) ?></h3>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-bg-info shadow-sm">
                        <div class="card-body">
                            <h5 class="card-title">Total Paid</h5>
                            <h3>₱<?= number_format($total_paid, 2) ?></h3>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-bg-danger shadow-sm">
                        <div class="card-body">
                            <h5 class="card-title">Unpaid Amount</h5>
                            <h3>₱<?= number_format($total_unpaid, 2) ?></h3>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Charts -->
            <div class="row g-4 mb-4">
                <div class="col-md-6">
                    <div class="card shadow-sm">
                        <div class="card-body">
                            <h5 class="card-title">Revenue by Payment Method</h5>
                            <div class="chart-container">
                                <canvas id="paymentChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card shadow-sm">
                        <div class="card-body">
                            <h5 class="card-title">Paid vs Unpaid</h5>
                            <div class="chart-container">
                                <canvas id="statusChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Receipts Table -->
            <div class="card shadow-sm">
                <div class="card-body">
                    <h5 class="card-title">Recent Receipts</h5>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th>ID</th>
                                    <th>Patient ID</th>
                                    <th>Grand Total</th>
                                    <th>Payment Method</th>
                                    <th>Status</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($recent_receipts): ?>
                                    <?php foreach ($recent_receipts as $r): ?>
                                        <tr>
                                            <td><?= $r['receipt_id'] ?></td>
                                            <td><?= $r['patient_id'] ?></td>
                                            <td>₱<?= number_format($r['grand_total'], 2) ?></td>
                                            <td><?= htmlspecialchars($r['payment_method']) ?></td>
                                            <td>
                                                <span class="badge <?= $r['status']=='Paid'?'bg-success':'bg-warning' ?>">
                                                    <?= $r['status'] ?>
                                                </span>
                                            </td>
                                            <td><?= date("Y-m-d", strtotime($r['created_at'])) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td colspan="6" class="text-center">No receipts found.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    const sidebar = document.getElementById('mySidebar');
    const mainContent = document.getElementById('mainContent');
    const toggleBtn = document.getElementById('sidebarToggle');

    toggleBtn.addEventListener('click', () => {
        sidebar.classList.toggle('closed');
        mainContent.classList.toggle('shifted');
    });
</script>

<!-- Chart.js scripts -->
<script>
    const paymentData = {
        labels: <?= json_encode(array_column($payment_methods, 'payment_method')) ?>,
        datasets: [{
            data: <?= json_encode(array_column($payment_methods, 'total')) ?>,
            backgroundColor: ['#007bff','#28a745','#ffc107','#dc3545','#17a2b8'],
            borderWidth: 2,
            borderColor: '#fff'
        }]
    };
    new Chart(document.getElementById('paymentChart'), {
        type: 'pie',
        data: paymentData,
        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } } }
    });

    const statusData = {
        labels: ['Paid', 'Unpaid'],
        datasets: [{
            data: [<?= $total_paid ?>, <?= $total_unpaid ?>],
            backgroundColor: ['#28a745','#dc3545'],
            borderWidth: 2,
            borderColor: '#fff'
        }]
    };
    new Chart(document.getElementById('statusChart'), {
        type: 'doughnut',
        data: statusData,
        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } } }
    });
</script>
</body>
</html>
