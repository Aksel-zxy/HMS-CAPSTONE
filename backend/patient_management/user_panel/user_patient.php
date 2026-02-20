<?php
include '../../../SQL/config.php';
include_once '../class/patient.php';
include_once '../class/caller.php';    

if (!isset($_SESSION['profession']) || $_SESSION['profession'] !== 'patient') {
    header('Location: ' . BASE_URL . 'backend/login.php');
    exit();
}

if (!isset($_SESSION['user_id'])) {
    echo "User ID is not set in session.";
    exit();
}

$patientObj = new Patient($conn);
$callerObj = new Caller($conn);

// Fetch user details from database
$query = "SELECT * FROM patient_user WHERE user_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if (!$user) {
    echo "No user found.";
    exit();
}

try {
    // Instead of $_GET, use the patient_id linked to this user
    $patient_id = $user['patient_id'] ?? null;

    if (!$patient_id) {
        throw new Exception("Patient ID not found for this user.");
    }

    // Get a single patient
    $patient = $patientObj->getPatientOrFail($patient_id);

    // Get only this patient's records
    $patients = $patientObj->getPatientsById($patient_id);

    $history = $callerObj->callHistory($patient_id);
    $wtf = $callerObj->getResults($patient_id);


    $prescriptions = $callerObj->callPrescription($patient_id);
} catch (Exception $e) {
    echo $e->getMessage();
    exit;
}

?>


