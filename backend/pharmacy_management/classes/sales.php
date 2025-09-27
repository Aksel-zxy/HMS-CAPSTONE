<?php
// sales.php (class)
class Sales
{
    private $conn;

    public function __construct($db)
    {
        $this->conn = $db;
    }

    // Total Sales (only cash)
    public function getTotalSales($period = 'all')
    {
        $where = "1";
        if ($period === '7days') {
            $where = "i.dispensed_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
        } elseif ($period === 'month') {
            $where = "MONTH(i.dispensed_date) = MONTH(CURDATE()) AND YEAR(i.dispensed_date) = YEAR(CURDATE())";
        } elseif ($period === 'last_month') {
            $where = "MONTH(i.dispensed_date) = MONTH(CURDATE() - INTERVAL 1 MONTH) AND YEAR(i.dispensed_date) = YEAR(CURDATE() - INTERVAL 1 MONTH)";
        }

        $sql = "SELECT SUM(i.total_price) as total
                FROM pharmacy_prescription_items i
                JOIN pharmacy_prescription p ON i.prescription_id = p.prescription_id
                WHERE i.quantity_dispensed > 0
                  AND p.payment_type = 'cash'
                  AND $where";
        $result = $this->conn->query($sql);
        if (!$result) die("Query failed in getTotalSales(): " . $this->conn->error);

        $row = $result->fetch_assoc();
        return $row['total'] ?? 0;
    }

    // Total Orders (only cash)
    public function getTotalOrders($period = 'all')
    {
        $where = "1";
        if ($period === '7days') {
            $where = "i.dispensed_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
        } elseif ($period === 'month') {
            $where = "MONTH(i.dispensed_date) = MONTH(CURDATE()) AND YEAR(i.dispensed_date) = YEAR(CURDATE())";
        } elseif ($period === 'last_month') {
            $where = "MONTH(i.dispensed_date) = MONTH(CURDATE() - INTERVAL 1 MONTH) AND YEAR(i.dispensed_date) = YEAR(CURDATE() - INTERVAL 1 MONTH)";
        }

        $sql = "SELECT COUNT(DISTINCT p.prescription_id) as orders
                FROM pharmacy_prescription_items i
                JOIN pharmacy_prescription p ON i.prescription_id = p.prescription_id
                WHERE i.quantity_dispensed > 0
                  AND p.payment_type = 'cash'
                  AND $where";
        $result = $this->conn->query($sql);
        $row = $result->fetch_assoc();
        return $row['orders'] ?? 0;
    }

    // Revenue by Category (only cash)
    public function getRevenueByCategory($period = 'all')
    {
        $where = "1";
        if ($period === '7days') {
            $where = "i.dispensed_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
        } elseif ($period === 'month') {
            $where = "MONTH(i.dispensed_date) = MONTH(CURDATE()) AND YEAR(i.dispensed_date) = YEAR(CURDATE())";
        } elseif ($period === 'last_month') {
            $where = "MONTH(i.dispensed_date) = MONTH(CURDATE() - INTERVAL 1 MONTH) AND YEAR(i.dispensed_date) = YEAR(CURDATE() - INTERVAL 1 MONTH)";
        }

        $sql = "SELECT inv.category, SUM(i.total_price) AS total
                FROM pharmacy_prescription_items i
                JOIN pharmacy_inventory inv ON i.med_id = inv.med_id
                JOIN pharmacy_prescription p ON i.prescription_id = p.prescription_id
                WHERE i.quantity_dispensed > 0
                  AND p.payment_type = 'cash'
                  AND $where
                GROUP BY inv.category";
        $result = $this->conn->query($sql);
        if (!$result) die("Query failed in getRevenueByCategory(): " . $this->conn->error);

        $data = [];
        while ($row = $result->fetch_assoc()) $data[] = $row;
        return $data;
    }

