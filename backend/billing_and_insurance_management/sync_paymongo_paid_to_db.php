<?php
require __DIR__ . '/vendor/autoload.php';
use GuzzleHttp\Client;
use Dotenv\Dotenv;

/* =========================================
   LOAD ENV
========================================= */
$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

$secretKey = $_ENV['PAYMONGO_SECRET_KEY'] ?? null;
if (!$secretKey) {
    die('PAYMONGO_SECRET_KEY missing in .env');
}

/* =========================================
   DATABASE
========================================= */
include '../../SQL/config.php';

/* =========================================
   PAYMONGO CLIENT
========================================= */
$client = new Client([
    'base_uri' => 'https://api.paymongo.com/v1/',
    'headers' => [
        'Accept'        => 'application/json',
        'Authorization' => 'Basic ' . base64_encode($secretKey . ':'),
    ],
    'timeout' => 10
]);

/* =========================================
   FETCH PAYMENTS
========================================= */
try {
    $response = $client->get('payments?limit=100');
    $body = json_decode($response->getBody(), true);
} catch (Exception $e) {
    die('PayMongo API error: ' . $e->getMessage());
}

$payments = $body['data'] ?? [];
if (!$payments) {
    die('No payments found.');
}

$updated = 0;

/* =========================================
   PROCESS PAID PAYMENTS
========================================= */
foreach ($payments as $payment) {

    $attr = $payment['attributes'];
    if (($attr['status'] ?? '') !== 'paid') continue;

    $paymentId = $payment['id'];
    $intentId  = $attr['payment_intent_id'] ?? null;
    $amount    = ($attr['amount'] ?? 0) / 100;
    $desc      = $attr['description'] ?? '';

    // Parse description: "Billing #7 - Patient #39"
    if (!preg_match('/Billing #(\d+)\s*-\s*Patient #(\d+)/', $desc, $matches)) {
        continue;
    }
    $billing_id = intval($matches[1]);
    $patient_id = intval($matches[2]);

    // Check if billing exists and amount matches
    $stmt = $conn->prepare("
        SELECT billing_id, patient_id, out_of_pocket, status 
        FROM billing_records 
        WHERE billing_id = ? AND patient_id = ? 
        LIMIT 1
    ");
    $stmt->bind_param("ii", $billing_id, $patient_id);
    $stmt->execute();
    $billing = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$billing || strtolower($billing['status']) === 'paid') {
        continue;
    }

    // Optional: check amount
    if (abs($billing['out_of_pocket'] - $amount) > 1) {
        continue; // amounts don't match
    }

    /* ===============================
       UPDATE BILLING RECORD
    ================================ */
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
            paymongo_payment_intent_id = ?,
            paymongo_reference_number = ?
        WHERE billing_id = ? AND patient_id = ?
    ");
    $stmt->bind_param(
        "dssiii",
        $amount,
        $paymentId,
        $intentId,
        $paymentId,
        $billing_id,
        $patient_id
    );
    $stmt->execute();
    $stmt->close();

    /* ===============================
       UPDATE PATIENT RECEIPT
    ================================ */
    $stmt = $conn->prepare("
        UPDATE patient_receipt
        SET 
            status = 'Paid',
            payment_status = 'Paid',
            payment_method = 'PayMongo',
            paid_amount = ?,
            balance = 0,
            paymongo_reference = ?
        WHERE billing_id = ? AND patient_id = ?
    ");
    $stmt->bind_param(
        "dsii",
        $amount,
        $paymentId,
        $billing_id,
        $patient_id
    );
    $stmt->execute();
    $stmt->close();

    $updated++;
}

echo "SYNC COMPLETE — {$updated} billing record(s) marked as PAID.";
