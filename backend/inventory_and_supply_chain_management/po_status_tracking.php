<?php
session_start();
require 'db.php';

// Handle vendor rating submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_rating'])) {
    $vendor_id = intval($_POST['vendor_id']);
    $po_number = trim($_POST['po_number']);
    $rating = intval($_POST['rating']);
    $feedback = trim($_POST['feedback']);

    $stmt = $pdo->prepare("INSERT INTO vendor_rating (vendor_id, purchase_order_number, rating, feedback) 
                           VALUES (?, ?, ?, ?)
                           ON DUPLICATE KEY UPDATE rating=?, feedback=?, created_at=NOW()");
    $stmt->execute([$vendor_id, $po_number, $rating, $feedback, $rating, $feedback]);

    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// Fetch vendors + purchase_order_number
$stmt = $pdo->prepare("
    SELECT v.id AS vendor_id, v.company_name, vo.purchase_order_number,
           vo.status, vo.is_received, MAX(vo.created_at) AS last_order_date
    FROM vendors v
    JOIN vendor_orders vo ON vo.vendor_id = v.id
    GROUP BY v.id, v.company_name, vo.purchase_order_number, vo.status, vo.is_received
    ORDER BY last_order_date DESC
");
$stmt->execute();
$vendors = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Function: status per vendor + PO
function getVendorStatus($pdo, $vendor_id, $po_number) {
    $stmt = $pdo->prepare("SELECT status, is_received FROM vendor_orders WHERE vendor_id = ? AND purchase_order_number = ?");
    $stmt->execute([$vendor_id, $po_number]);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($orders)) return ['text'=>'No Orders','color'=>'secondary','type'=>'none'];

    $is_received = array_column($orders, 'is_received');
    $statuses = array_column($orders, 'status');

    if (in_array(1, $is_received)) return ['text'=>'Received','color'=>'secondary','type'=>'completed'];
    if (in_array("Shipped", $statuses)) return ['text'=>'Shipped','color'=>'success','type'=>'processing'];
    if (in_array("Packed", $statuses)) return ['text'=>'Packed','color'=>'info','type'=>'processing'];
    if (in_array("Processing", $statuses)) return ['text'=>'Processing','color'=>'warning','type'=>'processing'];

    return ['text'=>'Processing','color'=>'secondary','type'=>'processing'];
}

// Fetch delivered/paid data
function getDeliveredData($pdo, $po_number) {
    $stmt = $pdo->prepare("
        SELECT rp.id, rp.paid_at, r.id AS receipt_id
        FROM receipts r
        JOIN receipt_payments rp ON rp.receipt_id = r.id
        WHERE r.order_id = ? AND rp.status = 'Paid'
        ORDER BY rp.id DESC LIMIT 1
    ");
    $stmt->execute([$po_number]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// Fetch order items from vendor_orders JSON
function getOrderItems($pdo, $po_number) {
    $stmt = $pdo->prepare("SELECT items FROM vendor_orders WHERE purchase_order_number = ?");
    $stmt->execute([$po_number]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$order || empty($order['items'])) return [];
    $items = json_decode($order['items'], true);
    return $items ?: [];
}

// Average ratings per vendor
$stmt = $pdo->prepare("SELECT vendor_id, purchase_order_number, AVG(rating) AS avg_rating FROM vendor_rating GROUP BY vendor_id, purchase_order_number");
$stmt->execute();
$avgRatings = $stmt->fetchAll(PDO::FETCH_GROUP|PDO::FETCH_UNIQUE|PDO::FETCH_ASSOC); // [vendor_id][po_number] => avg_rating
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>PO Status Tracking</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="po_status_tracking.css" rel="stylesheet">
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
            <button class="nav-link" id="completed-tab" data-bs-toggle="tab" data-bs-target="#completed" type="button">Completed / Received</button>
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
                        <th>PO Number</th>
                        <th>Last Order Date</th>
                        <th>Status</th>
                        <th>View Items</th>
                        <th>Avg Rating</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($vendors as $v): 
                        $vendorStatus = getVendorStatus($pdo, $v['vendor_id'], $v['purchase_order_number']);
                        if($vendorStatus['type'] !== 'processing') continue; // only processing
                        $hasProcessing = true;
                        $avgRating = isset($avgRatings[$v['vendor_id']][$v['purchase_order_number']]) ? number_format($avgRatings[$v['vendor_id']][$v['purchase_order_number']]['avg_rating'],1) : null;
                        $items = getOrderItems($pdo, $v['purchase_order_number']);
                    ?>
                    <tr>
                        <td><?= htmlspecialchars($v['company_name']) ?></td>
                        <td>#<?= htmlspecialchars($v['purchase_order_number']) ?></td>
                        <td><?= $v['last_order_date'] ? date("Y-m-d", strtotime($v['last_order_date'])) : '-' ?></td>
                        <td><span class="badge bg-<?= $vendorStatus['color'] ?>"><?= $vendorStatus['text'] ?></span></td>
                        <td>
                            <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#itemsModal<?= $v['vendor_id'] ?>_<?= $v['purchase_order_number'] ?>">View Items</button>
                        </td>
                        <td><?= $avgRating ? $avgRating.' ⭐' : '-' ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php if(!$hasProcessing) echo "<div class='alert alert-info'>No processing orders.</div>"; ?>
        </div>

        <!-- Completed / Received Tab -->
        <div class="tab-pane fade" id="completed" role="tabpanel">
            <?php $hasCompleted = false; ?>
            <table class="table table-bordered table-hover bg-white mb-4">
                <thead class="table-dark">
                    <tr>
                        <th>Company Name</th>
                        <th>PO Number</th>
                        <th>Last Order Date</th>
                        <th>Status</th>
                        <th>Delivered / Paid</th>
                        <th>View Receipt</th>
                        <th>Rating</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($vendors as $v):
                        $vendorStatus = getVendorStatus($pdo, $v['vendor_id'], $v['purchase_order_number']);
                        if($vendorStatus['type'] !== 'completed') continue; // only completed
                        $hasCompleted = true;
                        $deliveredData = getDeliveredData($pdo, $v['purchase_order_number']);

                        // Check if already rated
                        $stmt = $pdo->prepare("SELECT * FROM vendor_rating WHERE vendor_id=? AND purchase_order_number=?");
                        $stmt->execute([$v['vendor_id'], $v['purchase_order_number']]);
                        $ratingData = $stmt->fetch(PDO::FETCH_ASSOC);

                        $items = getOrderItems($pdo, $v['purchase_order_number']);
                    ?>
                    <tr>
                        <td><?= htmlspecialchars($v['company_name']) ?></td>
                        <td>#<?= htmlspecialchars($v['purchase_order_number']) ?></td>
                        <td><?= $v['last_order_date'] ? date("Y-m-d", strtotime($v['last_order_date'])) : '-' ?></td>
                        <td><span class="badge bg-secondary"><?= $vendorStatus['text'] ?></span></td>
                        <td><?= $deliveredData ? date("Y-m-d H:i", strtotime($deliveredData['paid_at'])) : 'N/A' ?></td>
                        <td>
                            <?php if($deliveredData): ?>
                                <a href="receipt_view.php?receipt_id=<?= $deliveredData['receipt_id'] ?>" target="_blank" class="btn btn-success btn-sm">📄 View Receipt</a>
                            <?php else: ?>
                                <span class="text-muted">N/A</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if($ratingData): ?>
                                <span>⭐ <?= $ratingData['rating'] ?></span><br>
                                <small><?= htmlspecialchars($ratingData['feedback']) ?></small>
                            <?php else: ?>
                                <button class="btn btn-warning btn-sm" data-bs-toggle="modal" data-bs-target="#rateModal<?= $v['vendor_id'] ?>_<?= $v['purchase_order_number'] ?>">Rate</button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php if(!$hasCompleted) echo "<div class='alert alert-info'>No completed/received orders.</div>"; ?>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
