<?php
class PDFReport {
    public $conn;
    public $billing_id;
    public $user_id;

    public $billing = [];
    public $doctor = null;
    public $service_results = [];
    public $subtotal = 0;
    public $insurance_covered = 0.00;

    public function __construct($conn, $billing_id, $user_id) {
        $this->conn = $conn;
        $this->billing_id = $billing_id;
        $this->user_id = $user_id;
    }

    public function fetchAll() {
        $this->fetchBilling();
        $this->fetchDoctor();
        $this->fetchServices();
        $this->fetchInsuranceCovered();
    }

    private function fetchBilling() {
        $sql = "
            SELECT br.*, br.insurance_covered, pi.fname, pi.mname, pi.lname, pi.address, pi.age, pi.dob, pi.gender, pi.civil_status,
                   pi.phone_number, pi.email, pi.admission_type, pi.discount
            FROM billing_records br
            JOIN patientinfo pi ON br.patient_id = pi.patient_id
            WHERE br.billing_id = ?
        ";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param('i', $this->billing_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $this->billing = $result->fetch_assoc();
        $stmt->close();
    }

    private function fetchDoctor() {
        $this->doctor = null;
        if (!$this->billing) return;
        $doctor_stmt = $this->conn->prepare("
            SELECT he.first_name, he.middle_name, he.last_name, he.suffix_name, 
                   he.contact_number, he.specialization
            FROM dl_schedule ds
            JOIN hr_employees he ON ds.employee_id = he.employee_id
            WHERE ds.patientID = ?
            ORDER BY ds.scheduleDate DESC, ds.scheduleTime DESC
            LIMIT 1
        ");
        $doctor_stmt->bind_param("i", $this->billing['patient_id']);
        $doctor_stmt->execute();
        $doctor_result = $doctor_stmt->get_result();
        if ($doctor_row = $doctor_result->fetch_assoc()) {
            $this->doctor = $doctor_row;
        }
        $doctor_stmt->close();
    }

    private function fetchServices() {
        $this->service_results = [];
        $this->subtotal = 0;
        if (!$this->billing) return;
        $sql_services = "
            SELECT sch.serviceName, ds.description, ds.price
            FROM dl_schedule sch
            LEFT JOIN dl_services ds ON sch.serviceName = ds.serviceName
            WHERE sch.patientID = ?
              AND sch.status = 'Completed'
            ORDER BY sch.scheduleDate DESC, sch.scheduleTime DESC
            LIMIT 5
        ";
        $stmt_services = $this->conn->prepare($sql_services);
        $stmt_services->bind_param('i', $this->billing['patient_id']);
        $stmt_services->execute();
        $result_services = $stmt_services->get_result();
        while ($row = $result_services->fetch_assoc()) {
            $this->service_results[] = $row;
            $this->subtotal += floatval($row['price']);
        }
        $stmt_services->close();
    }

    private function fetchInsuranceCovered() {
        $this->insurance_covered = 0.00;
        if (isset($this->billing['insurance_covered']) && is_numeric($this->billing['insurance_covered'])) {
            $this->insurance_covered = floatval($this->billing['insurance_covered']);
        }
    }
}
