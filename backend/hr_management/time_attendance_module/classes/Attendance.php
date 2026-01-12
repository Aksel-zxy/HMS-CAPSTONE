<?php
class Attendance {
    private $conn;

    public function __construct($conn) {
        $this->conn = $conn;
    }

    // Check if an employee is on leave for a specific date
    public function isOnLeave($employee_id, $attendance_date) {
        $stmt = $this->conn->prepare("
            SELECT * FROM hr_leave 
            WHERE employee_id = ? 
            AND TRIM(LOWER(leave_status)) = 'approved'
            AND DATE(?) BETWEEN DATE(leave_start_date) AND DATE(leave_end_date)
        ");
        $stmt->bind_param("is", $employee_id, $attendance_date);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->num_rows > 0;
    }

    // Save attendance
    public function saveAttendance($employee_id, $attendance_date, $time_in = null, $time_out = null) {
        $working_hours = null;
        $late_minutes = 0;
        $undertime_minutes = 0;
        $overtime_minutes = 0;
        $status = 'Present';

        if ($this->isOnLeave($employee_id, $attendance_date)) {
            $status = 'On Leave';
        } else {
            if ($time_in && $time_out) {
                $timeInObj = new DateTime($time_in);
                $timeOutObj = new DateTime($time_out);

                $shiftStart = new DateTime('08:00:00');
                $shiftEnd   = new DateTime('17:00:00'); 

                $interval = $timeInObj->diff($timeOutObj);
                $workedMinutes = ($interval->h * 60) + $interval->i;
                $working_hours = round($workedMinutes / 60, 2);

                // Late
                if ($timeInObj > $shiftStart) {
                    $lateInterval = $shiftStart->diff($timeInObj);
                    $late_minutes = ($lateInterval->h * 60) + $lateInterval->i;
                    $status = 'Late';
                }

                // Undertime
                if ($timeOutObj < $shiftEnd) {
                    $undertimeInterval = $timeOutObj->diff($shiftEnd);
                    $undertime_minutes = ($undertimeInterval->h * 60) + $undertimeInterval->i;
                    $status = 'Undertime';
                }

                // Overtime
                if ($timeOutObj > $shiftEnd) {
                    $overtimeInterval = $shiftEnd->diff($timeOutObj);
                    $overtime_minutes = ($overtimeInterval->h * 60) + $overtimeInterval->i;
                    $status = 'Overtime';
                }
            } else {
                $status = 'Absent';
            }
        }

        // Insert into database
        $stmt = $this->conn->prepare("
            INSERT INTO hr_daily_attendance 
            (employee_id, attendance_date, time_in, time_out, working_hours, late_minutes, undertime_minutes, overtime_minutes, status, encoded_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'HR Admin')
        ");
        $stmt->bind_param(
            "isssdiids",
            $employee_id,
            $attendance_date,
            $time_in,
            $time_out,
            $working_hours,
            $late_minutes,
            $undertime_minutes,
            $overtime_minutes,
            $status
        );
        $success = $stmt->execute();
        $stmt->close();

        return ['success' => $success, 'status' => $status];
    }

    // Get all employees
    public function getEmployees() {
        $employees = [];
        $result = $this->conn->query("
            SELECT 
                employee_id,
                TRIM(
                    CONCAT(
                        COALESCE(first_name, ''),
                        ' ',
                        COALESCE(middle_name, ''),
                        ' ',
                        COALESCE(last_name, ''),
                        ' ',
                        COALESCE(suffix_name, '')
                    )
                ) AS full_name
            FROM hr_employees
            ORDER BY employee_id ASC
        ");

        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $employees[] = $row;
            }
        }
        return $employees;
    }

}
