<?php
session_start();
session_unset();
session_destroy();
?>
<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <title>Logging Out</title>
    <meta http-equiv="refresh" content="3;url=login.php">
    <link rel="stylesheet" href="assets/CSS/logout.css">
    <link rel="stylesheet" href="assets/CSS/bootstrap.min.css">
</head>

<body>
    <div class="login-container">
        <div class="login-box">
            <a href="../index.php" class="logo">
                <img src="assets/image/logo-dark.png" alt="HMS Logo" style="height: 20px;">
            </a>
            <div class="text-center">
                <div class="mt-4">
                    <div class="logout-checkmark">
                        <svg version="1.1" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 130.2 130.2">
                            <circle class="path circle" fill="none" stroke="#4bd396" stroke-width="6" stroke-miterlimit="10" cx="65.1" cy="65.1" r="62.1" />
                            <polyline class="path check" fill="none" stroke="#4bd396" stroke-width="6" stroke-linecap="round" stroke-miterlimit="10" points="100.2,40.2 51.5,88.8 29.8,67.5 " />
                        </svg>
                    </div>
                </div>

                <h3>See you again !</h3>

                <p class="text-muted font-13"> You are now successfully sign out. </p>
            </div>
        </div>

    </div>
</body>

</html>