<?php
require 'db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['vendor_id'])) {
    header("Location: vlogin.php");
    exit;
}

$vendor_id = $_SESSION['vendor_id'];

//  Add Product
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_product'])) {
    $name = $_POST['item_name'];
    $desc = $_POST['item_description'];
    $type = $_POST['item_type'];
    $sub_type = $_POST['sub_type'] ?? null; 
    $price = $_POST['price'];
    $picture = null;

    if (!empty($_FILES['picture']['name'])) {
        $targetDir = "uploads/";
        if (!is_dir($targetDir)) mkdir($targetDir, 0777, true);
        $fileName = time() . "_" . basename($_FILES['picture']['name']);
        $targetFile = $targetDir . $fileName;
        move_uploaded_file($_FILES['picture']['tmp_name'], $targetFile);
        $picture = $targetFile;
    }

    $stmt = $pdo->prepare("INSERT INTO vendor_products (vendor_id, item_name, item_description, item_type, sub_type, price, picture) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$vendor_id, $name, $desc, $type, $sub_type, $price, $picture]);
    header("Location: vendor_products.php");
    exit;
}

//  Delete Product
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    $stmt = $pdo->prepare("DELETE FROM vendor_products WHERE id = ? AND vendor_id = ?");
    $stmt->execute([$id, $vendor_id]);
    header("Location: vendor_products.php");
    exit;
}

