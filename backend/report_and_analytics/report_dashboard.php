<?php
/*
include '../../SQL/config.php';

if (!isset($_SESSION['report']) || $_SESSION['report'] !== true) {
    header('Location: login.php'); // Redirect to login if not logged in
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
    */
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Report Dashboard</title>
    <link rel="shortcut icon" href="assets/image/favicon.ico" type="image/x-icon">
    <link rel="stylesheet" href="assets/CSS/bootstrap.min.css">
    <link rel="stylesheet" href="assets/CSS/super.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">

    <style>
        body {
            background: #f8f9fa;
            font-family: "Poppins", sans-serif;
        }

        .report-card {
            transition: all 0.3s ease;
            border-radius: 14px;
            background: #fff;
            border: 1px solid #e6e6e6;
        }

        .report-card:hover {
            transform: translateY(-6px);
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.15);
        }

        .card-title {
            font-size: 18px;
            font-weight: 600;
        }

        .grid-container {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
            gap: 25px;
        }

        .link-section {
            display: flex;
            justify-content: center;
            gap: 20px;
            margin-bottom: 30px;
        }

        .link-section a {
            text-decoration: none;
            color: #000;
            background: #fff;
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 8px;
            border: 1px solid #000;
        }

        .link-section a:hover {
            background: #000;
            color: #fff;
            transform: translateY(-3px);
        }

        hr {
            border-top: 1px solid #000;
            opacity: 0.3;
        }

        /* Available Doctors Section */
        .doctor-card {
            border: 1px solid #000;
            border-radius: 10px;
            background-color: #fff;
            transition: all 0.25s ease;
        }

        .doctor-card:hover {
            transform: translateY(-5px);
            background-color: #f1f1f1;
        }

        .doctor-avatar-placeholder {
            width: 70px;
            height: 70px;
            border-radius: 50%;
            background-color: #e9ecef;
            border: 2px solid #000;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #000;
            font-weight: 600;
            font-size: 1.1rem;
            text-transform: uppercase;
        }

        .btn-view {
            border: 1px solid #000;
            background: #000;
            color: #fff;
            border-radius: 20px;
            padding: 5px 14px;
            font-size: 0.85rem;
        }

        .btn-view:hover {
            background: #fff;
            color: #000;
        }

        .btn-collapse {
            text-decoration: none;
            color: #000;
            background: #fff;
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.3s;
            border: 1px solid #000;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-collapse:hover {
            background: #000;
            color: #fff;
        }

        .modal-header {
            background: #000;
            color: #fff;
        }

        .table thead {
            background: #000;
            color: #fff;
        }

        .list-group-item {
            border-color: #ddd;
        }
    </style>
</head>

