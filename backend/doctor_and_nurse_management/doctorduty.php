<?php
include '../../../SQL/config.php';
require '../../Pharmacy Management/classes/Prescription.php';
require '../../Pharmacy Management/classes/Medicine.php';

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

// Fetch all appointment_ids already assigned in duty_assignments
$assigned_appointments = [];
$res = $conn->query("SELECT appointment_id FROM duty_assignments");
if ($res && $res->num_rows > 0) {
    while ($row = $res->fetch_assoc()) {
        $assigned_appointments[] = $row['appointment_id'];
    }
}

// Fetch all appointments, only show those not managed and not assigned in duty_assignments
$appointments = [];
$sql = "SELECT appointment_id, patient_id, appointment_date, purpose, status, notes, doctor_id 
        FROM p_appointments 
        WHERE status != 'Managed'" . 
        (!empty($assigned_appointments) ? " AND appointment_id NOT IN (" . implode(',', array_map('intval', $assigned_appointments)) . ")" : "") . 
        " ORDER BY appointment_date DESC";
$result = $conn->query($sql);
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $appointments[] = $row;
    }
}

// Get selected appointment_id from GET
$appointment_id = $_GET['appointment_id'] ?? '';

// Fetch doctor_id from appointment for duty assignment
$selected_doctor_id = '';
if ($appointment_id) {
    $stmt = $conn->prepare("SELECT doctor_id FROM p_appointments WHERE appointment_id = ?");
    $stmt->bind_param("i", $appointment_id);
    $stmt->execute();
    $stmt->bind_result($selected_doctor_id);
    $stmt->fetch();
    $stmt->close();
}

// Fetch beds for dropdown
$beds = [];
$bed_res = $conn->query("SELECT bed_id, bed_number, ward, room_number, bed_type, status, notes FROM p_beds");
if ($bed_res && $bed_res->num_rows > 0) {
    while ($row = $bed_res->fetch_assoc()) {
        $beds[] = $row;
    }
}

// Fetch nurses for dropdown (profession = 'Nurse')
$nurses = [];
$nurse_res = $conn->query("SELECT employee_id, first_name, last_name FROM hr_employees WHERE profession = 'Nurse' AND status = 'active'");
if ($nurse_res && $nurse_res->num_rows > 0) {
    while ($row = $nurse_res->fetch_assoc()) {
        $nurses[] = $row;
    }
}

// Fetch equipment types and names (example static, replace with DB if needed)
$equipment_types = [
    'PPE' => ['Surgical gloves', 'Examination gloves', 'Face masks', 'Surgical caps', 'Shoe covers', 'Gowns', 'Face shields'],
    'Patient Care Supplies' => ['Disposable bed sheets', 'Adult diapers', 'Underpads', 'Disposable towels', 'Paper gowns'],
    'Surgical & Procedure Consumables' => ['Syringes', 'Needles', 'IV cannulas', 'Infusion sets', 'Surgical drapes', 'Surgical blades', 'Sutures', 'Cotton balls', 'Gauze pads', 'Bandages', 'Suction catheters'],
    'Laboratory Consumables' => ['Test tubes', 'Pipette tips', 'Petri dishes', 'Blood collection tubes', 'Urine containers', 'Swabs', 'Disposable centrifuge tubes'],
    'General Hospital Consumables' => ['Alcohol pads', 'Hand sanitizers', 'Disinfectant wipes', 'Disposable thermometers', 'Sharps containers', 'Biohazard bags']
];

// Flatten tools for selection
$tool_options = [];
foreach ($equipment_types as $type => $names) {
    foreach ($names as $name) {
        $tool_options[] = ['type' => $type, 'name' => $name];
    }
}

// Fetch machine equipments for equipment selection and listing
$machine_equipments = [];
$machine_types = [];
$machine_res = $conn->query("SELECT machine_id, machine_type, machine_name FROM machine_equipments");
if ($machine_res && $machine_res->num_rows > 0) {
    while ($row = $machine_res->fetch_assoc()) {
        $machine_equipments[] = $row;
        $machine_types[$row['machine_type']][] = $row;
    }
}

