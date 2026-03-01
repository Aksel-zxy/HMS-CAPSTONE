<?php
session_start();
include '../../SQL/config.php';
include 'classes/sales.php';
require_once "classes/notification.php";


if (!isset($_SESSION['pharmacy']) || $_SESSION['pharmacy'] !== true) {
    header('Location: login.php');
    exit();
}

if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    echo "User ID is not set in session.";
    exit();
}

$query = "SELECT * FROM users WHERE user_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if (!$user) {
    echo "No user found.";
    exit();
}



// 🔔 Pending prescriptions count
$notif_sql = "SELECT COUNT(*) AS pending 
              FROM pharmacy_prescription 
              WHERE status = 'Pending'";
$notif_res = $conn->query($notif_sql);

$pendingCount = 0;
if ($notif_res && $notif_res->num_rows > 0) {
    $notif_row = $notif_res->fetch_assoc();
    $pendingCount = $notif_row['pending'];
}

// 🔴 Expiry (Near Expiry or Expired) count
$expiry_sql = "SELECT COUNT(*) AS expiry 
               FROM pharmacy_stock_batches 
               WHERE expiry_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)";
$expiry_res = $conn->query($expiry_sql);

$expiryCount = 0;
if ($expiry_res && $expiry_res->num_rows > 0) {
    $expiry_row = $expiry_res->fetch_assoc();
    $expiryCount = $expiry_row['expiry'];
}

$notif = new Notification($conn);
$latestNotifications = $notif->load();
$notifCount = $notif->notifCount;


