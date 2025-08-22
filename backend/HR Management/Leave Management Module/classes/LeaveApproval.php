<?php
class LeaveApproval {
    private $conn;

    public function __construct($db) {
        $this->conn = $db;
    }

    public function approveOrRejectLeave($leave_id, $action) {
        $approval_date = date('Y-m-d');

        switch ($action) {
            case 'approve':
                $status  = 'Approved';
                $is_paid = 'Yes';
                break;

            case 'reject':
                $status  = 'Rejected';
                $is_paid = 'No';
                break;

            default:
                $status  = 'Pending';
                $is_paid = 'Pending';
                break;
        }

        $stmt = $this->conn->prepare("
            UPDATE hr_leave 
            SET leave_status = ?, approval_date = ?, is_paid = ? 
            WHERE leave_id = ?
        ");
        $stmt->bind_param("sssi", $status, $approval_date, $is_paid, $leave_id);
        return $stmt->execute();
    }

    public function fetchPendingLeaves() {
        $query = "SELECT * FROM hr_leave WHERE leave_status = 'Pending' ORDER BY submit_at DESC";
        $result = $this->conn->query($query);
        return $result;
    }

    public function fetchUser($user_id) {
        $stmt = $this->conn->prepare("SELECT * FROM users WHERE user_id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_assoc();
    }
}

