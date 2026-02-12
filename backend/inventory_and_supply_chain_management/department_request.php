<?php
include '../../SQL/config.php';

/* =====================================================
   HANDLE ACTIONS
=====================================================*/
if (isset($_POST['action'])) {

    $request_id = $_POST['id'];

    // Fetch the request
    $stmt = $pdo->prepare("SELECT * FROM department_request WHERE id=? LIMIT 1");
    $stmt->execute([$request_id]);
    $request = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$request) {
        header("Location: department_request.php");
        exit;
    }

    // Fetch items for this request
    $stmtItems = $pdo->prepare("SELECT * FROM department_request_items WHERE request_id=? ORDER BY id ASC");
    $stmtItems->execute([$request_id]);
    $items = $stmtItems->fetchAll(PDO::FETCH_ASSOC);

    /* ================= APPROVE ================= */
    if ($_POST['action'] === 'approve' && strcasecmp(trim($request['status']), 'Pending') === 0) {

        $approved_quantities = $_POST['approved_quantity'] ?? [];

        foreach ($items as $item) {
            $idx = $item['id'];
            $approved = isset($approved_quantities[$idx]) ? (int)$approved_quantities[$idx] : 0;
            $approved = min($approved, $item['quantity']);

            $stmtUpdate = $pdo->prepare("UPDATE department_request_items
                SET approved_quantity=?
                WHERE id=?");
            $stmtUpdate->execute([$approved, $idx]);
        }

        // Update total approved in request
        $stmtTotal = $pdo->prepare("SELECT SUM(approved_quantity) AS total FROM department_request_items WHERE request_id=?");
        $stmtTotal->execute([$request_id]);
        $total_approved = $stmtTotal->fetchColumn() ?: 0;

        $stmtUpdateRequest = $pdo->prepare("UPDATE department_request
            SET status='Approved', total_approved_items=?
            WHERE id=?");
        $stmtUpdateRequest->execute([$total_approved, $request_id]);
    }

    /* ================= REJECT ================= */
    if ($_POST['action'] === 'reject' && strcasecmp(trim($request['status']), 'Pending') === 0) {
        $stmt = $pdo->prepare("UPDATE department_request SET status='Rejected' WHERE id=?");
        $stmt->execute([$request_id]);
    }

    /* ================= PURCHASE ================= */
    if ($_POST['action'] === 'purchase' && strcasecmp(trim($request['status']), 'Approved') === 0 && !$request['purchased_at']) {

        $prices = $_POST['price'] ?? [];
        $units = $_POST['unit'] ?? [];
        $pcs_per_box_arr = $_POST['pcs_per_box'] ?? [];
        $payment_type = $_POST['payment_type'] ?? 'Direct';

        foreach ($items as $item) {
            $item_id = $item['id'];
            $approved_qty = $item['approved_quantity'] ?? 0;
            if ($approved_qty <= 0) continue;

            $unit_type = strtolower($units[$item_id] ?? $item['unit'] ?? 'pcs');
            $pcs_per_box = intval($pcs_per_box_arr[$item_id] ?? $item['pcs_per_box'] ?? 1);
            $price = floatval($prices[$item_id] ?? $item['price'] ?? 0);

            $total_price = $price * $approved_qty;
            $total_qty = ($unit_type === 'box') ? $approved_qty * $pcs_per_box : $approved_qty;

            // Update price for the item
            $stmtUpdatePrice = $pdo->prepare("UPDATE department_request_items 
                SET price=?, total_price=?
                WHERE id=?");
            $stmtUpdatePrice->execute([$price, $total_price, $item_id]);

            // Insert receiving record
            $stmtRecv = $pdo->prepare("INSERT INTO receiving
                (request_id, item_index, received_qty, pcs_per_box)
                VALUES (?, ?, 0, ?)");
            $stmtRecv->execute([$request_id, $item_id, $pcs_per_box]);

            // Insert into inventory
            $stmtInv = $pdo->prepare("INSERT INTO inventory
                (item_id, item_name, item_type, category, sub_type,
                 quantity, total_qty, price, unit_type,
                 pcs_per_box, received_at, location)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?)");
            $stmtInv->execute([
                $item_id,
                $item['item_name'],
                $unit_type,
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
        $stmt = $pdo->prepare("UPDATE department_request SET purchased_at=NOW(), payment_type=? WHERE id=?");
        $stmt->execute([$payment_type, $request_id]);
    }

    header("Location: department_request.php");
    exit;
}

/* =====================================================
   FETCH REQUESTS
=====================================================*/
$statusFilter = $_GET['status'] ?? 'All';
$searchDept = $_GET['search_dept'] ?? '';

$query = "SELECT * FROM department_request WHERE 1=1";
$params = [];

if ($statusFilter && $statusFilter !== 'All') {
    $query .= " AND LOWER(TRIM(status)) = ?";
    $params[] = strtolower(trim($statusFilter));
}

if ($searchDept) {
    $query .= " AND department LIKE ?";
    $params[] = "%$searchDept%";
}

$query .= " ORDER BY created_at DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
if (!$requests) $requests = [];
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
.qty-input, .price-input { width:80px; }
.total-price { font-weight:bold; }
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
    $stmtItems = $pdo->prepare("SELECT * FROM department_request_items WHERE request_id=? ORDER BY id ASC");
    $stmtItems->execute([$r['id']]);
    $itemsArray = $stmtItems->fetchAll(PDO::FETCH_ASSOC);
    $status = ucfirst(strtolower(trim($r['status'])));
?>
<tr>
<td><?= $r['id'] ?></td>
<td><?= htmlspecialchars($r['department']) ?></td>
<td><?= htmlspecialchars($r['user_id']) ?></td>
<td><?= count($itemsArray) ?></td>
<td>
<?php
    if(strcasecmp($status,'Pending')===0) echo '<span class="badge bg-warning text-dark">Pending</span>';
    elseif(strcasecmp($status,'Approved')===0) echo '<span class="badge bg-success">Approved</span>';
    else echo '<span class="badge bg-danger">Rejected</span>';
?>
</td>
<td><?= $r['created_at'] ?></td>
<td>
    <button class="btn btn-info btn-sm view-items-btn"
        data-id="<?= $r['id'] ?>"
        data-status="<?= $status ?>"
        data-purchased="<?= $r['purchased_at'] ?>"
        data-items='<?= htmlspecialchars(json_encode($itemsArray, JSON_UNESCAPED_UNICODE), ENT_QUOTES, "UTF-8") ?>'>
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
        const showPrice = (status.toLowerCase() === 'approved' && !purchased);

        let html = `<form method="post">
        <input type="hidden" name="id" value="${btn.dataset.id}">
        <div class="table-responsive">
        <table class="table table-bordered">
        <thead class="table-light">
        <tr>
            <th>Item</th>
            <th>Requested</th>
            <th>Approved</th>
            <th>Unit</th>
            <th>Pcs/Box</th>`;
        if(showPrice) html += `<th>Price</th><th>Total</th>`;
        html += `</tr></thead><tbody>`;

        items.forEach(item=>{
            const idx = item.id;
            const approved = item.approved_quantity || 0;
            const unit = item.unit || 'pcs';
            const pcs = item.pcs_per_box || 1;
            html += `<tr>
                <td>${item.item_name}</td>
                <td>${item.quantity}</td>
                <td><input type="number" class="form-control qty-input" name="approved_quantity[${idx}]" value="${approved}" ${status.toLowerCase()!=='pending'?'readonly':''}></td>
                <td>${unit}<input type="hidden" name="unit[${idx}]" value="${unit}"></td>
                <td>${pcs}<input type="hidden" name="pcs_per_box[${idx}]" value="${pcs}"></td>`;
            if(showPrice){
                html += `<td><input type="number" step="0.01" class="form-control price-input" name="price[${idx}]" value="${item.price || 0}"></td>
                         <td class="total-price text-end">0.00</td>`;
            }
            html += `</tr>`;
        });

        html += `</tbody></table></div>`;

        if(status.toLowerCase() === 'pending'){
            html += `<div class="d-flex justify-content-end gap-2 mt-3">
                        <button type="submit" name="action" value="approve" class="btn btn-success">Approve</button>
                        <button type="submit" name="action" value="reject" class="btn btn-danger">Reject</button>
                    </div>`;
        }

        if(showPrice){
            html += `<div class="text-end mt-2"><strong>Total Request Price: <span id="grandTotal">0.00</span></strong></div>
                     <div class="text-end mt-3"><button type="submit" name="action" value="purchase" class="btn btn-primary">Purchase</button></div>`;
        }

        html += `</form>`;
        document.getElementById('modalBodyContent').innerHTML = html;

        if(showPrice){
            const rows = document.querySelectorAll('#modalBodyContent tbody tr');
            const grandTotal = document.getElementById('grandTotal');

            function compute(){
                let sum = 0;
                rows.forEach(r=>{
                    const qty = parseFloat(r.querySelector('.qty-input')?.value || 0);
                    const price = parseFloat(r.querySelector('.price-input')?.value || 0);
                    let total = price * qty;
                    r.querySelector('.total-price').textContent = formatCurrency(total);
                    sum += total;
                });
                grandTotal.textContent = formatCurrency(sum);
            }
            document.querySelectorAll('.qty-input, .price-input').forEach(i=>i.addEventListener('input', compute));
            compute();
        }

        new bootstrap.Modal(document.getElementById('viewModal')).show();
    });
});
</script>
</body>
</html>
