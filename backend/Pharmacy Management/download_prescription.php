<?php
require_once('tcpdf/tcpdf.php');
include '../../SQL/config.php';

if (!isset($_GET['id'])) {
    die("No prescription ID provided.");
}

$prescriptionId = intval($_GET['id']);

// --- Fetch prescription info ---
$sql_prescription = "
SELECT 
    p.prescription_id,
    CONCAT(e.first_name, ' ', e.last_name) AS doctor_name,
    CONCAT(pi.fname, ' ', pi.lname) AS patient_name,
    p.note,
    DATE_FORMAT(p.prescription_date, '%b %e, %Y %l:%i %p') AS formatted_date
FROM pharmacy_prescription p
JOIN patientinfo pi ON p.patient_id = pi.patient_id
JOIN hr_employees e ON p.doctor_id = e.employee_id
WHERE p.prescription_id = ? AND e.profession = 'Doctor'
";

$stmt = $conn->prepare($sql_prescription);
if (!$stmt) die("Prepare failed (prescription): (" . $conn->errno . ") " . $conn->error);
$stmt->bind_param("i", $prescriptionId);
$stmt->execute();
$result = $stmt->get_result();
$prescription = $result->fetch_assoc();
$stmt->close();

if (!$prescription) die("Prescription not found.");

// --- Fetch prescription items (medicines only) ---
$sql_items = "
SELECT 
    m.med_name,
    i.dosage,
    i.quantity_prescribed
FROM pharmacy_prescription_items i
JOIN pharmacy_inventory m ON i.med_id = m.med_id
WHERE i.prescription_id = ?
";

$stmt = $conn->prepare($sql_items);
if (!$stmt) die("Prepare failed (items): (" . $conn->errno . ") " . $conn->error);
$stmt->bind_param("i", $prescriptionId);
$stmt->execute();
$result_items = $stmt->get_result();
$items = [];
while ($row = $result_items->fetch_assoc()) {
    $items[] = $row;
}
$stmt->close();

// --- Create PDF ---
$pdf = new TCPDF();
$pdf->SetCreator(PDF_CREATOR);
$pdf->SetAuthor('BCP Hospital');
$pdf->SetTitle("Prescription #{$prescriptionId}");
$pdf->SetMargins(15, 15, 15);
$pdf->SetAutoPageBreak(TRUE, 15);
$pdf->AddPage();
$pdf->SetFont('helvetica', '', 12);

// --- Medicines table (without repeating the general note) ---
$med_html = '<table width="100%" cellpadding="4" cellspacing="0" border="1">';
$med_html .= '<tr><th>Medicine</th></tr>';
foreach ($items as $item) {
    $med_name = htmlspecialchars($item['med_name'] . ' (' . $item['dosage'] . ') - Qty: ' . $item['quantity_prescribed']);
    $med_html .= "<tr><td>$med_name</td></tr>";
}
$med_html .= '</table>';

// --- PDF HTML ---
$html = '
<style>
body { font-family: helvetica, sans-serif; font-size: 11pt; }
.header { background-color: #fcefdc; padding: 10px; }
.line { border-top: 1px solid #000; margin: 5px 0; }
.section { margin: 10px 0; }
.label { font-weight: bold; }
.signature { margin-top: 40px; }
table { border-collapse: collapse; }
td, th { padding: 4px; vertical-align: top; }
th { background-color: #f2f2f2; }
</style>

<div class="header">
    <table width="100%">
        <tr>
            <td align="left" width="80%">
                <b>BCP Hospital</b><br>
                Ipo Rd. 1 Brgy. Muniyan Proper, CSJDM, Bulacan<br>
                9999-999-9999<br>
            </td>
            <td align="right" width="20%">
                <img src="' . __DIR__ . '/assets/image/bcp1.jpg" width="60">
            </td>
        </tr>
    </table>
</div>

<div class="line"></div>

<div class="section">
    <span class="label">Date:</span> ' . $prescription["formatted_date"] . '<br>
    <span class="label">Patient Name:</span> ' . htmlspecialchars($prescription["patient_name"]) . '<br>
</div>

<div class="section">
    <span class="label">Medication:</span><br><br>
    ' . $med_html . '
</div>

<div class="section">
    <span class="label">General Prescription Note:</span><br>
    ' . nl2br(htmlspecialchars($prescription["note"])) . '
</div>

<div class="signature">
    <span class="label">Physician Signature:</span><br><br>
    _______________________________<br>
    Dr. ' . htmlspecialchars($prescription["doctor_name"]) . '<br>
    Physician Name
</div>
';

$pdf->writeHTML($html, true, false, true, false, '');
$pdf->Output("Prescription_{$prescriptionId}.pdf", 'I');
