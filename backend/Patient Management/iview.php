<?php
include '../../SQL/config.php';
require_once 'class/patient.php';

$patientObj = new Patient($conn);

try {
    $patient_id = $_GET['patient_id'] ?? null;
    $patient = $patientObj->getPatientOrFail($patient_id);

    
} catch (Exception $e) {
    echo $e->getMessage();
    exit;
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
    <link rel="stylesheet" href="assets/CSS/iview.css">
</head>

<body>
    <div class="container mt-4">
        <div class="card shadow">
            <h4 class="mb-0">View Inpatient Details</h4>
            <div class="card-body">

                <p><strong>Name:</strong>
                    <?=  htmlspecialchars($patient['fname'] . ' ' . $patient['mname'] . ' ' . $patient['lname']) ?></p>
                <p><strong>Address:</strong> <?= htmlspecialchars($patient['address']) ?></p>
                <p><strong>Date of Birth:</strong> <?= htmlspecialchars($patient['dob']) ?></p>
                <p><strong>Age:</strong> <?= htmlspecialchars($patient['age']) ?></p>
                <p><strong>Gender:</strong> <?= htmlspecialchars($patient['gender']) ?></p>
                <p><strong>Civil Status:</strong> <?= htmlspecialchars($patient['civil_status']) ?></p>
                <p><strong>Contact Number:</strong> <?= htmlspecialchars($patient['phone_number']) ?></p>
                <p><strong>Email:</strong> <?= htmlspecialchars($patient['email']) ?></p>
                <p><strong>Admission Type:</strong> <?= htmlspecialchars($patient['admission_type']) ?></p>

                <p><strong>Attending Doctor:</strong> <?= htmlspecialchars($patient['attending_doctor']) ?></p>
                <?php
                // Fetch admission date and condition name from another table, e.g., 'admissions'
                $admission = null;
                if ($patient_id) {
                    $stmt = $conn->prepare("SELECT  condition_name, diagnosis_date, notes FROM p_previous_medical_history WHERE patient_id = ?");
                    $stmt->bind_param("i", $patient_id);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $admission = $result->fetch_assoc();
                    $stmt->close();
                }
                ?>


                <p><strong>Condition Name:</strong> <?= htmlspecialchars($admission['condition_name'] ?? 'N/A') ?></p>
                <p><strong>Diagnosis Date:</strong> <?= htmlspecialchars($admission['diagnosis_date'] ?? 'N/A') ?></p>
                <p><strong>Notes: </strong><?= htmlspecialchars($admission['notes'] ?? 'N/A')?></p>
                <a href="inpatient.php" class="btn btn-secondary">Back</a>
            </div>
        </div>
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