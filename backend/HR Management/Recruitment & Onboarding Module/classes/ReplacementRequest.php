<?php
class ReplacementRequest {
    private $conn;

    public function __construct($db) {
        $this->conn = $db;
    }

    public function getRequests() {
        $stmt = $this->conn->prepare("SELECT * FROM hr_replacement_requests ORDER BY date_requested DESC");
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_all(MYSQLI_ASSOC);
    }

    public function deleteRequest($id) {
        $stmt = $this->conn->prepare("DELETE FROM hr_replacement_requests WHERE request_id = ?");
        $stmt->bind_param("i", $id);
        return $stmt->execute();
    }
}
