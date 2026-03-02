<?php
class AttendanceRecord {
    private $conn;

    public function __construct($conn) {
        $this->conn = $conn;
    }

    // Get records by date
    public function getDailyRecords($date, $search = null) {

        $sql = "
            SELECT 
                a.employee_id,
                CONCAT(
                    e.first_name, ' ',
                    IFNULL(e.middle_name, ''), ' ',
                    e.last_name, ' ',
                    IFNULL(e.suffix_name, '')
                ) AS full_name,
                e.role,
                e.profession,
                a.attendance_date,
                a.time_in,
                a.time_out,
                a.working_hours,
                a.late_minutes,
                a.undertime_minutes,
                a.overtime_minutes,
                a.status
            FROM hr_daily_attendance a
            INNER JOIN hr_employees e 
                ON a.employee_id = e.employee_id
            WHERE a.attendance_date = ?
        ";

        if (!empty($search)) {
            $sql .= " AND (
                a.employee_id LIKE ? OR
                a.status LIKE ? OR
                CONCAT(
                    e.first_name, ' ',
                    IFNULL(e.middle_name, ''), ' ',
                    e.last_name, ' ',
                    IFNULL(e.suffix_name, '')
                ) LIKE ? OR
                e.role LIKE ? OR
                e.profession LIKE ?
            )";
        }

        $sql .= " ORDER BY a.employee_id ASC";

        $stmt = $this->conn->prepare($sql);

        if (!empty($search)) {
            $searchParam = "%{$search}%";
            $stmt->bind_param(
                "ssssss",
                $date,
                $searchParam,
                $searchParam,
                $searchParam,
                $searchParam,
                $searchParam
            );
        } else {
            $stmt->bind_param("s", $date);
        }

        $stmt->execute();
        return $stmt->get_result();
    }

    // Determine the proper display status for a record
    public function getStatusText($row) {
        $status = $row['status'] ?? 'Absent';

        // If the status itself is Off Duty → return as is
        if ($status === 'Off Duty') {
            return 'Off Duty';
        }

        // Half Day leave but did not attend
        if (in_array($status, ['On Leave', 'On Leave (Half Day)', 'Absent (Half Day)']) &&
            empty($row['time_in']) && empty($row['time_out'])) {
            return $status; // Keep as is
        }

        // If no time in/out and not on leave → Absent
        if (empty($row['time_in']) && empty($row['time_out']) &&
            !in_array($status, ['On Leave', 'On Leave (Half Day)'])) {
            return 'Absent';
        }

        // If worked partially → prioritize OT/UT/Late
        if (!empty($row['overtime_minutes']) && $row['overtime_minutes'] > 0) {
            return 'Overtime';
        }

        if (!empty($row['undertime_minutes']) && $row['undertime_minutes'] > 0) {
            return 'Undertime';
        }

        if (!empty($row['late_minutes']) && $row['late_minutes'] > 0) {
            return 'Late';
        }

        // Half Day actually worked
        if ($status === 'Half Day') {
            return 'Half Day';
        }

        // Fallback to status
        return $status;
    }

    // Map status text to a CSS class
    public function getStatusClass($statusText) {
        // Remove parentheses, spaces → lowercase
        $class = strtolower($statusText);
        $class = str_replace([' ', '(', ')'], ['', '', ''], $class);
        return 'status-' . $class;
    }

}
