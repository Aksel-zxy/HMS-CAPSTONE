<?php
require '../../../SQL/config.php';
include '../includes/FooterComponent.php';
require_once '../classes/Auth.php';
require_once '../classes/User.php';
require_once 'classes/Pharmacist.php';
require_once 'classes/FileUploader.php';

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

$pharmacist = new Pharmacist($conn);
$uploader = new FileUploader($conn);

$search = $_GET['search'] ?? '';
$status = $_GET['status'] ?? '';
$pharmacists = $pharmacist->getPharmacists($search, $status);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = $_POST;
    $data['password'] = password_hash($data['password'], PASSWORD_DEFAULT);

    if ($pharmacist->employeeExists($data['employee_id'])) {
        echo "<script>alert('Error: Employee ID already exists!'); history.back();</script>";
        exit;
    }

    if ($pharmacist->addPharmacist($data)) {
        $uploader->uploadDocuments($data['employee_id'], $_FILES);
        echo "<script>alert('Pharmacist registered successfully!'); window.location='list_of_pharmacits.php';</script>";
    } else {
        echo "<script>alert('Error registering Pharmacist!');</script>";
    }
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
    <link rel="stylesheet" href="css/list_of_employees.css">
</head>
<body>

    <!----- Full-page Loader ----->
    <div id="loading-screen">
        <div class="loader">
            <div></div>
            <div></div>
            <div></div>
            <div></div>
            <div></div>
        </div>
    </div>
    
    <!----- Main Content ----->
    <div class="main">

        <!-- ----- TopBar ----- -->
        <div class="topbars">

            <div class="link-bar">
                <a href="onboarding.php" style="color:black;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="35px" height="35px" fill="currentColor" class="bi bi-arrow-left-circle" viewBox="0 0 16 16">
                        <path fill-rule="evenodd" d="M1 8a7 7 0 1 0 14 0A7 7 0 0 0 1 8m15 0A8 8 0 1 1 0 8a8 8 0 0 1 16 0m-4.5-.5a.5.5 0 0 1 0 1H5.707l2.147 2.146a.5.5 0 0 1-.708.708l-3-3a.5.5 0 0 1 0-.708l3-3a.5.5 0 1 1 .708.708L5.707 7.5z"/>
                    </svg>
                </a>
            </div>

            <!-- ----- Serach Bar ----- -->
            <div class="search-bar">
                <form method="GET" action="list_of_pharmacists.php">
                    <input type="text" name="search" placeholder="Search by Employee ID..." value="<?= htmlspecialchars($search); ?>">
                        <select name="status">
                            <option value="">All Status</option>
                            <option value="Active" <?= ($status === 'Active') ? 'selected' : ''; ?>>Active</option>
                            <option value="Inactive" <?= ($status === 'Inactive') ? 'selected' : ''; ?>>Inactive</option>
                            <option value="Resigned" <?= ($status === 'Resigned') ? 'selected' : ''; ?>>Resigned</option>
                        </select>
                    <input type="submit" value="Search">
                </form>
            </div>

        </div>

        <!-- ----- Add Pharmacists ----- -->
        <center>
            <button class="hahaha" onclick="openForm()">Add Pharmacist</button>
        </center>

        <!-- ----- Table ----- -->
        <div class="employees">
            <p style="text-align: center;font-size: 35px;font-weight: bold;padding-bottom: 20px;color: black;">Pharmacists List</p>
            <table id="PharmacistsTable">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Employee ID</th>
                        <th>License Number</th>
                        <th>Full Name</th>
                        <th>Department</th> 
                        <th>Specialization</th> 
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($pharmacists && $pharmacists->num_rows > 0): 
                        $i = 1;
                        while ($row = $pharmacists->fetch_assoc()): 
                    ?>
                        <tr>
                            <td><?= $i++ ?></td>
                            <td><?= htmlspecialchars($row['employee_id']); ?></td>
                            <td><?= htmlspecialchars($row['license_number']); ?></td>
                            <td><?= htmlspecialchars($row['first_name'] . ' ' . $row['middle_name'] . ' ' . $row['last_name'] . ' ' . $row['suffix_name']); ?></td>
                            <td><?= htmlspecialchars($row['department']); ?></td>
                            <td><?= htmlspecialchars($row['specialization']); ?></td>
                            <td>
                                <?php
                                    $statusText = htmlspecialchars($row['status']);
                                    $statusClass = 'status-' . strtolower($statusText);
                                ?>
                                <span class="status-badge <?= $statusClass; ?>"><?= $statusText; ?></span>
                            </td>
                            <td>
                                <center>
                                    <a href="view_pharmacist.php?employee_id=<?= htmlspecialchars($row['employee_id']); ?>" class="view-link">View Details</a>
                                </center>
                            </td>
                        </tr>
                    <?php endwhile; 
                        else: 
                    ?>
                        <tr>
                            <td colspan="8" style="text-align:center;">No Doctor found</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>

            <!-- ----- Pagination Controls ----- -->
            <div id="pagination" class="pagination"></div>
        </div>

        <!-- ----- Pop-Up Form (Add Pharmacists) ----- -->
        <div id="popupForm" class="popup-form">
            <div class="form-container">
                <bttn class="close-btn" onclick="closeForm()">X</bttn>
                <center>
                    <h3 style="font-weight: bold;">Add Pharmacist</h3> 
                </center>
                <form action="" method="post" enctype="multipart/form-data">

                <br />
                <br />

                <center>
                    <span style="color: red;font-weight: bold;font-size: 15px;">*Required fields must be filled out completely before submitting.</span>
                </center>

                <br />

                <center>
                    <h4 style="font-weight: bold;">Account</h4> 
                </center>

                <label for="employee_id"><span style="color: red;font-weight: bold;font-size: 15px;">*</span>Employee ID:</label>
                <input type="text" id="employee_id" name="employee_id" required pattern="^\d{3}$" title="Employee ID must be exactly 3 digits." oninput="updateUsername()">
                <center>
                    <span id="employee_idError" style="color: red;font-size: 15px;"></span>
                </center>
                <br />

                <label for="username"><span style="color: red;font-weight: bold;font-size: 15px;">*</span>Username: &nbsp;&nbsp;&nbsp;&nbsp;<span style="color: red;font-weight: bold;">(p + Employee ID)</span></label>
                <div style="display: flex; align-items: center;">
                    <input type="text" id="username" name="username" required style="flex: 1;" readonly>
                </div>
                <center>
                  <span id="usernameError" style="color: red;font-size: 15px;"></span>
                </center>
                <br />

                <label for="password"><span style="color: red;font-weight: bold;font-size: 15px;">*</span>Password: &nbsp;&nbsp;&nbsp;&nbsp;<span style="color: red;font-weight: bold;">(Last Name + 123)</span></label>
                <input type="text" id="password" name="password" required readonly>
                <br />
                <br />

                <center>
                  <h4 style="font-weight: bold;">Personal Information</h4> 
                </center>

                <label for="first_name"><span style="color: red;font-weight: bold;font-size: 15px;">*</span>First Name:</label>
                <input type="text" id="first_name" name="first_name" required>
                <br />

                <label for="middle_name">Middle Name:</label>
                <input type="text" id="middle_name" name="middle_name">
                <br />

                <label for="last_name"><span style="color: red;font-weight: bold;font-size: 15px;">*</span>Last Name:</label>
                <input type="text" id="last_name" name="last_name" required>
                <br />

                <label for="suffix_name">Suffix Name:</label>
                <input type="text" id="suffix_name" name="suffix_name">
                <br />

                <label for="gender"><span style="color: red;font-weight: bold;font-size: 15px;">*</span>Gender:</label>
                <select id="gender" name="gender">
                    <option value="">--- Select Sex ---</option>
                    <option value="Male">Male</option>
                    <option value="Female">Female</option>
                </select>
                <br />

                <label for="date_of_birth"><span style="color: red;font-weight: bold;font-size: 15px;">*</span>Date of Birth:</label>
                <input type="date" id="date_of_birth" name="date_of_birth" required>
                <br />

                <label for="contact_number"><span style="color: red;font-weight: bold;font-size: 15px;">*</span>Contact Number:</label>
                <input type="text" id="contact_number" name="contact_number" required pattern="^\d{11}$" title="Contact number must be 11 digits.">
                <br />

                <label for="email"><span style="color: red;font-weight: bold;font-size: 15px;">*</span>Personal Email:</label>
                <input type="email" id="email" name="email">
                <br />

                <label for="citizenship"><span style="color: red;font-weight: bold;font-size: 15px;">*</span>Citizenship:</label>
                <select id="citizenship" name="citizenship" required>
                    <option value="Filipino">Filipino</option>
                    <option value="American">American</option>
                    <option value="Indian">Indian</option>
                    <option value="British">British</option>
                    <option value="Australian">Australian</option>
                    <option value="Canadian">Canadian</option>
                    <option value="Thai">Thai</option>
                    <option value="French">French</option>
                    <option value="Saudi Arabian">Saudi Arabian</option>
                    <option value="Singaporean">Singaporean</option>
                    <option value="Chinese">Chinese</option>
                    <option value="Korean">Korean</option>
                    <option value="Japanese">Japanese</option>
                </select>
                <br />

                <label for="house_no"><span style="color: red;font-weight: bold;font-size: 15px;">*</span>House No./Street:</label>
                <input type="text" id="house_no" name="house_no" required>
                <br />

                <label for="barangay"><span style="color: red;font-weight: bold;font-size: 15px;">*</span>Barangay:</label>
                <input type="text" id="barangay" name="barangay" required>
                <br />
            
                <label for="city"><span style="color: red;font-weight: bold;font-size: 15px;">*</span>Municipality/City:</label>
                <input type="text" id="city" name="city" required>
                <br />

                <label for="province"><span style="color: red;font-weight: bold;font-size: 15px;">*</span>Province:</label>
                <input type="text" id="province" name="province" required>
                <br />

                <label for="region"><span style="color: red; font-weight: bold; font-size: 15px;">*</span>Region:</label>
                <select id="region" name="region" required>
                    <option value="">--- Select Region ---</option>
                    <option value="Region 1 - Ilocos Region">Region 1 - Ilocos Region</option>
                    <option value="Region 2 - Cagayan Valley">Region 2 - Cagayan Valley</option>
                    <option value="Region 3 - Central Luzon">Region 3 - Central Luzon</option>
                    <option value="Region 4A - CALABARZON">Region 4-A - CALABARZON</option>
                    <option value="Region 4B - MIMAROPA">Region 4-B - MIMAROPA</option>
                    <option value="Region 5 - Bicol Region">Region 5 - Bicol Region</option>
                    <option value="Region 6 - Western Visayas">Region 6 - Western Visayas</option>
                    <option value="Region 7 - Central Visayas">Region 7 - Central Visayas</option>
                    <option value="Region 8 - Eastern Visayas">Region 8 - Eastern Visayas</option>
                    <option value="Region 9 - Zamboanga Peninsula">Region 9 - Zamboanga Peninsula</option>
                    <option value="Region 10 - Northern Mindanao">Region 10 - Northern Mindanao</option>
                    <option value="Region 11 - Davao Region">Region 11 - Davao Region</option>
                    <option value="Region 12 - SOCCSKSARGEN">Region 12 - SOCCSKSARGEN</option>
                    <option value="Region 13 - Caraga">Region 13 - Caraga</option>
                    <option value="CAR - Cordillera Administrative Region">CAR - Cordillera Administrative Region</option>
                    <option value="NCR - National Capital Region">NCR - National Capital Region</option>
                    <option value="ARMM - Autonomous Region in Muslim Mindanao">ARMM - Autonomous Region in Muslim Mindanao</option>
                    <option value="BARMM - Bangsamoro Autonomous Region">BARMM - Bangsamoro Autonomous Region</option>
                </select>
                <br />    
                <br />

                <center>
                    <h4 style="font-weight: bold;">Role and Employment</h4> 
                </center>

                <label for="profession"><span style="color: red;font-weight: bold;font-size: 15px;">*</span>Profession:</label>
                <select id="profession" name="profession" required>
                    <option value="Pharmacist">Pharmacist</option>
                </select>
                <br />

                <label for="role"><span style="color: red;font-weight: bold;font-size: 15px;">*</span>Role:</label>
                <select id="role" name="role" required>
                    <option value="">--- Select Role ---</option>
                    <option value="Resident Pharmacist">Resident Pharmacist</option>
                    <option value="Clinical Pharmacist">Clinical Pharmacist</option>
                    <option value="Senior Pharmacist">Senior Pharmacist</option>
                    <option value="Pharmacy Supervisor">Pharmacy Supervisor</option>
                    <option value="Chief Pharmacist">Chief Pharmacist</option>            
                </select>
                <br />

                <label for="pharmacyDepartment"><span style="color: red; font-weight: bold; font-size: 15px;">*</span>Department:</label>
                <select id="pharmacyDepartment" name="pharmacyDepartment" required>
                    <option value="Pharmacy">Pharmacy</option>
                </select>

                <label for="pharmacySpecialization"><span style="color: red; font-weight: bold; font-size: 15px;">*</span>Specialization:</label>
                <select id="pharmacySpecialization" name="pharmacySpecialization" required>
                    <option value="">--- Select Specialization ---</option>
                    <option value="Clinical Pharmacist">Clinical Pharmacist</option>
                    <option value="Hospital Pharmacist">Hospital Pharmacist</option>
                    <option value="Compounding Pharmacist">Compounding Pharmacist</option>
                    <option value="Dispensing Pharmacist">Dispensing Pharmacist</option>
                </select>

                <label for="employment_type"><span style="color: red;font-weight: bold;font-size: 15px;">*</span>Employment Type:</label>
                <select id="employment_type" name="employment_type" required>
                    <option value="">--- Select Employment Type ---</option>
                    <option value="Full-Time">Full-Time</option>
                    <option value="Part-Time">Part-Time</option>
                    <option value="Contractual">Contractual</option>
                    <option value="Consultant">Consultant</option>
                </select>
                <br />

                <label for="status"><span style="color: red;font-weight: bold;font-size: 15px;">*</span>Status:</label>
                <select id="status" name="status" required>
                    <option value="Active">Active</option>
                </select>
                <br />
                <br />

                <center>
                    <h4 style="font-weight: bold;">License and Education</h4> 
                </center>

                <label for="educational_status"><span style="color: red;font-weight: bold;font-size: 15px;">*</span>Educational Status:</label>
                <select id="educational_status" name="educational_status" required>
                    <option value="">--- Select Educational Status ---</option>
                    <option value="Graduate">Graduate</option>
                    <option value="Post Graduate">Post Graduate</option>
                <select>
                <br />

                <label for="degree_type"><span style="color: red;font-weight: bold;font-size: 15px;">*</span>Degree Type:</label>
                <select id="degree_type" name="degree_type" required>
                    <option value="">--- Select Degree Type ---</option>
                    <option value="Bachelor of Science in Pharmacy (BS Pharmacy)">Bachelor of Science in Pharmacy (BS Pharmacy)</option>
                    <option value="Doctor of Pharmacy (PharmD)">Doctor of Pharmacy (PharmD)</option>
                </select>
                <br />

                <label for="medical_school"><span style="color: red;font-weight: bold;font-size: 15px;">*</span>Medical School:</label>
                <input type="text" id="medical_school" name="medical_school" required>
                <br />

                <label for="graduation_year"><span style="color: red;font-weight: bold;font-size: 15px;">*</span>Graduation Year:</label>
                <input type="number" name="graduation_year" min="1980" max="<?php echo date('Y'); ?>" placeholder="Enter the graduation year">
                <br />

                <label for="license_type"><span style="color: red;font-weight: bold;font-size: 15px;">*</span>License Type:</label>
                <select id="license_type" name="license_type" required>
                    <option value="Registered Pharmacist (RPh)">Registered Pharmacist (RPh)</option>
                <select>
                <br />

                <label for="license_number"><span style="color: red;font-weight: bold;font-size: 15px;">*</span>License Number:</label>
                <input type="text" id="license_number" name="license_number" required pattern="^\d{7}$" title="License Number must be exactly 7 digits.">
                <br />

                <label for="license_issued"><span style="color: red;font-weight: bold;font-size: 15px;">*</span>License Issued:</label>
                <input type="date" id="license_issued" name="license_issued" required>
                <br />

                <label for="license_expiry"><span style="color: red;font-weight: bold;font-size: 15px;">*</span>License Expiry:</label>
                <input type="date" id="license_expiry" name="license_expiry" required>
                <br />
                <br />

                <center>
                    <h4 style="font-weight: bold;">Emergency Contact</h4> 
                </center>

                <label for="eg_name"><span style="color: red;font-weight: bold;font-size: 15px;">*</span>Name:</label>
                <input type="text" id="eg_name" name="eg_name" required>
                <br />

                <label for="eg_relationship"><span style="color: red;font-weight: bold;font-size: 15px;">*</span>Relationship:</label>
                <input type="text" id="eg_relationship" name="eg_relationship" required>
                <br />

                <label for="eg_cn"><span style="color: red;font-weight: bold;font-size: 15px;">*</span>Contact Number:</label>
                <input type="text" id="eg_cn" name="eg_cn" required pattern="^\d{11}$" title="Contact number must be 11 digits.">
                <br />
                <br />
            
                <center>
                    <h4 style="font-weight: bold;">Documents</h4> 
                    <span style="color: red;font-weight: bold;font-size: 15px;">*Maximum file size: 5MB per document. Only PDF, JPG, JPEG, or PNG formats are accepted.*</span>
                </center>
 
                <label><span style="color: red;font-weight: bold;font-size: 15px;">*</span>Resume:</label>
                <input type="file" name="resume" accept=".pdf,.jpg,.jpeg,.png">

                <label><span style="color: red;font-weight: bold;font-size: 15px;">*</span>License ID:</label>
                <input type="file" name="license_id" accept=".pdf,.jpg,.jpeg,.png">

                <label><span style="color: red;font-weight: bold;font-size: 15px;">*</span>Board Rating & Certificate of Passing:</label>
                <input type="file" name="board_certification" accept=".pdf,.jpg,.jpeg,.png">

                <label><span style="color: red;font-weight: bold;font-size: 15px;">*</span>Diploma:</label>
                <input type="file" name="diploma" accept=".pdf,.jpg,.jpeg,.png">

                <label><span style="color: red;font-weight: bold;font-size: 15px;">*</span>Government ID:</label>
                <input type="file" name="government_id" accept=".pdf,.jpg,.jpeg,.png">

                <label><span style="color: red;font-weight: bold;font-size: 15px;">*</span>Application Letter:</label>
                <input type="file" name="application_letter" accept=".pdf,.jpg,.jpeg,.png">

                <label><span style="color: red;font-weight: bold;font-size: 15px;">*</span>Transcription of Records:</label>
                <input type="file" name="tor" accept=".pdf,.jpg,.jpeg,.png">

                <label><span style="color: red;font-weight: bold;font-size: 15px;">*</span>2x2 Formal Picture:</label>
                <input type="file" name="id_picture" accept=".pdf,.jpg,.jpeg,.png">

                <button type="submit">Submit</button>
            </form>

        </div>
    </div>

    <script>

        window.addEventListener("load", function(){
            setTimeout(function(){
                document.getElementById("loading-screen").style.display = "none";
            }, 2000);
        });

        document.addEventListener("DOMContentLoaded", function () {
            const table = document.getElementById("PharmacistsTable");
            const rows = table.querySelectorAll("tbody tr");
            const pagination = document.getElementById("pagination");

            let rowsPerPage = 10;
            let currentPage = 1;
            let totalPages = Math.ceil(rows.length / rowsPerPage);

            function displayRows() {
                rows.forEach((row, index) => {
                    row.style.display =
                    index >= (currentPage - 1) * rowsPerPage && index < currentPage * rowsPerPage
                    ? ""
                    : "none";
                });
            }

            function updatePagination() {
                pagination.innerHTML = ""; 

                const createButton = (text, page, isDisabled = false, isActive = false) => {
                    const button = document.createElement("button");
                    button.textContent = text;
                    if (isDisabled) button.disabled = true;
                    if (isActive) button.classList.add("active");

                    button.addEventListener("click", function () {
                        currentPage = page;
                        displayRows();
                        updatePagination();
                    });
                    return button;
                };

                pagination.appendChild(createButton("First", 1, currentPage === 1));
                pagination.appendChild(createButton("Previous", currentPage - 1, currentPage === 1));

                for (let i = 1; i <= totalPages; i++) {
                    pagination.appendChild(createButton(i, i, false, i === currentPage));
                }

                pagination.appendChild(createButton("Next", currentPage + 1, currentPage === totalPages));
                pagination.appendChild(createButton("Last", totalPages, currentPage === totalPages));
            }

            displayRows();
            updatePagination();
        });
  
        function openForm() {
            document.getElementById("popupForm").style.display = "flex";
        }

        function closeForm() {
            document.getElementById("popupForm").style.display = "none";
        }

        function updateUsername() {
            let employeeId = document.getElementById("employee_id").value;
            let usernameField = document.getElementById("username");
            
            if (/^\n{3}$/.test(employeeId)) {
                usernameField.value = "p" + employeeId;
            } else {
                usernameField.value = "";
            }
        }

        function updateUsername() {
            let employeeId = document.getElementById("employee_id").value;
            let usernameField = document.getElementById("username");
        
            usernameField.value = "p" + employeeId;
        }

        document.getElementById('employee_id').addEventListener('input', function() {
            var employee_id = this.value;
            if (employee_id.length == 3) {
            fetch('check_availability.php?type=employee_id&value=' + employee_id)
            .then(response => response.json())
            .then(data => {
                if (data.exists) {
                document.getElementById('employee_idError').textContent = "*Employee ID already exists.";
                } else {
                document.getElementById('employee_idError').textContent = "";
                }
            })
                .catch(err => console.log(err));
            }
        });

        document.getElementById("last_name").addEventListener("input", function() {
            let lastName = this.value.trim().toLowerCase();
            if (lastName) {
                document.getElementById("password").value = lastName + "123";
            } else {
                document.getElementById("password").value = "";
            }
        });

    </script>
    <script src="bootstrap/all.min.js"></script>
    <script src="bootstrap/bootstrap.bundle.min.js"></script>
    <script src="bootstrap/bootstrap.min.css"></script>
    <script src="bootstrap/fontawesome.min.js"></script>
</body>
</html>

