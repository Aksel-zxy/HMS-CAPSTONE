<?php
include '../../../SQL/config.php';

if (!isset($_SESSION['pharmacy']) || $_SESSION['pharmacy'] !== true) {
    header('Location: ../login.php');
    exit();
}

// Date Range Filter
$from_date = $_GET['from_date'] ?? date('Y-m-d', strtotime('-30 days'));
$to_date = $_GET['to_date'] ?? date('Y-m-d');

// SALES REPORT - Combined Prescription and OTC sales by date
$sales_query = "
    SELECT
        sale_date,
        SUM(CASE WHEN transaction_type = 'Prescription' THEN 1 ELSE 0 END) as rx_orders,
        SUM(CASE WHEN transaction_type = 'OTC' THEN 1 ELSE 0 END) as otc_orders,
        SUM(CASE WHEN transaction_type = 'Prescription' THEN 1 ELSE 0 END) + SUM(CASE WHEN transaction_type = 'OTC' THEN 1 ELSE 0 END) as total_orders,
        SUM(CASE WHEN transaction_type = 'Prescription' THEN total_sales ELSE 0 END) as rx_sales,
        SUM(CASE WHEN transaction_type = 'OTC' THEN total_sales ELSE 0 END) as otc_sales,
        SUM(total_sales) as total_sales
    FROM (
        SELECT DATE(ppi.dispensed_date) as sale_date, SUM(ppi.total_price) as total_sales, 'Prescription' as transaction_type
        FROM pharmacy_prescription_items ppi
        WHERE DATE(ppi.dispensed_date) BETWEEN '{$from_date}' AND '{$to_date}' AND ppi.dispensed_date IS NOT NULL
        GROUP BY DATE(ppi.dispensed_date)

        UNION ALL

        SELECT DATE(ps.sale_date) as sale_date, SUM(ps.total_price) as total_sales, 'OTC' as transaction_type
        FROM pharmacy_sales ps
        WHERE DATE(ps.sale_date) BETWEEN '{$from_date}' AND '{$to_date}'
        GROUP BY DATE(ps.sale_date)
    ) combined
    GROUP BY sale_date
    ORDER BY sale_date DESC
";
$sales_result = $conn->query($sales_query);
$sales_data = $sales_result ? $sales_result->fetch_all(MYSQLI_ASSOC) : [];

// SALES BY MEDICINE - Get detailed medicine sales from both Prescription and OTC
$medicine_query = "
    SELECT
        med_id,
        med_name,
        category,
        SUM(quantity_sold) as quantity_sold,
        SUM(total_revenue) as total_revenue,
        SUM(cost) as cost,
        SUM(profit) as profit
    FROM (
        SELECT
            ppi.med_id,
            pi.med_name,
            pi.category,
            SUM(ppi.quantity_dispensed) as quantity_sold,
            SUM(ppi.total_price) as total_revenue,
            SUM(ppi.quantity_dispensed * ppi.unit_price) as cost,
            SUM(ppi.total_price - (ppi.quantity_dispensed * ppi.unit_price)) as profit
        FROM pharmacy_prescription_items ppi
        JOIN pharmacy_inventory pi ON ppi.med_id = pi.med_id
        WHERE DATE(ppi.dispensed_date) BETWEEN '{$from_date}' AND '{$to_date}' AND ppi.dispensed_date IS NOT NULL
        GROUP BY ppi.med_id, pi.med_name, pi.category

        UNION ALL

        SELECT
            pi.med_id,
            ps.med_name,
            pi.category,
            SUM(ps.quantity_sold) as quantity_sold,
            SUM(ps.total_price) as total_revenue,
            SUM(ps.quantity_sold * ps.price_per_unit) as cost,
            SUM(ps.total_price - (ps.quantity_sold * ps.price_per_unit)) as profit
        FROM pharmacy_sales ps
        LEFT JOIN pharmacy_inventory pi ON ps.med_name = pi.med_name
        WHERE DATE(ps.sale_date) BETWEEN '{$from_date}' AND '{$to_date}'
        GROUP BY ps.med_name, pi.category, pi.med_id
    ) combined_meds
    GROUP BY med_id, med_name, category
    ORDER BY total_revenue DESC
";
$medicine_result = $conn->query($medicine_query);
$medicine_data = $medicine_result ? $medicine_result->fetch_all(MYSQLI_ASSOC) : [];

// Calculate totals
$total_revenue = array_sum(array_column($medicine_data, 'total_revenue'));
$total_cost = array_sum(array_column($medicine_data, 'cost'));
$total_profit = $total_revenue - $total_cost;
$profit_margin = $total_revenue > 0 ? ($total_profit / $total_revenue) * 100 : 0;

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="Financial_Report_' . date('Y-m-d_His') . '.xlsx"');
header('Cache-Control: max-age=0');

