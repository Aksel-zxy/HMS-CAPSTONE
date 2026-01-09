<?php
require '../SQL/config.php';
require_once 'assets/oop/off_otp.php';
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hospital Management System | Login Page</title>
    <link rel="shortcut icon" href="assets/image/favicon.ico" type="image/x-icon">
    <link rel="stylesheet" href="assets/CSS/bootstrap.min.css">
    <link rel="stylesheet" href="assets/CSS/login.css">
</head>
     <style>
        .remember-me {
            margin: 10px 0 15px;
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
            color: #333;
        }

        .remember-me input[type="checkbox"] {
            width: 16px;
            height: 16px;
            cursor: pointer;
        }

        .remember-me label {
            cursor: pointer;
        }
    </style>
<body>
    <div class="login-container">
        <div class="login-box">
            <a href="../index.php" class="logo">
                <img src="assets/image/logo-dark.png" alt="HMS Logo" style="height: 20px;">
            </a>
            <p class="subtext">Enter your username and password to access your panel.</p>

            <?php if ($login->getError()): ?>
                <div class="alert alert-danger"><?= $login->getError() ?></div>
            <?php endif; ?>

            <form method="POST" action="">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" placeholder="Enter your Username" required>

                <label for="password">Password</label>
                <input type="password" id="password" name="password" placeholder="Enter your password" required>

                <!-- ✅ Remember Me styled nicely -->
                <div class="remember-me">
                    <input type="checkbox" id="remember_me" name="remember_me" value="1">
                    <label for="remember_me">Remember this device</label>
                </div>

                <button type="submit">Log In</button>
            </form>

            <div class="forgot-password">
                <a href="assets/auth/forgot_password.php">Forgot your password?</a>
                <span> | </span>
                <a href="../index.php">Back to Homepage</a>
            </div>
        </div>
    </div>
    <script src="assets/Bootstrap/all.min.js"></script>
    <script src="assets/Bootstrap/bootstrap.bundle.min.js"></script>
    <script src="assets/Bootstrap/fontawesome.min.js"></script>
    <script src="assets/Bootstrap/jq.js"></script>
</body>

</html>