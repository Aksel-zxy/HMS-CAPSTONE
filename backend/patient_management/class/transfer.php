<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
include __DIR__ . '/../../../SQL/config.php';

if (!isset($conn)) {
    header("Location: ../bedding.php?status=error&message=" . urlencode("DB connection missing"));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../bedding.php?status=error&message=" . urlencode("Invalid request method"));
    exit;
}

$patient_id = (int)($_POST['patient_id'] ?? 0);
$bed_id     = (int)($_POST['bed_id'] ?? 0);
$reason     = $_POST['reason'] ?? null;

if (!$patient_id || !$bed_id || !$reason) {
    header("Location: ../bedding.php?status=error&message=" . urlencode("Missing required data"));
    exit;
}

$conn->begin_transaction();

try {
    $stmt = $conn->prepare("SELECT assignment_id, bed_id FROM p_bed_assignments WHERE patient_id = ? AND released_date IS NULL LIMIT 1");
    $stmt->bind_param("i", $patient_id);
    $stmt->execute();
    $stmt->bind_result($assignment_id, $old_bed_id);
    $stmt->fetch();
    $stmt->close();

    if (!$assignment_id) {
        throw new Exception("No active bed assignment found");
    }

    $stmt = $conn->prepare("UPDATE p_bed_assignments SET bed_id = ? WHERE assignment_id = ?");
    $stmt->bind_param("ii", $bed_id, $assignment_id);
    if (!$stmt->execute()) throw new Exception("Failed to update bed assignment");
    $stmt->close();

    if ($old_bed_id != $bed_id) {
        $stmt = $conn->prepare("UPDATE p_beds SET status = 'Available' WHERE bed_id = ?");
        $stmt->bind_param("i", $old_bed_id);
        $stmt->execute();
        $stmt->close();

        $stmt = $conn->prepare("UPDATE p_beds SET status = 'Occupied', notes = ? WHERE bed_id = ?");
        $stmt->bind_param("si", $reason, $bed_id);
        $stmt->execute();
        $stmt->close();
    }

    $conn->commit();
    header("Location: ../bedding.php?status=1&message=" . urlencode("Patient bed updated successfully"));
    exit();

} catch (Exception $e) {
    $conn->rollback();
    header("Location: ../bedding.php?status=0&message=" . urlencode($e->getMessage()));
    exit();
}

$conn->close();