<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start(); // Only start the session if it hasn't been started already
}
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "hmscapstone";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
} else {
    // echo "Connected successfully";
}
