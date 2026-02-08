<?php 
class AttendanceReport {
    private $conn;

    public function __construct($conn) {
        $this->conn = $conn;
    }

    public function getAttendanceSummary($start_date, $end_date) {
        $sql = "SELECT 
                    e.employee_id,
                    CONCAT(e.first_name, ' ',
                           IFNULL(e.middle_name,''), ' ',
                           e.last_name,
                           IF(e.suffix_name IS NOT NULL AND e.suffix_name != '', CONCAT(' ', e.suffix_name), '')
                    ) AS full_name,

                    -- Days Present (including Late, Undertime, Overtime)
                    SUM(CASE 
                            WHEN a.status IN ('Present','Late','Undertime','Overtime') THEN 1
                            WHEN a.status='Half Day' THEN 0.5
                            ELSE 0
                        END) AS days_present,

                    -- Days On Leave
                    SUM(CASE 
                            WHEN a.status='On Leave' THEN 1
                            WHEN a.status='On Leave (Half Day)' THEN 0.5
                            WHEN a.status='Half Day' THEN 0.5
                            ELSE 0
                        END) AS days_on_leave,

                    -- Days Absent
                    SUM(CASE 
                            WHEN a.status='Absent' THEN 1
                            WHEN a.status='Absent (Half Day)' THEN 0.5
                            ELSE 0
                        END) AS days_absent,

                    SUM(a.working_hours) AS total_working_hours,
                    SUM(a.late_minutes) AS total_late,
                    SUM(a.undertime_minutes) AS total_undertime,
                    SUM(a.overtime_minutes) AS total_overtime

                FROM hr_employees e
                LEFT JOIN hr_daily_attendance a 
                       ON e.employee_id = a.employee_id
                       AND a.attendance_date BETWEEN ? AND ?
                WHERE e.status='Active'
                GROUP BY e.employee_id
                ORDER BY e.employee_id ASC";

        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param('ss', $start_date, $end_date);
        $stmt->execute();
        return $stmt->get_result();
    }
}
