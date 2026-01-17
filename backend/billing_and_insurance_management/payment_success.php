<?php
include '../../SQL/config.php';
if (session_status() === PHP_SESSION_NONE) session_start();

// Get parameters
$status     = $_GET['status'] ?? 'unknown';
$receipt_id = isset($_GET['receipt_id']) ? intval($_GET['receipt_id']) : 0;

// Fetch receipt info for display
$receipt = null;
if ($receipt_id > 0) {
    $stmt = $conn->prepare("SELECT * FROM patient_receipt WHERE receipt_id=?");
    $stmt->bind_param("i", $receipt_id);
    $stmt->execute();
    $receipt = $stmt->get_result()->fetch_assoc();
}
?>

<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Payment Result</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
<style>
    body {
        background: #f8f9fa;
        min-height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    .result-card {
        max-width: 500px;
        background: white;
        border-radius: 15px;
        box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        text-align: center;
        padding: 40px;
    }
    .icon-circle {
        width: 80px;
        height: 80px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 20px;
        font-size: 40px;
    }
    .success-icon {
        background: #d4edda;
        color: #28a745;
    }
    .failed-icon {
        background: #f8d7da;
        color: #dc3545;
    }
</style>
</head>
<body>

<div class="result-card">
    <?php if ($status === 'success'): ?>
        <div class="icon-circle success-icon">
            <i class="bi bi-check-circle-fill"></i>
        </div>
        <h2 class="text-success mb-3">Payment Successful!</h2>
        <p class="text-muted mb-4">
            Your payment has been processed successfully.
        </p>
        <div class="border rounded p-3 mb-4 bg-light">
            <small class="text-muted">Receipt ID</small><br>
            <strong><?= htmlspecialchars($receipt['receipt_id'] ?? 'N/A') ?></strong>
        </div>
        <div class="alert alert-success">
            <i class="bi bi-info-circle me-2"></i>
            Payment status has been updated in the system.
        </div>
    <?php elseif ($status === 'failed'): ?>
        <div class="icon-circle failed-icon">
            <i class="bi bi-x-circle-fill"></i>
        </div>
        <h2 class="text-danger mb-3">Payment Failed</h2>
        <p class="text-muted mb-4">
            Unfortunately, your payment could not be processed.
        </p>
        <div class="alert alert-warning">
            <strong>Possible reasons:</strong>
            <ul class="mb-0 mt-2 text-start small">
                <li>Insufficient funds</li>
                <li>Card declined</li>
                <li>Network timeout</li>
            </ul>
        </div>
    <?php else: ?>
        <div class="icon-circle" style="background: #e2e3e5; color: #6c757d;">
            <i class="bi bi-question-circle-fill"></i>
        </div>
        <h2 class="text-muted mb-3">Unknown Status</h2>
        <p>Unable to determine payment status.</p>
    <?php endif; ?>

    <div class="d-grid gap-2 mt-4">
        <?php if ($status === 'success'): ?>
            <a href="print_receipt.php?receipt_id=<?= urlencode($receipt['receipt_id'] ?? 0) ?>" class="btn btn-primary">
                <i class="bi bi-receipt me-2"></i>View Receipt
            </a>
        <?php else: ?>
            <a href="billing_summary.php?patient_id=<?= $_SESSION['patient_id'] ?? 0 ?>" class="btn btn-primary">
                <i class="bi bi-arrow-clockwise me-2"></i>Try Again
            </a>
        <?php endif; ?>
        
        <a href="../dashboard.php" class="btn btn-outline-secondary">
            <i class="bi bi-house me-2"></i>Return to Dashboard
        </a>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
