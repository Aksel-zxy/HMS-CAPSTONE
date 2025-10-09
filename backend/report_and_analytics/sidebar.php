<?php
// sidebar.php - Reusable sidebar component
include 'header.php';
?>
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