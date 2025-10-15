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
    <link rel="stylesheet" type="text/css" href="assets/css/billing_sidebar.css">
</head>
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
