<?php
require('../inventory_and_supply_chain_management/fpdf/fpdf.php');
include '../../SQL/config.php';

// ================= FILTERS =================
$filterType = $_GET['filter'] ?? 'month';
$month = $_GET['month'] ?? date('m');
$year  = $_GET['year']  ?? date('Y');

// ================= QUERY =================
if ($filterType === 'month') {
    $stmt = $pdo->prepare("
        SELECT * FROM department_request
        WHERE status='Approved'
        AND YEAR(month)=:year AND MONTH(month)=:month
    ");
    $stmt->execute([':year'=>$year, ':month'=>$month]);
    $periodLabel = date('F', mktime(0,0,0,$month,1))." ".$year;
} else {
    $stmt = $pdo->prepare("
        SELECT * FROM department_request
        WHERE status='Approved'
        AND YEAR(month)=:year
    ");
    $stmt->execute([':year'=>$year]);
    $periodLabel = "Year ".$year;
}

$requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================= PREPARE DATA =================
$rows = [];
$grandRequested = 0;
$grandApproved  = 0;
$grandTotal     = 0;

foreach ($requests as $r) {
    $items = json_decode($r['items'], true);
    if (!is_array($items)) continue;

    foreach ($items as $item) {
        $requested = (int)($item['quantity'] ?? 0);
        $approved  = (int)($item['approved_quantity'] ?? $requested);
        $price     = (float)($item['price'] ?? 0);
        $total     = $approved * $price;

        $grandRequested += $requested;
        $grandApproved  += $approved;
        $grandTotal     += $total;

        $rows[] = [
            $r['department'],
            $item['name'] ?? '',
            $item['description'] ?? '',
            $requested,
            $approved,
            number_format($total,2)
        ];
    }
}

// ================= PDF =================
$pdf = new FPDF('L','mm','A4');
$pdf->AddPage();
$pdf->SetFont('Arial','B',14);
$pdf->Cell(0,10,'Approved Item Requests Report',0,1,'C');

$pdf->SetFont('Arial','',10);
$pdf->Cell(0,8,'Period: '.$periodLabel,0,1,'C');
$pdf->Ln(3);

// Header
$pdf->SetFont('Arial','B',9);
$pdf->SetFillColor(30,144,255);
$pdf->SetTextColor(255);

$headers = ['Department','Item','Description','Requested','Approved','Total'];
$widths  = [45,45,90,30,30,30];

foreach ($headers as $i=>$h) {
    $pdf->Cell($widths[$i],8,$h,1,0,'C',true);
}
$pdf->Ln();

// Body
$pdf->SetFont('Arial','',9);
$pdf->SetTextColor(0);

foreach ($rows as $row) {
    foreach ($row as $i=>$col) {
        $pdf->Cell($widths[$i],8,$col,1);
    }
    $pdf->Ln();
}

// Totals
$pdf->SetFont('Arial','B',9);
$pdf->Cell(180,8,'GRAND TOTAL',1);
$pdf->Cell(30,8,$grandRequested,1,0,'C');
$pdf->Cell(30,8,$grandApproved,1,0,'C');
$pdf->Cell(30,8,'₱'.number_format($grandTotal,2),1,0,'C');

$pdf->Output('I','Approved_Item_Requests_Report.pdf');
