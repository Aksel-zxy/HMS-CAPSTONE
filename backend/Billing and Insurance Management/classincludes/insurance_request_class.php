<?php
// Insurance Request Class

class InsuranceRequest {
    private $conn;

    public function __construct($db) {
        $this->conn = $db;
    }

    // Create a new insurance request
   public function create($patient_id, $insurance_number, $insurance_type, $notes) {
    $stmt = $this->conn->prepare("
        INSERT INTO insurance_request (patient_id, insurance_number, insurance_type, notes)
        VALUES (?, ?, ?, ?)
    ");

    if (!$stmt) {
        // Optional: Debug SQL error
        die("Prepare failed: " . $this->conn->error);
    }

    $stmt->bind_param("iiss", $patient_id, $insurance_number, $insurance_type, $notes);
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
    //for summoning the patients
   public function insurance() {
    $stmt = $this->conn->prepare("SELECT 
       patient_id, fname, mname, lname, CONCAT(fname, ' ', IFNULL(mname, ''), ' ', lname) AS full_name from patientinfo");
    $stmt->execute();
    $result = $stmt->get_result();
    return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
}

    
}