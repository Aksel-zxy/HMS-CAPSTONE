<?php
// paymongo_webhook.php

include '../../SQL/config.php';

// Read webhook payload
$input = file_get_contents('php://input');
$data = json_decode($input, true);

// Log webhook payload (VERY IMPORTANT)
file_put_contents(
    __DIR__ . '/paymongo_webhook.log',
    date('Y-m-d H:i:s') . " " . json_encode($data) . PHP_EOL,
    FILE_APPEND
);

// Validate payload
if (!isset($data['type'], $data['data']['attributes'])) {
    http_response_code(400);
    exit;
}

$eventType = $data['type'];
$attributes = $data['data']['attributes'];

if ($eventType === 'link.paid') {

    $reference = $attributes['reference_number'] ?? null;
    $amount    = isset($attributes['amount']) ? $attributes['amount'] / 100 : 0;
    $paidAt    = date('Y-m-d H:i:s');

    if (!$reference) {
        http_response_code(200);
        exit;
    }

    // Find billing record
    $stmt = $conn->prepare(
        "SELECT * FROM billing_records WHERE transaction_id = ?"
    );
    $stmt->bind_param("s", $reference);
    $stmt->execute();
    $record = $stmt->get_result()->fetch_assoc();

    if ($record) {

        // ✅ Update billing_records
        $stmt = $conn->prepare("
            UPDATE billing_records
            SET 
                status = 'Paid',
                payment_status = 'Paid',
                paid_amount = ?,
                balance = 0,
                payment_date = ?
            WHERE transaction_id = ?
        ");
        $stmt->bind_param("dss", $amount, $paidAt, $reference);
        $stmt->execute();

        // ✅ Update patient_receipt
        $stmt = $conn->prepare("
            UPDATE patient_receipt
            SET 
                status = 'Paid',
                payment_status = 'Paid',
                paid_amount = ?,
                balance = 0
            WHERE billing_id = ? AND patient_id = ?
        ");
        $stmt->bind_param(
            "dii",
            $amount,
            $record['billing_id'],
            $record['patient_id']
        );
        $stmt->execute();
    }
}

// Always return 200 to PayMongo
http_response_code(200);
echo json_encode(['success' => true]);
