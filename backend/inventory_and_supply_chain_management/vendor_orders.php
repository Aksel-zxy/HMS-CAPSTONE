<?php
session_start();
require 'db.php';

// Ensure vendor is logged in
if (!isset($_SESSION['vendor_id'])) {
    die("❌ You must be logged in to view this page.");
}
$vendor_id = $_SESSION['vendor_id'];

// 🔹 Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'], $_POST['po_number'], $_POST['status'])) {
    $po_number = $_POST['po_number'];
    $new_status = $_POST['status'];
    $status_order = ["Processing", "Packed", "Shipped"];

    // Fetch all items for this PO belonging to this vendor
    $stmt = $pdo->prepare("SELECT id, status FROM vendor_orders WHERE purchase_order_number = ? AND vendor_id = ?");
    $stmt->execute([$po_number, $vendor_id]);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($orders as $order) {
        $current_index = array_search($order['status'], $status_order);
        $new_index = array_search($new_status, $status_order);

        // Only allow forward movement
        if ($new_index > $current_index) {
            $updateStmt = $pdo->prepare("UPDATE vendor_orders SET status = ? WHERE id = ?");
            $updateStmt->execute([$new_status, $order['id']]);
        }
    }

    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// 🔹 Fetch ongoing orders
$ongoingStmt = $pdo->prepare("
    SELECT vo.purchase_order_number, MIN(vo.created_at) AS order_time,
           SUM(vo.quantity) AS total_qty, SUM(vo.quantity * vp.price) AS total_price,
           MAX(vo.status) AS status
    FROM vendor_orders vo
    JOIN vendor_products vp ON vo.item_id = vp.id
    WHERE vo.vendor_id = ? AND vo.status != 'Completed'
    GROUP BY vo.purchase_order_number
");
$ongoingStmt->execute([$vendor_id]);
$ongoingOrders = $ongoingStmt->fetchAll(PDO::FETCH_ASSOC);

// 🔹 Fetch completed orders
$completedStmt = $pdo->prepare("
    SELECT vo.purchase_order_number, MIN(vo.created_at) AS order_time,
           SUM(vo.quantity) AS total_qty, SUM(vo.quantity * vp.price) AS total_price,
           'Completed' AS status
    FROM vendor_orders vo
    JOIN vendor_products vp ON vo.item_id = vp.id
    WHERE vo.vendor_id = ? AND vo.status = 'Completed'
    GROUP BY vo.purchase_order_number
");
$completedStmt->execute([$vendor_id]);
$completedOrders = $completedStmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Vendor Orders</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>

<div class="vendor-sidebar">
    <?php include 'vendorsidebar.php'; ?>
</div>

<div class="main-content container py-4">
    <h2 class="mb-4">PO Status Tracking</h2>

    <ul class="nav nav-tabs" id="orderTabs" role="tablist">
        <li class="nav-item">
            <button class="nav-link active" id="ongoing-tab" data-bs-toggle="tab" data-bs-target="#ongoing" type="button">Processing</button>
        </li>
        <li class="nav-item">
            <button class="nav-link" id="completed-tab" data-bs-toggle="tab" data-bs-target="#completed" type="button">Completed</button>
        </li>
    </ul>

    <div class="tab-content mt-3" id="orderTabsContent">
        <!-- Processing Orders -->
        <div class="tab-pane fade show active" id="ongoing">
            <table class="table table-striped table-bordered align-middle">
                <thead class="table-dark">
                    <tr>
                        <th>PO Number</th>
                        <th>Order Time</th>
                        <th>Total Qty</th>
                        <th>Total Price</th>
                        <th>Status</th>
                        <th>View / Update</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($ongoingOrders)): ?>
                        <tr><td colspan="6" class="text-center text-muted">No processing orders.</td></tr>
                    <?php else: ?>
                        <?php foreach($ongoingOrders as $order): ?>
                        <tr>
                            <td><?= htmlspecialchars($order['purchase_order_number']) ?></td>
                            <td><?= $order['order_time'] ?></td>
                            <td><?= $order['total_qty'] ?></td>
                            <td>₱<?= number_format($order['total_price'], 2) ?></td>
                            <td><span class="badge bg-warning"><?= $order['status'] ?></span></td>
                            <td>
                                <button class="btn btn-sm btn-primary viewBtn" data-po="<?= htmlspecialchars($order['purchase_order_number']) ?>" data-bs-toggle="modal" data-bs-target="#orderModal">View / Update</button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Completed Orders -->
        <div class="tab-pane fade" id="completed">
            <table class="table table-striped table-bordered align-middle">
                <thead class="table-dark">
                    <tr>
                        <th>PO Number</th>
                        <th>Order Time</th>
                        <th>Total Qty</th>
                        <th>Total Price</th>
                        <th>Status</th>
                        <th>View</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($completedOrders)): ?>
                        <tr><td colspan="6" class="text-center text-muted">No completed orders.</td></tr>
                    <?php else: ?>
                        <?php foreach($completedOrders as $order): ?>
                        <tr>
                            <td><?= htmlspecialchars($order['purchase_order_number']) ?></td>
                            <td><?= $order['order_time'] ?></td>
                            <td><?= $order['total_qty'] ?></td>
                            <td>₱<?= number_format($order['total_price'], 2) ?></td>
                            <td><span class="badge bg-success"><?= $order['status'] ?></span></td>
                            <td>
                                <button class="btn btn-sm btn-secondary viewBtn" data-po="<?= htmlspecialchars($order['purchase_order_number']) ?>" data-bs-toggle="modal" data-bs-target="#orderModal">View</button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal -->
<div class="modal fade" id="orderModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Order Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="orderDetails">Loading...</div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
$(document).on("click", ".viewBtn", function() {
    var po_number = $(this).data("po");
    $("#orderDetails").html("Loading...");
    $.get("vendor_orders_modal.php", { po_number: po_number }, function(data) {
        $("#orderDetails").html(data);
    });
});
</script>
</body>
</html>
