<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

include __DIR__ . "/../../../../SQL/config.php";

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

        $stmt = $conn->prepare($query) or die("Prepare failed: " . $conn->error);

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
            echo "<script>alert('CBC result saved successfully!'); window.location.href='../sample_processing.php';</script>";
        } else {
            echo "Error executing query: " . $stmt->error;
        }
    }

    /* ==============================
       X-RAY RESULT
    ============================== */
    elseif ($testType === "X-ray") {
        $findings   = $_POST['findings'];
        $impression = $_POST['impression'];
        $remarks    = $_POST['remarks'];

        $imagePath = "";
        if (!empty($_FILES['xray_image']['name'])) {
            $uploadDir = __DIR__ . "/../uploads/xray/";
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

            $fileName   = time() . "_" . basename($_FILES['xray_image']['name']);
            $targetPath = $uploadDir . $fileName;

            if (move_uploaded_file($_FILES['xray_image']['tmp_name'], $targetPath)) {
                $imagePath = "/HMS-CAPSTONE/backend/Laboratory and Diagnostic Management/user_panel/uploads/xray/" . $fileName;
            } else {
                die("Failed to upload X-ray image. PHP Error: " . $_FILES['xray_image']['error']);
            }
        }

        $query = "INSERT INTO dl_lab_xray
                  (scheduleID, patientID, testType, findings, impression, remarks, image_path, created_at) 
                  VALUES (?, ?, 'X-ray', ?, ?, ?, ?, NOW())";

        $stmt = $conn->prepare($query) or die("Prepare failed: " . $conn->error);
        $stmt->bind_param("iissss", $scheduleID, $patientID, $findings, $impression, $remarks, $imagePath);

        if ($stmt->execute()) {
            echo "<script>alert('X-ray result saved successfully!'); window.location.href='../sample_processing.php';</script>";
        } else {
            echo "Error: " . $stmt->error;
        }
    }

    /* ==============================
       MRI RESULT
    ============================== */
    elseif ($testType === "MRI") {
        $findings   = $_POST['findings'];
        $impression = $_POST['impression'];
        $remarks    = $_POST['remarks'];

        $imagePath = "";
        if (!empty($_FILES['mri_image']['name'])) {
            $uploadDir = __DIR__ . "/../uploads/mri/";
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

            $fileName   = time() . "_" . basename($_FILES['mri_image']['name']);
            $targetPath = $uploadDir . $fileName;

            if (move_uploaded_file($_FILES['mri_image']['tmp_name'], $targetPath)) {
                $imagePath = "/HMS-CAPSTONE/backend/Laboratory and Diagnostic Management/user_panel/uploads/mri/" . $fileName;
            } else {
                die("Failed to upload MRI image. PHP Error: " . $_FILES['mri_image']['error']);
            }
        }

        $query = "INSERT INTO dl_lab_mri
                  (scheduleID, patientID, testType, findings, impression, remarks, image_path, created_at) 
                  VALUES (?, ?, 'MRI', ?, ?, ?, ?, NOW())";

        $stmt = $conn->prepare($query) or die("Prepare failed: " . $conn->error);
        $stmt->bind_param("iissss", $scheduleID, $patientID, $findings, $impression, $remarks, $imagePath);

        if ($stmt->execute()) {
            echo "<script>alert('MRI result saved successfully!'); window.location.href='../sample_processing.php';</script>";
        } else {
            echo "Error: " . $stmt->error;
        }
    }

    /* ==============================
       CT SCAN RESULT
    ============================== */
    elseif ($testType === "CT") {
        $findings   = $_POST['findings'];
        $impression = $_POST['impression'];
        $remarks    = $_POST['remarks'];

        $imagePath = "";
        if (!empty($_FILES['ct_image']['name'])) {
            $uploadDir = __DIR__ . "/../uploads/ct/";
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

            $fileName   = time() . "_" . basename($_FILES['ct_image']['name']);
            $targetPath = $uploadDir . $fileName;

            if (move_uploaded_file($_FILES['ct_image']['tmp_name'], $targetPath)) {
                $imagePath = "/HMS-CAPSTONE/backend/Laboratory and Diagnostic Management/user_panel/uploads/ct/" . $fileName;
            } else {
                die("Failed to upload CT image. PHP Error: " . $_FILES['ct_image']['error']);
            }
        }

        $query = "INSERT INTO dl_lab_ct
                  (scheduleID, patientID, testType, findings, impression, remarks, image_path, created_at) 
                  VALUES (?, ?, 'CT', ?, ?, ?, ?, NOW())";

        $stmt = $conn->prepare($query) or die("Prepare failed: " . $conn->error);
        $stmt->bind_param("iissss", $scheduleID, $patientID, $findings, $impression, $remarks, $imagePath);

        if ($stmt->execute()) {
            echo "<script>alert('CT Scan result saved successfully!'); window.location.href='../sample_processing.php';</script>";
        } else {
            echo "Error: " . $stmt->error;
        }
    }

    else {
        echo "Unknown test type.";
    }
} else {
    echo "Invalid access method.";
}
?>
