<?php
require '../../../SQL/config.php';
require 'classes/FingerprintManager.php';

$manager = new FingerprintManager($conn);
$fingerprint = $_POST['fingerprint'];

$employee_id = $manager->scan($fingerprint);

if($employee_id) {
    echo "Attendance recorded for Employee ID: $employee_id";
} else {
    echo "Fingerprint not recognized.";
}
