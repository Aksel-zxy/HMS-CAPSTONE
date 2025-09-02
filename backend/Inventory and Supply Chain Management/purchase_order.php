<?php
session_start();
require 'db.php';

// Initialize cart
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// Handle AJAX Cart Actions
if (isset($_POST['ajax'])) {
    if ($_POST['ajax'] === 'add') {
        $id = $_POST['id'];
        $name = $_POST['name'];
        $price = $_POST['price'];

        if (isset($_SESSION['cart'][$id])) {
            $_SESSION['cart'][$id]['qty'] += 1;
        } else {
            $_SESSION['cart'][$id] = [
                "name" => $name,
                "price" => $price,
                "qty" => 1
            ];
        }
    }

    if ($_POST['ajax'] === 'update') {
        foreach ($_POST['qty'] as $id => $qty) {
            if ($qty > 0) {
                $_SESSION['cart'][$id]['qty'] = $qty;
            }
        }
    }

    if ($_POST['ajax'] === 'remove') {
        $id = $_POST['id'];
        unset($_SESSION['cart'][$id]);
    }

    if ($_POST['ajax'] === 'submit') {
        if (!empty($_SESSION['cart'])) {
            $stmt = $pdo->prepare("INSERT INTO purchase_requests (user_id, items, status, created_at) VALUES (?, ?, 'Pending', NOW())");
            $user_id = 1; // 🔹 Replace with logged-in user ID
            $items = json_encode($_SESSION['cart']);
            $stmt->execute([$user_id, $items]);

            $_SESSION['cart'] = [];
            echo json_encode(["success" => true, "message" => "Purchase request sent to admin."]);
            exit;
        } else {
            echo json_encode(["success" => false, "message" => "Cart is empty."]);
            exit;
        }
    }

    // Compute grand total
    $grand = 0;
    foreach ($_SESSION['cart'] as $item) {
        $grand += $item['qty'] * $item['price'];
    }

    // Return updated cart HTML
    ob_start();
    include "cart_table.php";
    $html = ob_get_clean();
    echo json_encode([
        "cart_html" => $html,
        "count" => count($_SESSION['cart']),
        "total" => number_format($grand, 2)
    ]);
    exit;
}

// --- PRODUCT LISTING ---
$categories = [
    "IT and supporting tech",
    "Medications and pharmacy supplies",
    "Consumables and disposables",
    "Therapeutic equipment",
    "Diagnostic Equipment"
];

$search = $_GET['search'] ?? '';
$selected_category = $_GET['category'] ?? '';

// Pagination
$limit = 8; 
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($page - 1) * $limit;

$where = "WHERE 1";
$params = [];

if ($selected_category) {
    $where .= " AND item_type = ?";
    $params[] = $selected_category;
}
if ($search) {
    $where .= " AND item_name LIKE ?";
    $params[] = "%$search%";
}

$total_stmt = $pdo->prepare("SELECT COUNT(*) FROM vendor_products $where");
$total_stmt->execute($params);
$total_products = $total_stmt->fetchColumn();
$total_pages = ceil($total_products / $limit);

$stmt = $pdo->prepare("SELECT * FROM vendor_products $where ORDER BY item_name ASC LIMIT $limit OFFSET $offset");
$stmt->execute($params);
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Purchase Order</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/purchase_order.css">
</head>
<body class="bg-light">

<div class="main-sidebar">
    <?php include 'Inventory_dashboard.php'; ?>
</div>

<div class="container py-5">
    <h2 class="mb-4">🛒 Purchase Request</h2>

    <!-- Filter Section -->
    <form method="get" class="mb-3">
        <div class="row g-2">
            <div class="col-md-4">
                <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" class="form-control" placeholder="🔍 Search items...">
            </div>
            <div class="col-md-4">
                <select name="category" class="form-select" onchange="this.form.submit()">
                    <option value="">All Categories</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= htmlspecialchars($cat) ?>" <?= ($cat == $selected_category) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($cat) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <button type="submit" class="btn btn-primary w-100">Filter</button>
            </div>
            <div class="col-md-1 text-end">
                <button type="button" class="btn btn-warning w-100" data-bs-toggle="modal" data-bs-target="#cartModal" id="cartBtn">
                    🛒(<span id="cartCount"><?= count($_SESSION['cart']) ?></span>)
                </button>
            </div>
        </div>
    </form>

    <!-- Product List -->
    <div class="row">
        <?php foreach ($products as $p): ?>
            <div class="col-md-3 mb-4">
                <div class="card h-100">
                    <?php if ($p['picture']): ?>
                        <img src="<?= htmlspecialchars($p['picture']) ?>" class="card-img-top" style="height:150px;object-fit:cover;">
                    <?php endif; ?>
                    <div class="card-body text-center">
                        <h6 class="card-title"><?= htmlspecialchars($p['item_name']) ?></h6>
                        <p class="small"><?= htmlspecialchars($p['item_description']) ?></p>
                        <p><strong>₱<?= number_format($p['price'], 2) ?></strong></p>
                        <button class="btn btn-success btn-sm w-100 addToCart" 
                            data-id="<?= $p['id'] ?>" 
                            data-name="<?= htmlspecialchars($p['item_name']) ?>" 
                            data-price="<?= $p['price'] ?>">
                            Add to Cart
                        </button>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Pagination -->
    <nav>
        <ul class="pagination justify-content-center">
            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                <li class="page-item <?= ($i == $page) ? 'active' : '' ?>">
                    <a class="page-link" href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&category=<?= urlencode($selected_category) ?>">
                        <?= $i ?>
                    </a>
                </li>
            <?php endfor; ?>
        </ul>
    </nav>
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
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
// Add item
$(document).on("click", ".addToCart", function() {
    $.post("purchase_order.php", {
        ajax: "add",
        id: $(this).data("id"),
        name: $(this).data("name"),
        price: $(this).data("price")
    }, function(res) {
        let data = JSON.parse(res);
        $("#cartContent").html(data.cart_html);
        $("#cartCount").text(data.count);
    });
});

// Remove item
$(document).on("click", ".removeItem", function() {
    $.post("purchase_order.php", {
        ajax: "remove",
        id: $(this).data("id")
    }, function(res) {
        let data = JSON.parse(res);
        $("#cartContent").html(data.cart_html);
        $("#cartCount").text(data.count);
    });
});

// Auto-update qty
$(document).on("input", ".qtyInput", function() {
    let formData = $("#updateCartForm").serialize();
    let row = $(this).closest("tr");
    let qty = parseInt($(this).val());
    let price = parseFloat(row.find("td:nth-child(3)").text().replace("₱","").replace(/,/g,""));

    if (!isNaN(qty) && qty > 0) {
        row.find(".rowTotal").text((price * qty).toFixed(2));
    }

    $.post("purchase_order.php", formData + "&ajax=update", function(res) {
        let data = JSON.parse(res);
        $("#cartTotal").text(data.total);
        $("#cartCount").text(data.count);
    });
});

// Submit cart
$(document).on("click", "#submitRequest", function() {
    $.post("purchase_order.php", {ajax: "submit"}, function(res) {
        let data = JSON.parse(res);
        alert(data.message);
        if (data.success) {
            $("#cartContent").html("<p>Your cart is empty.</p>");
            $("#cartCount").text("0");
        }
    });
});
</script>
</body>
</html>
