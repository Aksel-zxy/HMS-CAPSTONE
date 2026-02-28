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
                )) AS full_name,
                profession,
                role
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

    // Compute salary for an employee
    public function computeEmployeeSalary($employee_id, $pay_period, $period_type = 'full') {

        // 1️⃣ MONTHLY COMPENSATION
        $stmt = $this->conn->prepare("
            SELECT basic_pay, allowances, bonuses, thirteenth_month, sss, philhealth, pagibig
            FROM hr_compensation_benefits
            WHERE employee_id = ? AND pay_period = ? LIMIT 1
        ");
        $stmt->bind_param("is", $employee_id, $pay_period);
        $stmt->execute();
        $comp = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$comp) return null;

        $monthly_basic = (float)$comp['basic_pay'];
        $monthly_allowances = (float)$comp['allowances'];
        $monthly_bonuses = (float)$comp['bonuses'];
        $monthly_13th = (float)($comp['thirteenth_month'] ?? 0);
        $monthly_sss = (float)$comp['sss'];
        $monthly_philhealth = (float)$comp['philhealth'];
        $monthly_pagibig = (float)$comp['pagibig'];

        // 2️⃣ PAY PERIOD DATES
        [$year, $month] = explode('-', $pay_period);
        $start_date = "$year-$month-01";
        $end_date = date("Y-m-t", strtotime($start_date));
        if ($period_type === 'first') $end_date = "$year-$month-15";
        if ($period_type === 'second') $start_date = "$year-$month-16";

        // 3️⃣ PROFESSION
        $stmt = $this->conn->prepare("SELECT profession FROM hr_employees WHERE employee_id = ? LIMIT 1");
        $stmt->bind_param("i", $employee_id);
        $stmt->execute();
        $profession_row = $stmt->get_result()->fetch_assoc();
        $profession = $profession_row['profession'] ?? '';
        $stmt->close();

        // 4️⃣ FULL MONTH ON DUTY DAYS
        $stmt = $this->conn->prepare("
            SELECT COUNT(*) as total_duty_days
            FROM hr_daily_attendance
            WHERE employee_id = ? AND attendance_date BETWEEN ? AND ? AND duty_status = 'On Duty'
        ");
        $full_month_start = "$year-$month-01";
        $full_month_end = date("Y-m-t", strtotime($full_month_start));
        $stmt->bind_param("iss", $employee_id, $full_month_start, $full_month_end);
        $stmt->execute();
        $total_duty_days_full = (int)$stmt->get_result()->fetch_assoc()['total_duty_days'];
        $stmt->close();

        // 5️⃣ TOTAL DUTY DAYS IN CURRENT PAY PERIOD
        $stmt = $this->conn->prepare("
            SELECT COUNT(*) as total_duty_days
            FROM hr_daily_attendance
            WHERE employee_id = ? AND attendance_date BETWEEN ? AND ? AND duty_status = 'On Duty'
        ");
        $stmt->bind_param("iss", $employee_id, $start_date, $end_date);
        $stmt->execute();
        $total_duty_days_period = (int)$stmt->get_result()->fetch_assoc()['total_duty_days'];
        $stmt->close();

        // 6️⃣ ATTENDANCE
        $stmt = $this->conn->prepare("
            SELECT attendance_date, status, overtime_minutes, undertime_minutes
            FROM hr_daily_attendance
            WHERE employee_id = ? AND attendance_date BETWEEN ? AND ?
        ");
        $stmt->bind_param("iss", $employee_id, $start_date, $end_date);
        $stmt->execute();
        $result = $stmt->get_result();

        $days_worked = 0;
        $overtime_minutes = 0;
        $undertime_minutes = 0;

        while ($row = $result->fetch_assoc()) {
            $status = $row['status'];
            $is_paid_leave = false;

            if (in_array($status, ['On Leave','On Leave (Half Day)'])) {
                $stmt_leave = $this->conn->prepare("
                    SELECT is_paid
                    FROM hr_leave
                    WHERE employee_id = ? AND leave_status = 'Approved'
                    AND leave_start_date <= ? AND leave_end_date >= ?
                    LIMIT 1
                ");
                $stmt_leave->bind_param("iss", $employee_id, $row['attendance_date'], $row['attendance_date']);
                $stmt_leave->execute();
                $leave_row = $stmt_leave->get_result()->fetch_assoc();
                $stmt_leave->close();
                $is_paid_leave = ($leave_row['is_paid'] ?? 'No') === 'Yes';
            }

            switch ($status) {
                case 'Present':
                case 'Late':
                case 'Overtime':
                case 'Undertime':
                    $days_worked += 1;
                    break;
                case 'Half Day':
                    $days_worked += $is_paid_leave ? 1 : 0.5;
                    break;
                case 'On Leave':
                    $days_worked += $is_paid_leave ? 1 : 0;
                    break;
                case 'On Leave (Half Day)':
                case 'Absent (Half Day)':
                    $days_worked += 0.5;
                    break;
                case 'Off Duty':
                    break;
            }

            $overtime_minutes += (float)$row['overtime_minutes'];
            $undertime_minutes += (float)$row['undertime_minutes'];
        }
        $stmt->close();

        // 7️⃣ DAILY & HOURLY RATE
        $daily_rate = $total_duty_days_full > 0 ? $monthly_basic / $total_duty_days_full : 0;
        $hourly_rate = $daily_rate / 8;

        $basic_pay_period = $daily_rate * $days_worked;
        $allowances_period = $total_duty_days_full > 0 ? ($monthly_allowances / $total_duty_days_full) * $days_worked : 0;
        $bonuses_period = $total_duty_days_full > 0 ? ($monthly_bonuses / $total_duty_days_full) * $days_worked : 0;
        $thirteenth_month_period = $total_duty_days_full > 0 ? ($monthly_13th / $total_duty_days_full) * $days_worked : 0;

        // 8️⃣ ABSENCE DEDUCTION
        $absence_days = max(0, $total_duty_days_period - $days_worked);
        $absence_deduction = $daily_rate * $absence_days;

        // 9️⃣ OT & UNDERTIME
        $overtime_hours = round($overtime_minutes / 60, 2);
        $undertime_hours = round($undertime_minutes / 60, 2);
        $overtime_pay = $hourly_rate * 1.25 * $overtime_hours;
        $undertime_deduction = $hourly_rate * $undertime_hours;

        // 🔟 GOVERNMENT DEDUCTIONS
        $sss = $monthly_sss;
        $philhealth = $monthly_philhealth;
        $pagibig = $monthly_pagibig;
        if ($period_type !== 'full') {
            $sss /= 2; $philhealth /= 2; $pagibig /= 2;
        }

        // 1️⃣1️⃣ TOTALS
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

    // Save payroll only if it does not exist
    public function savePayroll($data, $status = 'Pending') {
        $existing = $this->checkExistingPayroll($data['employee_id'], $data['pay_period_start'], $data['pay_period_end']);
        if ($existing) return $existing['payroll_id']; // return existing payroll ID

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
                status,
                date_generated
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");

        $stmt->bind_param(
            "issddddddddddddddds",
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
            $status
        );

        if ($stmt->execute()) return $stmt->insert_id;
        return false;
    }

    // Check if payroll exists
    public function checkExistingPayroll($employee_id, $start, $end) {
        $stmt = $this->conn->prepare("
            SELECT * FROM hr_payroll 
            WHERE employee_id = ?
            AND pay_period_start = ?
            AND pay_period_end = ?
            LIMIT 1
        ");
        $stmt->bind_param("iss", $employee_id, $start, $end);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_assoc();
    }

    // Get payroll by ID
    public function getPayrollById($payroll_id) {
        $stmt = $this->conn->prepare("SELECT * FROM hr_payroll WHERE payroll_id = ?");
        $stmt->bind_param("i", $payroll_id);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_assoc();
    }

    // Mark payroll as Paid
    public function markAsPaid($payroll_id) {
        $stmt = $this->conn->prepare("
            UPDATE hr_payroll
            SET status = 'Paid'
            WHERE payroll_id = ?
        ");
        $stmt->bind_param("i", $payroll_id);
        return $stmt->execute();
    }
}