<?php

class labdiagnostic_appointments  {
    private $conn;
    private $table = "labdiagnostic_appointments";

    //properties
    public $appointment_id;
    public $test_id;
    public $patient_id;
    public $doctor_id;
    public $scheduled_datetime;
    public $end_datetime;
    public $status;
    public $notes;
   
    public function __construct($db) {
        $this->conn = $db;
    }

    //get all available test from labdiagnostic_tests, name for patient and doctor
    public function getAll() {
        $query = "SELECT a.*,
        t.test_name,
        p.name as patient_name,
        d.name as doctor_name
        FROM " .$this->table . " a 
        LEFT JOIN labdiagnostic_tests t ON a.test_id = t.test_id
        LEFT JOIN patients p ON a.patient_id = p.patient_id
        LEFT JOIN doctors d ON a.doctor_id = d.doctor_id
        ORDER BY a.scheduled_datetime ASC";

        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt;
    }

    //get single appointment
    public function getSingle() {
        $query = "SELECT a.*, 
        t.test_name,
        p.name as patient_name,
        d.name as doctor_name
        FROM " .$this->table . " a
        LEFT JOIN labdiagnostic_tests t ON a.test_id = t.test_id
        LEFT JOIN patients p ON a.patient_id = p.patient_id
        LEFT JOIN doctors d ON a.doctor_id = d.doctor_id
        WHERE a.appointment_id = ?";

        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("i", $this->appointment_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();

        if($row) {
            $this->test_id = $row['test_id'];
            $this->patient_id = $row['patient_id'];
            $this->doctor_id = $row['doctor_id'];
            $this->scheduled_datetime = $row['scheduled_datetime'];
            $this->end_datetime = $row['end_datetime'];
            $this->status = $row['status'];
            $this->notes = $row['notes'];
            return true;
        }
        return false;
    }

    //create appointment
    public function create() {
        $query = "INSERT INTO " .$this->table . "
        (test_id, patient_id, doctor_id, scheduled_datetime, end_datetime, status, notes)
        VALUES (?, ?, ?, ?, ?, ?, ?)";

        $stmt = $this->conn->prepare($query);

        //clean input
        $this->test_id = htmlspecialchars(strip_tags($this->test_id));
        $this->patient_id = htmlspecialchars(strip_tags($this->patient_id));
        $this->doctor_id = htmlspecialchars(strip_tags($this->doctor_id));
        $this->scheduled_datetime = htmlspecialchars(strip_tags($this->scheduled_datetime));
        $this->end_datetime = htmlspecialchars(strip_tags($this->end_datetime));
        $this->status = htmlspecialchars(strip_tags($this->status));
        $this->notes = htmlspecialchars(strip_tags($this->notes));

        $stmt->bind_param(
            "iiissss",
            $this->test_id,
            $this->patient_id,
            $this->doctor_id,
            $this->scheduled_datetime,
            $this->end_datetime,
            $this->status,
            $this->notes
        );

        return $stmt->execute();
    }

    //update appointment
    public function update() {
        $query = "UPDATE " . $this->table  . "
        SET test_id = ?, patient_id = ?, doctor_id = ?, scheduled_datetime = ?,
        end_datetime = ?, status = ?, notes = ? WHERE appointment_id = ?";

        $stmt = $this->conn->prepare($query);

        //clean input
        $this->appointment_id = htmlspecialchars(strip_tags($this->appointment_id));
        $this->test_id = htmlspecialchars(strip_tags($this->test_id));
        $this->patient_id = htmlspecialchars(strip_tags($this->patient_id));
        $this->doctor_id = htmlspecialchars(strip_tags($this->doctor_id));
        $this->scheduled_datetime = htmlspecialchars(strip_tags($this->scheduled_datetime));
        $this->end_datetime = htmlspecialchars(strip_tags($this->end_datetime));
        $this->status = htmlspecialchars(strip_tags($this->status));
        $this->notes = htmlspecialchars(strip_tags($this->notes));

        $stmt->bind_param(
            "iiissssi",
            $this->test_id,
            $this->patient_id,
            $this->doctor_id,
            $this->scheduled_datetime,
            $this->end_datetime,
            $this->status,
            $this->notes,
            $this->appointment_id
        );

        return $stmt->execute();
    }

    //delete
    public function delete() {
        $query = "DELETE FROM " . $this->table . " WHERE appointment_id = ?";
        $stmt = $this->conn->prepare($query);
        
        //clean
        $this->appointment_id = htmlspecialchars(strip_tags($this->appointment_id));
        
        $stmt->bind_param("i", $this->appointment_id);
        return $stmt->execute();
    }

    //get date by range
    public function getByDateRange($startDate, $endDate) {
        $query = "SELECT a.*, t.test_name FROM " . $this->table . " a
        LEFT JOIN labdiagnostic_tests t ON a.test_id = t.test_id
        WHERE a.scheduled_datetime BETWEEN ? AND ?
        ORDER BY a.scheduled_datetime ASC";

        $stmt = $this->conn->prepare($query);
        $stmt->bind_param(
            "ss", 
            $startDate,
            $endDate
        ); 

       $stmt->execute();
        return $stmt;
    }

    //get appointment by status
    public function getCountByStatus() {
        $query = "SELECT status, COUNT(*) as count FROM " .$this->table . " GROUP BY status";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt;
    }
}
?>