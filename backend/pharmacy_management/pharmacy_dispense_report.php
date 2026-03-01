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

// Default date range (last 7 days)
$end_date = date('Y-m-d');
$start_date = date('Y-m-d', strtotime('-7 days'));

// Get dates from request
if (isset($_GET['start_date'])) {
    $start_date = $_GET['start_date'];
}
if (isset($_GET['end_date'])) {
    $end_date = $_GET['end_date'];
}

// Initialize medicine object
$medicineObj = new Medicine($conn);

// Fetch Daily Dispensing Report Data
$daily_query = "
    SELECT
        DATE(ppi.dispensed_date) as date,
        COUNT(DISTINCT ppi.item_id) as total_items_dispensed,
        COUNT(DISTINCT pp.patient_id) as total_patients,
        SUM(ppi.quantity_dispensed) as total_quantity,
        SUM(ppi.total_price) as total_value
    FROM pharmacy_prescription_items ppi
    JOIN pharmacy_prescription pp ON ppi.prescription_id = pp.prescription_id
    WHERE DATE(ppi.dispensed_date) BETWEEN ? AND ?
    AND ppi.quantity_dispensed IS NOT NULL
    GROUP BY DATE(ppi.dispensed_date)
    ORDER BY DATE(ppi.dispensed_date) DESC
";
$stmt_daily = $conn->prepare($daily_query);
$stmt_daily->bind_param("ss", $start_date, $end_date);
$stmt_daily->execute();
$daily_result = $stmt_daily->get_result();
$daily_data = $daily_result->fetch_all(MYSQLI_ASSOC);

// Fetch Inpatient Medication Report Data
$inpatient_query = "
    SELECT 
        sm.*,
        CONCAT(pi.fname, ' ', pi.lname) AS patient_name,
        CONCAT(e.first_name, ' ', e.last_name) AS doctor_name,
        CONCAT(m.generic_name, ' (', m.brand_name, ')') AS medicine_name,
        m.stock_quantity,
        IFNULL(COUNT(sml.log_id),0) AS doses_given
    FROM scheduled_medications sm
    JOIN patientinfo pi ON sm.patient_id = pi.patient_id
    JOIN hr_employees e ON sm.doctor_id = e.employee_id
    JOIN pharmacy_inventory m ON sm.med_id = m.med_id
    LEFT JOIN scheduled_medication_logs sml ON sm.schedule_id = sml.schedule_id
    WHERE DATE(sm.start_date) BETWEEN ? AND ?
    GROUP BY sm.schedule_id
    ORDER BY sm.start_date ASC
";
$stmt_inpatient = $conn->prepare($inpatient_query);
$stmt_inpatient->bind_param("ss", $start_date, $end_date);
$stmt_inpatient->execute();
$inpatient_result = $stmt_inpatient->get_result();
$inpatient_data = $inpatient_result->fetch_all(MYSQLI_ASSOC);

// Fetch Medicine Usage Report Data
$usage_query = "
    SELECT
        pi.med_id,
        pi.med_name,
        pi.generic_name,
        pi.category,
        SUM(ppi.quantity_dispensed) as total_dispensed,
        COUNT(DISTINCT pp.patient_id) as unique_patients,
        SUM(ppi.total_price) as total_cost,
        AVG(ppi.quantity_dispensed) as avg_qty_per_dispensing
    FROM pharmacy_prescription_items ppi
    JOIN pharmacy_prescription pp ON ppi.prescription_id = pp.prescription_id
    JOIN pharmacy_inventory pi ON ppi.med_id = pi.med_id
    WHERE DATE(ppi.dispensed_date) BETWEEN ? AND ?
    AND ppi.quantity_dispensed IS NOT NULL
    GROUP BY pi.med_id, pi.med_name, pi.generic_name, pi.category
    ORDER BY total_dispensed DESC
";
$stmt_usage = $conn->prepare($usage_query);
$stmt_usage->bind_param("ss", $start_date, $end_date);
$stmt_usage->execute();
$usage_result = $stmt_usage->get_result();
$usage_data = $usage_result->fetch_all(MYSQLI_ASSOC);

// Fetch Daily Summary Statistics
$summary_query = "
    SELECT
        COUNT(DISTINCT ppi.item_id) as total_transactions,
        COUNT(DISTINCT pp.patient_id) as total_patients,
        SUM(ppi.quantity_dispensed) as total_items,
        SUM(ppi.total_price) as total_value,
        COUNT(DISTINCT ppi.med_id) as unique_medicines
    FROM pharmacy_prescription_items ppi
    JOIN pharmacy_prescription pp ON ppi.prescription_id = pp.prescription_id
    WHERE DATE(ppi.dispensed_date) BETWEEN ? AND ?
    AND ppi.quantity_dispensed IS NOT NULL
";
$stmt_summary = $conn->prepare($summary_query);
$stmt_summary->bind_param("ss", $start_date, $end_date);
$stmt_summary->execute();
$summary_result = $stmt_summary->get_result();
$summary = $summary_result->fetch_assoc();

