<?php
include __DIR__ . '../../../../SQL/config.php';


$sql = "
    SELECT rs.id AS rsID, r.resultID, r.patientID, r.status, r.resultDate, r.result,
           s.scheduleID, s.serviceName
    FROM dl_result_schedules rs
    JOIN dl_results r ON rs.resultID = r.resultID
    JOIN dl_schedule s ON rs.scheduleID = s.scheduleID
    ORDER BY r.resultDate DESC
";

$result = $conn->query($sql);

if ($result->num_rows > 0) {
    echo "<table border='1' cellpadding='8' cellspacing='0'>";
    echo "<tr>
            <th>Result Schedule ID</th>
            <th>Result ID</th>
            <th>Patient ID</th>
            <th>Status</th>
            <th>Result Date</th>
            <th>Result</th>
            <th>Schedule ID</th>
            <th>Service Name</th>
          </tr>";
    while ($row = $result->fetch_assoc()) {
        echo "<tr>
                <td>{$row['rsID']}</td>
                <td>{$row['resultID']}</td>
                <td>{$row['patientID']}</td>
                <td>{$row['status']}</td>
                <td>{$row['resultDate']}</td>
                <td>{$row['result']}</td>
                <td>{$row['scheduleID']}</td>
                <td>{$row['serviceName']}</td>
              </tr>";
    }
    echo "</table>";
} else {
    echo "No result schedules found.";
}
?>
