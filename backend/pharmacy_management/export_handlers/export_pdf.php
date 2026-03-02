<?php
// Inventory Report PDF Export using TCPDF - Professional Version
session_start();

include '../../../SQL/config.php';

if (!isset($_SESSION['pharmacy']) || $_SESSION['pharmacy'] !== true) {
    header('Location: ../login.php');
    exit();
}

// Include TCPDF library
require_once '../tcpdf/TCPDF-main/tcpdf.php';

// Get inventory data
$query = "
    SELECT
        med_id,
        med_name,
        generic_name,
        category,
        stock_quantity,
        unit_price,
        stock_quantity * unit_price as total_value,
        CASE
            WHEN stock_quantity = 0 THEN 'Out of Stock'
            WHEN stock_quantity > 0 AND stock_quantity <= 10 THEN 'Low Stock'
            ELSE 'Available'
        END as status
    FROM pharmacy_inventory
    ORDER BY category, med_name
";
$result = $conn->query($query);
$medicines = $result->fetch_all(MYSQLI_ASSOC);

// Get summary statistics
$summary_query = "
    SELECT
        COUNT(DISTINCT med_id) as total_medicines,
        SUM(stock_quantity) as total_stock_pieces,
        SUM(stock_quantity * unit_price) as total_inventory_value,
        COUNT(CASE WHEN stock_quantity = 0 THEN 1 END) as out_of_stock_count,
        COUNT(CASE WHEN stock_quantity <= 10 AND stock_quantity > 0 THEN 1 END) as low_stock_count
    FROM pharmacy_inventory
";
$summary_result = $conn->query($summary_query);
$summary = $summary_result->fetch_assoc();

// Get category breakdown
$category_query = "
    SELECT category, COUNT(*) as med_count, SUM(stock_quantity) as stock_qty
    FROM pharmacy_inventory
    GROUP BY category
    ORDER BY stock_qty DESC
";
$category_result = $conn->query($category_query);
$categories = $category_result->fetch_all(MYSQLI_ASSOC);

// Create TCPDF object
class ProfessionalPDF extends TCPDF
{
    public function Header()
    {
        // Gradient-like header
        $this->SetFillColor(32, 110, 215);
        $this->Rect(0, 0, $this->w, 30, 'F');

        // Title
        $this->SetTextColor(255, 255, 255);
        $this->SetFont('Helvetica', 'B', 22);
        $this->SetXY(20, 7);
        $this->Cell(0, 8, 'INVENTORY MANAGEMENT REPORT', 0, 0, 'L');

        // Subtitle
        $this->SetFont('Helvetica', '', 10);
        $this->SetXY(20, 18);
        $this->Cell(0, 5, 'HMS - Hospital Management System | Pharmacy Department', 0, 0, 'L');

        // Date
        $this->SetFont('Helvetica', '', 9);
        $this->SetTextColor(200, 220, 255);
        $this->SetXY(20, 24);
        $this->Cell(0, 5, 'Generated: ' . date('F d, Y - g:i A'), 0, 0, 'L');

        $this->SetTextColor(0, 0, 0);
        $this->SetY(35);
    }

    public function Footer()
    {
        $this->SetY(-18);
        $this->SetFont('Helvetica', '', 8);
        $this->SetTextColor(120, 120, 120);

        // Line
        $this->SetDrawColor(200, 200, 200);
        $this->SetLineWidth(0.3);
        $this->Line(20, $this->GetY(), $this->getPageWidth() - 20, $this->GetY());

        $this->SetY(-15);
        $this->Cell(0, 5, 'HMS Pharmacy Department', 0, 0, 'L');
        $this->Cell(0, 5, 'Page ' . $this->getAliasNumPage() . ' of ' . $this->getAliasNbPages(), 0, 0, 'R');
    }
}

$pdf = new ProfessionalPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
$pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);
$pdf->SetMargins(20, 40, 20);
$pdf->SetAutoPageBreak(TRUE, 25);
$pdf->AddPage();
$pdf->SetFont('dejavusans', '', 10);

// ===== EXECUTIVE SUMMARY =====
$pdf->SetFont('Helvetica', 'B', 14);
$pdf->SetTextColor(32, 110, 215);
$pdf->Cell(0, 8, 'EXECUTIVE SUMMARY', 0, 1, 'L');
$pdf->Ln(2);

// Summary boxes
$summaries = [
    ['Total Medicines', $summary['total_medicines'], [32, 110, 215]],
    ['Total Stock Value', '₱' . number_format($summary['total_inventory_value'] ?? 0, 2), [16, 185, 129]],
    ['Out of Stock', $summary['out_of_stock_count'], [239, 68, 68]],
    ['Low Stock Items', $summary['low_stock_count'], [255, 159, 64]]
];

$col_width = ($pdf->getPageWidth() - 40) / 2;
$box_height = 22;
$box_spacing = 4;
$box_y_start = $pdf->GetY();

foreach ($summaries as $i => $sum) {
    $col = $i % 2;
    $row = intdiv($i, 2);
    $x = 20 + ($col * ($col_width + 8));
    $y = $box_y_start + ($row * ($box_height + $box_spacing));

    // Colored border
    $pdf->SetFillColor(...$sum[2]);
    $pdf->Rect($x, $y, 4, $box_height, 'F');

    // Background box
    $pdf->SetFillColor(245, 248, 252);
    $pdf->Rect($x + 4, $y, $col_width - 4, $box_height, 'FD');

    // Border
    $pdf->SetDrawColor(200, 220, 240);
    $pdf->SetLineWidth(0.5);
    $pdf->Rect($x + 4, $y, $col_width - 4, $box_height);

    // Label
    $pdf->SetXY($x + 8, $y + 4);
    $pdf->SetFont('Helvetica', '', 9);
    $pdf->SetTextColor(100, 100, 100);
    $pdf->Cell(0, 5, $sum[0], 0, 0, 'L');

    // Value
    $pdf->SetXY($x + 8, $y + 12);
    $pdf->SetFont('Helvetica', 'B', 14);
    $pdf->SetTextColor(...$sum[2]);
    $pdf->Cell($col_width - 12, 7, $sum[1], 0, 0, 'L');
}

