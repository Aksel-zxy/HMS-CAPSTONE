<?php
class InsuranceRequestLogs {
    private $conn;

    public function __construct($db) {
        $this->conn = $db;
    }

    public function getAllRequests() {
        $stmt = $this->conn->prepare("SELECT * FROM insurance_request ORDER BY request_id DESC");
        $stmt->execute();
        $result = $stmt->get_result();
        return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    }
}
