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
    <link rel="stylesheet" href="Inventory_dashboard.css">
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
        <li>
          <button class="dropdown-btn">Equipment & Medicine Stock</button>
          <div class="dropdown-container">
            <a href="inventory_management.php">Inventory & Stock Tracking</a>
            <a href="batch&expiry.php">Batch & Expiry Tracking</a>
            <a href="#">Return & Damage Handling</a>
          </div>
        </li>
        <li>
          <button class="dropdown-btn">Vendor Management</button>
          <div class="dropdown-container">
            <a href="vendor_application.php">Vendor Registration Approval</a>
            <a href="vendor_list.php">Vendors</a>
            <a href="#">Vendor Rating & Feedback</a>
            <a href="#">Contract & Agreement Tracking</a>
            <a href="#">Compliance & Document Management</a>
          </div>
        </li>
        <li>
          <button class="dropdown-btn">Purchase Order Processing</button>
          <div class="dropdown-container">
            <a href="purchase_order.php">Purchase Order</a>
            <a href="admin_purchase_requests.php">Purchase Request</a>
            <a href="order_receive.php">Goods Receipt & Verification</a>
            <a href="po_status_tracking.php">PO Status Tracking</a>
          </div>
        </li>
        <li>
          <button class="dropdown-btn">Asset Tracking</button>
          <div class="dropdown-container">
            <a href="#">Departments Budget Request</a>
            <a href="#">Department Asset Mapping</a>
            <a href="#">Preventive Maintenance Schedule</a>
            <a href="#">Repair & Maintenance Requests</a>
            <a href="#">Asset Transfer & Disposal</a>
            <a href="#">Audit Logs & Usage History</a>
          </div>
        </li>
      </ul>
    </div>

    <div class="menu">
      <p class="title">Account</p>
      <ul>
        <li><a href="#"><span class="text">View Profile</span></a></li>
        <li>
          <a href="logout.php" onclick="return confirm('Are you sure you want to log out?');">
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
        dropdown.style.display = 'none'; // hide all dropdowns initially
        btn.setAttribute('aria-expanded', 'false');

        btn.addEventListener('click', function() {
            const isOpen = dropdown.style.display === 'block';

            // Close all dropdowns
            dropdownButtons.forEach(function(otherBtn) {
                const otherDropdown = otherBtn.nextElementSibling;
                otherDropdown.style.display = 'none';
                otherBtn.classList.remove('active');
                otherBtn.setAttribute('aria-expanded', 'false');
            });

            // Open clicked dropdown if it was closed
            if (!isOpen) {
                dropdown.style.display = 'block';
                btn.classList.add('active');
                btn.setAttribute('aria-expanded', 'true');
            }
        });
    });
});
</script>

</body>
</html>
