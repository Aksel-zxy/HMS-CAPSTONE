<?php
class LeaveApplication {
    private $conn;

    public function __construct($db) {
        $this->conn = $db;
    }

    // Get single employee details
    public function getEmployee($employee_id) {
        $stmt = $this->conn->prepare("
            SELECT employee_id, first_name, middle_name, last_name, suffix_name,
                   profession, role, department, gender
            FROM hr_employees
            WHERE employee_id = ?
        ");
        $stmt->bind_param("i", $employee_id);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_assoc();
    }

    // Get all employees
    public function getAllEmployees() {
        $sql = "SELECT employee_id, first_name, middle_name, last_name, suffix_name, profession, role, department, gender 
                FROM hr_employees
                ORDER BY employee_id ASC";
        return $this->conn->query($sql);
    }

    // Get remaining leave days
    public function getRemainingDays($employee_id, $leave_type, $year) {
        $stmt = $this->conn->prepare("
            SELECT allocated_days 
            FROM hr_leave_credits 
            WHERE employee_id = ? AND leave_type = ? AND year = ?
        ");
        $stmt->bind_param("isi", $employee_id, $leave_type, $year);
        $stmt->execute();
        $stmt->bind_result($allocatedDays);
        $stmt->fetch();
        $stmt->close();
        $allocatedDays = $allocatedDays ?? 0;

        $stmt2 = $this->conn->prepare("
            SELECT SUM(DATEDIFF(leave_end_date, leave_start_date) + 1) as used_days
            FROM hr_leave
            WHERE employee_id = ? AND leave_type = ? 
            AND leave_status = 'Approved' 
            AND YEAR(leave_start_date) = ?
        ");
        $stmt2->bind_param("isi", $employee_id, $leave_type, $year);
        $stmt2->execute();
        $stmt2->bind_result($usedDays);
        $stmt2->fetch();
        $stmt2->close();
        $usedDays = $usedDays ?? 0;

        return $allocatedDays - $usedDays;
    }

    // Submit leave with optional medical certificate (stored as BLOB)
    public function submit($data, $file = null) {
        try {
            $fileContent = null;

            // If a file is uploaded
            if ($file && $file['error'] === UPLOAD_ERR_OK) {
                $allowed = ['jpg','jpeg','png','pdf','docx'];
                $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

                if (!in_array($ext, $allowed)) {
                    throw new Exception("Invalid file type. Allowed: JPG, JPEG, PNG, PDF, DOCX.");
                }

                // Read file content as BLOB
                $fileContent = file_get_contents($file['tmp_name']);
                if ($fileContent === false) {
                    throw new Exception("Failed to read uploaded file.");
                }
            }

            // Prepare insert query
            $stmt = $this->conn->prepare("
                INSERT INTO hr_leave 
                (employee_id, leave_type, leave_start_date, leave_end_date, leave_status, leave_reason, medical_cert)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");

            if (!$stmt) {
                throw new Exception("Prepare failed: " . $this->conn->error);
            }

            $leave_status = 'Pending';

            // 'b' in bind_param for BLOB
            $stmt->bind_param(
                "ssssssb",
                $data['employee_id'],
                $data['leave_type'],
                $data['leave_start_date'],
                $data['leave_end_date'],
                $leave_status,
                $data['leave_reason'],
                $fileContent
            );

            // For BLOBs, need send_long_data
            if ($fileContent) {
                $stmt->send_long_data(6, $fileContent); // index 6 = 7th parameter (medical_cert)
            }

            if (!$stmt->execute()) {
                throw new Exception("Insert failed: " . $stmt->error);
            }

            return true;

        } catch (Exception $e) {
            error_log($e->getMessage());
            return false;
        }
    }
}
