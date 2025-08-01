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
        $stmt = $this->conn->prepare("SELECT * FROM hr_leave WHERE leave_status = ? ORDER BY approval_date DESC");
        $stmt->bind_param("s", $status);
        $stmt->execute();
        return $stmt->get_result();
    }
}
