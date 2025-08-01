<?php

class labdiagnostic_resultitems {
    private $conn;
    private $table = "labdiagnostic_resultitems";

    //properties
    public $item_id;
    public $result_id;
    public $parameter;
    public $result_value;
    public $normal_range;
    public $units;
    public $flag;

    public function __construct($db) {
        $this->conn = $db;
    }

    // Get all result items
    public function getAll() {
       $query = "SELECT ri.*,
                 tr.result_date,
                 p.name AS patient_name,
                 d.name AS doctor_name,
                 t.test_name
          FROM " . $this->table . " ri
          LEFT JOIN labdiagnostic_testresults tr ON ri.result_id = tr.result_id
          LEFT JOIN labdiagnostic_appointments a ON tr.appointment_id = a.appointment_id
          LEFT JOIN patients p ON a.patient_id = p.patient_id
          LEFT JOIN doctors d ON a.doctor_id = d.doctor_id
          LEFT JOIN labdiagnostic_tests t ON a.test_id = t.test_id
          ORDER BY tr.result_date DESC";

        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt; // This still returns the statement, you'll handle ->get_result() in result_items.php for this one
    }

    // Get header information for a specific result
    public function getHeaderInfo($result_id) {
        $query = "SELECT tr.result_date,
                         p.name AS patient_name,
                         d.name AS doctor_name,
                         t.test_name
                  FROM labdiagnostic_testresults tr
                  LEFT JOIN labdiagnostic_appointments a ON tr.appointment_id = a.appointment_id
                  LEFT JOIN patients p ON a.patient_id = p.patient_id
                  LEFT JOIN doctors d ON a.doctor_id = d.doctor_id
                  LEFT JOIN labdiagnostic_tests t ON a.test_id = t.test_id
                  WHERE tr.result_id = ?";

        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("i", $result_id);
        $stmt->execute();
        $result = $stmt->get_result(); // Get the result set
        return $result->fetch_assoc(); // Fetch a single row for header info
    }

    // Get result items by result_id
    public function getByResultId($result_id) {
        $query = "SELECT * FROM " . $this->table . " WHERE result_id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("i", $result_id);
        $stmt->execute();
        return $stmt->get_result(); // Return the result object directly
    }

    // Create item
    public function create() {
        $query = "INSERT INTO " . $this->table . "
                  SET result_id = ?, parameter = ?, result_value = ?, normal_range = ?, units = ?, flag = ?";
        $stmt = $this->conn->prepare($query);

        // Clean data
        $this->result_id = htmlspecialchars(strip_tags($this->result_id));
        $this->parameter = htmlspecialchars(strip_tags($this->parameter));
        $this->result_value = htmlspecialchars(strip_tags($this->result_value));
        $this->normal_range = htmlspecialchars(strip_tags($this->normal_range));
        $this->units = htmlspecialchars(strip_tags($this->units));
        $this->flag = htmlspecialchars(strip_tags($this->flag));

        $stmt->bind_param(
            "isssss",
            $this->result_id,
            $this->parameter,
            $this->result_value,
            $this->normal_range,
            $this->units,
            $this->flag
        );

        return $stmt->execute();
    }

    // Update item
    public function update() {
        $query = "UPDATE " . $this->table . "
                  SET parameter = ?, result_value = ?, normal_range = ?, units = ?, flag = ?
                  WHERE item_id = ?";
        $stmt = $this->conn->prepare($query);

        // Clean data
        $this->parameter = htmlspecialchars(strip_tags($this->parameter));
        $this->result_value = htmlspecialchars(strip_tags($this->result_value));
        $this->normal_range = htmlspecialchars(strip_tags($this->normal_range));
        $this->units = htmlspecialchars(strip_tags($this->units));
        $this->flag = htmlspecialchars(strip_tags($this->flag));
        $this->item_id = htmlspecialchars(strip_tags($this->item_id));

        $stmt->bind_param(
            "sssssi",
            $this->parameter,
            $this->result_value,
            $this->normal_range,
            $this->units,
            $this->flag,
            $this->item_id
        );

        return $stmt->execute();
    }

    // Delete item
    public function delete() {
        $query = "DELETE FROM " . $this->table . " WHERE item_id = ?";
        $stmt = $this->conn->prepare($query);

        // Clean data
        $this->item_id = htmlspecialchars(strip_tags($this->item_id));

        $stmt->bind_param("i", $this->item_id);

        return $stmt->execute();
    }
}
?>