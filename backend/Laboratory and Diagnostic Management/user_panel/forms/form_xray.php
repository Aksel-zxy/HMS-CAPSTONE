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
    <title>X-ray Test Form</title>
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
                X-ray (Chest) Test
            </div>
            <div class="card-body">
                <div class="mb-4">
                    <strong>Schedule ID:</strong> <?= htmlspecialchars($patient['scheduleID']) ?><br>
                    <strong>Patient:</strong> <?= htmlspecialchars($patient['fname'] . ' ' . $patient['lname']) ?><br>
                    <strong>Test:</strong> <?= htmlspecialchars($patient['serviceName']) ?><br>
                    <strong>Schedule:</strong> <?= htmlspecialchars($patient['scheduleDate']) ?> at <?= htmlspecialchars($patient['scheduleTime']) ?>
                </div>

                <form method="POST" action="forms/results.php" enctype="multipart/form-data">
                    <input type="hidden" name="testType" value="X-ray">
                    <input type="hidden" name="scheduleID" value="<?= $scheduleID ?>">
                    <input type="hidden" name="patientID" value="<?= $patient['patientID'] ?>">

                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label class="form-label">Findings</label>
                            <textarea class="form-control" name="findings" rows="5" required></textarea>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Impression</label>
                            <textarea class="form-control" name="impression" rows="5" required></textarea>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Remarks</label>
                            <textarea class="form-control" name="remarks" rows="5"></textarea>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Upload X-ray Image</label>
                        <input class="form-control" type="file" name="xray_image" accept="image/*" required>
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
