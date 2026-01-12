<?php
require '../../../SQL/config.php';
require 'classes/FingerprintManager.php';

$manager = new FingerprintManager($conn);
?>

<h2>Fingerprint Attendance Scan</h2>

<video id="video" width="300" height="220" autoplay></video>
<canvas id="canvas" style="display:none;"></canvas>
<form method="POST" action="process_scan.php">
    <input type="hidden" name="fingerprint" id="fingerprint">
    <button type="button" onclick="capture()">Scan Fingerprint</button>
    <button type="submit">Submit</button>
</form>

<script>
navigator.mediaDevices.getUserMedia({ video: true })
.then(stream => document.getElementById("video").srcObject = stream);

function capture() {
    const canvas = document.getElementById("canvas");
    const video = document.getElementById("video");
    canvas.getContext('2d').drawImage(video, 0, 0, canvas.width, canvas.height);
    document.getElementById("fingerprint").value = canvas.toDataURL("image/png");
}
</script>
