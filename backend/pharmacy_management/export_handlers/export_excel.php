<?php
include '../../../SQL/config.php';

if (!isset($_SESSION['pharmacy']) || $_SESSION['pharmacy'] !== true) {
    header('Location: ../login.php');
    exit();
}

// Get inventory data
$query = "
    SELECT
        med_id,
        med_name,
        generic_name,
        brand_name,
        category,
        dosage,
        unit,
        stock_quantity,
        unit_price,
        stock_quantity * unit_price as total_value,
        prescription_required,
        CASE
            WHEN stock_quantity = 0 THEN 'Out of Stock'
            WHEN stock_quantity > 0 AND stock_quantity <= 10 THEN 'Low Stock'
            ELSE 'Available'
        END as status,
        storage_room,
        shelf_no,
        rack_no,
        bin_no
    FROM pharmacy_inventory
    ORDER BY category, med_name
";
$result = $conn->query($query);
$medicines = $result->fetch_all(MYSQLI_ASSOC);

// Get category summary
$category_query = "
    SELECT
        category,
        COUNT(*) as medicine_count,
        SUM(stock_quantity) as total_stock,
        SUM(stock_quantity * unit_price) as category_value
    FROM pharmacy_inventory
    GROUP BY category
    ORDER BY category_value DESC
";
$category_result = $conn->query($category_query);
$categories = $category_result->fetch_all(MYSQLI_ASSOC);

// Get summary statistics
$summary_query = "
    SELECT
        COUNT(DISTINCT med_id) as total_medicines,
        SUM(stock_quantity) as total_stock_pieces,
        SUM(stock_quantity * unit_price) as total_inventory_value,
        COUNT(CASE WHEN stock_quantity = 0 THEN 1 END) as out_of_stock_count,
        COUNT(CASE WHEN stock_quantity <= 10 THEN 1 END) as low_stock_count
    FROM pharmacy_inventory
";
$summary_result = $conn->query($summary_query);
$summary = $summary_result->fetch_assoc();

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="Inventory_Report_' . date('Y-m-d_His') . '.xlsx"');
header('Cache-Control: max-age=0');

