<?php
include '../../SQL/config.php';

// PayMongo sends JSON
$payload = file_get_contents('php://input');
$event = json_decode($payload, true);

// Always respond fast
http_response_code(200);

if (!isset($event['data']['attributes']['type'])) {
    exit;
}

$type = $event['data']['attributes']['type'];
$data = $event['data']['attributes']['data'] ?? [];

if ($type !== 'payment_intent.succeeded') {
    exit; // ignore other events
}

$intent = $data;
$payment_intent_id = $intent['id'];
$amount = $intent['attributes']['amount'] / 100;

// -----------------------------
// FIND BILLING RECORD
// -----------------------------
$stmt = $conn->prepare("
    SELECT billing_id, status
    FROM billing_records
    WHERE paymongo_payment_intent_id = ?
    LIMIT 1
");
$stmt->bind_param("s", $payment_intent_id);
$stmt->execute();
$billing = $stmt->get_result()->fetch_assoc();

if (!$billing) exit;
if ($billing['status'] === 'Paid') exit;

// -----------------------------
// UPDATE BILLING
// -----------------------------
$stmt = $conn->prepare("
    UPDATE billing_records
    SET
        status='Paid',
        payment_status='Paid',
        payment_method='PayMongo',
        paid_amount=?,
        balance=0,
        payment_date=NOW()
    WHERE billing_id=?
");
$stmt->bind_param("di", $amount, $billing['billing_id']);
$stmt->execute();

// -----------------------------
// UPDATE RECEIPT
// -----------------------------
$stmt = $conn->prepare("
    UPDATE patient_receipt
    SET
        status='Paid',
        payment_status='Paid',
        payment_method='PayMongo',
        paid_amount=?,
        balance=0
    WHERE billing_id=?
");
$stmt->bind_param("di", $amount, $billing['billing_id']);
$stmt->execute();

exit;
