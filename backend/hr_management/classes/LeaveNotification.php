<?php
class LeaveNotification {
    private $conn;

    public function __construct($db) {
        $this->conn = $db;
    }

    public function getPendingLeaveCount() {
        $stmt = $this->conn->prepare("SELECT COUNT(*) as count FROM hr_leave WHERE leave_status = 'Pending'");
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        return $row['count'] ?? 0;
    }

}
?>
