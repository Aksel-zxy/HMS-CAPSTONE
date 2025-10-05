<?php
session_start();
require 'db.php';

// ✅ Vendor login check
if (!isset($_SESSION['vendor_id'])) {
    die("❌ You must be logged in to view this page.");
}
$vendor_id = $_SESSION['vendor_id'];

// 🔹 Handle status update for entire PO
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'], $_POST['po_number'], $_POST['status'])) {
    $po_number = $_POST['po_number'];
    $new_status = $_POST['status'];
    $status_order = ["Processing", "Packed", "Shipped"];

    // Fetch all items for this PO belonging to vendor
    $stmt = $pdo->prepare("SELECT id, status FROM vendor_orders WHERE purchase_order_number = ? AND vendor_id = ?");
    $stmt->execute([$po_number, $vendor_id]);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($orders as $order) {
        $current_index = array_search($order['status'], $status_order);
        $new_index = array_search($new_status, $status_order);
        if ($new_index > $current_index) {
            $updateStmt = $pdo->prepare("UPDATE vendor_orders SET status = ? WHERE id = ?");
            $updateStmt->execute([$new_status, $order['id']]);
        }
    }

    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// 🔹 Fetch all POs for this vendor
$poStmt = $pdo->prepare("
    SELECT vo.purchase_order_number, MIN(vo.created_at) AS order_time, 
           SUM(vo.quantity) AS total_qty, SUM(vo.quantity * vp.price) AS total_price,
           MAX(vo.status) AS status
    FROM vendor_orders vo
    JOIN vendor_products vp ON vo.item_id = vp.id
    WHERE vo.vendor_id = ?
    GROUP BY vo.purchase_order_number
    ORDER BY vo.created_at DESC
");
$poStmt->execute([$vendor_id]);
$poList = $poStmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Vendor Orders</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="container py-5">
    <h2 class="mb-4">Purchase Orders</h2>
    <table class="table table-striped table-bordered">
        <thead class="table-dark">
            <tr>
                <th>PO Number</th>
                <th>Order Time</th>
                <th>Total Qty</th>
                <th>Total Price</th>
                <th>Status</th>
                <th>View / Update</th>
            </tr>
        </thead>
        <tbody>
        <?php if(empty($poList)): ?>
            <tr><td colspan="6" class="text-center text-muted">No purchase orders found.</td></tr>
        <?php else: ?>
            <?php foreach($poList as $po): ?>
            <tr>
                <td><?= htmlspecialchars($po['purchase_order_number']) ?></td>
                <td><?= $po['order_time'] ?></td>
                <td><?= $po['total_qty'] ?></td>
                <td>₱<?= number_format($po['total_price'], 2) ?></td>
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
                    <button class="btn btn-sm btn-primary viewBtn" data-po="<?= $po['purchase_order_number'] ?>" data-bs-toggle="modal" data-bs-target="#poModal">
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
                <h5 class="modal-title">PO Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="poDetails">Loading...</div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
$(document).on("click", ".viewBtn", function() {
    var po_number = $(this).data("po");
    $("#poDetails").html("Loading...");

    $.get("<?= $_SERVER['PHP_SELF'] ?>", { ajax: "po_details", po_number: po_number }, function(data){
        $("#poDetails").html(data);
    });
});
</script>

<?php
// ✅ Handle AJAX request for modal
if(isset($_GET['ajax'], $_GET['po_number']) && $_GET['ajax'] === "po_details") {
    $po_number = $_GET['po_number'];

    // Fetch all items for this PO
    $stmt = $pdo->prepare("
        SELECT vo.*, vp.item_name, vp.price, vp.picture
        FROM vendor_orders vo
        JOIN vendor_products vp ON vo.item_id = vp.id
        WHERE vo.purchase_order_number = ? AND vo.vendor_id = ?
    ");
    $stmt->execute([$po_number, $vendor_id]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if(!$items){
        echo "<p class='text-muted'>No items found for this PO.</p>";
        exit;
    }

    // Ensure at least "Processing"
    foreach($items as $item){
        if(empty($item['status'])){
            $updateStmt = $pdo->prepare("UPDATE vendor_orders SET status='Processing' WHERE id=?");
            $updateStmt->execute([$item['id']]);
            $item['status'] = 'Processing';
        }
    }

    $current_status = $items[0]['status'];
    $status_order = ["Processing","Packed","Shipped"];
    ?>

    <table class="table table-bordered">
        <thead class="table-dark">
            <tr>
                <th>Picture</th>
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
            $subtotal = $it['quantity'] * $it['price'];
            $grand_total += $subtotal;
        ?>
            <tr>
                <td>
                    <?php if($it['picture']): ?>
                        <img src="<?= htmlspecialchars($it['picture']) ?>" width="60" height="60" style="object-fit:cover;border-radius:8px;">
                    <?php else: ?>
                        <span class="text-muted">N/A</span>
                    <?php endif; ?>
                </td>
                <td><?= htmlspecialchars($it['item_name']) ?></td>
                <td><?= (int)$it['quantity'] ?></td>
                <td>₱<?= number_format($it['price'],2) ?></td>
                <td>₱<?= number_format($subtotal,2) ?></td>
            </tr>
        <?php endforeach; ?>
        <tr class="table-secondary">
            <td colspan="4" class="text-end"><strong>Total Amount:</strong></td>
            <td><strong>₱<?= number_format($grand_total,2) ?></strong></td>
        </tr>
        </tbody>
    </table>

    <!-- Status Update Form -->
    <?php if($current_status !== "Shipped"): ?>
    <form method="post" class="mt-3">
        <input type="hidden" name="po_number" value="<?= htmlspecialchars($po_number) ?>">
        <label class="form-label fw-bold">Update Status:</label>
        <select name="status" class="form-select" required>
            <?php
            $current_index = array_search($current_status, $status_order);
            foreach($status_order as $i => $status):
                $disabled = ($i < $current_index || $i > $current_index + 1) ? "disabled" : "";
                $selected = ($status === $current_status) ? "selected" : "";
            ?>
                <option value="<?= $status ?>" <?= $disabled ?> <?= $selected ?>><?= $status ?></option>
            <?php endforeach; ?>
        </select>
        <button type="submit" name="update_status" class="btn btn-primary mt-3 w-100">Update Status</button>
    </form>
    <?php else: ?>
        <div class="alert alert-success mt-3 text-center fw-bold">PO Completed</div>
    <?php endif; ?>

    <?php
    exit; // Stop further output for AJAX
}
?>