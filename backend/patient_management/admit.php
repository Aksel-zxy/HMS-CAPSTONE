<?php
session_start();
include '../../SQL/config.php';
require_once 'class/caller.php';
include 'class/logs.php';

if (!isset($_GET['patient_id']) || empty($_GET['patient_id'])) {
    echo "Invalid patient ID.";
    exit();
}

$patient_id = intval($_GET['patient_id']);
$admission = new PatientAdmission($conn);

//  Fetch patient
$patient = $admission->getPatient($patient_id);
if (!$patient) {
    echo "Patient not found.";
    exit();
}

//  Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $bed_id = $_POST['bed_id'];
    $assigned_date = $_POST['assigned_date'];
    $admission_type = $_POST['admission_type'];
    $admission->admit($patient_id, $bed_id, $assigned_date, $admission_type);
}

$user_id = $_SESSION['user_id'];
logAction($conn, $user_id, 'Patient_Admitted', $patient_id);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admit Patient</title>
    <link rel="stylesheet" href="assets/CSS/bootstrap.min.css">
</head>

<body class="container mt-4">

    <h2>Admit Patient: <?= htmlspecialchars($patient['fname'] . " " . $patient['lname']) ?></h2>
    <p><strong>Patient ID:</strong> <?= $patient['patient_id'] ?></p>
    <p><strong>Address:</strong> <?= $patient['address'] ?></p>
    <p><strong>Gender:</strong> <?= $patient['gender'] ?></p>

    <form method="POST" class="mt-3">

        <div class="mb-3 row">
            <label class="col-sm-3 col-form-label">Select Bed</label>
            <div class="col-sm-9">
                <select name="bed_id" class="form-select" required>
                    <option value="">-- Choose Available Bed --</option>
                    <?php
                $beds = $admission->getAvailableBeds();
                while ($bed = $beds->fetch_assoc()) {
                    echo "<option value='{$bed['bed_id']}'>{$bed['bed_number']}</option>";
                }
                ?>
                </select>
            </div>
        </div>

        <div class="mb-3 row">
            <label class="col-sm-3 col-form-label">Admission Date</label>
            <div class="col-sm-9">
                <input type="date" name="assigned_date" class="form-control" required>
            </div>
        </div>

        <!--  Admission Type Dropdown -->
        <div class="mb-3 row">
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

        <button type="submit" class="btn btn-success">Admit Patient</button>
        <a href="inpatient.php" class="btn btn-secondary">Cancel</a>
    </form>


</body>

</html>