    // Top Products (only cash)
    public function getTopProducts($period = 'all', $limit = 10)
    {
        $where = "1";
        if ($period === '7days') {
            $where = "i.dispensed_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
        } elseif ($period === 'month') {
            $where = "MONTH(i.dispensed_date) = MONTH(CURDATE()) AND YEAR(i.dispensed_date) = YEAR(CURDATE())";
        } elseif ($period === 'last_month') {
            $where = "MONTH(i.dispensed_date) = MONTH(CURDATE() - INTERVAL 1 MONTH) AND YEAR(i.dispensed_date) = YEAR(CURDATE() - INTERVAL 1 MONTH)";
        }

        $sql = "SELECT 
                    inv.med_name,
                    inv.category,
                    SUM(i.quantity_dispensed) AS qty,
                    SUM(i.total_price) AS total,
                    inv.expiry_date
                FROM pharmacy_prescription_items i
                JOIN pharmacy_inventory inv ON i.med_id = inv.med_id
                JOIN pharmacy_prescription p ON i.prescription_id = p.prescription_id
                WHERE i.quantity_dispensed > 0
                  AND p.payment_type = 'cash'
                  AND $where
                GROUP BY inv.med_id, inv.med_name, inv.category, inv.expiry_date
                ORDER BY qty DESC
                LIMIT {$limit}";
        $result = $this->conn->query($sql);
        if (!$result) die("Query failed in getTopProducts(): " . $this->conn->error);

        return $result;
    }

    // Sales Performance (Weekly, Monthly, Yearly) (only cash)
    public function getSalesPerformance()
    {
        $data = [];

        // Weekly (last 7 days)
        $sqlWeek = "
            SELECT DATE_FORMAT(i.dispensed_date, '%a') AS day_name, SUM(i.total_price) AS total
            FROM pharmacy_prescription_items i
            JOIN pharmacy_prescription p ON i.prescription_id = p.prescription_id
            WHERE i.quantity_dispensed > 0
              AND p.payment_type = 'cash'
              AND i.dispensed_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
            GROUP BY DAY(i.dispensed_date)
            ORDER BY i.dispensed_date ASC
        ";
        $result = $this->conn->query($sqlWeek);
        $daysOfWeek = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
        $weekly = array_fill_keys($daysOfWeek, 0);
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $weekly[$row['day_name']] = floatval($row['total']);
            }
        }

        // Monthly (current month)
        $sqlMonth = "
            SELECT DAY(i.dispensed_date) AS day_num, SUM(i.total_price) AS total
            FROM pharmacy_prescription_items i
            JOIN pharmacy_prescription p ON i.prescription_id = p.prescription_id
            WHERE i.quantity_dispensed > 0
              AND p.payment_type = 'cash'
              AND MONTH(i.dispensed_date) = MONTH(CURDATE())
              AND YEAR(i.dispensed_date) = YEAR(CURDATE())
            GROUP BY DAY(i.dispensed_date)
            ORDER BY i.dispensed_date ASC
        ";
        $result = $this->conn->query($sqlMonth);
        $daysInMonth = range(1, date('t'));
        $monthly = array_fill_keys($daysInMonth, 0);
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $monthly[intval($row['day_num'])] = floatval($row['total']);
            }
        }

        // Yearly (current year)
        $sqlYear = "
            SELECT MONTH(i.dispensed_date) AS month_num, SUM(i.total_price) AS total
            FROM pharmacy_prescription_items i
            JOIN pharmacy_prescription p ON i.prescription_id = p.prescription_id
            WHERE i.quantity_dispensed > 0
              AND p.payment_type = 'cash'
              AND YEAR(i.dispensed_date) = YEAR(CURDATE())
            GROUP BY MONTH(i.dispensed_date)
            ORDER BY MONTH(i.dispensed_date) ASC
        ";
        $result = $this->conn->query($sqlYear);
        $months = range(1, 12);
        $yearly = array_fill_keys($months, 0);
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $yearly[intval($row['month_num'])] = floatval($row['total']);
            }
        }

        $data['weekly']  = $weekly;
        $data['monthly'] = $monthly;
        $data['yearly']  = $yearly;

        return $data;
    }
    // Dispensed Medicines Today (all payment types)
    public function getDispensedToday()
    {
        $sql = "SELECT SUM(i.quantity_dispensed) AS total
                FROM pharmacy_prescription_items i
                JOIN pharmacy_prescription p ON i.prescription_id = p.prescription_id
                WHERE DATE(i.dispensed_date) = CURDATE()
                  AND i.quantity_dispensed > 0";
        $result = $this->conn->query($sql);
        if (!$result) die("Query failed in getDispensedToday(): " . $this->conn->error);

        $row = $result->fetch_assoc();
        return $row['total'] ?? 0;
    }

    // Total Stocks (from inventory)
    public function getTotalStocks()
    {
        $sql = "SELECT SUM(stock_quantity) AS total FROM pharmacy_inventory";
        $result = $this->conn->query($sql);
        if (!$result) die("Query failed in getTotalStocks(): " . $this->conn->error);

        $row = $result->fetch_assoc();
        return $row['total'] ?? 0;
    }
}
