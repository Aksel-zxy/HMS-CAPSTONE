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

// Date Range Filter
$from_date = $_GET['from_date'] ?? date('Y-m-d', strtotime('-30 days'));
$to_date = $_GET['to_date'] ?? date('Y-m-d');

// SALES REPORT - Combined Prescription and OTC sales by date
$sales_query = "
    SELECT
        sale_date,
        SUM(CASE WHEN transaction_type = 'Prescription' THEN 1 ELSE 0 END) as rx_orders,
        SUM(CASE WHEN transaction_type = 'OTC' THEN 1 ELSE 0 END) as otc_orders,
        SUM(CASE WHEN transaction_type = 'Prescription' THEN 1 ELSE 0 END) + SUM(CASE WHEN transaction_type = 'OTC' THEN 1 ELSE 0 END) as total_orders,
        SUM(CASE WHEN transaction_type = 'Prescription' THEN total_sales ELSE 0 END) as rx_sales,
        SUM(CASE WHEN transaction_type = 'OTC' THEN total_sales ELSE 0 END) as otc_sales,
        SUM(total_sales) as total_sales
    FROM (
        SELECT DATE(ppi.dispensed_date) as sale_date, SUM(ppi.total_price) as total_sales, 'Prescription' as transaction_type
        FROM pharmacy_prescription_items ppi
        WHERE DATE(ppi.dispensed_date) BETWEEN '{$from_date}' AND '{$to_date}' AND ppi.dispensed_date IS NOT NULL
        GROUP BY DATE(ppi.dispensed_date)

        UNION ALL

        SELECT DATE(ps.sale_date) as sale_date, SUM(ps.total_price) as total_sales, 'OTC' as transaction_type
        FROM pharmacy_sales ps
        WHERE DATE(ps.sale_date) BETWEEN '{$from_date}' AND '{$to_date}'
        GROUP BY DATE(ps.sale_date)
    ) combined
    GROUP BY sale_date
    ORDER BY sale_date DESC
";
$sales_result = $conn->query($sales_query);
$sales_data = $sales_result ? $sales_result->fetch_all(MYSQLI_ASSOC) : [];

// SALES BY MEDICINE - Get detailed medicine sales from both Prescription and OTC
$medicine_query = "
    SELECT
        med_id,
        med_name,
        category,
        SUM(quantity_sold) as quantity_sold,
        SUM(total_revenue) as total_revenue,
        SUM(cost) as cost,
        SUM(profit) as profit
    FROM (
        SELECT
            ppi.med_id,
            pi.med_name,
            pi.category,
            SUM(ppi.quantity_dispensed) as quantity_sold,
            SUM(ppi.total_price) as total_revenue,
            SUM(ppi.quantity_dispensed * ppi.unit_price) as cost,
            SUM(ppi.total_price - (ppi.quantity_dispensed * ppi.unit_price)) as profit
        FROM pharmacy_prescription_items ppi
        JOIN pharmacy_inventory pi ON ppi.med_id = pi.med_id
        WHERE DATE(ppi.dispensed_date) BETWEEN '{$from_date}' AND '{$to_date}' AND ppi.dispensed_date IS NOT NULL
        GROUP BY ppi.med_id, pi.med_name, pi.category

        UNION ALL

        SELECT
            pi.med_id,
            ps.med_name,
            pi.category,
            SUM(ps.quantity_sold) as quantity_sold,
            SUM(ps.total_price) as total_revenue,
            SUM(ps.quantity_sold * ps.price_per_unit) as cost,
            SUM(ps.total_price - (ps.quantity_sold * ps.price_per_unit)) as profit
        FROM pharmacy_sales ps
        LEFT JOIN pharmacy_inventory pi ON ps.med_name = pi.med_name
        WHERE DATE(ps.sale_date) BETWEEN '{$from_date}' AND '{$to_date}'
        GROUP BY ps.med_name, pi.category, pi.med_id
    ) combined_meds
    GROUP BY med_id, med_name, category
    ORDER BY total_revenue DESC
