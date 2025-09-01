<?php
require '../../../SQL/config.php';
require '../classes/Auth.php';
require '../classes/User.php';

Auth::checkHR();

$conn = $conn;

$userId = Auth::getUserId();
if (!$userId) {
    die("User ID not set.");
}

$userObj = new User($conn);
$user = $userObj->getById($userId);
if (!$user) {
    die("User not found.");
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HMS | HR Management</title>
    <link rel="shortcut icon" href="../assets/image/favicon.ico" type="image/x-icon">
    <link rel="stylesheet" href="../assets/CSS/bootstrap.min.css">
    <link rel="stylesheet" href="../assets/CSS/super.css">
</head>
<style>

/* ---------- TopBar ---------- */
.link-bar {
    align-items: center;
    padding: 0 10px;
    margin-top: 30px;
    margin-left: 50px;
}

.link-bar a:hover svg {
  color: #0096c7;
  transform: scale(1.3);
  transition: 0.2s ease-in-out;
}

/* ---------- Button ---------- */
.hahaha {
    display: inline;
    background-color: #0047ab;
    color: white;
    padding: 15px 20px;
    border: none;
    cursor: pointer; 
    font-weight: bold;
    width: 300px;
    opacity: 0.9;
    border-radius: 5px;
    font-size: 18px;
    margin-bottom: 30px;
}
    
.hahaha:hover {
    background-color: #0096c7;
    font-weight: bold;
    transition: background-color 0.3s;
    box-shadow: 10px 10px 10px rgba(0, 0, 0, 1);
}

/* ---------- Pop-Up Form ---------- */
.popup-form {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.8);
    justify-content: center;
    align-items: center;
    overflow-y: auto;
}

.form-container {
    background-color: #F5F6F7;
    color: black;
    padding: 20px;
    width: 600px; 
    position: relative;
    overflow-y: auto;
    max-height: 95vh;
}

.form-container input, 
.form-container select,
.form-container textarea {
    width: 100%;
    padding: 8px;
    margin: 10px 0;
    min-width: 0;
    font-size: 13px;
}

label{
    display: block;
    font-size: 15px;
    text-align: left;
    font-weight: bold;
}

.form-container button {
    padding: 10px;
    background-color: #0047ab;
    color: white;
    border: none;
    cursor: pointer;
    font-weight: bold;
    margin-top: 20px;
    width: 100%;
    border-radius: 5px;
}

.form-container button:hover {
    background-color: #0096c7;
    transition: background-color 0.3s;
    font-weight: bold;
}

.close-btn {
    position: absolute;
    top: 8px;
    right: 8px;
    background-color: darkred;
    color: white;
    border: none;
    padding: 5px 10px;
    cursor: pointer;
    font-size: 13px;
    border-radius: 100px;
}

.close-btn:hover {
    background-color: red;
    transform: scale(1.3);
    transition: 0.2s ease-in-out;
}

</style>