<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HMS | Patient Management</title>
    <link rel="shortcut icon" href="../assets/image/favicon.ico" type="image/x-icon">
    <link rel="stylesheet" href="../assets/CSS/bootstrap.min.css">
    <link rel="stylesheet" href="../assets/CSS/super.css">
    <link rel="stylesheet" href="../assets/CSS/dashboard.css">
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
                <a href="user_patient.php" class="sidebar-link" data-bs-toggle="#" data-bs-target="#"
                    aria-expanded="false" aria-controls="auth">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor"
                        class="bi bi-cast" viewBox="0 0 16 16">
                        <path
                            d="m7.646 9.354-3.792 3.792a.5.5 0 0 0 .353.854h7.586a.5.5 0 0 0 .354-.854L8.354 9.354a.5.5 0 0 0-.708 0" />
                        <path
                            d="M11.414 11H14.5a.5.5 0 0 0 .5-.5v-7a.5.5 0 0 0-.5-.5h-13a.5.5 0 0 0-.5.5v7a.5.5 0 0 0 .5.5h3.086l-1 1H1.5A1.5 1.5 0 0 1 0 10.5v-7A1.5 1.5 0 0 1 1.5 2h13A1.5 1.5 0 0 1 16 3.5v7a1.5 1.5 0 0 1-1.5 1.5h-2.086z" />
                    </svg>
                    <span style="font-size: 18px;">Dashboard</span>
                </a>
            </li>


            <li class="sidebar-item">
                <a href="user_appointment.php" class="sidebar-link" data-bs-toggle="#" data-bs-target="#"
                    aria-expanded="false" aria-controls="auth">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor"
                        class="fa-regular fa-calendar" viewBox="0 0 16 16">

                        <path d=" M216 64C229.3 64 240 74.7 240 88L240 128L400 128L400 88C400 74.7 410.7 64 424 64C437.3
                        64 448 74.7 448 88L448 128L480 128C515.3 128 544 156.7 544 192L544 480C544 515.3 515.3 544 480
                        544L160 544C124.7 544 96 515.3 96 480L96 192C96 156.7 124.7 128 160 128L192 128L192 88C192 74.7
                        202.7 64 216 64zM216 176L160 176C151.2 176 144 183.2 144 192L144 240L496 240L496 192C496 183.2
                        488.8 176 480 176L216 176zM144 288L144 480C144 488.8 151.2 496 160 496L480 496C488.8 496 496
                        488.8 496 480L496 288L144 288z" />
                    </svg>
                    <span style="font-size: 18px;">Appointment History</span>
                </a>
            </li>


            <li class="sidebar-item">
                <a href="user_billing.php" class="sidebar-link" data-bs-toggle="#" data-bs-target="#"
                    aria-expanded="false" aria-controls="auth">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor"
                        class="fa-solid fa-clock-rotate-left" viewBox="0 0 16 16">

                        <path
                            d="M320 128C426 128 512 214 512 320C512 426 426 512 320 512C254.8 512 197.1 479.5 162.4 429.7C152.3 415.2 132.3 411.7 117.8 421.8C103.3 431.9 99.8 451.9 109.9 466.4C156.1 532.6 233 576 320 576C461.4 576 576 461.4 576 320C576 178.6 461.4 64 320 64C234.3 64 158.5 106.1 112 170.7L112 144C112 126.3 97.7 112 80 112C62.3 112 48 126.3 48 144L48 256C48 273.7 62.3 288 80 288L104.6 288C105.1 288 105.6 288 106.1 288L192.1 288C209.8 288 224.1 273.7 224.1 256C224.1 238.3 209.8 224 192.1 224L153.8 224C186.9 166.6 249 128 320 128zM344 216C344 202.7 333.3 192 320 192C306.7 192 296 202.7 296 216L296 320C296 326.4 298.5 332.5 303 337L375 409C384.4 418.4 399.6 418.4 408.9 409C418.2 399.6 418.3 384.4 408.9 375.1L343.9 310.1L343.9 216z" />
                    </svg>
                    <span style="font-size: 18px;">Billing History</span>
                </a>
            </li>

        </aside>
        <!----- End of Sidebar ----->
        <!----- Main Content ----->
        <div class="main">
            <div class="topbar">
                <div class="toggle">
                    <button class="toggler-btn" type="button">
                        <svg xmlns="http://www.w3.org/2000/svg" width="30px" height="30px" fill="currentColor"
                            class="bi bi-list-ul" viewBox="0 0 16 16">
                            <path fill-rule="evenodd"
                                d="M5 11.5a.5.5 0 0 1 .5-.5h9a.5.5 0 0 1 0 1h-9a.5.5 0 0 1-.5-.5m0-4a.5.5 0 0 1 .5-.5h9a.5.5 0 0 1 0 1h-9a.5.5 0 0 1-.5-.5m0-4a.5.5 0 0 1 .5-.5h9a.5.5 0 0 1 0 1h-9a.5.5 0 0 1-.5-.5m-3 1a1 1 0 1 0 0-2 1 1 0 0 0 0 2m0 4a1 1 0 1 0 0-2 1 1 0 0 0 0 2m0 4a1 1 0 1 0 0-2 1 1 0 0 0 0 2" />
                        </svg>
                    </button>
                </div>
                <div class="logo">
                    <div class="dropdown d-flex align-items-center">
                        <span class="username ml-1 me-2"><?php echo $user['fname']; ?>
                            <?php echo $user['lname']; ?></span><!-- Display the logged-in user's name -->
                        <button class="btn dropdown-toggle" type="button" id="dropdownMenuButton"
                            data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-person-circle"></i>
                        </button>
                        <ul class="dropdown-menu" aria-labelledby="dropdownMenuButton"
                            style="min-width: 200px; padding: 10px; border-radius: 5px; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); background-color: #fff; color: #333;">
                            <li style="margin-bottom: 8px; font-size: 14px; color: #555;">
                                <span>Welcome <strong
                                        style="color: #007bff;"><?php echo $user['lname']; ?></strong>!</span>
                            </li>
                            <li>
                                <a class="dropdown-item" href="../../logout.php"
                                    style="font-size: 14px; color: #007bff; text-decoration: none; padding: 8px 12px; border-radius: 4px; transition: background-color 0.3s ease;">
                                    Logout
                                </a>
                            </li>
                        </ul>

                    </div>
                </div>
            </div>

            <!-- START CODING HERE -->
            <div class="container-fluid border container-sm rounded shadow-sm bg-white p-4 mx-auto">
                <div class="border-bottom border-2 pb-3">
                    <h3>Records</h3>
                </div>
                <div class="mt-4 border-3 border-bottom mb-4">
                    <div class="card  mb-3" style="width: 100%;">
                        <div class="card-body w-100">
                            <h5 class="card-title text-primary mb-3">Patient Information</h5>
                            <div class="row">
                                <div class="col-md-6">
                                    <p class="fs-6"><strong>Patient ID:</strong>
                                        <?= htmlspecialchars($patient['patient_id']) ?></p>
                                    <p class="fs-6"><strong>Name:</strong>
                                        <?= htmlspecialchars($patient['fname'] . ' ' . $patient['mname'] . ' ' . $patient['lname']) ?>
                                    </p>
                                    <p class="fs-6"><strong>Age:</strong> <?= htmlspecialchars($patient['age']) ?></p>
                                </div>
                                <div class="col-md-6 text-md-start text-end">
                                    <p class="fs-6"><strong>Contact:</strong>
                                        <?= htmlspecialchars($patient['phone_number']) ?></p>
                                    <p class="fs-6"><strong>Gender:</strong> <?= htmlspecialchars($patient['gender']) ?>
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row g-3">
                        <!-- CBC Card -->
                        <div class="col-md-3 col-sm-6">
                            <div class="card shadow h-100">
                                <div class="card-body">
                                    <h5 class="card-title text-primary">CBC</h5>
                                    <p><strong>Test Type:</strong> <?= htmlspecialchars($wtf['cbc_test'] ?? 'N/A') ?>
                                    </p>
                                    <p><strong>WBC:</strong> <?= htmlspecialchars($wtf['wbc'] ?? 'N/A') ?></p>
                                    <p><strong>RBC:</strong> <?= htmlspecialchars($wtf['rbc'] ?? 'N/A') ?></p>
                                    <p><strong>Hemoglobin:</strong> <?= htmlspecialchars($wtf['hemoglobin'] ?? 'N/A') ?>
                                    </p>
                                    <p><strong>Platelets:</strong> <?= htmlspecialchars($wtf['platelets'] ?? 'N/A') ?>
                                    </p>
                                    <p><strong>Remarks:</strong> <?= htmlspecialchars($wtf['cbc_remarks'] ?? 'N/A') ?>
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- CT Scan Card -->
                        <div class="col-md-3 col-sm-6">
                            <div class="card shadow h-100">
                                <div class="card-body text-center">
                                    <h5 class="card-title text-primary">CT Scan</h5>
                                    <p><strong>Findings:</strong> <?= htmlspecialchars($wtf['ct_findings'] ?? 'N/A') ?>
                                    </p>
                                    <?php if (!empty($wtf['ct_image'])): ?>
                                    <a href="../view_image.php?type=ct&patient_id=<?= $wtf['patient_id'] ?>"
                                        class="btn btn-secondary btn-sm mt-2" target="_blank">View CT Scan Image</a>
                                    <?php else: ?>
                                    <p class="text-muted"><em>No CT Scan image</em></p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <!-- MRI Card -->
                        <div class="col-md-3 col-sm-6">
                            <div class="card shadow h-100">
                                <div class="card-body text-center">
                                    <h5 class="card-title text-primary">MRI</h5>
                                    <p><strong>Findings:</strong> <?= htmlspecialchars($wtf['mri_findings'] ?? 'N/A') ?>
                                    </p>
                                    <p><strong>Impression:</strong>
                                        <?= htmlspecialchars($wtf['mri_impression'] ?? 'N/A') ?></p>
                                    <?php if (!empty($wtf['mri_image'])): ?>
                                    <a href="../view_image.php?type=mri&patient_id=<?= $wtf['patient_id'] ?>"
                                        class="btn btn-secondary btn-sm mt-2" target="_blank">View MRI Image</a>
                                    <?php else: ?>
                                    <p class="text-muted"><em>No MRI image</em></p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <!-- X-Ray Card -->
                        <div class="col-md-3 col-sm-6">
                            <div class="card shadow h-100">
                                <div class="card-body text-center">
                                    <h5 class="card-title text-primary">X-Ray</h5>
                                    <p><strong>Findings:</strong>
                                        <?= htmlspecialchars($wtf['xray_findings'] ?? 'N/A') ?></p>
                                    <p><strong>Impression:</strong>
                                        <?= htmlspecialchars($wtf['xray_impression'] ?? 'N/A') ?></p>
                                    <?php if (!empty($wtf['xray_image'])): ?>
                                    <a href="../view_image.php?type=xray&patient_id=<?= $wtf['patient_id'] ?>"
                                        class="btn btn-secondary btn-sm mt-2" target="_blank">View X-Ray Image</a>
                                    <?php else: ?>
                                    <p class="text-muted"><em>No X-Ray image</em></p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- MEDICAL Table -->
                <div class="mt-4 border-3 border-bottom">
                    <div class="border-bottom border-2 pb-3">
                        <h3>Medical History</h3>
                    </div>
                    <table
                        style="width:100%; border-collapse:collapse; font-family:Arial, sans-serif; font-size:14px; background:#fff; border-radius:8px; overflow:hidden; min-height:100px;">
                        <thead>
                            <tr style="background:#f1f5f9; border-bottom:2px solid #dee2e6; text-align:left;">
                                <th style="padding:12px; text-align:center;">Date</th>
                                <th style="padding:12px; text-align:center;">Diagnosis</th>
                                <th style="padding:12px; text-align:center;">Notes</th>
                                <th style="padding:12px; text-align:center;"></th>

                            </tr>
                        </thead>
                        <!-- KAILANGAN I FIX-->
                        <tbody>
                            <?php if (!empty($history) && is_array($history)): ?>
                            <tr style="border-bottom:1px solid #f1f1f1; transition:background 0.2s;"
                                onmouseover="this.style.background='#f9fbfd';" onmouseout="this.style.background='';">
                                <td style="padding:12px; text-align:center;">
                                    <?= htmlspecialchars($history['diagnosis_date'] ?? '') ?>
                                </td>
                                <td style="padding:12px; text-align:center;">
                                    <?= htmlspecialchars($history['condition_name'] ?? '') ?>
                                </td>
                                <td style="padding:12px; text-align:center;">
                                    <?= htmlspecialchars($history['notes'] ?? '') ?>
                                </td>
                                <td style="text-align:center;">
                                    <a class="btn btn-sm"
                                        href="../Patient Management/discharged.php?patient_id=<?= htmlspecialchars($history['patient_id'] ?? '') ?>"
                                        style="padding:6px 12px; border-radius:6px; font-size:13px; background:red; border:none; color:#fff; cursor:pointer;">
                                        Download
                                    </a>
                                </td>
                            </tr>
                            <?php else: ?>
                            <tr>
                                <td colspan="11"
                                    style="text-align:center; padding:40px; color:#6c757d; font-style:italic;">
                                    📋 No Medical Found
                                </td>
                            </tr>
                            <?php endif; ?>
                        </tbody>

                    </table>
                </div>

                <!-- Prescriptions Table -->
                <div class="mt-4 border-3 border-bottom">
                    <div class="border-bottom border-2 pb-3">
                        <h3>Prescriptions</h3>
                    </div>
                    <table
                        style="width:100%; border-collapse:collapse; font-family:Arial, sans-serif; font-size:14px; background:#fff; border-radius:8px; overflow:hidden; min-height:100px;">
                        <thead>
                            <tr style="background:#f1f5f9; border-bottom:2px solid #dee2e6; text-align:left;">
                                <th style="text-align:center;">Date</th>
                                <th style="text-align:center;">Doctor</th>
                                <th style="text-align:center;">Medicines</th>
                                <th style="text-align:center;">Note</th>
                                <th style="text-align:center;">Status</th>
                                <th style="text-align:center;">Actions</th>

                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($prescriptions)): ?>
                            <?php foreach ($prescriptions as $pres): ?>
                            <tr style="border-bottom:1px solid #f1f1f1; transition:background 0.2s;"
                                onmouseover="this.style.background='#f9fbfd';" onmouseout="this.style.background='';">

                                <td style="padding:12px; text-align:center;">
                                    <?= htmlspecialchars($pres['formatted_date']) ?>
                                </td>
                                <td style="padding:12px; text-align:center;">
                                    <?= htmlspecialchars($pres['doctor_name']) ?>
                                </td>
                                <td style="padding:12px; text-align:center;">
                                    <?= $pres['medicines_list'] ?>
                                </td>
                                <td style="padding:12px; text-align:center;">
                                    <?= htmlspecialchars($pres['note'] ?? 'N/A') ?>
                                </td>
                                <td style="padding:12px; text-align:center;">
                                    <?= htmlspecialchars($pres['status']) ?>
                                </td>
                                <td>
                                    <a href="prescription_download.php?id=<?= $pres['prescription_id']; ?>"
                                        class="btn btn-info btn-sm" target="_blank">
                                        <i class="fa-solid fa-download"></i>
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php else: ?>
                            <tr>
                                <td colspan="6"
                                    style="text-align:center; padding:40px; color:#6c757d; font-style:italic;">
                                    📋 No Prescriptions Found
                                </td>
                            </tr>
                            <?php endif; ?>
                        </tbody>


                    </table>
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
    <script src="../assets/Bootstrap/all.min.js"></script>
    <script src="../assets/Bootstrap/bootstrap.bundle.min.js"></script>
    <script src="../assets/Bootstrap/fontawesome.min.js"></script>
    <script src="../assets/Bootstrap/jq.js"></script>
</body>

</html>