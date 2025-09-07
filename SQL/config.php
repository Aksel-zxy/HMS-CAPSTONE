<?php
// Prevent redeclaration
if (!defined("BASE_URL")) {
    define("BASE_URL", "/hms-capstone/");
}

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// 1800 for 30mins
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

// -----------------------------
// MySQL settings
// -----------------------------
$host     = "127.0.0.1";   // use IP instead of "localhost"
$port     = "3307";        // adjust for your setup
$dbname   = "hmscapstone"; // or hmscapstone1 for PDO
$username = "root";
$password = "";

$conn = new mysqli($host, $username, $password, $dbname, $port);
if ($conn->connect_error) {
    die("Connection failed (MySQLi): " . $conn->connect_error);
}

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
?>