<?php
session_start();
include '../../SQL/config.php';

// Show all errors (for debugging)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// ✅ Ensure user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// ✅ Fetch logged-in user info
$user_stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ? LIMIT 1");
$user_stmt->execute([$user_id]);
$user = $user_stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    die("⚠️ User not found in database.");
}

// ✅ Get department details safely
$department = !empty($user['department']) ? $user['department'] : 'Unknown Department';
$user_department_id = !empty($user['department_id']) ? $user['department_id'] : 0;

// ✅ Validate that department_id exists in departments table
$dept_stmt = $pdo->prepare("SELECT department_id FROM departments WHERE department_id = ?");
$dept_stmt->execute([$user_department_id]);

if ($dept_stmt->rowCount() > 0) {
    $department_id = $user_department_id;
} else {
    // fallback to a default department that exists (e.g., Admin or 1)
    $department_id = 1;
}

// ✅ Fetch available vendor products
try {
    $stmt = $pdo->query("SELECT * FROM vendor_products ORDER BY item_name ASC");
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Error loading products: " . $e->getMessage());
}

// ✅ Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $month = date('Y-m');
        $items = json_encode($_POST['items'], JSON_UNESCAPED_UNICODE);
        $total_price = $_POST['total_price'];

        $stmt = $pdo->prepare("INSERT INTO purchase_requests 
            (user_id, department, department_id, month, items, total_price, status)
            VALUES (:user_id, :department, :department_id, :month, :items, :total_price, 'Pending')");
        $stmt->execute([
            ':user_id' => $user_id,
            ':department' => $department,
            ':department_id' => $department_id,
            ':month' => $month,
            ':items' => $items,
            ':total_price' => $total_price
        ]);

        $success = "✅ Purchase request submitted successfully!";
    } catch (PDOException $e) {
        $error = "❌ Error submitting request: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Purchase Request</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<style>
body {background:#f8fafc}
.card {box-shadow:0 6px 20px rgba(0,0,0,0.08);border-radius:12px}
.table th {background:#f1f5f9}
.btn-remove {color:#dc3545;border:none;background:transparent}
.btn-remove:hover {color:#b91c1c}
.modal-img {width:60px;height:60px;object-fit:cover;border-radius:6px}
#searchInput {width: 300px;}
</style>
</head>
<body class="p-4">

<div class="container">
<div class="card p-4">
    <h2 class="text-center mb-4 text-primary">Department Purchase Request</h2>

    <div class="alert alert-info">
        <strong>Department:</strong> <?= htmlspecialchars($department) ?><br>
        <strong>Month:</strong> <?= date('F Y') ?>
    </div>

    <?php if(isset($success)): ?>
        <div class="alert alert-success"><?= $success ?></div>
    <?php elseif(isset($error)): ?>
        <div class="alert alert-danger"><?= $error ?></div>
    <?php endif; ?>

    <form method="POST" id="purchaseForm">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5>Requested Items</h5>
            <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#itemModal">
                + View Item List
            </button>
        </div>

        <div class="table-responsive">
            <table class="table table-bordered align-middle" id="itemTable">
                <thead class="text-center">
                    <tr>
                        <th>Item Name</th>
                        <th>Description</th>
                        <th>Qty</th>
                        <th>Market Price (₱)</th>
                        <th>Total (₱)</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody id="itemBody"></tbody>
            </table>
        </div>

        <div class="text-end mt-3">
            <label class="fw-bold me-2">Total Estimated Price (₱):</label>
            <input type="number" name="total_price" id="total_price" class="form-control d-inline-block w-auto" readonly required>
        </div>

        <div class="text-center mt-4">
            <button type="submit" class="btn btn-primary btn-lg">
                <i class="bi bi-send"></i> Submit Request
            </button>
        </div>
    </form>
</div>
</div>

<!-- 🧾 Modal: Item List -->
<div class="modal fade" id="itemModal" tabindex="-1">
<div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content">
        <div class="modal-header bg-primary text-white">
            <h5 class="modal-title">Available Items</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">

            <!-- 🔍 Search bar -->
            <div class="d-flex justify-content-end mb-3">
                <input type="text" id="searchInput" class="form-control" placeholder="Search item...">
            </div>

            <table class="table table-hover table-striped align-middle" id="productTable">
                <thead>
                    <tr>
                        <th>Image</th>
                        <th>Item Name</th>
                        <th>Description</th>
                        <th>Type</th>
                        <th>Price (₱)</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($products as $p): ?>
                    <tr>
                        <td>
                            <?php 
                                $fileName = basename($p['picture']); 
                                $imgFile = __DIR__ . "/uploads/" . $fileName; 
                                $imgSrc  = "uploads/" . $fileName;

                                if (!empty($fileName) && file_exists($imgFile)) {
                                    echo "<img src='$imgSrc' alt='Item Image' class='modal-img'>";
                                } else {
                                    echo "<img src='uploads/no_image.png' alt='No Image' class='modal-img'>";
                                }
                            ?>
                        </td>
                        <td><?= htmlspecialchars($p['item_name']) ?></td>
                        <td><?= htmlspecialchars($p['item_description']) ?></td>
                        <td><?= htmlspecialchars($p['item_type']) ?></td>
                        <td><?= number_format($p['price'], 2) ?></td>
                        <td>
                            <button type="button" class="btn btn-sm btn-success select-item"
                                data-name="<?= htmlspecialchars($p['item_name']) ?>"
                                data-desc="<?= htmlspecialchars($p['item_description']) ?>"
                                data-price="<?= $p['price'] ?>">
                                Add
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Keep modal open and add search functionality
let itemIndex = 0;

// ✅ Add item without closing modal
document.addEventListener('click', e => {
    if (e.target.classList.contains('select-item')) {
        const name = e.target.dataset.name;
        const desc = e.target.dataset.desc;
        const price = parseFloat(e.target.dataset.price);
        addItemRow(name, desc, price);
    }
});

function addItemRow(name='', desc='', price='') {
    const tbody = document.getElementById('itemBody');
    const row = document.createElement('tr');
    row.innerHTML = `
        <td><input type="text" name="items[${itemIndex}][name]" class="form-control" value="${name}" readonly></td>
        <td><input type="text" name="items[${itemIndex}][description]" class="form-control" value="${desc}" readonly></td>
        <td><input type="number" name="items[${itemIndex}][quantity]" class="form-control qty" min="1" value="1" required></td>
        <td><input type="number" name="items[${itemIndex}][price]" class="form-control price" value="${price}" step="0.01" readonly></td>
        <td><input type="text" class="form-control item-total" readonly></td>
        <td class="text-center"><button type="button" class="btn-remove"><i class="bi bi-x-circle"></i></button></td>
    `;
    tbody.appendChild(row);
    itemIndex++;
    updateTotals();
}

// ✅ Update totals dynamically
document.addEventListener('input', e => {
    if (e.target.classList.contains('qty')) updateTotals();
});

document.addEventListener('click', e => {
    if (e.target.closest('.btn-remove')) {
        e.target.closest('tr').remove();
        updateTotals();
    }
});

function updateTotals() {
    let total = 0;
    document.querySelectorAll('#itemBody tr').forEach(row => {
        const qty = parseFloat(row.querySelector('.qty').value) || 0;
        const price = parseFloat(row.querySelector('.price').value) || 0;
        const subtotal = qty * price;
        row.querySelector('.item-total').value = subtotal.toFixed(2);
        total += subtotal;
    });
    document.getElementById('total_price').value = total.toFixed(2);
}

// ✅ Live search filter
document.getElementById('searchInput').addEventListener('keyup', function() {
    const filter = this.value.toLowerCase();
    document.querySelectorAll('#productTable tbody tr').forEach(row => {
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(filter) ? '' : 'none';
    });
});
</script>
</body>
</html>
