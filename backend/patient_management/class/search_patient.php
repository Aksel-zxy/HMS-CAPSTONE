<?php
include __DIR__ . '/../../../SQL/config.php';

header('Content-Type: application/json');

$query = isset($_GET['q']) ? trim($_GET['q']) : '';

if ($query === '') {
    echo json_encode([]);
    exit();
}

$sql = "SELECT patient_id, CONCAT(fname, ' ', lname) AS name 
        FROM patientinfo
        WHERE fname LIKE ? OR lname LIKE ? 
        LIMIT 10";

$stmt = $conn->prepare($sql);
$likeQuery = "%$query%";
$stmt->bind_param("ss", $likeQuery, $likeQuery);
$stmt->execute();
$result = $stmt->get_result();

$patients = [];
while ($row = $result->fetch_assoc()) {
    $patients[] = [
        'id' => $row['patient_id'],
        'name' => $row['name']
    ];
}

echo json_encode($patients);
?>