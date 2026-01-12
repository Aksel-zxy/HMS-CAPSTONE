<?php
class LeaveApproval {
    private $conn;

    public function __construct($db) {
        $this->conn = $db;
    }

    /**
     * Approve or Reject Leave
     * Supports Half Day by considering leave_duration
     */
    public function approveOrRejectLeave($leave_id, $action) {
        $approval_date = date('Y-m-d');

        // Determine status and is_paid
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

        $this->conn->begin_transaction();
        try {
            // 1️⃣ Update leave_status and is_paid in hr_leave
            $stmt = $this->conn->prepare("
                UPDATE hr_leave 
                SET leave_status = ?, approval_date = ?, is_paid = ? 
                WHERE leave_id = ?
            ");
            $stmt->bind_param("sssi", $status, $approval_date, $is_paid, $leave_id);
            $stmt->execute();

            // 2️⃣ Only update used_days if leave is approved
            if ($status === 'Approved') {
                $stmt2 = $this->conn->prepare("
                    SELECT employee_id, leave_type, leave_start_date, leave_end_date, leave_duration
                    FROM hr_leave 
                    WHERE leave_id = ?
                ");
                $stmt2->bind_param("i", $leave_id);
                $stmt2->execute();
                $stmt2->bind_result($employee_id, $leave_type, $start, $end, $leave_duration);
                $stmt2->fetch();
                $stmt2->close();

                // Calculate days used
                $days = (new DateTime($end))->diff(new DateTime($start))->days + 1;
                if ($leave_duration === 'Half Day') {
                    $days = 0.5;
                }

                // Update used_days in hr_leave_credits
                $stmt3 = $this->conn->prepare("
                    UPDATE hr_leave_credits
                    SET used_days = used_days + ?
                    WHERE employee_id = ? AND leave_type = ?
                ");
                $stmt3->bind_param("dis", $days, $employee_id, $leave_type); // d = double
                $stmt3->execute();
                $stmt3->close();
            }

            $this->conn->commit();
            return true;

        } catch (\Exception $e) {
            $this->conn->rollback();
            error_log($e->getMessage());
            return false;
        }
    }

    /**
     * Fetch all pending leaves
     */
    public function fetchPendingLeaves() {
        $query = "SELECT * FROM hr_leave WHERE leave_status = 'Pending' ORDER BY submit_at DESC";
        return $this->conn->query($query);
    }

    /**
     * Fetch pending leaves with employee info
     * Includes leave_duration and half_day_type
     */
    public function fetchPendingLeavesWithEmployeeInfo() {
        $sql = "
            SELECT l.*, 
                   e.first_name, e.middle_name, e.last_name, e.suffix_name,
                   e.profession, e.role, e.department, e.gender
            FROM hr_leave l
            INNER JOIN hr_employees e ON l.employee_id = e.employee_id
            WHERE l.leave_status = 'Pending'
            ORDER BY l.submit_at DESC
        ";
        return $this->conn->query($sql);
    }
}
