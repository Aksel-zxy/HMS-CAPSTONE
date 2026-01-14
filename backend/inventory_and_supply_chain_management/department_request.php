<?php
include '../../SQL/config.php';

// Handle Approve / Reject / Purchase Actions
if (isset($_POST['action'])) {
    $request_id = $_POST['id'];

    // Fetch the request
    $check = $pdo->prepare("SELECT * FROM department_request WHERE id=?");
    $check->execute([$request_id]);
    $request = $check->fetch(PDO::FETCH_ASSOC);

    if ($request) {
        $items = json_decode($request['items'], true) ?: [];

        if ($_POST['action'] === 'approve') {
            $approved_quantities = $_POST['approved_quantity'] ?? [];
            foreach ($items as $index => &$item) {
                $approved = isset($approved_quantities[$index]) ? (int)$approved_quantities[$index] : ($item['approved_quantity'] ?? $item['quantity']);
                $approved = min($approved, $item['quantity']);
                $item['approved_quantity'] = $approved;
            }
            unset($item);
            $total_approved_items = array_sum(array_map(fn($i)=>$i['approved_quantity']??0, $items));

            $stmtDept = $pdo->prepare("SELECT department_id FROM departments WHERE department_name=? LIMIT 1");
            $stmtDept->execute([$request['department']]);
            $department_id = $stmtDept->fetchColumn();
            if (!$department_id) {
                $insertDept = $pdo->prepare("INSERT INTO departments (department_name) VALUES (?)");
                $insertDept->execute([$request['department']]);
                $department_id = $pdo->lastInsertId();
            }

            $stmt = $pdo->prepare("
                UPDATE department_request
                SET status='Approved',
                    items=:items_json,
                    department_id=:department_id,
                    total_approved_items=:total_approved
                WHERE id=:id
            ");
            $stmt->execute([
                ':items_json'=>json_encode($items, JSON_UNESCAPED_UNICODE),
                ':department_id'=>$department_id,
                ':total_approved'=>$total_approved_items,
                ':id'=>$request_id
            ]);

        } elseif ($_POST['action'] === 'reject') {
            $stmt = $pdo->prepare("UPDATE department_request SET status='Rejected' WHERE id=?");
            $stmt->execute([$request_id]);

        } elseif ($_POST['action'] === 'purchase') {
            // Purchase: Insert items into inventory
            $prices = $_POST['price'] ?? []; // price per item/box
            $units = $_POST['unit'] ?? [];
            $pcs_per_box_arr = $_POST['pcs_per_box'] ?? [];

            $total_price = 0;

            foreach ($items as $index => $item) {
                $approved_qty = $item['approved_quantity'] ?? 0;
                $unit_type = $units[$index] ?? ($item['unit'] ?? 'pcs');
                $pcs_per_box = intval($pcs_per_box_arr[$index] ?? ($item['pcs_per_box'] ?? 1));
                $price = floatval($prices[$index] ?? 0);

                // Compute total quantity in pcs
                $total_qty = ($unit_type === 'box') ? $approved_qty * $pcs_per_box : $approved_qty;

                $total_price += $total_qty * $price;

                // Insert into inventory
                $stmtInv = $pdo->prepare("
                    INSERT INTO inventory 
                    (item_id, item_name, item_type, category, sub_type, quantity, total_qty, price, unit_type, pcs_per_box, received_at, location)
                    VALUES 
                    (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?)
                ");
                $stmtInv->execute([
                    $item['id'] ?? 0,
                    $item['name'] ?? '',
                    $item['unit'] ?? 'pcs',
                    $item['category'] ?? '',
                    $item['sub_type'] ?? '',
                    $approved_qty,
                    $total_qty,
                    $price,
                    ucfirst($unit_type),
                    $pcs_per_box,
                    'Main Storage'
                ]);
            }

            // Mark request as purchased
            $stmt = $pdo->prepare("UPDATE department_request SET purchased_at=NOW() WHERE id=?");
            $stmt->execute([$request_id]);
        }
    }
}

// Filters
$statusFilter = $_GET['status'] ?? 'All';
$searchDept = $_GET['search_dept'] ?? '';

$query = "SELECT * FROM department_request WHERE 1=1";
$params = [];
if ($statusFilter!=='All') { $query .= " AND status=?"; $params[]=$statusFilter; }
if ($searchDept) { $query .= " AND department LIKE ?"; $params[]="%$searchDept%"; }
$query .= " ORDER BY created_at DESC";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Department Requests - Purchase</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<style>
body { background: #f8fafc; font-family: 'Segoe UI', sans-serif; }
.main-content { margin-left: 260px; padding: 30px; }
.card { border-radius:12px; box-shadow:0 5px 18px rgba(0,0,0,0.08); }
.table thead th { background:#1e293b; color:white; }
input.price-input { width:100px; }
input.total-input { background:#f8fafc; width:100px; }
</style>
</head>
<body>

<div class="main-sidebar"><?php include 'inventory_sidebar.php'; ?></div>

<div class="main-content">
<div class="card p-4 bg-white">
<h2 class="mb-4 text-primary"><i class="bi bi-cart4"></i> Department Requests</h2>

<form method="get" class="row g-3 mb-4">
    <div class="col-md-3">
        <select name="status" class="form-select">
            <option <?= $statusFilter==='All'?'selected':'' ?>>All</option>
            <option <?= $statusFilter==='Pending'?'selected':'' ?>>Pending</option>
            <option <?= $statusFilter==='Approved'?'selected':'' ?>>Approved</option>
            <option <?= $statusFilter==='Rejected'?'selected':'' ?>>Rejected</option>
        </select>
    </div>
    <div class="col-md-3">
        <input type="text" name="search_dept" class="form-control" placeholder="Search Department" value="<?= htmlspecialchars($searchDept) ?>">
    </div>
    <div class="col-md-2"><button class="btn btn-primary w-100"><i class="bi bi-search"></i> Filter</button></div>
    <div class="col-md-2"><a href="department_request.php" class="btn btn-secondary w-100"><i class="bi bi-arrow-clockwise"></i> Reset</a></div>
</form>

<div class="table-responsive">
<table class="table table-bordered table-hover align-middle">
<thead class="text-center">
<tr>
<th>ID</th>
<th>Department</th>
<th>User ID</th>
<th>Total Requested</th>
<th>Status</th>
<th>Requested At</th>
<th>Items</th>
<th>Actions</th>
<th>Purchased At</th>
</tr>
</thead>
<tbody>
<?php foreach($requests as $r):
    $itemsArray = json_decode($r['items'], true) ?: [];
?>
<tr>
<td><?= $r['id'] ?></td>
<td><?= htmlspecialchars($r['department']) ?></td>
<td><?= htmlspecialchars($r['user_id']) ?></td>
<td><?= $r['total_items'] ?></td>
<td class="text-center"><?= $r['status'] ?></td>
<td><?= $r['created_at'] ?></td>
<td class="text-center">
    <button class="btn btn-info btn-sm view-items-btn"
        data-id="<?= $r['id'] ?>"
        data-dept="<?= htmlspecialchars($r['department']) ?>"
        data-items='<?= htmlspecialchars(json_encode($itemsArray), ENT_QUOTES) ?>'
        data-status="<?= $r['status'] ?>">
        <i class="bi bi-eye"></i> View
    </button>
</td>
<td class="text-center">
<?php if($r['status']=='Approved' && !$r['purchased_at']): ?>
    <button class="btn btn-primary btn-sm purchase-btn" data-id="<?= $r['id'] ?>" data-items='<?= htmlspecialchars(json_encode($itemsArray), ENT_QUOTES) ?>'>
        <i class="bi bi-cart-check"></i> Purchase
    </button>
<?php else: ?>
    <em>No actions</em>
<?php endif; ?>
</td>
<td class="text-center"><?= $r['purchased_at'] ?? '-' ?></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
</div>
</div>

<!-- Modal for Purchase -->
<div class="modal fade" id="purchaseModal" tabindex="-1" aria-hidden="true">
<div class="modal-dialog modal-lg">
<div class="modal-content">
<div class="modal-header bg-primary text-white">
<h5 class="modal-title">Purchase Items</h5>
<button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<div class="modal-body" id="purchaseModalBody"></div>
</div>
</div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// View & compute total
document.querySelectorAll('.purchase-btn').forEach(btn=>{
    btn.addEventListener('click',()=>{
        let items = [];
        try { items=JSON.parse(btn.dataset.items || '[]'); } catch(e){ items=[]; }
        if (!Array.isArray(items)) items=Object.values(items);

        let html='<form method="post"><input type="hidden" name="id" value="'+btn.dataset.id+'"><input type="hidden" name="action" value="purchase"><table class="table table-bordered"><thead><tr><th>Item</th><th>Qty</th><th>Unit</th><th>Pcs/Box</th><th>Price per Unit</th><th>Total Price</th></tr></thead><tbody>';
        items.forEach((item,idx)=>{
            const qty = item.approved_quantity ?? 0;
            const unit = item.unit ?? 'pcs';
            const pcsPerBox = item.pcs_per_box ?? 1;
            html+='<tr>'+
                '<td>'+item.name+'</td>'+
                '<td>'+qty+'<input type="hidden" name="approved_quantity['+idx+']" value="'+qty+'"></td>'+
                '<td><input type="hidden" name="unit['+idx+']" value="'+unit+'">'+unit+'</td>'+
                '<td><input type="hidden" name="pcs_per_box['+idx+']" value="'+pcsPerBox+'">'+pcsPerBox+'</td>'+
                '<td><input type="number" step="0.01" class="form-control price-input" name="price['+idx+']" value="0" min="0"></td>'+
                '<td class="total-input text-end">0</td>'+
                '</tr>';
        });
        html+='</tbody></table><div class="text-end"><button type="submit" class="btn btn-success">Confirm Purchase</button></div></form>';
        document.getElementById('purchaseModalBody').innerHTML=html;

        // compute total dynamically
        document.querySelectorAll('.price-input').forEach((inp,idx)=>{
            inp.addEventListener('input',()=>{
                const row = inp.closest('tr');
                const qty = parseInt(row.cells[1].querySelector('input').value) || 0;
                row.cells[5].textContent = (qty * parseFloat(inp.value || 0)).toFixed(2);
            });
        });

        new bootstrap.Modal(document.getElementById('purchaseModal')).show();
    });
});
</script>

</body>
</html>
