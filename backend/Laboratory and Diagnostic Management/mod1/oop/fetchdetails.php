<?php
class Patient {
    private $conn;
    private $table = "patientinfo";

    public function __construct($db) {
        $this->conn = $db;
    }

    public function getAllPatients() {
        $query = "SELECT * FROM " . $this->table;
        $result = $this->conn->query($query);
        return $result->fetch_all(MYSQLI_ASSOC);
    }

    public function getPatientById($id) {
        $stmt = $this->conn->prepare("SELECT * FROM " . $this->table . " WHERE patient_id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_assoc();
    }
}
?>