<?php
session_start();
require 'db.php';

// Fetch all vendors and their latest order info
$stmt = $pdo->prepare("
    SELECT v.id AS vendor_id, v.company_name, 
           SUM(vo.quantity) AS total_quantity,
           MAX(vo.created_at) AS last_order_date
    FROM vendors v
    LEFT JOIN vendor_orders vo ON vo.vendor_id = v.id
    GROUP BY v.id, v.company_name
    ORDER BY last_order_date DESC
");
$stmt->execute();
$vendors = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Function to calculate vendor status
function getVendorStatus($pdo, $vendor_id) {
    $stmt = $pdo->prepare("
        SELECT checklist, status 
        FROM vendor_orders 
        WHERE vendor_id = ?
    ");
    $stmt->execute([$vendor_id]);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($orders)) {
        return ['text'=>'No Orders','color'=>'secondary','type'=>'none'];
    }

    // Active orders (still in progress)
    $activeOrders = array_filter($orders, function($o) {
        return !in_array($o['status'], ['Received','Completed']);
    });

    if (empty($activeOrders)) {
        return ['text'=>'Completed','color'=>'dark','type'=>'completed'];
    }

    $allStages = ['Packed', 'Labeled', 'Ready to Ship', 'Shipped'];
    $statusCounts = ['Packed'=>0,'Labeled'=>0,'Ready to Ship'=>0,'Shipped'=>0];

    foreach($activeOrders as $o){
        $checklist = json_decode($o['checklist'], true) ?? [];
        foreach($allStages as $stage){
            if(in_array($stage, $checklist) || $o['status']==$stage) $statusCounts[$stage]++;
        }
    }

    if($statusCounts['Shipped'] == count($activeOrders)) return ['text'=>'Shipped','color'=>'success','type'=>'processing'];
    if($statusCounts['Ready to Ship'] == count($activeOrders)) return ['text'=>'Ready to Ship','color'=>'warning','type'=>'processing'];
    if($statusCounts['Labeled'] == count($activeOrders)) return ['text'=>'Labeled','color'=>'primary','type'=>'processing'];
    if($statusCounts['Packed'] == count($activeOrders)) return ['text'=>'Packed','color'=>'info','type'=>'processing'];

    return ['text'=>'Processing','color'=>'secondary','type'=>'processing'];
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
            <button class="nav-link active" id="processing-tab" data-bs-toggle="tab" data-bs-target="#processing" type="button" role="tab">Processing</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="completed-tab" data-bs-toggle="tab" data-bs-target="#completed" type="button" role="tab">Completed</button>
        </li>
    </ul>

    <div class="tab-content">
        <!-- Processing Vendors -->
        <div class="tab-pane fade show active" id="processing" role="tabpanel">
            <?php 
            $hasProcessing = false;
            foreach ($vendors as $v):
                $vendorStatus = getVendorStatus($pdo, $v['vendor_id']);
                if($vendorStatus['type'] !== 'processing') continue;
                $hasProcessing = true;

                // Fetch all orders for modal
                $stmt = $pdo->prepare("
                    SELECT vo.*, vp.item_name, vp.item_description, vp.price, vp.picture
                    FROM vendor_orders vo
                    JOIN vendor_products vp ON vo.item_id = vp.id
                    WHERE vo.vendor_id = ?
                    ORDER BY vo.created_at DESC
                ");
                $stmt->execute([$v['vendor_id']]);
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
                        <th>Last Order Date</th>
                        <th>Total Quantity</th>
                        <th>Status</th>
                        <th>View</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><?= htmlspecialchars($v['company_name']) ?></td>
                        <td><?= $v['last_order_date'] ? date("Y-m-d", strtotime($v['last_order_date'])) : '-' ?></td>
                        <td><?= (int)$v['total_quantity'] ?></td>
                        <td><span class="text-white px-2 py-1 rounded bg-<?= $vendorStatus['color'] ?>"><?= $vendorStatus['text'] ?></span></td>
                        <td>
                            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#vendorModal<?= $v['vendor_id'] ?>">View Items</button>
                        </td>
                    </tr>
                </tbody>
            </table>

            <!-- Modal -->
            <div class="modal fade" id="vendorModal<?= $v['vendor_id'] ?>" tabindex="-1" aria-labelledby="vendorModalLabel<?= $v['vendor_id'] ?>" aria-hidden="true">
                <div class="modal-dialog modal-xl modal-dialog-scrollable">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Items from <?= htmlspecialchars($v['company_name']) ?></h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div class="row g-3">
                                <?php foreach ($orders as $o): ?>
                                    <div class="col-md-4">
                                        <div class="card item-card shadow-sm">
                                            <?php if(!empty($o['picture'])): ?>
                                                <img src="<?= htmlspecialchars($o['picture']) ?>" class="card-item-img" alt="Item Image">
                                            <?php endif; ?>
                                            <div class="card-body">
                                                <h6 class="card-title"><?= htmlspecialchars($o['item_name']) ?></h6>
                                                <p class="card-text small"><?= htmlspecialchars($o['item_description']) ?></p>
                                                <p class="card-text"><strong>Quantity:</strong> <?= (int)$o['quantity'] ?> | <strong>Unit Price:</strong> ₱<?= number_format($o['price'],2) ?></p>
                                                <p class="card-text"><strong>Status:</strong>
                                                    <span class="badge bg-<?php 
                                                        if($o['status']=='Shipped'){ echo 'success'; } 
                                                        elseif($o['status']=='Completed'){ echo 'dark'; } 
                                                        else { echo 'info'; } ?>">
                                                        <?= htmlspecialchars($o['status']) ?>
                                                    </span>
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <div class="modal-footer d-flex justify-content-between">
                            <span><strong>Active Orders Total: ₱<?= number_format($total_price,2) ?></strong></span>
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
            <?php if(!$hasProcessing): ?>
                <div class="alert alert-info">No process orders.</div>
            <?php endif; ?>
        </div>

        <!-- Completed Vendors -->
        <div class="tab-pane fade" id="completed" role="tabpanel">
            <?php 
            $hasCompleted = false;
            foreach ($vendors as $v):
                $vendorStatus = getVendorStatus($pdo, $v['vendor_id']);
                if($vendorStatus['type'] !== 'completed') continue;
                $hasCompleted = true;

                // Fetch completed orders for modal
                $stmt = $pdo->prepare("
                    SELECT vo.*, vp.item_name, vp.item_description, vp.price, vp.picture
                    FROM vendor_orders vo
                    JOIN vendor_products vp ON vo.item_id = vp.id
                    WHERE vo.vendor_id = ? AND vo.status IN ('Completed','Received')
                    ORDER BY vo.created_at DESC
                ");
                $stmt->execute([$v['vendor_id']]);
                $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

                $total_price = 0;
                foreach($orders as $o) {
                    $total_price += $o['quantity'] * $o['price'];
                }
            ?>
            <table class="table table-bordered table-hover bg-white mb-4">
                <thead class="table-dark">
                    <tr>
                        <th>Company Name</th>
                        <th>Last Order Date</th>
                        <th>Total Quantity</th>
                        <th>Status</th>
                        <th>View</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><?= htmlspecialchars($v['company_name']) ?></td>
                        <td><?= $v['last_order_date'] ? date("Y-m-d", strtotime($v['last_order_date'])) : '-' ?></td>
                        <td><?= (int)$v['total_quantity'] ?></td>
                        <td><span class="text-white px-2 py-1 rounded bg-dark">Completed</span></td>
                        <td>
                            <button class="btn btn-secondary" data-bs-toggle="modal" data-bs-target="#completedModal<?= $v['vendor_id'] ?>">View Items</button>
                        </td>
                    </tr>
                </tbody>
            </table>

            <!-- Completed Modal -->
            <div class="modal fade" id="completedModal<?= $v['vendor_id'] ?>" tabindex="-1" aria-labelledby="completedModalLabel<?= $v['vendor_id'] ?>" aria-hidden="true">
                <div class="modal-dialog modal-xl modal-dialog-scrollable">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Completed Items from <?= htmlspecialchars($v['company_name']) ?></h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div class="row g-3">
                                <?php foreach ($orders as $o): ?>
                                    <div class="col-md-4">
                                        <div class="card item-card shadow-sm">
                                            <?php if(!empty($o['picture'])): ?>
                                                <img src="<?= htmlspecialchars($o['picture']) ?>" class="card-item-img" alt="Item Image">
                                            <?php endif; ?>
                                            <div class="card-body">
                                                <h6 class="card-title"><?= htmlspecialchars($o['item_name']) ?></h6>
                                                <p class="card-text small"><?= htmlspecialchars($o['item_description']) ?></p>
                                                <p class="card-text"><strong>Quantity:</strong> <?= (int)$o['quantity'] ?> | <strong>Unit Price:</strong> ₱<?= number_format($o['price'],2) ?></p>
                                                <p class="card-text"><strong>Status:</strong>
                                                    <span class="badge bg-dark"><?= htmlspecialchars($o['status']) ?></span>
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <div class="modal-footer d-flex justify-content-between">
                            <span><strong>Total Completed Orders: ₱<?= number_format($total_price,2) ?></strong></span>
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
            <?php if(!$hasCompleted): ?>
                <div class="alert alert-info">No completed orders.</div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
