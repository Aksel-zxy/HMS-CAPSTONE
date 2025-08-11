<?php
require_once 'class/update.php';
require_once 'class/patient.php';



// Create patient object
$patientObj = new Patient($conn);

// Fetch main patient data
$patient = $patientObj->getPatientById($patient_id);

// Fetch medical history from p_previous_medical_history
$sql = "SELECT condition_name, diagnosis_date, notes 
        FROM p_previous_medical_history 
        WHERE patient_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $patient_id);
$stmt->execute();
$medical_history = $stmt->get_result()->fetch_assoc();

// Merge into $patient array so HTML works
if ($medical_history) {
    $patient = array_merge($patient, $medical_history);
} else {
    // If no history, set defaults
    $patient['condition_name'] = '';
    $patient['diagnosis_date'] = '';
    $patient['notes'] = '';
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

</head>

<body>
    <div class="container">
        <div class="card shadow">
            <div class="card-header bg-primary txt-white">
                <h4 class="mb-0">Edit Details</h4>
            </div>
            <div class="card-body">


                <form method="post">
                    <div class="step" id="step1">
                        <div class="row mb-3">
                            <label class="col-sm-3 col-form-label">First Name</label>
                            <div class="col-sm-6">
                                <input type="text" class="form-control" name="fname" value="<?= $patient['fname']; ?>"
                                    required minlenght="2">
                            </div>
                        </div>
                        <div class="row mb-3">
                            <label class="col-sm-3 col-form-label">Middle Name</label>
                            <div class="col-sm-6">
                                <input type="text" class="form-control" name="mname" value="<?= $patient['mname']; ?>">
                            </div>
                        </div>
                        <div class="row mb-3">
                            <label class="col-sm-3 col-form-label">Last Name</label>
                            <div class="col-sm-6">
                                <input type="text" class="form-control" name="lname" value="<?= $patient['lname']; ?>"
                                    required minlenght="2">
                            </div>
                        </div>
                        <div class="row mb-3">
                            <label class="col-sm-3 col-form-label">Address</label>
                            <div class="col-sm-6">
                                <input type="text" class="form-control" name="address"
                                    value="<?= $patient['address']; ?>">
                            </div>
                        </div>
                        <div class="row mb-3">
                            <label class="col-sm-3 col-form-label">Date of Birth</label>
                            <div class="col-sm-6">
                                <input type="date" class="form-control" id="dob" name="dob"
                                    value="<?= $patient['dob']; ?>">
                            </div>
                        </div>
                        <div class="row mb-3">
                            <label class="col-sm-3 col-form-label">Age</label>
                            <div class="col-sm-6">
                                <input type="age" class="form-control" id="age" name="age"
                                    value="<?= $patient['age']; ?>" readonly>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <label class="col-sm-3 col-form-label">Gender</label>
                            <div class="col-sm-6">
                                <select class="form-select" name="gender" required>
                                    <option value="">-- Select Gender --</option>
                                    <option value="Male" <?= ($patient['gender'] == 'Male') ? 'selected' : ''; ?>>Male
                                    </option>
                                    <option value="Female" <?= ($patient['gender'] == 'Female') ? 'selected' : ''; ?>>
                                        Female
                                    </option>
                                </select>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <label class="col-sm-3 col-form-label">Civil Status</label>
                            <div class="col-sm-6">
                                <select class="form-select" name="civil_status" required>
                                    <option value="">-- Select Civil Status --</option>
                                    <option value="Single"
                                        <?=($patient['civil_status'] == 'Single') ? 'selected' : ''; ?>>
                                        Single</option>
                                    <option value="Married"
                                        <?=($patient['civil_status'] == 'Married') ? 'selected' : ''; ?>>Married
                                    </option>
                                    <option value="Divorced"
                                        <?=($patient['civil_status'] == 'Divorced') ? 'selected' : ''; ?>>Divorced
                                    </option>
                                    <option value="Widowed"
                                        <?=($patient['civil_status'] == 'Widowed') ? 'selected' : ''; ?>>Widowed
                                    </option>
                                    <option value="Separated"
                                        <?=($patient['civil_status'] == 'Separated') ? 'selected' : ''; ?>>Separated
                                    </option>
                                </select>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <label class="col-sm-3 col-form-label">Phone Number</label>
                            <div class="col-sm-6">
                                <input type="tel" class="form-control" name="phone_number"
                                    value="<?= $patient['phone_number']; ?>" required maxlength="11">
                            </div>
                        </div>
                        <div class="row mb-3">
                            <label class="col-sm-3 col-form-label">Email</label>
                            <div class="col-sm-6">
                                <input type="email" class="form-control" name="email" value="<?= $patient['email']; ?>">
                            </div>
                        </div>
                        <div class="row mb-3">
                            <label class="col-sm-3 col-form-label">Admission Type</label>
                            <div class="col-sm-6">
                                <select class="form-select" name="admission_type" required>
                                    <option value="">-- Select Admission Type --</option>
                                    <option value="Emergency"
                                        <?=($patient['admission_type'] == 'Emergency') ? 'selected': ''; ?>>Emergency
                                    </option>
                                    <option value="Planned"
                                        <?=($patient['admission_type'] == 'Planned') ? 'selected': ''; ?>>Planned
                                    </option>
                                    <option value="Elective"
                                        <?=($patient['admission_type'] == 'Elective') ? 'selected': ''; ?>>Elective
                                    </option>
                                    <option value="Day Case"
                                        <?=($patient['admission_type'] == 'Day Case') ? 'selected': ''; ?>>Day Case
                                    </option>
                                    <option value="Maternity"
                                        <?=($patient['admission_type'] == 'Maternity') ? 'selected': ''; ?>>Maternity
                                    </option>
                                    <option value="Outpatient"
                                        <?=($patient['admission_type'] == 'Outpatient') ? 'selected': ''; ?>>Outpatient
                                    </option>
                                    <option value="Observation"
                                        <?=($patient['admission_type'] == 'Observation') ? 'selected': ''; ?>>
                                        Observation
                                    </option>
                                </select>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <label class="col-sm-3 col-form-label">Attending Doctor</label>
                            <div class="col-sm-6">
                                <input type="number" class="form-control" name="attending_doctor"
                                    value="<?= $patient['attending_doctor']; ?>" readonly>
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
                                <input type="text" class="form-control" name="condition_name"
                                    value="<?= $patient['condition_name']; ?>">
                            </div>
                        </div>

                        <div class="row mb-3">
                            <label class="col-sm-3 col-form-label">Diagnosis Date</label>
                            <div class="col-sm-9">
                                <input type="date" class="form-control" name="diagnosis_date"
                                    value="<?= $patient['diagnosis_date']; ?>">
                            </div>
                        </div>

                        <div class="row mb-3">
                            <label class="col-sm-3 col-form-label">Notes</label>
                            <div class="col-sm-9">
                                <input type="text" class="form-control" name="notes" value="<?= $patient['notes']; ?>">
                            </div>
                        </div>
                    </div>

                    <div class="modal-footer">
                        <button type="button" id="prevBtn" class="btn btn-secondary d-none mx-1">Back</button>
                        <button type="button" id="nextBtn" class="btn btn-primary m-3">Next</button>
                        <button type="submit" id="submitBtn" class="btn btn-success d-none mx-3">Submit</button>
                        <a href="inpatient.php" class="btn btn-danger">Cancel</a>
                    </div>
                </form>

            </div>
            <script>
            const toggler = document.querySelector(".toggler-btn");
            toggler.addEventListener("click", function() {
                document.querySelector("#sidebar").classList.toggle("collapsed");
            });
            </script>
            <script src="assets/Bootstrap/all.min.js"></script>
            <script src="assets/Bootstrap/bootstrap.bundle.min.js"></script>
            <script src="assets/Bootstrap/fontawesome.min.js"></script>
            <script src="assets/Bootstrap/jq.js"></script>

            <script>
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