// 🔔 Pending prescriptions count
$notif_sql = "SELECT COUNT(*) AS pending 
              FROM pharmacy_prescription 
              WHERE status = 'Pending'";
$notif_res = $conn->query($notif_sql);

$pendingCount = 0;
if ($notif_res && $notif_res->num_rows > 0) {
    $notif_row = $notif_res->fetch_assoc();
    $pendingCount += (int)$notif_row['pending'];
}

// 🔔 Pending Scheduled prescriptions count
$sched_notif_sql = "SELECT COUNT(*) AS pending 
                    FROM scheduled_medications 
                    WHERE status IN ('pending', 'ongoing')";
$sched_notif_res = $conn->query($sched_notif_sql);

if ($sched_notif_res && $sched_notif_res->num_rows > 0) {
    $sched_notif_row = $sched_notif_res->fetch_assoc();
    $pendingCount += (int)$sched_notif_row['pending'];
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
    <title>HMS | Dispensing Report</title>
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
            justify-content: space-between;
            align-items: center;
            gap: 15px;
            flex-wrap: wrap;
        }

        .filter-group {
            display: flex;
            gap: 10px;
            align-items: center;
            flex-wrap: wrap;
        }

        .filter-group label {
            font-weight: 600;
            color: #333;
            margin-bottom: 0;
        }

        .filter-group input {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 13px;
        }

        .export-button-group {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
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

        .filter-button {
            background: linear-gradient(135deg, #667eea 0%, #5a67d8 100%);
            color: white;
            padding: 10px 18px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .filter-button:hover {
            background: linear-gradient(135deg, #5a67d8 0%, #4c51bf 100%);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
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

            h1,
            h3 {
                color: #000 !important;
            }

            td,
            th {
                border: 1px solid #999 !important;
                padding: 6px !important;
            }

            .section-header {
                border-bottom: 2px solid #000 !important;
            }
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

        .empty-state {
            text-align: center;
            padding: 40px;
            color: #999;
        }

        .empty-state i {
            font-size: 48px;
            margin-bottom: 15px;
            color: #ccc;
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

            <div class="menu-title">Pharmacy Management | Dispensing Report</div>

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
                        <a href="pharmacy_inventory_report.php" class="sidebar-link">Inventory Report</a>
                    </li>
                    <li class="sidebar-item">
                        <a href="pharmacy_sales.php" class="sidebar-link">Financial Report</a>
                    </li>
                    <li class="sidebar-item">
                        <a href="pharmacy_dispense_report.php" class="sidebar-link active">Dispensing Report</a>
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
                    <i class="fa-solid fa-prescription-bottle-medical"></i>
                    <h1 class="page-title">Dispensing Report</h1>
                </div>

                <!-- Filter Section with Date Range -->
                <div class="filter-section">
                    <div class="filter-group">
                        <label for="start_date">From:</label>
                        <input type="date" id="start_date" value="<?php echo $start_date; ?>">
                        <label for="end_date">To:</label>
                        <input type="date" id="end_date" value="<?php echo $end_date; ?>">
                        <button class="filter-button" onclick="applyFilter()">
                            <i class="fa-solid fa-filter"></i> Filter
                        </button>
                    </div>
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
                            <h6>Total Transactions</h6>
                            <div class="value"><?php echo $summary['total_transactions'] ?? 0; ?></div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-box profit">
                            <h6>Total Patients</h6>
                            <div class="value"><?php echo $summary['total_patients'] ?? 0; ?></div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-box cost">
                            <h6>Total Items Dispensed</h6>
                            <div class="value"><?php echo $summary['total_items'] ?? 0; ?></div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-box warning">
                            <h6>Total Value</h6>
                            <div class="value">₱<?php echo number_format($summary['total_value'] ?? 0, 2); ?></div>
                        </div>
                    </div>
                </div>

                <!-- REPORTS TAB CONTAINER -->
                <div class="report-section">
                    <!-- Tab Navigation -->
                    <div class="report-tabs">
                        <button class="tab-button active" onclick="switchTab('daily-tab')">
                            <i class="fa-solid fa-calendar-days me-2"></i>Daily Dispensing
                        </button>
                        <button class="tab-button" onclick="switchTab('inpatient-tab')">
                            <i class="fa-solid fa-hospital-user me-2"></i>Inpatient Medications
                        </button>
                        <button class="tab-button" onclick="switchTab('usage-tab')">
                            <i class="fa-solid fa-chart-simple me-2"></i>Medicine Usage
                        </button>
                    </div>

                    <!-- TAB 1: DAILY DISPENSING REPORT -->
                    <div id="daily-tab" class="tab-content active">
                        <?php if (!empty($daily_data)): ?>
                            <table class="report-table">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th class="numeric">Items Dispensed</th>
                                        <th class="numeric">Total Patients</th>
                                        <th class="numeric">Total Quantity</th>
                                        <th class="numeric">Total Value (₱)</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($daily_data as $day): ?>
                                        <tr>
                                            <td><strong><?php echo date('M d, Y', strtotime($day['date'])); ?></strong></td>
                                            <td class="numeric"><?php echo $day['total_items_dispensed']; ?></td>
                                            <td class="numeric"><?php echo $day['total_patients']; ?></td>
                                            <td class="numeric"><?php echo $day['total_quantity']; ?></td>
                                            <td class="numeric">₱<?php echo number_format($day['total_value'], 2); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php else: ?>
                            <div class="empty-state">
                                <i class="fa-solid fa-inbox"></i>
                                <p>No dispensing data available for the selected date range.</p>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- TAB 2: INPATIENT MEDICATION REPORT -->
                    <div id="inpatient-tab" class="tab-content">
                        <?php if (!empty($inpatient_data)): ?>
                            <table class="report-table">
                                <thead>
                                    <tr>
                                        <th>Schedule ID</th>
                                        <th>Patient Name</th>
                                        <th>Doctor</th>
                                        <th>Medicine</th>
                                        <th>Dosage</th>
                                        <th>Frequency</th>
                                        <th>Route</th>
                                        <th class="numeric">Duration</th>
                                        <th class="numeric">Doses Given</th>
                                        <th>Status</th>
                                        <th>Start Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($inpatient_data as $inpatient): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($inpatient['schedule_id']); ?></td>
                                            <td><?php echo htmlspecialchars($inpatient['patient_name']); ?></td>
                                            <td><?php echo htmlspecialchars($inpatient['doctor_name']); ?></td>
                                            <td><?php echo htmlspecialchars($inpatient['medicine_name']); ?></td>
                                            <td><?php echo htmlspecialchars($inpatient['dosage']); ?></td>
                                            <td><?php echo htmlspecialchars($inpatient['frequency']); ?></td>
                                            <td><?php echo htmlspecialchars($inpatient['route']); ?></td>
                                            <td class="numeric"><?php echo $inpatient['duration_days']; ?> days</td>
                                            <td class="numeric"><?php echo $inpatient['doses_given']; ?></td>
                                            <td>
                                                <?php
                                                $status = strtolower($inpatient['status'] ?? 'pending');
                                                echo ucfirst($status);
                                                ?>
                                            </td>
                                            <td><?php echo date('M d, Y', strtotime($inpatient['start_date'])); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php else: ?>
                            <div class="empty-state">
                                <i class="fa-solid fa-inbox"></i>
                                <p>No inpatient medication data available for the selected date range.</p>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- TAB 3: MEDICINE USAGE REPORT -->
                    <div id="usage-tab" class="tab-content">
                        <?php if (!empty($usage_data)): ?>
                            <table class="report-table">
                                <thead>
                                    <tr>
                                        <th>Medicine Name</th>
                                        <th>Generic Name</th>
                                        <th>Category</th>
                                        <th class="numeric">Total Dispensed</th>
                                        <th class="numeric">Unique Patients</th>
                                        <th class="numeric">Avg Qty per Dispensing</th>
                                        <th class="numeric">Total Cost (₱)</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($usage_data as $medicine): ?>
                                        <tr>
                                            <td><strong><?php echo htmlspecialchars($medicine['med_name']); ?></strong></td>
                                            <td><?php echo htmlspecialchars($medicine['generic_name']); ?></td>
                                            <td><?php echo htmlspecialchars($medicine['category']); ?></td>
                                            <td class="numeric"><?php echo $medicine['total_dispensed']; ?></td>
                                            <td class="numeric"><?php echo $medicine['unique_patients']; ?></td>
                                            <td class="numeric">
                                                <?php echo number_format((float)($medicine['avg_qty_per_dispensing'] ?? 0), 2); ?>
                                            </td>
                                            <td class="numeric">₱<?php echo number_format($medicine['total_cost'], 2); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php else: ?>
                            <div class="empty-state">
                                <i class="fa-solid fa-inbox"></i>
                                <p>No medicine usage data available for the selected date range.</p>
                            </div>
                        <?php endif; ?>
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
        function applyFilter() {
            const startDate = document.getElementById('start_date').value;
            const endDate = document.getElementById('end_date').value;

            if (!startDate || !endDate) {
                alert('Please select both start and end dates');
                return;
            }

            if (new Date(startDate) > new Date(endDate)) {
                alert('Start date must be before end date');
                return;
            }

            window.location.href = `?start_date=${startDate}&end_date=${endDate}`;
        }

        function exportToExcel() {
            const startDate = document.getElementById('start_date').value;
            const endDate = document.getElementById('end_date').value;
            const url = `export_handlers/export_dispensing_excel.php?start_date=${startDate}&end_date=${endDate}`;
            window.location.href = url;
        }

        function exportToPDF() {
            const startDate = document.getElementById('start_date').value;
            const endDate = document.getElementById('end_date').value;
            const url = `export_handlers/export_dispensing_pdf.php?start_date=${startDate}&end_date=${endDate}`;
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