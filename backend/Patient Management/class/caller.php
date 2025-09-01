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

public function callResult($patient_id) {
    $stmt = $this->conn->prepare("SELECT * FROM dl_results WHERE patient_id = ?");
    $stmt->bind_param("i", $patient_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        return $result->fetch_assoc();
    } else {
        throw new Exception("No lab results found for patient ID: " . $patient_id);
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


class PatientAdmission {
    private $conn;
    private $patient;

    public function __construct($conn) {
        $this->conn = $conn;

        // Check session login
        if (!isset($_SESSION['patient']) || $_SESSION['patient'] !== true) {
            header("Location: login.php");
            exit();
        }
    }

    // Fetch patient info
    public function getPatient($patient_id) {
        $query = "SELECT * FROM patientinfo WHERE patient_id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("i", $patient_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $this->patient = $result->fetch_assoc();

        return $this->patient;
    }

     public function admit($patient_id, $bed_id, $assigned_date, $admission_type) {
        //  Insert into bed_assignment
        $insert = "INSERT INTO bed_assignment (patient_id, bed_id, assigned_date) VALUES (?, ?, ?)";
        $stmt = $this->conn->prepare($insert);
        $stmt->bind_param("iis", $patient_id, $bed_id, $assigned_date);

        if ($stmt->execute()) {
            //  Update bed status
            $updateBed = "UPDATE p_beds SET status = 'Occupied' WHERE bed_id = ?";
            $stmt2 = $this->conn->prepare($updateBed);
            $stmt2->bind_param("i", $bed_id);
            $stmt2->execute();

            //  Update patient admission type
            $updateType = "UPDATE patients SET admission_type = ? WHERE patient_id = ?";
            $stmt3 = $this->conn->prepare($updateType);
            $stmt3->bind_param("si", $admission_type, $patient_id);
            $stmt3->execute();

            echo "<script>alert('Patient admitted successfully!'); window.location='inpatient.php';</script>";
        } else {
            echo "Error: " . $this->conn->error;
        }
    }


    // Get available beds
    public function getAvailableBeds() {
        $beds = $this->conn->query("SELECT bed_id, bed_number FROM p_beds WHERE status = 'Available'");
        return $beds;
    }
}

class PatientDischarge {
    private $conn;
    private $assignment;

    public function __construct($conn) {
        $this->conn = $conn;

        //  Check login
        if (!isset($_SESSION['patient']) || $_SESSION['patient'] !== true) {
            header("Location: login.php");
            exit();
        }
    }

    //  Find active admission
    public function getActiveAdmission($patient_id) {
        $query = "SELECT * FROM p_bed_assignments WHERE patient_id = ? AND released_date IS NULL";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("i", $patient_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $this->assignment = $result->fetch_assoc();

        return $this->assignment;
    }


    public function discharge($assignment_id, $bed_id, $patient_id, $released_date) {

        //  Update bed_assignment released_date
        $updateAssign = "UPDATE p_bed_assignments SET released_date = ? WHERE assignment_id = ?";
        $stmt = $this->conn->prepare($updateAssign);
        $stmt->bind_param("si", $released_date, $assignment_id);

        if ($stmt->execute()) {
            //  Set bed back to Available
            $updateBed = "UPDATE p_beds SET status = 'Available' WHERE bed_id = ?";
            $stmt2 = $this->conn->prepare($updateBed);
            $stmt2->bind_param("i", $bed_id);

            if ($stmt2->execute()) {
                
                //  Update patient status
                $updatePatientStatus = "UPDATE patientinfo SET admission_type = 'Outpatient' WHERE patient_id = ?";
                $stmt3 = $this->conn->prepare($updatePatientStatus);
                $stmt3->bind_param("i", $patient_id);
                $stmt3->execute();

                echo "<script>alert('Patient discharged successfully!'); 
                      window.location='inpatient.php';</script>";
            } else {
                echo "Error updating bed status: " . $this->conn->error;
            }
        } else {
            echo "Error: " . $this->conn->error;
        }
    }
}