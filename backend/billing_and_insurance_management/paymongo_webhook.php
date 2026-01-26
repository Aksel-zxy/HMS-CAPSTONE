<?php
// paymongo_webhook.php - Webhook handler for PayMongo payment notifications

include '../../SQL/config.php';

// Log function for detailed debugging
function log_webhook($message, $data = null) {
    $log_file = __DIR__ . '/paymongo_webhook.log';
    $timestamp = date('Y-m-d H:i:s');
    $log_msg = "[{$timestamp}] {$message}";
    if ($data) {
        $log_msg .= " | " . json_encode($data);
    }
    $log_msg .= PHP_EOL;
    file_put_contents($log_file, $log_msg, FILE_APPEND);
}

// Read webhook payload
$input = file_get_contents('php://input');
$data = json_decode($input, true);

log_webhook('Webhook received', $data);

// Validate payload structure
if (!$data || !is_array($data)) {
    log_webhook('ERROR: Invalid JSON payload');
    http_response_code(400);
    exit;
}

if (!isset($data['type'])) {
    log_webhook('ERROR: No event type in payload');
    http_response_code(400);
    exit;
}

$eventType = $data['type'];
$eventData = $data['data'] ?? [];
$attributes = $eventData['attributes'] ?? [];

log_webhook('Processing event type', ['type' => $eventType]);

// Handle payment-related events
// PayMongo sends: 'link.payment.paid', 'payment.paid', 'link.paid', etc.
$isPaidEvent = (strpos($eventType, 'paid') !== false);

if ($isPaidEvent) {
    
    // Extract transaction reference from multiple possible fields
    $reference = null;
    
    // Priority 1: Check remarks (where we encode TXN:xxx)
    if (!empty($attributes['remarks']) && strpos($attributes['remarks'], 'TXN:') !== false) {
        if (preg_match('/TXN:([\w]+)/', $attributes['remarks'], $matches)) {
            $reference = $matches[1];
            log_webhook('Reference extracted from remarks', ['reference' => $reference]);
        }
    }
    
    // Priority 2: Check reference_number
    if (!$reference && !empty($attributes['reference_number'])) {
        $reference = $attributes['reference_number'];
        log_webhook('Reference extracted from reference_number', ['reference' => $reference]);
    }
    
    // Priority 3: Check description
    if (!$reference && !empty($attributes['description']) && strpos($attributes['description'], 'TXN:') !== false) {
        if (preg_match('/TXN:([\w]+)/', $attributes['description'], $matches)) {
            $reference = $matches[1];
            log_webhook('Reference extracted from description', ['reference' => $reference]);
        }
    }
    
    $amount = isset($attributes['amount']) ? ($attributes['amount'] / 100) : 0;
    
    if (!$reference) {
        log_webhook('ERROR: Could not extract transaction reference', $attributes);
        http_response_code(200);
        exit;
    }
    
    log_webhook('Processing payment', ['transaction_id' => $reference, 'amount' => $amount]);
    
    // Find billing record by transaction_id
    $stmt = $conn->prepare(
        "SELECT * FROM billing_records WHERE transaction_id = ? LIMIT 1"
    );
    $stmt->bind_param("s", $reference);
    $stmt->execute();
    $record = $stmt->get_result()->fetch_assoc();
    
    if (!$record) {
        log_webhook('ERROR: No billing record found', ['transaction_id' => $reference]);
        http_response_code(200);
        exit;
    }
    
    log_webhook('Found billing record', ['billing_id' => $record['billing_id'], 'patient_id' => $record['patient_id']]);
    
    // Update billing_records
    $update_status = 'Paid';
    $payment_method_val = 'PayMongo';
    $paymongo_ref = $attributes['id'] ?? substr($reference, 0, 50);
    
    $stmt = $conn->prepare("
        UPDATE billing_records
        SET 
            status = ?,
            payment_method = ?,
            paymongo_reference = ?
        WHERE transaction_id = ?
    ");
    $stmt->bind_param("ssss", $update_status, $payment_method_val, $paymongo_ref, $reference);
    $result1 = $stmt->execute();
    
    if (!$result1) {
        log_webhook('ERROR: Failed to update billing_records', ['error' => $stmt->error]);
    } else {
        log_webhook('SUCCESS: Updated billing_records');
    }
    
    // Update patient_receipt
    $stmt = $conn->prepare("
        UPDATE patient_receipt
        SET 
            status = ?,
            payment_method = ?,
            paymongo_reference = ?
        WHERE transaction_id = ?
    ");
    $stmt->bind_param("ssss", $update_status, $payment_method_val, $paymongo_ref, $reference);
    $result2 = $stmt->execute();
    
    if (!$result2) {
        log_webhook('ERROR: Failed to update patient_receipt', ['error' => $stmt->error]);
    } else {
        log_webhook('SUCCESS: Updated patient_receipt');
    }
    
    if ($result1 || $result2) {
        log_webhook('✓ PAYMENT MARKED AS PAID', [
            'transaction_id' => $reference,
            'billing_id' => $record['billing_id'],
            'patient_id' => $record['patient_id'],
            'amount' => $amount
        ]);
    }
}

// Always return 200 to PayMongo to acknowledge receipt
http_response_code(200);
echo json_encode(['status' => 'received', 'timestamp' => date('c')]);
exit;
?>
