<?php
// Insurance Request Class

class InsuranceRequest {
    private $conn;

    public function __construct($db) {
        $this->conn = $db;
    }

    // Create a new insurance request
    public function create($patient_id, $billing_id, $insurance_type, $coverage_covered, $notes) {
        $stmt = $this->conn->prepare("INSERT INTO insurance_request (patient_id, billing_id, insurance_type, coverage_covered, notes, status) VALUES (?, ?, ?, ?, ?, 'pending')");
        $stmt->bind_param("iisss", $patient_id, $billing_id, $insurance_type, $coverage_covered, $notes);
        return $stmt->execute();
    }

    // Get all insurance requests
    public function getAll() {
        $stmt = $this->conn->prepare("SELECT * FROM insurance_request ORDER BY request_id DESC");
        $stmt->execute();
        $result = $stmt->get_result();
        return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    }

    // Get a single insurance request by ID
    public function getById($request_id) {
        $stmt = $this->conn->prepare("SELECT * FROM insurance_request WHERE request_id = ?");
        $stmt->bind_param("i", $request_id);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result ? $result->fetch_assoc() : null;
    }

    // Update status (approve/decline)
    public function updateStatus($request_id, $status) {
        $stmt = $this->conn->prepare("UPDATE insurance_request SET status = ? WHERE request_id = ?");
        $stmt->bind_param("si", $status, $request_id);
        return $stmt->execute();
    }
}