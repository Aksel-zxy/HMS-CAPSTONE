<?php
session_start();
require 'db.php';

// ✅ Vendor login check
if (!isset($_SESSION['vendor_id'])) {
    die("❌ You must be logged in to view this page.");
}
$vendor_id = $_SESSION['vendor_id'];

// Status order
$status_order = ["Processing","Packed","Shipped"];

// 🔹 Handle AJAX request for modal
if(isset($_GET['ajax'], $_GET['po_number']) && $_GET['ajax'] === "po_details") {
    $po_number = $_GET['po_number'];

    $stmt = $pdo->prepare("SELECT * FROM vendor_orders WHERE purchase_order_number = ? AND vendor_id = ?");
    $stmt->execute([$po_number, $vendor_id]);
    $po = $stmt->fetch(PDO::FETCH_ASSOC);

    if(!$po){
        echo "<p class='text-muted'>No items found for this PO.</p>";
        exit;
    }

    $items = json_decode($po['items'], true);

    if(!$items){
        echo "<p class='text-muted'>No items found for this PO.</p>";
        exit;
    }

    // Find current status index
    $current_status_index = array_search($po['status'], $status_order);

    ?>
    <table class="table table-bordered">
        <thead class="table-dark">
            <tr>
                <th>Item</th>
                <th>Qty</th>
                <th>Price</th>
                <th>Subtotal</th>
            </tr>
        </thead>
        <tbody>
        <?php
        $grand_total = 0;
        foreach($items as $it):
            $qty = isset($it['qty']) ? (int)$it['qty'] : 1;
            $price = isset($it['price']) ? (float)$it['price'] : 0;
            $subtotal = $qty * $price;
            $grand_total += $subtotal;
        ?>
            <tr>
                <td><?= htmlspecialchars($it['name']) ?></td>
                <td><?= $qty ?></td>
                <td>₱<?= number_format($price,2) ?></td>
                <td>₱<?= number_format($subtotal,2) ?></td>
            </tr>
        <?php endforeach; ?>
        <tr class="table-secondary">
            <td colspan="3" class="text-end"><strong>Total Amount:</strong></td>
            <td><strong>₱<?= number_format($grand_total,2) ?></strong></td>
        </tr>
        </tbody>
    </table>

    <!-- Status Update Form -->
    <?php if($po['status'] !== "Shipped"): ?>
    <form method="post" action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>" class="mt-3">
        <input type="hidden" name="po_number" value="<?= htmlspecialchars($po_number) ?>">
        <label class="form-label fw-bold">Update Status:</label>
        <select name="status" class="form-select" required>
            <?php
            foreach($status_order as $i => $status):
                $selected = ($status === $po['status']) ? "selected" : "";
                $disabled = ($i < $current_status_index || $i > $current_status_index + 1) ? "disabled" : "";
            ?>
                <option value="<?= $status ?>" <?= $disabled ?> <?= $selected ?>><?= $status ?></option>
            <?php endforeach; ?>
        </select>
        <button type="submit" name="update_status" class="btn btn-primary mt-3 w-100">Update Status</button>
    </form>
    <?php else: ?>
        <div class="alert alert-success mt-3 text-center fw-bold">✅ PO Completed - Shipped</div>
    <?php endif; ?>
    <?php
    exit;
}

// 🔹 Handle status update for entire PO
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'], $_POST['po_number'], $_POST['status'])) {
    $po_number = $_POST['po_number'];
    $new_status = $_POST['status'];

    $stmt = $pdo->prepare("SELECT status FROM vendor_orders WHERE purchase_order_number = ? AND vendor_id = ?");
    $stmt->execute([$po_number, $vendor_id]);
    $po = $stmt->fetch(PDO::FETCH_ASSOC);

    if($po){
        $current_index = array_search($po['status'], $status_order);
        $new_index = array_search($new_status, $status_order);

        if($current_index === false) $current_index = -1;
        if($new_index > $current_index){
            $updateStmt = $pdo->prepare("UPDATE vendor_orders SET status = ? WHERE purchase_order_number = ? AND vendor_id = ?");
            $updateStmt->execute([$new_status, $po_number, $vendor_id]);
        }
    }

    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// 🔹 Fetch all POs for this vendor
$poStmt = $pdo->prepare("SELECT * FROM vendor_orders WHERE vendor_id = ? ORDER BY created_at DESC");
$poStmt->execute([$vendor_id]);
$poList = $poStmt->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Vendor Orders</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>

<div class="vendor-sidebar">
    <?php include 'vendorsidebar.php'; ?>
</div>

<div class="container py-5">
    <h2 class="mb-4">📦 Purchase Orders</h2>
    <table class="table table-striped table-bordered">
        <thead class="table-dark">
            <tr>
                <th>PO Number</th>
                <th>Total Price</th>
                <th>Status</th>
                <th>View / Update</th>
            </tr>
        </thead>
        <tbody>
        <?php if(empty($poList)): ?>
            <tr><td colspan="4" class="text-center text-muted">No purchase orders found.</td></tr>
        <?php else: ?>
            <?php foreach($poList as $po): ?>
            <tr>
                <td><?= htmlspecialchars($po['purchase_order_number']) ?></td>
                <td>₱<?= number_format($po['total_price'],2) ?></td>
                <td>
                    <?php
                    $badge = match($po['status']){
                        'Processing'=>'warning',
                        'Packed'=>'info',
                        'Shipped'=>'success',
                        default=>'secondary'
                    };
                    ?>
                    <span class="badge bg-<?= $badge ?>"><?= $po['status'] ?></span>
                </td>
                <td>
                    <button class="btn btn-sm btn-primary viewBtn" data-po="<?= htmlspecialchars($po['purchase_order_number']) ?>" data-bs-toggle="modal" data-bs-target="#poModal">
                        View / Update
                    </button>
                </td>
            </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Modal -->
<div class="modal fade" id="poModal" tabindex="-1" aria-hidden="true">
<div class="modal-dialog modal-lg">
<div class="modal-content">
<div class="modal-header">
<h5 class="modal-title">📋 PO Details</h5>
<button type="button" class="btn-close" data-bs-dismiss="modal"></button>
</div>
<div class="modal-body" id="poDetails">
<div class="text-center">
    <div class="spinner-border text-primary" role="status">
        <span class="visually-hidden">Loading...</span>
    </div>
    <p class="mt-2">Loading...</p>
</div>
</div>
</div>
</div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
$(document).on("click", ".viewBtn", function() {
    var po_number = $(this).data("po");
    $("#poDetails").html(`
        <div class="text-center">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
            <p class="mt-2">Loading...</p>
        </div>
    `);

    $.ajax({
        url: window.location.pathname,
        method: "GET",
        data: { ajax: "po_details", po_number: po_number },
        success: function(data){
            $("#poDetails").html(data);
        },
        error: function(){
            $("#poDetails").html('<div class="alert alert-danger">Error loading PO details.</div>');
        }
    });
});
</script>
</body>
</html>
