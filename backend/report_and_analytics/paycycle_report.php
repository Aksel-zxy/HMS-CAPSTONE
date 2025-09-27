<?php
// Step 1: Get all employees
$apiUrl = "http://host.docker.internal:5288/Hr/getEmployee";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $apiUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Accept: application/json'
]);
$response = curl_exec($ch);

if (curl_errno($ch)) {
    die("cURL Error (employees): " . curl_error($ch));
}
curl_close($ch);

$employees = json_decode($response, true);

// Step 2: Get selected employee ID from query string with validation - USING employee_id
$employeeId = isset($_GET['employee_id']) ? intval($_GET['employee_id']) : null;

// If no employee is selected, show only the employee selection page
if (!$employeeId) {
?>
    <!DOCTYPE html>
    <html lang="en">

    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>HMS | Select Employee</title>
        <link rel="shortcut icon" href="assets/image/favicon.ico" type="image/x-icon">
        <link rel="stylesheet" href="assets/CSS/bootstrap.min.css">
        <link rel="stylesheet" href="assets/CSS/super.css">
        <style>
            :root {
                --bg: #f5f7fb;
                --card: #ffffff;
                --ink: #1c1f2b;
                --muted: #6b7280;
                --accent: #0b74ff;
                --accent-dark: #0757c8;
                --line: #e5e7eb;
            }

            body {
                margin: 0;
                background: var(--bg);
                color: var(--ink);
                font-family: system-ui, -apple-system, Segoe UI, Roboto, Inter, Helvetica, Arial, sans-serif;
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
            }

            .container {
                max-width: 500px;
                width: 90%;
            }

            .card {
                background: white;
                border-radius: 14px;
                box-shadow: 0 6px 18px rgba(0, 0, 0, .06);
                overflow: hidden;
                border: 1px solid #eef2ff;
                padding: 40px;
                text-align: center;
            }

            .title {
                font-size: 28px;
                font-weight: 800;
                margin-bottom: 30px;
                color: var(--accent);
            }

            .form-group {
                margin-bottom: 25px;
                text-align: left;
            }

            .form-group label {
                display: block;
                margin-bottom: 8px;
                font-weight: 600;
                font-size: 16px;
            }

            .form-group select {
                padding: 12px;
                border: 2px solid var(--line);
                border-radius: 8px;
                font-size: 16px;
                width: 100%;
                transition: border-color 0.3s;
            }

            .form-group select:focus {
                outline: none;
                border-color: var(--accent);
            }

            .btn {
                background: var(--accent);
                color: white;
                border: none;
                padding: 12px 30px;
                border-radius: 8px;
                font-size: 16px;
                font-weight: 600;
                cursor: pointer;
                transition: background 0.3s;
                width: 100%;
            }

            .btn:hover {
                background: var(--accent-dark);
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
                <div class="menu-title">Navigation</div>

                <!----- Sidebar Navigation ----->
                <li class="sidebar-item">
                    <a href="admin_dashboard.php" class="sidebar-link" data-bs-toggle="#" data-bs-target="#"
                        aria-expanded="false" aria-controls="auth">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-cast" viewBox="0 0 16 16">
                            <path d="m7.646 9.354-3.792 3.792a.5.5 0 0 0 .353.854h7.586a.5.5 0 0 0 .354-.854L8.354 9.354a.5.5 0 0 0-.708 0" />
                            <path d="M11.414 11H14.5a.5.5 0 0 0 .5-.5v-7a.5.5 0 0 0-.5-.5h-13a.5.5 0 0 0-.5.5v7a.5.5 0 0 0 .5.5h3.086l-1 1H1.5A1.5 1.5 0 0 1 0 10.5v-7A1.5 1.5 0 0 1 1.5 2h13A1.5 1.5 0 0 1 16 3.5v7a1.5 1.5 0 0 1-1.5 1.5h-2.086z" />
                        </svg>
                        <span style="font-size: 18px;">Dashboard</span>
                    </a>
                </li>

                <li class="sidebar-item">
                    <a href="#" class="sidebar-link collapsed has-dropdown" data-bs-toggle="collapse" data-bs-target="#gerald"
                        aria-expanded="true" aria-controls="auth">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-person-vcard"
                            viewBox="0 0 16 16" style="margin-bottom: 6px;">
                            <path
                                d="M5 8a2 2 0 1 0 0-4 2 2 0 0 0 0 4m4-2.5a.5.5 0 0 1 .5-.5h4a.5.5 0 0 1 0 1h-4a.5.5 0 0 1-.5-.5M9 8a.5.5 0 0 1 .5-.5h4a.5.5 0 0 1 0 1h-4A.5.5 0 0 1 9 8m1 2.5a.5.5 0 0 1 .5-.5h3a.5.5 0 0 1 0 1h-3a.5.5 0 0 1-.5-.5" />
                            <path
                                d="M2 2a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V4a2 2 0 0 0-2-2zM1 4a1 1 0 0 1 1-1h12a1 1 0 0 1 1 1v8a1 1 0 0 1-1 1H8.96q.04-.245.04-.5C9 10.567 7.21 9 5 9c-2.086 0-3.8 1.398-3.984 3.181A1 1 0 0 1 1 12z" />
                        </svg>
                        <span style="font-size: 18px;">Doctor and Nurse Management</span>
                    </a>

                    <ul id="gerald" class="sidebar-dropdown list-unstyled collapse" data-bs-parent="#sidebar">
                        <li class="sidebar-item">
                            <a href="../Employee/doctor.php" class="sidebar-link">Doctors</a>
                        </li>
                        <li class="sidebar-item">
                            <a href="../Employee/nurse.php" class="sidebar-link">Nurses</a>
                        </li>
                        <li class="sidebar-item">
                            <a href="../Employee/admin.php" class="sidebar-link">Other Staff</a>
                        </li>
                    </ul>
                </li>
                <li class="sidebar-item">
                    <a href="report_dashboard.php" class="sidebar-link collapsed has-dropdown" data-bs-toggle="collapse" data-bs-target="#gerald"
                        aria-expanded="true" aria-controls="auth">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-person-vcard"
                            viewBox="0 0 16 16" style="margin-bottom: 6px;">
                            <path
                                d="M5 8a2 2 0 1 0 0-4 2 2 0 0 0 0 4m4-2.5a.5.5 0 0 1 .5-.5h4a.5.5 0 0 1 0 1h-4a.5.5 0 0 1-.5-.5M9 8a.5.5 0 0 1 .5-.5h4a.5.5 0 0 1 0 1h-4A.5.5 0 0 1 9 8m1 2.5a.5.5 0 0 1 .5-.5h3a.5.5 0 0 1 0 1h-3a.5.5 0 0 1-.5-.5" />
                            <path
                                d="M2 2a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V4a2 2 0 0 0-2-2zM1 4a1 1 0 0 1 1-1h12a1 1 0 0 1 1 1v8a1 1 0 0 1-1 1H8.96q.04-.245.04-.5C9 10.567 7.21 9 5 9c-2.086 0-3.8 1.398-3.984 3.181A1 1 0 0 1 1 12z" />
                        </svg>
                        <span style="font-size: 18px;">Reporting and Analytics</span>
                    </a>

                    <ul id="gerald" class="sidebar-dropdown list-unstyled collapse" data-bs-parent="#sidebar">
                        <li class="sidebar-item">
                            <a href="paycycle_report.php" class="sidebar-link">Employee Paycycle Report</a>
                            <a href="annualPayroll_Report.php" class="sidebar-link">Employee Annual Payroll Report</a>
                            <a href="hospital_income_report.php" class="sidebar-link">Hospital month income statement</a>
                        </li>
                    </ul>
                </li>
            </aside>
        </div>
        <div class="container">
            <div class="card">
                <div class="title">Select Employee</div>
                <form method="GET" id="employeeForm">
                    <div class="form-group">
                        <label for="employee_id">Choose Employee:</label>
                        <select name="employee_id" id="employee_id" required>
                            <option value="">-- Select an Employee --</option>
                            <?php foreach ($employees as $employee): ?>
                                <?php
                                $empId = $employee['employee_id'] ?? '';   // or employeeID if that's what API returns
                                $empName = $employee['employeeName'] ?? ('Employee ' . $empId);
                                ?>
                                <option value="<?php echo htmlspecialchars((string)$empId); ?>">
                                    <?php echo htmlspecialchars((string)$empName); ?>
                                </option>
                            <?php endforeach; ?>


                        </select>
                    </div>
                    <button type="submit" class="btn">View Payroll Report</button>
                </form>
            </div>
        </div>

    </body>
    </div>

    </html>
<?php
    exit;
}

// Step 3: Get available pay periods for selected employee with URL encoding
$apiUrl = "http://host.docker.internal:5288/Hr/getPayperiodStartDates/" . urlencode($employeeId);

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $apiUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Accept: application/json'
]);
$response = curl_exec($ch);

