<?php
class DoctorShiftScheduling {
    public $conn;
    public $user;
    public $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
    public $doctors = [];
    public $professions = [];
    public $departments = [];

    public function __construct($conn) {
        $this->conn = $conn;
        $this->authenticate();
        $this->fetchUser();
        $this->fetchDoctors();
        $this->fetchProfessions();
        $this->fetchDepartments();
    }

    private function authenticate() {
        if (!isset($_SESSION['doctor']) || $_SESSION['doctor'] !== true) {
            header('Location: login.php');
            exit();
        }
        if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
            echo "User ID is not set in session.";
            exit();
        }
    }

    private function fetchUser() {
        $query = "SELECT * FROM users WHERE user_id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("i", $_SESSION['user_id']);
        $stmt->execute();
        $result = $stmt->get_result();
        $this->user = $result->fetch_assoc();
        if (!$this->user) {
            echo "No user found.";
            exit();
        }
    }

    private function fetchDoctors() {
        $doctor_query = "
            SELECT e.employee_id, e.first_name, e.middle_name, e.last_name, e.role, e.profession, e.department
            FROM hr_employees e
            INNER JOIN clinical_profiles cp ON e.employee_id = cp.employee_id
            WHERE e.profession = 'Doctor' AND cp.clinical_status = 'Active'
        ";
        $doctor_result = $this->conn->query($doctor_query);
        if ($doctor_result && $doctor_result->num_rows > 0) {
            while ($row = $doctor_result->fetch_assoc()) {
                $this->doctors[] = $row;
            }
        }
    }

    private function fetchProfessions() {
        $profession_query = "SELECT DISTINCT profession FROM hr_employees";
        $profession_result = $this->conn->query($profession_query);
        if ($profession_result && $profession_result->num_rows > 0) {
            while ($row = $profession_result->fetch_assoc()) {
                $this->professions[] = $row['profession'];
            }
        }
    }

    private function fetchDepartments() {
        $dept_query = "SELECT DISTINCT department FROM hr_employees WHERE department IS NOT NULL AND department != ''";
        $dept_result = $this->conn->query($dept_query);
        if ($dept_result && $dept_result->num_rows > 0) {
            while ($row = $dept_result->fetch_assoc()) {
                $this->departments[] = $row['department'];
            }
        }
    }
}