//  Pagination Settings
$limit = 12; // items per page
$page = isset($_GET['page_num']) ? (int)$_GET['page_num'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

//  Filters
$search = $_GET['search'] ?? '';
$typeFilter = $_GET['type'] ?? '';

$query = "SELECT * FROM vendor_products WHERE vendor_id = :vendor_id";
$countQuery = "SELECT COUNT(*) FROM vendor_products WHERE vendor_id = :vendor_id";
$params = [':vendor_id' => $vendor_id];

if (!empty($search)) {
    $query .= " AND (item_name LIKE :search OR item_description LIKE :search)";
    $countQuery .= " AND (item_name LIKE :search OR item_description LIKE :search)";
    $params[':search'] = "%$search%";
}

if (!empty($typeFilter)) {
    $query .= " AND item_type = :type";
    $countQuery .= " AND item_type = :type";
    $params[':type'] = $typeFilter;
}

//  Order Alphabetically + Limit for Pagination
$query .= " ORDER BY item_name ASC LIMIT :limit OFFSET :offset";

//  Fetch Products
$stmt = $pdo->prepare($query);
foreach ($params as $key => $val) {
    $stmt->bindValue($key, $val);
}
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

//  Get Total Products
$stmtCount = $pdo->prepare($countQuery);
$stmtCount->execute($params);
$totalProducts = $stmtCount->fetchColumn();
$totalPages = ceil($totalProducts / $limit);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Vendor Products</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" type="text/css" href="assets/css/vendorsidebar.css">
    <link rel="stylesheet" type="text/css" href="assets/css/vendor_products.css">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function toggleSubType() {
            let type = document.getElementById("item_type").value;
            document.getElementById("subTypeDiv").style.display = (type === "Medications and pharmacy supplies") ? "block" : "none";
        }
    </script>
</head>
<body class="bg-light">

<div class="vendor-wrapper">
    <!-- Sidebar -->
    <div class="vendor-sidebar">
        <?php include 'vendorsidebar.php'; ?>
    </div>

    <!-- Main Content -->
    <div class="vendor-main">
        <main class="p-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h3>Manage Product Listings</h3>
                <!--  Add Product Button -->
                <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addProductModal">+ Add Product</button>
            </div>

            <!--  Search & Filter -->
            <div class="card mb-3">
                <div class="card-body">
                    <form method="GET" class="row g-2">
                        <input type="hidden" name="page" value="products">

                        <div class="col-md-6">
                            <input type="text" name="search" class="form-control" 
                                   placeholder="Search products..." 
                                   value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">
                        </div>

                        <div class="col-md-4">
                            <select name="type" class="form-control">
                                <option value="">All Types</option>
                                <option value="IT and supporting tech" <?= ($typeFilter=="IT and supporting tech"?"selected":"") ?>>IT and supporting tech</option>
                                <option value="Medications and pharmacy supplies" <?= ($typeFilter=="Medications and pharmacy supplies"?"selected":"") ?>>Medications and pharmacy supplies</option>
                                <option value="Consumables and disposables" <?= ($typeFilter=="Consumables and disposables"?"selected":"") ?>>Consumables and disposables</option>
                                <option value="Diagnostic Equipment" <?= ($typeFilter=="Diagnostic Equipment"?"selected":"") ?>>Diagnostic Equipment</option>
                            </select>
                        </div>

                        <div class="col-md-2 d-grid">
                            <button type="submit" class="btn btn-primary">Search</button>
                        </div>
                        <div class="col-md-2 d-grid mt-2">
                            <a href="vendor_products.php" class="btn btn-secondary">Reset</a>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Product List -->
            <div class="card">
                <div class="card-header">My Products</div>
                <div class="card-body">
                    <table class="table table-striped">
                        <thead>
                        <tr>
                            <th>Picture</th>
                            <th>Name</th>
                            <th>Description</th>
                            <th>Type</th>
                            <th>Sub Type</th>
                            <th>Price</th>
                            <th>Actions</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php if (empty($products)): ?>
                            <tr><td colspan="7" class="text-center">No products found</td></tr>
                        <?php endif; ?>
                        <?php foreach ($products as $p): ?>
                            <tr>
                                <td><?php if ($p['picture']): ?><img src="<?= $p['picture'] ?>" width="50"><?php endif; ?></td>
                                <td><?= htmlspecialchars($p['item_name']) ?></td>
                                <td><?= htmlspecialchars($p['item_description']) ?></td>
                                <td><?= htmlspecialchars($p['item_type']) ?></td>
                                <td><?= htmlspecialchars($p['sub_type'] ?? '-') ?></td>
                                <td>₱<?= number_format($p['price'], 2) ?></td>
                                <td>
                                    <!-- Edit Button -->
                                    <button class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#editModal<?= $p['id'] ?>">Edit</button>
                                    <!-- Delete -->
                                    <a href="vendor_products.php?delete=<?= $p['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this product?');">Delete</a>
                                </td>
                            </tr>

                            <!-- Edit Modal -->
                            <div class="modal fade" id="editModal<?= $p['id'] ?>" tabindex="-1">
                                <div class="modal-dialog">
                                    <div class="modal-content">
                                        <!--  Fixed form action -->
                                        <form method="POST" action="update_product.php" enctype="multipart/form-data">
                                            <div class="modal-header">
                                                <h5 class="modal-title">Edit Product</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <div class="modal-body">
                                                <input type="hidden" name="id" value="<?= $p['id'] ?>">
                                                <div class="mb-3">
                                                    <label>Item Name</label>
                                                    <input type="text" name="item_name" class="form-control" value="<?= htmlspecialchars($p['item_name']) ?>" required>
                                                </div>
                                                <div class="mb-3">
                                                    <label>Description</label>
                                                    <textarea name="item_description" class="form-control" required><?= htmlspecialchars($p['item_description']) ?></textarea>
                                                </div>
                                                <div class="mb-3">
                                                    <label>Type</label>
                                                    <select name="item_type" class="form-control" required>
                                                        <option value="IT and supporting tech" <?= $p['item_type']=="IT and supporting tech"?"selected":"" ?>>IT and supporting tech</option>
                                                        <option value="Medications and pharmacy supplies" <?= $p['item_type']=="Medications and pharmacy supplies"?"selected":"" ?>>Medications and pharmacy supplies</option>
                                                        <option value="Consumables and disposables" <?= $p['item_type']=="Consumables and disposables"?"selected":"" ?>>Consumables and disposables</option>
                                                        <option value="Diagnostic Equipment" <?= $p['item_type']=="Diagnostic Equipment"?"selected":"" ?>>Diagnostic Equipment</option>
                                                    </select>
                                                </div>
                                                <div class="mb-3">
                                                    <label>Medication Form</label>
                                                    <select name="sub_type" class="form-control">
                                                        <option value="">-- Select Form --</option>
                                                        <option value="Liquid" <?= $p['sub_type']=="Liquid"?"selected":"" ?>>Liquid</option>
                                                        <option value="Solid" <?= $p['sub_type']=="Solid"?"selected":"" ?>>Solid</option>
                                                        <option value="Semi-Solid" <?= $p['sub_type']=="Semi-Solid"?"selected":"" ?>>Semi-Solid</option>
                                                        <option value="Injectable" <?= $p['sub_type']=="Injectable"?"selected":"" ?>>Injectable</option>
                                                        <option value="Inhaled" <?= $p['sub_type']=="Inhaled"?"selected":"" ?>>Inhaled</option>
                                                    </select>
                                                </div>
                                                <div class="mb-3">
                                                    <label>Price</label>
                                                    <input type="number" step="0.01" name="price" class="form-control" value="<?= $p['price'] ?>" required>
                                                </div>
                                                <div class="mb-3">
                                                    <label>Picture</label>
                                                    <input type="file" name="picture" class="form-control">
                                                    <?php if ($p['picture']): ?><img src="<?= $p['picture'] ?>" width="60" class="mt-2"><?php endif; ?>
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="submit" name="update_product" class="btn btn-success">Save</button>
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>
</div>

<!--  Add Product Modal -->
<div class="modal fade" id="addProductModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" enctype="multipart/form-data">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Product</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row mb-3">
                        <div class="col">
                            <label>Item Name</label>
                            <input type="text" name="item_name" class="form-control" required>
                        </div>
                        <div class="col">
                            <label>Type</label>
                            <select name="item_type" id="item_type" class="form-control" required onchange="toggleSubType()">
                                <option value="">-- Select Type --</option>
                                <option value="IT and supporting tech">IT and supporting tech</option>
                                <option value="Medications and pharmacy supplies">Medications and pharmacy supplies</option>
                                <option value="Consumables and disposables">Consumables and disposables</option>
                                <option value="Diagnostic Equipment">Diagnostic Equipment</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3" id="subTypeDiv" style="display:none;">
                        <label>Medication Form</label>
                        <select name="sub_type" class="form-control">
                            <option value="">-- Select Form --</option>
                            <option value="Liquid">Liquid</option>
                            <option value="Solid">Solid</option>
                            <option value="Semi-Solid">Semi-Solid</option>
                            <option value="Injectable">Injectable</option>
                            <option value="Inhaled">Inhaled</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label>Description</label>
                        <textarea name="item_description" class="form-control" required></textarea>
                    </div>
                    <div class="row mb-3">
                        <div class="col">
                            <label>Price</label>
                            <input type="number" step="0.01" name="price" class="form-control" required>
                        </div>
                        <div class="col">
                            <label>Picture</label>
                            <input type="file" name="picture" class="form-control">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" name="add_product" class="btn btn-primary">Add Product</button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>

</body>
</html>
