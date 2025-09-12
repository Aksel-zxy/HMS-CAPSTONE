<?php
include '../../SQL/config.php';
require_once 'classes/Medicine.php';
if (!isset($_SESSION['pharmacy']) || $_SESSION['pharmacy'] !== true) {
    header('Location: login.php'); // Redirect to login if not logged in
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

$medicineObj = new Medicine($conn);
$data = $medicineObj->getAllBatches();
$grouped = [];
foreach ($data as $batch) {
    $grouped[$batch['med_name']][] = $batch;
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
    <link rel="stylesheet" href="assets/CSS/med_inventory.css">
</head>

<body>
    <div class="d-flex">
        <!----- Sidebar ----->
        <aside id="sidebar" class="sidebar-toggle">

            <div class="sidebar-logo mt-3">
                <img src="assets/image/logo-dark.png" width="90px" height="20px">
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
                <a class="sidebar-link" data-bs-toggle="collapse" href="#prescriptionMenu" role="button" aria-expanded="false" aria-controls="prescriptionMenu">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="fa-solid fa-file-prescription" viewBox="0 0 16 16">
                        <path d="m7.646 9.354-3.792 3.792a.5.5 0 0 0 .353.854h7.586a.5.5 0 0 0 .354-.854L8.354 9.354a.5.5 0 0 0-.708 0" />
                        <path d="M11.414 11H14.5a.5.5 0 0 0 .5-.5v-7a.5.5 0 0 0-.5-.5h-13a.5.5 0 0 0-.5.5v7a.5.5 0 0 0 .5.5h3.086l-1 1H1.5A1.5 1.5 0 0 1 0 10.5v-7A1.5 1.5 0 0 1 1.5 2h13A1.5 1.5 0 0 1 16 3.5v7a1.5 1.5 0 0 1-1.5 1.5h-2.086z" />
                    </svg>
                    <span style="font-size: 18px;">Prescription</span>
                </a>
                <ul class="collapse list-unstyled ms-3" id="prescriptionMenu">
                    <li>
                        <a href="pharmacy_prescription.php" class="sidebar-link">View Prescriptions</a>
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
            <li class="sidebar-item">
                <a href="pharmacy_expiry_tracking.php" class="sidebar-link" data-bs-toggle="#" data-bs-target="#"
                    aria-expanded="false" aria-controls="auth">
                    <i class="fa-solid fa-calendar-check"></i>
                    <span style="font-size: 18px;">Drug Expiry Tracking</span>
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
                    <div class="dropdown d-flex align-items-center">
                        <span class="username ml-1 me-2"><?php echo $user['fname']; ?> <?php echo $user['lname']; ?></span><!-- Display the logged-in user's name -->
                        <button class="btn dropdown-toggle" type="button" id="dropdownMenuButton" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-person-circle"></i>
                        </button>
                        <ul class="dropdown-menu" aria-labelledby="dropdownMenuButton" style="min-width: 200px; padding: 10px; border-radius: 5px; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); background-color: #fff; color: #333;">
                            <li style="margin-bottom: 8px; font-size: 14px; color: #555;">
                                <span>Welcome <strong style="color: #007bff;"><?php echo $user['lname']; ?></strong>!</span>
                            </li>
                            <li>
                                <a class="dropdown-item" href="../logout.php" style="font-size: 14px; color: #007bff; text-decoration: none; padding: 8px 12px; border-radius: 4px; transition: background-color 0.3s ease;">
                                    Logout
                                </a>
                            </li>
                        </ul>

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
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($grouped as $medName => $batches): ?>
                            <?php
                            $totalStock = array_sum(array_column($batches, 'stock_quantity'));
                            $earliestExpiry = $batches[0]['expiry_date'] ?? '-';
                            $status = $batches[0]['status'] ?? 'Available';
                            ?>
                            <!-- Main Medicine Row -->
                            <tr class="medicine-main" data-med-name="<?= htmlspecialchars($medName) ?>" style="cursor:pointer;">
                                <td><strong><?= htmlspecialchars($medName) ?></strong></td>
                                <td><?= $totalStock ?></td>
                                <td><?= formatMonthYear($earliestExpiry) ?></td>
                                <td>
                                    <span class="badge <?= $status == 'Available' ? 'bg-success' : ($status == 'Near Expiry' ? 'bg-warning text-dark' : 'bg-danger') ?>">
                                        <?= $status ?>
                                    </span>
                                </td>
                            </tr>

                            <!-- Hidden Batch Rows -->
                            <?php foreach ($batches as $batch): ?>
                                <tr class="batch-row" data-med="<?= htmlspecialchars($medName) ?>" style="display:none;">
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
                            document.querySelectorAll(`.batch-row[data-med="${row.dataset.medName}"]`)
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
                            document.querySelectorAll(`.batch-row[data-med="${medName}"]`).forEach(batchRow => {
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