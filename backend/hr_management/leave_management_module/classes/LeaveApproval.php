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

        $this->conn->begin_transaction();
        try {
            $stmt = $this->conn->prepare("
                UPDATE hr_leave 
                SET leave_status = ?, approval_date = ?, is_paid = ? 
                WHERE leave_id = ?
            ");
            $stmt->bind_param("sssi", $status, $approval_date, $is_paid, $leave_id);
            $stmt->execute();

            if ($status === 'Approved') {
                $stmt2 = $this->conn->prepare("SELECT employee_id, leave_type, leave_start_date, leave_end_date FROM hr_leave WHERE leave_id = ?");
                $stmt2->bind_param("i", $leave_id);
                $stmt2->execute();
                $stmt2->bind_result($employee_id, $leave_type, $start, $end);
                $stmt2->fetch();
                $stmt2->close();

                $days = (new DateTime($end))->diff(new DateTime($start))->days + 1;

                $stmt3 = $this->conn->prepare("
                    UPDATE hr_leave_credits
                    SET used_days = used_days + ?
                    WHERE employee_id = ? AND leave_type = ?
                ");
                $stmt3->bind_param("iis", $days, $employee_id, $leave_type);
                $stmt3->execute();
                $stmt3->close();
            }

            $this->conn->commit();
            return true;
        } catch (\Exception $e) {
            $this->conn->rollback();
            return false;
        }
    }

    public function fetchPendingLeaves() {
        $query = "SELECT * FROM hr_leave WHERE leave_status = 'Pending' ORDER BY submit_at DESC";
        $result = $this->conn->query($query);
        return $result;
    }


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
        $result = $this->conn->query($sql);
        return $result;
    }
}

