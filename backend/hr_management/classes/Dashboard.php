<?php
class Dashboard {
    private $conn;

    public function __construct($dbConn) {
        $this->conn = $dbConn;
    }

    public function getEmployeeAttendanceSummary($employeeId, $month = null, $year = null) {
        $month = $month ?? date('m');  // default current month
        $year  = $year ?? date('Y');   // default current year

        $sql = "SELECT status, COUNT(*) AS total
                FROM hr_daily_attendance
                WHERE employee_id = ? 
                AND MONTH(attendance_date) = ? 
                AND YEAR(attendance_date) = ?
                GROUP BY status";

        $stmt = $this->conn->prepare($sql);
        if (!$stmt) die("Prepare failed: " . $this->conn->error);

        $stmt->bind_param("iii", $employeeId, $month, $year);
        $stmt->execute();
        $result = $stmt->get_result();

        // ✅ Include all statuses
        $statuses = [
            'Present'            => 0,
            'Late'               => 0,
            'Undertime'          => 0,
            'Overtime'           => 0,
            'Half Day'           => 0,
            'On Leave'           => 0,
            'On Leave (Half Day)' => 0,
            'Absent'             => 0,
            'Absent (Half Day)'  => 0,
            'Off Duty'           => 0
        ];

        while ($row = $result->fetch_assoc()) {
            $statusKey = trim($row['status']);
            if (array_key_exists($statusKey, $statuses)) {
                $statuses[$statusKey] = (int)$row['total'];
            }
        }

        $stmt->close();
        return $statuses;
    }

    public function getAllEmployees() {
        $sql = "SELECT employee_id, 
                    COALESCE(CONCAT_WS(' ', first_name, middle_name, last_name, suffix_name), 'No Name') AS full_name
                FROM hr_employees 
                ORDER BY employee_id ASC";
        $result = $this->conn->query($sql);
        $employees = [];
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $employees[] = $row;
            }
        }
        return $employees;
    }

    public function getPendingLeaves($department = null) {
        $sql = "
            SELECT e.department AS department_name, COUNT(*) AS total_pending
            FROM hr_leave l
            JOIN hr_employees e ON l.employee_id = e.employee_id
            WHERE l.leave_status = 'Pending'
        ";

        if ($department) {
            $sql .= " AND e.department = ?";
            $stmt = $this->conn->prepare($sql);
            if (!$stmt) die("Prepare failed: " . $this->conn->error);
            $stmt->bind_param("s", $department);
            $stmt->execute();
            $result = $stmt->get_result();
        } else {
            $result = $this->conn->query($sql . " GROUP BY e.department");
        }

        $data = [];
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $data[] = $row;
            }
        }

        return $data;
    }

    public function getPendingLeaveDetails($department = null) {
        $sql = "
            SELECT l.leave_id,
                e.employee_id,
                COALESCE(CONCAT_WS(' ', e.first_name, e.middle_name, e.last_name, e.suffix_name), 'No Name') AS full_name,
                l.leave_type,
                l.leave_start_date,
                l.leave_end_date,
                l.leave_duration,
                l.leave_fraction,
                l.half_day_type,
                l.leave_reason
            FROM hr_leave l
            JOIN hr_employees e ON l.employee_id = e.employee_id
            WHERE l.leave_status = 'Pending'
        ";

        if ($department) {
            $sql .= " AND e.department = ?";
        }

        $sql .= " ORDER BY l.submit_at DESC";

        $stmt = $this->conn->prepare($sql);
        if (!$stmt) die("Prepare failed: " . $this->conn->error);

        if ($department) {
            $stmt->bind_param("s", $department);
        }

        $stmt->execute();
        $result = $stmt->get_result();

        $data = [];
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }

        $stmt->close();
        return $data;
    }



}
