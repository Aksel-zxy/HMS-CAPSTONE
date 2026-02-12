<?php
session_start();
include '../../SQL/config.php';

if (!isset($_SESSION['vendor_id'])) { header("Location: vlogin.php"); exit; }

$id = $_GET['id'];

$stmt = $pdo->prepare("DELETE FROM vendor_products WHERE id=? AND vendor_id=?");
$stmt->execute([$id, $_SESSION['vendor_id']]);

header("Location: vendordashboard.php");
exit;
