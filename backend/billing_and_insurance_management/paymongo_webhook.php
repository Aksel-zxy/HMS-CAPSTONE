<?php
// --------------------------------------------------
// Load Composer Autoloader
// --------------------------------------------------
require __DIR__ . '/vendor/autoload.php';

use Dotenv\Dotenv;

// --------------------------------------------------
// Load .env (same folder as webhook)
// --------------------------------------------------
if (file_exists(__DIR__ . '/.env')) {
    Dotenv::createImmutable(__DIR__)->load();
}

$webhookSecret = getenv('PAYMONGO_WEBHOOK_SECRET');

if (!$webhookSecret) {
    http_response_code(500);
    exit('Webhook secret not configured');
}

// --------------------------------------------------
// Database connection
// --------------------------------------------------
include '../../SQL/config.php';

// --------------------------------------------------
// Read raw payload
// --------------------------------------------------
$payload = file_get_contents('php://input');
if (!$payload) {
    http_response_code(400);
    exit('Empty payload');
}

// --------------------------------------------------
// Normalize headers
// --------------------------------------------------
$headers = [];
foreach ($_SERVER as $key => $value) {
    if (strpos($key, 'HTTP_') === 0) {
        $headers[strtolower(str_replace('_', '-', substr($key, 5)))] = $value;
    }
}

$signatureHeader = $headers['paymongo-signature'] ?? null;
if (!$signatureHeader) {
    http_response_code(400);
    exit('Missing signature');
}

// --------------------------------------------------
// Verify signature
// --------------------------------------------------
$parts = [];
foreach (explode(',', $signatureHeader) as $item) {
    [$k, $v] = array_map('trim', explode('=', $item, 2));
    $parts[$k] = $v;
}

if (!isset($parts['t'], $parts['v1'])) {
    http_response_code(400);
    exit('Invalid signature format');
}

$timestamp = (int) $parts['t'];
$signature = $parts['v1'];

if (abs(time() - $timestamp) > 300) {
    http_response_code(400);
    exit('Expired signature');
}

$expected = hash_hmac('sha256', $timestamp . '.' . $payload, $webhookSecret);
if (!hash_equals($expected, $signature)) {
    http_response_code(401);
    exit('Invalid signature');
}

// --------------------------------------------------
// Parse event
// --------------------------------------------------
$event = json_decode($payload, true);
if (!isset($event['data']['id'], $event['data']['attributes']['type'])) {
    http_response_code(400);
    exit('Invalid payload');
}

$eventId   = $event['data']['id'];
$eventType = $event['data']['attributes']['type'];

// --------------------------------------------------
// Idempotency check
// --------------------------------------------------
$stmt = $conn->prepare("
    SELECT id FROM paymongo_webhook_events
    WHERE event_id = ? LIMIT 1
");
$stmt->bind_param("s", $eventId);
$stmt->execute();
if ($stmt->get_result()->fetch_assoc()) {
    http_response_code(200);
    exit('Already processed');
}
$stmt->close();

// Log event
$stmt = $conn->prepare("
    INSERT INTO paymongo_webhook_events (event_id, event_type, payload)
    VALUES (?, ?, ?)
");
$stmt->bind_param("sss", $eventId, $eventType, $payload);
$stmt->execute();
$stmt->close();

// --------------------------------------------------
// Only handle successful payments
// --------------------------------------------------
if ($eventType !== 'payment_intent.succeeded') {
    http_response_code(200);
    exit('Ignored');
}

// --------------------------------------------------
// Extract payment info
// --------------------------------------------------
$intentData = $event['data']['attributes']['data'] ?? [];
$intentAttr = $intentData['attributes'] ?? [];

$paymentIntentId = $intentData['id'] ?? null;
$paymentStatus   = strtolower($intentAttr['status'] ?? '');
$amount          = isset($intentAttr['amount']) ? $intentAttr['amount'] / 100 : 0;
$currency        = $intentAttr['currency'] ?? '';
$remarks         = $intentAttr['description'] ?? $intentAttr['remarks'] ?? null;

// Payment ID (from latest_payment)
$paymentId = $intentAttr['latest_payment']['id'] ?? null;

if (
    !$paymentIntentId ||
    !$paymentId ||
    $paymentStatus !== 'succeeded' ||
    $currency !== 'PHP' ||
    !$remarks
) {
    http_response_code(200);
    exit('Invalid payment data');
}

// --------------------------------------------------
// Find billing by transaction_id (remarks)
// --------------------------------------------------
$stmt = $conn->prepare("
    SELECT billing_id, status
    FROM billing_records
    WHERE transaction_id = ?
    LIMIT 1
");
$stmt->bind_param("s", $remarks);
$stmt->execute();
$billing = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$billing || strtolower($billing['status']) === 'paid') {
    http_response_code(200);
    exit('Billing not found or already paid');
}

// --------------------------------------------------
// Update billing_records
// --------------------------------------------------
$stmt = $conn->prepare("
    UPDATE billing_records
    SET
        status = 'Paid',
        payment_status = 'Paid',
        payment_method = 'PayMongo',
        paid_amount = ?,
        balance = 0,
        payment_date = NOW(),
        paymongo_payment_id = ?,
        paymongo_payment_intent_id = ?
    WHERE billing_id = ?
");
$stmt->bind_param(
    "dssi",
    $amount,
    $paymentId,
    $paymentIntentId,
    $billing['billing_id']
);
$stmt->execute();
$stmt->close();

// --------------------------------------------------
// Update patient_receipt
// --------------------------------------------------
$stmt = $conn->prepare("
    UPDATE patient_receipt
    SET
        status = 'Paid',
        payment_status = 'Paid',
        payment_method = 'PayMongo',
        paid_amount = ?,
        balance = 0,
        paymongo_reference = ?
    WHERE billing_id = ?
");
$stmt->bind_param(
    "dsi",
    $amount,
    $paymentId,
    $billing['billing_id']
);
$stmt->execute();
$stmt->close();

// --------------------------------------------------
http_response_code(200);
echo 'Payment processed successfully';
