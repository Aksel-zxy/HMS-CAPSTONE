<?php
class ApplicantManager {
    private $conn;

    public function __construct($dbConnection) {
        $this->conn = $dbConnection;
    }

    public function getHiredApplicants() {
        $stmt = $this->conn->prepare("
            SELECT a.applicant_id, a.first_name, a.middle_name, a.last_name, a.suffix_name,
                a.role, a.email, a.phone, a.status, t.update_at
            FROM hr_applicant a
            INNER JOIN (
                SELECT applicant_id, MAX(update_at) AS update_at
                FROM hr_applicant_tracking
                WHERE status = 'Hired'
                GROUP BY applicant_id
            ) t ON a.applicant_id = t.applicant_id
            WHERE a.status = 'Hired'
            ORDER BY t.update_at DESC
        ");
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    public function getRejectedApplicants() {
        $stmt = $this->conn->prepare("
            SELECT a.applicant_id, a.first_name, a.middle_name, a.last_name, a.suffix_name,
                a.role, a.email, a.phone, a.status, t.update_at
            FROM hr_applicant a
            INNER JOIN (
                SELECT applicant_id, MAX(update_at) AS update_at
                FROM hr_applicant_tracking
                WHERE status = 'Rejected'
                GROUP BY applicant_id
            ) t ON a.applicant_id = t.applicant_id
            WHERE a.status = 'Rejected'
            ORDER BY t.update_at DESC
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
