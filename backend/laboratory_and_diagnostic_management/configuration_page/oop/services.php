<?php
class Service {
    private $conn;

    public function __construct($db) {
        $this->conn = $db;
    }

    public function getAll() {
        return $this->conn->query("SELECT * FROM dl_services ORDER BY serviceID ASC");
    }

    public function add($name, $desc, $price, $duration) {
        $stmt = $this->conn->prepare(
            "INSERT INTO dl_services (serviceName, description, price, durationMinutes)
             VALUES (?, ?, ?, ?)"
        );
        $stmt->bind_param("ssdi", $name, $desc, $price, $duration);
        return $stmt->execute();
    }

    public function update($id, $name, $desc, $price, $duration) {
        $stmt = $this->conn->prepare(
            "UPDATE dl_services 
             SET serviceName=?, description=?, price=?, durationMinutes=? 
             WHERE serviceID=?"
        );
        $stmt->bind_param("ssdii", $name, $desc, $price, $duration, $id);
        return $stmt->execute();
    }

    public function delete($id) {
        $stmt = $this->conn->prepare("DELETE FROM dl_services WHERE serviceID=?");
        $stmt->bind_param("i", $id);
        return $stmt->execute();
    }
}
