<?php
class Salary {
    private $conn;

    public function __construct($conn) {
        $this->conn = $conn;
    }

    // Get all employees
    public function getEmployees() {
        $stmt = $this->conn->prepare("
            SELECT 
                employee_id,
                TRIM(CONCAT(
                    COALESCE(first_name,''),' ',
                    COALESCE(middle_name,''),' ',
                    COALESCE(last_name,'')
                )) AS full_name
            FROM hr_employees
            ORDER BY employee_id
        ");
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    // Fetch payroll if exists
    public function getPayroll($employee_id, $start, $end) {
        $stmt = $this->conn->prepare("
            SELECT * 
            FROM hr_payroll
            WHERE employee_id = ? AND pay_period_start = ? AND pay_period_end = ?
        ");
        $stmt->bind_param("iss", $employee_id, $start, $end);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }

    // Get full month compensation (fallback)
    public function getCompensation($employee_id, $pay_period) {
        $stmt = $this->conn->prepare("
            SELECT * 
            FROM hr_compensation_benefits
            WHERE employee_id = ? AND pay_period = ?
        ");
        $stmt->bind_param("is", $employee_id, $pay_period);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }

    public function computeEmployeeSalary($employee_id, $pay_period, $period_type = 'full') {

        // FETCH COMPENSATION
        $stmt = $this->conn->prepare("
            SELECT basic_pay, allowances, bonuses, thirteenth_month, sss, philhealth, pagibig
            FROM hr_compensation_benefits
            WHERE employee_id = ? LIMIT 1
        ");
        $stmt->bind_param("i", $employee_id);
        $stmt->execute();
        $comp = $stmt->get_result()->fetch_assoc();
        if (!$comp) return null;

        // PAY PERIOD DATES
        [$year, $month] = explode('-', $pay_period);
        $first_day = "$year-$month-01";
        $last_day  = date("Y-m-t", strtotime($first_day));

        if ($period_type === 'first') {
            $start_date = $first_day;
            $end_date   = "$year-$month-15";
        } elseif ($period_type === 'second') {
            $start_date = "$year-$month-16";
            $end_date   = $last_day;
        } else {
            $start_date = $first_day;
            $end_date   = $last_day;
        }

    // ATTENDANCE (FIXED: commands out of sync)
    $stmt = $this->conn->prepare("
        SELECT status, overtime_minutes, undertime_minutes
        FROM hr_daily_attendance
        WHERE employee_id = ? AND attendance_date BETWEEN ? AND ?
    ");
    $stmt->bind_param("iss", $employee_id, $start_date, $end_date);
    $stmt->execute();

    $result = $stmt->get_result(); // ✅ GET RESULT ONCE

    $days_worked = 0;
    $overtime_minutes = 0;
    $undertime_minutes = 0;

    while ($row = $result->fetch_assoc()) {
        if (in_array($row['status'], ['Present','Late','Overtime','Undertime','On Leave'])) {
            $days_worked++;
        }
        $overtime_minutes += (float)$row['overtime_minutes'];
        $undertime_minutes += (float)$row['undertime_minutes'];
    }

    $stmt->close(); 

        // RATES
        $working_days = 22;
        $daily_rate  = $comp['basic_pay'] / $working_days;
        $hourly_rate = $daily_rate / 8; // FIX: standard 8 hrs

        $overtime_hours  = round($overtime_minutes / 60, 2);
        $undertime_hours = round($undertime_minutes / 60, 2);

        // PERIOD COMPUTATION (FIXED)
        $period_working_days = ($period_type === 'full') ? $working_days : ($working_days / 2);
        $days_worked = min($days_worked, $period_working_days);
        $absent_days = max(0, $period_working_days - $days_worked);

        // EARNINGS
        $basic_pay = $daily_rate * $days_worked;
        $allowances = ($comp['allowances'] / $working_days) * $days_worked;
        $bonuses = ($comp['bonuses'] / $working_days) * $days_worked;
        $thirteenth_month = ($comp['thirteenth_month'] / $working_days) * $days_worked;

        $overtime_pay = $hourly_rate * 1.25 * $overtime_hours;

        // DEDUCTIONS (FIXED)
        $undertime_deduction = $hourly_rate * $undertime_hours;
        $absence_deduction  = $daily_rate * $absent_days;

        // ✅ FIX: HALF per cutoff
        if ($period_type === 'first' || $period_type === 'second') {
            $sss        = $comp['sss'] / 2;
            $philhealth = $comp['philhealth'] / 2;
            $pagibig    = $comp['pagibig'] / 2;
        } else {
            $sss        = $comp['sss'];
            $philhealth = $comp['philhealth'];
            $pagibig    = $comp['pagibig'];
        }

        $gross_pay = $basic_pay + $allowances + $bonuses + $thirteenth_month + $overtime_pay;
        $total_deductions = $sss + $philhealth + $pagibig + $undertime_deduction + $absence_deduction;

        $net_pay = max(0, $gross_pay - $total_deductions); // FIX: no negative

        // RETURN
        return [
            'employee_id' => $employee_id,
            'pay_period_start' => $start_date,
            'pay_period_end' => $end_date,
            'days_worked' => $days_worked,
            'overtime_hours' => $overtime_hours,
            'undertime_hours' => $undertime_hours,
            'basic_pay' => round($basic_pay, 2),
            'allowances' => round($allowances, 2),
            'bonuses' => round($bonuses, 2),
            'thirteenth_month' => round($thirteenth_month, 2),
            'overtime_pay' => round($overtime_pay, 2),
            'undertime_deduction' => round($undertime_deduction, 2),
            'absence_deduction' => round($absence_deduction, 2),
            'sss_deduction' => round($sss, 2),
            'philhealth_deduction' => round($philhealth, 2),
            'pagibig_deduction' => round($pagibig, 2),
            'gross_pay' => round($gross_pay, 2),
            'total_deductions' => round($total_deductions, 2),
            'net_pay' => round($net_pay, 2),
        ];
    }

    public function markAsPaid($payroll_id) {
        $stmt = $this->conn->prepare("
            UPDATE hr_payroll
            SET status = 'Paid'
            WHERE payroll_id = ?
        ");
        $stmt->bind_param("i", $payroll_id);
        return $stmt->execute();
    }

    public function getPayrollById($payroll_id) {
        $stmt = $this->conn->prepare("SELECT * FROM hr_payroll WHERE payroll_id = ?");
        $stmt->bind_param("i", $payroll_id);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_assoc(); // return as associative array
    }
}
?>
