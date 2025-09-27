<?php
session_start();
require 'db.php';

// Categories
$categories = [
    "IT and supporting tech",
    "Medications and pharmacy supplies",
    "Consumables and disposables",
    "Therapeutic equipment",
    "Diagnostic Equipment"
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Inventory</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body class="bg-light">

<div class="main-sidebar">
    <?php include 'Inventory_dashboard.php'; ?>
</div>

<div class="container py-5">
    <h2 class="mb-4">Inventory</h2>

    <!-- Filters -->
    <form class="row g-3 mb-4" id="filterForm">
        <div class="col-md-4">
            <select name="category" id="category" class="form-select">
                <option value="">All Categories</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?= htmlspecialchars($cat) ?>"><?= htmlspecialchars($cat) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-6">
            <input type="text" name="search" id="search" class="form-control" placeholder="Search by item name or sub type...">
        </div>
    </form>

    <!-- Table -->
    <div id="inventoryTable">
        <!-- Table data will load here -->
    </div>
</div>

<script>
$(document).ready(function() {
    // Function to fetch inventory
    function fetchInventory() {
        let category = $("#category").val();
        let search = $("#search").val();
        $.ajax({
            url: "fetch_inventory.php",
            method: "GET",
            data: { category: category, search: search },
            success: function(data) {
                $("#inventoryTable").html(data);
            }
        });
    }

    // Initial load
    fetchInventory();

    // Trigger search when typing
    $("#search").on("keyup", function() {
        fetchInventory();
    });

    // Trigger when category changes
    $("#category").on("change", function() {
        fetchInventory();
    });
});
</script>
</body>
</html>
