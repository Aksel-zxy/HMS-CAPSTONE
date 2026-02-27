<?php
session_start();
include '../../../SQL/config.php';

if (!isset($_SESSION['employee_id'])) {
    header('Location: ../../../index.php');
    exit;
}

// Get parameters
$from_date = isset($_GET['from_date']) ? date('Y-m-d', strtotime($_GET['from_date'])) : date('Y-m-d', strtotime('-30 days'));
$to_date = isset($_GET['to_date']) ? date('Y-m-d', strtotime($_GET['to_date'])) : date('Y-m-d');
$report_type = isset($_GET['report_type']) ? $_GET['report_type'] : 'daily';

// Set headers for Excel download
header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
header('Content-Disposition: attachment; filename="Dispensing_Report_' . $report_type . '_' . date('Y-m-d_His') . '.xlsx"');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Create Excel file using simple approach with phpspreadsheet-like structure
// We'll use CSV format which Excel can open directly

$output = '';

// Add BOM for UTF-8
$output .= "\xEF\xBB\xBF";

// Report header
$output .= "HOSPITAL MANAGEMENT SYSTEM - PHARMACY DISPENSING REPORT\n";
$output .= "Report Type: " . ucfirst(str_replace('_', ' ', $report_type)) . "\n";
$output .= "Period: " . date('M d, Y', strtotime($from_date)) . " to " . date('M d, Y', strtotime($to_date)) . "\n";
$output .= "Generated: " . date('Y-m-d H:i:s') . "\n";
$output .= "\n";

switch ($report_type) {
    case 'daily':
        $output .= exportDailyDispensingExcel($conn, $from_date, $to_date);
        break;
    case 'inpatient':
        $output .= exportInpatientMedicationExcel($conn, $from_date, $to_date);
        break;
    case 'usage':
        $output .= exportMedicineUsageExcel($conn, $from_date, $to_date);
        break;
}

// Output the content
echo $output;
exit;

// ============================================
// CSV Export Functions
// ============================================

function exportDailyDispensingExcel($conn, $from_date, $to_date)
{
    $output = '';

    // Summary
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

    $output .= "SUMMARY STATISTICS\n";
    $output .= "Total Prescriptions," . ($summary['total_prescriptions'] ?? 0) . "\n";
    $output .= "Total Items Dispensed," . ($summary['total_items'] ?? 0) . "\n";
    $output .= "Total Quantity," . ($summary['total_quantity'] ?? 0) . "\n";
    $output .= "Total Revenue,₱" . number_format($summary['total_value'] ?? 0, 2) . "\n";
    $output .= "\n";

    // Daily Breakdown
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

    $output .= "DAILY BREAKDOWN\n";
    $output .= "Date,Prescriptions,Items Dispensed,Categories,Daily Total\n";

    while ($row = $result->fetch_assoc()) {
        $output .= csvEscape(date('M d, Y', strtotime($row['dispensing_date']))) . ",";
        $output .= csvEscape($row['prescriptions']) . ",";
        $output .= csvEscape($row['items_count']) . ",";
        $output .= csvEscape($row['categories']) . ",";
        $output .= csvEscape('₱' . number_format($row['daily_total'], 2)) . "\n";
    }

    return $output;
}

function exportInpatientMedicationExcel($conn, $from_date, $to_date)
{
    $output = '';

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

    $output .= "SUMMARY STATISTICS\n";
    $output .= "Total Inpatients," . ($summary['total_patients'] ?? 0) . "\n";
    $output .= "Total Prescriptions," . ($summary['total_prescriptions'] ?? 0) . "\n";
    $output .= "Total Items Dispensed," . ($summary['total_items'] ?? 0) . "\n";
    $output .= "Total Value,₱" . number_format($summary['total_value'] ?? 0, 2) . "\n";
    $output .= "\n";

    // Details
    $inpatient_query = "
        SELECT
            pp.prescription_id,
            CONCAT(pat.fname, ' ', pat.lname) as patient_name,
            pi.med_name,
            pi.category,
            ppi.quantity_prescribed,
            ppi.quantity_dispensed,
            ppi.unit_price,
            ppi.total_price,
            ppi.dispensed_date,
            emp.first_name as dispenser_name,
            pp.payment_type
        FROM pharmacy_prescription_items ppi
        JOIN pharmacy_prescription pp ON ppi.prescription_id = pp.prescription_id
        JOIN pharmacy_inventory pi ON ppi.med_id = pi.med_id
        JOIN patientinfo pat ON pp.patient_id = pat.patient_id
        LEFT JOIN hr_employees emp ON pp.dispensed_by = emp.employee_id
        WHERE DATE(ppi.dispensed_date) BETWEEN '$from_date' AND '$to_date'
        AND ppi.quantity_dispensed > 0
        ORDER BY ppi.dispensed_date DESC
    ";

    $result = $conn->query($inpatient_query);

    $output .= "MEDICATION DISPENSING DETAILS\n";
    $output .= "Rx ID,Patient Name,Medicine,Category,Qty Prescribed,Qty Dispensed,Unit Price,Total Price,Dispensed Date,Dispenser,Payment Type\n";

    while ($row = $result->fetch_assoc()) {
        $output .= csvEscape($row['prescription_id']) . ",";
        $output .= csvEscape($row['patient_name']) . ",";
        $output .= csvEscape($row['med_name']) . ",";
        $output .= csvEscape($row['category']) . ",";
        $output .= csvEscape($row['quantity_prescribed']) . ",";
        $output .= csvEscape($row['quantity_dispensed']) . ",";
        $output .= csvEscape('₱' . number_format($row['unit_price'], 2)) . ",";
        $output .= csvEscape('₱' . number_format($row['total_price'], 2)) . ",";
        $output .= csvEscape(date('M d, Y', strtotime($row['dispensed_date']))) . ",";
        $output .= csvEscape($row['dispenser_name'] ?? 'N/A') . ",";
        $output .= csvEscape($row['payment_type']) . "\n";
    }

    return $output;
}

