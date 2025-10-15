<?php 
include '../../SQL/config.php';

// ===============================
// Function to display quantity nicely
// ===============================
function displayQty($quantity, $pcs_per_box, $unit_type) {
    if ($unit_type === 'Box') {
        if ($pcs_per_box > 0) {
            $boxes = intdiv($quantity, $pcs_per_box);
            $pcs = $quantity % $pcs_per_box;
            $out = '';
            if ($boxes > 0) $out .= $boxes . " Box(es)";
            if ($pcs > 0) $out .= ($boxes > 0 ? " + " : "") . $pcs . " pcs";
            if ($out === '') $out = '0 pcs';
            return $out;
        } else {
            return $quantity . " pcs";
        }
    } else {
        return $quantity . " pcs";
    }
}

// ===============================
// Update inventory quantity function
// ===============================
function updateInventory($item_id, $pdo) {
    // Sum all batches that exist and have NOT been disposed
    // A batch is disposed if it appears in disposed_medicines table
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(mb.quantity), 0) as total_qty
        FROM medicine_batches mb
        WHERE mb.item_id = ? 
        AND mb.id NOT IN (SELECT DISTINCT batch_id FROM disposed_medicines WHERE batch_id IS NOT NULL)
    ");
    $stmt->execute([$item_id]);
    $total_qty = (int)$stmt->fetchColumn();

    $stmtCheck = $pdo->prepare("SELECT * FROM inventory WHERE item_id = ?");
    $stmtCheck->execute([$item_id]);
    $exists = $stmtCheck->fetch(PDO::FETCH_ASSOC);

    if ($exists) {
        $stmtUpdate = $pdo->prepare("UPDATE inventory SET quantity = ? WHERE item_id = ?");
        $stmtUpdate->execute([$total_qty, $item_id]);
    } else {
        $stmtProd = $pdo->prepare("SELECT * FROM vendor_products WHERE id = ?");
        $stmtProd->execute([$item_id]);
        $prod = $stmtProd->fetch(PDO::FETCH_ASSOC);

        if ($prod) {
            $stmtInsert = $pdo->prepare("INSERT INTO inventory 
                (item_id, item_name, quantity, price, item_type, unit_type, pcs_per_box, received_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmtInsert->execute([
                $item_id,
                $prod['item_name'],
                $total_qty,
                $prod['price'],
                $prod['item_type'],
                $prod['unit_type'],
                $prod['pcs_per_box']
            ]);
        }
    }

    return $total_qty;
}

// ===============================
// Handle expiry update for medicines (per box)
// ===============================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['batch_id'], $_POST['expiry_date'])) {
    $batch_id = intval($_POST['batch_id']);

    // Fetch batch info
    $stmt = $pdo->prepare("SELECT mb.*, vp.item_name, vp.price, vp.item_type, vp.unit_type, vp.pcs_per_box
                           FROM medicine_batches mb
                           JOIN vendor_products vp ON mb.item_id = vp.id
                           WHERE mb.id = ?");
    $stmt->execute([$batch_id]);
    $batch = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($batch) {
        // If unit is BOX → create separate batch entries for each box with expiry per box
        if ($batch['unit_type'] === 'Box' && $batch['quantity'] > 1 && is_array($_POST['expiry_date'])) {
            foreach ($_POST['expiry_date'] as $exp) {
                $stmtInsert = $pdo->prepare("INSERT INTO medicine_batches 
                    (item_id, batch_no, quantity, expiration_date, received_at) 
                    VALUES (?, ?, ?, ?, NOW())");
                $stmtInsert->execute([
                    $batch['item_id'],
                    "BATCH-" . uniqid(),
                    $batch['pcs_per_box'], // pieces per box stored as quantity
                    $exp
                ]);
            }
            // Remove placeholder batch
            $stmtDel = $pdo->prepare("DELETE FROM medicine_batches WHERE id = ?");
            $stmtDel->execute([$batch_id]);
        } else {
            // Single box or piece → set expiry directly
            $expiry_date = is_array($_POST['expiry_date']) ? $_POST['expiry_date'][0] : $_POST['expiry_date'];
            $stmtUpdate = $pdo->prepare("UPDATE medicine_batches SET expiration_date = ? WHERE id = ?");
            $stmtUpdate->execute([$expiry_date, $batch_id]);
        }

        // Update inventory stock
        updateInventory($batch['item_id'], $pdo);
    }

    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// ===============================
// Handle disposal of expired meds
// ===============================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['dispose_id'], $_POST['dispose_qty'])) {
    $dispose_id = intval($_POST['dispose_id']);
    $dispose_qty = intval($_POST['dispose_qty']); 

    // Fetch inventory item
    $stmt = $pdo->prepare("SELECT * FROM inventory WHERE id = ?");
    $stmt->execute([$dispose_id]);
    $item = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($item && $dispose_qty > 0 && $dispose_qty <= $item['quantity']) {
        while($dispose_qty > 0) {
            // Find expired batch that hasn't been disposed yet
            $stmtBatch = $pdo->prepare("SELECT mb.* FROM medicine_batches mb
                                        WHERE mb.item_id = ? 
                                        AND mb.expiration_date < CURDATE()
                                        AND mb.id NOT IN (SELECT DISTINCT batch_id FROM disposed_medicines WHERE batch_id IS NOT NULL)
                                        ORDER BY mb.expiration_date ASC, mb.id ASC 
                                        LIMIT 1");
            $stmtBatch->execute([$item['item_id']]);
            $batch = $stmtBatch->fetch(PDO::FETCH_ASSOC);

            if (!$batch) break;

            $batch_qty = $batch['quantity'];
            $qty_to_dispose = min($dispose_qty, $batch_qty);

            // Log into disposed_medicines
            $stmtLog = $pdo->prepare("INSERT INTO disposed_medicines 
                (batch_id, batch_no, item_id, item_name, quantity, price, expiration_date) 
                VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmtLog->execute([
                $batch['id'],
                $batch['batch_no'],
                $item['item_id'],
                $item['item_name'],
                $qty_to_dispose,
                $item['price'],
                $batch['expiration_date']
            ]);

            // Update or delete batch
            if ($batch_qty - $qty_to_dispose <= 0) {
                // If disposing entire batch, delete it
                $stmtDel = $pdo->prepare("DELETE FROM medicine_batches WHERE id = ?");
                $stmtDel->execute([$batch['id']]);
            } else {
                // Otherwise, reduce the quantity
                $stmtUpdate = $pdo->prepare("UPDATE medicine_batches SET quantity = quantity - ? WHERE id = ?");
                $stmtUpdate->execute([$qty_to_dispose, $batch['id']]);
            }

            $dispose_qty -= $qty_to_dispose;
        }

        // Update inventory quantity
        updateInventory($item['item_id'], $pdo);
    }

    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// ===============================
// Fetch new deliveries without expiry
// ===============================
$stmt = $pdo->prepare("SELECT mb.*, vp.item_name, vp.price, vp.unit_type, vp.pcs_per_box
                       FROM medicine_batches mb
                       JOIN vendor_products vp ON mb.item_id = vp.id
                       WHERE mb.expiration_date IS NULL
                       ORDER BY mb.id ASC");
$stmt->execute();
$new_delivered = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ===============================
// Fetch inventory with batch info
// ===============================
$stmt = $pdo->prepare("
    SELECT 
        mb.id as batch_id,
        mb.item_id,
        mb.batch_no,
        mb.quantity as batch_quantity,
        mb.expiration_date,
        mb.received_at,
        vp.item_name,
        vp.price,
        vp.unit_type,
        vp.pcs_per_box,
        COALESCE(i.id, 0) as inventory_id
    FROM medicine_batches mb
    JOIN vendor_products vp ON mb.item_id = vp.id
    LEFT JOIN inventory i ON mb.item_id = i.item_id
    WHERE mb.expiration_date IS NOT NULL
    AND mb.id NOT IN (SELECT DISTINCT batch_id FROM disposed_medicines WHERE batch_id IS NOT NULL)
    ORDER BY vp.item_name ASC, mb.expiration_date ASC
");
$stmt->execute();
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Debug: Check what we got
error_log("Total rows fetched: " . count($rows));
if (!empty($rows)) {
    error_log("First row: " . print_r($rows[0], true));
}

// Update inventory for all items that have batches
$stmt = $pdo->prepare("SELECT DISTINCT item_id FROM medicine_batches WHERE expiration_date IS NOT NULL");
$stmt->execute();
$items_with_batches = $stmt->fetchAll(PDO::FETCH_COLUMN);
foreach ($items_with_batches as $item_id) {
    updateInventory($item_id, $pdo);
}

// ===============================
// Fetch disposed meds
// ===============================
$stmt = $pdo->prepare("SELECT * FROM disposed_medicines ORDER BY disposed_at DESC");
$stmt->execute();
$disposed = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ===============================
// Categorize stock by expiry
// ===============================
$expired = [];
$near_expiry = [];
$safe = [];
$seven_days_alert = [];

foreach ($rows as $row) {
    if (!empty($row['expiration_date'])) {
        $today = new DateTime();
        $expiry = new DateTime($row['expiration_date']);
        $diffDays = (int)$today->diff($expiry)->format("%r%a");

        // Set correct ID for disposal (use inventory_id if exists, otherwise use batch_id)
        $row['id'] = $row['inventory_id'] > 0 ? $row['inventory_id'] : $row['batch_id'];
        $row['quantity'] = $row['batch_quantity']; // Use batch quantity for display

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
    <link rel="stylesheet" href="assets/css/Inventory_dashboard.css">
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
                <?php 
                $alerted = [];
                foreach ($seven_days_alert as $item): 
                    $itemName = isset($item['item_name']) ? $item['item_name'] : 'Unknown';
                    $batchNo = isset($item['batch_no']) ? $item['batch_no'] : 'N/A';
                    $expiryDate = isset($item['expiration_date']) ? $item['expiration_date'] : '';
                    
                    $key = $itemName . '_' . $expiryDate;
                    if (in_array($key, $alerted)) continue;
                    $alerted[] = $key;
                    
                    if (empty($expiryDate)) continue;
                    
                    $today = new DateTime();
                    $expiry = new DateTime($expiryDate);
                    $diffDays = (int)$today->diff($expiry)->format("%r%a");
                    $msg = $expiry < $today ? 
                           htmlspecialchars($itemName)." (Batch: ".htmlspecialchars($batchNo).") is already <strong>Expired</strong>!" :
                           htmlspecialchars($itemName)." (Batch: ".htmlspecialchars($batchNo).") will expire in <strong>$diffDays days</strong>!";
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
                <p class="text-muted">No new medicines.</p>
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
                                <td><?= displayQty($row['quantity'], $row['pcs_per_box'], $row['unit_type']) ?></td>
                                <td><?= number_format($row['price'],2) ?></td>
                                <td>
                                    <form method="post">
                                        <input type="hidden" name="batch_id" value="<?= $row['id'] ?>">
                                        <?php if ($row['quantity'] > 1 && $row['unit_type'] === 'Box'): ?>
                                            <?php for ($i=1; $i<=$row['quantity']; $i++): ?>
                                                <input type="date" name="expiry_date[]" class="form-control mb-1" required>
                                            <?php endfor; ?>
                                        <?php else: ?>
                                            <input type="date" name="expiry_date" class="form-control" required>
                                        <?php endif; ?>
                                        <button type="submit" class="btn btn-sm btn-success mt-2">Set</button>
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
            <?php if (empty($expired) && empty($near_expiry) && empty($safe)): ?>
                <p class="text-muted">No medicines in stock.</p>
            <?php else: ?>
                <table class="table table-bordered">
                    <thead class="table-light">
                        <tr>
                            <th>Batch ID</th>
                            <th>Batch No</th>
                            <th>Item Name</th>
                            <th>Quantity</th>
                            <th>Price</th>
                            <th>Expiration Date</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach (array_merge($expired,$near_expiry,$safe) as $row): ?>
                            <tr class="<?= $row['row_class'] ?>">
                                <td><?= isset($row['batch_id']) ? $row['batch_id'] : 'N/A' ?></td>
                                <td><?= isset($row['batch_no']) ? htmlspecialchars($row['batch_no']) : 'N/A' ?></td>
                                <td><?= isset($row['item_name']) ? htmlspecialchars($row['item_name']) : 'N/A' ?></td>
                                <td><?= displayQty($row['quantity'], $row['pcs_per_box'] ?? 0, $row['unit_type'] ?? 'Piece') ?></td>
                                <td><?= isset($row['price']) ? number_format($row['price'],2) : '0.00' ?></td>
                                <td><?= isset($row['expiration_date']) ? $row['expiration_date'] : 'N/A' ?></td>
                                <td>
                                    <?= isset($row['status']) ? $row['status'] : 'Unknown' ?>
                                    <?php if (isset($row['days_left'])): ?>
                                        (<?= $row['days_left'] ?> days)
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (isset($row['status']) && $row['status'] === 'Expired'): ?>
                                        <button class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#disposeModal" 
                                            data-id="<?= $row['id'] ?>" 
                                            data-name="<?= isset($row['item_name']) ? htmlspecialchars($row['item_name']) : '' ?>" 
                                            data-batch="<?= isset($row['batch_no']) ? htmlspecialchars($row['batch_no']) : '' ?>"
                                            data-qty="<?= $row['quantity'] ?>">Dispose</button>
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
                            <th>Batch No</th>
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
                                <td><?= htmlspecialchars($row['batch_no']) ?></td>
                                <td><?= htmlspecialchars($row['item_name']) ?></td>
                                <td><?= $row['quantity'] ?> pcs</td>
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
        <p class="text-muted small">Batch: <span id="batchNo"></span></p>
        <div class="mb-2">
            <label for="disposeQty" class="form-label">Quantity to Dispose:</label>
            <input type="number" min="1" class="form-control" id="disposeQty" name="dispose_qty">
        </div>
      </div>
      <div class="modal-footer">
        <form method="post" id="disposeForm">
            <input type="hidden" name="dispose_id" id="disposeId">
            <input type="hidden" name="dispose_qty" id="disposeQtyHidden">
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
    var batchNo = button.getAttribute('data-batch');
    var qty = button.getAttribute('data-qty');

    document.getElementById('disposeId').value = itemId;
    document.getElementById('medicineName').textContent = itemName;
    document.getElementById('batchNo').textContent = batchNo;
    document.getElementById('disposeQty').value = qty;
    document.getElementById('disposeQty').max = qty;
});

document.getElementById('disposeForm').addEventListener('submit', function(e) {
    var qty = document.getElementById('disposeQty').value;
    document.getElementById('disposeQtyHidden').value = qty;
});
</script>
</body>
</html>