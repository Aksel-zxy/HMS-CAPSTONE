<?php
require '../../SQL/config.php';
require 'classes/prescription.php';

// Check if only one doctor exists
$sql = "SELECT employee_id FROM hr_employees WHERE profession = 'Doctor'";
$result = $conn->query($sql);

if ($result && $result->num_rows === 1) {
    // Only one doctor in DB, auto-assign
    $row = $result->fetch_assoc();
    $doctor_id = $row['employee_id'];
} else {
    // Multiple doctors or none — use posted value
    $doctor_id = $_POST['doctor_id'] ?? null;
}

$patient_id = $_POST['patient_id'] ?? null;
$status     = "Pending";

// Medicines data
$med_ids    = $_POST['med_id'] ?? [];
$dosages    = $_POST['dosage'] ?? [];
$quantities = $_POST['quantity'] ?? [];
$notesArray = $_POST['note'] ?? []; // array of notes per medicine

// Combine notes into a single string for pharmacy_prescription.note
$note = implode(", ", $notesArray);

// Build items array for prescription items table
$items = [];
foreach ($med_ids as $index => $med_id) {
    $items[] = [
        'med_id'   => $med_id,
        'dosage'   => $dosages[$index] ?? '',
        'quantity' => $quantities[$index] ?? 0
    ];
}

$prescription = new Prescription($conn);

try {
    $success = $prescription->addPrescription(
        $doctor_id,
        $patient_id,
        $note,   // now properly a string
        $status,
        $items
    );

    if ($success) {
        header("Location: pharmacy_add_prescription.php?success=1");
        exit;
    } else {
        header("Location: pharmacy_add_prescription.php?error=1");
        exit;
    }
} catch (mysqli_sql_exception $e) {
    header("Location: pharmacy_add_prescription.php?error=" . urlencode($e->getMessage()));
    exit;
}