function exportMedicineUsageExcel($conn, $from_date, $to_date)
{
    $output = '';

    // Summary
    $usage_summary_query = "
        SELECT
            COUNT(DISTINCT pi.med_id) as unique_medicines,
            SUM(ppi.quantity_dispensed) as total_quantity_dispensed,
            SUM(ppi.total_price) as total_value,
            AVG(ppi.total_price) as avg_dispensing_value
        FROM pharmacy_prescription_items ppi
        JOIN pharmacy_inventory pi ON ppi.med_id = pi.med_id
        WHERE DATE(ppi.dispensed_date) BETWEEN '$from_date' AND '$to_date'
        AND ppi.quantity_dispensed > 0
    ";

    $summary = $conn->query($usage_summary_query)->fetch_assoc();

    $output .= "SUMMARY STATISTICS\n";
    $output .= "Unique Medicines Used," . ($summary['unique_medicines'] ?? 0) . "\n";
    $output .= "Total Quantity Dispensed," . ($summary['total_quantity_dispensed'] ?? 0) . "\n";
    $output .= "Total Value,₱" . number_format($summary['total_value'] ?? 0, 2) . "\n";
    $output .= "Avg Dispensing Value,₱" . number_format($summary['avg_dispensing_value'] ?? 0, 2) . "\n";
    $output .= "\n";

    // Category Breakdown
    $output .= "MEDICINE USAGE BY CATEGORY\n";
    $output .= "Category,Prescriptions,Unique Medicines,Total Quantity,Usage %,Category Total\n";

    $category_query = "
        SELECT
            pi.category,
            COUNT(DISTINCT ppi.prescription_id) as prescriptions,
            COUNT(DISTINCT ppi.med_id) as unique_medicines,
            SUM(ppi.quantity_dispensed) as total_quantity,
            SUM(ppi.total_price) as category_total,
            ROUND(SUM(ppi.quantity_dispensed) / (SELECT SUM(quantity_dispensed) FROM pharmacy_prescription_items WHERE DATE(dispensed_date) BETWEEN '$from_date' AND '$to_date' AND quantity_dispensed > 0) * 100, 2) as category_percentage
        FROM pharmacy_prescription_items ppi
        JOIN pharmacy_inventory pi ON ppi.med_id = pi.med_id
        WHERE DATE(ppi.dispensed_date) BETWEEN '$from_date' AND '$to_date'
        AND ppi.quantity_dispensed > 0
        GROUP BY pi.category
        ORDER BY total_quantity DESC
    ";

    $result = $conn->query($category_query);

    while ($row = $result->fetch_assoc()) {
        $output .= csvEscape($row['category']) . ",";
        $output .= csvEscape($row['prescriptions']) . ",";
        $output .= csvEscape($row['unique_medicines']) . ",";
        $output .= csvEscape($row['total_quantity']) . ",";
        $output .= csvEscape($row['category_percentage'] . '%') . ",";
        $output .= csvEscape('₱' . number_format($row['category_total'], 2)) . "\n";
    }

    $output .= "\n\n";

    // Top medicines
    $output .= "TOP MEDICINES BY USAGE\n";
    $output .= "#,Medicine Name,Generic Name,Category,Total Dispensed,Usage %,Prescriptions,Unit Price,Total Revenue\n";

    $top_query = "
        SELECT
            pi.med_id,
            pi.med_name,
            pi.generic_name,
            pi.category,
            pi.unit_price,
            SUM(ppi.quantity_dispensed) as total_dispensed,
            COUNT(DISTINCT ppi.prescription_id) as prescription_count,
            SUM(ppi.total_price) as total_revenue,
            ROUND(SUM(ppi.quantity_dispensed) / (SELECT SUM(quantity_dispensed) FROM pharmacy_prescription_items WHERE DATE(dispensed_date) BETWEEN '$from_date' AND '$to_date' AND quantity_dispensed > 0) * 100, 2) as usage_percentage
        FROM pharmacy_prescription_items ppi
        JOIN pharmacy_inventory pi ON ppi.med_id = pi.med_id
        WHERE DATE(ppi.dispensed_date) BETWEEN '$from_date' AND '$to_date'
        AND ppi.quantity_dispensed > 0
        GROUP BY ppi.med_id
        ORDER BY total_dispensed DESC
        LIMIT 100
    ";

    $result = $conn->query($top_query);

    $count = 1;
    while ($row = $result->fetch_assoc()) {
        $output .= csvEscape($count++) . ",";
        $output .= csvEscape($row['med_name']) . ",";
        $output .= csvEscape($row['generic_name']) . ",";
        $output .= csvEscape($row['category']) . ",";
        $output .= csvEscape($row['total_dispensed']) . ",";
        $output .= csvEscape($row['usage_percentage'] . '%') . ",";
        $output .= csvEscape($row['prescription_count']) . ",";
        $output .= csvEscape('₱' . number_format($row['unit_price'], 2)) . ",";
        $output .= csvEscape('₱' . number_format($row['total_revenue'], 2)) . "\n";
    }

    return $output;
}

function csvEscape($value)
{
    if (strpos($value, ',') !== false || strpos($value, '"') !== false || strpos($value, "\n") !== false) {
        return '"' . str_replace('"', '""', $value) . '"';
    }
    return $value;
}
