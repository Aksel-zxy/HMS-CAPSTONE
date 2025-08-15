<?php
require_once 'class/patient.php';
require_once 'class/caller.php';

$callerObj = new Caller($conn); // create Caller instance
$doctors = $callerObj->getAllDoctors(); // Fetch all doctors
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>HMS | Patient Management</title>
    <link rel="shortcut icon" href="assets/image/favicon.ico" type="image/x-icon">
    <link rel="stylesheet" href="assets/CSS/bootstrap.min.css">
    <link rel="stylesheet" href="assets/CSS/super.css">
    <!-- <link rel="stylesheet" href="assets/CSS/icreate.css"> -->
</head>

<body>

    <!-- Modal -->
    <div class="modal fade" id="addPatientModal" tabindex="-1" aria-labelledby="addPatientModalLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <form action="class/create.php" method="POST">
                    <div class="modal-body">
                        <?php if (isset($error)): ?>
                        <div class="alert alert-danger"><?php echo $error; ?></div>
                        <?php endif; ?>

                        <div class="step" id="step1">
                            <!-- FORM FIELDS -->
                            <div class="text-center mb-3">
                                <h5>Patient Information</h5>
                            </div>
                            <div class="row mb-3">
                                <label class="col-sm-3 col-form-label">First Name</label>
                                <div class="col-sm-9">
                                    <input type="text" class="form-control" name="fname" required minlength="2">
                                </div>
                            </div>

                            <div class="row mb-3">
                                <label class="col-sm-3 col-form-label">Middle Name</label>
                                <div class="col-sm-9">
                                    <input type="text" class="form-control" name="mname">
                                </div>
                            </div>

                            <div class="row mb-3">
                                <label class="col-sm-3 col-form-label">Last Name</label>
                                <div class="col-sm-9">
                                    <input type="text" class="form-control" name="lname" required minlength="2">
                                </div>
                            </div>

                            <div class="row mb-3">
                                <label class="col-sm-3 col-form-label">Address</label>
                                <div class="col-sm-9">
                                    <input type="text" class="form-control" name="address">
                                </div>
                            </div>

                            <div class="row mb-3">
                                <label class="col-sm-3 col-form-label">Date of Birth</label>
                                <div class="col-sm-9">
                                    <input type="date" class="form-control" id="dob" name="dob">
                                </div>
                            </div>

                            <div class="row mb-3">
                                <label class="col-sm-3 col-form-label">Age</label>
                                <div class="col-sm-9">
                                    <input type="number" class="form-control" id="age" name="age" readonly>
                                </div>
                            </div>

                            <div class="row mb-3">
                                <label class="col-sm-3 col-form-label">Gender</label>
                                <div class="col-sm-9">
                                    <select class="form-select" name="gender" required>
                                        <option value="">-- Select Gender --</option>
                                        <option value="Male">Male</option>
                                        <option value="Female">Female</option>
                                    </select>
                                </div>
                            </div>

                            <div class="row mb-3">
                                <label class="col-sm-3 col-form-label">Civil Status</label>
                                <div class="col-sm-9">
                                    <select class="form-select" name="civil_status" required>
                                        <option value="">-- Select Civil Status --</option>
                                        <option value="Single">Single</option>
                                        <option value="Married">Married</option>
                                        <option value="Divorced">Divorced</option>
                                        <option value="Widowed">Widowed</option>
                                        <option value="Separated">Separated</option>
                                    </select>
                                </div>
                            </div>

                            <div class="row mb-3">
                                <label class="col-sm-3 col-form-label">Phone Number</label>
                                <div class="col-sm-9">
                                    <input type="tel" class="form-control" name="phone_number" required maxlength="11">
                                </div>
                            </div>

                            <div class="row mb-3">
                                <label class="col-sm-3 col-form-label">Email</label>
                                <div class="col-sm-9">
                                    <input type="email" class="form-control" name="email">
                                </div>
                            </div>

                            <div class="row mb-3">
                                <label class="col-sm-3 col-form-label">Admission Type</label>
                                <div class="col-sm-9">
                                    <select class="form-select" name="admission_type" required>
                                        <option value="">-- Select Admission Type --</option>
                                        <option value="Emergency">Emergency</option>
                                        <option value="Planned">Planned</option>
                                        <option value="Elective">Elective</option>
                                        <option value="Day Case">Day Case</option>
                                        <option value="Maternity">Maternity</option>
                                        <option value="Outpatient">Outpatient</option>
                                        <option value="Observation">Observation</option>
                                    </select>
                                </div>
                            </div>


                            <div class="row mb-3">
                                <label class="col-sm-3 col-form-label">Attending Doctor</label>
                                <div class="col-sm-9">
                                    <select class="form-select" name="attending_doctor" required>
                                        <option value="">-- Attending Doctor --</option>
                                        <?php
                                        if ($doctors && $doctors->num_rows > 0) {
                                            while ($doc = $doctors->fetch_assoc()) {
                                                echo "<option value='{$doc['employee_id']}'>" .
                                                    htmlspecialchars($doc['first_name'] . ' ' . $doc['last_name']) .
                                                    "</option>";
                                            }
                                        } else {
                                            echo "<option value=''>No doctors available</option>";
                                        }
                                        ?>
                                    </select>
                                </div>
                            </div>

                        </div>

                        <div class="row mb-3 d-none" id="step2">
                            <div class="text-center mb-3">
                                <h5>Previous Medical History</h5>
                            </div>
                            <div class="row mb-3">
                                <label class="col-sm-3 col-form-label">Condition Name</label>
                                <div class="col-sm-9">
                                    <input type="text" class="form-control" name="condition_name" default="N/A">
                                </div>
                            </div>

                            <div class="row mb-3">
                                <label class="col-sm-3 col-form-label">Diagnosis Date</label>
                                <div class="col-sm-9">
                                    <input type="date" class="form-control" name="diagnosis_date" default="N/A">
                                </div>
                            </div>

                            <div class="row mb-3">
                                <label class="col-sm-3 col-form-label">Notes</label>
                                <div class="col-sm-9">
                                    <input type="text" class="form-control" name="note" default="N/A">
                                </div>
                            </div>
                        </div>
                    </div>



                    <div class="modal-footer">
                        <button type="button" id="prevBtn" class="btn btn-secondary d-none">Back</button>
                        <button type="button" id="nextBtn" class="btn btn-primary">Next</button>
                        <button type="submit" id="submitBtn" class="btn btn-success d-none">Submit</button>
                    </div>
                </form>
            </div>
        </div>
    </div>



    <script>
    // Auto-calculate age when DOB is changed
    document.getElementById("dob").addEventListener("change", function() {
        const dob = new Date(this.value);
        const today = new Date();
        if (!isNaN(dob.getTime())) {
            let age = today.getFullYear() - dob.getFullYear();
            const m = today.getMonth() - dob.getMonth();
            if (m < 0 || (m === 0 && today.getDate() < dob.getDate())) {
                age--;
            }
            document.getElementById("age").value = age;
        } else {
            document.getElementById("age").value = "";
        }
    });

    let step = 1;

    document.getElementById("nextBtn").addEventListener("click", function() {
        document.getElementById("step1").classList.add("d-none");
        document.getElementById("step2").classList.remove("d-none");
        document.getElementById("nextBtn").classList.add("d-none");
        document.getElementById("prevBtn").classList.remove("d-none");
        document.getElementById("submitBtn").classList.remove("d-none");
    });

    document.getElementById("prevBtn").addEventListener("click", function() {
        document.getElementById("step2").classList.add("d-none");
        document.getElementById("step1").classList.remove("d-none");
        document.getElementById("prevBtn").classList.add("d-none");
        document.getElementById("submitBtn").classList.add("d-none");
        document.getElementById("nextBtn").classList.remove("d-none");
    });
    </script>

</body>

</html>