<body>

    <!----- Main Content ----->
    <div class="main">

        <!-- ----- TopBar ----- -->
        <div class="topbars">

            <div class="link-bar">
                <a href="job_management.php" style="color:black;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="35px" height="35px" fill="currentColor" class="bi bi-arrow-left-circle" viewBox="0 0 16 16">
                        <path fill-rule="evenodd" d="M1 8a7 7 0 1 0 14 0A7 7 0 0 0 1 8m15 0A8 8 0 1 1 0 8a8 8 0 0 1 16 0m-4.5-.5a.5.5 0 0 1 0 1H5.707l2.147 2.146a.5.5 0 0 1-.708.708l-3-3a.5.5 0 0 1 0-.708l3-3a.5.5 0 1 1 .708.708L5.707 7.5z"/>
                    </svg>
                </a>
            </div>

        </div>

        <p style="text-align: center;font-size: 35px;font-weight: bold;padding-bottom: 20px;color: black;">Replacement Request Forms</p>


        <!-- Buttons  (Palitan nyo na lang yung mga pangalan)-->
        <button class="hahaha" onclick="openModal('doctorsModal')">Doctors</button>
        <button class="hahaha" onclick="openModal('nursesModal')">Nurses</button>
        <button class="hahaha" onclick="openModal('pharmacyModal')">Pharmacy</button>
        <button class="hahaha" onclick="openModal('laboratoryModal')">Laboratory</button>
        <button class="hahaha" onclick="openModal('accountingModal')">Accounting</button>

        <!-- Doctors Modal -->
        <div id="doctorsModal" class="popup-form">
            <div class="form-container">
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
        <div id="nursesModal" class="popup-form">
            <div class="form-container">
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





        <!-- Pharmacy Modal -->
        <div id="pharmacyModal" class="popup-form">
            <div class="form-container">
                <bttn class="close-btn" onclick="closeModal('pharmacyModal')">X</bttn>
                <center>
                    <h3 style="font-weight: bold;">Pharmacy Department Replacement Request</h3> 
                </center>
                <br />

                <form action="submit_replacement_request.php" method="POST">
                    <input type="hidden" name="profession" value="Pharmacist" required>

                    <label for="department">Department:</label>
                    <select id="department" name="department" required>
                        <option value="Pharmacy">Pharmacy</option>
                    </select>

                    <!-- Specialist Dropdown -->
                    <label>Pharmacist Type to Replace</label>
                    <select name="position" required>
                        <option value="">--- Select Pharmacist Type ---</option>
                        <option value="Clinical Pharmacist">Clinical Pharmacist</option>
                        <option value="Hospital Pharmacist">Hospital Pharmacist</option>
                        <option value="Compounding Pharmacist">Compounding Pharmacist</option>
                        <option value="Dispensing Pharmacist">Dispensing Pharmacist</option>
                    </select>

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





        <!-- Laboratory Modal -->
        <div id="laboratoryModal" class="popup-form">
            <div class="form-container">
                <bttn class="close-btn" onclick="closeModal('laboratoryModal')">X</bttn>
                <center>
                    <h3 style="font-weight: bold;">Laboratory Department Replacement Request</h3> 
                </center>
                <br />

                <form action="submit_replacement_request.php" method="POST">
                    <input type="hidden" name="profession" value="Laboratorist" required>

                    <label for="department">Department:</label>
                    <select id="department" name="department" required>
                        <option value="Laboratory">Laboratory</option>
                    </select>

                    <label>Specialist to Replace</label>
                    <select name="position" required>
                        <option value="">--Select Specialist--</option>
                        <option value="Clinical Chemistry">Clinical Chemistry</option>
                        <option value="Hematology">Hematology</option>
                        <option value="Microbiology">Microbiology</option>
                        <option value="Parasitology">Parasitology</option>
                        <option value="Blood Bank / Immunohematology">Blood Bank / Immunohematology</option>
                        <option value="Immunology / Serology">Immunology / Serology</option>
                        <option value="Histopathology">Histopathology</option>
                    </select>

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





        <!-- Accounting Modal -->
        <div id="accountingModal" class="popup-form">
            <div class="form-container">
                <bttn class="close-btn" onclick="closeModal('accountingModal')">X</bttn>
                <center>
                    <h3 style="font-weight: bold;">Accounting Department Replacement Request</h3> 
                </center>
                <br />

                <form action="submit_replacement_request.php" method="POST">
                    <input type="hidden" name="profession" value="Accountant" required>

                    <label for="department">Department:</label>
                    <select id="department" name="department" required>
                        <option value="Accounting">Accounting</option>
                    </select>
                
                    <!-- Specialist Dropdown -->
                    <label>Position to Replace</label>
                    <select name="position" required>
                        <option value="">--- Select Position ---</option>
                        <option value="Billing">Billing</option>
                        <option value="Insurance">Insurance</option>
                        <option value="Expenses">Expenses</option>
                    </select>

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







    <script>

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


        <!-- JavaScript For Nurse -->
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
    <script src="../assets/Bootstrap/all.min.js"></script>
    <script src="../assets/Bootstrap/bootstrap.bundle.min.js"></script>
    <script src="../assets/Bootstrap/fontawesome.min.js"></script>
    <script src="../assets/Bootstrap/jq.js"></script>
</body>

</html>