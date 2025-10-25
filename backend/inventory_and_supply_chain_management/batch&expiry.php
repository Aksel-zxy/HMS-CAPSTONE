<?php 
include '../../SQL/config.php';

// ===============================
// Handle expiry update for medicines (per batch)
// ===============================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['batch_id'], $_POST['expiry_date'])) {
    $batch_id = intval($_POST['batch_id']);
    $expiry_date = $_POST['expiry_date'];

    // Update the existing batch with expiry date only
    $stmt = $pdo->prepare("UPDATE medicine_batches SET expiration_date = ? WHERE id = ?");
    $stmt->execute([$expiry_date, $batch_id]);

    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// ===============================
// Handle disposal of expired meds
// ===============================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['dispose_id'], $_POST['dispose_qty'])) {
    $dispose_id = intval($_POST['dispose_id']);
    $dispose_qty = intval($_POST['dispose_qty']); 

    // Fetch batch
    $stmt = $pdo->prepare("SELECT * FROM medicine_batches WHERE id=?");
    $stmt->execute([$dispose_id]);
    $batch = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($batch && $dispose_qty > 0 && $dispose_qty <= $batch['quantity']) {
        // Log disposed quantity
        $stmt = $pdo->prepare("INSERT INTO disposed_medicines (batch_id, batch_no, item_name, quantity, price, expiration_date) 
                               VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $batch['id'],
            $batch['batch_no'],
            $batch['item_name'] ?? '', 
            $dispose_qty,
            $batch['price'] ?? 0,
            $batch['expiration_date']
        ]);

        // Update or delete batch
        $stmt = $pdo->prepare("UPDATE medicine_batches SET quantity = quantity - ? WHERE id = ?");
        $stmt->execute([$dispose_qty, $batch['id']]);
        if ($batch['quantity'] - $dispose_qty <= 0) {
            $stmt = $pdo->prepare("DELETE FROM medicine_batches WHERE id=?");
            $stmt->execute([$batch['id']]);
        }
    }

    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// ===============================
