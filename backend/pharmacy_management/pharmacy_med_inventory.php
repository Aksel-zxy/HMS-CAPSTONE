<?php
include '../../SQL/config.php';
require_once 'classes/medicine.php';
require_once "classes/notification.php";




if (!isset($_SESSION['pharmacy']) || $_SESSION['pharmacy'] !== true) {
    header('Location: login.php');
    exit();
}

if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    echo "User ID is not set in session.";
    exit();
}

// Get logged-in user info
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

// Initialize medicine object
$medicineObj = new Medicine($conn);

// Auto-update status for all medicines
$medicineObj->autoUpdateOutOfStock();

try {
    // Fetch all medicines (total stock & general info)
    $medicines = $medicineObj->getAllMedicines();
} catch (Exception $e) {
    $error = $e->getMessage();
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
    <link rel="stylesheet" href="assets/CSS/med_inventory.css">
    <link rel="stylesheet" href="assets/CSS/prescription.css">

</head>

<body>
    <div class="d-flex">
        <!----- Sidebar ----->
        <aside id="sidebar" class="sidebar-toggle">

            <div class="sidebar-logo mt-3">
                <img src="assets/image/logo-dark.png" width="90px" height="20px">
            </div>

            <div class="menu-title">Pharmacy Management | Medicine Inventory</div>

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
                <a class="sidebar-link position-relative" data-bs-toggle="collapse" href="#prescriptionMenu" role="button" aria-expanded="false" aria-controls="prescriptionMenu">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="fa-solid fa-file-prescription" viewBox="0 0 16 16">
                        <path d="m7.646 9.354-3.792 3.792a.5.5 0 0 0 .353.854h7.586a.5.5 0 0 0 .354-.854L8.354 9.354a.5.5 0 0 0-.708 0" />
                        <path d="M11.414 11H14.5a.5.5 0 0 0 .5-.5v-7a.5.5 0 0 0-.5-.5h-13a.5.5 0 0 0-.5.5v7a.5.5 0 0 0 .5.5h3.086l-1 1H1.5A1.5 1.5 0 0 1 0 10.5v-7A1.5 1.5 0 0 1 1.5 2h13A1.5 1.5 0 0 1 16 3.5v7a1.5 1.5 0 0 1-1.5 1.5h-2.086z" />
                    </svg>
                    <span style="font-size: 18px;">Prescription</span>
                    <?php if ($pendingCount > 0): ?>
                        <span class="notif-dot"></span>
                    <?php endif; ?>
                </a>

                <ul class="collapse list-unstyled ms-3" id="prescriptionMenu">
                    <li>
                        <a href="pharmacy_prescription.php" class="sidebar-link position-relative">
                            View Prescriptions
                            <?php if ($pendingCount > 0): ?>
                                <span class="notif-badge"><?php echo $pendingCount; ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                    <li>
                        <a href="pharmacy_add_prescription.php" class="sidebar-link">Add Prescription</a>
                    </li>
                </ul>
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
            <div class="content">
                <div class="title-container">
                    <i class="fa-solid fa-capsules"></i>
                    <h1 class="page-title">Medicine Inventory</h1>
                </div>



                <!-- Medicine Modal -->
                <div class="modal fade" id="medicineModal" tabindex="-1" aria-labelledby="medicineModalLabel" aria-hidden="true">
                    <div class="modal-dialog">
                        <div class="modal-content">

                            <!-- Modal Header -->
                            <div class="modal-header">
                                <h5 class="modal-title" id="medicineModalLabel">Add New Medicine</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>

                            <!-- Modal Body -->
                            <div class="modal-body">
                                <form action="add_medicine.php" method="POST">

                                    <!-- Medicine Name -->
                                    <div class="mb-3">
                                        <label class="form-label">Medicine Name</label>
                                        <input type="text" class="form-control" name="med_name" required>
                                    </div>

                                    <!-- Category -->
                                    <div class="mb-3">
                                        <label class="form-label">Category</label>
                                        <select class="form-control" name="category" required>
                                            <option value="">-- Select Category --</option>
                                            <option value="Paracetamol">Paracetamol</option>
                                            <option value="Pain Killers">Pain Killers</option>
                                            <option value="Antibiotics">Antibiotics</option>
                                            <option value="Cough & Cold">Cough & Cold</option>
                                            <option value="Allergy Medicine">Allergy Medicine</option>
                                            <option value="Stomach Medicine">Stomach Medicine</option>
                                            <option value="Antifungal">Antifungal</option>
                                            <option value="Vitamins">Vitamins</option>
                                            <option value="First Aid">First Aid</option>
                                            <option value="Other">Other</option>
                                        </select>
                                    </div>


                                    <!-- Dosage -->
                                    <div class="mb-3">
                                        <label class="form-label">Dosage</label>
                                        <input type="text" class="form-control" name="dosage">
                                    </div>

                                    <!-- Stock Quantity -->
                                    <div class="mb-3">
                                        <label class="form-label">Stock Quantity</label>
                                        <input type="number" class="form-control" name="stock_quantity" required>
                                    </div>

                                    <!-- Unit Price -->
                                    <div class="mb-3">
                                        <label class="form-label">Unit Price (₱)</label>
                                        <input type="number" step="0.01" class="form-control" name="unit_price" required>
                                    </div>

                                    <!-- Unit -->
                                    <div class="mb-3">
                                        <label class="form-label">Unit / Formulation</label>
                                        <select class="form-control" name="unit" required>
                                            <option value="">-- Select Unit / Formulation --</option>
                                            <option value="Tablets & Capsules">Tablets & Capsules</option>
                                            <option value="Syrups / Oral Liquids">Syrups / Oral Liquids</option>
                                            <option value="Antibiotic Dry Syrup (Powder)">Antibiotic Dry Syrup (Powder)</option>
                                            <option value="Injectables (Ampoules / Vials)">Injectables (Ampoules / Vials)</option>
                                            <option value="Eye Drops / Ear Drops">Eye Drops / Ear Drops</option>
                                            <option value="Insulin">Insulin</option>
                                            <option value="Topical Creams / Ointments">Topical Creams / Ointments</option>
                                            <option value="Vaccines">Vaccines</option>
                                            <option value="IV Fluids">IV Fluids</option>
                                        </select>
                                    </div>


                                    <!-- Submit Button -->
                                    <button type="submit" class="btn btn-success">Add Medicine</button>
                                </form>
                            </div>

                        </div>
                    </div>
                </div>






                <div class="content mt-4">
                    <!-- Header Section -->
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h2></h2>

                        <div class="d-flex align-items-center w-100" style="max-width: 700px;">
                            <!-- Search bar -->
                            <input type="text" id="searchInput" class="form-control me-2" placeholder="Search medicine...">

                            <!-- Add Medicine button -->
                            <button type="button" class="btn btn-primary text-nowrap" data-bs-toggle="modal" data-bs-target="#medicineModal">
                                Add Medicine
                            </button>
                        </div>
                    </div>


                    <!-- Medicine Inventory Table -->
                    <table id="medicineInventoryTable" class="table table-bordered table-hover">
                        <thead>
                            <tr>
                                <th onclick="sortTable(0)">Medicine ID</th>
                                <th onclick="sortTable(1)">Medicine Name</th>
                                <th onclick="groupTable(2)">Category</th>
                                <th>Dosage</th>
                                <th>Stock Quantity</th>
                                <th>Unit Price (₱)</th>
                                <th onclick="groupTable(6)">Unit</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (isset($error)): ?>
                                <tr>
                                    <td colspan="9"><?php echo $error; ?></td>
                                </tr>
                            <?php elseif (!empty($medicines)): ?>
                                <?php foreach ($medicines as $row): ?>
                                    <?php
                                    $status = htmlspecialchars($row['status'] ?? 'Unknown');
                                    $badgeClass = ($status == 'Available') ? 'success' : (($status == 'Out of Stock') ? 'danger' : 'secondary');
                                    ?>
                                    <tr>
                                        <td><?= htmlspecialchars($row['med_id']) ?></td>
                                        <td><?= htmlspecialchars($row['med_name']) ?></td>
                                        <td><?= htmlspecialchars($row['category']) ?></td>
                                        <td><?= htmlspecialchars($row['dosage']) ?></td>
                                        <td><?= htmlspecialchars($row['stock_quantity']) ?></td>
                                        <td><?= number_format($row['unit_price'], 2) ?></td>
                                        <td><?= htmlspecialchars($row['unit']) ?></td>
                                        <td>
                                            <span class="badge bg-<?= $badgeClass ?>">
                                                <?= $status ?>
                                            </span>
                                        </td>
                                        <td>
                                            <!-- Edit Button -->
                                            <button
                                                class="btn btn-warning btn-sm edit-btn"
                                                data-bs-toggle="modal"
                                                data-bs-target="#editMedicineModal"
                                                data-id="<?= $row['med_id'] ?>"
                                                data-name="<?= htmlspecialchars($row['med_name']) ?>"
                                                data-category="<?= htmlspecialchars($row['category']) ?>"
                                                data-dosage="<?= htmlspecialchars($row['dosage']) ?>"
                                                data-stock="<?= $row['stock_quantity'] ?>"
                                                data-unit="<?= htmlspecialchars($row['unit']) ?>"
                                                data-price="<?= $row['unit_price'] ?>">Edit</button>

                                            <!-- Delete Button Form -->
                                            <form action="update_medicine.php" method="POST" style="display:inline;">
                                                <input type="hidden" name="med_id" value="<?= $row['med_id'] ?>">
                                                <button type="submit" name="delete" class="btn btn-danger btn-sm"
                                                    onclick="return confirm('Are you sure you want to delete this medicine?');">
                                                    Delete
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="9">No medicine records found.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>

                    <!-- Pagination -->
                    <nav aria-label="Medicine Inventory pagination">
                        <ul class="pagination justify-content-center" id="medicineInventoryPagination"></ul>
                    </nav>


                    <script>
                        function setupPagination(tableId, paginationId, rowsPerPage) {
                            const table = document.getElementById(tableId);
                            const tbody = table.querySelector("tbody");
                            const rows = tbody.querySelectorAll("tr");
                            const pagination = document.getElementById(paginationId);

                            let currentPage = 1;
                            const totalPages = Math.ceil(rows.length / rowsPerPage);

                            function displayRows() {
                                rows.forEach((row, index) => {
                                    row.style.display =
                                        (index >= (currentPage - 1) * rowsPerPage && index < currentPage * rowsPerPage) ?
                                        "" : "none";
                                });
                            }

                            function updatePagination() {
                                pagination.innerHTML = "";

                                // Previous button
                                const prevItem = document.createElement("li");
                                prevItem.className = `page-item ${currentPage === 1 ? "disabled" : ""}`;
                                prevItem.innerHTML = `<a class="page-link" href="#">&laquo;</a>`;
                                prevItem.onclick = (e) => {
                                    e.preventDefault();
                                    if (currentPage > 1) {
                                        currentPage--;
                                        displayRows();
                                        updatePagination();
                                    }
                                };
                                pagination.appendChild(prevItem);

                                // Page numbers
                                for (let i = 1; i <= totalPages; i++) {
                                    const li = document.createElement("li");
                                    li.className = `page-item ${i === currentPage ? "active" : ""}`;
                                    li.innerHTML = `<a class="page-link" href="#">${i}</a>`;
                                    li.onclick = (e) => {
                                        e.preventDefault();
                                        currentPage = i;
                                        displayRows();
                                        updatePagination();
                                    };
                                    pagination.appendChild(li);
                                }

                                // Next button
                                const nextItem = document.createElement("li");
                                nextItem.className = `page-item ${currentPage === totalPages ? "disabled" : ""}`;
                                nextItem.innerHTML = `<a class="page-link" href="#">&raquo;</a>`;
                                nextItem.onclick = (e) => {
                                    e.preventDefault();
                                    if (currentPage < totalPages) {
                                        currentPage++;
                                        displayRows();
                                        updatePagination();
                                    }
                                };
                                pagination.appendChild(nextItem);
                            }

                            if (rows.length > 0) {
                                displayRows();
                                updatePagination();
                            }
                        }

                        // Initialize pagination when page loads
                        document.addEventListener("DOMContentLoaded", function() {
                            setupPagination("medicineInventoryTable", "medicineInventoryPagination", 15); // Show 10 rows per page
                        });
                    </script>



                </div>
            </div>

            <!-- Edit Medicine Modal -->
            <div class="modal fade" id="editMedicineModal" tabindex="-1" aria-labelledby="editMedicineModalLabel" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">

                        <form action="update_medicine.php" method="POST">
                            <div class="modal-header">
                                <h5 class="modal-title">Edit Medicine</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>

                            <div class="modal-body">
                                <input type="hidden" name="med_id" id="edit_med_id">
                                <div class="mb-3">
                                    <label class="form-label">Medicine Name</label>
                                    <input type="text" class="form-control" name="med_name" id="edit_med_name" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Category</label>
                                    <input type="text" class="form-control" name="category" id="edit_category" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Dosage</label>
                                    <input type="text" class="form-control" name="dosage" id="edit_dosage" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Stock Quantity</label>
                                    <input type="number" class="form-control" name="stock_quantity" id="edit_stock_quantity" readonly>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Unit Price (₱)</label>
                                    <input type="number" step="0.01" class="form-control" name="unit_price" id="edit_unit_price" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Unit</label>
                                    <input type="text" class="form-control" name="unit" id="edit_unit" required>
                                </div>
                            </div>

                            <div class="modal-footer">
                                <button type="submit" class="btn btn-success">Update</button>
                            </div>
                        </form>

                    </div>
                </div>
            </div>


            <!-- END CODING HERE -->
        </div>
        <!----- End of Main Content ----->
    </div>
    <script>
        // Simple table sort function (works for numbers and text)
        function sortTable(colIndex) {
            let table = document.getElementById("medicineInventoryTable");
            let rows = Array.from(table.rows).slice(1); // skip header
            let asc = table.getAttribute("data-sort-col") == colIndex && table.getAttribute("data-sort-dir") == "asc" ? false : true;

            rows.sort((a, b) => {
                let x = a.cells[colIndex].innerText.trim().toLowerCase();
                let y = b.cells[colIndex].innerText.trim().toLowerCase();

                // If number, compare numerically
                if (!isNaN(x) && !isNaN(y)) {
                    return asc ? x - y : y - x;
                }

                return asc ? x.localeCompare(y) : y.localeCompare(x);
            });

            rows.forEach(row => table.tBodies[0].appendChild(row));

            table.setAttribute("data-sort-col", colIndex);
            table.setAttribute("data-sort-dir", asc ? "asc" : "desc");
        }

        // Force grouping for Category & Unit
        function groupTable(colIndex) {
            let table = document.getElementById("medicineInventoryTable");
            let rows = Array.from(table.rows).slice(1);

            rows.sort((a, b) => {
                let x = a.cells[colIndex].innerText.trim().toLowerCase();
                let y = b.cells[colIndex].innerText.trim().toLowerCase();
                return x.localeCompare(y);
            });

            rows.forEach(row => table.tBodies[0].appendChild(row));
        }
    </script>

    <script>
        document.querySelectorAll('.edit-btn').forEach(button => {
            button.addEventListener('click', function() {
                document.getElementById('edit_med_id').value = this.dataset.id;
                document.getElementById('edit_med_name').value = this.dataset.name;
                document.getElementById('edit_category').value = this.dataset.category;
                document.getElementById('edit_dosage').value = this.dataset.dosage;
                document.getElementById('edit_stock_quantity').value = this.dataset.stock;
                document.getElementById('edit_unit').value = this.dataset.unit;
                document.getElementById('edit_unit_price').value = this.dataset.price; // NEW
            });
        });
    </script>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const dropdowns = document.querySelectorAll('.status-dropdown');

            dropdowns.forEach(dropdown => {
                dropdown.addEventListener('change', function() {
                    const med_id = this.getAttribute('data-id');
                    const new_status = this.value;

                    fetch('update_status.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded'
                            },
                            body: `med_id=${med_id}&new_status=${new_status}`
                        })
                        .then(res => res.json())
                        .then(data => {
                            if (data.success) {
                                alert('Status updated.');
                                location.reload(); // Optional: refresh table to see badge update
                            } else {
                                alert('Error: ' + data.message);
                            }
                        });
                });
            });
        });
    </script>
    <script>
        const searchInput = document.getElementById("searchInput");

        function filterInventoryTable() {
            const searchValue = searchInput.value.toLowerCase();

            document.querySelectorAll("#medicineInventoryTable tbody tr").forEach(row => {
                const medNameCell = row.querySelector("td:nth-child(2)"); // 2nd column = Medicine Name
                if (!medNameCell) return;

                const medName = medNameCell.textContent.toLowerCase();
                row.style.display = medName.includes(searchValue) ? "" : "none";
            });
        }

        searchInput.addEventListener("keyup", filterInventoryTable);
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