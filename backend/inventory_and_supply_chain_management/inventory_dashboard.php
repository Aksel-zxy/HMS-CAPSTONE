<?php
require 'db.php';

$requestUri = trim($_SERVER['REQUEST_URI'], '/'); // e.g., "inventory-stock"
$currentPage = basename(parse_url($requestUri, PHP_URL_PATH));
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory & Supply Chain Management</title>
    <link rel="stylesheet" href="assets/css/Inventory_dashboard.css">

</head>

<body>

    <div class="sidebar">
        <div class="logo-container">
            <img src="assets/image/logo-dark.png" alt="Logo">
        </div>

        <nav class="nav">
            <div class="menu">
                <p class="title">Inventory & Supply Chain Management</p>
                <ul>
                    <!-- Equipment & Medicine Stock -->
                    <?php
        $stockPages = ['inventory-stock', 'batch-expiry', 'return-damage'];
        $isStockActive = in_array($currentPage, $stockPages);
        ?>
                    <li>
                        <button class="dropdown-btn <?= $isStockActive ? 'active' : '' ?>">Equipment & Medicine
                            Stock</button>
                        <div class="dropdown-container"
                            style="<?= $isStockActive ? 'display:block;' : 'display:none;' ?>">
                            <a href="inventory-stock"
                                class="<?= $currentPage=='inventory-stock'?'active-link':'' ?>">Inventory & Stock
                                Tracking</a>
                            <a href="batch-expiry" class="<?= $currentPage=='batch-expiry'?'active-link':'' ?>">Batch &
                                Expiry Tracking</a>
                            <a href="return-damage">Return & Damage Handling</a>
                        </div>
                    </li>

                    <!-- Vendor Management -->
                    <?php
        $vendorPages = ['vendor-application','vendor-list','vendor-rating'];
        $isVendorActive = in_array($currentPage, $vendorPages);
        ?>
                    <li>
                        <button class="dropdown-btn <?= $isVendorActive ? 'active' : '' ?>">Vendor Management</button>
                        <div class="dropdown-container"
                            style="<?= $isVendorActive ? 'display:block;' : 'display:none;' ?>">
                            <a href="vendor-registration"
                                class="<?= $currentPage=='vendor-application'?'active-link':'' ?>">Vendor Registration
                                Approval</a>
                            <a href="vendors"
                                class="<?= $currentPage=='vendor-list'?'active-link':'' ?>">Vendors</a>
                            <a href="vendor-rating" class="<?= $currentPage=='vendor-rating'?'active-link':'' ?>">Vendor
                                Rating & Feedback</a>
                            <a href="contract-tracking">Contract & Agreement Tracking</a>
                            <a href="compliance-docs">Compliance & Document Management</a>
                        </div>
                    </li>

                    <!-- Purchase Order Processing -->
                    <?php
        $poPages = ['purchase-order','purchase-request','order-receive','po-status'];
        $isPOActive = in_array($currentPage, $poPages);
        ?>
                    <li>
                        <button class="dropdown-btn <?= $isPOActive ? 'active' : '' ?>">Purchase Order
                            Processing</button>
                        <div class="dropdown-container" style="<?= $isPOActive ? 'display:block;' : 'display:none;' ?>">
                            <a href="purchase-order"
                                class="<?= $currentPage=='purchase-order'?'active-link':'' ?>">Purchase Order</a>
                            <a href="purchase-request"
                                class="<?= $currentPage=='purchase-request'?'active-link':'' ?>">Purchase Request</a>
                            <a href="order-receive" class="<?= $currentPage=='order-receive'?'active-link':'' ?>">Goods
                                Receipt & Verification</a>
                            <a href="po-status" class="<?= $currentPage=='po-status'?'active-link':'' ?>">PO Status
                                Tracking</a>
                        </div>
                    </li>

                    <!-- Asset Tracking -->
                    <?php
        $assetPages = ['departments-budget','department-asset','preventive-maintenance','repair-maintenance','asset-transfer','audit-logs'];
        $isAssetActive = in_array($currentPage, $assetPages);
        ?>
                    <li>
                        <button class="dropdown-btn <?= $isAssetActive ? 'active' : '' ?>">Asset Tracking</button>
                        <div class="dropdown-container"
                            style="<?= $isAssetActive ? 'display:block;' : 'display:none;' ?>">
                            <a href="departments-budget">Departments Budget Request</a>
                            <a href="department-assets">Department Asset Mapping</a>
                            <a href="repair-maintenance">Preventive & Repair Maintenance</a>
                            <a href="#">Asset Transfer & Disposal</a>
                            <a href="#">Audit Logs & Usage History</a>
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
                        <a href="../logout.php" onclick="return confirm('Are you sure you want to log out?');">
                            <span class="text">Log Out</span>
                        </a>
                    </li>
                </ul>
            </div>
        </nav>
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