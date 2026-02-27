<?php
// Financial Report PDF Export using TCPDF
session_start();

include '../../../SQL/config.php';

if (!isset($_SESSION['pharmacy']) || $_SESSION['pharmacy'] !== true) {
    header('Location: ../login.php');
    exit();
}

// Include TCPDF library
require_once '../tcpdf/TCPDF-main/tcpdf.php';

// Date Range Filter
$from_date = $_GET['from_date'] ?? date('Y-m-d', strtotime('-30 days'));
$to_date   = $_GET['to_date']   ?? date('Y-m-d');

// Sanitize date inputs
$from_date = preg_replace('/[^0-9\-]/', '', $from_date);
$to_date   = preg_replace('/[^0-9\-]/', '', $to_date);

// SALES REPORT - Combined Prescription and OTC sales by date
$sales_query = "
    SELECT
        sale_date,
        SUM(CASE WHEN transaction_type = 'Prescription' THEN 1 ELSE 0 END) AS rx_orders,
        SUM(CASE WHEN transaction_type = 'OTC'          THEN 1 ELSE 0 END) AS otc_orders,
        SUM(CASE WHEN transaction_type = 'Prescription' THEN 1 ELSE 0 END)
            + SUM(CASE WHEN transaction_type = 'OTC'   THEN 1 ELSE 0 END)  AS total_orders,
        SUM(CASE WHEN transaction_type = 'Prescription' THEN total_sales ELSE 0 END) AS rx_sales,
        SUM(CASE WHEN transaction_type = 'OTC'          THEN total_sales ELSE 0 END) AS otc_sales,
        SUM(total_sales) AS total_sales
    FROM (
        SELECT DATE(ppi.dispensed_date) AS sale_date,
               SUM(ppi.total_price)     AS total_sales,
               'Prescription'           AS transaction_type
        FROM pharmacy_prescription_items ppi
        WHERE DATE(ppi.dispensed_date) BETWEEN '$from_date' AND '$to_date'
          AND ppi.dispensed_date IS NOT NULL
        GROUP BY DATE(ppi.dispensed_date)

        UNION ALL

        SELECT DATE(ps.sale_date) AS sale_date,
               SUM(ps.total_price) AS total_sales,
               'OTC'               AS transaction_type
        FROM pharmacy_sales ps
        WHERE DATE(ps.sale_date) BETWEEN '$from_date' AND '$to_date'
        GROUP BY DATE(ps.sale_date)
    ) combined
    GROUP BY sale_date
    ORDER BY sale_date DESC
    LIMIT 30
";
$sales_result = $conn->query($sales_query);
$sales_data   = $sales_result ? $sales_result->fetch_all(MYSQLI_ASSOC) : [];

// SALES BY MEDICINE
$medicine_query = "
    SELECT
        med_id,
        med_name,
        category,
        SUM(quantity_sold)  AS quantity_sold,
        SUM(total_revenue)  AS total_revenue,
        SUM(cost)           AS cost,
        SUM(profit)         AS profit
    FROM (
        SELECT
            ppi.med_id,
            pi.med_name,
            pi.category,
            SUM(ppi.quantity_dispensed)                              AS quantity_sold,
            SUM(ppi.total_price)                                     AS total_revenue,
            SUM(ppi.quantity_dispensed * ppi.unit_price)             AS cost,
            SUM(ppi.total_price - ppi.quantity_dispensed * ppi.unit_price) AS profit
        FROM pharmacy_prescription_items ppi
        JOIN pharmacy_inventory pi ON ppi.med_id = pi.med_id
        WHERE DATE(ppi.dispensed_date) BETWEEN '$from_date' AND '$to_date'
          AND ppi.dispensed_date IS NOT NULL
        GROUP BY ppi.med_id, pi.med_name, pi.category

        UNION ALL

        SELECT
            pi.med_id,
            ps.med_name,
            pi.category,
            SUM(ps.quantity_sold)                                    AS quantity_sold,
            SUM(ps.total_price)                                      AS total_revenue,
            SUM(ps.quantity_sold * ps.price_per_unit)                AS cost,
            SUM(ps.total_price - ps.quantity_sold * ps.price_per_unit) AS profit
        FROM pharmacy_sales ps
        LEFT JOIN pharmacy_inventory pi ON ps.med_name = pi.med_name
        WHERE DATE(ps.sale_date) BETWEEN '$from_date' AND '$to_date'
        GROUP BY ps.med_name, pi.category, pi.med_id
    ) combined_meds
    GROUP BY med_id, med_name, category
    ORDER BY total_revenue DESC
    LIMIT 30
";
$medicine_result = $conn->query($medicine_query);
$medicine_data   = $medicine_result ? $medicine_result->fetch_all(MYSQLI_ASSOC) : [];

