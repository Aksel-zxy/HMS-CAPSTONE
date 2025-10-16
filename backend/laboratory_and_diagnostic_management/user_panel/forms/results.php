<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

include __DIR__ . "/../../../../SQL/config.php";

/* ==============================
   FUNCTION: Update Schedule Status
============================== */
function markScheduleCompleted($conn, $scheduleID)
{
    $update = $conn->prepare("UPDATE dl_schedule SET status = 'Completed', completed_at = NOW() WHERE scheduleID = ?");
    $update->bind_param("i", $scheduleID);
    $update->execute();
    $update->close();
}

/* ==============================
   FUNCTION: Read Image as Blob
============================== */
function getImageBlob($fileInputName)
{
    if (!empty($_FILES[$fileInputName]['tmp_name'])) {
        return file_get_contents($_FILES[$fileInputName]['tmp_name']);
    }
    return null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $scheduleID = $_POST['scheduleID'] ?? null;
    $patientID  = $_POST['patientID'] ?? null;
    $testType   = $_POST['testType'] ?? null;

    if (!$scheduleID || !$patientID || !$testType) {
        die("Missing required data.");
    }

    /* ==============================
       CBC RESULT
    ============================== */
    if ($testType === "CBC") {
        $wbc        = $_POST['wbc'];
        $rbc        = $_POST['rbc'];
        $hemoglobin = $_POST['hemoglobin'];
        $hematocrit = $_POST['hematocrit'];
        $platelets  = $_POST['platelets'];
        $mcv        = $_POST['mcv'];
        $mch        = $_POST['mch'];
        $mchc       = $_POST['mchc'];
        $remarks    = $_POST['remarks'];

        $query = "INSERT INTO dl_lab_cbc
                  (scheduleID, patientID, testType, wbc, rbc, hemoglobin, hematocrit, 
                   platelets, mcv, mch, mchc, remarks, created_at) 
                  VALUES (?, ?, 'CBC', ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";

        $stmt = $conn->prepare($query);
        $stmt->bind_param(
            "iisssssssss",
            $scheduleID,
            $patientID,
            $wbc,
            $rbc,
            $hemoglobin,
            $hematocrit,
            $platelets,
            $mcv,
            $mch,
            $mchc,
            $remarks
        );

        if ($stmt->execute()) {
            markScheduleCompleted($conn, $scheduleID);
            echo "<script>alert('CBC result saved successfully!'); window.location.href='../sample_processing.php';</script>";
        } else {
            echo "Error executing query: " . $stmt->error;
        }
        $stmt->close();
    }

    /* ==============================
       IMAGE-BASED TEST (X-ray, MRI, CT)
    ============================== */
    elseif (in_array($testType, ['X-ray', 'MRI', 'CT'])) {
        $findings   = $_POST['findings'];
        $impression = $_POST['impression'];
        $remarks    = $_POST['remarks'];

        // Decide image input name
        $fileInputName = strtolower(str_replace('-', '', $testType)) . '_image'; // e.g., xray_image, mri_image, ct_image
        $imageBlob = getImageBlob($fileInputName);

        // Decide which table
        $tableMap = [
            'X-ray' => 'dl_lab_xray',
            'MRI'   => 'dl_lab_mri',
            'CT'    => 'dl_lab_ct'
        ];
        $table = $tableMap[$testType];

        $query = "INSERT INTO $table 
                  (scheduleID, patientID, testType, findings, impression, remarks, image_blob, created_at)
                  VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";

        $stmt = $conn->prepare($query);
        $null = NULL; // for binding blob

        // bind all parameters
        $stmt->bind_param("iissssb", $scheduleID, $patientID, $testType, $findings, $impression, $remarks, $null);

        // send blob data separately
        if ($imageBlob !== null) {
            $stmt->send_long_data(6, $imageBlob); // index 6 = 7th parameter (image_blob)
        }

        if ($stmt->execute()) {
            markScheduleCompleted($conn, $scheduleID);
            echo "<script>alert('$testType result saved successfully!'); window.location.href='../sample_processing.php';</script>";
        } else {
            echo "Error executing query: " . $stmt->error;
        }

        $stmt->close();
    }

    else {
        echo "Unknown test type.";
    }
} else {
    echo "Invalid access method.";
}
?>
