<?php
include '../../SQL/config.php';
include 'classes/Sales.php';

if (!isset($_SESSION['pharmacy']) || $_SESSION['pharmacy'] !== true) {
    header('Location: login.php');
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

$sales = new Sales($conn);

// Default period for summary cards
$period = $_GET['period'] ?? 'all';

// Fetch data based on selected period
$totalSales      = $sales->getTotalSales($period);
$totalOrders     = $sales->getTotalOrders($period);
$dispensedToday  = $sales->getDispensedToday();
$totalStocks     = $sales->getTotalStocks();
$categoryDataRaw = $sales->getRevenueByCategory($period);
$topProducts     = $sales->getTopProducts($period);

// Prepare category chart data
$categoryLabels = [];
$categoryValues = [];
foreach ($categoryDataRaw as $cat) {
    $categoryLabels[] = $cat['category'];
    $categoryValues[] = floatval($cat['total']);
}
// -------------------- Sales Performance --------------------
$performance = $sales->getSalesPerformance();

// Weekly sales (Sun, Mon, ...)
$weeklyLabels = array_keys($performance['weekly']);
$weeklyValues = array_values($performance['weekly']);

// Monthly sales (days of month 1-31)
$monthlyLabels = array_keys($performance['monthly']); // 1, 2, 3 ...
$monthlyValues = array_values($performance['monthly']);

// Yearly sales (months Jan-Dec)
$yearlyLabels = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
$yearlyValues = [];
foreach ($yearlyLabels as $i => $monthName) {
    $yearlyValues[] = $performance['yearly'][$i + 1] ?? 0; // 1-12
}

// Determine initial period for chart (from dropdown)
$salesPeriod = $_GET['sales_period'] ?? 'week';


// Query all medicines
$query = "SELECT med_name, stock_quantity FROM pharmacy_inventory ORDER BY med_name ASC";
$result = $conn->query($query);

// Group medicines by stock thresholds
$noStock = [];
$lowStock = [];
$nearLowStock = [];
$highStock = [];

while ($row = $result->fetch_assoc()) {
    $qty = (int)$row['stock_quantity'];
    if ($qty == 0) {
        $noStock[] = $row;
    } elseif ($qty >= 1 && $qty <= 10) {
        $lowStock[] = $row;
    } elseif ($qty >= 11 && $qty <= 50) {
        $nearLowStock[] = $row;
    } else {
        $highStock[] = $row;
    }
}

$notif_sql = "SELECT COUNT(*) AS pending 
              FROM pharmacy_prescription 
              WHERE status = 'Pending'";
$notif_res = $conn->query($notif_sql);

$pendingCount = 0;
if ($notif_res && $notif_res->num_rows > 0) {
    $notif_row = $notif_res->fetch_assoc();
    $pendingCount = $notif_row['pending'];
}
?>




<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HMS | Pharmacy Management</title>
    <link rel="shortcut icon" href="assets/image/favicon.ico" type="image/x-icon">
    <link rel="stylesheet" href="assets/CSS/bootstrap.min.css">
    <link rel="stylesheet" href="assets/CSS/super.css">
    <link rel="stylesheet" href="assets/CSS/med_inventory.css">
    <link rel="stylesheet" href="assets/CSS/prescription.css">

</head>

<body>
    <div class="d-flex">
        <!----- Sidebar ----->
        <aside id="sidebar" class="sidebar-toggle">

            <div class="sidebar-logo mt-3">
                <img src="assets/image/logo-dark.png" width="90px" height="20px">
            </div>

            <div class="menu-title">Pharmacy Management | <span>Sales</span></div>

            <!----- Sidebar Navigation ----->

            <li class="sidebar-item">
                <a href="pharmacy_dashboard.php" class="sidebar-link" data-bs-toggle="#" data-bs-target="#"
                    aria-expanded="false" aria-controls="auth">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-cast" viewBox="0 0 16 16">
                        <path d="m7.646 9.354-3.792 3.792a.5.5 0 0 0 .353.854h7.586a.5.5 0 0 0 .354-.854L8.354 9.354a.5.5 0 0 0-.708 0" />
                        <path d="M11.414 11H14.5a.5.5 0 0 0 .5-.5v-7a.5.5 0 0 0-.5-.5h-13a.5.5 0 0 0-.5.5v7a.5.5 0 0 0 .5.5h3.086l-1 1H1.5A1.5 1.5 0 0 1 0 10.5v-7A1.5 1.5 0 0 1 1.5 2h13A1.5 1.5 0 0 1 16 3.5v7a1.5 1.5 0 0 1-1.5 1.5h-2.086z" />
                    </svg>
                    <span style="font-size: 18px;">Dashboard</span>
                </a>
            </li>

            <li class="sidebar-item">
                <a href="pharmacy_med_inventory.php" class="sidebar-link" data-bs-toggle="#" data-bs-target="#"
                    aria-expanded="false" aria-controls="auth">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="fa-solid fa-capsules" viewBox="0 0 16 16">
                        <path d="m7.646 9.354-3.792 3.792a.5.5 0 0 0 .353.854h7.586a.5.5 0 0 0 .354-.854L8.354 9.354a.5.5 0 0 0-.708 0" />
                        <path d="M11.414 11H14.5a.5.5 0 0 0 .5-.5v-7a.5.5 0 0 0-.5-.5h-13a.5.5 0 0 0-.5.5v7a.5.5 0 0 0 .5.5h3.086l-1 1H1.5A1.5 1.5 0 0 1 0 10.5v-7A1.5 1.5 0 0 1 1.5 2h13A1.5 1.5 0 0 1 16 3.5v7a1.5 1.5 0 0 1-1.5 1.5h-2.086z" />
                    </svg>
                    <span style="font-size: 18px;">Medicine Inventory</span>
                </a>
            </li>

            <li class="sidebar-item">
                <a class="sidebar-link position-relative" data-bs-toggle="collapse" href="#prescriptionMenu" role="button" aria-expanded="false" aria-controls="prescriptionMenu">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="fa-solid fa-file-prescription" viewBox="0 0 16 16">
                        <path d="m7.646 9.354-3.792 3.792a.5.5 0 0 0 .353.854h7.586a.5.5 0 0 0 .354-.854L8.354 9.354a.5.5 0 0 0-.708 0" />
                        <path d="M11.414 11H14.5a.5.5 0 0 0 .5-.5v-7a.5.5 0 0 0-.5-.5h-13a.5.5 0 0 0-.5.5v7a.5.5 0 0 0 .5.5h3.086l-1 1H1.5A1.5 1.5 0 0 1 0 10.5v-7A1.5 1.5 0 0 1 1.5 2h13A1.5 1.5 0 0 1 16 3.5v7a1.5 1.5 0 0 1-1.5 1.5h-2.086z" />
                    </svg>
                    <span style="font-size: 18px;">Prescription</span>
                    <?php if ($pendingCount > 0): ?>
                        <span class="notif-dot"></span>
                    <?php endif; ?>
                </a>

                <ul class="collapse list-unstyled ms-3" id="prescriptionMenu">
                    <li>
                        <a href="pharmacy_prescription.php" class="sidebar-link position-relative">
                            View Prescriptions
                            <?php if ($pendingCount > 0): ?>
                                <span class="notif-badge"><?php echo $pendingCount; ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                    <li>
                        <a href="pharmacy_add_prescription.php" class="sidebar-link">Add Prescription</a>
                    </li>
                </ul>
            </li>

            <li class="sidebar-item">
                <a href="pharmacy_sales.php" class="sidebar-link" data-bs-toggle="#" data-bs-target="#"
                    aria-expanded="false" aria-controls="auth">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="fa-solid fa-chart-line" viewBox="0 0 16 16">
                        <path d="m7.646 9.354-3.792 3.792a.5.5 0 0 0 .353.854h7.586a.5.5 0 0 0 .354-.854L8.354 9.354a.5.5 0 0 0-.708 0" />
                        <path d="M11.414 11H14.5a.5.5 0 0 0 .5-.5v-7a.5.5 0 0 0-.5-.5h-13a.5.5 0 0 0-.5.5v7a.5.5 0 0 0 .5.5h3.086l-1 1H1.5A1.5 1.5 0 0 1 0 10.5v-7A1.5 1.5 0 0 1 1.5 2h13A1.5 1.5 0 0 1 16 3.5v7a1.5 1.5 0 0 1-1.5 1.5h-2.086z" />
                    </svg>
                    <span style="font-size: 18px;">Sales</span>
                </a>
            </li>
            <li class="sidebar-item">
                <a href="pharmacy_expiry_tracking.php" class="sidebar-link" data-bs-toggle="#" data-bs-target="#"
                    aria-expanded="false" aria-controls="auth">
                    <i class="fa-solid fa-calendar-check"></i>
                    <span style="font-size: 18px;">Drug Expiry Tracking</span>
                </a>
            </li>
            <li class="sidebar-item">
                <a href="pharmacy_supply_request.php" class="sidebar-link" data-bs-toggle="#" data-bs-target="#"
                    aria-expanded="false" aria-controls="auth">
                    <i class="fa-solid fa-boxes-stacked"></i>
                    <span style="font-size: 18px;">Supply Request</span>
                </a>
            </li>
        </aside>
        <!----- End of Sidebar ----->
        <!----- Main Content ----->
        <div class="main">
            <div class="topbar">
                <div class="toggle">
                    <button class="toggler-btn" type="button">
                        <svg xmlns="http://www.w3.org/2000/svg" width="30px" height="30px" fill="currentColor" class="bi bi-list-ul"
                            viewBox="0 0 16 16">
                            <path fill-rule="evenodd"
                                d="M5 11.5a.5.5 0 0 1 .5-.5h9a.5.5 0 0 1 0 1h-9a.5.5 0 0 1-.5-.5m0-4a.5.5 0 0 1 .5-.5h9a.5.5 0 0 1 0 1h-9a.5.5 0 0 1-.5-.5m0-4a.5.5 0 0 1 .5-.5h9a.5.5 0 0 1 0 1h-9a.5.5 0 0 1-.5-.5m-3 1a1 1 0 1 0 0-2 1 1 0 0 0 0 2m0 4a1 1 0 1 0 0-2 1 1 0 0 0 0 2m0 4a1 1 0 1 0 0-2 1 1 0 0 0 0 2" />
                        </svg>
                    </button>
                </div>
                <div class="logo">
                    <div class="dropdown d-flex align-items-center">
                        <span class="username ml-1 me-2"><?php echo $user['fname']; ?> <?php echo $user['lname']; ?></span><!-- Display the logged-in user's name -->
                        <button class="btn dropdown-toggle" type="button" id="dropdownMenuButton" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-person-circle"></i>
                        </button>
                        <ul class="dropdown-menu" aria-labelledby="dropdownMenuButton" style="min-width: 200px; padding: 10px; border-radius: 5px; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); background-color: #fff; color: #333;">
                            <li style="margin-bottom: 8px; font-size: 14px; color: #555;">
                                <span>Welcome <strong style="color: #007bff;"><?php echo $user['lname']; ?></strong>!</span>
                            </li>
                            <li>
                                <a class="dropdown-item" href="../logout.php" style="font-size: 14px; color: #007bff; text-decoration: none; padding: 8px 12px; border-radius: 4px; transition: background-color 0.3s ease;">
                                    Logout
                                </a>
                            </li>
                        </ul>

                    </div>
                </div>
            </div>
            <!-- START CODING HERE -->
            <div class="container-fluid py-4">
                <div class="title-container">
                    <i class="fa-solid fa-chart-line"></i>
                    <h1 class="page-title">Sales</h1>
                </div>
                <div id="dashboardContent">
                    <!-- Row 1: Sales Summary -->
                    <div class="row mb-4 align-items-center">
                        <div class="col-md-6 col-lg-3">
                            <div class="card shadow-sm p-3 rounded-3">
                                <div class="d-flex align-items-center justify-content-between mb-2">
                                    <h6 class="mb-0" style="font-weight: 700;">Total Sale</h6>
                                    <div class="d-flex align-items-center">
                                        <!-- Period selector -->
                                        <form method="get" class="d-flex align-items-center mb-0 me-2">
                                            <i class="fa-solid fa-calendar-days me-2"></i>
                                            <select name="period" class="form-select form-select-sm" onchange="this.form.submit()">
                                                <option value="all" <?= $period === 'all' ? 'selected' : '' ?>>All Time</option>
                                                <option value="7days" <?= $period === '7days' ? 'selected' : '' ?>>Last 7 Days</option>
                                                <option value="month" <?= $period === 'month' ? 'selected' : '' ?>>This Month</option>
                                                <option value="last_month" <?= $period === 'last_month' ? 'selected' : '' ?>>Last Month</option>
                                            </select>
                                        </form>
                                    </div>
                                </div>
                                <h3>₱<?= number_format($totalSales, 2) ?></h3>
                            </div>
                        </div>

                        <div class="col-md-6 col-lg-3">
                            <div class="card shadow-sm p-3 rounded-3">
                                <h6 style="font-weight: 700;">Total Orders</h6>
                                <h3><?= $totalOrders ?></h3>
                            </div>
                        </div>

                        <div class="col-md-6 col-lg-3">
                            <div class="card shadow-sm p-3 rounded-3">
                                <h6 style="font-weight: 700;">Dispensed Medicines Today</h6>
                                <h3><?= $dispensedToday ?></h3>
                            </div>
                        </div>

                        <div class="col-md-6 col-lg-3">
                            <div class="card shadow-sm p-3 rounded-3">
                                <h6 style="font-weight: 700;">Total Stocks</h6>
                                <h3><?= $totalStocks ?></h3>
                            </div>
                        </div>

                        <!-- Download Button -->
                        <div class="col-md-12 d-flex justify-content-end mt-3">
                            <button id="downloadPDFBtn" class="btn btn-primary" style="margin-right: 100px;">
                                <i class="fa-solid fa-download me-2"></i>Download Report
                            </button>
                        </div>


                    </div>




                    <!-- Row 2: Charts + Sales Performance -->
                    <div class="row mb-4">
                        <!-- Revenue by Category Chart -->
                        <div class="col-md-6">
                            <div class="card shadow-sm p-3 rounded-3">
                                <h6 style="font-weight: 700;">Revenue By Category</h6>
                                <div style="position: relative; height: 300px; width: 100%;">
                                    <canvas id="categoryChart"></canvas>
                                </div>
                            </div>
                        </div>

                        <!-- Sales Performance Card -->
                        <div class="col-md-6 col-lg-4">
                            <div class="card shadow-sm p-3 rounded-3">
                                <div class="d-flex justify-content-between align-items-center">
                                    <h6 style="font-weight: 700;">Sales Performance</h6>
                                    <form method="get" class="mb-0 d-flex align-items-center">
                                        <i class="fa-solid fa-calendar-days me-2"></i>
                                        <select name="sales_period" class="form-select form-select-sm" onchange="this.form.submit()">
                                            <option value="week" <?= $salesPeriod === 'week' ? 'selected' : '' ?>>This Week</option>
                                            <option value="month" <?= $salesPeriod === 'month' ? 'selected' : '' ?>>This Month</option>
                                            <option value="year" <?= $salesPeriod === 'year' ? 'selected' : '' ?>>This Year</option>
                                        </select>
                                    </form>
                                </div>
                                <div style="position: relative; height: 300px; width: 100%;">
                                    <canvas id="salesPerformanceChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Stock Thresholds -->
                    <div class="row mb-4">
                        <!-- High Stock -->
                        <div class="col-md-6">
                            <div class="card shadow-sm p-3 rounded-3">
                                <h6 style="font-weight: 700; color: green;">High Stock</h6>

                                <div style="max-height: 300px; overflow-y: auto;">
                                    <ul class="mt-2 mb-0 text-start">
                                        <?php if (!empty($highStock)): ?>
                                            <?php foreach ($highStock as $med): ?>
                                                <li>
                                                    <?= htmlspecialchars($med['med_name']) ?>
                                                    <span class="badge bg-success"><?= $med['stock_quantity'] ?></span>
                                                </li>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <li>None</li>
                                        <?php endif; ?>
                                    </ul>
                                </div>
                            </div>
                        </div>

                        <!-- Near Low Stock -->
                        <div class="col-md-6">
                            <div class="card shadow-sm p-3 rounded-3">
                                <h6 style="font-weight: 700; color: #ffc107;">Near Low Stock</h6>

                                <div style="max-height: 300px; overflow-y: auto;">
                                    <ul class="mt-2 mb-0 text-start">
                                        <?php if (!empty($nearLowStock)): ?>
                                            <?php foreach ($nearLowStock as $med): ?>
                                                <li>
                                                    <?= htmlspecialchars($med['med_name']) ?>
                                                    <span class="badge bg-warning text-dark"><?= $med['stock_quantity'] ?></span>
                                                </li>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <li>None</li>
                                        <?php endif; ?>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row mb-4">
                        <!-- Low Stock -->
                        <div class="col-md-6">
                            <div class="card shadow-sm p-3 rounded-3">
                                <h6 style="font-weight: 700; color: orange;">Low Stock</h6>
                                <div style="max-height: 300px; overflow-y: auto;">
                                    <ul class="mt-2 mb-0 text-start">
                                        <?php if (!empty($lowStock)): ?>
                                            <?php foreach ($lowStock as $med): ?>
                                                <li>
                                                    <?= htmlspecialchars($med['med_name']) ?>
                                                    <span class="badge bg-danger"><?= $med['stock_quantity'] ?></span>
                                                </li>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <li>None</li>
                                        <?php endif; ?>
                                    </ul>
                                </div>
                            </div>
                        </div>

                        <!-- No Stock -->
                        <div class="col-md-6">
                            <div class="card shadow-sm p-3 rounded-3">
                                <h6 style="font-weight: 700; color: red;">No Stock</h6>
                                <div style="max-height: 300px; overflow-y: auto;">
                                    <ul class="mt-2 mb-0 text-start">
                                        <?php if (!empty($noStock)): ?>
                                            <?php foreach ($noStock as $med): ?>
                                                <li>
                                                    <?= htmlspecialchars($med['med_name']) ?>
                                                    <span class="badge bg-secondary"><?= $med['stock_quantity'] ?></span>
                                                </li>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <li>None</li>
                                        <?php endif; ?>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>



                    <!-- Row 3: Top Selling Products -->
                    <div class="row">
                        <div class="col-12">
                            <div class="card shadow-sm p-3 rounded-3">
                                <h6 style="font-weight: 700;">Top Selling Products</h6>
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Product Name</th>
                                            <th>Category</th>
                                            <th>Quantity</th>
                                            <th>Total Price</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php while ($row = $topProducts->fetch_assoc()): ?>
                                            <tr>
                                                <td><?= $row['med_name'] ?></td>
                                                <td><?= $row['category'] ?></td>
                                                <td><?= $row['qty'] ?></td>
                                                <td>₱<?= number_format($row['total'], 2) ?></td>
                                            </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Chart.js -->
            <!-- Chart.js -->
            <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
            <script>
                const categoryCtx = document.getElementById('categoryChart').getContext('2d');
                new Chart(categoryCtx, {
                    type: 'doughnut',
                    data: {
                        labels: <?= json_encode($categoryLabels) ?>,
                        datasets: [{
                            data: <?= json_encode($categoryValues) ?>,
                            backgroundColor: ['#6CCF6C', '#F1C40F', '#E74C3C', '#3498DB', '#9B59B6']
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'right',
                                labels: {
                                    generateLabels: function(chart) {
                                        const data = chart.data;
                                        return data.labels.map((label, i) => {
                                            const value = data.datasets[0].data[i];
                                            return {
                                                text: label + ' - ₱' + Number(value).toLocaleString('en-PH', {
                                                    minimumFractionDigits: 2,
                                                    maximumFractionDigits: 2
                                                }),
                                                fillStyle: data.datasets[0].backgroundColor[i],
                                                hidden: false,
                                                index: i
                                            };
                                        });
                                    }
                                }
                            },
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        let label = context.label || '';
                                        let value = context.raw || 0;
                                        return label + ': ₱' + Number(value).toLocaleString('en-PH', {
                                            minimumFractionDigits: 2,
                                            maximumFractionDigits: 2
                                        });
                                    }
                                }
                            }
                        }
                    }
                });


                // -------------------- Sales Performance --------------------
                const salesCtx = document.getElementById('salesPerformanceChart').getContext('2d');

                const salesData = {
                    week: {
                        labels: <?= json_encode($weeklyLabels) ?>,
                        data: <?= json_encode($weeklyValues) ?>
                    },
                    month: {
                        labels: <?= json_encode($monthlyLabels) ?>,
                        data: <?= json_encode($monthlyValues) ?>
                    },
                    year: {
                        labels: <?= json_encode($yearlyLabels) ?>,
                        data: <?= json_encode($yearlyValues) ?>
                    }
                };

                // Initial chart based on selected period
                let salesChart = new Chart(salesCtx, {
                    type: 'bar',
                    data: {
                        labels: salesData['<?= $salesPeriod ?>'].labels,
                        datasets: [{
                            label: '₱ Sales',
                            data: salesData['<?= $salesPeriod ?>'].data,
                            backgroundColor: '#3498DB'
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                display: false
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true
                            }
                        }
                    }
                });

                // Update chart when dropdown changes
                document.querySelector('select[name="sales_period"]').addEventListener('change', function() {
                    const period = this.value;
                    salesChart.data.labels = salesData[period].labels;
                    salesChart.data.datasets[0].data = salesData[period].data;
                    salesChart.update();
                });
            </script>
            <script>
                document.getElementById("downloadPDFBtn").addEventListener("click", function() {
                    const {
                        jsPDF
                    } = window.jspdf;
                    const dashboard = document.querySelector(".container-fluid"); // Capture this section
                    const pdf = new jsPDF('p', 'mm', 'a4'); // 'p' = portrait, 'l' = landscape

                    // High-quality canvas capture
                    html2canvas(dashboard, {
                        scale: 3
                    }).then(canvas => {
                        const imgData = canvas.toDataURL('image/jpeg', 1.0);
                        const pdfWidth = pdf.internal.pageSize.getWidth();
                        const pdfHeight = (canvas.height * pdfWidth) / canvas.width;

                        let heightLeft = pdfHeight;
                        let position = 0;

                        // Add first page
                        pdf.addImage(imgData, 'JPEG', 0, position, pdfWidth, pdfHeight);
                        heightLeft -= pdf.internal.pageSize.getHeight();

                        // Add additional pages if content is taller than one page
                        while (heightLeft > 0) {
                            position = heightLeft - pdfHeight;
                            pdf.addPage();
                            pdf.addImage(imgData, 'JPEG', 0, position, pdfWidth, pdfHeight);
                            heightLeft -= pdf.internal.pageSize.getHeight();
                        }

                        pdf.save("Pharmacy_Sales_Report.pdf");
                    });
                });
            </script>






        </div>

        <!----- End of Main Content ----->
    </div>
    <script>
        const toggler = document.querySelector(".toggler-btn");
        toggler.addEventListener("click", function() {
            document.querySelector("#sidebar").classList.toggle("collapsed");
        });
    </script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>

    <script src="assets/Bootstrap/all.min.js"></script>
    <script src="assets/Bootstrap/bootstrap.bundle.min.js"></script>
    <script src="assets/Bootstrap/fontawesome.min.js"></script>
    <script src="assets/Bootstrap/jq.js"></script>
</body>

</html>