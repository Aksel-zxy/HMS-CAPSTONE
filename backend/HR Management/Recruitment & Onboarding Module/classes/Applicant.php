<?php

class Applicant {
    private $conn;

    public function __construct($conn) {
        $this->conn = $conn;
    }

    // Get all applicants as array
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
            $stmt->bind_param($types, ...$excludeStatus);
        }

        $stmt->execute();
        $result = $stmt->get_result();
        $applicants = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
        $stmt->close();
        return $applicants;
    }

    // Get applicant by ID
    public function getById($applicant_id) {
        $stmt = $this->conn->prepare("SELECT * FROM hr_applicant WHERE applicant_id = ?");
        $stmt->bind_param("i", $applicant_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $data = $result->fetch_assoc();
        $stmt->close();
        return $data;
    }

    // Get documents grouped by type
    public function getApplicantDocuments($applicant_id) {
        $stmt = $this->conn->prepare("SELECT document_type, file_path FROM hr_applicant_documents WHERE applicant_id = ?");
        $stmt->bind_param("i", $applicant_id);
        $stmt->execute();
        $result = $stmt->get_result();

        $documents = [];
        while ($row = $result->fetch_assoc()) {
            $documents[$row['document_type']][] = [
                'path' => $row['file_path'],
                'name' => basename($row['file_path'])
            ];
        }

        $stmt->close();
        return $documents;
    }

    // Schedule interview
    public function scheduleInterview($applicant_id, $interview_date, $notes = null) {
        // Validate date
        $timestamp = strtotime($interview_date);
        $interview_date = $timestamp ? date("Y-m-d", $timestamp) : date("Y-m-d");

        if (empty($notes)) {
            $notes = "Interview scheduled on " . $interview_date;
        }

        // Update applicant status
        $stmt = $this->conn->prepare("UPDATE hr_applicant SET status = 'Pending Interview' WHERE applicant_id = ?");
        $stmt->bind_param("i", $applicant_id);
        $stmt->execute();
        $stmt->close();

        // Insert tracking
        $stmt = $this->conn->prepare("
            INSERT INTO hr_applicant_tracking 
            (applicant_id, status, interview_date, interview_status, notes)
            VALUES (?, 'Pending Interview', ?, 'Scheduled', ?)
        ");
        $stmt->bind_param("iss", $applicant_id, $interview_date, $notes);
        $stmt->execute();
        $stmt->close();
    }

    // Update applicant status (Hired, Rejected, Done Interview, etc.)
    public function updateStatus($applicant_id, $status, $notes = null) {
        if (empty($notes)) {
            $notes = "Applicant marked as " . $status;
        }

        // Determine interview_status
        $interview_status = in_array($status, ['Hired', 'Rejected']) ? 'N/A' : 'Completed';

        // Update applicant table
        $stmt = $this->conn->prepare("UPDATE hr_applicant SET status = ? WHERE applicant_id = ?");
        $stmt->bind_param("si", $status, $applicant_id);
        $stmt->execute();
        $stmt->close();

        // Insert tracking
        $stmt = $this->conn->prepare("
            INSERT INTO hr_applicant_tracking
            (applicant_id, status, interview_status, notes)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->bind_param("isss", $applicant_id, $status, $interview_status, $notes);
        $stmt->execute();
        $stmt->close();
    }

    // Get latest tracking info
    public function getLatestTracking($applicant_id) {
        $stmt = $this->conn->prepare("
            SELECT status, interview_date, notes
            FROM hr_applicant_tracking
            WHERE applicant_id = ?
            ORDER BY tracking_id DESC
            LIMIT 1
        ");
        $stmt->bind_param("i", $applicant_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();

        return $row ?: ['status' => null, 'interview_date' => null, 'notes' => null];
    }

    // Get only Hired applicants
    public function getHiredApplicants() {
        $stmt = $this->conn->prepare("SELECT * FROM hr_applicant WHERE status = 'Hired' ORDER BY date_applied DESC");
        $stmt->execute();
        $result = $stmt->get_result();
        $applicants = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
        $stmt->close();
        return $applicants;
    }

    // Get only Rejected applicants
    public function getRejectedApplicants() {
        $stmt = $this->conn->prepare("SELECT * FROM hr_applicant WHERE status = 'Rejected' ORDER BY date_applied DESC");
        $stmt->execute();
        $result = $stmt->get_result();
        $applicants = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
        $stmt->close();
        return $applicants;
    }
}

