<?php
//oop
class Patient {
    private $conn;

    public function __construct($db) {
        $this->conn = $db;
    }

    public function getAllPatients() {
        $sql = "SELECT * FROM patientinfo where admission_type != 'Outpatient' ORDER BY patient_id DESC";
        $result = $this->conn->query($sql);

        if (!$result) {
            die("Invalid query: " . $this->conn->error);
        }

        return $result;
    }

    public function getPatientById($id) {
        $stmt = $this->conn->prepare("SELECT * FROM patientinfo WHERE patient_id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }

    public function insertPatient($data) {
        $stmt = $this->conn->prepare(" INSERT INTO patientinfo (fname, mname, lname, address, age, dob, gender, civil_status, phone_number, email, admission_type,  attending_doctor) VALUES ( ?,?,?,?,?,?,?, ?,?,?,?,?)");
        $stmt->bind_param("ssssisssssssi",
        $data['fname'], $data['mname'], $data['lname'], $data['address'], $data['age'], $data['dob'], $data['gender'], $data['civil_status'], $data['phone_number'],
        $data['email'], $data['admission_type'], $data['attending_doctor']);

        return $stmt->execute();
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

    
}

?>