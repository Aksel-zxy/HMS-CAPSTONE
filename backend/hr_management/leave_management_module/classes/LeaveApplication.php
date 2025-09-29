<?php
class LeaveApplication {
    private $conn;

    public function __construct($db) {
        $this->conn = $db;
    }

    // ✅ Get single employee details (for gender filtering or form autofill)
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
        return $result->fetch_assoc(); // returns assoc array or null
    }

    // ✅ Get all employees (for dropdowns)
    public function getAllEmployees() {
        $sql = "SELECT employee_id, first_name, middle_name, last_name, suffix_name, profession, role, department, gender 
                FROM hr_employees
                ORDER BY employee_id ASC";
        return $this->conn->query($sql);
    }

    // ✅ Get remaining leave days
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

        if (!$allocatedDays) $allocatedDays = 0;

        // ✅ Get used days with +1
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

        if (!$usedDays) $usedDays = 0;

        return $allocatedDays - $usedDays;
    }

    // ✅ Submit leave application
    public function submit($data, $file = null) {
        $uploadPath = null;

        if ($file && $file['error'] === UPLOAD_ERR_OK) {
            $upload_dir = 'uploads/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }

            $filename = uniqid() . '_' . basename($file['name']);
            $filepath = $upload_dir . $filename;

            if (move_uploaded_file($file['tmp_name'], $filepath)) {
                $uploadPath = $filepath;
            }
        }

        $stmt = $this->conn->prepare("INSERT INTO hr_leave 
            (employee_id, leave_type, leave_start_date, leave_end_date, leave_status, leave_reason, medical_cert)
            VALUES (?, ?, ?, ?, ?, ?, ?)");

        $leave_status = 'Pending';

        $stmt->bind_param("sssssss",
            $data['employee_id'],
            $data['leave_type'],
            $data['leave_start_date'],
            $data['leave_end_date'],
            $leave_status,
            $data['leave_reason'],
            $uploadPath
        );

        return $stmt->execute();
    }

}
