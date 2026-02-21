<?php
session_start();
include '../../../SQL/config.php';

if (!isset($_SESSION['labtech']) || $_SESSION['labtech'] !== true) {
    header('Location: ' . BASE_URL . 'backend/login.php');
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

$GEMINI_API_KEY = getenv('GERALD_KEY') ?? '';

function getGeminiSummary($apiKey, $prompt) {
    if (empty($apiKey)) return "This report presents the number of tests recorded per laboratory service category during the reporting period. It helps monitor testing trends, resource allocation, and overall laboratory performance.";

    $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-flash-latest:generateContent?key=" . $apiKey;
    $data = [ "contents" => [ [ "parts" => [ ["text" => $prompt] ] ] ] ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    $response = curl_exec($ch);
    if (curl_errno($ch)) return "This report presents the number of tests recorded per laboratory service category during the reporting period. It helps monitor testing trends, resource allocation, and overall laboratory performance.";
    curl_close($ch);

    $decoded = json_decode($response, true);
    if (isset($decoded['candidates'][0]['content']['parts'][0]['text'])) {
        return str_replace(['*', '#'], '', $decoded['candidates'][0]['content']['parts'][0]['text']);
    }
    return "This report presents the number of tests recorded per laboratory service category during the reporting period. It helps monitor testing trends, resource allocation, and overall laboratory performance.";
}

$selected_month = isset($_GET['month']) ? (int)$_GET['month'] : date('m');
$selected_year = isset($_GET['year']) ? (int)$_GET['year'] : date('Y');
$month_name = date("F", mktime(0, 0, 0, $selected_month, 10));

$total_patients = 0;
$sql_patients = "SELECT COUNT(DISTINCT patientID) as count FROM dl_schedule WHERE MONTH(scheduleDate) = $selected_month AND YEAR(scheduleDate) = $selected_year";
$res_p = $conn->query($sql_patients);
if ($res_p && $row = $res_p->fetch_assoc()) {
    $total_patients = $row['count'];
}

$chart_dates = [];
$chart_counts = [];

$sql_line = "SELECT scheduleDate as log_date, COUNT(*) as total 
             FROM dl_schedule 
             WHERE MONTH(scheduleDate) = $selected_month AND YEAR(scheduleDate) = $selected_year 
             GROUP BY scheduleDate 
             ORDER BY log_date ASC";

$res_line = $conn->query($sql_line);
if ($res_line) {
    while ($row = $res_line->fetch_assoc()) {
        $chart_dates[] = date('M d', strtotime($row['log_date']));
        $chart_counts[] = $row['total'];
    }
}

$donut_labels = [];
$donut_data = [];

$sql_donut = "SELECT serviceName, COUNT(*) as total 
              FROM dl_schedule 
              WHERE MONTH(scheduleDate) = $selected_month AND YEAR(scheduleDate) = $selected_year 
              GROUP BY serviceName 
              ORDER BY total DESC 
              LIMIT 5";

$res_donut = $conn->query($sql_donut);
if ($res_donut) {
    while ($row = $res_donut->fetch_assoc()) {
        $donut_labels[] = $row['serviceName'];
        $donut_data[] = $row['total'];
    }
}

$avg_turnaround = "0";
$sql_turnaround = "SELECT AVG(TIMESTAMPDIFF(MINUTE, CONCAT(scheduleDate, ' ', scheduleTime), completed_at)) as avg_min 
                   FROM dl_schedule 
                   WHERE completed_at IS NOT NULL AND completed_at != '0000-00-00 00:00:00' 
                   AND MONTH(scheduleDate) = $selected_month AND YEAR(scheduleDate) = $selected_year";

$res_turn = $conn->query($sql_turnaround);
if ($res_turn && $row = $res_turn->fetch_assoc()) {
    $minutes = $row['avg_min'];
    if ($minutes > 0) {
        $avg_turnaround = round($minutes / 60, 1);
    }
}

$equipment_health = 0;

$total_machines = 0;
$sql_total_eq = "SELECT COUNT(*) as total FROM machine_equipments";
$res_total_eq = $conn->query($sql_total_eq);
if ($res_total_eq && $row = $res_total_eq->fetch_assoc()) {
    $total_machines = $row['total'];
}

$working_machines = 0;
$sql_working_eq = "SELECT COUNT(*) as working FROM machine_equipments WHERE status = 'Available'";
$res_working_eq = $conn->query($sql_working_eq);
if ($res_working_eq && $row = $res_working_eq->fetch_assoc()) {
    $working_machines = $row['working'];
}

if ($total_machines > 0) {
    $equipment_health = round(($working_machines / $total_machines) * 100, 1);
} else {
    $equipment_health = 100;
}

$json_dates = json_encode($chart_dates);
$json_counts = json_encode($chart_counts);
$json_donut_labels = json_encode($donut_labels);
$json_donut_data = json_encode($donut_data);


$sql_all_tests = "SELECT serviceName, COUNT(*) as total 
                  FROM dl_schedule 
                  WHERE MONTH(scheduleDate) = $selected_month AND YEAR(scheduleDate) = $selected_year
                  GROUP BY serviceName 
                  ORDER BY total DESC";
$res_all_tests = $conn->query($sql_all_tests);
$all_tests = [];
$total_all_tests = 0;
if ($res_all_tests) {
    while ($row = $res_all_tests->fetch_assoc()) {
        $all_tests[] = $row;
        $total_all_tests += $row['total'];
    }
}

$test_stats_text = "";
if (!empty($all_tests)) {
    foreach($all_tests as $t) {
        $test_stats_text .= "- " . $t['serviceName'] . ": " . $t['total'] . " tests\n";
    }
} else {
    $test_stats_text = "No tests recorded.";
}

$prompt = "Act as a professional Laboratory Manager. Write a short, professional summary (max 3 sentences) for this month's laboratory operations based on the following data:\n" .
    "Total Patients: " . number_format($total_patients) . "\n" .
    "Average Turnaround Time: $avg_turnaround hours\n" .
    "Equipment Health: $equipment_health%\n" .
    "Test Statistics:\n$test_stats_text\n\n" .
    "Instructions: Make it professional, highlight key performance indicators, and mention any trends or areas of note. Do not use asterisks or formatting, just plain text.";

$ai_summary = getGeminiSummary($GEMINI_API_KEY, $prompt);

if (isset($_GET['ajax']) && $_GET['ajax'] == 1) {
    header('Content-Type: application/json');
    echo json_encode([
        'total_patients' => number_format($total_patients),
        'avg_turnaround' => $avg_turnaround,
        'equipment_health' => $equipment_health,
        'lineLabels' => $chart_dates,
        'lineData' => $chart_counts,
        'donutLabels' => $donut_labels,
        'donutData' => $donut_data,
        'all_tests' => $all_tests,
        'total_all_tests' => $total_all_tests,
        'month_name' => $month_name,
        'selected_year' => $selected_year,
        'ai_summary' => $ai_summary
    ]);
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HMS | Laboratory and Diagnostic Management</title>
    <link rel="shortcut icon" href="../assets/image/favicon.ico" type="image/x-icon">
    <link rel="stylesheet" href="../assets/CSS/bootstrap.min.css">
    <link rel="stylesheet" href="../assets/CSS/super.css">
    <style>
        .print-container {
            display: none;
            width: 100%; 
            background: white;
        }
        .print-header-banner {
            background-color: #1e5b86 !important; 
            color: #ffffff !important;
            padding: 25px 40px;
            font-size: 20px;
            font-weight: bold;
            text-transform: uppercase;
            margin-bottom: 20px;
            font-family: Arial, sans-serif;
        }
        .print-body { 
            padding: 0 40px; 
            font-family: Arial, sans-serif;
        }
        .print-info p { 
            margin: 0; 
            font-size: 15px; 
        }
        .print-info p strong {
            font-weight: bold;
        }
        .print-line { 
            border-top: 2px solid #a3b8c2; 
            margin: 20px 0; 
        }
        .print-section-title { 
            font-weight: bold; 
            font-size: 16px; 
            margin-bottom: 15px; 
            text-transform: uppercase; 
            color: #000;
        }
        .print-summary-text { 
            font-size: 14px; 
            line-height: 1.6; 
            margin-bottom: 20px; 
            text-align: justify; 
        }
        .print-table { 
            width: 100%; 
            border-collapse: collapse; 
            margin-bottom: 50px; 
            font-size: 14px; 
            color: #000;
        }
        .print-table th { 
            background-color: #ccebc5 !important; 
            padding: 10px 15px; 
            text-align: left; 
            font-weight: bold;
            color: #000;
            border-bottom: 1px solid #ccebc5;
        }
        .print-table td { 
            padding: 8px 15px; 
            border-bottom: 1px solid #ddd;
            color: #000;
        }
        .print-table tr:nth-child(even) td { 
            background-color: #f2f2f2 !important; 
        }
        .print-signatures { 
            display: flex; 
            justify-content: space-between; 
            margin-top: 100px;
            margin-bottom: 40px;
        }
        .signature-box { 
            width: 25%; 
            text-align: center; 
        }
        .signature-line { 
            border-top: 1px solid black; 
            margin-bottom: 5px; 
        }
        .signature-text { 
            font-size: 14px; 
            font-weight: bold; 
            color: #000;
        }

        @media print {
            @page {
                size: auto;
                margin: 0; 
            }
            body { 
                margin: 0; 
                padding: 0; 
                background-color: #ffffff; 
                -webkit-print-color-adjust: exact;
                color-adjust: exact;
                print-color-adjust: exact;
            }
            #sidebar, .topbar, .no-print { 
                display: none !important; 
            }
            .main { 
                margin: 0 !important; 
                padding: 0 !important; 
                width: 100% !important; 
                background-color: #ffffff !important; 
                overflow: visible !important;
            }
            .print-container { 
                display: block !important; 
            }
        }
    </style>
</head>

<body>
    <div class="d-flex">
        
        <aside id="sidebar" class="sidebar-toggle">
            <div class="sidebar-logo mt-3">
                <img src="../assets/image/logo-dark.png" width="90px" height="20px">
            </div>
            <div class="menu-title">Navigation</div>
            
            <li class="sidebar-item">
                <a href="../labtech_dashboard.php" class="sidebar-link" data-bs-toggle="#" data-bs-target="#"
                    aria-expanded="false" aria-controls="auth">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-cast" viewBox="0 0 16 16">
                        <path d="m7.646 9.354-3.792 3.792a.5.5 0 0 0 .353.854h7.586a.5.5 0 0 0 .354-.854L8.354 9.354a.5.5 0 0 0-.708 0" />
                        <path d="M11.414 11H14.5a.5.5 0 0 0 .5-.5v-7a.5.5 0 0 0-.5-.5h-13a.5.5 0 0 0-.5.5v7a.5.5 0 0 0 .5.5h3.086l-1 1H1.5A1.5 1.5 0 0 1 0 10.5v-7A1.5 1.5 0 0 1 1.5 2h13A1.5 1.5 0 0 1 16 3.5v7a1.5 1.5 0 0 1-1.5 1.5h-2.086z" />
                    </svg>
                    <span style="font-size: 18px;">Dashboard</span>
                </a>
            </li>
            <li class="sidebar-item">
                <a href="#" class="sidebar-link collapsed has-dropdown" data-bs-toggle="collapse" data-bs-target="#labtech"
                    aria-expanded="true" aria-controls="auth">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-building"
                        viewBox="0 0 16 16" style="margin-bottom: 7px;">
                        <path
                            d="M4 2.5a.5.5 0
             0 1 .5-.5h1a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5h-1a.5.5 0 0 1-.5-.5zm3 0a.5.5 0 0 1 .5-.5h1a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5h-1a.5.5 0 0 1-.5-.5zm3.5-.5a.5.5 0 0 0-.5.5v1a.5.5 0 0 0 .5.5h1a.5.5 0 0 0 .5-.5v-1a.5.5 0 0 0-.5-.5zM4 5.5a.5.5 0 0 1 .5-.5h1a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5h-1a.5.5 0 0 1-.5-.5zM7.5 5a.5.5 0 0 0-.5.5v1a.5.5 0 0 0 .5.5h1a.5.5 0 0 0 .5-.5v-1a.5.5 0 0 0-.5-.5zm2.5.5a.5.5 0 0 1 .5-.5h1a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5h-1a.5.5 0 0 1-.5-.5zM4.5 8a.5.5 0 0 0-.5.5v1a.5.5 0 0 0 .5.5h1a.5.5 0 0 0 .5-.5v-1a.5.5 0 0 0-.5-.5zm2.5.5a.5.5 0 0 1 .5-.5h1a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5h-1a.5.5 0 0 1-.5-.5zm3.5-.5a.5.5 0 0 0-.5.5v1a.5.5 0 0 0 .5.5h1a.5.5 0 0 0 .5-.5v-1a.5.5 0 0 0-.5-.5z" />
                        <path
                            d="M2 1a1 1 0 0 1 1-1h10a1 1 0 0 1 1 1v14a1 1 0 0 1-1 1H3a1 1 0 0 1-1-1zm11 0H3v14h3v-2.5a.5.5 0 0 1 .5-.5h3a.5.5 0 0 1 .5.5V15h3z" />
                    </svg>
                    <span style="font-size: 18px;">Test Booking and Scheduling</span>
                </a>

                <ul id="labtech" class="sidebar-dropdown list-unstyled collapse" data-bs-parent="#sidebar">
                    <li class="sidebar-item">
                        <a href="../mod1/doctor_referral.php" class="sidebar-link">Doctor Referral</a>
                    </li>
                    <li class="sidebar-item">
                        <a href="../mod1/cas.php" class="sidebar-link">Calendar & Appointment Slot</a>
                    </li>
                    <li class="sidebar-item">
                        <a href="../mod1/room_available.php" class="sidebar-link">Room Overview</a>
                    </li>
                </ul>
            </li>
            <li class="sidebar-item">
                <a href="#" class="sidebar-link collapsed has-dropdown" data-bs-toggle="collapse" data-bs-target="#sample"
                    aria-expanded="true" aria-controls="auth">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-collection" viewBox="0 0 16 16">
                        <path d="M2.5 3.5a.5.5 0 0 1 0-1h11a.5.5 0 0 1 0 1h-11zm2-2a.5.5 0 0 1 0-1h7a.5.5 0 0 1 0 1h-7zM0 13a1.5 1.5 0 0 0 1.5 1.5h13A1.5 1.5 0 0 0 16 13V6a1.5 1.5 0 0 0-1.5-1.5h-13A1.5 1.5 0 0 0 0 6v7zm1.5.5A.5.5 0 0 1 1 13V6a.5.5 0 0 1 .5-.5h13a.5.5 0 0 1 .5.5v7a.5.5 0 0 1-.5.5h-13z" />
                    </svg>
                    <span style="font-size: 18px;">Sample Collection & Tracking</span>
                </a>
                <ul id="sample" class="sidebar-dropdown list-unstyled collapse" data-bs-parent="#sidebar">
                    <li class="sidebar-item">
                        <a href="../mod2/test_process.php" class="sidebar-link">Sample Process</a>
                    </li>
                    <li class="sidebar-item">
                        <a href="../mod2/sps.php" class="sidebar-link">Sample Processing Status</a>
                    </li>
                    <li class="sidebar-item">
                        <a href="../mod2/audit.php" class="sidebar-link">Audit Trail</a>
                    </li>
                </ul>
            </li>
            <li class="sidebar-item">
                <a href="#" class="sidebar-link collapsed has-dropdown" data-bs-toggle="collapse" data-bs-target="#report"
                    aria-expanded="true" aria-controls="auth">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-file-earmark-text" viewBox="0 0 16 16">
                        <path d="M5.5 7a.5.5 0 0 0 0 1h5a.5.5 0 0 0 0-1h-5zM5 9.5a.5.5 0 0 1 .5-.5h5a.5.5 0 0 1 0 1h-5a.5.5 0 0 1-.5-.5zm0 2a.5.5 0 0 1 .5-.5h2a.5.5 0 0 1 0 1h-2a.5.5 0 0 1-.5-.5z" />
                        <path d="M9.5 0H4a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2V4.5L9.5 0zm0 1v2A1.5 1.5 0 0 0 11 4.5h2V14a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1h5.5z" />
                    </svg>
                    <span style="font-size: 18px;">Report Generation & Delivery</span>
                </a>
                <ul id="report" class="sidebar-dropdown list-unstyled collapse" data-bs-parent="#sidebar">
                    <li class="sidebar-item">
                        <a href="results.php" class="sidebar-link">Test Results</a>
                    </li>
                    <li class="sidebar-item">
                        <a href="result_deliveries.php" class="sidebar-link">Result Deliveries</a>
                    </li>
                    <li class="sidebar-item">
                        <a href="operation_report.php" class="sidebar-link">Laboratory Report</a>
                    </li>
                </ul>
            </li>
            <li class="sidebar-item">
                <a href="#" class="sidebar-link collapsed has-dropdown" data-bs-toggle="collapse" data-bs-target="#equipment"
                    aria-expanded="true" aria-controls="auth">
                     <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-tools" viewBox="0 0 640 640"><!--!Font Awesome Free v7.2.0 by @fontawesome - https://fontawesome.com License - https://fontawesome.com/license/free Copyright 2026 Fonticons, Inc.-->
                        <path d="M259.1 73.5C262.1 58.7 275.2 48 290.4 48L350.2 48C365.4 48 378.5 58.7 381.5 73.5L396 143.5C410.1 149.5 423.3 157.2 435.3 166.3L503.1 143.8C517.5 139 533.3 145 540.9 158.2L570.8 210C578.4 223.2 575.7 239.8 564.3 249.9L511 297.3C511.9 304.7 512.3 312.3 512.3 320C512.3 327.7 511.8 335.3 511 342.7L564.4 390.2C575.8 400.3 578.4 417 570.9 430.1L541 481.9C533.4 495 517.6 501.1 503.2 496.3L435.4 473.8C423.3 482.9 410.1 490.5 396.1 496.6L381.7 566.5C378.6 581.4 365.5 592 350.4 592L290.6 592C275.4 592 262.3 581.3 259.3 566.5L244.9 496.6C230.8 490.6 217.7 482.9 205.6 473.8L137.5 496.3C123.1 501.1 107.3 495.1 99.7 481.9L69.8 430.1C62.2 416.9 64.9 400.3 76.3 390.2L129.7 342.7C128.8 335.3 128.4 327.7 128.4 320C128.4 312.3 128.9 304.7 129.7 297.3L76.3 249.8C64.9 239.7 62.3 223 69.8 209.9L99.7 158.1C107.3 144.9 123.1 138.9 137.5 143.7L205.3 166.2C217.4 157.1 230.6 149.5 244.6 143.4L259.1 73.5zM320.3 400C364.5 399.8 400.2 363.9 400 319.7C399.8 275.5 363.9 239.8 319.7 240C275.5 240.2 239.8 276.1 240 320.3C240.2 364.5 276.1 400.2 320.3 400z"/>
                    </svg>
                    <span style="font-size: 18px;">Equipment Maintenance</span>
                </a>
                <ul id="equipment" class="sidebar-dropdown list-unstyled collapse" data-bs-parent="#sidebar">
                    <li class="sidebar-item">
                        <a href="../mod4/lab_equip.php" class="sidebar-link">Laboratory Equipment </a>
                    </li>
                    <li class="sidebar-item">
                        <a href="../mod4/maintenance.php" class="sidebar-link">Maintenance Schedule</a>
                    </li>
                </ul>
            </li>
            <li class="sidebar-item">
                <a href="../configuration_page/price.php" class="sidebar-link collapsed has-dropdown" data-bs-toggle="collapse" data-bs-target="#configuration"
                    aria-expanded="true" aria-controls="auth">
                     <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-tools" viewBox="0 0 640 640"><!--!Font Awesome Free v7.2.0 by @fontawesome - https://fontawesome.com License - https://fontawesome.com/license/free Copyright 2026 Fonticons, Inc.-->
                        <path d="M259.1 73.5C262.1 58.7 275.2 48 290.4 48L350.2 48C365.4 48 378.5 58.7 381.5 73.5L396 143.5C410.1 149.5 423.3 157.2 435.3 166.3L503.1 143.8C517.5 139 533.3 145 540.9 158.2L570.8 210C578.4 223.2 575.7 239.8 564.3 249.9L511 297.3C511.9 304.7 512.3 312.3 512.3 320C512.3 327.7 511.8 335.3 511 342.7L564.4 390.2C575.8 400.3 578.4 417 570.9 430.1L541 481.9C533.4 495 517.6 501.1 503.2 496.3L435.4 473.8C423.3 482.9 410.1 490.5 396.1 496.6L381.7 566.5C378.6 581.4 365.5 592 350.4 592L290.6 592C275.4 592 262.3 581.3 259.3 566.5L244.9 496.6C230.8 490.6 217.7 482.9 205.6 473.8L137.5 496.3C123.1 501.1 107.3 495.1 99.7 481.9L69.8 430.1C62.2 416.9 64.9 400.3 76.3 390.2L129.7 342.7C128.8 335.3 128.4 327.7 128.4 320C128.4 312.3 128.9 304.7 129.7 297.3L76.3 249.8C64.9 239.7 62.3 223 69.8 209.9L99.7 158.1C107.3 144.9 123.1 138.9 137.5 143.7L205.3 166.2C217.4 157.1 230.6 149.5 244.6 143.4L259.1 73.5zM320.3 400C364.5 399.8 400.2 363.9 400 319.7C399.8 275.5 363.9 239.8 319.7 240C275.5 240.2 239.8 276.1 240 320.3C240.2 364.5 276.1 400.2 320.3 400z"/>
                    </svg>
                    <span style="font-size: 18px;">Configuration</span>
                </a>
                <ul id="configuration" class="sidebar-dropdown list-unstyled collapse" data-bs-parent="#sidebar">
                    <li class="sidebar-item">
                        <a href="../configuration_page/price.php" class="sidebar-link">Laboratory Price Configuration</a>
                    </li>
                </ul>
            </li>
            <li class="sidebar-item">
                <a href="../repair_request.php" class="sidebar-link collapsed has-dropdown" data-bs-toggle="#" data-bs-target="#request_repair"
                    aria-expanded="true" aria-controls="auth">
                     <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-tools" viewBox="0 0 16 16">
                        <path d="M1 0 0 1l2.2 3.081a1 1 0 0 0 .815.419h.07a1 1 0 0 1 .708.293l2.675 2.675-2.617 2.654A3.003 3.003 0 0 0 0 13a3 3 0 1 0 5.878-.851l2.654-2.617.968.968-.305.914a1 1 0 0 0 .242 1.023l3.27 3.27a.997.997 0 0 0 1.414 0l1.586-1.586a.997.997 0 0 0 0-1.414l-3.27-3.27a1 1 0 0 0-1.023-.242L10.5 9.5l-.96-.96 2.68-2.643A3.005 3.005 0 0 0 16 3c0-.269-.035-.53-.102-.777l-2.14 2.141L12 4l-.364-1.757L13.777.102a3 3 0 0 0-3.675 3.68L7.462 6.46 4.793 3.793a1 1 0 0 1-.293-.707v-.071a1 1 0 0 0-.419-.814L1 0Zm9.646 10.646a.5.5 0 0 1 .708 0l2.914 2.915a.5.5 0 0 1-.707.707l-2.915-2.914a.5.5 0 0 1 0-.708ZM3 11l.471.242.529.026.287.445.445.287.026.529L5 13l-.242.471-.026.529-.445.287-.287.445-.529.026L3 15l-.471-.242L2 14.732l-.287-.445L1.268 14l-.026-.529L1 13l.242-.471.026-.529.445-.287.287-.445.529-.026L3 11Z" />
                    </svg>
                    <span style="font-size: 18px;">Request Repair</span>
                </a>
            </li>
        </aside>
        
        
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
                        <span class="username ml-1 me-2"><?php echo $user['fname']; ?> <?php echo $user['lname']; ?></span>
                        <button class="btn dropdown-toggle" type="button" id="dropdownMenuButton" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-person-circle"></i>
                        </button>
                        <ul class="dropdown-menu" aria-labelledby="dropdownMenuButton" style="min-width: 200px; padding: 10px; border-radius: 5px; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); background-color: #fff; color: #333;">
                            <li style="margin-bottom: 8px; font-size: 14px; color: #555;">
                                <span>Welcome <strong style="color: #007bff;"><?php echo $user['lname']; ?></strong>!</span>
                            </li>
                            <li>
                                <a class="dropdown-item" href="../../logout.php" style="font-size: 14px; color: #007bff; text-decoration: none; padding: 8px 12px; border-radius: 4px; transition: background-color 0.3s ease;">
                                    Logout
                                </a>
                            </li>
                        </ul>

                    </div>
                </div>
            </div>
            
            <div class="container-fluid p-4 no-print">

                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h3 style="font-family:Arial, sans-serif; color:#0d6efd; margin-bottom:20px; border-bottom:2px solid #0d6efd; padding-bottom:8px;"
                     class="mb-0 text-secondary">Monthly Laboratory Report</h3>
                    <div class="d-flex align-items-center">
                        <form id="filterForm" class="d-flex align-items-center me-3 mb-0" onsubmit="return false;">
                            <select id="filterMonth" class="form-select me-2" style="width: auto;">
                                
                            </select>
                            <select id="filterYear" class="form-select me-2" style="width: auto;">
                                
                            </select>
                        </form>
                        <button class="btn btn-primary px-4 py-2 shadow-sm" onclick="downloadPDF()">
                            <i class="fas fa-file-pdf me-2"></i> Download Report
                        </button>
                    </div>
                </div>

                <div class="row g-4 mb-4">
                    <div class="col-md-7">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-body">
                                <h5 class="card-title text-muted mb-4">Total Tests Performed</h5>
                                <canvas id="lineChart" style="max-height: 250px;"></canvas>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-5">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-body">
                                <h5 class="card-title text-muted mb-4">Test Breakdown</h5>
                                <div style="position: relative; height: 200px; display: flex; justify-content: center;">
                                    <canvas id="donutChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row g-4">
                    <div class="col-md-4">
                        <div class="card border-0 shadow-sm">
                            <div class="card-body d-flex align-items-center">
                                <div class="me-3 rounded" style="width: 8px; height: 40px; background-color: #0d6efd;"></div>
                                <div>
                                    <h6 class="text-muted mb-0">Total Patients</h6>
                                    <h4 id="valTotalPatients" class="mb-0 fw-bold"><?php echo number_format($total_patients); ?></h4>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="card border-0 shadow-sm">
                            <div class="card-body d-flex align-items-center">
                                <div class="me-3 rounded" style="width: 8px; height: 40px; background-color: #6ea8fe;"></div>
                                <div>
                                    <h6 class="text-muted mb-0">Avg Turnaround Time</h6>
                                    <h4 id="valAvgTurnaround" class="mb-0 fw-bold"><?php echo $avg_turnaround; ?> hrs</h4>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="card border-0 shadow-sm">
                            <div class="card-body d-flex align-items-center">

                                <div id="equipHealthColor" class="me-3 rounded" style="width: 8px; height: 40px; 
                background-color: <?php echo ($equipment_health < 50) ? '#dc3545' : (($equipment_health < 80) ? '#ffc107' : '#adb5bd'); ?>;">
                                </div>

                                <div>
                                    <h6 class="text-muted mb-0">Equipment Health</h6>
                                    <h4 id="valEquipHealth" class="mb-0 fw-bold"><?php echo $equipment_health; ?>%</h4>

                                    <small id="equipHealthWarning" class="text-danger" style="font-size: 12px; display: <?php echo ($equipment_health < 100) ? 'block' : 'none'; ?>;">Issues Detected</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>

            
            <div class="print-container">
                <div class="print-header-banner">
                    Diagnostic and Laboratory Services Report
                </div>
                <div class="print-body">
                    <div class="print-info">
                        <p><strong>Hospital:</strong> <?php echo "Dr. Eduardo V. Roquero Memorial Hospital"; ?></p>
                        <p><strong>Reporting period:</strong> <span id="valPrintPeriod"><?php echo $month_name . ' ' . $selected_year; ?></span></p>
                    </div>
                    
                    <div class="print-line"></div>
                    
                    <div class="print-section-title">SUMMARY</div>
                    <p class="print-summary-text" id="valPrintSummary">
                        <?php echo htmlspecialchars($ai_summary); ?>
                    </p>
                    
                    <div class="print-line"></div>
                    
                    <div class="print-section-title">TEST STATISTIC</div>
                    <table class="print-table">
                        <thead>
                            <tr>
                                <th>TEST NAME</th>
                                <th>TOTAL TESTS</th>
                                <th>PERCENTAGE (%)</th>
                            </tr>
                        </thead>
                        <tbody id="printTableBody">
                            <?php if (empty($all_tests)): ?>
                            <tr>
                                <td colspan="3" style="text-align:center;">No tests recorded in this period.</td>
                            </tr>
                            <?php else: ?>
                                <?php foreach ($all_tests as $test): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($test['serviceName']); ?></td>
                                    <td><?php echo number_format($test['total']); ?></td>
                                    <td>
                                        <?php 
                                        $pct = ($total_all_tests > 0) ? round(($test['total'] / $total_all_tests) * 100) : 0;
                                        echo $pct . '%'; 
                                        ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                    
                    <div class="print-signatures">
                        <div class="signature-box">
                            <div class="signature-line"></div>
                            <div class="signature-text">Prepared by</div>
                        </div>
                        <div class="signature-box">
                            <div class="signature-line"></div>
                            <div class="signature-text">Reviewed by</div>
                        </div>
                        <div class="signature-box">
                            <div class="signature-line"></div>
                            <div class="signature-text">Approved by</div>
                        </div>
                    </div>
                </div>
            </div>
            
        </div>
        
        <script>
            window.dashboardData = {
                lineLabels: <?php echo $json_dates; ?>,
                lineData: <?php echo $json_counts; ?>,
                donutLabels: <?php echo $json_donut_labels; ?>,
                donutData: <?php echo $json_donut_data; ?>
            };
            
            const selectedMonth = <?php echo $selected_month; ?>;
            const selectedYear = <?php echo $selected_year; ?>;
            const currentYear = new Date().getFullYear();
            const currentMonth = new Date().getMonth() + 1;
            
            const filterMonth = document.getElementById('filterMonth');
            const filterYear = document.getElementById('filterYear');

            
            for (let y = 2025; y <= currentYear; y++) {
                let opt = new Option(y, y);
                if (y == selectedYear) opt.selected = true;
                filterYear.add(opt);
            }

            
            function updateMonths() {
                const year = parseInt(filterYear.value);
                const maxMonth = (year === currentYear) ? currentMonth : 12;
                
                
                let currentVal = parseInt(filterMonth.value) || selectedMonth; 
                filterMonth.innerHTML = '';
                
                for (let m = 1; m <= maxMonth; m++) {
                    const d = new Date(2000, m - 1, 1);
                    const name = d.toLocaleString('en-US', { month: 'long' });
                    let opt = new Option(name, m);
                    if (m === currentVal) {
                        opt.selected = true;
                    }
                    filterMonth.add(opt);
                }
                
                
                if (parseInt(filterMonth.value) !== currentVal && currentVal > maxMonth) {
                    filterMonth.value = maxMonth;
                }
            }
            updateMonths();

            
            function fetchReportData() {
                const m = filterMonth.value;
                const y = filterYear.value;
                
                fetch(`operation_report.php?ajax=1&month=${m}&year=${y}`)
                .then(res => res.json())
                .then(data => {
                    
                    document.getElementById('valTotalPatients').innerText = data.total_patients;
                    document.getElementById('valAvgTurnaround').innerText = data.avg_turnaround + ' hrs';
                    document.getElementById('valEquipHealth').innerText = data.equipment_health + '%';
                    
                    document.getElementById('equipHealthWarning').style.display = (data.equipment_health < 100) ? 'block' : 'none';
                    let healthColor = (data.equipment_health < 50) ? '#dc3545' : ((data.equipment_health < 80) ? '#ffc107' : '#adb5bd');
                    document.getElementById('equipHealthColor').style.backgroundColor = healthColor;

                    
                    const tbody = document.getElementById('printTableBody');
                    tbody.innerHTML = '';
                    if (data.all_tests.length === 0) {
                        tbody.innerHTML = '<tr><td colspan="3" style="text-align:center;">No tests recorded in this period.</td></tr>';
                    } else {
                        data.all_tests.forEach(test => {
                            let pct = (data.total_all_tests > 0) ? Math.round((test.total / data.total_all_tests) * 100) : 0;
                            let tr = document.createElement('tr');
                            tr.innerHTML = `<td>${test.serviceName}</td><td>${test.total}</td><td>${pct}%</td>`;
                            tbody.appendChild(tr);
                        });
                    }

                    
                    document.getElementById('valPrintPeriod').innerText = `${data.month_name} ${data.selected_year}`;

                    if (data.ai_summary) {
                        document.getElementById('valPrintSummary').innerText = data.ai_summary;
                    }

                    
                    if (window.initCharts) {
                        window.initCharts(data);
                    }
                })
                .catch(err => console.error("Error fetching data: ", err));
            }

            filterYear.addEventListener('change', () => {
                updateMonths();
                fetchReportData();
            });
            filterMonth.addEventListener('change', fetchReportData);
            const toggler = document.querySelector(".toggler-btn");
            toggler.addEventListener("click", function() {
                document.querySelector("#sidebar").classList.toggle("collapsed");
            });

            function downloadPDF() {
                const element = document.querySelector('.print-container');
                element.style.display = 'block';
                
                var opt = {
                    margin:       [0.5, 0], 
                    filename:     'Lab_Report_<?php echo $month_name; ?>_<?php echo $selected_year; ?>.pdf',
                    image:        { type: 'jpeg', quality: 0.98 },
                    html2canvas:  { scale: 2 },
                    jsPDF:        { unit: 'in', format: 'letter', orientation: 'portrait' }
                };
                
                html2pdf().set(opt).from(element).save().then(() => {
                    element.style.display = 'none';
                });
            }
        </script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
        <script src="../assets/javascript/lab_report.js"></script>
        <script src="../assets/Bootstrap/all.min.js"></script>
        <script src="../assets/Bootstrap/bootstrap.bundle.min.js"></script>
        <script src="../assets/Bootstrap/fontawesome.min.js"></script>
        <script src="../assets/Bootstrap/jq.js"></script>
</body>

</html>