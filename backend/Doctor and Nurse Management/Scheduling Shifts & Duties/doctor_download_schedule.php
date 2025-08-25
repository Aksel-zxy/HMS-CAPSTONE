<?php
require_once('../../Pharmacy Management/tcpdf/tcpdf.php');
include '../../../SQL/config.php';

if (!isset($_GET['employee_id']) || empty(trim($_GET['employee_id']))) {
    echo "Invalid request.";
    exit;
}

$emp_id = trim($_GET['employee_id']);

// 1. Fetch doctor info
$stmt = $conn->prepare("
    SELECT employee_id, first_name, middle_name, last_name, profession, department 
    FROM hr_employees 
    WHERE employee_id = ?
");
$stmt->bind_param("s", $emp_id);
$stmt->execute();
$result = $stmt->get_result();
$doctor = $result->fetch_assoc();
$stmt->close();

if (!$doctor) {
    echo "Doctor not found.";
    exit;
}

// 2. Fetch schedules
$sched_stmt = $conn->prepare("
    SELECT * 
    FROM shift_scheduling 
    WHERE employee_id = ?
    ORDER BY week_start DESC
");
$sched_stmt->bind_param("s", $emp_id);
$sched_stmt->execute();
$sched_result = $sched_stmt->get_result();
$schedules = $sched_result->fetch_all(MYSQLI_ASSOC);
$sched_stmt->close();

if (!$schedules || count($schedules) === 0) {
    echo "No schedules found for this doctor.";
    exit;
}

// 3. Create PDF
$pdf = new TCPDF();
$pdf->SetCreator(PDF_CREATOR);
$pdf->SetAuthor('Hospital MIS');
$pdf->SetTitle('Doctor Schedule');
$pdf->AddPage();
$pdf->SetFont('helvetica', '', 12);

// Doctor info
$full_name = trim(
    ($doctor['first_name'] ?? '') . ' ' .
    ($doctor['middle_name'] ?? '') . ' ' .
    ($doctor['last_name'] ?? '')
);
$employee_id = htmlspecialchars($doctor['employee_id'] ?? '');
$profession = htmlspecialchars($doctor['profession'] ?? '');
$department = htmlspecialchars($doctor['department'] ?? '');

$html = "<h2>Doctor Schedule</h2>
    <p><strong>Name:</strong> {$full_name}</p>
    <p><strong>Employee ID:</strong> {$employee_id}</p>
    <p><strong>Profession:</strong> {$profession}</p>
    <p><strong>Department:</strong> {$department}</p><br>";

// Days mapping (adjust based on DB column names)
$days = [
    'Monday' => 'mon', 
    'Tuesday' => 'tue', 
    'Wednesday' => 'wed', 
    'Thursday' => 'thu', 
    'Friday' => 'fri', 
    'Saturday' => 'sat', 
    'Sunday' => 'sun'
];

// Loop through schedules
foreach ($schedules as $sched) {
    $week_start = htmlspecialchars($sched['week_start'] ?? '');
    $html .= "<h4>Week of: {$week_start}</h4>
        <table border='1' cellpadding='4'>
            <thead>
                <tr>
                    <th>Day</th>
                    <th>Start Time</th>
                    <th>End Time</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>";

    foreach ($days as $dayName => $prefix) {
        $start = htmlspecialchars($sched[$prefix . '_start'] ?? '');
        $end = htmlspecialchars($sched[$prefix . '_end'] ?? '');
        $status = htmlspecialchars($sched[$prefix . '_status'] ?? '');

        // Display --- if status is not active
        $display_start = (in_array($status, ['Off Duty', 'Leave', 'Sick'])) ? '---' : $start;
        $display_end = (in_array($status, ['Off Duty', 'Leave', 'Sick'])) ? '---' : $end;

        $html .= "<tr>
                    <td>{$dayName}</td>
                    <td>{$display_start}</td>
                    <td>{$display_end}</td>
                    <td>{$status}</td>
                </tr>";
    }

    $html .= "</tbody></table><br>";
}

$pdf->writeHTML($html, true, false, true, false, '');
$pdf->Output("doctor_schedule_{$employee_id}.pdf", 'D');
?>
