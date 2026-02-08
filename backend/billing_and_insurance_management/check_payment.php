<?php
// check_payment.php
include '../../SQL/config.php';
if (session_status() === PHP_SESSION_NONE) session_start();

if (empty($_GET['billing_id'])) {
    die('No billing ID provided.');
}

$billing_id = intval($_GET['billing_id']);

// Get transaction_id and patient_id for the billing
$stmt = $conn->prepare("SELECT transaction_id, patient_id FROM billing_records WHERE billing_id=?");
$stmt->bind_param("i", $billing_id);
$stmt->execute();
$billing = $stmt->get_result()->fetch_assoc();

if (!$billing || empty($billing['transaction_id'])) {
    header("Location: patient_billing.php?msg=" . urlencode("Transaction ID not found."));
    exit;
}

$transaction_id = $billing['transaction_id'];
$patient_id = $billing['patient_id'];

// Call PayMongo API to check status
require_once __DIR__.'/vendor/autoload.php';
use GuzzleHttp\Client;

$client = new Client([
    'base_uri' => 'https://api.paymongo.com/v1/',
    'headers' => [
        'Accept' => 'application/json',
        'Authorization' => 'Basic ' . base64_encode('sk_test_akT1ZW6za7m6FC9S9VqYNiVV' . ':')
    ]
]);

try {
    $response = $client->get('links?limit=50'); // you may filter by metadata if using it
    $links = json_decode($response->getBody(), true)['data'] ?? [];

    $found = false;
    foreach ($links as $link) {
        $remarks = $link['attributes']['remarks'] ?? '';
        $status  = $link['attributes']['status'] ?? '';
        $payment_method = $link['attributes']['payment_method'] ?? '';

        if (strpos($remarks, "TXN:$transaction_id") !== false) {
            if ($status === 'paid') {
                $amount = $link['attributes']['amount'] / 100;
                $paidAt = date('Y-m-d H:i:s');

                // Update billing_records
                $stmt = $conn->prepare("
                    UPDATE billing_records
                    SET status='Paid', payment_status='Paid', paid_amount=?, balance=0, payment_date=?, payment_method=?
                    WHERE transaction_id=?
                ");
                $stmt->bind_param("dsss", $amount, $paidAt, $payment_method, $transaction_id);
                $stmt->execute();

                // Update patient_receipt
                $stmt = $conn->prepare("
                    UPDATE patient_receipt
                    SET status='Paid', payment_status='Paid', paid_amount=?, balance=0, payment_method=?
                    WHERE billing_id=?
                ");
                $stmt->bind_param("dsi", $amount, $payment_method, $billing_id);
                $stmt->execute();

                $found = true;
            }
            break;
        }
    }

    $msg = $found ? "Payment is now marked as Paid." : "Payment still pending on PayMongo.";
    header("Location: patient_billing.php?msg=" . urlencode($msg));
    exit;

} catch (\Exception $e) {
    header("Location: patient_billing.php?msg=" . urlencode("Error checking PayMongo: " . $e->getMessage()));
    exit;
}
