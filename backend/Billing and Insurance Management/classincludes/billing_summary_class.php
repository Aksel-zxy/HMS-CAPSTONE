<?php
class BillingSummary {
    private $conn;

    public function __construct($conn) {
        $this->conn = $conn;
    }

    // Get all billing summary records for a specific patient
    public function getBillingSummaryByPatient($patient_id) {
        $query = "SELECT * FROM billingsummary WHERE patient_id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("i", $patient_id);
        $stmt->execute();
        return $stmt->get_result();
    }

    // Add a billing summary record for a specific patient
    public function addBillingSummary($patient_id, $particulars, $actual_charges, $vat, $amount_of_discount, $out_of_pocket) {
        $query = "INSERT INTO billingsummary (patient_id, particulars, actual_charges, vat, amount_of_discount, out_of_pocket) VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("isssss", $patient_id, $particulars, $actual_charges, $vat, $amount_of_discount, $out_of_pocket);
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