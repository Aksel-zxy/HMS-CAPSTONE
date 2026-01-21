<?php
// billing_summary.php

include '../../SQL/config.php';
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/vendor/autoload.php'; // Correct path for XAMPP

use GuzzleHttp\Client;

// -----------------------------
// SET PAYMONGO SECRET KEY DIRECTLY
// -----------------------------
define('PAYMONGO_SECRET_KEY', 'sk_test_akT1ZW6za7m6FC9S9VqYNiVV'); // Your secret key here
if (empty(PAYMONGO_SECRET_KEY)) {
    die("PayMongo secret key is not set. Please set PAYMONGO_API_KEY.");
}

define('PAYMONGO_API_BASE', 'https://api.paymongo.com/v1/');

// -----------------------------
// Helper: Create a PayMongo payment link
// -----------------------------
function create_paymongo_payment_link($amount, $description = '', $remarks = '') {
    $amount = max((int)$amount, 100); // Minimum 100 centavos (₱1)

    $payload = [
        'data' => [
            'attributes' => [
                'amount'      => $amount,
                'description' => $description ?: 'Billing Payment',
                'remarks'     => $remarks
            ]
        ]
    ];

    try {
        $client = new Client([
            'base_uri' => PAYMONGO_API_BASE,
            'headers'  => [
                'Accept'        => 'application/json',
                'Content-Type'  => 'application/json',
                'Authorization' => 'Basic ' . base64_encode(PAYMONGO_SECRET_KEY . ':'),
            ]
        ]);

        $resp = $client->post('links', ['body' => json_encode($payload)]);
        $body = json_decode($resp->getBody(), true);

        // Correct checkout URL
        $url = $body['data']['attributes']['checkout_url'] ?? null;

        return ['success' => true, 'url' => $url, 'response' => $body];

    } catch (\GuzzleHttp\Exception\ClientException $e) {
        $res = $e->getResponse();
        $body = $res ? (string)$res->getBody() : '';
        return [
            'success' => false,
            'url' => null,
            'error' => $body ?: $e->getMessage(),
            'payload' => $payload
        ];
    } catch (\Exception $e) {
        return [
            'success' => false,
            'url' => null,
            'error' => $e->getMessage(),
            'payload' => $payload
        ];
    }
}

// -----------------------------
// Patient & Billing Lookup
// -----------------------------
$patient_id = intval($_GET['patient_id'] ?? 0);
$billing_id = null;
$transaction_id = null;

$stmt = $conn->prepare("SELECT *, CONCAT(fname,' ',IFNULL(mname,''),' ',lname) AS full_name FROM patientinfo WHERE patient_id = ?");
$stmt->bind_param("i", $patient_id);
$stmt->execute();
$patient = $stmt->get_result()->fetch_assoc();

// Get the latest billing with transaction_id
$stmt = $conn->prepare("SELECT MAX(billing_id) AS bid FROM billing_items WHERE patient_id = ? AND finalized = 1");
$stmt->bind_param("i", $patient_id);
$stmt->execute();
$billing_id = $stmt->get_result()->fetch_assoc()['bid'] ?? null;

// Get transaction_id from billing_records
if ($billing_id) {
    $stmt = $conn->prepare("SELECT transaction_id FROM billing_records WHERE patient_id = ? AND billing_id = ?");
    $stmt->bind_param("ii", $patient_id, $billing_id);
    $stmt->execute();
    $txn_record = $stmt->get_result()->fetch_assoc();
    $transaction_id = $txn_record['transaction_id'] ?? null;
}

$items = [];
$total = 0;
if ($billing_id) {
    $stmt = $conn->prepare("
        SELECT bi.*, ds.serviceName, ds.description
        FROM billing_items bi
        LEFT JOIN dl_services ds ON bi.service_id = ds.serviceID
        WHERE bi.patient_id = ? AND bi.billing_id = ?
    ");
    $stmt->bind_param("ii", $patient_id, $billing_id);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $items[] = $row;
        $total += floatval($row['total_price']);
    }
}

// -----------------------------
// Create PayMongo Payment Link
// NOTE: We pass transaction_id as part of remarks so webhook can match it
// PayMongo doesn't support custom metadata on links, so we encode it in remarks
// -----------------------------
$enableLink = true;
$payLinkUrl = null;
$linkError = null;
$paymongo_response = null;

