<?php
session_start();
require 'db.php';

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
    $stmt = $pdo->prepare("
        SELECT status 
        FROM vendor_orders 
        WHERE vendor_id = ? AND purchase_request_id = ?
    ");
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

// Helper: fetch the latest Paid receipt for a given purchase_request_id
function getDeliveredData($pdo, $purchase_request_id) {
    $stmt = $pdo->prepare("
        SELECT id, paid_at, receipt_id
        FROM receipt_payments 
        WHERE receipt_id = ? AND status='Paid'
        ORDER BY id DESC LIMIT 1
    ");
    $stmt->execute([$purchase_request_id]);
    $data = $stmt->fetch(PDO::FETCH_ASSOC);

    // fallback: if no match, get latest Paid receipt
    if (!$data) {
        $stmt = $pdo->query("
            SELECT id, paid_at, receipt_id
            FROM receipt_payments 
            WHERE status='Paid'
            ORDER BY id DESC LIMIT 1
        ");
        $data = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    return $data;
}
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
    <?php include 'Inventory_dashboard.php'; ?>
</div>

<div class="container py-5">
    <h2 class="mb-4">PO Status Tracking</h2>

    <!-- Nav tabs -->
    <ul class="nav nav-tabs mb-3" id="vendorTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="processing-tab" data-bs-toggle="tab" data-bs-target="#processing" type="button" role="tab" aria-controls="processing" aria-selected="true">Processing</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="completed-tab" data-bs-toggle="tab" data-bs-target="#completed" type="button" role="tab" aria-controls="completed" aria-selected="false">Completed</button>
        </li>
    </ul>

    <div class="tab-content">
        <!-- Processing Tab -->
        <div class="tab-pane fade show active" id="processing" role="tabpanel" aria-labelledby="processing-tab">
            <?php 
            $hasProcessing = false;
            foreach ($vendors as $v):
                $vendorStatus = getVendorStatus($pdo, $v['vendor_id'], $v['purchase_request_id']);
                if($vendorStatus['type'] !== 'processing') continue;
                $hasProcessing = true;

                $stmt = $pdo->prepare("
                    SELECT vo.*, vp.item_name, vp.item_description, vp.price, vp.picture
                    FROM vendor_orders vo
                    JOIN vendor_products vp ON vo.item_id = vp.id
                    WHERE vo.vendor_id = ? AND vo.purchase_request_id = ?
                    ORDER BY vo.created_at DESC
                ");
                $stmt->execute([$v['vendor_id'], $v['purchase_request_id']]);
                $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

                $total_price = 0;
                foreach($orders as $o) {
                    if (!in_array($o['status'], ['Received','Completed'])) {
                        $total_price += $o['quantity'] * $o['price'];
                    }
                }
            ?>
            <table class="table table-bordered table-hover bg-white mb-4">
                <thead class="table-dark">
                    <tr>
                        <th>Company Name</th>
                        <th>Request ID</th>
                        <th>Last Order Date</th>
                        <th>Total Quantity</th>
                        <th>Status</th>
                        <th>View Items</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><?= htmlspecialchars($v['company_name']) ?></td>
                        <td>#<?= (int)$v['purchase_request_id'] ?></td>
                        <td><?= $v['last_order_date'] ? date("Y-m-d", strtotime($v['last_order_date'])) : '-' ?></td>
                        <td><?= (int)$v['total_quantity'] ?></td>
                        <td><span class="badge bg-<?= $vendorStatus['color'] ?>"><?= $vendorStatus['text'] ?></span></td>
                        <td><button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#vendorModal<?= $v['vendor_id'] ?>_<?= $v['purchase_request_id'] ?>">View Items</button></td>
                    </tr>
                </tbody>
            </table>

            <!-- Modal -->
            <div class="modal fade" id="vendorModal<?= $v['vendor_id'] ?>_<?= $v['purchase_request_id'] ?>" tabindex="-1">
                <div class="modal-dialog modal-xl modal-dialog-scrollable">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Items from <?= htmlspecialchars($v['company_name']) ?> (Request #<?= $v['purchase_request_id'] ?>)</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div class="row g-3">
                                <?php foreach ($orders as $o): ?>
                                    <div class="col-md-4">
                                        <div class="card item-card shadow-sm">
                                            <?php if(!empty($o['picture'])): ?>
                                                <img src="<?= htmlspecialchars($o['picture']) ?>" class="card-item-img" alt="Item">
                                            <?php endif; ?>
                                            <div class="card-body">
                                                <h6 class="card-title"><?= htmlspecialchars($o['item_name']) ?></h6>
                                                <p class="small"><?= htmlspecialchars($o['item_description']) ?></p>
                                                <p><strong>Qty:</strong> <?= (int)$o['quantity'] ?> | <strong>Price:</strong> ₱<?= number_format($o['price'],2) ?></p>
                                                <p><strong>Status:</strong> <span class="badge bg-<?= ($o['status']=='Shipped'?'success':($o['status']=='Completed'?'dark':'info')) ?>"><?= htmlspecialchars($o['status']) ?></span></p>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <div class="modal-footer d-flex justify-content-between">
                            <span><strong>Total: ₱<?= number_format($total_price,2) ?></strong></span>
                            <button class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        </div>
                    </div>
                </div>
            </div>

            <?php endforeach; ?>
            <?php if(!$hasProcessing): ?><div class="alert alert-info">No processing orders.</div><?php endif; ?>
        </div>

        <!-- Completed Tab -->
        <div class="tab-pane fade" id="completed" role="tabpanel" aria-labelledby="completed-tab">
            <?php 
            $hasCompleted = false;
            foreach ($vendors as $v):
                $vendorStatus = getVendorStatus($pdo, $v['vendor_id'], $v['purchase_request_id']);
                if($vendorStatus['type'] !== 'completed') continue;
                $hasCompleted = true;

                $stmt = $pdo->prepare("
                    SELECT vo.*, vp.item_name, vp.item_description, vp.price, vp.picture
                    FROM vendor_orders vo
                    JOIN vendor_products vp ON vo.item_id = vp.id
                    WHERE vo.vendor_id = ? AND vo.purchase_request_id = ? AND vo.status IN ('Completed','Received')
                    ORDER BY vo.created_at DESC
                ");
                $stmt->execute([$v['vendor_id'], $v['purchase_request_id']]);
                $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

                $total_price = 0;
                foreach($orders as $o) {
                    $total_price += $o['quantity'] * $o['price'];
                }

                $deliveredData = getDeliveredData($pdo, $v['purchase_request_id']);
            ?>
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
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><?= htmlspecialchars($v['company_name']) ?></td>
                        <td>#<?= (int)$v['purchase_request_id'] ?></td>
                        <td><?= $v['last_order_date'] ? date("Y-m-d", strtotime($v['last_order_date'])) : '-' ?></td>
                        <td><?= (int)$v['total_quantity'] ?></td>
                        <td><span class="badge bg-dark">Completed</span></td>
                        <td>
                            <?= $deliveredData ? date("Y-m-d H:i", strtotime($deliveredData['paid_at'])) : 'N/A' ?>
                        </td>
                        <td>
                            <?php if($deliveredData): ?>
                                <a href="receipt_view.php?id=<?= $deliveredData['id'] ?>" target="_blank" class="btn btn-success btn-sm">📄 View Receipt</a>
                            <?php else: ?>
                                <span class="text-muted">N/A</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                </tbody>
            </table>
            <?php endforeach; ?>
            <?php if(!$hasCompleted): ?><div class="alert alert-info">No completed orders.</div><?php endif; ?>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
