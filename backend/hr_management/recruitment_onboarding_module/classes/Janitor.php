<?php
class Janitor {
    private $conn;

    public function __construct($db) {
        $this->conn = $db;
    }

    public function getJanitors($search = '', $status = '') {
        $sql = "SELECT * FROM hr_employees WHERE profession = 'Janitor'";
        $conditions = [];
        $params = [];
        $types = '';

        if (!empty($search)) {
            $conditions[] = "(
                employee_id LIKE ? OR
                CONCAT_WS(' ', first_name, middle_name, last_name, suffix_name) LIKE ?
            )";

            $searchTerm = "%{$search}%";
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $types .= 'ss';
        }

        if (!empty($status)) {
            $conditions[] = "status = ?";
            $params[] = $status;
            $types .= 's';
        }

        if ($conditions) {
            $sql .= " AND " . implode(" AND ", $conditions);
        }

        $stmt = $this->conn->prepare($sql);
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }

        $stmt->execute();
        return $stmt->get_result();
    }

    public function employeeExists($employee_id) {
        $stmt = $this->conn->prepare("SELECT * FROM hr_employees WHERE employee_id = ?");
        $stmt->bind_param("s", $employee_id);
        $stmt->execute();
        return $stmt->get_result()->num_rows > 0;
    }

    public function addJanitor($data) {

        $sql = "INSERT INTO hr_employees (
            employee_id, username, password, first_name, middle_name, last_name, suffix_name,
            gender, date_of_birth, contact_number, email, citizenship, house_no, barangay, city,
            province, region, profession, role, department, specialization, employment_type,
            status, educational_status, degree_type, medical_school, graduation_year, license_type,
            license_number, license_issued, license_expiry, eg_name, eg_relationship, eg_cn
        ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";

        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("ssssssssssssssssssssssssssssssssss",
            $data['employee_id'], $data['username'], $data['password'], $data['first_name'],
            $data['middle_name'], $data['last_name'], $data['suffix_name'], $data['gender'],
            $data['date_of_birth'], $data['contact_number'], $data['email'], $data['citizenship'],
            $data['house_no'], $data['barangay'], $data['city'], $data['province'], $data['region'],
            $data['profession'], $data['role'], $data['department'], $data['specialization'],
            $data['employment_type'], $data['status'], $data['educational_status'], $data['degree_type'],
            $data['medical_school'], $data['graduation_year'], $data['license_type'], $data['license_number'],
            $data['license_issued'], $data['license_expiry'], $data['eg_name'], $data['eg_relationship'],
            $data['eg_cn']
        );
        return $stmt->execute();
    }

    public function getByEmployeeId($employeeId) {
        $stmt = $this->conn->prepare("SELECT * FROM hr_employees WHERE employee_id = ? AND profession = 'Janitor' LIMIT 1");
        $stmt->bind_param("s", $employeeId);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_assoc();
    }

    public function getEmployeeDocuments($employee_id) {
        $stmt = $this->conn->prepare("SELECT * FROM hr_employees_documents WHERE employee_id = ?");
        $stmt->bind_param("s", $employee_id);
        $stmt->execute();
        return $stmt->get_result();
    }


}
