<?php
header('Content-Type: application/json');
require '../../../SQL/config.php';

$data = json_decode(file_get_contents('php://input'), true);

if(isset($data['payroll_action']) && $data['payroll_action'] === 'mark_paid') {

    $payrollId = $data['payroll_id'] ?? null;

    if (!$payrollId) {
        echo json_encode(['success' => false, 'message' => 'Payroll ID missing']);
        exit;
    }

    try {

        $stmt = $conn->prepare("UPDATE hr_payroll SET status='Paid' WHERE payroll_id=?");
        $stmt->bind_param("i", $payrollId);

        if ($stmt->execute()) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Database update failed']);
        }

        exit;

    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
}

echo json_encode(['success' => false, 'message' => 'Invalid request']);
exit;