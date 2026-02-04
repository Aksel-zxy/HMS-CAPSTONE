<?php
include '../../SQL/config.php';

// Read PayMongo payload
$payload = file_get_contents('php://input');
$event = json_decode($payload, true);

// Respond immediately
http_response_code(200);

if (!isset($event['data']['attributes']['type'])) {
    exit;
}

$type = $event['data']['attributes']['type'];
$attributes = $event['data']['attributes']['data']['attributes'] ?? [];

// ✅ WE ONLY CARE ABOUT THIS
if ($type !== 'payment.paid') {
    exit;
}

// -----------------------------
// EXTRACT DATA
// -----------------------------
$amount = ($attributes['amount'] ?? 0) / 100;
$description = $attributes['description'] ?? '';
$reference = $event['data']['attributes']['data']['id'] ?? null;

// -----------------------------
// EXTRACT TXN ID
// -----------------------------
preg_match('/TXN:([\w]+)/', $description, $txnMatch);
if (!$txnMatch) exit;

$transaction_id = $txnMatch[1];

// -----------------------------
// FIND BILLING RECORD
// -----------------------------
$stmt = $conn->prepare("
    SELECT billing_id, status
    FROM billing_records
    WHERE transaction_id = ?
    LIMIT 1
");
$stmt->bind_param("s", $transaction_id);
$stmt->execute();
$billing = $stmt->get_result()->fetch_assoc();

if (!$billing) exit;
if ($billing['status'] === 'Paid') exit;

// -----------------------------
// UPDATE BILLING RECORD
// -----------------------------
$stmt = $conn->prepare("
    UPDATE billing_records
    SET
        status='Paid',
        payment_status='Paid',
        payment_method='PayMongo',
        paid_amount=?,
        balance=0,
        payment_date=NOW(),
        paymongo_reference=?
    WHERE billing_id=?
");
$stmt->bind_param("dsi", $amount, $reference, $billing['billing_id']);
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
