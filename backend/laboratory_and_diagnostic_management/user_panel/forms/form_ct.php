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
    <title>CT Scan Form</title>
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
<body class="container mt-4">

    <h3>CT Scan Test</h3>

    <div class="card p-3 mb-4">
        <p><strong>Schedule ID:</strong> <?= htmlspecialchars($patient['scheduleID']) ?></p>
        <p><strong>Patient:</strong> <?= htmlspecialchars($patient['fname'] . ' ' . $patient['lname']) ?></p>
        <p><strong>Test:</strong> <?= htmlspecialchars($patient['serviceName']) ?></p>
        <p><strong>Schedule Date:</strong> <?= htmlspecialchars($patient['scheduleDate']) ?> <?= htmlspecialchars($patient['scheduleTime']) ?></p>
    </div>

    <form method="POST" action="forms/results.php" enctype="multipart/form-data">
        <input type="hidden" name="testType" value="CT">
        <input type="hidden" name="scheduleID" value="<?= $patient['scheduleID'] ?>">
        <input type="hidden" name="patientID" value="<?= $patient['patientID'] ?>">

        <div class="mb-3">
            <label>Findings</label>
            <textarea class="form-control" name="findings" required></textarea>
        </div>
        <div class="mb-3">
            <label>Impression</label>
            <textarea class="form-control" name="impression" required></textarea>
        </div>
        <div class="mb-3">
            <label>Remarks</label>
            <textarea class="form-control" name="remarks"></textarea>
        </div>
        <div class="mb-3">
            <label>Upload CT Scan Image</label>
            <input type="file" class="form-control" name="ct_image" accept="image/*">
        </div>
        <button type="submit" class="btn btn-primary">Save Result</button>
    </form>

</body>
</html>
