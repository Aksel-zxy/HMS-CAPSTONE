<?php
session_start();
include '../../SQL/config.php';

require_once 'class/caller.php';

//  Ensure patient_id is passed
if (!isset($_GET['patient_id']) || empty($_GET['patient_id'])) {
    echo "Invalid patient ID.";
    exit();
}

$patient_id = intval($_GET['patient_id']);
$discharge = new PatientDischarge($conn);

//  Get active admission
$assignment = $discharge->getActiveAdmission($patient_id);
if (!$assignment) {
    echo "This patient is not currently admitted.";
    exit();
}

$bed_id = $assignment['bed_id'];

//  Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $released_date = $_POST['released_date'];
    $discharge->discharge($assignment['assignment_id'], $bed_id, $patient_id, $released_date);
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Discharge Patient</title>
    <link rel="stylesheet" href="assets/CSS/bootstrap.min.css">
</head>

<body class="container mt-4">

    <h2>Discharge Patient: <?= htmlspecialchars($patient_id) ?></h2>
    <p><strong>Bed ID:</strong> <?= htmlspecialchars($bed_id) ?></p>
    <p><strong>Admitted on:</strong> <?= htmlspecialchars($assignment['assigned_date']) ?></p>

    <form method="POST" class="mt-3">
        <div class="mb-3">
            <label class="form-label">Discharge Date</label>
            <input type="date" name="released_date" class="form-control" required>
        </div>

        <button type="submit" class="btn btn-danger">Discharge</button>
        <a href="inpatient.php" class="btn btn-secondary">Cancel</a>
    </form>

</body>

</html>