<?php
require '../../../SQL/config.php';
require_once '../classes/Auth.php';
require_once '../classes/User.php';
require_once 'classes/PayrollReports.php';

Auth::checkHR();

$conn = $conn;

// Get payroll_id from URL
$payroll_id = $_GET['payroll_id'] ?? '';
if (!$payroll_id) {
    die("Payroll ID not specified.");
}

// Fetch payroll details
$report = new PayrollReports($conn);
$payroll = null;
$allPayrolls = $report->getPayrolls(); // get all paid payrolls
foreach ($allPayrolls as $row) {
    if ($row['payroll_id'] == $payroll_id) {
        $payroll = $row;
        break;
    }
}

if (!$payroll) {
    die("Payroll record not found.");
}

// Optional: fetch employee info
$userObj = new User($conn);
$employee = $userObj->getById($payroll['employee_id']);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payslip Preview | <?= htmlspecialchars($payroll['employee_name']) ?></title>
    <link rel="shortcut icon" href="../assets/image/favicon.ico" type="image/x-icon">
    <link rel="stylesheet" href="../assets/CSS/bootstrap.min.css">
    <link rel="stylesheet" href="../assets/CSS/super.css">
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

        <!-- Download PDF Button -->
        <div class="text-center download-btn">
            <a href="generate_payslip_pdf.php?payroll_id=<?= $payroll['payroll_id'] ?>" class="btn btn-success">
                Download PDF
            </a>
        </div>
    </div>

    <script src="../assets/Bootstrap/bootstrap.bundle.min.js"></script>
</body>
</html>
