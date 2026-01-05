<?php 
require_once 'class/patient.php';
require_once 'class/caller.php';

$patientObj = new Patient($conn);
$patients = $patientObj->getAllPatients();

$CallerObj = new PatientAdmission($conn);
$beds = $CallerObj->getAvailableBeds();
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
    <link rel="stylesheet" href="assets/CSS/move.css">
</head>

<body>

    <!-- Transfer Patient Modal -->
    <div class="modal fade" id="moveModal" tabindex="-1" aria-labelledby="moveModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content shadow-lg slide-in">

                <div class="modal-header">
                    <h5 class="modal-title">Transfer Patient</h5>
                    <a href="bedding.php" class="btn-close"></a>
                </div>

                <div class="modal-body">

                    <form id="movePatientForm" method="POST" action="class/transfer.php">

                        <div class="mb-3">
                            <label for="patient_select" class="form-label">Patient</label>
                            <select class="form-select" id="patient_select" name="patient_id" required>
                                <option value="">Select a patient...</option>
                                <?php
                                if ($patients && $patients->num_rows > 0) {
                                    while ($patient = $patients->fetch_assoc()) {
                                        $displayName = "{$patient['fname']} {$patient['mname']} {$patient['lname']}";
                                        echo "<option value=\"{$patient['patient_id']}\">{$displayName}</option>";
                                    }
                                }
                                ?>
                            </select>
                        </div>


                        <div class="mb-3">
                            <label for="bed_select" class="form-label">Available Beds</label>
                            <select class="form-select" id="bed_select" name="bed_id" required>
                                <option value="">Select a bed...</option>
                                <?php
                                if ($beds && $beds->num_rows > 0) {
                                    while ($bed = $beds->fetch_assoc()) {
                                        echo "<option value=\"{$bed['bed_id']}\">{$bed['bed_number']}</option>";
                                    }
                                }
                                ?>
                            </select>
                        </div>

                        <div class="form-floating mb-3">
                            <select class="form-select" name="reason" required>
                                <option value="">Select reason...</option>
                                <option value="transfer">Transfer</option>
                                <option value="discharge">Discharge</option>
                                <option value="emergency">Emergency</option>
                            </select>
                            <label>Reason</label>
                        </div>

                        <div class="d-flex justify-content-end gap-2">
                            <a href="patient_dashboard.php" class="btn btn-secondary">Cancel</a>
                            <button type="submit" class="btn btn-primary">Move Patient</button>
                        </div>

                    </form>
                </div>
            </div>
        </div>
    </div>



    <script>
    const patientInput = document.getElementById('patient_search');
    const patientIdInput = document.getElementById('patient_id');
    const form = document.getElementById('movePatientForm');

    // Fill hidden patient_id when user selects from datalist
    patientInput.addEventListener('input', function() {
        patientIdInput.value = '';
        const inputVal = this.value.trim().toLowerCase();
        const options = document.querySelectorAll('#patientsList option');
        options.forEach(opt => {
            if (opt.value.trim().toLowerCase() === inputVal) {
                patientIdInput.value = opt.dataset.id;
            }
        });
    });

    // Submit-time validation to prevent empty patient_id
    form.addEventListener('submit', function(e) {
        if (!patientIdInput.value) {
            e.preventDefault(); // Stop form submission
            alert("Please select a valid patient from the list.");
            patientInput.focus();
        }
    });
    </script>
    <script src="assets/Bootstrap/all.min.js"></script>
    <script src="assets/Bootstrap/bootstrap.bundle.min.js"></script>
    <script src="assets/Bootstrap/fontawesome.min.js"></script>
    <script src="assets/Bootstrap/jq.js"></script>

</body>

</html>