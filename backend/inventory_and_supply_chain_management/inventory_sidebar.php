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

$requestUri = trim($_SERVER['REQUEST_URI'], '/');
$currentPage = basename(parse_url($requestUri, PHP_URL_PATH));
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory & Supply Chain Management</title>
    <link rel="stylesheet" href="assets/CSS/inventory_dashboard.css">
    <link rel="stylesheet" type="text/css" href="/HMS-CAPSTONE/backend/inventory_and_supply_chain_management/assets/CSS/inventory_dashboard.css"> 
</head>
<style>

body {
    font-family: "Nunito", "Segoe UI", Arial, sans-serif;
    margin: 0;
    padding: 0;
    background-color: #F5F6F7;
    color: #6e768e;
}

/* ── FLOATING TOP NAVBAR ── */
.top-navbar {
    position: fixed;
    top: 12px;
    right: 20px;
    display: flex;
    align-items: center;
    justify-content: flex-end;
    z-index: 1200;
}

/* Account dropdown trigger */
.account-dropdown {
    position: relative;
}

.account-btn {
    display: flex;
    align-items: center;
    gap: 10px;
    background: #fff;
    border: 1px solid #e0e0e0;
    border-radius: 8px;
    padding: 8px 16px;
    cursor: pointer;
    font-family: "Nunito", sans-serif;
    font-size: .9rem;
    color: #6e768e;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
    transition: background 0.2s, border-color 0.2s, box-shadow 0.2s;
}

.account-btn:hover {
    background: #f0fafc;
    border-color: #00acc1;
    color: #00acc1;
    box-shadow: 0 4px 16px rgba(0, 172, 193, 0.15);
}

.account-btn .caret {
    border: solid #6e768e;
    border-width: 0 2px 2px 0;
    display: inline-block;
    padding: 3px;
    transform: rotate(45deg);
    transition: transform 0.2s, border-color 0.2s;
    margin-top: -2px;
}

.account-btn.open .caret,
.account-btn:hover .caret {
    border-color: #00acc1;
}

.account-btn.open .caret {
    transform: rotate(-135deg);
    margin-top: 2px;
}

/* Dropdown panel */
.account-menu {
    display: none;
    position: absolute;
    top: calc(100% + 10px);
    right: 0;
    min-width: 210px;
    background: #fff;
    border: 1px solid #e8e8e8;
    border-radius: 10px;
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.12);
    z-index: 1300;
    overflow: hidden;
    animation: fadeDown 0.15s ease;
}

@keyframes fadeDown {
    from { opacity: 0; transform: translateY(-6px); }
    to   { opacity: 1; transform: translateY(0); }
}

.account-menu.show {
    display: block;
}

.account-menu .welcome-label {
    padding: 14px 18px 12px;
    font-size: .85rem;
    color: #6e768e;
    border-bottom: 1px solid #f0f0f0;
    background: #fafafa;
}

.account-menu .welcome-label span {
    color: #00acc1;
    font-weight: 700;
}

.account-menu a {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 11px 18px;
    font-size: .9rem;
    color: #6e768e;
    text-decoration: none;
    transition: background 0.2s, color 0.2s;
}

.account-menu a:hover {
    background: rgba(0, 172, 193, 0.07);
    color: #00acc1;
}

.account-menu a.logout-link {
    border-top: 1px solid #f0f0f0;
    color: #e05555;
}

.account-menu a.logout-link:hover {
    background: rgba(224, 85, 85, 0.07);
    color: #c0392b;
}

/* ── SIDEBAR ── */
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
    transition: all 0.3s ease-in-out;
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
    transition: color 0.3s;
    box-sizing: border-box;
}

/* Hover + active state */
.menu ul li a:hover,
.dropdown-btn:hover,
.dropdown-btn.active {
    color: #00acc1;
    background: rgba(0, 172, 193, 0.1);
    border-radius: 4px;
}

