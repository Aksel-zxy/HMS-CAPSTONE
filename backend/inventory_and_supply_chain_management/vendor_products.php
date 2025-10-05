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

$error = '';
$success = '';

/* -----------------------------
   Handle Add Product (POST)
   - Only allow image uploads (validate MIME)
   ----------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_product'])) {
    $name = trim($_POST['item_name'] ?? '');
    $desc = trim($_POST['item_description'] ?? '');
    $type = trim($_POST['item_type'] ?? '');
    $sub_type = trim($_POST['sub_type'] ?? '');
    $price = $_POST['price'] ?? 0;
    $unit_type = $_POST['unit_type'] ?? 'Piece';
    $pcs_per_box = ($unit_type === "Box") ? ($_POST['pcs_per_box'] ?? null) : null;
    $picture = null;

    if (!empty($_FILES['picture']['name'])) {
        // Validate MIME type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $_FILES['picture']['tmp_name']);
        finfo_close($finfo);

        $allowed = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
        if (!in_array($mime, $allowed)) {
            $error = "Only image files (JPG, PNG, GIF, WEBP) are allowed.";
        } else {
            $ext = pathinfo($_FILES['picture']['name'], PATHINFO_EXTENSION);
            $safeName = time() . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
            $targetDir = "uploads/";
            if (!is_dir($targetDir)) mkdir($targetDir, 0777, true);
            $targetFile = $targetDir . $safeName;

            if (!move_uploaded_file($_FILES['picture']['tmp_name'], $targetFile)) {
                $error = "Failed to upload image.";
            } else {
                $picture = $targetFile;
            }
        }
    }

    if (empty($error)) {
        $stmt = $pdo->prepare("INSERT INTO vendor_products 
            (vendor_id, item_name, item_description, item_type, sub_type, price, unit_type, pcs_per_box, picture) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$vendor_id, $name, $desc, $type, $sub_type, $price, $unit_type, $pcs_per_box, $picture]);
        // redirect to avoid resubmission
        header("Location: vendor_products.php");
        exit;
    }
}

/* -----------------------------
   Delete Product (GET)
   ----------------------------- */
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    // Optionally fetch picture to remove file (not removing here to keep it simple)
    $stmt = $pdo->prepare("DELETE FROM vendor_products WHERE id = ? AND vendor_id = ?");
    $stmt->execute([$id, $vendor_id]);
    header("Location: vendor_products.php");
    exit;
}

/* -----------------------------
   Pagination & Filters (GET)
   ----------------------------- */
