<?php
include '../../SQL/config.php';
include 'classes/sales.php';
require_once "classes/notification.php";


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

$salesData = [];
$yearsQuery = mysqli_query($conn, "
    SELECT DISTINCT YEAR(date) AS year
    FROM (
        SELECT dispensed_date AS date FROM pharmacy_prescription_items
        UNION ALL
        SELECT sale_date AS date FROM pharmacy_sales
    ) AS combined
    ORDER BY year ASC
");

while ($yearRow = mysqli_fetch_assoc($yearsQuery)) {
    $year = $yearRow['year'];
    $salesData[$year] = array_fill(0, 12, 0);

    $salesQuery = mysqli_query($conn, "
        SELECT MONTH(date) AS month, SUM(total) AS total
        FROM (
            SELECT pi.dispensed_date AS date, pi.total_price AS total
            FROM pharmacy_prescription_items pi
            JOIN pharmacy_prescription p ON pi.prescription_id = p.prescription_id
            WHERE p.status='Dispensed' AND p.payment_type='cash'

            UNION ALL

            SELECT s.sale_date AS date, s.total_price AS total
            FROM pharmacy_sales s
            WHERE s.payment_method='cash'
        ) AS combined
        WHERE YEAR(date) = $year
        GROUP BY MONTH(date)
    ");

    while ($row = mysqli_fetch_assoc($salesQuery)) {
        $monthIndex = $row['month'] - 1; // 0-based
        $salesData[$year][$monthIndex] = (float)$row['total'];
    }
}



$sales = new Sales($conn);

// Default period for summary cards
$period = $_GET['period'] ?? 'all';

// Fetch data based on selected period
$totalSales      = $sales->getTotalcashSales($period);
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
    } elseif ($qty >= 1 && $qty <= 100) {
        $lowStock[] = $row;
    } elseif ($qty >= 101 && $qty <= 500) {
        $nearLowStock[] = $row;
    } else {
        $highStock[] = $row;
    }
}

// 🔔 Pending prescriptions count
$notif_sql = "SELECT COUNT(*) AS pending 
              FROM pharmacy_prescription 
              WHERE status = 'Pending'";
$notif_res = $conn->query($notif_sql);

$pendingCount = 0;
if ($notif_res && $notif_res->num_rows > 0) {
    $notif_row = $notif_res->fetch_assoc();
    $pendingCount = $notif_row['pending'];
}

// 🔴 Expiry (Near Expiry or Expired) count
$expiry_sql = "SELECT COUNT(*) AS expiry 
               FROM pharmacy_stock_batches 
               WHERE expiry_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)";
$expiry_res = $conn->query($expiry_sql);

$expiryCount = 0;
if ($expiry_res && $expiry_res->num_rows > 0) {
    $expiry_row = $expiry_res->fetch_assoc();
    $expiryCount = $expiry_row['expiry'];
}

$notif = new Notification($conn);
$latestNotifications = $notif->load();
$notifCount = $notif->notifCount;

// Initialize counts
$cashPayments = 0;
$billingPayments = 0;

// Prescription + OTC combined
$paymentQuery = "
    SELECT payment_type, COUNT(*) AS count
    FROM (
        -- Prescription items
        SELECT p.payment_type, pi.total_price
        FROM pharmacy_prescription_items pi
        JOIN pharmacy_prescription p ON pi.prescription_id = p.prescription_id
        WHERE p.status = 'Dispensed'

        UNION ALL

        -- OTC sales (assume cash)
        SELECT 'cash' AS payment_type, s.total_price
        FROM pharmacy_sales s
    ) AS combined
    GROUP BY payment_type
";

$result = $conn->query($paymentQuery);

while ($row = $result->fetch_assoc()) {
    if ($row['payment_type'] === 'cash') {
        $cashPayments = (int)$row['count'];
    } elseif ($row['payment_type'] === 'post_discharged') {
        $billingPayments = (int)$row['count'];
    }
}
$weeklyRevenue = array_fill(0, 7, 0);
$startOfWeek = date('Y-m-d', strtotime('last Sunday'));
$endOfWeek = date('Y-m-d', strtotime('next Saturday'));