// Calculate totals
$total_revenue = array_sum(array_column($medicine_data, 'total_revenue'));
$total_cost    = array_sum(array_column($medicine_data, 'cost'));
$total_profit  = $total_revenue - $total_cost;
$profit_margin = $total_revenue > 0 ? ($total_profit / $total_revenue) * 100 : 0;

// ─── Custom PDF class ────────────────────────────────────────────────────────

class FinancialPDF extends TCPDF
{

    public function Header()
    {
        // Blue header background
        $this->SetFillColor(32, 110, 215);
        $this->Rect(0, 0, $this->getPageWidth(), 25, 'F');

        // Title
        $this->SetTextColor(255, 255, 255);
        $this->SetFont('helvetica', 'B', 22);
        $this->SetXY(15, 7);
        $this->Cell(0, 10, 'FINANCIAL REPORT', 0, 0, 'L');

        // Sub-title
        $this->SetFont('helvetica', '', 9);
        $this->SetXY(15, 17);
        $this->Cell(0, 5, 'HMS - Hospital Management System | Pharmacy Department', 0, 0, 'L');

        $this->SetTextColor(0, 0, 0);
        $this->SetY(30);
    }

    public function Footer()
    {
        $pageW = $this->getPageWidth();
        $this->SetY(-15);
        $this->SetFont('helvetica', '', 8);
        $this->SetTextColor(100, 100, 100);

        // Footer rule
        $this->SetDrawColor(200, 200, 200);
        $this->Line(15, $this->GetY(), $pageW - 15, $this->GetY());

        $this->SetY(-12);
        $this->Cell(0, 5, 'Generated: ' . date('F d, Y - g:i A'), 0, 0, 'L');
        $this->Cell(0, 5, 'Page ' . $this->getAliasNumPage() . ' of ' . $this->getAliasNbPages(), 0, 0, 'R');
    }
}

// ─── Build PDF ───────────────────────────────────────────────────────────────

$pdf = new FinancialPDF('P', 'mm', 'A4', true, 'UTF-8', false);
$pdf->SetDefaultMonospacedFont('courier');
$pdf->SetMargins(15, 35, 15);
$pdf->SetAutoPageBreak(true, 20);
$pdf->AddPage();

$pageW    = $pdf->getPageWidth();
$usableW  = $pageW - 30; // left + right margin

// Report period line
$pdf->SetFont('helvetica', '', 9);
$pdf->SetTextColor(100, 100, 100);
$pdf->Cell(0, 5, 'Period: ' . date('M d, Y', strtotime($from_date)) . ' to ' . date('M d, Y', strtotime($to_date)), 0, 1, 'L');

// ── Financial Summary ────────────────────────────────────────────────────────

$pdf->SetFont('helvetica', 'B', 14);
$pdf->SetTextColor(32, 110, 215);
$pdf->SetY($pdf->GetY() + 3);
$pdf->Cell(0, 8, 'FINANCIAL SUMMARY', 0, 1, 'L');
$pdf->SetY($pdf->GetY() + 2);

$summaries = [
    ['Total Revenue', 'P' . number_format($total_revenue, 2), [16,  185, 129]],
    ['Total Cost',    'P' . number_format($total_cost,    2), [255, 159,  64]],
    ['Total Profit',  'P' . number_format($total_profit,  2), [59,  130, 246]],
    ['Profit Margin', number_format($profit_margin, 2) . '%', [168,  85, 247]],
];

$col_width  = $usableW / 2;
$box_height = 12;
$box_gap    = 4;

for ($i = 0; $i < count($summaries); $i++) {
    $stat = $summaries[$i];
    $col  = $i % 2;       // 0 = left, 1 = right
    $row  = intdiv($i, 2);

    // Y position: recalculate from base each row so columns stay aligned
    if ($col === 0) {
        $rowY = $pdf->GetY();
    }

    $x = 15 + $col * $col_width;

    // Light tinted background
    $pdf->setAlpha(0.12); // FIX: use setAlpha() instead of SetOpacity()
    $pdf->SetFillColor($stat[2][0], $stat[2][1], $stat[2][2]);
    $pdf->Rect($x + 2, $rowY, $col_width - 4, $box_height, 'F');
    $pdf->setAlpha(1);

    // Coloured border
    $pdf->SetDrawColor($stat[2][0], $stat[2][1], $stat[2][2]);
    $pdf->SetLineWidth(0.5);
    $pdf->Rect($x + 2, $rowY, $col_width - 4, $box_height);

    // Label
    $pdf->SetXY($x + 4, $rowY + 2);
    $pdf->SetFont('helvetica', '', 9);
    $pdf->SetTextColor(80, 80, 80);
    $pdf->Cell($col_width - 60, 8, $stat[0], 0, 0, 'L');

    // Value
    $pdf->SetXY($x + $col_width - 55, $rowY + 2);
    $pdf->SetFont('helvetica', 'B', 11);
    $pdf->SetTextColor($stat[2][0], $stat[2][1], $stat[2][2]);
    $pdf->Cell(50, 8, $stat[1], 0, 0, 'R');

    // After placing the right column (or the last item), advance Y
    if ($col === 1 || $i === count($summaries) - 1) {
        $pdf->SetY($rowY + $box_height + $box_gap);
    }
}

