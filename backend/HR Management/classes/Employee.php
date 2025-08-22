<?php
class Employee {
    private $conn;

    public function __construct($db) {
        $this->conn = $db;
    }

    public function getAll() {
        $result = $this->conn->query("SELECT * FROM hr_employees");
        return $result->fetch_all(MYSQLI_ASSOC);
    }

    public function getById($id) {
        $stmt = $this->conn->prepare("SELECT * FROM hr_employees WHERE employee_id = ?");
        $stmt->bind_param("s", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_assoc();
    }

    public function countByProfession($profession, $status = 'Active') {
        $sql = "SELECT COUNT(*) AS total 
                FROM hr_employees 
                WHERE profession = ? AND status = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("ss", $profession, $status);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        return (int) $row['total'];
    }

    public function existsById($employee_id) {
        $stmt = $this->conn->prepare("SELECT * FROM hr_employees WHERE employee_id = ?");
        $stmt->bind_param("s", $employee_id);
        $stmt->execute();
        $stmt->store_result();
        return $stmt->num_rows > 0;
    }
    
    public function update($employeeId, array $data): bool {
        $set = [];
        $params = [];
        $types = "";

        foreach ($data as $key => $value) {
            $set[] = "$key = ?";
            $params[] = $value;
            $types .= "s";
        }

        $params[] = $employeeId;
        $types .= "s";

        $sql = "UPDATE hr_employees SET " . implode(", ", $set) . " WHERE employee_id = ?";
        $stmt = $this->conn->prepare($sql);

        if (!$stmt) return false;

        $stmt->bind_param($types, ...$params);
        return $stmt->execute();
    }

}