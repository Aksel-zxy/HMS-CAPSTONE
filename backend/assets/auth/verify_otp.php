<?php
require __DIR__ . '/../../../SQL/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['otp'])) {
    header("Location: login.php");
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $enteredOtp = trim($_POST['otp']);
    if (empty($enteredOtp)) {
        $error = "Please enter the OTP.";
    } elseif (time() > $_SESSION['otp_expiry']) {
        $error = "OTP expired. Please log in again.";
        session_unset();
        session_destroy();
        header("Refresh:2; url=login.php");
        exit;
    } elseif ($enteredOtp == $_SESSION['otp']) {
        $_SESSION['otp_verified'] = true;
        $_SESSION['user_id'] = $_SESSION['pending_user_id'];
        $_SESSION['username'] = $_SESSION['pending_username'];
        $_SESSION['role'] = $_SESSION['pending_role'];
        switch ($_SESSION['role']) {
            case '0':
                $_SESSION['superadmin'] = true;
                break;
            case '1':
                $_SESSION['hr'] = true;
                break;
            case '2':
                $_SESSION['doctor'] = true;
                break;
            case '3':
                $_SESSION['patient'] = true;
                break;
            case '4':
                $_SESSION['billing'] = true;
                break;
            case '5':
                $_SESSION['pharmacy'] = true;
                break;
            case '6':
                $_SESSION['labtech'] = true;
                break;
            case '7':
                $_SESSION['inventory'] = true;
                break;
            case '8':
                $_SESSION['report'] = true;
                break;
        }
        if (!empty($_SESSION['remember_me']) && $_SESSION['remember_me'] === true) {
            $token = bin2hex(random_bytes(32));
            setcookie(
                "remember_token",
                $token,
                time() + (86400 * 7),
                "/",
                "",
                false,
                true
            );
            $stmt = $conn->prepare("UPDATE users SET remember_token=? WHERE user_id=?");
            $stmt->bind_param("si", $token, $_SESSION['user_id']);
            $stmt->execute();
        }
        unset(
            $_SESSION['otp'],
            $_SESSION['otp_expiry'],
            $_SESSION['pending_user_id'],
            $_SESSION['pending_username'],
            $_SESSION['pending_role']
        );
        switch ($_SESSION['role']) {
            case '0':
                header("Location: " . BASE_URL . "backend/superadmin_dashboard.php");
                break;
            case '1':
                header("Location: " . BASE_URL . "backend/hr_management/admin_dashboard.php");
                break;
            case '2':
                header("Location: " . BASE_URL . "backend/doctor_and_nurse_management/doctor_dashboard.php");
                break;
            case '3':
                header("Location: " . BASE_URL . "backend/patient_management/patient_dashboard.php");
                break;
            case '4':
                header("Location: " . BASE_URL . "backend/billing_and_insurance_management/billing_dashboard.php");
                break;
            case '5':
                header("Location: " . BASE_URL . "backend/pharmacy_management/pharmacy_dashboard.php");
                break;
            case '6':
                header("Location: " . BASE_URL . "backend/laboratory_and_diagnostic_management/labtech_dashboard.php");
                break;
            case '7':
                header("Location: " . BASE_URL . "backend/inventory_and_supply_chain_management/inventory_dashboard.php");
                break;
            case '8':
                header("Location: " . BASE_URL . "backend/report_and_analytics/report_dashboard.php");
                break;
            default:
                header("Location: " . BASE_URL . "backend/login.php?error=Invalid role.");
                break;
        }
        exit;
    } else {
        $error = "Invalid OTP. Try again.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify OTP</title>
    <link rel="shortcut icon" href="../image/favicon.ico" type="image/x-icon">
    <link rel="stylesheet" href="../CSS/bootstrap.min.css">
    <link rel="stylesheet" href="../CSS/login.css">
</head>

<body>
    <div class="login-container">
        <div class="login-box">
            <h2>OTP Verification</h2>
            <p class="subtext">Enter the OTP sent to your email.</p>

            <?php if (!empty($error)): ?>
                <div class="alert alert-danger"><?= $error ?></div>
            <?php endif; ?>

            <form method="POST" action="">
                <label for="otp">One-Time Password</label>
                <input type="text" id="otp" name="otp" placeholder="Enter 6-digit OTP" required>
                <button type="submit">Verify</button>
            </form>

            <div class="forgot-password">
                <a href="../../login.php">Back to Login</a>
            </div>
        </div>
    </div>
    <script src="../Bootstrap/bootstrap.bundle.min.js"></script>
</body>

</html>