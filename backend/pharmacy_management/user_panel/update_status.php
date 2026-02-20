<?php
include '../../../SQL/config.php';
require_once '../classes/medicine.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $med_id = $_POST['med_id'] ?? null;
    $new_status = $_POST['new_status'] ?? null;

    if ($med_id && $new_status) {
        try {
            $medicine = new Medicine($conn);
            $medicine->updateStatus($med_id, $new_status);
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid input']);
    }
}
