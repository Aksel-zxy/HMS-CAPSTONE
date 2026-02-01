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

    /* =======================
       1️⃣ MONTHLY COMPENSATION
    ======================== */
    $stmt = $this->conn->prepare("
        SELECT basic_pay, allowances, bonuses, thirteenth_month, sss, philhealth, pagibig
        FROM hr_compensation_benefits
        WHERE employee_id = ? AND pay_period = ? LIMIT 1
    ");
    $stmt->bind_param("is", $employee_id, $pay_period);
    $stmt->execute();
    $comp = $stmt->get_result()->fetch_assoc();
    if (!$comp) return null;

    $monthly_basic = (float)$comp['basic_pay'];
    $monthly_allowances = (float)$comp['allowances'];
    $monthly_bonuses = (float)$comp['bonuses'];
    $monthly_13th = (float)($comp['thirteenth_month'] ?? 0);
    $sss = (float)$comp['sss'];
    $philhealth = (float)$comp['philhealth'];
    $pagibig = (float)$comp['pagibig'];

    /* =======================
       2️⃣ PAY PERIOD DATES
    ======================== */
    [$year, $month] = explode('-', $pay_period);
    $start_date = "$year-$month-01";
    $end_date = date("Y-m-t", strtotime($start_date));
    if ($period_type === 'first') $end_date = "$year-$month-15";
    if ($period_type === 'second') $start_date = "$year-$month-16";

    /* =======================
       3️⃣ PROFESSION
    ======================== */
    $stmt = $this->conn->prepare("SELECT profession FROM hr_employees WHERE employee_id = ? LIMIT 1");
    $stmt->bind_param("i", $employee_id);
    $stmt->execute();
    $profession = $stmt->get_result()->fetch_assoc()['profession'];
    $stmt->close();

    /* =======================
       4️⃣ ATTENDANCE (COUNT DAYS + OT + Paid Leave)
    ======================== */
    $stmt = $this->conn->prepare("
        SELECT attendance_date, status, overtime_minutes, undertime_minutes
        FROM hr_daily_attendance
        WHERE employee_id = ?
          AND attendance_date BETWEEN ? AND ?
    ");
    $stmt->bind_param("iss", $employee_id, $start_date, $end_date);
    $stmt->execute();
    $result = $stmt->get_result();

    $days_worked = 0;
    $overtime_minutes = 0;
    $undertime_minutes = 0;

    while ($row = $result->fetch_assoc()) {
        $status = $row['status'];
        $leave = null;

        if (in_array($status, ['Half Day','On Leave','On Leave (Half Day)'])) {
            $stmt_leave = $this->conn->prepare("
                SELECT is_paid, half_day_type
                FROM hr_leave
                WHERE employee_id = ?
                  AND leave_status = 'Approved'
                  AND leave_start_date <= ?
                  AND leave_end_date >= ?
                LIMIT 1
            ");
            $stmt_leave->bind_param("iss", $employee_id, $row['attendance_date'], $row['attendance_date']);
            $stmt_leave->execute();
            $leave = $stmt_leave->get_result()->fetch_assoc();
            $stmt_leave->close();
        }

        // Full day worked
        if (in_array($status, ['Present','Late','Overtime','Undertime'])) {
            $days_worked += 1;
        }
        // Half Day worked
        elseif ($status === 'Half Day') {
            if ($leave && $leave['is_paid'] === 'Yes') {
                $days_worked += 1; // Half worked + Half Paid Leave = Full
            } else {
                $days_worked += 0.5;
            }
        }
        // On Leave full or half
        elseif (in_array($status, ['On Leave','On Leave (Half Day)'])) {
            if ($leave && $leave['is_paid'] === 'Yes') {
                if ($status === 'On Leave (Half Day)') {
                    $days_worked += 0.5; // Paid Half Day leave only
                } else {
                    $days_worked += 1;   // Full Paid Leave
                }
            }
        }
        // Absent Half Day
        elseif ($status === 'Absent (Half Day)') {
            $days_worked += 0.5;
        }

        // OT / Undertime accumulation
        $overtime_minutes += (float)$row['overtime_minutes'];
        $undertime_minutes += (float)$row['undertime_minutes'];
    }
    $stmt->close();

    /* =======================
       5️⃣ PERIOD FACTOR
    ======================== */
    $period_factor = ($period_type === 'full') ? 1 : 0.5;

    /* =======================
       6️⃣ ATTENDANCE-BASED PAY LOGIC
    ======================== */
    $daily_rate = $monthly_basic / 22; 
    $hourly_rate = $daily_rate / 8;
    $expected_days = ($period_type === 'full') ? 22 : 11;

    $basic_pay_period = $daily_rate * $days_worked;
    $allowances_period = ($monthly_allowances / 22) * $days_worked;
    $bonuses_period = ($monthly_bonuses / 22) * $days_worked;
    $thirteenth_month_period = ($monthly_13th / 22) * $days_worked;

    $absence_days = max(0, $expected_days - $days_worked);
    $absence_deduction = $daily_rate * $absence_days;

    /* =======================
       7️⃣ OT & UNDERTIME
    ======================== */
    $overtime_hours = round($overtime_minutes / 60, 2);
    $undertime_hours = round($undertime_minutes / 60, 2);
    $overtime_pay = $hourly_rate * 1.25 * $overtime_hours;
    $undertime_deduction = $hourly_rate * $undertime_hours;

    /* =======================
       8️⃣ GOVERNMENT DEDUCTIONS
    ======================== */
    if ($period_type !== 'full') {
        $sss /= 2; $philhealth /= 2; $pagibig /= 2;
    }

    /* =======================
       9️⃣ TOTALS
    ======================== */
    $gross_pay = $basic_pay_period + $allowances_period + $bonuses_period + $thirteenth_month_period + $overtime_pay;
    $total_deductions = $sss + $philhealth + $pagibig + $undertime_deduction + $absence_deduction;
    $net_pay = max(0, $gross_pay - $total_deductions);

    return [
        'employee_id' => $employee_id,
        'profession' => $profession,
        'pay_period_start' => $start_date,
        'pay_period_end' => $end_date,
        'daily_rate' => round($daily_rate,2),
        'days_worked' => $days_worked,
        'basic_pay' => round($basic_pay_period,2),
        'allowances' => round($allowances_period,2),
        'bonuses' => round($bonuses_period,2),
        'thirteenth_month' => round($thirteenth_month_period,2),
        'overtime_hours' => $overtime_hours,
        'overtime_pay' => round($overtime_pay,2),
        'undertime_hours' => $undertime_hours,
        'undertime_deduction' => round($undertime_deduction,2),
        'absence_deduction' => round($absence_deduction,2),
        'sss_deduction' => round($sss,2),
        'philhealth_deduction' => round($philhealth,2),
        'pagibig_deduction' => round($pagibig,2),
        'gross_pay' => round($gross_pay,2),
        'total_deductions' => round($total_deductions,2),
        'net_pay' => round($net_pay,2),
    ];
}

// Inside Salary.php class
public function savePayroll($data) {
    $stmt = $this->conn->prepare("
        INSERT INTO hr_payroll (
            employee_id,
            pay_period_start,
            pay_period_end,
            days_worked,
            overtime_hours,
            undertime_deduction,
            basic_pay,
            overtime_pay,
            allowances,
            bonuses,
            thirteenth_month,
            sss_deduction,
            philhealth_deduction,
            pagibig_deduction,
            absence_deduction,
            gross_pay,
            total_deductions,
            net_pay,
            date_generated
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ");

    $stmt->bind_param(
        "issddddddddddddddd",
        $data['employee_id'],
        $data['pay_period_start'],
        $data['pay_period_end'],
        $data['days_worked'],
        $data['overtime_hours'],
        $data['undertime_deduction'],
        $data['basic_pay'],
        $data['overtime_pay'],
        $data['allowances'],
        $data['bonuses'],
        $data['thirteenth_month'],
        $data['sss_deduction'],
        $data['philhealth_deduction'],
        $data['pagibig_deduction'],
        $data['absence_deduction'],
        $data['gross_pay'],
        $data['total_deductions'],
        $data['net_pay'],
    );

    if ($stmt->execute()) {
        return $stmt->insert_id; // Returns the new payroll_id
    } else {
        return false;
    }
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
