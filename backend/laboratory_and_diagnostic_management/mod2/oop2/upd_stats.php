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

    public function updateSchedule($scheduleID, $new_status = null, $scheduleDate = null, $scheduleTime = null, $cancelReason = null)
    {
        if (empty($scheduleID)) {
            echo "<script>alert('❌ Error: Missing schedule ID.'); window.history.back();</script>";
            return false;
        }

        $fields = [];
        $params = [];
        $types  = "";

        if (!empty($new_status)) {
            $fields[] = "status = ?";
            $params[] = $new_status;
            $types   .= "s";
        }

        if (!empty($scheduleDate)) {
            $fields[] = "scheduleDate = ?";
            $params[] = $scheduleDate;
            $types   .= "s";
        }

        if (!empty($scheduleTime)) {
            $fields[] = "scheduleTime = ?";
            $params[] = $scheduleTime;
            $types   .= "s";
        }

        if ($new_status === "Cancelled") {
            $fields[] = "cancel_reason = ?";
            $params[] = $cancelReason ?? "No reason provided";
            $types   .= "s";
        } else {
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

        // ✅ If update succeeded and status is Completed, insert into dl_results (only if not exists)
        if ($success && $new_status === "Completed") {
            $check = $this->conn->prepare("SELECT resultID FROM dl_results WHERE scheduleID = ?");
            $check->bind_param("i", $scheduleID);
            $check->execute();
            $check->store_result();

            if ($check->num_rows == 0) {
                $check->close();

                // fetch patientID from schedule safely
                $s = $this->conn->prepare("SELECT patientID FROM dl_schedule WHERE scheduleID = ? LIMIT 1");
                $s->bind_param("i", $scheduleID);
                $s->execute();
                $patientID = null;
                $s->bind_result($patientID);
                if ($s->fetch() && !empty($patientID)) {
                    $s->close();

                    $insert = $this->conn->prepare("INSERT INTO dl_results (scheduleID, patientID, resultDate, status, result, remarks) 
                                                    VALUES (?, ?, NOW(), 'Processing', NULL, 'Pending results')");
                    $insert->bind_param("ii", $scheduleID, $patientID);
                    $insert->execute();
                    $insert->close();
                } else {
                    $s->close();
                    error_log("❌ Failed to fetch patientID for scheduleID={$scheduleID}");
                }
            } else {
                $check->close();
            }
        }

        return $success;
    }
}


// ----------------- POST Handling -----------------
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
        $schedule_id   = (int) $_POST['scheduleID'];
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
