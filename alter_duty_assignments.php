<?php
include 'C:/xampp/htdocs/HMS-CAPSTONE/SQL/config.php';

$sql = "
ALTER TABLE duty_assignments 
ADD COLUMN patient_id INT NULL AFTER doctor_id,
ADD COLUMN shift1_nurse_id INT NULL AFTER nurse_assistant,
ADD COLUMN shift2_nurse_id INT NULL AFTER shift1_nurse_id,
ADD COLUMN shift3_nurse_id INT NULL AFTER shift2_nurse_id;
";

if ($conn->query($sql) === TRUE) {
    echo "Table duty_assignments altered successfully.";
} else {
    echo "Error altering table: " . $conn->error;
}
?>
