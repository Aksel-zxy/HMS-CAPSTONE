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

    public function submit($data, $file = null) {
        try {
            $uploadPath = null;

            // ✅ Check if a file is uploaded
            if ($file && $file['error'] === UPLOAD_ERR_OK) {
                // Use an absolute path (works on any server)
                $upload_dir = __DIR__ . '/uploads/';

                // ✅ Create uploads folder if missing
                if (!is_dir($upload_dir)) {
                    if (!mkdir($upload_dir, 0777, true)) {
                        throw new Exception("Failed to create upload directory: $upload_dir");
                    }
                }

                // ✅ Validate allowed file types
                $allowed = ['jpg', 'jpeg', 'png', 'pdf'];
                $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                if (!in_array($ext, $allowed)) {
                    throw new Exception("Invalid file type. Allowed: JPG, JPEG, PNG, PDF only.");
                }

                // ✅ Create a unique filename
                $filename = uniqid('cert_', true) . '.' . $ext;
                $filepath = $upload_dir . $filename;

                // ✅ Move uploaded file
                if (!move_uploaded_file($file['tmp_name'], $filepath)) {
                    throw new Exception("Failed to move uploaded file to: $filepath");
                }

                // ✅ Save relative path to DB (for displaying later)
                $uploadPath = 'uploads/' . $filename;
            }

            // ✅ Insert data into database
            $stmt = $this->conn->prepare("
                INSERT INTO hr_leave 
                (employee_id, leave_type, leave_start_date, leave_end_date, leave_status, leave_reason, medical_cert)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");

            if (!$stmt) {
                throw new Exception("Database prepare failed: " . $this->conn->error);
            }

            $leave_status = 'Pending';

            $stmt->bind_param(
                "sssssss",
                $data['employee_id'],
                $data['leave_type'],
                $data['leave_start_date'],
                $data['leave_end_date'],
                $leave_status,
                $data['leave_reason'],
                $uploadPath
            );

            if (!$stmt->execute()) {
                throw new Exception("Database insert failed: " . $stmt->error);
            }

            return true; // ✅ Success

        } catch (Exception $e) {
            // Log or display the error safely (for debugging)
            error_log($e->getMessage());
            return false;
        }
    }

}
