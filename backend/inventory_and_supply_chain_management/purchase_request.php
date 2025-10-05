<?php
session_start();
include 'db.php'; // ✅ adjust as needed

// Example session values for testing (remove in production)
if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 1;
    $_SESSION['department'] = 'Medical Laboratory';
    $_SESSION['department_id'] = 2;
}

// Get available items from vendor_products for modal
$products = [];
$result = $conn->query("SELECT * FROM vendor_products ORDER BY item_name ASC");
while ($row = $result->fetch_assoc()) {
    $products[] = $row;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = $_SESSION['user_id'];
    $department = $_SESSION['department'];
    $department_id = $_SESSION['department_id'];
    $month = date('Y-m'); // automatic month
    $items = json_encode($_POST['items']);
    $total_price = $_POST['total_price'];

    $stmt = $conn->prepare("INSERT INTO purchase_requests (user_id, department, department_id, month, items, total_price, status)
                            VALUES (?, ?, ?, ?, ?, ?, 'Pending')");
    $stmt->bind_param("isissd", $user_id, $department, $department_id, $month, $items, $total_price);

    if ($stmt->execute()) {
        $success = "✅ Purchase request submitted successfully!";
    } else {
        $error = "❌ Error submitting request: " . $stmt->error;
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Purchase Request</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
body{background:#f8fafc}
.card{box-shadow:0 6px 20px rgba(0,0,0,0.08);border-radius:12px}
.table th{background:#f1f5f9}
.btn-remove{color:#dc3545;border:none;background:transparent}
.btn-remove:hover{color:#b91c1c}
.modal-img{width:60px;height:60px;object-fit:cover;border-radius:6px}
</style>
</head>
<body class="p-4">

<div class="container">
<div class="card p-4">
    <h2 class="text-center mb-4 text-primary">Department Purchase Request</h2>

    <div class="alert alert-info">
        <strong>Department:</strong> <?= htmlspecialchars($_SESSION['department']) ?><br>
        <strong>Department ID:</strong> <?= htmlspecialchars($_SESSION['department_id']) ?><br>
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

        <div class="text-end">
            <label class="fw-bold me-2">Total Estimated Price (₱):</label>
            <input type="number" name="total_price" id="total_price" class="form-control d-inline-block w-auto" readonly required>
        </div>

        <div class="text-center mt-4">
            <button type="submit" class="btn btn-primary btn-lg">Submit Request</button>
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
            <table class="table table-hover table-striped">
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
                        <td><img src="../<?= htmlspecialchars($p['picture']) ?>" class="modal-img"></td>
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
let itemIndex = 0;

// When an item is selected from modal
document.addEventListener('click', e => {
    if (e.target.classList.contains('select-item')) {
        const name = e.target.dataset.name;
        const desc = e.target.dataset.desc;
        const price = parseFloat(e.target.dataset.price);
        addItemRow(name, desc, price);
        const modal = bootstrap.Modal.getInstance(document.getElementById('itemModal'));
        modal.hide();
    }
});

// Add a new row
function addItemRow(name='', desc='', price='') {
    const tbody = document.getElementById('itemBody');
    const row = document.createElement('tr');
    row.innerHTML = `
        <td><input type="text" name="items[${itemIndex}][name]" class="form-control" value="${name}" required readonly></td>
        <td><input type="text" name="items[${itemIndex}][description]" class="form-control" value="${desc}" readonly></td>
        <td><input type="number" name="items[${itemIndex}][quantity]" class="form-control qty" min="1" value="1" required></td>
        <td><input type="number" name="items[${itemIndex}][price]" class="form-control price" value="${price}" step="0.01" required readonly></td>
        <td><input type="text" class="form-control item-total" readonly></td>
        <td class="text-center"><button type="button" class="btn-remove"><i class="bi bi-x-circle"></i></button></td>
    `;
    tbody.appendChild(row);
    itemIndex++;
    updateTotals();
}

// Auto-update totals
document.addEventListener('input', e => {
    if (e.target.classList.contains('qty')) updateTotals();
});

// Remove row
document.addEventListener('click', e => {
    if (e.target.closest('.btn-remove')) {
        e.target.closest('tr').remove();
        updateTotals();
    }
});

// Compute totals
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
</script>
</body>
</html>
