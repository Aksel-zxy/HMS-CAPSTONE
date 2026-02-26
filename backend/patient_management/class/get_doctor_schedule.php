<?php
include '../../../SQL/config.php';
require_once 'caller.php';

header('Content-Type: application/json');

if (isset($_GET['doctor_id'])) {

    $doctor_id = intval($_GET['doctor_id']);

    $callerObj = new Caller($conn);
    $schedule = $callerObj->getDoctorSchedule($doctor_id);

    echo json_encode($schedule ?: []);

} else {
    echo json_encode([]);
}
exit;
?>