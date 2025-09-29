<?php
require '../../../SQL/config.php';
require_once '../classes/Auth.php';
require_once '../classes/User.php';
require_once 'classes/Nurse.php';

Auth::checkHR();

$userId = Auth::getUserId();
if (!$userId) {
    die("User ID not set.");
}

$userObj = new User($conn);
$user = $userObj->getById($userId);
if (!$user) {
    die("User not found.");
}

$employeeId = $_GET['employee_id'] ?? '';
if (!$employeeId) {
    die("No Employee ID provided.");
}

$nurseObj = new Nurse($conn);
$employee = $nurseObj->getByEmployeeId($employeeId);

if (!$employee) {
    die("Employee not found.");
}

$documentsResult = $nurseObj->getEmployeeDocuments($employeeId);
$documents = $documentsResult->fetch_all(MYSQLI_ASSOC);

$uploadedDocs = [];
foreach ($documents as $doc) {
    $uploadedDocs[$doc['document_type']] = $doc['file_path'];
}

$docType = 'ID Picture';
$photoSrc = isset($uploadedDocs[$docType]) && !empty($uploadedDocs[$docType])
    ? str_replace(' ', '%20', $uploadedDocs[$docType]) 
    : 'css/pics/favicon.ico';

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
    <link rel="stylesheet" href="css/view_employees.css">
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

        <div class="employee-container">
            <div class="employee-card">
                <!-- ----- TopBar ----- -->
                <div class="topbars">

                    <div class="link-bar">
                        <a href="list_of_nurses.php" style="color:black;">
                            <svg xmlns="http://www.w3.org/2000/svg" width="35px" height="35px" fill="currentColor" class="bi bi-arrow-left-circle" viewBox="0 0 16 16">
                                <path fill-rule="evenodd" d="M1 8a7 7 0 1 0 14 0A7 7 0 0 0 1 8m15 0A8 8 0 1 1 0 8a8 8 0 0 1 16 0m-4.5-.5a.5.5 0 0 1 0 1H5.707l2.147 2.146a.5.5 0 0 1-.708.708l-3-3a.5.5 0 0 1 0-.708l3-3a.5.5 0 1 1 .708.708L5.707 7.5z"/>
                            </svg>
                        </a>
                    </div>

                    <!-- ----- Heading ----- -->
                    <div class="heading">
                        <h4>Employee Details</h4>
                    </div>
                </div>


                <div class="employee-header">
                    
                    <!-- ----- Left: Employee Photo ----- -->
                    <div class="employee-photo-section">
                        <img src="<?= htmlspecialchars($photoSrc); ?>"
                            alt="Employee Photo" 
                            class="employee-photo">

                        <?php
                            $statusClass = 'status-' . strtolower($employee['status']);
                            echo "<span class='status-badge {$statusClass}'>{$employee['status']}</span>";
                        ?>

                        <div class="employee-photo-buttons">
                            <center>
                                <a href="#" class="btn-edit" onclick="openEditModal()">Edit</a>
                            </center>
                        </div>
                    </div>

                    <!-- ----- Right: Personal Information ----- -->
                    <div class="card personal-info-card">
                        <h2 class="card-heading">Personal Information</h2>
                        <div class="info-grid">
                            <div><strong>Employee ID:</strong> <?= htmlspecialchars($employee['employee_id']); ?></div>
                            <div><strong>Full Name:</strong> <?= htmlspecialchars(trim($employee['first_name'] . ' ' . 
                                ($employee['middle_name'] ? $employee['middle_name'] . ' ' : '') . 
                                $employee['last_name'] . 
                                ($employee['suffix_name'] ? ', ' . $employee['suffix_name'] : ''))); ?></div>
                            <div><strong>Gender:</strong> <?= htmlspecialchars($employee['gender']); ?></div>
                            <div><strong>Date of Birth:</strong> <?= htmlspecialchars($employee['date_of_birth']); ?></div>
                            <div><strong>Contact Number:</strong> <?= htmlspecialchars($employee['contact_number']); ?></div>
                            <div><strong>Email:</strong> <?= htmlspecialchars($employee['email']); ?></div>
                            <div><strong>Citizenship:</strong> <?= htmlspecialchars($employee['citizenship']); ?></div>
                        </div>
                    </div>

                </div>


                <!-- ----- Two Column Layout ----- -->
                <div class="two-column-layout">
                        
                    <!-- ----- Left Column ----- -->
                    <div class="column">
                        <div class="card">
                            <h2 class="card-heading">Address Information</h2>
                            <div class="info-grid">
                                <div><strong>House No.:</strong> <?= htmlspecialchars($employee['house_no']); ?></div>
                                <div><strong>Barangay:</strong> <?= htmlspecialchars($employee['barangay']); ?></div>
                                <div><strong>City:</strong> <?= htmlspecialchars($employee['city']); ?></div>
                                <div><strong>Province:</strong> <?= htmlspecialchars($employee['province']); ?></div>
                                <div><strong>Region:</strong> <?= htmlspecialchars($employee['region']); ?></div>
                            </div>
                        </div>

                        <div class="card">
                            <h2 class="card-heading">License Information</h2>
                            <div class="info-grid">
                                <div><strong>License Type:</strong> <?= htmlspecialchars($employee['license_type']); ?></div>
                                <div><strong>License Number:</strong> <?= htmlspecialchars($employee['license_number']); ?></div>
                                <div><strong>License Issued:</strong> <?= htmlspecialchars($employee['license_issued']); ?></div>
                                <div><strong>License Expiry:</strong> <?= htmlspecialchars($employee['license_expiry']); ?></div>
                            </div>
                        </div>

                        <div class="card">
                            <h2 class="card-heading">Emergency Contact</h2>
                            <div class="info-grid">
                                <div><strong>Name:</strong> <?= htmlspecialchars($employee['eg_name']); ?></div>
                                <div><strong>Relationship:</strong> <?= htmlspecialchars($employee['eg_relationship']); ?></div>
                                <div><strong>Contact Number:</strong> <?= htmlspecialchars($employee['eg_cn']); ?></div>
                            </div>
                        </div>

                        <div class="card">
                            <h2 class="card-heading">System Information</h2>
                            <div class="info-grid">
                                <div><strong>Username:</strong> <?= htmlspecialchars($employee['username']); ?></div>
                                <div><strong>Password (hashed):</strong> <?= htmlspecialchars($employee['password']); ?></div>
                                <div><strong>Created At:</strong> <?= htmlspecialchars($employee['created_at']); ?></div>
                                <div><strong>Updated At:</strong> <?= htmlspecialchars($employee['update_at']); ?></div>
                            </div>
                        </div>
                        
                    </div>

                    <!-- ----- Right Column ----- -->
                    <div class="column">
                        <div class="card">
                            <h2 class="card-heading">Job Information</h2>
                            <div class="info-grid">
                                <div><strong>Hire Date:</strong> <?= htmlspecialchars($employee['hire_date']); ?></div>
                                <div><strong>Profession:</strong> <?= htmlspecialchars($employee['profession']); ?></div>
                                <div><strong>Role:</strong> <?= htmlspecialchars($employee['role']); ?></div>
                                <div><strong>Department:</strong> <?= htmlspecialchars($employee['department']); ?></div>
                                <div><strong>Specialization:</strong> <?= htmlspecialchars($employee['specialization']); ?></div>
                                <div><strong>Employment Type:</strong> <?= htmlspecialchars($employee['employment_type']); ?></div>
                                <div><strong>Status:</strong> <?= htmlspecialchars($employee['status']); ?></div>
                            </div>
                        </div>

                        <div class="card">
                            <h2 class="card-heading">Education Information</h2>
                            <div class="info-grid">
                                <div><strong>Educational Status:</strong> <?= htmlspecialchars($employee['educational_status']); ?></div>
                                <div><strong>Degree Type:</strong> <?= htmlspecialchars($employee['degree_type']); ?></div>
                                <div><strong>Medical School:</strong> <?= htmlspecialchars($employee['medical_school']); ?></div>
                                <div><strong>Graduation Year:</strong> <?= htmlspecialchars($employee['graduation_year']); ?></div>
                            </div>
                        </div>

                        <div class="card">
                            <h2 class="card-heading">Employee Documents</h2>
                            <div class="info-grid">
                                <?php
                                $requiredDocs = [
                                    'Resume',
                                    'License ID',
                                    'Board Rating & Certificate of Passing',
                                    'Diploma',
                                    'NBI/Police Clearance',
                                    'Government ID',
                                    'Birth Certificate',
                                    'Certificate of Good Moral',
                                    'Application Letter',
                                    'Medical Certificate',
                                    'Transcript of Records',
                                    'ID Picture'
                                ];

                                $uploadedDocs = [];
                                if (!empty($documents)) {
                                    foreach ($documents as $doc) {
                                        $path = $doc['document_path'] 
                                            ?? $doc['file_path'] 
                                            ?? $doc['path'] 
                                            ?? null;

                                        if (!empty($doc['document_type']) && !empty($path)) {
                                            $uploadedDocs[$doc['document_type']] = $path;
                                        }
                                    }
                                }

                                foreach ($requiredDocs as $docType) :
                                ?>
                                    <div>
                                        <strong><?= htmlspecialchars($docType); ?>:</strong>
                                        <?php if (isset($uploadedDocs[$docType])): ?>
                                            <a href="<?= htmlspecialchars($uploadedDocs[$docType]); ?>" target="_blank">View</a>
                                        <?php else: ?>
                                            <span style="color: red;">No Uploaded</span>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        
                    </div>

                </div>
            </div>
        </div>

        <div id="editModal" class="popup-form">
            <div class="form-container">
                <bttn class="close-btn" onclick="closeEditModal()">X</bttn>
                <center>
                    <h3 style="font-weight: bold;">Edit Employee Information</h3> 
                </center>

                <form action="update_nurse.php" method="post" enctype="multipart/form-data">
                    <input type="hidden" name="employee_id" value="<?= $employee['employee_id']; ?>">

                    <br />
                    <br />
                    
                    <center>
                        <h4 style="font-weight: bold;">Personal Information</h4>
                    </center>

                    <label>First Name:</label>
                    <input type="text" name="first_name" value="<?= htmlspecialchars($employee['first_name']); ?>" required>

                    <label>Middle Name:</label>
                    <input type="text" name="middle_name" value="<?= htmlspecialchars($employee['middle_name']); ?>">

                    <label>Last Name:</label>
                    <input type="text" name="last_name" value="<?= htmlspecialchars($employee['last_name']); ?>" required>

                    <label>Suffix:</label>
                    <input type="text" name="suffix_name" value="<?= htmlspecialchars($employee['suffix_name']); ?>">

                    <label>Gender:</label>
                    <select name="gender" required>
                        <option value="Male" <?= $employee['gender'] == 'Male' ? 'selected' : '' ?>>Male</option>
                        <option value="Female" <?= $employee['gender'] == 'Female' ? 'selected' : '' ?>>Female</option>
                    </select>

                    <label>Date of Birth:</label>
                    <input type="date" name="date_of_birth" value="<?= $employee['date_of_birth']; ?>" required>

                    <label>Contact Number:</label>
                    <input type="text" name="contact_number" value="<?= htmlspecialchars($employee['contact_number']); ?>" required>

                    <label>Email:</label>
                    <input type="email" name="email" value="<?= htmlspecialchars($employee['email']); ?>" required>

                    <label for="citizenship">Citizenship:</label>
                    <select id="citizenship" name="citizenship" required>
                        <option value="Filipino" <?= $employee['citizenship'] == 'Filipino' ? 'selected' : '' ?>>Filipino</option>
                        <option value="American" <?= $employee['citizenship'] == 'American' ? 'selected' : '' ?>>American</option>
                        <option value="Indian" <?= $employee['citizenship'] == 'Indian' ? 'selected' : '' ?>>Indian</option>
                        <option value="British" <?= $employee['citizenship'] == 'British' ? 'selected' : '' ?>>British</option>
                        <option value="Australian" <?= $employee['citizenship'] == 'Australian' ? 'selected' : '' ?>>Australian</option>
                        <option value="Canadian" <?= $employee['citizenship'] == 'Canadian' ? 'selected' : '' ?>>Canadian</option>
                        <option value="Thai" <?= $employee['citizenship'] == 'Thai' ? 'selected' : '' ?>>Thai</option>
                        <option value="French" <?= $employee['citizenship'] == 'French' ? 'selected' : '' ?>>French</option>
                        <option value="Saudi Arabian" <?= $employee['citizenship'] == 'Saudi Arabian' ? 'selected' : '' ?>>Saudi Arabian</option>
                        <option value="Singaporean" <?= $employee['citizenship'] == 'Singaporean' ? 'selected' : '' ?>>Singaporean</option>
                        <option value="Chinese" <?= $employee['citizenship'] == 'Chinese' ? 'selected' : '' ?>>Chinese</option>
                        <option value="Korean" <?= $employee['citizenship'] == 'Korean' ? 'selected' : '' ?>>Korean</option>
                        <option value="Japanese" <?= $employee['citizenship'] == 'Japanese' ? 'selected' : '' ?>>Japanese</option>
                    </select>

                    <label>House No.:</label>
                    <input type="text" name="house_no" value="<?= htmlspecialchars($employee['house_no']); ?>">

                    <label>Barangay:</label>
                    <input type="text" name="barangay" value="<?= htmlspecialchars($employee['barangay']); ?>">

                    <label>City:</label>
                    <input type="text" name="city" value="<?= htmlspecialchars($employee['city']); ?>">

                    <label>Province:</label>
                    <input type="text" name="province" value="<?= htmlspecialchars($employee['province']); ?>">

                    <label for="region">Region:</label>
                    <select id="region" name="region" required>
                        <option value="Region 1 - Ilocos Region" <?= $employee['region'] == 'Region 1 - Ilocos Region' ? 'selected' : '' ?>>Region 1 - Ilocos Region</option>
                        <option value="Region 2 - Cagayan Valley" <?= $employee['region'] == 'Region 2 - Cagayan Valley' ? 'selected' : '' ?>>Region 2 - Cagayan Valley</option>
                        <option value="Region 3 - Central Luzon" <?= $employee['region'] == 'Region 3 - Central Luzon' ? 'selected' : '' ?>>Region 3 - Central Luzon</option>
                        <option value="Region 4A - CALABARZON" <?= $employee['region'] == 'Region 4A - CALABARZON' ? 'selected' : '' ?>>Region 4A - CALABARZON</option>
                        <option value="Region 4B - MIMAROPA" <?= $employee['region'] == 'Region 4B - MIMAROPA' ? 'selected' : '' ?>>Region 4B - MIMAROPA</option>
                        <option value="Region 5 - Bicol Region" <?= $employee['region'] == 'Region 5 - Bicol Region' ? 'selected' : '' ?>>Region 5 - Bicol Region</option>
                        <option value="Region 6 - Western Visayas" <?= $employee['region'] == 'Region 6 - Western Visayas' ? 'selected' : '' ?>>Region 6 - Western Visayas</option>
                        <option value="Region 7 - Central Visayas" <?= $employee['region'] == 'Region 7 - Central Visayas' ? 'selected' : '' ?>>Region 7 - Central Visayas</option>
                        <option value="Region 8 - Eastern Visayas" <?= $employee['region'] == 'Region 8 - Eastern Visayas' ? 'selected' : '' ?>>Region 8 - Eastern Visayas</option>
                        <option value="Region 9 - Zamboanga Peninsula" <?= $employee['region'] == 'Region 9 - Zamboanga Peninsula' ? 'selected' : '' ?>>Region 9 - Zamboanga Peninsula</option>
                        <option value="Region 10 - Northern Mindanao" <?= $employee['region'] == 'Region 10 - Northern Mindanao' ? 'selected' : '' ?>>Region 10 - Northern Mindanao</option>
                        <option value="Region 11 - Davao Region" <?= $employee['region'] == 'Region 11 - Davao Region' ? 'selected' : '' ?>>Region 11 - Davao Region</option>
                        <option value="Region 12 - SOCCSKSARGEN" <?= $employee['region'] == 'Region 12 - SOCCSKSARGEN' ? 'selected' : '' ?>>Region 12 - SOCCSKSARGEN</option>
                        <option value="Region 13 - Caraga" <?= $employee['region'] == 'Region 13 - Caraga' ? 'selected' : '' ?>>Region 13 - Caraga</option>
                        <option value="CAR - Cordillera Administrative Region" <?= $employee['region'] == 'CAR - Cordillera Administrative Region' ? 'selected' : '' ?>>CAR - Cordillera Administrative Region</option>
                        <option value="NCR - National Capital Region" <?= $employee['region'] == 'NCR - National Capital Region' ? 'selected' : '' ?>>NCR - National Capital Region</option>
                        <option value="ARMM - Autonomous Region in Muslim Mindanao" <?= $employee['region'] == 'ARMM - Autonomous Region in Muslim Mindanao' ? 'selected' : '' ?>>ARMM - Autonomous Region in Muslim Mindanao</option>
                        <option value="BARMM - Bangsamoro Autonomous Region" <?= $employee['region'] == 'BARMM - Bangsamoro Autonomous Region' ? 'selected' : '' ?>>BARMM - Bangsamoro Autonomous Region</option>
                    </select>
                    <br />
                    <br />

                    <center>
                        <h4 style="font-weight: bold;">Role and Employment</h4> 
                    </center>
                    
                    <label for="profession">Profession:</label>
                    <select id="profession" name="profession" required>
                    <option value="Nurse" <?= ($employee['profession'] == 'Nurse') ? 'selected' : ''; ?>>Nurse</option>
                    </select>

                    <label for="role">Role:</label>
                    <select id="role" name="role" required>
                        <option value="Registered Nurse" <?= ($employee['role'] == 'Registered Nurse') ? 'selected' : ''; ?>>Registered Nurse</option>
                        <option value="Staff Nurse" <?= ($employee['role'] == 'Staff Nurse') ? 'selected' : ''; ?>>Staff Nurse</option>
                        <option value="Senior Staff Nurse" <?= ($employee['role'] == 'Senior Staff Nurse') ? 'selected' : ''; ?>>Senior Staff Nurse</option>
                        <option value="Charge Nurse" <?= ($employee['role'] == 'Charge Nurse') ? 'selected' : ''; ?>>Charge Nurse</option>
                        <option value="Head Nurse" <?= ($employee['role'] == 'Head Nurse') ? 'selected' : ''; ?>>Head Nurse</option>
                        <option value="Nursing Supervisor" <?= ($employee['role'] == 'Nursing Supervisor') ? 'selected' : ''; ?>>Nursing Supervisor</option>
                        <option value="Chief Nurse" <?= ($employee['role'] == 'Chief Nurse') ? 'selected' : ''; ?>>Chief Nurse</option>
                    </select>

                    <label for="department">Department:</label>
                    <select id="department" name="department" required>
                        <option value="Anesthesiology & Pain Management" <?= $employee['department'] == 'Anesthesiology & Pain Management' ? 'selected' : '' ?>>Anesthesiology & Pain Management</option>
                        <option value="Cardiology (Heart & Vascular System)" <?= $employee['department'] == 'Cardiology (Heart & Vascular System)' ? 'selected' : '' ?>>Cardiology (Heart & Vascular System)</option>
                        <option value="Dermatology (Skin, Hair, & Nails)" <?= $employee['department'] == 'Dermatology (Skin, Hair, & Nails)' ? 'selected' : '' ?>>Dermatology (Skin, Hair, & Nails)</option>
                        <option value="Ear, Nose, and Throat (ENT)" <?= $employee['department'] == 'Ear, Nose, and Throat (ENT)' ? 'selected' : '' ?>>Ear, Nose, and Throat (ENT)</option>
                        <option value="Emergency Department (ER)" <?= $employee['department'] == 'Emergency Department (ER)' ? 'selected' : '' ?>>Emergency Department (ER)</option>
                        <option value="Gastroenterology (Digestive System & Liver)" <?= $employee['department'] == 'Gastroenterology (Digestive System & Liver)' ? 'selected' : '' ?>>Gastroenterology (Digestive System & Liver)</option>
                        <option value="Geriatrics & Palliative Care (Elderly & Terminal Care)" <?= $employee['department'] == 'Geriatrics & Palliative Care (Elderly & Terminal Care)' ? 'selected' : '' ?>>Geriatrics & Palliative Care</option>
                        <option value="Infectious Diseases & Immunology" <?= $employee['department'] == 'Infectious Diseases & Immunology' ? 'selected' : '' ?>>Infectious Diseases & Immunology</option>
                        <option value="Internal Medicine (General & Subspecialties)" <?= $employee['department'] == 'Internal Medicine (General & Subspecialties)' ? 'selected' : '' ?>>Internal Medicine (General & Subspecialties)</option>
                        <option value="Nephrology (Kidneys & Dialysis)" <?= $employee['department'] == 'Nephrology (Kidneys & Dialysis)' ? 'selected' : '' ?>>Nephrology (Kidneys & Dialysis)</option>
                        <option value="Neurology & Neurosurgery (Brain & Nervous System)" <?= $employee['department'] == 'Neurology & Neurosurgery (Brain & Nervous System)' ? 'selected' : '' ?>>Neurology & Neurosurgery</option>
                        <option value="Obstetrics & Gynecology (OB-GYN)" <?= $employee['department'] == 'Obstetrics & Gynecology (OB-GYN)' ? 'selected' : '' ?>>OB-GYN</option>
                        <option value="Oncology (Cancer Treatment)" <?= $employee['department'] == 'Oncology (Cancer Treatment)' ? 'selected' : '' ?>>Oncology</option>
                        <option value="Ophthalmology (Eye Care)" <?= $employee['department'] == 'Ophthalmology (Eye Care)' ? 'selected' : '' ?>>Ophthalmology</option>
                        <option value="Orthopedics (Bones, Joints, and Muscles)" <?= $employee['department'] == 'Orthopedics (Bones, Joints, and Muscles)' ? 'selected' : '' ?>>Orthopedics</option>
                        <option value="Pediatrics (Child Healthcare)" <?= $employee['department'] == 'Pediatrics (Child Healthcare)' ? 'selected' : '' ?>>Pediatrics</option>
                        <option value="Psychiatry & Mental Health" <?= $employee['department'] == 'Psychiatry & Mental Health' ? 'selected' : '' ?>>Psychiatry & Mental Health</option>
                        <option value="Pulmonology (Lungs & Respiratory System)" <?= $employee['department'] == 'Pulmonology (Lungs & Respiratory System)' ? 'selected' : '' ?>>Pulmonology</option>
                        <option value="Rehabilitation & Physical Therapy" <?= $employee['department'] == 'Rehabilitation & Physical Therapy' ? 'selected' : '' ?>>Rehabilitation & Physical Therapy</option>
                        <option value="Surgery (General & Subspecialties)" <?= $employee['department'] == 'Surgery (General & Subspecialties)' ? 'selected' : '' ?>>Surgery</option>
                    </select>

                    <br />

                    <label for="specialization">Specialization:</label>
                    <select id="specialization" name="specialization" required>
                    </select>

                    <input type="text" id="otherSpecialization" name="otherSpecialization" placeholder="Please specify" style="display:none; margin-top:5px;">

                    <label for="employment_type">Employment Type:</label>
                    <select id="employment_type" name="employment_type" required>
                        <option value="Full-Time" <?= $employee['employment_type'] == 'Full-Time' ? 'selected' : '' ?>>Full-Time</option>
                        <option value="Part-Time" <?= $employee['employment_type'] == 'Part-Time' ? 'selected' : '' ?>>Part-Time</option>
                        <option value="Contractual" <?= $employee['employment_type'] == 'Contractual' ? 'selected' : '' ?>>Contractual</option>
                        <option value="Consultant" <?= $employee['employment_type'] == 'Consultant' ? 'selected' : '' ?>>Consultant</option>
                    </select>

                    <label>Status:</label>
                    <select name="status">
                        <option value="Active" <?= $employee['status'] == 'Active' ? 'selected' : '' ?>>Active</option>
                        <option value="Inactive" <?= $employee['status'] == 'Inactive' ? 'selected' : '' ?>>Inactive</option>
                        <option value="Resigned" <?= $employee['status'] == 'Resigned' ? 'selected' : '' ?>>Resigned</option>
                    </select>
                    <br />
                    <br />

                    <center>
                    <h4 style="font-weight: bold;">License and Education</h4> 
                    </center>

                    <label for="educational_status">Educational Status:</label>
                    <select id="educational_status" name="educational_status" required>
                        <option value="Graduate" <?= ($employee['educational_status'] == 'Graduate') ? 'selected' : ''; ?>>Graduate</option>
                        <option value="Post Graduate" <?= ($employee['educational_status'] == 'Post Graduate') ? 'selected' : ''; ?>>Post Graduate</option>
                    </select>

                    <label for="degree_type">Degree Type:</label>
                    <select id="degree_type" name="degree_type" required>
                        <option value="Bachelor of Science in Nursing (BSN)" <?= ($employee['degree_type'] == 'Bachelor of Science in Nursing (BSN)') ? 'selected' : ''; ?>>Bachelor of Science in Nursing (BSN)</option>
                    </select>
            
                    <label for="medical_school">Medical School:</label>
                    <input type="text" id="medical_school" name="medical_school" value="<?= $employee['medical_school']; ?>">

                    <label for="graduation_year">Graduation Year:</label>
                    <input type="number" name="graduation_year" id="graduation_year" min="1980" max="<?= date('Y'); ?>" value="<?= htmlspecialchars($employee['graduation_year'] ?? '') ?>">

                    <label>License Type:</label>
                    <select id="license_type" name="license_type">
                        <option value="General Physician" <?= ($employee['license_type'] == 'General Physician') ? 'selected' : ''; ?>>General Physician</option>
                    </select>

                    <label>License Number:</label>
                    <input type="text" name="license_number" value="<?= htmlspecialchars($employee['license_number']); ?>">

                    <label>License Issued:</label>
                    <input type="date" name="license_issued" value="<?= $employee['license_issued']; ?>">

                    <label>License Expiry:</label>
                    <input type="date" name="license_expiry" value="<?= $employee['license_expiry']; ?>">
                    <br />
                    <br />

                    <center>
                        <h4 style="font-weight: bold;">Emergency Contact</h4> 
                    </center>

                    <label>Name:</label>
                    <input type="text" name="eg_name" value="<?= htmlspecialchars($employee['eg_name']); ?>">

                    <label>Relationship:</label>
                    <input type="text" name="eg_relationship" value="<?= htmlspecialchars($employee['eg_relationship']); ?>">

                    <label>Contact Number:</label>
                    <input type="text" name="eg_cn" value="<?= htmlspecialchars($employee['eg_cn']); ?>">
                    <br />
                    <br />

                    <center>
                        <h4 style="font-weight: bold;">Uploaded Documents</h4> 
                    </center>


                    <!-- ----- Show all existing documents if any ----- -->
                    <?php
                        $requiredDocs = [
                            'Resume',
                            'License ID',
                            'Board Rating & Certificate of Passing',
                            'Diploma',
                            'NBI/Police Clearance',
                            'Government ID',
                            'Birth Certificate',
                            'Certificate of Good Moral',
                            'Application Letter',
                            'Medical Certificate',
                            'Transcript of Records',
                            'ID Picture'
                        ];

                        $uploadedDocs = [];
                        if (!empty($documents)) {
                            foreach ($documents as $doc) {
                                $path = $doc['document_path'] ?? $doc['file_path'] ?? null;
                                $uploadedDocs[$doc['document_type']] = $path;
                            }
                        }
                    ?>

                    <table class="info-table">
                        <?php foreach ($requiredDocs as $docType): ?>
                            <tr>
                                <td><?= htmlspecialchars($docType) ?></td>
                                <td>:</td>
                                <td>
                                    <?php if (!empty($uploadedDocs[$docType])): ?>
                                        <a class="link-btn" href="<?= htmlspecialchars($uploadedDocs[$docType]) ?>" target="_blank">View Document</a>
                                            <br />
                                            <br />
                                        <span class="badge badge-ok">Uploaded</span>
                                    <?php else: ?>
                                        <span class="badge badge-missing">No Uploaded</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </table>

                    <br />
                    <br />

                    <!-- ----- Upload new document ----- -->
                    <center>
                    <h4 style="font-weight: bold;">Upload New Updated Documents</h4> 
                    <span style="color: red;font-weight: bold;font-size: 15px;">*Maximum file size: 5MB per document. Only PDF, JPG, JPEG, or PNG formats are accepted.*</span>
                    </center>
                    <br />

                    <label>Resume:</label>
                    <input type="file" name="resume" accept=".pdf,.jpg,.jpeg,.png">

                    <label>License ID:</label>
                    <input type="file" name="license_id" accept=".pdf,.jpg,.jpeg,.png">

                    <label>Board Rating & Certificate of Passing:</label>
                    <input type="file" name="board_certificate" accept=".pdf,.jpg,.jpeg,.png">        
                        
                    <label>Diploma:</label>
                    <input type="file" name="diploma" accept=".pdf,.jpg,.jpeg,.png">

                    <label>NBI/Police Clearance:</label>
                    <input type="file" name="nbi_clearance" accept=".pdf,.jpg,.jpeg,.png">

                    <label>Government ID:</label>
                    <input type="file" name="government_id" accept=".pdf,.jpg,.jpeg,.png">

                    <label>Birth Certificate:</label>
                    <input type="file" name="birth_certificate" accept=".pdf,.jpg,.jpeg,.png">

                    <label>Certificate of Good Moral:</label>
                    <input type="file" name="good_moral" accept=".pdf,.jpg,.jpeg,.png">

                    <label>Application Letter:</label>
                    <input type="file" name="application_letter" accept=".pdf,.jpg,.jpeg,.png">

                    <label>Medical Certificate:</label>
                    <input type="file" name="medical_certificate" accept=".pdf,.jpg,.jpeg,.png">

                    <label>Transcription of Records:</label>
                    <input type="file" name="tor" accept=".pdf,.jpg,.jpeg,.png">

                    <label>2x2 Formal Picture:</label>
                    <input type="file" name="id_picture" accept=".pdf,.jpg,.jpeg,.png">


                    <button type="submit">Save Changes</button>
                </form>
            </div>
        </div>

    </div>

    <script>

        window.addEventListener("load", function(){
            setTimeout(function(){
                document.getElementById("loading-screen").style.display = "none";
            }, 2000);
        });

        function openEditModal() {
            document.getElementById('editModal').style.display = 'flex';
        }

        function closeEditModal() {
            document.getElementById('editModal').style.display = 'none';
        }

        window.onclick = function(event) {
            const modal = document.getElementById('editModal');
            if (event.target == modal) {
                modal.style.display = 'none';
            }
        }

        const specializations = {
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

        const deptSelect = document.getElementById("department");
        const specSelect = document.getElementById("specialization");
        const otherInput = document.getElementById("otherSpecialization");

        function populateSpecializations(selectedDept, selectedSpec = "") {
            specSelect.innerHTML = ' ';
            if (specializations[selectedDept]) {
                specializations[selectedDept].forEach(spec => {
                    const opt = document.createElement("option");
                    opt.value = spec;
                    opt.textContent = spec;
                    if (spec === selectedSpec) opt.selected = true;
                    specSelect.appendChild(opt);
                });
                const optOthers = document.createElement("option");
                optOthers.value = "Others";
                optOthers.textContent = "Others";
                if (selectedSpec === "Others") optOthers.selected = true;
                specSelect.appendChild(optOthers);
            }
            otherInput.style.display = selectedSpec === "Others" ? "block" : "none";
        }

        deptSelect.addEventListener("change", () => populateSpecializations(deptSelect.value));

        specSelect.addEventListener("change", () => {
            otherInput.style.display = specSelect.value === "Others" ? "block" : "none";
        });

        populateSpecializations("<?= $employee['department']; ?>", "<?= $employee['specialization']; ?>");

    </script>
    <script src="../assets/Bootstrap/all.min.js"></script>
    <script src="../assets/Bootstrap/bootstrap.bundle.min.js"></script>
    <script src="../assets/Bootstrap/fontawesome.min.js"></script>
    <script src="../assets/Bootstrap/jq.js"></script>
</body>

</html>