<?php
class CompensationBenefits {
    private $conn;

    public function __construct($conn) {
        $this->conn = $conn;
    }

    // Get all employees
    public function getEmployees() {
        $employees = [];
        $result = $this->conn->query("
            SELECT 
                employee_id,
                TRIM(
                    CONCAT(
                        COALESCE(first_name, ''),
                        ' ',
                        COALESCE(middle_name, ''),
                        ' ',
                        COALESCE(last_name, ''),
                        ' ',
                        COALESCE(suffix_name, '')
                    )
                ) AS full_name
            FROM hr_employees
            ORDER BY employee_id ASC
        ");

        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $employees[] = $row;
            }
        }
        return $employees;
    }

    // Get C&B for a specific employee and period
    public function getByEmployeeAndPeriod($employee_id, $pay_period) {
        $stmt = $this->conn->prepare("SELECT * FROM hr_compensation_benefits WHERE employee_id=? AND pay_period=?");
        $stmt->bind_param("is", $employee_id, $pay_period);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }

    // Save or update C&B
    public function save($data) {
        $stmt = $this->conn->prepare("
            INSERT INTO hr_compensation_benefits 
            (employee_id, pay_period, basic_pay, allowances, bonuses, sss, philhealth, pagibig, thirteenth_month) 
            VALUES (?,?,?,?,?,?,?,?,?)
            ON DUPLICATE KEY UPDATE 
            basic_pay=?, allowances=?, bonuses=?, sss=?, philhealth=?, pagibig=?, thirteenth_month=?
        ");

        $employee_id = $data['employee_id'];
        $pay_period = $data['pay_period'];
        $basic_pay = (float) $data['basic_pay'];
        $allowances = (float) $data['allowances'];
        $bonuses = (float) $data['bonuses'];
        $sss = (float) $data['sss'];
        $philhealth = (float) $data['philhealth'];
        $pagibig = (float) $data['pagibig'];
        $thirteenth_month = (float) ($data['thirteenth_month'] ?? 0);

        $stmt->bind_param(
            "isdddddddddddddd",
            $employee_id,
            $pay_period,
            $basic_pay,
            $allowances,
            $bonuses,
            $sss,
            $philhealth,
            $pagibig,
            $thirteenth_month,
            $basic_pay,
            $allowances,
            $bonuses,
            $sss,
            $philhealth,
            $pagibig,
            $thirteenth_month
        );

        return $stmt->execute();
    }

    // Generate payroll for all active employees
    public function generatePayroll($pay_period, $semi_monthly = true) {
        $period_start = date("Y-m-01", strtotime($pay_period));
        $period_end = date("Y-m-t", strtotime($pay_period));

        $employees = $this->getEmployees();
        foreach ($employees as $emp) {
            $cb = $this->getByEmployeeAndPeriod($emp['employee_id'], $pay_period);
            if (!$cb) continue;

            $periods = $semi_monthly ? 2 : 1;

            for ($i = 1; $i <= $periods; $i++) {
                $basic_pay = (float)$cb['basic_pay'] / $periods;
                $allowances = (float)$cb['allowances'] / $periods;
                $bonuses = (float)$cb['bonuses'] / $periods;
                $thirteenth = (float)$cb['thirteenth_month'] / $periods;

                $sss = (float)$cb['sss'];
                $philhealth = (float)$cb['philhealth'];
                $pagibig = (float)$cb['pagibig'];

                $gross = $basic_pay + $allowances + $bonuses + $thirteenth;
                $total_deduction = $sss + $philhealth + $pagibig;
                $net = $gross - $total_deduction;

                $stmt = $this->conn->prepare("
                    INSERT INTO hr_payroll 
                    (employee_id, pay_period_start, pay_period_end, basic_pay, allowances, bonuses, thirteenth_month, 
                    sss_deduction, philhealth_deduction, pagibig_deduction, gross_pay, total_deductions, net_pay, status, date_generated) 
                    VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?, 'Pending', NOW())
                ");

                $stmt->bind_param(
                    "issiddddddddd",
                    $emp['employee_id'],
                    $period_start,
                    $period_end,
                    $basic_pay,
                    $allowances,
                    $bonuses,
                    $thirteenth,
                    $sss,
                    $philhealth,
                    $pagibig,
                    $gross,
                    $total_deduction,
                    $net
                );

                $stmt->execute();
            }
        }
        return true;
    }


}
