<?php
require_once 'class/patient.php';

class Caller {
    private $conn;

    public function __construct($db) {
        $this->conn = $db;
    }

public function callEmr($patient_id) {
    $stmt = $this->conn->prepare("SELECT * FROM p_emr WHERE patient_id = ?");
    $stmt->bind_param("i", $patient_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        return $result->fetch_assoc();
    } else {
        throw new Exception("No medical history found for patient ID: " . $patient_id);
    }
}


public function callHistory($patient_id) {
    $stmt = $this->conn->prepare("SELECT * FROM p_previous_medical_history WHERE patient_id = ?");
    $stmt->bind_param("i", $patient_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        return $result->fetch_assoc();
    } else {
        throw new Exception("No previous medical history found for patient ID: " . $patient_id);
    }
}

public function callBeddings ($patient_id) {
    $stmt = $this->conn->prepare("SELECT * FROM p_bed_assignments WHERE patient_id = ?");
    $stmt->bind_param("i", $patient_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        return $result->fetch_assoc();
    } else {
        throw new Exception("No beddings found for patient ID: " . $patient_id);
    }
}

}

?>