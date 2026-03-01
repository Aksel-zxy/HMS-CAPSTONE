<?php
session_start();
include '../../../SQL/config.php';
require_once '../tcpdf/TCPDF-main/tcpdf.php';

if (!isset($_SESSION['pharmacy']) || $_SESSION['pharmacy'] !== true) {
    header('Location: ../login.php');
    exit();
}

// Get parameters
$from_date = isset($_GET['from_date']) ? date('Y-m-d', strtotime($_GET['from_date'])) : date('Y-m-d', strtotime('-30 days'));
$to_date = isset($_GET['to_date']) ? date('Y-m-d', strtotime($_GET['to_date'])) : date('Y-m-d');
$report_type = isset($_GET['report_type']) ? $_GET['report_type'] : 'daily';

// Custom PDF class
class DispenseReportPDF extends TCPDF
{
    private $report_type;
    private $date_range;

    public function setReportInfo($type, $range)
    {
        $this->report_type = $type;
        $this->date_range = $range;
    }

    public function Header()
    {
        $this->SetFont('helvetica', '', 10);

        // Hospital Header
        $this->SetFont('helvetica', 'B', 16);
        $this->SetTextColor(32, 110, 215);
        $this->Cell(0, 10, 'Hospital Management System', 0, 1, 'C');

        $this->SetFont('helvetica', '', 9);
        $this->SetTextColor(100, 100, 100);
        $this->Cell(0, 5, 'Pharmacy Dispensing Report', 0, 1, 'C');

        // Report Type
        $this->SetFont('helvetica', 'B', 11);
        $this->SetTextColor(32, 110, 215);
        $report_titles = [
            'daily' => 'Daily Dispensing Report',
            'inpatient' => 'Inpatient Medication Report',
            'usage' => 'Medicine Usage Report'
        ];
        $this->Cell(0, 8, isset($report_titles[$this->report_type]) ? $report_titles[$this->report_type] : 'Report', 0, 1, 'C');

        // Date Range
        $this->SetFont('helvetica', '', 9);
        $this->SetTextColor(100, 100, 100);
        $this->Cell(0, 6, 'Period: ' . $this->date_range, 0, 1, 'C');

        $this->SetDrawColor(200, 200, 200);
        $this->Line(15, $this->GetY() + 2, 195, $this->GetY() + 2);

        $this->SetY($this->GetY() + 5);
    }

    public function Footer()
    {
        $this->SetY(-20);
        $this->SetFont('helvetica', '', 8);
        $this->SetTextColor(128, 128, 128);

        $this->SetDrawColor(200, 200, 200);
        $this->Line(15, $this->GetY(), 195, $this->GetY());

        $this->SetY($this->GetY() + 3);
        $this->Cell(0, 5, 'Page ' . $this->getAliasNumPage() . ' of ' . $this->getAliasNbPages(), 0, 0, 'C');

        $this->SetY($this->GetY() + 3);
        $this->Cell(0, 5, 'Generated on ' . date('Y-m-d H:i:s'), 0, 0, 'C');
    }
}

// Create PDF
$pdf = new DispenseReportPDF('P', 'mm', 'A4', true, 'UTF-8', false);
$date_range = date('M d, Y', strtotime($from_date)) . ' to ' . date('M d, Y', strtotime($to_date));
$pdf->setReportInfo($report_type, $date_range);

$pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);
$pdf->SetMargins(15, 40, 15);
$pdf->SetAutoPageBreak(TRUE, 30);
$pdf->SetFont('helvetica', '', 9);

switch ($report_type) {
    case 'daily':
        addDailyDispensingReport($pdf, $conn, $from_date, $to_date);
        break;
    case 'inpatient':
        addInpatientMedicationReport($pdf, $conn, $from_date, $to_date);
        break;
    case 'usage':
        addMedicineUsageReport($pdf, $conn, $from_date, $to_date);
        break;
}

$filename = 'Dispensing_' . $report_type . '_' . date('Y-m-d_His') . '.pdf';
$pdf->Output($filename, 'D');

// ============================================
// Report Functions
// ============================================

