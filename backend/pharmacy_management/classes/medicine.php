<?php
class Medicine
{
    private $conn;

    // ===== NEW: Category-based locations =====
    private $category_locations = array(
        'Antibiotic' => array('Storage Room' => 'Main', 'Shelf No' => 1, 'Rack No' => 1),
        'Painkiller' => array('Storage Room' => 'Main', 'Shelf No' => 1, 'Rack No' => 2),
        'Vitamins' => array('Storage Room' => 'Main', 'Shelf No' => 2, 'Rack No' => 1),
        'Cold & Flu' => array('Storage Room' => 'Main', 'Shelf No' => 2, 'Rack No' => 2),
        'Others' => array('Storage Room' => 'Main', 'Shelf No' => 3, 'Rack No' => 1)
    );

    public function __construct($dbConnection)
    {
        $this->conn = $dbConnection;
    }

    // ===== NEW: Assign location based on category =====
    private function assignLocationByCategory($category)
    {
        $base = isset($this->category_locations[$category]) ? $this->category_locations[$category] : $this->category_locations['Others'];

        $storage_room = $base['Storage Room'];
        $shelf_no = $base['Shelf No'];
        $rack_no = $base['Rack No'];

        // Get last used Bin in this Shelf/Rack
        $query = "SELECT `Bin No` 
                  FROM `pharmacy_inventory`
                  WHERE `Storage Room`='$storage_room' AND `Shelf No`='$shelf_no' AND `Rack No`='$rack_no'
                  ORDER BY `Bin No` DESC LIMIT 1";
        $result = mysqli_query($this->conn, $query);

        if (mysqli_num_rows($result) > 0) {
            $row = mysqli_fetch_assoc($result);
            $bin_no = intval($row['Bin No']) + 1;
        } else {
            $bin_no = 1;
        }

        // Optional: max 10 bins per rack
        if ($bin_no > 10) {
            $bin_no = 1;
            $rack_no += 1;
        }

        return array(
            'Storage Room' => $storage_room,
            'Shelf No' => $shelf_no,
            'Rack No' => $rack_no,
            'Bin No' => $bin_no
        );
    }

    // ===== NEW: Add medicine with auto-location =====
    public function addMedicineWithAutoLocation($med_name, $generic_name, $brand_name, $prescription_required, $category, $dosage, $unit, $unit_price, $stock_quantity)
    {
        $location = $this->assignLocationByCategory($category);

        $stmt = $this->conn->prepare("
            INSERT INTO pharmacy_inventory (med_name, generic_name, brand_name, prescription_required, category, dosage, unit, unit_price, stock_quantity, `Storage Room`, `Shelf No`, `Rack No`, `Bin No`)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param(
            "ssssssdiiiiii",
            $med_name,
            $generic_name,
            $brand_name,
            $prescription_required,
            $category,
            $dosage,
            $unit,
            $unit_price,
            $stock_quantity,
            $location['Storage Room'],
            $location['Shelf No'],
            $location['Rack No'],
            $location['Bin No']
        );

        if (!$stmt->execute()) {
            throw new Exception("Failed to add medicine with auto-location: " . $stmt->error);
        }

        return true;
    }

    // =================== Existing methods ===================
    public function getAllMedicines()
    {
        $query = "SELECT med_id, med_name, generic_name, brand_name, prescription_required, category, dosage, stock_quantity, unit_price, unit, status 
                  FROM pharmacy_inventory 
                  ORDER BY med_name ASC";
        $result = $this->conn->query($query);
        if (!$result) throw new Exception("Error fetching medicines: " . $this->conn->error);
        return $result->fetch_all(MYSQLI_ASSOC);
    }

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

        if (!$batch_no) {
            $batch_no = 'B' . date('YmdHis');
        }

        $stmt = $this->conn->prepare("
            INSERT INTO pharmacy_stock_batches (med_id, batch_no, stock_quantity, expiry_date)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->bind_param("isis", $med_id, $batch_no, $quantity, $expiry_date);
        if (!$stmt->execute()) {
            throw new Exception("Failed to add stock batch: " . $stmt->error);
        }

        $stmt2 = $this->conn->prepare("
            UPDATE pharmacy_inventory
            SET stock_quantity = (SELECT IFNULL(SUM(stock_quantity),0) FROM pharmacy_stock_batches WHERE med_id = ?)
            WHERE med_id = ?
        ");
        $stmt2->bind_param("ii", $med_id, $med_id);
        $stmt2->execute();

        $this->autoUpdateStatus($med_id);
        return true;
    }

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

    public function updateMedicine($med_id, $med_name, $generic_name, $brand_name, $prescription_required, $category, $dosage, $unit, $unit_price)
    {
        $stmt = $this->conn->prepare("
            UPDATE pharmacy_inventory 
            SET med_name = ?, generic_name = ?, brand_name = ?, prescription_required = ?, category = ?, dosage = ?, unit = ?, unit_price = ?
            WHERE med_id = ?
        ");
        $stmt->bind_param("sssssssdi", $med_name, $generic_name, $brand_name, $prescription_required, $category, $dosage, $unit, $unit_price, $med_id);
        if (!$stmt->execute()) {
            throw new Exception("Failed to update medicine: " . $stmt->error);
        }
        return true;
    }

    public function autoUpdateStatus($med_id = null)
    {
        if ($med_id) {
            $stmt = $this->conn->prepare("
                UPDATE pharmacy_inventory i
                JOIN (SELECT med_id, SUM(stock_quantity) AS total_qty FROM pharmacy_stock_batches WHERE med_id = ? GROUP BY med_id) b
                ON i.med_id = b.med_id
                SET i.status = CASE WHEN b.total_qty > 0 THEN 'Available' ELSE 'Out of Stock' END
            ");
            $stmt->bind_param("i", $med_id);
            $stmt->execute();
        } else {
            $this->conn->query("
                UPDATE pharmacy_inventory i
                LEFT JOIN (SELECT med_id, SUM(stock_quantity) AS total_qty FROM pharmacy_stock_batches GROUP BY med_id) b
                ON i.med_id = b.med_id
                SET i.status = CASE WHEN b.total_qty > 0 THEN 'Available' ELSE 'Out of Stock' END
            ");
        }
    }

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
        if (!$result) throw new Exception("Failed to fetch expiry tracking: " . $this->conn->error);
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
            i.generic_name,
            i.brand_name,
            i.prescription_required,
            i.unit_price,
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

    public function updateStatus($med_id, $new_status)
    {
        $stmt = $this->conn->prepare("
            UPDATE pharmacy_inventory 
            SET status = ? 
            WHERE med_id = ?
        ");

        if (!$stmt) {
            throw new Exception("Prepare failed: " . $this->conn->error);
        }

        $stmt->bind_param("si", $new_status, $med_id);

        if (!$stmt->execute()) {
            throw new Exception("Execute failed: " . $stmt->error);
        }

        $stmt->close();
        return true;
    }
}
