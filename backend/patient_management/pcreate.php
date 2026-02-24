<?php
include '../../SQL/config.php';
require_once 'class/patient.php';
require_once 'class/caller.php';

$appointmentObj = new caller($conn);
$appointments = $appointmentObj->getAllAppointments();

$getDoctors = new Caller($conn);
$doctors = $getDoctors->getDoctors();

$patientObj = new Patient($conn);
$patients = $patientObj->getAllPatients();
?>

<div class="modal fade" id="appointmentModal" tabindex="-1" aria-labelledby="appointmentModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content shadow-lg slide-in">

            <!-- Modal Header -->
            <div class="modal-header">
                <h5 class="modal-title" id="appointmentModalLabel">Book Appointment</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <!-- Modal Form -->
            <form action="class/pcreate.php" method="POST">
                <div class="modal-body">

                    <!-- Patient ID -->
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

                    <!-- Purpose -->
                    <div class="mb-3">
                        <label for="doctor" class="form-label">Doctor</label>
                        <select class="form-select" id="doctor" name="doctor" required>
                            <option value="">-- Select Doctor --</option>
                            <?php 
                                        if ($doctors && $doctors->num_rows > 0) {
                                            while ($doctor = $doctors->fetch_assoc()) {
                                                $doctorName = "{$doctor['first_name']} {$doctor['last_name']}";
                                                // The value is employee_id
                                                echo "<option value=\"{$doctor['employee_id']}\">{$doctorName} - {$doctor['specialization']}</option>";
                                            }
                                        } else {
                                            echo "<option value=\"\">No doctors available</option>";
                                        }
                                        ?>
                        </select>
                    </div>
                    <!-- Appointment Date & Time -->
                   <label for="appointment_time">Appointment Time:</label>
<select name="appointment_time" id="appointment_time" required>
    <?php if (!empty($timeSlots)): ?>
        <?php foreach ($timeSlots as $slot): ?>
            <option value="<?= $slot ?>"><?= $slot ?></option>
        <?php endforeach; ?>
    <?php else: ?>
        <option value="">No available times</option>
    <?php endif; ?>
</select>

                    <!-- Purpose -->
                    <div class="mb-3">
                        <label for="purpose" class="form-label">Purpose</label>
                        <input type="text" class="form-control" id="purpose" name="purpose" value="consultation"
                            readonly>
                    </div>

                    <!-- Status -->
                    <div class="mb-3">
                        <label for="status" class="form-label">Status</label>
                        <input type="text" class="form-control" id="status" name="status" value="Scheduled" readonly>
                    </div>

                    <!-- Notes -->
                    <div class="mb-3">

                        <label for="notes" class="form-label">Notes</label>
                        <textarea class="form-control" id="notes" name="notes" rows="3"></textarea>
                        <p>Please Indicate what test is taken</p>
                    </div>

                </div>
                <input type="hidden" name="submitted_by" value="admin">

                <!-- Modal Footer -->
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Save Appointment</button>
                </div>
            </form>
        </div>
    </div>
</div>