<?php
include '../../SQL/config.php';

/* =====================================================
   HANDLE RECEIVING ACTION
=====================================================*/
if (isset($_POST['action']) && $_POST['action'] === 'receive') {

    $request_id     = $_POST['id'];
    $received_items = $_POST['received_qty'] ?? [];

    // Fetch request
    $stmt = $pdo->prepare("SELECT * FROM department_request WHERE id=? LIMIT 1");
    $stmt->execute([$request_id]);
    $request = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$request || !$request['purchased_at']) {
        header("Location: order_receive.php");
        exit;
    }

    // Fetch request items
    $stmtItems = $pdo->prepare("SELECT * FROM department_request_items WHERE request_id=?");
    $stmtItems->execute([$request_id]);
    $items = $stmtItems->fetchAll(PDO::FETCH_ASSOC);

    foreach ($items as $item) {

        $item_id       = $item['id'];
        $approved_qty  = (int)$item['approved_quantity'];
        $prev_received = (int)($item['received_quantity'] ?? 0);
        $received_qty  = isset($received_items[$item_id]) ? (int)$received_items[$item_id] : 0;

        $remaining = $approved_qty - $prev_received;

        if ($received_qty <= 0) continue;
        if ($received_qty > $remaining) continue;

        $unit_type   = $item['unit'] ?? 'pcs';
        $pcs_per_box = $item['pcs_per_box'] ?? 1;
        $price       = $item['price'] ?? 0;

        $total_qty = (strtolower($unit_type) === 'box')
            ? $received_qty * $pcs_per_box
            : $received_qty;

        /* ================= INVENTORY UPDATE ================= */
        $stmtCheck = $pdo->prepare("SELECT * FROM inventory WHERE item_name=? LIMIT 1");
        $stmtCheck->execute([$item['item_name']]);
        $existing = $stmtCheck->fetch(PDO::FETCH_ASSOC);

        if ($existing) {
            $stmtUpdate = $pdo->prepare(
                "UPDATE inventory
                 SET quantity = quantity + ?,
                     total_qty = total_qty + ?,
                     price = ?
                 WHERE id=?"
            );
            $stmtUpdate->execute([$received_qty, $total_qty, $price, $existing['id']]);
        } else {
            $stmtInsert = $pdo->prepare(
                "INSERT INTO inventory
                 (item_id, item_name, item_type, category, sub_type,
                  quantity, total_qty, price, unit_type,
                  pcs_per_box, received_at, location)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?)"
            );
            $stmtInsert->execute([
                $item_id,
                $item['item_name'],
                $item['item_type'] ?? 'Supply',
                $item['category'] ?? '',
                $item['sub_type'] ?? '',
                $received_qty,
                $total_qty,
                $price,
                ucfirst($unit_type),
                $pcs_per_box,
                'Main Storage'
            ]);
        }

        // Update received quantity
        $new_received = $prev_received + $received_qty;

        $stmtUpdateItem = $pdo->prepare(
            "UPDATE department_request_items
             SET received_quantity=?
             WHERE id=?"
        );
        $stmtUpdateItem->execute([$new_received, $item_id]);
    }

    /* ================= STRICT COMPLETION CHECK ================= */
    $stmtCheckAll = $pdo->prepare(
        "SELECT approved_quantity, received_quantity
         FROM department_request_items
         WHERE request_id=? AND approved_quantity > 0"
    );
    $stmtCheckAll->execute([$request_id]);
    $checkItems = $stmtCheckAll->fetchAll(PDO::FETCH_ASSOC);

    $all_completed = true;

    foreach ($checkItems as $ci) {
        if ((int)$ci['received_quantity'] !== (int)$ci['approved_quantity']) {
            $all_completed = false;
            break;
        }
    }

    if ($all_completed && count($checkItems) > 0) {
        $stmtDone = $pdo->prepare(
            "UPDATE department_request
             SET status='Completed',
                 delivered_at=NOW()
             WHERE id=?"
        );
        $stmtDone->execute([$request_id]);
    } else {
        $stmtReceiving = $pdo->prepare(
            "UPDATE department_request
             SET status='Receiving'
             WHERE id=?"
        );
        $stmtReceiving->execute([$request_id]);
    }

    header("Location: order_receive.php");
    exit;
}

/* =====================================================
   FETCH PURCHASED REQUESTS (FIXED)
=====================================================*/
$statusFilter = $_GET['status'] ?? 'Pending';
$searchDept   = $_GET['search_dept'] ?? '';

$query  = "SELECT * FROM department_request WHERE 1=1";
$params = [];

