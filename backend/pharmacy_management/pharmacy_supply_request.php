<?php
include '../../SQL/config.php';
require_once "classes/notification.php";

error_reporting(E_ALL);
ini_set('display_errors', 1);

// 🔐 Ensure login
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// 👤 Fetch user info
$user_stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ? LIMIT 1");
$user_stmt->execute([$user_id]);
$user = $user_stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) die("User not found.");

$full_name = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
$department = $user['department'] ?? 'Unknown Department';
$department_id = $user['department_id'] ?? 0;
$request_date = date('F d, Y');

// 📤 Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $items = $_POST['items'] ?? [];
        $valid_items = array_filter($items, fn($i) => !empty(trim($i['name'] ?? '')));
        if (count($valid_items) === 0) throw new Exception("Please add at least one item before submitting.");

        // Remove 'items' from the query
        $stmt = $pdo->prepare("
            INSERT INTO department_request
            (user_id, department, department_id, month, total_items, status)
            VALUES
            (:user_id, :department, :department_id, :month, :total_items, 'Pending')
        ");
        $stmt->execute([
            ':user_id'       => $user_id,
            ':department'    => $department,
            ':department_id' => $department_id,
            ':month'         => date('Y-m-d'),
            ':total_items'   => count($valid_items)
        ]);

        $success = "Purchase request successfully submitted!";
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}


// 🔎 Fetch user's requests
$request_stmt = $pdo->prepare("SELECT * FROM department_request WHERE user_id = ? ORDER BY created_at DESC");
$request_stmt->execute([$user_id]);
$my_requests = $request_stmt->fetchAll(PDO::FETCH_ASSOC);

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
</head>

