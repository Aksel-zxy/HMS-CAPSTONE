<?php
include 'db.php';

// Handle expiry update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['item_id'], $_POST['expiry_date'])) {
    $item_id = intval($_POST['item_id']);
    $expiry_date = $_POST['expiry_date'];

    $stmt = $pdo->prepare("UPDATE inventory SET expiration_date = ? WHERE id = ?");
    $stmt->execute([$expiry_date, $item_id]);

    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// Handle dispose expired
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['dispose_id'])) {
    $dispose_id = intval($_POST['dispose_id']);

    // Fetch expired item
    $stmt = $pdo->prepare("SELECT * FROM inventory WHERE id = ?");
    $stmt->execute([$dispose_id]);
    $item = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($item) {
        // Insert into disposed_medicines
        $stmt = $pdo->prepare("INSERT INTO disposed_medicines (batch_id, item_id, item_name, quantity, price, expiration_date) 
                               VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $item['id'],
            $item['item_id'],
            $item['item_name'],
            $item['quantity'],
            $item['price'],
            $item['expiration_date']
        ]);

        // Delete from inventory
        $stmt = $pdo->prepare("DELETE FROM inventory WHERE id = ?");
        $stmt->execute([$dispose_id]);
    }

    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// Fetch inventory medicines
$stmt = $pdo->prepare("SELECT * FROM inventory WHERE item_type = 'Medications and pharmacy supplies' ORDER BY id ASC");
$stmt->execute();
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch disposed medicines
$stmt = $pdo->prepare("SELECT * FROM disposed_medicines ORDER BY disposed_at DESC");
$stmt->execute();
$disposed = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Categorize
$new_delivered = [];
$expired = [];
$near_expiry = [];
$safe = [];
$seven_days_alert = [];

foreach ($rows as $row) {
    if (empty($row['expiration_date'])) {
        $new_delivered[] = $row;
    } else {
        $today = new DateTime();
        $expiry = new DateTime($row['expiration_date']);
        $diff = $today->diff($expiry)->days;

        if ($expiry < $today) {
            $row['row_class'] = "table-danger";
            $row['status'] = "Expired";
            $expired[] = $row;
        } elseif ($diff <= 7) {
            $row['row_class'] = "table-warning";
            $row['status'] = "Near Expiry";
            $seven_days_alert[] = $row;
            $near_expiry[] = $row;
        } elseif ($diff <= 30) {
            $row['row_class'] = "table-warning";
            $row['status'] = "Near Expiry";
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
            Some medicines will expire within 7 days!
        </div>
    <?php endif; ?>

    <!-- Tabs -->
    <ul class="nav nav-tabs mb-3" id="medicineTabs" role="tablist">
        <li class="nav-item">
            <button class="nav-link" id="new-tab" data-bs-toggle="tab" data-bs-target="#newDelivered" type="button" role="tab">New Delivered</button>
        </li>
        <li class="nav-item">
            <button class="nav-link active" id="stock-tab" data-bs-toggle="tab" data-bs-target="#medicineStocks" type="button" role="tab">Medicine Stocks</button>
        </li>
        <li class="nav-item">
            <button class="nav-link" id="disposed-tab" data-bs-toggle="tab" data-bs-target="#disposedMedicines" type="button" role="tab">Disposed Medicines</button>
        </li>
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
                                        <input type="hidden" name="item_id" value="<?= $row['id'] ?>">
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
                                        <!-- Button trigger modal -->
                                        <button class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#disposeModal" data-id="<?= $row['id'] ?>" data-name="<?= htmlspecialchars($row['item_name']) ?>">
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
                            <th>Item ID</th>
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
                                <td><?= $row['item_id'] ?></td>
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
      </div>
      <div class="modal-footer">
        <form method="post" id="disposeForm">
            <input type="hidden" name="dispose_id" id="disposeId">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-danger">Yes, Dispose</button>
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

        document.getElementById('disposeId').value = itemId;
        document.getElementById('medicineName').textContent = itemName;
    });
</script>
</body>
</html>
