<?php
session_start();
include '../../SQL/config.php';
require_once 'pdf_class.php';

// Session and input checks
if (!isset($_SESSION['billing']) || $_SESSION['billing'] !== true) {
    header('Location: login.php');
    exit();
}
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    echo "User ID is not set in session.";
    exit();
}
$billing_id = isset($_GET['billing_id']) ? intval($_GET['billing_id']) : 0;
if ($billing_id <= 0) {
    echo "Invalid billing ID.";
    exit();
}

// Instantiate and fetch data
$pdf = new PDFReport($conn, $billing_id, $_SESSION['user_id']);
$pdf->fetchAll();

// Assign variables for template
$billing = $pdf->billing;
$doctor = $pdf->doctor;
$service_results = $pdf->service_results;
$subtotal = $pdf->subtotal;
$insurance_covered = $pdf->insurance_covered;
$billing_id = $pdf->billing_id;

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Invoice Report</title>
    <link rel="stylesheet" href="../assets/CSS/pdf.css">
</head>
<body>
    <button class="print-btn" onclick="window.print()">Print Invoice</button>
    <div class="header-bar"></div>
    <h2 style="font-weight:bold; margin-top:18px;">PATIENT INVOICE REPORT</h2>
    <table width="100%" style="margin-bottom:10px;">
        <tr>
            <td width="48%" valign="top" style="border-right:1px solid #1976d2;">
                <span class="blue-label">PATIENT INFORMATION</span><br>
                <table class="info-table">
                    <tr><td>Name:</td><td>
                        <?php
                            echo htmlspecialchars($billing['fname'] . ' ' .
                                (!empty($billing['mname']) ? $billing['mname'] . ' ' : '') .
                                $billing['lname']);
                        ?>
                    </td></tr>
                    <tr><td>Contact Number:</td><td>
                        <?php echo htmlspecialchars($billing['phone_number']); ?>
                    </td></tr>
                    <tr><td>Address:</td><td>
                        <?php echo htmlspecialchars($billing['address']); ?>
                    </td></tr>
                </table>
            </td>
            <td width="4%"></td>
            <td width="48%" valign="top" style="border-left:1px solid #1976d2;">
                <span class="blue-label">DOCTOR INFORMATION</span><br>
                <table class="info-table">
                    <tr><td>Name:</td><td>
                        <?php
                            if ($doctor) {
                                $full_name = $doctor['first_name'];
                                if (!empty($doctor['middle_name'])) $full_name .= ' ' . $doctor['middle_name'];
                                if (!empty($doctor['last_name'])) $full_name .= ' ' . $doctor['last_name'];
                                if (!empty($doctor['suffix_name'])) $full_name .= ', ' . $doctor['suffix_name'];
                                echo htmlspecialchars($full_name);
                            } else {
                                echo "N/A";
                            }
                        ?>
                    </td></tr>
                    <tr><td>Contact Number:</td><td>
                        <?php
                            echo ($doctor && !empty($doctor['contact_number']))
                                ? htmlspecialchars($doctor['contact_number'])
                                : "N/A";
                        ?>
                    </td></tr>
                    <tr><td>Specialization:</td><td>
                        <?php
                            echo ($doctor && !empty($doctor['specialization']))
                                ? htmlspecialchars($doctor['specialization'])
                                : "N/A";
                        ?>
                    </td></tr>
                </table>
            </td>
        </tr>
    </table>
    <table width="100%" style="margin-bottom:10px;">`
        <tr>
            <td width="20%" class="blue-label">INVOICE NUMBER</td>
            <td width="20%" class="blue-label">DATE</td>
            <td width="20%" class="blue-label">INVOICE DUE DATE</td>
            <td width="20%" class="blue-label">AMOUNT DUE</td>
        </tr>
        <tr>
            <td>
                <?php echo 'INV-' . $billing_id; ?>
            </td>
            <td>
                <?php echo htmlspecialchars($billing['billing_date']); ?>
            </td>
            <td>
                <?php echo date('Y-m-d', strtotime($billing['billing_date'] . ' +7 days')); ?>
            </td>
            <td class="amount-due-box">
                <?php echo '₱ ' . number_format($subtotal - $insurance_covered, 2); ?>
            </td>
        </tr>
    </table>
    <table class="invoice-table">
        <tr>
            <th width="25%">ITEM</th>
            <th width="55%">DESCRIPTION</th>
            <th width="20%">AMOUNT</th>
        </tr>
        <?php if (!empty($service_results)): ?>
            <?php foreach ($service_results as $item): ?>
                <tr>
                    <td><?php echo htmlspecialchars($item['serviceName']); ?></td>
                    <td><?php echo htmlspecialchars($item['description']); ?></td>
                    <td><?php echo '₱ ' . number_format($item['price'], 2); ?></td>
                </tr>
            <?php endforeach; ?>
        <?php else: ?>
            <tr>
                <td colspan="3" class="text-center">No completed services found.</td>
            </tr>
        <?php endif; ?>
    </table>
    <table width="100%" style="margin-bottom:10px;">
        <tr>
            <td width="70%"></td>
            <td width="30%">
                SUB TOTAL: ₱ <?php echo number_format($subtotal, 2); ?><br>
                INSURANCE COVERED: ₱ <?php echo number_format($insurance_covered, 2); ?><br>
                TAX: ₱ 0.00<br>
                <div class="total-box">TOTAL: ₱ <?php echo number_format($subtotal - $insurance_covered, 2); ?></div>
            </td>
        </tr>
    </table>
    <b style="font-size:15px;">NOTES</b><br>
    Thank you for choosing our hospital!
    <div class="footer-bar">
        <table width="100%">
            <tr>
                <td width="50%" style="font-weight:bold;">Name of the Hospital</td>
                <td width="50%" align="right">ADDRESS</td>
            </tr>
        </table>
    </div>
</body>
</html>