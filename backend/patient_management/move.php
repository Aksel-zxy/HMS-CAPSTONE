<?php 
require_once 'class/patient.php';
require_once 'class/caller.php';

$patientObj = new Patient($conn);
$patients = $patientObj->getinPatients();

$CallerObj = new PatientAdmission($conn);
$beds = $CallerObj->getAvailableBeds();
?>

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
                        <label for="patient_search" class="form-label">Patient</label>
                        <input class="form-control" list="patientsList" id="patient_search" name="patient_search"
                            placeholder="Search patient..." autocomplete="off" required>
                        <datalist id="patientsList">
                            <?php
                                        if ($patients && $patients->num_rows > 0) {
                                            while ($patient = $patients->fetch_assoc()) {
                                                $displayName = "{$patient['fname']} {$patient['mname']} {$patient['lname']}";
                                                echo "<option value=\"{$displayName}\" data-id=\"{$patient['patient_id']}\"></option>";
                                            }
                                        }
                                        ?>
                        </datalist>
                        <input type="hidden" id="patient_id" name="patient_id">
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