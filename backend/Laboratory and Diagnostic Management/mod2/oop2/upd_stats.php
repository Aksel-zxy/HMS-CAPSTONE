<?php
require_once __DIR__ . '../../../../../SQL/config.php';
class PatientSchedule
{
    private $conn;
    private $scheduleTable = "dl_schedule";

    public function __construct($db)
    {
        $this->conn = $db;
    }

    // Fetch all schedules (or optionally by patient ID)
   public function getSchedules($patient_id = null, $status = null)
{
    if ($patient_id !== null && $status !== null) {
        $stmt = $this->conn->prepare("SELECT * FROM {$this->scheduleTable} WHERE patientID = ? AND status = ?");
        if (!$stmt) {
            echo "Prepare failed (getSchedules): " . $this->conn->error;
            return [];
        }
        $stmt->bind_param("is", $patient_id, $status);
        $stmt->execute();
        $result = $stmt->get_result();
        $data = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
        $stmt->close();
        return $data;
    } elseif ($patient_id !== null) {
        $stmt = $this->conn->prepare("SELECT * FROM {$this->scheduleTable} WHERE patientID = ?");
        if (!$stmt) {
            echo "Prepare failed (getSchedules): " . $this->conn->error;
            return [];
        }
        $stmt->bind_param("i", $patient_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $data = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
        $stmt->close();
        return $data;
    } elseif ($status !== null) {
        // Fetch all schedules with specific status
        $stmt = $this->conn->prepare("SELECT * FROM {$this->scheduleTable} WHERE status = ?");
        if (!$stmt) {
            echo "Prepare failed (getSchedules): " . $this->conn->error;
            return [];
        }
        $stmt->bind_param("s", $status);
        $stmt->execute();
        $result = $stmt->get_result();
        $data = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
        $stmt->close();
        return $data;
    } else {
        // Return all schedules
        $query = "SELECT * FROM {$this->scheduleTable}";
        $result = $this->conn->query($query);
        return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    }
}


    // Update the status of a schedule record by patientID
    public function updateStatus($patient_id, $new_status)
    {
        if (empty($patient_id) || empty($new_status)) {
            echo "Error: Patient ID or status empty.\n";
            return false;
        }

        // Fetch the current schedule record
        $stmt = $this->conn->prepare("SELECT scheduleDate, scheduleTime FROM {$this->scheduleTable} WHERE patientID = ?");
        if (!$stmt) {
            echo "Prepare failed (select schedule): " . $this->conn->error . "\n";
            return false;
        }
        $stmt->bind_param("i", $patient_id);
        if (!$stmt->execute()) {
            echo "Execute failed (select schedule): " . $stmt->error . "\n";
            return false;
        }
        $result = $stmt->get_result();
        $row = $result ? $result->fetch_assoc() : null;
        $stmt->close();

        if (!$row) {
            echo "No schedule record found for patient ID $patient_id.\n";
            return false;
        }

        // Update scheduleDate and scheduleTime only if status is 'Completed'
        if ($new_status === 'Completed') {
            $scheduleDate = date('Y-m-d');
            $scheduleTime = date('H:i:s');
        } else {
            $scheduleDate = $row['scheduleDate'];
            $scheduleTime = $row['scheduleTime'];
        }

        $stmtUpdate = $this->conn->prepare("
            UPDATE {$this->scheduleTable}
            SET status = ?, scheduleDate = ?, scheduleTime = ?
            WHERE patientID = ?
        ");
        if (!$stmtUpdate) {
            echo "Prepare failed (update schedule): " . $this->conn->error . "\n";
            return false;
        }
        $stmtUpdate->bind_param("sssi", $new_status, $scheduleDate, $scheduleTime, $patient_id);
        if (!$stmtUpdate->execute()) {
            echo "Execute failed (update schedule): " . $stmtUpdate->error . "\n";
            return false;
        }
        $stmtUpdate->close();

        return true;
    }
}

// Example usage:

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $patientSchedule = new PatientSchedule($conn);

    $patient_id = isset($_POST['patient_id']) ? (int)$_POST['patient_id'] : null;
    $status = isset($_POST['status']) ? trim($_POST['status']) : null;

    if ($patient_id !== null && $patient_id > 0 && !empty($status)) {
        if ($patientSchedule->updateStatus($patient_id, $status)) {
            header("Location: ../sps.php?updated=1");
            exit;
        } else {
            echo "Failed to update status.";
            exit;
        }
    } else {
        echo "Missing required data.";
        exit;
    }
}

// If you want to fetch and display all schedules (example):
// $patientSchedule = new PatientSchedule($conn);
// $allSchedules = $patientSchedule->getSchedules();
// print_r($allSchedules);
