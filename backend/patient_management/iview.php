<?php
include '../../SQL/config.php';
require_once 'class/patient.php';
require_once 'class/caller.php';
include 'class/logs.php';

$patientObj = new Patient($conn);

$callerObj = new Caller($conn); // create Caller instance



try {
    $patient_id = $_GET['patient_id'] ?? null;
  
    $patient = $patientObj->getPatientOrFail($patient_id);
    $prescriptions = $callerObj->callPrescription($patient_id);
    $balance = $callerObj->callBalance($patient_id);
    $wtf = $callerObj->getResults($patient_id);
    //  Fetch admission/EMR details
    try {
   
        $admission = $callerObj->callHistory($patient_id);
   
        
    } catch (Exception $e) {
        $admission = null; // No admission found
        $prescriptions = null; // No prescriptions found
    }

} catch (Exception $e) {
    echo $e->getMessage();
    exit;
}
$user_id = $_SESSION['user_id'];

logAction($conn, $user_id, 'VIEW_PATIENT', $patient_id);
?>


<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HMS | Patient Management</title>
    <link rel="shortcut icon" href="assets/image/favicon.ico" type="image/x-icon">
    <link rel="stylesheet" href="assets/CSS/bootstrap.min.css">
    <link rel="stylesheet" href="assets/CSS/super.css">
    <link rel="stylesheet" href="assets/CSS/iview.css">
</head>

