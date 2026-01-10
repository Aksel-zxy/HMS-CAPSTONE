<?php

include '../../SQL/config.php';

if (isset($_SESSION['user_id'])) {
    $stmt = $conn->prepare("SELECT fname, lname, username FROM users WHERE user_id = ?");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
} else {
    header('Location: login.php');
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Billing & Insurance Management</title>
    <link rel="stylesheet" type="text/css" href="assets/CSS/billing_sidebar.css">
    <link rel="stylesheet" type="text/css" href="/HMS-CAPSTONE/backend/billing_and_insurance_management/assets/CSS/billing_sidebar.css"> 
     <link rel="stylesheet" href="assets/CSS/billing_sidebar.css">
</head>
<style>
body {
    font-family: "Nunito", "Segoe UI", Arial, sans-serif;
    margin: 0;
    padding: 0;
    background-color: #F5F6F7;
    color: #6e768e;
    transition: margin-left 0.3s ease-in-out;
}

/* Sidebar */
.sidebar {
    width: 250px;
    height: 100vh;
    background: #fff;
    color: #6e768e;
    position: fixed;
    left: 0;
    top: 0;
    overflow-y: auto;
    border-right: 1px solid #e0e0e0;
    transition: transform 0.3s ease-in-out, width 0.3s ease-in-out;
    z-index: 1000;
}

/* Closed state */
.sidebar.closed {
    transform: translateX(-250px);
}

/* Logo */
.sidebar .logo-container {
    text-align: center;
    padding: 20px;
    border-bottom: 1px solid #e0e0e0;
}

.sidebar .logo-container img {
    max-width: 100px;
    height: auto;
}

.sidebar .welcome-text {
    display: block;
    margin-top: 10px;
    font-size: 0.85rem;
    color: #6e768e;
}

/* Section Titles */
.menu .title {
    font-size: .6875rem;
    font-weight: 600;
    padding: 10px 20px;
    text-transform: uppercase;
    color: #6e768e;
    letter-spacing: .05em;
}

/* Menu items */
.menu ul {
    list-style: none;
    margin: 0;
    padding: 0;
}

.menu ul li a,
.dropdown-btn {
    display: block;
    width: 100%;
    padding: .625rem 1.625rem;
    font-size: .95rem;
    text-decoration: none;
    background: none;
    border: none;
    text-align: left;
    cursor: pointer;
    font-family: "Nunito", sans-serif;
    color: #6e768e;
    transition: color 0.3s, background 0.3s;
}

/* Hover + active state */
.menu ul li a:hover,
.dropdown-btn:hover,
.dropdown-btn.active {
    color: #00acc1;
    background: rgba(0, 172, 193, 0.1);
    border-radius: 4px;
}

/* Dropdown caret */
.dropdown-btn {
    position: relative;
    padding-right: 30px;
}

.dropdown-btn::after {
    content: "";
    border: solid;
    border-width: 0 .075rem .075rem 0;
    display: inline-block;
    padding: 2px;
    position: absolute;
    right: 1.5rem;
    top: 1.2rem;
    transform: rotate(45deg);
    transition: transform 0.2s ease-out, color 0.2s;
    color: #6e768e;
}

.dropdown-btn.active::after {
    transform: rotate(-135deg);
    color: #00acc1;
}

/* Dropdown container */
.dropdown-container {
    display: none;
    flex-direction: column;
    margin-left: .5rem;
}

.dropdown-container a {
    padding: .5rem 2rem;
    font-size: .9rem;
    color: #6e768e;
    text-decoration: none;
    transition: color 0.3s;
}

.dropdown-container a:hover {
    color: #00acc1;
}

/* Toggle button */
.sidebar-toggle {
    position: fixed;
    top: 15px;
    left: 260px;
    background: #fefefe;
    color: #000000;
    border: none;
    font-size: 20px;
    padding: 8px 12px;
    cursor: pointer;
    z-index: 1100;
    border-radius: 4px;
    transition: all 0.3s ease;
}

.sidebar.closed ~ .sidebar-toggle {
    left: 10px;
}

/* Hover effect */
.sidebar-toggle:hover {
    background: #00acc1;
    color: #fff;
}

/* Scrollbar */
.sidebar::-webkit-scrollbar {
    width: 6px;
}

.sidebar::-webkit-scrollbar-thumb {
    background-color: #c0c0c0;
    border-radius: 3px;
}

/* Responsive */
@media (max-width: 768px) {
    .sidebar {
        width: 200px;
    }

    .sidebar.closed {
        transform: translateX(-200px);
    }

    .sidebar-toggle {
        left: 210px;
    }

    .sidebar.closed ~ .sidebar-toggle {
        left: 10px;
    }
}

@media (max-width: 480px) {
    .sidebar {
        width: 100%;
        height: auto;
        position: relative;
        transform: translateX(-100%);
    }

    .sidebar.closed {
        transform: translateX(-100%);
    }

    .sidebar-toggle {
        top: 10px;
        left: 10px;
        font-size: 18px;
        padding: 6px 10px;
    }
}


</style>
<body>

<!-- Sidebar -->
<div class="sidebar open" id="mySidebar">
    <div class="logo-container">
        <img src="assets/image/logo-dark.png" alt="Logo">
        <span class="welcome-text">
            Welcome, 
            <?php
                if (isset($user) && is_array($user) && isset($user['fname'], $user['lname'])) {
                    echo htmlspecialchars($user['fname'] . " " . $user['lname']);
                } else {
                    echo "Guest";
                }
            ?>
        </span>
    </div>

    <nav class="nav">
        <div class="menu">
            <p class="title">Navigation</p>
            <ul>
                <!-- Dashboard -->
                <li><a href="billing_dashboard.php">Dashboard</a></li>

                <!-- Billing Management -->
                <li>
                    <button class="dropdown-btn">Billing Management</button>
                    <div class="dropdown-container">
                        <a href="billing_items.php">Billing Items</a>
                        <a href="patient_billing.php">Patient Billing</a>
                        <a href="billing_records.php">Billing Records</a>
                        <!-- <a href="billing_items.php">Billing Items</a> -->
                        <a href="expense_logs.php">Expense Logs</a>
                    </div>
                </li>

                <!-- Journal -->
                <li>
                    <button class="dropdown-btn">Journal</button>
                    <div class="dropdown-container">
                        <a href="journal_account.php">Journal Account</a>
                        <a href="journal_entry.php">Journal Entry</a>
                    </div>
                </li>
                <li><a href="insurance_approval.php" target="_blank">Insurance Approval</a></li>
            </ul>
        </div>

        <div class="menu">
            <p class="title">Account</p>
            
            <ul>
                <li>
                    <a href="../logout.php" onclick="return confirm('Are you sure you want to log out?');">
                        Log Out
                    </a>
                </li>
            </ul>
        </div>
    </nav>
</div>

<!-- Toggle Button -->
<button class="sidebar-toggle" id="sidebarToggle">✖</button>

<script>
    const sidebar = document.getElementById('mySidebar');
    const toggleBtn = document.getElementById('sidebarToggle');

    function toggleSidebar() {
        sidebar.classList.toggle('closed');
        if (sidebar.classList.contains('closed')) {
            toggleBtn.innerHTML = '☰';
        } else {
            toggleBtn.innerHTML = '✖';
        }
    }

    toggleBtn.addEventListener('click', toggleSidebar);

    // Dropdown toggle
    document.addEventListener('DOMContentLoaded', function() {
        const dropdowns = document.querySelectorAll('.dropdown-btn');
        dropdowns.forEach(btn => {
            btn.addEventListener('click', () => {
                const container = btn.nextElementSibling;
                const isOpen = container.style.display === 'block';

                // Close all other dropdowns
                document.querySelectorAll('.dropdown-container').forEach(c => c.style.display = 'none');
                document.querySelectorAll('.dropdown-btn').forEach(b => b.classList.remove('active'));

                // Toggle current dropdown
                if (!isOpen) {
                    container.style.display = 'block';
                    btn.classList.add('active');
                }
            });
        });
    });
</script>
</body>
</html>
