<?php
include '../../SQL/config.php';
require_once 'classes/medicine.php';
require_once "classes/notification.php";

if (!isset($_SESSION['pharmacy']) || $_SESSION['pharmacy'] !== true) {
    header('Location: login.php');
    exit();
}

if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    echo "User ID is not set in session.";
    exit();
}

// Get logged-in user info
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

// Initialize medicine object
$medicineObj = new Medicine($conn);

// Fetch inventory summary statistics
$summary_query = "
    SELECT
        COUNT(DISTINCT med_id) as total_medicines,
        SUM(stock_quantity) as total_stock_pieces,
        SUM(stock_quantity * unit_price) as total_inventory_value,
        COUNT(CASE WHEN stock_quantity = 0 THEN 1 END) as out_of_stock_count,
        COUNT(CASE WHEN stock_quantity <= 10 THEN 1 END) as low_stock_count
    FROM pharmacy_inventory
";
$summary_result = $conn->query($summary_query);
$summary = $summary_result->fetch_assoc();

// Fetch inventory by category
$category_query = "
    SELECT
        category,
        COUNT(*) as medicine_count,
        SUM(stock_quantity) as total_stock,
        SUM(stock_quantity * unit_price) as category_value
    FROM pharmacy_inventory
    GROUP BY category
    ORDER BY category_value DESC
";
$category_result = $conn->query($category_query);
$categories = $category_result->fetch_all(MYSQLI_ASSOC);

// Fetch expiry tracking data
$expiry_query = "
    SELECT
        pi.med_id,
        pi.med_name,
        pi.generic_name,
        pi.brand_name,
        pi.category,
        psb.batch_no,
        psb.stock_quantity,
        psb.expiry_date,
        DATEDIFF(psb.expiry_date, CURDATE()) as days_to_expiry,
        CASE
            WHEN psb.expiry_date < CURDATE() THEN 'Expired'
            WHEN psb.expiry_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY) THEN 'Near Expiry (30 days)'
            ELSE 'Good'
        END as expiry_status
    FROM pharmacy_stock_batches psb
    JOIN pharmacy_inventory pi ON psb.med_id = pi.med_id
    WHERE psb.expiry_date <= DATE_ADD(CURDATE(), INTERVAL 90 DAY)
    ORDER BY psb.expiry_date ASC
";
$expiry_result = $conn->query($expiry_query);
$expiry_items = $expiry_result->fetch_all(MYSQLI_ASSOC);

// Pagination Setup
$items_per_page = 15;
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($current_page < 1) $current_page = 1;
$offset = ($current_page - 1) * $items_per_page;

// Get total count of medicines
$count_query = "SELECT COUNT(*) as total FROM pharmacy_inventory";
$count_result = $conn->query($count_query);
$count_row = $count_result->fetch_assoc();
$total_medicines = $count_row['total'];
$total_pages = ceil($total_medicines / $items_per_page);

// Get all medicines for detailed report (with pagination)
$all_medicines_query = "
    SELECT
        med_id,
        med_name,
        generic_name,
        brand_name,
        category,
        dosage,
        unit,
        stock_quantity,
        unit_price,
        stock_quantity * unit_price as total_value,
        prescription_required,
        CASE
            WHEN stock_quantity = 0 THEN 'Out of Stock'
            WHEN stock_quantity > 0 AND stock_quantity <= 10 THEN 'Low Stock'
            ELSE 'Available'
        END as status,
        storage_room,
        shelf_no,
        rack_no,
        bin_no
    FROM pharmacy_inventory
    ORDER BY category, med_name
    LIMIT ? OFFSET ?
";
$stmt_meds = $conn->prepare($all_medicines_query);
$stmt_meds->bind_param("ii", $items_per_page, $offset);
$stmt_meds->execute();
$all_medicines_result = $stmt_meds->get_result();
$all_medicines = $all_medicines_result->fetch_all(MYSQLI_ASSOC);

// 🔔 Pending prescriptions count
$notif_sql = "SELECT COUNT(*) AS pending FROM pharmacy_prescription WHERE status = 'Pending'";
$notif_res = $conn->query($notif_sql);
$pendingCount = 0;
if ($notif_res && $notif_res->num_rows > 0) {
    $notif_row = $notif_res->fetch_assoc();
    $pendingCount = $notif_row['pending'];
}

