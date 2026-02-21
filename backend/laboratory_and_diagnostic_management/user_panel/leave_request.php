<?php
include '../../../SQL/config.php';

// ------------------ LeaveApplication Class ------------------
class LeaveApplication {
    private $conn;

    public function __construct($db) {
        $this->conn = $db;
    }

    public function getEmployee($employee_id) {
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
    public function getRemainingDays($employee_id, $leave_type, $year) {

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

    public function submit($data, $file = null) {
        try {
            $fileContent = null;
            if ($file && $file['error'] === UPLOAD_ERR_OK) {
                $allowed = ['jpg','jpeg','png','pdf','docx'];
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
if (!isset($_SESSION['profession']) || $_SESSION['profession'] !== 'Laboratorist') {
    header('Location: login.php');
    exit();
}

if (!isset($_SESSION['employee_id'])) {
    echo "User ID is not set in session.";
    exit();
}

$leaveApp = new LeaveApplication($conn);
$user = $leaveApp->getEmployee($_SESSION['employee_id']);
if (!$user) { echo "No user found."; exit(); }

// ------------------ Handle AJAX Remaining Days ------------------
if (isset($_POST['ajax']) && $_POST['ajax'] === 'remaining') {
    $year = $_POST['year'] ?? date('Y');
    $remaining = $leaveApp->getRemainingDays($_POST['employee_id'], $_POST['leave_type'], $year);
    echo json_encode(['success'=>true,'remaining_days'=>$remaining]);
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
        'leave_start_date'=> $_POST['leave_start_date'],
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
     <link rel="stylesheet" href="leave_request.css">
</head>

<body>
    <div class="d-flex">
        <!----- Sidebar ----->
        <aside id="sidebar" class="sidebar-toggle">

            <div class="sidebar-logo mt-3">
                <img src="../../assets/image/logo-dark.png" width="90px" height="20px">
            </div>

            <div class="menu-title">Navigation</div>

            <!----- Sidebar Navigation ----->
            <li class="sidebar-item">
                <a href="leave_request.php" class="sidebar-link" data-bs-toggle="#" data-bs-target="#"
                    aria-expanded="false" aria-controls="auth">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-person-walking" viewBox="0 0 16 16">
                        <path d="M9.5 1.5a1.5 1.5 0 1 1-3 0 1.5 1.5 0 0 1 3 0M6.44 3.752A.75.75 0 0 1 7 3.5h1.445c.742 0 1.32.643 1.243 1.38l-.43 4.083a1.8 1.8 0 0 1-.088.395l-.318.906.213.242a.8.8 0 0 1 .114.175l2 4.25a.75.75 0 1 1-1.357.638l-1.956-4.154-1.68-1.921A.75.75 0 0 1 6 8.96l.138-2.613-.435.489-.464 2.786a.75.75 0 1 1-1.48-.246l.5-3a.75.75 0 0 1 .18-.375l2-2.25Z"/>
                        <path d="M6.25 11.745v-1.418l1.204 1.375.261.524a.8.8 0 0 1-.12.231l-2.5 3.25a.75.75 0 1 1-1.19-.914zm4.22-4.215-.494-.494.205-1.843.006-.067 1.124 1.124h1.44a.75.75 0 0 1 0 1.5H11a.75.75 0 0 1-.531-.22Z"/>
                    </svg>
                    <span style="font-size: 18px;">Leave Request</span>
                </a>
            </li>
            <li class="sidebar-item">
                <a href="payslip_viewing.php" class="sidebar-link" data-bs-toggle="#" data-bs-target="#"
                    aria-expanded="false" aria-controls="auth">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-file-earmark-text-fill" viewBox="0 0 16 16">
                        <path d="M9.293 0H4a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2V4.707A1 1 0 0 0 13.707 4L10 .293A1 1 0 0 0 9.293 0M9.5 3.5v-2l3 3h-2a1 1 0 0 1-1-1M4.5 9a.5.5 0 0 1 0-1h7a.5.5 0 0 1 0 1zM4 10.5a.5.5 0 0 1 .5-.5h7a.5.5 0 0 1 0 1h-7a.5.5 0 0 1-.5-.5m.5 2.5a.5.5 0 0 1 0-1h4a.5.5 0 0 1 0 1z"/>
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
                <div class="logo">
                    <div class="dropdown d-flex align-items-center">
                        <span class="username ml-1 me-2"><?php echo $user['first_name']; ?> <?php echo $user['last_name']; ?></span><!-- Display the logged-in user's name -->
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
                        while($row = $sql->fetch_assoc()):
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
    <script>
        // ---------------- SIDEBAR TOGGLE ----------------
        const toggler = document.querySelector(".toggler-btn");
        if (toggler) {
            toggler.addEventListener("click", function () {
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
        const leaveTypeSelect     = document.getElementById('leave_type');
        const leaveDurationSelect = document.getElementById('leave_duration');
        const halfDayOptionsDiv   = document.getElementById('halfDayOptions');
        const remainingInput      = document.getElementById('remaining_days');
        const warningMsg          = document.getElementById('leave_warning');

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

    <script src="../../assets/Bootstrap/all.min.js"></script>
    <script src="../../assets/Bootstrap/bootstrap.bundle.min.js"></script>
    <script src="../../assets/Bootstrap/fontawesome.min.js"></script>
    <script src="../../assets/Bootstrap/jq.js"></script>
</body>

</html>