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

if (!$user) die("User not found.");

$full_name = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
$department = $user['department'] ?? 'Unknown Department';
$department_id = $user['department_id'] ?? 0;
$request_date = date('F d, Y');

/* =====================================================
   📤 HANDLE FORM SUBMISSION
=====================================================*/
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    try {

        $pdo->beginTransaction();

        $items = $_POST['items'] ?? [];
        $valid_items = array_filter($items, fn($i) => !empty(trim($i['name'] ?? '')));

        if (count($valid_items) === 0) {
            throw new Exception("Please add at least one item before submitting.");
        }

        // 1️⃣ Insert main request
        $stmt = $pdo->prepare("
            INSERT INTO department_request
            (user_id, department, department_id, month, total_items, status)
            VALUES (?, ?, ?, ?, ?, 'Pending')
        ");

        $stmt->execute([
            $user_id,
            $department,
            $department_id,
            date('Y-m-d'),
            count($valid_items)
        ]);

        $request_id = $pdo->lastInsertId();

        // 2️⃣ Insert items separately
        $item_stmt = $pdo->prepare("
            INSERT INTO department_request_items
            (request_id, item_name, description, unit, quantity, pcs_per_box, total_pcs)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");

        foreach ($valid_items as $item) {

            $quantity = (int)($item['quantity'] ?? 0);
            $pcs_box = (int)($item['pcs_per_box'] ?? 1);
            $total_pcs = (int)($item['total_pcs'] ?? 0);

            $item_stmt->execute([
                $request_id,
                $item['name'],
                $item['description'] ?? '',
                $item['unit'] ?? '',
                $quantity,
                $pcs_box,
                $total_pcs
            ]);
        }

        $pdo->commit();
        $success = "Purchase request successfully submitted!";

    } catch (Exception $e) {
        $pdo->rollBack();
        $error = $e->getMessage();
    }
}

/* =====================================================
   🔎 FETCH USER REQUESTS
=====================================================*/
$request_stmt = $pdo->prepare("
    SELECT * FROM department_request 
    WHERE user_id = ? 
    ORDER BY created_at DESC
");
$request_stmt->execute([$user_id]);
$my_requests = $request_stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Purchase Request</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
body { background: #f8fafc; }
.card { border-radius:12px; margin-bottom:30px; }
.table { table-layout: fixed; }
th, td { vertical-align: middle; text-align:center; }
.unit-select { min-width:120px; }
.qty-input { min-width:80px; }
.pcs-box-input { min-width:90px; }
.total-pcs-input { background:#f8fafc; min-width:90px; }
.info-box strong { display:inline-block; width:120px; }
.status-badge { font-size: 0.85rem; padding: 0.4em 0.6em; }
</style>
</head>
<body class="bg-light">

<div class="main-sidebar">
    <?php include 'inventory_sidebar.php'; ?>
</div>

<div class="container py-5">
<h2 class="mb-4 fw-bold">📋 Purchase Requests</h2>

<ul class="nav nav-tabs">
    <li class="nav-item">
        <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#form">Request Form</button>
    </li>
    <li class="nav-item">
        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#my-requests">My Requests</button>
    </li>
</ul>

<div class="tab-content mt-3">

<!-- ================= FORM TAB ================= -->
<div class="tab-pane fade show active" id="form">
<div class="card p-4">

<?php if(isset($success)) echo '<div class="alert alert-success">'.$success.'</div>'; ?>
<?php if(isset($error)) echo '<div class="alert alert-danger">'.$error.'</div>'; ?>

<div class="alert alert-info info-box mb-4">
    <div><strong>Department:</strong> <?= htmlspecialchars($department) ?></div>
    <div><strong>Date:</strong> <?= $request_date ?></div>
</div>

<form method="POST" id="requestForm">

<div class="table-responsive">
<table class="table table-bordered">
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
<select name="items[0][unit]" class="form-select form-select-sm unit">
<option value="pcs">Per Piece</option>
<option value="box">Per Box</option>
</select>
</td>
<td><input type="number" name="items[0][quantity]" class="form-control form-control-sm quantity" value="1" min="1"></td>
<td><input type="number" name="items[0][pcs_per_box]" class="form-control form-control-sm pcs-per-box" value="1" min="1" disabled></td>
<td><input type="number" name="items[0][total_pcs]" class="form-control form-control-sm total-pcs" value="1" readonly></td>
<td><button type="button" class="btn btn-sm btn-danger btn-remove">✕</button></td>
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

<!-- ================= MY REQUESTS TAB ================= -->
<div class="tab-pane fade" id="my-requests">
<div class="card p-4">

<table class="table table-bordered table-hover">
<thead class="table-dark text-center">
<tr>
<th>ID</th>
<th>Total Items</th>
<th>Status</th>
<th>Date</th>
<th>Action</th>
</tr>
</thead>
<tbody>
<?php foreach($my_requests as $req): ?>
<tr>
<td><?= $req['id'] ?></td>
<td><?= $req['total_items'] ?></td>
<td>
<?php
if($req['status']==='Pending') echo '<span class="badge bg-warning text-dark">Pending</span>';
elseif($req['status']==='Approved') echo '<span class="badge bg-success">Approved</span>';
else echo '<span class="badge bg-danger">Rejected</span>';
?>
</td>
<td><?= $req['created_at'] ?></td>
<td>
<button class="btn btn-sm btn-info btn-view-items" data-id="<?= $req['id'] ?>">View</button>
</td>
</tr>
<?php endforeach; ?>
</tbody>
</table>

</div>
</div>

</div>
</div>

<!-- ================= MODAL ================= -->
<div class="modal fade" id="viewItemsModal">
<div class="modal-dialog modal-lg">
<div class="modal-content">
<div class="modal-header">
<h5 class="modal-title">Request Items</h5>
<button type="button" class="btn-close" data-bs-dismiss="modal"></button>
</div>
<div class="modal-body">
<table class="table table-bordered">
<thead class="table-light text-center">
<tr>
<th>Item</th>
<th>Description</th>
<th>Unit</th>
<th>Qty</th>
</tr>
</thead>
<tbody id="modalItemBody"></tbody>
</table>
</div>
</div>
</div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
let itemIndex = 1;
const itemBody = document.getElementById('itemBody');

document.getElementById('addRowBtn').onclick = () => {
    const row = itemBody.querySelector('tr').cloneNode(true);
    row.querySelectorAll('input, select').forEach(el=>{
        el.name = el.name.replace(/\[\d+\]/, `[${itemIndex}]`);
        if(el.type !== "select-one") el.value = el.classList.contains('quantity') ? 1 : '';
    });
    itemBody.appendChild(row);
    itemIndex++;
};

itemBody.addEventListener('click', e=>{
    if(e.target.classList.contains('btn-remove')){
        if(itemBody.querySelectorAll('tr').length>1)
            e.target.closest('tr').remove();
        else alert("At least one item is required.");
    }
});

itemBody.addEventListener('input', e=>{
    const row = e.target.closest('tr');
    if(!row) return;
    const unit = row.querySelector('.unit').value;
    const qty = parseFloat(row.querySelector('.quantity').value)||0;
    const pcsBox = row.querySelector('.pcs-per-box');
    const pcsPerBox = parseFloat(pcsBox.value)||1;
    row.querySelector('.total-pcs').value = unit==='box'? qty*pcsPerBox: qty;
    pcsBox.disabled = unit!=='box';
});

// Load items via AJAX
document.querySelectorAll('.btn-view-items').forEach(btn=>{
    btn.addEventListener('click', ()=>{
        fetch('fetch_request_items.php?id=' + btn.dataset.id)
        .then(res=>res.json())
        .then(data=>{
            const body = document.getElementById('modalItemBody');
            body.innerHTML='';
            data.forEach(i=>{
                body.innerHTML += `
                    <tr>
                        <td>${i.item_name}</td>
                        <td>${i.description}</td>
                        <td>${i.unit}</td>
                        <td class="text-center">${i.quantity}</td>
                    </tr>`;
            });
            new bootstrap.Modal(document.getElementById('viewItemsModal')).show();
        });
    });
});
</script>

</body>
</html>
