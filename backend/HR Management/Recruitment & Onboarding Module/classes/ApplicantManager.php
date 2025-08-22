<?php
class ApplicantManager {
    private $conn;

    public function __construct($dbConnection) {
        $this->conn = $dbConnection;
    }

    public function getHiredApplicants() {
        $stmt = $this->conn->prepare("SELECT * FROM hr_applicant_tracking WHERE status = 'Hired' ORDER BY tracking_id DESC");
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    public function getRejectedApplicants() {
        $stmt = $this->conn->prepare("SELECT * FROM hr_applicant_tracking WHERE status = 'Rejected' ORDER BY tracking_id DESC");
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    public function getApplicantDocuments($applicant_id) {
        $stmt = $this->conn->prepare("SELECT * FROM hr_applicant_documents WHERE applicant_id = ?");
        $stmt->bind_param("i", $applicant_id);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
}
