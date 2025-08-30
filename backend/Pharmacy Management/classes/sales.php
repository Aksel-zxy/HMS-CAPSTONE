<?php
// sales.php (class)
class Sales
{
    private $conn;

    public function __construct($db)
    {
        $this->conn = $db;
    }

    // Total Sales
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
                WHERE i.quantity_dispensed > 0 AND $where";
        $result = $this->conn->query($sql);
        if (!$result) die("Query failed in getTotalSales(): " . $this->conn->error);

        $row = $result->fetch_assoc();
        return $row['total'] ?? 0;
    }

    // Total Orders
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
                FROM pharmacy_prescription p
                JOIN pharmacy_prescription_items i ON p.prescription_id = i.prescription_id
                WHERE p.status = 'Dispensed' AND $where";
        $result = $this->conn->query($sql);
        $row = $result->fetch_assoc();
        return $row['orders'] ?? 0;
    }

    // Revenue by Category
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
                WHERE i.quantity_dispensed > 0 AND $where
                GROUP BY inv.category";
        $result = $this->conn->query($sql);
        if (!$result) die("Query failed in getRevenueByCategory(): " . $this->conn->error);

        $data = [];
        while ($row = $result->fetch_assoc()) $data[] = $row;
        return $data;
    }

    // Top Products
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
                WHERE i.quantity_dispensed > 0 AND $where
                GROUP BY inv.med_id, inv.med_name, inv.category, inv.expiry_date
                ORDER BY qty DESC
                LIMIT {$limit}";
        $result = $this->conn->query($sql);
        if (!$result) die("Query failed in getTopProducts(): " . $this->conn->error);

        return $result;
    }

    // -------------------- NEW: Sales Performance (Weekly, Monthly, Yearly) --------------------
    public function getSalesPerformance()
    {
        $data = [];

        // Weekly (last 7 days)
        $sqlWeek = "
            SELECT DATE_FORMAT(dispensed_date, '%a') AS day_name, SUM(total_price) AS total
            FROM pharmacy_prescription_items
            WHERE quantity_dispensed > 0
              AND dispensed_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
            GROUP BY DAY(dispensed_date)
            ORDER BY dispensed_date ASC
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
            SELECT DAY(dispensed_date) AS day_num, SUM(total_price) AS total
            FROM pharmacy_prescription_items
            WHERE quantity_dispensed > 0
              AND MONTH(dispensed_date) = MONTH(CURDATE()) 
              AND YEAR(dispensed_date) = YEAR(CURDATE())
            GROUP BY DAY(dispensed_date)
            ORDER BY dispensed_date ASC
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
            SELECT MONTH(dispensed_date) AS month_num, SUM(total_price) AS total
            FROM pharmacy_prescription_items
            WHERE quantity_dispensed > 0
              AND YEAR(dispensed_date) = YEAR(CURDATE())
            GROUP BY MONTH(dispensed_date)
            ORDER BY MONTH(dispensed_date) ASC
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
}
