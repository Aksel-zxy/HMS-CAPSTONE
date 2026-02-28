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
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content shadow-lg slide-in">

            <!-- Modal Header -->
            <div class="modal-header">
                <h5 class="modal-title" id="appointmentModalLabel">Book Appointment</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <!-- Modal Form -->
            <form action="class/pcreate.php" method="POST">
                <div class="modal-body">
                    <div class="row g-4 align-items-start">

                        <!-- LEFT SIDE: FORM -->
                        <div class="col-12 col-lg-6">

                            <!-- Patient -->
                            <div class="mb-3">
                                <label for="patient_search" class="form-label">Patient</label>
                                <input class="form-control" list="patientsList" id="patient_search"
                                    name="patient_search" placeholder="Search patient..." autocomplete="off" required>
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

                            <!-- Doctor -->
                            <div class="mb-3">
                                <label for="doctor" class="form-label">Doctor</label>
                                <select class="form-select" id="doctor" name="doctor" required>
                                    <option value="">-- Select Doctor --</option>
                                    <?php
                                    if ($doctors && $doctors->num_rows > 0) {
                                        while ($doctor = $doctors->fetch_assoc()) {
                                            $doctorName = "{$doctor['first_name']} {$doctor['last_name']}";
                                            echo "<option value=\"{$doctor['employee_id']}\">{$doctorName} - {$doctor['specialization']}</option>";
                                        }
                                    } else {
                                        echo "<option value=''>No doctors available</option>";
                                    }
                                    ?>
                                </select>
                            </div>

                            <!-- Appointment Date & Time -->
                            <div class="mb-3">
                                <label for="appointment_date" class="form-label">Appointment Date & Time</label>
                                <input type="datetime-local" class="form-control" id="appointment_date"
                                    name="appointment_date" required>
                            </div>

                            <!-- Purpose -->
                            <div class="mb-3">
                                <label for="purpose" class="form-label">Purpose</label>
                                <input type="text" class="form-control" id="purpose" name="purpose" value="consultation"
                                    readonly>
                            </div>

                            <!-- Status -->
                            <div class="mb-3">
                                <label for="status" class="form-label">Status</label>
                                <input type="text" class="form-control" id="status" name="status" value="Scheduled"
                                    readonly>
                            </div>

                            <!-- Notes -->
                            <div class="mb-3">
                                <label for="notes" class="form-label">Notes</label>
                                <textarea class="form-control" id="notes" name="notes" rows="3"></textarea>
                                <p class="text-muted small">Please indicate what test is taken</p>
                            </div>

                        </div>

                        <!-- RIGHT SIDE: DOCTOR SCHEDULE -->
                        <div class="col-12 col-lg-6">
                            <div id="doctor-schedule-panel" class="border rounded p-3 bg-light h-100">

                                <div class="d-flex align-items-center justify-content-center h-100 text-muted">
                                    <div class="text-center">
                                        <i class="bi bi-calendar2-week fs-1 mb-2 d-block"></i>
                                        <p>Select a doctor to view their schedule</p>
                                    </div>
                                </div>

                            </div>
                        </div>

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
<script>
const days = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'];
const dayLabels = {
    mon: 'Monday',
    tue: 'Tuesday',
    wed: 'Wednesday',
    thu: 'Thursday',
    fri: 'Friday',
    sat: 'Saturday',
    sun: 'Sunday'
};

document.getElementById('doctor').addEventListener('change', function() {
    const doctorId = this.value;
    const panel = document.getElementById('doctor-schedule-panel');

    if (!doctorId) {
        panel.innerHTML = `
            <div class="d-flex align-items-center justify-content-center h-100 text-muted">
                <div class="text-center">
                    <i class="bi bi-calendar2-week fs-1 mb-2 d-block"></i>
                    <p>Select a doctor to view their schedule</p>
                </div>
            </div>`;
        return;
    }

    panel.innerHTML =
        `<div class="text-center text-muted mt-5"><div class="spinner-border" role="status"></div><p class="mt-2">Loading schedule...</p></div>`;

    fetch(`class/get_doctor_schedule.php?doctor_id=${doctorId}`)
        .then(res => res.json())
        .then(data => {

            if (!data || data.length === 0) {
                panel.innerHTML =
                    `<div class="alert alert-success mt-3">
                No existing appointments for this doctor.
            </div>`;
                return;
            }

            let rows = '';

            data.forEach(appt => {
                const dateObj = new Date(appt.appointment_date);
                const formattedDate = dateObj.toLocaleDateString('en-US', {
                    year: 'numeric',
                    month: 'long',
                    day: 'numeric'
                });

                const formattedTime = dateObj.toLocaleTimeString('en-US', {
                    hour: '2-digit',
                    minute: '2-digit'
                });

                rows += `
            <tr>
                <td>${formattedDate}</td>
                <td>${formattedTime}</td>
                <td><span class="badge bg-primary">${appt.status}</span></td>
            </tr>
        `;
            });

            panel.innerHTML = `
        <h6 class="fw-semibold mb-3">Existing Appointments</h6>
        <div class="table-responsive">
            <table class="table table-bordered table-sm">
                <thead class="table-primary">
                    <tr>
                        <th>Date</th>
                        <th>Time</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>${rows}</tbody>
            </table>
        </div>`;
        })
        .catch(() => {
            panel.innerHTML =
                `<div class="alert alert-danger mt-3">Failed to load schedule. Please try again.</div>`;
        });
});

function formatTime(timeStr) {
    if (!timeStr) return '—';
    const [hour, minute] = timeStr.split(':');
    const h = parseInt(hour);
    const ampm = h >= 12 ? 'PM' : 'AM';
    const h12 = h % 12 || 12;
    return `${h12}:${minute} ${ampm}`;
}
</script>