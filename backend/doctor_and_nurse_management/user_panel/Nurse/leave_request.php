<?php
include '../../../../SQL/config.php';

// ------------------ LeaveApplication Class ------------------
class LeaveApplication
{
    private $conn;

    public function __construct($db)
    {
        $this->conn = $db;
    }

    public function getEmployee($employee_id)
    {
        $stmt = $this->conn->prepare("
            SELECT employee_id, first_name, middle_name, last_name, suffix_name,
                   profession, role, department, gender
            FROM hr_employees
            WHERE employee_id = ?
        ");
        $stmt->bind_param("i", $employee_id);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_assoc();
    }

    // ✅ Updated to fetch remaining_days directly from hr_leave_credits
    public function getRemainingDays($employee_id, $leave_type, $year)
    {

        $stmt = $this->conn->prepare("
            SELECT 
                allocated_days,
                used_days,
                (allocated_days - used_days) AS remaining_days
            FROM hr_leave_credits
            WHERE employee_id = ?
            AND leave_type = ?
            AND year = ?
            LIMIT 1
        ");

        $stmt->bind_param("isi", $employee_id, $leave_type, $year);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();

        if (!$row) {
            return 0; // no record = no credits
        }

        return max(0, (float)$row['remaining_days']);
    }

    public function submit($data, $file = null)
    {
        try {
            $fileContent = null;
            if ($file && $file['error'] === UPLOAD_ERR_OK) {
                $allowed = ['jpg', 'jpeg', 'png', 'pdf', 'docx'];
                $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                if (!in_array($ext, $allowed)) throw new Exception("Invalid file type.");
                $fileContent = file_get_contents($file['tmp_name']);
            }

            $leave_fraction = ($data['leave_duration'] ?? '') === 'Half Day' ? 0.5 : 1;

            $stmt = $this->conn->prepare("
                INSERT INTO hr_leave 
                (employee_id, leave_type, leave_start_date, leave_end_date, leave_status, leave_reason, medical_cert, leave_duration, half_day_type, leave_fraction)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            if (!$stmt) throw new Exception($this->conn->error);

            $leave_status = 'Pending';
            $half_day_type = $data['half_day_type'] ?? null;

            $stmt->bind_param(
                "sssssssssd",
                $data['employee_id'],
                $data['leave_type'],
                $data['leave_start_date'],
                $data['leave_end_date'],
                $leave_status,
                $data['leave_reason'],
                $fileContent,
                $data['leave_duration'],
                $half_day_type,
                $leave_fraction
            );

            if ($fileContent) $stmt->send_long_data(6, $fileContent);

            if (!$stmt->execute()) throw new Exception($stmt->error);
            return true;
        } catch (Exception $e) {
            error_log($e->getMessage());
            return false;
        }
    }
}

// ------------------ Access Control ------------------
if (!isset($_SESSION['profession']) || $_SESSION['profession'] !== 'Nurse') {
    header('Location: login.php');
    exit();
}

if (!isset($_SESSION['employee_id'])) {
    echo "User ID is not set in session.";
    exit();
}

$leaveApp = new LeaveApplication($conn);
$user = $leaveApp->getEmployee($_SESSION['employee_id']);
if (!$user) {
    echo "No user found.";
    exit();
}

// ------------------ Handle AJAX Remaining Days ------------------
if (isset($_POST['ajax']) && $_POST['ajax'] === 'remaining') {
    $year = $_POST['year'] ?? date('Y');
    $remaining = $leaveApp->getRemainingDays($_POST['employee_id'], $_POST['leave_type'], $year);
    echo json_encode(['success' => true, 'remaining_days' => $remaining]);
    exit();
}

// ------------------ Handle Form Submission ------------------
$form_message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['ajax'])) {
    $data = [
        'employee_id'     => $_POST['employee_id'],
        'leave_type'      => $_POST['leave_type'],
        'leave_duration'  => $_POST['leave_duration'],
        'half_day_type'   => $_POST['half_day_type'] ?? null,
        'leave_start_date' => $_POST['leave_start_date'],
        'leave_end_date'  => $_POST['leave_end_date'],
        'leave_reason'    => $_POST['leave_reason']
    ];
    $file = $_FILES['medical_cert'] ?? null;

    if ($leaveApp->submit($data, $_FILES['medical_cert'] ?? null)) {
        echo "<script>alert('Leave request submitted successfully.');window.location.href='leave_request.php';</script>";
    } else {
        echo "<script>alert('Error submitting leave request.');</script>";
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HMS | User Panel</title>
    <link rel="shortcut icon" href="../../assets/image/favicon.ico" type="image/x-icon">
    <link rel="stylesheet" href="../../assets/CSS/bootstrap.min.css">
    <link rel="stylesheet" href="../../assets/CSS/super.css">
    <link rel="stylesheet" href="../../assets/CSS/my_schedule.css">
    <link rel="stylesheet" href="../Doctor/notif.css">
    <link rel="stylesheet" href="leave_request.css">
</head>

<body>
    <div class="d-flex">
        <!----- Sidebar ----->
        <aside id="sidebar" class="sidebar-toggle">
            <div class="sidebar-logo mt-3">
                <img src="../../assets/image/logo-dark.png" width="90px" height="20px">
            </div>
            <div class="text-center my-4">
                <a href="#" class="d-inline-block text-decoration-none profile-trigger" data-bs-toggle="modal" data-bs-target="#profileModal">

                    <div class="profile-icon-circle shadow-sm">
                        <div style="font-size: 30px; font-weight: bold;">
                            <?php
                            // Get first letter of First Name + First letter of Last Name
                            $initials = substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1);
                            echo strtoupper($initials);
                            ?>
                        </div>
                    </div>

                    <div class="mt-2 text-primary fw-bold" style="font-size: 14px;"><?php echo $user['first_name']; ?> <?php echo $user['last_name']; ?></div>
                </a>
            </div>
            <div class="menu-title">Navigation</div>
            <li class="sidebar-item">
                <a href="my_nurse_schedule.php" class="sidebar-link" data-bs-toggle="#" data-bs-target="#"
                    aria-expanded="false" aria-controls="auth">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 640 640">
                        <path d="M224 64C241.7 64 256 78.3 256 96L256 128L384 128L384 96C384 78.3 398.3 64 416 64C433.7 64 448 78.3 448 96L448 128L480 128C515.3 128 544 156.7 544 192L544 480C544 515.3 515.3 544 480 544L160 544C124.7 544 96 515.3 96 480L96 192C96 156.7 124.7 128 160 128L192 128L192 96C192 78.3 206.3 64 224 64zM160 304L160 336C160 344.8 167.2 352 176 352L208 352C216.8 352 224 344.8 224 336L224 304C224 295.2 216.8 288 208 288L176 288C167.2 288 160 295.2 160 304zM288 304L288 336C288 344.8 295.2 352 304 352L336 352C344.8 352 352 344.8 352 336L352 304C352 295.2 344.8 288 336 288L304 288C295.2 288 288 295.2 288 304zM432 288C423.2 288 416 295.2 416 304L416 336C416 344.8 423.2 352 432 352L464 352C472.8 352 480 344.8 480 336L480 304C480 295.2 472.8 288 464 288L432 288zM160 432L160 464C160 472.8 167.2 480 176 480L208 480C216.8 480 224 472.8 224 464L224 432C224 423.2 216.8 416 208 416L176 416C167.2 416 160 423.2 160 432zM304 416C295.2 416 288 423.2 288 432L288 464C288 472.8 295.2 480 304 480L336 480C344.8 480 352 472.8 352 464L352 432C352 423.2 344.8 416 336 416L304 416zM416 432L416 464C416 472.8 423.2 480 432 480L464 480C472.8 480 480 472.8 480 464L480 432C480 423.2 472.8 416 464 416L432 416C423.2 416 416 423.2 416 432z" />
                    </svg>
                    <span style="font-size: 18px;">My Schedule</span>
                </a>
            </li>
            <li class="sidebar-item">
                <a href="nurse_duty.php" class="sidebar-link" data-bs-toggle="#" data-bs-target="#"
                    aria-expanded="false" aria-controls="auth">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 640 640">
                        <path d="M160 96C160 78.3 174.3 64 192 64L448 64C465.7 64 480 78.3 480 96C480 113.7 465.7 128 448 128L418.5 128L428.8 262.1C465.9 283.3 494.6 318.5 507 361.8L510.8 375.2C513.6 384.9 511.6 395.2 505.6 403.3C499.6 411.4 490 416 480 416L160 416C150 416 140.5 411.3 134.5 403.3C128.5 395.3 126.5 384.9 129.3 375.2L133 361.8C145.4 318.5 174 283.3 211.2 262.1L221.5 128L192 128C174.3 128 160 113.7 160 96zM288 464L352 464L352 576C352 593.7 337.7 608 320 608C302.3 608 288 593.7 288 576L288 464z" />
                    </svg>
                    <span style="font-size: 18px;">Duty Assignment</span>
                </a>
            </li>
            <li class="sidebar-item">
                <a href="nurse_renew.php" class="sidebar-link" data-bs-toggle="#" data-bs-target="#"
                    aria-expanded="false" aria-controls="auth">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 640 640">
                        <path d="M32 160C32 124.7 60.7 96 96 96L544 96C579.3 96 608 124.7 608 160L32 160zM32 208L608 208L608 480C608 515.3 579.3 544 544 544L96 544C60.7 544 32 515.3 32 480L32 208zM279.3 480C299.5 480 314.6 460.6 301.7 445C287 427.3 264.8 416 240 416L176 416C151.2 416 129 427.3 114.3 445C101.4 460.6 116.5 480 136.7 480L279.2 480zM208 376C238.9 376 264 350.9 264 320C264 289.1 238.9 264 208 264C177.1 264 152 289.1 152 320C152 350.9 177.1 376 208 376zM392 272C378.7 272 368 282.7 368 296C368 309.3 378.7 320 392 320L504 320C517.3 320 528 309.3 528 296C528 282.7 517.3 272 504 272L392 272zM392 368C378.7 368 368 378.7 368 392C368 405.3 378.7 416 392 416L504 416C517.3 416 528 405.3 528 392C528 378.7 517.3 368 504 368L392 368z" />
                    </svg>
                    <span style="font-size: 18px;">Compliance Licensing</span>
                </a>
            </li>
            <li class="sidebar-item">
                <a href="my_eval.php" class="sidebar-link" data-bs-toggle="#" data-bs-target="#"
                    aria-expanded="false" aria-controls="auth">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 640 640">
                        <path d="M128 128C128 92.7 156.7 64 192 64L341.5 64C358.5 64 374.8 70.7 386.8 82.7L493.3 189.3C505.3 201.3 512 217.6 512 234.6L512 512C512 547.3 483.3 576 448 576L192 576C156.7 576 128 547.3 128 512L128 128zM336 122.5L336 216C336 229.3 346.7 240 360 240L453.5 240L336 122.5zM337 327C327.6 317.6 312.4 317.6 303.1 327L239.1 391C229.7 400.4 229.7 415.6 239.1 424.9C248.5 434.2 263.7 434.3 273 424.9L296 401.9L296 488C296 501.3 306.7 512 320 512C333.3 512 344 501.3 344 488L344 401.9L367 424.9C376.4 434.3 391.6 434.3 400.9 424.9C410.2 415.5 410.3 400.3 400.9 391L336.9 327z" />
                    </svg>
                    <span style="font-size: 18px;">Performance and Evaluation</span>
                </a>
            </li>
            <li class="sidebar-item">
                <a href="leave_request.php" class="sidebar-link" data-bs-toggle="#" data-bs-target="#"
                    aria-expanded="false" aria-controls="auth">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" class="bi bi-person-walking" viewBox="0 0 16 16">
                        <path d="M9.5 1.5a1.5 1.5 0 1 1-3 0 1.5 1.5 0 0 1 3 0M6.44 3.752A.75.75 0 0 1 7 3.5h1.445c.742 0 1.32.643 1.243 1.38l-.43 4.083a1.8 1.8 0 0 1-.088.395l-.318.906.213.242a.8.8 0 0 1 .114.175l2 4.25a.75.75 0 1 1-1.357.638l-1.956-4.154-1.68-1.921A.75.75 0 0 1 6 8.96l.138-2.613-.435.489-.464 2.786a.75.75 0 1 1-1.48-.246l.5-3a.75.75 0 0 1 .18-.375l2-2.25Z" />
                        <path d="M6.25 11.745v-1.418l1.204 1.375.261.524a.8.8 0 0 1-.12.231l-2.5 3.25a.75.75 0 1 1-1.19-.914zm4.22-4.215-.494-.494.205-1.843.006-.067 1.124 1.124h1.44a.75.75 0 0 1 0 1.5H11a.75.75 0 0 1-.531-.22Z" />
                    </svg>
                    <span style="font-size: 18px;">Leave Request</span>
                </a>
            </li>
            <li class="sidebar-item">
                <a href="payslip_viewing.php" class="sidebar-link" data-bs-toggle="#" data-bs-target="#"
                    aria-expanded="false" aria-controls="auth">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" class="bi bi-file-earmark-text-fill" viewBox="0 0 16 16">
                        <path d="M9.293 0H4a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2V4.707A1 1 0 0 0 13.707 4L10 .293A1 1 0 0 0 9.293 0M9.5 3.5v-2l3 3h-2a1 1 0 0 1-1-1M4.5 9a.5.5 0 0 1 0-1h7a.5.5 0 0 1 0 1zM4 10.5a.5.5 0 0 1 .5-.5h7a.5.5 0 0 1 0 1h-7a.5.5 0 0 1-.5-.5m.5 2.5a.5.5 0 0 1 0-1h4a.5.5 0 0 1 0 1z" />
                    </svg>
                    <span style="font-size: 18px;">Payslip Viewing</span>
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
                 <div class="logo d-flex align-items-center">
                    <div class="notification-wrapper position-relative me-4" style="cursor: pointer;">
                        <div onclick="toggleNotifications()">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-bell-fill" viewBox="0 0 16 16">
                                <path d="M8 16a2 2 0 0 0 2-2H6a2 2 0 0 0 2 2m.995-14.901a1 1 0 1 0-1.99 0A5 5 0 0 0 3 6c0 1.098-.5 6-2 7h14c-1.5-1-2-5.902-2-7 0-2.42-1.72-4.44-4.005-4.901" />
                            </svg>
                            <span id="notification-count" class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="display:none; font-size: 0.6rem;">
                                0
                            </span>
                        </div>

                        <div id="notification-dropdown" class="custom-notify-dropdown hidden">
                            <div class="notify-header">
                                License Alerts
                            </div>
                            <ul id="notification-list">
                                <li class="empty-state">Loading...</li>
                            </ul>
                        </div>
                    </div>
                    <div class="dropdown d-flex align-items-center">
                        <span class="username ml-1 me-2"><?php echo $user['first_name']; ?> <?php echo $user['last_name']; ?></span>
                        <button class="btn dropdown-toggle" type="button" id="dropdownMenuButton" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-person-circle"></i>
                        </button>
                        <ul class="dropdown-menu" aria-labelledby="dropdownMenuButton" style="min-width: 200px; padding: 10px; border-radius: 5px; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); background-color: #fff; color: #333;">
                            <li style="margin-bottom: 8px; font-size: 14px; color: #555;">
                                <span>Welcome <strong style="color: #007bff;"><?php echo $user['last_name']; ?></strong>!</span>
                            </li>
                            <li>
                                <a class="dropdown-item" href="../../../logout.php" style="font-size: 14px; color: #007bff; text-decoration: none; padding: 8px 12px; border-radius: 4px; transition: background-color 0.3s ease;">
                                    Logout
                                </a>
                            </li>
                        </ul>
                    </div>

                </div>
            </div>
            <!-- START CODING HERE -->
            <!-- Button to open leave application modal -->
            <div style="text-align:center; margin-bottom:20px;">
                <button id="openLeaveModal" class="submit-btn">
                    Apply for Leave
                </button>
            </div>

            <!-- Leave Application Modal -->
            <div id="leaveModal" class="custom-modal">
                <div class="custom-modal-content">

                    <span class="close-modal" id="closeLeaveModal">X</span>

                    <h3 style="font-weight: bold; text-align: center;">Leave Application Form</h3>

                    <form action="" method="POST" enctype="multipart/form-data">

                        <input type="hidden" name="employee_id" value="<?= $user['employee_id'] ?>">

                        <label>Leave Type:</label>
                        <select name="leave_type" id="leave_type" required>
                            <option value="">-- Select Leave Type --</option>
                            <option value="Vacation Leave">Vacation Leave</option>
                            <option value="Sick Leave">Sick Leave</option>
                            <option value="Emergency Leave">Emergency Leave</option>
                            <option value="Maternity Leave" data-gender="Female">Maternity Leave</option>
                            <option value="Paternity Leave" data-gender="Male">Paternity Leave</option>
                            <option value="Bereavement Leave">Bereavement Leave</option>
                        </select>

                        <label>Remaining Days:</label>
                        <input type="text" id="remaining_days" readonly>
                        <p id="leave_warning" style="color:red; display:none; text-align:center;"></p>

                        <label>Leave Duration:</label>
                        <select name="leave_duration" id="leave_duration" required>
                            <option value="Whole Day">Full Day</option>
                            <option value="Half Day">Half Day</option>
                        </select>

                        <div id="halfDayOptions" style="display:none;">
                            <label>Half Day Type:</label>
                            <select name="half_day_type">
                                <option value="AM">Morning</option>
                                <option value="PM">Afternoon</option>
                            </select>
                        </div>

                        <label>Start Date:</label>
                        <input type="date" name="leave_start_date" required>

                        <label>End Date:</label>
                        <input type="date" name="leave_end_date" required>

                        <label>Reason:</label>
                        <textarea name="leave_reason" rows="2" required></textarea>

                        <label>Attach Document:</label>
                        <input type="file" name="medical_cert">

                        <button type="submit" class="submit-button">Submit Leave</button>
                    </form>
                </div>
            </div>

            <p style="text-align: center; font-size: 35px; font-weight: bold; padding-bottom: 10px; color: #0047ab;">My Leave Applications</p>
            <div class="employees">
                <table>
                    <thead>
                        <tr>
                            <th>Leave Type</th>
                            <th>Duration</th>
                            <th>Start</th>
                            <th>End</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $empId = $user['employee_id'];
                        $sql = $conn->query("
                            SELECT leave_type, leave_duration, leave_start_date, leave_end_date, leave_status
                            FROM hr_leave
                            WHERE employee_id = '$empId'
                            ORDER BY leave_start_date DESC
                        ");
                        while ($row = $sql->fetch_assoc()):
                        ?>
                            <tr>
                                <td><?= $row['leave_type'] ?></td>
                                <td><?= $row['leave_duration'] ?></td>
                                <td><?= $row['leave_start_date'] ?></td>
                                <td><?= $row['leave_end_date'] ?></td>
                                <td data-status="<?= $row['leave_status'] ?>">
                                    <?= $row['leave_status'] ?>
                                </td>

                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>

            <!-- END CODING HERE -->
        </div>
        <!----- End of Main Content ----->
    </div>
    <?php include 'nurse_profile.php' ?>
    <script>
        // ---------------- SIDEBAR TOGGLE ----------------
        const toggler = document.querySelector(".toggler-btn");
        if (toggler) {
            toggler.addEventListener("click", function() {
                document.querySelector("#sidebar").classList.toggle("collapsed");
            });
        }

        const modal = document.getElementById("leaveModal");
        const openBtn = document.getElementById("openLeaveModal");
        const closeBtn = document.getElementById("closeLeaveModal");

        // Only open on click
        openBtn.addEventListener("click", () => {
            modal.style.display = "flex"; // use flex for centering
        });

        // Close modal
        closeBtn.addEventListener("click", () => {
            modal.style.display = "none";
        });

        // Click outside modal closes it
        window.addEventListener("click", (e) => {
            if (e.target === modal) modal.style.display = "none";
        });

        // ---------------- ELEMENT REFERENCES ----------------
        const leaveTypeSelect = document.getElementById('leave_type');
        const leaveDurationSelect = document.getElementById('leave_duration');
        const halfDayOptionsDiv = document.getElementById('halfDayOptions');
        const remainingInput = document.getElementById('remaining_days');
        const warningMsg = document.getElementById('leave_warning');

        const selectedEmployee = {
            id: <?= $user['employee_id'] ?>,
            gender: "<?= $user['gender'] ?>"
        };

        // ---------------- FILTER LEAVE TYPES BY GENDER ----------------
        function filterLeaveTypesByGender() {
            [...leaveTypeSelect.options].forEach(opt => {
                if (opt.dataset.gender) {
                    opt.hidden = opt.dataset.gender !== selectedEmployee.gender;
                } else {
                    opt.hidden = false;
                }
            });
        }
        filterLeaveTypesByGender();

        // ---------------- HALF DAY TOGGLE ----------------
        leaveDurationSelect.addEventListener('change', () => {
            halfDayOptionsDiv.style.display =
                leaveDurationSelect.value === 'Half Day' ? 'block' : 'none';

            refreshRemaining();
        });

        // ---------------- FETCH REMAINING DAYS ----------------
        leaveTypeSelect.addEventListener('change', refreshRemaining);

        function refreshRemaining() {
            if (!leaveTypeSelect.value) return;

            const data = new URLSearchParams();
            data.append('ajax', 'remaining');
            data.append('employee_id', selectedEmployee.id);
            data.append('leave_type', leaveTypeSelect.value);
            data.append('year', new Date().getFullYear());

            fetch("", {
                    method: "POST",
                    body: data
                })
                .then(res => res.json())
                .then(d => {
                    let remaining = parseFloat(d.remaining_days) || 0;
                    remainingInput.value = remaining;

                    // ---------------- WARNING LOGIC ----------------
                    let required = leaveDurationSelect.value === 'Half Day' ? 0.5 : 1;

                    if (remaining < required) {
                        warningMsg.textContent = "No remaining leave credits.";
                        warningMsg.style.display = "block";
                    } else {
                        warningMsg.textContent = "";
                        warningMsg.style.display = "none";
                    }
                })
                .catch(err => {
                    console.error("Fetch error:", err);
                });
        }
    </script>
    <script src="../Doctor/notif.js"></script>
    <script src="../../assets/Bootstrap/all.min.js"></script>
    <script src="../../assets/Bootstrap/bootstrap.bundle.min.js"></script>
    <script src="../../assets/Bootstrap/fontawesome.min.js"></script>
    <script src="../../assets/Bootstrap/jq.js"></script>
</body>

</html>