";
$medicine_result = $conn->query($medicine_query);
$medicine_data = $medicine_result ? $medicine_result->fetch_all(MYSQLI_ASSOC) : [];

// PROFIT REPORT - Overall summary
$profit_rx_query = "
    SELECT
        COUNT(DISTINCT pp.prescription_id) as total_transactions,
        SUM(ppi.total_price) as gross_revenue,
        SUM(ppi.quantity_dispensed) as total_items_sold,
        SUM(ppi.quantity_dispensed * ppi.unit_price) as total_cost
    FROM pharmacy_prescription pp
    JOIN pharmacy_prescription_items ppi ON pp.prescription_id = ppi.prescription_id
    WHERE DATE(ppi.dispensed_date) BETWEEN '{$from_date}' AND '{$to_date}' AND ppi.dispensed_date IS NOT NULL
";

$profit_otc_query = "
    SELECT
        COUNT(DISTINCT ps.sale_id) as total_transactions,
        SUM(ps.total_price) as gross_revenue,
        SUM(ps.quantity_sold) as total_items_sold,
        SUM(ps.quantity_sold * ps.price_per_unit) as total_cost
    FROM pharmacy_sales ps
    WHERE DATE(ps.sale_date) BETWEEN '{$from_date}' AND '{$to_date}'
";

$profit_rx_result = $conn->query($profit_rx_query);
$profit_rx_summary = $profit_rx_result ? $profit_rx_result->fetch_assoc() : ['total_transactions' => 0, 'gross_revenue' => 0, 'total_items_sold' => 0, 'total_cost' => 0];

$profit_otc_result = $conn->query($profit_otc_query);
$profit_otc_summary = $profit_otc_result ? $profit_otc_result->fetch_assoc() : ['total_transactions' => 0, 'gross_revenue' => 0, 'total_items_sold' => 0, 'total_cost' => 0];

// Combine profit summaries
$profit_summary = [
    'total_transactions' => ($profit_rx_summary['total_transactions'] ?? 0) + ($profit_otc_summary['total_transactions'] ?? 0),
    'gross_revenue' => ($profit_rx_summary['gross_revenue'] ?? 0) + ($profit_otc_summary['gross_revenue'] ?? 0),
    'total_items_sold' => ($profit_rx_summary['total_items_sold'] ?? 0) + ($profit_otc_summary['total_items_sold'] ?? 0),
    'total_cost' => ($profit_rx_summary['total_cost'] ?? 0) + ($profit_otc_summary['total_cost'] ?? 0)
];

// Calculate totals
$total_revenue = array_sum(array_column($medicine_data, 'total_revenue'));
$total_cost = array_sum(array_column($medicine_data, 'cost'));
$total_profit = $total_revenue - $total_cost;
$profit_margin = $total_revenue > 0 ? ($total_profit / $total_revenue) * 100 : 0;

// 🔔 Pending prescriptions count
$notif_sql = "SELECT COUNT(*) AS pending FROM pharmacy_prescription WHERE status = 'Pending'";
$notif_res = $conn->query($notif_sql);
$pendingCount = 0;
if ($notif_res && $notif_res->num_rows > 0) {
    $notif_row = $notif_res->fetch_assoc();
    $pendingCount = $notif_row['pending'];
}

