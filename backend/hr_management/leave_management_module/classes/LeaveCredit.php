<?php
class LeaveCredit {
    private $conn;

    public function __construct($db) {
        $this->conn = $db;
    }

    /**
     * Get all employees
     */
    public function getAllEmployees() {
        $sql = "SELECT employee_id, first_name, middle_name, last_name, suffix_name, role, profession, gender 
                FROM hr_employees 
                WHERE status = 'Active'
                AND profession IN ('Doctor','Nurse','Pharmacist','Laboratorist','Accountant')
                ORDER BY employee_id ASC";
        return $this->conn->query($sql);
    }

    /**
     * Get all leave credits for the year
     * Supports Half Day leave calculation
     */
    public function getAllLeaveCredits($year, $search = null) {
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
                        THEN CASE 
                                WHEN h.leave_duration = 'Half Day' THEN 0.5
                                ELSE DATEDIFF(h.leave_end_date, h.leave_start_date) + 1
                            END
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
            WHERE e.status = 'Active'
            AND profession IN ('Doctor','Nurse','Pharmacist','Laboratorist','Accountant')
        ";

        // 🔎 Add Search Condition
        if (!empty($search)) {
            $sql .= " AND (
                e.employee_id LIKE ? OR
                CONCAT(
                    e.first_name, ' ',
                    IFNULL(e.middle_name, ''), ' ',
                    e.last_name, 
                    IF(e.suffix_name IS NOT NULL AND e.suffix_name != '', CONCAT(' ', e.suffix_name), '')
                ) LIKE ? OR
                e.role LIKE ? OR
                e.profession LIKE ?
            )";
        }

        $sql .= "
            GROUP BY 
                e.employee_id, e.first_name, e.middle_name, e.last_name, e.suffix_name,
                e.profession, e.role, e.gender, lc.leave_type, lc.allocated_days
            ORDER BY e.employee_id ASC, lc.leave_type ASC
        ";

        $stmt = $this->conn->prepare($sql);

        if (!empty($search)) {
            $searchParam = "%{$search}%";
            $stmt->bind_param("iissss", $year, $year, $searchParam, $searchParam, $searchParam, $searchParam);
        } else {
            $stmt->bind_param("ii", $year, $year);
        }

        $stmt->execute();
        $result = $stmt->get_result();

        $filtered = [];
        while ($row = $result->fetch_assoc()) {
            if ($row['leave_type'] === "Maternity Leave" && $row['gender'] !== "Female") continue;
            if ($row['leave_type'] === "Paternity Leave" && $row['gender'] !== "Male") continue;

            $row['used_days'] = (float)$row['used_days'];
            $row['allocated_days'] = (float)$row['allocated_days'];

            $filtered[] = $row;
        }

        return $filtered;
    }

    /**
     * Generate leave message for display
     */
    public function generateLeaveMessage($leaveData, $requestedDays = 0) {
        $remaining = $leaveData['allocated_days'] - $leaveData['used_days'];

        // Format remaining: integer kung whole number, decimal kung may .5
        $remainingFormatted = ($remaining == (int)$remaining) ? (int)$remaining : $remaining;
        $requestedFormatted = ($requestedDays == (int)$requestedDays) ? (int)$requestedDays : $requestedDays;

        if ($requestedDays > $remaining) {
            return "❌ {$leaveData['full_name']} ({$leaveData['role']}) has insufficient leave balance for {$leaveData['leave_type']} ({$remainingFormatted} left, needs {$requestedFormatted}).";
        }

        return "✅ {$leaveData['full_name']} ({$leaveData['role']}) still has {$remainingFormatted} {$leaveData['leave_type']} credits remaining.";
    }

    /**
     * Assign leave credit to employee
     * Supports decimal allocation (Half Day)
     */
    public function assignLeaveCredit($employeeId, $leaveType, $allocatedDays, $year) {
        $sql = "
            INSERT INTO hr_leave_credits (employee_id, leave_type, allocated_days, year)
            VALUES (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE 
                allocated_days = VALUES(allocated_days)
        ";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("isdi", $employeeId, $leaveType, $allocatedDays, $year); // d = double for decimals
        return $stmt->execute();
    }
}
