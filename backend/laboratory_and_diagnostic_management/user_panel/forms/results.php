<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . "/../../../../SQL/config.php";
session_start();
$employee_id = $_SESSION['employee_id'] ?? null;


function markScheduleCompleted($conn, $scheduleID)
{
    
    $stmt = $conn->prepare("
        SELECT room_id 
        FROM dl_schedule 
        WHERE scheduleID = ?
        LIMIT 1
    ");
    $stmt->bind_param("i", $scheduleID);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $roomID = $result['room_id'] ?? null;

    
    $updateSchedule = $conn->prepare("
        UPDATE dl_schedule 
        SET status = 'Completed', completed_at = NOW() 
        WHERE scheduleID = ?
    ");
    $updateSchedule->bind_param("i", $scheduleID);
    $updateSchedule->execute();
    $updateSchedule->close();

    
    if ($roomID) {
        $freeRoom = $conn->prepare("
            UPDATE rooms 
            SET status = 'Available'
            WHERE roomID = ?
        ");
        $freeRoom->bind_param("i", $roomID);
        $freeRoom->execute();
        $freeRoom->close();
    }
}


function saveToolsUsed($conn, $scheduleID, $patientID) {
    if (!empty($_POST['tool_id']) && is_array($_POST['tool_id'])) {
        $tool_ids = $_POST['tool_id'];
        $tool_names = $_POST['tool_name'] ?? [];
        $tool_prices = $_POST['tool_price'] ?? [];
        $tool_qtys = $_POST['tool_qty'] ?? [];

        $stmtInv = $conn->prepare("INSERT INTO dl_lab_tools_used (scheduleID, patientID, item_id, item_name, quantity, price, item_type) VALUES (?, ?, ?, ?, ?, ?, 'Inventory')");
        $updateInv = $conn->prepare("UPDATE inventory SET quantity = quantity - ? WHERE item_id = ?");
        
        $stmtMac = $conn->prepare("INSERT INTO dl_lab_tools_used (scheduleID, patientID, item_id, item_name, quantity, price, item_type) VALUES (?, ?, ?, ?, 1, 0, 'Equipment')");

        for ($i = 0; $i < count($tool_ids); $i++) {
            $raw_id = $tool_ids[$i];
            if (strpos($raw_id, 'inv_') === 0) {
                $id = intval(substr($raw_id, 4));
                $qty = intval($tool_qtys[$i] ?? 0);
                if ($id > 0 && $qty > 0) {
                    $name = $tool_names[$i] ?? '';
                    $price = floatval($tool_prices[$i] ?? 0);
                    
                    $stmtInv->bind_param("iiisid", $scheduleID, $patientID, $id, $name, $qty, $price);
                    $stmtInv->execute();

                    $updateInv->bind_param("ii", $qty, $id);
                    $updateInv->execute();
                }
            } elseif (strpos($raw_id, 'mac_') === 0) {
                $id = intval(substr($raw_id, 4));
                if ($id > 0) {
                    $name = $tool_names[$i] ?? '';
                    $stmtMac->bind_param("iiis", $scheduleID, $patientID, $id, $name);
                    $stmtMac->execute();
                }
            }
        }
        if ($stmtInv) $stmtInv->close();
        if ($updateInv) $updateInv->close();
        if ($stmtMac) $stmtMac->close();
    }
}

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

        $query = "
            INSERT INTO dl_lab_cbc
            (scheduleID, patientID, testType, wbc, rbc, hemoglobin, hematocrit, 
             platelets, mcv, mch, mchc, remarks, processed_by, created_at)
            VALUES (?, ?, 'CBC', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ";

        $stmt = $conn->prepare($query);
        $stmt->bind_param(
            "iissssssssssi",
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
            $remarks,
            $employee_id
        );

        if ($stmt->execute()) {
            saveToolsUsed($conn, $scheduleID, $patientID);
            markScheduleCompleted($conn, $scheduleID);
            echo "<script>alert('CBC result saved successfully!'); window.location.href='../sample_processing.php';</script>";
        } else {
            echo "Error: " . $stmt->error;
        }

        $stmt->close();
    }

    
    elseif (in_array($testType, ['X-ray', 'MRI', 'CT'])) {

        $findings   = $_POST['findings'];
        $impression = $_POST['impression'];
        $remarks    = $_POST['remarks'];

        
        $fileInputName = strtolower(str_replace('-', '', $testType)) . '_image';
        $imageBlob = getImageBlob($fileInputName);

        
        $tableMap = [
            'X-ray' => 'dl_lab_xray',
            'MRI'   => 'dl_lab_mri',
            'CT'    => 'dl_lab_ct'
        ];

        $table = $tableMap[$testType];

        $query = "
            INSERT INTO $table
            (scheduleID, patientID, testType, findings, impression, remarks, image_blob, processed_by, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ";

        $stmt = $conn->prepare($query);
        $null = NULL;

        $stmt->bind_param(
            "iissssbi",
            $scheduleID,
            $patientID,
            $testType,
            $findings,
            $impression,
            $remarks,
            $null,
            $employee_id
        );

        if ($imageBlob !== null) {
            $stmt->send_long_data(6, $imageBlob);
        }

        if ($stmt->execute()) {
            saveToolsUsed($conn, $scheduleID, $patientID);
            markScheduleCompleted($conn, $scheduleID);
            echo "<script>alert('$testType result saved successfully!'); window.location.href='../sample_processing.php';</script>";
        } else {
            echo "Error: " . $stmt->error;
        }

        $stmt->close();
    }

    else {
        echo "Unknown test type.";
    }
}
else {
    echo "Invalid access method.";
}
?>
