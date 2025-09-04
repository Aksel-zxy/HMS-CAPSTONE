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

// Fetch medicines
$stmt = $pdo->prepare("SELECT * FROM inventory WHERE item_type = 'Medications and pharmacy supplies' ORDER BY id ASC");
$stmt->execute();
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
            $expired[] = $row;
        } elseif ($diff <= 7) {
            $row['row_class'] = "table-warning";
            $seven_days_alert[] = $row;
            $near_expiry[] = $row;
        } elseif ($diff <= 30) {
            $row['row_class'] = "table-warning";
            $near_expiry[] = $row;
        } else {
            $row['row_class'] = "table-success";
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
    <link rel="stylesheet" href="assets/css/Batch&Expiry.css">
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
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="new-tab" data-bs-toggle="tab" data-bs-target="#newDelivered" type="button" role="tab">
                     New Delivered
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="stock-tab" data-bs-toggle="tab" data-bs-target="#medicineStocks" type="button" role="tab">
                     Medicine Stocks
                </button>
            </li>
        </ul>

        <div class="tab-content border p-3 bg-white rounded-bottom" id="medicineTabsContent">

            <!-- New Delivered -->
            <div class="tab-pane fade" id="newDelivered" role="tabpanel">
                <?php if (empty($new_delivered)): ?>
                    <p class="text-muted">No newly delivered medicines.</p>
                <?php else: ?>
                    <div class="table-responsive">
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
                    </div>
                <?php endif; ?>
            </div>

            <!-- Medicine Stocks -->
            <div class="tab-pane fade show active" id="medicineStocks" role="tabpanel">
                <!-- Dropdown filter -->
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5> Medicine Stocks</h5>
                    <div class="dropdown">
                        <button class="btn btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                            Filter Medicines
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="#" onclick="showTable('all')"> All</a></li>
                            <li><a class="dropdown-item" href="#" onclick="showTable('expired')">Expired</a></li>
                            <li><a class="dropdown-item" href="#" onclick="showTable('nearExpiry')"> Near Expiry (≤30 days)</a></li>
                            <li><a class="dropdown-item" href="#" onclick="showTable('safe')"> Safe</a></li>
                        </ul>
                    </div>
                </div>

                <!-- All -->
                <div id="all" class="stock-table">
                    <h6> All Medicines</h6>
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
                                        <td>
                                            <?php if ($row['row_class'] == 'table-danger') echo " Expired"; ?>
                                            <?php if ($row['row_class'] == 'table-warning') echo " Near Expiry"; ?>
                                            <?php if ($row['row_class'] == 'table-success') echo " Safe"; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>

                <!-- Expired -->
                <div id="expired" class="stock-table" style="display:none;">
                    <h6> Expired Medicines</h6>
                    <?php if (empty($expired)): ?>
                        <p class="text-muted">No expired medicines.</p>
                    <?php else: ?>
                        <table class="table table-bordered">
                            <thead class="table-light">
                                <tr>
                                    <th>ID</th>
                                    <th>Item Name</th>
                                    <th>Quantity</th>
                                    <th>Price</th>
                                    <th>Expiration Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($expired as $row): ?>
                                    <tr class="<?= $row['row_class'] ?>">
                                        <td><?= $row['id'] ?></td>
                                        <td><?= htmlspecialchars($row['item_name']) ?></td>
                                        <td><?= $row['quantity'] ?></td>
                                        <td><?= number_format($row['price'], 2) ?></td>
                                        <td><?= $row['expiration_date'] ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>

                <!-- Near Expiry -->
                <div id="nearExpiry" class="stock-table" style="display:none;">
                    <h6> Near Expiry (≤30 days)</h6>
                    <?php if (empty($near_expiry)): ?>
                        <p class="text-muted">No near-expiry medicines.</p>
                    <?php else: ?>
                        <table class="table table-bordered">
                            <thead class="table-light">
                                <tr>
                                    <th>ID</th>
                                    <th>Item Name</th>
                                    <th>Quantity</th>
                                    <th>Price</th>
                                    <th>Expiration Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($near_expiry as $row): ?>
                                    <tr class="<?= $row['row_class'] ?>">
                                        <td><?= $row['id'] ?></td>
                                        <td><?= htmlspecialchars($row['item_name']) ?></td>
                                        <td><?= $row['quantity'] ?></td>
                                        <td><?= number_format($row['price'], 2) ?></td>
                                        <td><?= $row['expiration_date'] ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>

                <!-- Safe -->
                <div id="safe" class="stock-table" style="display:none;">
                    <h6> Safe Medicines</h6>
                    <?php if (empty($safe)): ?>
                        <p class="text-muted">No safe medicines.</p>
                    <?php else: ?>
                        <table class="table table-bordered">
                            <thead class="table-light">
                                <tr>
                                    <th>ID</th>
                                    <th>Item Name</th>
                                    <th>Quantity</th>
                                    <th>Price</th>
                                    <th>Expiration Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($safe as $row): ?>
                                    <tr class="<?= $row['row_class'] ?>">
                                        <td><?= $row['id'] ?></td>
                                        <td><?= htmlspecialchars($row['item_name']) ?></td>
                                        <td><?= $row['quantity'] ?></td>
                                        <td><?= number_format($row['price'], 2) ?></td>
                                        <td><?= $row['expiration_date'] ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function showTable(tableId) {
            document.querySelectorAll('.stock-table').forEach(el => el.style.display = 'none');
            document.getElementById(tableId).style.display = 'block';
        }

        // Show "All" by default when opening Medicine Stocks
        document.addEventListener("DOMContentLoaded", function() {
            showTable("all");
        });
    </script>
</body>
</html>
