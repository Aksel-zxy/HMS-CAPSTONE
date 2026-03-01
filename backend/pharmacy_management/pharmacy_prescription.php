<?php
include '../../SQL/config.php';
require_once "classes/notification.php";

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


// 🔔 Pending prescriptions count
$notif_sql = "SELECT COUNT(*) AS pending 
              FROM pharmacy_prescription 
              WHERE status = 'Pending'";
$notif_res = $conn->query($notif_sql);

$pendingCount = 0;
if ($notif_res && $notif_res->num_rows > 0) {
    $notif_row = $notif_res->fetch_assoc();
    $pendingCount += (int)$notif_row['pending'];
}

// 🔔 Pending Scheduled prescriptions count
$sched_notif_sql = "SELECT COUNT(*) AS pending 
                    FROM scheduled_medications 
                    WHERE status IN ('pending', 'ongoing')";
$sched_notif_res = $conn->query($sched_notif_sql);

if ($sched_notif_res && $sched_notif_res->num_rows > 0) {
    $sched_notif_row = $sched_notif_res->fetch_assoc();
    $pendingCount += (int)$sched_notif_row['pending'];
}

$notif = new Notification($conn);
$latestNotifications = $notif->load();
$notifCount = $notif->notifCount;
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
    <!-- FontAwesome -->
    <link rel="stylesheet" href="assets/Bootstrap/all.min.css">
    <link rel="stylesheet" href="assets/Bootstrap/fontawesome.min.css">

    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">


    <link rel="stylesheet" href="assets/CSS/prescription.css">
</head>

