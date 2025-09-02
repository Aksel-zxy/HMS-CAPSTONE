<?php
// Prevent redeclaration
if (!defined("BASE_URL")) {
    define("BASE_URL", "/hms-capstone/");
}
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
$inactive = 1800;

if (isset($_SESSION['timeout'])) {
    $session_life = time() - $_SESSION['timeout'];
    if ($session_life > $inactive) {
        session_unset();
        session_destroy();
        echo "<script>
            alert('You have been logged out due to inactivity.');
            window.location.href = '" . BASE_URL . "backend/logout.php';
        </script>";
        exit();
    }
}
$_SESSION['timeout'] = time();
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "hmscapstone";
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>
