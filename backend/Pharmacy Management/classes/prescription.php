<?php
class Prescription
{
    private $conn;

    public function __construct($db)
    {
        $this->conn = $db;
    }

    // Add new prescription
    public function addPrescription($doctor_id, $patient_id, $note, $status, $items)
    {
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

        try {
            $this->conn->begin_transaction();

            // Insert into pharmacy_prescription
            $stmt = $this->conn->prepare("
                INSERT INTO pharmacy_prescription 
                (doctor_id, patient_id, prescription_date, note, status) 
                VALUES (?, ?, NOW(), ?, ?)
            ");
            $stmt->bind_param("iiss", $doctor_id, $patient_id, $note, $status);
            $stmt->execute();
            $prescription_id = $this->conn->insert_id;

            // Insert each medicine into items table
            foreach ($items as $item) {
                $stmtItem = $this->conn->prepare("
                    INSERT INTO pharmacy_prescription_items 
                    (prescription_id, med_id, dosage, quantity_prescribed, quantity_dispensed) 
                    VALUES (?, ?, ?, ?, 0)
                ");
                $stmtItem->bind_param(
                    "iisi",
                    $prescription_id,
                    $item['med_id'],
                    $item['dosage'],
                    $item['quantity']
                );
                $stmtItem->execute();
            }

            $this->conn->commit();
            return true;
        } catch (mysqli_sql_exception $e) {
            $this->conn->rollback();
            die("MySQL Error: " . $e->getMessage());
        }
    }

    // Fetch doctors only
    public function getDoctors()
    {
        $query = "SELECT employee_id, first_name, last_name 
                  FROM hr_employees 
                  WHERE profession = 'Doctor'";
        return $this->conn->query($query);
    }

    // Fetch patients
    public function getPatients()
    {
        return $this->conn->query("
            SELECT patient_id, fname, lname 
            FROM patientinfo
        ");
    }

    // Fetch medicines
    public function getMedicines()
    {
        return $this->conn->query("
            SELECT med_id, med_name, dosage, stock_quantity
            FROM pharmacy_inventory
        ");
    }
}
