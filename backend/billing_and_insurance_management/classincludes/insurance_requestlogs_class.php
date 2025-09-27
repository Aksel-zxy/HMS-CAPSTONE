<?php
class InsuranceRequestLogs {
    private $conn;

    public function __construct($db) {
        $this->conn = $db;
    }

    public function getAllRequests() {
        $stmt = $this->conn->prepare("SELECT i.request_id, i.patient_id, i.insurance_number, i.insurance_type, i.notes, i.status, p.fname, p.mname, p.lname,
        concat(p.fname, ' ', IFNULL(p.mname, ''), ' ', p.lname) AS full_name
                                      FROM insurance_request i
                                      JOIN patientinfo p ON i.patient_id = p.patient_id
                                      ORDER BY i.request_id DESC");
        
        $stmt->execute();
        $result = $stmt->get_result();
        return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    }

 
}
