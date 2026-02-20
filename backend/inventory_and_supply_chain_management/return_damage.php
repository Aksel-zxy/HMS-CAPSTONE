<?php
session_start();
include '../../SQL/config.php';

// Assume user_id comes from session
$user_id = $_SESSION['user_id'] ?? null;

// Fetch inventory items (quantity > 0)
$inventoryStmt = $pdo->query("SELECT * FROM inventory WHERE quantity > 0 ORDER BY item_name ASC");
$items = $inventoryStmt->fetchAll(PDO::FETCH_ASSOC);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $inventory_id = (int)$_POST['inventory_id'];
    $quantity = (int)$_POST['quantity'];
    $reason = trim($_POST['reason']);

    // Check inventory
    $checkStmt = $pdo->prepare("SELECT quantity FROM inventory WHERE id = ?");
    $checkStmt->execute([$inventory_id]);
    $itemData = $checkStmt->fetch(PDO::FETCH_ASSOC);

    if (!$itemData) {
        $error = "Invalid item selected.";
    } elseif ($quantity < 1) {
        $error = "Quantity must be at least 1.";
    } elseif ($quantity > $itemData['quantity']) {
        $error = "Quantity cannot exceed available stock ({$itemData['quantity']}).";
    } else {
        // Handle photo upload
        $photoPath = null;
        if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {

            // Ensure uploads/returns folder exists
            $uploadDir = 'uploads/returns/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }

            $ext = pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION);
            $photoPath = $uploadDir . uniqid() . '.' . $ext;

            if (!move_uploaded_file($_FILES['photo']['tmp_name'], $photoPath)) {
                $error = "Failed to upload photo. Please check folder permissions.";
            }
        }

        if (!isset($error)) {
            // Auto-approve logic: if reason contains "damage" or "defective" => approve, else reject
            $reasonLower = strtolower($reason);
            if (strpos($reasonLower, 'damage') !== false || strpos($reasonLower, 'defective') !== false || strlen($reason) > 5) {
                $status = 'Approved';
            } else {
                $status = 'Rejected';
            }

            // Insert into DB
            $stmt = $pdo->prepare("
                INSERT INTO return_requests (inventory_id, requested_by, quantity, reason, photo, status, requested_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())
            ");
            $stmt->execute([$inventory_id, $user_id, $quantity, $reason, $photoPath, $status]);

            $success = "Return request submitted successfully and automatically marked as <strong>{$status}</strong>.";
        }
    }
}

// Fetch all return requests
$requestStmt = $pdo->prepare("
    SELECT rr.id, rr.inventory_id, rr.requested_by, rr.quantity, rr.reason, rr.photo, rr.status, rr.requested_at,
           i.item_name, u.username
    FROM return_requests rr
    JOIN inventory i ON rr.inventory_id = i.id
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
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Return & Damage Requests</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
body { font-family: 'Segoe UI', sans-serif; background: #f8f9fa; }
.main-container { margin-left: 270px; padding: 40px 20px; }
.table img { max-width: 80px; border-radius: 5px; }
.nav-tabs .nav-link.active { background-color: #0d6efd; color: #fff; }
</style>
</head>
<body>

<div class="main-sidebar">
    <?php include 'inventory_sidebar.php'; ?>
</div>

<div class="main-container">
    <h2 class="mb-4">Return & Damage Requests</h2>

    <?php if (isset($success)): ?>
        <div class="alert alert-success"><?= $success ?></div>
    <?php elseif (isset($error)): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

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
            <div class="card shadow-sm mb-4">
                <div class="card-body">
                    <form method="post" enctype="multipart/form-data">
                        <div class="mb-3">
                            <label for="inventory_id" class="form-label">Select Item</label>
                            <select name="inventory_id" id="inventory_id" class="form-select" required>
                                <option value="">-- Select Item --</option>
                                <?php foreach ($items as $item): ?>
                                    <option value="<?= $item['id'] ?>" data-available="<?= $item['quantity'] ?>">
                                        <?= htmlspecialchars($item['item_name']) ?> (Available: <?= $item['quantity'] ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="quantity" class="form-label">Quantity to Return</label>
                            <input type="number" name="quantity" id="quantity" class="form-control" min="1" required>
                            <small id="availableQty" class="text-muted"></small>
                        </div>

                        <div class="mb-3">
                            <label for="reason" class="form-label">Reason</label>
                            <textarea name="reason" id="reason" class="form-control" rows="3" placeholder="Explain why this item is being returned or damaged" required></textarea>
                        </div>

                        <div class="mb-3">
                            <label for="photo" class="form-label">Upload Photo</label>
                            <input type="file" name="photo" id="photo" class="form-control" accept="image/*" required>
                        </div>

                        <button type="submit" class="btn btn-danger">Submit Return Request</button>
                    </form>
                </div>
            </div>
        </div>

        <!-- All Requests Table -->
        <div class="tab-pane fade" id="all-requests" role="tabpanel">
            <div class="table-responsive">
                <table class="table table-bordered table-striped align-middle">
                    <thead class="table-dark">
                        <tr>
                            <th>#</th>
                            <th>Item</th>
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
                            <tr><td colspan="8" class="text-center">No requests found.</td></tr>
                        <?php else: ?>
                            <?php foreach ($requests as $req): ?>
                                <tr>
                                    <td><?= $req['id'] ?></td>
                                    <td><?= htmlspecialchars($req['item_name']) ?></td>
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
                                    <td><?= isset($req['requested_at']) ? date('Y-m-d', strtotime($req['requested_at'])) : 'N/A' ?></td>
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
        var available = $(this).find(':selected').data('available') || 0;
        $('#quantity').val('');
        $('#quantity').attr('max', available);
        $('#availableQty').text('Available quantity: ' + available);
    });

    $('#quantity').on('input', function(){
        var max = parseInt($(this).attr('max')) || 0;
        var val = parseInt($(this).val()) || 0;
        if (val > max) {
            alert("Quantity cannot exceed available stock (" + max + ").");
            $(this).val(max);
        }
    });
});
</script>
</body>
</html>