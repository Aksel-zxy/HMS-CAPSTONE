<?php
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
                   ON p.attending_doctor = e.employee_id
            WHERE p.admission_type != 'Outpatient'";
        $result = $this->conn->query($sql);

        if (!$result) {
            die("Invalid query: " . $this->conn->error);
        }

        return $result;
    }

    public function getPatientById($id) {
        $stmt = $this->conn->prepare("
        SELECT p.*, 
               CONCAT(e.first_name, ' ', e.last_name) AS doctor_name
        FROM patientinfo p
        LEFT JOIN hr_employees e
               ON p.attending_doctor = e.employee_id
        WHERE p.patient_id = ?
        LIMIT 1
    ");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }

    public function insertPatient($data) {
        $stmt = $this->conn->prepare(" INSERT INTO patientinfo (fname, mname, lname, address, age, dob, gender, civil_status, phone_number, email, admission_type,  attending_doctor) VALUES ( ?,?,?,?,?,?,?, ?,?,?,?,?)");
        $stmt->bind_param("ssssissssssi",
        $data['fname'], $data['mname'], $data['lname'], $data['address'], $data['age'], $data['dob'], $data['gender'], $data['civil_status'], $data['phone_number'],
        $data['email'], $data['admission_type'], $data['attending_doctor']);

       if (!$stmt->execute()) {
        throw new Exception("Insert failed: " . $stmt->error);
    }

    // ✅ Return the actual inserted patient ID
    return $this->conn->insert_id;
    }

    public function updatePatient($patient_id, $data) {
    $stmt = $this->conn->prepare(" UPDATE patientinfo SET fname=?, mname=?, lname=?, address=?, dob=?, age=?, gender=?, civil_status=?,
     phone_number=?, email=?, admission_type=?, attending_doctor=? WHERE patient_id=?");

     if (!$stmt){
        die("Prepare failed: " . $this->conn->error);
     }
    $stmt->bind_param("ssssissssssii", 
        $data['fname'], $data['mname'], $data['lname'], $data['address'], 
        $data['dob'], $data['age'], $data['gender'], $data['civil_status'], 
        $data['phone_number'], $data['email'], $data['admission_type'], $data['attending_doctor'],
        $patient_id
    );

    if(!$stmt->execute()){
        die("Execute failed: " . $stmt->error);
    }
    
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
            WHERE p.admission_type = 'Outpatient'";
        $result = $this->conn->query($sql);

        if (!$result) {
            die("Invalid query: " . $this->conn->error);
        }

        return $result;
    }
    
}

?>