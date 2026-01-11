<?php
require '../../../SQL/config.php';
require 'classes/FingerprintManager.php';

$manager = new FingerprintManager($conn);

$employee_id = $_POST['employee_id'];
$fingerprint = $_POST['fingerprint'];

if ($manager->enroll($employee_id, $fingerprint)) {
    echo "<script>alert('Fingerprint enrolled successfully for Employee ID: $employee_id');window.location.href='enroll_fingerprint.php';</script>";

} else {
    echo "<script>alert('Enrollment failed!');</script>";
}

