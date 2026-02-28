<?php
class PayrollReports {
    private $conn;

    public function __construct($conn) {
        $this->conn = $conn;
    }

    /**
     * Get all payrolls marked as 'Paid', optionally filtered by date range and search
     *
     * @param string $start Start date (YYYY-MM-DD)
     * @param string $end End date (YYYY-MM-DD)
     * @param string $search Search term (Employee ID, Name, Role, Profession, Department)
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
                e.role,
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

        // -------------------- DATE FILTER --------------------
        if (!empty($start) && !empty($end)) {
            $sql .= " AND p.pay_period_start >= ? AND p.pay_period_end <= ?";
        }

        // -------------------- SEARCH FILTER --------------------
        if (!empty($search)) {
            $sql .= " AND (
                        e.employee_id LIKE ? OR
                        TRIM(CONCAT(
                            COALESCE(e.first_name, ''), ' ',
                            COALESCE(e.middle_name, ''), ' ',
                            COALESCE(e.last_name, ''), ' ',
                            COALESCE(e.suffix_name, '')
                        )) LIKE ? OR
                        e.role LIKE ? OR
                        e.profession LIKE ? OR
                        e.department LIKE ?
                    )";
        }

        $sql .= " ORDER BY p.date_generated DESC";

        $stmt = $this->conn->prepare($sql);

        // -------------------- BIND PARAMETERS --------------------
        if (!empty($start) && !empty($end) && !empty($search)) {
            $searchParam = "%{$search}%";
            $stmt->bind_param("sssssss", $start, $end, $searchParam, $searchParam, $searchParam, $searchParam, $searchParam);
        } elseif (!empty($start) && !empty($end)) {
            $stmt->bind_param("ss", $start, $end);
        } elseif (!empty($search)) {
            $searchParam = "%{$search}%";
            $stmt->bind_param("sssss", $searchParam, $searchParam, $searchParam, $searchParam, $searchParam);
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
     */
    public function formatCurrency($amount) {
        return number_format($amount, 2);
    }
}