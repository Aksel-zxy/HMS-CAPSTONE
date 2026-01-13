<?php
require '../../../SQL/config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $employee_id = $_POST['employee_id'] ?? null;
    $leave_type = $_POST['leave_type'] ?? null;
    $year = $_POST['year'] ?? date('Y');

    if (!$employee_id || !$leave_type) {
        echo json_encode(['success' => false]);
        exit;
    }

    $stmt = $conn->prepare("
        SELECT allocated_days, used_days
        FROM hr_leave_credits
        WHERE employee_id = ? AND leave_type = ?
    ");
    $stmt->bind_param("is", $employee_id, $leave_type);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();

    if ($row) {
        $remaining_days = max(
            0,
            (float)$row['allocated_days'] - (float)$row['used_days']
        );

        echo json_encode([
            'success' => true,
            'remaining_days' => $remaining_days
        ]);
    } else {
        echo json_encode([
            'success' => true,
            'remaining_days' => 0
        ]);
    }
}
?>