// Fetch new deliveries without expiry
// ===============================
$stmt = $pdo->prepare("
    SELECT mb.*, vp.item_name, vp.price, vp.unit_type, vp.pcs_per_box
    FROM medicine_batches mb
    JOIN vendor_products vp ON mb.item_id = vp.id
    WHERE mb.expiration_date IS NULL
    ORDER BY mb.id ASC
");
$stmt->execute();
$new_delivered = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ===============================
// Fetch inventory with combined batches per expiry
// ===============================
$stmt = $pdo->prepare("
    SELECT 
        mb.item_id,
        vp.item_name,
        vp.price,
        vp.unit_type,
        vp.pcs_per_box,
        SUM(mb.quantity) AS total_pcs,
        COUNT(mb.id) AS boxes_count,
        GROUP_CONCAT(mb.batch_no SEPARATOR ', ') AS batch_numbers,
        mb.expiration_date
    FROM medicine_batches mb
    JOIN vendor_products vp ON mb.item_id = vp.id
    WHERE mb.expiration_date IS NOT NULL
    GROUP BY mb.item_id, mb.expiration_date
    ORDER BY vp.item_name, mb.expiration_date
");
$stmt->execute();
$inventory_rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ===============================
// Categorize stock by expiry
// ===============================
$expired = [];
$near_expiry = [];
$safe = [];
$seven_days_alert = [];

foreach ($inventory_rows as $row) {
    $today = new DateTime();
    $expiry = new DateTime($row['expiration_date']);
    $diffDays = (int)$today->diff($expiry)->format("%r%a");

    if ($expiry < $today) {
        $row['row_class'] = "table-danger";
        $row['status'] = "Expired";
        $expired[] = $row;
        $seven_days_alert[] = $row;
    } elseif ($diffDays <= 7) {
        $row['row_class'] = "table-warning";
        $row['status'] = "Near Expiry";
        $row['days_left'] = $diffDays;
        $near_expiry[] = $row;
        $seven_days_alert[] = $row;
    } elseif ($diffDays <= 30) {
        $row['row_class'] = "table-warning";
        $row['status'] = "Near Expiry";
        $row['days_left'] = $diffDays;
        $near_expiry[] = $row;
    } else {
        $row['row_class'] = "table-success";
        $row['status'] = "Safe";
        $safe[] = $row;
    }
}

// ===============================
// Fetch disposed medicines
// ===============================
$stmt = $pdo->prepare("SELECT * FROM disposed_medicines ORDER BY disposed_at DESC");
$stmt->execute();
$disposed = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Batch & Expiry Tracking - Medicines</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light p-4">

<div class="main-sidebar">
    <?php include 'inventory_sidebar.php'; ?>
</div>

<div class="container">
    <h2 class="mb-4">Batch & Expiry Tracking - Medicines</h2>

    <!-- Alert -->
    <?php if (!empty($seven_days_alert)): ?>
        <div class="alert alert-warning">
            <h6><strong>⚠️ Expiry Alerts</strong></h6>
            <ul class="mb-0">
                <?php foreach ($seven_days_alert as $item): ?>
                    <?php
                        $today = new DateTime();
                        $expiry = new DateTime($item['expiration_date']);
                        $diffDays = (int)$today->diff($expiry)->format("%r%a");
                        $msg = $expiry < $today ?
                               htmlspecialchars($item['item_name'])." is already <strong>Expired</strong>!" :
                               htmlspecialchars($item['item_name'])." will expire in <strong>$diffDays days</strong>!";
                    ?>
                    <li><?= $msg ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <!-- Tabs -->
    <ul class="nav nav-tabs mb-3">
        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#newDelivered">New Delivered</button></li>
        <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#medicineStocks">Medicine Stocks</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#disposedMedicines">Disposed Medicines</button></li>
    </ul>

    <div class="tab-content border p-3 bg-white rounded-bottom">
        <!-- New Delivered -->
        <div class="tab-pane fade" id="newDelivered">
            <?php if (empty($new_delivered)): ?>
                <p class="text-muted">No new medicines awaiting expiry date.</p>
            <?php else: ?>
                <table class="table table-bordered">
                    <thead class="table-light">
                        <tr>
                            <th>ID</th>
                            <th>Item Name</th>
                            <th>Quantity (pcs)</th>
                            <th>Unit</th>
                            <th>Set Expiry</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($new_delivered as $row): ?>
                            <tr>
                                <td><?= $row['id'] ?></td>
                                <td><?= htmlspecialchars($row['item_name']) ?></td>
                                <td><?= $row['quantity'] ?></td>
                                <td><?= $row['unit_type'] ?></td>
                                <td>
                                    <form method="post">
                                        <input type="hidden" name="batch_id" value="<?= $row['id'] ?>">
                                        <input type="date" name="expiry_date" class="form-control" required>
                                        <button type="submit" class="btn btn-success btn-sm mt-1">Set</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <!-- Medicine Stocks -->
        <div class="tab-pane fade show active" id="medicineStocks">
            <h5>Medicine Stocks</h5>
            <table class="table table-bordered">
                <thead class="table-light">
                    <tr>
                        <th>Item Name</th>
                        <th>Boxes</th>
                        <th>Total Pieces</th>
                        <th>Batch Numbers</th>
                        <th>Expiration Date</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach (array_merge($expired, $near_expiry, $safe) as $row): ?>
                        <tr class="<?= $row['row_class'] ?>">
                            <td><?= htmlspecialchars($row['item_name']) ?></td>
                            <td><?= $row['boxes_count'] ?></td>
                            <td><?= $row['total_pcs'] ?></td>
                            <td><?= $row['batch_numbers'] ?></td>
                            <td><?= $row['expiration_date'] ?></td>
                            <td><?= $row['status'] ?></td>
                            <td>
                                <?php if ($row['status'] === 'Expired'): ?>
                                    <button class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#disposeModal" 
                                        data-id="<?= $row['item_id'] ?>" data-name="<?= htmlspecialchars($row['item_name']) ?>" 
                                        data-qty="<?= $row['total_pcs'] ?>">Dispose</button>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Disposed Medicines -->
        <div class="tab-pane fade" id="disposedMedicines">
            <h5>Disposed Medicines</h5>
            <?php if (empty($disposed)): ?>
                <p class="text-muted">No disposed medicines.</p>
            <?php else: ?>
                <table class="table table-bordered">
                    <thead class="table-light">
                        <tr>
                            <th>ID</th>
                            <th>Batch ID</th>
                            <th>Item Name</th>
                            <th>Quantity</th>
                            <th>Price</th>
                            <th>Expiration Date</th>
                            <th>Disposed At</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($disposed as $row): ?>
                            <tr class="table-secondary">
                                <td><?= $row['id'] ?></td>
                                <td><?= $row['batch_id'] ?></td>
                                <td><?= htmlspecialchars($row['item_name']) ?></td>
                                <td><?= $row['quantity'] ?></td>
                                <td><?= number_format($row['price'],2) ?></td>
                                <td><?= $row['expiration_date'] ?></td>
                                <td><?= $row['disposed_at'] ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Dispose Confirmation Modal -->
<div class="modal fade" id="disposeModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header bg-danger text-white">
        <h5 class="modal-title">Confirm Disposal</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <p>Are you sure you want to dispose <strong id="medicineName"></strong>?</p>
        <div class="mb-2">
            <label for="disposeQty" class="form-label">Quantity to Dispose:</label>
            <input type="number" min="1" class="form-control" id="disposeQty" name="dispose_qty">
        </div>
      </div>
      <div class="modal-footer">
        <form method="post" id="disposeForm">
            <input type="hidden" name="dispose_id" id="disposeId">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-danger">Dispose</button>
        </form>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
var disposeModal = document.getElementById('disposeModal');
disposeModal.addEventListener('show.bs.modal', function (event) {
    var button = event.relatedTarget;
    var itemId = button.getAttribute('data-id');
    var itemName = button.getAttribute('data-name');
    var qty = button.getAttribute('data-qty');

    document.getElementById('disposeId').value = itemId;
    document.getElementById('medicineName').textContent = itemName;
    document.getElementById('disposeQty').value = qty;
    document.getElementById('disposeQty').max = qty;
});
</script>
</body>
</html>
