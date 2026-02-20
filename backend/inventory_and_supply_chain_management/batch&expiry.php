<?php 
include '../../SQL/config.php';

/*
    REQUIRED SQL (run once):
    ALTER TABLE medicine_batches ADD COLUMN has_expiry TINYINT DEFAULT 1;
*/

// ===============================
// Handle setting expiry for new delivered items
// ===============================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['set_expiry_submit'])) {
    $request_item_id = intval($_POST['request_item_id']);
    $has_expiry      = isset($_POST['has_expiry']) ? 1 : 0;
    $expiry_date     = ($has_expiry && !empty($_POST['expiry_date'])) ? $_POST['expiry_date'] : null;

    $stmt = $pdo->prepare("
        SELECT di.*, dr.id AS req_id
        FROM department_request_items di
        JOIN department_request dr ON di.request_id = dr.id
        WHERE di.id = ? AND dr.status = 'Completed'
    ");
    $stmt->execute([$request_item_id]);
    $item = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($item) {
        $stmt2 = $pdo->prepare("SELECT id FROM vendor_products WHERE item_name LIKE ? LIMIT 1");
        $stmt2->execute(['%' . $item['item_name'] . '%']);
        $vp      = $stmt2->fetch(PDO::FETCH_ASSOC);
        $item_id = $vp ? $vp['id'] : 0;

        $batch_no  = 'DRI-' . $item['id'];
        $total_pcs = intval($item['received_quantity']) * intval($item['pcs_per_box']);
        if ($total_pcs <= 0) $total_pcs = intval($item['received_quantity']);

        $stmt3 = $pdo->prepare("
            INSERT INTO medicine_batches (item_id, batch_no, quantity, expiration_date, has_expiry)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt3->execute([$item_id, $batch_no, $total_pcs, $expiry_date, $has_expiry]);
    }

    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// ===============================
// Handle expiry update for existing batches
// ===============================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['batch_id'], $_POST['expiry_date'])) {
    $batch_id    = intval($_POST['batch_id']);
    $expiry_date = $_POST['expiry_date'];

    $stmt = $pdo->prepare("UPDATE medicine_batches SET expiration_date = ? WHERE id = ?");
    $stmt->execute([$expiry_date, $batch_id]);

    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// ===============================
// Handle disposal of expired meds
// ===============================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['dispose_id'], $_POST['dispose_qty'])) {
    $dispose_id  = intval($_POST['dispose_id']);
    $dispose_qty = intval($_POST['dispose_qty']);

    $stmt = $pdo->prepare("
        SELECT mb.*, vp.item_name, vp.price
        FROM medicine_batches mb
        LEFT JOIN vendor_products vp ON mb.item_id = vp.id
        WHERE mb.id = ?
    ");
    $stmt->execute([$dispose_id]);
    $batch = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($batch && $dispose_qty > 0 && $dispose_qty <= $batch['quantity']) {
        $price     = $batch['price']     ?? 0;
        $item_name = $batch['item_name'] ?? 'Unknown';

        $stmt = $pdo->prepare("INSERT INTO disposed_medicines 
            (batch_id, batch_no, item_id, item_name, quantity, price, expiration_date) 
            VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $batch['id'],
            $batch['batch_no'],
            $batch['item_id'],
            $item_name,
            $dispose_qty,
            $price,
            $batch['expiration_date']
        ]);

        $new_qty = $batch['quantity'] - $dispose_qty;
        if ($new_qty > 0) {
            $stmt = $pdo->prepare("UPDATE medicine_batches SET quantity = ? WHERE id = ?");
            $stmt->execute([$new_qty, $batch['id']]);
        } else {
            $stmt = $pdo->prepare("DELETE FROM medicine_batches WHERE id = ?");
            $stmt->execute([$batch['id']]);
        }
    }

    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// ===============================
// Fetch NEW DELIVERED items
// ===============================
$stmt = $pdo->prepare("
    SELECT di.*, dr.delivered_at, dr.id AS request_id
    FROM department_request_items di
    JOIN department_request dr ON di.request_id = dr.id
    WHERE dr.status = 'Completed'
      AND NOT EXISTS (
          SELECT 1 FROM medicine_batches mb
          WHERE mb.batch_no = CONCAT('DRI-', di.id)
      )
    ORDER BY di.id ASC
");
$stmt->execute();
$new_delivered = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ===============================
// Fetch inventory
// ===============================
$stmt = $pdo->prepare("
    SELECT 
        mb.id            AS batch_id,
        mb.item_id,
        COALESCE(vp.item_name, mb.batch_no) AS item_name,
        vp.price,
        vp.unit_type,
        vp.pcs_per_box,
        mb.quantity,
        mb.batch_no,
        mb.expiration_date,
        mb.has_expiry
    FROM medicine_batches mb
    LEFT JOIN vendor_products vp ON mb.item_id = vp.id
    WHERE mb.expiration_date IS NOT NULL
       OR mb.has_expiry = 0
    ORDER BY COALESCE(vp.item_name, mb.batch_no), mb.expiration_date
");
$stmt->execute();
$inventory_rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ===============================
// Categorize stock by expiry
// ===============================
$expired          = [];
$near_expiry      = [];
$safe             = [];
$no_expiry        = [];
$seven_days_alert = [];

foreach ($inventory_rows as $row) {
    if (!$row['has_expiry']) {
        $row['row_class'] = "table-info";
        $row['status']    = "No Expiry";
        $no_expiry[]      = $row;
        continue;
    }

    $today    = new DateTime();
    $expiry   = new DateTime($row['expiration_date']);
    $diffDays = (int)$today->diff($expiry)->format("%r%a");

    if ($expiry < $today) {
        $row['row_class']   = "table-danger";
        $row['status']      = "Expired";
        $expired[]          = $row;
        $seven_days_alert[] = $row;
    } elseif ($diffDays <= 7) {
        $row['row_class']   = "table-warning";
        $row['status']      = "Near Expiry";
        $row['days_left']   = $diffDays;
        $near_expiry[]      = $row;
        $seven_days_alert[] = $row;
    } elseif ($diffDays <= 30) {
        $row['row_class']   = "table-warning";
        $row['status']      = "Near Expiry";
        $row['days_left']   = $diffDays;
        $near_expiry[]      = $row;
    } else {
        $row['row_class']   = "table-success";
        $row['status']      = "Safe";
        $safe[]             = $row;
    }
}

$all_stocks = array_merge($expired, $near_expiry, $safe, $no_expiry);

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
    <title>Batch &amp; Expiry Tracking - Medicines</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .badge-no-expiry { background-color: #0dcaf0; color: #000; }
        .badge-expired   { background-color: #dc3545; color: #fff; }
        .badge-near      { background-color: #ffc107; color: #000; }
        .badge-safe      { background-color: #198754; color: #fff; }
        #stockFilter     { min-width: 210px; }
    </style>
</head>
<body class="bg-light p-4">

<div class="main-sidebar">
    <?php include 'inventory_sidebar.php'; ?>
</div>

<div class="container">
    <h2 class="mb-4">Batch &amp; Expiry Tracking - Medicines</h2>

    <!-- Expiry Alerts -->
    <?php if (!empty($seven_days_alert)): ?>
        <div class="alert alert-warning alert-dismissible fade show">
            <h6><strong>⚠️ Expiry Alerts</strong></h6>
            <ul class="mb-0">
                <?php foreach ($seven_days_alert as $item): ?>
                    <?php
                        $today    = new DateTime();
                        $expiry   = new DateTime($item['expiration_date']);
                        $diffDays = (int)$today->diff($expiry)->format("%r%a");
                        $msg = $expiry < $today
                            ? htmlspecialchars($item['item_name']) . " is already <strong>Expired</strong>!"
                            : htmlspecialchars($item['item_name']) . " will expire in <strong>{$diffDays} day(s)</strong>!";
                    ?>
                    <li><?= $msg ?></li>
                <?php endforeach; ?>
            </ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Tabs -->
    <ul class="nav nav-tabs mb-3">
        <li class="nav-item">
            <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#newDelivered">
                New Delivered
                <?php if (!empty($new_delivered)): ?>
                    <span class="badge bg-danger ms-1"><?= count($new_delivered) ?></span>
                <?php endif; ?>
            </button>
        </li>
        <li class="nav-item">
            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#medicineStocks">Stocks</button>
        </li>
        <li class="nav-item">
            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#disposedMedicines">Disposed Medicines</button>
        </li>
    </ul>

    <div class="tab-content border p-3 bg-white rounded-bottom">

        <!-- ===========================
             NEW DELIVERED TAB
        ============================ -->
        <div class="tab-pane fade show active" id="newDelivered">
            <h5>New Delivered Items — Set Expiration</h5>
            <p class="text-muted small">
                These are items from <strong>Completed</strong> department requests not yet processed.
                Toggle whether the item has an expiry date, then click <em>Set &amp; Save</em>.
            </p>

            <?php if (empty($new_delivered)): ?>
                <div class="alert alert-info mb-0">No new delivered items pending expiry setup.</div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-bordered align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>#</th>
                                <th>Item Name</th>
                                <th>Description</th>
                                <th>Received Qty (boxes)</th>
                                <th>Pcs / Box</th>
                                <th>Total Pcs</th>
                                <th>Has Expiry?</th>
                                <th>Expiration Date</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $ndNum = 1; foreach ($new_delivered as $nd):
                                $total_pcs = intval($nd['received_quantity']) * intval($nd['pcs_per_box']);
                                if ($total_pcs <= 0) $total_pcs = intval($nd['received_quantity']);
                            ?>
                            <tr>
                                <form method="post">
                                    <input type="hidden" name="set_expiry_submit" value="1">
                                    <input type="hidden" name="request_item_id" value="<?= $nd['id'] ?>">

                                    <td><?= $ndNum++ ?></td>
                                    <td><?= htmlspecialchars($nd['item_name']) ?></td>
                                    <td><?= htmlspecialchars($nd['description'] ?? '—') ?></td>
                                    <td><?= intval($nd['received_quantity']) ?></td>
                                    <td><?= intval($nd['pcs_per_box']) ?></td>
                                    <td><?= $total_pcs ?></td>

                                    <!-- Has Expiry Toggle -->
                                    <td class="text-center">
                                        <div class="form-check form-switch d-inline-block">
                                            <input
                                                class="form-check-input"
                                                type="checkbox"
                                                name="has_expiry"
                                                id="hasExpiry_<?= $nd['id'] ?>"
                                                checked
                                                onchange="toggleExpiryField(<?= $nd['id'] ?>, this.checked)"
                                            >
                                            <label class="form-check-label" for="hasExpiry_<?= $nd['id'] ?>">
                                                <span id="expiryLabel_<?= $nd['id'] ?>">Yes</span>
                                            </label>
                                        </div>
                                    </td>

                                    <!-- Expiry Date Field -->
                                    <td>
                                        <div id="expiryField_<?= $nd['id'] ?>">
                                            <input
                                                type="date"
                                                name="expiry_date"
                                                class="form-control form-control-sm"
                                                min="<?= date('Y-m-d') ?>"
                                                required
                                            >
                                        </div>
                                        <div id="noExpiryMsg_<?= $nd['id'] ?>" class="d-none text-muted fst-italic small pt-1">
                                            No expiration date
                                        </div>
                                    </td>

                                    <!-- Submit -->
                                    <td>
                                        <button type="submit" class="btn btn-sm btn-primary">
                                            ✔ Set &amp; Save
                                        </button>
                                    </td>
                                </form>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- ===========================
             MEDICINE STOCKS TAB
        ============================ -->
        <div class="tab-pane fade" id="medicineStocks">

            <!-- Header + Filter Dropdown -->
            <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
                <h5 class="mb-0">Stocks</h5>
                <div class="d-flex align-items-center gap-2">
                    <label for="stockFilter" class="form-label mb-0 fw-semibold text-nowrap">
                        🔽 Filter by:
                    </label>
                    <select id="stockFilter" class="form-select form-select-sm" onchange="filterStocks(this.value)">
                        <option value="all">— All Items —</option>
                        <optgroup label="Expiry Type">
                            <option value="with_expiry">✅ With Expiry Date</option>
                            <option value="no_expiry">🚫 No Expiry Date</option>
                        </optgroup>
                        <optgroup label="Expiry Status">
                            <option value="expired">🔴 Expired Only</option>
                            <option value="near_expiry">🟡 Near Expiry Only</option>
                            <option value="safe">🟢 Safe Only</option>
                        </optgroup>
                    </select>
                </div>
            </div>

            <!-- Summary Badges -->
            <div class="mb-3 d-flex flex-wrap gap-2">
                <span class="badge rounded-pill bg-secondary fs-6 px-3 py-2">
                    All: <?= count($all_stocks) ?>
                </span>
                <span class="badge rounded-pill bg-danger fs-6 px-3 py-2">
                    Expired: <?= count($expired) ?>
                </span>
                <span class="badge rounded-pill bg-warning text-dark fs-6 px-3 py-2">
                    Near Expiry: <?= count($near_expiry) ?>
                </span>
                <span class="badge rounded-pill bg-success fs-6 px-3 py-2">
                    Safe: <?= count($safe) ?>
                </span>
                <span class="badge rounded-pill bg-info text-dark fs-6 px-3 py-2">
                    No Expiry: <?= count($no_expiry) ?>
                </span>
            </div>

            <div class="table-responsive">
                <table class="table table-bordered" id="stocksTable">
                    <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th>Item Name</th>
                            <th>Quantity (pcs)</th>
                            <th>Batch No</th>
                            <th>Expiration Date</th>
                            <th>Expiry Type</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $rowNum = 1; foreach ($all_stocks as $row): ?>
                            <?php
                                // Expiry Type badge + filter tag
                                if ($row['status'] === 'No Expiry') {
                                    $filterExpiryTag = 'no_expiry';
                                    $expiryTypeBadge = '<span class="badge badge-no-expiry">🚫 No Expiry Date</span>';
                                } else {
                                    $filterExpiryTag = 'with_expiry';
                                    $expiryTypeBadge = '<span class="badge bg-primary">✅ With Expiry Date</span>';
                                }

                                // Status badge
                                switch ($row['status']) {
                                    case 'Expired':
                                        $statusBadge   = '<span class="badge badge-expired">🔴 Expired</span>';
                                        $filterStatus  = 'expired';
                                        break;
                                    case 'Near Expiry':
                                        $statusBadge   = '<span class="badge badge-near">🟡 Near Expiry</span>';
                                        $filterStatus  = 'near_expiry';
                                        break;
                                    case 'Safe':
                                        $statusBadge   = '<span class="badge badge-safe">🟢 Safe</span>';
                                        $filterStatus  = 'safe';
                                        break;
                                    default: // No Expiry
                                        $statusBadge   = '<span class="badge badge-no-expiry">🚫 No Expiry</span>';
                                        $filterStatus  = 'no_expiry';
                                }
                            ?>
                            <tr
                                class="stock-row <?= $row['row_class'] ?>"
                                data-filter-expiry="<?= $filterExpiryTag ?>"
                                data-filter-status="<?= $filterStatus ?>"
                            >
                                <td><?= $rowNum++ ?></td>
                                <td><?= htmlspecialchars($row['item_name']) ?></td>
                                <td><?= $row['quantity'] ?></td>
                                <td><?= htmlspecialchars($row['batch_no']) ?></td>
                                <td>
                                    <?php if ($row['status'] === 'No Expiry'): ?>
                                        <span class="text-muted fst-italic">—</span>
                                    <?php else: ?>
                                        <?= htmlspecialchars($row['expiration_date']) ?>
                                        <?php if (!empty($row['days_left'])): ?>
                                            <br><small class="text-muted"><?= $row['days_left'] ?> day(s) left</small>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </td>
                                <td><?= $expiryTypeBadge ?></td>
                                <td><?= $statusBadge ?></td>
                                <td>
                                    <?php if ($row['status'] === 'Expired'): ?>
                                        <button
                                            class="btn btn-sm btn-danger"
                                            data-bs-toggle="modal"
                                            data-bs-target="#disposeModal"
                                            data-id="<?= $row['batch_id'] ?>"
                                            data-name="<?= htmlspecialchars($row['item_name']) ?>"
                                            data-qty="<?= $row['quantity'] ?>">
                                            Dispose
                                        </button>
                                    <?php else: ?>
                                        <span class="text-muted">—</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>

                        <?php if (empty($all_stocks)): ?>
                            <tr>
                                <td colspan="8" class="text-center text-muted py-3">No stock records found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- No results message -->
            <div id="noFilterResults" class="alert alert-info d-none mt-2">
                No items match the selected filter.
            </div>

        </div>

        <!-- ===========================
             DISPOSED MEDICINES TAB
        ============================ -->
        <div class="tab-pane fade" id="disposedMedicines">
            <h5>Disposed Medicines</h5>
            <?php if (empty($disposed)): ?>
                <p class="text-muted">No disposed medicines.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-bordered">
                        <thead class="table-light">
                            <tr>
                                <th>#</th>
                                <th>Batch ID</th>
                                <th>Item Name</th>
                                <th>Quantity</th>
                                <th>Price</th>
                                <th>Expiration Date</th>
                                <th>Disposed At</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $i = 1; foreach ($disposed as $row): ?>
                                <tr class="table-secondary">
                                    <td><?= $i++ ?></td>
                                    <td><?= $row['batch_id'] ?></td>
                                    <td><?= htmlspecialchars($row['item_name']) ?></td>
                                    <td><?= $row['quantity'] ?></td>
                                    <td>₱<?= number_format($row['price'], 2) ?></td>
                                    <td><?= htmlspecialchars($row['expiration_date'] ?? 'N/A') ?></td>
                                    <td><?= $row['disposed_at'] ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

    </div><!-- end tab-content -->
</div><!-- end container -->

<!-- Dispose Modal -->
<div class="modal fade" id="disposeModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form method="post">
        <div class="modal-header bg-danger text-white">
            <h5 class="modal-title">Confirm Disposal</h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
            <p>Are you sure you want to dispose <strong id="medicineName"></strong>?</p>
            <div class="mb-2">
                <label for="disposeQty" class="form-label">Quantity to Dispose:</label>
                <input type="number" min="1" class="form-control" id="disposeQty" name="dispose_qty" required>
            </div>
        </div>
        <div class="modal-footer">
            <input type="hidden" name="dispose_id" id="disposeId">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-danger">Dispose</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
// ============================================
// Toggle expiry date field (New Delivered tab)
// ============================================
function toggleExpiryField(itemId, hasExpiry) {
    const fieldDiv  = document.getElementById('expiryField_'  + itemId);
    const msgDiv    = document.getElementById('noExpiryMsg_'  + itemId);
    const labelSpan = document.getElementById('expiryLabel_'  + itemId);
    const dateInput = fieldDiv.querySelector('input[type="date"]');

    if (hasExpiry) {
        fieldDiv.classList.remove('d-none');
        msgDiv.classList.add('d-none');
        labelSpan.textContent = 'Yes';
        dateInput.required    = true;
    } else {
        fieldDiv.classList.add('d-none');
        msgDiv.classList.remove('d-none');
        labelSpan.textContent = 'No';
        dateInput.required    = false;
        dateInput.value       = '';
    }
}

// ============================================
// Filter stocks table
// ============================================
function filterStocks(value) {
    const rows      = document.querySelectorAll('#stocksTable .stock-row');
    const noResults = document.getElementById('noFilterResults');
    let   visible   = 0;

    rows.forEach(function(row) {
        const expiryType = row.getAttribute('data-filter-expiry'); // with_expiry | no_expiry
        const status     = row.getAttribute('data-filter-status'); // expired | near_expiry | safe | no_expiry

        let show = false;
        switch (value) {
            case 'all':         show = true;                          break;
            case 'with_expiry': show = expiryType === 'with_expiry'; break;
            case 'no_expiry':   show = expiryType === 'no_expiry';   break;
            case 'expired':     show = status     === 'expired';     break;
            case 'near_expiry': show = status     === 'near_expiry'; break;
            case 'safe':        show = status     === 'safe';        break;
            default:            show = true;
        }

        row.style.display = show ? '' : 'none';
        if (show) visible++;
    });

    noResults.classList.toggle('d-none', visible > 0);
}

// ============================================
// Dispose Modal population
// ============================================
document.getElementById('disposeModal').addEventListener('show.bs.modal', function (event) {
    const button   = event.relatedTarget;
    const batchId  = button.getAttribute('data-id');
    const itemName = button.getAttribute('data-name');
    const qty      = button.getAttribute('data-qty');

    document.getElementById('disposeId').value          = batchId;
    document.getElementById('medicineName').textContent = itemName;
    document.getElementById('disposeQty').value         = qty;
    document.getElementById('disposeQty').max           = qty;
});
</script>

</body>
</html>