// 🔴 Expiry count
$expiry_count_sql = "SELECT COUNT(*) AS expiry FROM pharmacy_stock_batches WHERE expiry_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)";
$expiry_count_res = $conn->query($expiry_count_sql);
$expiryCount = 0;
if ($expiry_count_res && $expiry_count_res->num_rows > 0) {
    $expiry_count_row = $expiry_count_res->fetch_assoc();
    $expiryCount = $expiry_count_row['expiry'];
}

$notif = new Notification($conn);
$latestNotifications = $notif->load();
$notifCount = $notif->notifCount;
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HMS | Inventory Report</title>
    <link rel="shortcut icon" href="assets/image/favicon.ico" type="image/x-icon">
    <link rel="stylesheet" href="assets/CSS/bootstrap.min.css">
    <link rel="stylesheet" href="assets/CSS/super.css">
    <link rel="stylesheet" href="assets/CSS/med_inventory.css">
    <link rel="stylesheet" href="assets/CSS/prescription.css">
    <style>
        .stat-box {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            text-align: center;
            border-left: 4px solid #667eea;
            margin-bottom: 15px;
        }

        .stat-box h6 {
            color: #666;
            font-size: 13px;
            text-transform: uppercase;
            margin-bottom: 10px;
            font-weight: 600;
        }

        .stat-box .value {
            font-size: 28px;
            font-weight: bold;
            color: #333;
        }

        .stat-box.profit {
            border-left-color: #66bb6a;
        }

        .stat-box.cost {
            border-left-color: #fb8c00;
        }

        .stat-box.revenue {
            border-left-color: #42a5f5;
        }

        .stat-box.warning {
            border-left-color: #ffa726;
        }

        .stat-box.danger {
            border-left-color: #ef5350;
        }

        .filter-section {
            background: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            display: flex;
            justify-content: flex-end;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }

        .export-button-group {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            justify-content: flex-end;
        }

        .export-button-group .btn {
            padding: 10px 18px;
            font-weight: 600;
            font-size: 13px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.3s ease;
            white-space: nowrap;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .export-button-group .btn-success {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
        }

        .export-button-group .btn-success:hover {
            background: linear-gradient(135deg, #059669 0%, #047857 100%);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.4);
        }

        .export-button-group .btn-danger {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            color: white;
        }

        .export-button-group .btn-danger:hover {
            background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(239, 68, 68, 0.4);
        }

        .export-button-group .btn-info {
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
            color: white;
        }

        .export-button-group .btn-info:hover {
            background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.4);
        }

        .chart-container {
            background: white;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .table-container {
            background: white;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            overflow-x: auto;
        }

        .expiry-badge-expired {
            background-color: #dc3545;
            color: white;
            padding: 5px 10px;
            border-radius: 4px;
            font-size: 12px;
        }

        .expiry-badge-warning {
            background-color: #ffc107;
            color: black;
            padding: 5px 10px;
            border-radius: 4px;
            font-size: 12px;
        }

        .expiry-badge-good {
            background-color: #28a745;
            color: white;
            padding: 5px 10px;
            border-radius: 4px;
            font-size: 12px;
        }

        .section-header {
            border-bottom: 3px solid #667eea;
            padding-bottom: 10px;
            margin-bottom: 20px;
            font-size: 18px;
            font-weight: bold;
            color: #333;
        }

        .title-container {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 30px;
        }

        .title-container i {
            font-size: 32px;
            color: #667eea;
        }

        .page-title {
            margin: 0;
            font-size: 28px;
            font-weight: bold;
            color: #333;
        }

        @media print {
            body {
                background-color: white;
                margin: 0;
                padding: 0;
            }
            .sidebar-toggle,
            .topbar,
            .filter-section,
            .export-button-group,
            .pagination,
            .pagination-container,
            .page-info,
            .toggler-btn {
                display: none !important;
            }
            .main {
                margin-left: 0 !important;
                padding: 0 !important;
            }
            .content {
                padding: 20px !important;
            }
            .table {
                font-size: 11px;
            }
            .stat-box {
                page-break-inside: avoid;
            }
            .table-container,
            .chart-container {
                page-break-inside: avoid;
                box-shadow: none !important;
                border: 1px solid #ddd;
            }
            .stat-box {
                background: white !important;
                border: 1px solid #ddd !important;
                color: #333 !important;
            }
            h1, h3 {
                color: #000 !important;
            }
            td, th {
                border: 1px solid #999 !important;
                padding: 6px !important;
            }
            .section-header {
                border-bottom: 2px solid #000 !important;
            }
        }

        .pagination-container {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 8px;
            margin-top: 20px;
            flex-wrap: wrap;
            padding: 15px;
        }

        .pagination-container .page-item .page-link {
            min-width: 40px;
            padding: 8px 12px;
            border: 1px solid #ddd;
            background-color: white;
            color: #333;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s ease;
            font-size: 13px;
        }

        .pagination-container .page-item .page-link:hover {
            background-color: #f0f0f0;
            border-color: #667eea;
            color: #667eea;
        }

        .pagination-container .page-item.active .page-link {
            background-color: #667eea;
            border-color: #667eea;
            color: white;
            font-weight: 600;
        }

        .pagination-container .page-item.disabled .page-link {
            background-color: #f5f5f5;
            border-color: #ddd;
            color: #999;
            cursor: not-allowed;
        }

        .page-info {
            text-align: center;
            color: #666;
            font-size: 13px;
            margin-top: 10px;
            font-weight: 500;
        }

        .report-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }

        .report-table thead {
            background-color: #f8f9fa;
            border-bottom: 2px solid #667eea;
        }

        .report-table thead th {
            padding: 12px;
            text-align: left;
            font-weight: 600;
            color: #333;
            font-size: 14px;
        }

        .report-table tbody tr {
            border-bottom: 1px solid #eee;
            transition: background-color 0.2s;
        }

        .report-table tbody tr:hover {
            background-color: #f8f9fa;
        }

        .report-table td {
            padding: 12px;
            color: #666;
            font-size: 14px;
        }

        .report-table .numeric {
            text-align: right;
            font-weight: 500;
        }

        .report-section {
            background: white;
            padding: 25px;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .report-tabs {
            display: flex;
            gap: 0;
            margin-bottom: 0;
            border-bottom: 3px solid #667eea;
            background-color: #f8f9fa;
            border-radius: 8px 8px 0 0;
            overflow: hidden;
        }

        .report-tabs button {
            padding: 14px 24px;
            border: none;
            background-color: #f8f9fa;
            color: #555;
            cursor: pointer;
            font-weight: 600;
            font-size: 14px;
            transition: all 0.3s ease;
            border-bottom: 3px solid transparent;
            margin-bottom: 0;
            flex: 1;
            text-align: center;
        }

        .report-tabs button:hover {
            background-color: #e9ecef;
            color: #333;
        }

        .report-tabs button.active {
            background-color: white;
            color: #667eea;
            border-bottom-color: #667eea;
        }

        .tab-content {
            display: none;
            padding: 25px;
            background-color: white;
            border-radius: 0 8px 8px 8px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .tab-content.active {
            display: block;
            animation: fadeIn 0.3s ease;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(5px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
    </style>
</head>

<body>
    <div class="d-flex">
        <!----- Sidebar ----->
        <aside id="sidebar" class="sidebar-toggle">

            <div class="sidebar-logo mt-3">
                <img src="assets/image/logo-dark.png" width="90px" height="20px">
            </div>

            <div class="menu-title">Pharmacy Management | Inventory Report</div>

            <!----- Sidebar Navigation ----->

            <li class="sidebar-item">
                <a href="pharmacy_dashboard.php" class="sidebar-link">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor"
                        class="bi bi-cast" viewBox="0 0 16 16">
                        <path
                            d="m7.646 9.354-3.792 3.792a.5.5 0 0 0 .353.854h7.586a.5.5 0 0 0 .354-.854L8.354 9.354a.5.5 0 0 0-.708 0" />
                        <path
                            d="M11.414 11H14.5a.5.5 0 0 0 .5-.5v-7a.5.5 0 0 0-.5-.5h-13a.5.5 0 0 0-.5.5v7a.5.5 0 0 0 .5.5h3.086l-1 1H1.5A1.5 1.5 0 0 1 0 10.5v-7A1.5 1.5 0 0 1 1.5 2h13A1.5 1.5 0 0 1 16 3.5v7a1.5 1.5 0 0 1-1.5 1.5h-2.086z" />
                    </svg>
                    <span style="font-size: 18px;">Dashboard</span>
                </a>
            </li>

            <li class="sidebar-item">
                <a href="pharmacy_med_inventory.php" class="sidebar-link">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor"
                        class="fa-solid fa-capsules" viewBox="0 0 16 16">
                        <path
                            d="m7.646 9.354-3.792 3.792a.5.5 0 0 0 .353.854h7.586a.5.5 0 0 0 .354-.854L8.354 9.354a.5.5 0 0 0-.708 0" />
                        <path
                            d="M11.414 11H14.5a.5.5 0 0 0 .5-.5v-7a.5.5 0 0 0-.5-.5h-13a.5.5 0 0 0-.5.5v7a.5.5 0 0 0 .5.5h3.086l-1 1H1.5A1.5 1.5 0 0 1 0 10.5v-7A1.5 1.5 0 0 1 1.5 2h13A1.5 1.5 0 0 1 16 3.5v7a1.5 1.5 0 0 1-1.5 1.5h-2.086z" />
                    </svg>
                    <span style="font-size: 18px;">Medicine Inventory</span>
                </a>
            </li>

            <li class="sidebar-item">
                <a href="pharmacy_search_locate.php" class="sidebar-link">
                    <i class="fa-brands fa-searchengin"></i>
                    <span style="font-size: 18px;">Search & Locate</span>
                </a>
            </li>

            <li class="sidebar-item">
                <a href="pharmacy_prescription.php" class="sidebar-link position-relative">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor"
                        class="fa-solid fa-file-prescription" viewBox="0 0 16 16">
                        <path
                            d="m7.646 9.354-3.792 3.792a.5.5 0 0 0 .353.854h7.586a.5.5 0 0 0 .354-.854L8.354 9.354a.5.5 0 0 0-.708 0" />
                        <path
                            d="M11.414 11H14.5a.5.5 0 0 0 .5-.5v-7a.5.5 0 0 0-.5-.5h-13a.5.5 0 0 0-.5.5v7a.5.5 0 0 0 .5.5h3.086l-1 1H1.5A1.5 1.5 0 0 1 0 10.5v-7A1.5 1.5 0 0 1 1.5 2h13A1.5 1.5 0 0 1 16 3.5v7a1.5 1.5 0 0 1-1.5 1.5h-2.086z" />
                    </svg>
                    <span style="font-size: 18px;">Prescription</span>
                    <?php if ($pendingCount > 0): ?>
                        <span class="notif-badge"><?php echo $pendingCount; ?></span>
                    <?php endif; ?>
                </a>
            </li>

            <li class="sidebar-item">
                <a href="pharmacy_otc.php" class="sidebar-link">
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

                <ul id="gerald" class="sidebar-dropdown list-unstyled collapse show" data-bs-parent="#sidebar">
                    <li class="sidebar-item">
                        <a href="pharmacy_inventory_report.php" class="sidebar-link active">Inventory Report</a>
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
                <a href="pharmacy_supply_request.php" class="sidebar-link">
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
                        <svg xmlns="http://www.w3.org/2000/svg" width="30px" height="30px" fill="currentColor"
                            class="bi bi-list-ul" viewBox="0 0 16 16">
                            <path fill-rule="evenodd"
                                d="M5 11.5a.5.5 0 0 1 .5-.5h9a.5.5 0 0 1 0 1h-9a.5.5 0 0 1-.5-.5m0-4a.5.5 0 0 1 .5-.5h9a.5.5 0 0 1 0 1h-9a.5.5 0 0 1-.5-.5m0-4a.5.5 0 0 1 .5-.5h9a.5.5 0 0 1 0 1h-9a.5.5 0 0 1-.5-.5m-3 1a1 1 0 1 0 0-2 1 1 0 0 0 0 2m0 4a1 1 0 1 0 0-2 1 1 0 0 0 0 2m0 4a1 1 0 1 0 0-2 1 1 0 0 0 0 2" />
                        </svg>
                    </button>
                </div>
                <div class="logo">
                    <div class="d-flex align-items-center">
                        <!-- Notification Icon -->
                        <div class="notification me-3 dropdown position-relative">
                            <a href="#" id="notifBell" data-bs-toggle="dropdown" aria-expanded="false" class="text-dark"
                                style="text-decoration: none;">
                                <i class="fa-solid fa-bell fs-4"></i>
                                <?php if ($notifCount > 0): ?>
                                    <span
                                        class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger"
                                        style="font-size:11px;">
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
                                                    <div class="small fw-semibold text-wrap"
                                                        style="white-space: normal; word-break: break-word;">
                                                        <?php echo $n['message']; ?>
                                                    </div>
                                                    <div class="small text-muted text-wrap"
                                                        style="white-space: normal; word-break: break-word;">
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
                            <span class="username ml-1 me-2"><?php echo $user['fname']; ?>
                                <?php echo $user['lname']; ?></span>
                            <button class="btn dropdown-toggle" type="button" id="dropdownMenuButton"
                                data-bs-toggle="dropdown">
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

            <!-- START MAIN CONTENT -->
            <div class="content" style="padding: 30px;">
                <div class="title-container">
                    <i class="fa-solid fa-chart-bar"></i>
                    <h1 class="page-title">Inventory Report</h1>
                </div>

                <!-- Export Buttons Section -->
                <div class="filter-section">
                    <div class="export-button-group">
                        <button class="btn btn-success" onclick="exportToExcel()" title="Download as Excel">
                            <i class="fa-solid fa-file-excel"></i> Excel
                        </button>
                        <button class="btn btn-danger" onclick="exportToPDF()" title="Download as PDF">
                            <i class="fa-solid fa-file-pdf"></i> PDF
                        </button>
                        <button class="btn btn-info" onclick="printReport()" title="Print Report">
                            <i class="fa-solid fa-print"></i> Print
                        </button>
                    </div>
                </div>

                <!-- Summary Statistics -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="stat-box revenue">
                            <h6>Total Medicines</h6>
                            <div class="value"><?php echo $summary['total_medicines'] ?? 0; ?></div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-box profit">
                            <h6>Total Stock Value</h6>
                            <div class="value">₱<?php echo number_format($summary['total_inventory_value'] ?? 0, 2); ?></div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-box warning">
                            <h6>Low Stock Items</h6>
                            <div class="value"><?php echo $summary['low_stock_count'] ?? 0; ?></div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-box danger">
                            <h6>Out of Stock</h6>
                            <div class="value"><?php echo $summary['out_of_stock_count'] ?? 0; ?></div>
                        </div>
                    </div>
                </div>

                <!-- REPORTS TAB CONTAINER -->
                <div class="report-section">
                    <!-- Tab Navigation -->
                    <div class="report-tabs">
                        <button class="tab-button active" onclick="switchTab('category-tab')">
                            <i class="fa-solid fa-layer-group me-2"></i>Inventory by Category
                        </button>
                        <button class="tab-button" onclick="switchTab('expiry-tab')">
                            <i class="fa-solid fa-calendar-xmark me-2"></i>Expiring Soon
                        </button>
                        <button class="tab-button" onclick="switchTab('listing-tab')">
                            <i class="fa-solid fa-list me-2"></i>Complete Listing
                        </button>
                    </div>

                    <!-- TAB 1: INVENTORY BY CATEGORY -->
                    <div id="category-tab" class="tab-content active">
                        <table class="report-table">
                            <thead>
                                <tr>
                                    <th>Category</th>
                                    <th class="numeric">Medicine Count</th>
                                    <th class="numeric">Total Stock</th>
                                    <th class="numeric">Inventory Value (₱)</th>
                                    <th>% of Total Value</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $total_value = $summary['total_inventory_value'] ?? 0;
                                if (!empty($categories)):
                                    foreach ($categories as $cat):
                                        $percentage = $total_value > 0 ? ($cat['category_value'] / $total_value) * 100 : 0;
                                ?>
                                        <tr>
                                            <td><strong><?php echo htmlspecialchars($cat['category']); ?></strong></td>
                                            <td class="numeric"><?php echo $cat['medicine_count']; ?></td>
                                            <td class="numeric"><?php echo $cat['total_stock']; ?> pieces</td>
                                            <td class="numeric">₱<?php echo number_format($cat['category_value'], 2); ?></td>
                                            <td>
                                                <div class="progress" style="height: 20px;">
                                                    <div class="progress-bar" role="progressbar" style="width: <?php echo $percentage; ?>%;" aria-valuenow="<?php echo $percentage; ?>" aria-valuemin="0" aria-valuemax="100">
                                                        <?php echo number_format($percentage, 1); ?>%
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php
                                    endforeach;
                                else:
                                    ?>
                                    <tr>
                                        <td colspan="5" class="text-center text-muted">No category data available</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- TAB 2: ITEMS EXPIRING SOON -->
                    <div id="expiry-tab" class="tab-content">
                        <?php if (!empty($expiry_items)): ?>
                            <table class="report-table">
                                <thead>
                                    <tr>
                                        <th>Medicine Name</th>
                                        <th>Generic Name</th>
                                        <th>Batch No</th>
                                        <th class="numeric">Stock</th>
                                        <th>Expiry Date</th>
                                        <th class="numeric">Days to Expiry</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($expiry_items as $item): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($item['med_name']); ?></td>
                                            <td><?php echo htmlspecialchars($item['generic_name']); ?></td>
                                            <td><?php echo htmlspecialchars($item['batch_no']); ?></td>
                                            <td class="numeric"><?php echo $item['stock_quantity']; ?></td>
                                            <td><?php echo date('M d, Y', strtotime($item['expiry_date'])); ?></td>
                                            <td class="numeric"><?php echo $item['days_to_expiry']; ?></td>
                                            <td>
                                                <?php
                                                if ($item['expiry_status'] == 'Expired') {
                                                    echo '<span class="expiry-badge-expired">Expired</span>';
                                                } elseif ($item['expiry_status'] == 'Near Expiry (30 days)') {
                                                    echo '<span class="expiry-badge-warning">Near Expiry</span>';
                                                } else {
                                                    echo '<span class="expiry-badge-good">Good</span>';
                                                }
                                                ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php else: ?>
                            <div class="text-center text-muted py-5">
                                <p>No items expiring soon</p>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- TAB 3: COMPLETE INVENTORY LISTING -->
                    <div id="listing-tab" class="tab-content">
                        <div style="overflow-x: auto;">
                            <table id="detailedInventoryTable" class="report-table">
                            <thead class="table-light">
                                <tr class="text-nowrap">
                                    <th>Med ID</th>
                                    <th>Medicine Name</th>
                                    <th>Generic Name</th>
                                    <th>Category</th>
                                    <th>Stock</th>
                                    <th>Unit Price (₱)</th>
                                    <th>Total Value (₱)</th>
                                    <th>Unit</th>
                                    <th>Rx</th>
                                    <th>Status</th>
                                    <th>Location</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($all_medicines)): ?>
                                    <?php foreach ($all_medicines as $med): ?>
                                        <?php
                                        $status_badge = '';
                                        if ($med['status'] == 'Out of Stock') {
                                            $status_badge = 'danger';
                                        } elseif ($med['status'] == 'Low Stock') {
                                            $status_badge = 'warning';
                                        } else {
                                            $status_badge = 'success';
                                        }
                                        ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($med['med_id']); ?></td>
                                            <td><?php echo htmlspecialchars($med['med_name']); ?></td>
                                            <td><?php echo htmlspecialchars($med['generic_name']); ?></td>
                                            <td><?php echo htmlspecialchars($med['category']); ?></td>
                                            <td><?php echo $med['stock_quantity']; ?> pieces</td>
                                            <td><?php echo number_format($med['unit_price'], 2); ?></td>
                                            <td><?php echo number_format($med['total_value'], 2); ?></td>
                                            <td><?php echo htmlspecialchars($med['unit']); ?></td>
                                            <td>
                                                <?php echo $med['prescription_required'] == 'Yes' ? '<span class="badge bg-danger">Rx</span>' : '<span class="badge bg-success">OTC</span>'; ?>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?php echo $status_badge; ?>">
                                                    <?php echo htmlspecialchars($med['status']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <small><?php echo htmlspecialchars($med['storage_room']); ?> | Shelf <?php echo $med['shelf_no']; ?> | Rack <?php echo $med['rack_no']; ?> | Bin <?php echo $med['bin_no']; ?></small>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="11" class="text-center text-muted">No inventory records found</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                        </div>
                    </div>
                </div>

                <!-- Report Generated Date -->
                <div class="mt-4 text-muted text-center">
                    <p><small>Report Generated: <?php echo date('F d, Y \a\t g:i A'); ?></small></p>
                </div>

            </div>
            <!-- END MAIN CONTENT -->
        </div>
        <!----- End of Main Content ----->
    </div>

    <script>
        function exportToExcel() {
            const url = 'export_handlers/export_excel.php?report=inventory';
            window.location.href = url;
        }

        function exportToPDF() {
            const url = 'export_handlers/export_pdf.php?report=inventory';
            window.location.href = url;
        }

        function printReport() {
            window.print();
        }

        // Tab Switching Function
        function switchTab(tabId) {
            // Hide all tab contents
            const tabContents = document.querySelectorAll('.tab-content');
            tabContents.forEach(tab => tab.classList.remove('active'));

            // Remove active class from all buttons
            const tabButtons = document.querySelectorAll('.tab-button');
            tabButtons.forEach(btn => btn.classList.remove('active'));

            // Show selected tab content
            document.getElementById(tabId).classList.add('active');

            // Add active class to clicked button
            event.target.closest('.tab-button').classList.add('active');
        }
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