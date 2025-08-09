<?php
class Patient {
    private $conn;
    private $appointmentsTable = "p_appointments";
    private $patientTable = "patientinfo";

    public function __construct($db) {
        $this->conn = $db;
    }

    // Get all patients with appointment details
    public function getAllPatients() {
        $query = "
            SELECT p.*, a.*
            FROM {$this->patientTable} p
            INNER JOIN {$this->appointmentsTable} a 
                ON p.patient_id = a.patient_id
        ";
        $result = $this->conn->query($query);
        return $result->fetch_all(MYSQLI_ASSOC);
    }

    // Get single patient with appointment details
    public function getPatientById($id) {
        $stmt = $this->conn->prepare("
            SELECT p.*, a.*
            FROM {$this->patientTable} p
            INNER JOIN {$this->appointmentsTable} a 
                ON p.patient_id = a.patient_id
            WHERE p.patient_id = ?
        ");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_assoc();
    }
}
?>
