<?php
session_start();
include '../../SQL/config.php';

// Make sure vendor is logged in
if (!isset($_SESSION['vendor_id'])) {
    die("❌ You must be logged in to view this page.");
}
$vendor_id = $_SESSION['vendor_id'];

// 🔹 Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'], $_POST['purchase_request_id'], $_POST['status'])) {
    $request_id = (int)$_POST['purchase_request_id'];
    $new_status = $_POST['status'];

    $status_order = ["Processing", "Packed", "Shipped"];

    // Fetch all items for this request belonging to logged-in vendor
    $stmt = $pdo->prepare("SELECT id, status FROM vendor_orders WHERE purchase_request_id = ? AND vendor_id = ?");
    $stmt->execute([$request_id, $vendor_id]);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($orders as $order) {
        $current_index = array_search($order['status'], $status_order);
        $new_index = array_search($new_status, $status_order);

        // ✅ Only allow forward movement
        if ($new_index > $current_index) {
            $updateStmt = $pdo->prepare("UPDATE vendor_orders SET status = ? WHERE id = ?");
            $updateStmt->execute([$new_status, $order['id']]);
        }
    }

    // Redirect to avoid resubmission and reload modal
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

/**
 * PROCESSING ORDERS
 */
$ongoingStmt = $pdo->prepare("
    SELECT pr.id AS request_id, pr.created_at AS order_time,
           SUM(vo.quantity) AS total_qty, SUM(vo.quantity * vp.price) AS total_price,
           MAX(vo.status) AS status
    FROM purchase_requests pr
    JOIN vendor_orders vo ON pr.id = vo.purchase_request_id
    JOIN vendor_products vp ON vo.item_id = vp.id
    WHERE vo.vendor_id = ? AND vo.status != 'Completed'
    GROUP BY pr.id
");
$ongoingStmt->execute([$vendor_id]);
$ongoingOrders = $ongoingStmt->fetchAll(PDO::FETCH_ASSOC);

/**
 * COMPLETED ORDERS
 */
$completedStmt = $pdo->prepare("
    SELECT pr.id AS request_id, pr.created_at AS order_time,
           SUM(vo.quantity) AS total_qty, SUM(vo.quantity * vp.price) AS total_price,
           'Completed' AS status,
           rp.paid_at, r.id AS receipt_id, rp.id AS payment_id
    FROM purchase_requests pr
    JOIN vendor_orders vo ON pr.id = vo.purchase_request_id
    JOIN vendor_products vp ON vo.item_id = vp.id
    JOIN receipts r ON r.order_id = pr.id
    JOIN receipt_payments rp ON rp.receipt_id = r.id AND rp.status = 'Paid'
    WHERE vo.vendor_id = ?
    GROUP BY pr.id, rp.paid_at, r.id, rp.id
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
    <link href="assets/css/vendor_orders.css" rel="stylesheet">
</head>
<body>

<div class="vendor-sidebar">
    <?php include 'vendorsidebar.php'; ?>
</div>

<div class="main-content">
    <h2 class="mb-4">PO Status Tracking</h2>

    <!-- Tabs -->
    <ul class="nav nav-tabs" id="orderTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="ongoing-tab" data-bs-toggle="tab" data-bs-target="#ongoing" type="button" role="tab">Processing</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="completed-tab" data-bs-toggle="tab" data-bs-target="#completed" type="button" role="tab">Completed</button>
        </li>
    </ul>

    <div class="tab-content mt-3" id="orderTabsContent">
        <!-- Processing Orders -->
        <div class="tab-pane fade show active" id="ongoing" role="tabpanel">
            <table class="table table-striped table-bordered align-middle">
                <thead class="table-dark">
                    <tr>
                        <th>Order Time</th>
                        <th>Total Qty</th>
                        <th>Total Price</th>
                        <th>Status</th>
                        <th>View / Update</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($ongoingOrders)): ?>
                        <tr><td colspan="5" class="text-center text-muted">No processing orders.</td></tr>
                    <?php else: ?>
                        <?php foreach ($ongoingOrders as $order): ?>
                        <tr>
                            <td><?= $order['order_time'] ?></td>
                            <td><?= $order['total_qty'] ?></td>
                            <td>₱<?= number_format($order['total_price'], 2) ?></td>
                            <td>
                                <span class="badge bg-warning"><?= $order['status'] ?></span>
                            </td>
                            <td>
                                <button class="btn btn-sm btn-primary viewBtn" data-id="<?= $order['request_id'] ?>" data-bs-toggle="modal" data-bs-target="#orderModal">View / Update</button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Completed Orders -->
        <div class="tab-pane fade" id="completed" role="tabpanel">
            <table class="table table-striped table-bordered align-middle">
                <thead class="table-dark">
                    <tr>
                        <th>Order Time</th>
                        <th>Total Qty</th>
                        <th>Total Price</th>
                        <th>Status</th>
                        <th>Delivered Date</th>
                        <th>View</th>
                        <th>Receipt</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($completedOrders)): ?>
                        <tr><td colspan="7" class="text-center text-muted">No completed orders.</td></tr>
                    <?php else: ?>
                        <?php foreach ($completedOrders as $order): ?>
                        <tr>
                            <td><?= $order['order_time'] ?></td>
                            <td><?= $order['total_qty'] ?></td>
                            <td>₱<?= number_format($order['total_price'], 2) ?></td>
                            <td><span class="badge bg-success"><?= $order['status'] ?></span></td>
                            <td><?= !empty($order['paid_at']) ? date("F d, Y h:i A", strtotime($order['paid_at'])) : '<span class="text-muted">Not recorded</span>' ?></td>
                            <td>
                                <button class="btn btn-sm btn-secondary viewBtn" data-id="<?= $order['request_id'] ?>" data-bs-toggle="modal" data-bs-target="#orderModal">View</button>
                            </td>
                            <td>
                                <?php if(!empty($order['payment_id'])): ?>
                                    <a href="receipt_view.php?id=<?= $order['payment_id'] ?>" target="_blank" class="btn btn-sm btn-success">📄 View Receipt</a>
                                <?php else: ?>
                                    <span class="text-muted">N/A</span>
                                <?php endif; ?>
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
    var requestId = $(this).data("id");
    $("#orderDetails").html("Loading...");
    $.get("vendor_orders_modal.php", { request_id: requestId }, function(data) {
        $("#orderDetails").html(data);
    });
});
</script>
</body>
</html>
