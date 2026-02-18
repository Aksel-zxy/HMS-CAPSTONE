<?php
include '../../SQL/config.php';

// Get request ID from URL
$request_id = $_GET['request_id'] ?? 0;

// Fetch the request
$stmt = $pdo->prepare("SELECT * FROM department_request WHERE id=? LIMIT 1");
$stmt->execute([$request_id]);
$request = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$request) {
    echo "<h3>Request not found!</h3>";
    exit;
}

// Fetch items for the request
$stmtItems = $pdo->prepare("SELECT * FROM department_request_items WHERE request_id=? ORDER BY id ASC");
$stmtItems->execute([$request_id]);
$items = $stmtItems->fetchAll(PDO::FETCH_ASSOC);

// Calculate totals
$total_items = 0;
$total_cost = 0;
foreach ($items as $item) {
    $qty = (int)$item['received_quantity'];
    $price = (float)$item['price'];
    $total_items += $qty;
    $total_cost += $qty * $price;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Delivery Receipt - Request #<?= $request['id'] ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
body { font-family:'Segoe UI',sans-serif; background:#f8fafc; padding:20px;}
.receipt-card { background:#fff; padding:30px; border-radius:12px; box-shadow:0 5px 18px rgba(0,0,0,0.08);}
.receipt-header { border-bottom:2px solid #333; padding-bottom:15px; margin-bottom:20px;}
.table th, .table td { vertical-align: middle !important; text-align:center;}
.signature { margin-top:50px; display:flex; justify-content:space-between; }
.signature div { width:30%; text-align:center;}
@media print {
    .no-print { display:none; }
}
</style>
</head>
<body>

<div class="receipt-card">
    <div class="receipt-header text-center">
        <h2>Hospital Inventory & Supply Chain</h2>
        <h4>Delivery Receipt</h4>
        <small>Request ID: <?= $request['id'] ?> | Date: <?= date('Y-m-d H:i') ?></small>
    </div>

    <div class="mb-4">
        <p><strong>Department:</strong> <?= htmlspecialchars($request['department']) ?></p>
        <p><strong>User ID:</strong> <?= htmlspecialchars($request['user_id']) ?></p>
        <p><strong>Status:</strong> <?= ucfirst($request['status']) ?></p>
        <p><strong>Purchased At:</strong> <?= $request['purchased_at'] ?></p>
    </div>

    <div class="table-responsive">
    <table class="table table-bordered">
        <thead class="table-light">
            <tr>
                <th>#</th>
                <th>Item Name</th>
                <th>Approved Qty</th>
                <th>Received Qty</th>
                <th>Unit</th>
                <th>Price</th>
                <th>Total</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach($items as $index => $item):
            $received_qty = (int)$item['received_quantity'];
            $price = (float)$item['price'];
            $total = $received_qty * $price;
        ?>
            <tr>
                <td><?= $index + 1 ?></td>
                <td><?= htmlspecialchars($item['item_name']) ?></td>
                <td><?= $item['approved_quantity'] ?></td>
                <td><?= $received_qty ?></td>
                <td><?= $item['unit'] ?? 'pcs' ?></td>
                <td>₱ <?= number_format($price,2) ?></td>
                <td>₱ <?= number_format($total,2) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr>
                <th colspan="3">Total Items</th>
                <th><?= $total_items ?></th>
                <th colspan="2">Grand Total</th>
                <th>₱ <?= number_format($total_cost,2) ?></th>
            </tr>
        </tfoot>
    </table>
    </div>

    <div class="signature">
        <div>
            <p>Prepared By:</p>
            <br><br>
            <p>_______________________</p>
        </div>
        <div>
            <p>Received By:</p>
            <br><br>
            <p>_______________________</p>
        </div>
        <div>
            <p>Date:</p>
            <br><br>
            <p>_______________________</p>
        </div>
    </div>

    <div class="text-center mt-4 no-print">
        <button onclick="window.print()" class="btn btn-primary"><i class="bi bi-printer"></i> Print Receipt</button>
        <a href="order_receive.php" class="btn btn-secondary">Back</a>
    </div>
</div>

</body>
</html>
