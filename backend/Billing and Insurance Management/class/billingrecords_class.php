<?php 

class billing_records {
    public $conn;
    public $table = 'billing_records';


public $billing_id;
public $patient_id;
public $billing_date;
public $total_amount;
public $insurance_covered;
public $out_of_pocket;
public $status;
public $payment_method;
public $transaction_id;

public function __construct($conn) {
    $this->conn = $conn;
}
//get all billing records
public function getAllBillingRecords() {
    $query = "SELECT * FROM " . $this->table;
    $stmt = $this->conn->prepare($query);
    $stmt->execute();
    return $stmt->get_result();
}

//get single billing record by ID
public function getBillingRecordById($billing_id) {
    $query = "SELECT * FROM " . $this->table . " WHERE billing_id = ?";
    $stmt = $this->conn->prepare($query);
    $stmt->bind_param("i", $billing_id);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();

    if ($row) {
        $this->billing_id = $row['billing_id'];
        $this->patient_id = $row['patient_id'];
        $this->billing_date = $row['billing_date'];
        $this->total_amount = $row['total_amount'];
        $this->insurance_covered = $row['insurance_covered'];
        $this->out_of_pocket = $row['out_of_pocket'];
        $this->status = $row['status'];
        $this->payment_method = $row['payment_method'];
        $this->transaction_id = $row['transaction_id'];
    } else {
        return null;
    }

}

}
