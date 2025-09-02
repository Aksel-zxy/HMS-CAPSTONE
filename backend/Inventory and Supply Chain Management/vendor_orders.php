<?php
require 'db.php';

// Handle status update
if (isset($_POST['update_status'])) {
    $purchase_request_id = $_POST['purchase_request_id'];
    $new_status = $_POST['status'];

    $stmt = $pdo->prepare("UPDATE vendor_orders SET status = ? WHERE purchase_request_id = ?");
    $stmt->execute([$new_status, $purchase_request_id]);
}

// 🔹 Fetch ongoing (not completed) orders
$ongoingStmt = $pdo->prepare("
    SELECT pr.id AS request_id, pr.created_at AS order_time,
           SUM(vo.quantity) AS total_qty, SUM(vo.quantity * vp.price) AS total_price,
           vo.status,
           (SELECT paid_at FROM receipt_payments rp WHERE rp.receipt_id = pr.id AND rp.status = 'Paid' LIMIT 1) AS paid_at
    FROM purchase_requests pr
    JOIN vendor_orders vo ON pr.id = vo.purchase_request_id
    JOIN vendor_products vp ON vo.item_id = vp.id
    GROUP BY pr.id, vo.status
    HAVING paid_at IS NULL
");
$ongoingStmt->execute();
$ongoingOrders = $ongoingStmt->fetchAll(PDO::FETCH_ASSOC);

// 🔹 Fetch completed (paid) orders
$completedStmt = $pdo->prepare("
    SELECT pr.id AS request_id, pr.created_at AS order_time,
           SUM(vo.quantity) AS total_qty, SUM(vo.quantity * vp.price) AS total_price,
           'Completed' AS status,
           rp.paid_at
    FROM purchase_requests pr
    JOIN vendor_orders vo ON pr.id = vo.purchase_request_id
    JOIN vendor_products vp ON vo.item_id = vp.id
    JOIN receipt_payments rp ON rp.receipt_id = pr.id AND rp.status = 'Paid'
    GROUP BY pr.id, rp.paid_at
");
$completedStmt->execute();
$completedOrders = $completedStmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Vendor Orders</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/vendor_orders.css" rel="stylesheet">
    <style>
    
    </style>
</head>
<body>

    <!-- Sidebar -->
    <div class="vendor-sidebar">
        <?php include 'vendorsidebar.php'; ?>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <h2 class="mb-4">PO Status Tracking</h2>

        <!-- 🔹 Tabs -->
        <ul class="nav nav-tabs" id="orderTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="ongoing-tab" data-bs-toggle="tab" data-bs-target="#ongoing" type="button" role="tab">
                    Processing
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="completed-tab" data-bs-toggle="tab" data-bs-target="#completed" type="button" role="tab">
                    Completed
                </button>
            </li>
        </ul>

        <div class="tab-content mt-3" id="orderTabsContent">
            <!-- 🔹 Ongoing Orders -->
            <div class="tab-pane fade show active" id="ongoing" role="tabpanel">
                <table class="table table-striped table-bordered align-middle">
                    <thead class="table-dark">
                        <tr>
                            <th>Order Time</th>
                            <th>Total Qty</th>
                            <th>Total Price</th>
                            <th>Status</th>
                            <th>View</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($ongoingOrders as $order): ?>
                        <tr>
                            <td><?= $order['order_time'] ?></td>
                            <td><?= $order['total_qty'] ?></td>
                            <td>₱<?= number_format($order['total_price'], 2) ?></td>
                            <td><?= $order['status'] ?></td>
                            <td>
                                <button class="btn btn-sm btn-primary viewBtn" data-id="<?= $order['request_id'] ?>" data-bs-toggle="modal" data-bs-target="#orderModal">
                                    View
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- 🔹 Completed Orders -->
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
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($completedOrders as $order): ?>
                        <tr>
                            <td><?= $order['order_time'] ?></td>
                            <td><?= $order['total_qty'] ?></td>
                            <td>₱<?= number_format($order['total_price'], 2) ?></td>
                            <td><span class="badge bg-success">Completed</span></td>
                            <td><?= $order['paid_at'] ?></td>
                            <td>
                                <button class="btn btn-sm btn-secondary viewBtn" data-id="<?= $order['request_id'] ?>" data-bs-toggle="modal" data-bs-target="#orderModal">
                                    View
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
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
                <div class="modal-body" id="orderDetails">
                    Loading...
                </div>
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
