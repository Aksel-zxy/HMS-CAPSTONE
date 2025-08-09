<?php
include '../../SQL/config.php';
require_once 'class/patient.php';

$patientObj = new Patient($conn);


$patient_id = $_GET['patient_id'];
$patient = $patientObj->getPatientById($patient_id);


if ( $_SERVER['REQUEST_METHOD'] == 'POST') {
    $updatedData = [
    'fname' => $_POST["fname"],
    'mname' => $_POST["mname"],
    'lname' => $_POST["lname"],
    'address' => $_POST["address"],
    'age' => $_POST["age"],
    'dob' => $_POST["dob"],
    'gender' => $_POST["gender"],
    'civil_status' => $_POST["civil_status"],
    'phone_number' => $_POST["phone_number"],
    'email' => $_POST["email"],
    'admission_type' => $_POST["admission_type"],
    'bed_number' => $_POST["bed_number"],
    ];

    $result = $patientObj->updatePatient($patient_id,$updatedData);
   

        if($result){
             header("location:../Patient Management/inpatient.php");
             exit;    
        } else {
            echo "Failed to update Patient.";
        }
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
    <link rel="stylesheet" href="assets/CSS/iedit.css">
</head>

<body>
    <div class="container">
        <div class="card shadow">
            <div class="card-header bg-primary txt-white">
                <h4 class="mb-0">Edit Details</h4>
            </div>
            <div class="card-body">


                <form method="post">
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
                            <input type="text" class="form-control" name="address" value="<?= $patient['address']; ?>">
                        </div>
                    </div>
                    <div class="row mb-3">
                        <label class="col-sm-3 col-form-label">Date of Birth</label>
                        <div class="col-sm-6">
                            <input type="date" class="form-control" id="dob" name="dob" value="<?= $patient['dob']; ?>">
                        </div>
                    </div>
                    <div class="row mb-3">
                        <label class="col-sm-3 col-form-label">Age</label>
                        <div class="col-sm-6">
                            <input type="age" class="form-control" id="age" name="age" value="<?= $patient['age']; ?>"
                                readonly>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <label class="col-sm-3 col-form-label">Gender</label>
                        <div class="col-sm-6">
                            <select class="form-select" name="gender" required>
                                <option value="">-- Select Gender --</option>
                                <option value="Male" <?= ($patient['gender'] == 'Male') ? 'selected' : ''; ?>>Male
                                </option>
                                <option value="Female" <?= ($patient['gender'] == 'Female') ? 'selected' : ''; ?>>Female
                                </option>
                            </select>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <label class="col-sm-3 col-form-label">Civil Status</label>
                        <div class="col-sm-6">
                            <select class="form-select" name="civil_status" required>
                                <option value="">-- Select Civil Status --</option>
                                <option value="Single" <?=($patient['civil_status'] == 'Single') ? 'selected' : ''; ?>>
                                    Single</option>
                                <option value="Married"
                                    <?=($patient['civil_status'] == 'Married') ? 'selected' : ''; ?>>Married</option>
                                <option value="Divorced"
                                    <?=($patient['civil_status'] == 'Divorced') ? 'selected' : ''; ?>>Divorced</option>
                                <option value="Widowed"
                                    <?=($patient['civil_status'] == 'Widowed') ? 'selected' : ''; ?>>Widowed</option>
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
                                    <?=($patient['admission_type'] == 'Planned') ? 'selected': ''; ?>>Planned</option>
                                <option value="Elective"
                                    <?=($patient['admission_type'] == 'Elective') ? 'selected': ''; ?>>Elective</option>
                                <option value="Day Case"
                                    <?=($patient['admission_type'] == 'Day Case') ? 'selected': ''; ?>>Day Case</option>
                                <option value="Maternity"
                                    <?=($patient['admission_type'] == 'Maternity') ? 'selected': ''; ?>>Maternity
                                </option>
                                <option value="Outpatient"
                                    <?=($patient['admission_type'] == 'Outpatient') ? 'selected': ''; ?>>Outpatient
                                </option>
                                <option value="Observation"
                                    <?=($patient['admission_type'] == 'Observation') ? 'selected': ''; ?>>Observation
                                </option>
                            </select>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <label class="col-sm-3 col-form-label">Bed Number</label>
                        <div class="col-sm-6">
                            <input type="number" class="form-control" name="bed_number"
                                value="<?= $patient['bed_number']; ?>">
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="offset-sm-3 col-sm-3 d-grid">
                            <button type="submit" class="btn btn-primary">Submit</button>
                        </div>
                        <div class="col-sm-3 d-grid">
                            <a class="btn btn-outline-primary" href="../Patient Management/inpatient.php"
                                role="button">Cancel</a>
                        </div>
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
            </script>
</body>

</html>