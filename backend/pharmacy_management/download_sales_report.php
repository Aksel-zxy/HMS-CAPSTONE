<?php
// Start session at the very top
session_start();

require_once('../../SQL/config.php');
require_once('classes/Sales.php');
require_once('tcpdf/tcpdf.php');

// Check user session (optional, for security)
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    die("User not logged in.");
}

$sales = new Sales($conn);

// Get period (optional)
$period = $_GET['period'] ?? 'all';

// Fetch summary data
$totalSales      = $sales->getTotalCashSales($period);
$totalOrders     = $sales->getTotalOrders($period);
$categoryDataRaw = $sales->getRevenueByCategory($period);
$topProducts     = $sales->getTopProducts($period);

// Prepare category chart data (if needed for labels)
$categoryLabels = [];
$categoryValues = [];
foreach ($categoryDataRaw as $cat) {
    $categoryLabels[] = $cat['category'];
    $categoryValues[] = floatval($cat['total']);
}

// Get chart images from POST (from dashboard page)
$categoryChartImg = $_POST['categoryChartImg'] ?? '';
$salesChartImg    = $_POST['salesChartImg'] ?? '';

// Create new PDF
$pdf = new TCPDF('P', PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
$pdf->SetCreator('4Stack Dev');
$pdf->SetAuthor('Pharmacy');
$pdf->SetTitle('Full Sales Dashboard Report');
$pdf->SetMargins(15, 20, 15);
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);
$pdf->AddPage();

// ----------------- Title -----------------
$pdf->SetFont('helvetica', 'B', 16);
$pdf->Cell(0, 10, 'Pharmacy Sales Dashboard Report', 0, 1, 'C');
$pdf->Ln(5);

// ----------------- Totals -----------------
$pdf->SetFont('helvetica', '', 12);
$pdf->Cell(0, 8, 'Total Sales: ₱' . number_format($totalSales, 2), 0, 1);
$pdf->Cell(0, 8, 'Total Orders: ' . $totalOrders, 0, 1);
$pdf->Ln(5);

// ----------------- Revenue by Category Chart -----------------
if ($categoryChartImg) {
    $imgData = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $categoryChartImg));
    $pdf->Image('@' . $imgData, 15, '', 90, 60); // X = 15mm, width 90mm, height 60mm
    $pdf->Ln(65);
}

// ----------------- Sales Performance Chart -----------------
if ($salesChartImg) {
    $imgData = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $salesChartImg));
    $pdf->Image('@' . $imgData, 15, '', 90, 60); // X = 15mm, width 90mm, height 60mm
    $pdf->Ln(65);
}

// ----------------- Top Selling Products Table -----------------
$pdf->SetFont('helvetica', 'B', 12);
$pdf->Cell(0, 8, 'Top Selling Products', 0, 1);
$pdf->Ln(2);

// Table header
$pdf->SetFont('helvetica', 'B', 10);
$pdf->SetFillColor(230, 230, 230);
$pdf->Cell(50, 8, 'Product Name', 1, 0, 'C', 1);
$pdf->Cell(40, 8, 'Category', 1, 0, 'C', 1);
$pdf->Cell(30, 8, 'Quantity', 1, 0, 'C', 1);
$pdf->Cell(40, 8, 'Total Price', 1, 1, 'C', 1);

// Table body
$pdf->SetFont('helvetica', '', 10);
while ($row = $topProducts->fetch_assoc()) {
    $pdf->Cell(50, 8, $row['med_name'], 1);
    $pdf->Cell(40, 8, $row['category'], 1);
    $pdf->Cell(30, 8, $row['qty'], 1, 0, 'C');
    $pdf->Cell(40, 8, '₱' . number_format($row['total'], 2), 1, 1, 'R');
}

// ----------------- Output PDF -----------------
$pdf->Output('Pharmacy_Sales_Dashboard.pdf', 'D'); // 'D' for download
