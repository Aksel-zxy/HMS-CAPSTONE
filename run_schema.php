<?php
include 'C:/xampp/htdocs/HMS-CAPSTONE/SQL/config.php';

// Prepare a multi-query insert for dummy data
$sql = "
INSERT INTO daily_medical_reports (patient_id, employee_id, report_date, shift, interventions, tasks_done, patient_status) VALUES
(1, 6, CURRENT_DATE(), 'Shift 1', 'Administered scheduled antibiotics (IV).\nMonitored post-op vitals.', 'BP checked hourly.\nIncision site cleaned.', 'Stable but experiencing mild discomfort.'),
(1, 5, CURRENT_DATE(), 'Doctor', 'Reviewed lab results from morning draw.\nAdjusted pain management plan.', 'Consulted with patient family.\nOrdered new bloodwork.', 'Improving. Fever has subsided.'),
(2, 6, CURRENT_DATE(), 'Shift 2', 'Assisted with mobility exercises.\nAdministered evening medication.', 'Catheter care.\nReplaced IV fluid bag.', 'Patient is alert and cooperative.');
";

if ($conn->multi_query($sql)) {
    echo "Sample reports inserted successfully!\n";
} else {
    echo "Error inserting sample reports: " . $conn->error . "\n";
}

$conn->close();
?>
