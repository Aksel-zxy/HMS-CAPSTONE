<?php
session_start();
include '../../SQL/config.php';

error_reporting(E_ALL);
ini_set('display_errors', 1);

// 🔐 Ensure login
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// 👤 Fetch user info
$user_stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ? LIMIT 1");
$user_stmt->execute([$user_id]);
$user = $user_stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    die("User not found.");
}

// ✅ User details
$full_name = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
$department = $user['department'] ?? 'Unknown Department';
$department_id = $user['department_id'] ?? 0;
$request_date = date('F d, Y');

// 📤 Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $items = $_POST['items'] ?? [];
        $grand_total = $_POST['grand_total'] ?? 0;

        if ($grand_total <= 0) {
            throw new Exception("Grand total must be greater than zero.");
        }

        $stmt = $pdo->prepare("
            INSERT INTO department_request
            (user_id, department, department_id, month, items, total_items, grand_total, status)
            VALUES
            (:user_id, :department, :department_id, :month, :items, :total_items, :grand_total, 'Pending')
        ");

        $stmt->execute([
            ':user_id'        => $user_id,
            ':department'     => $department,
            ':department_id'  => $department_id,
            ':month'          => date('Y-m-d'),
            ':items'          => json_encode($items, JSON_UNESCAPED_UNICODE),
            ':total_items'    => count($items),
            ':grand_total'    => $grand_total
        ]);

        $success = "Purchase request successfully submitted!";
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Purchase Request</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

<style>
body { background:#f8fafc; }
.card { border-radius:12px; }
.table { table-layout: fixed; }
th, td { vertical-align: middle; text-align:center; }

.unit-select { min-width:120px; }
.qty-input { min-width:80px; }
.price-input { min-width:120px; }
.pcs-box-input { min-width:90px; }
.total-input { background:#f8fafc; min-width:120px; }
.total-pcs-input { background:#f8fafc; min-width:90px; }
.info-box strong { display:inline-block; width:120px; }
</style>
</head>

<body class="p-4">
<div class="container">
<div class="card p-4">

<h4 class="text-center text-primary mb-4">📋 Purchase Request Form</h4>

<!-- 🔎 REQUEST INFORMATION -->
<div class="alert alert-info info-box mb-4">
    <div><strong>Department:</strong> <?= htmlspecialchars($department) ?></div>
    <div><strong>Request Date:</strong> <?= $request_date ?></div>
</div>

<?php if(isset($success)): ?>
<div class="alert alert-success"><?= $success ?></div>
<?php elseif(isset($error)): ?>
<div class="alert alert-danger"><?= $error ?></div>
<?php endif; ?>

<form method="POST">
<div class="table-responsive">
<table class="table table-bordered align-middle">
<thead class="table-light">
<tr>
<th>Item</th>
<th>Description</th>
<th>Unit</th>
<th>Qty</th>
<th>Pcs / Box</th>
<th>Total Pcs</th>
<th>Price</th>
<th>Total</th>
<th>Action</th>
</tr>
</thead>

<tbody id="itemBody">
<tr>
<td><input type="text" name="items[0][name]" class="form-control form-control-sm" required></td>
<td><input type="text" name="items[0][description]" class="form-control form-control-sm"></td>

<td>
<select name="items[0][unit]" class="form-select form-select-sm unit unit-select">
    <option value="pcs">Per Piece</option>
    <option value="box">Per Box</option>
</select>
</td>

<td>
<input type="number" name="items[0][quantity]" class="form-control form-control-sm quantity qty-input" value="1" min="1">
</td>

<td>
<input type="number" name="items[0][pcs_per_box]" class="form-control form-control-sm pcs-per-box pcs-box-input" value="1" min="1" disabled>
</td>

<td>
<input type="number" name="items[0][total_pcs]" class="form-control form-control-sm total-pcs total-pcs-input" value="1" readonly>
</td>

<td>
<input type="number" name="items[0][price]" class="form-control form-control-sm price price-input"
       step="0.01" min="0" placeholder="₱ / pcs">
</td>

<td>
<input type="text" class="form-control form-control-sm total total-input" readonly value="₱0.00">
</td>

<td>
<button type="button" class="btn btn-sm btn-danger btn-remove">✕</button>
</td>
</tr>
</tbody>

<tfoot>
<tr>
<td colspan="7" class="text-end fw-bold">Grand Total</td>
<td>
<input type="text" id="grandTotalDisplay" class="form-control fw-bold text-success" readonly value="₱0.00">
<input type="hidden" name="grand_total" id="grandTotal">
</td>
<td></td>
</tr>
</tfoot>
</table>
</div>

<div class="text-center mt-3">
<button type="button" id="addRowBtn" class="btn btn-outline-primary">➕ Add Item</button>
</div>

<div class="text-center mt-4">
<button type="submit" class="btn btn-primary btn-lg">Submit Request</button>
</div>
</form>

</div>
</div>

<script>
let itemIndex = 1;
const currency = "₱";

document.getElementById('addRowBtn').onclick = () => {
    const row = document.querySelector('#itemBody tr').cloneNode(true);
    row.querySelectorAll('input, select').forEach(el => {
        el.name = el.name.replace(/\[\d+\]/, `[${itemIndex}]`);
        if (el.classList.contains('quantity')) el.value = 1;
        if (el.classList.contains('pcs-per-box')) { el.value = 1; el.disabled = true; }
        if (el.classList.contains('total-pcs')) el.value = 1;
        if (el.classList.contains('price')) { el.value = ''; el.placeholder = '₱ / pcs'; }
        if (el.classList.contains('total')) el.value = currency + '0.00';
    });
    document.getElementById('itemBody').appendChild(row);
    itemIndex++;
};

function calculateTotals() {
    let grand = 0;

    document.querySelectorAll('#itemBody tr').forEach(row => {
        const unit = row.querySelector('.unit').value;
        const qty = parseFloat(row.querySelector('.quantity').value) || 0;
        const pcsBox = row.querySelector('.pcs-per-box');
        const pcsPerBox = parseFloat(pcsBox.value) || 1;
        const priceInput = row.querySelector('.price');
        const price = parseFloat(priceInput.value) || 0;

        let totalPcs = qty;

        if (unit === 'box') {
            pcsBox.disabled = false;
            totalPcs = qty * pcsPerBox;
            priceInput.placeholder = '₱ / box';
        } else {
            pcsBox.disabled = true;
            pcsBox.value = 1;
            priceInput.placeholder = '₱ / pcs';
        }

        row.querySelector('.total-pcs').value = totalPcs;

        // 💰 Price aligned with unit
        let lineTotal = unit === 'box'
            ? qty * price
            : totalPcs * price;

        row.querySelector('.total').value = currency + lineTotal.toFixed(2);
        grand += lineTotal;
    });

    document.getElementById('grandTotalDisplay').value = currency + grand.toFixed(2);
    document.getElementById('grandTotal').value = grand.toFixed(2);
}

document.addEventListener('input', e => {
    if (e.target.closest('table')) calculateTotals();
});

document.addEventListener('click', e => {
    if (e.target.classList.contains('btn-remove')) {
        e.target.closest('tr').remove();
        calculateTotals();
    }
});
</script>

</body>
</html>