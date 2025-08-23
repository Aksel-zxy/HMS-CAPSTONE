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

    // Fetch schedules
    public function getSchedules($patient_id = null, $status = null)
    {
        if ($patient_id !== null && $status !== null) {
            $stmt = $this->conn->prepare("SELECT * FROM {$this->scheduleTable} WHERE patientID = ? AND status = ?");
            $stmt->bind_param("is", $patient_id, $status);
        } elseif ($patient_id !== null) {
            $stmt = $this->conn->prepare("SELECT * FROM {$this->scheduleTable} WHERE patientID = ?");
            $stmt->bind_param("i", $patient_id);
        } elseif ($status !== null) {
            $stmt = $this->conn->prepare("SELECT * FROM {$this->scheduleTable} WHERE status = ?");
            $stmt->bind_param("s", $status);
        } else {
            $result = $this->conn->query("SELECT * FROM {$this->scheduleTable}");
            return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
        }

        $stmt->execute();
        $result = $stmt->get_result();
        $data = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
        $stmt->close();
        return $data;
    }

    // ✅ Update by scheduleID
    public function updateSchedule($scheduleID, $new_status = null, $scheduleDate = null, $scheduleTime = null, $cancelReason = null)
    {
        if (empty($scheduleID)) {
            echo "<script>alert('❌ Error: Missing schedule ID.'); window.history.back();</script>";
            return false;
        }

        $fields = [];
        $params = [];
        $types  = "";

        if ($new_status !== null && $new_status !== "") {
            $fields[] = "status = ?";
            $params[] = $new_status;
            $types   .= "s";
        }

        if ($scheduleDate !== null && $scheduleDate !== "") {
            $fields[] = "scheduleDate = ?";
            $params[] = $scheduleDate;
            $types   .= "s";
        }

        if ($scheduleTime !== null && $scheduleTime !== "") {
            $fields[] = "scheduleTime = ?";
            $params[] = $scheduleTime;
            $types   .= "s";
        }

        // ✅ handle cancel reason if status = Cancelled
        if ($new_status === "Cancelled") {
            $fields[] = "cancel_reason = ?";
            $params[] = $cancelReason ?? "No reason provided";
            $types   .= "s";
        } else {
            // Clear cancel_reason if not cancelled
            $fields[] = "cancel_reason = NULL";
        }

        if (empty($fields)) {
            echo "<script>alert('⚠️ Nothing to update.'); window.history.back();</script>";
            return false;
        }

        $sql = "UPDATE {$this->scheduleTable} SET " . implode(", ", $fields) . " WHERE scheduleID = ?";
        $stmt = $this->conn->prepare($sql);

        $params[] = $scheduleID;
        $types   .= "i";

        $stmt->bind_param($types, ...$params);
        $success = $stmt->execute();
        $stmt->close();

        return $success;
    }
}

// ✅ POST Handling
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $patientSchedule = new PatientSchedule($conn);

    if (isset($_POST['update_schedule'])) {
        $schedule_id   = (int) $_POST['scheduleID'];
        $status        = !empty($_POST['status']) ? $_POST['status'] : null;
        $date          = !empty($_POST['schedule_date']) ? $_POST['schedule_date'] : null;
        $time          = !empty($_POST['schedule_time']) ? $_POST['schedule_time'] : null;
        $cancel_reason = !empty($_POST['cancel_reason']) ? $_POST['cancel_reason'] : null;

        if ($patientSchedule->updateSchedule($schedule_id, $status, $date, $time, $cancel_reason)) {
            echo "<script>alert('✅ Schedule updated successfully!'); window.location.href='../sps.php';</script>";
            exit;
        } else {
            echo "<script>alert('❌ Failed to update schedule.'); window.location.href='../sps.php';</script>";
        }
    }

    if (isset($_POST['delete_schedule'])) {
        $schedule_id   = (int) $_POST['scheduleID']; // ✅ fixed
        $cancel_reason = !empty($_POST['cancel_reason']) ? $_POST['cancel_reason'] : "No reason provided";

        $stmt = $conn->prepare("UPDATE dl_schedule SET status = 'Cancelled', cancel_reason = ? WHERE scheduleID = ?");
        $stmt->bind_param("si", $cancel_reason, $schedule_id);

        if ($stmt->execute()) {
            echo "<script>alert('✅ Schedule cancelled successfully.'); window.location='../sps.php?cancelled=1';</script>";
        } else {
            echo "<script>alert('❌ Failed to cancel schedule.'); window.location='../sps.php?error=1';</script>";
        }
        $stmt->close();
        exit;
    }
}