function addDailyDispensingReport($pdf, $conn, $from_date, $to_date)
{
    // Summary Section
    $summary_query = "
        SELECT
            COUNT(DISTINCT ppi.prescription_id) as total_prescriptions,
            COUNT(ppi.item_id) as total_items,
            SUM(ppi.quantity_dispensed) as total_quantity,
            SUM(ppi.total_price) as total_value
        FROM pharmacy_prescription_items ppi
        WHERE DATE(ppi.dispensed_date) BETWEEN '$from_date' AND '$to_date'
        AND ppi.quantity_dispensed > 0
    ";

    $summary = $conn->query($summary_query)->fetch_assoc();

    // Summary boxes
    $pdf->AddPage();
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(0, 8, 'Summary Statistics', 0, 1, 'L');
    $pdf->SetDrawColor(32, 110, 215);
    $pdf->Line(15, $pdf->GetY(), 195, $pdf->GetY());
    $pdf->SetY($pdf->GetY() + 2);

    $pdf->SetFont('helvetica', '', 9);
    $summaries = [
        ['label' => 'Total Prescriptions', 'value' => $summary['total_prescriptions'] ?? 0],
        ['label' => 'Total Items Dispensed', 'value' => $summary['total_items'] ?? 0],
        ['label' => 'Total Quantity', 'value' => $summary['total_quantity'] ?? 0],
        ['label' => 'Total Revenue', 'value' => '₱' . number_format($summary['total_value'] ?? 0, 2)]
    ];

    $col_width = 45;
    $y_pos = $pdf->GetY();
    foreach ($summaries as $index => $item) {
        $x_pos = 15 + ($index % 4) * $col_width;
        if ($index > 0 && $index % 4 == 0) {
            $y_pos += 15;
        }

        $pdf->SetXY($x_pos, $y_pos);
        $pdf->SetFillColor(245, 247, 250);
        $pdf->Cell($col_width - 2, 12, $item['label'], 1, 0, 'C', true);

        $pdf->SetFont('helvetica', 'B', 11);
        $pdf->SetXY($x_pos, $y_pos + 12);
        $pdf->Cell($col_width - 2, 8, $item['value'], 1, 0, 'C', true);
        $pdf->SetFont('helvetica', '', 9);
    }

    $pdf->SetY($y_pos + 45);

    // Daily Breakdown Table
    $pdf->SetFont('helvetica', 'B', 11);
    $pdf->Cell(0, 8, 'Daily Breakdown', 0, 1, 'L');
    $pdf->SetDrawColor(32, 110, 215);
    $pdf->Line(15, $pdf->GetY(), 195, $pdf->GetY());
    $pdf->SetY($pdf->GetY() + 2);

    $daily_query = "
        SELECT
            DATE(ppi.dispensed_date) as dispensing_date,
            COUNT(DISTINCT ppi.prescription_id) as prescriptions,
            SUM(ppi.quantity_dispensed) as items_count,
            SUM(ppi.total_price) as daily_total,
            GROUP_CONCAT(DISTINCT pi.category) as categories
        FROM pharmacy_prescription_items ppi
        JOIN pharmacy_inventory pi ON ppi.med_id = pi.med_id
        WHERE DATE(ppi.dispensed_date) BETWEEN '$from_date' AND '$to_date'
        AND ppi.quantity_dispensed > 0
        GROUP BY DATE(ppi.dispensed_date)
        ORDER BY ppi.dispensed_date DESC
    ";

    $result = $conn->query($daily_query);

    // Table headers
    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->SetFillColor(32, 110, 215);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->Cell(35, 8, 'Date', 1, 0, 'C', true);
    $pdf->Cell(30, 8, 'Prescriptions', 1, 0, 'C', true);
    $pdf->Cell(30, 8, 'Items', 1, 0, 'C', true);
    $pdf->Cell(30, 8, 'Categories', 1, 0, 'C', true);
    $pdf->Cell(40, 8, 'Daily Total', 1, 1, 'C', true);

    $pdf->SetFont('helvetica', '', 8);
    $pdf->SetTextColor(0, 0, 0);

    $alt_color = false;
    while ($row = $result->fetch_assoc()) {
        if ($pdf->GetY() > 250) {
            $pdf->AddPage();
            $pdf->SetFont('helvetica', 'B', 9);
            $pdf->SetFillColor(32, 110, 215);
            $pdf->SetTextColor(255, 255, 255);
            $pdf->Cell(35, 8, 'Date', 1, 0, 'C', true);
            $pdf->Cell(30, 8, 'Prescriptions', 1, 0, 'C', true);
            $pdf->Cell(30, 8, 'Items', 1, 0, 'C', true);
            $pdf->Cell(30, 8, 'Categories', 1, 0, 'C', true);
            $pdf->Cell(40, 8, 'Daily Total', 1, 1, 'C', true);
            $pdf->SetFont('helvetica', '', 8);
            $pdf->SetTextColor(0, 0, 0);
            $alt_color = false;
        }

        if ($alt_color) {
            $pdf->SetFillColor(240, 240, 240);
        } else {
            $pdf->SetFillColor(255, 255, 255);
        }
        $alt_color = !$alt_color;

        $pdf->Cell(35, 7, date('M d, Y', strtotime($row['dispensing_date'])), 1, 0, 'L', true);
        $pdf->Cell(30, 7, $row['prescriptions'], 1, 0, 'C', true);
        $pdf->Cell(30, 7, $row['items_count'], 1, 0, 'C', true);
        $pdf->Cell(30, 7, substr($row['categories'], 0, 20), 1, 0, 'L', true);
        $pdf->Cell(40, 7, '₱' . number_format($row['daily_total'], 2), 1, 1, 'R', true);
    }
}

