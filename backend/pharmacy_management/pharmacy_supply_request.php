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
            <div class="container-fluid">
                <h1>ALA PA</h1> <br>
                <h1></h1> <br>
                <H1></H1>
                <H1></H1>
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