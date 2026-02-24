<?php
include 'header.php'
?>

<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title>Leave Report — Employee Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
        body {
            background: #f7f7f8;
            font-family: Inter, system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
        }

        .page-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin: 1.5rem 0;
        }

        .card-sm {
            border-radius: .6rem;
            box-shadow: 0 6px 16px rgba(16, 24, 40, 0.04);
        }

        .table thead th {
            background: #f1f1f1;
        }

        .page-item.disabled .page-link {
            pointer-events: none;
            opacity: 0.5;
        }
    </style>
</head>

<body>
    <div class="d-flex">
        <!----- Sidebar ----->
        <!----- Sidebar ----->
        <aside id="sidebar" class="sidebar-toggle">

            <div class="sidebar-logo mt-3">
                <img src="assets/image/logo-dark.png" width="90px" height="20px">
            </div>

            <div class="menu-title">Navigation</div>

            <!----- Sidebar Navigation ----->

            <li class="sidebar-item">
                <a href="superadmin_dashboard.php" class="sidebar-link" data-bs-toggle="#" data-bs-target="#"
                    aria-expanded="false" aria-controls="auth">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-cast" viewBox="0 0 16 16">
                        <path d="m7.646 9.354-3.792 3.792a.5.5 0 0 0 .353.854h7.586a.5.5 0 0 0 .354-.854L8.354 9.354a.5.5 0 0 0-.708 0" />
                        <path d="M11.414 11H14.5a.5.5 0 0 0 .5-.5v-7a.5.5 0 0 0-.5-.5h-13a.5.5 0 0 0-.5.5v7a.5.5 0 0 0 .5.5h3.086l-1 1H1.5A1.5 1.5 0 0 1 0 10.5v-7A1.5 1.5 0 0 1 1.5 2h13A1.5 1.5 0 0 1 16 3.5v7a1.5 1.5 0 0 1-1.5 1.5h-2.086z" />
                    </svg>
                    <span style="font-size: 18px;">Dashboard</span>
                </a>
            </li>

            <li class="sidebar-item">
                <a href="#" class="sidebar-link has-dropdown" data-bs-toggle="collapse" data-bs-target="#auth"
                    aria-expanded="false" aria-controls="auth">

                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor" class="bi bi-people"
                        viewBox="0 0 18 18" style="margin-bottom: 5px;">
                        <path
                            d="M15 14s1 0 1-1-1-4-5-4-5 3-5 4 1 1 1 1zm-7.978-1L7 12.996c.001-.264.167-1.03.76-1.72C8.312 10.629 9.282 10 11 10c1.717 0 2.687.63 3.24 1.276.593.69.758 1.457.76 1.72l-.008.002-.014.002zM11 7a2 2 0 1 0 0-4 2 2 0 0 0 0 4m3-2a3 3 0 1 1-6 0 3 3 0 0 1 6 0M6.936 9.28a6 6 0 0 0-1.23-.247A7 7 0 0 0 5 9c-4 0-5 3-5 4q0 1 1 1h4.216A2.24 2.24 0 0 1 5 13c0-1.01.377-2.042 1.09-2.904.243-.294.526-.569.846-.816M4.92 10A5.5 5.5 0 0 0 4 13H1c0-.26.164-1.03.76-1.724.545-.636 1.492-1.256 3.16-1.275ZM1.5 5.5a3 3 0 1 1 6 0 3 3 0 0 1-6 0" />
                    </svg>
                    <span style="font-size: 18px;">HR Management</span>
                </a>
                <ul id="auth" class="collapse list-unstyled">
                    <li class="sidebar-item">
                        <a href="../HR RECRUITMENT/Job Management/job_management.php" class="sidebar-link">Job Management</a>
                    </li>
                    <li class="sidebar-item">
                        <a href="../HR RECRUITMENT/Applicant Tracking/applicant_tracking.php" class="sidebar-link">Applicant Tracking</a>
                    </li>
                    <li class="sidebar-item">
                        <a href="../HR RECRUITMENT/Employee Onboarding/employee_onboarding.php" class="sidebar-link">Employee Onboarding</a>
                    </li>
                    <li class="sidebar-item">
                        <a href="../HR RECRUITMENT/Employee Registration/employee_registration.php" class="sidebar-link">Employee Registration</a>
                    </li>
                </ul>
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
                <a href="#" class="sidebar-link collapsed has-dropdown" data-bs-toggle="collapse" data-bs-target="#axl"
                    aria-expanded="true" aria-controls="auth">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-building"
                        viewBox="0 0 16 16" style="margin-bottom: 7px;">
                        <path
                            d="M4 2.5a.5.5 0
             0 1 .5-.5h1a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5h-1a.5.5 0 0 1-.5-.5zm3 0a.5.5 0 0 1 .5-.5h1a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5h-1a.5.5 0 0 1-.5-.5zm3.5-.5a.5.5 0 0 0-.5.5v1a.5.5 0 0 0 .5.5h1a.5.5 0 0 0 .5-.5v-1a.5.5 0 0 0-.5-.5zM4 5.5a.5.5 0 0 1 .5-.5h1a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5h-1a.5.5 0 0 1-.5-.5zM7.5 5a.5.5 0 0 0-.5.5v1a.5.5 0 0 0 .5.5h1a.5.5 0 0 0 .5-.5v-1a.5.5 0 0 0-.5-.5zm2.5.5a.5.5 0 0 1 .5-.5h1a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5h-1a.5.5 0 0 1-.5-.5zM4.5 8a.5.5 0 0 0-.5.5v1a.5.5 0 0 0 .5.5h1a.5.5 0 0 0 .5-.5v-1a.5.5 0 0 0-.5-.5zm2.5.5a.5.5 0 0 1 .5-.5h1a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5h-1a.5.5 0 0 1-.5-.5zm3.5-.5a.5.5 0 0 0-.5.5v1a.5.5 0 0 0 .5.5h1a.5.5 0 0 0 .5-.5v-1a.5.5 0 0 0-.5-.5z" />
                        <path
                            d="M2 1a1 1 0 0 1 1-1h10a1 1 0 0 1 1 1v14a1 1 0 0 1-1 1H3a1 1 0 0 1-1-1zm11 0H3v14h3v-2.5a.5.5 0 0 1 .5-.5h3a.5.5 0 0 1 .5.5V15h3z" />
                    </svg>
                    <span style="font-size: 18px;">Patient Management</span>
                </a>

                <ul id="axl" class="sidebar-dropdown list-unstyled collapse" data-bs-parent="#sidebar">
                    <li class="sidebar-item">
                        <a href="../HR/attendance.php" class="sidebar-link">Attendance</a>
                    </li>
                    <li class="sidebar-item">
                        <a href="../HR/leave.php" class="sidebar-link">Leave</a>
                    </li>
                    <li class="sidebar-item">
                        <a href="payroll.php" class="sidebar-link">Payroll</a>
                    </li>
                </ul>
            </li>

            <li class="sidebar-item">
                <a href="#" class="sidebar-link collapsed has-dropdown" data-bs-toggle="collapse" data-bs-target="#billing"
                    aria-expanded="true" aria-controls="auth">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-building"
                        viewBox="0 0 16 16" style="margin-bottom: 7px;">
                        <path
                            d="M4 2.5a.5.5 0
             0 1 .5-.5h1a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5h-1a.5.5 0 0 1-.5-.5zm3 0a.5.5 0 0 1 .5-.5h1a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5h-1a.5.5 0 0 1-.5-.5zm3.5-.5a.5.5 0 0 0-.5.5v1a.5.5 0 0 0 .5.5h1a.5.5 0 0 0 .5-.5v-1a.5.5 0 0 0-.5-.5zM4 5.5a.5.5 0 0 1 .5-.5h1a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5h-1a.5.5 0 0 1-.5-.5zM7.5 5a.5.5 0 0 0-.5.5v1a.5.5 0 0 0 .5.5h1a.5.5 0 0 0 .5-.5v-1a.5.5 0 0 0-.5-.5zm2.5.5a.5.5 0 0 1 .5-.5h1a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5h-1a.5.5 0 0 1-.5-.5zM4.5 8a.5.5 0 0 0-.5.5v1a.5.5 0 0 0 .5.5h1a.5.5 0 0 0 .5-.5v-1a.5.5 0 0 0-.5-.5zm2.5.5a.5.5 0 0 1 .5-.5h1a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5h-1a.5.5 0 0 1-.5-.5zm3.5-.5a.5.5 0 0 0-.5.5v1a.5.5 0 0 0 .5.5h1a.5.5 0 0 0 .5-.5v-1a.5.5 0 0 0-.5-.5z" />
                        <path
                            d="M2 1a1 1 0 0 1 1-1h10a1 1 0 0 1 1 1v14a1 1 0 0 1-1 1H3a1 1 0 0 1-1-1zm11 0H3v14h3v-2.5a.5.5 0 0 1 .5-.5h3a.5.5 0 0 1 .5.5V15h3z" />
                    </svg>
                    <span style="font-size: 18px;">Billing and Insurance Management</span>
                </a>

                <ul id="billing" class="sidebar-dropdown list-unstyled collapse" data-bs-parent="#sidebar">
                    <li class="sidebar-item">
                        <a href="../HR/attendance.php" class="sidebar-link">Attendance</a>
                    </li>
                    <li class="sidebar-item">
                        <a href="../HR/leave.php" class="sidebar-link">Leave</a>
                    </li>
                    <li class="sidebar-item">
                        <a href="payroll.php" class="sidebar-link">Payroll</a>
                    </li>
                </ul>
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
                    <span style="font-size: 18px;">Laboratory and Diagnostic Management</span>
                </a>

                <ul id="labtech" class="sidebar-dropdown list-unstyled collapse" data-bs-parent="#sidebar">
                    <li class="sidebar-item">
                        <a href="../HR/attendance.php" class="sidebar-link">Attendance</a>
                    </li>
                    <li class="sidebar-item">
                        <a href="../HR/leave.php" class="sidebar-link">Leave</a>
                    </li>
                    <li class="sidebar-item">
                        <a href="payroll.php" class="sidebar-link">Payroll</a>
                    </li>
                </ul>
            </li>

            <li class="sidebar-item">
                <a href="#" class="sidebar-link collapsed has-dropdown" data-bs-toggle="collapse" data-bs-target="#inventory"
                    aria-expanded="true" aria-controls="auth">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-building"
                        viewBox="0 0 16 16" style="margin-bottom: 7px;">
                        <path
                            d="M4 2.5a.5.5 0
             0 1 .5-.5h1a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5h-1a.5.5 0 0 1-.5-.5zm3 0a.5.5 0 0 1 .5-.5h1a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5h-1a.5.5 0 0 1-.5-.5zm3.5-.5a.5.5 0 0 0-.5.5v1a.5.5 0 0 0 .5.5h1a.5.5 0 0 0 .5-.5v-1a.5.5 0 0 0-.5-.5zM4 5.5a.5.5 0 0 1 .5-.5h1a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5h-1a.5.5 0 0 1-.5-.5zM7.5 5a.5.5 0 0 0-.5.5v1a.5.5 0 0 0 .5.5h1a.5.5 0 0 0 .5-.5v-1a.5.5 0 0 0-.5-.5zm2.5.5a.5.5 0 0 1 .5-.5h1a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5h-1a.5.5 0 0 1-.5-.5zM4.5 8a.5.5 0 0 0-.5.5v1a.5.5 0 0 0 .5.5h1a.5.5 0 0 0 .5-.5v-1a.5.5 0 0 0-.5-.5zm2.5.5a.5.5 0 0 1 .5-.5h1a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5h-1a.5.5 0 0 1-.5-.5zm3.5-.5a.5.5 0 0 0-.5.5v1a.5.5 0 0 0 .5.5h1a.5.5 0 0 0 .5-.5v-1a.5.5 0 0 0-.5-.5z" />
                        <path
                            d="M2 1a1 1 0 0 1 1-1h10a1 1 0 0 1 1 1v14a1 1 0 0 1-1 1H3a1 1 0 0 1-1-1zm11 0H3v14h3v-2.5a.5.5 0 0 1 .5-.5h3a.5.5 0 0 1 .5.5V15h3z" />
                    </svg>
                    <span style="font-size: 18px;">Inventory and Supply Chain Management</span>
                </a>

                <ul id="inventory" class="sidebar-dropdown list-unstyled collapse" data-bs-parent="#sidebar">
                    <li class="sidebar-item">
                        <a href="../HR/attendance.php" class="sidebar-link">Attendance</a>
                    </li>
                    <li class="sidebar-item">
                        <a href="../HR/leave.php" class="sidebar-link">Leave</a>
                    </li>
                    <li class="sidebar-item">
                        <a href="payroll.php" class="sidebar-link">Payroll</a>
                    </li>
                </ul>
            </li>

            <li class="sidebar-item">
                <a href="../report_and_analytics/report_dashboard.php" class="sidebar-link">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor"
                        class="bi bi-building" viewBox="0 0 16 16" style="margin-bottom: 7px;">
                        <path
                            d="M4 2.5a.5.5 0
                0 1 .5-.5h1a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5h-1a.5.5 0 0 1-.5-.5zm3 0a.5.5 0 0 1 .5-.5h1a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5h-1a.5.5 0 0 1-.5-.5zm3.5-.5a.5.5 0 0 0-.5.5v1a.5.5 0 0 0 .5.5h1a.5.5 0 0 0 .5-.5v-1a.5.5 0 0 0-.5-.5zM4 5.5a.5.5 0 0 1 .5-.5h1a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5h-1a.5.5 0 0 1-.5-.5zM7.5 5a.5.5 0 0 0-.5.5v1a.5.5 0 0 0 .5.5h1a.5.5 0 0 0 .5-.5v-1a.5.5 0 0 0-.5-.5zm2.5.5a.5.5 0 0 1 .5-.5h1a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5h-1a.5.5 0 0 1-.5-.5zM4.5 8a.5.5 0 0 0-.5.5v1a.5.5 0 0 0 .5.5h1a.5.5 0 0 0 .5-.5v-1a.5.5 0 0 0-.5-.5zm2.5.5a.5.5 0 0 1 .5-.5h1a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5h-1a.5.5 0 0 1-.5-.5zm3.5-.5a.5.5 0 0 0-.5.5v1a.5.5 0 0 0 .5.5h1a.5.5 0 0 0 .5-.5v-1a.5.5 0 0 0-.5-.5z" />
                        <path
                            d="M2 1a1 1 0 0 1 1-1h10a1 1 0 0 1 1 1v14a1 1 0 0 1-1 1H3a1 1 0 0 1-1-1zm11 0H3v14h3v-2.5a.5.5 0 0 1 .5-.5h3a.5.5 0 0 1 .5.5V15h3z" />
                    </svg>
                    <span style="font-size: 18px;">Report and Analytics</span>
                </a>
            </li>

        </aside>
        <div class="container-fluid py-4">
            <div class="page-head">
                <div>
                    <h3 class="mb-0">Leave Report</h3>
                    <div class="text-muted">Filter by month and year to view employee leave summaries</div>
                </div>
                <div class="d-flex gap-2">
                    <select id="monthSelect" class="form-select">
                        <option value="1">January</option>
                        <option value="2">February</option>
                        <option value="3">March</option>
                        <option value="4">April</option>
                        <option value="5">May</option>
                        <option value="6">June</option>
                        <option value="7">July</option>
                        <option value="8">August</option>
                        <option value="9">September</option>
                        <option value="10">October</option>
                        <option value="11">November</option>
                        <option value="12">December</option>
                    </select>

                    <select id="yearSelect" class="form-select">
                        <option>2023</option>
                        <option>2024</option>
                        <option selected>2025</option>
                        <option>2026</option>
                    </select>

                    <button class="btn btn-primary" onclick="reloadReports()">Load</button>
                </div>
            </div>

            <!-- Summary -->
            <div class="row g-3 mb-3">
                <div class="col-sm-3">
                    <div class="card card-sm p-3">
                        <small class="text-muted">Total Requests</small>
                        <h4 id="summaryTotal" class="mb-0">0</h4>
                    </div>
                </div>
                <div class="col-sm-3">
                    <div class="card card-sm p-3">
                        <small class="text-muted">Approved</small>
                        <h4 id="summaryApproved" class="mb-0">0</h4>
                    </div>
                </div>
                <div class="col-sm-3">
                    <div class="card card-sm p-3">
                        <small class="text-muted">Rejected</small>
                        <h4 id="summaryRejected" class="mb-0">0</h4>
                    </div>
                </div>
                <div class="col-sm-3">
                    <div class="card card-sm p-3">
                        <small class="text-muted">Pending</small>
                        <h4 id="summaryPending" class="mb-0">0</h4>
                    </div>
                </div>
            </div>

            <!-- Table -->
            <div class="card card-sm shadow-sm">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Leave ID</th>
                                <th>Start</th>
                                <th>End</th>
                                <th>Type</th>
                                <th>Status</th>
                                <th>Processed on</th>
                                <th>Submitted</th>
                            </tr>
                        </thead>
                        <tbody id="leaveTbody">
                            <tr>
                                <td colspan="11" class="text-center text-muted py-4">Loading...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="d-flex justify-content-between align-items-center p-3">
                    <div id="pagerInfo" class="text-muted small"></div>
                    <ul class="pagination pagination-sm mb-0" id="pager"></ul>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const PAGE_SIZE = 5; // you can increase to 5 or 10 later
        let allLeaves = [];
        let currentPage = 1;
        let totalPages = 1;
        let month = 8,
            year = 2025;

        async function reloadReports() {
            month = document.getElementById('monthSelect').value;
            year = document.getElementById('yearSelect').value;
            await loadReportData();
            renderPage(1);
        }

        async function loadReportData() {
            const url = `https://localhost:7212/employee/getMonthLeaveReports/${month}/${year}`;
            try {
                const res = await fetch(url);
                const data = await res.json();

                // ✅ Update summary counts
                document.getElementById('summaryTotal').textContent = data.total_leaves ?? 0;
                document.getElementById('summaryApproved').textContent = data.approved ?? 0;
                document.getElementById('summaryRejected').textContent = data.rejected ?? 0;
                document.getElementById('summaryPending').textContent = data.pending ?? 0;

                // ✅ Leaves array
                if (Array.isArray(data.leaves)) {
                    allLeaves = data.leaves;
                } else {
                    allLeaves = [];
                }

                totalPages = Math.ceil(allLeaves.length / PAGE_SIZE);
            } catch (err) {
                console.error('Failed to load report data:', err);
                allLeaves = [];
                totalPages = 1;
            }
        }

        function renderPage(page) {
            currentPage = page;
            const startIndex = (page - 1) * PAGE_SIZE;
            const pageData = allLeaves.slice(startIndex, startIndex + PAGE_SIZE);
            const tbody = document.getElementById('leaveTbody');

            if (!pageData.length) {
                tbody.innerHTML = `<tr><td colspan="11" class="text-center text-muted py-4">No records found</td></tr>`;
                document.getElementById('pager').innerHTML = '';
                document.getElementById('pagerInfo').textContent = '';
                return;
            }

            tbody.innerHTML = pageData.map((r, i) => `
            <tr>
                <td>${r.leave_id}</td>
                <td>${r.leave_start_date}</td>
                <td>${r.leave_end_date}</td>
                <td>${r.leave_type}</td>
                <td>
                    <span class="badge bg-${getStatusColor(r.leave_status)}">${r.leave_status}</span>
                </td>
                <td>${r.approval_date ?? '—'}</td>
                <td>${formatDateTime(r.submit_at)}</td>
            </tr>
        `).join('');

            renderPager();
        }

        function getStatusColor(status) {
            switch ((status || '').toLowerCase()) {
                case 'approved':
                    return 'success';
                case 'rejected':
                    return 'danger';
                case 'pending':
                    return 'warning';
                default:
                    return 'secondary';
            }
        }

        function formatDateTime(dateStr) {
            if (!dateStr) return '—';
            const d = new Date(dateStr);
            return isNaN(d) ? dateStr : d.toLocaleString();
        }

        function renderPager() {
            const pager = document.getElementById('pager');
            pager.innerHTML = '';

            const createBtn = (label, disabled, page) => {
                const li = document.createElement('li');
                li.className = `page-item ${disabled ? 'disabled' : ''} ${page === currentPage ? 'active' : ''}`;
                li.innerHTML = `<a class="page-link" href="#">${label}</a>`;
                if (!disabled) {
                    li.onclick = e => {
                        e.preventDefault();
                        renderPage(page);
                    };
                }
                return li;
            };

            pager.appendChild(createBtn('« First', currentPage === 1, 1));
            pager.appendChild(createBtn('‹ Prev', currentPage === 1, currentPage - 1));

            for (let i = 1; i <= totalPages; i++) {
                pager.appendChild(createBtn(i, false, i));
            }

            pager.appendChild(createBtn('Next ›', currentPage === totalPages, currentPage + 1));
            pager.appendChild(createBtn('Last »', currentPage === totalPages, totalPages));

            const totalRecords = allLeaves.length;
            const start = (currentPage - 1) * PAGE_SIZE + 1;
            const end = Math.min(totalRecords, currentPage * PAGE_SIZE);
            document.getElementById('pagerInfo').textContent =
                `Showing ${start}-${end} of ${totalRecords}`;
        }

        // 🚀 Load initial data on page load
        reloadReports();
    </script>

</body>

</html>