function addInpatientMedicationReport($pdf, $conn, $from_date, $to_date)
{
    // Summary
    $inpatient_summary_query = "
        SELECT
            COUNT(DISTINCT pp.patient_id) as total_patients,
            COUNT(DISTINCT ppi.prescription_id) as total_prescriptions,
            COUNT(ppi.item_id) as total_items,
            SUM(ppi.quantity_dispensed) as total_quantity,
            SUM(ppi.total_price) as total_value
        FROM pharmacy_prescription_items ppi
        JOIN pharmacy_prescription pp ON ppi.prescription_id = pp.prescription_id
        WHERE DATE(ppi.dispensed_date) BETWEEN '$from_date' AND '$to_date'
        AND ppi.quantity_dispensed > 0
    ";

    $summary = $conn->query($inpatient_summary_query)->fetch_assoc();

    $pdf->AddPage();
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(0, 8, 'Summary Statistics', 0, 1, 'L');
    $pdf->SetDrawColor(32, 110, 215);
    $pdf->Line(15, $pdf->GetY(), 195, $pdf->GetY());
    $pdf->SetY($pdf->GetY() + 2);

    $pdf->SetFont('helvetica', '', 9);
    $summaries = [
        ['label' => 'Total Patients', 'value' => $summary['total_patients'] ?? 0],
        ['label' => 'Total Prescriptions', 'value' => $summary['total_prescriptions'] ?? 0],
        ['label' => 'Total Items', 'value' => $summary['total_items'] ?? 0],
        ['label' => 'Total Value', 'value' => '₱' . number_format($summary['total_value'] ?? 0, 2)]
    ];

    $col_width = 45;
    $y_pos = $pdf->GetY();
    foreach ($summaries as $index => $item) {
        $x_pos = 15 + ($index % 4) * $col_width;
        if ($index > 0 && $index % 4 == 0) {
            $y_pos += 15;
        }

        $pdf->SetXY($x_pos, $y_pos);
        $pdf->SetFillColor(245, 247, 250);
        $pdf->Cell($col_width - 2, 12, $item['label'], 1, 0, 'C', true);

        $pdf->SetFont('helvetica', 'B', 11);
        $pdf->SetXY($x_pos, $y_pos + 12);
        $pdf->Cell($col_width - 2, 8, $item['value'], 1, 0, 'C', true);
        $pdf->SetFont('helvetica', '', 9);
    }

    $pdf->SetY($y_pos + 45);

    // Details Table
    $pdf->SetFont('helvetica', 'B', 11);
    $pdf->Cell(0, 8, 'Medication Dispensing Details', 0, 1, 'L');
    $pdf->SetDrawColor(32, 110, 215);
    $pdf->Line(15, $pdf->GetY(), 195, $pdf->GetY());
    $pdf->SetY($pdf->GetY() + 2);

    $inpatient_query = "
        SELECT
            pp.prescription_id,
            CONCAT(pat.fname, ' ', pat.lname) as patient_name,
            pi.med_name,
            ppi.quantity_prescribed,
            ppi.quantity_dispensed,
            ppi.total_price,
            ppi.dispensed_date
        FROM pharmacy_prescription_items ppi
        JOIN pharmacy_prescription pp ON ppi.prescription_id = pp.prescription_id
        JOIN pharmacy_inventory pi ON ppi.med_id = pi.med_id
        JOIN patientinfo pat ON pp.patient_id = pat.patient_id
        WHERE DATE(ppi.dispensed_date) BETWEEN '$from_date' AND '$to_date'
        AND ppi.quantity_dispensed > 0
        ORDER BY ppi.dispensed_date DESC
        LIMIT 200
    ";

    $result = $conn->query($inpatient_query);

    // Table headers
    $pdf->SetFont('helvetica', 'B', 8);
    $pdf->SetFillColor(32, 110, 215);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->Cell(25, 7, 'Rx ID', 1, 0, 'C', true);
    $pdf->Cell(30, 7, 'Patient', 1, 0, 'L', true);
    $pdf->Cell(35, 7, 'Medicine', 1, 0, 'L', true);
    $pdf->Cell(15, 7, 'Pres.', 1, 0, 'C', true);
    $pdf->Cell(15, 7, 'Disp.', 1, 0, 'C', true);
    $pdf->Cell(20, 7, 'Price', 1, 0, 'R', true);
    $pdf->Cell(25, 7, 'Date', 1, 1, 'C', true);

    $pdf->SetFont('helvetica', '', 7);
    $pdf->SetTextColor(0, 0, 0);

    $alt_color = false;
    while ($row = $result->fetch_assoc()) {
        if ($pdf->GetY() > 255) {
            $pdf->AddPage();
            $pdf->SetFont('helvetica', 'B', 8);
            $pdf->SetFillColor(32, 110, 215);
            $pdf->SetTextColor(255, 255, 255);
            $pdf->Cell(25, 7, 'Rx ID', 1, 0, 'C', true);
            $pdf->Cell(30, 7, 'Patient', 1, 0, 'L', true);
            $pdf->Cell(35, 7, 'Medicine', 1, 0, 'L', true);
            $pdf->Cell(15, 7, 'Pres.', 1, 0, 'C', true);
            $pdf->Cell(15, 7, 'Disp.', 1, 0, 'C', true);
            $pdf->Cell(20, 7, 'Price', 1, 0, 'R', true);
            $pdf->Cell(25, 7, 'Date', 1, 1, 'C', true);
            $pdf->SetFont('helvetica', '', 7);
            $pdf->SetTextColor(0, 0, 0);
            $alt_color = false;
        }

        if ($alt_color) {
            $pdf->SetFillColor(240, 240, 240);
        } else {
            $pdf->SetFillColor(255, 255, 255);
        }
        $alt_color = !$alt_color;

        $pdf->Cell(25, 6, $row['prescription_id'], 1, 0, 'C', true);
        $pdf->Cell(30, 6, substr($row['patient_name'], 0, 20), 1, 0, 'L', true);
        $pdf->Cell(35, 6, substr($row['med_name'], 0, 20), 1, 0, 'L', true);
        $pdf->Cell(15, 6, $row['quantity_prescribed'], 1, 0, 'C', true);
        $pdf->Cell(15, 6, $row['quantity_dispensed'], 1, 0, 'C', true);
        $pdf->Cell(20, 6, '₱' . number_format($row['total_price'], 2), 1, 0, 'R', true);
        $pdf->Cell(25, 6, date('m/d/Y', strtotime($row['dispensed_date'])), 1, 1, 'C', true);
    }
}

