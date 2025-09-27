<?php
include '../../SQL/config.php';
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
                <form id="addPatientForm" action="class/create.php" method="POST">
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
                                    <input type="text" class="form-control" name="fname">
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
                                    <input type="text" class="form-control" name="lname">
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
                                <!-- Height -->
                                <label class="col-sm-2 col-form-label">Height</label>
                                <div class="col-sm-4">
                                    <input type="text" class="form-control" name="height">
                                </div>

                                <!-- Weight -->
                                <label class="col-sm-2 col-form-label">Weight</label>
                                <div class="col-sm-4">
                                    <input type="text" class="form-control" name="weight">
                                </div>
                            </div>

                            <div class="row mb-3">
                                <!-- Color of the Eyes -->
                                <label class="col-sm-3 col-form-label">Color of the Eyes</label>
                                <div class="col-sm-9">
                                    <input type="text" class="form-control" name="Coe">
                                </div>

                            </div>


                            <div class="row mb-3">
                                <label class="col-sm-3 col-form-label">Admission Type</label>
                                <div class="col-sm-9">
                                    <input class="form-control" name="admission_type" value="Registered Patient"
                                        readonly>
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
                                    <input type="text" class="form-control" name="condition_name">
                                </div>
                            </div>

                            <div class="row mb-3">
                                <label class="col-sm-3 col-form-label">Diagnosis Date</label>
                                <div class="col-sm-9">
                                    <input type="date" class="form-control" name="diagnosis_date">
                                </div>
                            </div>

                            <div class="row mb-3">
                                <label class="col-sm-3 col-form-label">Notes</label>
                                <div class="col-sm-9">
                                    <input type="text" class="form-control" name="note">
                                </div>
                            </div>
                        </div>
                    </div>




                    <div class="modal-footer">
                        <button type="button" id="prevBtn" class="btn btn-secondary d-none">Back</button>
                        <button type="button" id="nextBtn" class="btn btn-primary">Next</button>
                        <button type="submit" id="submitBtn" class="btn btn-success d-none">Submit</button>
                        <button type="submit" id="skipBtn" class="btn btn-warning">Skip</button>
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

    // ✅ Validation function
    function validateForm(form) {
        const fname = form.querySelector("input[name='fname']").value.trim();
        const lname = form.querySelector("input[name='lname']").value.trim();
        const mname = form.querySelector("input[name='mname']").value.trim();
        const age = form.querySelector("input[name='age']").value;

        const nameRegex = /^[A-Za-z\s\-]+$/;
        const vowelRegex = /[AEIOUaeiou]/;

        if (fname.length < 2 || !nameRegex.test(fname) || !vowelRegex.test(fname)) {
            alert(
                "First name invalid. Only letters, spaces, and hyphens allowed, and must contain at least one vowel."
            );
            return false;
        }

        if (lname.length < 2 || !nameRegex.test(lname)) {
            alert("Last name invalid. Only letters, spaces, and hyphens allowed.");
            return false;
        }

        if (mname !== "" && !nameRegex.test(mname)) {
            alert("Middle name invalid. Only letters, spaces, and hyphens allowed.");
            return false;
        }


        // ✅ Check if age is -1
        if (parseInt(age, 10) < 0) {
            alert("Invalid Date of Birth.");
            return false;
        }

        return true; // ✅ All good
    }

    document.addEventListener("DOMContentLoaded", function() {
        const form = document.getElementById("addPatientForm");

        // Handle main form submit
        form.addEventListener("submit", function(e) {
            e.preventDefault(); // always stop default first
            console.log("Submit intercepted");

            if (validateForm(form)) {
                console.log("All good, submitting...");
                form.submit(); // only submit if valid
            }
        });

        // Handle next/prev buttons
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

        // ✅ Fix skip button (no bypass)
        document.getElementById("skipBtn").addEventListener("click", function(e) {
            e.preventDefault(); // stop instant submission
            if (validateForm(form)) {
                console.log("Skip pressed, form valid, submitting...");
                form.submit();
            }
        });
    });
    </script>


</body>

</html>