<?php
include '../../SQL/config.php';

// Handle Approve / Reject / Purchase Actions
if (isset($_POST['action'])) {
    $request_id = $_POST['id'];

    $check = $pdo->prepare("SELECT * FROM department_request WHERE id=? LIMIT 1");
    $check->execute([$request_id]);
    $request = $check->fetch(PDO::FETCH_ASSOC);
    $items = json_decode($request['items'], true) ?: [];

    if ($_POST['action'] === 'approve' && $request['status'] === 'Pending') {
        $approved_quantities = $_POST['approved_quantity'] ?? [];
        foreach ($items as $index => &$item) {
            $approved = isset($approved_quantities[$index]) ? (int)$approved_quantities[$index] : ($item['approved_quantity'] ?? 0);
            $approved = min($approved, $item['quantity']);
            $item['approved_quantity'] = $approved;
        }
        unset($item);
        $total_approved_items = array_sum(array_map(fn($i)=>$i['approved_quantity']??0, $items));
        $stmt = $pdo->prepare("UPDATE department_request
            SET status='Approved',
                items=:items_json,
                total_approved_items=:total_approved
            WHERE id=:id");
        $stmt->execute([
            ':items_json'=>json_encode($items, JSON_UNESCAPED_UNICODE),
            ':total_approved'=>$total_approved_items,
            ':id'=>$request_id
        ]);
    }

    if ($_POST['action'] === 'reject' && $request['status'] === 'Pending') {
        $stmt = $pdo->prepare("UPDATE department_request SET status='Rejected' WHERE id=?");
        $stmt->execute([$request_id]);
    }

    if ($_POST['action'] === 'purchase' && $request['status'] === 'Approved' && !$request['purchased_at']) {
        $prices = $_POST['price'] ?? [];
        $units = $_POST['unit'] ?? [];
        $pcs_per_box_arr = $_POST['pcs_per_box'] ?? [];
        $payment_type = $_POST['payment_type'] ?? 'Direct';

        $total_request_price = 0;

        foreach ($items as $index => $item) {
            $approved_qty = $item['approved_quantity'] ?? 0;
            $unit_type = $units[$index] ?? ($item['unit'] ?? 'pcs');
            $pcs_per_box = intval($pcs_per_box_arr[$index] ?? ($item['pcs_per_box'] ?? 1));
            $price = floatval($prices[$index] ?? 0);
            $total_price = ($unit_type === 'box') ? $approved_qty * $pcs_per_box * $price : $approved_qty * $price;
            $total_request_price += $total_price;

            // Save price to database
            $stmtPrice = $pdo->prepare("INSERT INTO department_request_prices 
                (request_id, item_index, price, total_price) VALUES (?,?,?,?)");
            $stmtPrice->execute([$request_id, $index, $price, $total_price]);

            // Create receiving row
            $stmtRecv = $pdo->prepare("INSERT INTO receiving 
                (request_id, item_index, received_qty, pcs_per_box) VALUES (?,?,?,?)");
            $stmtRecv->execute([$request_id, $index, 0, $pcs_per_box]);

            // Insert into inventory
            $total_qty = ($unit_type==='box')?$approved_qty*$pcs_per_box:$approved_qty;
            $stmtInv = $pdo->prepare("INSERT INTO inventory 
                (item_id, item_name, item_type, category, sub_type, quantity, total_qty, price, unit_type, pcs_per_box, received_at, location)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?)");
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

        // Update request as purchased
        $stmt = $pdo->prepare("UPDATE department_request SET purchased_at=NOW(), payment_type=? WHERE id=?");
        $stmt->execute([$payment_type, $request_id]);
    }
}

// Filters
$statusFilter = $_GET['status'] ?? 'Pending';
$searchDept = $_GET['search_dept'] ?? '';
$query = "SELECT * FROM department_request WHERE 1=1";
$params = [];
if ($statusFilter && $statusFilter!=='All') { $query.=" AND status=?"; $params[]=$statusFilter; }
if ($searchDept) { $query.=" AND department LIKE ?"; $params[]="%$searchDept%"; }
$query.=" ORDER BY created_at DESC";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Department Requests</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<style>
body { background:#f8fafc; font-family:'Segoe UI',sans-serif; }
.main-content { margin-left:260px; padding:30px; }
.card { border-radius:12px; box-shadow:0 5px 18px rgba(0,0,0,0.08);}
.table td, .table th { text-align:center; vertical-align:middle;}
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
            <option value="Pending" <?= $statusFilter==='Pending'?'selected':'' ?>>Pending</option>
            <option value="Approved" <?= $statusFilter==='Approved'?'selected':'' ?>>Approved</option>
            <option value="Rejected" <?= $statusFilter==='Rejected'?'selected':'' ?>>Rejected</option>
            <option value="All" <?= $statusFilter==='All'?'selected':'' ?>>All</option>
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
<thead>
<tr>
<th>ID</th>
<th>Department</th>
<th>User ID</th>
<th>Total Requested</th>
<th>Status</th>
<th>Requested At</th>
<th>View / Purchase</th>
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
<td>
<?php
    if($r['status']==='Pending') echo '<span class="badge bg-warning text-dark">Pending</span>';
    elseif($r['status']==='Approved') echo '<span class="badge bg-success">Approved</span>';
    else echo '<span class="badge bg-danger">Rejected</span>';
?>
</td>
<td><?= $r['created_at'] ?></td>
<td>
    <button class="btn btn-info btn-sm view-items-btn"
        data-id="<?= $r['id'] ?>"
        data-status="<?= $r['status'] ?>"
        data-purchased="<?= $r['purchased_at'] ?>"
        data-items='<?= htmlspecialchars(json_encode($itemsArray), ENT_QUOTES) ?>'>
        <i class="bi bi-eye"></i> View
    </button>
</td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
</div>
</div>

<!-- Modal -->
<div class="modal fade" id="viewModal" tabindex="-1">
<div class="modal-dialog modal-lg modal-dialog-scrollable">
<div class="modal-content">
<div class="modal-header bg-primary text-white">
<h5 class="modal-title">Request Details</h5>
<button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<div class="modal-body" id="modalBodyContent"></div>
</div>
</div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
function formatCurrency(num){ return parseFloat(num||0).toFixed(2); }

document.querySelectorAll('.view-items-btn').forEach(btn=>{
    btn.addEventListener('click',()=>{
        const items = JSON.parse(btn.dataset.items || '[]');
        const status = btn.dataset.status;
        const purchased = btn.dataset.purchased;

        let html = `<form method="post">
            <input type="hidden" name="id" value="${btn.dataset.id}">
            <div class="table-responsive">
            <table class="table table-bordered">
            <thead class="table-light">
            <tr>
                <th>Item</th>
                <th>Requested Qty</th>
                <th>Approved Qty</th>
                <th>Unit</th>
                <th>Pcs/Box</th>
                <th>Price/unit</th>
                <th>Total Price</th>
            </tr>
            </thead><tbody>`;

        items.forEach((item,idx)=>{
            const approvedQty = item.approved_quantity ?? 0;
            html+=`<tr>
                <td>${item.name}</td>
                <td>${item.quantity}</td>
                <td><input type="number" min="0" max="${item.quantity}" name="approved_quantity[${idx}]" class="form-control" value="${approvedQty}" ${status!=='Pending'?'readonly':''}></td>
                <td>${item.unit}</td>
                <td>${item.pcs_per_box??1}</td>
                <td><input type="number" step="0.01" class="form-control price-input" name="price[${idx}]" value="0" ${status==='Pending'?'readonly':''}></td>
                <td class="total-price text-end">0.00</td>
            </tr>`;
        });

        html+=`</tbody></table></div>
            <div class="text-end mt-2"><strong>Total Request Price: <span id="totalRequestPrice">0.00</span></strong></div>`;

        if(status==='Pending'){
            html+=`<div class="d-flex justify-content-end gap-2 mt-3">
                <button type="submit" name="action" value="approve" class="btn btn-success">Approve</button>
                <button type="submit" name="action" value="reject" class="btn btn-danger">Reject</button>
            </div>`;
        } else if(status==='Approved' && !purchased){
            html+=`<div class="row mt-3">
                <div class="col-md-4">
                    <select name="payment_type" class="form-select">
                        <option value="Direct">Pay Directly</option>
                        <option value="Monthly">Monthly</option>
                    </select>
                </div>
                <div class="col-md-8 text-end">
                    <button type="submit" name="action" value="purchase" class="btn btn-primary">Purchase</button>
                </div>
            </div>`;
        } else {
            html+=`<div class="text-center mt-3"><span class="text-muted">This request has been ${status}${purchased?' and purchased.':''}</span></div>`;
        }

        html+=`</form>`;
        document.getElementById('modalBodyContent').innerHTML = html;

        // Auto compute total price
        const rows = document.querySelectorAll('#modalBodyContent tbody tr');
        const totalSpan = document.getElementById('totalRequestPrice');
        function computeTotal(){
            let sum = 0;
            rows.forEach(r=>{
                const qty = parseInt(r.cells[2].querySelector('input')?.value||0);
                const price = parseFloat(r.cells[5].querySelector('input')?.value||0);
                r.cells[6].textContent = formatCurrency(qty*price);
                sum+=qty*price;
            });
            totalSpan.textContent = formatCurrency(sum);
        }
        rows.forEach(r=>{
            const input = r.cells[5].querySelector('input');
            if(input) input.addEventListener('input',computeTotal);
            const qtyInput = r.cells[2].querySelector('input');
            if(qtyInput) qtyInput.addEventListener('input',computeTotal);
        });
        computeTotal();
        new bootstrap.Modal(document.getElementById('viewModal')).show();
    });
});
</script>
</body>
</html>