// Handle OTC Sale Submission (AJAX)
if (isset($_POST['submit_otc'])) {
    header('Content-Type: application/json');

    $customer_name = !empty($_POST['customer_name']) ? $_POST['customer_name'] : 'Walk-in';
    $generic_name = $_POST['generic_name'] ?? '';
    $brand_name = $_POST['brand_name'] ?? '';
    $dosage = $_POST['dosage'] ?? '';
    $quantity_needed = max(0, intval($_POST['quantity'] ?? 0));
    $unit_price = max(0, floatval($_POST['unit_price'] ?? 0));
    $payment_method = $_POST['payment_method'] ?? '';
    $staff_name = $user['fname'] . ' ' . $user['lname'];

    if (!$generic_name || !$brand_name || !$dosage || $quantity_needed <= 0 || $unit_price <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid form data.']);
        exit;
    }

    // Get Medicine ID
    $stmt = $conn->prepare("SELECT med_id FROM pharmacy_inventory WHERE generic_name=? AND brand_name=? AND dosage=? LIMIT 1");
    $stmt->bind_param("sss", $generic_name, $brand_name, $dosage);
    $stmt->execute();
    $med = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$med) {
        echo json_encode(['status' => 'error', 'message' => 'Medicine not found']);
        exit;
    }

    $med_id = $med['med_id'];
    $med_name = $generic_name . ' ' . $brand_name . ' ' . $dosage; // <-- Properly define med_name
    $today = date('Y-m-d');

    $conn->begin_transaction();
    try {
        // Fetch batches (FIFO)
        $stockQuery = $conn->prepare("
            SELECT batch_id, stock_quantity 
            FROM pharmacy_stock_batches 
            WHERE med_id=? AND expiry_date>=? 
            ORDER BY expiry_date ASC
        ");
        $stockQuery->bind_param("is", $med_id, $today);
        $stockQuery->execute();
        $batches = $stockQuery->get_result()->fetch_all(MYSQLI_ASSOC);
        $stockQuery->close();

        $total_available = array_sum(array_column($batches, 'stock_quantity'));
        if ($total_available < $quantity_needed) throw new Exception("Not enough stock. Available: $total_available");

        // Insert sale
        $total_price = $unit_price * $quantity_needed;
        $insertSale = $conn->prepare("
            INSERT INTO pharmacy_sales 
            (customer_name, med_name, quantity_sold, price_per_unit, total_price, sale_date, payment_method, staff_name, transaction_type) 
            VALUES (?,?,?,?,?,NOW(),?,?, 'OTC')
        ");
        $insertSale->bind_param(
            "ssidsss",
            $customer_name,
            $med_name,
            $quantity_needed,
            $unit_price,
            $total_price,
            $payment_method,
            $staff_name
        );
        $insertSale->execute();
        $insertSale->close();

        // Deduct stock from batches
        $remaining_qty = $quantity_needed;
        foreach ($batches as $batch) {
            if ($remaining_qty <= 0) break;
            $deduct = min($remaining_qty, $batch['stock_quantity']);
            $updateBatch = $conn->prepare("
                UPDATE pharmacy_stock_batches 
                SET stock_quantity = stock_quantity - ? 
                WHERE batch_id = ?
            ");
            $updateBatch->bind_param("ii", $deduct, $batch['batch_id']);
            $updateBatch->execute();
            $updateBatch->close();
            $remaining_qty -= $deduct;
        }

        // Deduct from main inventory
        $updateInventory = $conn->prepare("
            UPDATE pharmacy_inventory 
            SET stock_quantity = stock_quantity - ? 
            WHERE med_id = ?
        ");
        $updateInventory->bind_param("ii", $quantity_needed, $med_id);
        $updateInventory->execute();
        $updateInventory->close();

        $conn->commit();
        echo json_encode(['status' => 'success', 'message' => 'OTC sale recorded successfully']);
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit();
}
?>





<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HMS | Pharmacy Management</title>
    <link rel="shortcut icon" href="assets/image/favicon.ico" type="image/x-icon">
    <link rel="stylesheet" href="assets/CSS/bootstrap.min.css">
    <link rel="stylesheet" href="assets/CSS/super.css">
    <link rel="stylesheet" href="assets/CSS/prescription.css">
    <link rel="stylesheet" href="assets/CSS/med_inventory.css">
</head>

<body>
    <div class="d-flex">
        <!----- Sidebar ----->
        <aside id="sidebar" class="sidebar-toggle">

            <div class="sidebar-logo mt-3">
                <img src="assets/image/logo-dark.png" width="90px" height="20px">
            </div>

            <div class="menu-title">Pharmacy Management | Dashboard</div>

            <!----- Sidebar Navigation ----->

            <li class="sidebar-item">
                <a href="pharmacy_dashboard.php" class="sidebar-link" data-bs-toggle="#" data-bs-target="#"
                    aria-expanded="false" aria-controls="auth">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-cast" viewBox="0 0 16 16">
                        <path d="m7.646 9.354-3.792 3.792a.5.5 0 0 0 .353.854h7.586a.5.5 0 0 0 .354-.854L8.354 9.354a.5.5 0 0 0-.708 0" />
                        <path d="M11.414 11H14.5a.5.5 0 0 0 .5-.5v-7a.5.5 0 0 0-.5-.5h-13a.5.5 0 0 0-.5.5v7a.5.5 0 0 0 .5.5h3.086l-1 1H1.5A1.5 1.5 0 0 1 0 10.5v-7A1.5 1.5 0 0 1 1.5 2h13A1.5 1.5 0 0 1 16 3.5v7a1.5 1.5 0 0 1-1.5 1.5h-2.086z" />
                    </svg>
                    <span style="font-size: 18px;">Dashboard</span>
                </a>
            </li>

            <li class="sidebar-item">
                <a href="pharmacy_med_inventory.php" class="sidebar-link" data-bs-toggle="#" data-bs-target="#"
                    aria-expanded="false" aria-controls="auth">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="fa-solid fa-capsules" viewBox="0 0 16 16">
                        <path d="m7.646 9.354-3.792 3.792a.5.5 0 0 0 .353.854h7.586a.5.5 0 0 0 .354-.854L8.354 9.354a.5.5 0 0 0-.708 0" />
                        <path d="M11.414 11H14.5a.5.5 0 0 0 .5-.5v-7a.5.5 0 0 0-.5-.5h-13a.5.5 0 0 0-.5.5v7a.5.5 0 0 0 .5.5h3.086l-1 1H1.5A1.5 1.5 0 0 1 0 10.5v-7A1.5 1.5 0 0 1 1.5 2h13A1.5 1.5 0 0 1 16 3.5v7a1.5 1.5 0 0 1-1.5 1.5h-2.086z" />
                    </svg>
                    <span style="font-size: 18px;">Medicine Inventory</span>
                </a>
            </li>

            <li class="sidebar-item">
                <a href="pharmacy_search_locate.php" class="sidebar-link" data-bs-toggle="#" data-bs-target="#"
                    aria-expanded="false" aria-controls="auth">
                    <i class="fa-brands fa-searchengin"></i>
                    <span style="font-size: 18px;">Search & Locate</span>
                </a>
            </li>

            <li class="sidebar-item">
                <a href="pharmacy_prescription.php" class="sidebar-link position-relative">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="fa-solid fa-file-prescription" viewBox="0 0 16 16">
                        <path d="m7.646 9.354-3.792 3.792a.5.5 0 0 0 .353.854h7.586a.5.5 0 0 0 .354-.854L8.354 9.354a.5.5 0 0 0-.708 0" />
                        <path d="M11.414 11H14.5a.5.5 0 0 0 .5-.5v-7a.5.5 0 0 0-.5-.5h-13a.5.5 0 0 0-.5.5v7a.5.5 0 0 0 .5.5h3.086l-1 1H1.5A1.5 1.5 0 0 1 0 10.5v-7A1.5 1.5 0 0 1 1.5 2h13A1.5 1.5 0 0 1 16 3.5v7a1.5 1.5 0 0 1-1.5 1.5h-2.086z" />
                    </svg>
                    <span style="font-size: 18px;">Prescription</span>
                    <?php if ($pendingCount > 0): ?>
                        <span class="notif-badge"><?php echo $pendingCount; ?></span>
                    <?php endif; ?>
                </a>
            </li>

            <li class="sidebar-item">
                <a href="pharmacy_otc.php" class="sidebar-link position-relative">
                    <i class="fa-solid fa-briefcase-medical"></i>
                    <span style="font-size: 18px;">Over The Counter</span>
                </a>
            </li>

            <li class="sidebar-item">
                <a href="#" class="sidebar-link collapsed has-dropdown" data-bs-toggle="collapse"
                    data-bs-target="#gerald" aria-expanded="true" aria-controls="auth">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="fa-solid fa-chart-line" viewBox="0 0 16 16">
                        <path d="m7.646 9.354-3.792 3.792a.5.5 0 0 0 .353.854h7.586a.5.5 0 0 0 .354-.854L8.354 9.354a.5.5 0 0 0-.708 0" />
                        <path d="M11.414 11H14.5a.5.5 0 0 0 .5-.5v-7a.5.5 0 0 0-.5-.5h-13a.5.5 0 0 0-.5.5v7a.5.5 0 0 0 .5.5h3.086l-1 1H1.5A1.5 1.5 0 0 1 0 10.5v-7A1.5 1.5 0 0 1 1.5 2h13A1.5 1.5 0 0 1 16 3.5v7a1.5 1.5 0 0 1-1.5 1.5h-2.086z" />
                    </svg>
                    <span style="font-size: 18px;">Reports</span>
                </a>

                <ul id="gerald" class="sidebar-dropdown list-unstyled collapse show" data-bs-parent="#sidebar">
                    <li class="sidebar-item">
                        <a href="pharmacy_inventory_report.php" class="sidebar-link active">Inventory Report</a>
                    </li>
                    <li class="sidebar-item">
                        <a href="pharmacy_sales.php" class="sidebar-link">Financial Report</a>
                    </li>
                    <li class="sidebar-item">
                        <a href="pharmacy_dispense_report.php" class="sidebar-link">Dispensing Report</a>
                    </li>
                </ul>
            </li>
            <li class="sidebar-item position-relative">
                <a href="pharmacy_expiry_tracking.php" class="sidebar-link">
                    <i class="fa-solid fa-calendar-check"></i>
                    <span style="font-size: 18px;">Drug Expiry Tracking</span>
                    <?php if ($expiryCount > 0): ?>
                        <span class="expiry-dot"></span>
                    <?php endif; ?>
                </a>
            </li>
            <li class="sidebar-item">
                <a href="pharmacy_supply_request.php" class="sidebar-link" data-bs-toggle="#" data-bs-target="#"
                    aria-expanded="false" aria-controls="auth">
                    <i class="fa-solid fa-boxes-stacked"></i>
                    <span style="font-size: 18px;">Supply Request</span>
                </a>
            </li>


        </aside>
        <!----- End of Sidebar ----->
        <!----- Main Content ----->
        <div class="main">
            <div class="topbar">
                <div class="toggle">
                    <button class="toggler-btn" type="button">
                        <svg xmlns="http://www.w3.org/2000/svg" width="30px" height="30px" fill="currentColor" class="bi bi-list-ul"
                            viewBox="0 0 16 16">
                            <path fill-rule="evenodd"
                                d="M5 11.5a.5.5 0 0 1 .5-.5h9a.5.5 0 0 1 0 1h-9a.5.5 0 0 1-.5-.5m0-4a.5.5 0 0 1 .5-.5h9a.5.5 0 0 1 0 1h-9a.5.5 0 0 1-.5-.5m0-4a.5.5 0 0 1 .5-.5h9a.5.5 0 0 1 0 1h-9a.5.5 0 0 1-.5-.5m-3 1a1 1 0 1 0 0-2 1 1 0 0 0 0 2m0 4a1 1 0 1 0 0-2 1 1 0 0 0 0 2m0 4a1 1 0 1 0 0-2 1 1 0 0 0 0 2" />
                        </svg>
                    </button>
                </div>
                <div class="logo">
                    <div class="d-flex align-items-center">
                        <!-- Notification Icon -->
                        <div class="notification me-3 dropdown position-relative">
                            <a href="#" id="notifBell" data-bs-toggle="dropdown" aria-expanded="false" class="text-dark" style="text-decoration: none;">
                                <i class="fa-solid fa-bell fs-4"></i>
                                <?php if ($notifCount > 0): ?>
                                    <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size:11px;">
                                        <?php echo $notifCount; ?>
                                    </span>
                                <?php endif; ?>
                            </a>

                            <ul class="dropdown-menu dropdown-menu-end shadow" aria-labelledby="notifBell"
                                style="min-width:400px; max-height:400px; overflow-y:auto; border-radius:8px;">
                                <li class="dropdown-header fw-bold">Notifications</li>

                                <?php if (!empty($latestNotifications)): ?>
                                    <?php foreach ($latestNotifications as $index => $n): ?>
                                        <li class="notif-item <?php echo $index >= 10 ? 'd-none extra-notif' : ''; ?>">
                                            <a class="dropdown-item d-flex align-items-start" href="<?php echo $n['link']; ?>">
                                                <div class="me-2">
                                                    <?php if ($n['type'] == 'prescription'): ?>
                                                        <i class="bi bi-file-earmark-medical-fill text-primary fs-5"></i>
                                                    <?php elseif ($n['type'] == 'expiry'): ?>
                                                        <i class="bi bi-exclamation-triangle-fill text-warning fs-5"></i>
                                                    <?php else: ?>
                                                        <i class="bi bi-info-circle-fill text-secondary fs-5"></i>
                                                    <?php endif; ?>
                                                </div>
                                                <div>
                                                    <div class="small fw-semibold text-wrap" style="white-space: normal; word-break: break-word;">
                                                        <?php echo $n['message']; ?>
                                                    </div>
                                                    <div class="small text-muted text-wrap" style="white-space: normal; word-break: break-word;">
                                                        <?php
                                                        if ($n['type'] == 'expiry' && isset($n['days_left'])) {
                                                            $daysLeft = (int)$n['days_left'];
                                                            if ($daysLeft > 0) {
                                                                echo "in {$daysLeft} day(s)";
                                                            } elseif ($daysLeft == 0) {
                                                                echo "today";
                                                            } else {
                                                                echo abs($daysLeft) . " day(s) ago";
                                                            }
                                                        } else {
                                                            echo $n['time'];
                                                        }
                                                        ?>
                                                    </div>
                                                </div>
                                            </a>
                                        </li>
                                        <?php if ($index < count($latestNotifications) - 1): ?>
                                            <li class="<?php echo $index >= 10 ? 'd-none extra-notif' : ''; ?>">
                                                <hr class="dropdown-divider">
                                            </li>
                                        <?php endif; ?>
                                    <?php endforeach; ?>

                                    <!-- Load More Button -->
                                    <?php if (count($latestNotifications) > 10): ?>
                                        <li class="text-center">
                                            <a href="#" id="loadMoreNotif" class="dropdown-item fw-semibold">View Previous Notifications</a>
                                        </li>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <li><span class="dropdown-item text-muted">No new notifications</span></li>
                                <?php endif; ?>
                            </ul>
                        </div>

                        <!-- Username + Profile Dropdown -->
                        <div class="dropdown d-flex align-items-center">
                            <span class="username ml-1 me-2"><?php echo $user['fname']; ?> <?php echo $user['lname']; ?></span>
                            <button class="btn dropdown-toggle" type="button" id="dropdownMenuButton" data-bs-toggle="dropdown">
                                <i class="bi bi-person-circle"></i>
                            </button>
                            <ul class="dropdown-menu" aria-labelledby="dropdownMenuButton">
                                <li><span>Welcome <strong><?php echo $user['lname']; ?></strong>!</span></li>
                                <li><a class="dropdown-item" href="../logout.php">Logout</a></li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>




            <div class="content">
                <div class="title-container">
                    <i class="fa-solid fa-briefcase-medical"></i>
                    <h1 class="page-title">Over The Counter</h1>
                </div>

                <div class="d-flex justify-content-end mb-3">
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#otcModal">
                        Create Invoice
                    </button>
                </div>

                <!-- OTC Modal -->
                <div class="modal fade" id="otcModal" tabindex="-1">
                    <div class="modal-dialog modal-lg">
                        <div class="modal-content">
                            <form id="otcForm" action="process_otc_invoice.php" method="POST">
                                <div class="modal-header">
                                    <h5 class="modal-title">Create OTC Invoice</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>
                                <div class="modal-body">
                                    <!-- Customer Name -->
                                    <div class="mb-3">
                                        <label>Customer Name</label>
                                        <input type="text" name="customer_name" class="form-control" placeholder="Optional for walk-in" />
                                    </div>

                                    <!-- Medicine Selection -->
                                    <div class="row g-3 mb-3">
                                        <div class="col-md-4">
                                            <label>Generic Name</label>
                                            <select id="generic_name" name="generic_name" class="form-select" required>
                                                <option value="">-- Select Generic --</option>
                                                <?php
                                                $q = $conn->query("
    SELECT DISTINCT generic_name 
    FROM pharmacy_inventory 
    WHERE prescription_required = 'No'
    ORDER BY generic_name
");
                                                while ($row = $q->fetch_assoc()) {
                                                    echo "<option value='{$row['generic_name']}'>{$row['generic_name']}</option>";
                                                }
                                                ?>
                                            </select>
                                        </div>
                                        <div class="col-md-4">
                                            <label>Brand Name</label>
                                            <select id="brand_name" name="brand_name" class="form-select" disabled required>
                                                <option value="">-- Select Brand --</option>
                                            </select>
                                        </div>
                                        <div class="col-md-4">
                                            <label>Dosage</label>
                                            <select id="dosage" name="dosage" class="form-select" disabled required>
                                                <option value="">-- Select Dosage --</option>
                                            </select>
                                        </div>
                                    </div>

                                    <div class="row g-3 mb-3">
                                        <div class="col-md-4">
                                            <label>Quantity</label>
                                            <input type="number" name="quantity" id="quantity" class="form-control" min="1" value="1" required />
                                        </div>
                                        <div class="col-md-4">
                                            <label>Unit Price</label>
                                            <input type="text" name="unit_price" id="unit_price" class="form-control" readonly />
                                        </div>
                                        <div class="col-md-4">
                                            <label>Total Price</label>
                                            <input type="text" name="total_price" id="total_price" class="form-control" readonly />
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <label>Payment Method</label>
                                        <select name="payment_method" class="form-select" required>
                                            <option value="">-- Select Payment Method --</option>
                                            <option value="cash">Cash</option>

                                        </select>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="submit" name="submit_otc" class="btn btn-primary">Create Invoice</button>
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- OTC Invoices Table -->
                <div class="mt-4">

                    <?php
                    $query = $conn->query("SELECT * FROM pharmacy_sales WHERE transaction_type='OTC' ORDER BY sale_date DESC");
                    if ($query->num_rows > 0):
                    ?>
                        <table class="table table-bordered table-sm align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>Invoice #</th>
                                    <th>Customer Name</th>
                                    <th>Medicine</th>
                                    <th>Quantity</th>
                                    <th>Unit Price</th>
                                    <th>Total Price</th>
                                    <th>Payment Method</th>
                                    <th>Staff</th>
                                    <th>Date</th>

                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($row = $query->fetch_assoc()): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($row['sale_id']) ?></td>
                                        <td><?= htmlspecialchars($row['customer_name'] ?: 'Walk-in') ?></td>
                                        <td><?= htmlspecialchars($row['med_name']) ?></td>
                                        <td><?= htmlspecialchars($row['quantity_sold']) ?></td>
                                        <td><?= number_format($row['price_per_unit'], 2) ?></td>
                                        <td><?= number_format($row['total_price'], 2) ?></td>
                                        <td><?= htmlspecialchars(ucfirst($row['payment_method'])) ?></td>
                                        <td><?= htmlspecialchars($row['staff_name']) ?></td>
                                        <td><?= date('Y-m-d H:i', strtotime($row['sale_date'])) ?></td>

                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <div class="alert alert-secondary text-center">No OTC invoices yet.</div>
                    <?php endif; ?>
                </div>
            </div>




            <!----- End of Main Content ----->
        </div>
        <script>
            document.addEventListener("DOMContentLoaded", function() {
                const loadMoreBtn = document.getElementById("loadMoreNotif");
                if (loadMoreBtn) {
                    loadMoreBtn.addEventListener("click", function(e) {
                        e.preventDefault();
                        document.querySelectorAll(".extra-notif").forEach(el => el.classList.remove("d-none"));
                        this.style.display = "none"; // hide the button once expanded
                    });
                }
            });
        </script>


        <script>
            const genericSelect = document.getElementById('generic_name');
            const brandSelect = document.getElementById('brand_name');
            const dosageSelect = document.getElementById('dosage');
            const quantityInput = document.getElementById('quantity');
            const unitPriceInput = document.getElementById('unit_price');
            const totalPriceInput = document.getElementById('total_price');

            // When Generic changes → load Brands
            genericSelect.addEventListener('change', function() {
                const generic = this.value;
                brandSelect.innerHTML = '<option value="">-- Select Brand --</option>';
                dosageSelect.innerHTML = '<option value="">-- Select Dosage --</option>';
                brandSelect.disabled = true;
                dosageSelect.disabled = true;
                unitPriceInput.value = '';
                totalPriceInput.value = '';

                if (!generic) return;

                fetch('get_medicine_info.php?generic=' + encodeURIComponent(generic))
                    .then(res => res.json())
                    .then(data => {
                        data.forEach(b => {
                            const opt = document.createElement('option');
                            opt.value = b.brand_name;
                            opt.text = b.brand_name;
                            brandSelect.appendChild(opt);
                        });
                        brandSelect.disabled = false;
                    });
            });

            // When Brand changes → load Dosages
            brandSelect.addEventListener('change', function() {
                const generic = genericSelect.value;
                const brand = this.value;
                dosageSelect.innerHTML = '<option value="">-- Select Dosage --</option>';
                dosageSelect.disabled = true;
                unitPriceInput.value = '';
                totalPriceInput.value = '';

                if (!brand) return;

                fetch(`get_medicine_info.php?generic=${encodeURIComponent(generic)}&brand=${encodeURIComponent(brand)}`)
                    .then(res => res.json())
                    .then(data => {
                        data.forEach(d => {
                            const opt = document.createElement('option');
                            opt.value = d.dosage;
                            opt.text = d.dosage;
                            dosageSelect.appendChild(opt);
                        });
                        dosageSelect.disabled = false;
                    });
            });

            // When Dosage changes → load Unit Price
            dosageSelect.addEventListener('change', function() {
                const generic = genericSelect.value;
                const brand = brandSelect.value;
                const dosage = this.value;
                unitPriceInput.value = '';
                totalPriceInput.value = '';

                if (!dosage) return;

                fetch(`get_medicine_info.php?generic=${encodeURIComponent(generic)}&brand=${encodeURIComponent(brand)}&dosage=${encodeURIComponent(dosage)}`)
                    .then(res => res.json())
                    .then(data => {
                        unitPriceInput.value = data.unit_price;
                        totalPriceInput.value = (data.unit_price * quantityInput.value).toFixed(2);
                    });
            });

            // Update total price on quantity input
            quantityInput.addEventListener('input', function() {
                const unit_price = parseFloat(unitPriceInput.value) || 0;
                totalPriceInput.value = (unit_price * this.value).toFixed(2);
            });
            document.getElementById('otcForm').addEventListener('submit', function(e) {
                e.preventDefault();

                const formData = new FormData(this);
                formData.append('submit_otc', '1'); // <-- important for PHP

                fetch('pharmacy_otc.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(res => res.json())
                    .then(data => {
                        if (data.status === 'error') {
                            alert("⚠ Warning: " + data.message);
                        } else {
                            alert(data.message);
                            location.reload();
                        }
                    })
                    .catch(err => {
                        console.error(err);
                        alert("An error occurred. Please try again.");
                    });
            });
        </script>
        <script>
            const toggler = document.querySelector(".toggler-btn");
            toggler.addEventListener("click", function() {
                document.querySelector("#sidebar").classList.toggle("collapsed");
            });
        </script>
        <script src="assets/Bootstrap/all.min.js"></script>
        <script src="assets/Bootstrap/bootstrap.bundle.min.js"></script>
        <script src="assets/Bootstrap/fontawesome.min.js"></script>
        <script src="assets/Bootstrap/jq.js"></script>
</body>

</html>