// Separator
$pdf->SetY($pdf->GetY() + 2);
$pdf->SetDrawColor(200, 200, 200);
$pdf->SetLineWidth(0.3);
$pdf->Line(15, $pdf->GetY(), $pageW - 15, $pdf->GetY());
$pdf->SetY($pdf->GetY() + 5);

// ── Sales Report Table ───────────────────────────────────────────────────────

$pdf->SetFont('helvetica', 'B', 13);
$pdf->SetTextColor(32, 110, 215);
$pdf->Cell(0, 8, 'SALES REPORT', 0, 1, 'L');
$pdf->SetY($pdf->GetY() + 2);

$sales_headers = ['Date', 'Total Orders', 'Rx Orders', 'OTC Orders', 'Total Sales'];
$sales_widths  = [30, 28, 28, 28, 36];

// Header row
$pdf->SetFillColor(32, 110, 215);
$pdf->SetTextColor(255, 255, 255);
$pdf->SetFont('helvetica', 'B', 9);
$pdf->SetDrawColor(32, 110, 215);
foreach ($sales_headers as $k => $col) {
    $pdf->Cell($sales_widths[$k], 7, $col, 1, 0, 'C', true);
}
$pdf->Ln();

// Data rows
$pdf->SetFont('helvetica', '', 8);
$pdf->SetTextColor(0, 0, 0);
foreach ($sales_data as $idx => $sale) {
    $fill = ($idx % 2 === 0) ? [245, 248, 252] : [255, 255, 255];
    $pdf->SetFillColor($fill[0], $fill[1], $fill[2]);
    $pdf->SetDrawColor(220, 220, 220);

    $pdf->Cell($sales_widths[0], 6, date('M d, Y', strtotime($sale['sale_date'])), 1, 0, 'C', true);
    $pdf->Cell($sales_widths[1], 6, $sale['total_orders'],                         1, 0, 'C', true);
    $pdf->Cell($sales_widths[2], 6, $sale['rx_orders'],                            1, 0, 'C', true);
    $pdf->Cell($sales_widths[3], 6, $sale['otc_orders'],                           1, 0, 'C', true);
    $pdf->Cell($sales_widths[4], 6, 'P' . number_format($sale['total_sales'] ?? 0, 2), 1, 0, 'R', true);
    $pdf->Ln();
}

// ── Sales by Medicine (new page) ─────────────────────────────────────────────

$pdf->AddPage();

$pdf->SetFont('helvetica', 'B', 13);
$pdf->SetTextColor(32, 110, 215);
$pdf->Cell(0, 8, 'SALES BY MEDICINE', 0, 1, 'L');
$pdf->SetY($pdf->GetY() + 2);

$med_headers = ['Medicine Name', 'Qty Sold', 'Revenue', 'Cost', 'Profit'];
$med_widths  = [55, 20, 28, 28, 29];

// Header row
$pdf->SetFillColor(32, 110, 215);
$pdf->SetTextColor(255, 255, 255);
$pdf->SetFont('helvetica', 'B', 9);
$pdf->SetDrawColor(32, 110, 215);
foreach ($med_headers as $k => $col) {
    $pdf->Cell($med_widths[$k], 7, $col, 1, 0, 'C', true);
}
$pdf->Ln();

// Data rows
$pdf->SetFont('helvetica', '', 8);
$pdf->SetTextColor(0, 0, 0);
foreach ($medicine_data as $idx => $med) {
    $fill = ($idx % 2 === 0) ? [245, 248, 252] : [255, 255, 255];
    $pdf->SetFillColor($fill[0], $fill[1], $fill[2]);
    $pdf->SetDrawColor(220, 220, 220);

    $pdf->Cell($med_widths[0], 6, substr($med['med_name'], 0, 25),                1, 0, 'L', true);
    $pdf->Cell($med_widths[1], 6, $med['quantity_sold'],                           1, 0, 'C', true);
    $pdf->Cell($med_widths[2], 6, 'P' . number_format($med['total_revenue'], 2),  1, 0, 'R', true);
    $pdf->Cell($med_widths[3], 6, 'P' . number_format($med['cost'],          2),  1, 0, 'R', true);
    $pdf->Cell($med_widths[4], 6, 'P' . number_format($med['profit'],        2),  1, 0, 'R', true);
    $pdf->Ln();
}

// ── Output ───────────────────────────────────────────────────────────────────

$pdf->Output('Financial_Report_' . date('Y-m-d_His') . '.pdf', 'D');
exit();
