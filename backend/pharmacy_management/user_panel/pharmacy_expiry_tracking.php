<?php
include '../../../SQL/config.php';
require_once '../classes/medicine.php';
require_once "../classes/notification.php";

if (!isset($_SESSION['profession']) || $_SESSION['profession'] !== 'Pharmacist') {
    header('Location: login.php');
    exit();
}

if (!isset($_SESSION['employee_id'])) {
    echo "User ID is not set in session.";
    exit();
}

// Fetch user details from database
$query = "SELECT * FROM hr_employees WHERE employee_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $_SESSION['employee_id']);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if (!$user) {
    echo "No user found.";
    exit();
}

$medicineObj = new Medicine($conn);
$data = $medicineObj->getAllBatches();
$grouped = [];

foreach ($data as $batch) {
    $key = $batch['med_name'] . '|' . $batch['brand_name'];
    $grouped[$key][] = $batch;
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
    <link rel="shortcut icon" href="../assets/image/favicon.ico" type="image/x-icon">
    <link rel="stylesheet" href="../assets/CSS/bootstrap.min.css">
    <link rel="stylesheet" href="../assets/CSS/super.css">
    <link rel="stylesheet" href="../assets/CSS/med_inventory.css">
    <link rel="stylesheet" href="../assets/CSS/prescription.css">
</head>

<body>
    <div class="d-flex">
        <!----- Sidebar ----->
        <aside id="sidebar" class="sidebar-toggle">

            <div class="sidebar-logo mt-3">
                <img src="../assets/image/logo-dark.png" width="90px" height="20px">
            </div>

            <div class="menu-title">
                Pharmacy Management | <span>Expiry Tracking</span>
            </div>

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
                            <span class="username ml-1 me-2"><?php echo $user['first_name']; ?> <?php echo $user['last_name']; ?></span>
                            <button class="btn dropdown-toggle" type="button" id="dropdownMenuButton" data-bs-toggle="dropdown">
                                <i class="bi bi-person-circle"></i>
                            </button>
                            <ul class="dropdown-menu" aria-labelledby="dropdownMenuButton">
                                <li><span>Welcome <strong><?php echo $user['last_name']; ?></strong>!</span></li>
                                <li><a class="dropdown-item" href="../../logout.php">Logout</a></li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
            <!-- START CODING HERE -->
            <div class="content">
                <div class="title-container">
                    <i class="fa-solid fa-calendar-check"></i>
                    <h1 class="page-title">Medicine Expiry Tracking</h1>
                </div>

                <!-- Filter + Search -->
                <div class="row mb-3 justify-content-end">
                    <!-- Search -->
                    <div class="col-md-4">
                        <input type="text" id="searchInput" class="form-control" placeholder="Search medicine name...">
                    </div>

                    <!-- Filter -->
                    <div class="col-md-3">
                        <select id="statusFilter" class="form-select">
                            <option value="All" selected>Show All</option>
                            <option value="Available">Available</option>
                            <option value="Near Expiry">Near Expiry</option>
                            <option value="Expired">Expired</option>
                        </select>
                    </div>
                </div>



                <!-- Medicine Expiry Tracking Table -->
                <?php
                // Helper function to format date as Month Year
                function formatMonthYear($dateStr)
                {
                    if (!$dateStr) return '-';
                    $date = DateTime::createFromFormat('Y-m-d', $dateStr);
                    return $date ? $date->format('M Y') : '-'; // e.g., Sep 2025
                }
                ?>

                <table class="table" id="medicineExpiryTable">
                    <thead class="table">
                        <tr>
                            <th>Medicine Name</th>
                            <th>Stock Quantity</th>
                            <th>Expiry Date</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($grouped as $key => $batches): ?>
                            <?php
                            $medName = $batches[0]['med_name'];
                            $brandName = $batches[0]['brand_name'];
                            $totalStock = array_sum(array_column($batches, 'stock_quantity'));
                            $earliestExpiry = $batches[0]['expiry_date'] ?? '-';
                            $status = $batches[0]['status'] ?? 'Available';
                            ?>
                            <!-- Main Medicine Row -->
                            <tr class="medicine-main"
                                data-med="<?= htmlspecialchars($key) ?>"
                                style="cursor:pointer;">

                                <td>
                                    <?= htmlspecialchars($medName) ?><br>
                                    <small class="text-muted"><?= htmlspecialchars($brandName) ?></small>
                                </td>

                                <td><?= $totalStock ?></td>
                                <td><?= formatMonthYear($earliestExpiry) ?></td>
                                <td>
                                    <span class="badge <?= $status == 'Available' ? 'bg-success' : ($status == 'Near Expiry' ? 'bg-warning text-dark' : 'bg-danger') ?>">
                                        <?= $status ?>
                                    </span>
                                </td>
                                <td></td>
                            </tr>

                            <!-- Hidden Batch Rows -->
                            <?php foreach ($batches as $batch): ?>
                                <tr class="batch-row" data-med="<?= htmlspecialchars($key) ?>" style="display:none;">
                                    <td style="padding-left:30px;">— Batch <?= $batch['batch_no'] ?? 'N/A' ?></td>
                                    <td><?= $batch['stock_quantity'] ?></td>
                                    <td><?= formatMonthYear($batch['expiry_date']) ?></td>
                                    <td>
                                        <span class="badge <?= $batch['status'] == 'Available' ? 'bg-success' : ($batch['status'] == 'Near Expiry' ? 'bg-warning text-dark' : 'bg-danger') ?>">
                                            <?= $batch['status'] ?>
                                        </span>
                                    </td>

                                </tr>
                            <?php endforeach; ?>

                        <?php endforeach; ?>
                    </tbody>
                </table>

                <!-- Bootstrap Pagination -->
                <nav aria-label="Medicine table pagination">
                    <ul class="pagination justify-content-center" id="pagination"></ul>
                </nav>

                <script>
                    const rowsPerPage = 15;
                    let currentPage = 1;

                    function paginateTable() {
                        const rows = document.querySelectorAll('#medicineExpiryTable tbody tr.medicine-main');
                        const totalRows = rows.length;
                        const totalPages = Math.ceil(totalRows / rowsPerPage);

                        // Hide all rows
                        rows.forEach(row => {
                            row.style.display = "none";
                            document.querySelectorAll(`.batch-row[data-med="${row.dataset.med}"]`)
                                .forEach(batch => batch.style.display = "none");
                        });

                        // Show only rows for current page
                        const start = (currentPage - 1) * rowsPerPage;
                        const end = start + rowsPerPage;
                        for (let i = start; i < end && i < totalRows; i++) {
                            rows[i].style.display = "";
                        }

                        // Build pagination
                        const pagination = document.getElementById("pagination");
                        pagination.innerHTML = "";

                        // Previous button
                        const prevItem = document.createElement("li");
                        prevItem.className = "page-item" + (currentPage === 1 ? " disabled" : "");
                        prevItem.innerHTML = `
            <a class="page-link" href="#" aria-label="Previous">
                <span aria-hidden="true">&laquo;</span>
            </a>`;
                        prevItem.addEventListener("click", e => {
                            e.preventDefault();
                            if (currentPage > 1) {
                                currentPage--;
                                paginateTable();
                            }
                        });
                        pagination.appendChild(prevItem);

                        // Page numbers
                        for (let i = 1; i <= totalPages; i++) {
                            const li = document.createElement("li");
                            li.className = "page-item" + (i === currentPage ? " active" : "");
                            li.innerHTML = `<a class="page-link" href="#">${i}</a>`;
                            li.addEventListener("click", e => {
                                e.preventDefault();
                                currentPage = i;
                                paginateTable();
                            });
                            pagination.appendChild(li);
                        }

                        // Next button
                        const nextItem = document.createElement("li");
                        nextItem.className = "page-item" + (currentPage === totalPages ? " disabled" : "");
                        nextItem.innerHTML = `
            <a class="page-link" href="#" aria-label="Next">
                <span aria-hidden="true">&raquo;</span>
            </a>`;
                        nextItem.addEventListener("click", e => {
                            e.preventDefault();
                            if (currentPage < totalPages) {
                                currentPage++;
                                paginateTable();
                            }
                        });
                        pagination.appendChild(nextItem);
                    }

                    // Initial load
                    paginateTable();

                    // Toggle batch rows
                    document.querySelectorAll('.medicine-main').forEach(row => {
                        row.addEventListener('click', () => {
                            const medName = row.dataset.medName;
                            document.querySelectorAll(`.batch-row[data-med="${row.dataset.med}"]`)
                                .forEach(batchRow => {
                                    batchRow.style.display = batchRow.style.display === 'none' ? '' : 'none';
                                });
                        });
                    });
                </script>





            </div>
        </div>

        <!-- END CODING HERE -->
    </div>
    <!----- End of Main Content ----->
    </div>

    <script>
        const searchInput = document.getElementById("searchInput");
        const statusFilter = document.getElementById("statusFilter");

        function filterTable() {
            const searchValue = searchInput.value.toLowerCase();
            const filterValue = statusFilter.value;

            document.querySelectorAll("#medicineExpiryTable tbody tr.medicine-main").forEach(row => {
                const medName = row.querySelector("td").innerText.toLowerCase(); // medicine name
                const status = row.querySelector("td span").innerText.trim(); // status badge text

                // check search + filter
                const matchesSearch = medName.includes(searchValue);
                const matchesFilter = (filterValue === "All" || status === filterValue);

                if (matchesSearch && matchesFilter) {
                    row.style.display = ""; // show medicine row
                } else {
                    row.style.display = "none"; // hide medicine row
                }

                // always hide batch rows here → don't reveal them by search/filter
                const medNameAttr = row.dataset.medName;
                document.querySelectorAll(`.batch-row[data-med="${medNameAttr}"]`)
                    .forEach(batch => batch.style.display = "none");
            });
        }

        searchInput.addEventListener("keyup", filterTable);
        statusFilter.addEventListener("change", filterTable);
    </script>



    <script>
        const toggler = document.querySelector(".toggler-btn");
        toggler.addEventListener("click", function() {
            document.querySelector("#sidebar").classList.toggle("collapsed");
        });
    </script>
    <script src="../assets/Bootstrap/all.min.js"></script>
    <script src="../assets/Bootstrap/bootstrap.bundle.min.js"></script>
    <script src="../assets/Bootstrap/fontawesome.min.js"></script>
    <script src="../assets/Bootstrap/jq.js"></script>
</body>

</html>