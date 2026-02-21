<?php
class AttendanceRecord {
    private $conn;

    public function __construct($conn) {
        $this->conn = $conn;
    }

    // Get records by date
    public function getDailyRecords($date) {
        $stmt = $this->conn->prepare("
            SELECT 
                employee_id,
                attendance_date,
                time_in,
                time_out,
                working_hours,
                late_minutes,
                undertime_minutes,
                overtime_minutes,
                status
            FROM hr_daily_attendance
            WHERE attendance_date = ?
            ORDER BY employee_id ASC
        ");
        $stmt->bind_param("s", $date);
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
