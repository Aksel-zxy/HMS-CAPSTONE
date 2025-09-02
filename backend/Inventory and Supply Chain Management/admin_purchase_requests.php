<?php
require 'db.php';

// ✅ Handle approve/reject
if (isset($_POST['action'])) {
    $request_id = $_POST['id'];

    if ($_POST['action'] === 'approve') {
        $stmt = $pdo->prepare("UPDATE purchase_requests SET status='Approved' WHERE id=?");
        $stmt->execute([$request_id]);

        // fetch items
        $stmt = $pdo->prepare("SELECT items FROM purchase_requests WHERE id=?");
        $stmt->execute([$request_id]);
        $items_json = $stmt->fetchColumn();
        $items = json_decode($items_json, true);

        if ($items) {
            foreach ($items as $item_id => $item) {
                $qty = $item['qty'];

                $stmtVendor = $pdo->prepare("SELECT vendor_id FROM vendor_products WHERE id=?");
                $stmtVendor->execute([$item_id]);
                $vendor_id = $stmtVendor->fetchColumn();

                if ($vendor_id) {
                    $stmtInsert = $pdo->prepare("
                        INSERT INTO vendor_orders 
                        (purchase_request_id, vendor_id, item_id, quantity, status, checklist, created_at)
                        VALUES (?, ?, ?, ?, 'Processing', '[]', NOW())
                    ");
                    $stmtInsert->execute([$request_id, $vendor_id, $item_id, $qty]);
                }
            }
        }
    } elseif ($_POST['action'] === 'reject') {
        $stmt = $pdo->prepare("UPDATE purchase_requests SET status='Rejected' WHERE id=?");
        $stmt->execute([$request_id]);
    }
}

// ✅ Filtering
$statusFilter = $_GET['status'] ?? 'All';
$searchUser   = $_GET['search_user'] ?? '';

$query = "SELECT * FROM purchase_requests WHERE 1=1";
$params = [];

if ($statusFilter !== 'All') {
    $query .= " AND status = ?";
    $params[] = $statusFilter;
}

if (!empty($searchUser)) {
    $query .= " AND user_id LIKE ?";
    $params[] = "%$searchUser%";
}

$query .= " ORDER BY created_at DESC";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin - Purchase Requests</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<div class="main-sidebar">
    <?php include 'Inventory_dashboard.php'; ?>
</div>

<div class="container py-5">
    <h2 class="mb-4"> Purchase Requests</h2>

    <!-- ✅ Filter & Search -->
    <form method="get" class="row g-3 mb-4">
        <div class="col-md-3">
            <select name="status" class="form-select">
                <option <?= $statusFilter==='All'?'selected':'' ?>>All</option>
                <option <?= $statusFilter==='Pending'?'selected':'' ?>>Pending</option>
                <option <?= $statusFilter==='Approved'?'selected':'' ?>>Approved</option>
                <option <?= $statusFilter==='Rejected'?'selected':'' ?>>Rejected</option>
            </select>
        </div>
        <div class="col-md-3">
            <input type="text" name="search_user" class="form-control" placeholder="Search by User ID" value="<?= htmlspecialchars($searchUser) ?>">
        </div>
        <div class="col-md-2">
            <button class="btn btn-primary w-100">Filter</button>
        </div>
        <div class="col-md-2">
            <a href="admin_purchase_request.php" class="btn btn-secondary w-100">Reset</a>
        </div>
    </form>

    <table class="table table-bordered table-hover bg-white shadow-sm">
        <thead class="table-dark">
            <tr>
                <th>ID</th>
                <th>User</th>
                <th>Items</th>
                <th>Grand Total</th>
                <th>Status</th>
                <th>Requested At</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($requests as $r): 
            $items = json_decode($r['items'], true);
            $grandTotal = 0;
            if ($items) {
                foreach ($items as $item) {
                    $grandTotal += $item['price'] * $item['qty'];
                }
            }
        ?>
            <tr>
                <td><?= $r['id'] ?></td>
                <td><?= htmlspecialchars($r['user_id']) ?></td>
                <td>
                    <button class="btn btn-sm btn-info" data-bs-toggle="modal" data-bs-target="#itemsModal<?= $r['id'] ?>">
                        View Items
                    </button>

                    <!-- Modal -->
                    <div class="modal fade" id="itemsModal<?= $r['id'] ?>" tabindex="-1">
                      <div class="modal-dialog modal-lg">
                        <div class="modal-content">
                          <div class="modal-header">
                            <h5 class="modal-title">🛒 Request #<?= $r['id'] ?> - Items</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                          </div>
                          <div class="modal-body">
                            <?php if ($items): ?>
                                <table class="table table-bordered">
                                    <thead>
                                        <tr>
                                            <th>Item</th>
                                            <th>Price</th>
                                            <th>Qty</th>
                                            <th>Total</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    <?php 
                                    $modalTotal = 0;
                                    foreach ($items as $item): 
                                        $lineTotal = $item['price'] * $item['qty'];
                                        $modalTotal += $lineTotal;
                                    ?>
                                        <tr>
                                            <td><?= htmlspecialchars($item['name']) ?></td>
                                            <td>₱<?= number_format($item['price'], 2) ?></td>
                                            <td><?= $item['qty'] ?></td>
                                            <td>₱<?= number_format($lineTotal, 2) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                    <tfoot>
                                        <tr class="table-dark">
                                            <td colspan="3" class="text-end"><strong>Grand Total:</strong></td>
                                            <td><strong>₱<?= number_format($modalTotal, 2) ?></strong></td>
                                        </tr>
                                    </tfoot>
                                </table>
                            <?php else: ?>
                                <p>No items found.</p>
                            <?php endif; ?>
                          </div>
                        </div>
                      </div>
                    </div>
                </td>
                <td><strong>₱<?= number_format($grandTotal, 2) ?></strong></td>
                <td>
                    <?php if ($r['status'] == 'Pending'): ?>
                        <span class="badge bg-warning text-dark">Pending</span>
                    <?php elseif ($r['status'] == 'Approved'): ?>
                        <span class="badge bg-success">Approved</span>
                    <?php else: ?>
                        <span class="badge bg-danger">Rejected</span>
                    <?php endif; ?>
                </td>
                <td><?= $r['created_at'] ?></td>
                <td>
                    <?php if ($r['status'] == 'Pending'): ?>
                        <form method="post" class="d-inline">
                            <input type="hidden" name="id" value="<?= $r['id'] ?>">
                            <input type="hidden" name="action" value="approve">
                            <button class="btn btn-sm btn-success">Approve</button>
                        </form>
                        <form method="post" class="d-inline">
                            <input type="hidden" name="id" value="<?= $r['id'] ?>">
                            <input type="hidden" name="action" value="reject">
                            <button class="btn btn-sm btn-danger">Reject</button>
                        </form>
                    <?php else: ?>
                        <em>No actions</em>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
