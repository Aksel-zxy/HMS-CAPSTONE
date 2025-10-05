<?php
session_start();
require 'db.php';
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Initialize cart
if (!isset($_SESSION['cart'])) $_SESSION['cart'] = [];
$status_order = ["Processing", "Packed", "Shipped"];
$vendor_id = 1; // Replace with logged-in vendor ID

// --- Handle AJAX requests ---
if (isset($_POST['ajax'])) {
    $action = $_POST['ajax'];

    // Add item to cart
    if ($action === 'add') {
        $id = $_POST['id'];
        $name = $_POST['name'];
        $price = $_POST['price'];
        $unit_type = $_POST['unit_type'] ?? 'Piece';
        $pcs_per_box = $_POST['pcs_per_box'] ?? null;

        if (isset($_SESSION['cart'][$id])) {
            $_SESSION['cart'][$id]['qty'] += 1;
        } else {
            $_SESSION['cart'][$id] = [
                "name" => $name,
                "price" => $price,
                "unit_type" => $unit_type,
                "pcs_per_box" => $pcs_per_box,
                "qty" => 1
            ];
        }
    }

    // Update quantities
    if ($action === 'update') {
        foreach ($_POST['qty'] as $id => $qty) {
            if ($qty > 0) $_SESSION['cart'][$id]['qty'] = $qty;
        }
    }

    // Remove item
    if ($action === 'remove') {
        unset($_SESSION['cart'][$_POST['id']]);
    }

    // Submit order
    if ($action === 'submit') {
        if (!empty($_SESSION['cart'])) {
            $po_number = 'PO' . date('YmdHis') . rand(100, 999);
            $items = $_SESSION['cart'];
            $total_price = 0;
            foreach ($items as $it) {
                $total_price += $it['price'] * $it['qty'];
            }

            // Insert into vendor_orders using JSON items
            $stmt = $pdo->prepare("
                INSERT INTO vendor_orders
                (purchase_order_number, vendor_id, items, status, total_price, created_at)
                VALUES (?, ?, ?, 'Processing', ?, NOW())
            ");
            $stmt->execute([$po_number, $vendor_id, json_encode($items), $total_price]);

            $_SESSION['cart'] = [];
            echo json_encode(["success" => true, "message" => "✅ Order placed successfully!", "po_number" => $po_number]);
            exit;
        } else {
            echo json_encode(["success" => false, "message" => "⚠️ Cart is empty."]);
            exit;
        }
    }

    // Update cart HTML
    $grand = 0;
    foreach ($_SESSION['cart'] as $it) $grand += $it['price'] * $it['qty'];
    ob_start();
    ?>
    <table class="table table-bordered">
        <thead>
        <tr><th>Item</th><th>Qty</th><th>Price</th><th>Subtotal</th><th>Action</th></tr>
        </thead>
        <tbody>
        <?php foreach ($_SESSION['cart'] as $id => $it): ?>
            <tr>
                <td><?= htmlspecialchars($it['name']) ?></td>
                <td><input type="number" class="form-control qtyInput" name="qty[<?= $id ?>]" value="<?= $it['qty'] ?>" min="1"></td>
                <td>₱<?= number_format($it['price'], 2) ?></td>
                <td>₱<?= number_format($it['price'] * $it['qty'], 2) ?></td>
                <td><button class="btn btn-sm btn-danger removeItem" data-id="<?= $id ?>">Remove</button></td>
            </tr>
        <?php endforeach; ?>
        <tr class="table-secondary">
            <td colspan="3" class="text-end"><strong>Total:</strong></td>
            <td colspan="2"><strong>₱<?= number_format($grand, 2) ?></strong></td>
        </tr>
        </tbody>
    </table>
    <?php
    $html = ob_get_clean();
    echo json_encode(["cart_html" => $html, "count" => count($_SESSION['cart']), "total" => number_format($grand, 2)]);
    exit;
}

// --- Handle status update ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'], $_POST['po_number'], $_POST['status'])) {
    $po_number = $_POST['po_number'];
    $new_status = $_POST['status'];

    $stmt = $pdo->prepare("SELECT status FROM vendor_orders WHERE purchase_order_number=? AND vendor_id=?");
    $stmt->execute([$po_number, $vendor_id]);
    $current_status = $stmt->fetchColumn();

    $current_index = array_search($current_status, $status_order);
    $new_index = array_search($new_status, $status_order);

    if ($new_index > $current_index) {
        $update = $pdo->prepare("UPDATE vendor_orders SET status=? WHERE purchase_order_number=? AND vendor_id=?");
        $update->execute([$new_status, $po_number, $vendor_id]);
    }

    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// --- Fetch Products ---
$search = $_GET['search'] ?? '';
$category = $_GET['category'] ?? '';
$where = "WHERE 1";
$params = [];
if ($category) { $where .= " AND item_type=?"; $params[] = $category; }
if ($search) { $where .= " AND item_name LIKE ?"; $params[] = "%$search%"; }
$stmt = $pdo->prepare("SELECT * FROM vendor_products $where ORDER BY item_name ASC");
$stmt->execute($params);
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

// --- Fetch Vendor Orders ---
$poStmt = $pdo->prepare("SELECT * FROM vendor_orders WHERE vendor_id=? ORDER BY created_at DESC");
$poStmt->execute([$vendor_id]);
$poList = $poStmt->fetchAll(PDO::FETCH_ASSOC);

$categories = [
    "IT and supporting tech",
    "Medications and pharmacy supplies",
    "Consumables and disposables",
    "Therapeutic equipment",
    "Diagnostic Equipment"
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Direct Vendor Orders</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body class="bg-light">

<div class="main-sidebar">
    <?php include 'inventory_sidebar.php'; ?>
</div>

<div class="container py-5">
<h2>📦 Direct Vendor Orders</h2>

<!-- Filters -->
<form method="get" class="mb-3 row g-2">
    <div class="col-md-4">
        <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" class="form-control" placeholder="Search items...">
    </div>
    <div class="col-md-4">
        <select name="category" class="form-select" onchange="this.form.submit()">
            <option value="">All Categories</option>
            <?php foreach ($categories as $cat): ?>
                <option value="<?= htmlspecialchars($cat) ?>" <?= ($cat == $category) ? 'selected' : '' ?>><?= htmlspecialchars($cat) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-2">
        <button type="submit" class="btn btn-primary w-100">Filter</button>
    </div>
    <div class="col-md-2 text-end">
        <button type="button" class="btn btn-warning w-100" data-bs-toggle="modal" data-bs-target="#cartModal" id="cartBtn">
            🛒(<span id="cartCount"><?= count($_SESSION['cart']) ?></span>)
        </button>
    </div>
</form>

<!-- Products -->
<div class="row">
<?php foreach ($products as $p): ?>
<div class="col-md-3 mb-4">
    <div class="card h-100">
        <?php if ($p['picture']): ?>
        <img src="<?= htmlspecialchars($p['picture']) ?>" class="card-img-top" style="height:150px;object-fit:cover;">
        <?php endif; ?>
        <div class="card-body text-center">
            <h6><?= htmlspecialchars($p['item_name']) ?></h6>
            <p class="small text-muted"><?= htmlspecialchars($p['item_description']) ?></p>
            <p><strong>₱<?= number_format($p['price'],2) ?> / <?= htmlspecialchars($p['unit_type'] ?? 'Piece') ?></strong></p>
            <button class="btn btn-success btn-sm w-100 addToCart"
                data-id="<?= $p['id'] ?>"
                data-name="<?= htmlspecialchars($p['item_name']) ?>"
                data-price="<?= $p['price'] ?>"
                data-unit_type="<?= htmlspecialchars($p['unit_type'] ?? 'Piece') ?>"
                data-pcs_per_box="<?= htmlspecialchars($p['pcs_per_box'] ?? '') ?>">
                Add to Cart
            </button>
        </div>
    </div>
</div>
<?php endforeach; ?>
</div>

<!-- Cart Modal -->
<div class="modal fade" id="cartModal" tabindex="-1">
<div class="modal-dialog modal-lg">
<div class="modal-content">
<div class="modal-header">
<h5 class="modal-title">🛒 Your Cart</h5>
<button type="button" class="btn-close" data-bs-dismiss="modal"></button>
</div>
<div class="modal-body" id="cartContent">
<?php include "cart_table.php"; ?>
<div class="text-end mt-3">
    <button class="btn btn-primary" id="submitOrder">Submit Order</button>
</div>
</div>
</div>
</div>
</div>

<hr>
<h3>Vendor Purchase Orders</h3>
<table class="table table-bordered">
<thead class="table-dark">
<tr>
<th>PO Number</th>
<th>Items</th>
<th>Total Price</th>
<th>Status</th>
<th>Update Status</th>
</tr>
</thead>
<tbody>
<?php foreach($poList as $po): ?>
<tr>
<td><?= $po['purchase_order_number'] ?></td>
<td>
<?php
$items = json_decode($po['items'], true);
foreach($items as $it) {
    echo htmlspecialchars($it['name'])." x".$it['qty']."<br>";
}
?>
</td>
<td>₱<?= number_format($po['total_price'],2) ?></td>
<td><?= $po['status'] ?></td>
<td>
<?php if($po['status']!=='Shipped'): ?>
<form method="post">
<input type="hidden" name="po_number" value="<?= $po['purchase_order_number'] ?>">
<select name="status" class="form-select">
<?php
$current_index = array_search($po['status'],$status_order);
foreach($status_order as $i=>$s) {
    $disabled = ($i<$current_index || $i>$current_index+1)?'disabled':''; 
    $selected = ($s==$po['status'])?'selected':''; 
    echo "<option value='$s' $disabled $selected>$s</option>";
}
?>
</select>
<button type="submit" name="update_status" class="btn btn-sm btn-primary mt-1 w-100">Update</button>
</form>
<?php else: ?>
<span class="text-success fw-bold">Shipped</span>
<?php endif; ?>
</td>
</tr>
<?php endforeach; ?>
</tbody>
</table>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
$(document).ready(function(){

    // Add to cart
    $(document).on("click",".addToCart",function(){
        $.post("",{
            ajax:"add",
            id:$(this).data("id"),
            name:$(this).data("name"),
            price:$(this).data("price"),
            unit_type:$(this).data("unit_type"),
            pcs_per_box:$(this).data("pcs_per_box")
        },function(res){
            let data = JSON.parse(res);
            $("#cartContent").html(data.cart_html + '<div class="text-end mt-3"><button class="btn btn-primary" id="submitOrder">Submit Order</button></div>');
            $("#cartCount").text(data.count);
            $('#cartModal').modal('show');
        });
    });

    // Update quantity
    $(document).on("change",".qtyInput",function(){
        let qtyData = {};
        $(".qtyInput").each(function(){
            qtyData[$(this).attr("name").replace("qty[","").replace("]","")] = $(this).val();
        });
        $.post("",{ajax:"update", qty:qtyData}, function(res){
            let data = JSON.parse(res);
            $("#cartContent").html(data.cart_html + '<div class="text-end mt-3"><button class="btn btn-primary" id="submitOrder">Submit Order</button></div>');
            $("#cartCount").text(data.count);
        });
    });

    // Remove item
    $(document).on("click",".removeItem",function(){
        $.post("",{ajax:"remove", id:$(this).data("id")}, function(res){
            let data = JSON.parse(res);
            $("#cartContent").html(data.cart_html + '<div class="text-end mt-3"><button class="btn btn-primary" id="submitOrder">Submit Order</button></div>');
            $("#cartCount").text(data.count);
        });
    });

    // Submit order
    $(document).on("click","#submitOrder",function(){
        $.post("",{ajax:"submit"},function(res){
            let data = JSON.parse(res);
            alert(data.message);
            if(data.success){
                $("#cartContent").html('');
                $("#cartCount").text('0');
                location.reload();
            }
        });
    });

});
</script>

</body>
</html>
