<?php
include 'C:/xampp/htdocs/HMS-CAPSTONE/SQL/config.php';
$result = $conn->query("DESCRIBE p_beds");
while ($row = $result->fetch_assoc()) {
    echo $row['Field'] . " - " . $row['Type'] . "\n";
}
?>
