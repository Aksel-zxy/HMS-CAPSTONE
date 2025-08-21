<?php
class Medicine
{
    private $conn;

    public function __construct($dbConnection)
    {
        $this->conn = $dbConnection;
    }

    // Fetch all medicines
    public function getAllMedicines()
    {
        $query = "SELECT * FROM pharmacy_inventory ORDER BY med_id ASC";
        $result = $this->conn->query($query);

        if (!$result) {
            throw new Exception("Error fetching medicines: " . $this->conn->error);
        }

        return $result->fetch_all(MYSQLI_ASSOC);
    }

    public function addMedicine($med_name, $category, $dosage, $stock_quantity, $unit, $status)
    {

        $stmt = $this->conn->prepare("SELECT med_id, stock_quantity FROM pharmacy_inventory WHERE med_name = ? AND dosage = ?");
        $stmt->bind_param("ss", $med_name, $dosage);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {

            $row = $result->fetch_assoc();
            $new_quantity = $row['stock_quantity'] + $stock_quantity;
            $updateStmt = $this->conn->prepare("UPDATE pharmacy_inventory SET stock_quantity = ?, status = ? WHERE med_id = ?");
            $updateStmt->bind_param("isi", $new_quantity, $status, $row['med_id']);
            if (!$updateStmt->execute()) {
                throw new Exception("Failed to update stock: " . $updateStmt->error);
            }
        } else {

            $insertStmt = $this->conn->prepare("INSERT INTO pharmacy_inventory (med_name, category, dosage, stock_quantity, unit, status) VALUES (?, ?, ?, ?, ?, ?)");
            $insertStmt->bind_param("sssiss", $med_name, $category, $dosage, $stock_quantity, $unit, $status);
            if (!$insertStmt->execute()) {
                throw new Exception("Failed to add medicine: " . $insertStmt->error);
            }
        }

        return true;
    }


    public function updateStatus($med_id, $new_status)
    {
        $stmt = $this->conn->prepare("UPDATE pharmacy_inventory SET status = ? WHERE med_id = ?");
        $stmt->bind_param("si", $new_status, $med_id);

        if (!$stmt->execute()) {
            throw new Exception("Failed to update status: " . $stmt->error);
        }

        return true;
    }

    public function autoUpdateOutOfStock()
    {
        $sql = "UPDATE pharmacy_inventory 
            SET status = CASE 
                WHEN stock_quantity = 0 THEN 'Out of Stock'
                ELSE 'Available'
            END";
        $this->conn->query($sql);
    }
}
