<?php
class ApplicantManager {
    private $conn;

    public function __construct($dbConnection) {
        $this->conn = $dbConnection;
    }

    public function getHiredApplicants() {
        $stmt = $this->conn->prepare("
            SELECT at.tracking_id, at.applicant_id, at.status, at.update_at,
                a.first_name, a.middle_name, a.last_name, a.suffix_name,
                a.role, a.email, a.phone
            FROM hr_applicant_tracking AS at
            JOIN hr_applicant AS a ON at.applicant_id = a.applicant_id
            WHERE at.status = 'Hired'
            ORDER BY at.tracking_id DESC
        ");
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    public function getRejectedApplicants() {
        $stmt = $this->conn->prepare("
            SELECT at.tracking_id, at.applicant_id, at.status, at.update_at,
                a.first_name, a.middle_name, a.last_name, a.suffix_name,
                a.role, a.email, a.phone
            FROM hr_applicant_tracking AS at
            JOIN hr_applicant AS a ON at.applicant_id = a.applicant_id
            WHERE at.status = 'Rejected'
            ORDER BY at.tracking_id DESC
        ");
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
