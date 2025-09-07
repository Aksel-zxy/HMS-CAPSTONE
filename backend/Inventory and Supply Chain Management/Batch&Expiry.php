<?php
include 'db.php';

// ✅ Handle expiry update (set in medicine_batches + add/update inventory)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['batch_id'], $_POST['expiry_date'])) {
    $batch_id = intval($_POST['batch_id']);
    $expiry_date = $_POST['expiry_date'];

    // Fetch batch info
    $stmt = $pdo->prepare("SELECT mb.*, vp.item_name, vp.price, vp.item_type 
                           FROM medicine_batches mb
                           JOIN vendor_products vp ON mb.item_id = vp.id
                           WHERE mb.id = ?");
    $stmt->execute([$batch_id]);
    $batch = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($batch) {
        // Update expiry in medicine_batches
        $stmt = $pdo->prepare("UPDATE medicine_batches SET expiration_date = ? WHERE id = ?");
        $stmt->execute([$expiry_date, $batch_id]);

        // Check if already in inventory
        $stmt = $pdo->prepare("SELECT * FROM inventory WHERE item_id = ?");
        $stmt->execute([$batch['item_id']]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($existing) {
            // Update stock quantity
            $stmt = $pdo->prepare("UPDATE inventory SET quantity = quantity + ? WHERE item_id = ?");
            $stmt->execute([$batch['quantity'], $batch['item_id']]);
        } else {
            // Insert new stock
            $stmt = $pdo->prepare("
                INSERT INTO inventory (item_id, item_name, quantity, price, item_type, received_at)
                VALUES (?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $batch['item_id'],
                $batch['item_name'],
                $batch['quantity'],
                $batch['price'],
                $batch['item_type']
            ]);
        }
    }

    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// ✅ Handle partial dispose expired
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['dispose_id'], $_POST['dispose_qty'])) {
    $dispose_id = intval($_POST['dispose_id']);
    $dispose_qty = intval($_POST['dispose_qty']); // quantity to dispose

    // Fetch inventory item
    $stmt = $pdo->prepare("SELECT * FROM inventory WHERE id = ?");
    $stmt->execute([$dispose_id]);
    $item = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($item && $dispose_qty > 0 && $dispose_qty <= $item['quantity']) {
        // Find the expired batch for this item
        $stmt = $pdo->prepare("SELECT * FROM medicine_batches 
                               WHERE item_id = ? AND expiration_date < CURDATE()
                               ORDER BY id ASC LIMIT 1");
        $stmt->execute([$item['item_id']]);
        $batch = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($batch) {
            $batch_id = $batch['id'];
            $batch_no = $batch['batch_no'];

            // Insert into disposed_medicines (without item_id)
            $stmt = $pdo->prepare("INSERT INTO disposed_medicines (batch_id, batch_no, item_name, quantity, price, expiration_date) 
                                   VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $batch_id,
                $batch_no,
                $item['item_name'],
                $dispose_qty,
                $item['price'],
                $batch['expiration_date']
            ]);

            // Update inventory quantity
            $new_qty = $item['quantity'] - $dispose_qty;
            if ($new_qty <= 0) {
                // remove from inventory if all disposed
                $stmt = $pdo->prepare("DELETE FROM inventory WHERE id = ?");
                $stmt->execute([$dispose_id]);
            } else {
                // update remaining quantity
                $stmt = $pdo->prepare("UPDATE inventory SET quantity = ? WHERE id = ?");
                $stmt->execute([$new_qty, $dispose_id]);
            }
        }
    }

    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// ✅ Fetch newly delivered medicines
$stmt = $pdo->prepare("SELECT mb.*, vp.item_name, vp.price 
                       FROM medicine_batches mb
                       JOIN vendor_products vp ON mb.item_id = vp.id
                       WHERE mb.expiration_date IS NULL
                       ORDER BY mb.id ASC");
$stmt->execute();
$new_delivered = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ✅ Fetch inventory medicines with batch info, excluding already disposed
$stmt = $pdo->prepare("
    SELECT i.*, mb.batch_no, mb.expiration_date 
    FROM inventory i
    LEFT JOIN medicine_batches mb ON i.item_id = mb.item_id
    LEFT JOIN disposed_medicines dm ON mb.id = dm.batch_id
    WHERE i.item_type = 'Medications and pharmacy supplies' 
      AND dm.id IS NULL
    ORDER BY i.id ASC
");
$stmt->execute();
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ✅ Fetch disposed medicines
$stmt = $pdo->prepare("SELECT * FROM disposed_medicines ORDER BY disposed_at DESC");
$stmt->execute();
$disposed = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Categorize & prepare alerts
$expired = [];
$near_expiry = [];
$safe = [];
$seven_days_alert = [];

foreach ($rows as $row) {
    if (!empty($row['expiration_date'])) {
        $today = new DateTime();
        $expiry = new DateTime($row['expiration_date']);
        $diffDays = $today->diff($expiry)->days;

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
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Batch & Expiry Tracking - Medicines</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light p-4">

<div class="main-sidebar">
    <?php include 'Inventory_dashboard.php'; ?>
</div>

<div class="container">
    <h2 class="mb-4">Batch & Expiry Tracking - Medicines</h2>

    <!-- Alert if any expiring in 7 days -->
    <?php if (!empty($seven_days_alert)): ?>
        <div class="alert alert-warning">
            <h6><strong>⚠️ Expiry Alerts</strong></h6>
            <ul class="mb-0">
                <?php foreach ($seven_days_alert as $item): ?>
                    <?php
                        $today = new DateTime();
                        $expiry = new DateTime($item['expiration_date']);
                        $diffDays = $today->diff($expiry)->days;

                        if ($expiry < $today) {
                            $msg = htmlspecialchars($item['item_name']) . " is already <strong>Expired</strong>!";
                        } elseif ($diffDays == 0) {
                            $msg = htmlspecialchars($item['item_name']) . " will expire <strong>Today</strong>!";
                        } elseif ($diffDays == 1) {
                            $msg = htmlspecialchars($item['item_name']) . " will expire in <strong>1 day</strong>!";
                        } else {
                            $msg = htmlspecialchars($item['item_name']) . " will expire in <strong>$diffDays days</strong>!";
                        }
                    ?>
                    <li><?= $msg ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <!-- Tabs -->
    <ul class="nav nav-tabs mb-3" id="medicineTabs" role="tablist">
        <li class="nav-item"><button class="nav-link" id="new-tab" data-bs-toggle="tab" data-bs-target="#newDelivered" type="button" role="tab">New Delivered</button></li>
        <li class="nav-item"><button class="nav-link active" id="stock-tab" data-bs-toggle="tab" data-bs-target="#medicineStocks" type="button" role="tab">Medicine Stocks</button></li>
        <li class="nav-item"><button class="nav-link" id="disposed-tab" data-bs-toggle="tab" data-bs-target="#disposedMedicines" type="button" role="tab">Disposed Medicines</button></li>
    </ul>

    <div class="tab-content border p-3 bg-white rounded-bottom">
        <!-- New Delivered -->
        <div class="tab-pane fade" id="newDelivered" role="tabpanel">
            <?php if (empty($new_delivered)): ?>
                <p class="text-muted">No newly delivered medicines.</p>
            <?php else: ?>
                <table class="table table-bordered">
                    <thead class="table-light">
                        <tr>
                            <th>ID</th>
                            <th>Item Name</th>
                            <th>Quantity</th>
                            <th>Price</th>
                            <th>Set Expiry</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($new_delivered as $row): ?>
                            <tr>
                                <td><?= $row['id'] ?></td>
                                <td><?= htmlspecialchars($row['item_name']) ?></td>
                                <td><?= $row['quantity'] ?></td>
                                <td><?= number_format($row['price'], 2) ?></td>
                                <td>
                                    <form method="post" class="d-flex">
                                        <input type="hidden" name="batch_id" value="<?= $row['id'] ?>">
                                        <input type="date" name="expiry_date" class="form-control me-2" required>
                                        <button type="submit" class="btn btn-sm btn-success">Set</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <!-- Medicine Stocks -->
        <div class="tab-pane fade show active" id="medicineStocks" role="tabpanel">
            <h5>Medicine Stocks</h5>
            <?php if (empty($expired) && empty($near_expiry) && empty($safe)): ?>
                <p class="text-muted">No medicines available.</p>
            <?php else: ?>
                <table class="table table-bordered">
                    <thead class="table-light">
                        <tr>
                            <th>ID</th>
                            <th>Item Name</th>
                            <th>Quantity</th>
                            <th>Price</th>
                            <th>Expiration Date</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach (array_merge($expired, $near_expiry, $safe) as $row): ?>
                            <tr class="<?= $row['row_class'] ?>">
                                <td><?= $row['id'] ?></td>
                                <td><?= htmlspecialchars($row['item_name']) ?></td>
                                <td><?= $row['quantity'] ?></td>
                                <td><?= number_format($row['price'], 2) ?></td>
                                <td><?= $row['expiration_date'] ?></td>
                                <td><?= $row['status'] ?></td>
                                <td>
                                    <?php if ($row['status'] === 'Expired'): ?>
                                        <button class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#disposeModal" data-id="<?= $row['id'] ?>" data-name="<?= htmlspecialchars($row['item_name']) ?>" data-qty="<?= $row['quantity'] ?>">
                                            Dispose
                                        </button>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <!-- Disposed Medicines -->
        <div class="tab-pane fade" id="disposedMedicines" role="tabpanel">
            <h5>Disposed Medicines</h5>
            <?php if (empty($disposed)): ?>
                <p class="text-muted">No disposed medicines yet.</p>
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
                                <td><?= number_format($row['price'], 2) ?></td>
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
<div class="modal fade" id="disposeModal" tabindex="-1" aria-hidden="true">
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
