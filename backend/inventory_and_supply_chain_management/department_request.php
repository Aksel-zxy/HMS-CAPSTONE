<?php
include '../../SQL/config.php';

// Handle Approve / Reject Actions
if (isset($_POST['action'])) {
    $request_id = $_POST['id'];

    // Fetch the request
    $check = $pdo->prepare("SELECT * FROM department_request WHERE id=?");
    $check->execute([$request_id]);
    $request = $check->fetch(PDO::FETCH_ASSOC);

    if ($request) {
        $items = json_decode($request['items'], true);
        if (!is_array($items)) $items = [];

        if ($_POST['action'] === 'approve') {
            $approved_qty = 0;
            $grand_total = 0;

            // Read approved quantities from POST
            $approved_quantities = $_POST['approved_quantity'] ?? [];

            foreach ($items as $index => &$item) {
                $approved = isset($approved_quantities[$index]) ? (int)$approved_quantities[$index] : ($item['approved_quantity'] ?? $item['quantity']);
                // Validation: cannot approve more than requested
                $approved = min($approved, $item['quantity']);
                $item['approved_quantity'] = $approved;
                $approved_qty += $approved;
                $grand_total += $approved * $item['price'];
            }
            unset($item);

            // Ensure department_id exists
            $stmtDept = $pdo->prepare("SELECT department_id FROM departments WHERE department_name = ? LIMIT 1");
            $stmtDept->execute([$request['department']]);
            $department_id = $stmtDept->fetchColumn();
            if (!$department_id) {
                $insertDept = $pdo->prepare("INSERT INTO departments (department_name) VALUES (?)");
                $insertDept->execute([$request['department']]);
                $department_id = $pdo->lastInsertId();
            }

            // Update request
            $stmt = $pdo->prepare("
                UPDATE department_request
                SET status='Approved',
                    items=:items_json,
                    total_approved_items=:approved_qty,
                    grand_total=:grand_total,
                    department_id=:department_id
                WHERE id=:id
            ");
            $stmt->execute([
                ':items_json' => json_encode($items, JSON_UNESCAPED_UNICODE),
                ':approved_qty' => $approved_qty,
                ':grand_total' => $grand_total,
                ':department_id' => $department_id,
                ':id' => $request_id
            ]);
        } elseif ($_POST['action'] === 'reject') {
            $stmt = $pdo->prepare("UPDATE department_request SET status='Rejected' WHERE id=?");
            $stmt->execute([$request_id]);
        }
    }
}

// Filters
$statusFilter = $_GET['status'] ?? 'All';
$searchDept = $_GET['search_dept'] ?? '';

$query = "SELECT * FROM department_request WHERE 1=1";
$params = [];

if ($statusFilter !== 'All') {
    $query .= " AND status = ?";
    $params[] = $statusFilter;
}

if (!empty($searchDept)) {
    $query .= " AND department LIKE ?";
    $params[] = "%$searchDept%";
}

$query .= " ORDER BY created_at DESC";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Admin - Department Requests</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<style>
body { background-color: #f8fafc; font-family: 'Segoe UI', sans-serif; }
.main-content { margin-left: 260px; padding: 30px; }
.card { border-radius: 12px; box-shadow: 0 5px 18px rgba(0,0,0,0.08); }
.table thead th { background-color: #1e293b; color: #fff; }
.modal-header { background-color: #2563eb; color: white; }
input.approved-qty { width: 70px; }
.grand-total { font-weight: bold; text-align: right; margin-top: 10px; }
.total-approved { font-weight: bold; text-align: right; margin-top: 5px; }
</style>
</head>
<body>

<div class="main-sidebar">
    <?php include 'inventory_sidebar.php'; ?>
</div>

<!-- Modal for Viewing & Editing Approved Quantities -->
<div class="modal fade" id="viewItemsModal" tabindex="-1" aria-labelledby="viewItemsLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="viewItemsLabel">📦 Request Items</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="modalBody">
                <div class="text-center text-muted">Select a request to view its items.</div>
            </div>
        </div>
    </div>
</div>

<div class="main-content">
    <div class="card p-4 bg-white">
        <h2 class="mb-4 text-primary"><i class="bi bi-clipboard-check"></i> Department Requests</h2>

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
            <div class="col-md-2">
                <button class="btn btn-primary w-100"><i class="bi bi-search"></i> Filter</button>
            </div>
            <div class="col-md-2">
                <a href="department_request.php" class="btn btn-secondary w-100"><i class="bi bi-arrow-clockwise"></i> Reset</a>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table table-bordered table-hover align-middle">
                <thead class="text-center">
                    <tr>
                        <th>ID</th>
                        <th>Department</th>
                        <th>Requested By (User ID)</th>
                        <th>Total Requested Items</th>
                        <th>Total Approved Items</th>
                        <th>Status</th>
                        <th>Requested At</th>
                        <th>Items</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($requests as $r): 
                    $itemsArray = json_decode($r['items'], true);
                    if (!is_array($itemsArray)) $itemsArray = [];
                    if (array_keys($itemsArray) !== range(0, count($itemsArray) - 1)) {
                        $itemsArray = array_values($itemsArray);
                    }
                ?>
                    <tr>
                        <td><?= $r['id'] ?></td>
                        <td><?= htmlspecialchars($r['department']) ?></td>
                        <td><?= htmlspecialchars($r['user_id']) ?></td>
                        <td class="text-center"><?= $r['total_items'] ?></td>
                        <td class="text-center"><?= $r['total_approved_items'] ?? 0 ?></td>
                        <td class="text-center">
                            <?php if ($r['status'] == 'Pending'): ?>
                                <span class="badge bg-warning text-dark">Pending</span>
                            <?php elseif ($r['status'] == 'Approved'): ?>
                                <span class="badge bg-success">Approved</span>
                            <?php else: ?>
                                <span class="badge bg-danger">Rejected</span>
                            <?php endif; ?>
                        </td>
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
                            <?php if ($r['status'] == 'Pending'): ?>
                                <form method="post" class="d-inline">
                                    <input type="hidden" name="id" value="<?= $r['id'] ?>">
                                    <input type="hidden" name="action" value="reject">
                                    <button class="btn btn-danger btn-sm"><i class="bi bi-x-circle"></i> Reject</button>
                                </form>
                            <?php else: ?>
                                <em>No actions</em>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
function updateTotals() {
    const rows = document.querySelectorAll('#approveItemsForm tbody tr');
    let grandTotal = 0;
    let totalApproved = 0;
    rows.forEach(row => {
        const approvedInput = row.querySelector('input.approved-qty');
        const priceCell = row.cells[4];
        const requestedQty = parseInt(row.cells[2].textContent) || 0;
        if (approvedInput && priceCell) {
            let approved = parseInt(approvedInput.value) || 0;
            // Validation: cannot exceed requested quantity
            if (approved > requestedQty) {
                approved = requestedQty;
                approvedInput.value = approved;
            } else if (approved < 0) {
                approved = 0;
                approvedInput.value = 0;
            }
            const price = parseFloat(priceCell.textContent.replace('₱','')) || 0;
            row.cells[5].textContent = '₱' + (approved * price).toFixed(2);
            grandTotal += approved * price;
            totalApproved += approved;
        }
    });
    document.getElementById('grandTotalDisplay').textContent = 'Grand Total: ₱' + grandTotal.toFixed(2);
    document.getElementById('totalApprovedDisplay').textContent = 'Total Approved Items: ' + totalApproved;
}

document.querySelectorAll('.view-items-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        const department = btn.dataset.dept;
        const requestId = btn.dataset.id;
        const status = btn.dataset.status;
        let items = [];
        try {
            items = JSON.parse(btn.dataset.items || '[]');
            if (!Array.isArray(items)) items = Object.values(items);
        } catch(e) { console.error(e); }

        let html = '';
        if (items.length > 0) {
            html += `<form id="approveItemsForm" method="post">
                        <input type="hidden" name="id" value="${requestId}">
                        <input type="hidden" name="action" value="approve">
                        <table class="table table-bordered">
                        <thead class="table-light">
                            <tr>
                                <th>Item Name</th>
                                <th>Description</th>
                                <th>Requested Quantity</th>
                                <th>Approved Quantity</th>
                                <th>Price</th>
                                <th>Total</th>
                            </tr>
                        </thead>
                        <tbody>`;
            items.forEach((item, idx) => {
                const requested = item.quantity ?? 0;
                const approved = item.approved_quantity ?? requested;
                const price = item.price ?? 0;
                html += `<tr>
                            <td>${item.name || ''}</td>
                            <td>${item.description || item.desc || ''}</td>
                            <td>${requested}</td>
                            <td>`;
                if (status === 'Pending') {
                    html += `<input type="number" class="form-control approved-qty" name="approved_quantity[${idx}]" value="${approved}" min="0" max="${requested}">`;
                } else {
                    html += approved;
                }
                html += `</td>
                         <td>₱${parseFloat(price).toFixed(2)}</td>
                         <td>₱${(approved*price).toFixed(2)}</td>
                         </tr>`;
            });
            html += `</tbody></table>
                     <div class="d-flex justify-content-between mt-2 align-items-center">
                        <div>
                            <div class="total-approved" id="totalApprovedDisplay"></div>
                            <div class="grand-total" id="grandTotalDisplay"></div>
                        </div>
                        <button type="submit" class="btn btn-success"><i class="bi bi-check2-circle"></i> Approve</button>
                     </div>
                     </form>`;
        } else {
            html = `<p class="text-center text-muted">No items found for this request.</p>`;
        }

        document.getElementById('viewItemsLabel').innerHTML = `📦 Request from ${department}`;
        document.getElementById('modalBody').innerHTML = html;

        document.querySelectorAll('input.approved-qty').forEach(input => {
            input.addEventListener('input', updateTotals);
        });

        // Prevent form submission if approved > requested
        const form = document.getElementById('approveItemsForm');
        form.addEventListener('submit', (e) => {
            let invalid = false;
            document.querySelectorAll('#approveItemsForm tbody tr').forEach(row => {
                const approved = parseInt(row.querySelector('input.approved-qty').value) || 0;
                const requested = parseInt(row.cells[2].textContent) || 0;
                if (approved > requested) invalid = true;
            });
            if (invalid) {
                alert('Approved quantity cannot exceed requested quantity!');
                e.preventDefault();
            }
        });

        updateTotals();
        const modal = new bootstrap.Modal(document.getElementById('viewItemsModal'));
        modal.show();
    });
});
</script>

</body>
</html>
