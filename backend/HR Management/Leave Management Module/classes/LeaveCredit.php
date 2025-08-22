<?php
class LeaveCredit {
    private $conn;

    public function __construct($db) {
        $this->conn = $db;
    }

    public function getAllLeaveCredits($year) {
        $sql = "
            SELECT 
                e.employee_id, 
                CONCAT(e.first_name, ' ', e.last_name) AS full_name, 
                e.profession, 
                e.role, 
                e.leave_credits,

                COUNT(h.leave_id) AS total_leaves,

                SUM(CASE 
                    WHEN h.leave_status = 'Approved' AND YEAR(h.leave_start_date) = ? 
                    THEN 1 ELSE 0 
                END) AS approved_leaves,

                SUM(CASE 
                    WHEN h.leave_status = 'Rejected' THEN 1 ELSE 0 
                END) AS rejected_leaves,

                SUM(CASE 
                    WHEN h.leave_status = 'Pending' THEN 1 ELSE 0 
                END) AS pending_leaves,

                SUM(CASE 
                    WHEN h.leave_status = 'Approved' AND YEAR(h.leave_start_date) = ? 
                    THEN DATEDIFF(h.leave_end_date, h.leave_start_date) + 1 
                    ELSE 0 
                END) AS total_approved_leave_days,

                e.leave_credits - SUM(CASE 
                    WHEN h.leave_status = 'Approved' AND YEAR(h.leave_start_date) = ? 
                    THEN DATEDIFF(h.leave_end_date, h.leave_start_date) + 1 
                    ELSE 0 
                END) AS remaining_leave_days

            FROM hr_employees e
            LEFT JOIN hr_leave h ON e.employee_id = h.employee_id
            GROUP BY 
                e.employee_id, e.first_name, e.last_name, 
                e.profession, e.role, e.leave_credits
            ORDER BY e.employee_id ASC
        ";

        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("iii", $year, $year, $year);
        $stmt->execute();
        return $stmt->get_result();
    }

    public function generateLeaveMessage($leaveData) {
        if ($leaveData['remaining_leave_days'] <= 0) {
            return "❌ {$leaveData['role']} has no remaining leave credits. Approval not possible.";
        }
        return "✅ {$leaveData['role']} still has {$leaveData['remaining_leave_days']} leave credits remaining.";
    }

}