$limit = 6;
$page = isset($_GET['page_num']) ? (int)$_GET['page_num'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

$search = trim($_GET['search'] ?? '');
$typeFilter = trim($_GET['type'] ?? '');

$query = "SELECT * FROM vendor_products WHERE vendor_id = :vendor_id";
$countQuery = "SELECT COUNT(*) FROM vendor_products WHERE vendor_id = :vendor_id";
$params = [':vendor_id' => $vendor_id];

if ($search !== '') {
    $query .= " AND (item_name LIKE :search OR item_description LIKE :search)";
    $countQuery .= " AND (item_name LIKE :search OR item_description LIKE :search)";
    $params[':search'] = "%$search%";
}

if ($typeFilter !== '') {
    $query .= " AND item_type = :type";
    $countQuery .= " AND item_type = :type";
    $params[':type'] = $typeFilter;
}

$query .= " ORDER BY item_name ASC LIMIT :limit OFFSET :offset";

$stmt = $pdo->prepare($query);
foreach ($params as $key => $val) {
    $stmt->bindValue($key, $val);
}
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmtCount = $pdo->prepare($countQuery);
$stmtCount->execute($params);
$totalProducts = (int)$stmtCount->fetchColumn();
$totalPages = $totalProducts > 0 ? (int)ceil($totalProducts / $limit) : 1;

/* -----------------------------
   Helper functions to generate HTML fragments (used for AJAX)
   ----------------------------- */
function esc($s) {
    return htmlspecialchars((string)$s, ENT_QUOTES);
}

function renderRowsHtml($products) {
    $html = '';
    foreach ($products as $p) {
        $img = $p['picture'] ? '<img src="' . esc($p['picture']) . '" class="product-thumb" alt="thumb">' : '';
        $item_name = esc($p['item_name']);
        $item_desc = esc($p['item_description']);
        $item_type = esc($p['item_type']);
        $sub_type = esc($p['sub_type'] ?? '-');
        $unit_type = esc($p['unit_type'] ?? 'Piece');
        $pcs = esc($p['pcs_per_box'] ?? '');
        $price = number_format($p['price'], 2);
        $id = (int)$p['id'];

        // data attributes for populating the single edit modal
        $dataAttrs = 'data-id="' . $id . '"'
                   . ' data-item_name="' . esc($p['item_name']) . '"'
                   . ' data-item_description="' . esc($p['item_description']) . '"'
                   . ' data-item_type="' . esc($p['item_type']) . '"'
                   . ' data-sub_type="' . esc($p['sub_type'] ?? '') . '"'
                   . ' data-unit_type="' . esc($p['unit_type'] ?? 'Piece') . '"'
                   . ' data-pcs_per_box="' . esc($p['pcs_per_box'] ?? '') . '"'
                   . ' data-price="' . esc($p['price']) . '"'
                   . ' data-picture="' . esc($p['picture'] ?? '') . '"';

        $html .= "<tr>
            <td>{$img}</td>
            <td>{$item_name}</td>
            <td>{$item_desc}</td>
            <td>{$item_type}</td>
            <td>{$sub_type}</td>
            <td>{$unit_type}" . (($unit_type === "Box" && $pcs) ? " ({$pcs} pcs)" : "") . "</td>
            <td>₱{$price}</td>
            <td>
                <button type=\"button\" class=\"btn btn-sm btn-warning btn-edit\" {$dataAttrs}>Edit</button>
                <a href=\"vendor_products.php?delete={$id}\" class=\"btn btn-sm btn-danger\" onclick=\"return confirm('Are you sure you want to delete this product?');\">Delete</a>
            </td>
        </tr>";
    }
    return $html;
}

function renderPaginationHtml($currentPage, $totalPages, $search, $type) {
    // Build a small window of pages around current page
    $html = '<ul class="pagination justify-content-center">';
    // Prev
    $prevPage = max(1, $currentPage - 1);
    $disabledPrev = $currentPage <= 1 ? ' disabled' : '';
    $html .= '<li class="page-item' . $disabledPrev . '"><a class="page-link" href="?page_num=' . $prevPage . '&search=' . urlencode($search) . '&type=' . urlencode($type) . '" data-page="' . $prevPage . '">Previous</a></li>';

    $window = 2; // pages on each side
    $start = max(1, $currentPage - $window);
    $end = min($totalPages, $currentPage + $window);

    if ($start > 1) {
        $html .= '<li class="page-item"><a class="page-link" href="?page_num=1&search=' . urlencode($search) . '&type=' . urlencode($type) . '" data-page="1">1</a></li>';
        if ($start > 2) $html .= '<li class="page-item disabled"><span class="page-link">…</span></li>';
    }

    for ($i = $start; $i <= $end; $i++) {
        $active = $i === $currentPage ? ' active' : '';
        $html .= '<li class="page-item' . $active . '"><a class="page-link" href="?page_num=' . $i . '&search=' . urlencode($search) . '&type=' . urlencode($type) . '" data-page="' . $i . '">' . $i . '</a></li>';
    }

    if ($end < $totalPages) {
        if ($end < $totalPages - 1) $html .= '<li class="page-item disabled"><span class="page-link">…</span></li>';
        $html .= '<li class="page-item"><a class="page-link" href="?page_num=' . $totalPages . '&search=' . urlencode($search) . '&type=' . urlencode($type) . '" data-page="' . $totalPages . '">' . $totalPages . '</a></li>';
    }

    // Next
    $nextPage = min($totalPages, $currentPage + 1);
    $disabledNext = $currentPage >= $totalPages ? ' disabled' : '';
    $html .= '<li class="page-item' . $disabledNext . '"><a class="page-link" href="?page_num=' . $nextPage . '&search=' . urlencode($search) . '&type=' . urlencode($type) . '" data-page="' . $nextPage . '">Next</a></li>';

    $html .= '</ul>';
    return $html;
}

/* -----------------------------
   If AJAX request: return table rows + pagination JSON
   ----------------------------- */
if (isset($_GET['ajax']) && $_GET['ajax'] == '1') {
    $rows = renderRowsHtml($products);
    $pagination = $totalPages > 1 ? renderPaginationHtml($page, $totalPages, $search, $typeFilter) : '';
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['table' => $rows, 'pagination' => $pagination]);
    exit;
}


