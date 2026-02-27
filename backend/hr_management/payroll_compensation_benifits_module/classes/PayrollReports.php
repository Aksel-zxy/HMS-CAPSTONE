<?php
class PayrollReports {
    private $conn;

    public function __construct($conn) {
        $this->conn = $conn;
    }

    /**
     * Get all payrolls marked as 'Paid', optionally filtered by date range
     *
     * @param string $start Start date (YYYY-MM-DD)
     * @param string $end End date (YYYY-MM-DD)
     * @return array
     */
    public function getPayrolls($start = '', $end = '', $search = '') {
        $sql = "
            SELECT 
                p.payroll_id,
                e.employee_id,
                TRIM(CONCAT(
                    COALESCE(e.first_name, ''), ' ',
                    COALESCE(e.middle_name, ''), ' ',
                    COALESCE(e.last_name, ''), ' ',
                    COALESCE(e.suffix_name, '')
                )) AS employee_name,
                e.profession,
                e.department,
                p.pay_period_start,
                p.pay_period_end,
                p.gross_pay,
                p.total_deductions,
                p.net_pay,
                p.date_generated
            FROM hr_payroll p
            JOIN hr_employees e ON p.employee_id = e.employee_id
            WHERE p.status = 'Paid'
        ";

        $params = [];
        $types = "";

        // DATE FILTER
        if (!empty($start) && !empty($end)) {
            $sql .= " AND p.pay_period_start >= ? AND p.pay_period_end <= ?";
            $params[] = $start;
            $params[] = $end;
            $types .= "ss";
        }

        // SEARCH FILTER
        if (!empty($search)) {
            $words = explode(' ', $search);
            $sql .= " AND (";

            foreach ($words as $index => $word) {
                $word = trim($word);
                if ($word === '') continue;

                if ($index > 0) $sql .= " AND "; // all words must match somewhere

                $sql .= "(
                    e.first_name LIKE ?
                    OR e.middle_name LIKE ?
                    OR e.last_name LIKE ?
                    OR e.suffix_name LIKE ?
                    OR e.profession LIKE ?
                    OR e.role LIKE ?
                    OR e.department LIKE ?
                )";

                // Add params for this word (7s columns now)
                $searchTerm = "%{$word}%";
                for ($i = 0; $i < 7; $i++) {
                    $params[] = $searchTerm;
                    $types .= "s";
                }
            }

            $sql .= ")";
        }

        $sql .= " ORDER BY p.date_generated DESC";

        $stmt = $this->conn->prepare($sql);

        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }

        $stmt->execute();
        $result = $stmt->get_result();

        $payrolls = [];
        while ($row = $result->fetch_assoc()) {
            $row['gross_pay'] = (float) ($row['gross_pay'] ?? 0);
            $row['total_deductions'] = (float) ($row['total_deductions'] ?? 0);
            $row['net_pay'] = (float) ($row['net_pay'] ?? 0);

            $payrolls[] = $row;
        }

        return $payrolls;
    }

    /**
     * Get payroll summary totals
     *
     * @param array $payrolls Array of payroll rows
     * @return array ['total_gross' => , 'total_deductions' => , 'total_net' => ]
     */
    public function getSummaryTotals($payrolls) {
        $totalGross = $totalDeductions = $totalNet = 0;

        foreach ($payrolls as $row) {
            $totalGross += $row['gross_pay'];
            $totalDeductions += $row['total_deductions'];
            $totalNet += $row['net_pay'];
        }

        return [
            'total_gross' => $totalGross,
            'total_deductions' => $totalDeductions,
            'total_net' => $totalNet
        ];
    }

    /**
     * Format totals for display
     *
     * @param float $amount
     * @return string
     */
    public function formatCurrency($amount) {
        return number_format($amount, 2);
    }
}