<body>
    <div class="d-flex">
        <!----- Sidebar ----->
        <aside id="sidebar" class="sidebar-toggle">

            <div class="sidebar-logo mt-3">
                <img src="assets/image/logo-dark.png" width="90px" height="20px">
            </div>

            <div class="menu-title">Pharmacy Management | Prescription Records</div>

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

                <ul id="gerald" class="sidebar-dropdown list-unstyled collapse" data-bs-parent="#sidebar">
                    <li class="sidebar-item">
                        <a href="pharmacy_inventory_report.php" class="sidebar-link">Inventory Report</a>
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
            <div id="alertContainer"></div>
            <!-- START CODING HERE -->
            <div class="content">
                <div class="title-container d-flex justify-content-between align-items-center mb-3">

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
                                id="scheduled-tab"
                                data-bs-toggle="tab"
                                data-bs-target="#scheduled"
                                type="button" role="tab">
                                <i class="fa-solid fa-calendar-check"></i>
                                <span>Inpatient Schedule Prescription</span>
                            </button>
                        </li>
                    </ul>

                    <!-- View Records Button on Right -->
                    <div>
                        <button class="btn btn-dark" data-bs-toggle="modal" data-bs-target="#recordsModal">
                            <i class="fa-solid fa-notes-medical"></i> View Records
                        </button>
                    </div>

                </div>




                <!-- Tab Content -->
                <div class="tab-content mt-3" id="prescriptionTabsContent">

                    <!-- Prescription Tab -->
                    <div class="tab-pane fade show active" id="prescription" role="tabpanel">
                        <table class="table" id="prescriptionTable">
                            <thead>
                                <tr>
                                    <th>Prescription ID</th>
                                    <th>Doctor</th>
                                    <th>Patient</th>
                                    <th>Medicines</th>
                                    <th>Total Quantity</th>
                                    <th>Quantity Dispensed</th>
                                    <th>Status</th>
                                    <th>Payment Type</th> <!-- NEW COLUMN -->
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
                    p.payment_type, -- FETCH PAYMENT TYPE
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
                                                <select class="form-select form-select-sm"
                                                    data-old="<?= $row['payment_type']; ?>"
                                                    onchange="handlePaymentTypeChange(this, <?= $prescriptionId; ?>)"
                                                    <?= ($status === 'Dispensed' || $status === 'Cancelled') ? 'disabled' : ''; ?>>
                                                    <option value="cash" <?= ($row['payment_type'] === 'cash' ? 'selected' : ''); ?>>Cash</option>
                                                    <option value="post_discharged" <?= ($row['payment_type'] === 'post_discharged' ? 'selected' : ''); ?>>Post-Discharge</option>
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
                                    echo "<tr><td colspan='10' class='text-center'>No prescriptions found</td></tr>";
                                }
                                ?>
                            </tbody>
                        </table>
                        <!-- Pagination -->
                        <nav aria-label="Prescription pagination">
                            <ul class="pagination justify-content-center" id="prescriptionPagination"></ul>
                        </nav>
                    </div>

                    <!-- Scheduled Medications Tab -->
                    <div class="tab-pane fade" id="scheduled" role="tabpanel">



                        <table class="table table-striped table-hover" id="scheduledTable">
                            <thead class="table">
                                <tr>
                                    <th>Schedule ID</th>
                                    <th>Patient</th>
                                    <th>Doctor</th>
                                    <th>Medicine</th>
                                    <th>Dosage</th>
                                    <th>Frequency</th>
                                    <th>Route</th>
                                    <th>Duration</th>
                                    <th>Start</th>
                                    <th>End</th>
                                    <th>Doses</th>
                                    <th>Action</th>
                                    <th>Instructions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $sql_scheduled = "
        SELECT 
            sm.*,
            CONCAT(pi.fname, ' ', pi.lname) AS patient_name,
            CONCAT(e.first_name, ' ', e.last_name) AS doctor_name,
            CONCAT(m.generic_name, ' (', m.brand_name, ')') AS medicine_name,
            m.stock_quantity,
            IFNULL(COUNT(sml.log_id),0) AS doses_given
        FROM scheduled_medications sm
        JOIN patientinfo pi ON sm.patient_id = pi.patient_id
        JOIN hr_employees e ON sm.doctor_id = e.employee_id
        JOIN pharmacy_inventory m ON sm.med_id = m.med_id
        LEFT JOIN scheduled_medication_logs sml ON sm.schedule_id = sml.schedule_id
        GROUP BY sm.schedule_id
        ORDER BY sm.start_date ASC
        ";


                                $scheduled_result = $conn->query($sql_scheduled);

                                if ($scheduled_result && $scheduled_result->num_rows > 0) {
                                    while ($row = $scheduled_result->fetch_assoc()) {

                                        $frequency = strtolower(trim($row['frequency']));
                                        $duration = (int)$row['duration_days'];
                                        $doses_given = (int)$row['doses_given'];
                                        $stock_quantity = (int)$row['stock_quantity'];

                                        $doses_per_day = 1; // default

                                        // -----------------------------
                                        // 1️⃣ Specific times of day (e.g., "9 AM & 9 PM")
                                        // -----------------------------
                                        if (preg_match_all('/(\d{1,2}\s*(am|pm))/i', $frequency, $matches)) {
                                            $doses_per_day = count($matches[0]);

                                            // -----------------------------
                                            // 2️⃣ Interval-based (e.g., "every 6 hours")
                                            // -----------------------------
                                        } elseif (preg_match('/every\s+(\d+)\s*hours?/', $frequency, $matches)) {
                                            $hours = (int)$matches[1];
                                            $doses_per_day = ($hours > 0 && $hours <= 24) ? floor(24 / $hours) : 1;

                                            // -----------------------------
                                            // 3️⃣ X times a day (e.g., "5x a day", "twice a day")
                                            // -----------------------------
                                        } elseif (preg_match('/^(\d+)\s*(x|times)\s*(a day|per day)$/i', $frequency, $matches)) {
                                            $doses_per_day = (int)$matches[1];

                                            // -----------------------------
                                            // 4️⃣ Fallback phrases
                                            // -----------------------------
                                        } else {
                                            switch ($frequency) {
                                                case 'twice a day':
                                                    $doses_per_day = 2;
                                                    break;
                                                case 'three times a day':
                                                    $doses_per_day = 3;
                                                    break;
                                                default:
                                                    $doses_per_day = 1;
                                                    break;
                                            }
                                        }

                                        // Total doses = doses per day × duration
                                        $total_doses = $doses_per_day * $duration;
                                        $remaining = $total_doses - $doses_given;

                                        // Next dose timing (simplified for X per day / interval-based)
                                        $interval_hours = ($doses_per_day > 0) ? 24 / $doses_per_day : 24;
                                        $start_timestamp = strtotime($row['start_date']);
                                        $next_dose_time = strtotime("+" . ($doses_given * $interval_hours) . " hours", $start_timestamp);
                                        $allow_time = strtotime("-5 minutes", $next_dose_time);
                                        $current_time = time();

                                        $can_dispense = ($remaining > 0 && $stock_quantity > 0 && $current_time >= $allow_time);
                                ?>
                                        <tr>
                                            <td><?= $row['schedule_id']; ?></td>
                                            <td><?= htmlspecialchars($row['patient_name']); ?></td>
                                            <td><?= htmlspecialchars($row['doctor_name']); ?></td>
                                            <td><?= htmlspecialchars($row['medicine_name']); ?></td>
                                            <td><?= htmlspecialchars($row['dosage']); ?></td>
                                            <td><?= htmlspecialchars($row['frequency']); ?></td>
                                            <td><?= htmlspecialchars($row['route']); ?></td>
                                            <td><?= $duration; ?> days</td>
                                            <td><?= date('M d, Y h:i A', strtotime($row['start_date'])); ?></td>
                                            <td><?= date('M d, Y h:i A', strtotime($row['end_date'])); ?></td>
                                            <td>
                                                <strong><?= $doses_given; ?></strong> / <?= $total_doses; ?><br>
                                                <small class="text-muted">Remaining: <?= $remaining; ?></small>
                                            </td>
                                            <td>
                                                <?php if ($remaining <= 0): ?>
                                                    <span class="badge bg-success">Completed</span>
                                                <?php elseif (!$can_dispense): ?>
                                                    <button class="btn btn-secondary btn-sm" disabled>Not Yet Time</button>
                                                <?php elseif ($stock_quantity <= 0): ?>
                                                    <button class="btn btn-danger btn-sm" disabled>No Stock</button>
                                                <?php else: ?>
                                                    <button type="button" class="btn btn-success btn-sm" onclick="dispenseScheduled(<?= $row['schedule_id']; ?>, this)">
                                                        Dispense Dose
                                                    </button>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <button class="btn btn-info btn-sm" data-bs-toggle="modal" data-bs-target="#modal<?= $row['schedule_id']; ?>">View</button>
                                            </td>
                                        </tr>

                                        <!-- Instructions Modal -->
                                        <div class="modal fade" id="modal<?= $row['schedule_id']; ?>" tabindex="-1">
                                            <div class="modal-dialog modal-dialog-scrollable">
                                                <div class="modal-content">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title">Special Instructions</h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <p><?= nl2br(htmlspecialchars($row['special_instructions'])); ?></p>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                <?php
                                    } // end while
                                } else {
                                    echo "<tr><td colspan='14' class='text-center'>No scheduled medications found</td></tr>";
                                }
                                ?>
                            </tbody>
                        </table>

                    </div>
                    <div class="modal fade" id="recordsModal" tabindex="-1">
                        <div class="modal-dialog modal-custom modal-dialog-scrollable">
                            <div class="modal-content">

                                <div class="modal-header">
                                    <h5 class="modal-title">Pharmacy Records</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>

                                <div class="modal-body">

                                    <!-- Inner Tabs -->
                                    <ul class="nav nav-tabs mb-3" id="recordTabs">
                                        <li class="nav-item">
                                            <button class="nav-link active"
                                                data-bs-toggle="tab"
                                                data-bs-target="#prescriptionRecords">
                                                Prescription Records
                                            </button>
                                        </li>
                                        <li class="nav-item">
                                            <button class="nav-link"
                                                data-bs-toggle="tab"
                                                data-bs-target="#scheduledRecords">
                                                Scheduled Prescription Records
                                            </button>
                                        </li>
                                    </ul>

                                    <div class="tab-content">

                                        <!-- ===================== -->
                                        <!-- PRESCRIPTION RECORDS -->
                                        <!-- ===================== -->
                                        <div class="tab-pane fade show active" id="prescriptionRecords" style="text-wrap: nowrap;">

                                            <table class="table" id="recordTable">
                                                <thead>
                                                    <tr>
                                                        <th>Prescription ID</th>
                                                        <th>Doctor</th>
                                                        <th>Patient</th>
                                                        <th>Medicines</th>
                                                        <th>Total Quantity</th>
                                                        <th>Quantity Dispensed</th>
                                                        <th>Status</th>
                                                        <th>Payment Type</th>
                                                        <th>Note</th>
                                                        <th>Dispensed Date</th>
                                                        <th>Download</th>
                                                        <th>Dispensed By</th>
                                                    </tr>
                                                </thead>
                                                <tbody> <?php $sql_records = "SELECT p.prescription_id, CONCAT(e.first_name, ' ', e.last_name) AS doctor_name, CONCAT(pi.fname, ' ', pi.lname) AS patient_name, GROUP_CONCAT( CONCAT(m.med_name, ' (', i.dosage, ') - Qty: ', i.quantity_prescribed) SEPARATOR '<br>' ) AS medicines_list, SUM(i.quantity_prescribed) AS total_quantity, SUM(i.quantity_dispensed) AS total_dispensed, MAX(p.status) AS status, GROUP_CONCAT( DISTINCT CASE WHEN i.dispensed_role = 'admin' THEN CONCAT(u.fname, ' ', u.lname) WHEN i.dispensed_role = 'pharmacist' THEN CONCAT(h.first_name, ' ', h.last_name) ELSE NULL END SEPARATOR ', ' ) AS staff_name, MAX(p.payment_type) AS payment_type, MAX(p.note) AS note, DATE_FORMAT(MAX(i.dispensed_date), '%b %e, %Y %l:%i%p') AS dispensed_date FROM pharmacy_prescription p JOIN patientinfo pi ON p.patient_id = pi.patient_id JOIN hr_employees e ON p.doctor_id = e.employee_id JOIN pharmacy_prescription_items i ON p.prescription_id = i.prescription_id JOIN pharmacy_inventory m ON i.med_id = m.med_id LEFT JOIN users u ON i.dispensed_by = u.user_id AND i.dispensed_role = 'admin' LEFT JOIN hr_employees h ON i.dispensed_by = h.employee_id AND i.dispensed_role = 'pharmacist' WHERE p.status IN ('Dispensed', 'Cancelled') GROUP BY p.prescription_id ORDER BY MAX(i.dispensed_date) DESC";
                                                        $records_result = $conn->query($sql_records);
                                                        if ($records_result && $records_result->num_rows > 0) {
                                                            while ($row = $records_result->fetch_assoc()) {
                                                                $noteId = "noteModal" . $row['prescription_id'];
                                                                $prescriptionId = $row['prescription_id'];
                                                                $status = $row['status']; ?> <tr style="opacity:0.6;">
                                                                <td><?= $row['prescription_id']; ?></td>
                                                                <td><?= $row['doctor_name']; ?></td>
                                                                <td><?= $row['patient_name']; ?></td>
                                                                <td><?= $row['medicines_list']; ?></td>
                                                                <td><?= $row['total_quantity']; ?></td>
                                                                <td><?= $row['total_dispensed']; ?></td>
                                                                <td><?= $row['status']; ?></td>
                                                                <td><?= ucfirst(str_replace('_', ' ', $row['payment_type'])); ?></td>
                                                                <td> <button class="btn btn-info btn-sm" data-bs-toggle="modal" data-bs-target="#<?= $noteId; ?>">View Note</button> </td>
                                                                <td><?= $row['dispensed_date']; ?></td>
                                                                <td> <a href="download_prescription.php?id=<?= $prescriptionId; ?>" class="btn btn-info btn-sm" target="_blank"> <i class="fa-solid fa-download"></i> </a> </td>
                                                                <td><?= $row['staff_name'] ?? 'N/A'; ?></td>
                                                            </tr> <!-- Modal for prescription note -->
                                                            <div class="modal fade" id="<?= $noteId; ?>" tabindex="-1">
                                                                <div class="modal-dialog">
                                                                    <div class="modal-content">
                                                                        <div class="modal-header">
                                                                            <h5 class="modal-title">Prescription Note</h5> <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                                        </div>
                                                                        <div class="modal-body">
                                                                            <p><?= nl2br(htmlspecialchars($row['note'])); ?></p>
                                                                        </div>
                                                                        <div class="modal-footer"> <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button> </div>
                                                                    </div>
                                                                </div>
                                                            </div> <?php }
                                                            } else {
                                                                echo "<tr><td colspan='12' class='text-center'>No prescriptions found</td></tr>";
                                                            } ?>
                                                </tbody>
                                            </table>

                                        </div>

                                        <!-- =============================== -->
                                        <!-- SCHEDULED PRESCRIPTION RECORDS -->
                                        <!-- =============================== -->
                                        <div class="tab-pane fade" id="scheduledRecords">

                                            <table class="table table-bordered table-sm">
                                                <thead>
                                                    <tr>
                                                        <th>Schedule ID</th>
                                                        <th>Patient</th>
                                                        <th>Doctor</th>
                                                        <th>Medicine</th>
                                                        <th>Frequency</th>
                                                        <th>Duration</th>
                                                        <th>Doses Given</th>
                                                        <th>Status</th>
                                                        <th>Start</th>
                                                        <th>End</th>
                                                    </tr>
                                                </thead>
                                                <tbody>

                                                    <?php
                                                    $sql_sched_records = "
