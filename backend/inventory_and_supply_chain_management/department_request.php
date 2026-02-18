<?php
include '../../SQL/config.php';

/* =====================================================
   HANDLE ACTIONS (Approve / Reject / Purchase)
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

            // Clamp approved quantity to valid range [0, requested quantity]
            if ($approved < 0) $approved = 0;
            if ($approved > $item['quantity']) $approved = $item['quantity'];

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

    /* ================= PURCHASE (NO INVENTORY HERE - ONLY UPDATE PRICES & STATUS) ================= */
    if ($_POST['action'] === 'purchase' && strcasecmp($request['status'], 'Approved') === 0 && !$request['purchased_at']) {

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

            // Update price and details for the item ONLY (approved quantities are LOCKED)
            $stmtUpdatePrice = $pdo->prepare("UPDATE department_request_items 
                SET price=?, total_price=?, unit=?, pcs_per_box=?
                WHERE id=?");
            $stmtUpdatePrice->execute([$price, $total_price, ucfirst($unit_type), $pcs_per_box, $item_id]);
        }

        // Mark request as purchased (Status stays 'Approved', just mark purchased_at)
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
.locked-badge { font-size:0.75rem; margin-left:5px; }
.quantity-locked { background-color: #e9ecef !important; }
.action-section { padding: 15px; border-radius: 8px; margin-bottom: 20px; }
.action-section.reject-selected { background-color: #ffe5e5; border: 1px solid #ff9999; }
.action-section.approve-selected { background-color: #e5ffe5; border: 1px solid #99ff99; }
</style>
</head>
<body>

<div class="main-sidebar"><?php include 'inventory_sidebar.php'; ?></div>

<div class="main-content">
<div class="card p-4 bg-white">
<h2 class="mb-4 text-primary"><i class="bi bi-cart4"></i> Department Requests - Purchase Order</h2>

<form method="get" class="row g-3 mb-4">
    <div class="col-md-3">
        <select name="status" class="form-select">
            <option value="Pending" <?= $statusFilter==='Pending'?'selected':'' ?>>Pending</option>
            <option value="Approved" <?= $statusFilter==='Approved'?'selected':'' ?>>Approved</option>
            <option value="Purchased" <?= $statusFilter==='Purchased'?'selected':'' ?>>Purchased</option>
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
<th>View / Action</th>
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
    elseif(strcasecmp($status,'Purchased')===0) echo '<span class="badge bg-primary">Purchased</span>';
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
        const isPending = (status.toLowerCase() === 'pending');
        const isApproved = (status.toLowerCase() === 'approved');
        const showPrice = (isApproved && !purchased);

        let html = `<form method="post" id="requestForm">
        <input type="hidden" name="id" value="${btn.dataset.id}">`;

        // STATUS BANNER
        if (isApproved) {
            html += `<div class="alert alert-success" role="alert">
                <i class="bi bi-check-circle"></i> <strong>This request is APPROVED.</strong> Approved quantities are LOCKED and cannot be changed.
            </div>`;
        }

        // ============ PENDING REQUEST - APPROVE/REJECT DROPDOWN ============
        if (isPending) {
            html += `<div class="action-section" id="actionSection">
                <div class="mb-3">
                    <label for="pendingActionDropdown" class="form-label"><strong>Select Action:</strong></label>
                    <select class="form-select" id="pendingActionDropdown" required>
                        <option value="">-- Choose an action --</option>
                        <option value="approve">✓ APPROVE - Enter approved quantities</option>
                        <option value="reject">✗ REJECT - Deny this request</option>
                    </select>
                </div>
            </div>`;
        }

        // ITEMS TABLE
        html += `<div class="table-responsive">
        <table class="table table-bordered">
        <thead class="table-light">
        <tr>
            <th>Item</th>
            <th>Requested</th>
            <th>Approved ${isApproved ? '<span class="badge bg-success locked-badge">LOCKED</span>' : ''}</th>
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
                <td>
                    <input type="number" 
                           class="form-control qty-input" 
                           name="approved_quantity[${idx}]" 
                           value="${approved}" 
                           min="0" 
                           max="${item.quantity}" 
                           readonly 
                           style="background-color:#e9ecef;">
                </td>
                <td>${unit}<input type="hidden" name="unit[${idx}]" value="${unit}"></td>
                <td>${pcs}<input type="hidden" name="pcs_per_box[${idx}]" value="${pcs}"></td>`;
            if(showPrice){
                html += `<td><input type="number" step="0.01" class="form-control price-input" name="price[${idx}]" value="${item.price || 0}" required></td>
                         <td class="total-price text-end">0.00</td>`;
            }
            html += `</tr>`;
        });

        html += `</tbody></table></div>`;

        // ============ PENDING REQUEST - APPROVE/REJECT BUTTONS (HIDDEN UNTIL SELECTION) ============
        if (isPending){
            html += `<div class="d-flex justify-content-end gap-2 mt-3" id="pendingActionButtons" style="display:none;">
                        <button type="button" class="btn btn-success" id="pendingApproveBtn">
                            <i class="bi bi-check-circle"></i> APPROVE
                        </button>
                        <button type="button" class="btn btn-danger" id="pendingRejectBtn">
                            <i class="bi bi-x-circle"></i> REJECT
                        </button>
                    </div>`;
        }

        // ============ APPROVED REQUEST - PURCHASE SECTION ============
        if(showPrice){
            html += `<div class="alert alert-info mt-3">
                <strong>Purchase Instructions:</strong> Set the unit price for each item. Total price will be calculated automatically.
            </div>
            <div id="priceErrorAlert" class="alert alert-danger" style="display:none;">
                <i class="bi bi-exclamation-triangle"></i> <strong>Error:</strong> Please set a price for all items before purchasing.
            </div>
            <div class="text-end"><strong>Total Request Price: <span id="grandTotal">0.00</span></strong></div>
            <div class="text-end mt-3">
                <button type="button" class="btn btn-primary" id="purchaseBtn">
                    <i class="bi bi-bag-check"></i> Purchase
                </button>
            </div>`;
        }

        html += `</form>`;
        document.getElementById('modalBodyContent').innerHTML = html;

        // ============ PENDING REQUEST - DROPDOWN LOGIC ============
        if (isPending) {
            const actionDropdown = document.getElementById('pendingActionDropdown');
            const actionButtons = document.getElementById('pendingActionButtons');
            const approveBtn = document.getElementById('pendingApproveBtn');
            const rejectBtn = document.getElementById('pendingRejectBtn');
            const actionSection = document.getElementById('actionSection');
            const qtyInputs = document.querySelectorAll('#modalBodyContent .qty-input');
            const requestForm = document.getElementById('requestForm');

            actionDropdown.addEventListener('change', function() {
                const selectedAction = this.value;

                if (selectedAction === 'approve') {
                    // Enable quantity inputs
                    qtyInputs.forEach(input => {
                        input.disabled = false;
                        input.style.backgroundColor = '#ffffff';
                        input.style.borderColor = '#28a745';
                    });
                    actionSection.classList.remove('reject-selected');
                    actionSection.classList.add('approve-selected');
                    actionButtons.style.display = 'flex';
                    rejectBtn.style.display = 'none';
                    approveBtn.style.display = 'block';
                } 
                else if (selectedAction === 'reject') {
                    // Disable quantity inputs
                    qtyInputs.forEach(input => {
                        input.disabled = true;
                        input.value = 0;
                        input.style.backgroundColor = '#ffcccc';
                        input.style.borderColor = '#dc3545';
                    });
                    actionSection.classList.remove('approve-selected');
                    actionSection.classList.add('reject-selected');
                    actionButtons.style.display = 'flex';
                    approveBtn.style.display = 'none';
                    rejectBtn.style.display = 'block';
                } 
                else {
                    qtyInputs.forEach(input => {
                        input.disabled = true;
                        input.style.backgroundColor = '#f0f0f0';
                        input.style.borderColor = '';
                    });
                    actionSection.classList.remove('approve-selected', 'reject-selected');
                    actionButtons.style.display = 'none';
                }
            });

            // Approve button handler
            approveBtn.addEventListener('click', function(e) {
                e.preventDefault();
                requestForm.innerHTML += '<input type="hidden" name="action" value="approve">';
                requestForm.submit();
            });

            // Reject button handler
            rejectBtn.addEventListener('click', function(e) {
                e.preventDefault();
                requestForm.innerHTML += '<input type="hidden" name="action" value="reject">';
                requestForm.submit();
            });
        }

        // ============ APPROVED REQUEST - PURCHASE LOGIC WITH PRICE VALIDATION ============
        if(showPrice){
            const purchaseBtn = document.getElementById('purchaseBtn');
            const requestForm = document.getElementById('requestForm');
            const priceErrorAlert = document.getElementById('priceErrorAlert');
            const rows = document.querySelectorAll('#modalBodyContent tbody tr');
            const grandTotal = document.getElementById('grandTotal');

            // Calculate totals
            function compute(){
                let sum = 0;
                rows.forEach(r=>{
                    const qtyInput = r.querySelector('.qty-input');
                    const qty = parseFloat(qtyInput?.value || 0);
                    const price = parseFloat(r.querySelector('.price-input')?.value || 0);
                    let total = price * qty;
                    r.querySelector('.total-price').textContent = formatCurrency(total);
                    sum += total;
                });
                grandTotal.textContent = formatCurrency(sum);
            }

            document.querySelectorAll('.qty-input, .price-input').forEach(i=>i.addEventListener('input', compute));
            compute();

            // Purchase button handler with price validation
            purchaseBtn.addEventListener('click', function(e) {
                e.preventDefault();
                
                // Validate all prices are set
                let allPricesSet = true;
                rows.forEach(r => {
                    const qtyInput = r.querySelector('.qty-input');
                    const qty = parseFloat(qtyInput?.value || 0);
                    const priceInput = r.querySelector('.price-input');
                    const price = parseFloat(priceInput?.value || 0);
                    
                    if (qty > 0 && price <= 0) {
                        allPricesSet = false;
                        priceInput.style.borderColor = '#dc3545';
                        priceInput.style.borderWidth = '2px';
                    } else {
                        priceInput.style.borderColor = '';
                        priceInput.style.borderWidth = '';
                    }
                });

                if (!allPricesSet) {
                    priceErrorAlert.style.display = 'block';
                    return;
                }

                // All prices are valid, proceed with purchase
                priceErrorAlert.style.display = 'none';
                requestForm.innerHTML += '<input type="hidden" name="action" value="purchase">';
                requestForm.submit();
            });
        }

        new bootstrap.Modal(document.getElementById('viewModal')).show();
    });
});
</script>
</body>
</html>