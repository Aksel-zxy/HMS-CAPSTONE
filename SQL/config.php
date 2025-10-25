<?php
if (!defined("BASE_URL")) {
    define("BASE_URL", getenv('APP_URL') ?: "/hms-capstone/");
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


// MySQL settings

$host     = getenv('DB_HOST') ?: "127.0.0.1";
$port     = getenv('DB_PORT') ?: "3306";
$dbname   = getenv('DB_NAME') ?: "hmscapstone";
$username = getenv('DB_USERNAME') ?: "root";
$password = getenv('DB_PASSWORD') ?: "";


// mysqli connection

$conn = new mysqli($host, $username, $password, $dbname, $port);
if ($conn->connect_error) {
    die("Connection failed (MySQLi): " . $conn->connect_error);
}


// PDO connection

try {
    $pdo = new PDO(
        "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4",
        $username,
        $password
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Connection failed (PDO): " . $e->getMessage());
}
