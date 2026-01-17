<?php
// paymongo_webhook.php

include '../../SQL/config.php'; // database connection
if (session_status() === PHP_SESSION_NONE) session_start();

$input = file_get_contents('php://input');
$data = json_decode($input, true);

// Log the webhook for debugging
file_put_contents(__DIR__.'/paymongo_webhook.log', date('Y-m-d H:i:s')." - ".json_encode($data)."\n", FILE_APPEND);

// Only handle payment links events
$eventType = $data['type'] ?? '';
$paymentData = $data['data']['attributes'] ?? [];

if ($eventType === 'link.paid') { // fired when a payment link is paid
    $reference = $paymentData['reference_number'] ?? '';
    $amount = $paymentData['amount'] ?? 0;
    $currency = $paymentData['currency'] ?? '';
    $paidAt = date('Y-m-d H:i:s', $paymentData['updated_at'] ?? time());

    // Find the billing record by reference_number
    $stmt = $conn->prepare("SELECT * FROM billing_records WHERE transaction_id = ?");
    $stmt->bind_param("s", $reference);
    $stmt->execute();
    $record = $stmt->get_result()->fetch_assoc();

    if ($record) {
        $billingId = $record['billing_id'];
        $patientId = $record['patient_id'];

        // Update billing_records
        $stmt = $conn->prepare("UPDATE billing_records SET status='Paid', paid_amount=?, balance=0, payment_date=? WHERE transaction_id=?");
        $stmt->bind_param("dss", $amount, $paidAt, $reference);
        $stmt->execute();

        // Update patient_receipt
        $stmt = $conn->prepare("
            UPDATE patient_receipt 
            SET status='Paid', balance=0, paid_amount=? 
            WHERE billing_id=? AND patient_id=?
        ");
        $stmt->bind_param("dii", $amount, $billingId, $patientId);
        $stmt->execute();
    }
}

// Respond with 200 OK
http_response_code(200);
echo json_encode(['success'=>true]);
