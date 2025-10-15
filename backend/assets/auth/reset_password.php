<?php
require __DIR__ . '/../../../SQL/config.php';

if (!isset($_GET['token'])) {
    die("Invalid request.");
}

$token = $_GET['token'];
$now = time();

// Fetch user with valid token
$stmt = $conn->prepare("SELECT * FROM users WHERE reset_token=? AND reset_expires > ?");
$stmt->bind_param("si", $token, $now);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if (!$user) {
    die("Invalid or expired token.");
}

$message = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'];
    $confirmPassword = $_POST['confirm_password'];

    if ($password !== $confirmPassword) {
        $message = "Passwords do not match. Please try again.";
    } else {
        $newPassword = password_hash($password, PASSWORD_DEFAULT);

        // ✅ bind_param type fixed: user_id is integer
        $stmt = $conn->prepare("UPDATE users SET password=?, reset_token=NULL, reset_expires=NULL WHERE user_id=?");
        if (!$stmt) {
            die("Prepare failed: " . $conn->error);
        }

        $stmt->bind_param("si", $newPassword, $user['user_id']);
        $stmt->execute();

        // ✅ check if any rows were updated
        if ($stmt->affected_rows > 0) {
            echo "
            <!DOCTYPE html>
            <html lang='en'>
            <head>
                <meta charset='UTF-8'>
                <meta name='viewport' content='width=device-width, initial-scale=1.0'>
                <title>Password Reset Successful</title>
                <link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css' rel='stylesheet'>
                <style>
                    body { background: #f0f4f8; font-family: Arial, sans-serif; }
                    .success-container {
                        max-width: 500px; margin: 100px auto; background: #fff;
                        padding: 30px; border-radius: 12px;
                        box-shadow: 0 4px 12px rgba(0,0,0,0.1); text-align: center;
                    }
                    .success-container img { height: 40px; margin-bottom: 15px; }
                    .success-icon { font-size: 60px; color: #198754; margin-bottom: 15px; }
                    .btn-login {
                        background: #198754; border: none; padding: 10px 20px;
                        font-size: 16px; border-radius: 6px; color: #fff;
                        text-decoration: none;
                    }
                    .btn-login:hover { background: #146c43; }
                </style>
            </head>
            <body>
                <div class='success-container'>
                    <img src='../image/logo-dark.png' alt='HMS Logo'>
                    <div class='success-icon'>✅</div>
                    <h2>Password Reset Successful</h2>
                    <p>Your password has been updated. You can now log in with your new credentials.</p>
                    <a href='../../login.php' class='btn-login'>Go to Login</a>
                </div>
            </body>
            </html>";
            exit;
        } else {
            $message = "Failed to update password. Please try again.";
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hospital Management System | Reset Password</title>
    <link rel="shortcut icon" href="../image/favicon.ico" type="image/x-icon">
    <link rel="stylesheet" href="../CSS/bootstrap.min.css">
    <link rel="stylesheet" href="../CSS/login.css">
</head>
<body>
    <div class="login-container">
        <div class="login-box">
            <a href="#" class="logo">
                <img src="../image/logo-dark.png" alt="HMS Logo" style="height: 20px;">
            </a>
            <p class="subtext">Enter your new password to reset your account.</p>

            <?php if (!empty($message)): ?>
                <div class="alert alert-warning text-center"><?= htmlspecialchars($message) ?></div>
            <?php endif; ?>

            <form method="POST" action="">
                <label for="password">New Password</label>
                <input type="password" id="password" name="password" placeholder="Enter new password" required>

                <label for="confirm_password">Confirm Password</label>
                <input type="password" id="confirm_password" name="confirm_password" placeholder="Confirm new password" required>

                <button type="submit">Reset Password</button>
            </form>

            <div class="forgot-password">
                <a href="../../login.php">Back to Login</a>
            </div>
        </div>
    </div>
</body>
</html>
