<?php
include '../../SQL/config.php';

/* =====================================================
   HANDLE RECEIVING
=====================================================*/
if (isset($_POST['action']) && $_POST['action'] === 'receive') {

    $request_id = $_POST['id'];

    $stmt = $pdo->prepare("SELECT * FROM department_request WHERE id=? AND status IN ('Approved','Receiving') LIMIT 1");
    $stmt->execute([$request_id]);
    $request = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($request) {

        $items = json_decode($request['items'], true) ?: [];
        $received_quantities = $_POST['received_quantity'] ?? [];

        foreach ($items as $index => &$item) {

            $approved_qty = $item['approved_quantity'] ?? 0;
            $already_received = $item['received_quantity'] ?? 0;
            $receive_now = isset($received_quantities[$index]) ? (int)$received_quantities[$index] : 0;

            $remaining = $approved_qty - $already_received;
            $receive_now = max(0, min($receive_now, $remaining));
            if ($receive_now <= 0) continue;

            $item['received_quantity'] = $already_received + $receive_now;

            $item_name = $item['name'];
            $unit = strtolower($item['unit'] ?? 'piece');
            $pcs_per_box = $item['pcs_per_box'] ?? 1;

            $total_qty = ($unit === 'box') ? $receive_now * $pcs_per_box : $receive_now;

            /* GET ITEM PRICE FROM department_request_prices */
            $stmtPrice = $pdo->prepare("
                SELECT price 
                FROM department_request_prices 
                WHERE request_id=? AND item_index=? 
                ORDER BY id DESC LIMIT 1
            ");
            $stmtPrice->execute([$request_id, $index]);
            $priceRow = $stmtPrice->fetch(PDO::FETCH_ASSOC);
            $price = $priceRow['price'] ?? 0;

            /* UPDATE INVENTORY */
            $stmtCheck = $pdo->prepare("SELECT * FROM inventory WHERE item_name=? LIMIT 1");
            $stmtCheck->execute([$item_name]);
            $existing = $stmtCheck->fetch(PDO::FETCH_ASSOC);

            if ($existing) {
                $stmtUpdate = $pdo->prepare("
                    UPDATE inventory
                    SET quantity = quantity + ?,
                        total_qty = total_qty + ?,
                        price = ?
                    WHERE item_name = ?
                ");
                $stmtUpdate->execute([$receive_now, $total_qty, $price, $item_name]);
            } else {
                $stmtInsert = $pdo->prepare("
                    INSERT INTO inventory
                    (item_name, quantity, total_qty, price, unit_type, pcs_per_box,
                     location, min_stock, max_stock)
                    VALUES (?, ?, ?, ?, ?, ?, 'Main Storage', 0, 9999)
                ");
                $stmtInsert->execute([$item_name, $receive_now, $total_qty, $price, ucfirst($unit), $pcs_per_box]);
            }
        }
        unset($item);

        $all_received = true;
        foreach ($items as $item) {
            if (($item['received_quantity'] ?? 0) < ($item['approved_quantity'] ?? 0)) {
                $all_received = false;
                break;
            }
        }

        $new_status = $all_received ? 'Completed' : 'Receiving';

        $stmtUpdateReq = $pdo->prepare("
            UPDATE department_request
            SET items=?, status=?
            WHERE id=?
        ");
        $stmtUpdateReq->execute([json_encode($items), $new_status, $request_id]);
    }

    header("Location: receive_order.php");
    exit;
}

/* =====================================================
   FETCH ORDERS
=====================================================*/
$query = "SELECT * FROM department_request 
          WHERE status IN ('Approved','Receiving','Completed')
          ORDER BY created_at DESC";

$stmt = $pdo->prepare($query);
$stmt->execute();
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Receive Orders</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

<style>
body { background:#f4f6f9; font-family:'Segoe UI',sans-serif; }
.main-content { margin-left:260px; padding:30px; }
.card { border-radius:12px; box-shadow:0 5px 18px rgba(0,0,0,0.08); }
.receipt-header { border-bottom:2px solid #000; padding-bottom:10px; margin-bottom:20px; }
.signature-line { border-top:1px solid #000; margin-top:60px; text-align:center; padding-top:5px; }
.print-area { background:white; padding:30px; }
</style>
</head>
<body>

<div class="main-sidebar"><?php include 'inventory_sidebar.php'; ?></div>

<div class="main-content">
<div class="card p-4 bg-white">

<h2 class="mb-4 text-primary">
<i class="bi bi-truck"></i> Delivery Orders
</h2>

<div class="table-responsive">
<table class="table table-bordered table-hover">
<thead class="table-light">
<tr>
<th>ID</th>
<th>Department</th>
<th>Status</th>
<th>Purchased At</th>
<th>Receipt</th>
<th>Receive</th>
</tr>
</thead>
<tbody>

<?php foreach($orders as $o): ?>
<tr>
<td><?= $o['id'] ?></td>
<td><?= htmlspecialchars($o['department']) ?></td>
<td><?= $o['status'] ?></td>
<td><?= $o['purchased_at'] ?></td>

<td>
<button class="btn btn-secondary btn-sm receipt-btn"
    data-id="<?= $o['id'] ?>"
    data-department="<?= htmlspecialchars($o['department']) ?>"
    data-date="<?= $o['purchased_at'] ?>"
    data-items='<?= htmlspecialchars($o['items'], ENT_QUOTES) ?>'>
    <i class="bi bi-receipt"></i> Receipt
</button>
</td>

<td>
<?php if($o['status'] !== 'Completed'): ?>
<button class="btn btn-success btn-sm receive-btn"
    data-id="<?= $o['id'] ?>"
    data-items='<?= htmlspecialchars($o['items'], ENT_QUOTES) ?>'>
    <i class="bi bi-box-seam"></i> Receive
</button>
<?php else: ?>
<span class="badge bg-primary">Completed</span>
<?php endif; ?>
</td>

</tr>
<?php endforeach; ?>

</tbody>
</table>
</div>

</div>
</div>

<!-- MODAL -->
<div class="modal fade" id="modalBox">
<div class="modal-dialog modal-xl modal-dialog-scrollable">
<div class="modal-content">
<div class="modal-body" id="modalContent"></div>
</div>
</div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>

/* ================= RECEIPT VIEW ================= */
document.querySelectorAll('.receipt-btn').forEach(btn => {
btn.addEventListener('click', async () => {

const id = btn.dataset.id;

let response = await fetch('fetch_receipt_prices.php?id=' + id);
let data = await response.json();

let grandTotal = 0;

let html = `
<div class="print-area">

<div class="receipt-header text-center">
<h3><strong>YOUR COMPANY NAME</strong></h3>
<p>Warehouse & Inventory Department</p>
<h4 class="mt-3">DELIVERY RECEIPT</h4>
</div>

<table class="table table-bordered">
<thead class="table-light">
<tr>
<th>Item</th>
<th>Qty</th>
<th>Unit Price</th>
<th>Total</th>
</tr>
</thead>
<tbody>
`;

data.forEach(item => {
grandTotal += parseFloat(item.total_price);

html += `
<tr>
<td>${item.name}</td>
<td class="text-center">${item.quantity}</td>
<td class="text-end">₱ ${parseFloat(item.price).toFixed(2)}</td>
<td class="text-end">₱ ${parseFloat(item.total_price).toFixed(2)}</td>
</tr>
`;
});

html += `
<tr>
<td colspan="3" class="text-end"><strong>GRAND TOTAL</strong></td>
<td class="text-end"><strong>₱ ${grandTotal.toFixed(2)}</strong></td>
</tr>
</tbody>
</table>

<div class="text-end mt-4">
<button onclick="window.print()" class="btn btn-dark">Print</button>
</div>

</div>
`;

document.getElementById('modalContent').innerHTML = html;
new bootstrap.Modal(document.getElementById('modalBox')).show();
});
});

</script>

</body>
</html>