function addMedicineUsageReport($pdf, $conn, $from_date, $to_date)
{
    // Summary
    $usage_summary_query = "
        SELECT
            COUNT(DISTINCT pi.med_id) as unique_medicines,
            SUM(ppi.quantity_dispensed) as total_quantity_dispensed,
            SUM(ppi.total_price) as total_value
        FROM pharmacy_prescription_items ppi
        JOIN pharmacy_inventory pi ON ppi.med_id = pi.med_id
        WHERE DATE(ppi.dispensed_date) BETWEEN '$from_date' AND '$to_date'
        AND ppi.quantity_dispensed > 0
    ";

    $summary = $conn->query($usage_summary_query)->fetch_assoc();

    $pdf->AddPage();
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(0, 8, 'Summary Statistics', 0, 1, 'L');
    $pdf->SetDrawColor(32, 110, 215);
    $pdf->Line(15, $pdf->GetY(), 195, $pdf->GetY());
    $pdf->SetY($pdf->GetY() + 2);

    $pdf->SetFont('helvetica', '', 9);
    $summaries = [
        ['label' => 'Unique Medicines', 'value' => $summary['unique_medicines'] ?? 0],
        ['label' => 'Total Quantity', 'value' => $summary['total_quantity_dispensed'] ?? 0],
        ['label' => 'Total Value', 'value' => '₱' . number_format($summary['total_value'] ?? 0, 2)],
    ];

    $col_width = 50;
    $y_pos = $pdf->GetY();
    foreach ($summaries as $index => $item) {
        $x_pos = 15 + ($index % 3) * $col_width;
        if ($index > 0 && $index % 3 == 0) {
            $y_pos += 15;
        }

        $pdf->SetXY($x_pos, $y_pos);
        $pdf->SetFillColor(245, 247, 250);
        $pdf->Cell($col_width - 2, 12, $item['label'], 1, 0, 'C', true);

        $pdf->SetFont('helvetica', 'B', 11);
        $pdf->SetXY($x_pos, $y_pos + 12);
        $pdf->Cell($col_width - 2, 8, $item['value'], 1, 0, 'C', true);
        $pdf->SetFont('helvetica', '', 9);
    }

    $pdf->SetY($y_pos + 45);

    // Category Breakdown
    $pdf->SetFont('helvetica', 'B', 11);
    $pdf->Cell(0, 8, 'Medicine Usage by Category', 0, 1, 'L');
    $pdf->SetDrawColor(32, 110, 215);
    $pdf->Line(15, $pdf->GetY(), 195, $pdf->GetY());
    $pdf->SetY($pdf->GetY() + 2);

    $category_query = "
        SELECT
            pi.category,
            COUNT(DISTINCT ppi.med_id) as unique_medicines,
            SUM(ppi.quantity_dispensed) as total_quantity,
            SUM(ppi.total_price) as category_total
        FROM pharmacy_prescription_items ppi
        JOIN pharmacy_inventory pi ON ppi.med_id = pi.med_id
        WHERE DATE(ppi.dispensed_date) BETWEEN '$from_date' AND '$to_date'
        AND ppi.quantity_dispensed > 0
        GROUP BY pi.category
        ORDER BY total_quantity DESC
    ";

    $result = $conn->query($category_query);

    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->SetFillColor(32, 110, 215);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->Cell(50, 8, 'Category', 1, 0, 'L', true);
    $pdf->Cell(35, 8, 'Medicines', 1, 0, 'C', true);
    $pdf->Cell(35, 8, 'Quantity', 1, 0, 'C', true);
    $pdf->Cell(40, 8, 'Total Value', 1, 1, 'R', true);

    $pdf->SetFont('helvetica', '', 8);
    $pdf->SetTextColor(0, 0, 0);

    $alt_color = false;
    while ($row = $result->fetch_assoc()) {
        if ($pdf->GetY() > 240) {
            $pdf->AddPage();
            $pdf->SetFont('helvetica', 'B', 9);
            $pdf->SetFillColor(32, 110, 215);
            $pdf->SetTextColor(255, 255, 255);
            $pdf->Cell(50, 8, 'Category', 1, 0, 'L', true);
            $pdf->Cell(35, 8, 'Medicines', 1, 0, 'C', true);
            $pdf->Cell(35, 8, 'Quantity', 1, 0, 'C', true);
            $pdf->Cell(40, 8, 'Total Value', 1, 1, 'R', true);
            $pdf->SetFont('helvetica', '', 8);
            $pdf->SetTextColor(0, 0, 0);
            $alt_color = false;
        }

        if ($alt_color) {
            $pdf->SetFillColor(240, 240, 240);
        } else {
            $pdf->SetFillColor(255, 255, 255);
        }
        $alt_color = !$alt_color;

        $pdf->Cell(50, 7, $row['category'], 1, 0, 'L', true);
        $pdf->Cell(35, 7, $row['unique_medicines'], 1, 0, 'C', true);
        $pdf->Cell(35, 7, $row['total_quantity'], 1, 0, 'C', true);
        $pdf->Cell(40, 7, '₱' . number_format($row['category_total'], 2), 1, 1, 'R', true);
    }

    // Add page for top medicines
    $pdf->AddPage();
    $pdf->SetFont('helvetica', 'B', 11);
    $pdf->Cell(0, 8, 'Top 20 Medicines by Usage', 0, 1, 'L');
    $pdf->SetDrawColor(32, 110, 215);
    $pdf->Line(15, $pdf->GetY(), 195, $pdf->GetY());
    $pdf->SetY($pdf->GetY() + 2);

    $top_query = "
        SELECT
            pi.med_name,
            pi.generic_name,
            pi.category,
            SUM(ppi.quantity_dispensed) as total_dispensed,
            SUM(ppi.total_price) as total_revenue
        FROM pharmacy_prescription_items ppi
        JOIN pharmacy_inventory pi ON ppi.med_id = pi.med_id
        WHERE DATE(ppi.dispensed_date) BETWEEN '$from_date' AND '$to_date'
        AND ppi.quantity_dispensed > 0
        GROUP BY ppi.med_id
        ORDER BY total_dispensed DESC
        LIMIT 20
    ";

    $result = $conn->query($top_query);

    $pdf->SetFont('helvetica', 'B', 8);
    $pdf->SetFillColor(32, 110, 215);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->Cell(5, 7, '#', 1, 0, 'C', true);
    $pdf->Cell(40, 7, 'Medicine', 1, 0, 'L', true);
    $pdf->Cell(35, 7, 'Generic Name', 1, 0, 'L', true);
    $pdf->Cell(25, 7, 'Qty', 1, 0, 'C', true);
    $pdf->Cell(50, 7, 'Total Revenue', 1, 1, 'R', true);

    $pdf->SetFont('helvetica', '', 7);
    $pdf->SetTextColor(0, 0, 0);

    $count = 1;
    $alt_color = false;
    while ($row = $result->fetch_assoc()) {
        if ($pdf->GetY() > 255) {
            $pdf->AddPage();
            $pdf->SetFont('helvetica', 'B', 8);
            $pdf->SetFillColor(32, 110, 215);
            $pdf->SetTextColor(255, 255, 255);
            $pdf->Cell(5, 7, '#', 1, 0, 'C', true);
            $pdf->Cell(40, 7, 'Medicine', 1, 0, 'L', true);
            $pdf->Cell(35, 7, 'Generic Name', 1, 0, 'L', true);
            $pdf->Cell(25, 7, 'Qty', 1, 0, 'C', true);
            $pdf->Cell(50, 7, 'Total Revenue', 1, 1, 'R', true);
            $pdf->SetFont('helvetica', '', 7);
            $pdf->SetTextColor(0, 0, 0);
            $count = 1;
            $alt_color = false;
        }

        if ($alt_color) {
            $pdf->SetFillColor(240, 240, 240);
        } else {
            $pdf->SetFillColor(255, 255, 255);
        }
        $alt_color = !$alt_color;

        $pdf->Cell(5, 6, $count++, 1, 0, 'C', true);
        $pdf->Cell(40, 6, substr($row['med_name'], 0, 18), 1, 0, 'L', true);
        $pdf->Cell(35, 6, substr($row['generic_name'], 0, 16), 1, 0, 'L', true);
        $pdf->Cell(25, 6, $row['total_dispensed'], 1, 0, 'C', true);
        $pdf->Cell(50, 6, '₱' . number_format($row['total_revenue'], 2), 1, 1, 'R', true);
    }
}
