<?php
class LeaveCredit {
    private $conn;

    public function __construct($db) {
        $this->conn = $db;
    }

    public function getAllEmployees() {
        $sql = "SELECT employee_id, first_name, middle_name, last_name, suffix_name, role, profession, gender 
                FROM hr_employees 
                ORDER BY employee_id ASC";
        return $this->conn->query($sql);
    }

    public function getAllLeaveCredits($year) {
        $sql = "
            SELECT 
                e.employee_id, 
                CONCAT(
                    e.first_name, ' ',
                    IFNULL(e.middle_name, ''), ' ',
                    e.last_name, ' ',
                    IFNULL(e.suffix_name, '')
                ) AS full_name, 
                e.profession, 
                e.role, 
                e.gender,
                lc.leave_type,
                IFNULL(lc.allocated_days, 0) AS allocated_days,
                IFNULL(SUM(
                    CASE 
                        WHEN h.leave_status = 'Approved' 
                            AND h.leave_type = lc.leave_type 
                            AND YEAR(h.leave_start_date) = ? 
                        THEN DATEDIFF(h.leave_end_date, h.leave_start_date) + 1  -- ✅ Add +1
                        ELSE 0 
                    END
                ), 0) AS used_days
            FROM hr_employees e
            LEFT JOIN hr_leave_credits lc 
                ON e.employee_id = lc.employee_id 
                AND lc.year = ?
            LEFT JOIN hr_leave h 
                ON e.employee_id = h.employee_id
                AND h.leave_type = lc.leave_type
            GROUP BY 
                e.employee_id, e.first_name, e.middle_name, e.last_name, e.suffix_name,
                e.profession, e.role, e.gender, lc.leave_type, lc.allocated_days
            ORDER BY e.employee_id ASC, lc.leave_type ASC
        ";

        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("ii", $year, $year);
        $stmt->execute();
        $result = $stmt->get_result();

        $filtered = [];
        while ($row = $result->fetch_assoc()) {
            if ($row['leave_type'] === "Maternity Leave" && $row['gender'] !== "Female") continue;
            if ($row['leave_type'] === "Paternity Leave" && $row['gender'] !== "Male") continue;
            $filtered[] = $row;
        }

        return $filtered;
    }

    public function generateLeaveMessage($leaveData, $requestedDays = 0) {
        $remaining = $leaveData['allocated_days'] - $leaveData['used_days'];

        if ($requestedDays > $remaining) {
            return "❌ {$leaveData['full_name']} ({$leaveData['role']}) has insufficient leave balance for {$leaveData['leave_type']} ({$remaining} left, needs {$requestedDays}).";
        }

        return "✅ {$leaveData['full_name']} ({$leaveData['role']}) still has {$remaining} {$leaveData['leave_type']} credits remaining.";
    }

    public function assignLeaveCredit($employeeId, $leaveType, $allocatedDays, $year) {
        $sql = "
            INSERT INTO hr_leave_credits (employee_id, leave_type, allocated_days, remaining_days, year)
            VALUES (?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE 
                allocated_days = VALUES(allocated_days), 
                remaining_days = VALUES(remaining_days)
        ";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("isiii", $employeeId, $leaveType, $allocatedDays, $allocatedDays, $year);
        return $stmt->execute();
    }
}