// Handle duty assignment form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_duty'])) {
    $appointment_id = $_POST['appointment_id'];
    $doctor_id = $selected_doctor_id;
    $bed_id = !empty($_POST['bed_id']) ? $_POST['bed_id'] : null;
    $nurse_assistant = !empty($_POST['nurse_assistant']) ? $_POST['nurse_assistant'] : null;
    $procedure = $_POST['procedure'];
    $notes = $_POST['notes'];
    $status = "Pending";

    // Equipment array: collect selected machine type/name pairs
    $equipments = [];
    if (!empty($_POST['equipment_type']) && !empty($_POST['equipment_name'])) {
        foreach ($_POST['equipment_type'] as $idx => $type) {
            $name = $_POST['equipment_name'][$idx] ?? '';
            if ($type && $name) {
                $equipments[] = $type . ' - ' . $name;
            }
        }
    }
    $equipment = implode(', ', $equipments); // Save as comma-separated string

    // Tools array
    $tools = [];
    if (!empty($_POST['tool_name']) && !empty($_POST['tool_qty'])) {
        foreach ($_POST['tool_name'] as $idx => $tool_name) {
            $qty = $_POST['tool_qty'][$idx] ?? 1;
            if ($tool_name) {
                $tools[] = ['name' => $tool_name, 'qty' => intval($qty)];
            }
        }
    }
    $tools_json = json_encode($tools);

    // Save duty assignment
    $stmt = $conn->prepare("INSERT INTO duty_assignments (appointment_id, doctor_id, bed_id, nurse_assistant, `procedure`, equipment, tools, notes, status, created_at) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
    $stmt->bind_param(
        "iiissssss",
        $appointment_id,
        $doctor_id,
        $bed_id,
        $nurse_assistant,
        $procedure,
        $equipment,
        $tools_json,
        $notes,
        $status
    );
    $stmt->execute();

    // Insert purchase request for tools
    if (!empty($tools)) {
        $user_id = $doctor_id; // hr_employees.employee_id
        $items = $tools_json;
        $pr_status = "Pending";
        $created_at = date('Y-m-d H:i:s');
        $stmt2 = $conn->prepare("INSERT INTO purchase_requests (user_id, items, status, created_at) VALUES (?, ?, ?, ?)");
        $stmt2->bind_param("isss", $user_id, $items, $pr_status, $created_at);
        $stmt2->execute();
    }

    header("Location: doctor_duty.php?success=1");
    exit;
}

// Handle mark as completed action
if (isset($_GET['complete_duty_id'])) {
    $complete_duty_id = intval($_GET['complete_duty_id']);
    // Update status and updated_at timestamp
    $stmt = $conn->prepare("UPDATE duty_assignments SET status = 'Completed', updated_at = NOW() WHERE duty_id = ?");
    $stmt->bind_param("i", $complete_duty_id);
    $stmt->execute();
    $stmt->close();
    header("Location: doctor_duty.php");
    exit;
}

// Fetch all duty assignments for "My Duties" table, only show non-completed
$duties = [];
$duty_res = $conn->query("SELECT duty_id, appointment_id, doctor_id, bed_id, nurse_assistant, `procedure`, equipment, tools, notes, status, created_at FROM duty_assignments WHERE status != 'Completed' ORDER BY created_at DESC");
if ($duty_res && $duty_res->num_rows > 0) {
    while ($row = $duty_res->fetch_assoc()) {
        $duties[] = $row;
    }
}




$medicineObj = new Medicine($conn);
$medicines = $medicineObj->getAllMedicines();

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

$prescription = new Prescription($conn);
$doctors = $prescription->getDoctors();
$patients = $prescription->getPatients();
// $medicines = $prescription->getMedicines();

$doctors = [];
$result_doc = $prescription->getDoctors();
if ($result_doc && $result_doc->num_rows > 0) {
    while ($row = $result_doc->fetch_assoc()) {
        $doctors[] = $row;
    }
}


