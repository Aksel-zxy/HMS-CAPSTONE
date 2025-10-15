<?php
// mock_billease_checkout.php
// This simulates an offline BillEase checkout for local testing.

// Get the fake transaction/order ID from URL
$order = $_GET['order'] ?? 'UNKNOWN';

// Generate a fake success transaction ID (optional)
$transactionId = 'OFFLINE-' . strtoupper(bin2hex(random_bytes(4)));

// Optional: You can auto-redirect back to billing after 5 seconds
$redirectUrl = 'billing_records.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Mock BillEase Checkout</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body {
      background: #f8f9fa;
      display: flex;
      align-items: center;
      justify-content: center;
      height: 100vh;
      flex-direction: column;
      font-family: 'Segoe UI', sans-serif;
    }
    .mock-box {
      background: #fff;
      padding: 30px 40px;
      border-radius: 12px;
      box-shadow: 0 4px 10px rgba(0,0,0,0.1);
      text-align: center;
      max-width: 480px;
    }
    .mock-box h2 {
      color: #198754;
      font-weight: 600;
      margin-bottom: 15px;
    }
    .mock-box p {
      color: #555;
      margin-bottom: 20px;
    }
    .btn-success {
      background-color: #198754;
      border: none;
    }
  </style>
</head>
<body>

  <div class="mock-box">
    <h2>🧩 Mock BillEase Checkout</h2>
    <p>Simulating BillEase checkout for <strong><?php echo htmlspecialchars($order); ?></strong></p>
    <p>This is a <b>local offline test page</b> since your XAMPP can’t connect to the real BillEase sandbox.</p>

    <div class="alert alert-success text-start">
      <strong>Transaction ID:</strong> <?php echo $transactionId; ?><br>
      <strong>Status:</strong> Payment simulated successfully.
    </div>

    <a href="<?php echo $redirectUrl; ?>" class="btn btn-success mt-3">
      ✅ Return to Billing Records
    </a>

    <p class="text-muted mt-3"><small>You’ll be automatically redirected in 5 seconds...</small></p>
  </div>

  <script>
    setTimeout(() => {
      window.location.href = "<?php echo $redirectUrl; ?>";
    }, 5000);
  </script>

</body>
</html>
