<?php
class AttendanceReport {
    private $conn;

    public function __construct($conn) {
        $this->conn = $conn;
    }

    public function getAttendanceSummary($start_date, $end_date) {
        $sql = "SELECT e.employee_id,
                       CONCAT(e.first_name, ' ',
                              IFNULL(e.middle_name,''), ' ',
                              e.last_name,
                              IF(e.suffix_name IS NOT NULL AND e.suffix_name != '', CONCAT(' ', e.suffix_name), '')
                       ) AS full_name,
                       SUM(CASE WHEN a.attendance_id IS NOT NULL 
                                AND a.status IN ('Present','Undertime','Late','Overtime') 
                                THEN 1 ELSE 0 END) AS days_present,
                       SUM(CASE WHEN a.status='On Leave' THEN 1 ELSE 0 END) AS days_on_leave,
                       SUM(a.working_hours) AS total_working_hours,
                       SUM(a.late_minutes) AS total_late,
                       SUM(a.undertime_minutes) AS total_undertime,
                       SUM(a.overtime_minutes) AS total_overtime,
                       SUM(CASE WHEN a.status='Absent' THEN 1 ELSE 0 END) AS days_absent
                FROM hr_employees e
                LEFT JOIN hr_daily_attendance a 
                       ON e.employee_id = a.employee_id
                       AND a.attendance_date BETWEEN ? AND ?
                WHERE e.status='Active'
                GROUP BY e.employee_id";

        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param('ss', $start_date, $end_date);
        $stmt->execute();
        return $stmt->get_result();
    }
}
