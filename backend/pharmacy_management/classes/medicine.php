<?php
class Medicine
{
    private $conn;



    public function __construct($dbConnection)
    {
        $this->conn = $dbConnection;
    }


    private function assignLocationAutomatically()
    {
        $query = "
        SELECT storage_room, shelf_no, rack_no, bin_no
        FROM pharmacy_inventory
        ORDER BY shelf_no DESC, rack_no DESC, bin_no DESC
        LIMIT 1
    ";

        $result = mysqli_query($this->conn, $query);

        if (mysqli_num_rows($result) > 0) {
            $row = mysqli_fetch_assoc($result);

            $storage_room = 'Main';
            $shelf_no = intval($row['shelf_no']);
            $rack_no = intval($row['rack_no']);
            $bin_no = intval($row['bin_no']) + 1;
        } else {
            $storage_room = 'Main';
            $shelf_no = 1;
            $rack_no = 1;
            $bin_no = 1;
        }

        // Max 10 bins per rack
        if ($bin_no > 10) {
            $bin_no = 1;
            $rack_no += 1;
        }

        // Max 5 racks per shelf
        if ($rack_no > 5) {
            $rack_no = 1;
            $shelf_no += 1;
        }

        return array(
            'storage_room' => $storage_room,
            'shelf_no' => $shelf_no,
            'rack_no' => $rack_no,
            'bin_no' => $bin_no
        );
    }



    public function addMedicineWithAutoLocation(
        $med_name,
        $generic_name,
        $brand_name,
        $prescription_required,
        $category,
        $dosage,
        $unit,
        $unit_price,
        $initial_stock = 0,
        $expiry_date = null
    ) {
        // Shelf life in years by unit/formulation
        $shelf_life = [
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

        // Auto-calculate expiry date if not provided
        if (!$expiry_date) {
            $years = $shelf_life[$unit] ?? 1; // default 1 year
            $expiry_date = date("Y-m-d", strtotime("+$years year"));
        }

        $location = $this->assignLocationAutomatically();

        $this->conn->begin_transaction();

        try {
            // 1️⃣ Insert medicine into inventory
            $stmt = $this->conn->prepare("
            INSERT INTO pharmacy_inventory 
            (med_name, generic_name, brand_name, prescription_required, category, dosage, unit, unit_price, stock_quantity, storage_room, shelf_no, rack_no, bin_no)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
            $stmt->bind_param(
                "sssssssdisiii",
                $med_name,
                $generic_name,
                $brand_name,
                $prescription_required,
                $category,
                $dosage,
                $unit,
                $unit_price,
                $initial_stock,
                $location['storage_room'],
                $location['shelf_no'],
                $location['rack_no'],
                $location['bin_no']
            );

            if (!$stmt->execute()) {
                throw new Exception("Failed to add medicine: " . $stmt->error);
            }

            $new_med_id = $this->conn->insert_id;

            // 2️⃣ Create initial batch if stock > 0
            if ($initial_stock > 0) {
                $batch_no = 'B' . date('YmdHis');
                $stmt2 = $this->conn->prepare("
                INSERT INTO pharmacy_stock_batches (med_id, batch_no, stock_quantity, expiry_date)
                VALUES (?, ?, ?, ?)
            ");
                $stmt2->bind_param("isis", $new_med_id, $batch_no, $initial_stock, $expiry_date);

                if (!$stmt2->execute()) {
                    throw new Exception("Failed to create initial batch: " . $stmt2->error);
                }
            }

            // 3️⃣ Update stock_quantity in inventory based on batches
            $stmt3 = $this->conn->prepare("
            UPDATE pharmacy_inventory
            SET stock_quantity = (SELECT IFNULL(SUM(stock_quantity),0) FROM pharmacy_stock_batches WHERE med_id = ?)
            WHERE med_id = ?
        ");
            $stmt3->bind_param("ii", $new_med_id, $new_med_id);
            $stmt3->execute();

            // 4️⃣ Update status
            $this->autoUpdateStatus($new_med_id);

            $this->conn->commit();
            return true;
        } catch (Exception $e) {
            $this->conn->rollback();
            throw $e;
        }
    }




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