?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Vendor Products</title>
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" type="text/css" href="assets/css/vendorsidebar.css">
    <link rel="stylesheet" type="text/css" href="assets/css/vendor_products.css">
    <style>
        /* Ensure uniform thumbnails */

    </style>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</head>
<body class="bg-light">
<div class="vendor-wrapper">
    <div class="vendor-sidebar">
        <?php include 'vendorsidebar.php'; ?>
    </div>

    <div class="vendor-main">
        <main class="p-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h3>Manage Product Listings</h3>
                <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addProductModal">+ Add Product</button>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-danger"><?= esc($error) ?></div>
            <?php endif; ?>

            <div class="card mb-3">
                <div class="card-body">
                    <form id="filterForm" method="GET" class="row g-2" onsubmit="event.preventDefault(); loadProducts(1);">
                        <div class="col-md-6">
                            <input id="searchInput" type="text" name="search" class="form-control" placeholder="Search products..." value="<?= esc($search) ?>">
                        </div>
                        <div class="col-md-4">
                            <select id="typeSelect" name="type" class="form-control">
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
                            <a id="resetBtn" href="vendor_products.php" class="btn btn-secondary">Reset</a>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card">
                <div class="card-header">My Products</div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table id="productsTable" class="table table-striped">
                            <thead>
                            <tr>
                                <th>Picture</th>
                                <th>Name</th>
                                <th>Description</th>
                                <th>Type</th>
                                <th>Sub Type</th>
                                <th>Unit</th>
                                <th>Price</th>
                                <th>Actions</th>
                            </tr>
                            </thead>
                            <tbody id="tableBody">
                                <?= renderRowsHtml($products) ?>
                            </tbody>
                        </table>
                    </div>

                    <div id="pagination" class="mt-3">
                        <?= ($totalPages > 1) ? renderPaginationHtml($page, $totalPages, $search, $typeFilter) : '' ?>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<!-- Single Edit Modal (populate dynamically) -->
