<?php
require '../../SQL/config.php';
require_once 'classes/Dashboard.php';

// Check if year is passed via GET, default to current year
$year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');

$query = "
SELECT
    DATE_FORMAT(pay_period_start, '%Y-%m') AS month,
    SUM(net_pay) AS full_month,
    SUM(CASE WHEN DAY(pay_period_start) <= 15 THEN net_pay ELSE 0 END) AS first_half,
    SUM(CASE WHEN DAY(pay_period_start) > 15 THEN net_pay ELSE 0 END) AS second_half
FROM hr_payroll
WHERE YEAR(pay_period_start) = $year
GROUP BY DATE_FORMAT(pay_period_start, '%Y-%m')
ORDER BY DATE_FORMAT(pay_period_start, '%Y-%m');
";

$result = $conn->query($query);

$data = [];
while($row = $result->fetch_assoc()){
    $data[] = $row;
}

echo json_encode($data);
