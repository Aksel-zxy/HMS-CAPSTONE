<?php
class Salary {
    private $conn;

    // Monthly salary base per profession
    private $monthlySalaries = [
        "Doctor" => 50000,
        "Nurse" => 30000,
        "Pharmacist" => 40000,
        "Laboratorist" => 23000,
        "Accountant" => 25000,
    ];

    private $working_days = 26; // Default monthly working days

    public function __construct($conn) {
        $this->conn = $conn;
    }

    public function getEmployees() {
        $employees = [];
        $sql = "SELECT employee_id, 
                       CONCAT(first_name, ' ', middle_name, ' ', last_name, ' ', suffix_name) AS full_name, 
                       profession 
                FROM hr_employees 
                ORDER BY employee_id ASC";
        $result = $this->conn->query($sql);
        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $employees[] = $row;
            }
        }
        return $employees;
    }

    // Get all per-day rates for all professions
    public function getAllPerDayRates() {
        $rates = [];
        foreach ($this->monthlySalaries as $profession => $monthly) {
            $rates[$profession] = $this->getPerDayRate($profession);
        }
        return $rates;
    }

    public function getPerDayRate($profession) {
        return isset($this->monthlySalaries[$profession])
            ? $this->monthlySalaries[$profession] / $this->working_days
            : 0;
    }

    public function getAttendanceSummary($employee_id, $start_date, $end_date) {
        $stmt = $this->conn->prepare("
            SELECT 
                COUNT(*) AS days_worked,
                SUM(overtime_minutes) / 60 AS overtime_hours
            FROM hr_daily_attendance
            WHERE employee_id = ? 
            AND DATE(attendance_date) BETWEEN ? AND ?
            AND status IN ('Present', 'Overtime', 'Undertime')
        ");
        $stmt->bind_param("iss", $employee_id, $start_date, $end_date);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        return [
            'days_worked' => $result['days_worked'] ?? 0,
            'overtime_hours' => $result['overtime_hours'] ?? 0
        ];
    }

    public function computeSalary($employee_id, $profession, $start_date, $end_date, $allowances = 0, $bonuses = 0) {
        $rate_per_day = $this->getPerDayRate($profession);
        $attendance = $this->getAttendanceSummary($employee_id, $start_date, $end_date);

        $days_worked = $attendance['days_worked'];
        $overtime_hours = $attendance['overtime_hours'];

        // Basic & overtime pay
        $basic_pay = $rate_per_day * $days_worked;
        $overtime_pay = ($rate_per_day / 8) * $overtime_hours * 1.25;

        // ✅ Automatic government deductions based on profession
        switch ($profession) {
            case 'Doctor':
                $sss = 2250;
                $philhealth = 1250;
                break;
            case 'Nurse':
                $sss = 1350;
                $philhealth = 750;
                break;
            case 'Pharmacist':
                $sss = 1800;
                $philhealth = 1000;
                break;
            case 'Laboratorist':
                $sss = 1035;
                $philhealth = 575;
                break;
            case 'Accountant':
                $sss = 1125;
                $philhealth = 625;
                break;
            default:
                $sss = $basic_pay * 0.045;
                $philhealth = $basic_pay * 0.025;
                break;
        }

        $pagibig = 100; // fixed
        $absence = max(0, ($this->working_days - $days_worked)) * $rate_per_day;

        // Computations
        $gross_pay = $basic_pay + $overtime_pay + $allowances + $bonuses;
        $total_deductions = $sss + $philhealth + $pagibig + $absence;
        $net_pay = $gross_pay - $total_deductions;

        return [
            'days_worked' => $days_worked,
            'overtime_hours' => $overtime_hours,
            'basic_pay' => $basic_pay,
            'overtime_pay' => $overtime_pay,
            'allowances' => $allowances,
            'bonuses' => $bonuses,
            'sss' => $sss,
            'philhealth' => $philhealth,
            'pagibig' => $pagibig,
            'absence' => $absence,
            'gross_pay' => $gross_pay,
            'total_deductions' => $total_deductions,
            'net_pay' => $net_pay
        ];
    }

    public function savePayroll($employee_id, $start_date, $end_date, $data, $disbursement = 'Manual') {
        $stmt = $this->conn->prepare("
            INSERT INTO hr_payroll (
                employee_id, pay_period_start, pay_period_end, 
                days_worked, overtime_hours, basic_pay, overtime_pay, 
                allowances, bonuses, sss_deduction, philhealth_deduction, 
                pagibig_deduction, absence_deduction, gross_pay, 
                total_deductions, net_pay, disbursement_method, status
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Pending')
        ");

        $stmt->bind_param(
            "issiddddddddddddds",
            $employee_id,
            $start_date,
            $end_date,
            $data['days_worked'],
            $data['overtime_hours'],
            $data['basic_pay'],
            $data['overtime_pay'],
            $data['allowances'],
            $data['bonuses'],
            $data['sss'],
            $data['philhealth'],
            $data['pagibig'],
            $data['absence'],
            $data['gross_pay'],
            $data['total_deductions'],
            $data['net_pay'],
            $disbursement
        );

        return $stmt->execute();
    }
}
