<?php
class labdiagnostic_sampletypes {
    private $conn;
    private $table = "labdiagnostic_sampletypes";

    // Properties
    public $sample_type_id;
    public $name;
    public $code;
    public $storage_requirements;
    public $stability_duration; 

    public function __construct($db) {
        $this->conn = $db;
    }

    // Get all sample types
    public function getAll() {
        $query = "SELECT * FROM " . $this->table;
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    // Get single sample type by ID
    public function getById($sample_type_id) {
        $query = "SELECT * FROM " . $this->table . " WHERE sample_type_id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("i", $sample_type_id);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }

    // Create new sample type
    public function create() {
        $query = "INSERT INTO " . $this->table . " 
                 (name, code, storage_requirements, stability_duration) 
                 VALUES (?, ?, ?, ?)";

        $stmt = $this->conn->prepare($query);

        // Clean data
        $this->name = htmlspecialchars(strip_tags($this->name));
        $this->code = htmlspecialchars(strip_tags($this->code));
        $this->storage_requirements = htmlspecialchars(strip_tags($this->storage_requirements));
        $this->stability_duration = htmlspecialchars(strip_tags($this->stability_duration));
        
        $stmt->bind_param(
            "ssss",
            $this->name,
            $this->code,
            $this->storage_requirements,
            $this->stability_duration
        );

        if($stmt->execute()) {
            return true;
        }
        return false;
    }

    // Update sample type
    public function update() {
        $query = "UPDATE " . $this->table . " 
                 SET name = ?, code = ?, storage_requirements = ?, stability_duration = ? 
                 WHERE sample_type_id = ?";

        $stmt = $this->conn->prepare($query);

        // Clean data
        $this->name = htmlspecialchars(strip_tags($this->name));
        $this->code = htmlspecialchars(strip_tags($this->code));
        $this->storage_requirements = htmlspecialchars(strip_tags($this->storage_requirements));
        $this->stability_duration = htmlspecialchars(strip_tags($this->stability_duration));
        $this->sample_type_id = htmlspecialchars(strip_tags($this->sample_type_id));
        
        $stmt->bind_param(
            "ssssi", 
            $this->name,
            $this->code,
            $this->storage_requirements,
            $this->stability_duration,
            $this->sample_type_id
        );

        if($stmt->execute()) {
            return true;
        }
        return false;
    }

    // Delete sample type
    public function delete($sample_type_id) {
        $query = "DELETE FROM " . $this->table . " WHERE sample_type_id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("i", $sample_type_id);
        return $stmt->execute();
    }
}
?>