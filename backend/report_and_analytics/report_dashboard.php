<?php
$user = [
    'fname' => 'Test',
    'lname' => 'User'
];
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="shortcut icon" href="assets/image/favicon.ico" type="image/x-icon">
    <link rel="stylesheet" href="assets/CSS/bootstrap.min.css">
    <link rel="stylesheet" href="assets/CSS/super.css">
    <style>
        .report-card {
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            border-radius: 12px;
        }

        .report-card:hover {
            transform: translateY(-6px);
            box-shadow: 0 6px 18px rgba(0, 0, 0, 0.2);
        }

        .card-title {
            font-size: 18px;
            font-weight: 600;
        }

        .grid-container {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 20px;
        }

        body {
            background: #f8f9fa;
        }
    </style>
</head>

<body>
    <div class="d-flex">
        <!-- Sidebar -->
        <aside id="sidebar" class="sidebar-toggle">
            <div class="sidebar-logo mt-3">
                <img src="assets/image/logo-dark.png" width="90px" height="20px">
            </div>

            <div class="menu-title">Navigation</div>

            <li class="sidebar-item">
                <a href="report_dashboard.php" class="sidebar-link" data-bs-toggle="#" data-bs-target="#"
                    aria-expanded="false" aria-controls="auth">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-cast" viewBox="0 0 16 16">
                        <path d="m7.646 9.354-3.792 3.792a.5.5 0 0 0 .353.854h7.586a.5.5 0 0 0 .354-.854L8.354 9.354a.5.5 0 0 0-.708 0" />
                        <path d="M11.414 11H14.5a.5.5 0 0 0 .5-.5v-7a.5.5 0 0 0-.5-.5h-13a.5.5 0 0 0-.5.5v7a.5.5 0 0 0 .5.5h3.086l-1 1H1.5A1.5 1.5 0 0 1 0 10.5v-7A1.5 1.5 0 0 1 1.5 2h13A1.5 1.5 0 0 1 16 3.5v7a1.5 1.5 0 0 1-1.5 1.5h-2.086z" />
                    </svg>
                    <span style="font-size: 18px;">Dashboard</span>
                </a>
            </li>

            <li class="sidebar-item">
                <a href="#" class="sidebar-link collapsed has-dropdown" data-bs-toggle="collapse" data-bs-target="#staffMgmt"
                    aria-expanded="true" aria-controls="staffMgmt">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-person-vcard"
                        viewBox="0 0 16 16" style="margin-bottom: 6px;">
                        <path
                            d="M5 8a2 2 0 1 0 0-4 2 2 0 0 0 0 4m4-2.5a.5.5 0 0 1 .5-.5h4a.5.5 0 0 1 0 1h-4a.5.5 0 0 1-.5-.5M9 8a.5.5 0 0 1 .5-.5h4a.5.5 0 0 1 0 1h-4A.5.5 0 0 1 9 8m1 2.5a.5.5 0 0 1 .5-.5h3a.5.5 0 0 1 0 1h-3a.5.5 0 0 1-.5-.5" />
                        <path
                            d="M2 2a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V4a2 2 0 0 0-2-2zM1 4a1 1 0 0 1 1-1h12a1 1 0 0 1 1 1v8a1 1 0 0 1-1 1H8.96q.04-.245.04-.5C9 10.567 7.21 9 5 9c-2.086 0-3.8 1.398-3.984 3.181A1 1 0 0 1 1 12z" />
                    </svg>
                    <span style="font-size: 18px;">Doctor and Nurse Management</span>
                </a>

                <ul id="staffMgmt" class="sidebar-dropdown list-unstyled collapse" data-bs-parent="#sidebar">
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

            <li class="sidebar-item active">
                <a href="report_dashboard.php" class="sidebar-link">
                    Reporting & Analytics
                </a>
            </li>
        </aside>

        <!-- Main Content -->
        <div class="main">


            <div class="container my-5">
                <h4 class="mb-4 text-center">Select a Report</h4>
                <div class="grid-container">
                    <a href="annualPayroll_Report.php" class="text-decoration-none text-dark">
                        <div class="card report-card shadow-sm p-4 text-center">
                            <div class="mb-3"><i class="bi bi-cash-stack" style="font-size:40px; color:#007bff;"></i></div>
                            <h5 class="card-title">Annual Payroll Report</h5>
                        </div>
                    </a>

                    <a href="daily_attendance_report.php" class="text-decoration-none text-dark">
                        <div class="card report-card shadow-sm p-4 text-center">
                            <div class="mb-3"><i class="bi bi-calendar-check" style="font-size:40px; color:#28a745;"></i></div>
                            <h5 class="card-title">Daily Attendance Report</h5>
                        </div>
                    </a>

                    <a href="hospital_income_report.php" class="text-decoration-none text-dark">
                        <div class="card report-card shadow-sm p-4 text-center">
                            <div class="mb-3"><i class="bi bi-hospital" style="font-size:40px; color:#17a2b8;"></i></div>
                            <h5 class="card-title">Hospital Income Report</h5>
                        </div>
                    </a>

                    <a href="month_insurance_claim_report.php" class="text-decoration-none text-dark">
                        <div class="card report-card shadow-sm p-4 text-center">
                            <div class="mb-3"><i class="bi bi-file-earmark-medical" style="font-size:40px; color:#ffc107;"></i></div>
                            <h5 class="card-title">Insurance Claim Report</h5>
                        </div>
                    </a>

                    <a href="paycycle_report.php" class="text-decoration-none text-dark">
                        <div class="card report-card shadow-sm p-4 text-center">
                            <div class="mb-3"><i class="bi bi-clock-history" style="font-size:40px; color:#6f42c1;"></i></div>
                            <h5 class="card-title">Paycycle Report</h5>
                        </div>
                    </a>

                    <a href="revenue_report.php" class="text-decoration-none text-dark">
                        <div class="card report-card shadow-sm p-4 text-center">
                            <div class="mb-3"><i class="bi bi-bar-chart-line" style="font-size:40px; color:#20c997;"></i></div>
                            <h5 class="card-title">Revenue Report</h5>
                        </div>
                    </a>

                    <a href="salary_paid_report.php" class="text-decoration-none text-dark">
                        <div class="card report-card shadow-sm p-4 text-center">
                            <div class="mb-3"><i class="bi bi-wallet2" style="font-size:40px; color:#fd7e14;"></i></div>
                            <h5 class="card-title">Salary Paid Report</h5>
                        </div>
                    </a>

                    <a href="shift_and_duty.php" class="text-decoration-none text-dark">
                        <div class="card report-card shadow-sm p-4 text-center">
                            <div class="mb-3"><i class="bi bi-people" style="font-size:40px; color:#dc3545;"></i></div>
                            <h5 class="card-title">Shift & Duty Report</h5>
                        </div>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script src="assets/Bootstrap/bootstrap.bundle.min.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
</body>

</html>