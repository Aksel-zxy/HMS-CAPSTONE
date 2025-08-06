<?php
class billing_records {
    private $conn;
    public function __construct($conn) {
        $this->conn = $conn;
    }
    public function getAllBillingRecords() {
        return $this->conn->query("SELECT * FROM billing_records");
    }

    public function addBillingRecord($patient_id, $billing_date, $total_amount, $insurance_covered, $payment_method, $transaction_id, $status = null) {
        if ($status === null) {
            $status = 'pending';
        }
        $out_of_pocket = $total_amount - $insurance_covered;
        $query = "INSERT INTO " . $this->table . " (patient_id, billing_date, total_amount, insurance_covered, out_of_pocket, status, payment_method, transaction_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("isddssss", $patient_id, $billing_date, $total_amount, $insurance_covered, $out_of_pocket, $status, $payment_method, $transaction_id);
        return $stmt->execute();
    }
}
