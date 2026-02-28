<?php
class LeaveReport {
    private $conn;

    public function __construct($db) {
        $this->conn = $db;
    }

    public function fetchAll($month = null, $year = null, $search = null) {

        $sql = "SELECT 
                    l.leave_id,
                    l.employee_id,
                    CONCAT(
                        e.first_name, ' ',
                        IFNULL(e.middle_name, ''), ' ',
                        e.last_name, ' ',
                        IFNULL(e.suffix_name, '')
                    ) AS full_name,
                    e.role,
                    e.profession,
                    l.leave_type,
                    l.leave_duration,
                    l.leave_start_date,
                    l.leave_end_date,
                    l.medical_cert,

                    -- total days
                    CASE 
                        WHEN l.leave_duration = 'Half Day' THEN 0.5
                        ELSE DATEDIFF(l.leave_end_date, l.leave_start_date) + 1
                    END AS total_days,

                    l.leave_status,
                    CASE 
                        WHEN l.leave_status = 'Approved' THEN 'Yes'
                        WHEN l.leave_status = 'Rejected' THEN 'No'
                        WHEN l.leave_status = 'Pending' THEN 'Pending'
                    END AS is_paid,
                    l.leave_reason,
                    l.submit_at
                FROM hr_leave l
                JOIN hr_employees e 
                    ON l.employee_id = e.employee_id
                WHERE 1=1
        ";

        // 🔹 Month filter
        if (!empty($month)) {
            $sql .= " AND MONTH(l.leave_start_date) = ?";
        }

        // 🔹 Year filter
        if (!empty($year)) {
            $sql .= " AND YEAR(l.leave_start_date) = ?";
        }

        // 🔹 Search filter (Employee ID, Full Name, Role, Profession)
        if (!empty($search)) {
            $sql .= " AND (
                        l.employee_id LIKE ? OR
                        CONCAT(e.first_name, ' ', IFNULL(e.middle_name,''), ' ', e.last_name, IF(e.suffix_name != '', CONCAT(' ', e.suffix_name), '')) LIKE ? OR
                        e.role LIKE ? OR
                        e.profession LIKE ?
                    )";
        }

        $sql .= " ORDER BY l.submit_at DESC";

        $stmt = $this->conn->prepare($sql);

        // Bind parameters dynamically
        $types = "";
        $params = [];

        if (!empty($month)) { $types .= "i"; $params[] = $month; }
        if (!empty($year))  { $types .= "i"; $params[] = $year; }
        if (!empty($search)) {
            $types .= "ssss";
            $searchParam = "%{$search}%";
            $params[] = $searchParam;
            $params[] = $searchParam;
            $params[] = $searchParam;
            $params[] = $searchParam;
        }

        if ($types) {
            $stmt->bind_param($types, ...$params);
        }

        $stmt->execute();
        return $stmt->get_result();
    }
}


