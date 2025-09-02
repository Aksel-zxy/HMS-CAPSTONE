<?php
require __DIR__ . '/../../../SQL/config.php';

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

    // ✅ Promote pending login to full login
    $_SESSION['user_id'] = $_SESSION['pending_user_id'];
    $_SESSION['username'] = $_SESSION['pending_username'];
    $_SESSION['role'] = $_SESSION['pending_role'];

    // ✅ Set role flag (needed for dashboard checks)
    switch ($_SESSION['role']) {
        case '0': $_SESSION['superadmin'] = true; break;
        case '1': $_SESSION['hr'] = true; break;
        case '2': $_SESSION['doctor'] = true; break;
        case '3': $_SESSION['patient'] = true; break;
        case '4': $_SESSION['billing'] = true; break;
        case '5': $_SESSION['pharmacy'] = true; break;
        case '6': $_SESSION['labtech'] = true; break;
        case '7': $_SESSION['inventory'] = true; break;
        case '8': $_SESSION['report'] = true; break;
    }

    // cleanup only OTP-related vars
    unset($_SESSION['otp']);
    unset($_SESSION['otp_expiry']);
    unset($_SESSION['pending_user_id']);
    unset($_SESSION['pending_username']);
    unset($_SESSION['pending_role']);

    // ✅ Redirect to role dashboard
    switch ($_SESSION['role']) {
        case '0': header("Location: ../../superadmin_dashboard.php"); break;
        case '1': header("Location: ../../HR Management/admin_dashboard.php"); break;
        case '2': header("Location: ../../Doctor and Nurse Management/doctor_dashboard.php"); break;
        case '3': header("Location: ../../Patient Management/patient_dashboard.php"); break;
        case '4': header("Location: ../../Billing and Insurance Management/billing_dashboard.php"); break;
        case '5': header("Location: ../../Pharmacy Management/pharmacy_dashboard.php"); break;
        case '6': header("Location: ../../Laboratory and Diagnostic Management/labtech_dashboard.php"); break;
        case '7': header("Location: ../../Inventory and Supply Chain Management/inventory_dashboard.php"); break;
        case '8': header("Location: ../../Report and Analytics/report_dashboard.php"); break;
        default: header("Location: ../../login.php?error=Invalid role."); break;
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
    <script src="assets/Bootstrap/bootstrap.bundle.min.js"></script>
</body>

</html>