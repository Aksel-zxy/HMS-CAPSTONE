<?php
include '../../SQL/config.php';

$paymongo_webhook_secret = getenv('PAYMONGO_WEBHOOK_SECRET');
if (!$paymongo_webhook_secret) {
    error_log("PAYMONGO_WEBHOOK_SECRET not configured");
    http_response_code(500);
    exit;
}

$payload = file_get_contents('php://input');
$event = json_decode($payload, true);

// Verify webhook signature
$signature = $_SERVER['HTTP_X_PAYMONGO_SIGNATURE'] ?? null;
if (!$signature) {
    error_log("No webhook signature provided");
    http_response_code(401);
    exit;
}

$computed_signature = hash_hmac('sha256', $payload, $paymongo_webhook_secret);
if (!hash_equals($computed_signature, $signature)) {
    error_log("Invalid webhook signature. Expected: $computed_signature, Got: $signature");
    http_response_code(401);
    exit;
}

error_log("PayMongo Webhook verified: " . print_r($event, true));

// Always acknowledge PayMongo immediately
http_response_code(200);

if (!isset($event['data']['attributes']['type'])) {
    error_log("No event type found");
    exit;
}

$type = $event['data']['attributes']['type'];

// Only process successful payments
if ($type !== 'payment.paid') {
    error_log("Event type not payment.paid: " . $type);
    exit;
}

$data       = $event['data']['attributes']['data'] ?? [];
$attributes = $data['attributes'] ?? [];

$amount      = ($attributes['amount'] ?? 0) / 100;
$description = $attributes['description'] ?? '';
$payment_id  = $data['id'] ?? null;

if (!$payment_id || !$description) {
    error_log("Missing payment_id or description");
    exit;
}

// Extract billing_id from description "Billing #6 - Patient 39"
if (!preg_match('/Billing\s+#(\d+)/', $description, $match)) {
    error_log("Could not extract billing_id from: " . $description);
    exit;
}

$billing_id = (int)$match[1];
error_log("Processing payment for billing_id: $billing_id, payment_id: $payment_id");

// UPDATE billing_records
$stmt = $conn->prepare("
    UPDATE billing_records
    SET
        status = 'Paid',
        payment_method = 'PayMongo',
        paymongo_payment_id = ?,
        paymongo_reference = ?
    WHERE billing_id = ?
      AND status != 'Paid'
");
$stmt->bind_param("ssi", $payment_id, $payment_id, $billing_id);
$stmt->execute();
error_log("Updated billing_records: " . $stmt->affected_rows . " rows");

// UPDATE patient_receipt
$stmt = $conn->prepare("
    UPDATE patient_receipt
    SET
        status = 'Paid',
        payment_method = 'PayMongo',
        paymongo_reference = ?
    WHERE billing_id = ?
      AND status != 'Paid'
");
$stmt->bind_param("si", $payment_id, $billing_id);
$stmt->execute();
error_log("Updated patient_receipt: " . $stmt->affected_rows . " rows");

exit;