$patients = [];
$result_pat = $prescription->getPatients();
if ($result_pat && $result_pat->num_rows > 0) {
    while ($row = $result_pat->fetch_assoc()) {
        $patients[] = $row;
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
    <link rel="stylesheet" href="../assets/CSS/user_duty.css">
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
                        <a href="../dnrcl/registration_clinical_profile.php" class="sidebar-link">Registration & Clinical Profile Management</a>
                    </li>
                    <li class="sidebar-item">
                        <a href="../dnrcl/license_management.php" class="sidebar-link">License Management</a>
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
                        <a href="doctor_duty.php" class="sidebar-link">Doctor Duty</a>
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
                        <a href="../nurse_panel/nurse_duty.php" class="sidebar-link">Nurse Duty</a>
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
    <h2  style="font-family:Arial, sans-serif; color:#0d6efd; margin-bottom:20px; border-bottom:2px solid #0d6efd; padding-bottom:8px">📌Appointments</h2>
                <table class="appointments-table table table-bordered table-hover">
                    <thead class="table-primary">
                        <tr>
                            <th>Appointment ID</th>
                            <th>Patient ID</th>
                            <th>Date</th>
                            <th>Purpose</th>
                            <th>Status</th>
                            <th>Notes</th>
                            <th>Doctor ID</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($appointments)): ?>
                            <?php foreach ($appointments as $appt): ?>
                                <tr>
                                    <td><?= htmlspecialchars($appt['appointment_id']); ?></td>
                                    <td><?= htmlspecialchars($appt['patient_id']); ?></td>
                                    <td><?= htmlspecialchars($appt['appointment_date']); ?></td>
                                    <td><?= htmlspecialchars($appt['purpose']); ?></td>
                                    <td><?= htmlspecialchars($appt['status']); ?></td>
                                    <td><?= htmlspecialchars($appt['notes']); ?></td>
                                    <td><?= htmlspecialchars($appt['doctor_id']); ?></td>
                                    <td>
                                        <a href="doctor_duty.php?appointment_id=<?= $appt['appointment_id']; ?>" class="assign-btn btn btn-primary btn-sm">Manage</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="8">No appointments found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
                <!-- Modal-like form for duty assignment -->
                <?php if ($appointment_id): ?>
                <div class="duty-modal" id="dutyModal">
                    <div class="form-container bg-white p-4 shadow rounded position-relative" style="max-width: 500px; width: 100%;">
                        <button class="close-modal-btn position-absolute top-0 end-0 m-2" onclick="window.location.href='doctor_duty.php'" aria-label="Close">&times;</button>
                        <h4 class="mb-4 text-center text-primary fw-bold">Manage Appointment</h4>
                        <form action="" method="POST">
                            <input type="hidden" name="appointment_id" value="<?= htmlspecialchars($appointment_id) ?>">

                            <div class="mb-3">
                                <label class="form-label">Bed</label>
                                <select name="bed_id" class="form-select form-select-sm" required>
                                    <option value="">-- Select Bed --</option>
                                    <?php foreach($beds as $row): ?>
                                        <?php if(strtolower($row['status']) == 'available'): ?>
                                            <option value="<?= $row['bed_id'] ?>">
                                                <?= htmlspecialchars($row['bed_number']) ?> (<?= htmlspecialchars($row['ward']) ?>, Room <?= htmlspecialchars($row['room_number']) ?>, <?= htmlspecialchars($row['bed_type']) ?>)
                                        </option>
                                        <?php else: ?>
                                            <option value="<?= $row['bed_id'] ?>" disabled style="color:#aaa;">
                                                <?= htmlspecialchars($row['bed_number']) ?> (<?= htmlspecialchars($row['ward']) ?>, Room <?= htmlspecialchars($row['room_number']) ?>, <?= htmlspecialchars($row['bed_type']) ?>) - <?= ucfirst($row['status']) ?>
                                        </option>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Nurse Assistant</label>
                                <select name="nurse_assistant" class="form-select form-select-sm">
                                    <option value="">-- Select Nurse --</option>
                                    <?php foreach($nurses as $row): ?>
                                        <option value="<?= $row['employee_id'] ?>">
                                            <?= htmlspecialchars($row['first_name'] . ' ' . $row['last_name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Procedure</label>
                                <input type="text" name="procedure" class="form-control form-control-sm" required>
                            </div>

                            <!-- Equipment selection by machine type and name -->
                            <div class="mb-3">
                                <label class="form-label">Equipment</label>
                                <div id="equipmentsList">
                                    <div class="d-flex mb-2 equipment-row">
                                        <select name="equipment_type[]" class="form-select form-select-sm equipment-type" style="width:48%;" required>
                                            <option value="">-- Select Machine Type --</option>
                                            <?php foreach(array_keys($machine_types) as $type): ?>
                                                <option value="<?= htmlspecialchars($type) ?>"><?= htmlspecialchars($type) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <select name="equipment_name[]" class="form-select form-select-sm equipment-name ms-2" style="width:48%;" required>
                                            <option value="">-- Select Machine Name --</option>
                                            <!-- JS will populate based on type -->
                                        </select>
                                        <button type="button" class="btn btn-danger btn-sm ms-2 remove-equipment">&times;</button>
                                    </div>
                                </div>
                                <button type="button" class="btn btn-success btn-sm mt-2" id="addEquipmentBtn">+ Add Equipment</button>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Tools Needed</label>
                                <div id="toolsList">
                                    <div class="d-flex mb-2 tool-row">
                                        <select name="tool_name[]" class="form-select form-select-sm" style="width:60%;">
                                            <option value="">-- Select Tool --</option>
                                            <?php foreach($tool_options as $tool): ?>
                                                <option value="<?= htmlspecialchars($tool['name']) ?>">
                                                    <?= htmlspecialchars($tool['type']) ?> - <?= htmlspecialchars($tool['name']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <input type="number" name="tool_qty[]" class="form-control form-control-sm ms-2" style="width:30%;" min="1" value="1" placeholder="Qty">
                                        <button type="button" class="btn btn-danger btn-sm ms-2 remove-tool">&times;</button>
                                    </div>
                                </div>
                                <button type="button" class="btn btn-success btn-sm mt-2" id="addToolBtn">+ Add Tool</button>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Notes</label>
                                <textarea name="notes" class="form-control form-control-sm" rows="2"></textarea>
                            </div>

                            <button type="submit" name="save_duty" class="btn btn-primary w-100 mt-2">Save Assignment</button>
                        </form>
                    </div>
                </div>
                <script>
                    // Equipment name dynamic population
                    const machineTypes = <?= json_encode($machine_types) ?>;
                    function updateEquipmentName(selectType, selectName) {
                        const type = selectType.value;
                        selectName.innerHTML = '<option value="">-- Select Machine Name --</option>';
                        if (machineTypes[type]) {
                            machineTypes[type].forEach(function(equip) {
                                selectName.innerHTML += '<option value="' + equip.machine_name + '">' + equip.machine_name + '</option>';
                            });
                        }
                    }
                    document.querySelectorAll('.equipment-type').forEach(function(selectType, idx) {
                        const selectName = document.querySelectorAll('.equipment-name')[idx];
                        selectType.addEventListener('change', function() {
                            updateEquipmentName(selectType, selectName);
                        });
                    });
                    document.getElementById('addEquipmentBtn').onclick = function() {
                        const equipmentsList = document.getElementById('equipmentsList');
                        const row = document.createElement('div');
                        row.className = 'd-flex mb-2 equipment-row';
                        row.innerHTML = `
                            <select name="equipment_type[]" class="form-select form-select-sm equipment-type" style="width:48%;" required>
                                <option value="">-- Select Machine Type --</option>
                                <?php foreach(array_keys($machine_types) as $type): ?>
                                    <option value="<?= htmlspecialchars($type) ?>"><?= htmlspecialchars($type) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <select name="equipment_name[]" class="form-select form-select-sm equipment-name ms-2" style="width:48%;" required>
                                <option value="">-- Select Machine Name --</option>
                            </select>
                            <button type="button" class="btn btn-danger btn-sm ms-2 remove-equipment">&times;</button>
                        `;
                        equipmentsList.appendChild(row);
                        // Attach change event for new row
                        const selectType = row.querySelector('.equipment-type');
                        const selectName = row.querySelector('.equipment-name');
                        selectType.addEventListener('change', function() {
                            updateEquipmentName(selectType, selectName);
                        });
                    };
                    document.getElementById('equipmentsList').addEventListener('click', function(e) {
                        if (e.target.classList.contains('remove-equipment')) {
                            e.target.parentElement.remove();
                        }
                    });

                    // Tools dynamic add/remove
                    document.getElementById('addToolBtn').onclick = function() {
                        const toolsList = document.getElementById('toolsList');
                        const row = document.createElement('div');
                        row.className = 'd-flex mb-2 tool-row';
                        row.innerHTML = `
                            <select name="tool_name[]" class="form-select form-select-sm" style="width:60%;">
                                <option value="">-- Select Tool --</option>
                                <?php foreach($tool_options as $tool): ?>
                                    <option value="<?= htmlspecialchars($tool['name']) ?>">
                                        <?= htmlspecialchars($tool['type']) ?> - <?= htmlspecialchars($tool['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <input type="number" name="tool_qty[]" class="form-control form-control-sm ms-2" style="width:30%;" min="1" value="1" placeholder="Qty">
                            <button type="button" class="btn btn-danger btn-sm ms-2 remove-tool">&times;</button>
                        `;
                        toolsList.appendChild(row);
                    };
                    document.getElementById('toolsList').addEventListener('click', function(e) {
                        if (e.target.classList.contains('remove-tool')) {
                            e.target.parentElement.remove();
                        }
                    });
                </script>
                <!-- Machine Equipments Table -->
                <div class="container-fluid mt-4">
                    <h4 class="mb-3 text-primary">Machine Equipments List</h4>
                    <table class="table table-bordered table-hover">
                        <thead>
                            <tr>
                                <th>Machine ID</th>
                                <th>Machine Type</th>
                                <th>Machine Name</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($machine_equipments as $equip): ?>
                                <tr>
                                    <td><?= htmlspecialchars($equip['machine_id']) ?></td>
                                    <td><?= htmlspecialchars($equip['machine_type']) ?></td>
                                    <td><?= htmlspecialchars($equip['machine_name']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
</div>

        
   <!-- My Duties Table -->
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2  style="font-family:Arial, sans-serif; color:#0d6efd; margin-bottom:20px; border-bottom:2px solid #0d6efd; padding-bottom:8px;">📋My Duties</h2>
          
        </div>
        <table class="table table-bordered table-hover">
            <thead class="table-info">
                <tr>
                    <th>Duty ID</th>
                    <th>Appointment ID</th>
                    <th>Doctor ID</th>
                    <th>Bed ID</th>
                    <th>Nurse Assistant</th>
                    <th>Procedure</th>
                    <th>Equipment</th>
                    <th>Tools</th>
                    <th>Notes</th>
                    <th>Status</th>
                    <th>Created At</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($duties)): ?>
                    <?php foreach ($duties as $duty): ?>
                        <tr>
                            <td><?= htmlspecialchars($duty['duty_id']) ?></td>
                            <td><?= htmlspecialchars($duty['appointment_id']) ?></td>
                            <td><?= htmlspecialchars($duty['doctor_id']) ?></td>
                            <td><?= htmlspecialchars($duty['bed_id']) ?></td>
                            <td><?= htmlspecialchars($duty['nurse_assistant']) ?></td>
                            <td><?= htmlspecialchars($duty['procedure']) ?></td>
                            <td><?= htmlspecialchars($duty['equipment']) ?></td>
                            <td><?= htmlspecialchars($duty['tools']) ?></td>
                            <td><?= htmlspecialchars($duty['notes']) ?></td>
                            <td><?= htmlspecialchars($duty['status']) ?></td>
                            <td><?= htmlspecialchars($duty['created_at']) ?></td>
                            <td>
                                <a href="doctor_duty.php?complete_duty_id=<?= $duty['duty_id'] ?>" class="btn btn-success btn-sm" onclick="return confirm('Mark this duty as completed?')">Mark as Completed</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="12">No duties found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
                        



    
            <div class="container-fluid">
                <div class="title-container">
                    <i class="fa-solid fa-capsules"></i>
                    <h1 class="page-title">Add Prescription</h1>
                </div>

                <div class="content mt-4">
                    <!-- Header Section -->
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h2></h2>
                        <div>
                            <!-- Button trigger modal -->
                            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#prescriptionModal">
                                Add Prescription
                            </button>
                        </div>
                    </div>



                    <!-- Modal -->
                    <div class="modal fade" id="prescriptionModal" tabindex="-1" aria-labelledby="prescriptionModalLabel" aria-hidden="true">
                        <div class="modal-dialog modal-lg">
                            <div class="modal-content">

                                <div class="modal-header">
                                    <h5 class="modal-title" id="prescriptionModalLabel">Add Prescription</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>

                                <form action="process_prescription.php" method="POST">
                                    <div class="modal-body">

                                        <!-- Doctor -->
                                        <div class="mb-3">
                                            <label for="doctor_id" class="form-label">Doctor</label>
                                            <select class="form-select" id="doctor_id" name="doctor_id" required>
                                                <option value="">-- Select Doctor --</option>
                                                <?php foreach ($doctors as $doc): ?>
                                                    <option value="<?= $doc['employee_id'] ?>">
                                                        <?= $doc['first_name'] . ' ' . $doc['last_name'] ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>

                                        <!-- Patient -->
                                        <div class="mb-3">
                                            <label for="patient_id" class="form-label">Patient</label>
                                            <select class="form-select" id="patient_id" name="patient_id" required>
                                                <option value="">-- Select Patient --</option>
                                                <?php foreach ($patients as $pat): ?>
                                                    <option value="<?= $pat['patient_id'] ?>">
                                                        <?= htmlspecialchars($pat['fname'] . ' ' . $pat['lname']) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>

                                        <!-- Medicine Rows -->
                                        <div id="medicineRows">
                                            <div class="medicine-row row mb-2">
                                                <div class="col-md-4">
                                                    <label class="form-label">Medicine</label>
                                                    <select class="form-select medicine-select" name="med_id[]" required>
                                                        <option value="">-- Select Medicine --</option>
                                                        <?php foreach ($medicines as $med): ?>
                                                            <option value="<?= htmlspecialchars($med['med_id']) ?>"
                                                                data-dosage="<?= htmlspecialchars($med['dosage']) ?>"
                                                                data-stock="<?= htmlspecialchars($med['stock_quantity'] ?? 0) ?>">
                                                                <?= htmlspecialchars($med['med_name'] . ' (' . $med['dosage'] . ')') ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                    <input type="hidden" class="dosage-input" name="dosage[]">
                                                </div>
                                                <div class="col-md-2">
                                                    <label class="form-label">Stock</label>
                                                    <input type="text" class="form-control stock-display" value="" readonly>
                                                </div>
                                                <div class="col-md-2">
                                                    <label class="form-label">Quantity</label>
                                                    <input type="number" class="form-control" name="quantity[]" required>
                                                </div>
                                                <div class="col-md-3">
                                                    <label class="form-label">Note</label>
                                                    <input type="text" class="form-control" name="note[]" placeholder="e.g. 3x a day">
                                                </div>
                                                <div class="col-md-1 d-flex align-items-end">
                                                    <button type="button" class="btn btn-danger remove-medicine">X</button>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Add Medicine Button -->
                                        <button type="button" id="addMedicine" class="btn btn-success mb-3">+ Add Medicine</button>

                                        <!-- Status (Auto Pending, hidden from doctor) -->
                                        <input type="hidden" name="status" value="Pending">

                                    </div>

                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                        <button type="submit" class="btn btn-primary">Save Prescription</button>
                                    </div>
                                </form>

                            </div>
                        </div>
                    </div>


                    <script>
                        // Update medicine info and validate quantity
                        function updateMedicineInfo(selectElem) {
                            let selected = selectElem.options[selectElem.selectedIndex];
                            let dosage = selected.getAttribute('data-dosage') || '';
                            let stock = selected.getAttribute('data-stock') || '0';

                            let row = selectElem.closest('.medicine-row');
                            row.querySelector('.dosage-input').value = dosage;
                            row.querySelector('.stock-display').value = stock;

                            // Remove old input listener to prevent stacking
                            let qtyInput = row.querySelector('input[name="quantity[]"]');
                            let newQtyInput = qtyInput.cloneNode(true);
                            qtyInput.parentNode.replaceChild(newQtyInput, qtyInput);

                            newQtyInput.addEventListener('input', function() {
                                let currentStock = parseInt(row.querySelector('.stock-display').value) || 0;
                                let enteredQty = parseInt(this.value);

                                if (!isNaN(enteredQty) && enteredQty > currentStock) {
                                    alert("Entered quantity exceeds available stock (" + currentStock + ")");
                                    this.value = currentStock;
                                }
                            });

                            updateMedicineOptions(); // refresh options to disable already selected medicines
                        }

                        // Disable already selected medicines in all rows
                        function updateMedicineOptions() {
                            let selectedValues = Array.from(document.querySelectorAll('.medicine-select'))
                                .map(sel => sel.value)
                                .filter(val => val !== '');

                            document.querySelectorAll('.medicine-select').forEach(sel => {
                                Array.from(sel.options).forEach(option => {
                                    if (option.value !== '' && option.value !== sel.value) {
                                        option.disabled = selectedValues.includes(option.value);
                                    }
                                });
                            });
                        }

                        // Bind existing medicine selects
                        document.querySelectorAll('.medicine-select').forEach(sel => {
                            sel.addEventListener('change', function() {
                                updateMedicineInfo(this);
                            });
                        });

                        // Add new medicine row
                        document.getElementById('addMedicine').addEventListener('click', function() {
                            let newRow = document.querySelector('.medicine-row').cloneNode(true);

                            // Clear inputs and selects
                            newRow.querySelectorAll('input').forEach(input => input.value = '');
                            newRow.querySelectorAll('select').forEach(select => select.selectedIndex = 0);

                            document.getElementById('medicineRows').appendChild(newRow);

                            // Re-bind events for the new row
                            let newSelect = newRow.querySelector('.medicine-select');
                            newSelect.addEventListener('change', function() {
                                updateMedicineInfo(this);
                            });

                            newRow.querySelector('.remove-medicine').addEventListener('click', function() {
                                newRow.remove();
                                updateMedicineOptions(); // re-enable removed medicine
                            });

                            updateMedicineOptions(); // refresh options for all rows
                        });

                        // Remove medicine row buttons
                        document.querySelectorAll('.remove-medicine').forEach(btn => {
                            btn.addEventListener('click', function() {
                                this.closest('.medicine-row').remove();
                                updateMedicineOptions();
                            });
                        });

                        // Prevent form submission if any quantity is 0 or empty
                        document.querySelector('form').addEventListener('submit', function(e) {
                            let invalid = false;
                            document.querySelectorAll('input[name="quantity[]"]').forEach(qtyInput => {
                                let val = parseInt(qtyInput.value);
                                if (isNaN(val) || val <= 0) {
                                    invalid = true;
                                    qtyInput.classList.add('is-invalid'); // optional: highlight invalid fields
                                } else {
                                    qtyInput.classList.remove('is-invalid');
                                }
                            });

                            if (invalid) {
                                e.preventDefault();
                                alert("Please enter a valid quantity greater than 0 for all medicines.");
                            }
                        });
                    </script>

            <!-- END CODING HERE -->

         
        <!----- End of Main Content ----->

    <script>
        const toggler = document.querySelector(".toggler-btn");
        toggler && toggler.addEventListener("click", function() {
            document.querySelector("#sidebar").classList.toggle("collapsed");
        });
    </script>
    <script src="../assets/Bootstrap/all.min.js"></script>
    <script src="../assets/Bootstrap/bootstrap.bundle.min.js"></script>
    <script src="../assets/Bootstrap/fontawesome.min.js"></script>
    <script src="../assets/Bootstrap/jq.js"></script>
</body>
</html>







