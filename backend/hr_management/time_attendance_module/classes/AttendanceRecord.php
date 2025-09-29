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

    public function getStatusText($row) {
        if (empty($row['time_in']) && empty($row['time_out']) && $row['status'] != 'On Leave') {
            return 'Absent';
        } elseif (!empty($row['late_minutes']) && $row['late_minutes'] > 0) {
            return 'Late';
        } elseif (!empty($row['undertime_minutes']) && $row['undertime_minutes'] > 0) {
            return 'Undertime';
        } elseif (!empty($row['overtime_minutes']) && $row['overtime_minutes'] > 0) {
            return 'Overtime';
        } else {
            return !empty($row['status']) ? $row['status'] : '-';
        }
    }

    public function getStatusClass($statusText) {
        return 'status-' . strtolower(str_replace(' ', '', $statusText));
    }
}
