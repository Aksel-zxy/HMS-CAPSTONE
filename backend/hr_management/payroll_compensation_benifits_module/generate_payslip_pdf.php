<?php
require '../../../SQL/config.php';
require_once '../classes/Auth.php';
require_once '../classes/User.php';
require_once 'classes/PayrollReports.php';
require 'dompdf/autoload.inc.php';

use Dompdf\Dompdf;

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

// Build HTML content for the PDF
$html = '
<style>
    body { font-family: Arial, sans-serif; }
    .header { text-align: center; margin-bottom: 20px; }
    table { width: 100%; border-collapse: collapse; margin-bottom: 15px; }
    table, th, td { border: 1px solid black; }
    th, td { padding: 8px; text-align: left; }
    .text-right { text-align: right; }
    h5 { font-weight: bold; margin-bottom: 5px;font-size: 20px; }
</style>

<div class="header">
    <h2>Hospital Management System</h2>
    <h3>Payslip</h3>
</div>

<table>
    <tr><th>Employee ID</th><td>'.htmlspecialchars($payroll['employee_id']).'</td></tr>
    <tr><th>Employee</th><td>'.htmlspecialchars($payroll['employee_name']).'</td></tr>
    <tr><th>Position</th><td>'.htmlspecialchars($payroll['profession']).'</td></tr>
    <tr><th>Department</th><td>'.htmlspecialchars($payroll['department']).'</td></tr>
    <tr><th>Pay Period</th><td>'.$payroll['pay_period_start'].' to '.$payroll['pay_period_end'].'</td></tr>
    <tr><th>Date Paid</th><td>'.$payroll['date_generated'].'</td></tr>
</table>

<h5>Earnings</h5>
<table>
    <tr><th>Description</th><th class="text-right">Amount</th></tr>
    <tr><td>Basic Pay</td><td class="text-right">'.number_format($payroll['basic_pay'], 2).'</td></tr>
    <tr><td>Allowances</td><td class="text-right">'.number_format($payroll['allowances'], 2).'</td></tr>
    <tr><td>Bonuses</td><td class="text-right">'.number_format($payroll['bonuses'], 2).'</td></tr>
    <tr><th>Gross Pay</th><th class="text-right">'.number_format($payroll['gross_pay'], 2).'</th></tr>
</table>

<h5>Deductions</h5>
<table>
    <tr><th>Description</th><th class="text-right">Amount</th></tr>
    <tr><td>SSS</td><td class="text-right">'.number_format($payroll['sss_deduction'], 2).'</td></tr>
    <tr><td>PhilHealth</td><td class="text-right">'.number_format($payroll['philhealth_deduction'], 2).'</td></tr>
    <tr><td>Pag-IBIG</td><td class="text-right">'.number_format($payroll['pagibig_deduction'], 2).'</td></tr>
    <tr><td>Undertime / Absences</td><td class="text-right">'.number_format($payroll['undertime_deduction'] + $payroll['absence_deduction'], 2).'</td></tr>
    <tr><th>Total Deductions</th><th class="text-right">'.number_format($payroll['total_deductions'], 2).'</th></tr>
</table>

<h5 style="text-align:center;">Net Pay: '.number_format($payroll['net_pay'], 2).'</h5>
';

// Initialize Dompdf
$dompdf = new Dompdf();
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

// Stream PDF to browser for download
$dompdf->stream("Payslip_{$payroll['employee_id']}.pdf", ["Attachment" => true]);
exit;
