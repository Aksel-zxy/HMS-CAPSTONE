<?php
include '../../../SQL/config.php';

if (isset($_POST['employee_id']) && isset($_POST['week_start'])) {
    $emp_id = $_POST['employee_id'];
    $week_start = $_POST['week_start'];

    $stmt = $conn->prepare("SELECT schedule_id FROM shift_scheduling WHERE employee_id = ? AND week_start = ? LIMIT 1");
    $stmt->bind_param("is", $emp_id, $week_start);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        echo "exists";
    } else {
        echo "available";
    }
    $stmt->close();
}
?>