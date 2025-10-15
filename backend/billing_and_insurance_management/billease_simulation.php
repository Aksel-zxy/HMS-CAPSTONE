<?php
include '../../SQL/config.php';
if (session_status() === PHP_SESSION_NONE) session_start();

// Get data from URL
$receipt_id = isset($_GET['receipt_id']) ? intval($_GET['receipt_id']) : 0;
$order_id   = $_GET['order_id'] ?? '';
$amount     = isset($_GET['amount']) ? floatval($_GET['amount']) : 0;

// Handle payment simulation
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'success' && $receipt_id > 0) {
        // Update patient_receipt status to Paid and record transaction info
        $stmt = $conn->prepare("UPDATE patient_receipt SET status='Paid', transaction_id=? WHERE receipt_id=?");
        $stmt->bind_param("si", $order_id, $receipt_id);
        $stmt->execute();

        // Redirect to success page
        header("Location: payment_success.php?receipt_id=$receipt_id&status=success");
        exit;

    } elseif ($action === 'failed' && $receipt_id > 0) {
        // Update patient_receipt status to Pending or Failed (choose Pending for simulation)
        $stmt = $conn->prepare("UPDATE patient_receipt SET status='Pending' WHERE receipt_id=?");
        $stmt->bind_param("i", $receipt_id);
        $stmt->execute();

        // Redirect to failure page
        header("Location: payment_success.php?receipt_id=$receipt_id&status=failed");
        exit;
    }
}
?>

<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>BillEase Payment - Test Mode</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
<style>
    body {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        min-height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    .payment-card {
        max-width: 500px;
        background: white;
        border-radius: 15px;
        box-shadow: 0 10px 40px rgba(0,0,0,0.2);
    }
    .billease-logo {
        background: #1a73e8;
        color: white;
        padding: 20px;
        border-radius: 15px 15px 0 0;
    }
</style>
</head>
<body>

<div class="payment-card">
    <div class="billease-logo text-center">
        <h3 class="mb-0"><i class="bi bi-credit-card-2-front me-2"></i>BillEase</h3>
        <small>Test Payment Gateway</small>
    </div>
    
    <div class="p-4">
        <div class="alert alert-warning mb-4">
            <i class="bi bi-info-circle me-2"></i>
            <strong>Test Mode:</strong> This is a simulated payment page for testing purposes.
        </div>

        <div class="mb-4">
            <h5>Payment Details</h5>
            <div class="border rounded p-3 bg-light">
                <div class="d-flex justify-content-between mb-2">
                    <span>Order ID:</span>
                    <strong><?= htmlspecialchars($order_id) ?></strong>
                </div>
                <div class="d-flex justify-content-between">
                    <span>Amount:</span>
                    <strong class="text-primary">₱<?= number_format($amount, 2) ?></strong>
                </div>
            </div>
        </div>

        <div class="mb-4">
            <h6>Select Payment Outcome:</h6>
            <p class="text-muted small">In a real integration, the customer would complete payment here.</p>
        </div>

        <form method="POST" class="d-grid gap-2">
            <button type="submit" name="action" value="success" class="btn btn-success btn-lg">
                <i class="bi bi-check-circle me-2"></i>
                Simulate Successful Payment
            </button>
            
            <button type="submit" name="action" value="failed" class="btn btn-danger">
                <i class="bi bi-x-circle me-2"></i>
                Simulate Failed Payment
            </button>
            
            <a href="patient_billing.php" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left me-2"></i>
                Cancel Payment
            </a>
        </form>
    </div>
    
    <div class="text-center p-3 border-top text-muted small">
        <i class="bi bi-shield-check me-1"></i>
        Secure test payment simulation
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
