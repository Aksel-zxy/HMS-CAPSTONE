<?php
include '../../SQL/config.php';

class DoctorDashboard
{
    public $conn;
    public $user;

    public function __construct($conn)
    {
        $this->conn = $conn;
        $this->authenticate();
        $this->fetchUser();
    }

    private function authenticate()
    {
        if (!isset($_SESSION['doctor']) || $_SESSION['doctor'] !== true) {
            header('Location: login.php');
            exit();
        }
        if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
            echo "User ID is not set in session.";
            exit();
        }
    }

    private function fetchUser()
    {
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

$query = "
    SELECT profession, COUNT(*) AS total
    FROM hr_employees
    WHERE profession IN ('Doctor', 'Nurse')
    GROUP BY profession
";
$result = $conn->query($query);

$doctorCount = 0;
$nurseCount  = 0;

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        if (strtolower($row['profession']) === 'doctor') {
            $doctorCount = $row['total'];
        } elseif (strtolower($row['profession']) === 'nurse') {
            $nurseCount = $row['total'];
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
                <a href="#" class="sidebar-link collapsed has-dropdown" data-bs-toggle="collapse" data-bs-target="#schedule"
                    aria-expanded="true" aria-controls="auth">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-cast" viewBox="0 0 640 512"><!--!Font Awesome Free v7.0.0 by @fontawesome - https://fontawesome.com License - https://fontawesome.com/license/free Copyright 2025 Fonticons, Inc.-->
                        <path d="M320 16a104 104 0 1 1 0 208 104 104 0 1 1 0-208zM96 88a72 72 0 1 1 0 144 72 72 0 1 1 0-144zM0 416c0-70.7 57.3-128 128-128 12.8 0 25.2 1.9 36.9 5.4-32.9 36.8-52.9 85.4-52.9 138.6l0 16c0 11.4 2.4 22.2 6.7 32L32 480c-17.7 0-32-14.3-32-32l0-32zm521.3 64c4.3-9.8 6.7-20.6 6.7-32l0-16c0-53.2-20-101.8-52.9-138.6 11.7-3.5 24.1-5.4 36.9-5.4 70.7 0 128 57.3 128 128l0 32c0 17.7-14.3 32-32 32l-86.7 0zM472 160a72 72 0 1 1 144 0 72 72 0 1 1 -144 0zM160 432c0-88.4 71.6-160 160-160s160 71.6 160 160l0 16c0 17.7-14.3 32-32 32l-256 0c-17.7 0-32-14.3-32-32l0-16z" />
                    </svg>
                    <span style="font-size: 18px;">Scheduling Shifts and Duties</span>
                </a>

                <ul id="schedule" class="sidebar-dropdown list-unstyled collapse" data-bs-parent="#sidebar">
                    <li class="sidebar-item">
                        <a href="scheduling_shifts_and_duties/doctor_shift_scheduling.php" class="sidebar-link">Doctor Shift Scheduling</a>
                    </li>
                    <li class="sidebar-item">
                        <a href="scheduling_shifts_and_duties/nurse_shift_scheduling.php" class="sidebar-link">Nurse Shift Scheduling</a>
                    </li>
                    <li class="sidebar-item">
                        <a href="scheduling_shifts_and_duties/duty_assignment.php" class="sidebar-link">Duty Assignment</a>
                    </li>
                    <li class="sidebar-item">
                        <a href="scheduling_shifts_and_duties/schedule_calendar.php" class="sidebar-link">Schedule Calendar</a>
                    </li>
                </ul>
            </li>

            <li class="sidebar-item">
                <a href="#" class="sidebar-link collapsed has-dropdown" data-bs-toggle="collapse" data-bs-target="#license"
                    aria-expanded="true" aria-controls="auth">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-cast" viewBox="0 0 640 640"><!--!Font Awesome Free v7.0.0 by @fontawesome - https://fontawesome.com License - https://fontawesome.com/license/free Copyright 2025 Fonticons, Inc.-->
                        <path d="M80 480L80 224L560 224L560 480C560 488.8 552.8 496 544 496L352 496C352 451.8 316.2 416 272 416L208 416C163.8 416 128 451.8 128 496L96 496C87.2 496 80 488.8 80 480zM96 96C60.7 96 32 124.7 32 160L32 480C32 515.3 60.7 544 96 544L544 544C579.3 544 608 515.3 608 480L608 160C608 124.7 579.3 96 544 96L96 96zM240 376C270.9 376 296 350.9 296 320C296 289.1 270.9 264 240 264C209.1 264 184 289.1 184 320C184 350.9 209.1 376 240 376zM408 272C394.7 272 384 282.7 384 296C384 309.3 394.7 320 408 320L488 320C501.3 320 512 309.3 512 296C512 282.7 501.3 272 488 272L408 272zM408 368C394.7 368 384 378.7 384 392C384 405.3 394.7 416 408 416L488 416C501.3 416 512 405.3 512 392C512 378.7 501.3 368 488 368L408 368z" />
                    </svg>
                    <span style="font-size: 18px;">Doctor & Nurse Registration & Compliance Licensing</span>
                </a>

                <ul id="license" class="sidebar-dropdown list-unstyled collapse" data-bs-parent="#sidebar">
                    <li class="sidebar-item">
                        <a href="dnrcl/registration_clinical_profile.php" class="sidebar-link">Registration & Clinical Profile Management</a>
                    </li>
                    <li class="sidebar-item">
                        <a href="dnrcl/license_management.php" class="sidebar-link">License Management</a>
                    </li>
                    <li class="sidebar-item">
                        <a href="dnrcl/compliance.php" class="sidebar-link">Compliance Monitoring Dashboard</a>
                    </li>
                    <li class="sidebar-item">
                        <a href="dnrcl/notif_alert.php" class="sidebar-link">Notifications & Alerts</a>
                    </li>
                    <li class="sidebar-item">
                        <a href="dnrcl/audit_log.php" class="sidebar-link">Compliance Audit Log</a>
                    </li>
                </ul>
            </li>

            <li class="sidebar-item">
                <a href="#" class="sidebar-link collapsed has-dropdown" data-bs-toggle="collapse" data-bs-target="#evaluation"
                    aria-expanded="true" aria-controls="auth">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-cast" viewBox="0 0 640 640"><!--!Font Awesome Free v7.0.0 by @fontawesome - https://fontawesome.com License - https://fontawesome.com/license/free Copyright 2025 Fonticons, Inc.-->
                        <path d="M96 96C113.7 96 128 110.3 128 128L128 464C128 472.8 135.2 480 144 480L544 480C561.7 480 576 494.3 576 512C576 529.7 561.7 544 544 544L144 544C99.8 544 64 508.2 64 464L64 128C64 110.3 78.3 96 96 96zM208 288C225.7 288 240 302.3 240 320L240 384C240 401.7 225.7 416 208 416C190.3 416 176 401.7 176 384L176 320C176 302.3 190.3 288 208 288zM352 224L352 384C352 401.7 337.7 416 320 416C302.3 416 288 401.7 288 384L288 224C288 206.3 302.3 192 320 192C337.7 192 352 206.3 352 224zM432 256C449.7 256 464 270.3 464 288L464 384C464 401.7 449.7 416 432 416C414.3 416 400 401.7 400 384L400 288C400 270.3 414.3 256 432 256zM576 160L576 384C576 401.7 561.7 416 544 416C526.3 416 512 401.7 512 384L512 160C512 142.3 526.3 128 544 128C561.7 128 576 142.3 576 160z" />
                    </svg>
                    <span style="font-size: 18px;">Performance and Evaluation</span>
                </a>

                <ul id="evaluation" class="sidebar-dropdown list-unstyled collapse" data-bs-parent="#sidebar">
                    <li class="sidebar-item">
                        <a href="evaluation/doc_feedback.php" class="sidebar-link">View Nurse Evaluation</a>
                    </li>
                    <li class="sidebar-item">
                        <a href="evaluation/analytics.php" class="sidebar-link">Evaluation Report & Analytics</a>
                    </li>
                    <li class="sidebar-item">
                        <a href="evaluation/criteria.php" class="sidebar-link">Manage Evaluation Criteria</a>
                    </li>
                </ul>
            </li>
            <li class="sidebar-item">
                <a href="repair_request.php" class="sidebar-link collapsed has-dropdown" data-bs-toggle="#" data-bs-target="#request_repair"
                    aria-expanded="true" aria-controls="auth">
                     <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-cast" viewBox="0 0 640 640"><!--!Font Awesome Free v7.2.0 by @fontawesome - https://fontawesome.com License - https://fontawesome.com/license/free Copyright 2026 Fonticons, Inc.-->
                        <path d="M160 80c0-35.3 28.7-64 64-64s64 28.7 64 64l0 48-128 0 0-48zm-48 48l-64 0c-26.5 0-48 21.5-48 48L0 384c0 53 43 96 96 96l256 0c53 0 96-43 96-96l0-208c0-26.5-21.5-48-48-48l-64 0 0-48c0-61.9-50.1-112-112-112S112 18.1 112 80l0 48zm24 48a24 24 0 1 1 0 48 24 24 0 1 1 0-48zm152 24a24 24 0 1 1 48 0 24 24 0 1 1 -48 0z"/>
                    </svg>
                    <span style="font-size: 18px;">Purchase Request</span>
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
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:25px;">
                <!-- Doctors Box -->
                <div style="background:#fff; border-radius:14px; padding:25px; box-shadow:0 3px 10px rgba(0,0,0,0.06); text-align:center;">
                    <h2 style="font-size:18px; color:#333; margin-bottom:10px;">Doctors</h2>
                    <p style="color:#888; font-size:13px; margin-bottom:20px;">Total number of doctors</p>
                    <div style="font-size:48px; font-weight:700; color:#0d6efd;">
                        <?php echo $doctorCount; ?>
                    </div>
                </div>

                <!-- Nurses Box -->
                <div style="background:#fff; border-radius:14px; padding:25px; box-shadow:0 3px 10px rgba(0,0,0,0.06); text-align:center;">
                    <h2 style="font-size:18px; color:#333; margin-bottom:10px;">Nurses</h2>
                    <p style="color:#888; font-size:13px; margin-bottom:20px;">Total number of nurses</p>
                    <div style="font-size:48px; font-weight:700; color:#198754;">
                        <?php echo $nurseCount; ?>
                    </div>
                </div>
            </div>

            <center style="margin-top: 40px;">
                <button class="hahaha" onclick="openModal('doctorsModal')">Request Employee for Doctor</button> &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                <button class="hahaha" onclick="openModal('nursesModal')">Request Employee for Nurse</button>
            </center>

            <!-- Doctors Modal -->
            <div id="doctorsModal" class="bastabubukas">
                <div class="lalagyanannya">
                    <bttn class="close-btn" onclick="closeModal('doctorsModal')">X</bttn>
                    <center>
                        <h3 style="font-weight: bold;">Doctor Replacement Request</h3> 
                    </center>
                    <br />

                    <form action="submit_replacement_request.php" method="POST">
                        <input type="hidden" name="profession" value="Doctor" required>

                        <!-- Department / Subspecialty Dropdown -->
                        <label>Department / Subspecialty</label>
                        <select id="department" name="department" required>
                            <option value="">--- Select Department ---</option>
                            <option value="Anesthesiology & Pain Management">Anesthesiology & Pain Management</option>
                            <option value="Cardiology (Heart & Vascular System)">Cardiology (Heart & Vascular System)</option>
                            <option value="Dermatology (Skin, Hair, & Nails)">Dermatology (Skin, Hair, & Nails)</option>
                            <option value="Ear, Nose, and Throat (ENT)">Ear, Nose, and Throat (ENT)</option>
                            <option value="Emergency Department (ER)">Emergency Department (ER)</option>
                            <option value="Gastroenterology (Digestive System & Liver)">Gastroenterology (Digestive System & Liver)</option>
                            <option value="Geriatrics & Palliative Care (Elderly & Terminal Care)">Geriatrics & Palliative Care</option>
                            <option value="Infectious Diseases & Immunology">Infectious Diseases & Immunology</option>
                            <option value="Internal Medicine (General & Subspecialties)">Internal Medicine</option>
                            <option value="Nephrology (Kidneys & Dialysis)">Nephrology</option>
                            <option value="Neurology & Neurosurgery (Brain & Nervous System)">Neurology & Neurosurgery</option>
                            <option value="Obstetrics & Gynecology (OB-GYN)">Obstetrics & Gynecology (OB-GYN)</option>
                            <option value="Oncology (Cancer Treatment)">Oncology</option>
                            <option value="Ophthalmology (Eye Care)">Ophthalmology</option>
                            <option value="Orthopedics (Bones, Joints, and Muscles)">Orthopedics</option>
                            <option value="Pediatrics (Child Healthcare)">Pediatrics</option>
                            <option value="Psychiatry & Mental Health">Psychiatry & Mental Health</option>
                            <option value="Pulmonology (Lungs & Respiratory System)">Pulmonology</option>
                            <option value="Rehabilitation & Physical Therapy">Rehabilitation & Physical Therapy</option>
                            <option value="Surgery (General & Subspecialties)">Surgery</option>
                        </select>

                        <!-- Specialization Dropdown -->
                        <label>Specialist to Replace</label>
                        <select id="specialization" name="position" required>
                            <option value="">--- Select Specialization ---</option>
                        </select>

                        <!-- Other Specialization (hidden initially) -->
                        <input type="text" id="otherSpecialization" name="other_specialization" placeholder="Specify Other Specialist" style="display:none;">

                        <label>Leaving Employee Name</label>
                        <input type="text" name="leaving_employee_name">

                        <label>Leaving Employee ID</label>
                        <input type="text" name="leaving_employee_id">

                        <label>Reason for Leaving</label>
                        <textarea name="reason_for_leaving"></textarea>

                        <label>Requested By</label>
                        <input type="text" name="requested_by" required>

                        <button type="submit">Submit Request</button>
                    </form>
                </div>
            </div>

            <!-- Nurses Modal -->
            <div id="nursesModal" class="bastabubukas">
                <div class="lalagyanannya">
                    <bttn class="close-btn" onclick="closeModal('nursesModal')">X</bttn>
                    <center>
                        <h3 style="font-weight: bold;">Nurse Replacement Request</h3> 
                    </center>
                    <br />

                    <form action="submit_replacement_request.php" method="POST">
                        <input type="hidden" name="profession" value="Nurse" required>

                        <!-- Department / Subspecialty Dropdown -->
                        <label>Department / Subspecialty</label>
                        <select id="nurseDepartment" name="department" required>
                            <option value="">--- Select Department ---</option>
                            <option value="Anesthesiology & Pain Management">Anesthesiology & Pain Management</option>
                            <option value="Cardiology (Heart & Vascular System)">Cardiology (Heart & Vascular System)</option>
                            <option value="Dermatology (Skin, Hair, & Nails)">Dermatology (Skin, Hair, & Nails)</option>
                            <option value="Ear, Nose, and Throat (ENT)">Ear, Nose, and Throat (ENT)</option>
                            <option value="Emergency Department (ER)">Emergency Department (ER)</option>
                            <option value="Gastroenterology (Digestive System & Liver)">Gastroenterology (Digestive System & Liver)</option>
                            <option value="Geriatrics & Palliative Care (Elderly & Terminal Care)">Geriatrics & Palliative Care</option>
                            <option value="Infectious Diseases & Immunology">Infectious Diseases & Immunology</option>
                            <option value="Internal Medicine (General & Subspecialties)">Internal Medicine</option>
                            <option value="Nephrology (Kidneys & Dialysis)">Nephrology</option>
                            <option value="Neurology & Neurosurgery (Brain & Nervous System)">Neurology & Neurosurgery</option>
                            <option value="Obstetrics & Gynecology (OB-GYN)">Obstetrics & Gynecology (OB-GYN)</option>
                            <option value="Oncology (Cancer Treatment)">Oncology</option>
                            <option value="Ophthalmology (Eye Care)">Ophthalmology</option>
                            <option value="Orthopedics (Bones, Joints, and Muscles)">Orthopedics</option>
                            <option value="Pediatrics (Child Healthcare)">Pediatrics</option>
                            <option value="Psychiatry & Mental Health">Psychiatry & Mental Health</option>
                            <option value="Pulmonology (Lungs & Respiratory System)">Pulmonology</option>
                            <option value="Rehabilitation & Physical Therapy">Rehabilitation & Physical Therapy</option>
                            <option value="Surgery (General & Subspecialties)">Surgery</option>
                        </select>

                        <!-- Specialization Dropdown -->
                        <label>Nurse Type to Replace</label>
                        <select id="nurseSpecialization" name="position" required>
                            <option value="">--- Select Specialization ---</option>
                        </select>

                        <!-- Other Specialization (hidden initially) -->
                        <input type="text" id="otherNurseSpecialization" name="other_specialization" placeholder="Specify Other Nurse Type" style="display:none;">

                        <label>Leaving Employee Name</label>
                        <input type="text" name="leaving_employee_name">

                        <label>Leaving Employee ID</label>
                        <input type="text" name="leaving_employee_id">

                        <label>Reason for Leaving</label>
                        <textarea name="reason_for_leaving"></textarea>

                        <label>Requested By</label>
                        <input type="text" name="requested_by" required>

                        <button type="submit">Submit Request</button>
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

        function openModal(id) {
            document.getElementById(id).style.display = "flex";
        }
        function closeModal(id) {
            document.getElementById(id).style.display = "none";
        }

        window.onclick = function(event) {
            const modals = ['doctorsModal','nursesModal','pharmacyModal','laboratoryModal','accountingModal'];
            modals.forEach(id => {
                const modal = document.getElementById(id);
                if(event.target == modal) closeModal(id);
            });
        }

        <!-- JavaScript For Doctors -->
        const specializations = {
            "Anesthesiology & Pain Management": ["Anesthesiologist"],
            "Cardiology (Heart & Vascular System)": ["Cardiologist"],
            "Dermatology (Skin, Hair, & Nails)": ["Dermatologist"],
            "Ear, Nose, and Throat (ENT)": ["ENT Specialist (Otolaryngologist)"],
            "Emergency Department (ER)": ["Emergency Medicine Physician"],
            "Gastroenterology (Digestive System & Liver)": ["Gastroenterologist"],
            "Geriatrics & Palliative Care (Elderly & Terminal Care)": ["Internal Medicine Physician (Elder Care)", "General Practitioner (Elder Care)"],
            "Infectious Diseases & Immunology": ["Infectious Disease Specialist"],
            "Internal Medicine (General & Subspecialties)": ["Internal Medicine Physician", "General Practitioner"],
            "Nephrology (Kidneys & Dialysis)": ["Nephrologist"],
            "Neurology & Neurosurgery (Brain & Nervous System)": ["Neurologist", "Neurosurgeon"],
            "Obstetrics & Gynecology (OB-GYN)": ["Gynecologist / Obstetrician (OB-GYN)"],
            "Oncology (Cancer Treatment)": ["Oncologist"],
            "Ophthalmology (Eye Care)": ["Ophthalmologist"],
            "Orthopedics (Bones, Joints, and Muscles)": ["Orthopedic Surgeon"],
            "Pediatrics (Child Healthcare)": ["Pediatrician"],
            "Psychiatry & Mental Health": ["Psychiatrist"],
            "Pulmonology (Lungs & Respiratory System)": ["Pulmonologist"],
            "Rehabilitation & Physical Therapy": ["Rehabilitation Medicine Specialist"],
            "Surgery (General & Subspecialties)": ["General Surgeon", "Plastic Surgeon", "Vascular Surgeon"]
        };

        document.getElementById("department").addEventListener("change", function() {
            const dept = this.value;
            const specializationSelect = document.getElementById("specialization");
            const otherInput = document.getElementById("otherSpecialization");
            
            specializationSelect.innerHTML = '<option value="">--- Select Specialization ---</option>';
            
            if (specializations[dept]) {
                specializations[dept].forEach(function(spec) {
                    const opt = document.createElement("option");
                    opt.value = spec;
                    opt.textContent = spec;
                    specializationSelect.appendChild(opt);
                });
                const optOthers = document.createElement("option");
                optOthers.value = "Others";
                optOthers.textContent = "Others";
                specializationSelect.appendChild(optOthers);
            }
            otherInput.style.display = "none";
        });

        document.getElementById("specialization").addEventListener("change", function() {
            const otherInput = document.getElementById("otherSpecialization");
            if (this.value === "Others") {
                otherInput.style.display = "block";
            } else {
                otherInput.style.display = "none";
            }
        });

        // <!-- JavaScript For Nurse -->
        const nurseSpecializations = {
            "Anesthesiology & Pain Management": ["Anesthesia Nurse", "Pain Management Nurse"],
            "Cardiology (Heart & Vascular System)": ["Cardiac Nurse", "CCU Nurse"],
            "Dermatology (Skin, Hair, & Nails)": ["Dermatology Nurse", "Aesthetic Nurse"],
            "Ear, Nose, and Throat (ENT)": ["ENT Nurse"],
            "Emergency Department (ER)": ["ER Nurse", "Trauma Nurse"],
            "Gastroenterology (Digestive System & Liver)": ["GI Nurse", "Endoscopy Nurse"],
            "Geriatrics & Palliative Care (Elderly & Terminal Care)": ["Geriatric Nurse", "Palliative Care Nurse", "Hospice Nurse"],
            "Infectious Diseases & Immunology": ["Infection Control Nurse", "Immunology Nurse"],
            "Internal Medicine (General & Subspecialties)": ["General Medicine Nurse", "Medical Ward Nurse"],
            "Nephrology (Kidneys & Dialysis)": ["Dialysis Nurse", "Renal Nurse"],
            "Neurology & Neurosurgery (Brain & Nervous System)": ["Neuro Nurse", "Neuroscience Nurse"],
            "Obstetrics & Gynecology (OB-GYN)": ["OB Nurse", "Labor & Delivery Nurse", "Antenatal Care Nurse"],
            "Oncology (Cancer Treatment)": ["Oncology Nurse", "Chemotherapy Nurse"],
            "Ophthalmology (Eye Care)": ["Ophthalmic Nurse"],
            "Orthopedics (Bones, Joints, and Muscles)": ["Orthopedic Nurse", "Post-Ortho Surgery Nurse"],
            "Pediatrics (Child Healthcare)": ["Pediatric Nurse", "Pediatric ICU Nurse"],
            "Psychiatry & Mental Health": ["Psychiatric Nurse", "Mental Health Nurse"],
            "Pulmonology (Lungs & Respiratory System)": ["Pulmonary Nurse", "Respiratory Therapy Nurse"],
            "Rehabilitation & Physical Therapy": ["Rehab Nurse", "Physiotherapy Support Nurse"],
            "Surgery (General & Subspecialties)": ["Scrub Nurse", "Circulating Nurse", "Perioperative Nurse"]
        };

        document.getElementById("nurseDepartment").addEventListener("change", function() {
            const dept = this.value;
            const specializationSelect = document.getElementById("nurseSpecialization");
            const otherInput = document.getElementById("otherNurseSpecialization");
            
            specializationSelect.innerHTML = '<option value="">--- Select Specialization ---</option>';
            
            if (nurseSpecializations[dept]) {
                nurseSpecializations[dept].forEach(function(spec) {
                    const opt = document.createElement("option");
                    opt.value = spec;
                    opt.textContent = spec;
                    specializationSelect.appendChild(opt);
                });
                const optOthers = document.createElement("option");
                optOthers.value = "Others";
                optOthers.textContent = "Others";
                specializationSelect.appendChild(optOthers);
            }
            otherInput.style.display = "none";
        });

        document.getElementById("nurseSpecialization").addEventListener("change", function() {
            const otherInput = document.getElementById("otherNurseSpecialization");
            if (this.value === "Others") {
                otherInput.style.display = "block";
            } else {
                otherInput.style.display = "none";
            }
        });

    </script>
    <script src="assets/Bootstrap/all.min.js"></script>
    <script src="assets/Bootstrap/bootstrap.bundle.min.js"></script>
    <script src="assets/Bootstrap/fontawesome.min.js"></script>
    <script src="assets/Bootstrap/jq.js"></script>
</body>

</html>