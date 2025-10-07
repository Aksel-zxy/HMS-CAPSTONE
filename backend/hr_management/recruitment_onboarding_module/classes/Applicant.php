<?php
class Applicant {
    private $conn;

    public function __construct($conn) {
        $this->conn = $conn;
    }

    // Get all applicants, excluding certain statuses (from hr_applicant table only).
    public function getAllApplicants($excludeStatus = ['Hired', 'Rejected']) {
        $statusFilter = '';
        if (!empty($excludeStatus)) {
            $placeholders = implode(',', array_fill(0, count($excludeStatus), '?'));
            $statusFilter = "WHERE status NOT IN ($placeholders)";
        }

        $sql = "SELECT * FROM hr_applicant $statusFilter ORDER BY date_applied DESC";
        $stmt = $this->conn->prepare($sql);

        if (!empty($excludeStatus)) {
            $types = str_repeat('s', count($excludeStatus));
            $params = array_merge([$types], $excludeStatus);
            $tmp = [];
            foreach ($params as $key => $value) {
                $tmp[$key] = &$params[$key];
            }
            call_user_func_array([$stmt, 'bind_param'], $tmp);
        }

        $stmt->execute();
        $result = $stmt->get_result();
        $applicants = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
        $stmt->close();

        return $applicants;
    }

    // Get applicant by ID.
    public function getById($applicant_id) {
        $stmt = $this->conn->prepare("
            SELECT * FROM hr_applicant WHERE applicant_id = ?
        ");
        $stmt->bind_param("i", $applicant_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $data = $result->fetch_assoc();
        $stmt->close();

        return $data;
    }

    // Get applicant documents grouped by type.
    public function getApplicantDocuments($applicant_id) {
        $stmt = $this->conn->prepare("
            SELECT document_type, file_path 
            FROM hr_applicant_documents 
            WHERE applicant_id = ?
        ");
        $stmt->bind_param("i", $applicant_id);
        $stmt->execute();
        $result = $stmt->get_result();

        $documents = [];
        while ($row = $result->fetch_assoc()) {
            $type = !empty($row['document_type']) ? $row['document_type'] : 'Others';
            $documents[$type][] = [
                'path' => $row['file_path'],
                'name' => basename($row['file_path'])
            ];
        }

        $stmt->close();
        return $documents;
    }

    // Schedule an interview.
    public function scheduleInterview($applicant_id, $interview_date, $notes = null) {
        $timestamp = strtotime($interview_date);
        $interview_date = $timestamp ? date("Y-m-d", $timestamp) : date("Y-m-d");

        if (empty($notes)) {
            $notes = "Interview scheduled on " . $interview_date;
        }

        // Insert tracking (instead of updating hr_applicant)
        $stmt = $this->conn->prepare("
            INSERT INTO hr_applicant_tracking 
                (applicant_id, status, interview_date, interview_status, notes, update_at)
            VALUES (?, 'Pending Interview', ?, 'Scheduled', ?, NOW())
        ");
        $stmt->bind_param("iss", $applicant_id, $interview_date, $notes);
        $stmt->execute();
        $stmt->close();
    }

    // Update applicant status.
    public function updateStatus($applicant_id, $status, $notes = null) {
        if (empty($notes)) {
            $notes = "Applicant marked as " . $status;
        }

        // Set interview status rules
        $interview_status = in_array($status, ['Hired', 'Rejected'])
            ? 'N/A'
            : 'Completed';

        // Kung Hired o Rejected lang, update din sa hr_applicant
        if (in_array($status, ['Hired', 'Rejected'])) {
            $stmt = $this->conn->prepare("
                UPDATE hr_applicant 
                SET status = ? 
                WHERE applicant_id = ?
            ");
            $stmt->bind_param("si", $status, $applicant_id);
            $stmt->execute();
            $stmt->close();
        }

        // Always insert sa tracking history
        $stmt = $this->conn->prepare("
            INSERT INTO hr_applicant_tracking 
                (applicant_id, status, interview_status, notes, update_at)
            VALUES (?, ?, ?, ?, NOW())
        ");
        $stmt->bind_param("isss", $applicant_id, $status, $interview_status, $notes);
        $stmt->execute();
        $stmt->close();
    }

    // Get latest tracking info (including update_at).
    public function getLatestTracking($applicant_id) {
        $stmt = $this->conn->prepare("
            SELECT status, notes, update_at, interview_date
            FROM hr_applicant_tracking 
            WHERE applicant_id = ? 
            ORDER BY tracking_id DESC 
            LIMIT 1
        ");
        $stmt->bind_param("i", $applicant_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $latest = $result->fetch_assoc();
        $stmt->close();

        return $latest;
    }

    // Get applicants whose latest tracking status = "Hired"
    public function getReadyForRegistration() {
        $sql = "
            SELECT a.* 
            FROM hr_applicant a
            INNER JOIN (
                SELECT applicant_id, MAX(tracking_id) AS last_track
                FROM hr_applicant_tracking
                GROUP BY applicant_id
            ) t ON a.applicant_id = t.applicant_id
            INNER JOIN hr_applicant_tracking h ON h.tracking_id = t.last_track
            WHERE h.status = 'Hired'
            ORDER BY a.date_applied DESC
        ";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        $result = $stmt->get_result();
        $applicants = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
        $stmt->close();

        return $applicants;
    }

    public function getHiredApplicants() {
        $stmt = $this->conn->prepare("
            SELECT * FROM hr_applicant 
            WHERE status = 'Hired' 
            ORDER BY date_applied DESC
        ");
        $stmt->execute();
        $result = $stmt->get_result();
        $applicants = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
        $stmt->close();

        return $applicants;
    }

    public function getRejectedApplicants() {
        $stmt = $this->conn->prepare("
            SELECT * FROM hr_applicant 
            WHERE status = 'Rejected' 
            ORDER BY date_applied DESC
        ");
        $stmt->execute();
        $result = $stmt->get_result();
        $applicants = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
        $stmt->close();

        return $applicants;
    }
}
