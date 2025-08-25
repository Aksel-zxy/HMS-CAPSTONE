<?php
require '../SQL/config.php';
require_once 'assets/oop/auth.php';
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
                <input type="text" id="username" name="username" placeholder="Enter your Username">

                <label for="password">Password</label>
                <input type="password" id="password" name="password" placeholder="Enter your password">

                <button type="submit">Log In</button>
            </form>
            <div class="forgot-password">
                <a href="#">Forgot your password?</a>
            </div>
        </div>
    </div>
    <script src="assets/Bootstrap/all.min.js"></script>
    <script src="assets/Bootstrap/bootstrap.bundle.min.js"></script>
    <script src="assets/Bootstrap/fontawesome.min.js"></script>
    <script src="assets/Bootstrap/jq.js"></script>
</body>

</html>