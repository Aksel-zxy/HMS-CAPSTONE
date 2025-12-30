<?php
session_start();
include '../../SQL/config.php';

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Ensure login
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Fetch user info
$user_stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ? LIMIT 1");
$user_stmt->execute([$user_id]);
$user = $user_stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) die("User not found.");

$department = $user['department'] ?? 'Unknown Department';
$department_id = $user['department_id'] ?? 0;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $date = date('Y-m-d');
        $items = $_POST['items'] ?? [];
        $grand_total = $_POST['grand_total'] ?? 0;

        if ($grand_total <= 0) throw new Exception("Grand total must be greater than zero.");

        $items_json = json_encode($items, JSON_UNESCAPED_UNICODE);
        $total_items = count($items);

        $stmt = $pdo->prepare("
            INSERT INTO department_request
            (user_id, department, department_id, month, items, total_items, grand_total, status)
            VALUES
            (:user_id, :department, :department_id, :month, :items, :total_items, :grand_total, 'Pending')
        ");

        $stmt->execute([
            ':user_id'=>$user_id,
            ':department'=>$department,
            ':department_id'=>$department_id,
            ':month'=>$date,
            ':items'=>$items_json,
            ':total_items'=>$total_items,
            ':grand_total'=>$grand_total
        ]);

        $success = "Request successfully submitted!";
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Department Request Form</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<style>
body { background:#f8fafc; font-family:'Segoe UI',sans-serif; }
.card { border-radius:12px; box-shadow:0 6px 20px rgba(0,0,0,.08); }
.table th { background:#f1f5f9; }
.btn-remove { background:none; border:none; color:#dc3545; }
.btn-remove:hover { color:#b91c1c; }
</style>
</head>
<body class="p-4">
<div class="container">
<div class="card p-4">

<h3 class="text-center text-primary mb-3">📋 Department Request Form</h3>

<div class="alert alert-info">
<strong>Department:</strong> <?= htmlspecialchars($department) ?><br>
<strong>Date:</strong> <?= date('F d, Y') ?>
</div>

<?php if(isset($success)): ?>
<div class="alert alert-success"><?= $success ?></div>
<?php elseif(isset($error)): ?>
<div class="alert alert-danger"><?= $error ?></div>
<?php endif; ?>

<form method="POST">
<div class="table-responsive">
<table class="table table-bordered align-middle" id="itemTable">
<thead class="text-center">
<tr>
<th>Item</th>
<th>Description</th>
<th>Qty</th>
<th>Price</th>
<th>Total</th>
<th>Action</th>
</tr>
</thead>
<tbody id="itemBody">
<tr>
<td><input type="text" name="items[0][name]" class="form-control" required></td>
<td><input type="text" name="items[0][description]" class="form-control"></td>
<td><input type="number" name="items[0][quantity]" class="form-control quantity" min="1" value="1" required></td>
<td><input type="number" name="items[0][price]" class="form-control price" min="0.01" step="0.01" value="0" required></td>
<td><input type="text" class="form-control total" readonly value="₱0.00"></td>
<td class="text-center"><button type="button" class="btn-remove"><i class="bi bi-x-circle"></i></button></td>
</tr>
</tbody>
<tfoot>
<tr>
<td colspan="4" class="text-end fw-bold">Grand Total</td>
<td>
<input type="text" id="grandTotalDisplay" class="form-control fw-bold text-success" readonly value="₱0.00">
<input type="hidden" name="grand_total" id="grandTotal">
</td>
<td></td>
</tr>
</tfoot>
</table>
</div>

<div class="text-center my-3">
<button type="button" id="addRowBtn" class="btn btn-outline-primary">
<i class="bi bi-plus-circle"></i> Add Item
</button>
</div>

<div class="text-center">
<button type="submit" class="btn btn-primary btn-lg px-5">
<i class="bi bi-send"></i> Submit
</button>
</div>
</form>
</div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
let itemIndex = 1;
const currency = "₱";

document.getElementById('addRowBtn').addEventListener('click', ()=>{
    const tbody = document.getElementById('itemBody');
    const row = document.createElement('tr');
    row.innerHTML = `
<td><input type="text" name="items[${itemIndex}][name]" class="form-control" required></td>
<td><input type="text" name="items[${itemIndex}][description]" class="form-control"></td>
<td><input type="number" name="items[${itemIndex}][quantity]" class="form-control quantity" min="1" value="1" required></td>
<td><input type="number" name="items[${itemIndex}][price]" class="form-control price" min="0.01" step="0.01" value="0" required></td>
<td><input type="text" class="form-control total" readonly value="${currency}0.00"></td>
<td class="text-center"><button type="button" class="btn-remove"><i class="bi bi-x-circle"></i></button></td>
`;
    tbody.appendChild(row);
    itemIndex++;
});

function calculateTotals(){
    let grand = 0;
    document.querySelectorAll('#itemBody tr').forEach(row=>{
        const qty = parseFloat(row.querySelector('.quantity').value)||0;
        const price = parseFloat(row.querySelector('.price').value)||0;
        const total = qty*price;
        row.querySelector('.total').value = currency + total.toFixed(2);
        grand += total;
    });
    document.getElementById('grandTotalDisplay').value = currency + grand.toFixed(2);
    document.getElementById('grandTotal').value = grand.toFixed(2);
}

document.addEventListener('input', e=>{
    if(e.target.classList.contains('quantity')||e.target.classList.contains('price')) calculateTotals();
});

document.addEventListener('click', e=>{
    if(e.target.closest('.btn-remove')) {
        e.target.closest('tr').remove();
        calculateTotals();
    }
});
</script>
</body>
</html>
