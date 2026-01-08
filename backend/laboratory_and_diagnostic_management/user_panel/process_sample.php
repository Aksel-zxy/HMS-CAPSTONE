<?php
$serviceName = $_GET['serviceName'] ?? '';

switch ($serviceName) {
    case "CBC":
    case "Complete Blood Count (CBC)":
        include __DIR__ . "/forms/form_cbc.php";
        break;

    case "X-Ray (Chest)":
        include __DIR__ . "/forms/form_xray.php";
        break;

    case "MRI Scan":
        include __DIR__ . "/forms/form_mri.php";
        break;

    case "CT Scan":
        include __DIR__ . "/forms/form_ct.php";
        break;

    default:
        echo "<p>No form available for this service.</p>";
}
?>
