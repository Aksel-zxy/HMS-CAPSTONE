<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require __DIR__ . '/../../../SQL/config.php';
require __DIR__ . '/../../../vendor/autoload.php';
require __DIR__ . '/../oop/mailer_config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);

    $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();

    if ($user) {
        // Generate secure token
        $token = bin2hex(random_bytes(16));
        $expires = time() + 900; // 15 minutes

        $stmt = $conn->prepare("UPDATE users SET reset_token=?, reset_expires=? WHERE email=?");
        $stmt->bind_param("sis", $token, $expires, $email);
        $stmt->execute();

        // Send reset email
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
        $host = $_SERVER['HTTP_HOST']; // usually "localhost"
        $resetLink = $protocol . $host . "/backend/assets/auth/reset_password.php?token=" . $token;

        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host       = SMTP_HOST;
            $mail->SMTPAuth   = true;
            $mail->Username   = SMTP_USER;
            $mail->Password   = SMTP_PASS;
            $mail->SMTPSecure = 'tls';
            $mail->Port       = SMTP_PORT;

            $mail->setFrom('no-reply@hospital.com', 'BSIS-4101 Reset Password (HMS CAPSTONE)');
            $mail->addAddress($email);
            $mail->addReplyTo('no-reply@hospital.com', 'No Reply');

            $mail->isHTML(true);
            $mail->Subject = 'Password Reset Request';
            $mail->Body    = "<p>Click the link below to reset your password (valid for 15 minutes):</p>
                              <p><a href='$resetLink'>$resetLink</a></p>";

            $mail->send();
            $message = "Password reset link has been sent to your email.";
        } catch (Exception $e) {
            $message = "Error sending email: " . $mail->ErrorInfo;
        }
    } else {
        $message = "No account found with that email.";
    }
}
?>

<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hospital Management System | Forgot Password</title>
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
        <p class="subtext">Enter your registered email to reset your password.</p>

        <?php if (!empty($message)): ?>
            <div class="alert alert-info"><?= $message ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <label for="email">Email Address</label>
            <input 
                type="email" 
                id="email" 
                name="email" 
                placeholder="Enter your registered email" 
                required
            >

            <button type="submit">Send Reset Link</button>
        </form>

        <div class="forgot-password">
            <a href="../../login.php">Back to Login</a>
        </div>
    </div>
</div>

</body>

</html>