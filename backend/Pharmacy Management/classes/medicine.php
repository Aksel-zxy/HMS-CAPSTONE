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

    // Shelf life constants in years
    private function getShelfLife()
    {
        return [
            "Tablets & Capsules" => 3,
            "Syrups / Oral Liquids" => 2,
            "Antibiotic Dry Syrup (Powder)" => 2,
            "Injectables (Ampoules / Vials)" => 3,
            "Eye Drops / Ear Drops" => 2,
            "Insulin" => 2,
            "Topical Creams / Ointments" => 3,
            "Vaccines" => 2,
            "IV Fluids" => 2
        ];
    }

    // Add new medicine or update existing one
    public function addMedicine($med_name, $category, $dosage, $stock_quantity, $unit, $status, $unit_price)
    {
        $shelf_life = $this->getShelfLife();
        $expiry_date = array_key_exists($unit, $shelf_life)
            ? date('Y-m-d', strtotime("+" . $shelf_life[$unit] . " years"))
            : NULL;

        // Check if medicine exists
        $stmt = $this->conn->prepare("SELECT med_id, stock_quantity FROM pharmacy_inventory WHERE med_name = ? AND dosage = ?");
        $stmt->bind_param("ss", $med_name, $dosage);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            // Update stock and price if exists
            $row = $result->fetch_assoc();
            $new_quantity = $row['stock_quantity'] + $stock_quantity;

            $updateStmt = $this->conn->prepare("
                UPDATE pharmacy_inventory 
                SET stock_quantity = ?, status = ?, unit_price = ?, expiry_date = ? 
                WHERE med_id = ?
            ");
            $updateStmt->bind_param("isdsi", $new_quantity, $status, $unit_price, $expiry_date, $row['med_id']);

            if (!$updateStmt->execute()) {
                throw new Exception("Failed to update stock: " . $updateStmt->error);
            }
        } else {
            // Insert new medicine
            $insertStmt = $this->conn->prepare("
                INSERT INTO pharmacy_inventory 
                (med_name, category, dosage, stock_quantity, unit, status, unit_price, expiry_date) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $insertStmt->bind_param("sssissds", $med_name, $category, $dosage, $stock_quantity, $unit, $status, $unit_price, $expiry_date);

            if (!$insertStmt->execute()) {
                throw new Exception("Failed to add medicine: " . $insertStmt->error);
            }
        }

        return true;
    }

    // Update medicine fully
    public function updateMedicine($med_id, $med_name, $category, $dosage, $stock_quantity, $unit, $status, $unit_price)
    {
        $shelf_life = $this->getShelfLife();
        $expiry_date = array_key_exists($unit, $shelf_life)
            ? date('Y-m-d', strtotime("+" . $shelf_life[$unit] . " years"))
            : NULL;

        $stmt = $this->conn->prepare("
            UPDATE pharmacy_inventory 
            SET med_name = ?, category = ?, dosage = ?, stock_quantity = ?, unit = ?, status = ?, unit_price = ?, expiry_date = ?
            WHERE med_id = ?
        ");
        $stmt->bind_param("sssissdsi", $med_name, $category, $dosage, $stock_quantity, $unit, $status, $unit_price, $expiry_date, $med_id);

        if (!$stmt->execute()) {
            throw new Exception("Failed to update medicine: " . $stmt->error);
        }

        return true;
    }

    // Update medicine status only
    public function updateStatus($med_id, $new_status)
    {
        $stmt = $this->conn->prepare("UPDATE pharmacy_inventory SET status = ? WHERE med_id = ?");
        $stmt->bind_param("si", $new_status, $med_id);

        if (!$stmt->execute()) {
            throw new Exception("Failed to update status: " . $stmt->error);
        }

        return true;
    }

    // Auto update stock status
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
