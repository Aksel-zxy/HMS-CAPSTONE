<?php
// Insurance Request Class

class pdf_class {
    private $conn;

    public function __construct($db) {
        $this->conn = $db;
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