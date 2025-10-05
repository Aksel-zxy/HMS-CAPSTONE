<?php
session_start();
require 'db.php';

// Show errors (for debugging)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Ensure user is logged in
if (!isset($_SESSION['user_id'])) {
    die("You must log in.");
}

$user_id = $_SESSION['user_id'];

// Fetch user info
$user_stmt = $pdo->prepare("SELECT * FROM users WHERE user_id=? LIMIT 1");
$user_stmt->execute([$user_id]);
$user = $user_stmt->fetch(PDO::FETCH_ASSOC);
if (!$user) die("User not found.");

$department = !empty($user['department']) ? $user['department'] : 'N/A';
$department_id = $user['role'] ?? null;

// --- CART ---
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// Handle AJAX requests
if (isset($_POST['ajax'])) {
    $action = $_POST['ajax'];

    // Add item
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
            if ($qty > 0) {
                $_SESSION['cart'][$id]['qty'] = $qty;
            }
        }
    }

    // Remove item
    if ($action === 'remove') {
        unset($_SESSION['cart'][$_POST['id']]);
    }

    // ✅ Process Order Directly (no admin approval)
    if ($action === 'submit') {
        if (!empty($_SESSION['cart'])) {
            $items = $_SESSION['cart'];
            $total_price = 0;

            foreach ($items as $it) {
                $total_price += ($it['unit_type'] === 'Box')
                    ? $it['price']
                    : $it['price'] * $it['qty'];
            }

            try {
                // Create vendor orders directly
                foreach ($items as $item_id => $item) {
                    $qty = $item['qty'];

                    $stmtVendor = $pdo->prepare("SELECT vendor_id FROM vendor_products WHERE id=?");
                    $stmtVendor->execute([$item_id]);
                    $vendor_id = $stmtVendor->fetchColumn();

                    if ($vendor_id) {
                        $stmtInsert = $pdo->prepare("
                            INSERT INTO vendor_orders 
                            (user_id, department, department_id, vendor_id, item_id, quantity, status, checklist, created_at)
                            VALUES (?, ?, ?, ?, ?, ?, 'Processing', '[]', NOW())
                        ");
                        $stmtInsert->execute([
                            $user_id,
                            $department,
                            $department_id,
                            $vendor_id,
                            $item_id,
                            $qty
                        ]);
                    }
                }

                $_SESSION['cart'] = [];
                echo json_encode(["success" => true, "message" => "✅ Order successfully processed!"]);
                exit;
            } catch (PDOException $e) {
                echo json_encode(["success" => false, "message" => "Database error: " . $e->getMessage()]);
                exit;
            }
        } else {
            echo json_encode(["success" => false, "message" => "Cart is empty."]);
            exit;
        }
    }

    // Update cart display
    $grand = 0;
    foreach ($_SESSION['cart'] as $it) {
        $grand += ($it['unit_type'] === 'Box') ? $it['price'] : $it['price'] * $it['qty'];
    }

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
    <title>Process Order</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<div class="main-sidebar">
    <?php include 'inventory_sidebar.php'; ?>
</div>

<div class="container py-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>🧾 Process Order</h2>
        <div class="text-end">
            <h5 class="mb-1"><?= htmlspecialchars($department) ?></h5>
        </div>
    </div>

    <!-- Filter -->
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
                        <p>
                            <strong>₱<?= number_format($p['price'], 2) ?> / <?= htmlspecialchars($p['unit_type'] ?? 'Piece') ?></strong>
                        </p>
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
        <h5 class="modal-title">🧾 Your Order</h5>
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
// Add to cart
$(document).on("click", ".addToCart", function() {
    $.post("purchase_order.php", {
        ajax: "add",
        id: $(this).data("id"),
        name: $(this).data("name"),
        price: $(this).data("price"),
        unit_type: $(this).data("unit_type"),
        pcs_per_box: $(this).data("pcs_per_box")
    }, function(res) {
        let data = JSON.parse(res);
        $("#cartContent").html(data.cart_html);
        $("#cartCount").text(data.count);
    });
});

// Remove item
$(document).on("click", ".removeItem", function() {
    $.post("purchase_order.php", {ajax:"remove", id:$(this).data("id")}, function(res) {
        let data = JSON.parse(res);
        $("#cartContent").html(data.cart_html);
        $("#cartCount").text(data.count);
    });
});

// Update qty
$(document).on("input", ".qtyInput", function() {
    let formData = $("#updateCartForm").serialize();
    $.post("purchase_order.php", formData + "&ajax=update", function(res) {
        let data = JSON.parse(res);
        $("#cartTotal").text(data.total);
        $("#cartCount").text(data.count);
    });
});
</script>
</body>
</html>