if ($enableLink && $total > 0 && $transaction_id) {
    // Create payment link with transaction ID embedded in remarks
    $description = "Billing #{$billing_id} - Patient {$patient_id}";
    $remarks = "TXN:{$transaction_id}";
    
    $linkResult = create_paymongo_payment_link(
        (int)($total * 100),
        $description,
        $remarks
    );
    
    $paymongo_response = $linkResult['response'] ?? null;

    if ($linkResult['success'] && !empty($linkResult['url'])) {
        $payLinkUrl = $linkResult['url'];
        error_log("[BILLING] Payment link created - Patient: {$patient_id}, Billing: {$billing_id}, TXN: {$transaction_id}, Amount: {$total}");
    } else {
        $linkError = $linkResult['error'] ?? 'Unknown error creating payment link';
        error_log("[BILLING] Payment link error - " . $linkError);
    }
} elseif (!$transaction_id) {
    $linkError = "Transaction ID not found. Please finalize billing first.";
}

// -----------------------------
// Generate GCash QR (optional)
// -----------------------------
$qrImage = null;
if ($total > 0) {
    try {
        $client = new Client([
            'base_uri' => PAYMONGO_API_BASE,
            'headers'  => [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
                'Authorization' => 'Basic ' . base64_encode(PAYMONGO_SECRET_KEY . ':'),
            ]
        ]);

        $response = $client->post('sources', [
            'json' => [
                'data' => [
                    'attributes' => [
                        'type'     => 'gcash_qr',
                        'amount'   => intval($total * 100),
                        'currency' => 'PHP',
                        'redirect' => [
                            'success' => 'https://yourdomain.com/payment_callback.php?status=success',
                            'failed'  => 'https://yourdomain.com/payment_callback.php?status=failed'
                        ],
                        'metadata' => [
                            'patient_id' => $patient_id,
                            'billing_id' => $billing_id
                        ]
                    ]
                ]
            ]
        ]);

        $source = json_decode($response->getBody(), true);
        $qrString = $source['data']['attributes']['flow']['qr_string'] ?? null;
        if ($qrString) {
            $qrImage = "https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=" . urlencode($qrString);
        }
    } catch (\Exception $e) {
        $qrImage = null;
    }
}
?>

<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Billing Summary</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light p-4">
<div class="container">
<div class="card p-4 shadow-sm">

<h4 class="mb-3">Billing Summary</h4>

<p><strong>Patient:</strong> <?= htmlspecialchars($patient['full_name'] ?? 'N/A') ?></p>

<table class="table table-bordered">
<thead class="table-primary">
<tr>
    <th>Service</th>
    <th>Description</th>
    <th class="text-end">Amount</th>
</tr>
</thead>
<tbody>
<?php if ($items): ?>
    <?php foreach ($items as $item): ?>
    <tr>
        <td><?= htmlspecialchars($item['serviceName']) ?></td>
        <td><?= htmlspecialchars($item['description']) ?></td>
        <td class="text-end">₱<?= number_format($item['total_price'], 2) ?></td>
    </tr>
    <?php endforeach; ?>
<?php else: ?>
    <tr><td colspan="3" class="text-center">No billing items found.</td></tr>
<?php endif; ?>
</tbody>
</table>

<h5 class="text-end mt-3">
    Total Payable: ₱<?= number_format($total, 2) ?>
</h5>

<?php if ($payLinkUrl): ?>
<div class="mt-3 text-center">
    <a href="<?= htmlspecialchars($payLinkUrl) ?>" target="_blank" class="btn btn-primary btn-lg">
        Pay Now
    </a>
    <p class="text-muted mt-2">Open the PayMongo payment link in a new tab.</p>
</div>
<?php elseif ($linkError): ?>
<div class="alert alert-warning mt-3" role="alert">
    Payment link error: <?= htmlspecialchars($linkError) ?>
</div>
<?php endif; ?>

<?php if ($qrImage): ?>
<hr>
<div class="text-center mt-4">
    <h5>Scan to Pay via GCash</h5>
    <img src="<?= $qrImage ?>" alt="PayMongo GCash QR">
    <p class="text-muted mt-2">Powered by PayMongo</p>
</div>
<?php endif; ?>

<div class="main-sidebar">
<?php include 'billing_sidebar.php'; ?>
</div>

</div>
</div>
</body>
</html>
