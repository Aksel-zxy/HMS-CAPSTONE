<?php
include '../../SQL/config.php';

class NurseScheduleEditing {
    public $conn;
    public $user;
    public $nurses = [];
    public $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];

    public function __construct($conn) {
        $this->conn = $conn;
        $this->authenticate();
        $this->fetchUser();
        $this->fetchNurses();
    }

    private function authenticate() {
        if (!isset($_SESSION['doctor']) || $_SESSION['doctor'] !== true) {
            header('Location: login.php');
            exit();
        }
        if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
            echo "User ID is not set in session.";
            exit();
        }
    }

    private function fetchUser() {
        $query = "SELECT * FROM users WHERE user_id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("i", $_SESSION['user_id']);
        $stmt->execute();
        $result = $stmt->get_result();
        $this->user = $result->fetch_assoc();
        if (!$this->user) {
            echo "No user found.";
            exit();
        }
    }

    private function fetchNurses() {
        $nurse_query = "SELECT employee_id, first_name, middle_name, last_name, role, profession, department FROM hr_employees WHERE profession = 'Nurse'";
        $nurse_result = $this->conn->query($nurse_query);
        if ($nurse_result && $nurse_result->num_rows > 0) {
            while ($row = $nurse_result->fetch_assoc()) {
                $this->nurses[] = $row;
            }
        }
    }
}

$nurseEdit = new NurseScheduleEditing($conn);
$user = $nurseEdit->user;
$nurses = $nurseEdit->nurses;
$days = $nurseEdit->days;

// Handle schedule update (edit)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_schedule'])) {
    $schedule_id = $_POST['schedule_id'];
    $employee_id = $_POST['employee_id'];
    $week_start = $_POST['week_start'];
    $created_at = date('Y-m-d H:i:s');
    $params = [];
    $types = '';
    $fields = '';
    foreach ($days as $day) {
        $prefix = strtolower(substr($day, 0, 3));
        $fields .= "{$prefix}_start = ?, {$prefix}_end = ?, {$prefix}_status = ?, ";
        $params[] = $_POST[$prefix . '_start'] ?? null;
        $params[] = $_POST[$prefix . '_end'] ?? null;
        $params[] = $_POST[$prefix . '_status'] ?? null;
        $types .= 'sss';
    }
    $fields .= "created_at = ?";
    $params[] = $created_at;
    $types .= 's';
    $params[] = $schedule_id;
    $types .= 's';

    $sql = "UPDATE shift_scheduling SET $fields WHERE schedule_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $success = "Schedule updated successfully!";
    // Redirect to non-edit mode after saving
    header("Location: nurse_schedule_editing.php?view_sched_id=" . urlencode($employee_id));
    exit();
}

// Handle schedule delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_schedule'])) {
    $schedule_id = $_POST['schedule_id'];
    $stmt = $conn->prepare("DELETE FROM shift_scheduling WHERE schedule_id = ?");
    $stmt->bind_param("s", $schedule_id);
    $stmt->execute();
    $success = "Schedule deleted successfully!";
}

// Fetch all schedules for modal view
$modal_schedules = [];
$edit_sched_id = $_GET['edit_sched_id'] ?? null;
if (isset($_GET['view_sched_id'])) {
    $view_id = $_GET['view_sched_id'];
    $stmt = $conn->prepare("SELECT * FROM shift_scheduling WHERE employee_id = ? ORDER BY week_start DESC");
    $stmt->bind_param("i", $view_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $modal_schedules[] = $row;
    }
}


?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HMS | Doctor and Nurse Management</title>
    <link rel="shortcut icon" href="assets/image/favicon.ico" type="image/x-icon">
    <link rel="stylesheet" href="assets/CSS/bootstrap.min.css">
    <link rel="stylesheet" href="assets/CSS/super.css">
</head>

