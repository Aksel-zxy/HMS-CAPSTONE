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
    public function getTotalCashSales($period = 'all')
    {
        $wherePrescription = "1";
        $whereOTC = "1";

        if ($period === '7days') {
            $wherePrescription = "i.dispensed_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
            $whereOTC = "sale_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
        } elseif ($period === 'month') {
            $wherePrescription = "MONTH(i.dispensed_date) = MONTH(CURDATE()) AND YEAR(i.dispensed_date) = YEAR(CURDATE())";
            $whereOTC = "MONTH(sale_date) = MONTH(CURDATE()) AND YEAR(sale_date) = YEAR(CURDATE())";
        } elseif ($period === 'last_month') {
            $wherePrescription = "MONTH(i.dispensed_date) = MONTH(CURDATE() - INTERVAL 1 MONTH)
                              AND YEAR(i.dispensed_date) = YEAR(CURDATE() - INTERVAL 1 MONTH)";
            $whereOTC = "MONTH(sale_date) = MONTH(CURDATE() - INTERVAL 1 MONTH)
                     AND YEAR(sale_date) = YEAR(CURDATE() - INTERVAL 1 MONTH)";
        }

        $sql = "
    SELECT SUM(total) as total FROM (
        -- Prescription cash sales only
        SELECT SUM(i.total_price) as total
        FROM pharmacy_prescription_items i
        JOIN pharmacy_prescription p ON i.prescription_id = p.prescription_id
        WHERE i.quantity_dispensed > 0
          AND p.payment_type = 'cash'
          AND $wherePrescription

        UNION ALL

        -- OTC cash sales only
        SELECT SUM(total_price) as total
        FROM pharmacy_sales
        WHERE transaction_type='OTC'
          AND payment_method='cash'
          AND $whereOTC
    ) combined
    ";

        $result = $this->conn->query($sql);
        $row = $result->fetch_assoc();
        return $row['total'] ?? 0;
    }

    // Total Orders (only cash)
    public function getTotalOrders($period = 'all')
    {
        $wherePrescription = "1";
        $whereOTC = "1";

        if ($period === '7days') {
            $wherePrescription = "i.dispensed_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
            $whereOTC = "sale_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
        } elseif ($period === 'month') {
            $wherePrescription = "MONTH(i.dispensed_date)=MONTH(CURDATE()) AND YEAR(i.dispensed_date)=YEAR(CURDATE())";
            $whereOTC = "MONTH(sale_date)=MONTH(CURDATE()) AND YEAR(sale_date)=YEAR(CURDATE())";
        } elseif ($period === 'last_month') {
            $wherePrescription = "MONTH(i.dispensed_date)=MONTH(CURDATE()-INTERVAL 1 MONTH)
                              AND YEAR(i.dispensed_date)=YEAR(CURDATE()-INTERVAL 1 MONTH)";
            $whereOTC = "MONTH(sale_date)=MONTH(CURDATE()-INTERVAL 1 MONTH)
                     AND YEAR(sale_date)=YEAR(CURDATE()-INTERVAL 1 MONTH)";
        }

        $sql = "
        SELECT SUM(total_orders) AS orders FROM (
            -- Prescription orders
            SELECT COUNT(DISTINCT p.prescription_id) AS total_orders
            FROM pharmacy_prescription_items i
            JOIN pharmacy_prescription p ON i.prescription_id = p.prescription_id
            WHERE i.quantity_dispensed > 0
              AND $wherePrescription

            UNION ALL

            -- OTC orders
            SELECT COUNT(sale_id) AS total_orders
            FROM pharmacy_sales
            WHERE transaction_type='OTC'
              AND $whereOTC
        ) combined
    ";

        $result = $this->conn->query($sql);
        $row = $result->fetch_assoc();
        return $row['orders'] ?? 0;
    }

    // Revenue by Category (only cash)
    public function getRevenueByCategory($period = 'all')
    {
        $wherePrescription = "1";
        $whereOTC = "1";

        if ($period === '7days') {
            $wherePrescription = "i.dispensed_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
            $whereOTC = "sale_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
        } elseif ($period === 'month') {
            $wherePrescription = "MONTH(i.dispensed_date)=MONTH(CURDATE()) AND YEAR(i.dispensed_date)=YEAR(CURDATE())";
            $whereOTC = "MONTH(sale_date)=MONTH(CURDATE()) AND YEAR(sale_date)=YEAR(CURDATE())";
        } elseif ($period === 'last_month') {
            $wherePrescription = "MONTH(i.dispensed_date)=MONTH(CURDATE()-INTERVAL 1 MONTH)
                              AND YEAR(i.dispensed_date)=YEAR(CURDATE()-INTERVAL 1 MONTH)";
            $whereOTC = "MONTH(sale_date)=MONTH(CURDATE()-INTERVAL 1 MONTH)
                     AND YEAR(sale_date)=YEAR(CURDATE()-INTERVAL 1 MONTH)";
        }

        $sql = "
    SELECT category, SUM(total) as total
    FROM (
        -- Prescription Sales as one category
        SELECT 'Prescription Sales' as category, SUM(i.total_price) as total
        FROM pharmacy_prescription_items i
        JOIN pharmacy_prescription p ON i.prescription_id = p.prescription_id
        WHERE i.quantity_dispensed > 0
          AND p.status = 'Dispensed'
          AND $wherePrescription

        UNION ALL

        -- OTC Sales as one category
        SELECT 'OTC Sales' as category, SUM(total_price) as total
        FROM pharmacy_sales
        WHERE transaction_type='OTC'
          AND $whereOTC
    ) combined
    GROUP BY category
    ";

        $result = $this->conn->query($sql);
        $data = [];
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $data[] = $row;
            }
        }
        return $data;
    }

    // Top Products (only cash)
    public function getTopProducts($period = 'all', $limit = 10)
    {
        $wherePrescription = "1";
        $whereOTC = "1";

        if ($period === '7days') {
            $wherePrescription = "i.dispensed_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
            $whereOTC = "sale_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
        } elseif ($period === 'month') {
            $wherePrescription = "MONTH(i.dispensed_date)=MONTH(CURDATE()) AND YEAR(i.dispensed_date)=YEAR(CURDATE())";
            $whereOTC = "MONTH(sale_date)=MONTH(CURDATE()) AND YEAR(sale_date)=YEAR(CURDATE())";
        } elseif ($period === 'last_month') {
            $wherePrescription = "MONTH(i.dispensed_date)=MONTH(CURDATE()-INTERVAL 1 MONTH)
                              AND YEAR(i.dispensed_date)=YEAR(CURDATE()-INTERVAL 1 MONTH)";
            $whereOTC = "MONTH(sale_date)=MONTH(CURDATE()-INTERVAL 1 MONTH)
                     AND YEAR(sale_date)=YEAR(CURDATE()-INTERVAL 1 MONTH)";
        }

        $sql = "
        SELECT med_name, SUM(qty) as qty, SUM(total) as total
        FROM (
            -- Prescription
            SELECT inv.med_name,
                   i.quantity_dispensed as qty,
                   i.total_price as total
            FROM pharmacy_prescription_items i
            JOIN pharmacy_inventory inv ON i.med_id = inv.med_id
            JOIN pharmacy_prescription p ON i.prescription_id = p.prescription_id
            WHERE i.quantity_dispensed > 0
              AND $wherePrescription

            UNION ALL

            -- OTC
            SELECT med_name,
                   quantity_sold as qty,
                   total_price as total
            FROM pharmacy_sales
            WHERE transaction_type='OTC'
              AND $whereOTC
        ) combined
        GROUP BY med_name
        ORDER BY qty DESC
        LIMIT {$limit}
    ";

        return $this->conn->query($sql);
    }

    public function getSalesPerformance()
    {
        $performance = [
            'weekly'  => [],
            'monthly' => [],
            'yearly'  => []
        ];

        // -------------------- PRESCRIPTION ITEMS (cash only) --------------------
        $prescQuery = "
        SELECT DATE(i.dispensed_date) AS date, SUM(i.total_price) AS total
        FROM pharmacy_prescription_items i
        INNER JOIN pharmacy_prescription AS p ON i.prescription_id = p.prescription_id
        WHERE p.status = 'Dispensed'
          AND p.payment_type = 'cash'
        GROUP BY DATE(i.dispensed_date)
    ";

        $prescResult = $this->conn->query($prescQuery);
        $prescData = [];
        while ($row = $prescResult->fetch_assoc()) {
            $prescData[$row['date']] = floatval($row['total']);
        }

        // -------------------- OTC / SALES (cash only) --------------------
        $otcQuery = "
        SELECT DATE(sale_date) AS date, SUM(total_price) AS total
        FROM pharmacy_sales
        WHERE payment_method = 'cash'
        GROUP BY DATE(sale_date)
    ";

        $otcResult = $this->conn->query($otcQuery);
        $otcData = [];
        while ($row = $otcResult->fetch_assoc()) {
            $otcData[$row['date']] = floatval($row['total']);
        }

        // -------------------- MERGE DATA --------------------
        $allDates = array_unique(array_merge(array_keys($prescData), array_keys($otcData)));
        sort($allDates);

        // -------------------- WEEKLY --------------------
        $performance['weekly'] = [
            'Sun' => 0,
            'Mon' => 0,
            'Tue' => 0,
            'Wed' => 0,
            'Thu' => 0,
            'Fri' => 0,
            'Sat' => 0
        ];

        // -------------------- MONTHLY --------------------
        $daysInMonth = date('t'); // current month total days
        for ($d = 1; $d <= $daysInMonth; $d++) {
            $performance['monthly'][$d] = 0;
        }

        // -------------------- YEARLY --------------------
        for ($m = 1; $m <= 12; $m++) {
            $performance['yearly'][$m] = 0;
        }

        // -------------------- FILL DATA --------------------
        foreach ($allDates as $date) {
            $total = ($prescData[$date] ?? 0) + ($otcData[$date] ?? 0);
            $timestamp = strtotime($date);

            // Weekly
            $weekDay = date('D', $timestamp);
            $performance['weekly'][$weekDay] += $total;

            // Monthly
            $day = date('j', $timestamp);
            $performance['monthly'][$day] += $total;

            // Yearly
            $month = date('n', $timestamp);
            $performance['yearly'][$month] += $total;
        }

        return $performance;
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