function createXLSXFile($tmpfile, $summary, $categories, $medicines) {
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
<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>
</Relationships>';
    $zip->addFromString('xl/_rels/workbook.xml.rels', $workbook_rels);

    // xl/workbook.xml
    $workbook = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
<sheets>
<sheet name="Summary" sheetId="1" r:id="rId1"/>
<sheet name="Inventory" sheetId="2" r:id="rId2"/>
</sheets>
</workbook>';
    $zip->addFromString('xl/workbook.xml', $workbook);

    // xl/styles.xml - Define styles
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
<dc:title>Pharmacy Inventory Report</dc:title>
<dcterms:created xsi:type="dcterms:W3CDTF">' . date('c') . '</dcterms:created>
</cp:coreProperties>';
    $zip->addFromString('docProps/core.xml', $core_props);

    // Sheet 1 - Summary
    $sheet1 = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
<sheetPr filterOn="0"/>
<sheetData>';

    // Title
    $sheet1 .= '<row r="1"><c r="A1" s="2"><v>PHARMACY INVENTORY REPORT - SUMMARY</v></c></row>';
    $sheet1 .= '<row r="2"><c r="A2" s="0"><v>Generated: ' . date('F d, Y g:i A') . '</v></c></row>';
    $sheet1 .= '<row r="3"><c r="A3"><v></v></c></row>';

    // Summary Stats
    $sheet1 .= '<row r="4"><c r="A4" s="1"><v>Metric</v></c><c r="B4" s="1"><v>Value</v></c></row>';
    $sheet1 .= '<row r="5"><c r="A5" s="3"><v>Total Medicines</v></c><c r="B5" s="4"><v>' . $summary['total_medicines'] . '</v></c></row>';
    $sheet1 .= '<row r="6"><c r="A6" s="3"><v>Total Stock (pieces)</v></c><c r="B6" s="4"><v>' . $summary['total_stock_pieces'] . '</v></c></row>';
    $sheet1 .= '<row r="7"><c r="A7" s="3"><v>Total Inventory Value (₱)</v></c><c r="B7" s="4"><v>' . number_format($summary['total_inventory_value'], 2) . '</v></c></row>';
    $sheet1 .= '<row r="8"><c r="A8" s="3"><v>Out of Stock Items</v></c><c r="B8" s="4"><v>' . $summary['out_of_stock_count'] . '</v></c></row>';
    $sheet1 .= '<row r="9"><c r="A9" s="3"><v>Low Stock Items</v></c><c r="B9" s="4"><v>' . $summary['low_stock_count'] . '</v></c></row>';

    $sheet1 .= '<row r="10"><c r="A10"><v></v></c></row>';
    $sheet1 .= '<row r="11"><c r="A11" s="2"><v>INVENTORY BY CATEGORY</v></c></row>';
    $sheet1 .= '<row r="12"><c r="A12" s="1"><v>Category</v></c><c r="B12" s="1"><v>Count</v></c><c r="C12" s="1"><v>Stock</v></c><c r="D12" s="1"><v>Value (₱)</v></c></row>';

    $row = 13;
    foreach ($categories as $cat) {
        $sheet1 .= '<row r="' . $row . '"><c r="A' . $row . '" s="3"><v>' . htmlspecialchars($cat['category']) . '</v></c>';
        $sheet1 .= '<c r="B' . $row . '" s="4"><v>' . $cat['medicine_count'] . '</v></c>';
        $sheet1 .= '<c r="C' . $row . '" s="4"><v>' . $cat['total_stock'] . '</v></c>';
        $sheet1 .= '<c r="D' . $row . '" s="4"><v>' . number_format($cat['category_value'], 2) . '</v></c></row>';
        $row++;
    }

    $sheet1 .= '</sheetData>
<colBreaks count="0" max="16384"/>
<rowBreaks count="0" max="1048576"/>
<pageMargins left="0.75" top="1" right="0.75" bottom="1" header="0.5" footer="0.5"/>
</worksheet>';
    $zip->addFromString('xl/worksheets/sheet1.xml', $sheet1);

    // Sheet 2 - Detailed Inventory
    $sheet2 = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
<sheetPr filterOn="0"/>
<sheetData>';

    // Header Row
    $headers = ['Med ID', 'Medicine Name', 'Generic Name', 'Brand Name', 'Category', 'Dosage', 'Unit', 'Stock Qty', 'Unit Price (₱)', 'Total Value (₱)', 'Rx', 'Status', 'Storage', 'Shelf', 'Rack', 'Bin'];
    $cols = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P'];

    $sheet2 .= '<row r="1">';
    foreach ($headers as $idx => $header) {
        $sheet2 .= '<c r="' . $cols[$idx] . '1" s="1"><v>' . $header . '</v></c>';
    }
    $sheet2 .= '</row>';

    // Data Rows
    $row = 2;
    foreach ($medicines as $med) {
        $sheet2 .= '<row r="' . $row . '">';
        $sheet2 .= '<c r="A' . $row . '" s="3"><v>' . htmlspecialchars($med['med_id']) . '</v></c>';
        $sheet2 .= '<c r="B' . $row . '" s="3"><v>' . htmlspecialchars($med['med_name']) . '</v></c>';
        $sheet2 .= '<c r="C' . $row . '" s="3"><v>' . htmlspecialchars($med['generic_name']) . '</v></c>';
        $sheet2 .= '<c r="D' . $row . '" s="3"><v>' . htmlspecialchars($med['brand_name']) . '</v></c>';
        $sheet2 .= '<c r="E' . $row . '" s="3"><v>' . htmlspecialchars($med['category']) . '</v></c>';
        $sheet2 .= '<c r="F' . $row . '" s="3"><v>' . htmlspecialchars($med['dosage']) . '</v></c>';
        $sheet2 .= '<c r="G' . $row . '" s="3"><v>' . htmlspecialchars($med['unit']) . '</v></c>';
        $sheet2 .= '<c r="H' . $row . '" s="4"><v>' . $med['stock_quantity'] . '</v></c>';
        $sheet2 .= '<c r="I' . $row . '" s="4"><v>' . number_format($med['unit_price'], 2) . '</v></c>';
        $sheet2 .= '<c r="J' . $row . '" s="4"><v>' . number_format($med['total_value'], 2) . '</v></c>';
        $sheet2 .= '<c r="K' . $row . '" s="3"><v>' . $med['prescription_required'] . '</v></c>';
        $sheet2 .= '<c r="L' . $row . '" s="3"><v>' . htmlspecialchars($med['status']) . '</v></c>';
        $sheet2 .= '<c r="M' . $row . '" s="3"><v>' . htmlspecialchars($med['storage_room']) . '</v></c>';
        $sheet2 .= '<c r="N' . $row . '" s="3"><v>' . $med['shelf_no'] . '</v></c>';
        $sheet2 .= '<c r="O' . $row . '" s="3"><v>' . $med['rack_no'] . '</v></c>';
        $sheet2 .= '<c r="P' . $row . '" s="3"><v>' . $med['bin_no'] . '</v></c>';
        $sheet2 .= '</row>';
        $row++;
    }

    $sheet2 .= '</sheetData>
<cols>
<col min="1" max="1" width="10"/>
<col min="2" max="2" width="20"/>
<col min="3" max="3" width="18"/>
<col min="4" max="4" width="15"/>
<col min="5" max="5" width="15"/>
<col min="6" max="6" width="12"/>
<col min="7" max="7" width="10"/>
<col min="8" max="8" width="12"/>
<col min="9" max="9" width="14"/>
<col min="10" max="10" width="14"/>
<col min="11" max="11" width="8"/>
<col min="12" max="12" width="15"/>
<col min="13" max="13" width="14"/>
<col min="14" max="14" width="10"/>
<col min="15" max="15" width="10"/>
<col min="16" max="16" width="10"/>
</cols>
<pageMargins left="0.75" top="1" right="0.75" bottom="1" header="0.5" footer="0.5"/>
</worksheet>';
    $zip->addFromString('xl/worksheets/sheet2.xml', $sheet2);

    $zip->close();
}

$tmpfile = tempnam(sys_get_temp_dir(), 'xlsx');
createXLSXFile($tmpfile, $summary, $categories, $medicines);

echo file_get_contents($tmpfile);
unlink($tmpfile);
exit();
?>