<body>
    <div class="d-flex">
        <!----- Sidebar ----->
        <aside id="sidebar" class="sidebar-toggle">

            <div class="sidebar-logo mt-3">
                <img src="assets/image/logo-dark.png" width="90px" height="20px">
            </div>

            <div class="menu-title">Navigation</div>

            <!----- Sidebar Navigation ----->
        
            <li class="sidebar-item">
                <a href="doctor_dashboard.php" class="sidebar-link" data-bs-toggle="#" data-bs-target="#"
                    aria-expanded="false" aria-controls="auth">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-cast" viewBox="0 0 16 16">
                        <path d="m7.646 9.354-3.792 3.792a.5.5 0 0 0 .353.854h7.586a.5.5 0 0 0 .354-.854L8.354 9.354a.5.5 0 0 0-.708 0" />
                        <path d="M11.414 11H14.5a.5.5 0 0 0 .5-.5v-7a.5.5 0 0 0-.5-.5h-13a.5.5 0 0 0-.5.5v7a.5.5 0 0 0 .5.5h3.086l-1 1H1.5A1.5 1.5 0 0 1 0 10.5v-7A1.5 1.5 0 0 1 1.5 2h13A1.5 1.5 0 0 1 16 3.5v7a1.5 1.5 0 0 1-1.5 1.5h-2.086z" />
                    </svg>
                    <span style="font-size: 18px;">Dashboard</span>
                </a>
            </li>

            <li class="sidebar-item">
                <a href="#" class="sidebar-link collapsed has-dropdown" data-bs-toggle="collapse" data-bs-target="#gerald"
                    aria-expanded="true" aria-controls="auth">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-person-vcard"
                        viewBox="0 0 16 16" style="margin-bottom: 6px;">
                        <path
                            d="M5 8a2 2 0 1 0 0-4 2 2 0 0 0 0 4m4-2.5a.5.5 0 0 1 .5-.5h4a.5.5 0 0 1 0 1h-4a.5.5 0 0 1-.5-.5M9 8a.5.5 0 0 1 .5-.5h4a.5.5 0 0 1 0 1h-4A.5.5 0 0 1 9 8m1 2.5a.5.5 0 0 1 .5-.5h3a.5.5 0 0 1 0 1h-3a.5.5 0 0 1-.5-.5" />
                        <path
                            d="M2 2a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V4a2 2 0 0 0-2-2zM1 4a1 1 0 0 1 1-1h12a1 1 0 0 1 1 1v8a1 1 0 0 1-1 1H8.96q.04-.245.04-.5C9 10.567 7.21 9 5 9c-2.086 0-3.8 1.398-3.984 3.181A1 1 0 0 1 1 12z" />
                    </svg>
                    <span style="font-size: 18px;">Scheduling Shifts and Duties</span>
                </a>

                <ul id="gerald" class="sidebar-dropdown list-unstyled collapse" data-bs-parent="#sidebar">
                    <li class="sidebar-item">
                        <a href="doctor_shift_scheduling.php" class="sidebar-link">Doctor Shift Scheduling</a>
                    </li>
                    <li class="sidebar-item">
                        <a href="nurse_shift_scheduling.php" class="sidebar-link">Nurse Shift Scheduling</a>
                    </li>
                    <li class="sidebar-item">
                        <a href="../Employee/admin.php" class="sidebar-link">Doctor Duty</a>
                    </li>
                       <li class="sidebar-item">
                        <a href="../Employee/admin.php" class="sidebar-link">Nurse Duty</a>
                    </li>
                       <li class="sidebar-item">
                        <a href="schedule_calendar.php" class="sidebar-link">Schedule Calendar</a>
                    </li>
                </ul>
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
            <div class="container mt-5">
    <h2 class="mb-4">Nurses Schedule Editing</h2>
      <a href="nurse_shift_scheduling.php" class="btn btn-info">
                  Back
                </a>
    <?php if (!empty($success)): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
    <?php elseif (!empty($error)): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <!-- Nurses List Table -->
    <div class="card mb-4">
        <div class="card-header bg-primary text-white">Nurses List</div>
        <div class="card-body">
            <table class="table table-bordered table-striped">
                <thead>
                    <tr>
                        <th>Employee ID</th>
                        <th>First Name</th>
                        <th>Middle Name</th>
                        <th>Last Name</th>
                        <th>Role</th>
                        <th>Profession</th>
                        <th>Department</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($nurses as $doc): ?>
                        <tr>
                            <td><?= htmlspecialchars($doc['employee_id']) ?></td>
                            <td><?= htmlspecialchars($doc['first_name']) ?></td>
                            <td><?= htmlspecialchars($doc['middle_name']) ?></td>
                            <td><?= htmlspecialchars($doc['last_name']) ?></td>
                            <td><?= htmlspecialchars($doc['role']) ?></td>
                            <td><?= htmlspecialchars($doc['profession']) ?></td>
                            <td><?= htmlspecialchars($doc['department']) ?></td>
                            <td>
                                <form method="get" style="display:inline;">
                                    <input type="hidden" name="view_sched_id" value="<?= htmlspecialchars($doc['employee_id']) ?>">
                                    <button type="submit" class="btn btn-sm btn-info">View Schedule</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Modal for Viewing, Editing, and Deleting Schedules -->
    <?php if (!empty($modal_schedules)): ?>
    <div class="modal fade show" id="scheduleModal" tabindex="-1" style="display:block; background:rgba(0,0,0,0.5);" aria-modal="true" role="dialog">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Schedules for Nurse ID: <?= htmlspecialchars($modal_schedules[0]['employee_id']) ?></h5>
                    <a href="nurse_schedule_editing.php" class="btn-close"></a>
                </div>
                <div class="modal-body">
                    <?php foreach ($modal_schedules as $modal_schedule): ?>
                    <?php $is_editing = ($edit_sched_id == $modal_schedule['schedule_id']); ?>
                    <form method="POST" class="mb-4 border rounded p-3">
                        <input type="hidden" name="schedule_id" value="<?= htmlspecialchars($modal_schedule['schedule_id']) ?>">
                        <input type="hidden" name="employee_id" value="<?= htmlspecialchars($modal_schedule['employee_id']) ?>">
                        <h6>Week: 
                            <?php if ($is_editing): ?>
                                <input type="date" name="week_start" class="form-control d-inline-block w-auto"
                                    value="<?= htmlspecialchars($modal_schedule['week_start']) ?>">
                            <?php else: ?>
                                <?= htmlspecialchars($modal_schedule['week_start']) ?>
                            <?php endif; ?>
                        </h6>
                        <table class="table table-bordered bg-white" style="border-radius:8px;overflow:hidden;">
                            <thead>
                                <tr style="background:#007bff;color:#fff;">
                                    <th>Day</th>
                                    <th>Start Time</th>
                                    <th>End Time</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($days as $day): ?>
                                    <?php $prefix = strtolower(substr($day, 0, 3)); ?>
                                    <tr>
                                        <td><?= $day ?></td>
                                        <td>
                                            <?php if (!$is_editing): ?>
                                                <?php if (in_array(($modal_schedule[$prefix . '_status'] ?? ''), ['Off Duty', 'Leave', 'Sick'])): ?>
                                                    ---
                                                <?php else: ?>
                                                    <?= htmlspecialchars($modal_schedule[$prefix . '_start'] ?? '') ?>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <input type="time" name="<?= $prefix ?>_start" class="form-control"
                                                    value="<?= htmlspecialchars($modal_schedule[$prefix . '_start'] ?? '') ?>">
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if (!$is_editing): ?>
                                                <?php if (in_array(($modal_schedule[$prefix . '_status'] ?? ''), ['Off Duty', 'Leave', 'Sick'])): ?>
                                                    ---
                                                <?php else: ?>
                                                    <?= htmlspecialchars($modal_schedule[$prefix . '_end'] ?? '') ?>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <input type="time" name="<?= $prefix ?>_end" class="form-control"
                                                    value="<?= htmlspecialchars($modal_schedule[$prefix . '_end'] ?? '') ?>">
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if (!$is_editing): ?>
                                                <?= htmlspecialchars($modal_schedule[$prefix . '_status'] ?? '') ?>
                                            <?php else: ?>
                                                <select name="<?= $prefix ?>_status" class="form-select">
                                                    <option value="">-- Select Status --</option>
                                                    <option value="On Duty" <?= ($modal_schedule[$prefix . '_status'] ?? '') == 'On Duty' ? 'selected' : '' ?>>On Duty</option>
                                                    <option value="Off Duty" <?= ($modal_schedule[$prefix . '_status'] ?? '') == 'Off Duty' ? 'selected' : '' ?>>Off Duty</option>
                                                    <option value="Leave" <?= ($modal_schedule[$prefix . '_status'] ?? '') == 'Leave' ? 'selected' : '' ?>>Leave</option>
                                                    <option value="Sick" <?= ($modal_schedule[$prefix . '_status'] ?? '') == 'Sick' ? 'selected' : '' ?>>Sick</option>
                                                </select>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <div class="d-flex gap-2 mt-2">
                            <?php if ($is_editing): ?>
                                <button type="submit" name="update_schedule" class="btn btn-success">Save Changes</button>
                                <a href="?view_sched_id=<?= htmlspecialchars($modal_schedule['employee_id']) ?>" class="btn btn-secondary">Cancel</a>
                            <?php else: ?>
                                <a href="?view_sched_id=<?= htmlspecialchars($modal_schedule['employee_id']) ?>&edit_sched_id=<?= htmlspecialchars($modal_schedule['schedule_id']) ?>" class="btn btn-warning">Edit</a>
                            <?php endif; ?>
                            <button type="submit" name="delete_schedule" class="btn btn-danger" onclick="return confirm('Are you sure you want to delete this schedule?');">Delete</button>
                        </div>
                    </form>
                    <?php endforeach; ?>
                </div>
                <div class="modal-footer">
                    <a href="nurse_schedule_editing.php" class="btn btn-secondary">Close</a>
                </div>
            </div>
        </div>
    </div>
    <script>
        document.body.classList.add('modal-open');
    </script>
    <?php endif; ?>

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