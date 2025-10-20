<?php
require '../../../SQL/config.php';
include '../includes/FooterComponent.php';
require_once '../classes/Auth.php';
require_once '../classes/User.php';
require_once 'classes/LeaveManager.php';

Auth::checkHR();

$conn = $conn;

$userId = Auth::getUserId();
if (!$userId) {
    die("User ID not set.");
}

$userModel = new User($conn);
$leaveManager = new LeaveManager($conn);

$userObj = new User($conn);
$user = $userObj->getById($userId);
if (!$user) {
    die("User not found.");
}

$approvedLeaves = $leaveManager->getApprovedLeaves();

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Approved Leaves | HR Management</title>
    <link rel="shortcut icon" href="../assets/image/favicon.ico" type="image/x-icon">
    <link rel="stylesheet" href="../assets/CSS/bootstrap.min.css">
    <link rel="stylesheet" href="../assets/CSS/super.css">
    <link rel="stylesheet" href="css/leave_history.css">
</head> 
<body>

    <!----- Full-page Loader ----->
    <div id="loading-screen">
        <div class="loader">
            <div></div>
            <div></div>
            <div></div>
            <div></div>
            <div></div>
        </div>
    </div>

    <div class="main">
        <div class="mamamo">
            <a href="leave_reports.php" style="color:black;">
                <svg xmlns="http://www.w3.org/2000/svg" width="35px" height="35px" fill="currentColor" class="bi bi-arrow-left-circle" viewBox="0 0 16 16">
                    <path fill-rule="evenodd" d="M1 8a7 7 0 1 0 14 0A7 7 0 0 0 1 8m15 0A8 8 0 1 1 0 8a8 8 0 0 1 16 0m-4.5-.5a.5.5 0 0 1 0 1H5.707l2.147 2.146a.5.5 0 0 1-.708.708l-3-3a.5.5 0 0 1 0-.708l3-3a.5.5 0 1 1 .708.708L5.707 7.5z"/>
                </svg>
            </a>

            <div class="history">
                <p style="text-align: center; font-size: 35px; font-weight: bold; padding-bottom: 20px; color: black;">Approved Leave History</p>
                <table>
                    <thead>
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
                            if ($approvedLeaves && $approvedLeaves->num_rows > 0) {
                                $i = 1;
                                while ($row = $approvedLeaves->fetch_assoc()) {
                                    echo "<tr>
                                        <td>{$i}</td>
                                        <td>{$row['first_name']} {$row['middle_name']} {$row['last_name']} {$row['suffix_name']}</td>
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
                                            echo "<a href='download_med_cert.php?leave_id={$row['leave_id']}' target='_blank'>View</a>";
                                        } else {
                                            echo "<span class='text-muted'>None</span>";
                                        }
                                    echo "</td><td>{$row['submit_at']}</td></tr>";
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
    </div>

    <!----- Footer Content ----->
    <?php FooterComponent::render();?>

    <!----- End of Footer Content ----->

    <script>

        window.addEventListener("load", function(){
            setTimeout(function(){
                document.getElementById("loading-screen").style.display = "none";
            }, 2000);
        });

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
