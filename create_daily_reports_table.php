<?php
include 'C:/xampp/htdocs/HMS-CAPSTONE/SQL/config.php';

$sql = "
CREATE TABLE IF NOT EXISTS daily_medical_reports (
    id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id INT NOT NULL,
    employee_id INT NOT NULL,
    shift ENUM('Doctor', 'Shift 1', 'Shift 2', 'Shift 3') NOT NULL,
    report_date DATE NOT NULL,
    interventions TEXT,
    tasks_done TEXT,
    patient_status VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
";

if ($conn->query($sql) === TRUE) {
    echo "Table daily_medical_reports created successfully.\n";
} else {
    echo "Error creating table: " . $conn->error . "\n";
}
?>
