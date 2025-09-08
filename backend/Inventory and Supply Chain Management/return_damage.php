<?php
session_start();
require 'db.php';

// Fetch inventory items with their actual vendors (only items with quantity > 0)
$inventoryStmt = $pdo->query("
    SELECT i.*, vp.id AS product_id, v.id AS vendor_id, v.company_name 
    FROM inventory i
    JOIN vendor_products vp ON i.item_id = vp.id
    JOIN vendors v ON vp.vendor_id = v.id
    WHERE i.quantity > 0
    ORDER BY i.item_name ASC
");
$items = $inventoryStmt->fetchAll(PDO::FETCH_ASSOC);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $inventory_id = $_POST['inventory_id'];
    $vendor_id = $_POST['vendor_id'];
    $quantity = $_POST['quantity'];
    $reason = $_POST['reason'];

    $photoPath = null;
    if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
        $ext = pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION);
        $photoPath = 'uploads/returns/' . uniqid() . '.' . $ext;
        move_uploaded_file($_FILES['photo']['tmp_name'], $photoPath);
    }

    $stmt = $pdo->prepare("
        INSERT INTO return_requests (inventory_id, vendor_id, requested_by, quantity, reason, photo, status, requested_at, updated_at) 
        VALUES (?, ?, ?, ?, ?, ?, 'Pending', NOW(), NOW())
    ");
    $stmt->execute([$inventory_id, $vendor_id, $user_id, $quantity, $reason, $photoPath]);

    $success = "Return/Damage request submitted successfully.";
}

// Fetch all return requests
$requestStmt = $pdo->prepare("
    SELECT rr.id, rr.inventory_id, rr.vendor_id, rr.requested_by, rr.quantity, rr.reason, rr.photo, rr.status, rr.requested_at, rr.updated_at,
           i.item_name, v.company_name, u.username
    FROM return_requests rr
    JOIN inventory i ON rr.inventory_id = i.id
    JOIN vendors v ON rr.vendor_id = v.id
    JOIN users u ON rr.requested_by = u.user_id
    ORDER BY rr.id DESC
");
$requestStmt->execute();
$requests = $requestStmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Return & Damage Handling</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
body { font-family: Arial, sans-serif; }
.container { margin-left: 270px; }
.nav-tabs .nav-link.active { background-color: #0d6efd; color: #fff; }
.table img { max-width: 100px; }
</style>
</head>
<body class="bg-light">

<div class="main-sidebar">
    <?php include 'Inventory_dashboard.php'; ?>
</div>

<div class="container py-5">
    <h2 class="mb-4">Return & Damage Handling</h2>

    <?php if (isset($success)): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <!-- Tabs -->
    <ul class="nav nav-tabs mb-4" id="returnTab" role="tablist">
        <li class="nav-item">
            <a class="nav-link active" id="new-request-tab" data-bs-toggle="tab" href="#new-request" role="tab">New Request</a>
        </li>
        <li class="nav-item">
            <a class="nav-link" id="all-requests-tab" data-bs-toggle="tab" href="#all-requests" role="tab">All Requests</a>
        </li>
    </ul>

    <div class="tab-content">
        <!-- New Request Form -->
        <div class="tab-pane fade show active" id="new-request" role="tabpanel">
            <form method="post" enctype="multipart/form-data">
                <div class="mb-3">
                    <label for="inventory_id" class="form-label">Select Item</label>
                    <select name="inventory_id" id="inventory_id" class="form-select" required>
                        <option value="">-- Select Item --</option>
                        <?php foreach ($items as $item): ?>
                            <option value="<?= $item['id'] ?>" 
                                    data-vendor="<?= $item['vendor_id'] ?>" 
                                    data-vendorname="<?= htmlspecialchars($item['company_name']) ?>" 
                                    data-available="<?= $item['quantity'] ?>">
                                <?= htmlspecialchars($item['item_name']) ?> (Available: <?= $item['quantity'] ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="mb-3">
                    <label for="vendor_id" class="form-label">Vendor</label>
                    <input type="text" id="vendor_name" class="form-control" readonly>
                    <input type="hidden" name="vendor_id" id="vendor_id">
                </div>

                <div class="mb-3">
                    <label for="quantity" class="form-label">Quantity to Return</label>
                    <input type="number" name="quantity" id="quantity" class="form-control" min="1" required>
                    <small id="availableQty" class="form-text text-muted"></small>
                </div>

                <div class="mb-3">
                    <label for="reason" class="form-label">Reason</label>
                    <textarea name="reason" id="reason" class="form-control" rows="3" placeholder="Explain why this item is being returned or damaged" required></textarea>
                </div>

                <div class="mb-3">
                    <label for="photo" class="form-label">Upload Photo (optional)</label>
                    <input type="file" name="photo" id="photo" class="form-control" accept="image/*">
                </div>

                <button type="submit" class="btn btn-danger">Submit Return Request</button>
            </form>
        </div>

        <!-- All Requests Table -->
        <div class="tab-pane fade" id="all-requests" role="tabpanel">
            <div class="table-responsive mt-3">
                <table class="table table-bordered table-striped">
                    <thead class="table-dark">
                        <tr>
                            <th>#</th>
                            <th>Item</th>
                            <th>Vendor</th>
                            <th>Requested By</th>
                            <th>Quantity</th>
                            <th>Reason</th>
                            <th>Photo</th>
                            <th>Status</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($requests) === 0): ?>
                            <tr><td colspan="9" class="text-center">No requests found.</td></tr>
                        <?php else: ?>
                            <?php foreach ($requests as $req): ?>
                                <tr>
                                    <td><?= $req['id'] ?></td>
                                    <td><?= htmlspecialchars($req['item_name']) ?></td>
                                    <td><?= htmlspecialchars($req['company_name']) ?></td>
                                    <td><?= htmlspecialchars($req['username']) ?></td>
                                    <td><?= $req['quantity'] ?></td>
                                    <td><?= htmlspecialchars($req['reason']) ?></td>
                                    <td>
                                        <?php if ($req['photo']): ?>
                                            <img src="<?= htmlspecialchars($req['photo']) ?>" alt="Photo">
                                        <?php else: ?>
                                            N/A
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php
                                            switch (strtolower($req['status'])) {
                                                case 'pending': echo '<span class="badge bg-warning">Pending</span>'; break;
                                                case 'approved': echo '<span class="badge bg-success">Approved</span>'; break;
                                                case 'rejected': echo '<span class="badge bg-danger">Rejected</span>'; break;
                                                case 'returned': echo '<span class="badge bg-info">Returned</span>'; break;
                                                default: echo '<span class="badge bg-secondary">Unknown</span>';
                                            }
                                        ?>
                                    </td>
                                    <td>
                                        <?= isset($req['requested_at']) ? date('Y-m-d', strtotime($req['requested_at'])) : 'N/A' ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
$(function(){
    $('#inventory_id').on('change', function(){
        var selected = $(this).find(':selected');
        var vendorName = selected.data('vendorname');
        var vendorId = selected.data('vendor');
        var available = selected.data('available');

        $('#vendor_name').val(vendorName);
        $('#vendor_id').val(vendorId);
        $('#quantity').val(available);
        $('#quantity').attr('max', available);
        $('#availableQty').text('Available quantity: ' + available);
    });
});
</script>

</body>
</html>
