<?php
include __DIR__ . '/../../../SQL/config.php';
//oop
class Patient {
    private $conn;

    public function __construct($db) {
        $this->conn = $db;
    }

    public function getAllPatients() {
        $sql = "SELECT p.patient_id, p.fname, p.mname, p.lname, p.address, p.gender, 
                   p.civil_status, p.admission_type, 
                   CONCAT(e.first_name, ' ', e.last_name) AS doctor_name
            FROM patientinfo p
            LEFT JOIN hr_employees e 
                   ON p.attending_doctor = e.employee_id order by p.patient_id desc";
        $result = $this->conn->query($sql);

        if (!$result) {
            die("Invalid query: " . $this->conn->error);
        }

        return $result;
    }
    
    public function getRegisteredPatients() {
        $sql = "SELECT p.patient_id, p.fname, p.mname, p.lname, p.address, p.gender, 
                   p.civil_status, p.admission_type, 
                   CONCAT(e.first_name, ' ', e.last_name) AS doctor_name
            FROM patientinfo p 
            LEFT JOIN hr_employees e 
                   ON p.attending_doctor = e.employee_id
                   where p.admission_type = 'Registered Patient'
                    order by p.patient_id desc";
        $result = $this->conn->query($sql);

        if (!$result) {
            die("Invalid query: " . $this->conn->error);
        }

        return $result;
    }
    
    public function getinPatients() {
        $sql = "SELECT p.patient_id, p.fname, p.mname, p.lname, p.address, p.gender, 
                   p.civil_status, p.admission_type, 
                   CONCAT(e.first_name, ' ', e.last_name) AS doctor_name
            FROM patientinfo p
            LEFT JOIN hr_employees e 
                   ON p.attending_doctor = e.employee_id
            WHERE p.admission_type != 'Outpatient'
              AND p.admission_type != 'Registered Patient'
            ORDER BY p.patient_id DESC";
        $result = $this->conn->query($sql);

        if (!$result) {
            die("Invalid query: " . $this->conn->error);
        }

        return $result;
    }

    //for user panel
    public function getPatientsById($patient_id) {
        $query = "SELECT * FROM patient_user WHERE patient_id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("i", $patient_id);
        $stmt->execute();
        return $stmt->get_result();
    }

    //For Edit Patient
    public function getPatientById($id) {
        $stmt = $this->conn->prepare("
        SELECT 
    p.*, 
    CONCAT(e.first_name, ' ', e.last_name) AS doctor_name,
    b.bed_number,
    b.room_number
FROM patientinfo p

LEFT JOIN hr_employees e
    ON p.attending_doctor = e.employee_id

LEFT JOIN p_bed_assignments ba
    ON p.patient_id = ba.patient_id

LEFT JOIN p_beds b
    ON ba.bed_id = b.bed_id

WHERE p.patient_id = ?
LIMIT 1;
    ");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }

public function insertPatient($data) {
    $stmt = $this->conn->prepare("
        INSERT INTO patientinfo (
            fname, mname, lname, address, age, dob, gender, civil_status,
            phone_number, email, admission_type, attending_doctor, height, weight, color_of_eyes, discount
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    if (!$stmt) {
        die("Prepare failed: " . $this->conn->error);
    }

    $stmt->bind_param(
        "ssssissssssissss",
        $data['fname'], $data['mname'], $data['lname'], $data['address'],
        $data['age'], $data['dob'], $data['gender'], $data['civil_status'],
        $data['phone_number'], $data['email'], $data['admission_type'],
        $data['attending_doctor'], $data['height'], $data['weight'], $data['color_of_eyes'], $data['discount']
    );

    if (!$stmt->execute()) {
        die("Execute failed: " . $stmt->error);
    }

    $insert_id = $this->conn->insert_id;
    $stmt->close();
    return $insert_id;
}

    public function updatePatient($patient_id, $data) {
    // Validate and sanitize dob to ensure it's in YYYY-MM-DD format or null
    if (isset($data['dob']) && $data['dob'] !== null) {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $data['dob'])) {
            $data['dob'] = null; // Set to null if invalid format
        }
    }

    $stmt = $this->conn->prepare("UPDATE patientinfo SET fname=?, mname=?, lname=?, address=?, dob=?, age=?, gender=?, civil_status=?,
     phone_number=?, email=?, admission_type=?, attending_doctor=? WHERE patient_id=?");

    if (!$stmt) {
        die("Prepare failed: " . $this->conn->error);
    }

    $stmt->bind_param( "sssssisssssii", 
        $data['fname'], $data['mname'], $data['lname'], $data['address'], 
        $data['dob'], $data['age'], $data['gender'], $data['civil_status'], 
        $data['phone_number'], $data['email'], $data['admission_type'], $data['attending_doctor'],
        $patient_id
    );

    if (!$stmt->execute()) {
        die("Execute failed: " . $stmt->error);
    }

    $stmt->close();
    return true;
}

    public function getPatientOrFail($patient_id) {
        if (empty($patient_id)) {
            throw new Exception("Patient ID is missing.");
        }

        $patient = $this->getPatientById($patient_id);

        if (!$patient) {
            throw new Exception("Patient not found.");
        }

        return $patient;
    }
    
     public function getOutPatients() {
        $sql = "SELECT p.patient_id, p.fname, p.mname, p.lname, p.address, p.gender, 
                   p.civil_status, p.admission_type, 
                   CONCAT(e.first_name, ' ', e.last_name) AS doctor_name
            FROM patientinfo p
            LEFT JOIN hr_employees e 
                   ON p.attending_doctor = e.employee_id
            WHERE p.admission_type = 'Outpatient' order by p.patient_id desc";
        $result = $this->conn->query($sql);

        if (!$result) {
            die("Invalid query: " . $this->conn->error);
        }

        return $result;
    }
    
    public function insertAppointment($data) {
        $stmt = $this->conn->prepare("
            INSERT INTO p_appointments (patient_id, doctor_id, appointment_date, purpose, notes)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->bind_param(
            "iisss",
            $data['patient_id'],
            $data['doctor_id'],
            $data['appointment_date'],
            $data['purpose'],
            $data['notes']
        );
        if ($stmt->execute()) {
            return true;
        } else {
            return false;
        }
    }

}

?>