SELECT 
    sm.schedule_id,
    CONCAT(pi.fname,' ',pi.lname) AS patient_name,
    CONCAT(e.first_name,' ',e.last_name) AS doctor_name,
    m.med_name,
    sm.frequency,
    sm.duration_days,
    sm.status,
    sm.start_date,
    sm.end_date,
    COUNT(sml.log_id) AS doses_given
FROM scheduled_medications sm
JOIN patientinfo pi ON sm.patient_id = pi.patient_id
JOIN hr_employees e ON sm.doctor_id = e.employee_id
JOIN pharmacy_inventory m ON sm.med_id = m.med_id
LEFT JOIN scheduled_medication_logs sml 
    ON sm.schedule_id = sml.schedule_id
WHERE sm.status IN ('completed','cancelled')
GROUP BY sm.schedule_id
ORDER BY sm.start_date ASC
";

                                                    $schedResult = $conn->query($sql_sched_records);

                                                    if ($schedResult && $schedResult->num_rows > 0) {
                                                        while ($row = $schedResult->fetch_assoc()) {
                                                    ?>
                                                            <tr>
                                                                <td><?= $row['schedule_id']; ?></td>
                                                                <td><?= $row['patient_name']; ?></td>
                                                                <td><?= $row['doctor_name']; ?></td>
                                                                <td><?= $row['med_name']; ?></td>
                                                                <td><?= $row['frequency']; ?></td>
                                                                <td><?= $row['duration_days']; ?> days</td>
                                                                <td><?= $row['doses_given']; ?></td>
                                                                <td><?= ucfirst($row['status']); ?></td>
                                                                <td><?= date('M d, Y h:i A', strtotime($row['start_date'])); ?></td>
                                                                <td><?= date('M d, Y h:i A', strtotime($row['end_date'])); ?></td>
                                                            </tr>
                                                    <?php
                                                        }
                                                    } else {
                                                        echo "<tr><td colspan='10' class='text-center'>No scheduled records found</td></tr>";
                                                    }
                                                    ?>

                                                </tbody>
                                            </table>

                                        </div>

                                    </div>
                                </div>

                            </div>
                        </div>
                    </div>

                </div>




            </div>

        </div>

        <!-- END CODING HERE -->
    </div>
    <!----- End of Main Content ----->
    </div>
    <!-- 🔎 Search Script -->
    <script>
        document.getElementById("recordSearchInput").addEventListener("keyup", function() {
            const searchValue = this.value.toLowerCase();
            const rows = document.querySelectorAll("#recordTable tbody tr");

            rows.forEach(row => {
                const patientName = row.cells[2].textContent.toLowerCase(); // Patient column
                row.style.display = patientName.includes(searchValue) ? "" : "none";
            });
        });

        function setupPagination(tableId, paginationId, rowsPerPage = 15) {
            let currentPage = 1;

            function paginate() {
                const table = document.getElementById(tableId);
                const rows = table.querySelectorAll("tbody tr");
                const totalRows = rows.length;
                const totalPages = Math.ceil(totalRows / rowsPerPage);

                // Hide all rows
                rows.forEach(row => row.style.display = "none");

                // Show only rows for current page
                const start = (currentPage - 1) * rowsPerPage;
                const end = start + rowsPerPage;
                for (let i = start; i < end && i < totalRows; i++) {
                    rows[i].style.display = "";
                }

                // Build pagination
                const pagination = document.getElementById(paginationId);
                pagination.innerHTML = "";

                // Prev button
                const prev = document.createElement("li");
                prev.className = "page-item" + (currentPage === 1 ? " disabled" : "");
                prev.innerHTML = `<a class="page-link" href="#">&laquo;</a>`;
                prev.addEventListener("click", e => {
                    e.preventDefault();
                    if (currentPage > 1) {
                        currentPage--;
                        paginate();
                    }
                });
                pagination.appendChild(prev);

                // Page numbers
                for (let i = 1; i <= totalPages; i++) {
                    const li = document.createElement("li");
                    li.className = "page-item" + (i === currentPage ? " active" : "");
                    li.innerHTML = `<a class="page-link" href="#">${i}</a>`;
                    li.addEventListener("click", e => {
                        e.preventDefault();
                        currentPage = i;
                        paginate();
                    });
                    pagination.appendChild(li);
                }

                // Next button
                const next = document.createElement("li");
                next.className = "page-item" + (currentPage === totalPages ? " disabled" : "");
                next.innerHTML = `<a class="page-link" href="#">&raquo;</a>`;
                next.addEventListener("click", e => {
                    e.preventDefault();
                    if (currentPage < totalPages) {
                        currentPage++;
                        paginate();
                    }
                });
                pagination.appendChild(next);
            }

            paginate();
        }

        // Initialize for both tabs
        document.addEventListener("DOMContentLoaded", function() {
            setupPagination("prescriptionTable", "prescriptionPagination", 15);
            setupPagination("recordTable", "recordPagination", 15);
        });
    </script>
    <script>
        function handleStatusChange(selectEl, prescriptionId, oldStatus) {
            const newStatus = selectEl.value;

            // 1️⃣ Prevent changing if already Dispensed or Cancelled
            if (oldStatus === 'Dispensed' || oldStatus === 'Cancelled') {
                Swal.fire('Cannot Change', "Status cannot be changed once it is Dispensed or Cancelled.", 'warning');
                selectEl.value = oldStatus;
                return;
            }

            // 2️⃣ Do nothing if status didn't change
            if (newStatus === oldStatus) return;

            // 3️⃣ Confirm change
            Swal.fire({
                title: 'Change Status?',
                text: `Are you sure you want to change status to "${newStatus}"?`,
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#00acc1',
                cancelButtonColor: '#6e768e',
                confirmButtonText: 'Yes, change it!'
            }).then((result) => {
                if (result.isConfirmed) {
                    Swal.fire({
                        title: 'Updating...',
                        allowOutsideClick: false,
                        didOpen: () => Swal.showLoading()
                    });

                    // 4️⃣ Send update to backend
                    fetch('update_prescription.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded'
                            },
                            body: `prescription_id=${prescriptionId}&status=${newStatus}`
                        })
                        .then(res => res.json())
                        .then(data => {
                            const row = selectEl.closest('tr');

                            if (data.error) {
                                Swal.fire('Error', data.error, 'error');
                                selectEl.value = oldStatus;
                                return;
                            }

                            // 5️⃣ Show success + warnings if any
                            let message = data.success || "Status updated successfully.";
                            let msgIcon = 'success';
                            if (data.warnings && data.warnings.length > 0) {
                                message += "<br><br><strong>Warnings:</strong><br><ul class='text-start mb-0'><li>" + data.warnings.join("</li><li>") + "</li></ul>";
                                msgIcon = 'warning';
                            }
                            Swal.fire({
                                title: 'Updated!',
                                html: message,
                                icon: msgIcon,
                                timer: 2000,
                                timerProgressBar: true
                            });

                            // 6️⃣ Update Quantity Dispensed if returned
                            if (data.dispensed_quantity !== undefined) {
                                const qtyCell = row.querySelector('td:nth-child(6)');
                                if (qtyCell) qtyCell.textContent = data.dispensed_quantity;
                            }

                            // 7️⃣ Move row between tabs based on new status
                            if (newStatus === 'Dispensed' || newStatus === 'Cancelled') {
                                // Disable select
                                selectEl.disabled = true;

                                // Replace payment type select with text
                                const paymentCell = row.querySelector('td:nth-child(8)');
                                const paymentSelect = paymentCell?.querySelector('select');
                                if (paymentSelect) {
                                    paymentCell.textContent = paymentSelect.value.charAt(0).toUpperCase() + paymentSelect.value.slice(1);
                                }

                                // Move to Record tab
                                const recordTable = document.querySelector("#record table tbody");
                                if (recordTable) {
                                    row.parentNode.removeChild(row);
                                    recordTable.appendChild(row);
                                    row.style.opacity = "0.8";
                                }
                            } else if (newStatus === 'Pending') {
                                // Move back to Prescription tab if status reverted
                                const prescriptionTable = document.querySelector("#prescription table tbody");
                                if (prescriptionTable && row.closest("#record")) {
                                    row.parentNode.removeChild(row);
                                    prescriptionTable.appendChild(row);
                                    row.style.opacity = "1";
                                }
                            }
                        })
                        .catch(err => {
                            Swal.fire('Error', "An error occurred: " + err, 'error');
                            selectEl.value = oldStatus;
                        });
                } else {
                    selectEl.value = oldStatus;
                }
            });
        }

        function handlePaymentTypeChange(selectEl, prescriptionId) {
            const newType = selectEl.value;
            const oldType = selectEl.getAttribute('data-old');

            if (newType === oldType) return;

            Swal.fire({
                title: 'Change Payment Type?',
                text: "Are you sure you want to change payment type to " + newType + "?",
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#00acc1',
                cancelButtonColor: '#6e768e',
                confirmButtonText: 'Yes, change it!'
            }).then((result) => {
                if (result.isConfirmed) {
                    Swal.fire({
                        title: 'Updating...',
                        allowOutsideClick: false,
                        didOpen: () => Swal.showLoading()
                    });

                    fetch('update_prescription.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded'
                            },
                            body: 'prescription_id=' + prescriptionId + '&payment_type=' + newType
                        })
                        .then(res => res.json())
                        .then(data => {
                            if (data.error) {
                                Swal.fire('Error', data.error, 'error');
                                selectEl.value = oldType;
                            } else if (data.success) {
                                Swal.fire({
                                    title: 'Updated!',
                                    text: data.success,
                                    icon: 'success',
                                    timer: 2000,
                                    timerProgressBar: true
                                });
                                selectEl.setAttribute('data-old', newType);
                            }
                        })
                        .catch(err => {
                            Swal.fire('Error', "An error occurred: " + err, 'error');
                            selectEl.value = oldType;
                        });
                } else {
                    selectEl.value = oldType;
                }
            });
        }
    </script>

    <script>
        function dispenseScheduled(scheduleId, btnEl) {
            Swal.fire({
                title: 'Dispense Medication?',
                text: "Are you sure you want to dispense this scheduled dose now?",
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#00acc1',
                cancelButtonColor: '#6e768e',
                confirmButtonText: 'Yes, Dispense',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    Swal.fire({
                        title: 'Dispensing...',
                        text: 'Please wait while we process the dispense.',
                        allowOutsideClick: false,
                        didOpen: () => {
                            Swal.showLoading()
                        }
                    });

                    fetch('dispensed_scheduled.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded'
                            },
                            body: 'schedule_id=' + scheduleId
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                Swal.fire({
                                    title: 'Dispensed!',
                                    html: `${data.success}<br><br><strong>Doses Given:</strong> ${data.doses_given} / ${data.total_doses}`,
                                    icon: 'success',
                                    confirmButtonColor: '#00acc1',
                                    timer: 2000,
                                    timerProgressBar: true
                                });

                                // Dynamically update the DOM without reloading!
                                const row = btnEl.closest('tr');
                                if (row) {
                                    // Update Doses cell (11th column = index 10)
                                    const dosesCell = row.cells[10];
                                    if (dosesCell) {
                                        dosesCell.innerHTML = `<strong>${data.doses_given}</strong> / ${data.total_doses}<br><small class="text-muted">Remaining: ${data.total_doses - data.doses_given}</small>`;
                                    }

                                    if (data.status === 'completed') {
                                        // Update action cell with badge
                                        const actionCell = row.cells[11];
                                        if (actionCell) {
                                            actionCell.innerHTML = '<span class="badge bg-success">Completed</span>';
                                        }

                                        // Move to scheduled records tab
                                        const recordTableBody = document.querySelector("#scheduledRecords table tbody");
                                        if (recordTableBody) {
                                            // The scheduled records table has different columns than the main table, so we extract and create a new row
                                            const newRow = document.createElement('tr');
                                            newRow.innerHTML = `
                                                <td>${row.cells[0].textContent}</td> <!-- Schedule ID -->
                                                <td>${row.cells[1].textContent}</td> <!-- Patient -->
                                                <td>${row.cells[2].textContent}</td> <!-- Doctor -->
                                                <td>${row.cells[3].textContent}</td> <!-- Medicine -->
                                                <td>${row.cells[5].textContent}</td> <!-- Frequency -->
                                                <td>${row.cells[7].textContent}</td> <!-- Duration -->
                                                <td>${data.doses_given}</td>         <!-- Doses Given -->
                                                <td>Completed</td>                   <!-- Status -->
                                                <td>${row.cells[8].textContent}</td> <!-- Start Date -->
                                                <td>${row.cells[9].textContent}</td> <!-- End Date -->
                                            `;
                                            recordTableBody.appendChild(newRow);
                                            row.remove(); // Remove from Pending Scheduled tab
                                        }
                                    } else {
                                        // Not yet completed, but current dose was taken.
                                        // Update button to "Not Yet Time" to prevent double clicking until time passes.
                                        btnEl.disabled = true;
                                        btnEl.className = "btn btn-secondary btn-sm";
                                        btnEl.textContent = "Not Yet Time";
                                    }
                                }
                            } else if (data.error) {
                                let iconType = 'error';
                                // Determine if this is a timing or stock issue based on the message
                                if (data.error.toLowerCase().includes('stock') || data.error.toLowerCase().includes('expire')) {
                                    iconType = 'warning';
                                } else if (data.error.toLowerCase().includes('not yet time')) {
                                    iconType = 'info';
                                }

                                Swal.fire({
                                    title: 'Cannot Dispense',
                                    text: data.error,
                                    icon: iconType,
                                    confirmButtonColor: '#00acc1'
                                });
                            }
                        })
                        .catch(error => {
                            Swal.fire({
                                title: 'Error!',
                                text: 'A network or server error occurred: ' + error,
                                icon: 'error',
                                confirmButtonColor: '#00acc1'
                            });
                        });
                }
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