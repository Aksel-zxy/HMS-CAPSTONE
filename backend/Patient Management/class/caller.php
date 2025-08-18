<?php
require_once 'class/patient.php';

class Caller {
    private $conn;

    public function __construct($db) {
        $this->conn = $db;
    }

public function callEmr($patient_id) {
    $stmt = $this->conn->prepare("SELECT * FROM p_emr WHERE patient_id = ?");
    $stmt->bind_param("i", $patient_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        return $result->fetch_assoc();
    } else {
        throw new Exception("No medical history found for patient ID: " . $patient_id);
    }
}


public function callHistory($patient_id) {
    $stmt = $this->conn->prepare("SELECT * FROM p_previous_medical_records WHERE patient_id = ?");
    $stmt->bind_param("i", $patient_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        return $result->fetch_assoc();
    } else {
        throw new Exception("No previous medical history found for patient ID: " . $patient_id);
    }
}

public function callBeddings ($patient_id) {
    $stmt = $this->conn->prepare("SELECT * FROM p_bed_assignments WHERE patient_id = ?");
    $stmt->bind_param("i", $patient_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        return $result->fetch_assoc();
    } else {
        throw new Exception("No beddings found for patient ID: " . $patient_id);
    }
}
public function getAllDoctors() {
    $sql = "SELECT employee_id, first_name, last_name
            FROM hr_employees 
            WHERE profession = 'Doctor'";
    $result = $this->conn->query($sql);
    return $result;
}

public function getAllAppointments() {
        $sql = "SELECT p.appointment_id, p.doctor_id, p.patient_id, p.appointment_date, p.purpose, p.status, p.notes,
        concat(a.fname, ' ', a.lname) AS patient_name,
        concat(d.first_name, ' ', d.last_name) AS doctor_name
         FROM p_appointments p 
         LEFT JOIN patientinfo a ON p.patient_id = a.patient_id
         LEFT JOIN hr_employees d ON p.doctor_id = d.employee_id"
         ;
        $result = $this->conn->query($sql);
        return $result;
    }
    
public function getDoctors() {
    $sql = "SELECT employee_id, first_name, last_name, specialization
            FROM hr_employees 
            WHERE profession IN ('Doctor', 'Laboratorist')";
    $result = $this->conn->query($sql);
    return $result;
}

  public function getAppointmentById($appointment_id) {
        $query = "SELECT a.appointment_id, 
                         a.patient_id,
                         a.doctor_id,
                         a.appointment_date,
                         a.purpose,
                         a.status,
                         a.notes,
                         CONCAT(p.fname, ' ', p.mname, ' ', p.lname) AS patient_name,
                         CONCAT(d.first_name, ' ', d.last_name) AS doctor_name
                  FROM p_appointments a
                  JOIN patientinfo p ON a.patient_id = p.patient_id
                  JOIN hr_employees d ON a.doctor_id = d.employee_id
                  WHERE a.appointment_id = ?";

        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("i", $appointment_id);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_assoc(); // returns array or null
    
}
}