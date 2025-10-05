<?php
require 'db.php';

$payment_id = $_GET['id'] ?? 0;
$receipt_id = $_GET['receipt_id'] ?? 0;

if ($payment_id) {
    $stmt = $pdo->prepare("
        SELECT rp.*, r.id AS receipt_id, r.subtotal, r.vat, r.total, r.created_at AS receipt_date,
               v.company_name, v.company_address, v.contact_name, v.phone, v.email, v.tin_vat
        FROM receipt_payments rp
        JOIN receipts r ON rp.receipt_id = r.id
        JOIN vendors v ON r.vendor_id = v.id
        WHERE rp.id = ?
    ");
    $stmt->execute([$payment_id]);
    $payment = $stmt->fetch(PDO::FETCH_ASSOC);
} elseif ($receipt_id) {
    $stmt = $pdo->prepare("
        SELECT NULL AS id, 'Pending' AS status, NULL AS paid_at,
               r.id AS receipt_id, r.subtotal, r.vat, r.total, r.created_at AS receipt_date,
               v.company_name, v.company_address, v.contact_name, v.phone, v.email, v.tin_vat
        FROM receipts r
        JOIN vendors v ON r.vendor_id = v.id
        WHERE r.id = ?
    ");
    $stmt->execute([$receipt_id]);
    $payment = $stmt->fetch(PDO::FETCH_ASSOC);
}

if (!$payment) {
    die("❌ Receipt not found");
}

// Fetch receipt items
$stmt = $pdo->prepare("SELECT * FROM receipt_items WHERE receipt_id = ?");
$stmt->execute([$payment['receipt_id']]);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Receipt #<?= $payment['receipt_id'] ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<div class="container py-5">
    <div class="card shadow-lg">
        <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
            <h4>🧾 Receipt #<?= $payment['receipt_id'] ?></h4>
            <?php if ($payment['id']): ?>
                <span class="fw-bold">Payment Ref: <?= $payment['id'] ?></span>
            <?php endif; ?>
        </div>
        <div class="card-body">

            <!-- Vendor Information -->
            <h5 class="mb-3">Vendor Information</h5>
            <p><strong>Company:</strong> <?= htmlspecialchars($payment['company_name']) ?></p>
            <p><strong>Address:</strong> <?= htmlspecialchars($payment['company_address']) ?></p>
            <p><strong>Contact Person:</strong> <?= htmlspecialchars($payment['contact_name']) ?></p>
            <p><strong>Phone:</strong> <?= htmlspecialchars($payment['phone']) ?></p>
            <p><strong>Email:</strong> <?= htmlspecialchars($payment['email']) ?></p>
            <p><strong>TIN/VAT:</strong> <?= htmlspecialchars($payment['tin_vat']) ?></p>
            <p><strong>Date Issued:</strong> <?= date("Y-m-d H:i", strtotime($payment['receipt_date'])) ?></p>

            <!-- Items Table -->
            <table class="table table-bordered mt-4">
                <thead class="table-dark">
                    <tr>
                        <th>Item</th>
                        <th>Quantity Received</th>
                        <th>Unit</th>
                        <th>Price</th>
                        <th>Subtotal</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $it): ?>
                        <tr>
                            <td><?= htmlspecialchars($it['item_name']) ?></td>
                            <td><?= $it['quantity_received'] ?></td>
                            <td>
                                <?= htmlspecialchars($it['unit_type']) ?>
                                <?php if ($it['unit_type'] === "Box" && $it['pcs_per_box']): ?>
                                    (<?= (int)$it['pcs_per_box'] ?> pcs)
                                <?php endif; ?>
                            </td>
                            <td>₱<?= number_format($it['price'], 2) ?></td>
                            <td>₱<?= number_format($it['subtotal'], 2) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <!-- Totals -->
            <div class="text-end mt-3">
                <p><strong>Subtotal:</strong> ₱<?= number_format($payment['subtotal'], 2) ?></p>
                <p><strong>VAT (12%):</strong> ₱<?= number_format($payment['vat'], 2) ?></p>
                <h5><strong>Total:</strong> ₱<?= number_format($payment['total'], 2) ?></h5>
            </div>

            <!-- Payment Status -->
            <div class="mt-4">
                <?php if ($payment['status'] === 'Paid'): ?>
                    <span class="badge bg-success">✅ Paid on <?= date("Y-m-d H:i", strtotime($payment['paid_at'])) ?></span>
                <?php else: ?>
                    <span class="badge bg-warning">⏳ Pending</span>
                <?php endif; ?>
            </div>

        </div>
    </div>
</div>

</body>
</html>
