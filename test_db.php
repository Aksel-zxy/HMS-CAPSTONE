<?php
include 'C:/xampp/htdocs/HMS-CAPSTONE/SQL/config.php';
$result = $conn->query("DESCRIBE duty_assignments");
while ($row = $result->fetch_assoc()) {
    echo $row['Field'] . " - " . $row['Type'] . "\n";
}
echo "\n====\n";
$result2 = $conn->query("DESCRIBE patientinfo");
if($result2) {
    while ($row = $result2->fetch_assoc()) {
        echo $row['Field'] . " - " . $row['Type'] . "\n";
    }
} else {
    echo "No patientinfo table found.\n";
}
?>
