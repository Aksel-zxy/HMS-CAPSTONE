<?php
class LeaveManager {
    private $conn;

    public function __construct($dbConnection) {
        $this->conn = $dbConnection;
    }

    public function getApprovedLeaves() {
        return $this->getLeavesByStatus('Approved');
    }

    public function getRejectedLeaves() {
        return $this->getLeavesByStatus('Rejected');
    }

    private function getLeavesByStatus($status) {
        $sql = "
            SELECT l.*, 
                   e.first_name, e.middle_name, e.last_name, e.suffix_name,
                   e.profession, e.role, e.department
            FROM hr_leave l
            INNER JOIN hr_employees e ON l.employee_id = e.employee_id
            WHERE l.leave_status = ?
            ORDER BY l.approval_date DESC
        ";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("s", $status);
        $stmt->execute();
        return $stmt->get_result();
    }
}
