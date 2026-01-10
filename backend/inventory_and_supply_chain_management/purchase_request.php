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

        // ❌ Validation: ensure at least one item with a name
        $valid_items = array_filter($items, fn($i) => !empty(trim($i['name'] ?? '')));
        if (count($valid_items) === 0) {
            throw new Exception("Please add at least one item before submitting.");
        }

        $stmt = $pdo->prepare("
            INSERT INTO department_request
            (user_id, department, department_id, month, items, total_items, status)
            VALUES
            (:user_id, :department, :department_id, :month, :items, :total_items, 'Pending')
        ");

        $stmt->execute([
            ':user_id'        => $user_id,
            ':department'     => $department,
            ':department_id'  => $department_id,
            ':month'          => date('Y-m-d'),
            ':items'          => json_encode($valid_items, JSON_UNESCAPED_UNICODE),
            ':total_items'    => count($valid_items)
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
.pcs-box-input { min-width:90px; }
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

<form method="POST" id="requestForm">
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
<button type="button" class="btn btn-sm btn-danger btn-remove">✕</button>
</td>
</tr>
</tbody>
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

const addRowBtn = document.getElementById('addRowBtn');
const itemBody = document.getElementById('itemBody');
const requestForm = document.getElementById('requestForm');

// ➕ Add new row
addRowBtn.onclick = () => {
    const row = itemBody.querySelector('tr').cloneNode(true);
    row.querySelectorAll('input, select').forEach(el => {
        el.name = el.name.replace(/\[\d+\]/, `[${itemIndex}]`);
        if (el.classList.contains('quantity')) el.value = 1;
        if (el.classList.contains('pcs-per-box')) { el.value = 1; el.disabled = true; }
        if (el.classList.contains('total-pcs')) el.value = 1;
        if (el.classList.contains('name')) el.value = '';
        if (el.classList.contains('description')) el.value = '';
    });
    itemBody.appendChild(row);
    itemIndex++;
};

// ✖ Remove row (if more than one)
itemBody.addEventListener('click', e => {
    if (e.target.classList.contains('btn-remove')) {
        if (itemBody.querySelectorAll('tr').length > 1) {
            e.target.closest('tr').remove();
        } else {
            alert("At least one item must be in the request.");
        }
    }
});

// Update Total Pcs based on Qty & Unit
itemBody.addEventListener('input', e => {
    const row = e.target.closest('tr');
    if (!row) return;

    const unit = row.querySelector('.unit').value;
    const qty = parseFloat(row.querySelector('.quantity').value) || 0;
    const pcsBox = row.querySelector('.pcs-per-box');
    const pcsPerBox = parseFloat(pcsBox.value) || 1;

    row.querySelector('.total-pcs').value = unit === 'box' ? qty * pcsPerBox : qty;
    pcsBox.disabled = unit !== 'box';
});

// ❌ Validate form before submit
requestForm.addEventListener('submit', e => {
    const rows = Array.from(itemBody.querySelectorAll('tr'));
    const hasItem = rows.some(row => {
        const name = row.querySelector('input[name*="[name]"]').value.trim();
        return name !== '';
    });

    if (!hasItem) {
        e.preventDefault();
        alert("Please add at least one item with a name before submitting.");
    }
});
</script>

</body>
</html>
