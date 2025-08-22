<?php

class Applicant {
    private $conn;

    public function __construct($conn) {
        $this->conn = $conn;
    }

    public function getAllApplicants() {
        $sql = "SELECT * FROM hr_applicant ORDER BY date_applied DESC";
        return $this->conn->query($sql);
    }

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

    public function getById($applicant_id) {
        $stmt = $this->conn->prepare("SELECT * FROM hr_applicant WHERE applicant_id = ?");
        $stmt->bind_param("i", $applicant_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $data = $result->fetch_assoc();
        $stmt->close();
        return $data;
    }

    public function addTracking($applicantId, $status, $interviewDate, $interviewStatus, $notes) {
        $sql = "INSERT INTO hr_applicant_tracking (applicant_id, status, interview_date, interview_status, notes) 
                VALUES (?, ?, ?, ?, ?)";
        $stmt = $this->conn->prepare($sql);

        if (empty($interviewDate)) {
            $interviewDate = null;
        }

        $stmt->bind_param("issss", $applicantId, $status, $interviewDate, $interviewStatus, $notes);

        if ($stmt->execute()) {
            return true;
        } else {
            echo "❌ SQL Error: " . $stmt->error . "<br>";
            return false;
        }
    }

    public function scheduleInterview($applicant_id, $interview_date, $notes = null) {
        $interview_date = date("Y-m-d", strtotime($interview_date));
        if (empty($notes)) {
            $notes = "Interview scheduled on " . $interview_date;
        }

        $stmt = $this->conn->prepare("UPDATE hr_applicant SET status = 'Pending Interview' WHERE applicant_id = ?");
        if (!$stmt) {
            die("Error preparing update: " . $this->conn->error);
        }
        $stmt->bind_param("i", $applicant_id);
        if (!$stmt->execute()) {
            die("Error updating applicant: " . $stmt->error);
        }
        $stmt->close();

        $stmt = $this->conn->prepare("
            INSERT INTO hr_applicant_tracking 
            (applicant_id, status, interview_date, interview_status, notes) 
            VALUES (?, 'Pending Interview', ?, 'Scheduled', ?)
        ");
        if (!$stmt) {
            die("Error preparing insert (scheduleInterview): " . $this->conn->error);
        }
        $stmt->bind_param("iss", $applicant_id, $interview_date, $notes);
        if (!$stmt->execute()) {
            die("Error inserting tracking record (scheduleInterview): " . $stmt->error);
        }
        $stmt->close();
    }

    public function updateStatus($applicant_id, $status, $notes = null) {
        if (empty($notes)) {
            $notes = "Applicant marked as " . $status;
        }

        $stmt = $this->conn->prepare("UPDATE hr_applicant SET status = ? WHERE applicant_id = ?");
        if (!$stmt) {
            die("Error preparing update: " . $this->conn->error);
        }
        $stmt->bind_param("si", $status, $applicant_id);
        if (!$stmt->execute()) {
            die("Error updating applicant: " . $stmt->error);
        }
        $stmt->close();

        $stmt = $this->conn->prepare("
            INSERT INTO hr_applicant_tracking 
            (applicant_id, status, interview_status, notes) 
            VALUES (?, ?, 'Completed', ?)
        ");
        if (!$stmt) {
            die("Error preparing insert (updateStatus): " . $this->conn->error);
        }
        $stmt->bind_param("iss", $applicant_id, $status, $notes);
        if (!$stmt->execute()) {
            die("Error inserting tracking record (updateStatus): " . $stmt->error);
        }
        $stmt->close();
    }

    public function getLatestTracking($applicant_id) {
        $sql = "SELECT status, interview_date, notes
                FROM hr_applicant_tracking
                WHERE applicant_id = ?
                ORDER BY tracking_id DESC
                LIMIT 1";
        $stmt = $this->conn->prepare($sql);
        if (!$stmt) {
            die("Error preparing select: " . $this->conn->error);
        }
        $stmt->bind_param("i", $applicant_id);
        if (!$stmt->execute()) {
            die("Error executing select: " . $stmt->error);
        }
        $result = $stmt->get_result();
        return $result->fetch_assoc();
    }

}
