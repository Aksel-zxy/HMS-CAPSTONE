<?php
class BillingSummary {
    private $conn;

    public function __construct($conn) {
        $this->conn = $conn;
    }

    // Fetch all billing summary records for a patient
    public function getBillingSummaryByPatient($patient_id) {
        $query = "SELECT * FROM billingsummary WHERE patient_id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("i", $patient_id);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    // Create billing summary from finalized billing_items
    public function createSummaryFromItems($patient_id) {
        // Fetch finalized billing_items for the patient that are not yet summarized
        $query = "SELECT * FROM billing_items WHERE patient_id = ? AND finalized = 1 AND billing_id IS NOT NULL";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("i", $patient_id);
        $stmt->execute();
        $items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        foreach ($items as $item) {
            // Check if this item is already in billingsummary
            $check = $this->conn->prepare("SELECT 1 FROM billingsummary WHERE patient_id = ? AND particulars = ? LIMIT 1");
            $check->bind_param("is", $patient_id, $item['service_name']);
            $check->execute();
            if ($check->get_result()->num_rows > 0) continue; // already summarized

            // Insert into billingsummary
            $insert = $this->conn->prepare("
                INSERT INTO billingsummary 
                (patient_id, particulars, actual_charges, vat, amount_of_discount, out_of_pocket) 
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $actual_charges = floatval($item['total_price']);
            $discount = floatval($item['discount']);
            $vat = 0; // VAT removed
            $out_of_pocket = ($actual_charges - $discount); // No VAT added
            $insert->bind_param("isdddd", $patient_id, $item['service_name'], $actual_charges, $vat, $discount, $out_of_pocket);
            $insert->execute();
        }
    }

    // Generate a receipt for the patient
    public function generateReceipt($patient_id) {
        // Ensure billing summary exists
        $this->createSummaryFromItems($patient_id);

        // Fetch all billing summary rows
        $query = "SELECT actual_charges, vat, amount_of_discount, out_of_pocket FROM billingsummary WHERE patient_id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("i", $patient_id);
        $stmt->execute();
        $result = $stmt->get_result();

        $total_charges = $total_vat = $total_discount = $total_out_of_pocket = 0;
        while ($row = $result->fetch_assoc()) {
            $total_charges += $row['actual_charges'];
            $total_vat += 0; // VAT removed
            $total_discount += $row['amount_of_discount'];
            $total_out_of_pocket += $row['out_of_pocket'];
        }
        $grand_total = $total_out_of_pocket;

        // Insert into patient_receipt
        $transaction_id = uniqid("TXN");
        $stmt = $this->conn->prepare("
            INSERT INTO patient_receipt 
            (patient_id, total_charges, total_vat, total_discount, total_out_of_pocket, grand_total, created_at, status, transaction_id)
            VALUES (?, ?, ?, ?, ?, ?, NOW(), 'Pending', ?)
        ");
        $stmt->bind_param("iddddds", $patient_id, $total_charges, $total_vat, $total_discount, $total_out_of_pocket, $grand_total, $transaction_id);
        return $stmt->execute();
    }

    // Get latest receipt
    public function getReceipt($patient_id) {
        $query = "SELECT * FROM patient_receipt WHERE patient_id = ? ORDER BY created_at DESC LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("i", $patient_id);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }

    // Fetch all patients
    public function getAllPatients() {
        $stmt = $this->conn->prepare("
            SELECT patient_id, fname, mname, lname,
                   CONCAT(fname, ' ', IFNULL(mname, ''), ' ', lname) AS full_name
            FROM patientinfo
        ");
        $stmt->execute();
        $result = $stmt->get_result();
        return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    }
}
?>