<body>
    <div class="container mt-4">
        <div class="row g-4">

            <!-- Patient Details Card -->
            <div class="col-md-4">
                <div class="card shadow h-100">
                    <h4 class="mb-0 p-3">Patient Details</h4>
                    <div class="card-body">
                        <p><strong>Name:</strong>
                            <?= htmlspecialchars($patient['fname'] . ' ' . $patient['mname'] . ' ' . $patient['lname']) ?>
                        </p>
                        <p><strong>Address: </strong><?= htmlspecialchars($patient['address'] ?? '') ?></p>
                        <p><strong>Birth of Date:
                            </strong><?= htmlspecialchars(date('F - d - Y', strtotime($patient['dob'] ?? ''))) ?>
                        </p>
                        <p><strong>Age: </strong><?= htmlspecialchars($patient['age'] ?? '') ?></p>
                        <p><strong>Gender: </strong><?= htmlspecialchars($patient['gender'] ?? '') ?></p>
                        <p><strong>Civil Status: </strong><?= htmlspecialchars($patient['civil_status'] ?? '') ?>
                        </p>
                        <p><strong>Phone_number: </strong><?= htmlspecialchars($patient['phone_number'] ?? '') ?>
                        </p>
                        <p><strong>Email: </strong><?= htmlspecialchars($patient['email'] ?? '') ?></p>
                        <p><strong>Admission Type:
                            </strong><?= htmlspecialchars($patient['admission_type'] ?? '') ?></p>
                        <p><strong>Attending Doctor: </strong><?= htmlspecialchars($patient['doctor_name'] ?? '') ?>
                        </p>
                        <p><strong>Weight: </strong><?= htmlspecialchars($patient['weight'] ?? '') ?></p>
                        <p><strong>Height: </strong><?= htmlspecialchars($patient['height'] ?? '') ?></p>
                        <p><strong>Color of eyes: </strong><?= htmlspecialchars($patient['color_of_eyes'] ?? '') ?>

                        <h5 class="mt-4">Previous Medical History</h5>
                        <div>
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

            <!-- Laboratory Results Card -->
            <div class="col-md-4">
                <div class="card shadow h-100">
                    <h4 class="mb-0 p-3">Laboratory Results</h4>
                    <div class="card-body">

                        <h6 class="text-primary">Complete Blood Count (CBC)</h6>
                        <p><strong>Test Type:</strong> <?= htmlspecialchars($wtf['cbc_test'] ?? 'N/A') ?></p>
                        <p><strong>WBC:</strong> <?= htmlspecialchars($wtf['wbc'] ?? 'N/A') ?></p>
                        <p><strong>RBC:</strong> <?= htmlspecialchars($wtf['rbc'] ?? 'N/A') ?></p>
                        <p><strong>Hemoglobin:</strong> <?= htmlspecialchars($wtf['hemoglobin'] ?? 'N/A') ?></p>
                        <p><strong>Platelets:</strong> <?= htmlspecialchars($wtf['platelets'] ?? 'N/A') ?></p>
                        <p><strong>Remarks:</strong> <?= htmlspecialchars($wtf['cbc_remarks'] ?? 'N/A') ?></p>

                        <hr>
                        <h6 class="text-primary">CT Scan</h6>
                        <p><strong>Test Type:</strong> <?= htmlspecialchars($wtf['ct_test'] ?? 'N/A') ?></p>
                        <p><strong>Findings:</strong> <?= htmlspecialchars($wtf['ct_findings'] ?? 'N/A') ?></p>
                        <p><strong>Impression:</strong> <?= htmlspecialchars($wtf['ct_impression'] ?? 'N/A') ?></p>
                        <p><strong>Remarks:</strong> <?= htmlspecialchars($wtf['ct_remarks'] ?? 'N/A') ?></p>
                        <?php if (!empty($wtf['ct_image'])): ?>
                        <a href="../view_image.php?type=ct&patient_id=<?= $wtf['patient_id'] ?>"
                            class="btn btn-secondary btn-sm mt-2" target="_blank">View CT Scan Image</a>
                        <?php else: ?>
                        <p class="text-muted"><em>No CT Scan image</em></p>
                        <?php endif; ?>

                        <hr>
                        <h6 class="text-primary">MRI</h6>
                        <p><strong>Findings:</strong> <?= htmlspecialchars($wtf['mri_findings'] ?? 'N/A') ?></p>
                        <p><strong>Impression:</strong> <?= htmlspecialchars($wtf['mri_impression'] ?? 'N/A') ?></p>
                        <p><strong>Remarks:</strong> <?= htmlspecialchars($wtf['mri_remarks'] ?? 'N/A') ?></p>
                        <?php if (!empty($wtf['mri_image'])): ?>
                        <a href="../view_image.php?type=mri&patient_id=<?= $wtf['patient_id'] ?>"
                            class="btn btn-secondary btn-sm mt-2" target="_blank">View MRI Image</a>
                        <?php else: ?>
                        <p class="text-muted"><em>No MRI image</em></p>
                        <?php endif; ?>

                        <hr>
                        <h6 class="text-primary">X-Ray</h6>
                        <p><strong>Findings:</strong> <?= htmlspecialchars($wtf['xray_findings'] ?? 'N/A') ?></p>
                        <p><strong>Impression:</strong> <?= htmlspecialchars($wtf['xray_impression'] ?? 'N/A') ?>
                        </p>
                        <p><strong>Remarks:</strong> <?= htmlspecialchars($wtf['xray_remarks'] ?? 'N/A') ?></p>
                        <?php if (!empty($wtf['xray_image'])): ?>
                        <a href="view_image.php?type=xray&patient_id=<?= $wtf['patient_id'] ?>" target="_blank"
                            class="btn btn-secondary btn-sm mt-2">View X-Ray Image</a>
                        <?php else: ?>
                        <p class="text-muted"><em>No X-Ray image available</em></p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Prescription & Balance Card -->
            <div class="col-md-4">
                <div class="card shadow h-100">
                    <h4 class="mb-0 p-3">Prescription & Balance</h4>
                    <div class="card-body">
                        <?php if (!empty($prescriptions)): ?>
                        <?php foreach ($prescriptions as $pres): ?>
                        <p><strong>Date:</strong> <?= htmlspecialchars($pres['formatted_date']) ?></p>
                        <p><strong>Doctor:</strong> <?= htmlspecialchars($pres['doctor_name']) ?></p>
                        <p><strong>Medicines:</strong><br><?= $pres['medicines_list'] ?></p>
                        <p><strong>Note:</strong> <?= htmlspecialchars($pres['note'] ?? 'N/A') ?></p>
                        <p><strong>Status:</strong> <?= htmlspecialchars($pres['status']) ?></p>
                        <hr>
                        <?php endforeach; ?>
                        <?php else: ?>
                        <p class="text-muted"><em>No prescriptions available for this patient.</em></p>
                        <?php endif; ?>

                        <!-- Dynamic Balance Section -->
                        <!-- Dynamic Balance Section -->
                        <?php if (!empty($balance) && is_array($balance)): ?>

                        <h5 class="text-primary mt-3">Services</h5>

                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Service Name</th>
                                    <th>Qty</th>
                                    <th>Unit Price (₱)</th>
                                    <th>Total (₱)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $grandTotal = 0;
                                foreach ($balance as $row): 
                                    $grandTotal += $row['total_price'];
                                                    ?>
                                <tr>
                                    <td><?= htmlspecialchars($row['serviceName']) ?></td>
                                    <td><?= htmlspecialchars($row['quantity']) ?></td>
                                    <td><?= number_format($row['unit_price'], 2) ?></td>
                                    <td><?= number_format($row['total_price'], 2) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>

                        <hr>
                        <h5 class="text-end">
                            <strong>Total Balance: ₱<?= number_format($grandTotal, 2) ?></strong>
                        </h5>

                        <?php else: ?>
                        <p><strong>Balance:</strong> There's no outstanding balance.</p>
                        <?php endif; ?>

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




<script src="assets/Bootstrap/all.min.js"></script>
<script src="assets/Bootstrap/bootstrap.bundle.min.js"></script>
<script src="assets/Bootstrap/fontawesome.min.js"></script>
<script src="assets/Bootstrap/jq.js"></script>


</body>

</html>