/* Active link */
.active-link {
    color: #00acc1 !important;
    font-weight: 600;
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

/* Scrollbar */
.sidebar::-webkit-scrollbar { width: 6px; }
.sidebar::-webkit-scrollbar-thumb { background-color: #c0c0c0; border-radius: 3px; }

/* Responsive */
@media (max-width: 768px) {
    .sidebar { width: 200px; }
    .top-navbar { top: 8px; right: 12px; }
}

@media (max-width: 480px) {
    .sidebar {
        width: 100%;
        height: auto;
        position: relative;
    }
    .top-navbar { top: 8px; right: 12px; }
}

</style>
<body>

<!-- ── FLOATING ACCOUNT DROPDOWN (Top Right) ── -->
<div class="top-navbar">
    <div class="account-dropdown">
        <button class="account-btn" id="accountBtn">
            <?php
                if (isset($user) && is_array($user) && isset($user['fname'], $user['lname'])) {
                    echo htmlspecialchars($user['fname'] . " " . $user['lname']);
                } else {
                    echo "Guest";
                }
            ?>
            <span class="caret"></span>
        </button>

        <div class="account-menu" id="accountMenu">
            <div class="welcome-label">
                Welcome, <span><?php
                    if (isset($user) && isset($user['lname'])) {
                        echo htmlspecialchars($user['lname']) . "!";
                    } else {
                        echo "Guest!";
                    }
                ?></span>
            </div>
            <a href="../logout.php" class="logout-link"
               onclick="return confirm('Are you sure you want to log out?');">
               Logout
            </a>
        </div>
    </div>
</div>

<!-- ── SIDEBAR ── -->
<div class="sidebar">
    <div class="logo-container">
        <img src="assets/image/logo-dark.png" alt="Logo">
    </div>

    <nav class="nav">
        <div class="menu">
            <p class="title">Inventory & Supply Chain Management</p>
            <ul>
                <!-- Dashboard Link -->
                <li>
                    <a href="inventory_dashboard.php" class="<?= $currentPage=='inventory_dashboard.php'?'active-link':'' ?>">
                        📊 Dashboard
                    </a>
                </li>

                <!-- Equipment & Medicine Stock -->
                <?php
                $stockPages = ['inventory_management.php', 'batch_expiry.php', 'return_damage.php'];
                $isStockActive = in_array($currentPage, $stockPages);
                ?>
                <li>
                    <button class="dropdown-btn <?= $isStockActive ? 'active' : '' ?>">Equipment & Medicine Stock</button>
                    <div class="dropdown-container" style="<?= $isStockActive ? 'display:block;' : 'display:none;' ?>">
                        <a href="inventory_management.php" class="<?= $currentPage=='inventory_management.php'?'active-link':'' ?>">Inventory & Stock Tracking</a>
                        <a href="batch&expiry.php" class="<?= $currentPage=='batch&expiry.php'?'active-link':'' ?>">Batch & Expiry Tracking</a>
                        <a href="return_damage.php" class="<?= $currentPage=='return_damage.php'?'active-link':'' ?>">Return & Damage Handling</a>
                    </div>
                </li>

                <!-- Purchase Order Processing -->
                <?php
                $poPages = ['purchase_order.php','admin_purchase_requests.php','department_request.php','order_receive.php','po_status_tracking.php'];
                $isPOActive = in_array($currentPage, $poPages);
                ?>
                <li>
                    <button class="dropdown-btn <?= $isPOActive ? 'active' : '' ?>">Purchase Order Processing</button>
                    <div class="dropdown-container" style="<?= $isPOActive ? 'display:block;' : 'display:none;' ?>">
                        <a href="department_request.php" class="<?= $currentPage=='department_request.php'?'active-link':'' ?>">Department Request</a>
                        <a href="purchase_request.php" class="<?= $currentPage=='purchase_request.php'?'active-link':'' ?>">Purchase Request</a>
                        <a href="order_receive.php" class="<?= $currentPage=='order_receive.php'?'active-link':'' ?>">Goods Receipt & Verification</a>
                    </div>
                </li>

                <!-- Asset Tracking -->
                <?php
                $assetPages = ['budget_request.php','asset_mapping.php','preventive_maintenance.php','maintenance.php','asset_transfer.php','audit_logs.php','vlogin.php'];
                $isAssetActive = in_array($currentPage, $assetPages);
                ?>
                <li>
                    <button class="dropdown-btn <?= $isAssetActive ? 'active' : '' ?>">Asset Tracking</button>
                    <div class="dropdown-container" style="<?= $isAssetActive ? 'display:block;' : 'display:none;' ?>">
                        <a href="asset_mapping.php" class="<?= $currentPage=='asset_mapping.php'?'active-link':'' ?>">Department Asset Mapping</a>
                        <a href="maintenance.php" class="<?= $currentPage=='maintenance.php'?'active-link':'' ?>">Repair & Maintenance</a>
                        <a href="asset_transfer.php" class="<?= $currentPage=='asset_transfer.php'?'active-link':'' ?>">Asset Transfer & Disposal</a>
                    </div>
                </li>
            </ul>
        </div>
    </nav>
</div>

<script>
    // ── Account dropdown toggle ──
    const accountBtn = document.getElementById('accountBtn');
    const accountMenu = document.getElementById('accountMenu');

    accountBtn.addEventListener('click', (e) => {
        e.stopPropagation();
        accountMenu.classList.toggle('show');
        accountBtn.classList.toggle('open');
    });

    // Close when clicking outside
    document.addEventListener('click', () => {
        accountMenu.classList.remove('show');
        accountBtn.classList.remove('open');
    });

    // ── Sidebar dropdowns ──
    document.addEventListener('DOMContentLoaded', function() {
        const dropdownButtons = document.querySelectorAll('.dropdown-btn');
        dropdownButtons.forEach(function(btn) {
            const dropdown = btn.nextElementSibling;

            btn.addEventListener('click', function() {
                const isOpen = dropdown.style.display === 'block';
                dropdownButtons.forEach(function(otherBtn) {
                    const otherDropdown = otherBtn.nextElementSibling;
                    otherDropdown.style.display = 'none';
                    otherBtn.classList.remove('active');
                });
                if (!isOpen) {
                    dropdown.style.display = 'block';
                    btn.classList.add('active');
                }
            });
        });
    });
</script>

</body>
</html>