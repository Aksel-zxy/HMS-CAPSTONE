<?php
session_start();
require 'db.php'; // Database connection file

$message = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    $stmt = $pdo->prepare("SELECT * FROM vendors WHERE username = ?");
    $stmt->execute([$username]);
    $vendor = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($vendor && password_verify($password, $vendor['password'])) {
        // Registration number for messages
        $reg_number = $vendor['registration_number'] ?: 'N/A';

        if ($vendor['status'] === 'Pending') {
            $message = "⏳ Your application number: <strong>{$reg_number}</strong> is under review. Please wait 3–7 days for approval.";
        } elseif ($vendor['status'] === 'Rejected') {
            $registration_date = date("F j, Y", strtotime($vendor['created_at']));
            $reapply_date = date("F j, Y", strtotime($vendor['created_at'] . " +1 month"));
            $message = "❌ Your application number: <strong>{$reg_number}</strong> was declined on {$registration_date}. You may register again after {$reapply_date}.";
        } elseif ($vendor['status'] === 'Approved') {
            // Store session variables
            $_SESSION['vendor_id']            = $vendor['id'];
            $_SESSION['company_name']         = $vendor['company_name']; 
            $_SESSION['registration_number']  = $vendor['registration_number']; 

            // Redirect to dashboard
            header("Location: vendor_products.php");
            exit;
        }
    } else {
        $message = "⚠ Invalid username or password.";
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Vendor Login</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
</head>
<body class="bg-light">
<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-5">
            <div class="card shadow-sm">
                <div class="card-header text-center">
                    <h4>Vendor Login</h4>
                </div>
                <div class="card-body">
                    <?php if (!empty($message)): ?>
                        <div class="alert alert-info">
                            <?= $message ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="">
                        <div class="mb-3">
                            <label class="form-label">Username</label>
                            <input type="text" name="username" class="form-control" required autofocus>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Password</label>
                            <input type="password" name="password" class="form-control" required>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">Login</button>
                    </form>
                </div>
                <div class="card-footer text-center">
                    <small><a href="vendor_registration.php">Register as a Vendor</a></small>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
