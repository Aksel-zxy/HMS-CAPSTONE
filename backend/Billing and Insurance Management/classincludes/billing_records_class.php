<?php
class billing_records {
    private $conn;
    private $table = "billing_records";
    
    public function __construct($conn) {
        $this->conn = $conn;
    }
    
    public function getAllBillingRecords() {
        $query = "
            SELECT 
                br.*, 
                p.fname, 
                p.lname,
                COALESCE(ir.insurance_covered, 0) as insurance_covered_amount
            FROM billing_records br 
            LEFT JOIN patientinfo p ON br.patient_id = p.patient_id
            LEFT JOIN (
                SELECT patient_id, insurance_covered
                FROM insurance_request 
                WHERE status = 'Approved'
            ) ir ON br.patient_id = ir.patient_id
            ORDER BY br.billing_id DESC
        ";
        
        $result = $this->conn->query($query);
        return $result ? $result : false;
    }

    // Get the next auto-increment billing ID
    private function getNextBillingId() {
        // First, check if there are any existing records
        $query = "SELECT MAX(billing_id) as max_id FROM billing_records";
        $result = $this->conn->query($query);
        
        if ($result && $result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $max_id = $row['max_id'];
            
            // If max_id is numeric, increment it
            if (is_numeric($max_id)) {
                return $max_id + 1;
            }
        }
        
        // If no records exist or max_id is not numeric, start from 1
        return 1;
    }

    // Check if a patient already has a billing record
    public function patientHasBillingRecord($patient_id) {
        $query = "SELECT COUNT(*) as count FROM billing_records WHERE patient_id = ?";
        $stmt = $this->conn->prepare($query);
        
        if (!$stmt) {
            return false;
        }
        
        $stmt->bind_param("i", $patient_id);
        
        if (!$stmt->execute()) {
            return false;
        }
        
        $result = $stmt->get_result();
        
        if ($result) {
            $row = $result->fetch_assoc();
            return $row['count'] > 0;
        }
        
        return false;
    }

    // Get insurance coverage for a patient
    public function getInsuranceCoverage($patient_id) {
        $query = "SELECT insurance_covered FROM insurance_request WHERE patient_id = ? AND status = 'Approved'";
        $stmt = $this->conn->prepare($query);
        
        if (!$stmt) {
            return 0.00;
        }
        
        $stmt->bind_param("i", $patient_id);
        
        if (!$stmt->execute()) {
            return 0.00;
        }
        
        $result = $stmt->get_result();
        
        if ($result && $result->num_rows > 0) {
            $row = $result->fetch_assoc();
            return $row['insurance_covered'];
        }
        
        return 0.00;
    }

    // Create initial billing record for a new patient
    public function createInitialBillingRecord($patient_id) {
        // Check if record already exists
        if ($this->patientHasBillingRecord($patient_id)) {
            return true; // Record already exists
        }
        
        // Generate billing ID
        $billing_id = $this->getNextBillingId();
        
        // Get insurance coverage if available
        $insurance_covered = $this->getInsuranceCoverage($patient_id);
        
        // Set default values for a new patient
        $billing_date = date('Y-m-d H:i:s');
        $total_amount = 0.00;
        $out_of_pocket = max(0, $total_amount - $insurance_covered);
        $status = 'pending';
        $payment_method = 'Not Set';
        $transaction_id = 'N/A';
        
        $query = "INSERT INTO " . $this->table . " (billing_id, patient_id, billing_date, total_amount, insurance_covered, out_of_pocket, status, payment_method, transaction_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $this->conn->prepare($query);
        
        if (!$stmt) {
            return false;
        }
        
        $stmt->bind_param("isddddsss", $billing_id, $patient_id, $billing_date, $total_amount, $insurance_covered, $out_of_pocket, $status, $payment_method, $transaction_id);
        return $stmt->execute();
    }

    // Get all patients without billing records
    public function getPatientsWithoutBillingRecords() {
        $query = "
            SELECT p.* FROM patientinfo p 
            LEFT JOIN billing_records br ON p.patient_id = br.patient_id 
            WHERE br.patient_id IS NULL
        ";
        
        $result = $this->conn->query($query);
        return $result ? $result : false;
    }

    // Process all patients without billing records
    public function processAllPatientsWithoutBilling() {
        $patients = $this->getPatientsWithoutBillingRecords();
        $count = 0;
        
        if ($patients) {
            while ($patient = $patients->fetch_assoc()) {
                if ($this->createInitialBillingRecord($patient['patient_id'])) {
                    $count++;
                }
            }
        }
        
        return $count;
    }
    
    // Get patientinfo table structure
    public function getPatientInfoColumns() {
        $result = $this->conn->query("SELECT * FROM patientinfo LIMIT 1");
        
        if (!$result) {
            return [];
        }
        
        $columns = [];
        $fields = $result->fetch_fields();
        foreach ($fields as $field) {
            $columns[] = $field->name;
        }
        return $columns;
    }
}
?>