<?php

class labdiagnostic_tests {
    private $conn;
    private $table = "labdiagnostic_tests";

    // Properties
    public $test_id;
    public $test_code;
    public $test_name;
    public $description;
    public $category;
    public $preparation_instructions;
    public $estimated_duration;
    public $is_active;

    public function __construct($db) {
        $this->conn = $db;
    }

    // Get all tests
    public function getAll() {
        $query = "SELECT * FROM " . $this->table;
        return $this->conn->query($query);
    }

    // Get single test
    public function getSingle() {
        $query = "SELECT * FROM " . $this->table . " WHERE test_id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("i", $this->test_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();

        if ($row) {
            $this->test_code = $row['test_code'];
            $this->test_name = $row['test_name'];
            $this->description = $row['description'];
            $this->category = $row['category'];
            $this->preparation_instructions = $row['preparation_instructions'];
            $this->estimated_duration = $row['estimated_duration'];
            $this->is_active = $row['is_active'];
            return true;
        }

        return false;
    }

    // Create new test
    public function create() {
        $query = "INSERT INTO " . $this->table . "
        (test_code, test_name, description, category, preparation_instructions, estimated_duration, is_active)
        VALUES (?, ?, ?, ?, ?, ?, ?)";

        $stmt = $this->conn->prepare($query);

        // Clean input
        $this->test_code = htmlspecialchars(strip_tags($this->test_code));
        $this->test_name = htmlspecialchars(strip_tags($this->test_name));
        $this->description = htmlspecialchars(strip_tags($this->description));
        $this->category = htmlspecialchars(strip_tags($this->category));
        $this->preparation_instructions = htmlspecialchars(strip_tags($this->preparation_instructions));

        $stmt->bind_param(
            "ssssssi",
            $this->test_code,
            $this->test_name,
            $this->description,
            $this->category,
            $this->preparation_instructions,
            $this->estimated_duration,
            $this->is_active
        );

        return $stmt->execute();
    }

    // Get one test (for editing)
    public function getOne() {
        $query = "SELECT * FROM " . $this->table . " WHERE test_id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("i", $this->test_id);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_assoc();
    }

    // Update test
    public function update() {
        $query = "UPDATE " . $this->table . "
        SET test_code = ?, test_name = ?, description = ?, category = ?, preparation_instructions = ?, estimated_duration = ?, is_active = ?
        WHERE test_id = ?";

        $stmt = $this->conn->prepare($query);

        // Clean input
        $this->test_code = htmlspecialchars(strip_tags($this->test_code));
        $this->test_name = htmlspecialchars(strip_tags($this->test_name));
        $this->description = htmlspecialchars(strip_tags($this->description));
        $this->category = htmlspecialchars(strip_tags($this->category));
        $this->preparation_instructions = htmlspecialchars(strip_tags($this->preparation_instructions));

        $stmt->bind_param(
            "ssssssii",
            $this->test_code,
            $this->test_name,
            $this->description,
            $this->category,
            $this->preparation_instructions,
            $this->estimated_duration,
            $this->is_active,
            $this->test_id
        );

        return $stmt->execute();
    }

    // Delete test and related appointments
    public function delete() {
        try {
            // Delete related appointments
            $query1 = "DELETE FROM labdiagnostic_appointments WHERE test_id = ?";
            $stmt1 = $this->conn->prepare($query1);
            $stmt1->bind_param("i", $this->test_id);
            $stmt1->execute();

            // Delete the test
            $query2 = "DELETE FROM labdiagnostic_tests WHERE test_id = ?";
            $stmt2 = $this->conn->prepare($query2);
            $stmt2->bind_param("i", $this->test_id);

            return $stmt2->execute();
        }   catch (Exception $e) {
            error_log("MySQLi Error (delete): " . $e->getMessage());
            return false;
        }
    }
}

?>