$weeklyQuery = "
    SELECT DAYOFWEEK(date) AS day_num, SUM(total) AS total
    FROM (
        SELECT pi.dispensed_date AS date, pi.total_price AS total
        FROM pharmacy_prescription_items pi
        JOIN pharmacy_prescription p ON pi.prescription_id = p.prescription_id
        WHERE p.status = 'Dispensed' AND p.payment_type = 'cash'

        UNION ALL

        SELECT s.sale_date AS date, s.total_price AS total
        FROM pharmacy_sales s
        WHERE s.payment_method = 'cash'  -- corrected column name
    ) AS combined
    WHERE date BETWEEN '$startOfWeek' AND '$endOfWeek'
    GROUP BY DAYOFWEEK(date)
";
$result = $conn->query($weeklyQuery);
$weekDays = [];
while ($row = $result->fetch_assoc()) {
    $weekDays[(int)$row['day_num']] = (float)$row['total'];
}

// Fill the array from Sun (1) to Sat (7)
for ($i = 1; $i <= 7; $i++) {
    $weeklyRevenue[$i - 1] = $weekDays[$i] ?? 0;
}
$monthlyRevenue = array_fill(0, 12, 0);
$currentYear = date('Y');

$monthlyQuery = "
    SELECT MONTH(date) AS month_num, SUM(total) AS total
    FROM (
        SELECT pi.dispensed_date AS date, pi.total_price AS total
        FROM pharmacy_prescription_items pi
        JOIN pharmacy_prescription p ON pi.prescription_id = p.prescription_id
        WHERE p.status = 'Dispensed' AND p.payment_type = 'cash'
        UNION ALL
        SELECT s.sale_date AS date, s.total_price AS total
        FROM pharmacy_sales s
        WHERE s.payment_method = 'cash'
    ) AS combined
    WHERE YEAR(date) = $currentYear
    GROUP BY MONTH(date)
";

$result = $conn->query($monthlyQuery);
$months = [];
while ($row = $result->fetch_assoc()) {
    $months[(int)$row['month_num']] = (float)$row['total'];
}

