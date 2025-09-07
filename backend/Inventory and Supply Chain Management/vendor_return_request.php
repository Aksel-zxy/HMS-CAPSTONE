<?php
session_start();
require 'db.php';

// Ensure vendor is logged in
if (!isset($_SESSION['vendor_id'])) {
    header("Location: vlogin.php");
    exit;
}

$vendor_id = $_SESSION['vendor_id'];

// Handle vendor action (approve/reject)
if (isset($_GET['action']) && isset($_GET['id'])) {
    $action = $_GET['action'];
    $request_id = intval($_GET['id']);

    if (in_array($action, ['Approved','Rejected'])) {
        $stmt = $pdo->prepare("UPDATE return_requests 
                               SET status = ?, updated_at = NOW() 
                               WHERE id = ? AND vendor_id = ?");
        $stmt->execute([$action, $request_id, $vendor_id]);
        $msg = "Request #$request_id has been marked as $action.";
    }
}

// Fetch all return requests for this vendor
$stmt = $pdo->prepare("
    SELECT rr.*, i.item_name, u.fname, u.lname 
    FROM return_requests rr
    JOIN inventory i ON rr.inventory_id = i.id
    JOIN users u ON rr.requested_by = u.user_id
    WHERE rr.vendor_id = ?
    ORDER BY rr.requested_at DESC
");
$stmt->execute([$vendor_id]);
$requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Return & Damage Requests - Vendor</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
body {
    font-family: Arial, sans-serif;
}
.container {
    margin-left: 270px; /* Safe for sidebar if included */
}
</style>
</head>
<body class="bg-light">

<div class="vendor-sidebar">
    <?php include 'vendorsidebar.php'; ?>
</div>

<div class="container py-5">
    <h2 class="mb-4">Return & Damage Requests</h2>

    <?php if(isset($msg)): ?>
        <div class="alert alert-success"><?= htmlspecialchars($msg) ?></div>
    <?php endif; ?>

    <table class="table table-bordered table-hover bg-white">
        <thead class="table-dark">
            <tr>
                <th>#</th>
                <th>Item</th>
                <th>Requested By (Department)</th>
                <th>Quantity</th>
                <th>Reason</th>
                <th>Photo</th>
                <th>Status</th>
                <th>Requested At</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if(empty($requests)): ?>
                <tr><td colspan="9" class="text-center text-muted">No return requests for your items.</td></tr>
            <?php else: ?>
                <?php foreach($requests as $r): ?>
                    <tr>
                        <td><?= $r['id'] ?></td>
                        <td><?= htmlspecialchars($r['item_name']) ?></td>
                        <td><?= htmlspecialchars($r['fname'] . ' ' . $r['lname']) ?></td>
                        <td><?= $r['quantity'] ?></td>
                        <td><?= htmlspecialchars($r['reason']) ?></td>
                        <td>
                            <?php if($r['photo']): ?>
                                <a href="<?= htmlspecialchars($r['photo']) ?>" target="_blank">View</a>
                            <?php else: ?>
                                N/A
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($r['status']) ?></td>
                        <td><?= htmlspecialchars($r['requested_at']) ?></td>
                        <td>
                            <?php if($r['status'] === 'Pending'): ?>
                                <a href="?id=<?= $r['id'] ?>&action=Approved" class="btn btn-success btn-sm">Approve</a>
                                <a href="?id=<?= $r['id'] ?>&action=Rejected" class="btn btn-danger btn-sm">Reject</a>
                            <?php else: ?>
                                <span class="text-muted">No Action</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

</body>
</html>
