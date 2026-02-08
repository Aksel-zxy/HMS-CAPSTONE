<?php
class Attendance {
    private $conn;

    public function __construct($conn) {
        $this->conn = $conn;
    }

    public function getLeaveInfo($employee_id, $attendance_date) {
        $stmt = $this->conn->prepare("
            SELECT leave_duration, half_day_type, is_paid
            FROM hr_leave
            WHERE employee_id = ?
              AND TRIM(LOWER(leave_status)) = 'approved'
              AND DATE(?) BETWEEN DATE(leave_start_date) AND DATE(leave_end_date)
            LIMIT 1
        ");
        $stmt->bind_param("is", $employee_id, $attendance_date);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc(); // null if none
    }

    public function saveAttendance($employee_id, $attendance_date, $time_in = null, $time_out = null) {

        // normalize empty times
        $time_in  = empty($time_in)  ? null : $time_in;
        $time_out = empty($time_out) ? null : $time_out;

        $working_hours = 0;
        $late_minutes = 0;
        $undertime_minutes = 0;
        $overtime_minutes = 0;
        $status = 'Absent';

        $leave = $this->getLeaveInfo($employee_id, $attendance_date);

        /* =======================
           LEAVE LOGIC
        ======================== */
        if ($leave) {

            /* 🔹 FULL DAY LEAVE */
            if ($leave['leave_duration'] === 'Full Day') {
                $status = 'On Leave';
                $working_hours = 0;
            }

            /* 🔹 HALF DAY LEAVE */
            elseif ($leave['leave_duration'] === 'Half Day') {

                // ❗ DID NOT ATTEND
                if (!$time_in && !$time_out) {

                    if ($leave['is_paid'] === 'Yes') {
                        $status = 'On Leave (Half Day)';
                    } else {
                        $status = 'Absent (Half Day)';
                    }

                    $working_hours = 0;

                } 
                // ✅ ATTENDED HALF DAY
                else {

                    $timeInObj  = new DateTime($time_in);
                    $timeOutObj = new DateTime($time_out);

                    // AM Leave → works PM
                    if ($leave['half_day_type'] === 'AM') {
                        $shiftStart = new DateTime('13:00:00');
                        $shiftEnd   = new DateTime('17:00:00');
                    }

                    // PM Leave → works AM
                    else {
                        $shiftStart = new DateTime('08:00:00');
                        $shiftEnd   = new DateTime('12:00:00');
                    }

                    $interval = $timeInObj->diff($timeOutObj);
                    $workedMinutes = ($interval->h * 60) + $interval->i;
                    $working_hours = round($workedMinutes / 60, 2);

                    // Late
                    if ($timeInObj > $shiftStart) {
                        $lateInterval = $shiftStart->diff($timeInObj);
                        $late_minutes = ($lateInterval->h * 60) + $lateInterval->i;
                    }

                    // Undertime
                    if ($timeOutObj < $shiftEnd) {
                        $undertimeInterval = $timeOutObj->diff($shiftEnd);
                        $undertime_minutes = ($undertimeInterval->h * 60) + $undertimeInterval->i;
                    }

                    // Overtime
                    if ($timeOutObj > $shiftEnd) {
                        $overtimeInterval = $shiftEnd->diff($timeOutObj);
                        $overtime_minutes = ($overtimeInterval->h * 60) + $overtimeInterval->i;
                    }

                    $status = 'Half Day';
                }
            }

        }

        /* =======================
           NO LEAVE (NORMAL DAY)
        ======================== */
        else {

            if ($time_in && $time_out) {

                $timeInObj  = new DateTime($time_in);
                $timeOutObj = new DateTime($time_out);

                $shiftStart = new DateTime('08:00:00');
                $shiftEnd   = new DateTime('17:00:00');

                $interval = $timeInObj->diff($timeOutObj);
                $workedMinutes = ($interval->h * 60) + $interval->i;
                $working_hours = round($workedMinutes / 60, 2);

                $status = 'Present';

                if ($timeInObj > $shiftStart) {
                    $lateInterval = $shiftStart->diff($timeInObj);
                    $late_minutes = ($lateInterval->h * 60) + $lateInterval->i;
                    $status = 'Late';
                }

                if ($timeOutObj < $shiftEnd) {
                    $undertimeInterval = $timeOutObj->diff($shiftEnd);
                    $undertime_minutes = ($undertimeInterval->h * 60) + $undertimeInterval->i;
                    $status = 'Undertime';
                }

                if ($timeOutObj > $shiftEnd) {
                    $overtimeInterval = $shiftEnd->diff($timeOutObj);
                    $overtime_minutes = ($overtimeInterval->h * 60) + $overtimeInterval->i;
                    $status = 'Overtime';
                }

            } else {
                $status = 'Absent';
            }
        }

        /* =======================
           SAVE
        ======================== */
        $stmt = $this->conn->prepare("
            INSERT INTO hr_daily_attendance 
            (employee_id, attendance_date, time_in, time_out, working_hours,
             late_minutes, undertime_minutes, overtime_minutes, status, encoded_by)
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

    public function getEmployees() {
        $employees = [];
        $result = $this->conn->query("
            SELECT employee_id,
                   TRIM(CONCAT(
                       COALESCE(first_name,''),' ',
                       COALESCE(middle_name,''),' ',
                       COALESCE(last_name,''),' ',
                       COALESCE(suffix_name,'')
                   )) AS full_name
            FROM hr_employees
            ORDER BY employee_id ASC
        ");

        while ($row = $result->fetch_assoc()) {
            $employees[] = $row;
        }
        return $employees;
    }
}
