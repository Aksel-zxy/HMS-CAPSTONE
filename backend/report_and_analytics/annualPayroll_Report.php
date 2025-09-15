<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Payroll Annual Summary Report</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;800&display=swap" rel="stylesheet" />
    <link rel="shortcut icon" href="assets/image/favicon.ico" type="image/x-icon">
    <link rel="stylesheet" href="assets/CSS/bootstrap.min.css">
    <link rel="stylesheet" href="assets/CSS/super.css">
    <style>
        :root {
            --ink: #0f172a;
            --text: #1f2937;
            --muted: #6b7280;
            --line: #e5e7eb;
            --brand: #0ea5a6;
            --brand-700: #0b7e7f;
            --brand-50: #e6fbfb;
            --panel: #ffffff;
            --sidebar: #113437;
            --bg: #f7f7fb;
        }

        * {
            box-sizing: border-box
        }

        html,
        body {
            height: 100%
        }


        body {
            margin: 0;
            background: var(--bg);
            color: var(--text);
            font: 14px/1.4 Inter, system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial, sans-serif;
            display: flex;
            justify-content: center;
            /* Center horizontally */
            align-items: flex-start;
            /* Keep content from going too high */
            padding-left: 270px;
            /* Offset for sidebar */
        }


        /* Sidebar fix */
        .sidebar-toggle {
            width: 250px;
            /* fixed width */
            background: var(--sidebar);
            min-height: 100vh;
            position: fixed;
            top: 0;
            left: 0;
            padding: 20px;
            color: #fff;
        }

        .page {
            width: 100%;
            max-width: 1000px;
            margin: 32px auto;
            /* Center inside remaining width */
            background: var(--panel);
            box-shadow: 0 10px 30px rgba(0, 0, 0, .06);
            border-radius: 14px;
            overflow: hidden;
            display: block;
        }

        .header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            padding: 28px 28px 10px 28px
        }

        .title {
            font-weight: 800;
            color: var(--ink);
            letter-spacing: .3px
        }

        .title span {
            display: block;
            font-size: 12px;
            font-weight: 600;
            color: var(--muted);
            margin-top: 6px
        }

        .year {
            font-weight: 800;
            color: var(--brand-700);
            font-size: 24px
        }

        .meta {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 14px;
            padding: 0 28px 24px 28px
        }

        .kpi {
            border: 1px solid var(--line);
            border-radius: 12px;
            padding: 14px;
            background: #fff
        }

        .kpi h4 {
            margin: 0 0 6px 0;
            font-weight: 600;
            color: var(--muted);
            font-size: 12px;
            letter-spacing: .3px
        }

        .kpi p {
            margin: 0;
            font-weight: 700;
            color: var(--ink)
        }

        .table-wrap {
            padding: 0 28px 28px
        }

        table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            border: 1px solid var(--line);
            border-radius: 12px;
            overflow: hidden
        }

        thead th {
            background: var(--brand-50);
            color: #0b4243;
            font-weight: 700;
            text-align: left;
            padding: 12px 14px;
            font-size: 13px;
            border-bottom: 1px solid var(--line)
        }

        tbody td {
            padding: 11px 14px;
            border-bottom: 1px solid var(--line);
            font-size: 13px
        }

        tbody tr:nth-child(even) {
            background: #fafafa
        }

        tfoot td {
            padding: 12px 14px;
            font-weight: 800;
            color: #0b4243;
            background: #111827;
            color: #fff
        }

        tfoot td:last-child {
            font-weight: 800
        }

        .badge {
            display: inline-block;
            padding: .2rem .5rem;
            border-radius: 999px;
            background: var(--brand-50);
            color: var(--brand-700);
            font-weight: 700;
            font-size: 12px
        }

        @media (max-width:900px) {
            .meta {
                grid-template-columns: repeat(2, 1fr)
            }

            .page {
                margin-left: 0;
                /* hide sidebar offset on small screens */
            }

            .sidebar-toggle {
                position: relative;
                width: 100%;
                min-height: auto;
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
                    </li>
                </ul>
            </li>
        </aside>

        <!-- Main Page -->
        <div class="page">
            <header class="header">
                <div>
                    <h1 class="title">PAYROLL ANNUAL SUMMARY REPORT
                    </h1>
                </div>
            </header>

            <section class="meta">
                <div class="kpi">
                    <h4>Total Hours Worked</h4>
                    <p>1820h</p>
                </div>
                <div class="kpi">
                    <h4>Total Overtime Hours Worked</h4>
                    <p>387h</p>
                </div>
                <div class="kpi">
                    <h4>Total Wage</h4>
                    <p>$13,242</p>
                </div>
            </section>

            <section class="table-wrap">
                <table role="table" aria-label="Payroll annual summary">
                    <thead>
                        <tr>
                            <th>Months</th>
                            <th>Overtime Hours</th>
                            <th>Total Worked Hours</th>
                            <th style="text-align:right">Total Wage</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>January</td>
                            <td>9h 35m</td>
                            <td>168h 58m</td>
                            <td style="text-align:right">$910</td>
                        </tr>
                        <tr>
                            <td>February</td>
                            <td>2h 20m</td>
                            <td>157h 40m</td>
                            <td style="text-align:right">$901</td>
                        </tr>
                        <tr>
                            <td>March</td>
                            <td>4h</td>
                            <td>164h</td>
                            <td style="text-align:right">$965</td>
                        </tr>
                        <tr>
                            <td>April</td>
                            <td>10h</td>
                            <td>169h 9m</td>
                            <td style="text-align:right">$1,018</td>
                        </tr>
                        <tr>
                            <td>May</td>
                            <td>11h</td>
                            <td>153h 49m</td>
                            <td style="text-align:right">$908</td>
                        </tr>
                        <tr>
                            <td>June</td>
                            <td>11h 40m</td>
                            <td>158h 58m</td>
                            <td style="text-align:right">$953</td>
                        </tr>
                        <tr>
                            <td>July</td>
                            <td>5h</td>
                            <td>165h</td>
                            <td style="text-align:right">$958</td>
                        </tr>
                        <tr>
                            <td>August</td>
                            <td>5h</td>
                            <td>145h 55m</td>
                            <td style="text-align:right">$872</td>
                        </tr>
                        <tr>
                            <td>September</td>
                            <td>4h</td>
                            <td>162h 25m</td>
                            <td style="text-align:right">$907</td>
                        </tr>
                        <tr>
                            <td>October</td>
                            <td>12h 10m</td>
                            <td>157h 34m</td>
                            <td style="text-align:right">$945</td>
                        </tr>
                        <tr>
                            <td>November</td>
                            <td>7h 25m</td>
                            <td>167h 25m</td>
                            <td style="text-align:right">$1,002</td>
                        </tr>
                        <tr>
                            <td>December</td>
                            <td>7h 25m</td>
                            <td>167h 25m</td>
                            <td style="text-align:right">$1,083</td>
                        </tr>
                    </tbody>
                </table>
            </section>
        </div>
</body>

</html>