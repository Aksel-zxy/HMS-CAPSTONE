<?php
include '../../SQL/config.php';

$requestUri = trim($_SERVER['REQUEST_URI'], '/'); // e.g., "inventory_dashboard.php"
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
}

/* Hover + active state */
.menu ul li a:hover,
.dropdown-btn:hover,
.dropdown-btn.active {
    color: #00acc1;
    background: transparent;
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

.dropdown-btn[aria-expanded="true"]::after {
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

/* Responsive */
@media (max-width: 768px) {
    .sidebar {
        width: 200px;
    }
}

@media (max-width: 480px) {
    .sidebar {
        width: 100%;
        height: auto;
        position: relative;
    }
}


</style>
<body>

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

                    <!-- Vendor Management -->
                    <?php
                    $vendorPages = ['vendor_application.php','vendor_list.php','vendor_rating.php','admin_vendor_contracts.php','admin_vendor_documents.php'];
                    $isVendorActive = in_array($currentPage, $vendorPages);
                    ?>
                    <li>
                        <button class="dropdown-btn <?= $isVendorActive ? 'active' : '' ?>">Vendor Management</button>
                        <div class="dropdown-container" style="<?= $isVendorActive ? 'display:block;' : 'display:none;' ?>">
                            <a href="vendor_application.php" class="<?= $currentPage=='vendor_application.php'?'active-link':'' ?>">Vendor Registration Approval</a>
                            <a href="vendor_list.php" class="<?= $currentPage=='vendor_list.php'?'active-link':'' ?>">Vendors</a>
                            <a href="vendor_rating.php" class="<?= $currentPage=='vendor_rating.php'?'active-link':'' ?>">Vendor Rating & Feedback</a>
                            <a href="admin_vendor_contracts.php" class="<?= $currentPage=='admin_vendor_contracts.php'?'active-link':'' ?>">Contract & Agreement Tracking</a>
                            <a href="admin_vendor_documents.php" class="<?= $currentPage=='admin_vendor_documents.php'?'active-link':'' ?>">Compliance & Document Management</a>
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
                            <a href="purchase_order.php" class="<?= $currentPage=='purchase_order.php'?'active-link':'' ?>">Purchase Order</a>
                            <a href="department_request.php" class="<?= $currentPage=='department_request.php'?'active-link':'' ?>">Department Request</a>
                            <a href="admin_purchase_requests.php" class="<?= $currentPage=='admin_purchase_requests.php'?'active-link':'' ?>">Purchase Request</a>
                            <a href="order_receive.php" class="<?= $currentPage=='order_receive.php'?'active-link':'' ?>">Goods Receipt & Verification</a>
                            <a href="po_status_tracking.php" class="<?= $currentPage=='po_status_tracking.php'?'active-link':'' ?>">PO Status Tracking</a>
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
                            <a href="budget_request.php" class="<?= $currentPage=='budget_request.php'?'active-link':'' ?>">Departments Budget Request</a>
                            <a href="asset_mapping.php" class="<?= $currentPage=='asset_mapping.php'?'active-link':'' ?>">Department Asset Mapping</a>
                            <a href="maintenance.php" class="<?= $currentPage=='maintenance.php'?'active-link':'' ?>">Repair & Maintenance</a>
                            <a href="asset_transfer.php" class="<?= $currentPage=='asset_transfer.php'?'active-link':'' ?>">Asset Transfer & Disposal</a>
                            
                        </div>
                    </li>
                </ul>
            </div>

     <!-- Account -->
<div class="menu">
    <p class="title">Account</p>
    <ul>
        <li><a href="#"><span class="text">View Profile</span></a></li>
        <li>
            <a href="vlogin.php" target="_blank">
                <span class="text">Vendor Login</span>
            </a>
        </li>
        <li>
            <a href="../logout.php" onclick="return confirm('Are you sure you want to log out?');">
                <span class="text">Log Out</span>
            </a>
        </li>
    </ul>
</div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const dropdownButtons = document.querySelectorAll('.dropdown-btn');
        dropdownButtons.forEach(function(btn) {
            const dropdown = btn.nextElementSibling;
            dropdown.style.display = dropdown.style.display || 'none';

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
