<?php
require_once __DIR__ . '../../../../../SQL/config.php'; // note the leading slash

class Audit {
    private $conn;

    public function __construct(mysqli $dbConn) {
        $this->conn = $dbConn;
    }

    public function getAuditLogs(): array {
        $sql = "
            SELECT 
                s.scheduleID, s.patientID, s.serviceName, s.scheduleDate, s.scheduleTime,
                s.status, s.notes, s.cancel_reason, s.employee_id,
                TRIM(CONCAT(p.fname,' ',COALESCE(p.mname,''),' ',p.lname))   AS patient_name,
                TRIM(CONCAT(e.first_name,' ',COALESCE(e.middle_name,''),' ',e.last_name)) AS employee_name
            FROM dl_schedule s
            LEFT JOIN patientinfo  p ON s.patientID  = p.patient_id
            LEFT JOIN hr_employees e ON s.employee_id = e.employee_id
            ORDER BY s.scheduleID DESC
        ";

        $result = $this->conn->query($sql);
        if (!$result) {
            throw new Exception("Query failed: " . $this->conn->error);
        }

        $logs = [];
        while ($row = $result->fetch_assoc()) {
            $logs[] = $row;
        }
        return $logs;
    }
}

// --- instantiate and fetch
$audit = new Audit($conn);           // ✅ this was missing
$auditLogs = [];
$fetchError = '';

try {
    $auditLogs = $audit->getAuditLogs();
} catch (Exception $e) {
    $fetchError = $e->getMessage();
}
?>
