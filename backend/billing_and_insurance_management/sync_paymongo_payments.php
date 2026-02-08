<?php
include '../../SQL/config.php';
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/vendor/autoload.php';
use GuzzleHttp\Client;

define('PAYMONGO_SECRET_KEY', 'sk_test_akT1ZW6za7m6FC9S9VqYNiVV');
define('PAYMONGO_API_BASE', 'https://api.paymongo.com/v1/');

/* =====================================================
   FETCH ALL PAID PAYMENTS FROM PAYMONGO
===================================================== */
$client = new Client([
    'base_uri' => PAYMONGO_API_BASE,
    'headers' => [
        'Accept'        => 'application/json',
        'Authorization' => 'Basic ' . base64_encode(PAYMONGO_SECRET_KEY . ':'),
    ],
    'timeout' => 10
]);

$sync_results = [];
$page = 1;
$has_more = true;

try {
    while ($has_more && $page <= 10) {
        $response = $client->get('payments', [
            'query' => [
                'limit' => 100,
                'page' => $page
            ]
        ]);

        $body = json_decode($response->getBody(), true);
        $payments = $body['data'] ?? [];

        if (empty($payments)) {
            $has_more = false;
            break;
        }

        foreach ($payments as $payment) {
            $status = $payment['attributes']['status'] ?? null;
            
            // Only process paid payments
            if ($status !== 'paid') {
                continue;
            }

            $payment_id = $payment['id'] ?? null;
            $amount = ($payment['attributes']['amount'] ?? 0) / 100;
            $source = $payment['attributes']['source'] ?? [];
            $source_type = $source['type'] ?? 'unknown';
            $metadata = $payment['attributes']['metadata'] ?? [];
            $description = $payment['attributes']['description'] ?? '';

            // Extract billing_id from description
            if (!preg_match('/Billing\s+#(\d+)/', $description, $match)) {
                continue;
            }

            $billing_id = (int)$match[1];

            // Check if already synced
            $check_stmt = $conn->prepare("
                SELECT receipt_id FROM patient_receipt
                WHERE billing_id = ? AND paymongo_reference = ?
            ");
            $check_stmt->bind_param("is", $billing_id, $payment_id);
            $check_stmt->execute();
            $existing = $check_stmt->get_result()->fetch_assoc();
            $check_stmt->close();

            if ($existing) {
                continue; // Already synced
            }

            // Get patient_id and billing info
            $bill_stmt = $conn->prepare("
                SELECT patient_id FROM billing_records
                WHERE billing_id = ?
            ");
            $bill_stmt->bind_param("i", $billing_id);
            $bill_stmt->execute();
            $bill_data = $bill_stmt->get_result()->fetch_assoc();
            $bill_stmt->close();

            if (!$bill_data) {
                continue;
            }

            $patient_id = $bill_data['patient_id'];

            // Determine payment method from source
            $payment_method = match($source_type) {
                'card' => 'Credit/Debit Card (' . ($source['brand'] ?? 'Unknown') . ')',
                'e_wallet' => 'E-Wallet (' . ($source['brand'] ?? 'GCash') . ')',
                'bank_transfer' => 'Bank Transfer',
                default => $source_type
            };

            // Update billing_records
            $update_stmt = $conn->prepare("
                UPDATE billing_records
                SET
                    status = 'Paid',
                    payment_method = ?,
                    paymongo_payment_id = ?,
                    paymongo_reference = ?
                WHERE billing_id = ?
                  AND status != 'Paid'
            ");
            $update_stmt->bind_param("sssi", $payment_method, $payment_id, $payment_id, $billing_id);
            $update_stmt->execute();

            $billing_updated = $update_stmt->affected_rows;
            $update_stmt->close();

            // Update or insert patient_receipt
            $receipt_stmt = $conn->prepare("
                UPDATE patient_receipt
                SET
                    status = 'Paid',
                    payment_method = ?,
                    paymongo_reference = ?
                WHERE billing_id = ? AND status != 'Paid'
            ");
            $receipt_stmt->bind_param("ssi", $payment_method, $payment_id, $billing_id);
            $receipt_stmt->execute();

            $receipt_updated = $receipt_stmt->affected_rows;
            $receipt_stmt->close();

            if ($billing_updated > 0 || $receipt_updated > 0) {
                $sync_results[] = [
                    'billing_id' => $billing_id,
                    'patient_id' => $patient_id,
                    'amount' => $amount,
                    'payment_method' => $payment_method,
                    'payment_id' => $payment_id,
                    'status' => 'Synced'
                ];
            }
        }

        $page++;
    }
} catch (Exception $e) {
    error_log("PayMongo sync error: " . $e->getMessage());
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Sync PayMongo Payments</title>
<meta name="viewport" content="width=device-width, initial-scale=1">

<link rel="stylesheet" href="assets/CSS/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
<link rel="stylesheet" href="assets/css/billing_sidebar.css">

<style>
body { background-color: #f5f5f5; }
.main-content-wrapper { margin-left: 280px; padding: 20px; }
.sync-container { background: white; border-radius: 8px; padding: 30px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
</style>
</head>

<body>
<div class="main-content-wrapper">
<div class="sync-container">

<div class="d-flex justify-content-between align-items-center mb-4">
    <h3><i class="bi bi-arrow-repeat"></i> Sync PayMongo Payments</h3>
    <a href="patient_billing.php" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left"></i> Back
    </a>
</div>

<?php if (!empty($sync_results)): ?>
<div class="alert alert-success alert-dismissible fade show" role="alert">
    <strong>✓ Synced <?= count($sync_results) ?> payment(s)</strong>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>

<div class="table-responsive">
<table class="table table-striped align-middle">
<thead class="table-success">
<tr>
    <th>Billing ID</th>
    <th>Patient ID</th>
    <th>Amount</th>
    <th>Payment Method</th>
    <th>PayMongo ID</th>
    <th>Status</th>
</tr>
</thead>
<tbody>
<?php foreach ($sync_results as $result): ?>
<tr>
    <td>#<?= $result['billing_id'] ?></td>
    <td><?= $result['patient_id'] ?></td>
    <td>₱<?= number_format($result['amount'], 2) ?></td>
    <td>
        <span class="badge bg-info">
            <?= htmlspecialchars($result['payment_method']) ?>
        </span>
    </td>
    <td><code style="font-size: 0.8rem;"><?= substr($result['payment_id'], 0, 20) ?>...</code></td>
    <td><span class="badge bg-success">✓ <?= $result['status'] ?></span></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>

<?php else: ?>
<div class="alert alert-info">
    <i class="bi bi-info-circle"></i> No new payments to sync.
</div>
<?php endif; ?>

<div class="mt-4">
    <a href="sync_paymongo_payments.php" class="btn btn-primary">
        <i class="bi bi-arrow-repeat"></i> Sync Again
    </a>
    <a href="billing_records.php" class="btn btn-secondary">
        View All Records
    </a>
</div>

</div>
</div>

<div class="main-sidebar">
<?php include 'billing_sidebar.php'; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