<div class="modal fade" id="editModal" tabindex="-1">
  <div class="modal-dialog">
    <form id="editForm" method="POST" action="update_product.php" enctype="multipart/form-data" class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Edit Product</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
          <input type="hidden" name="id" id="edit_id">
          <div class="mb-3">
              <label>Item Name</label>
              <input type="text" name="item_name" id="edit_item_name" class="form-control" required>
          </div>
          <div class="mb-3">
              <label>Description</label>
              <textarea name="item_description" id="edit_item_description" class="form-control" required></textarea>
          </div>
          <div class="mb-3">
              <label>Type</label>
              <select name="item_type" id="edit_item_type" class="form-control" required>
                  <option value="IT and supporting tech">IT and supporting tech</option>
                  <option value="Medications and pharmacy supplies">Medications and pharmacy supplies</option>
                  <option value="Consumables and disposables">Consumables and disposables</option>
                  <option value="Diagnostic Equipment">Diagnostic Equipment</option>
              </select>
          </div>
          <div class="mb-3" id="edit_subtype_wrapper">
              <label>Medication Form</label>
              <select name="sub_type" id="edit_sub_type" class="form-control">
                  <option value="">-- Select Form --</option>
                  <option value="Liquid">Liquid</option>
                  <option value="Solid">Solid</option>
                  <option value="Semi-Solid">Semi-Solid</option>
                  <option value="Injectable">Injectable</option>
                  <option value="Inhaled">Inhaled</option>
              </select>
          </div>
          <div class="mb-3">
              <label>Unit Type</label>
              <select name="unit_type" id="edit_unit_type" class="form-control">
                  <option value="Piece">Piece</option>
                  <option value="Box">Box</option>
              </select>
          </div>
          <div class="mb-3">
              <label>Pcs per Box</label>
              <input type="number" name="pcs_per_box" id="edit_pcs_per_box" class="form-control">
          </div>
          <div class="mb-3">
              <label>Price</label>
              <input type="number" step="0.01" name="price" id="edit_price" class="form-control" required>
          </div>
          <div class="mb-3">
              <label>Picture (leave empty to keep current)</label>
              <input type="file" name="picture" id="edit_picture" class="form-control" accept="image/*">
              <div class="mt-2">
                  <img id="edit_preview" src="" alt="preview" style="max-width:120px; max-height:120px; object-fit:cover; display:none; border-radius:6px;">
              </div>
          </div>
      </div>
      <div class="modal-footer">
          <button type="submit" name="update_product" class="btn btn-success">Save</button>
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
      </div>
    </form>
  </div>
</div>

<!-- Add Product Modal -->
<div class="modal fade" id="addProductModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <form method="POST" enctype="multipart/form-data" class="modal-content">
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
                        <select name="item_type" id="add_item_type" class="form-control" required onchange="document.getElementById('add_subTypeDiv').style.display = (this.value==='Medications and pharmacy supplies') ? 'block':'none';">
                            <option value="">-- Select Type --</option>
                            <option value="IT and supporting tech">IT and supporting tech</option>
                            <option value="Medications and pharmacy supplies">Medications and pharmacy supplies</option>
                            <option value="Consumables and disposables">Consumables and disposables</option>
                            <option value="Diagnostic Equipment">Diagnostic Equipment</option>
                        </select>
                    </div>
                </div>
                <div class="mb-3" id="add_subTypeDiv" style="display:none;">
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
                    <label>Unit Type</label>
                    <select name="unit_type" id="unit_type" class="form-control" onchange="document.getElementById('pcs_per_box').disabled = (this.value!=='Box');">
                        <option value="Piece">Piece</option>
                        <option value="Box">Box</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label>Pcs per Box</label>
                    <input type="number" name="pcs_per_box" id="pcs_per_box" class="form-control" disabled>
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
                        <input type="file" name="picture" class="form-control" accept="image/*">
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

<script>
/* -----------------------------
   AJAX live search & pagination
   - Debounced input (500ms)
   - type select triggers search
   - intercept pagination clicks
   - populate single edit modal from data- attributes
   ----------------------------- */

const searchInput = document.getElementById('searchInput');
const typeSelect = document.getElementById('typeSelect');
const paginationContainer = document.getElementById('pagination');
const tableBody = document.getElementById('tableBody');
let debounceTimer = null;

function buildAjaxUrl(page = 1) {
    const s = encodeURIComponent(searchInput.value.trim());
    const t = encodeURIComponent(typeSelect.value);
    return `vendor_products.php?ajax=1&page_num=${page}&search=${s}&type=${t}`;
}

