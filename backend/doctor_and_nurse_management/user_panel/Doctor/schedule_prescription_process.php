<?php
session_start();
include '../../../../SQL/config.php'; // adjust path to your config.php

// -------------------------------
// Check if doctor is logged in
// -------------------------------
if (!isset($_SESSION['employee_id']) || $_SESSION['profession'] !== 'Doctor') {
    header("Location: login.php");
    exit();
}

$doctor_id = $_SESSION['employee_id'];

// -------------------------------
// Get POST data
// -------------------------------
$patient_id = isset($_POST['patient_id']) ? intval($_POST['patient_id']) : 0;
$med_id = isset($_POST['med_id']) ? intval($_POST['med_id']) : 0;
$dosage = isset($_POST['dosage']) ? mysqli_real_escape_string($conn, $_POST['dosage']) : '';
$route = isset($_POST['route']) ? mysqli_real_escape_string($conn, $_POST['route']) : '';
$frequency = isset($_POST['frequency']) ? mysqli_real_escape_string($conn, $_POST['frequency']) : '';
$duration_days = isset($_POST['duration_days']) ? intval($_POST['duration_days']) : 0;
$start_date = isset($_POST['start_date']) ? mysqli_real_escape_string($conn, $_POST['start_date']) : '';
$special_instructions = isset($_POST['special_instructions']) ? mysqli_real_escape_string($conn, $_POST['special_instructions']) : '';

// -------------------------------
// Validate required fields
// -------------------------------
if ($patient_id == 0 || $med_id == 0 || empty($dosage) || empty($route) || empty($frequency) || $duration_days <= 0 || empty($start_date)) {
    $_SESSION['error'] = "Please fill in all required fields.";
    header("Location: " . $_SERVER['HTTP_REFERER']);
    exit();
}

// -------------------------------
// Calculate end_date from duration_days
// -------------------------------
$start_datetime = date('Y-m-d H:i:s', strtotime($start_date));
$end_datetime = date('Y-m-d H:i:s', strtotime($start_datetime . " + $duration_days days"));

// -------------------------------
// Insert into scheduled_medications
// -------------------------------
$sql = "INSERT INTO scheduled_medications 
        (patient_id, doctor_id, med_id, dosage, route, frequency, duration_days, start_date, end_date, special_instructions, billing_type, status) 
        VALUES 
        ('$patient_id', '$doctor_id', '$med_id', '$dosage', '$route', '$frequency', '$duration_days', '$start_datetime', '$end_datetime', '$special_instructions', 'bill_when_administered', 'pending')";

if (mysqli_query($conn, $sql)) {
    $_SESSION['success'] = "Medication schedule saved successfully.";
    header("Location: " . $_SERVER['HTTP_REFERER']);
    exit();
} else {
    $_SESSION['error'] = "Database error: " . mysqli_error($conn);
    header("Location: " . $_SERVER['HTTP_REFERER']);
    exit();
}
