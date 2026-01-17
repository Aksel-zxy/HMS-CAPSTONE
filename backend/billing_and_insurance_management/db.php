<?php
$host = "127.0.0.1";
$port = "3306";
$dbname = "hmscapstone";
$username = "root";
$password = "";

// MySQLi connection
$conn = new mysqli($host, $username, $password, $dbname, $port);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$conn->set_charset("utf8mb4");
?>
