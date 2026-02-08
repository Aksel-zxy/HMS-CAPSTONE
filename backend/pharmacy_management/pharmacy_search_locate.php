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
// ---------------- Recent Searches ----------------
$_SESSION['recent_searches'] ??= [];
$searchResultHTML = "";
$openResultModal = false;

// ---------------- Recent Searches ----------------
$_SESSION['recent_searches'] ??= [];
$searchResultHTML = "";
$openResultModal = false;

// ---------------- Search & Locate ----------------
if (isset($_POST['search_medicine'])) {
    $generic = $_POST['generic_name'] ?? '';
    $brand   = $_POST['brand_name'] ?? '';
    $dosage  = $_POST['dosage'] ?? '';

    $stmt = $conn->prepare("
        SELECT med_id, med_name, generic_name, brand_name, dosage, shelf_no, rack_no, bin_no, stock_quantity
        FROM pharmacy_inventory
        WHERE generic_name=? AND brand_name=? AND dosage=?
        LIMIT 1
    ");
    $stmt->bind_param("sss", $generic, $brand, $dosage);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {

        $medId = (int)$row['med_id'];
        $location = "Shelf {$row['shelf_no']} → Rack {$row['rack_no']} → Bin {$row['bin_no']}";
        $stock = (int)$row['stock_quantity'];

        // ---------------- Step 2: Stock Status ----------------
        $reorder = 100; // reorder threshold
        $stockStatus = ($stock <= $reorder * 0.5) ? "Critical" : (($stock <= $reorder) ? "Low" : "Normal");

        // ---------------- Step 4: Real Movement Analysis ----------------
        // Count how many times this medicine was dispensed in last 30 days
        $dispenseStmt = $conn->prepare("
            SELECT SUM(quantity_dispensed) AS total_dispensed
            FROM pharmacy_prescription_items AS ppi
            JOIN pharmacy_prescription AS pp 
              ON pp.prescription_id = ppi.prescription_id
            WHERE ppi.med_id = ? 
              AND pp.status = 'Dispensed' 
              AND pp.prescription_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        ");
        $dispenseStmt->bind_param("i", $medId);
        $dispenseStmt->execute();
        $dispenseResult = $dispenseStmt->get_result();
        $dispensed = 0;
        if ($dispenseRow = $dispenseResult->fetch_assoc()) {
            $dispensed = (int)$dispenseRow['total_dispensed'];
        }

        // Determine movement based on actual dispense rate
        if ($dispensed >= 20) {
            $movement = "Fast-moving";
        } elseif ($dispensed >= 5) {
            $movement = "Moderate-moving";
        } else {
            $movement = "Slow-moving";
        }

        // ---------------- Step 4b: Average Daily Usage ----------------
        $avgUseStmt = $conn->prepare("
            SELECT IFNULL(SUM(quantity_dispensed)/30, 0) AS avg_daily
            FROM pharmacy_prescription_items AS ppi
            JOIN pharmacy_prescription AS pp
              ON pp.prescription_id = ppi.prescription_id
            WHERE ppi.med_id = ?
              AND pp.status = 'Dispensed'
              AND pp.prescription_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        ");
        $avgUseStmt->bind_param("i", $medId);
        $avgUseStmt->execute();
        $avgUseResult = $avgUseStmt->get_result();
        $avgUse = 0;
        if ($avgRow = $avgUseResult->fetch_assoc()) {
            $avgUse = (float)$avgRow['avg_daily'];
        }

        // ---------------- Step 5: AI Analysis ----------------
        require_once 'pharmacy_ai.php'; // <-- your AI function file
        $aiAdvice = analyzeStockAI([
            'med_name' => $row['med_name'],
            'dosage'   => $row['dosage'],
            'stock'    => $stock,
            'reorder'  => $reorder,
            'avg_use'  => $avgUse,
            'status'   => $stockStatus,
            'movement' => $movement
        ]);

        // ---------------- Save to recent searches ----------------
        array_unshift($_SESSION['recent_searches'], [
            'generic_name' => $row['generic_name'],
            'brand_name'   => $row['brand_name'],
            'med_name'     => $row['med_name'],
            'dosage'       => $row['dosage'],
            'location'     => $location,
            'stock'        => $stock,
            'stock_status' => $stockStatus,
            'movement'     => $movement,
            'ai_advice'    => $aiAdvice
        ]);
        $_SESSION['recent_searches'] = array_slice($_SESSION['recent_searches'], 0, 10);

        // ---------------- Display Result ----------------
        // Format AI advice as bullet list
        $aiLines = preg_split('/\d\.\s*/', $aiAdvice, -1, PREG_SPLIT_NO_EMPTY);
        $aiList = "<ul>";
        foreach ($aiLines as $line) {
            $aiList .= "<li>" . trim($line) . "</li>";
        }
        $aiList .= "</ul>";

        $searchResultHTML = "
<div class='alert alert-success'>
    <b>Generic:</b> {$row['generic_name']}<br>
    <b>Medicine:</b> {$row['med_name']}<br>
    <b>Brand:</b> {$row['brand_name']}<br>
    <b>Dosage:</b> {$row['dosage']}<br><br>
    <b>Location:</b> {$location}<br>
    <b>Stock:</b>
    <span class='badge " . ($stockStatus !== 'Normal' ? 'bg-danger' : 'bg-success') . "'>
        {$stock} ({$stockStatus})
    </span><br>
    <b>Movement (last 30 days):</b>
    <span class='badge bg-warning text-dark'>{$movement}</span><br><br>
    <b>AI Recommendation:</b>
    <div class='p-2 bg-light border rounded'>{$aiList}</div>
</div>
";
    } else {
        $searchResultHTML = "<div class='alert alert-danger text-center'>Medicine not found.</div>";
    }

    $_SESSION['search_result_html'] = $searchResultHTML;
    $_SESSION['show_search_modal'] = true;
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}


// ---------------- Modal Display ----------------
if (!empty($_SESSION['show_search_modal'])) {
    $openResultModal = true;
    $searchResultHTML = $_SESSION['search_result_html'];
    unset($_SESSION['show_search_modal']);
    unset($_SESSION['search_result_html']);
}

// ---------------- Clear Recent ----------------
if (isset($_POST['clear_recent'])) {
    unset($_SESSION['recent_searches']);
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
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
                <a href="pharmacy_sales.php" class="sidebar-link" data-bs-toggle="#" data-bs-target="#"
                    aria-expanded="false" aria-controls="auth">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="fa-solid fa-chart-line" viewBox="0 0 16 16">
                        <path d="m7.646 9.354-3.792 3.792a.5.5 0 0 0 .353.854h7.586a.5.5 0 0 0 .354-.854L8.354 9.354a.5.5 0 0 0-.708 0" />
                        <path d="M11.414 11H14.5a.5.5 0 0 0 .5-.5v-7a.5.5 0 0 0-.5-.5h-13a.5.5 0 0 0-.5.5v7a.5.5 0 0 0 .5.5h3.086l-1 1H1.5A1.5 1.5 0 0 1 0 10.5v-7A1.5 1.5 0 0 1 1.5 2h13A1.5 1.5 0 0 1 16 3.5v7a1.5 1.5 0 0 1-1.5 1.5h-2.086z" />
                    </svg>
                    <span style="font-size: 18px;">Sales</span>
                </a>
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
            <div class="content">
                <div class="title-container">
                    <i class="fa-brands fa-searchengin"></i>
                    <h1 class="page-title">Search & Locate</h1>
                </div>

                <div class="content mt-4">
                    <!-- Header Section -->
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h2></h2>

                        <div class="d-flex justify-content-end align-items-center gap-2 mb-3">
                            <!-- Search Medicine Button -->
                            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#medicineModal">
                                Search Medicine
                            </button>
                        </div>


                        <div class="modal fade" id="medicineModal" tabindex="-1">
                            <div class="modal-dialog">
                                <div class="modal-content">
                                    <form method="POST" id="medicineSearchForm">
                                        <div class="modal-header">
                                            <h5 class="modal-title">Search & Locate Medicine</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <div class="modal-body">
                                            <div class="mb-3">
                                                <label class="form-label">Generic Name</label>
                                                <select class="form-select" id="generic_name" name="generic_name" required>
                                                    <option value="">-- Select Generic --</option>
                                                    <?php
                                                    $q = $conn->query("SELECT DISTINCT generic_name FROM pharmacy_inventory ORDER BY generic_name");
                                                    while ($row = $q->fetch_assoc()) {
                                                        echo "<option value='{$row['generic_name']}'>{$row['generic_name']}</option>";
                                                    }
                                                    ?>
                                                </select>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Brand Name</label>
                                                <select class="form-select" id="brand_name" name="brand_name" disabled required>
                                                    <option value="">-- Select Brand --</option>
                                                </select>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Dosage</label>
                                                <select class="form-select" id="dosage" name="dosage" disabled>
                                                    <option value="">-- Select Dosage --</option>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="submit" name="search_medicine" class="btn btn-primary">Search</button>
                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                        <!-- SEARCH RESULT MODAL -->
                        <div class="modal fade" id="resultModal" tabindex="-1">
                            <div class="modal-dialog">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title">Search Result</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                    </div>
                                    <div class="modal-body">
                                        <div id="locationResult" class="p-3"><?= $searchResultHTML ?></div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                    </div>
                                </div>
                            </div>
                        </div>


                    </div>
                    <div class="mt-4">
                        <h4>Recently Searched Medicines</h4>
                        <?php if (!empty($_SESSION['recent_searches'])): ?>
                            <div class="d-flex justify-content-end mb-2">
                                <form method="POST">
                                    <button type="submit" name="clear_recent" class="btn btn-danger btn-sm">Clear Recent Searches</button>
                                </form>
                            </div>
                            <table class="table table-bordered table-sm align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th>Medicine</th>
                                        <th>Generic Name</th>
                                        <th>Brand</th>
                                        <th>Dosage</th>
                                        <th>Location</th>
                                        <th>Stock</th>
                                        <th>Status</th>
                                        <th>Movement</th>
                                        <th>AI Advice</th> <!-- NEW -->
                                    </tr>
                                </thead>

                                <tbody>
                                    <?php foreach ($_SESSION['recent_searches'] as $search): ?>
                                        <tr>
                                            <td style="vertical-align: top;"><?= htmlspecialchars($search['med_name'] ?? '-') ?></td>
                                            <td style="vertical-align: top;"><?= htmlspecialchars($search['generic_name'] ?? '-') ?></td>
                                            <td style="vertical-align: top;"><?= htmlspecialchars($search['brand_name'] ?? '-') ?></td>
                                            <td style="vertical-align: top;"><?= htmlspecialchars($search['dosage'] ?? '-') ?></td>
                                            <td style="white-space: nowrap; vertical-align: top;"><?= htmlspecialchars($search['location'] ?? '-') ?></td>
                                            <td style="vertical-align: top;">
                                                <span class="badge <?= ($search['stock_status'] ?? 'Normal') !== 'Normal' ? 'bg-danger' : 'bg-success' ?>">
                                                    <?= htmlspecialchars($search['stock'] ?? 0) ?>
                                                </span>
                                            </td>
                                            <td style="vertical-align: top;">
                                                <span class="badge <?= ($search['stock_status'] ?? 'Normal') === 'Critical' ? 'bg-danger' : ($search['stock_status'] === 'Low' ? 'bg-warning text-dark' : 'bg-success') ?>">
                                                    <?= htmlspecialchars($search['stock_status'] ?? '-') ?>
                                                </span>
                                            </td>
                                            <td style="vertical-align: top;">
                                                <span class="badge bg-info text-dark"><?= htmlspecialchars($search['movement'] ?? '-') ?></span>
                                            </td>
                                            <td style="vertical-align: top;">
                                                <div class="small p-1 bg-light border rounded">
                                                    <?php
                                                    if (isset($search['ai_advice'])) {
                                                        $plainAI = str_replace('**', '', $search['ai_advice']);
                                                        echo nl2br(htmlspecialchars($plainAI));
                                                    } else {
                                                        echo '-';
                                                    }
                                                    ?>
                                                </div>
                                            </td>
                                        </tr>

                                    <?php endforeach; ?>

                                </tbody>
                            </table>
                        <?php else: ?>
                            <div class="alert alert-secondary text-center">No recent searches yet.</div>
                        <?php endif; ?>
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
            <script>
                // Dropdown fetching
                document.getElementById('generic_name').addEventListener('change', function() {
                    const generic = this.value;
                    const brandSelect = document.getElementById('brand_name');
                    const dosageSelect = document.getElementById('dosage');
                    brandSelect.innerHTML = '<option value="">-- Select Brand --</option>';
                    dosageSelect.innerHTML = '<option value="">-- Select Dosage --</option>';
                    brandSelect.disabled = true;
                    dosageSelect.disabled = true;
                    if (!generic) return;
                    fetch(`fetch_medicine_options.php?type=brands&generic=${encodeURIComponent(generic)}`)
                        .then(res => res.json()).then(data => {
                            if (data.length === 0) return;
                            brandSelect.disabled = false;
                            data.forEach(b => brandSelect.innerHTML += `<option value="${b}">${b}</option>`);
                        });
                });

                document.getElementById('brand_name').addEventListener('change', function() {
                    const brand = this.value;
                    const generic = document.getElementById('generic_name').value;
                    const dosageSelect = document.getElementById('dosage');
                    dosageSelect.innerHTML = '<option value="">-- Select Dosage --</option>';
                    dosageSelect.disabled = true;
                    if (!brand || !generic) return;
                    fetch(`fetch_medicine_options.php?type=dosage&generic=${encodeURIComponent(generic)}&brand=${encodeURIComponent(brand)}`)
                        .then(res => res.json()).then(data => {
                            if (data.length === 0) return;
                            dosageSelect.disabled = false;
                            data.forEach(d => dosageSelect.innerHTML += `<option value="${d}">${d}</option>`);
                        });
                });

                // Clear result modal on close
                const resultModalEl = document.getElementById('resultModal');
                resultModalEl.addEventListener('hidden.bs.modal', function() {
                    document.getElementById('locationResult').innerHTML = '';
                });

                // Auto-open result modal after search
                <?php if ($openResultModal && !empty($searchResultHTML)): ?>
                    document.addEventListener("DOMContentLoaded", function() {
                        const resultModal = new bootstrap.Modal(document.getElementById('resultModal'));
                        document.getElementById('locationResult').innerHTML = <?= json_encode($searchResultHTML) ?>;
                        resultModal.show();
                    });
                <?php endif; ?>
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