<?php
include '../../SQL/config.php';

class DoctorShiftScheduling {
    public $conn;
    public $user;
    public $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
    public $doctors = [];
    public $professions = [];
    public $departments = [];

    public function __construct($conn) {
        $this->conn = $conn;
        $this->authenticate();
        $this->fetchUser();
        $this->fetchDoctors();
        $this->fetchProfessions();
        $this->fetchDepartments();
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

    private function fetchDoctors() {
        $doctor_query = "SELECT employee_id, first_name, last_name FROM hr_employees WHERE profession = 'Doctor'";
        $doctor_result = $this->conn->query($doctor_query);
        if ($doctor_result && $doctor_result->num_rows > 0) {
            while ($row = $doctor_result->fetch_assoc()) {
                $this->doctors[] = $row;
            }
        }
    }

    private function fetchProfessions() {
        $profession_query = "SELECT DISTINCT profession FROM hr_employees";
        $profession_result = $this->conn->query($profession_query);
        if ($profession_result && $profession_result->num_rows > 0) {
            while ($row = $profession_result->fetch_assoc()) {
                $this->professions[] = $row['profession'];
            }
        }
    }

    private function fetchDepartments() {
        $dept_query = "SELECT DISTINCT department FROM hr_employees WHERE department IS NOT NULL AND department != ''";
        $dept_result = $this->conn->query($dept_query);
        if ($dept_result && $dept_result->num_rows > 0) {
            while ($row = $dept_result->fetch_assoc()) {
                $this->departments[] = $row['department'];
            }
        }
    }
}

$doctorSched = new DoctorShiftScheduling($conn);
$user = $doctorSched->user;
$days = $doctorSched->days;
$doctors = $doctorSched->doctors;
$professions = $doctorSched->professions;
$departments = $doctorSched->departments;

// Handle schedule form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['employee_id'], $_POST['week_start'])) {
    $employee_id = $_POST['employee_id'];
    $week_start = $_POST['week_start'];
    $created_at = date('Y-m-d H:i:s');
    $schedule_id = uniqid('sched_');

    // Check if employee_id is not empty and exists in hr_employees
    $check_stmt = $conn->prepare("SELECT employee_id FROM hr_employees WHERE employee_id = ?");
    $check_stmt->bind_param("i", $employee_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();

    if (empty($employee_id) || $check_result->num_rows === 0) {
        $error = "Selected employee ID does not exist or is empty. Please choose a valid Doctor.";
    } else {
        // Collect start, end, and status for each day
        $mon_start = $_POST['mon_start'] ?? null;
        $mon_end   = $_POST['mon_end'] ?? null;
        $mon_status = $_POST['mon_status'] ?? null;
        $tue_start = $_POST['tue_start'] ?? null;
        $tue_end   = $_POST['tue_end'] ?? null;
        $tue_status = $_POST['tue_status'] ?? null;
        $wed_start = $_POST['wed_start'] ?? null;
        $wed_end   = $_POST['wed_end'] ?? null;
        $wed_status = $_POST['wed_status'] ?? null;
        $thu_start = $_POST['thu_start'] ?? null;
        $thu_end   = $_POST['thu_end'] ?? null;
        $thu_status = $_POST['thu_status'] ?? null;
        $fri_start = $_POST['fri_start'] ?? null;
        $fri_end   = $_POST['fri_end'] ?? null;
        $fri_status = $_POST['fri_status'] ?? null;
        $sat_start = $_POST['sat_start'] ?? null;
        $sat_end   = $_POST['sat_end'] ?? null;
        $sat_status = $_POST['sat_status'] ?? null;
        $sun_start = $_POST['sun_start'] ?? null;
        $sun_end   = $_POST['sun_end'] ?? null;
        $sun_status = $_POST['sun_status'] ?? null;

        $stmt = $conn->prepare(
            "INSERT INTO shift_scheduling 
            (employee_id, schedule_id, week_start, mon_start, mon_end, mon_status, tue_start, tue_end, tue_status, wed_start, wed_end, wed_status, thu_start, thu_end, thu_status, fri_start, fri_end, fri_status, sat_start, sat_end, sat_status, sun_start, sun_end, sun_status, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->bind_param(
            "sssssssssssssssssssssssss",
            $employee_id, $schedule_id, $week_start,
            $mon_start, $mon_end, $mon_status,
            $tue_start, $tue_end, $tue_status,
            $wed_start, $wed_end, $wed_status,
            $thu_start, $thu_end, $thu_status,
            $fri_start, $fri_end, $fri_status,
            $sat_start, $sat_end, $sat_status,
            $sun_start, $sun_end, $sun_status,
            $created_at
        );

        if ($stmt->execute()) {
            header("Location: doctor_shift_scheduling.php?success=1");
            exit();
        } else {
            $error = "Error saving schedule: " . $stmt->error;
        }
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
            <div class="container-fluid">
            

                <div class="container mt-5">
    <h2 class="mb-4">Doctor Shift Scheduling</h2>
    <?php if (isset($_GET['success'])): ?>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                let notif = document.createElement('div');
                notif.className = 'toast align-items-center text-bg-success border-0 show position-fixed top-0 end-0 m-3';
                notif.setAttribute('profession', 'alert');
                notif.setAttribute('aria-live', 'assertive');
                notif.setAttribute('aria-atomic', 'true');
                notif.innerHTML = `
                    <div class="d-flex">
                        <div class="toast-body">
                            Schedule saved successfully!
                        </div>
                        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                    </div>
                `;
                document.body.appendChild(notif);
                setTimeout(() => notif.remove(), 3000);
            });
        </script>
    <?php elseif (!empty($error)): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <!-- Select Doctor -->
    <form method="POST">
        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <label for="employee_id" class="form-label">Select Doctor</label>
                <select name="employee_id" id="employee_id" class="form-select" required>
                    <option value="">-- Choose a Doctor --</option>
                    <?php foreach ($doctors as $doc): ?>
                        <option value="<?= htmlspecialchars($doc['employee_id']) ?>">
                            <?= htmlspecialchars($doc['employee_id'] . ' - ' . $doc['first_name'] . ' ' . $doc['last_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label for="week_start" class="form-label">Week Starting</label>
                <input type="date" name="week_start" id="week_start" class="form-control" required>
            </div>
            <div></div>
                <a href="doctor_schedule_editing.php" class="btn btn-info">
                    Doctors Schedule Editing
                </a>
            </div>
        </div>

        <!-- Weekly Schedule Table -->
        <table class="table table-bordered bg-white">
            <thead>
                <tr>
                    <th>Day</th>
                    <th>Start Time</th>
                    <th>End Time</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($days as $day): ?>
                    <tr>
                        <td><?= $day ?></td>
                        <td><input type="time" name="<?= strtolower(substr($day, 0, 3)) ?>_start" class="form-control"></td>
                        <td><input type="time" name="<?= strtolower(substr($day, 0, 3)) ?>_end" class="form-control"></td>
                        <td>
                            <select name="<?= strtolower(substr($day, 0, 3)) ?>_status" class="form-select" required >
                                <option value="">-- Select Status --</option>
                                <option value="On Duty">On Duty</option>
                                <option value="Off Duty">Off Duty</option>
                                <option value="Leave">Leave</option>
                                <option value="Sick">Sick</option>
                                <!-- Add more statuses as needed -->
                            </select>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <button type="submit" class="btn btn-success">Save Schedule</button>
    </form>


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