<?php
include '../../SQL/config.php';
require_once 'class/patient.php';
require_once 'class/caller.php';

$patientObj = new Patient($conn);

$callerObj = new Caller($conn); // create Caller instance



try {
    $patient_id = $_GET['patient_id'] ?? null;
  
    $patient = $patientObj->getPatientOrFail($patient_id);
$wtf = $callerObj->getResults($patient_id);
    //  Fetch admission/EMR details
    try {
   
        $admission = $callerObj->callHistory($patient_id);
   
        
    } catch (Exception $e) {
        $admission = null; // No admission found
    }

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
    <title>HMS | Patient Managementnt</title>
    <link rel="shortcut icon" href="assets/image/favicon.ico" type="image/x-icon">
    <link rel="stylesheet" href="assets/CSS/bootstrap.min.css">
    <link rel="stylesheet" href="assets/CSS/super.css">
    <link rel="stylesheet" href="assets/CSS/iview.css">
</head>

<body>
    <div class="container mt-4">
        <div class="row">
            <div class="col-md-6 mb-4">
                <div class="card shadow h-100">
                    <h4 class="mb-0 p-3">View Patient Details</h4>
                    <div class="card-body">
                        <p><strong>Name:</strong>
                            <?= htmlspecialchars($patient['fname'] . ' ' . $patient['mname'] . ' ' . $patient['lname']) ?>
                        </p>
                        <p><strong>Address:</strong> <?= htmlspecialchars($patient['address']) ?></p>
                        <p><strong>Date of Birth:</strong>
                            <?= htmlspecialchars(date('F - d - Y', strtotime($patient['dob']))) ?></p>
                        <p><strong>Age:</strong> <?= htmlspecialchars($patient['age']) ?></p>
                        <p><strong>Gender:</strong> <?= htmlspecialchars($patient['gender']) ?></p>
                        <p><strong>Civil Status:</strong> <?= htmlspecialchars($patient['civil_status']) ?></p>
                        <p><strong>Contact Number:</strong> <?= htmlspecialchars($patient['phone_number']) ?></p>
                        <p><strong>Email:</strong> <?= htmlspecialchars($patient['email']) ?></p>
                        <p><strong>Admission Type:</strong> <?= htmlspecialchars($patient['admission_type']) ?></p>
                        <p><strong>Attending Doctor:</strong> <?= htmlspecialchars($patient['doctor_name']) ?></p>

                        <h4 class="mb-0 p-3">Previous Medical History</h4>
                        <div class="card-body">
                            <p><strong>Condition Name:</strong>
                                <?= htmlspecialchars($admission['condition_name'] ?? 'N/A') ?></p>
                            <p><strong>Diagnosis Date:</strong>
                                <?= htmlspecialchars($admission['diagnosis_date'] ?? 'N/A') ?></p>
                            <p><strong>Notes:</strong> <?= htmlspecialchars($admission['notes'] ?? 'N/A') ?></p>
                        </div>
                        <a href="inpatient.php" class="btn btn-secondary mt-3">Back</a>
                    </div>
                </div>
            </div>

            <!-- Laboratory Results -->
            <div class="col-lg-6">
                <div class="card shadow h-100">
                    <h4 class="mb-0 p-3">Laboratory Result</h4>
                    <div class="card-body">

                        <!-- CBC + CT Row -->
                        <div class="row d-flex align-items-stretch">
                            <!-- CBC -->
                            <div class="col-md-6 mb-3 d-flex">
                                <div class="border rounded p-3 border-3 flex-fill">
                                    <h6 class="text-primary">Complete Blood Count (CBC)</h6>
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

                            <!-- CT -->
                            <div class="col-md-6 mb-3 d-flex">
                                <div class="border rounded p-3 border-3 flex-fill">
                                    <h6 class="text-primary">CT Scan</h6>
                                    <p><strong>Test Type:</strong> <?= htmlspecialchars($wtf['ct_test'] ?? 'N/A') ?></p>
                                    <p><strong>Findings:</strong> <?= htmlspecialchars($wtf['ct_findings'] ?? 'N/A') ?>
                                    </p>
                                    <p><strong>Impression:</strong>
                                        <?= htmlspecialchars($wtf['ct_impression'] ?? 'N/A') ?></p>
                                    <p><strong>Remarks:</strong> <?= htmlspecialchars($wtf['ct_remarks'] ?? 'N/A') ?>
                                    </p>
                                    <?php if (!empty($wtf['ct_image'])): ?>
                                    <a href="<?= htmlspecialchars($wtf['ct_image']) ?>" target="_blank"
                                        class="btn btn-secondary btn-sm mt-2">
                                        View CT Image
                                    </a>
                                    <?php else: ?>
                                    <p class="text-muted"><em>No CT image available</em></p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <!-- MRI + X-Ray Row -->
                        <div class="row d-flex align-items-stretch">
                            <!-- MRI -->
                            <div class="col-md-6 mb-3 d-flex">
                                <div class="border rounded p-3 border-3 flex-fill">
                                    <h6 class="text-primary">MRI</h6>
                                    <p><strong>Test Type:</strong> <?= htmlspecialchars($wtf['mri_test'] ?? 'N/A') ?>
                                    </p>
                                    <p><strong>Findings:</strong> <?= htmlspecialchars($wtf['mri_findings'] ?? 'N/A') ?>
                                    </p>
                                    <p><strong>Impression:</strong>
                                        <?= htmlspecialchars($wtf['mri_impression'] ?? 'N/A') ?></p>
                                    <p><strong>Remarks:</strong> <?= htmlspecialchars($wtf['mri_remarks'] ?? 'N/A') ?>
                                    </p>
                                    <?php if (!empty($wtf['mri_image'])): ?>
                                    <a href="<?= htmlspecialchars($wtf['mri_image']) ?>" target="_blank"
                                        class="btn btn-secondary btn-sm mt-2">
                                        View MRI Image
                                    </a>
                                    <?php else: ?>
                                    <p class="text-muted"><em>No MRI image available</em></p>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- X-Ray -->
                            <div class="col-md-6 mb-3 d-flex">
                                <div class="border rounded p-3 border-3 flex-fill">
                                    <h6 class="text-primary">X-Ray</h6>
                                    <p><strong>Test Type:</strong> <?= htmlspecialchars($wtf['xray_test'] ?? 'N/A') ?>
                                    </p>
                                    <p><strong>Findings:</strong>
                                        <?= htmlspecialchars($wtf['xray_findings'] ?? 'N/A') ?></p>
                                    <p><strong>Impression:</strong>
                                        <?= htmlspecialchars($wtf['xray_impression'] ?? 'N/A') ?></p>
                                    <p><strong>Remarks:</strong> <?= htmlspecialchars($wtf['xray_remarks'] ?? 'N/A') ?>
                                    </p>
                                    <?php if (!empty($wtf['xray_image'])): ?>
                                    <a href="<?= htmlspecialchars($wtf['xray_image']) ?>" target="_blank"
                                        class="btn btn-secondary btn-sm mt-2  ">
                                        View X-Ray Image
                                    </a>
                                    <?php else: ?>
                                    <p class="text-muted"><em>No X-Ray image available</em></p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>
            </div>

        </div>
    </div>


    <script src="assets/Bootstrap/all.min.js"></script>
    <script src="assets/Bootstrap/bootstrap.bundle.min.js"></script>
    <script src="assets/Bootstrap/fontawesome.min.js"></script>
    <script src="assets/Bootstrap/jq.js"></script>


</body>

</html>