<body>
    <div class="d-flex">
        <!----- Sidebar ----->
        <aside id="sidebar" class="sidebar-toggle">

            <div class="sidebar-logo mt-3">
                <img src="assets/image/logo-dark.png" width="90px" height="20px">
            </div>

            <div class="menu-title">Pharmacy Management | <span>Supply Request</span></div>

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
                <a href="pharmacy_sales.php" class="sidebar-link" data-bs-toggle="#" data-bs-target="#"
                    aria-expanded="false" aria-controls="auth">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="fa-solid fa-chart-line" viewBox="0 0 16 16">
                        <path d="m7.646 9.354-3.792 3.792a.5.5 0 0 0 .353.854h7.586a.5.5 0 0 0 .354-.854L8.354 9.354a.5.5 0 0 0-.708 0" />
                        <path d="M11.414 11H14.5a.5.5 0 0 0 .5-.5v-7a.5.5 0 0 0-.5-.5h-13a.5.5 0 0 0-.5.5v7a.5.5 0 0 0 .5.5h3.086l-1 1H1.5A1.5 1.5 0 0 1 0 10.5v-7A1.5 1.5 0 0 1 1.5 2h13A1.5 1.5 0 0 1 16 3.5v7a1.5 1.5 0 0 1-1.5 1.5h-2.086z" />
                    </svg>
                    <span style="font-size: 18px;">Sales</span>
                </a>
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
            <!-- START CODING HERE -->
            <div class="container py-5">
                <h2 class="mb-4 fw-bold">📋 Purchase Requests</h2>

                <!-- Tabs -->
                <ul class="nav nav-tabs" id="requestTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="form-tab" data-bs-toggle="tab" data-bs-target="#form" type="button" role="tab">Request Form</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="my-requests-tab" data-bs-toggle="tab" data-bs-target="#my-requests" type="button" role="tab">My Requests</button>
                    </li>
                </ul>

                <div class="tab-content mt-3">
                    <!-- Request Form Tab -->
                    <div class="tab-pane fade show active" id="form" role="tabpanel">
                        <div class="card p-4">
                            <?php if (isset($success)) echo '<div class="alert alert-success">' . $success . '</div>'; ?>
                            <?php if (isset($error)) echo '<div class="alert alert-danger">' . $error . '</div>'; ?>

                            <div class="alert alert-info info-box mb-4">
                                <div><strong>Department:</strong> <?= htmlspecialchars($department) ?></div>
                                <div><strong>Request Date:</strong> <?= $request_date ?></div>
                            </div>

                            <form method="POST" id="requestForm">
                                <div class="table-responsive">
                                    <table class="table table-bordered align-middle">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Item</th>
                                                <th>Description</th>
                                                <th>Unit</th>
                                                <th>Qty</th>
                                                <th>Pcs / Box</th>
                                                <th>Total Pcs</th>
                                                <th>Action</th>
                                            </tr>
                                        </thead>
                                        <tbody id="itemBody">
                                            <tr>
                                                <td><input type="text" name="items[0][name]" class="form-control form-control-sm" required></td>
                                                <td><input type="text" name="items[0][description]" class="form-control form-control-sm"></td>
                                                <td>
                                                    <select name="items[0][unit]" class="form-select form-select-sm unit unit-select">
                                                        <option value="pcs">Per Piece</option>
                                                        <option value="box">Per Box</option>
                                                    </select>
                                                </td>
                                                <td><input type="number" name="items[0][quantity]" class="form-control form-control-sm quantity qty-input" value="1" min="1"></td>
                                                <td><input type="number" name="items[0][pcs_per_box]" class="form-control form-control-sm pcs-per-box pcs-box-input" value="1" min="1" disabled></td>
                                                <td><input type="number" name="items[0][total_pcs]" class="form-control form-control-sm total-pcs total-pcs-input" value="1" readonly></td>
                                                <td><button type="button" class="btn btn-sm btn-danger btn-remove">✕</button></td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>

                                <div class="text-center mt-3">
                                    <button type="button" id="addRowBtn" class="btn btn-outline-primary">➕ Add Item</button>
                                </div>
                                <div class="text-center mt-4">
                                    <button type="submit" class="btn btn-primary btn-lg">Submit Request</button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- My Requests Tab -->
                    <div class="tab-pane fade" id="my-requests" role="tabpanel">
                        <div class="card p-4">
                            <div class="table-responsive">
                                <table class="table table-bordered table-hover bg-white">
                                    <thead class="table-dark text-center">
                                        <tr>
                                            <th>ID</th>
                                            <th>Items</th>
                                            <th>Total Requested</th>
                                            <th>Total Approved</th>
                                            <th>Status</th>
                                            <th>Requested At</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($my_requests as $req):
                                            // ✅ Safely handle missing 'items' key
                                            $items_array = isset($req['items']) ? json_decode($req['items'], true) : [];
                                            $items_array = is_array($items_array) ? $items_array : [];
                                            $items_text = implode(", ", array_map(fn($i) => $i['name'] ?? '', $items_array));
                                            $total_requested = $req['total_items'] ?? count($items_array);
                                            $total_approved = $req['total_approved_items'] ?? 0;
                                            $items_json = htmlspecialchars(json_encode($items_array, JSON_UNESCAPED_UNICODE));
                                        ?>
                                            <tr>
                                                <td><?= htmlspecialchars($req['id']) ?></td>
                                                <td><?= htmlspecialchars($items_text) ?></td>
                                                <td class="text-center"><?= htmlspecialchars($total_requested) ?></td>
                                                <td class="text-center"><?= htmlspecialchars($total_approved) ?></td>
                                                <td>
                                                    <?php
                                                    $status = $req['status'] ?? '';
                                                    if ($status === 'Pending') echo '<span class="badge bg-warning text-dark status-badge">Pending</span>';
                                                    elseif ($status === 'Approved') echo '<span class="badge bg-success status-badge">Approved</span>';
                                                    else echo '<span class="badge bg-danger status-badge">Declined</span>';
                                                    ?>
                                                </td>
                                                <td><?= htmlspecialchars($req['created_at']) ?></td>
                                                <td>
                                                    <button class="btn btn-sm btn-info btn-view-items" data-items='<?= $items_json ?>'>View</button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>

                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <!-- Modal for viewing items -->
            <div class="modal fade" id="viewItemsModal" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-lg modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Request Items</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div class="table-responsive">
                                <table class="table table-bordered">
                                    <thead class="table-light text-center">
                                        <tr>
                                            <th>Item</th>
                                            <th>Description</th>
                                            <th>Unit</th>
                                            <th>Qty Requested</th>
                                            <th>Approved Qty</th>
                                        </tr>
                                    </thead>
                                    <tbody id="modalItemBody"></tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <!-- END CODING HERE -->
        </div>
        <!----- End of Main Content ----->
    </div>
    <script>
        const toggler = document.querySelector(".toggler-btn");
        toggler.addEventListener("click", function() {
            document.querySelector("#sidebar").classList.toggle("collapsed");
        });
    </script>
    <script>
        let itemIndex = 1;
        const addRowBtn = document.getElementById('addRowBtn');
        const itemBody = document.getElementById('itemBody');
        const requestForm = document.getElementById('requestForm');

        addRowBtn.onclick = () => {
            const row = itemBody.querySelector('tr').cloneNode(true);
            row.querySelectorAll('input, select').forEach(el => {
                el.name = el.name.replace(/\[\d+\]/, `[${itemIndex}]`);
                if (el.name.includes('[name]')) el.value = '';
                if (el.name.includes('[description]')) el.value = '';
                if (el.classList.contains('quantity')) el.value = 1;
                if (el.classList.contains('pcs-per-box')) {
                    el.value = 1;
                    el.disabled = true;
                }
                if (el.classList.contains('total-pcs')) el.value = 1;
            });
            itemBody.appendChild(row);
            itemIndex++;
        };

        itemBody.addEventListener('click', e => {
            if (e.target.classList.contains('btn-remove')) {
                if (itemBody.querySelectorAll('tr').length > 1) e.target.closest('tr').remove();
                else alert("At least one item must be in the request.");
            }
        });

        itemBody.addEventListener('input', e => {
            const row = e.target.closest('tr');
            if (!row) return;
            const unit = row.querySelector('.unit').value;
            const qty = parseFloat(row.querySelector('.quantity').value) || 0;
            const pcsBox = row.querySelector('.pcs-per-box');
            const pcsPerBox = parseFloat(pcsBox.value) || 1;
            row.querySelector('.total-pcs').value = unit === 'box' ? qty * pcsPerBox : qty;
            pcsBox.disabled = unit !== 'box';
        });

        requestForm.addEventListener('submit', e => {
            const rows = Array.from(itemBody.querySelectorAll('tr'));
            const hasItem = rows.some(row => row.querySelector('input[name*="[name]"]').value.trim() !== '');
            if (!hasItem) {
                e.preventDefault();
                alert("Please add at least one item with a name before submitting.");
            }
        });

        // View button logic
        document.querySelectorAll('.btn-view-items').forEach(btn => {
            btn.addEventListener('click', () => {
                const items = JSON.parse(btn.dataset.items);
                const modalBody = document.getElementById('modalItemBody');
                modalBody.innerHTML = '';
                items.forEach(i => {
                    const tr = document.createElement('tr');
                    tr.innerHTML = `
                <td>${i.name ?? ''}</td>
                <td>${i.description ?? ''}</td>
                <td>${i.unit ?? ''}</td>
                <td class="text-center">${i.quantity ?? 0}</td>
                <td class="text-center">${i.approved_quantity ?? 0}</td>
            `;
                    modalBody.appendChild(tr);
                });
                new bootstrap.Modal(document.getElementById('viewItemsModal')).show();
            });
        });
    </script>
    <script src="assets/Bootstrap/all.min.js"></script>
    <script src="assets/Bootstrap/bootstrap.bundle.min.js"></script>
    <script src="assets/Bootstrap/fontawesome.min.js"></script>
    <script src="assets/Bootstrap/jq.js"></script>
</body>

</html>