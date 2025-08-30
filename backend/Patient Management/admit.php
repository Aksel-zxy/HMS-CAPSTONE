<?php
session_start();
include '../../SQL/config.php';
require_once 'class/caller.php';


if (!isset($_GET['patient_id']) || empty($_GET['patient_id'])) {
    echo "Invalid patient ID.";
    exit();
}

$patient_id = intval($_GET['patient_id']);
$admission = new PatientAdmission($conn);

// ✅ Fetch patient
$patient = $admission->getPatient($patient_id);
if (!$patient) {
    echo "Patient not found.";
    exit();
}

// ✅ Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $bed_id = $_POST['bed_id'];
    $assigned_date = $_POST['assigned_date'];
    $admission->admitPatient($patient_id, $bed_id, $assigned_date);
}
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

        <div class="mb-3">
            <label class="form-label">Select Bed</label>
            <select name="bed_id" class="form-control" required>
                <option value="">-- Choose Available Bed --</option>
                <?php
                $beds = $admission->getAvailableBeds();
                while ($bed = $beds->fetch_assoc()) {
                    echo "<option value='{$bed['bed_id']}'>{$bed['bed_number']}</option>";
                }
                ?>
            </select>
        </div>

        <div class="mb-3">
            <label class="form-label">Admission Date</label>
            <input type="date" name="assigned_date" class="form-control" required>
        </div>

        <button type="submit" class="btn btn-success">Admit Patient</button>
        <a href="inpatient.php" class="btn btn-secondary">Cancel</a>
    </form>

</body>

</html>