<body>
    <div class="d-flex">
        <!-- SIDEBAR -->
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

        <!-- MAIN CONTENT -->
        <div class="main w-100">
            <div class="container my-5">
                <div class="text-center mb-4">
                    <h4 class="fw-bold"> Reports Dashboard</h4>
                </div>

                <!-- Black & White link buttons -->
                <div class="link-section">
                    <a href="staff_information.php"><i class="bi bi-person-lines-fill"></i> Staff Information</a>
                    <a href="doctor_specialization_and_evaluation.php"><i class="bi bi-clipboard-data"></i> Doctor Details & Evaluation</a>
                </div>

                <h5 class="text-center mb-4">Select a Report</h5>

                <!-- Reports Grid -->
                <div class="grid-container mb-5">
                    <a href="annualPayroll_Report.php" class="text-decoration-none text-dark">
                        <div class="card report-card p-4 text-center">
                            <div class="mb-3"><i class="bi bi-cash-stack" style="font-size:40px;"></i></div>
                            <h5 class="card-title">Annual Payroll Report</h5>
                        </div>
                    </a>

                    <a href="daily_attendance_report.php" class="text-decoration-none text-dark">
                        <div class="card report-card p-4 text-center">
                            <div class="mb-3"><i class="bi bi-calendar-check" style="font-size:40px;"></i></div>
                            <h5 class="card-title">Daily Attendance Report</h5>
                        </div>
                    </a>

                    <a href="hospital_income_report.php" class="text-decoration-none text-dark">
                        <div class="card report-card p-4 text-center">
                            <div class="mb-3"><i class="bi bi-hospital" style="font-size:40px;"></i></div>
                            <h5 class="card-title">Hospital Income Report</h5>
                        </div>
                    </a>

                    <a href="month_insurance_claim_report.php" class="text-decoration-none text-dark">
                        <div class="card report-card p-4 text-center">
                            <div class="mb-3"><i class="bi bi-file-earmark-medical" style="font-size:40px;"></i></div>
                            <h5 class="card-title">Insurance Claim Report</h5>
                        </div>
                    </a>

                    <a href="paycycle_report.php" class="text-decoration-none text-dark">
                        <div class="card report-card p-4 text-center">
                            <div class="mb-3"><i class="bi bi-clock-history" style="font-size:40px;"></i></div>
                            <h5 class="card-title">Paycycle Report</h5>
                        </div>
                    </a>

                    <a href="revenue_report.php" class="text-decoration-none text-dark">
                        <div class="card report-card p-4 text-center">
                            <div class="mb-3"><i class="bi bi-bar-chart-line" style="font-size:40px;"></i></div>
                            <h5 class="card-title">Revenue Report</h5>
                        </div>
                    </a>

                    <a href="salary_paid_report.php" class="text-decoration-none text-dark">
                        <div class="card report-card p-4 text-center">
                            <div class="mb-3"><i class="bi bi-wallet2" style="font-size:40px;"></i></div>
                            <h5 class="card-title">Monthly Payroll Summary</h5>
                        </div>
                    </a>

                    <a href="shift_and_duty.php" class="text-decoration-none text-dark">
                        <div class="card report-card p-4 text-center">
                            <div class="mb-3"><i class="bi bi-people" style="font-size:40px;"></i></div>
                            <h5 class="card-title">Shift & Duty Report</h5>
                        </div>
                    </a>
                </div>

                <hr class="my-5">

                <!-- Collapsible Available Doctors Section -->
                <div class="text-center mb-3">
                    <button class="btn-collapse" type="button" data-bs-toggle="collapse" data-bs-target="#doctorSection" aria-expanded="false" aria-controls="doctorSection">
                        <i class="bi bi-person-badge"></i> Available Doctors <i class="bi bi-chevron-down ms-1"></i>
                    </button>
                </div>

                <div class="collapse" id="doctorSection">
                    <div class="row g-4" id="doctorList"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal -->
    <div class="modal fade" id="doctorModal" tabindex="-1" aria-labelledby="doctorModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content rounded-4">
                <div class="modal-header">
                    <h5 class="modal-title" id="doctorModalLabel">Doctor Details</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <h6 class="fw-semibold">Professional Details</h6>
                    <ul id="profDetails" class="list-group mb-3"></ul>

                    <h6 class="fw-semibold">Educational Background</h6>
                    <ul id="eduDetails" class="list-group mb-3"></ul>

                    <h6 class="fw-semibold">License Information</h6>
                    <ul id="licenseDetails" class="list-group mb-3"></ul>

                    <h6 class="fw-semibold">Evaluation Records</h6>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Score</th>
                                    <th>Rating</th>
                                    <th>Comments</th>
                                </tr>
                            </thead>
                            <tbody id="evalTable">
                                <tr>
                                    <td colspan="4" class="text-center text-muted">Loading...</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="assets/Bootstrap/bootstrap.bundle.min.js"></script>
    <script>
        const doctorList = document.getElementById("doctorList");

        async function loadDoctors() {
            try {
                const response = await fetch('http://localhost:5288/employee/getDoctorsDetails');
                if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
                const doctors = await response.json();

                doctorList.innerHTML = '';
                doctors.forEach(doc => {
                    const initials = ((doc.first_name || ' ')[0] + (doc.last_name || ' ')[0]).toUpperCase();
                    const col = document.createElement("div");
                    col.className = "col-md-4 col-sm-6";
                    col.innerHTML = `
                        <div class="card doctor-card p-3 h-100">
                            <div class="d-flex align-items-center">
                                <div class="doctor-avatar-placeholder me-3">${initials}</div>
                                <div class="doctor-info">
                                    <h6>${doc.first_name || ''} ${doc.last_name || ''}</h6>
                                    <small class="text-muted">${doc.specialization || ''}</small><br>
                                    <small>${doc.department || ''}</small>
                                </div>
                            </div>
                            <div class="mt-3 text-end">
                                <button class="btn btn-view" onclick="viewDetails('${doc.employee_id}')">View Details</button>
                            </div>
                        </div>`;
                    doctorList.appendChild(col);
                });
            } catch (error) {
                console.error('Error fetching doctor data:', error);
                doctorList.innerHTML = `<p class="text-danger text-center">Failed to load doctor data.</p>`;
            }
        }

        async function viewDetails(id) {
            try {
                const response = await fetch(`http://localhost:5288/employee/getDoctorDetailsAndEvaluation/${id}`);
                if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
                const doc = await response.json();

                document.getElementById("doctorModalLabel").innerText = `${doc.role || 'Doctor'} — ${doc.specialization || ''}`;
                document.getElementById("profDetails").innerHTML = `
                    <li class="list-group-item"><strong>Department:</strong> ${doc.department || '—'}</li>
                    <li class="list-group-item"><strong>Specialization:</strong> ${doc.specialization || '—'}</li>
                    <li class="list-group-item"><strong>Role:</strong> ${doc.role || '—'}</li>
                    <li class="list-group-item"><strong>Employment Type:</strong> ${doc.employmentType || '—'}</li>`;
                document.getElementById("eduDetails").innerHTML = `
                    <li class="list-group-item"><strong>Educational Status:</strong> ${doc.educationalStatus || '—'}</li>
                    <li class="list-group-item"><strong>Degree Type:</strong> ${doc.degreeType || '—'}</li>
                    <li class="list-group-item"><strong>Medical School:</strong> ${doc.medicalSchool || '—'}</li>
                    <li class="list-group-item"><strong>Graduation Year:</strong> ${doc.graduationYear || '—'}</li>`;
                document.getElementById("licenseDetails").innerHTML = `
                    <li class="list-group-item"><strong>License Type:</strong> ${doc.licenseType || '—'}</li>
                    <li class="list-group-item"><strong>License Number:</strong> ${doc.licenseNumber || '—'}</li>
                    <li class="list-group-item"><strong>Issued Date:</strong> ${doc.licenseIssued || '—'}</li>
                    <li class="list-group-item"><strong>Expiry Date:</strong> ${doc.licenseExpiry || '—'}</li>`;

                const evalTable = document.getElementById("evalTable");
                evalTable.innerHTML = "";
                if (doc.evaluation_records && doc.evaluation_records.length > 0) {
                    doc.evaluation_records.forEach(e => {
                        evalTable.innerHTML += `
                            <tr>
                                <td>${e.date || '—'}</td>
                                <td>${e.score || '—'}</td>
                                <td>${e.rating || '—'}</td>
                                <td>${e.comments || '—'}</td>
                            </tr>`;
                    });
                } else {
                    evalTable.innerHTML = `<tr><td colspan="4" class="text-center text-muted">No evaluations found</td></tr>`;
                }

                new bootstrap.Modal(document.getElementById("doctorModal")).show();

            } catch (error) {
                console.error('Error fetching doctor details:', error);
                alert('Failed to load doctor details.');
            }
        }

        loadDoctors();
    </script>
</body>

</html>