if (curl_errno($ch)) {
    die("cURL Error (pay periods): " . curl_error($ch) . " - URL: " . $apiUrl);
}
curl_close($ch);

$payPeriods = json_decode($response, true);

// Validate pay periods data
if (!is_array($payPeriods)) {
    die("Invalid pay periods data received from API.");
}

// Step 4: Pick pay period (from query string or default to first) with validation
$selectedPayPeriod = null;
if (isset($_GET['payPeriodStartDate']) && !empty($_GET['payPeriodStartDate'])) {
    $selectedPayPeriod = $_GET['payPeriodStartDate'];
} elseif (!empty($payPeriods)) {
    $selectedPayPeriod = $payPeriods[0];
}

if (!$selectedPayPeriod) {
    die("No pay period available for selected employee.");
}

// Step 5: Build API URL for payroll info with proper encoding
$apiUrl = "http://host.docker.internal:5288/Hr/getPayrollInformation/" .
    urlencode($employeeId) . "/" .
    urlencode($selectedPayPeriod);

// Step 6: Fetch payroll information with cURL
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $apiUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Accept: application/json'
]);
$response = curl_exec($ch);

if (curl_errno($ch)) {
    die("cURL Error (payroll info): " . curl_error($ch) . " - URL: " . $apiUrl);
}
curl_close($ch);

