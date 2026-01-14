<?php
session_start();
include '../../SQL/config.php';
require_once 'class/caller.php';
include 'class/logs.php';
include 'los_predictor.php'; 
if (!isset($_GET['patient_id']) || empty($_GET['patient_id'])) {
    echo "Invalid patient ID.";
    exit();
}

$patient_id = intval($_GET['patient_id']);
$admission = new PatientAdmission($conn);

// Fetch patient
$patient = $admission->getPatient($patient_id);
if (!$patient) {
echo "Patient not found.";
exit();
}

$comorbidity_count = getComorbidityCount($conn, $patient_id);

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
$bed_id = $_POST['bed_id'];
$assigned_date = $_POST['assigned_date'];
$admission_type = $_POST['admission_type'];
$severity = $_POST['severity'] ?? 3;
$predicted_los = $_POST['predicted_los'];

$predicted_los = predictLoS(
        $patient['age'],
        $severity,
        $comorbidity_count
    );
$admission->admit($patient_id, $bed_id, $assigned_date, $admission_type, $severity, $predicted_los);
}

$severity = $_POST['severity'] ?? 3;
$predicted_los = predictLoS(
    $patient['age'],
    $severity,
    $comorbidity_count
);

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

        <div class="mb-3 row">
            <label class="col-sm-3 col-form-label">Severity Level</label>
            <div class="col-sm-9">
                <select name="severity" class="form-control" required>
                    <option value="1" <?= ($severity == 1) ? 'selected' : '' ?>>Mild</option>
                    <option value="3" <?= ($severity == 3) ? 'selected' : '' ?>>Moderate</option>
                    <option value="5" <?= ($severity== 5) ? 'selected' : '' ?>>Severe</option>
                </select>
            </div>
        </div>

        <!-- Predicted Length of Stay -->

        <div class="alert alert-info justify-content-center" role="alert">
            <strong>Predicted Length of Stay:</strong>
            <span id="predicted_los_text"><?= $predicted_los ?></span> days
            <span id="los_badge"
                class="badge <?= $predicted_los >= 7 ? 'bg-danger' : ($predicted_los >= 4 ? 'bg-warning' : 'bg-success') ?>">
                <?= $predicted_los >= 7 ? 'Long Stay Risk' : ($predicted_los >= 4 ? 'Moderate Stay' : 'Short Stay') ?>
            </span>
        </div>

        <!-- Hidden input so the value is submitted -->
        <input type="hidden" name="predicted_los" id="predicted_los_input" value="<?= $predicted_los ?>">


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

    <script>
    const severitySelect = document.querySelector('select[name="severity"]');
    const losText = document.getElementById('predicted_los_text'); // shows the days
    const losBadge = document.getElementById('los_badge'); // badge span
    const hiddenInput = document.getElementById('predicted_los_input');

    const age = <?= $patient['age'] ?>;
    const comorbidity = <?= $comorbidity_count ?>;

    severitySelect.addEventListener('change', () => {
        const severity = parseInt(severitySelect.value);

        // Recalculate predicted LoS
        const predicted = Math.max(1, Math.round(0.05 * age + 1.3 * severity + 0.85 * comorbidity + 1.7));

        // Update display
        losText.textContent = predicted;

        // Update badge
        if (predicted >= 7) {
            losBadge.textContent = 'Long Stay Risk';
            losBadge.className = 'badge bg-danger';
        } else if (predicted >= 4) {
            losBadge.textContent = 'Moderate Stay';
            losBadge.className = 'badge bg-warning';
        } else {
            losBadge.textContent = 'Short Stay';
            losBadge.className = 'badge bg-success';
        }

        // Update hidden input so correct value is submitted
        hiddenInput.value = predicted;
    });
    </script>

</body>

</html>