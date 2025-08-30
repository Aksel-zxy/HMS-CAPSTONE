<?php
include '../../SQL/config.php';

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
                    <!-- Tabs -->
                    <ul class="nav nav-tabs custom-tabs" id="prescriptionTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active d-flex align-items-center gap-2"
                                id="prescription-tab"
                                data-bs-toggle="tab"
                                data-bs-target="#prescription"
                                type="button" role="tab">
                                <i class="fa-solid fa-capsules"></i>

                                <span>Prescription</span>
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link d-flex align-items-center gap-2"
                                id="record-tab"
                                data-bs-toggle="tab"
                                data-bs-target="#record"
                                type="button" role="tab">
                                <i class="fa-solid fa-notes-medical"></i>
                                <span>Records</span>
                            </button>
                        </li>
                    </ul>
                </div>



                <!-- Tab Content -->
                <div class="tab-content mt-3" id="prescriptionTabsContent">

                    <!-- Prescription Tab -->
                    <div class="tab-pane fade show active" id="prescription" role="tabpanel">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Prescription ID</th>
                                    <th>Doctor</th>
                                    <th>Patient</th>
                                    <th>Medicines</th>
                                    <th>Total Quantity</th>
                                    <th>Quantity Dispensed</th>
                                    <th>Status</th>
                                    <th>Note</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                // Pending prescriptions
                                $sql_prescriptions = "
                    SELECT 
                        p.prescription_id,
                        CONCAT(e.first_name, ' ', e.last_name) AS doctor_name,
                        CONCAT(pi.fname, ' ', pi.lname) AS patient_name,
                        GROUP_CONCAT(
                            CONCAT(m.med_name, ' (', i.dosage, ') - Qty: ', i.quantity_prescribed)
                            SEPARATOR '<br>'
                        ) AS medicines_list,
                        SUM(i.quantity_prescribed) AS total_quantity,
                        SUM(i.quantity_dispensed) AS total_dispensed,
                        p.status,
                        p.note,
                        DATE_FORMAT(p.prescription_date, '%b %e, %Y %l:%i%p') AS formatted_date
                    FROM pharmacy_prescription p
                    JOIN patientinfo pi ON p.patient_id = pi.patient_id
                    JOIN hr_employees e ON p.doctor_id = e.employee_id
                    JOIN pharmacy_prescription_items i ON p.prescription_id = i.prescription_id
                    JOIN pharmacy_inventory m ON i.med_id = m.med_id
                    WHERE p.status = 'Pending' AND LOWER(e.profession) = 'doctor'
                    GROUP BY p.prescription_id
                    ORDER BY p.prescription_date DESC
                ";

                                $pending_result = $conn->query($sql_prescriptions);

                                if ($pending_result && $pending_result->num_rows > 0) {
                                    while ($row = $pending_result->fetch_assoc()) {
                                        $noteId = "noteModal" . $row['prescription_id'];
                                        $prescriptionId = $row['prescription_id'];
                                        $status = $row['status'];
                                ?>
                                        <tr <?= ($status === 'Dispensed' || $status === 'Cancelled') ? 'style="opacity:0.6;"' : ''; ?>>
                                            <td><?= $row['prescription_id']; ?></td>
                                            <td><?= $row['doctor_name']; ?></td>
                                            <td><?= $row['patient_name']; ?></td>
                                            <td><?= $row['medicines_list']; ?></td>
                                            <td><?= $row['total_quantity']; ?></td>
                                            <td><?= $row['total_dispensed']; ?></td>
                                            <td>
                                                <select class="form-select form-select-sm"
                                                    onchange="handleStatusChange(this, <?= $prescriptionId; ?>, '<?= $status; ?>')"
                                                    <?= ($status === 'Dispensed' || $status === 'Cancelled') ? 'disabled' : ''; ?>>
                                                    <option value="Pending" <?= ($status == 'Pending' ? 'selected' : ''); ?>>Pending</option>
                                                    <option value="Dispensed" <?= ($status == 'Dispensed' ? 'selected' : ''); ?>>Dispensed</option>
                                                    <option value="Cancelled" <?= ($status == 'Cancelled' ? 'selected' : ''); ?>>Cancelled</option>
                                                </select>
                                            </td>
                                            <td>
                                                <button class="btn btn-info btn-sm" data-bs-toggle="modal" data-bs-target="#<?= $noteId; ?>">View Note</button>
                                            </td>
                                            <td><?= $row['formatted_date']; ?></td>
                                        </tr>

                                        <!-- Modal for prescription note -->
                                        <div class="modal fade" id="<?= $noteId; ?>" tabindex="-1">
                                            <div class="modal-dialog">
                                                <div class="modal-content">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title">Prescription Note</h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <p><?= nl2br(htmlspecialchars($row['note'])); ?></p>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                <?php
                                    }
                                } else {
                                    echo "<tr><td colspan='9' class='text-center'>No prescriptions found</td></tr>";
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Record Tab -->
                    <div class="tab-pane fade" id="record" role="tabpanel">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Prescription ID</th>
                                    <th>Doctor</th>
                                    <th>Patient</th>
                                    <th>Medicines</th>
                                    <th>Total Quantity</th>
                                    <th>Quantity Dispensed</th>
                                    <th>Status</th>
                                    <th>Note</th>
                                    <th>Dispensed Date</th>
                                    <th>Download</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                // Dispensed / Cancelled prescriptions
                                $sql_records = "
                SELECT 
                    p.prescription_id,
                    CONCAT(e.first_name, ' ', e.last_name) AS doctor_name,
                    CONCAT(pi.fname, ' ', pi.lname) AS patient_name,
                    GROUP_CONCAT(
                        CONCAT(m.med_name, ' (', i.dosage, ') - Qty: ', i.quantity_prescribed)
                        SEPARATOR '<br>'
                    ) AS medicines_list,
                    SUM(i.quantity_prescribed) AS total_quantity,
                    SUM(i.quantity_dispensed) AS total_dispensed,
                    p.status,
                    p.note,
                    DATE_FORMAT(MAX(i.dispensed_date), '%b %e, %Y %l:%i%p') AS dispensed_date
                FROM pharmacy_prescription p
                JOIN patientinfo pi ON p.patient_id = pi.patient_id
                JOIN hr_employees e ON p.doctor_id = e.employee_id
                JOIN pharmacy_prescription_items i ON p.prescription_id = i.prescription_id
                JOIN pharmacy_inventory m ON i.med_id = m.med_id
                WHERE p.status IN ('Dispensed', 'Cancelled') AND LOWER(e.profession) = 'doctor'
                GROUP BY p.prescription_id
                ORDER BY MAX(i.dispensed_date) DESC
            ";

                                $records_result = $conn->query($sql_records);

                                if ($records_result && $records_result->num_rows > 0) {
                                    while ($row = $records_result->fetch_assoc()) {
                                        $noteId = "noteModal" . $row['prescription_id'];
                                        $prescriptionId = $row['prescription_id'];
                                        $status = $row['status'];
                                ?>
                                        <tr <?= ($status === 'Dispensed' || $status === 'Cancelled') ? 'style="opacity:0.6;"' : ''; ?>>
                                            <td><?= $row['prescription_id']; ?></td>
                                            <td><?= $row['doctor_name']; ?></td>
                                            <td><?= $row['patient_name']; ?></td>
                                            <td><?= $row['medicines_list']; ?></td>
                                            <td><?= $row['total_quantity']; ?></td>
                                            <td><?= $row['total_dispensed']; ?></td>
                                            <td>
                                                <select class="form-select form-select-sm"
                                                    onchange="handleStatusChange(this, <?= $prescriptionId; ?>, '<?= $status; ?>')"
                                                    <?= ($status === 'Dispensed' || $status === 'Cancelled') ? 'disabled' : ''; ?>>
                                                    <option value="Pending" <?= ($status == 'Pending' ? 'selected' : ''); ?>>Pending</option>
                                                    <option value="Dispensed" <?= ($status == 'Dispensed' ? 'selected' : ''); ?>>Dispensed</option>
                                                    <option value="Cancelled" <?= ($status == 'Cancelled' ? 'selected' : ''); ?>>Cancelled</option>
                                                </select>
                                            </td>
                                            <td>
                                                <button class="btn btn-info btn-sm" data-bs-toggle="modal" data-bs-target="#<?= $noteId; ?>">View Note</button>
                                            </td>
                                            <td><?= $row['dispensed_date']; ?></td>
                                            <td>
                                                <a href="download_prescription.php?id=<?= $prescriptionId; ?>" class="btn btn-info btn-sm" target="_blank">
                                                    <i class="fa-solid fa-download"></i>
                                                </a>
                                            </td>
                                        </tr>

                                        <!-- Modal for prescription note -->
                                        <div class="modal fade" id="<?= $noteId; ?>" tabindex="-1">
                                            <div class="modal-dialog">
                                                <div class="modal-content">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title">Prescription Note</h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <p><?= nl2br(htmlspecialchars($row['note'])); ?></p>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                <?php
                                    }
                                } else {
                                    echo "<tr><td colspan='10' class='text-center'>No prescriptions found</td></tr>";
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>

                </div>

            </div>

            <!-- END CODING HERE -->
        </div>
        <!----- End of Main Content ----->
    </div>
    <script>
        function handleStatusChange(selectEl, prescriptionId, oldStatus) {
            const newStatus = selectEl.value;

            // Prevent changing if old status is Dispensed or Cancelled
            if (oldStatus === 'Dispensed' || oldStatus === 'Cancelled') {
                alert("Status cannot be changed once it is Dispensed or Cancelled.");
                selectEl.value = oldStatus;
                return;
            }

            // If new status is same as old, do nothing
            if (newStatus === oldStatus) return;

            if (!confirm("Are you sure you want to change status to " + newStatus + "?")) {
                selectEl.value = oldStatus; // revert if cancelled
                return;
            }

            fetch('update_prescription.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: 'prescription_id=' + prescriptionId + '&status=' + newStatus
                })
                .then(res => res.json())
                .then(data => {
                    if (data.error) {
                        alert(data.error);
                        selectEl.value = oldStatus;
                    } else if (data.success) {
                        alert(data.success);

                        const row = selectEl.closest('tr');

                        // ✅ If Dispensed or Cancelled → move row to Records tab
                        if (newStatus === 'Dispensed' || newStatus === 'Cancelled') {
                            selectEl.disabled = true;

                            // Remove row from current table
                            row.parentNode.removeChild(row);

                            // Append to Records table
                            const recordTable = document.querySelector("#record table tbody");
                            if (recordTable) {
                                recordTable.appendChild(row);
                                row.style.opacity = "0.8"; // faded
                            }
                        }

                        // ✅ If it’s still Pending → keep in Prescription tab
                        if (newStatus === 'Pending') {
                            const prescriptionTable = document.querySelector("#prescription table tbody");
                            if (prescriptionTable && row.closest("#record")) {
                                // If somehow it’s in Records, move it back
                                row.parentNode.removeChild(row);
                                prescriptionTable.appendChild(row);
                                row.style.opacity = "1";
                            }
                        }

                        // Update Quantity Dispensed if returned
                        if (data.dispensed_quantity) {
                            row.querySelector('td:nth-child(6)').textContent = data.dispensed_quantity;
                        }
                    }
                })
                .catch(err => {
                    alert("An error occurred: " + err);
                    selectEl.value = oldStatus;
                });
        }
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