<?php
session_start(); // Always start session

include '../../../../SQL/config.php';
require '../../../Pharmacy Management/classes/Prescription.php';
require '../../../Pharmacy Management/classes/Medicine.php';

// ✅ Authentication check
if (!isset($_SESSION['profession']) || $_SESSION['profession'] !== 'Doctor') {
    header('Location: ../../login.php'); // adjust path if needed
    exit();
}

if (!isset($_SESSION['employee_id'])) {
    echo "User ID is not set in session.";
    exit();
}

// ✅ Fetch logged-in doctor details
$query = "SELECT * FROM hr_employees WHERE employee_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $_SESSION['employee_id']);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if (!$user) {
    echo "No user found.";
    exit();
}

// -----------------------------------
// Fetch appointments
// -----------------------------------

// Already assigned appointments
$assigned_appointments = [];
$res = $conn->query("SELECT appointment_id FROM duty_assignments");
if ($res && $res->num_rows > 0) {
    while ($row = $res->fetch_assoc()) {
        $assigned_appointments[] = $row['appointment_id'];
    }
}

// All unassigned + not managed appointments
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

// Selected appointment doctor_id
$appointment_id = $_GET['appointment_id'] ?? '';
$selected_doctor_id = '';
if ($appointment_id) {
    $stmt = $conn->prepare("SELECT doctor_id FROM p_appointments WHERE appointment_id = ?");
    $stmt->bind_param("i", $appointment_id);
    $stmt->execute();
    $stmt->bind_result($selected_doctor_id);
    $stmt->fetch();
    $stmt->close();
}

// -----------------------------------
// Fetch beds and nurses
// -----------------------------------
$beds = [];
$bed_res = $conn->query("SELECT bed_id, bed_number, ward, room_number, bed_type, status, notes FROM p_beds");
if ($bed_res && $bed_res->num_rows > 0) {
    while ($row = $bed_res->fetch_assoc()) {
        $beds[] = $row;
    }
}

$nurses = [];
$nurse_res = $conn->query("SELECT employee_id, first_name, last_name FROM hr_employees WHERE profession = 'Nurse' AND status = 'active'");
if ($nurse_res && $nurse_res->num_rows > 0) {
    while ($row = $nurse_res->fetch_assoc()) {
        $nurses[] = $row;
    }
}

// -----------------------------------
// Equipment + Tools
// -----------------------------------
$equipment_types = [
    'PPE' => ['Surgical gloves', 'Examination gloves', 'Face masks', 'Surgical caps', 'Shoe covers', 'Gowns', 'Face shields'],
    'Patient Care Supplies' => ['Disposable bed sheets', 'Adult diapers', 'Underpads', 'Disposable towels', 'Paper gowns'],
    'Surgical & Procedure Consumables' => ['Syringes', 'Needles', 'IV cannulas', 'Infusion sets', 'Surgical drapes', 'Surgical blades', 'Sutures', 'Cotton balls', 'Gauze pads', 'Bandages', 'Suction catheters'],
    'Laboratory Consumables' => ['Test tubes', 'Pipette tips', 'Petri dishes', 'Blood collection tubes', 'Urine containers', 'Swabs', 'Disposable centrifuge tubes'],
    'General Hospital Consumables' => ['Alcohol pads', 'Hand sanitizers', 'Disinfectant wipes', 'Disposable thermometers', 'Sharps containers', 'Biohazard bags']
];

$tool_options = [];
foreach ($equipment_types as $type => $names) {
    foreach ($names as $name) {
        $tool_options[] = ['type' => $type, 'name' => $name];
    }
}

// Machine equipments
$machine_equipments = [];
$machine_types = [];
$machine_res = $conn->query("SELECT machine_id, machine_type, machine_name FROM machine_equipments");
if ($machine_res && $machine_res->num_rows > 0) {
    while ($row = $machine_res->fetch_assoc()) {
        $machine_equipments[] = $row;
        $machine_types[$row['machine_type']][] = $row;
    }
}

