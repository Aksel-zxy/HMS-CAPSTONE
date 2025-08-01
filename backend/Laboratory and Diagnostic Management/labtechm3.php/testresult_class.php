<?php

class labdiagnostic_testresults {
    private $conn;
    private $table = "labdiagnostic_testresults";

    // Properties
    public $result_id;
    public $appointment_id;
    public $performed_by;
    public $verified_by;
    public $result_date;
    public $verification_date;
    public $status;
    public $conclusion;

    public function __construct($db) {
        $this->conn = $db;
    }

    // Get all test results
    public function getAll() {
        $query = "SELECT tr.*, 
                         p.name AS patient_name, 
                         t.test_name, 
                         s.name AS performer_name, 
                         v.name AS verifier_name 
                  FROM " . $this->table . " tr
                  LEFT JOIN labdiagnostic_appointments a ON tr.appointment_id = a.appointment_id
                  LEFT JOIN patients p ON a.patient_id = p.patient_id
                  LEFT JOIN labdiagnostic_tests t ON a.test_id = t.test_id
                  LEFT JOIN staff s ON tr.performed_by = s.staff_id
                  LEFT JOIN staff v ON tr.verified_by = v.staff_id
                  ORDER BY tr.result_date ASC";

        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt;
    }

    // Get a single test result by ID
    public function getSingle() {
        $query = "SELECT tr.*, 
                         p.name AS patient_name, 
                         t.test_name, 
                         s.name AS performer_name, 
                         v.name AS verifier_name 
                  FROM " . $this->table . " tr
                  LEFT JOIN labdiagnostic_appointments a ON tr.appointment_id = a.appointment_id
                  LEFT JOIN patients p ON a.patient_id = p.patient_id
                  LEFT JOIN labdiagnostic_tests t ON a.test_id = t.test_id
                  LEFT JOIN staff s ON tr.performed_by = s.staff_id
                  LEFT JOIN staff v ON tr.verified_by = v.staff_id
                  WHERE tr.result_id = ?";

        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("i", $this->result_id);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_assoc();
    }

    // Create a new test result
    public function create() {
        $query = "INSERT INTO " . $this->table . "
                  (appointment_id, performed_by,verified_by, result_date, status, conclusion)
                  VALUES (?, ?, ?, ?, ?,?)";

        $stmt = $this->conn->prepare($query);

        // Clean data
        $this->appointment_id = htmlspecialchars(strip_tags($this->appointment_id));
        $this->performed_by = htmlspecialchars(strip_tags($this->performed_by));
        $this->verified_by = !empty($this->verified_by) ? htmlspecialchars(strip_tags($this->verified_by)) : null;
        $this->result_date = htmlspecialchars(strip_tags($this->result_date));
        $this->status = htmlspecialchars(strip_tags($this->status));
        $this->conclusion = htmlspecialchars(strip_tags($this->conclusion));

        $stmt->bind_param("iiisss", 
            $this->appointment_id,
            $this->performed_by,
            $this->verified_by,
            $this->result_date,
            $this->status,
            $this->conclusion
        );

        return $stmt->execute();
    }

    // Update a test result
    public function update() {
        $query = "UPDATE " . $this->table . "
                  SET status = ?, conclusion = ?, verified_by = ?, verification_date = ?
                  WHERE result_id = ?";

        $stmt = $this->conn->prepare($query);

        // Clean data
        $this->status = htmlspecialchars(strip_tags($this->status));
        $this->conclusion = htmlspecialchars(strip_tags($this->conclusion));
        $this->verified_by = htmlspecialchars(strip_tags($this->verified_by));
        $this->verification_date = htmlspecialchars(strip_tags($this->verification_date));
        $this->result_id = htmlspecialchars(strip_tags($this->result_id));

        $stmt->bind_param("ssssi", 
            $this->status,
            $this->conclusion,
            $this->verified_by,
            $this->verification_date,
            $this->result_id
        );

        return $stmt->execute();
    }

    // Delete a test result
    public function delete() {
        $query = "DELETE FROM " . $this->table . " WHERE result_id = ?";
        $stmt = $this->conn->prepare($query);

        $this->result_id = htmlspecialchars(strip_tags($this->result_id));

        $stmt->bind_param("i", $this->result_id);
        return $stmt->execute();
    }

    // Get test result count by status
    public function getCountByStatus() {
        $query = "SELECT status, COUNT(*) as count FROM " . $this->table . " GROUP BY status";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt;
    }
}
?>
