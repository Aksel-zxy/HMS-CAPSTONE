<?php

session_start();
include '../../SQL/config.php';

// Check login/session
if (!isset($_SESSION['billing']) || $_SESSION['billing'] !== true) {
    header('Location: login.php');
    exit();
}
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    echo "User ID is not set in session.";
    exit();
}

// Get billing_id from GET
$billing_id = isset($_GET['billing_id']) ? intval($_GET['billing_id']) : 0;
if ($billing_id <= 0) {
    echo "Invalid billing ID.";
    exit();
}

// Fetch billing record + patient info
$sql = "
    SELECT br.*, br.insurance_covered, pi.fname, pi.mname, pi.lname, pi.address, pi.age, pi.dob, pi.gender, pi.civil_status,
           pi.phone_number, pi.email, pi.admission_type, pi.discount
    FROM billing_records br
    JOIN patientinfo pi ON br.patient_id = pi.patient_id
    WHERE br.billing_id = ?
";
$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $billing_id);
$stmt->execute();
$result = $stmt->get_result();
$billing = $result->fetch_assoc();
if (!$billing) {
    echo "Billing record not found.";
    exit();
}

// Fetch last 5 diagnostic results for patient
$sql_diag = "
    SELECT resultDate, status, result, remarks
    FROM dl_results
    WHERE patientID = ?
    ORDER BY resultDate DESC
    LIMIT 5
";
$stmt_diag = $conn->prepare($sql_diag);
$stmt_diag->bind_param('i', $billing['patient_id']);
$stmt_diag->execute();
$result_diag = $stmt_diag->get_result();

$diag_results = [];
while ($row = $result_diag->fetch_assoc()) {
    $diag_results[] = $row;
}

// Fetch user info from users table
$query = "SELECT * FROM users WHERE user_id = ?";
$stmt_user = $conn->prepare($query);
$stmt_user->bind_param("i", $_SESSION['user_id']);
$stmt_user->execute();
$result_user = $stmt_user->get_result();
$user = $result_user->fetch_assoc();

// Fetch doctor info from hr_employees using employee_id from users table
$doctor = null;
if ($user && !empty($user['employee_id'])) {
    $doctor_stmt = $conn->prepare(
        "SELECT first_name, middle_name, last_name, suffix_name, contact_number, specialization 
         FROM hr_employees 
         WHERE employee_id = ? LIMIT 1"
    );
    $doctor_stmt->bind_param("i", $user['employee_id']);
    $doctor_stmt->execute();
    $doctor_result = $doctor_stmt->get_result();
    if ($doctor_row = $doctor_result->fetch_assoc()) {
        $doctor = $doctor_row;
    }
}

// Fetch assigned doctor for the patient from dl_schedule (latest schedule)
$doctor = null;
$doctor_stmt = $conn->prepare("
    SELECT he.first_name, he.middle_name, he.last_name, he.suffix_name, 
           he.contact_number, he.specialization
    FROM dl_schedule ds
    JOIN hr_employees he ON ds.employee_id = he.employee_id
    WHERE ds.patientID = ?
    ORDER BY ds.scheduleDate DESC, ds.scheduleTime DESC
    LIMIT 1
");
$doctor_stmt->bind_param("i", $billing['patient_id']);
$doctor_stmt->execute();
$doctor_result = $doctor_stmt->get_result();
if ($doctor_row = $doctor_result->fetch_assoc()) {
    $doctor = $doctor_row;
}

// Calculate subtotal from diagnostic results
$subtotal = 0;
if (!empty($diag_results)) {
    foreach ($diag_results as $item) {
        $service_name = $item['result'];
        $service_stmt = $conn->prepare("SELECT price FROM dl_services WHERE serviceName = ?");
        $service_stmt->bind_param("s", $service_name);
        $service_stmt->execute();
        $service_result = $service_stmt->get_result();
        if ($service_row = $service_result->fetch_assoc()) {
            $subtotal += floatval($service_row['price']);
        }
    }
}

// Ensure out_of_pocket is set and numeric
$out_of_pocket = (isset($billing['out_of_pocket']) && is_numeric($billing['out_of_pocket']))
    ? floatval($billing['out_of_pocket'])
    : 0.00;

// Fetch insurance_covered for this billing_id from billing_records
$insurance_covered = 0.00;
if (isset($billing['insurance_covered']) && is_numeric($billing['insurance_covered'])) {
    $insurance_covered = floatval($billing['insurance_covered']);
}

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
    <table width="100%" style="margin-bottom:10px;">
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
                <?php echo '₱ ' . number_format($out_of_pocket, 2); ?>
            </td>
        </tr>
    </table>
    <table class="invoice-table">
        <tr>
            <th width="25%">ITEM</th>
            <th width="55%">DESCRIPTION</th>
            <th width="20%">AMOUNT</th>
        </tr>
        <?php if (!empty($diag_results)): ?>
            <?php foreach ($diag_results as $item): ?>
                <tr>
                    <td><?php echo htmlspecialchars($item['result']); ?></td>
                    <td><?php echo htmlspecialchars($item['remarks']); ?></td>
                    <td>
                        <?php
                        // Fetch price from dl_services table based on result (serviceName)
                        $service_name = $item['result'];
                        $price = '-';

                        $service_stmt = $conn->prepare("SELECT price FROM dl_services WHERE serviceName = ?");
                        $service_stmt->bind_param("s", $service_name);
                        $service_stmt->execute();
                        $service_result = $service_stmt->get_result();
                        if ($service_row = $service_result->fetch_assoc()) {
                            $price = '₱ ' . number_format($service_row['price'], 2);
                        }
                        echo $price;
                        ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php else: ?>
            <tr>
                <td colspan="3" class="text-center">No diagnostic results found.</td>
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
                <div class="total-box">TOTAL: ₱ <?php echo number_format($out_of_pocket, 2); ?></div>
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