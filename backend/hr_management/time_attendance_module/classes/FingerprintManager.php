<?php
class FingerprintManager {
    private $conn;

    public function __construct($db) {
        $this->conn = $db;
    }

    // Enroll fingerprint
    public function enroll($employee_id, $imageData) {
        $imageData = str_replace('data:image/png;base64,', '', $imageData);
        $imageData = base64_decode($imageData);
        $hash = hash('sha256', $imageData);

        $stmt = $this->conn->prepare("
            INSERT INTO hr_fingerprint_templates (employee_id, fingerprint_hash)
            VALUES (?, ?)
        ");
        $stmt->bind_param("is", $employee_id, $hash);
        return $stmt->execute();
    }

    // Scan fingerprint and log attendance
    public function scan($imageData) {
        $imageData = str_replace('data:image/png;base64,', '', $imageData);
        $imageData = base64_decode($imageData);
        $hash = hash('sha256', $imageData);

        $result = $this->conn->query("SELECT * FROM hr_fingerprint_templates");
        $today = date('Y-m-d');

        while ($row = $result->fetch_assoc()) {
            if (hash_equals($row['fingerprint_hash'], $hash)) {
                $employee_id = $row['employee_id'];

                // Check if employee already logged today
                $check = $this->conn->prepare("
                    SELECT * FROM hr_daily_attendance 
                    WHERE employee_id = ? AND attendance_date = ?
                ");
                $check->bind_param("is", $employee_id, $today);
                $check->execute();
                $res = $check->get_result();

                if ($res->num_rows > 0) {
                    // Already logged today
                    return $employee_id;
                }

                // Insert new attendance record
                $stmt = $this->conn->prepare("
                    INSERT INTO hr_daily_attendance (employee_id, attendance_date, time_in, method)
                    VALUES (?, ?, NOW(), 'fingerprint')
                ");
                $stmt->bind_param("is", $employee_id, $today);
                $stmt->execute();

                return $employee_id;
            }
        }
        return false;
    }

    // Get employees without fingerprint (Active only)
    public function employeesWithoutFingerprint() {
        $sql = "
            SELECT 
                employee_id, 
                first_name, 
                middle_name, 
                last_name, 
                suffix_name
            FROM hr_employees
            WHERE status='Active' 
            AND employee_id NOT IN (SELECT employee_id FROM hr_fingerprint_templates)
            ORDER BY employee_id
        ";
        return $this->conn->query($sql);
    }
}
