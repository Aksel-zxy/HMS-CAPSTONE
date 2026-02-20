<?php
if (!isset($conn)) {
    include __DIR__ . "/../../../../SQL/config.php";
}

$scheduleID = $_GET['scheduleID'] ?? null;

if (!$scheduleID) {
    echo "Invalid request.";
    exit();
}


$query = "SELECT s.scheduleID, s.patientID, s.serviceName, s.scheduleDate, s.scheduleTime,
                 p.fname, p.lname
          FROM dl_schedule s
          JOIN patientinfo p ON s.patientID = p.patient_id
          WHERE s.scheduleID = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $scheduleID);
$stmt->execute();
$result = $stmt->get_result();
$patient = $result->fetch_assoc();

if (!$patient) {
    echo "Schedule not found.";
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>CBC Test Form</title>
    <link rel="stylesheet" href="../assets/CSS/bootstrap.min.css">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .card-header {
            background-color: #007bff;
            color: white;
            font-weight: bold;
        }
        .form-label {
            font-weight: 500;
        }
        .btn-save {
            background-color: #007bff;
            color: white;
        }
        .btn-save:hover {
            background-color: #0056b3;
        }
        textarea.form-control {
            resize: vertical;
        }
    </style>
</head>

<body>
    <div class="container mt-5">
        <div class="card shadow-sm">
            <div class="card-header">
                Complete Blood Count (CBC) Test
            </div>
            <div class="card-body">
                <div class="mb-4">
                    <strong>Schedule ID:</strong> <?= htmlspecialchars($patient['scheduleID']) ?><br>
                    <strong>Patient:</strong> <?= htmlspecialchars($patient['fname'] . ' ' . $patient['lname']) ?><br>
                    <strong>Test:</strong> <?= htmlspecialchars($patient['serviceName']) ?><br>
                    <strong>Schedule:</strong> <?= htmlspecialchars($patient['scheduleDate']) ?> at <?= htmlspecialchars($patient['scheduleTime']) ?>
                </div>

                <form method="POST" action="forms/results.php">
                    <input type="hidden" name="scheduleID" value="<?= $patient['scheduleID'] ?>">
                    <input type="hidden" name="patientID" value="<?= $patient['patientID'] ?>">
                    <input type="hidden" name="testType" value="CBC">

                    <div class="row g-3 mb-3">
                        <div class="col">
                            <label class="form-label">WBC (x10^9/L)</label>
                            <input type="text" name="wbc" class="form-control" required>
                        </div>
                        <div class="col">
                            <label class="form-label">RBC (x10^12/L)</label>
                            <input type="text" name="rbc" class="form-control" required>
                        </div>
                        <div class="col">
                            <label class="form-label">Hemoglobin (g/dL)</label>
                            <input type="text" name="hemoglobin" class="form-control" required>
                        </div>
                        <div class="col">
                            <label class="form-label">Hematocrit (%)</label>
                            <input type="text" name="hematocrit" class="form-control">
                        </div>
                        <div class="col">
                            <label class="form-label">Platelets (x10^9/L)</label>
                            <input type="text" name="platelets" class="form-control">
                        </div>
                        <div class="col">
                            <label class="form-label">MCV (fL)</label>
                            <input type="text" name="mcv" class="form-control">
                        </div>
                        <div class="col">
                            <label class="form-label">MCH (pg)</label>
                            <input type="text" name="mch" class="form-control">
                        </div>
                        <div class="col">
                            <label class="form-label">MCHC (g/dL)</label>
                            <input type="text" name="mchc" class="form-control">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Remarks</label>
                        <textarea name="remarks" class="form-control" rows="2"></textarea>
                    </div>

                    <button type="submit" class="btn btn-save">Save Result</button>
                    <a href="sample_processing.php" class="btn btn-secondary">Cancel</a>
                </form>
            </div>
        </div>
    </div>

    <script src="../../assets/Bootstrap/bootstrap.bundle.min.js"></script>
</body>

</html>