for ($i = 1; $i <= 12; $i++) {
    $monthlyRevenue[$i - 1] = $months[$i] ?? 0;
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
    <link rel="stylesheet" href="assets/CSS/prescription.css">
    <link rel="stylesheet" href="assets/CSS/med_inventory.css">
</head>

<body>
    <div class="d-flex">
        <!----- Sidebar ----->
        <aside id="sidebar" class="sidebar-toggle">

            <div class="sidebar-logo mt-3">
                <img src="assets/image/logo-dark.png" width="90px" height="20px">
            </div>

            <div class="menu-title">Pharmacy Management | Dashboard</div>

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
                <a href="pharmacy_search_locate.php" class="sidebar-link" data-bs-toggle="#" data-bs-target="#"
                    aria-expanded="false" aria-controls="auth">
                    <i class="fa-brands fa-searchengin"></i>
                    <span style="font-size: 18px;">Search & Locate</span>
                </a>
            </li>

            <li class="sidebar-item">
                <a href="pharmacy_prescription.php" class="sidebar-link position-relative">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="fa-solid fa-file-prescription" viewBox="0 0 16 16">
                        <path d="m7.646 9.354-3.792 3.792a.5.5 0 0 0 .353.854h7.586a.5.5 0 0 0 .354-.854L8.354 9.354a.5.5 0 0 0-.708 0" />
                        <path d="M11.414 11H14.5a.5.5 0 0 0 .5-.5v-7a.5.5 0 0 0-.5-.5h-13a.5.5 0 0 0-.5.5v7a.5.5 0 0 0 .5.5h3.086l-1 1H1.5A1.5 1.5 0 0 1 0 10.5v-7A1.5 1.5 0 0 1 1.5 2h13A1.5 1.5 0 0 1 16 3.5v7a1.5 1.5 0 0 1-1.5 1.5h-2.086z" />
                    </svg>
                    <span style="font-size: 18px;">Prescription</span>
                    <?php if ($pendingCount > 0): ?>
                        <span class="notif-badge"><?php echo $pendingCount; ?></span>
                    <?php endif; ?>
                </a>
            </li>

            <li class="sidebar-item">
                <a href="pharmacy_otc.php" class="sidebar-link position-relative">
                    <i class="fa-solid fa-briefcase-medical"></i>
                    <span style="font-size: 18px;">Over The Counter</span>
                </a>
            </li>


            <li class="sidebar-item">
                <a href="#" class="sidebar-link collapsed has-dropdown" data-bs-toggle="collapse"
                    data-bs-target="#gerald" aria-expanded="true" aria-controls="auth">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="fa-solid fa-chart-line" viewBox="0 0 16 16">
                        <path d="m7.646 9.354-3.792 3.792a.5.5 0 0 0 .353.854h7.586a.5.5 0 0 0 .354-.854L8.354 9.354a.5.5 0 0 0-.708 0" />
                        <path d="M11.414 11H14.5a.5.5 0 0 0 .5-.5v-7a.5.5 0 0 0-.5-.5h-13a.5.5 0 0 0-.5.5v7a.5.5 0 0 0 .5.5h3.086l-1 1H1.5A1.5 1.5 0 0 1 0 10.5v-7A1.5 1.5 0 0 1 1.5 2h13A1.5 1.5 0 0 1 16 3.5v7a1.5 1.5 0 0 1-1.5 1.5h-2.086z" />
                    </svg>
                    <span style="font-size: 18px;">Reports</span>
                </a>

                <ul id="gerald" class="sidebar-dropdown list-unstyled collapse" data-bs-parent="#sidebar">
                    <li class="sidebar-item">
                        <a href="pharmacy_inventory_report.php" class="sidebar-link">Inventory Report</a>
                    </li>
                    <li class="sidebar-item">
                        <a href="pharmacy_sales.php" class="sidebar-link">Financial Report</a>
                    </li>
                    <li class="sidebar-item">
                        <a href="" class="sidebar-link">Dispensing Report</a>
                    </li>
                </ul>
            </li>
            <li class="sidebar-item position-relative">
                <a href="pharmacy_expiry_tracking.php" class="sidebar-link">
                    <i class="fa-solid fa-calendar-check"></i>
                    <span style="font-size: 18px;">Drug Expiry Tracking</span>
                    <?php if ($expiryCount > 0): ?>
                        <span class="expiry-dot"></span>
                    <?php endif; ?>
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
                    <div class="d-flex align-items-center">
                        <!-- Notification Icon -->
                        <div class="notification me-3 dropdown position-relative">
                            <a href="#" id="notifBell" data-bs-toggle="dropdown" aria-expanded="false" class="text-dark" style="text-decoration: none;">
                                <i class="fa-solid fa-bell fs-4"></i>
                                <?php if ($notifCount > 0): ?>
                                    <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size:11px;">
                                        <?php echo $notifCount; ?>
                                    </span>
                                <?php endif; ?>
                            </a>

                            <ul class="dropdown-menu dropdown-menu-end shadow" aria-labelledby="notifBell"
                                style="min-width:400px; max-height:400px; overflow-y:auto; border-radius:8px;">
                                <li class="dropdown-header fw-bold">Notifications</li>

                                <?php if (!empty($latestNotifications)): ?>
                                    <?php foreach ($latestNotifications as $index => $n): ?>
                                        <li class="notif-item <?php echo $index >= 10 ? 'd-none extra-notif' : ''; ?>">
                                            <a class="dropdown-item d-flex align-items-start" href="<?php echo $n['link']; ?>">
                                                <div class="me-2">
                                                    <?php if ($n['type'] == 'prescription'): ?>
                                                        <i class="bi bi-file-earmark-medical-fill text-primary fs-5"></i>
                                                    <?php elseif ($n['type'] == 'expiry'): ?>
                                                        <i class="bi bi-exclamation-triangle-fill text-warning fs-5"></i>
                                                    <?php else: ?>
                                                        <i class="bi bi-info-circle-fill text-secondary fs-5"></i>
                                                    <?php endif; ?>
                                                </div>
                                                <div>
                                                    <div class="small fw-semibold text-wrap" style="white-space: normal; word-break: break-word;">
                                                        <?php echo $n['message']; ?>
                                                    </div>
                                                    <div class="small text-muted text-wrap" style="white-space: normal; word-break: break-word;">
                                                        <?php
                                                        if ($n['type'] == 'expiry' && isset($n['days_left'])) {
                                                            $daysLeft = (int)$n['days_left'];
                                                            if ($daysLeft > 0) {
                                                                echo "in {$daysLeft} day(s)";
                                                            } elseif ($daysLeft == 0) {
                                                                echo "today";
                                                            } else {
                                                                echo abs($daysLeft) . " day(s) ago";
                                                            }
                                                        } else {
                                                            echo $n['time'];
                                                        }
                                                        ?>
                                                    </div>
                                                </div>
                                            </a>
                                        </li>
                                        <?php if ($index < count($latestNotifications) - 1): ?>
                                            <li class="<?php echo $index >= 10 ? 'd-none extra-notif' : ''; ?>">
                                                <hr class="dropdown-divider">
                                            </li>
                                        <?php endif; ?>
                                    <?php endforeach; ?>

                                    <!-- Load More Button -->
                                    <?php if (count($latestNotifications) > 10): ?>
                                        <li class="text-center">
                                            <a href="#" id="loadMoreNotif" class="dropdown-item fw-semibold">View Previous Notifications</a>
                                        </li>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <li><span class="dropdown-item text-muted">No new notifications</span></li>
                                <?php endif; ?>
                            </ul>
                        </div>

                        <!-- Username + Profile Dropdown -->
                        <div class="dropdown d-flex align-items-center">
                            <span class="username ml-1 me-2"><?php echo $user['fname']; ?> <?php echo $user['lname']; ?></span>
                            <button class="btn dropdown-toggle" type="button" id="dropdownMenuButton" data-bs-toggle="dropdown">
                                <i class="bi bi-person-circle"></i>
                            </button>
                            <ul class="dropdown-menu" aria-labelledby="dropdownMenuButton">
                                <li><span>Welcome <strong><?php echo $user['lname']; ?></strong>!</span></li>
                                <li><a class="dropdown-item" href="../logout.php">Logout</a></li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
            <!-- START CODING HERE -->
            <div class="container-fluid py-4">

                <!-- PAGE TITLE -->
                <div class="d-flex align-items-center mb-4">
                    <i class="fa-solid fa-chart-simple fs-4 me-2"></i>
                    <h3 class="mb-0 fw-bold">Dashboard</h3>
                </div>

                <!-- KPI CARDS -->
                <div class="row g-3 mb-4">
                    <div class="col-md-3">
                        <div class="card card-gradient-sales shadow-sm rounded-4 p-3">
                            <div class="d-flex justify-content-center align-items-center gap-3">
                                <!-- ICON -->
                                <div class="bg-success bg-opacity-10 
                rounded-3 d-flex align-items-center justify-content-center"
                                    style="width:55px; height:55px;">
                                    <i class="fa-solid fa-money-bill-1-wave fs-3"></i>
                                </div>

                                <!-- TEXT -->
                                <div class="text-center">
                                    <small class="text-muted d-block">Total Sales</small>
                                    <h3 class="fw-bold">
                                        <?= number_format($totalSales, 2) ?>
                                    </h3>
                                </div>
                            </div>
                        </div>

                    </div>
                    <div class="col-md-3">
                        <div class="card card-gradient-orders shadow-sm rounded-4 p-3">
                            <div class="d-flex justify-content-center align-items-center gap-3">

                                <!-- ICON -->
                                <div class="bg-success bg-opacity-10 
                        rounded-3 d-flex align-items-center justify-content-center"
                                    style="width:55px; height:55px;">
                                    <i class="fa-solid fa-clipboard-list fs-3"></i>
                                </div>

                                <!-- TEXT -->
                                <div>
                                    <small class="text-muted">Total Orders</small>
                                    <h3 class="fw-bold"><?= $totalOrders ?></h3>
                                </div>

                            </div>

                        </div>
                    </div>


                    <div class="col-md-3">
                        <div class="card card-gradient-dispensed shadow-sm rounded-4 p-3">

                            <div class="d-flex justify-content-center align-items-center gap-3">

                                <!-- ICON -->
                                <div class="bg-success bg-opacity-10 
                        rounded-3 d-flex align-items-center justify-content-center"
                                    style="width:55px; height:55px;">
                                    <i class="fa-solid fa-file-prescription fs-3"></i>
                                </div>

                                <!-- TEXT -->
                                <div>
                                    <small class="text-muted">Dispensed Today</small>
                                    <h3 class="fw-bold"><?= $dispensedToday ?></h3>
                                </div>

                            </div>

                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card card-gradient-stocks shadow-sm rounded-4 p-3">

                            <div class="d-flex justify-content-center align-items-center gap-3">

                                <!-- ICON -->
                                <div class="bg-success bg-opacity-10 
                        rounded-3 d-flex align-items-center justify-content-center"
                                    style="width:55px; height:55px;">
                                    <i class="fa-solid fa-boxes-stacked fs-3"></i>
                                </div>

                                <!-- TEXT -->
                                <div>
                                    <small class="text-muted">Total Stocks</small>
                                    <h3 class="fw-bold"><?= $totalStocks ?></h3>
                                </div>

                            </div>

                        </div>
                    </div>

                </div>

                <!-- MAIN CHART ROW -->
                <div class="row mb-4">
                    <!-- SALES PERFORMANCE -->
                    <div class="col-lg-8">
                        <div class="card shadow-sm rounded-4 p-4">
                            <div class="d-flex justify-content-between mb-3 align-items-center">
                                <h6 class="fw-bold mb-0">Sales Performance</h6>
                                <div class="d-flex gap-2">
                                    <select id="yearFilter1" class="form-select form-select-sm w-auto"></select>
                                    <select id="yearFilter2" class="form-select form-select-sm w-auto"></select>
                                </div>
                            </div>
                            <div id="salesChart"></div>
                        </div>
                    </div>

                    <!-- STOCK STATUS -->
                    <div class="col-lg-4">
                        <div class="card shadow-sm rounded-4 p-4">
                            <h6 class="fw-bold mb-3">Payment Methods</h6>
                            <div id="stockChart"></div>
                        </div>
                    </div>
                </div>

                <!-- BOTTOM CHART -->
                <div class="row mb-4">
                    <div class="col-lg-6">
                        <div class="card shadow-sm rounded-4 p-4">
                            <div class="d-flex justify-content-between mb-3 align-items-center">
                                <h6 class="fw-bold mb-0">Revenue Performance</h6>
                                <div class="d-flex gap-2">
                                    <select id="revenueFilter" class="form-select form-select-sm w-auto">
                                        <option value="weekly" selected>Weekly</option>
                                        <option value="monthly">Monthly</option>
                                    </select>
                                </div>
                            </div>
                            <div id="revenueChart"></div>
                        </div>
                    </div>

                </div>



            </div>

            <!-- END CODING HERE -->
        </div>
        <!----- End of Main Content ----->
    </div>
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            const loadMoreBtn = document.getElementById("loadMoreNotif");
            if (loadMoreBtn) {
                loadMoreBtn.addEventListener("click", function(e) {
                    e.preventDefault();
                    document.querySelectorAll(".extra-notif").forEach(el => el.classList.remove("d-none"));
                    this.style.display = "none"; // hide the button once expanded
                });
            }
        });
    </script>
    <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
    <script>
        document.addEventListener("DOMContentLoaded", function() {

            // ---------- SALES PERFORMANCE (2 YEAR FILTER) ----------
            var salesData = <?= json_encode($salesData) ?>;
            var currentYear = new Date().getFullYear();
            var startYear = 2025;
            var years = [];
            for (var y = startYear; y <= currentYear; y++) {
                years.push(y.toString());
                if (!salesData[y]) salesData[y] = Array(12).fill(0);
            }

            var yearFilter1 = document.getElementById('yearFilter1');
            var yearFilter2 = document.getElementById('yearFilter2');

            years.forEach(year => {
                yearFilter1.add(new Option(year, year));
                yearFilter2.add(new Option(year, year));
            });

            // Default to latest two years
            yearFilter1.value = currentYear.toString();
            yearFilter2.value = (currentYear - 1).toString();

            function getSalesSeries(y1, y2) {
                var series = [];
                if (y1) series.push({
                    name: y1,
                    data: salesData[y1]
                });
                if (y2) series.push({
                    name: y2,
                    data: salesData[y2]
                });
                return series;
            }

            var salesChart = new ApexCharts(document.querySelector("#salesChart"), {
                chart: {
                    type: 'area',
                    height: 300,
                    toolbar: {
                        show: false
                    }
                },
                series: getSalesSeries(yearFilter1.value, yearFilter2.value),
                stroke: {
                    curve: 'smooth',
                    width: 3
                },
                dataLabels: {
                    enabled: false
                },
                fill: {
                    type: 'gradient',
                    gradient: {
                        opacityFrom: 0.4,
                        opacityTo: 0.1
                    }
                },
                colors: ['#22c55e', '#6366f1'],
                xaxis: {
                    categories: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec']
                },
                legend: {
                    position: 'top'
                }
            });
            salesChart.render();

            yearFilter1.addEventListener('change', () => salesChart.updateSeries(getSalesSeries(yearFilter1.value, yearFilter2.value)));
            yearFilter2.addEventListener('change', () => salesChart.updateSeries(getSalesSeries(yearFilter1.value, yearFilter2.value)));


            // ---------- PAYMENT METHODS (DONUT) ----------
            new ApexCharts(document.querySelector("#stockChart"), {
                chart: {
                    type: 'donut',
                    height: 250
                },
                series: [<?= $cashPayments ?>, <?= $billingPayments ?>],
                labels: ['Cash', 'Billing'],
                colors: ['#22c55e', '#6366f1'],
                legend: {
                    position: 'bottom'
                },
                dataLabels: {
                    enabled: false
                },
                plotOptions: {
                    pie: {
                        donut: {
                            labels: {
                                show: true,
                                total: {
                                    show: true,
                                    label: 'Total',
                                    formatter: function(w) {
                                        return w.globals.seriesTotals.reduce((a, b) => a + b, 0);
                                    }
                                }
                            }
                        }
                    }
                }
            }).render();


            // ---------- REVENUE PERFORMANCE (Weekly / Monthly) ----------
            var revenueData = {
                weekly: <?= json_encode($weeklyRevenue) ?>,
                monthly: <?= json_encode($monthlyRevenue) ?>
            };

            var xAxisCategories = {
                weekly: ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'],
                monthly: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec']
            };

            var revenueChart = new ApexCharts(document.querySelector("#revenueChart"), {
                chart: {
                    type: 'bar',
                    height: 300,
                    toolbar: {
                        show: false
                    },
                    animations: {
                        enabled: true,
                        easing: 'easeinout',
                        speed: 800
                    }
                },
                series: [{
                    name: 'Revenue',
                    data: revenueData['weekly']
                }],
                plotOptions: {
                    bar: {
                        borderRadius: 6,
                        columnWidth: '50%'
                    }
                },
                colors: ['#6366f1'],
                xaxis: {
                    categories: xAxisCategories['weekly'],
                    labels: {
                        rotate: -45,
                        style: {
                            fontSize: '12px',
                            fontWeight: 'bold'
                        }
                    }
                },
                yaxis: {
                    labels: {
                        formatter: val => '₱' + val.toLocaleString()
                    }
                },
                tooltip: {
                    y: {
                        formatter: val => '₱' + val.toLocaleString()
                    }
                },
                dataLabels: {
                    enabled: true,
                    formatter: val => '₱' + val.toLocaleString(),
                    style: {
                        colors: ['#000'],
                        fontSize: '12px'
                    }
                }
            });
            revenueChart.render();

            // Revenue filter
            var revenueFilter = document.getElementById('revenueFilter');
            revenueFilter.addEventListener('change', function() {
                var selected = this.value;
                revenueChart.updateOptions({
                    xaxis: {
                        categories: xAxisCategories[selected]
                    }
                });
                revenueChart.updateSeries([{
                    name: 'Revenue',
                    data: revenueData[selected]
                }]);
            });

        });
    </script>


    <script>
        const toggler = document.querySelector(".toggler-btn");
        toggler.addEventListener("click", function() {
            document.querySelector("#sidebar").classList.toggle("collapsed");
        });
    </script>
    <script src="assets/Bootstrap/all.min.js"></script>
    <script src="assets/Bootstrap/bootstrap.bundle.min.js"></script>
    <script src="assets/Bootstrap/fontawesome.min.js"></script>
    <script src="assets/Bootstrap/jq.js"></script>
</body>

</html>