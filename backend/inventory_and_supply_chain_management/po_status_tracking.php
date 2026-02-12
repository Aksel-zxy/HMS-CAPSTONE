<?php
session_start();
include '../../SQL/config.php';

// Handle vendor rating submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_rating'])) {
    $vendor_id = intval($_POST['vendor_id']);
    $purchase_request_id = intval($_POST['purchase_request_id']);
    $rating = intval($_POST['rating']);
    $feedback = trim($_POST['feedback']);

    $stmt = $pdo->prepare("INSERT INTO vendor_rating (vendor_id, purchase_request_id, rating, feedback) 
                           VALUES (?, ?, ?, ?)
                           ON DUPLICATE KEY UPDATE rating=?, feedback=?, created_at=NOW()");
    $stmt->execute([$vendor_id, $purchase_request_id, $rating, $feedback, $rating, $feedback]);

    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// Fetch vendors + purchase_request_id
$stmt = $pdo->prepare("
    SELECT v.id AS vendor_id, v.company_name, vo.purchase_request_id,
           SUM(vo.quantity) AS total_quantity,
           MAX(vo.created_at) AS last_order_date
    FROM vendors v
    JOIN vendor_orders vo ON vo.vendor_id = v.id
    GROUP BY v.id, v.company_name, vo.purchase_request_id
    ORDER BY last_order_date DESC
");
$stmt->execute();
$vendors = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Function: status per vendor + request
function getVendorStatus($pdo, $vendor_id, $request_id) {
    $stmt = $pdo->prepare("SELECT status FROM vendor_orders WHERE vendor_id = ? AND purchase_request_id = ?");
    $stmt->execute([$vendor_id, $request_id]);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($orders)) return ['text'=>'No Orders','color'=>'secondary','type'=>'none'];

    $activeOrders = array_filter($orders, fn($o) => !in_array($o['status'], ['Received','Completed']));
    if (empty($activeOrders)) return ['text'=>'Completed','color'=>'dark','type'=>'completed'];

    $statuses = array_column($orders, 'status');
    if (in_array("Shipped", $statuses)) return ['text'=>'Shipped','color'=>'success','type'=>'processing'];
    if (in_array("Ready to Ship", $statuses)) return ['text'=>'Ready to Ship','color'=>'warning','type'=>'processing'];
    if (in_array("Labeled", $statuses)) return ['text'=>'Labeled','color'=>'primary','type'=>'processing'];
    if (in_array("Packed", $statuses)) return ['text'=>'Packed','color'=>'info','type'=>'processing'];

    return ['text'=>'Processing','color'=>'secondary','type'=>'processing'];
}

// Fetch receipt/payment
function getDeliveredData($pdo, $purchase_request_id) {
    $stmt = $pdo->prepare("
        SELECT rp.id, rp.paid_at, r.id AS receipt_id
        FROM receipts r
        JOIN receipt_payments rp ON rp.receipt_id = r.id
        WHERE r.order_id = ? AND rp.status = 'Paid'
        ORDER BY rp.id DESC LIMIT 1
    ");
    $stmt->execute([$purchase_request_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// Fetch order items from purchase_requests table
function getOrderItems($pdo, $purchase_request_id) {
    $stmt = $pdo->prepare("SELECT items FROM purchase_requests WHERE id = ?");
    $stmt->execute([$purchase_request_id]);
    $request = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$request || empty($request['items'])) return [];
    $items = json_decode($request['items'], true);
    return $items ?: [];
}

// Average ratings per vendor
$stmt = $pdo->prepare("SELECT vendor_id, AVG(rating) AS avg_rating FROM vendor_rating GROUP BY vendor_id");
$stmt->execute();
$avgRatings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR); // vendor_id => avg_rating
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>PO Status Tracking</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="po_status_tracking.css" rel="stylesheet">
<link rel="stylesheet" href="assets/css/inventory_dashboard.css">
</head>
<body class="bg-light">

<div class="main-sidebar">
    <?php include 'inventory_sidebar.php'; ?>
</div>

<div class="container py-5">
    <h2 class="mb-4">PO Status Tracking</h2>

    <!-- Tabs -->
    <ul class="nav nav-tabs mb-3" id="vendorTabs" role="tablist">
        <li class="nav-item">
            <button class="nav-link active" id="processing-tab" data-bs-toggle="tab" data-bs-target="#processing" type="button">Processing</button>
        </li>
        <li class="nav-item">
            <button class="nav-link" id="completed-tab" data-bs-toggle="tab" data-bs-target="#completed" type="button">Completed</button>
        </li>
    </ul>

    <div class="tab-content">
        <!-- Processing Tab -->
        <div class="tab-pane fade show active" id="processing" role="tabpanel">
            <?php $hasProcessing = false; ?>
            <table class="table table-bordered table-hover bg-white mb-4">
                <thead class="table-dark">
                    <tr>
                        <th>Company Name</th>
                        <th>Request ID</th>
                        <th>Last Order Date</th>
                        <th>Total Quantity</th>
                        <th>Status</th>
                        <th>View Items</th>
                        <th>Avg Rating</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($vendors as $v): 
                        $vendorStatus = getVendorStatus($pdo, $v['vendor_id'], $v['purchase_request_id']);
                        if($vendorStatus['type'] !== 'processing') continue;
                        $hasProcessing = true;
                        $avgRating = isset($avgRatings[$v['vendor_id']]) ? number_format($avgRatings[$v['vendor_id']],1) : null;
                        $items = getOrderItems($pdo, $v['purchase_request_id']);
                    ?>
                    <tr>
                        <td><?= htmlspecialchars($v['company_name']) ?></td>
                        <td>#<?= $v['purchase_request_id'] ?></td>
                        <td><?= $v['last_order_date'] ? date("Y-m-d", strtotime($v['last_order_date'])) : '-' ?></td>
                        <td><?= (int)$v['total_quantity'] ?></td>
                        <td><span class="badge bg-<?= $vendorStatus['color'] ?>"><?= $vendorStatus['text'] ?></span></td>
                        <td>
                            <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#itemsModal<?= $v['vendor_id'] ?>_<?= $v['purchase_request_id'] ?>">View Items</button>

                            <!-- Items Modal -->
                            <div class="modal fade" id="itemsModal<?= $v['vendor_id'] ?>_<?= $v['purchase_request_id'] ?>" tabindex="-1">
                                <div class="modal-dialog modal-lg">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title">Items for <?= htmlspecialchars($v['company_name']) ?> (Request #<?= $v['purchase_request_id'] ?>)</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <div class="modal-body">
                                            <?php if($items): ?>
                                                <table class="table table-bordered">
                                                    <thead>
                                                        <tr>
                                                            <th>Item</th>
                                                            <th>Unit</th>
                                                            <th>Quantity</th>
                                                            <th>Price</th>
                                                            <th>Subtotal</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php 
                                                        $total_qty = 0;
                                                        $total_price = 0;
                                                        foreach($items as $item): 
                                                            $qty = isset($item['qty']) ? (int)$item['qty'] : 1;
                                                            $subtotal = $qty * (float)$item['price'];
                                                            $total_qty += $qty;
                                                            $total_price += $subtotal;
                                                        ?>
                                                        <tr>
                                                            <td><?= htmlspecialchars($item['name']) ?></td>
                                                            <td><?= htmlspecialchars($item['unit_type'] ?? 'Piece') ?></td>
                                                            <td><?= $qty ?></td>
                                                            <td>₱<?= number_format((float)$item['price'],2) ?></td>
                                                            <td>₱<?= number_format($subtotal,2) ?></td>
                                                        </tr>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                    <tfoot class="table-dark">
                                                        <tr>
                                                            <th colspan="2">Total</th>
                                                            <th><?= $total_qty ?></th>
                                                            <th></th>
                                                            <th>₱<?= number_format($total_price,2) ?></th>
                                                        </tr>
                                                    </tfoot>
                                                </table>
                                            <?php else: ?>
                                                <div class="alert alert-info">No items found.</div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="modal-footer">
                                            <button class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </td>
                        <td><?= $avgRating ? $avgRating.' ⭐' : '-' ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php if(!$hasProcessing) echo "<div class='alert alert-info'>No processing orders.</div>"; ?>
        </div>

        <!-- Completed Tab -->
        <div class="tab-pane fade" id="completed" role="tabpanel">
            <?php $hasCompleted = false; ?>
            <table class="table table-bordered table-hover bg-white mb-4">
                <thead class="table-dark">
                    <tr>
                        <th>Company Name</th>
                        <th>Request ID</th>
                        <th>Last Order Date</th>
                        <th>Total Quantity</th>
                        <th>Status</th>
                        <th>Delivered Date</th>
                        <th>View Receipt</th>
                        <th>Rating</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($vendors as $v):
                        $vendorStatus = getVendorStatus($pdo, $v['vendor_id'], $v['purchase_request_id']);
                        if($vendorStatus['type'] !== 'completed') continue;
                        $hasCompleted = true;
                        $deliveredData = getDeliveredData($pdo, $v['purchase_request_id']);

                        // Check if already rated
                        $stmt = $pdo->prepare("SELECT * FROM vendor_rating WHERE vendor_id=? AND purchase_request_id=?");
                        $stmt->execute([$v['vendor_id'], $v['purchase_request_id']]);
                        $ratingData = $stmt->fetch(PDO::FETCH_ASSOC);
                        $items = getOrderItems($pdo, $v['purchase_request_id']);
                    ?>
                    <tr>
                        <td><?= htmlspecialchars($v['company_name']) ?></td>
                        <td>#<?= $v['purchase_request_id'] ?></td>
                        <td><?= $v['last_order_date'] ? date("Y-m-d", strtotime($v['last_order_date'])) : '-' ?></td>
                        <td><?= (int)$v['total_quantity'] ?></td>
                        <td><span class="badge bg-dark">Completed</span></td>
                        <td><?= $deliveredData ? date("Y-m-d H:i", strtotime($deliveredData['paid_at'])) : 'N/A' ?></td>
                        <td>
                            <?php if($deliveredData): ?>
                                <a href="receipt_view.php?id=<?= $deliveredData['id'] ?>" target="_blank" class="btn btn-success btn-sm">📄 View Receipt</a>
                            <?php else: ?>
                                <span class="text-muted">N/A</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if($ratingData): ?>
                                <span>⭐ <?= $ratingData['rating'] ?></span><br>
                                <small><?= htmlspecialchars($ratingData['feedback']) ?></small>
                            <?php else: ?>
                                <button class="btn btn-warning btn-sm" data-bs-toggle="modal" data-bs-target="#rateModal<?= $v['vendor_id'] ?>_<?= $v['purchase_request_id'] ?>">Rate</button>

                                <!-- Rating Modal -->
                                <div class="modal fade" id="rateModal<?= $v['vendor_id'] ?>_<?= $v['purchase_request_id'] ?>" tabindex="-1">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <form method="post">
                                                <div class="modal-header">
                                                    <h5 class="modal-title">Rate <?= htmlspecialchars($v['company_name']) ?> (Request #<?= $v['purchase_request_id'] ?>)</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <input type="hidden" name="vendor_id" value="<?= $v['vendor_id'] ?>">
                                                    <input type="hidden" name="purchase_request_id" value="<?= $v['purchase_request_id'] ?>">
                                                    <div class="mb-3">
                                                        <label>Rating (1-5)</label>
                                                        <select name="rating" class="form-select" required>
                                                            <option value="">Select rating</option>
                                                            <option value="1">1</option>
                                                            <option value="2">2</option>
                                                            <option value="3">3</option>
                                                            <option value="4">4</option>
                                                            <option value="5">5</option>
                                                        </select>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label>Feedback</label>
                                                        <textarea name="feedback" class="form-control" rows="3" placeholder="Enter feedback"></textarea>
                                                    </div>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                    <button type="submit" name="submit_rating" class="btn btn-primary">Submit</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <!-- Completed Items Modal (Optional) -->
                            <?php if($items): ?>
                                <button class="btn btn-info btn-sm mt-1" data-bs-toggle="modal" data-bs-target="#itemsModal<?= $v['vendor_id'] ?>_<?= $v['purchase_request_id'] ?>">View Items</button>

                                <div class="modal fade" id="itemsModal<?= $v['vendor_id'] ?>_<?= $v['purchase_request_id'] ?>" tabindex="-1">
                                    <div class="modal-dialog modal-lg">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title">Items for <?= htmlspecialchars($v['company_name']) ?> (Request #<?= $v['purchase_request_id'] ?>)</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <div class="modal-body">
                                                <table class="table table-bordered">
                                                    <thead>
                                                        <tr>
                                                            <th>Item</th>
                                                            <th>Unit</th>
                                                            <th>Quantity</th>
                                                            <th>Price</th>
                                                            <th>Subtotal</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php 
                                                        $total_qty = 0;
                                                        $total_price = 0;
                                                        foreach($items as $item): 
                                                            $qty = isset($item['qty']) ? (int)$item['qty'] : 1;
                                                            $subtotal = $qty * (float)$item['price'];
                                                            $total_qty += $qty;
                                                            $total_price += $subtotal;
                                                        ?>
                                                        <tr>
                                                            <td><?= htmlspecialchars($item['name']) ?></td>
                                                            <td><?= htmlspecialchars($item['unit_type'] ?? 'Piece') ?></td>
                                                            <td><?= $qty ?></td>
                                                            <td>₱<?= number_format((float)$item['price'],2) ?></td>
                                                            <td>₱<?= number_format($subtotal,2) ?></td>
                                                        </tr>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                    <tfoot class="table-dark">
                                                        <tr>
                                                            <th colspan="2">Total</th>
                                                            <th><?= $total_qty ?></th>
                                                            <th></th>
                                                            <th>₱<?= number_format($total_price,2) ?></th>
                                                        </tr>
                                                    </tfoot>
                                                </table>
                                            </div>
                                            <div class="modal-footer">
                                                <button class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php if(!$hasCompleted) echo "<div class='alert alert-info'>No completed orders.</div>"; ?>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