// 🔴 Expiry count
$expiry_sql = "SELECT COUNT(*) AS expiry FROM pharmacy_stock_batches WHERE expiry_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)";
$expiry_res = $conn->query($expiry_sql);
$expiryCount = 0;
if ($expiry_res && $expiry_res->num_rows > 0) {
    $expiry_row = $expiry_res->fetch_assoc();
    $expiryCount = $expiry_row['expiry'];
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
    <title>HMS | Financial Report</title>
    <link rel="shortcut icon" href="assets/image/favicon.ico" type="image/x-icon">
    <link rel="stylesheet" href="assets/CSS/bootstrap.min.css">
    <link rel="stylesheet" href="assets/CSS/super.css">
    <link rel="stylesheet" href="assets/CSS/med_inventory.css">
    <link rel="stylesheet" href="assets/CSS/prescription.css">
    <style>
        .financial-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .date-filter-section {
            background: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            gap: 20px;
            flex-wrap: wrap;
        }

        .filter-group {
            display: flex;
            gap: 15px;
            align-items: flex-end;
            flex-wrap: wrap;
            flex: 1;
            min-width: 300px;
        }

        .filter-group .form-group {
            flex: 1;
            min-width: 150px;
            margin-bottom: 0;
        }

        .filter-group label {
            font-weight: 600;
            margin-bottom: 5px;
            display: block;
            color: #333;
        }

        .filter-group input[type="date"] {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }

        .filter-group .btn {
            padding: 8px 16px;
            font-size: 14px;
            white-space: nowrap;
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

        .btn-group-custom {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
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

        .pagination-container .page-btn {
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

        .pagination-container .page-btn:hover {
            background-color: #f0f0f0;
            border-color: #667eea;
            color: #667eea;
        }

        .pagination-container .page-btn.active {
            background-color: #667eea;
            border-color: #667eea;
            color: white;
            font-weight: 600;
        }

        .pagination-container .page-btn:disabled {
            background-color: #f5f5f5;
            border-color: #ddd;
            color: #999;
            cursor: not-allowed;
        }

        .pagination-info {
            text-align: center;
            color: #666;
            font-size: 13px;
            margin-top: 10px;
            font-weight: 500;
        }

        .table-pagination-wrapper {
            margin-bottom: 20px;
        }

        .stat-box {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            text-align: center;
            border-left: 4px solid #667eea;
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

        .report-section {
            background: white;
            padding: 25px;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .report-section h3 {
            border-bottom: 3px solid #667eea;
            padding-bottom: 10px;
            margin-bottom: 20px;
            font-size: 18px;
            font-weight: bold;
            color: #333;
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

        .profit-positive {
            color: #66bb6a;
            font-weight: 600;
        }

        .profit-negative {
            color: #ef5350;
            font-weight: 600;
        }

        .export-buttons {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-top: 20px;
        }

        .export-buttons button {
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 600;
            transition: transform 0.2s;
        }

        .export-buttons button:hover {
            transform: translateY(-2px);
        }

        .report-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 15px;
        }

        .report-header h3 {
            margin: 0;
            flex: 1;
            border-bottom: 3px solid #667eea;
            padding-bottom: 10px;
            font-size: 18px;
            font-weight: bold;
            color: #333;
            min-width: 250px;
        }

        .section-export-buttons {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .section-export-buttons button {
            padding: 8px 15px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 600;
            font-size: 12px;
            transition: transform 0.2s;
            white-space: nowrap;
        }

        .section-export-buttons button:hover {
            transform: translateY(-2px);
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

        @media print {
            body {
                background-color: white;
                margin: 0;
                padding: 0;
            }

            .sidebar-toggle,
            .topbar,
            .date-filter-section,
            .export-buttons,
            .btn-group-custom,
            .toggler-btn,
            .section-export-buttons,
            .export-button-group,
            .report-tabs {
                display: none !important;
            }

            .main {
                margin-left: 0 !important;
                padding: 0 !important;
            }

            .content {
                padding: 20px !important;
            }

            .title-container {
                text-align: center;
                margin-bottom: 30px;
                border-bottom: 3px solid #333;
                padding-bottom: 15px;
            }

            .title-container h1 {
                font-size: 24px;
                margin: 0;
                font-weight: bold;
            }

            .report-section {
                page-break-inside: avoid !important;
                box-shadow: none !important;
                border: 1px solid #ccc;
                margin-bottom: 20px;
            }

            .stat-box {
                page-break-inside: avoid !important;
                box-shadow: none !important;
                border: 1px solid #ccc !important;
                background: white !important;
            }

            .report-table {
                font-size: 11px;
                margin: 15px 0;
            }

            .report-table thead {
                background-color: #e0e0e0 !important;
                font-weight: bold;
            }

            .report-table th,
            .report-table td {
                border: 1px solid #999 !important;
                padding: 8px !important;
            }

            .report-header {
                border-bottom: 2px solid #333;
                margin-bottom: 15px;
                page-break-inside: avoid !important;
            }

            .report-header h3 {
                border-bottom: none !important;
                padding-bottom: 5px;
                color: #000;
                font-style: italic;
            }

            a {
                color: black;
                text-decoration: none;
            }

            .profit-positive,
            .profit-negative {
                font-weight: bold;
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

            <div class="menu-title">Pharmacy Management | <span>Financial</span></div>

            <!----- Sidebar Navigation ----->

            <li class="sidebar-item">
                <a href="pharmacy_dashboard.php" class="sidebar-link">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-cast" viewBox="0 0 16 16">
                        <path d="m7.646 9.354-3.792 3.792a.5.5 0 0 0 .353.854h7.586a.5.5 0 0 0 .354-.854L8.354 9.354a.5.5 0 0 0-.708 0" />
                        <path d="M11.414 11H14.5a.5.5 0 0 0 .5-.5v-7a.5.5 0 0 0-.5-.5h-13a.5.5 0 0 0-.5.5v7a.5.5 0 0 0 .5.5h3.086l-1 1H1.5A1.5 1.5 0 0 1 0 10.5v-7A1.5 1.5 0 0 1 1.5 2h13A1.5 1.5 0 0 1 16 3.5v7a1.5 1.5 0 0 1-1.5 1.5h-2.086z" />
                    </svg>
                    <span style="font-size: 18px;">Dashboard</span>
                </a>
            </li>

            <li class="sidebar-item">
                <a href="pharmacy_med_inventory.php" class="sidebar-link">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="fa-solid fa-capsules" viewBox="0 0 16 16">
                        <path d="m7.646 9.354-3.792 3.792a.5.5 0 0 0 .353.854h7.586a.5.5 0 0 0 .354-.854L8.354 9.354a.5.5 0 0 0-.708 0" />
                        <path d="M11.414 11H14.5a.5.5 0 0 0 .5-.5v-7a.5.5 0 0 0-.5-.5h-13a.5.5 0 0 0-.5.5v7a.5.5 0 0 0 .5.5h3.086l-1 1H1.5A1.5 1.5 0 0 1 0 10.5v-7A1.5 1.5 0 0 1 1.5 2h13A1.5 1.5 0 0 1 16 3.5v7a1.5 1.5 0 0 1-1.5 1.5h-2.086z" />
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
                <a href="pharmacy_otc.php" class="sidebar-link">
                    <i class="fa-solid fa-briefcase-medical"></i>
                    <span style="font-size: 18px;">Over The Counter</span>
                </a>
            </li>

            <li class="sidebar-item">
                <a href="#" class="sidebar-link collapsed has-dropdown" data-bs-toggle="collapse" data-bs-target="#gerald" aria-expanded="true" aria-controls="auth">
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
                        <a href="pharmacy_sales.php" class="sidebar-link active">Financial Report</a>
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
                        <svg xmlns="http://www.w3.org/2000/svg" width="30px" height="30px" fill="currentColor" class="bi bi-list-ul" viewBox="0 0 16 16">
                            <path fill-rule="evenodd" d="M5 11.5a.5.5 0 0 1 .5-.5h9a.5.5 0 0 1 0 1h-9a.5.5 0 0 1-.5-.5m0-4a.5.5 0 0 1 .5-.5h9a.5.5 0 0 1 0 1h-9a.5.5 0 0 1-.5-.5m0-4a.5.5 0 0 1 .5-.5h9a.5.5 0 0 1 0 1h-9a.5.5 0 0 1-.5-.5m-3 1a1 1 0 1 0 0-2 1 1 0 0 0 0 2m0 4a1 1 0 1 0 0-2 1 1 0 0 0 0 2m0 4a1 1 0 1 0 0-2 1 1 0 0 0 0 2" />
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

                            <ul class="dropdown-menu dropdown-menu-end shadow" aria-labelledby="notifBell" style="min-width:400px; max-height:400px; overflow-y:auto; border-radius:8px;">
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

            <!-- START MAIN CONTENT -->
            <div class="content" style="padding: 30px;">
                <div class="title-container">
                    <i class="fa-solid fa-chart-line"></i>
                    <h1 class="page-title">Financial Report</h1>
                </div>

                <!-- Date Range Filter -->
                <div class="date-filter-section">
                    <form method="GET" class="filter-group">
                        <div class="form-group">
                            <label for="from_date">From Date</label>
                            <input type="date" id="from_date" name="from_date" value="<?php echo $from_date; ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="to_date">To Date</label>
                            <input type="date" id="to_date" name="to_date" value="<?php echo $to_date; ?>" required>
                        </div>
                        <button type="submit" class="btn btn-primary">
                            <i class="fa-solid fa-filter me-1"></i>Apply Filter
                        </button>
                    </form>

                    <!-- Export and Print Buttons -->
                    <div class="export-button-group">
                        <button class="btn btn-success" onclick="exportAllExcel()" title="Download all reports as Excel">
                            <i class="fa-solid fa-file-excel"></i> Excel
                        </button>
                        <button class="btn btn-danger" onclick="exportAllPDF()" title="Download all reports as PDF">
                            <i class="fa-solid fa-file-pdf"></i> PDF
                        </button>
                        <button class="btn btn-info" onclick="printAllReports()" title="Print all reports">
                            <i class="fa-solid fa-print"></i> Print
                        </button>
                    </div>
                </div>

                <!-- Summary Statistics -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="stat-box revenue">
                            <h6>Total Revenue</h6>
                            <div class="value">₱<?php echo number_format($total_revenue, 2); ?></div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-box cost">
                            <h6>Total Cost</h6>
                            <div class="value">₱<?php echo number_format($total_cost, 2); ?></div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-box profit">
                            <h6>Total Profit</h6>
                            <div class="value <?php echo $total_profit >= 0 ? 'profit-positive' : 'profit-negative'; ?>">
                                ₱<?php echo number_format($total_profit, 2); ?>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-box">
                            <h6>Profit Margin</h6>
                            <div class="value"><?php echo number_format($profit_margin, 2); ?>%</div>
                        </div>
                    </div>
                </div>

                <!-- REPORTS TAB CONTAINER -->
                <div class="report-section">
                    <!-- Tab Navigation -->
                    <div class="report-tabs">
                        <button class="tab-button active" onclick="switchTab('sales-tab')">
                            <i class="fa-solid fa-receipt me-2"></i>Sales Report
                        </button>
                        <button class="tab-button" onclick="switchTab('medicine-tab')">
                            <i class="fa-solid fa-pills me-2"></i>Sales by Medicine
                        </button>
                        <button class="tab-button" onclick="switchTab('profit-tab')">
                            <i class="fa-solid fa-chart-pie me-2"></i>Profit Report
                        </button>
                    </div>

                    <!-- TAB 1: SALES REPORT -->
                    <div id="sales-tab" class="tab-content active">
                        <div class="table-pagination-wrapper">
                            <table class="report-table" id="salesReportTable">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th class="numeric">Total Orders</th>
                                        <th class="numeric">Prescription Orders</th>
                                        <th class="numeric">OTC Orders</th>
                                        <th class="numeric">Rx Sales</th>
                                        <th class="numeric">OTC Sales</th>
                                        <th class="numeric">Daily Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($sales_data)): ?>
                                        <?php foreach ($sales_data as $sale): ?>
                                            <tr>
                                                <td><?php echo date('M d, Y', strtotime($sale['sale_date'])); ?></td>
                                                <td class="numeric"><?php echo $sale['total_orders'] ?? 0; ?></td>
                                                <td class="numeric"><?php echo $sale['rx_orders'] ?? 0; ?></td>
                                                <td class="numeric"><?php echo $sale['otc_orders'] ?? 0; ?></td>
                                                <td class="numeric">₱<?php echo number_format($sale['rx_sales'] ?? 0, 2); ?></td>
                                                <td class="numeric">₱<?php echo number_format($sale['otc_sales'] ?? 0, 2); ?></td>
                                                <td class="numeric" style="font-weight: bold;">₱<?php echo number_format($sale['total_sales'] ?? 0, 2); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="7" class="text-center text-muted">No sales data available for the selected date range</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>

                            <!-- Pagination Controls for Sales Report -->
                            <?php if (!empty($sales_data)): ?>
                                <div class="pagination-container" id="salesReportPagination"></div>
                                <div class="pagination-info" id="salesReportInfo"></div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- TAB 2: SALES BY MEDICINE REPORT -->
                    <div id="medicine-tab" class="tab-content">
                        <div class="table-pagination-wrapper">
                            <table class="report-table" id="medicineReportTable">
                                <thead>
                                    <tr>
                                        <th>Medicine Name</th>
                                        <th>Category</th>
                                        <th class="numeric">Quantity Sold</th>
                                        <th class="numeric">Total Revenue</th>
                                        <th class="numeric">Total Cost</th>
                                        <th class="numeric">Profit</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($medicine_data)): ?>
                                        <?php foreach ($medicine_data as $med): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($med['med_name']); ?></td>
                                                <td><?php echo htmlspecialchars($med['category']); ?></td>
                                                <td class="numeric"><?php echo $med['quantity_sold']; ?></td>
                                                <td class="numeric">₱<?php echo number_format($med['total_revenue'], 2); ?></td>
                                                <td class="numeric">₱<?php echo number_format($med['cost'], 2); ?></td>
                                                <td class="numeric <?php echo $med['profit'] >= 0 ? 'profit-positive' : 'profit-negative'; ?>">
                                                    ₱<?php echo number_format($med['profit'], 2); ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="6" class="text-center text-muted">No medicine sales data available for the selected date range</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>

                            <!-- Pagination Controls for Medicine Report -->
                            <?php if (!empty($medicine_data)): ?>
                                <div class="pagination-container" id="medicineReportPagination"></div>
                                <div class="pagination-info" id="medicineReportInfo"></div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- TAB 3: PROFIT REPORT -->
                    <div id="profit-tab" class="tab-content">

                        <div class="row mb-4">
                            <div class="col-md-3">
                                <div class="stat-box">
                                    <h6>Total Transactions</h6>
                                    <div class="value"><?php echo $profit_summary['total_transactions'] ?? 0; ?></div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="stat-box revenue">
                                    <h6>Gross Revenue</h6>
                                    <div class="value">₱<?php echo number_format($profit_summary['gross_revenue'] ?? 0, 2); ?></div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="stat-box">
                                    <h6>Items Sold</h6>
                                    <div class="value"><?php echo $profit_summary['total_items_sold'] ?? 0; ?></div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="stat-box cost">
                                    <h6>Total Cost</h6>
                                    <div class="value">₱<?php echo number_format($profit_summary['total_cost'] ?? 0, 2); ?></div>
                                </div>
                            </div>
                        </div>

                        <table class="report-table">
                            <thead>
                                <tr>
                                    <th>Metric</th>
                                    <th class="numeric">Value</th>
                                    <th>Description</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td><strong>Total Revenue</strong></td>
                                    <td class="numeric"><strong>₱<?php echo number_format($total_revenue, 2); ?></strong></td>
                                    <td>Sum of all sales revenue</td>
                                </tr>
                                <tr>
                                    <td><strong>Total Cost</strong></td>
                                    <td class="numeric"><strong>₱<?php echo number_format($total_cost, 2); ?></strong></td>
                                    <td>Cost of all medicines sold</td>
                                </tr>
                                <tr>
                                    <td><strong>Total Profit</strong></td>
                                    <td class="numeric <?php echo $total_profit >= 0 ? 'profit-positive' : 'profit-negative'; ?>"><strong>₱<?php echo number_format($total_profit, 2); ?></strong></td>
                                    <td>Revenue minus Cost</td>
                                </tr>
                                <tr>
                                    <td><strong>Profit Margin</strong></td>
                                    <td class="numeric"><strong><?php echo number_format($profit_margin, 2); ?>%</strong></td>
                                    <td>Profit as percentage of revenue</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Export and Print Buttons -->


            </div>
            <!-- END MAIN CONTENT -->
        </div>
        <!----- End of Main Content ----->
    </div>

    <script>
        const toggler = document.querySelector(".toggler-btn");
        if (toggler) {
            toggler.addEventListener("click", function() {
                document.querySelector("#sidebar").classList.toggle("collapsed");
            });
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

            // Initialize pagination for the active tab
            if (tabId === 'sales-tab') {
                initPagination('salesReportTable', 'salesReportPagination', 'salesReportInfo', 10);
            } else if (tabId === 'medicine-tab') {
                initPagination('medicineReportTable', 'medicineReportPagination', 'medicineReportInfo', 10);
            }
        }

        // Pagination Function
        function initPagination(tableId, paginationContainerId, infoContainerId, rowsPerPage) {
            const table = document.getElementById(tableId);
            const rows = Array.from(table.querySelectorAll('tbody tr')).filter(row => {
                // Exclude "no data" rows
                const text = row.textContent;
                return !text.includes('No') || !text.includes('data');
            });

            if (rows.length === 0) return;

            const totalPages = Math.ceil(rows.length / rowsPerPage);
            let currentPage = 1;

            function showPage(page) {
                const start = (page - 1) * rowsPerPage;
                const end = start + rowsPerPage;

                rows.forEach((row, index) => {
                    if (index >= start && index < end) {
                        row.style.display = '';
                    } else {
                        row.style.display = 'none';
                    }
                });

                // Update pagination buttons
                updatePaginationButtons(page);
            }

            function updatePaginationButtons(page) {
                const paginationContainer = document.getElementById(paginationContainerId);
                const infoContainer = document.getElementById(infoContainerId);

                paginationContainer.innerHTML = '';

                // Previous button
                const prevBtn = document.createElement('button');
                prevBtn.className = 'page-btn';
                prevBtn.innerHTML = '<i class="fa-solid fa-chevron-left me-1"></i>Previous';
                prevBtn.disabled = page === 1;
                prevBtn.onclick = () => {
                    if (page > 1) {
                        currentPage = page - 1;
                        showPage(currentPage);
                    }
                };
                paginationContainer.appendChild(prevBtn);

                // Page numbers
                const startPage = Math.max(1, page - 2);
                const endPage = Math.min(totalPages, page + 2);

                if (startPage > 1) {
                    const ellipsis = document.createElement('span');
                    ellipsis.className = 'page-btn';
                    ellipsis.style.border = 'none';
                    ellipsis.style.cursor = 'default';
                    ellipsis.innerHTML = '...';
                    paginationContainer.appendChild(ellipsis);
                }

                for (let i = startPage; i <= endPage; i++) {
                    const pageBtn = document.createElement('button');
                    pageBtn.className = 'page-btn';
                    if (i === page) pageBtn.classList.add('active');
                    pageBtn.textContent = i;
                    pageBtn.onclick = () => {
                        currentPage = i;
                        showPage(currentPage);
                    };
                    paginationContainer.appendChild(pageBtn);
                }

                if (endPage < totalPages) {
                    const ellipsis = document.createElement('span');
                    ellipsis.className = 'page-btn';
                    ellipsis.style.border = 'none';
                    ellipsis.style.cursor = 'default';
                    ellipsis.innerHTML = '...';
                    paginationContainer.appendChild(ellipsis);
                }

                // Next button
                const nextBtn = document.createElement('button');
                nextBtn.className = 'page-btn';
                nextBtn.innerHTML = 'Next<i class="fa-solid fa-chevron-right ms-1"></i>';
                nextBtn.disabled = page === totalPages;
                nextBtn.onclick = () => {
                    if (page < totalPages) {
                        currentPage = page + 1;
                        showPage(currentPage);
                    }
                };
                paginationContainer.appendChild(nextBtn);

                // Update info
                const start = (page - 1) * rowsPerPage + 1;
                const end = Math.min(page * rowsPerPage, rows.length);
                infoContainer.textContent = `Showing ${start}-${end} of ${rows.length} items | Page ${page} of ${totalPages}`;
            }

            // Show first page
            showPage(currentPage);
        }

        // Initialize pagination on page load
        document.addEventListener('DOMContentLoaded', function() {
            initPagination('salesReportTable', 'salesReportPagination', 'salesReportInfo', 10);
            initPagination('medicineReportTable', 'medicineReportPagination', 'medicineReportInfo', 10);
        });

        // Main Export & Print Functions (for filter section buttons)
        function exportAllExcel() {
            const fromDate = document.getElementById('from_date').value;
            const toDate = document.getElementById('to_date').value;
            window.location.href = `export_handlers/export_financial_excel.php?from_date=${fromDate}&to_date=${toDate}`;
        }

        function exportAllPDF() {
            const fromDate = document.getElementById('from_date').value;
            const toDate = document.getElementById('to_date').value;
            window.location.href = `export_handlers/export_financial_pdf.php?from_date=${fromDate}&to_date=${toDate}`;
        }

        function printAllReports() {
            window.print();
        }

        // Tab-Specific Export Functions (legacy - kept for compatibility)
        function exportSalesReport() {
            const fromDate = document.getElementById('from_date').value;
            const toDate = document.getElementById('to_date').value;
            window.location.href = `export_handlers/export_financial_excel.php?from_date=${fromDate}&to_date=${toDate}`;
        }

        function exportSalesReportPDF() {
            const fromDate = document.getElementById('from_date').value;
            const toDate = document.getElementById('to_date').value;
            window.location.href = `export_handlers/export_financial_pdf.php?from_date=${fromDate}&to_date=${toDate}`;
        }

        function printSalesReport() {
            window.print();
        }

        function exportMedicineReport() {
            const fromDate = document.getElementById('from_date').value;
            const toDate = document.getElementById('to_date').value;
            window.location.href = `export_handlers/export_financial_excel.php?from_date=${fromDate}&to_date=${toDate}`;
        }

        function exportMedicineReportPDF() {
            const fromDate = document.getElementById('from_date').value;
            const toDate = document.getElementById('to_date').value;
            window.location.href = `export_handlers/export_financial_pdf.php?from_date=${fromDate}&to_date=${toDate}`;
        }

        function printMedicineReport() {
            window.print();
        }

        function exportProfitReport() {
            const fromDate = document.getElementById('from_date').value;
            const toDate = document.getElementById('to_date').value;
            window.location.href = `export_handlers/export_financial_excel.php?from_date=${fromDate}&to_date=${toDate}`;
        }

        function exportProfitReportPDF() {
            const fromDate = document.getElementById('from_date').value;
            const toDate = document.getElementById('to_date').value;
            window.location.href = `export_handlers/export_financial_pdf.php?from_date=${fromDate}&to_date=${toDate}`;
        }

        function printProfitReport() {
            window.print();
        }

        // Full Report Export Functions
        function exportToExcel() {
            const fromDate = document.getElementById('from_date').value;
            const toDate = document.getElementById('to_date').value;
            window.location.href = `export_handlers/export_financial_excel.php?from_date=${fromDate}&to_date=${toDate}`;
        }

        function exportToPDF() {
            const fromDate = document.getElementById('from_date').value;
            const toDate = document.getElementById('to_date').value;
            window.location.href = `export_handlers/export_financial_pdf.php?from_date=${fromDate}&to_date=${toDate}`;
        }

        function printReport() {
            window.print();
        }
    </script>

    <script src="assets/Bootstrap/all.min.js"></script>
    <script src="assets/Bootstrap/bootstrap.bundle.min.js"></script>
    <script src="assets/Bootstrap/fontawesome.min.js"></script>
    <script src="assets/Bootstrap/jq.js"></script>
</body>

</html>