// -----------------------------------
// Handle duty assignment submission
// -----------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_duty'])) {
    $appointment_id = $_POST['appointment_id'];
    $doctor_id = $selected_doctor_id;
    $bed_id = !empty($_POST['bed_id']) ? $_POST['bed_id'] : null;
    $nurse_assistant = !empty($_POST['nurse_assistant']) ? $_POST['nurse_assistant'] : null;
    $procedure = $_POST['procedure'];
    $notes = $_POST['notes'];
    $status = "Pending";

    // Equipments
    $equipments = [];
    if (!empty($_POST['equipment_type']) && !empty($_POST['equipment_name'])) {
        foreach ($_POST['equipment_type'] as $idx => $type) {
            $name = $_POST['equipment_name'][$idx] ?? '';
            if ($type && $name) {
                $equipments[] = $type . ' - ' . $name;
            }
        }
    }
    $equipment = implode(', ', $equipments);

    // Tools
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

    // Save duty
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

    // Save purchase request if tools exist
    if (!empty($tools)) {
        $user_id = $doctor_id;
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

// -----------------------------------
// Mark duty completed
// -----------------------------------
if (isset($_GET['complete_duty_id'])) {
    $complete_duty_id = intval($_GET['complete_duty_id']);
    $stmt = $conn->prepare("UPDATE duty_assignments SET status = 'Completed', updated_at = NOW() WHERE duty_id = ?");
    $stmt->bind_param("i", $complete_duty_id);
    $stmt->execute();
    $stmt->close();
    header("Location: doctor_duty.php");
    exit;
}

// -----------------------------------
// Fetch doctor’s duties
// -----------------------------------
$duties = [];
$duty_res = $conn->query("SELECT duty_id, appointment_id, doctor_id, bed_id, nurse_assistant, `procedure`, equipment, tools, notes, status, created_at 
                          FROM duty_assignments 
                          WHERE status != 'Completed' 
                          ORDER BY created_at DESC");
if ($duty_res && $duty_res->num_rows > 0) {
    while ($row = $duty_res->fetch_assoc()) {
        $duties[] = $row;
    }
}

// -----------------------------------
// Medicines + Prescriptions
// -----------------------------------
$medicineObj = new Medicine($conn);
$medicines = $medicineObj->getAllMedicines();

$prescription = new Prescription($conn);
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
    <title>HMS | Doctor User Panel</title>
    <link rel="shortcut icon" href="../../assets/image/favicon.ico" type="image/x-icon">
    <link rel="stylesheet" href="../../assets/CSS/bootstrap.min.css">
    <link rel="stylesheet" href="../../assets/CSS/super.css">
    <link rel="stylesheet" href="../../assets/CSS/user_duty.css">
    <link rel="stylesheet" href="../../../Pharmacy Management/assets/css/med_inventory.css">

    <script>
        // Only validate prescription form, not the appointment assignment form
        document.addEventListener("DOMContentLoaded", function() {
            var prescriptionForm = document.querySelector('#prescriptionModal form');
            if (prescriptionForm) {
                prescriptionForm.addEventListener('submit', function(e) {
                    let invalid = false;
                    prescriptionForm.querySelectorAll('input[name="quantity[]"]').forEach(qtyInput => {
                        let val = parseInt(qtyInput.value);
                        if (isNaN(val) || val <= 0) {
                            invalid = true;
                            qtyInput.classList.add('is-invalid');
                        } else {
                            qtyInput.classList.remove('is-invalid');
                        }
                    });
                    if (invalid) {
                        e.preventDefault();
                        alert("Please enter a valid quantity greater than 0 for all medicines.");
                    }
                });
            }
        });
    </script>
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
                <a href="my_doctor_schedule.php" class="sidebar-link" data-bs-toggle="#" data-bs-target="#"
                    aria-expanded="false" aria-controls="auth">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 640 640"><!--!Font Awesome Free v7.0.0 by @fontawesome - https://fontawesome.com License - https://fontawesome.com/license/free Copyright 2025 Fonticons, Inc.-->
                        <path d="M224 64C241.7 64 256 78.3 256 96L256 128L384 128L384 96C384 78.3 398.3 64 416 64C433.7 64 448 78.3 448 96L448 128L480 128C515.3 128 544 156.7 544 192L544 480C544 515.3 515.3 544 480 544L160 544C124.7 544 96 515.3 96 480L96 192C96 156.7 124.7 128 160 128L192 128L192 96C192 78.3 206.3 64 224 64zM160 304L160 336C160 344.8 167.2 352 176 352L208 352C216.8 352 224 344.8 224 336L224 304C224 295.2 216.8 288 208 288L176 288C167.2 288 160 295.2 160 304zM288 304L288 336C288 344.8 295.2 352 304 352L336 352C344.8 352 352 344.8 352 336L352 304C352 295.2 344.8 288 336 288L304 288C295.2 288 288 295.2 288 304zM432 288C423.2 288 416 295.2 416 304L416 336C416 344.8 423.2 352 432 352L464 352C472.8 352 480 344.8 480 336L480 304C480 295.2 472.8 288 464 288L432 288zM160 432L160 464C160 472.8 167.2 480 176 480L208 480C216.8 480 224 472.8 224 464L224 432C224 423.2 216.8 416 208 416L176 416C167.2 416 160 423.2 160 432zM304 416C295.2 416 288 423.2 288 432L288 464C288 472.8 295.2 480 304 480L336 480C344.8 480 352 472.8 352 464L352 432C352 423.2 344.8 416 336 416L304 416zM416 432L416 464C416 472.8 423.2 480 432 480L464 480C472.8 480 480 472.8 480 464L480 432C480 423.2 472.8 416 464 416L432 416C423.2 416 416 423.2 416 432z" />
                    </svg>
                    <span style="font-size: 18px;">My Schedule</span>
                </a>
            </li>
            <li class="sidebar-item">
                <a href="doctor_duty.php" class="sidebar-link" data-bs-toggle="#" data-bs-target="#"
                    aria-expanded="false" aria-controls="auth">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 640 640"><!--!Font Awesome Free v7.0.0 by @fontawesome - https://fontawesome.com License - https://fontawesome.com/license/free Copyright 2025 Fonticons, Inc.-->
                        <path d="M160 96C160 78.3 174.3 64 192 64L448 64C465.7 64 480 78.3 480 96C480 113.7 465.7 128 448 128L418.5 128L428.8 262.1C465.9 283.3 494.6 318.5 507 361.8L510.8 375.2C513.6 384.9 511.6 395.2 505.6 403.3C499.6 411.4 490 416 480 416L160 416C150 416 140.5 411.3 134.5 403.3C128.5 395.3 126.5 384.9 129.3 375.2L133 361.8C145.4 318.5 174 283.3 211.2 262.1L221.5 128L192 128C174.3 128 160 113.7 160 96zM288 464L352 464L352 576C352 593.7 337.7 608 320 608C302.3 608 288 593.7 288 576L288 464z" />
                    </svg>
                    <span style="font-size: 18px;">Doctor Duty</span>
                </a>
            </li>
            <li class="sidebar-item">
                <a href="superadmin_dashboard.php" class="sidebar-link" data-bs-toggle="#" data-bs-target="#"
                    aria-expanded="false" aria-controls="auth">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 448 512"><!--!Font Awesome Free v7.0.0 by @fontawesome - https://fontawesome.com License - https://fontawesome.com/license/free Copyright 2025 Fonticons, Inc.-->
                        <path d="M224 8a120 120 0 1 0 0 240 120 120 0 1 0 0-240zm60 312.8c-5.4-.5-11-.8-16.6-.8l-86.9 0c-5.6 0-11.1 .3-16.6 .8l0 67.5c16.5 7.6 28 24.3 28 43.6 0 26.5-21.5 48-48 48s-48-21.5-48-48c0-19.4 11.5-36.1 28-43.6l0-58.4C61 353 16 413.6 16 484.6 16 499.7 28.3 512 43.4 512l361.1 0c15.1 0 27.4-12.3 27.4-27.4 0-71-45-131.5-108-154.6l0 37.4c23.3 8.2 40 30.5 40 56.6l0 32c0 11-9 20-20 20s-20-9-20-20l0-32c0-11-9-20-20-20s-20 9-20 20l0 32c0 11-9 20-20 20s-20-9-20-20l0-32c0-26.1 16.7-48.3 40-56.6l0-46.6z" />
                    </svg>
                    <span style="font-size: 18px;">View Clinical Profile</span>
                </a>
            </li>
            <li class="sidebar-item">
                <a href="superadmin_dashboard.php" class="sidebar-link" data-bs-toggle="#" data-bs-target="#"
                    aria-expanded="false" aria-controls="auth">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 640 640"><!--!Font Awesome Free v7.0.0 by @fontawesome - https://fontawesome.com License - https://fontawesome.com/license/free Copyright 2025 Fonticons, Inc.-->
                        <path d="M32 160C32 124.7 60.7 96 96 96L544 96C579.3 96 608 124.7 608 160L32 160zM32 208L608 208L608 480C608 515.3 579.3 544 544 544L96 544C60.7 544 32 515.3 32 480L32 208zM279.3 480C299.5 480 314.6 460.6 301.7 445C287 427.3 264.8 416 240 416L176 416C151.2 416 129 427.3 114.3 445C101.4 460.6 116.5 480 136.7 480L279.2 480zM208 376C238.9 376 264 350.9 264 320C264 289.1 238.9 264 208 264C177.1 264 152 289.1 152 320C152 350.9 177.1 376 208 376zM392 272C378.7 272 368 282.7 368 296C368 309.3 378.7 320 392 320L504 320C517.3 320 528 309.3 528 296C528 282.7 517.3 272 504 272L392 272zM392 368C378.7 368 368 378.7 368 392C368 405.3 378.7 416 392 416L504 416C517.3 416 528 405.3 528 392C528 378.7 517.3 368 504 368L392 368z" />
                    </svg>
                    <span style="font-size: 18px;">License & Compliance Viewer</span>
                </a>
            </li>
            <li class="sidebar-item">
                <a href="superadmin_dashboard.php" class="sidebar-link" data-bs-toggle="#" data-bs-target="#"
                    aria-expanded="false" aria-controls="auth">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 640 640"><!--!Font Awesome Free v7.0.0 by @fontawesome - https://fontawesome.com License - https://fontawesome.com/license/free Copyright 2025 Fonticons, Inc.-->
                        <path d="M128 128C128 92.7 156.7 64 192 64L341.5 64C358.5 64 374.8 70.7 386.8 82.7L493.3 189.3C505.3 201.3 512 217.6 512 234.6L512 512C512 547.3 483.3 576 448 576L192 576C156.7 576 128 547.3 128 512L128 128zM336 122.5L336 216C336 229.3 346.7 240 360 240L453.5 240L336 122.5zM337 327C327.6 317.6 312.4 317.6 303.1 327L239.1 391C229.7 400.4 229.7 415.6 239.1 424.9C248.5 434.2 263.7 434.3 273 424.9L296 401.9L296 488C296 501.3 306.7 512 320 512C333.3 512 344 501.3 344 488L344 401.9L367 424.9C376.4 434.3 391.6 434.3 400.9 424.9C410.2 415.5 410.3 400.3 400.9 391L336.9 327z" />
                    </svg>
                    <span style="font-size: 18px;">Upload Renewal Documents</span>
                </a>
            </li>
            <li class="sidebar-item">
                <a href="superadmin_dashboard.php" class="sidebar-link" data-bs-toggle="#" data-bs-target="#"
                    aria-expanded="false" aria-controls="auth">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 640 640"><!--!Font Awesome Free v7.0.0 by @fontawesome - https://fontawesome.com License - https://fontawesome.com/license/free Copyright 2025 Fonticons, Inc.-->
                        <path d="M320 64C302.3 64 288 78.3 288 96L288 99.2C215 114 160 178.6 160 256L160 277.7C160 325.8 143.6 372.5 113.6 410.1L103.8 422.3C98.7 428.6 96 436.4 96 444.5C96 464.1 111.9 480 131.5 480L508.4 480C528 480 543.9 464.1 543.9 444.5C543.9 436.4 541.2 428.6 536.1 422.3L526.3 410.1C496.4 372.5 480 325.8 480 277.7L480 256C480 178.6 425 114 352 99.2L352 96C352 78.3 337.7 64 320 64zM258 528C265.1 555.6 290.2 576 320 576C349.8 576 374.9 555.6 382 528L258 528z" />
                    </svg>
                    <span style="font-size: 18px;">Notification Alerts</span>
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
            <!-- TAB -->
            <div class="container-fluid mt-3">
                <!-- Tabs Navigation -->
                <div class="title-container">
                    <!-- Tabs -->
                    <ul class="nav nav-tabs custom-tabs" id="doctorTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active d-flex align-items-center gap-2"
                                id="appointments-tab"
                                data-bs-toggle="tab"
                                data-bs-target="#appointments"
                                type="button" role="tab">
                                <i class="fa-solid fa-calendar-check"></i>
                                <span>Appointment</span>
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link d-flex align-items-center gap-2"
                                id="duties-tab"
                                data-bs-toggle="tab"
                                data-bs-target="#duties"
                                type="button" role="tab">
                                <i class="fa-solid fa-user-nurse"></i>
                                <span>My Duties</span>
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link d-flex align-items-center gap-2"
                                id="prescriptions-tab"
                                data-bs-toggle="tab"
                                data-bs-target="#prescriptions"
                                type="button" role="tab">
                                <i class="fa-solid fa-capsules"></i>
                                <span>Prescriptions</span>
                            </button>
                        </li>
                    </ul>
                </div>

                <!-- Tabs Content -->
                <div class="tab-content mt-3" id="doctorTabsContent">

                    <!-- Appointments Tab -->
                    <div class="tab-pane fade show active" id="appointments" role="tabpanel">
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
                                    <tr>
                                        <td colspan="8">No appointments found.</td>
                                    </tr>
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
                                                <?php foreach ($beds as $row): ?>
                                                    <?php if (strtolower($row['status']) == 'available'): ?>
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
                                                <?php foreach ($nurses as $row): ?>
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
                                                        <?php foreach (array_keys($machine_types) as $type): ?>
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
                                                        <?php foreach ($tool_options as $tool): ?>
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
                                <?php foreach (array_keys($machine_types) as $type): ?>
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
                                <?php foreach ($tool_options as $tool): ?>
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
                        <?php endif; ?>
                    </div>


                    <!-- Duties Tab -->
                    <div class="tab-pane fade" id="duties" role="tabpanel">
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
                                    <?php foreach ($duties as $duty):
                                        $equipModalId = "equipModal" . $duty['duty_id'];
                                        $toolsModalId = "toolsModal" . $duty['duty_id'];
                                        $notesModalId = "notesModal" . $duty['duty_id'];
                                    ?>
                                        <tr>
                                            <td><?= htmlspecialchars($duty['duty_id']) ?></td>
                                            <td><?= htmlspecialchars($duty['appointment_id']) ?></td>
                                            <td><?= htmlspecialchars($duty['doctor_id']) ?></td>
                                            <td><?= htmlspecialchars($duty['bed_id']) ?></td>
                                            <td><?= htmlspecialchars($duty['nurse_assistant']) ?></td>
                                            <td><?= htmlspecialchars($duty['procedure']) ?></td>
                                            <td>
                                                <button class="btn btn-info btn-sm" data-bs-toggle="modal" data-bs-target="#<?= $equipModalId ?>">See</button>
                                                <!-- Equipment Modal -->
                                                <div class="modal fade" id="<?= $equipModalId ?>" tabindex="-1">
                                                    <div class="modal-dialog">
                                                        <div class="modal-content">
                                                            <div class="modal-header">
                                                                <h5 class="modal-title">Equipment</h5>
                                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                            </div>
                                                            <div class="modal-body">
                                                                <pre><?= htmlspecialchars($duty['equipment']) ?></pre>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <button class="btn btn-info btn-sm" data-bs-toggle="modal" data-bs-target="#<?= $toolsModalId ?>">See</button>
                                                <!-- Tools Modal -->
                                                <div class="modal fade" id="<?= $toolsModalId ?>" tabindex="-1">
                                                    <div class="modal-dialog">
                                                        <div class="modal-content">
                                                            <div class="modal-header">
                                                                <h5 class="modal-title">Tools</h5>
                                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                            </div>
                                                            <div class="modal-body">
                                                                <pre><?php
                                                                        // Try to pretty print JSON if possible
                                                                        $tools = $duty['tools'];
                                                                        if ($tools && ($decoded = json_decode($tools, true))) {
                                                                            foreach ($decoded as $tool) {
                                                                                echo htmlspecialchars($tool['name']) . " (Qty: " . htmlspecialchars($tool['qty']) . ")\n";
                                                                            }
                                                                        } else {
                                                                            echo htmlspecialchars($tools);
                                                                        }
                                                                        ?></pre>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <button class="btn btn-info btn-sm" data-bs-toggle="modal" data-bs-target="#<?= $notesModalId ?>">See</button>
                                                <!-- Notes Modal -->
                                                <div class="modal fade" id="<?= $notesModalId ?>" tabindex="-1">
                                                    <div class="modal-dialog">
                                                        <div class="modal-content">
                                                            <div class="modal-header">
                                                                <h5 class="modal-title">Notes</h5>
                                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                            </div>
                                                            <div class="modal-body">
                                                                <pre><?= htmlspecialchars($duty['notes']) ?></pre>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td><?= htmlspecialchars($duty['status']) ?></td>
                                            <td><?= htmlspecialchars($duty['created_at']) ?></td>
                                            <td>
                                                <a href="doctor_duty.php?complete_duty_id=<?= $duty['duty_id'] ?>" class="btn btn-success btn-sm" onclick="return confirm('Mark this duty as completed?')">Mark as Completed</a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="12">No duties found.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>


                    <!-- Prescriptions Tab -->
                    <div class="tab-pane fade" id="prescriptions" role="tabpanel">
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
                            <!-- Prescription Modal -->
                            <div class="modal fade" id="prescriptionModal" tabindex="-1" aria-labelledby="prescriptionModalLabel" aria-hidden="true">
                                <div class="modal-dialog modal-lg">
                                    <div class="modal-content">

                                        <div class="modal-header">
                                            <h5 class="modal-title" id="prescriptionModalLabel">Add Prescription</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>

                                        <form action="process_prescription.php" method="POST">
                                            <div class="modal-body">

                                                <!-- Doctor (auto-filled from logged-in account) -->
                                                <div class="mb-3">
                                                    <label class="form-label">Doctor</label>
                                                    <input type="text" class="form-control" value="<?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?>" readonly>
                                                    <input type="hidden" name="doctor_id" value="<?= $_SESSION['employee_id'] ?>">
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


                            <!-- Medicine Inventory Table -->
                            <table class="table">
                                <thead class="table">
                                    <tr>
                                        <th>Prescription ID</th>
                                        <th>Doctor</th>
                                        <th>Patient</th>
                                        <th>Medicines</th>
                                        <th>Total Quantity</th>
                                        <th>Note</th>
                                        <th>Date Prescribed</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $doctor_id = $_SESSION['employee_id']; // logged-in doctor

                                    $sql = "SELECT 
            p.prescription_id,
            CONCAT(e.first_name, ' ', e.last_name) AS doctor_name,
            CONCAT(pi.fname, ' ', pi.lname) AS patient_name,
            GROUP_CONCAT(
                CONCAT(m.med_name, ' (', i.dosage, ') - Qty: ', i.quantity_prescribed)
                SEPARATOR '<br>'
            ) AS medicines_list,
            SUM(i.quantity_prescribed) AS total_quantity,
            p.note,
            DATE_FORMAT(p.prescription_date, '%b %e, %Y %l:%i%p') AS formatted_date,
            p.status
        FROM pharmacy_prescription p
        JOIN patientinfo pi 
            ON p.patient_id = pi.patient_id
        JOIN hr_employees e 
            ON p.doctor_id = e.employee_id 
            AND LOWER(e.profession) = 'doctor'
        JOIN pharmacy_prescription_items i 
            ON p.prescription_id = i.prescription_id
        JOIN pharmacy_inventory m 
            ON i.med_id = m.med_id
        WHERE p.doctor_id = ?  -- 👈 Only prescriptions by this doctor
        GROUP BY p.prescription_id
        ORDER BY p.prescription_date DESC";

                                    $stmt = $conn->prepare($sql);
                                    $stmt->bind_param("i", $doctor_id);
                                    $stmt->execute();
                                    $result = $stmt->get_result();

                                    if ($result === false) {
                                        echo "<tr><td colspan='8' class='text-danger'>SQL Error: " . $conn->error . "</td></tr>";
                                    } elseif ($result->num_rows > 0) {
                                        while ($row = $result->fetch_assoc()) {
                                            $noteId = "noteModal" . $row['prescription_id'];

                                            // Determine badge class based on status
                                            $status = strtolower($row['status']);
                                            if ($status === 'dispensed') {
                                                $badgeClass = 'bg-success';
                                            } elseif ($status === 'pending') {
                                                $badgeClass = 'bg-secondary';
                                            } elseif ($status === 'cancelled' || $status === 'out of stock') {
                                                $badgeClass = 'bg-danger';
                                            } else {
                                                $badgeClass = 'bg-info';
                                            }

                                            echo "<tr>
                <td>{$row['prescription_id']}</td>
                <td>{$row['doctor_name']}</td>
                <td>{$row['patient_name']}</td>
                <td>{$row['medicines_list']}</td>
                <td>{$row['total_quantity']}</td>
                <td><button class='btn btn-info btn-sm' data-bs-toggle='modal' data-bs-target='#{$noteId}'>View Note</button></td>
                <td>{$row['formatted_date']}</td>
                <td><span class='badge {$badgeClass} text-uppercase fw-bold'>" . htmlspecialchars($row['status']) . "</span></td>
            </tr>";

                                            // Modal for each note
                                            echo "
        <div class='modal fade' id='{$noteId}' tabindex='-1'>
            <div class='modal-dialog'>
                <div class='modal-content'>
                    <div class='modal-header'>
                        <h5 class='modal-title'>Prescription Note</h5>
                        <button type='button' class='btn-close' data-bs-dismiss='modal'></button>
                    </div>
                    <div class='modal-body'>
                        <p>" . nl2br(htmlspecialchars($row['note'])) . "</p>
                    </div>
                    <div class='modal-footer'>
                        <button type='button' class='btn btn-secondary' data-bs-dismiss='modal'>Close</button>
                    </div>
                </div>
            </div>
        </div>";
                                        }
                                    } else {
                                        echo "<tr><td colspan='8' class='text-center'>No prescriptions found</td></tr>";
                                    }
                                    ?>

                                </tbody>
                            </table>
                        </div>

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
            </script>


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
    <script src="../../assets/Bootstrap/all.min.js"></script>
    <script src="../../assets/Bootstrap/bootstrap.bundle.min.js"></script>
    <script src="../../assets/Bootstrap/fontawesome.min.js"></script>
    <script src="../../assets/Bootstrap/jq.js"></script>
</body>

</html>