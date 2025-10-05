<?php
session_start();
require 'db.php';

if (!isset($_SESSION['vendor_id'])) {
    header("Location: vendor_login.php");
    exit;
}
$vendor_id = $_SESSION['vendor_id'];

// Fetch combined receipts
$stmt = $pdo->prepare("
    SELECT r.id AS receipt_id, r.created_at, r.total, rp.status AS payment_status
    FROM receipts r
    LEFT JOIN receipt_payments rp ON r.id = rp.receipt_id
    WHERE r.vendor_id=?
    ORDER BY r.created_at DESC
");
$stmt->execute([$vendor_id]);
$receipts = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Delivered Receipts</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container py-5">
    <h2 class="mb-4">Delivered Receipts</h2>

    <?php if(empty($receipts)): ?>
        <div class="alert alert-info">No delivered receipts yet.</div>
    <?php else: ?>
        <table class="table table-bordered bg-white shadow">
            <thead class="table-dark">
                <tr>
                    <th>Receipt ID</th>
                    <th>Date</th>
                    <th>Total</th>
                    <th>Payment Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($receipts as $r): ?>
                <tr>
                    <td><?= $r['receipt_id'] ?></td>
                    <td><?= $r['created_at'] ?></td>
                    <td>₱<?= number_format($r['total'],2) ?></td>
                    <td>
                        <span class="badge bg-<?= $r['payment_status']=='Paid'?'success':'warning' ?>">
                            <?= $r['payment_status'] ?? 'Pending' ?>
                        </span>
                    </td>
                    <td>
                        <button class="btn btn-info btn-sm" data-bs-toggle="modal" data-bs-target="#receiptModal<?= $r['receipt_id'] ?>">View Receipt</button>
                    </td>
                </tr>

                <!-- Receipt Modal -->
                <div class="modal fade" id="receiptModal<?= $r['receipt_id'] ?>" tabindex="-1">
                    <div class="modal-dialog modal-lg">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">Receipt #<?= $r['receipt_id'] ?></h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <?php
                                $stmt2 = $pdo->prepare("SELECT * FROM receipt_items WHERE receipt_id=?");
                                $stmt2->execute([$r['receipt_id']]);
                                $items = $stmt2->fetchAll(PDO::FETCH_ASSOC);
                                ?>
                                <table class="table table-bordered">
                                    <thead class="table-dark">
                                        <tr>
                                            <th>Item</th>
                                            <th>Qty Received</th>
                                            <th>Price</th>
                                            <th>Subtotal</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach($items as $it): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($it['item_name']) ?></td>
                                            <td><?= $it['quantity_received'] ?></td>
                                            <td>₱<?= number_format($it['price'],2) ?></td>
                                            <td>₱<?= number_format($it['subtotal'],2) ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                                <p class="text-end"><strong>Total:</strong> ₱<?= number_format($r['total'],2) ?></p>
                            </div>
                        </div>
                    </div>
                </div>

                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