$pdf->SetY($box_y_start + (2 * ($box_height + $box_spacing)) + 5);

// ===== CATEGORY BREAKDOWN =====
$pdf->SetFont('Helvetica', 'B', 13);
$pdf->SetTextColor(32, 110, 215);
$pdf->Cell(0, 7, 'CATEGORY BREAKDOWN', 0, 1, 'L');
$pdf->Ln(1);

// Table header
$pdf->SetFillColor(32, 110, 215);
$pdf->SetTextColor(255, 255, 255);
$pdf->SetFont('Helvetica', 'B', 10);
$pdf->SetDrawColor(32, 110, 215);
$pdf->SetLineWidth(0.4);

$pdf->Cell(80, 8, 'Category', 1, 0, 'L', true);
$pdf->Cell(35, 8, 'Medicines', 1, 0, 'C', true);
$pdf->Cell(35, 8, 'Stock Qty', 1, 0, 'C', true);
$pdf->Ln();

// Category rows
$pdf->SetFont('Helvetica', '', 9);
$pdf->SetTextColor(0, 0, 0);

foreach ($categories as $idx => $cat) {
    $pdf->SetFillColor($idx % 2 == 0 ? 245 : 255, $idx % 2 == 0 ? 248 : 255, $idx % 2 == 0 ? 252 : 255);
    $pdf->SetDrawColor(220, 220, 220);
    $pdf->Cell(80, 7, $cat['category'], 1, 0, 'L', true);
    $pdf->Cell(35, 7, $cat['med_count'], 1, 0, 'C', true);
    $pdf->Cell(35, 7, $cat['stock_qty'], 1, 0, 'C', true);
    $pdf->Ln();
}

$pdf->Ln(3);

// ===== DETAILED INVENTORY LIST =====
$pdf->SetFont('Helvetica', 'B', 13);
$pdf->SetTextColor(32, 110, 215);
$pdf->Cell(0, 7, 'DETAILED INVENTORY LISTING', 0, 1, 'L');
$pdf->Ln(2);

// Table header
$pdf->SetFillColor(32, 110, 215);
$pdf->SetTextColor(255, 255, 255);
$pdf->SetFont('Helvetica', 'B', 9);
$pdf->SetDrawColor(32, 110, 215);

$headers = ['ID' => 18, 'Medicine Name' => 35, 'Category' => 22, 'Stock' => 15, 'Unit Price' => 20, 'Total Value' => 22, 'Status' => 18];
foreach ($headers as $title => $w) {
    $align = in_array($title, ['Unit Price', 'Total Value']) ? 'R' : 'C';
    $pdf->Cell($w, 8, $title, 1, 0, $align, true);
}
$pdf->Ln();

// Table data
$pdf->SetFont('Helvetica', '', 8);
$pdf->SetTextColor(0, 0, 0);

$row_count = 0;
foreach ($medicines as $med) {
    if ($pdf->GetY() > 250) {
        $pdf->AddPage();
        // Repeat header
        $pdf->SetFillColor(32, 110, 215);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetFont('Helvetica', 'B', 9);
        foreach ($headers as $title => $w) {
            $align = in_array($title, ['Unit Price', 'Total Value']) ? 'R' : 'C';
            $pdf->Cell($w, 8, $title, 1, 0, $align, true);
        }
        $pdf->Ln();
        $pdf->SetFont('Helvetica', '', 8);
        $pdf->SetTextColor(0, 0, 0);
        $row_count = 0;
    }

    $pdf->SetFillColor($row_count % 2 == 0 ? 245 : 255, $row_count % 2 == 0 ? 248 : 255, $row_count % 2 == 0 ? 252 : 255);
    $pdf->SetDrawColor(220, 220, 220);

    $pdf->Cell(18, 6, substr($med['med_id'], 0, 6), 1, 0, 'C', true);
    $pdf->Cell(35, 6, substr($med['med_name'], 0, 18), 1, 0, 'L', true);
    $pdf->Cell(22, 6, substr($med['category'], 0, 10), 1, 0, 'C', true);
    $pdf->Cell(15, 6, $med['stock_quantity'], 1, 0, 'C', true);
    $pdf->Cell(20, 6, '₱' . number_format($med['unit_price'], 2), 1, 0, 'R', true);
    $pdf->Cell(22, 6, '₱' . number_format($med['total_value'], 2), 1, 0, 'R', true);

    // Status coloring
    if ($med['status'] == 'Available') $pdf->SetTextColor(16, 185, 129);
    elseif ($med['status'] == 'Low Stock') $pdf->SetTextColor(255, 159, 64);
    else $pdf->SetTextColor(239, 68, 68);

    $pdf->SetFont('Helvetica', 'B', 8);
    $pdf->Cell(18, 6, substr($med['status'], 0, 10), 1, 0, 'C', true);

    $pdf->SetTextColor(0, 0, 0);
    $pdf->SetFont('Helvetica', '', 8);
    $pdf->Ln();
    $row_count++;
}

// Output PDF
$pdf->Output('Inventory_Report_' . date('Y-m-d_His') . '.pdf', 'D');
exit();
