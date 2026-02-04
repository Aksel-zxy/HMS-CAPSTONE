<?php
// ------------------------------
// Load Composer Autoloader
// ------------------------------
require __DIR__ . '/../../vendor/autoload.php'; // adjust path to your project

// ------------------------------
// Load .env
// ------------------------------
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../../'); // adjust path to your .env
$dotenv->load();

// ------------------------------
// Config
// ------------------------------
include '../../SQL/config.php'; // your DB connection

$webhookSecret = getenv('PAYMONGO_WEBHOOK_SECRET');

if (!$webhookSecret) {
    http_response_code(500);
    exit('Webhook secret not configured');
}

// ------------------------------
// Read Raw Payload
// ------------------------------
$payload = file_get_contents('php://input');
if (!$payload) {
    http_response_code(400);
    exit('Empty payload');
}

// ------------------------------
// Normalize Headers (Apache/Nginx safe)
// ------------------------------
$headers = [];
foreach ($_SERVER as $key => $value) {
    if (strpos($key, 'HTTP_') === 0) {
        $header = strtolower(str_replace('_', '-', substr($key, 5)));
        $headers[$header] = $value;
    }
}

$signatureHeader = $headers['paymongo-signature'] ?? null;

if (!$signatureHeader) {
    http_response_code(400);
    exit('Missing signature');
}

// ------------------------------
// Verify Signature
// ------------------------------
$parts = [];
foreach (explode(',', $signatureHeader) as $item) {
    [$k, $v] = array_map('trim', explode('=', $item, 2));
    $parts[$k] = $v;
}

if (!isset($parts['t'], $parts['v1'])) {
    http_response_code(400);
    exit('Invalid signature format');
}

$timestamp = (int)$parts['t'];
$signature = $parts['v1'];

// Prevent replay attacks (5 mins)
if (abs(time() - $timestamp) > 300) {
    http_response_code(400);
    exit('Expired signature');
}

$expectedSignature = hash_hmac(
    'sha256',
    $timestamp . '.' . $payload,
    $webhookSecret
);

if (!hash_equals($expectedSignature, $signature)) {
    http_response_code(401);
    exit('Invalid signature');
}

// ------------------------------
// Parse Event
// ------------------------------
$event = json_decode($payload, true);

if (!isset($event['data']['id'], $event['data']['attributes']['type'])) {
    http_response_code(400);
    exit('Invalid payload');
}

$eventId   = $event['data']['id'];
$eventType = $event['data']['attributes']['type'];

// ------------------------------
// Idempotency Check
// ------------------------------
$stmt = $conn->prepare("
    SELECT id FROM paymongo_webhook_events
    WHERE event_id = ?
    LIMIT 1
");
$stmt->bind_param("s", $eventId);
$stmt->execute();
$exists = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($exists) {
    http_response_code(200);
    exit('Event already processed');
}

// Log event immediately
$stmt = $conn->prepare("
    INSERT INTO paymongo_webhook_events (event_id, event_type, payload)
    VALUES (?, ?, ?)
");
$stmt->bind_param("sss", $eventId, $eventType, $payload);
$stmt->execute();
$stmt->close();

// ------------------------------
// Handle Only Successful Payments
// ------------------------------
if ($eventType !== 'payment_intent.succeeded') {
    http_response_code(200);
    exit('Event ignored');
}

$intent = $event['data']['attributes']['data'];
$attr   = $intent['attributes'] ?? [];

$intentId = $intent['id'] ?? null;
$status   = strtolower($attr['status'] ?? '');
$currency = $attr['currency'] ?? '';
$amount   = isset($attr['amount']) ? $attr['amount'] / 100 : 0;

if (!$intentId || $status !== 'succeeded' || $currency !== 'PHP') {
    http_response_code(200);
    exit('Invalid payment data');
}

// ------------------------------
// Find Billing Record
// ------------------------------
$stmt = $conn->prepare("
    SELECT billing_id, status
    FROM billing_records
    WHERE paymongo_payment_intent_id = ?
    LIMIT 1
");
$stmt->bind_param("s", $intentId);
$stmt->execute();
$billing = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$billing || strtolower($billing['status']) === 'paid') {
    http_response_code(200);
    exit('Billing already paid or not found');
}

// ------------------------------
// Update Billing
// ------------------------------
$stmt = $conn->prepare("
    UPDATE billing_records
    SET
        status = 'Paid',
        payment_status = 'Paid',
        payment_method = 'PayMongo',
        paid_amount = ?,
        balance = 0,
        payment_date = NOW()
    WHERE billing_id = ?
");
$stmt->bind_param("di", $amount, $billing['billing_id']);
$stmt->execute();
$stmt->close();

// ------------------------------
// Update Patient Receipt
// ------------------------------
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
$stmt->bind_param("dsi", $amount, $intentId, $billing['billing_id']);
$stmt->execute();
$stmt->close();

http_response_code(200);
echo 'Payment processed successfully';
