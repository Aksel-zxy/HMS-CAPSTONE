<?php
include '../../../SQL/config.php';

class DoctorDashboard {
    public $conn;
    public $user;

    public function __construct($conn) {
        $this->conn = $conn;
        $this->authenticate();
        $this->fetchUser();
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
}

$dashboard = new DoctorDashboard($conn);
$user = $dashboard->user;

// Fetch unique employees (Doctor/Nurse) who have documents
$emp_query = "
    SELECT DISTINCT e.employee_id, e.first_name, e.last_name, e.profession
    FROM hr_employees_documents d
    INNER JOIN hr_employees e ON d.employee_id = e.employee_id
    WHERE e.profession IN ('Doctor', 'Nurse')
    ORDER BY e.last_name ASC
";
$employees = $conn->query($emp_query);

// If modal is triggered, fetch all documents for that employee
$modal_docs = [];
$modal_emp_id = $_GET['view_docs'] ?? null;
if ($modal_emp_id) {
    $doc_query = "
        SELECT d.document_id, d.document_type, d.file_blob, d.uploaded_at
        FROM hr_employees_documents d
        WHERE d.employee_id = ?
        ORDER BY d.uploaded_at DESC
    ";
    $stmt = $conn->prepare($doc_query);
    $stmt->bind_param("i", $modal_emp_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $modal_docs[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HMS | Doctor and Nurse Management</title>
    <link rel="shortcut icon" href="../assets/image/favicon.ico" type="image/x-icon">
    <link rel="stylesheet" href="../assets/CSS/bootstrap.min.css">
    <link rel="stylesheet" href="../assets/CSS/super.css">
     <link rel="stylesheet" href="../assets/CSS/license_management.css">
     
</head>

<body>
    <div class="d-flex">
        <!----- Sidebar ----->
       <aside id="sidebar" class="sidebar-toggle">

            <div class="sidebar-logo mt-3">
                <img src="../assets/image/logo-dark.png" width="90px" height="20px">
            </div>

            <div class="menu-title">Navigation</div>

            <!----- Sidebar Navigation ----->
        
            <li class="sidebar-item">
                <a href="../doctor_dashboard.php" class="sidebar-link" data-bs-toggle="#" data-bs-target="#"
                    aria-expanded="false" aria-controls="auth">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-cast" viewBox="0 0 16 16">
                        <path d="m7.646 9.354-3.792 3.792a.5.5 0 0 0 .353.854h7.586a.5.5 0 0 0 .354-.854L8.354 9.354a.5.5 0 0 0-.708 0" />
                        <path d="M11.414 11H14.5a.5.5 0 0 0 .5-.5v-7a.5.5 0 0 0-.5-.5h-13a.5.5 0 0 0-.5.5v7a.5.5 0 0 0 .5.5h3.086l-1 1H1.5A1.5 1.5 0 0 1 0 10.5v-7A1.5 1.5 0 0 1 1.5 2h13A1.5 1.5 0 0 1 16 3.5v7a1.5 1.5 0 0 1-1.5 1.5h-2.086z" />
                    </svg>
                    <span style="font-size: 18px;">Dashboard</span>
                </a>
            </li>

            <li class="sidebar-item">
                <a href="#" class="sidebar-link collapsed has-dropdown" data-bs-toggle="collapse" data-bs-target="#schedule"
                    aria-expanded="true" aria-controls="auth">
                   <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-cast" viewBox="0 0 640 512"><!--!Font Awesome Free v7.0.0 by @fontawesome - https://fontawesome.com License - https://fontawesome.com/license/free Copyright 2025 Fonticons, Inc.--><path d="M320 16a104 104 0 1 1 0 208 104 104 0 1 1 0-208zM96 88a72 72 0 1 1 0 144 72 72 0 1 1 0-144zM0 416c0-70.7 57.3-128 128-128 12.8 0 25.2 1.9 36.9 5.4-32.9 36.8-52.9 85.4-52.9 138.6l0 16c0 11.4 2.4 22.2 6.7 32L32 480c-17.7 0-32-14.3-32-32l0-32zm521.3 64c4.3-9.8 6.7-20.6 6.7-32l0-16c0-53.2-20-101.8-52.9-138.6 11.7-3.5 24.1-5.4 36.9-5.4 70.7 0 128 57.3 128 128l0 32c0 17.7-14.3 32-32 32l-86.7 0zM472 160a72 72 0 1 1 144 0 72 72 0 1 1 -144 0zM160 432c0-88.4 71.6-160 160-160s160 71.6 160 160l0 16c0 17.7-14.3 32-32 32l-256 0c-17.7 0-32-14.3-32-32l0-16z"/></svg>
                    <span style="font-size: 18px;">Scheduling Shifts and Duties</span>
                </a>

                <ul id="schedule" class="sidebar-dropdown list-unstyled collapse" data-bs-parent="#sidebar">
                    <li class="sidebar-item">
                        <a href="../scheduling_shifts_and_duties/doctor_shift_scheduling.php" class="sidebar-link">Doctor Shift Scheduling</a>
                    </li>
                    <li class="sidebar-item">
                        <a href="../scheduling_shifts_and_duties/nurse_shift_scheduling.php" class="sidebar-link">Nurse Shift Scheduling</a>
                    </li>
                     <li class="sidebar-item">
                        <a href="../scheduling_shifts_and_duties/duty_assignment.php" class="sidebar-link">Duty Assignment</a>
                    </li>
                       <li class="sidebar-item">
                        <a href="../scheduling_shifts_and_duties/schedule_calendar.php" class="sidebar-link">Schedule Calendar</a>
                    </li>
                </ul>
            </li>

<li class="sidebar-item">
                <a href="#" class="sidebar-link collapsed has-dropdown" data-bs-toggle="collapse" data-bs-target="#license"
                                 aria-expanded="true" aria-controls="auth">
                   <svg xmlns="http://www.w3.org/2000/svg"  width="16" height="16" fill="currentColor" class="bi bi-cast" viewBox="0 0 640 640"><!--!Font Awesome Free v7.0.0 by @fontawesome - https://fontawesome.com License - https://fontawesome.com/license/free Copyright 2025 Fonticons, Inc.--><path d="M80 480L80 224L560 224L560 480C560 488.8 552.8 496 544 496L352 496C352 451.8 316.2 416 272 416L208 416C163.8 416 128 451.8 128 496L96 496C87.2 496 80 488.8 80 480zM96 96C60.7 96 32 124.7 32 160L32 480C32 515.3 60.7 544 96 544L544 544C579.3 544 608 515.3 608 480L608 160C608 124.7 579.3 96 544 96L96 96zM240 376C270.9 376 296 350.9 296 320C296 289.1 270.9 264 240 264C209.1 264 184 289.1 184 320C184 350.9 209.1 376 240 376zM408 272C394.7 272 384 282.7 384 296C384 309.3 394.7 320 408 320L488 320C501.3 320 512 309.3 512 296C512 282.7 501.3 272 488 272L408 272zM408 368C394.7 368 384 378.7 384 392C384 405.3 394.7 416 408 416L488 416C501.3 416 512 405.3 512 392C512 378.7 501.3 368 488 368L408 368z"/></svg>
                    <span style="font-size: 18px;">Doctor & Nurse Registration & Compliance Licensing</span>
                </a>

                <ul id="license" class="sidebar-dropdown list-unstyled collapse" data-bs-parent="#sidebar">
                    <li class="sidebar-item">
                        <a href="registration_clinical_profile.php" class="sidebar-link">Registration & Clinical Profile Management</a>
                    </li>
                    <li class="sidebar-item">
                        <a href="license_management.php" class="sidebar-link">License Management</a>
                    </li>
                     <li class="sidebar-item">
                        <a href="duty_assignment.php" class="sidebar-link">Compliance Monitoring Dashboard</a>
                    </li>
                    <li class="sidebar-item">
                        <a href="../Employee/admin.php" class="sidebar-link">Notifications & Alerts</a>
                    </li>
                       <li class="sidebar-item">
                        <a href="../Employee/admin.php" class="sidebar-link">Compliance Audit Log</a>
                    </li>
                </ul>
            </li>

              <li class="sidebar-item">
                <a href="doctor_dashboard.php" class="sidebar-link" data-bs-toggle="#" data-bs-target="#"
                    aria-expanded="false" aria-controls="auth">
                   <svg xmlns="http://www.w3.org/2000/svg"  width="16" height="16" fill="currentColor" class="bi bi-cast" viewBox="0 0 640 640"><!--!Font Awesome Free v7.0.0 by @fontawesome - https://fontawesome.com License - https://fontawesome.com/license/free Copyright 2025 Fonticons, Inc.--><path d="M96 96C113.7 96 128 110.3 128 128L128 464C128 472.8 135.2 480 144 480L544 480C561.7 480 576 494.3 576 512C576 529.7 561.7 544 544 544L144 544C99.8 544 64 508.2 64 464L64 128C64 110.3 78.3 96 96 96zM208 288C225.7 288 240 302.3 240 320L240 384C240 401.7 225.7 416 208 416C190.3 416 176 401.7 176 384L176 320C176 302.3 190.3 288 208 288zM352 224L352 384C352 401.7 337.7 416 320 416C302.3 416 288 401.7 288 384L288 224C288 206.3 302.3 192 320 192C337.7 192 352 206.3 352 224zM432 256C449.7 256 464 270.3 464 288L464 384C464 401.7 449.7 416 432 416C414.3 416 400 401.7 400 384L400 288C400 270.3 414.3 256 432 256zM576 160L576 384C576 401.7 561.7 416 544 416C526.3 416 512 401.7 512 384L512 160C512 142.3 526.3 128 544 128C561.7 128 576 142.3 576 160z"/></svg>
                    <span style="font-size: 18px;">Performance and Evaluation</span>
                </a>
            </li>



        <li class="sidebar-item">
                <a href="#" class="sidebar-link collapsed has-dropdown" data-bs-toggle="collapse" data-bs-target="#doctor"
                                 aria-expanded="true" aria-controls="auth">
                  <svg xmlns="http://www.w3.org/2000/svg"  width="16" height="16" fill="currentColor" class="bi bi-cast" viewBox="0 0 640 640"><!--!Font Awesome Free v7.0.0 by @fontawesome - https://fontawesome.com License - https://fontawesome.com/license/free Copyright 2025 Fonticons, Inc.--><path d="M320 72C253.7 72 200 125.7 200 192C200 258.3 253.7 312 320 312C386.3 312 440 258.3 440 192C440 125.7 386.3 72 320 72zM380 384.8C374.6 384.3 369 384 363.4 384L276.5 384C270.9 384 265.4 384.3 259.9 384.8L259.9 452.3C276.4 459.9 287.9 476.6 287.9 495.9C287.9 522.4 266.4 543.9 239.9 543.9C213.4 543.9 191.9 522.4 191.9 495.9C191.9 476.5 203.4 459.8 219.9 452.3L219.9 393.9C157 417 112 477.6 112 548.6C112 563.7 124.3 576 139.4 576L500.5 576C515.6 576 527.9 563.7 527.9 548.6C527.9 477.6 482.9 417.1 419.9 394L419.9 431.4C443.2 439.6 459.9 461.9 459.9 488L459.9 520C459.9 531 450.9 540 439.9 540C428.9 540 419.9 531 419.9 520L419.9 488C419.9 477 410.9 468 399.9 468C388.9 468 379.9 477 379.9 488L379.9 520C379.9 531 370.9 540 359.9 540C348.9 540 339.9 531 339.9 520L339.9 488C339.9 461.9 356.6 439.7 379.9 431.4L379.9 384.8z"/></svg>
                    <span style="font-size: 18px;">Doctor Panel</span>
                </a>

                <ul id="doctor" class="sidebar-dropdown list-unstyled collapse" data-bs-parent="#sidebar">
                  <li class="sidebar-item">
                        <a href="../Employee/admin.php" class="sidebar-link">My Schedule</a>
                  </li>
                  <li class="sidebar-item">
                        <a href="../Employee/admin.php" class="sidebar-link">Doctor Duty</a>
                    </li>
                    <li class="sidebar-item">
                        <a href="../Employee/admin.php" class="sidebar-link">View Clinical Profile</a>
                    </li>
                    <li class="sidebar-item">
                        <a href="../Employee/admin.php" class="sidebar-link">License & Compliance Viewer</a>
                    </li>
                    <li class="sidebar-item">
                        <a href="../Employee/admin.php" class="sidebar-link">Upload Renewal Documents</a>
                    </li>
                    <li class="sidebar-item">
                        <a href="../Employee/admin.php" class="sidebar-link">Notification Alerts</a>
                    </li>
                </ul>
            </li>

                <li class="sidebar-item">
                <a href="#" class="sidebar-link collapsed has-dropdown" data-bs-toggle="collapse" data-bs-target="#nurse"
                                 aria-expanded="true" aria-controls="auth">
                  <svg xmlns="http://www.w3.org/2000/svg"  width="16" height="16" fill="currentColor" class="bi bi-cast" viewBox="0 0 640 640"><!--!Font Awesome Free v7.0.0 by @fontawesome - https://fontawesome.com License - https://fontawesome.com/license/free Copyright 2025 Fonticons, Inc.--><path d="M192 108.9C192 96.2 199.5 84.7 211.2 79.6L307.2 37.6C315.4 34 324.7 34 332.9 37.6L428.9 79.6C440.5 84.7 448 96.2 448 108.9L448 208C448 278.7 390.7 336 320 336C249.3 336 192 278.7 192 208L192 108.9zM400 192L288.4 192L288 192L240 192L240 208C240 252.2 275.8 288 320 288C364.2 288 400 252.2 400 208L400 192zM304 80L304 96L288 96C283.6 96 280 99.6 280 104L280 120C280 124.4 283.6 128 288 128L304 128L304 144C304 148.4 307.6 152 312 152L328 152C332.4 152 336 148.4 336 144L336 128L352 128C356.4 128 360 124.4 360 120L360 104C360 99.6 356.4 96 352 96L336 96L336 80C336 75.6 332.4 72 328 72L312 72C307.6 72 304 75.6 304 80zM238.6 387C232.1 382.1 223.4 380.8 216 384.2C154.6 412.4 111.9 474.4 111.9 546.3C111.9 562.7 125.2 576 141.6 576L498.2 576C514.6 576 527.9 562.7 527.9 546.3C527.9 474.3 485.2 412.3 423.8 384.2C416.4 380.8 407.7 382.1 401.2 387L334.2 437.2C325.7 443.6 313.9 443.6 305.4 437.2L238.4 387z"/></svg>
                    <span style="font-size: 18px;">Nurse Panel</span>
                </a>

                <ul id="nurse" class="sidebar-dropdown list-unstyled collapse" data-bs-parent="#sidebar">
                    <li class="sidebar-item">
                        <a href="../Employee/admin.php" class="sidebar-link">My Schedule</a>
                    </li>
                     <li class="sidebar-item">
                        <a href="../Employee/admin.php" class="sidebar-link">Nurse Duty</a>
                    </li>
                      <li class="sidebar-item">
                        <a href="../Employee/admin.php" class="sidebar-link">View Clinical Profile</a>
                    </li>
                    <li class="sidebar-item">
                        <a href="../Employee/admin.php" class="sidebar-link">License & Compliance Viewer</a>
                    </li>
                    <li class="sidebar-item">
                        <a href="../Employee/admin.php" class="sidebar-link">Upload Renewal Documents</a>
                    </li>
                    <li class="sidebar-item">
                        <a href="../Employee/admin.php" class="sidebar-link">Notification Alerts</a>
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
                                <a class="dropdown-item" href="../../logout.php" style="font-size: 14px; color: #007bff; text-decoration: none; padding: 8px 12px; border-radius: 4px; transition: background-color 0.3s ease;">
                                    Logout
                                </a>
                            </li>
                        </ul>

                    </div>
                </div>
            </div>
            <!-- START CODING HERE -->
             <div class="container-fluid">



             <h2  style="font-family:Arial, sans-serif; color:#0d6efd; margin-bottom:20px; border-bottom:2px solid #0d6efd; padding-bottom:8px;">🪪Doctor & Nurse Documents</h2>
<table>
    <tr>
        <th>Employee ID</th>
        <th>Name</th>
        <th>Profession</th>
        <th>View Documents</th>
    </tr>
    <?php while ($row = $employees->fetch_assoc()): ?>
    <tr>
        <td><?= htmlspecialchars($row['employee_id']); ?></td>
        <td><?= htmlspecialchars($row['first_name'] . " " . $row['last_name']); ?></td>
        <td><?= htmlspecialchars($row['profession']); ?></td>
        <td>
            <a href="?view_docs=<?= htmlspecialchars($row['employee_id']); ?>" class="btn btn-primary btn-sm">View Documents</a>
        </td>
    </tr>
    <?php endwhile; ?>
</table>

<?php if ($modal_emp_id): ?>
<!-- Modal for documents -->
<div class="modal fade show" id="docsModal" tabindex="-1" aria-modal="true" role="dialog" style="display:block; background:rgba(0,0,0,0.3);">
    <div class="modal-dialog modal-lg">
        <div class="modal-content rounded shadow">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">Documents for Employee ID: <?= htmlspecialchars($modal_emp_id) ?></h5>
                <a href="license_management.php" class="btn-close"></a>
            </div>
            <div class="modal-body">
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th>Document ID</th>
                            <th>Document Type</th>
                            <th>View File</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($modal_docs as $doc): ?>
                        <tr>
                            <td><?= htmlspecialchars($doc['document_id']); ?></td>
                            <td><?= htmlspecialchars($doc['document_type']); ?></td>
                            <td>
                             <td>
    <?php if (!empty($doc['file_blob'])): ?>
        <a href="../../HR Management/Recruitment & Onboarding Module/view_document"<?= urlencode($doc['file_blob']); ?>
           target="_blank" 
           class="btn btn-info btn-sm">View File</a>
    <?php else: ?>
        No File
    <?php endif; ?>
</td>

                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="modal-footer">
                <a href="license_management.php" class="btn btn-secondary">Close</a>
            </div>
        </div>
    </div>
    <script>document.body.classList.add('modal-open');</script>
</div>
<?php endif; ?>

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
    <script src="../assets/Bootstrap/all.min.js"></script>
    <script src="../assets/Bootstrap/bootstrap.bundle.min.js"></script>
    <script src="../assets/Bootstrap/fontawesome.min.js"></script>
    <script src="../assets/Bootstrap/jq.js"></script>
</body>

</html>