function createFinancialXLSX($tmpfile, $sales_data, $medicine_data, $from_date, $to_date, $total_revenue, $total_cost, $total_profit, $profit_margin) {
    $zip = new ZipArchive();
    $zip->open($tmpfile, ZipArchive::CREATE | ZipArchive::OVERWRITE);

    // [Content_Types].xml
    $content_types = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
<Default Extension="xml" ContentType="application/xml"/>
<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>
<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
<Override PartName="/xl/worksheets/sheet2.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
<Override PartName="/xl/worksheets/sheet3.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>
<Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>
</Types>';
    $zip->addFromString('[Content_Types].xml', $content_types);

    // _rels/.rels
    $rels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>
<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>
</Relationships>';
    $zip->addFromString('_rels/.rels', $rels);

    // xl/_rels/workbook.xml.rels
    $workbook_rels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>
<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet2.xml"/>
<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet3.xml"/>
<Relationship Id="rId4" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>
</Relationships>';
    $zip->addFromString('xl/_rels/workbook.xml.rels', $workbook_rels);

    // xl/workbook.xml
    $workbook = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
<sheets>
<sheet name="Summary" sheetId="1" r:id="rId1"/>
<sheet name="Sales Report" sheetId="2" r:id="rId2"/>
<sheet name="Sales by Medicine" sheetId="3" r:id="rId3"/>
</sheets>
</workbook>';
    $zip->addFromString('xl/workbook.xml', $workbook);

    // xl/styles.xml
    $styles = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
<fonts>
<font><sz val="11"/><color theme="1"/><name val="Calibri"/><family val="2"/></font>
<font><b/><sz val="11"/><color theme="1"/><name val="Calibri"/><family val="2"/></font>
<font><b/><sz val="14"/><color theme="1"/><name val="Calibri"/><family val="2"/></font>
</fonts>
<fills>
<fill><patternFill patternType="none"/></fill>
<fill><patternFill patternType="gray125"/></fill>
<fill><patternFill patternType="solid"><fgColor theme="2"/></patternFill></fill>
<fill><patternFill patternType="solid"><fgColor RGB="D3E4FD"/></patternFill></fill>
</fills>
<borders>
<border><left/><right/><top/><bottom/><diagonal/></border>
<border><left style="thin"/><right style="thin"/><top style="thin"/><bottom style="thin"/><diagonal/></border>
</borders>
<cellStyleXfs>
<xf numFmtId="0" fontId="0" fillId="0" borderId="0"/>
</cellStyleXfs>
<cellXfs>
<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>
<xf numFmtId="0" fontId="1" fillId="3" borderId="1" xfId="0"/>
<xf numFmtId="0" fontId="2" fillId="2" borderId="1" xfId="0"/>
<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0"/>
<xf numFmtId="2" fontId="0" fillId="0" borderId="1" xfId="0"/>
</cellXfs>
</styleSheet>';
    $zip->addFromString('xl/styles.xml', $styles);

    // docProps/core.xml
    $core_props = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/officeDocument/2006/custom-properties" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" xmlns:dcmitype="http://purl.org/dc/dcmitype/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">
<dc:creator>HMS Pharmacy</dc:creator>
<dc:title>Financial Report</dc:title>
<dcterms:created xsi:type="dcterms:W3CDTF">' . date('c') . '</dcterms:created>
</cp:coreProperties>';
    $zip->addFromString('docProps/core.xml', $core_props);

    // Sheet 1 - Summary
    $sheet1 = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
<sheetPr filterOn="0"/>
<sheetData>';

    $sheet1 .= '<row r="1"><c r="A1" s="2"><v>FINANCIAL REPORT - SUMMARY</v></c></row>';
    $sheet1 .= '<row r="2"><c r="A2" s="0"><v>Period: ' . htmlspecialchars(date('F d, Y', strtotime($from_date))) . ' to ' . htmlspecialchars(date('F d, Y', strtotime($to_date))) . '</v></c></row>';
    $sheet1 .= '<row r="3"><c r="A3" s="0"><v>Generated: ' . date('F d, Y g:i A') . '</v></c></row>';
    $sheet1 .= '<row r="4"><c r="A4"><v></v></c></row>';

    // Summary Stats
    $sheet1 .= '<row r="5"><c r="A5" s="1"><v>Metric</v></c><c r="B5" s="1"><v>Value</v></c></row>';
    $sheet1 .= '<row r="6"><c r="A6" s="3"><v>Total Revenue</v></c><c r="B6" s="4"><v>' . number_format($total_revenue, 2) . '</v></c></row>';
    $sheet1 .= '<row r="7"><c r="A7" s="3"><v>Total Cost</v></c><c r="B7" s="4"><v>' . number_format($total_cost, 2) . '</v></c></row>';
    $sheet1 .= '<row r="8"><c r="A8" s="3"><v>Total Profit</v></c><c r="B8" s="4"><v>' . number_format($total_profit, 2) . '</v></c></row>';
    $sheet1 .= '<row r="9"><c r="A9" s="3"><v>Profit Margin (%)</v></c><c r="B9" s="4"><v>' . number_format($profit_margin, 2) . '</v></c></row>';

    $sheet1 .= '</sheetData>
<colBreaks count="0" max="16384"/>
<rowBreaks count="0" max="1048576"/>
<pageMargins left="0.75" top="1" right="0.75" bottom="1" header="0.5" footer="0.5"/>
</worksheet>';
    $zip->addFromString('xl/worksheets/sheet1.xml', $sheet1);

    // Sheet 2 - Sales Report
    $sheet2 = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
<sheetPr filterOn="0"/>
<sheetData>';

    $sheet2 .= '<row r="1"><c r="A1" s="1"><v>Date</v></c><c r="B1" s="1"><v>Total Orders</v></c><c r="C1" s="1"><v>Rx Orders</v></c><c r="D1" s="1"><v>OTC Orders</v></c><c r="E1" s="1"><v>Rx Sales</v></c><c r="F1" s="1"><v>OTC Sales</v></c><c r="G1" s="1"><v>Daily Total</v></c></row>';

    $row = 2;
    foreach ($sales_data as $sale) {
        $sheet2 .= '<row r="' . $row . '"><c r="A' . $row . '" s="3"><v>' . htmlspecialchars(date('M d, Y', strtotime($sale['sale_date']))) . '</v></c>';
        $sheet2 .= '<c r="B' . $row . '" s="4"><v>' . ($sale['total_orders'] ?? 0) . '</v></c>';
        $sheet2 .= '<c r="C' . $row . '" s="4"><v>' . ($sale['rx_orders'] ?? 0) . '</v></c>';
        $sheet2 .= '<c r="D' . $row . '" s="4"><v>' . ($sale['otc_orders'] ?? 0) . '</v></c>';
        $sheet2 .= '<c r="E' . $row . '" s="4"><v>' . number_format($sale['rx_sales'] ?? 0, 2) . '</v></c>';
        $sheet2 .= '<c r="F' . $row . '" s="4"><v>' . number_format($sale['otc_sales'] ?? 0, 2) . '</v></c>';
        $sheet2 .= '<c r="G' . $row . '" s="4"><v>' . number_format($sale['total_sales'] ?? 0, 2) . '</v></c></row>';
        $row++;
    }

    $sheet2 .= '</sheetData>
<cols>
<col min="1" max="1" width="15"/>
<col min="2" max="2" width="12"/>
<col min="3" max="3" width="12"/>
<col min="4" max="4" width="12"/>
<col min="5" max="5" width="14"/>
<col min="6" max="6" width="14"/>
<col min="7" max="7" width="14"/>
</cols>
<pageMargins left="0.75" top="1" right="0.75" bottom="1" header="0.5" footer="0.5"/>
</worksheet>';
    $zip->addFromString('xl/worksheets/sheet2.xml', $sheet2);

    // Sheet 3 - Sales by Medicine
    $sheet3 = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
<sheetPr filterOn="0"/>
<sheetData>';

    $sheet3 .= '<row r="1"><c r="A1" s="1"><v>Medicine Name</v></c><c r="B1" s="1"><v>Category</v></c><c r="C1" s="1"><v>Qty Sold</v></c><c r="D1" s="1"><v>Total Revenue</v></c><c r="E1" s="1"><v>Total Cost</v></c><c r="F1" s="1"><v>Profit</v></c></row>';

    $row = 2;
    foreach ($medicine_data as $med) {
        $sheet3 .= '<row r="' . $row . '"><c r="A' . $row . '" s="3"><v>' . htmlspecialchars($med['med_name']) . '</v></c>';
        $sheet3 .= '<c r="B' . $row . '" s="3"><v>' . htmlspecialchars($med['category'] ?? 'N/A') . '</v></c>';
        $sheet3 .= '<c r="C' . $row . '" s="4"><v>' . $med['quantity_sold'] . '</v></c>';
        $sheet3 .= '<c r="D' . $row . '" s="4"><v>' . number_format($med['total_revenue'], 2) . '</v></c>';
        $sheet3 .= '<c r="E' . $row . '" s="4"><v>' . number_format($med['cost'], 2) . '</v></c>';
        $sheet3 .= '<c r="F' . $row . '" s="4"><v>' . number_format($med['profit'], 2) . '</v></c></row>';
        $row++;
    }

    $sheet3 .= '</sheetData>
<cols>
<col min="1" max="1" width="20"/>
<col min="2" max="2" width="15"/>
<col min="3" max="3" width="10"/>
<col min="4" max="4" width="14"/>
<col min="5" max="5" width="14"/>
<col min="6" max="6" width="14"/>
</cols>
<pageMargins left="0.75" top="1" right="0.75" bottom="1" header="0.5" footer="0.5"/>
</worksheet>';
    $zip->addFromString('xl/worksheets/sheet3.xml', $sheet3);

    $zip->close();
}

$tmpfile = tempnam(sys_get_temp_dir(), 'xlsx');
createFinancialXLSX($tmpfile, $sales_data, $medicine_data, $from_date, $to_date, $total_revenue, $total_cost, $total_profit, $profit_margin);

echo file_get_contents($tmpfile);
unlink($tmpfile);
exit();
?>
