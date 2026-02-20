<?php
include __DIR__ . '/../../../SQL/config.php';
require_once 'patient.php';

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

public function callBalance($patient_id) {
    $stmt = $this->conn->prepare("
               SELECT 
    bi.quantity,
    bi.unit_price,
    bi.total_price,
    ds.serviceName,
    ds.description,
    pi.patient_id

FROM billing_items bi

LEFT JOIN billing_records br 
    ON bi.billing_id = br.billing_id

LEFT JOIN patientinfo pi 
    ON br.patient_id = pi.patient_id

LEFT JOIN dl_services ds 
    ON bi.service_id = ds.serviceID

WHERE pi.patient_id = ?

    ");
    
    // ✅ Keep bind_param now
    $stmt->bind_param("i", $patient_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        return $result->fetch_all(MYSQLI_ASSOC);
    } else {
        return [];
    }
}


public function callPrescription($patient_id) {
    $stmt = $this->conn->prepare("
        SELECT 
            p.prescription_id,
            CONCAT(e.first_name, ' ', e.last_name) AS doctor_name,
            CONCAT(pi.fname, ' ', pi.lname) AS patient_name,
            GROUP_CONCAT(
                CONCAT(m.med_name, ' (', i.dosage, ') - Qty: ', i.quantity_prescribed)
                SEPARATOR '<br>'
            ) AS medicines_list,
            SUM(i.quantity_prescribed) AS total_quantity,
            p.note,
            DATE_FORMAT(p.prescription_date, '%b %e, %Y %l:%i%p') AS formatted_date,
            p.status
        FROM pharmacy_prescription p
        JOIN patientinfo pi 
            ON p.patient_id = pi.patient_id
        JOIN hr_employees e 
            ON p.doctor_id = e.employee_id 
            AND LOWER(e.profession) = 'doctor'
        JOIN pharmacy_prescription_items i 
            ON p.prescription_id = i.prescription_id
        JOIN pharmacy_inventory m 
            ON i.med_id = m.med_id
        WHERE p.patient_id = ? 
        GROUP BY p.prescription_id
        ORDER BY p.prescription_date DESC
    ");

    $stmt->bind_param("i", $patient_id);
    $stmt->execute();
    $result = $stmt->get_result();

    // Return all results, or empty array if none
    if ($result->num_rows > 0) {
        return $result->fetch_all(MYSQLI_ASSOC);
    } else {
        return []; // ✅ return empty array instead of throwing
    }
}



public function callHistory($patient_id) {
    $query = "SELECT * FROM p_previous_medical_records WHERE patient_id = ?";
    $stmt = $this->conn->prepare($query);
    $stmt->bind_param("i", $patient_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        return null; // or return [];
    }

    return $result->fetch_assoc();
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

public function callBeddings() {
    $stmt = $this->conn->prepare("
        SELECT DISTINCT
            b.room_number, 
            b.bed_number, 
            b.status, 
            p.fname, 
            p.lname
        FROM p_beds b
        LEFT JOIN p_bed_assignments ba ON b.bed_id = ba.bed_id
        LEFT JOIN patientinfo p ON ba.patient_id = p.patient_id
        ORDER BY b.room_number, b.bed_number 
    ");
    $stmt->execute();
    $result = $stmt->get_result();

    $rooms = [];
    while ($row = $result->fetch_assoc()) {
        $rooms[$row['room_number']][] = $row;
    }

    return $rooms;
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



    public function getResults($patient_id) {
    $stmt = $this->conn->prepare("
        SELECT 
            p.patient_id,

            -- CBC
            c.testType AS cbc_test,
            c.wbc, c.rbc, c.hemoglobin, c.hematocrit, c.platelets,
            c.mcv, c.mch, c.mchc, c.remarks AS cbc_remarks,

            -- CT
            ct.testType AS ct_test,
            ct.findings AS ct_findings,
            ct.impression AS ct_impression,
            ct.remarks AS ct_remarks,
            ct.image_blob AS ct_image,

            -- MRI
            mri.testType AS mri_test,
            mri.findings AS mri_findings,
            mri.impression AS mri_impression,
            mri.remarks AS mri_remarks,
            mri.image_blob AS mri_image,

            -- X-Ray
            x.testType AS xray_test,
            x.findings AS xray_findings,
            x.impression AS xray_impression,
            x.remarks AS xray_remarks,
            x.image_blob AS xray_image

        FROM patientinfo p
        LEFT JOIN dl_lab_cbc c   ON p.patient_id = c.patientID
        LEFT JOIN dl_lab_ct ct   ON p.patient_id = ct.patientID
        LEFT JOIN dl_lab_mri mri ON p.patient_id = mri.patientID
        LEFT JOIN dl_lab_xray x  ON p.patient_id = x.patientID
        WHERE p.patient_id = ? LIMIT 1
    ");

    $stmt->bind_param("i", $patient_id);
    $stmt->execute();
    $result = $stmt->get_result();

    return $result->fetch_assoc(); 
}


}
class Apppointment {
    private $conn;

    public function __construct($conn) {
        $this->conn = $conn;

  
       
    }

    public function book($patient_id, $doctor_id, $appointment_date, $purpose, $status, $notes) {
        $insert = "INSERT INTO p_appointments (patient_id, doctor_id, appointment_date, purpose, status, notes) 
                   VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $this->conn->prepare($insert);
        $stmt->bind_param("iissss", $patient_id, $doctor_id, $appointment_date, $purpose, $status, $notes);

        if ($stmt->execute()) {
            echo "<script>alert('Appointment booked successfully!'); window.location='user_appointment.php';</script>";
        } else {
            echo "Error: " . $this->conn->error;
        }
    }

    public function getAppointments($patient_id) {
        $sql = "SELECT a.appointment_id, a.doctor_id, a.patient_id, a.appointment_date, a.purpose, a.status, a.notes,
                concat(d.first_name, ' ', d.last_name) AS doctor_name
                FROM p_appointments a 
                LEFT JOIN hr_employees d ON a.doctor_id = d.employee_id
                WHERE a.patient_id = ? AND a.status = 'Scheduled'
                ORDER BY a.appointment_date DESC";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("i", $patient_id);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result;
    }

     public function getpastAppointments($patient_id) {
        $sql = "SELECT a.appointment_id, a.doctor_id, a.patient_id, a.appointment_date, a.purpose, a.status, a.notes,
                concat(d.first_name, ' ', d.last_name) AS doctor_name
                FROM p_appointments a 
                LEFT JOIN hr_employees d ON a.doctor_id = d.employee_id
                WHERE a.patient_id = ? AND a.status != 'Scheduled'
                ORDER BY a.appointment_date DESC";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("i", $patient_id);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result;
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

     public function admit($patient_id, $bed_id, $assigned_date, $admission_type, $severity, $predicted_los) {
        
        //  Insert into bed_assignment
        $insert = "INSERT INTO p_bed_assignments (patient_id, bed_id, assigned_date, severity_level, predicted_los) VALUES (?, ?, ?, ?, ?)";
        $stmt = $this->conn->prepare($insert);
        $stmt->bind_param("iissd", $patient_id, $bed_id, $assigned_date, $severity, $predicted_los);
        if ($stmt->execute()) {

            //  Update bed status
            $updateBed = "UPDATE p_beds SET status = 'Occupied' WHERE bed_id = ?";
            $stmt2 = $this->conn->prepare($updateBed);
            $stmt2->bind_param("i", $bed_id);
            $stmt2->execute();

            //  Update patient admission type
            $updateType = "UPDATE patientinfo SET admission_type = ? WHERE patient_id = ?";
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
            $beds = $this->conn->query("SELECT DISTINCT bed_id, bed_number FROM p_beds WHERE status = 'Available'");
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