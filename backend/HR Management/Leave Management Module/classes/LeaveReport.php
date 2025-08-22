<?php
class LeaveReport {
    private $conn;

    public function __construct($db) {
        $this->conn = $db;
    }

    public function fetchAll() {
        $sql = "SELECT 
                    l.employee_id,
                    CONCAT(e.first_name, ' ', e.last_name) AS full_name,
                    l.leave_type,
                    l.leave_start_date,
                    l.leave_end_date,
                    DATEDIFF(l.leave_end_date, l.leave_start_date) + 1 AS total_days,
                    l.leave_status,
                    CASE 
                        WHEN l.leave_status = 'Approved' THEN 'Yes'
                        WHEN l.leave_status = 'Rejected' THEN 'No'
                        WHEN l.leave_status = 'Pending' THEN 'Pending'
                    END AS is_paid,
                    l.leave_reason,
                    l.submit_at
                FROM hr_leave l
                JOIN hr_employees e ON l.employee_id = e.employee_id
                ORDER BY l.submit_at DESC";
        return $this->conn->query($sql);
    }
}
