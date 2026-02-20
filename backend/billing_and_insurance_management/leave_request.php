<?php
session_start(); // ✅ Must be first — before any output or includes

include '../../SQL/config.php';

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
        if (!$row) return 0;
        return max(0, (float)$row['remaining_days']);
    }

    public function submit($data, $file = null) {
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

            $leave_status  = 'Pending';
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

// ─────────────────────────────────────────────
// ACCESS CONTROL
// ─────────────────────────────────────────────
// FIX: The original code required $_SESSION['profession'] === 'Doctor',
// which blocked all non-Doctor users (billing staff, nurses, admins, etc.)
// and sent them straight back to login.php.
//
// Strategy: Accept any authenticated session. We check for employee_id
// OR user_id so this works whether the user logged in through the HR
// portal (sets employee_id) or the billing portal (sets user_id).
//
// If your project has separate logins per module and you DO want to
// restrict this page to specific roles, replace the condition below
// with your actual role check, e.g.:
//   in_array($_SESSION['role'] ?? '', ['Doctor','Nurse','Admin'])
// ─────────────────────────────────────────────

$session_employee_id = $_SESSION['employee_id'] ?? $_SESSION['user_id'] ?? null;

if (!$session_employee_id) {
    // No valid session at all — redirect to login
    header('Location: ../login.php');
    exit();
}

// Instantiate and load employee record
$leaveApp = new LeaveApplication($conn);
$user = $leaveApp->getEmployee($session_employee_id);

if (!$user) {
    // Authenticated session but no matching employee record
    // (e.g. billing user has no hr_employees row yet)
    // Show a friendly error instead of a blank page or crash.
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Leave Request — Access Error</title>
        <link rel="stylesheet" href="../../assets/CSS/bootstrap.min.css">
        <style>
            body { display:flex; align-items:center; justify-content:center; min-height:100vh; background:#f5f6f7; font-family:'Segoe UI',sans-serif; }
            .err-card { background:#fff; border-radius:12px; padding:2.5rem 2rem; box-shadow:0 4px 24px rgba(0,0,0,.1); max-width:420px; width:100%; text-align:center; }
            .err-icon { font-size:3rem; margin-bottom:1rem; }
            h2 { font-size:1.3rem; font-weight:700; color:#1a2640; margin-bottom:.5rem; }
            p  { color:#7a8fb5; font-size:.9rem; margin-bottom:1.5rem; }
            a  { display:inline-block; background:#0047ab; color:#fff; padding:.6rem 1.4rem; border-radius:8px; text-decoration:none; font-size:.9rem; }
        </style>
    </head>
    <body>
        <div class="err-card">
            <div class="err-icon">⚠️</div>
            <h2>Employee Record Not Found</h2>
            <p>Your account is authenticated but no matching employee profile was found in the HR system.
               Please contact your HR administrator to link your account.</p>
            <a href="../login.php">Back to Login</a>
        </div>
    </body>
    </html>
    <?php
    exit();
}

// ─────────────────────────────────────────────
// AJAX — Remaining Days
// ─────────────────────────────────────────────
if (isset($_POST['ajax']) && $_POST['ajax'] === 'remaining') {
    header('Content-Type: application/json');
    $year      = $_POST['year'] ?? date('Y');
    $remaining = $leaveApp->getRemainingDays(
        (int)$_POST['employee_id'],
        $_POST['leave_type'],
        (int)$year
    );
    echo json_encode(['success' => true, 'remaining_days' => $remaining]);
    exit();
}

// ─────────────────────────────────────────────
// FORM SUBMISSION
// ─────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['ajax'])) {
    $data = [
        'employee_id'      => $user['employee_id'], // always use server-side value
        'leave_type'       => $_POST['leave_type']       ?? '',
        'leave_duration'   => $_POST['leave_duration']   ?? 'Whole Day',
        'half_day_type'    => $_POST['half_day_type']    ?? null,
        'leave_start_date' => $_POST['leave_start_date'] ?? '',
        'leave_end_date'   => $_POST['leave_end_date']   ?? '',
        'leave_reason'     => $_POST['leave_reason']     ?? '',
    ];

    if ($leaveApp->submit($data, $_FILES['medical_cert'] ?? null)) {
        echo "<script>alert('Leave request submitted successfully.');window.location.href='leave_request.php';</script>";
    } else {
        echo "<script>alert('Error submitting leave request. Please try again.');</script>";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HMS | Leave Request</title>
    <link rel="shortcut icon" href="../../assets/image/favicon.ico" type="image/x-icon">
    <link rel="stylesheet" href="../../assets/CSS/bootstrap.min.css">
    <link rel="stylesheet" href="../../assets/CSS/super.css">
    <link rel="stylesheet" href="../../assets/CSS/leave_request.css">
</head>

<body>
    <div class="d-flex">

        <!----- Sidebar ----->
        <aside id="sidebar" class="sidebar-toggle">

            <div class="sidebar-logo mt-3">
                <img src="../../assets/image/logo-dark.png" width="90px" height="20px" alt="Logo">
            </div>

            <div class="menu-title">Navigation</div>

            <li class="sidebar-item">
                <a href="leave_request.php" class="sidebar-link" aria-current="page">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-person-walking" viewBox="0 0 16 16">
                        <path d="M9.5 1.5a1.5 1.5 0 1 1-3 0 1.5 1.5 0 0 1 3 0M6.44 3.752A.75.75 0 0 1 7 3.5h1.445c.742 0 1.32.643 1.243 1.38l-.43 4.083a1.8 1.8 0 0 1-.088.395l-.318.906.213.242a.8.8 0 0 1 .114.175l2 4.25a.75.75 0 1 1-1.357.638l-1.956-4.154-1.68-1.921A.75.75 0 0 1 6 8.96l.138-2.613-.435.489-.464 2.786a.75.75 0 1 1-1.48-.246l.5-3a.75.75 0 0 1 .18-.375l2-2.25Z"/>
                        <path d="M6.25 11.745v-1.418l1.204 1.375.261.524a.8.8 0 0 1-.12.231l-2.5 3.25a.75.75 0 1 1-1.19-.914zm4.22-4.215-.494-.494.205-1.843.006-.067 1.124 1.124h1.44a.75.75 0 0 1 0 1.5H11a.75.75 0 0 1-.531-.22Z"/>
                    </svg>
                    <span style="font-size:18px;">Leave Request</span>
                </a>
            </li>
            <li class="sidebar-item">
                <a href="payslip_viewing.php" class="sidebar-link">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-file-earmark-text-fill" viewBox="0 0 16 16">
                        <path d="M9.293 0H4a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2V4.707A1 1 0 0 0 13.707 4L10 .293A1 1 0 0 0 9.293 0M9.5 3.5v-2l3 3h-2a1 1 0 0 1-1-1M4.5 9a.5.5 0 0 1 0-1h7a.5.5 0 0 1 0 1zM4 10.5a.5.5 0 0 1 .5-.5h7a.5.5 0 0 1 0 1h-7a.5.5 0 0 1-.5-.5m.5 2.5a.5.5 0 0 1 0-1h4a.5.5 0 0 1 0 1z"/>
                    </svg>
                    <span style="font-size:18px;">Payslip Viewing</span>
                </a>
            </li>
        </aside>
        <!----- End Sidebar ----->

        <!----- Main Content ----->
        <div class="main">

            <!-- Topbar -->
            <div class="topbar">
                <div class="toggle">
                    <button class="toggler-btn" type="button" aria-label="Toggle sidebar">
                        <svg xmlns="http://www.w3.org/2000/svg" width="30" height="30" fill="currentColor" class="bi bi-list-ul" viewBox="0 0 16 16">
                            <path fill-rule="evenodd" d="M5 11.5a.5.5 0 0 1 .5-.5h9a.5.5 0 0 1 0 1h-9a.5.5 0 0 1-.5-.5m0-4a.5.5 0 0 1 .5-.5h9a.5.5 0 0 1 0 1h-9a.5.5 0 0 1-.5-.5m0-4a.5.5 0 0 1 .5-.5h9a.5.5 0 0 1 0 1h-9a.5.5 0 0 1-.5-.5m-3 1a1 1 0 1 0 0-2 1 1 0 0 0 0 2m0 4a1 1 0 1 0 0-2 1 1 0 0 0 0 2m0 4a1 1 0 1 0 0-2 1 1 0 0 0 0 2"/>
                        </svg>
                    </button>
                </div>
                <div class="logo">
                    <div class="dropdown d-flex align-items-center">
                        <span class="username ml-1 me-2">
                            <?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?>
                        </span>
                        <button class="btn dropdown-toggle" type="button" id="dropdownMenuButton"
                                data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-person-circle"></i>
                        </button>
                        <ul class="dropdown-menu" aria-labelledby="dropdownMenuButton"
                            style="min-width:200px;padding:10px;border-radius:5px;box-shadow:0 4px 6px rgba(0,0,0,.1);background:#fff;color:#333;">
                            <li style="margin-bottom:8px;font-size:14px;color:#555;">
                                Welcome <strong style="color:#007bff;"><?= htmlspecialchars($user['last_name']) ?></strong>!
                            </li>
                            <li>
                                <a class="dropdown-item" href="../../../logout.php"
                                   onclick="return confirm('Are you sure you want to log out?');"
                                   style="font-size:14px;color:#007bff;padding:8px 12px;border-radius:4px;">
                                    Logout
                                </a>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
            <!-- End Topbar -->

            <!-- Apply Leave Button -->
            <div style="text-align:center; margin-bottom:20px;">
                <button id="openLeaveModal" class="submit-btn">Apply for Leave</button>
            </div>

            <!-- ── Leave Application Modal ── -->
            <div id="leaveModal" class="custom-modal" role="dialog" aria-modal="true" aria-labelledby="modalTitle">
                <div class="custom-modal-content">

                    <button class="close-modal" id="closeLeaveModal" aria-label="Close modal">✕</button>

                    <h3 id="modalTitle" style="font-weight:bold;text-align:center;">Leave Application Form</h3>

                    <form action="" method="POST" enctype="multipart/form-data">

                        <!-- Use server-resolved employee_id, not raw POST -->
                        <input type="hidden" name="employee_id" value="<?= htmlspecialchars($user['employee_id']) ?>">

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
                        <input type="text" id="remaining_days" readonly placeholder="Select a leave type">
                        <p id="leave_warning" style="color:red;display:none;text-align:center;"></p>

                        <label>Leave Duration:</label>
                        <select name="leave_duration" id="leave_duration" required>
                            <option value="Whole Day">Full Day</option>
                            <option value="Half Day">Half Day</option>
                        </select>

                        <div id="halfDayOptions" style="display:none;">
                            <label>Half Day Type:</label>
                            <select name="half_day_type">
                                <option value="AM">Morning (AM)</option>
                                <option value="PM">Afternoon (PM)</option>
                            </select>
                        </div>

                        <label>Start Date:</label>
                        <input type="date" name="leave_start_date" required>

                        <label>End Date:</label>
                        <input type="date" name="leave_end_date" required>

                        <label>Reason:</label>
                        <textarea name="leave_reason" rows="3" required placeholder="Briefly describe your reason…"></textarea>

                        <label>Attach Document <small style="font-weight:normal;color:#888;">(optional: jpg, png, pdf, docx)</small>:</label>
                        <input type="file" name="medical_cert" accept=".jpg,.jpeg,.png,.pdf,.docx">

                        <button type="submit" class="submit-button">Submit Leave</button>
                    </form>
                </div>
            </div>

            <!-- ── My Leave Applications Table ── -->
            <p style="text-align:center;font-size:35px;font-weight:bold;padding-bottom:10px;color:#0047ab;">
                My Leave Applications
            </p>

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
                        // FIX: Use a prepared statement instead of raw string interpolation
                        // to prevent SQL injection.
                        $empId = (int)$user['employee_id'];
                        $leaveStmt = $conn->prepare("
                            SELECT leave_type, leave_duration, leave_start_date, leave_end_date, leave_status
                            FROM hr_leave
                            WHERE employee_id = ?
                            ORDER BY leave_start_date DESC
                        ");
                        $leaveStmt->bind_param("i", $empId);
                        $leaveStmt->execute();
                        $leaveResult = $leaveStmt->get_result();

                        if ($leaveResult->num_rows === 0): ?>
                            <tr>
                                <td colspan="5" style="text-align:center;color:#999;padding:1.5rem;">
                                    No leave applications found.
                                </td>
                            </tr>
                        <?php else:
                            while ($row = $leaveResult->fetch_assoc()):
                                // Color-code status badge
                                $statusColors = [
                                    'Approved' => '#28a745',
                                    'Rejected' => '#dc3545',
                                    'Pending'  => '#ffc107',
                                ];
                                $badgeColor = $statusColors[$row['leave_status']] ?? '#6c757d';
                        ?>
                            <tr>
                                <td><?= htmlspecialchars($row['leave_type']) ?></td>
                                <td><?= htmlspecialchars($row['leave_duration']) ?></td>
                                <td><?= htmlspecialchars($row['leave_start_date']) ?></td>
                                <td><?= htmlspecialchars($row['leave_end_date']) ?></td>
                                <td>
                                    <span style="
                                        display:inline-block;
                                        padding:3px 10px;
                                        border-radius:12px;
                                        font-size:.82rem;
                                        font-weight:700;
                                        color:#fff;
                                        background:<?= $badgeColor ?>;
                                    ">
                                        <?= htmlspecialchars($row['leave_status']) ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endwhile; endif; ?>
                    </tbody>
                </table>
            </div>

        </div>
        <!----- End Main Content ----->
    </div>

    <script>
    // ── Sidebar Toggle ──
    const toggler = document.querySelector('.toggler-btn');
    if (toggler) {
        toggler.addEventListener('click', () => {
            document.getElementById('sidebar').classList.toggle('collapsed');
        });
    }

    // ── Modal Open / Close ──
    const modal    = document.getElementById('leaveModal');
    const openBtn  = document.getElementById('openLeaveModal');
    const closeBtn = document.getElementById('closeLeaveModal');

    openBtn.addEventListener('click', () => {
        modal.style.display = 'flex';
    });
    closeBtn.addEventListener('click', () => {
        modal.style.display = 'none';
    });
    window.addEventListener('click', (e) => {
        if (e.target === modal) modal.style.display = 'none';
    });
    // Also close on Escape key
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && modal.style.display === 'flex') {
            modal.style.display = 'none';
        }
    });

    // ── Element References ──
    const leaveTypeSelect     = document.getElementById('leave_type');
    const leaveDurationSelect = document.getElementById('leave_duration');
    const halfDayOptionsDiv   = document.getElementById('halfDayOptions');
    const remainingInput      = document.getElementById('remaining_days');
    const warningMsg          = document.getElementById('leave_warning');

    const selectedEmployee = {
        id:     <?= (int)$user['employee_id'] ?>,
        gender: <?= json_encode($user['gender'] ?? '') ?>
    };

    // ── Filter Leave Types by Gender ──
    function filterLeaveTypesByGender() {
        [...leaveTypeSelect.options].forEach(opt => {
            if (opt.dataset.gender) {
                opt.hidden = opt.dataset.gender !== selectedEmployee.gender;
                // If currently selected option becomes hidden, reset
                if (opt.hidden && opt.selected) leaveTypeSelect.value = '';
            }
        });
    }
    filterLeaveTypesByGender();

    // ── Half Day Toggle ──
    leaveDurationSelect.addEventListener('change', () => {
        halfDayOptionsDiv.style.display =
            leaveDurationSelect.value === 'Half Day' ? 'block' : 'none';
        refreshRemaining();
    });

    // ── Fetch Remaining Days ──
    leaveTypeSelect.addEventListener('change', refreshRemaining);

    function refreshRemaining() {
        if (!leaveTypeSelect.value) {
            remainingInput.value = '';
            warningMsg.style.display = 'none';
            return;
        }

        const body = new URLSearchParams({
            ajax:        'remaining',
            employee_id: selectedEmployee.id,
            leave_type:  leaveTypeSelect.value,
            year:        new Date().getFullYear()
        });

        fetch('', { method: 'POST', body })
            .then(res => res.json())
            .then(d => {
                const remaining = parseFloat(d.remaining_days) || 0;
                remainingInput.value = remaining;

                const required = leaveDurationSelect.value === 'Half Day' ? 0.5 : 1;

                if (remaining < required) {
                    warningMsg.textContent = `⚠ Only ${remaining} day(s) remaining — not enough for this leave type.`;
                    warningMsg.style.display = 'block';
                } else {
                    warningMsg.textContent = '';
                    warningMsg.style.display = 'none';
                }
            })
            .catch(err => console.error('Fetch error:', err));
    }
    </script>

    <script src="../../assets/Bootstrap/all.min.js"></script>
    <script src="../../assets/Bootstrap/bootstrap.bundle.min.js"></script>
    <script src="../../assets/Bootstrap/fontawesome.min.js"></script>
    <script src="../../assets/Bootstrap/jq.js"></script>
</body>
</html>