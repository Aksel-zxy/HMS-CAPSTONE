<?php
include '../../../../SQL/config.php';

// ------------------ PaySlip Viewing Class ------------------
class PayrollReports {
    private $conn;

    public function __construct($conn) {
        $this->conn = $conn;
    }

    // Get all payrolls marked as 'Pending', optionally filtered by date range
    public function getPayrolls($employeeId = null, $start = '', $end = '') {
        $sql = "
            SELECT 
                p.payroll_id,
                e.employee_id,
                TRIM(CONCAT_WS(' ', e.first_name, e.middle_name, e.last_name, e.suffix_name)) AS employee_name,
                e.profession,
                e.department,
                p.pay_period_start,
                p.pay_period_end,
                p.days_worked,
                p.overtime_hours,
                p.basic_pay,
                p.overtime_pay,
                p.allowances,
                p.bonuses,
                p.thirteenth_month,
                p.undertime_deduction,
                p.sss_deduction,
                p.philhealth_deduction,
                p.pagibig_deduction,
                p.absence_deduction,
                p.gross_pay,
                p.total_deductions,
                p.net_pay,
                p.disbursement_method,
                p.date_generated
            FROM hr_payroll p
            JOIN hr_employees e ON p.employee_id = e.employee_id
            WHERE p.status = 'Pending'
        ";

        $params = [];
        $types = '';

        if ($employeeId) {
            $sql .= " AND e.employee_id = ?";
            $params[] = $employeeId;
            $types .= "i";
        }

        if (!empty($start) && !empty($end)) {
            $sql .= " AND p.pay_period_start >= ? AND p.pay_period_end <= ?";
            $params[] = $start;
            $params[] = $end;
            $types .= "ss";
        }

        $sql .= " ORDER BY p.date_generated DESC";

        $stmt = $this->conn->prepare($sql);
        if ($params) {
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

    public function formatCurrency($amount) {
        return number_format($amount, 2);
    }
}

// Get payroll_id from URL
$payroll_id = $_GET['payroll_id'] ?? '';
if (!$payroll_id) {
    die("Payroll ID not specified.");
}

// Fetch payroll details
$report = new PayrollReports($conn);
$allPayrolls = $report->getPayrolls(); // fetch all pending payrolls
$payroll = null;
foreach ($allPayrolls as $row) {
    if ($row['payroll_id'] == $payroll_id) {
        $payroll = $row;
        break;
    }
}

if (!$payroll) {
    die("Payroll record not found.");
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payslip Preview | <?= htmlspecialchars($payroll['employee_name']) ?></title>
    <link rel="stylesheet" href="../../assets/CSS/bootstrap.min.css">
    <link rel="stylesheet" href="../../assets/CSS/super.css">
    <link rel="stylesheet" href="../../assets/CSS/my_schedule.css">
    <style>
        .payslip-box {
            max-width: 700px;
            margin: 20px auto;
            border: 1px solid #000;
            padding: 20px;
            border-radius: 8px;
            background-color: #fff;
        }
        .payslip-header {
            text-align: center;
            margin-bottom: 20px;
        }
        .payslip-table td, .payslip-table th {
            padding: 8px;
        }
        .text-right {
            text-align: right;
        }
        h5 {
            font-weight: bold;
        }
        .download-btn {
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <div class="payslip-box">
        <div class="payslip-header">
            <h3>Hospital Management System</h3>
            <h4>Payslip Preview</h4>
        </div>

        <table class="table table-borderless">
            <tr>
                <th>Employee ID:</th>
                <td><?= htmlspecialchars($payroll['employee_id']) ?></td>
            </tr>
            <tr>
                <th>Employee:</th>
                <td><?= htmlspecialchars($payroll['employee_name']) ?></td>
            </tr>
            <tr>
                <th>Position:</th>
                <td><?= htmlspecialchars($payroll['profession']) ?></td>
            </tr>
            <tr>
                <th>Department:</th>
                <td><?= htmlspecialchars($payroll['department']) ?></td>
            </tr>
            <tr>
                <th>Pay Period:</th>
                <td><?= $payroll['pay_period_start'] ?> to <?= $payroll['pay_period_end'] ?></td>
            </tr>
            <tr>
                <th>Date Paid:</th>
                <td><?= $payroll['date_generated'] ?></td>
            </tr>
        </table>

        <h5>Earnings</h5>
        <table class="table table-bordered payslip-table">
            <tr>
                <th>Description</th>
                <th class="text-right">Amount</th>
            </tr>
            <tr>
                <td>Basic Pay</td>
                <td class="text-right"><?= number_format($payroll['basic_pay'], 2) ?></td>
            </tr>
            <tr>
                <td>Allowances</td>
                <td class="text-right"><?= number_format($payroll['allowances'], 2) ?></td>
            </tr>
            <tr>
                <td>Bonuses</td>
                <td class="text-right"><?= number_format($payroll['bonuses'], 2) ?></td>
            </tr>
            <tr>
                <th>Gross Pay</th>
                <th class="text-right"><?= number_format($payroll['gross_pay'], 2) ?></th>
            </tr>
        </table>

        <h5>Deductions</h5>
        <table class="table table-bordered payslip-table">
            <tr>
                <th>Description</th>
                <th class="text-right">Amount</th>
            </tr>
            <tr>
                <td>SSS</td>
                <td class="text-right"><?= number_format($payroll['sss_deduction'], 2) ?></td>
            </tr>
            <tr>
                <td>PhilHealth</td>
                <td class="text-right"><?= number_format($payroll['philhealth_deduction'], 2) ?></td>
            </tr>
            <tr>
                <td>Pag-IBIG</td>
                <td class="text-right"><?= number_format($payroll['pagibig_deduction'], 2) ?></td>
            </tr>
            <tr>
                <td>Undertime / Absences</td>
                <td class="text-right"><?= number_format($payroll['undertime_deduction'] + $payroll['absence_deduction'], 2) ?></td>
            </tr>
            <tr>
                <th>Total Deductions</th>
                <th class="text-right"><?= number_format($payroll['total_deductions'], 2) ?></th>
            </tr>
        </table>

        <center>
            <h5>Net Pay: <?= number_format($payroll['net_pay'], 2) ?></h5>
        </center>
    </div>

    <script src="../assets/Bootstrap/bootstrap.bundle.min.js"></script>
</body>
</html>
