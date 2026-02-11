<?php
require '../../../SQL/config.php';

// Set JSON response header
header('Content-Type: application/json');

// Get JSON input from fetch
$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['applicant_id'], $input['ai_remarks'])) {
    echo json_encode(['error' => 'Missing applicant ID or AI remarks']);
    exit;
}

$applicantId = intval($input['applicant_id']);
$aiRemarks = $input['ai_remarks'];

// Update the hr_applicant table with AI remarks
$sql = "UPDATE hr_applicant 
        SET ai_remarks = ? 
        WHERE applicant_id = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("si", $aiRemarks, $applicantId);

if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['error' => 'Failed to save AI remarks']);
}

$stmt->close();
$conn->close();
