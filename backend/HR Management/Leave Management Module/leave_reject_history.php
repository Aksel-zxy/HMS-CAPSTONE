<?php
include '../../../SQL/config.php';
include 'classes/LeaveManager.php';
include 'classes/UserManager.php';

// Redirect if not logged in
if (!isset($_SESSION['hr']) || $_SESSION['hr'] !== true) {
    header('Location: ../../login.php');
    exit();
}

// Ensure user_id is set
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    echo "User ID is not set in session.";
    exit();
}

// Initialize managers
$userManager = new UserManager($conn);
$leaveManager = new LeaveManager($conn);

// Get current HR user
$user = $userManager->getUserById($_SESSION['user_id']);
if (!$user) {
    echo "No user found.";
    exit();
}

// Get rejected leave records
$rejectedLeaves = $leaveManager->getRejectedLeaves();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Rejected Leaves | HR Management</title>
    <link rel="shortcut icon" href="../assets/image/favicon.ico" type="image/x-icon">
    <link rel="stylesheet" href="../assets/CSS/bootstrap.min.css">
    <link rel="stylesheet" href="../assets/CSS/super.css">
    <link rel="stylesheet" href="css/leave_history.css">
</head>
<body>
<div class="main">
    <div class="container mt-4">
        <a href="leave_approval.php" style="color:black;">
            <svg xmlns="http://www.w3.org/2000/svg" width="35px" height="35px" fill="currentColor" class="bi bi-arrow-left-circle" viewBox="0 0 16 16">
                <path fill-rule="evenodd" d="M1 8a7 7 0 1 0 14 0A7 7 0 0 0 1 8m15 0A8 8 0 1 1 0 8a8 8 0 0 1 16 0m-4.5-.5a.5.5 0 0 1 0 1H5.707l2.147 2.146a.5.5 0 0 1-.708.708l-3-3a.5.5 0 0 1 0-.708l3-3a.5.5 0 1 1 .708.708L5.707 7.5z"/>
            </svg>
        </a>

        <h3>Rejected Leave History</h3>
        <table class="table table-bordered table-hover mt-3">
            <thead class="table-dark">
                <tr>
                    <th>#</th>
                    <th>Employee</th>
                    <th>Profession</th>
                    <th>Role</th>
                    <th>Department</th>
                    <th>Leave Type</th>
                    <th>Start</th>
                    <th>End</th>
                    <th>Reason</th>
                    <th>Status</th>
                    <th>Medical Cert</th>
                    <th>Submitted At</th>
                </tr>
            </thead>
            <tbody>
                <?php
                    if ($rejectedLeaves && $rejectedLeaves->num_rows > 0) {
                        $i = 1;
                        while ($row = $rejectedLeaves->fetch_assoc()) {
                            echo "<tr>
                                <td>{$i}</td>
                                <td>{$row['first_name']} {$row['last_name']}</td>
                                <td>{$row['profession']}</td>
                                <td>{$row['role']}</td>
                                <td>{$row['department']}</td>
                                <td>{$row['leave_type']}</td>
                                <td>{$row['leave_start_date']}</td>
                                <td>{$row['leave_end_date']}</td>
                                <td>{$row['leave_reason']}</td>
                                <td>{$row['leave_status']}</td>
                                <td>";

                            if (!empty($row['medical_cert'])) {
                                echo "<a href='{$row['medical_cert']}' target='_blank'>View</a>";
                            } else {
                                echo "<span class='text-muted'>None</span>";
                            }

                            echo "</td>
                                <td>{$row['submit_at']}</td>
                            </tr>";
                            $i++;
                        }
                    } else {
                        echo "<tr><td colspan='12' class='text-center'>No leave applications found.</td></tr>";
                    }
                ?>
            </tbody>
        </table>
    </div>
</div>

<?php include '../../FooterComponent.php';
FooterComponent::render(); ?>

<script>
    document.querySelector(".toggler-btn")?.addEventListener("click", function() {
        document.querySelector("#sidebar").classList.toggle("collapsed");
    });
</script>
<script src="../assets/Bootstrap/all.min.js"></script>
<script src="../assets/Bootstrap/bootstrap.bundle.min.js"></script>
<script src="../assets/Bootstrap/fontawesome.min.js"></script>
<script src="../assets/Bootstrap/jq.js"></script>
</body>
</html>