// Step 7: Decode JSON response
$payrollData = json_decode($response, true);
if ($payrollData === null) {
    die("Error decoding JSON. Response: " . $response);
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HMS | Report and Analytics</title>
    <link rel="shortcut icon" href="assets/image/favicon.ico" type="image/x-icon">
    <link rel="stylesheet" href="assets/CSS/bootstrap.min.css">
    <link rel="stylesheet" href="assets/CSS/super.css">

    <style>
        :root {
            --bg: #f5f7fb;
            --card: #ffffff;
            --ink: #1c1f2b;
            --muted: #6b7280;
            --accent: #0b74ff;
            --accent-dark: #0757c8;
            --line: #e5e7eb;
            --thead: #0b74ff;
            --thead-ink: #ffffff;
        }

        * {
            box-sizing: border-box
        }

        html,
        body {
            margin: 0;
            background: var(--bg);
            color: var(--ink);
            font-family: system-ui, -apple-system, Segoe UI, Roboto, Inter, Helvetica, Arial, sans-serif
        }

        .wrapper {
            max-width: 1100px;
            margin: 24px auto;
            padding: 0 16px 40px;
        }

        /* Header */
        .header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            margin-bottom: 18px;
        }

        .title {
            font-size: 28px;
            font-weight: 800;
            letter-spacing: .2px;
        }

        .badge {
            font-weight: 700;
            font-size: 18px;
            color: var(--accent);
            border: 2px solid var(--accent);
            padding: 6px 14px;
            border-radius: 999px;
            background: #fff;
        }

        /* Card */
        .card {
            background: var(--card);
            border-radius: 14px;
            box-shadow: 0 6px 18px rgba(0, 0, 0, .06);
            overflow: hidden;
            border: 1px solid #eef2ff;
        }

        .card+.card {
            margin-top: 18px;
        }

        .card-head {
            background: var(--thead);
            color: var(--thead-ink);
            padding: 10px 14px;
            font-weight: 800;
            letter-spacing: .3px;
            text-transform: uppercase;
            font-size: 13px;
        }

        /* Grid for first band */
        .band {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
            padding: 16px;
        }

        @media (max-width: 900px) {
            .band {
                grid-template-columns: 1fr;
            }
        }

        /* Small tables */
        .mini {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            border: 1px solid var(--line);
            border-radius: 10px;
            overflow: hidden;
        }

        .mini thead th {
            background: var(--thead);
            color: var(--thead-ink);
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: .3px;
            padding: 10px 12px;
            text-align: left;
        }

        .mini tbody td,
        .mini tbody th {
            padding: 12px;
            border-top: 1px solid var(--line);
            vertical-align: middle;
            font-size: 14px;
        }

        .mini tbody th {
            width: 42%;
            color: var(--muted);
            font-weight: 600;
            background: #fafbff;
        }

        .mini tfoot td {
            border-top: 1px solid var(--line);
            background: #fbfdff;
            font-weight: 700;
        }

        /* Summary block */
        .summary {
            padding: 16px;
        }

        .table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            overflow: hidden;
            border: 1px solid var(--line);
            border-radius: 10px;
        }

        .table thead th {
            background: var(--thead);
            color: var(--thead-ink);
            padding: 12px;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: .35px;
            text-align: left;
        }

        .table tbody td {
            padding: 12px;
            border-top: 1px solid var(--line);
            background: #fff;
            font-size: 14px;
        }

        .table tfoot td {
            padding: 12px;
            border-top: 2px solid var(--line);
            font-weight: 800;
            background: #f8fbff;
        }

        .num {
            text-align: right;
            white-space: nowrap;
        }

        /* Form styles */
        .form-group {
            margin-bottom: 20px;
            padding: 0 16px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
        }

        .form-group select {
            padding: 8px 12px;
            border: 1px solid var(--line);
            border-radius: 6px;
            font-size: 14px;
            width: 100%;
            max-width: 300px;
        }

        .back-btn {
            background: var(--muted);
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 6px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            margin-left: 16px;
        }

        .back-btn:hover {
            background: #5a6268;
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
            <div class="menu-title">Navigation</div>

            <!----- Sidebar Navigation ----->
            <li class="sidebar-item">
                <a href="admin_dashboard.php" class="sidebar-link" data-bs-toggle="#" data-bs-target="#"
                    aria-expanded="false" aria-controls="auth">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-cast" viewBox="0 0 16 16">
                        <path d="m7.646 9.354-3.792 3.792a.5.5 0 0 0 .353.854h7.586a.5.5 0 0 0 .354-.854L8.354 9.354a.5.5 0 0 0-.708 0" />
                        <path d="M11.414 11H14.5a.5.5 0 0 0 .5-.5v-7a.5.5 0 0 0-.5-.5h-13a.5.5 0 0 0-.5.5v7a.5.5 0 0 0 .5.5h3.086l-1 1H1.5A1.5 1.5 0 0 1 0 10.5v-7A1.5 1.5 0 0 1 1.5 2h13A1.5 1.5 0 0 1 16 3.5v7a1.5 1.5 0 0 1-1.5 1.5h-2.086z" />
                    </svg>
                    <span style="font-size: 18px;">Dashboard</span>
                </a>
            </li>

            <li class="sidebar-item">
                <a href="#" class="sidebar-link collapsed has-dropdown" data-bs-toggle="collapse" data-bs-target="#gerald"
                    aria-expanded="true" aria-controls="auth">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-person-vcard"
                        viewBox="0 0 16 16" style="margin-bottom: 6px;">
                        <path
                            d="M5 8a2 2 0 1 0 0-4 2 2 0 0 0 0 4m4-2.5a.5.5 0 0 1 .5-.5h4a.5.5 0 0 1 0 1h-4a.5.5 0 0 1-.5-.5M9 8a.5.5 0 0 1 .5-.5h4a.5.5 0 0 1 0 1h-4A.5.5 0 0 1 9 8m1 2.5a.5.5 0 0 1 .5-.5h3a.5.5 0 0 1 0 1h-3a.5.5 0 0 1-.5-.5" />
                        <path
                            d="M2 2a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V4a2 2 0 0 0-2-2zM1 4a1 1 0 0 1 1-1h12a1 1 0 0 1 1 1v8a1 1 0 0 1-1 1H8.96q.04-.245.04-.5C9 10.567 7.21 9 5 9c-2.086 0-3.8 1.398-3.984 3.181A1 1 0 0 1 1 12z" />
                    </svg>
                    <span style="font-size: 18px;">Doctor and Nurse Management</span>
                </a>

                <ul id="gerald" class="sidebar-dropdown list-unstyled collapse" data-bs-parent="#sidebar">
                    <li class="sidebar-item">
                        <a href="../Employee/doctor.php" class="sidebar-link">Doctors</a>
                    </li>
                    <li class="sidebar-item">
                        <a href="../Employee/nurse.php" class="sidebar-link">Nurses</a>
                    </li>
                    <li class="sidebar-item">
                        <a href="../Employee/admin.php" class="sidebar-link">Other Staff</a>
                    </li>
                </ul>
            </li>
            <li class="sidebar-item">
                <a href="report_dashboard.php" class="sidebar-link collapsed has-dropdown" data-bs-toggle="collapse" data-bs-target="#gerald"
                    aria-expanded="true" aria-controls="auth">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-person-vcard"
                        viewBox="0 0 16 16" style="margin-bottom: 6px;">
                        <path
                            d="M5 8a2 2 0 1 0 0-4 2 2 0 0 0 0 4m4-2.5a.5.5 0 0 1 .5-.5h4a.5.5 0 0 1 0 1h-4a.5.5 0 0 1-.5-.5M9 8a.5.5 0 0 1 .5-.5h4a.5.5 0 0 1 0 1h-4A.5.5 0 0 1 9 8m1 2.5a.5.5 0 0 1 .5-.5h3a.5.5 0 0 1 0 1h-3a.5.5 0 0 1-.5-.5" />
                        <path
                            d="M2 2a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V4a2 2 0 0 0-2-2zM1 4a1 1 0 0 1 1-1h12a1 1 0 0 1 1 1v8a1 1 0 0 1-1 1H8.96q.04-.245.04-.5C9 10.567 7.21 9 5 9c-2.086 0-3.8 1.398-3.984 3.181A1 1 0 0 1 1 12z" />
                    </svg>
                    <span style="font-size: 18px;">Reporting and Analytics</span>
                </a>

                <ul id="gerald" class="sidebar-dropdown list-unstyled collapse" data-bs-parent="#sidebar">
                    <li class="sidebar-item">
                        <a href="paycycle_report.php" class="sidebar-link">Employee Paycycle Report</a>
                        <a href="annualPayroll_Report.php" class="sidebar-link">Employee Annual Payroll Report</a>
                        <a href="hospital_income_report.php" class="sidebar-link">Hospital month income statement</a>
                    </li>
                </ul>
            </li>
        </aside>
        <!----- End of Sidebar ----->

        <div class="wrapper">
            <div class="header">
                <div class="title">Payroll Statement</div>
                <a href="paycycle_report.php" class="back-btn">← Select Different Employee</a>
            </div>
            <div class="content" style="padding-bottom: 30px;">
                <form method="GET" id="payPeriodForm">
                    <!-- Changed to employee_id -->
                    <input type="hidden" name="employee_id" value="<?php echo $employeeId; ?>">
                    <div class="form-group">
                        <label for="payPeriodStartDate">Select Pay Period:</label>
                        <select name="payPeriodStartDate" id="payPeriodStartDate" onchange="document.getElementById('payPeriodForm').submit();">
                            <?php if (!empty($payPeriods)): ?>
                                <?php foreach ($payPeriods as $period): ?>
                                    <option value="<?php echo $period; ?>"
                                        <?php echo ($selectedPayPeriod == $period) ? 'selected' : ''; ?>>
                                        <?php echo date("F d, Y", strtotime($period)); ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <option value="">No pay periods available</option>
                            <?php endif; ?>
                        </select>
                    </div>
                </form>
            </div>

            <!-- Employee Info + Current Earnings -->
            <div class="card">
                <div class="band">
                    <!-- Employee / Period Info -->
                    <table class="mini" aria-label="Employee and Period Information">
                        <tbody>
                            <tr>
                                <th scope="row">Employee Name</th>
                                <td><?php echo htmlspecialchars($payrollData['employeeName']); ?></td>
                            </tr>
                            <tr>
                                <th scope="row">Employee ID</th>
                                <td><?php echo htmlspecialchars($employeeId); ?></td>
                            </tr>
                            <tr>
                                <th scope="row">Pay Period Start Date</th>
                                <td><?php echo date("m/d/Y", strtotime($payrollData['payPeriodStartDate'])); ?></td>
                            </tr>
                        </tbody>
                    </table>

                    <!-- Current Earnings -->
                    <table class="mini" aria-label="Current Earnings">
                        <thead>
                            <tr>
                                <th colspan="3">Current Earnings</th>
                            </tr>
                            <tr>
                                <th>Earning Type</th>
                                <th class="num">Hours</th>
                                <th class="num">Payment</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>Overtime</td>
                                <td class="num"> <?php echo number_format($payrollData['overtimeHours']); ?> </td>
                                <td class="num">$ <?php echo number_format($payrollData['overtimePay'], 2); ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Statement Summary -->
            <div class="card">
                <div class="card-head">Statement Summary</div>
                <div class="summary">
                    <table class="table" aria-label="Statement Summary">
                        <thead>
                            <tr>
                                <th>Earnings Type</th>
                                <th class="num">Current</th>
                                <th class="num">YTD</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>Gross Pay</td>
                                <td class="num">$ <?php echo number_format($payrollData['payCycleGrossPay'], 2); ?></td>
                                <td class="num">$ <?php echo number_format($payrollData['grossPay'], 2); ?></td>
                            </tr>
                            <tr>
                                <td>Deductions</td>
                                <td class="num">$ <?php echo number_format($payrollData['payCycleTotalDeductions'], 2); ?></td>
                                <td class="num">$ <?php echo number_format($payrollData['ytdTotalDeductions'], 2); ?></td>
                            </tr>
                            <tr>
                                <td>Net Pay</td>
                                <td class="num">$ <?php echo number_format($payrollData['payCycleNetpay'], 2); ?></td>
                                <td class="num">$ <?php echo number_format($payrollData['ytdNetPay'], 2); ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Deduction Details -->
            <div class="card">
                <div class="card-head">Deduction Details</div>
                <div class="summary">
                    <table class="table" aria-label="Deduction Details">
                        <thead>
                            <tr>
                                <th>Deduction Type</th>
                                <th>Notes</th>
                                <th class="num">Current</th>
                                <th class="num">YTD</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>SSS Deductions</td>
                                <td></td>
                                <td class="num">$ <?php echo number_format($payrollData['payCycleSssDeduction'], 2); ?></td>
                                <td class="num">$ <?php echo number_format($payrollData['ytdsssDeductions'], 2); ?></td>
                            </tr>
                            <tr>
                                <td>Phil Health Deductions</td>
                                <td></td>
                                <td class="num">$ <?php echo number_format($payrollData['payCyclePhilHealthDeduction'], 2); ?></td>
                                <td class="num">$ <?php echo number_format($payrollData['ytdphilHealthDeductions'], 2); ?></td>
                            </tr>
                            <tr>
                                <td>Pag-Ibig Deductions</td>
                                <td></td>
                                <td class="num">$ <?php echo number_format($payrollData['payCyclePhilHealthDeduction'], 2); ?></td>
                                <td class="num">$ <?php echo number_format($payrollData['ytdphilHealthDeductions'], 2); ?></td>
                            </tr>
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="2">Totals</td>
                                <td class="num">$ <?php echo number_format($payrollData['payCycleTotalDeductions'], 2); ?></td>
                                <td class="num">$ <?php echo number_format($payrollData['ytdTotalDeductions'], 2); ?></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    </div>
</body>

</html>