if ($statusFilter === 'Pending') {
    $query .= " AND purchased_at IS NOT NULL AND status IN ('Purchased','Receiving')";
} elseif ($statusFilter === 'Completed') {
    $query .= " AND status = 'Completed'";
} elseif ($statusFilter === 'All') {
    $query .= " AND purchased_at IS NOT NULL";
}

if (!empty($searchDept)) {
    $query .= " AND department LIKE ?";
    $params[] = "%$searchDept%";
}

$query .= " ORDER BY created_at DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (!$requests) {
    $requests = [];
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Department Receiving</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<style>
body { background:#f8fafc; font-family:'Segoe UI',sans-serif; }
.main-content { margin-left:260px; padding:30px; }
.card { border-radius:12px; box-shadow:0 5px 18px rgba(0,0,0,0.08); }
.table td, .table th { text-align:center; vertical-align:middle; }
.qty-input { width:100px; }
.progress { height:20px; }
.progress-bar { font-size:0.8rem; line-height:20px; }
</style>
</head>
<body>

<div class="main-sidebar"><?php include 'inventory_sidebar.php'; ?></div>

<div class="main-content">
<div class="card p-4 bg-white">
<h2 class="mb-4 text-primary"><i class="bi bi-box-seam"></i> Department Receiving</h2>

<form method="get" class="row g-3 mb-4">
    <div class="col-md-3">
        <select name="status" class="form-select">
            <option value="Pending"   <?= $statusFilter==='Pending'   ?'selected':'' ?>>Ready to Receive</option>
            <option value="Completed" <?= $statusFilter==='Completed' ?'selected':'' ?>>Completed</option>
            <option value="All"       <?= $statusFilter==='All'       ?'selected':'' ?>>All Purchased</option>
        </select>
    </div>
    <div class="col-md-3">
        <input type="text" name="search_dept" class="form-control"
               placeholder="Search Department"
               value="<?= htmlspecialchars($searchDept) ?>">
    </div>
    <div class="col-md-2">
        <button class="btn btn-primary w-100"><i class="bi bi-search"></i> Filter</button>
    </div>
    <div class="col-md-2">
        <a href="order_receive.php" class="btn btn-secondary w-100">
            <i class="bi bi-arrow-clockwise"></i> Reset
        </a>
    </div>
</form>

<div class="table-responsive">
<table class="table table-bordered table-hover align-middle">
<thead class="table-dark">
<tr>
    <th>ID</th>
    <th>Department</th>
    <th>User ID</th>
    <th>Total Items</th>
    <th>Status</th>
    <th>Receive Progress</th>
    <th>Purchased At</th>
    <th>Completed At</th>
    <th>Action</th>
</tr>
</thead>
<tbody>

<?php foreach($requests as $r):
    $stmtItems = $pdo->prepare("SELECT * FROM department_request_items WHERE request_id=? ORDER BY id ASC");
    $stmtItems->execute([$r['id']]);
    $itemsArray = $stmtItems->fetchAll(PDO::FETCH_ASSOC);

    $status = strtolower(trim($r['status']));

    $totalApproved = 0;
    $totalReceived = 0;
    foreach ($itemsArray as $it) {
        $totalApproved += (int)$it['approved_quantity'];
        $totalReceived += (int)($it['received_quantity'] ?? 0);
    }
    $progressPct = ($totalApproved > 0) ? round(($totalReceived / $totalApproved) * 100) : 0;
    $isCompleted  = ($status === 'completed');
?>
<tr class="<?= $isCompleted ? 'table-success' : '' ?>">
    <td><?= $r['id'] ?></td>
    <td><?= htmlspecialchars($r['department']) ?></td>
    <td><?= htmlspecialchars($r['user_id']) ?></td>
    <td><?= count($itemsArray) ?></td>
    <td>
        <?php if ($isCompleted): ?>
            <span class="badge bg-success fs-6">
                <i class="bi bi-check-circle-fill"></i> Completed
            </span>
        <?php elseif ($status === 'receiving'): ?>
            <span class="badge bg-warning text-dark">
                <i class="bi bi-arrow-repeat"></i> Partial
            </span>
        <?php else: ?>
            <span class="badge bg-primary">
                <i class="bi bi-box-seam"></i> Ready
            </span>
        <?php endif; ?>
    </td>
    <td style="min-width:160px;">
        <div class="progress">
            <div class="progress-bar <?= $isCompleted ? 'bg-success' : 'bg-primary' ?>"
                 role="progressbar"
                 style="width:<?= $progressPct ?>%">
                <?= $totalReceived ?>/<?= $totalApproved ?>
            </div>
        </div>
    </td>
    <td>
        <?= !empty($r['purchased_at'])
            ? date('M d, Y h:i A', strtotime($r['purchased_at']))
            : '<span class="text-muted">—</span>' ?>
    </td>
    <td>
        <?php if ($isCompleted && !empty($r['delivered_at'])): ?>
            <span class="text-success fw-semibold">
                <i class="bi bi-check2-all"></i>
                <?= date('M d, Y h:i A', strtotime($r['delivered_at'])) ?>
            </span>
        <?php else: ?>
            <span class="text-muted">—</span>
        <?php endif; ?>
    </td>
    <td>
        <a href="view_receipt.php?request_id=<?= $r['id'] ?>" class="btn btn-info btn-sm mb-1">
            <i class="bi bi-file-text"></i> Receipt
        </a>

        <?php if ($isCompleted): ?>
            <button class="btn btn-secondary btn-sm mb-1" disabled>
                <i class="bi bi-check2-all"></i> Received
            </button>
        <?php else: ?>
            <button class="btn btn-success btn-sm mb-1 receive-items-btn"
                data-id="<?= $r['id'] ?>"
                data-items='<?= htmlspecialchars(json_encode($itemsArray, JSON_UNESCAPED_UNICODE), ENT_QUOTES, "UTF-8") ?>'>
                <i class="bi bi-check2-square"></i> Receive
            </button>
        <?php endif; ?>
    </td>
</tr>
<?php endforeach; ?>

<?php if (empty($requests)): ?>
<tr>
    <td colspan="9" class="text-center text-muted py-4">
        <i class="bi bi-inbox fs-4 d-block mb-2"></i>
        No records found.
    </td>
</tr>
<?php endif; ?>

</tbody>
</table>
</div>
</div>
</div>

<!-- Receive Modal -->
<div class="modal fade" id="receiveModal" tabindex="-1">
<div class="modal-dialog modal-lg modal-dialog-scrollable">
<div class="modal-content">
<div class="modal-header bg-success text-white">
    <h5 class="modal-title"><i class="bi bi-box-seam"></i> Receive Items & Add to Inventory</h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<div class="modal-body" id="modalBodyContent"></div>
</div>
</div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.querySelectorAll('.receive-items-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        const items = JSON.parse(btn.dataset.items || '[]');

        const receivableItems = items.filter(item => {
            const approved = parseInt(item.approved_quantity || 0);
            const received = parseInt(item.received_quantity || 0);
            return (approved - received) > 0;
        });

        if (receivableItems.length === 0) {
            alert('All items have already been fully received.');
            return;
        }

        let html = `<form method="post" id="receiveForm">
        <input type="hidden" name="id" value="${btn.dataset.id}">
        <input type="hidden" name="action" value="receive">
        <div class="alert alert-info">
            <i class="bi bi-info-circle"></i>
            <strong>Instructions:</strong> Enter the quantity received for each item.
            Maximum allowed is the remaining quantity not yet received.
        </div>
        <div class="table-responsive">
        <table class="table table-bordered">
        <thead class="table-light">
        <tr>
            <th>Item Name</th>
            <th>Approved</th>
            <th>Received</th>
            <th>Remaining</th>
            <th>Unit</th>
            <th>Price</th>
            <th>Receive Qty</th>
        </tr></thead><tbody>`;

        items.forEach(item => {
            const idx       = item.id;
            const approved  = parseInt(item.approved_quantity || 0);
            const prev      = parseInt(item.received_quantity || 0);
            const remaining = approved - prev;
            const unit      = item.unit  || 'pcs';
            const price     = parseFloat(item.price || 0).toFixed(2);
            const isDone    = remaining <= 0;

            html += `<tr class="${isDone ? 'table-secondary' : ''}">
                <td>${item.item_name}</td>
                <td>${approved}</td>
                <td>${prev}</td>
                <td>${isDone
                    ? '<span class="badge bg-success">Done</span>'
                    : `<strong class="text-danger">${remaining}</strong>`}
                </td>
                <td>${unit}</td>
                <td>₱${price}</td>
                <td>
                    <input type="number"
                           class="form-control qty-input"
                           name="received_qty[${idx}]"
                           value="0"
                           min="0"
                           max="${remaining}"
                           ${isDone ? 'disabled' : 'required'}>
                </td>
            </tr>`;
        });

        html += `</tbody></table></div>
        <div class="text-end mt-3">
            <button type="submit" class="btn btn-success btn-lg">
                <i class="bi bi-check2"></i> Receive & Add to Inventory
            </button>
        </div>
        </form>`;

        document.getElementById('modalBodyContent').innerHTML = html;
        new bootstrap.Modal(document.getElementById('receiveModal')).show();
    });
});
</script>
</body>
</html>