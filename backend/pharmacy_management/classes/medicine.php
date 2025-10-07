<?php
class Medicine
{
    private $conn;

    public function __construct($dbConnection)
    {
        $this->conn = $dbConnection;
    }

    public function getAllMedicines()
    {
        $query = "SELECT med_id, med_name, category, dosage, stock_quantity, unit_price, unit, status 
              FROM pharmacy_inventory 
              ORDER BY med_name ASC";
        $result = $this->conn->query($query);
        if (!$result) throw new Exception("Error fetching medicines: " . $this->conn->error);
        return $result->fetch_all(MYSQLI_ASSOC);
    }



    // Fetch stock batches for a medicine
    public function getStockBatches($med_id)
    {
        $stmt = $this->conn->prepare("
            SELECT * FROM pharmacy_stock_batches 
            WHERE med_id = ? AND stock_quantity > 0
            ORDER BY expiry_date ASC
        ");
        $stmt->bind_param("i", $med_id);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    public function addStock($med_id, $quantity, $expiry_date = null, $batch_no = null)
    {
        if (!$expiry_date) {
            throw new Exception("Expiry date must be provided when adding stock.");
        }

        // Auto-generate batch number if not provided
        if (!$batch_no) {
            $batch_no = 'B' . date('YmdHis'); // e.g., B20250901153045
        }

        // Insert batch
        $stmt = $this->conn->prepare("
        INSERT INTO pharmacy_stock_batches (med_id, batch_no, stock_quantity, expiry_date)
        VALUES (?, ?, ?, ?)
    ");
        $stmt->bind_param("isis", $med_id, $batch_no, $quantity, $expiry_date);
        if (!$stmt->execute()) {
            throw new Exception("Failed to add stock batch: " . $stmt->error);
        }

        // Update total inventory stock to match sum of batches
        $stmt2 = $this->conn->prepare("
        UPDATE pharmacy_inventory
        SET stock_quantity = (SELECT IFNULL(SUM(stock_quantity),0) FROM pharmacy_stock_batches WHERE med_id = ?)
        WHERE med_id = ?
    ");
        $stmt2->bind_param("ii", $med_id, $med_id);
        $stmt2->execute();

        // Update status
        $this->autoUpdateStatus($med_id);

        return true;
    }



    // Dispense medicine (FIFO by earliest expiry)
    public function dispenseMedicine($med_id, $dispense_qty)
    {
        $stmt = $this->conn->prepare("
            SELECT batch_id, stock_quantity 
            FROM pharmacy_stock_batches 
            WHERE med_id = ? AND stock_quantity > 0
            ORDER BY expiry_date ASC
        ");
        $stmt->bind_param("i", $med_id);
        $stmt->execute();
        $result = $stmt->get_result();

        while ($dispense_qty > 0 && ($row = $result->fetch_assoc())) {
            $batch_id = $row['batch_id'];
            $available = $row['stock_quantity'];

            if ($available >= $dispense_qty) {
                $new_qty = $available - $dispense_qty;
                $update = $this->conn->prepare("UPDATE pharmacy_stock_batches SET stock_quantity = ? WHERE batch_id = ?");
                $update->bind_param("ii", $new_qty, $batch_id);
                $update->execute();
                $dispense_qty = 0;
            } else {
                $dispense_qty -= $available;
                $update = $this->conn->prepare("UPDATE pharmacy_stock_batches SET stock_quantity = 0 WHERE batch_id = ?");
                $update->bind_param("i", $batch_id);
                $update->execute();
            }
        }

        // Update inventory total stock
        $stmt2 = $this->conn->prepare("
            UPDATE pharmacy_inventory
            SET stock_quantity = (SELECT IFNULL(SUM(stock_quantity),0) FROM pharmacy_stock_batches WHERE med_id = ?)
            WHERE med_id = ?
        ");
        $stmt2->bind_param("ii", $med_id, $med_id);
        $stmt2->execute();

        $this->autoUpdateStatus($med_id);

        return $dispense_qty === 0;
    }

    // Update medicine general info
    public function updateMedicine($med_id, $med_name, $category, $dosage, $unit, $unit_price)
    {
        $stmt = $this->conn->prepare("
            UPDATE pharmacy_inventory 
            SET med_name = ?, category = ?, dosage = ?, unit = ?, unit_price = ?
            WHERE med_id = ?
        ");
        $stmt->bind_param("sssdsi", $med_name, $category, $dosage, $unit, $unit_price, $med_id);
        if (!$stmt->execute()) {
            throw new Exception("Failed to update medicine: " . $stmt->error);
        }
        return true;
    }

    // Auto-update stock status
    public function autoUpdateStatus($med_id = null)
    {
        if ($med_id) {
            // Single medicine
            $stmt = $this->conn->prepare("
                UPDATE pharmacy_inventory i
                JOIN (SELECT med_id, SUM(stock_quantity) AS total_qty FROM pharmacy_stock_batches WHERE med_id = ? GROUP BY med_id) b
                ON i.med_id = b.med_id
                SET i.status = CASE WHEN b.total_qty > 0 THEN 'Available' ELSE 'Out of Stock' END
            ");
            $stmt->bind_param("i", $med_id);
            $stmt->execute();
        } else {
            // All medicines
            $this->conn->query("
                UPDATE pharmacy_inventory i
                LEFT JOIN (SELECT med_id, SUM(stock_quantity) AS total_qty FROM pharmacy_stock_batches GROUP BY med_id) b
                ON i.med_id = b.med_id
                SET i.status = CASE WHEN b.total_qty > 0 THEN 'Available' ELSE 'Out of Stock' END
            ");
        }
    }

    // Get expiry tracking
    public function getExpiryTracking()
    {
        $query = "
            SELECT i.med_name, b.batch_no, b.stock_quantity, b.expiry_date,
            CASE 
                WHEN b.expiry_date < CURDATE() THEN 'Expired'
                WHEN b.expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY) THEN 'Near Expiry'
                ELSE 'Available'
            END AS status
            FROM pharmacy_stock_batches b
            JOIN pharmacy_inventory i ON i.med_id = b.med_id
            ORDER BY b.expiry_date ASC
        ";
        $result = $this->conn->query($query);
        if (!$result) {
            throw new Exception("Failed to fetch expiry tracking: " . $this->conn->error);
        }
        return $result->fetch_all(MYSQLI_ASSOC);
    }

    public function autoUpdateOutOfStock()
    {
        $sql = "UPDATE pharmacy_inventory SET status = CASE WHEN stock_quantity = 0 THEN 'Out of Stock' ELSE 'Available' END";
        $this->conn->query($sql);
    }
    public function getAllBatches()
    {
        $query = "
        SELECT 
            i.med_id,
            i.med_name,
            i.unit_price, -- get price from pharmacy_inventory
            b.batch_id,
            b.batch_no,
            b.stock_quantity,
            b.expiry_date,
            CASE 
                WHEN b.expiry_date < CURDATE() THEN 'Expired'
                WHEN b.expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY) THEN 'Near Expiry'
                ELSE 'Available'
            END AS status
        FROM pharmacy_stock_batches b
        JOIN pharmacy_inventory i ON i.med_id = b.med_id
        ORDER BY b.expiry_date ASC, i.med_name
    ";

        $result = $this->conn->query($query);
        if (!$result) throw new Exception("Error fetching batches: " . $this->conn->error);
        return $result->fetch_all(MYSQLI_ASSOC);
    }
}
