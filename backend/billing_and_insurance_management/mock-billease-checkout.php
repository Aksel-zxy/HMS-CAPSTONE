<?php
$order = $_GET['order'] ?? 'UNKNOWN';
echo "<h2>🧩 Mock BillEase Checkout</h2>";
echo "<p>Simulating BillEase checkout for <b>$order</b></p>";
echo "<p>This page appears because your local XAMPP can’t reach the real BillEase sandbox.</p>";
echo "<a href='billing_records.php' style='padding:10px 20px;background:#198754;color:white;text-decoration:none;border-radius:5px;'>Return to Billing Records</a>";
?>