async function loadProducts(page = 1, pushState = true) {
    const url = buildAjaxUrl(page);
    try {
        const res = await fetch(url, {credentials: 'same-origin'});
        if (!res.ok) throw new Error('Network error');
        const data = await res.json();
        tableBody.innerHTML = data.table;
        paginationContainer.innerHTML = data.pagination;
        bindEditButtons();
        bindPaginationLinks();
        if (pushState) {
            const params = new URLSearchParams(window.location.search);
            params.set('page_num', page);
            if (searchInput.value.trim() !== '') params.set('search', searchInput.value.trim()); else params.delete('search');
            if (typeSelect.value !== '') params.set('type', typeSelect.value); else params.delete('type');
            const newUrl = window.location.pathname + '?' + params.toString();
            history.replaceState({}, '', newUrl);
        }
    } catch (err) {
        console.error(err);
    }
}

function bindPaginationLinks() {
    // delegate clicks
    const links = paginationContainer.querySelectorAll('a.page-link');
    links.forEach(a => {
        a.addEventListener('click', function(e) {
            e.preventDefault();
            const page = parseInt(this.getAttribute('data-page')) || 1;
            loadProducts(page);
        });
    });
}

function bindEditButtons() {
    const editBtns = document.querySelectorAll('.btn-edit');
    editBtns.forEach(btn => {
        btn.removeEventListener('click', handleEditClick);
        btn.addEventListener('click', handleEditClick);
    });
}

function handleEditClick(e) {
    const btn = e.currentTarget;
    const id = btn.getAttribute('data-id');
    // populate modal fields
    document.getElementById('edit_id').value = id;
    document.getElementById('edit_item_name').value = btn.getAttribute('data-item_name') || '';
    document.getElementById('edit_item_description').value = btn.getAttribute('data-item_description') || '';
    document.getElementById('edit_item_type').value = btn.getAttribute('data-item_type') || '';
    document.getElementById('edit_sub_type').value = btn.getAttribute('data-sub_type') || '';
    document.getElementById('edit_unit_type').value = btn.getAttribute('data-unit_type') || 'Piece';
    document.getElementById('edit_pcs_per_box').value = btn.getAttribute('data-pcs_per_box') || '';
    document.getElementById('edit_price').value = btn.getAttribute('data-price') || '';
    const pic = btn.getAttribute('data-picture') || '';
    const preview = document.getElementById('edit_preview');
    if (pic) {
        preview.src = pic;
        preview.style.display = 'inline-block';
    } else {
        preview.style.display = 'none';
    }
    // show/hide subtype wrapper
    document.getElementById('edit_subtype_wrapper').style.display = (document.getElementById('edit_item_type').value === 'Medications and pharmacy supplies') ? 'block' : 'none';
    // enable/disable pcs per box
    document.getElementById('edit_pcs_per_box').disabled = (document.getElementById('edit_unit_type').value !== 'Box');

    // clear file input
    document.getElementById('edit_picture').value = '';

    const myModal = new bootstrap.Modal(document.getElementById('editModal'));
    myModal.show();
}

// debounce search input
searchInput.addEventListener('input', function() {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => {
        loadProducts(1);
    }, 500);
});

// type filter change
typeSelect.addEventListener('change', function() {
    loadProducts(1);
});

// bind initial buttons and pagination on page load
document.addEventListener('DOMContentLoaded', function() {
    bindEditButtons();
    bindPaginationLinks();

    // update subtype visibility inside edit modal when type changes
    document.getElementById('edit_item_type').addEventListener('change', function() {
        document.getElementById('edit_subtype_wrapper').style.display = (this.value === 'Medications and pharmacy supplies') ? 'block' : 'none';
    });

    // enable/disable pcs per box in edit modal
    document.getElementById('edit_unit_type').addEventListener('change', function() {
        document.getElementById('edit_pcs_per_box').disabled = (this.value !== 'Box');
    });

    // when user selects new image in edit modal, preview it
    document.getElementById('edit_picture').addEventListener('change', function(e) {
        const file = this.files[0];
        if (file) {
            const preview = document.getElementById('edit_preview');
            preview.src = URL.createObjectURL(file);
            preview.style.display = 'inline-block';
        }
    });
});
</script>
</body>
</html>
