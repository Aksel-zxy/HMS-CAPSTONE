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




public function saveAttendance($employee_id, $attendance_date, $time_in = null, $time_out = null, $duty_status = null) {
    // Normalize inputs
    $time_in  = empty($time_in)  ? null : $time_in;
    $time_out = empty($time_out) ? null : $time_out;
    $duty_status_val = trim($duty_status ?? 'On Duty'); // remove spaces

    // Default working metrics
    $working_hours = 0;
    $late_minutes = 0;
    $undertime_minutes = 0;
    $overtime_minutes = 0;
    $status = 'Absent'; // ✅ default valid ENUM

    /* =======================
       0️⃣ OFF DUTY CHECK
       ======================= */
    if (strcasecmp($duty_status_val, 'Off Duty') === 0) {
        $status = 'Off Duty';
        $time_in = $time_out = null;
        $working_hours = $late_minutes = $undertime_minutes = $overtime_minutes = 0;
    }

    /* =======================
       1️⃣ LEAVE LOGIC
       ======================= */
    elseif ($leave = $this->getLeaveInfo($employee_id, $attendance_date)) {
        if ($leave['leave_duration'] === 'Full Day') {
            $status = 'On Leave';
            $working_hours = 0;
        } elseif ($leave['leave_duration'] === 'Half Day') {
            if (!$time_in && !$time_out) {
                $status = ($leave['is_paid'] === 'Yes') ? 'On Leave (Half Day)' : 'Absent (Half Day)';
                $working_hours = 0;
            } else {
                $timeInObj  = new DateTime($time_in);
                $timeOutObj = new DateTime($time_out);

                if ($leave['half_day_type'] === 'AM') {
                    $shiftStart = new DateTime('13:00:00');
                    $shiftEnd   = new DateTime('17:00:00');
                } else {
                    $shiftStart = new DateTime('08:00:00');
                    $shiftEnd   = new DateTime('12:00:00');
                }

                $interval = $timeInObj->diff($timeOutObj);
                $workedMinutes = ($interval->h * 60) + $interval->i;
                $working_hours = round($workedMinutes / 60, 2);

                if ($timeInObj > $shiftStart) {
                    $lateInterval = $shiftStart->diff($timeInObj);
                    $late_minutes = ($lateInterval->h * 60) + $lateInterval->i;
                }
                if ($timeOutObj < $shiftEnd) {
                    $undertimeInterval = $timeOutObj->diff($shiftEnd);
                    $undertime_minutes = ($undertimeInterval->h * 60) + $undertimeInterval->i;
                }
                if ($timeOutObj > $shiftEnd) {
                    $overtimeInterval = $shiftEnd->diff($timeOutObj);
                    $overtime_minutes = ($overtimeInterval->h * 60) + $overtimeInterval->i;
                }
                $status = 'Half Day';
            }
        }
    }

    /* =======================
       2️⃣ NORMAL DAY
       ======================= */
    else {
        if ($time_in && $time_out) {
            $timeInObj  = new DateTime($time_in);
            $timeOutObj = new DateTime($time_out);

            // Determine shift
            if ($timeInObj >= new DateTime('08:00:00') && $timeInObj < new DateTime('16:00:00')) {
                $shiftStart = new DateTime('08:00:00');
                $shiftEnd   = new DateTime('16:00:00');
            } elseif ($timeInObj >= new DateTime('16:00:00') && $timeInObj <= new DateTime('23:59:59')) {
                $shiftStart = new DateTime('16:00:00');
                $shiftEnd   = new DateTime('00:00:00');
            } else {
                $shiftStart = new DateTime('00:00:00');
                $shiftEnd   = new DateTime('08:00:00');
            }

            $interval = $timeInObj->diff($timeOutObj);
            $workedMinutes = ($interval->h * 60) + $interval->i;
            $working_hours = round($workedMinutes / 60, 2);
            $status = 'Present';

            if ($timeInObj > $shiftStart) {
                $lateInterval = $shiftStart->diff($timeInObj);
                $late_minutes = ($lateInterval->h * 60) + $lateInterval->i;
                $status = 'Late';
            }

            $shiftEndForCalc = $shiftEnd <= $shiftStart ? (clone $shiftEnd)->modify('+1 day') : $shiftEnd;
            $timeOutForCalc  = $timeOutObj < $shiftStart ? (clone $timeOutObj)->modify('+1 day') : $timeOutObj;

            if ($timeOutForCalc < $shiftEndForCalc) {
                $undertimeInterval = $timeOutForCalc->diff($shiftEndForCalc);
                $undertime_minutes = ($undertimeInterval->h * 60) + $undertimeInterval->i;
                if ($status === 'Present') $status = 'Undertime';
            }
            if ($timeOutForCalc > $shiftEndForCalc) {
                $overtimeInterval = $shiftEndForCalc->diff($timeOutForCalc);
                $overtime_minutes = ($overtimeInterval->h * 60) + $overtimeInterval->i;
                if ($status === 'Present') $status = 'Overtime';
            }
        } else {
            // No time_in/out → default status = Absent
            $status = 'Absent';
        }
    }

    /* =======================
       SAVE TO DATABASE
       ======================= */
    $stmt = $this->conn->prepare("
        INSERT INTO hr_daily_attendance 
        (employee_id, attendance_date, time_in, time_out, working_hours,
         late_minutes, undertime_minutes, overtime_minutes, status, duty_status, encoded_by)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'HR Admin')
    ");
    $stmt->bind_param(
        "isssddisss", // notice status and duty_status are "s" (string)
        $employee_id,
        $attendance_date,
        $time_in,
        $time_out,
        $working_hours,
        $late_minutes,
        $undertime_minutes,
        $overtime_minutes,
        $status,         // s = string
        $duty_status_val // s = string
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