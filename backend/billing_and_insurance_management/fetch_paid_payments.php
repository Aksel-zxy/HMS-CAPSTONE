<?php
include '../../SQL/config.php';

// PayMongo config
$apiKey = 'sk_test_akT1ZW6za7m6FC9S9VqYNiVV';
$auth = 'Basic ' . base64_encode($apiKey . ':');

$startingAfter = null;

do {

    $url = 'https://api.paymongo.com/v1/payments?limit=50';
    if ($startingAfter) {
        $url .= '&starting_after=' . $startingAfter;
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: ' . $auth,
            'Accept: application/json'
        ]
    ]);

    $response = curl_exec($ch);
    curl_close($ch);

    $data = json_decode($response, true);
    if (empty($data['data'])) break;

    foreach ($data['data'] as $payment) {

        $attr = $payment['attributes'];

        // ✅ Only PAID payments
        if ($attr['status'] !== 'paid') {
            continue;
        }

        $paymentId = $payment['id'];
        $amount = $attr['amount'] / 100;

        // 🔥 FIND BILLING RECORD BY PAYMONGO ID
        $stmt = $conn->prepare("
            SELECT billing_id, status
            FROM billing_records
            WHERE paymongo_payment_id = ?
            LIMIT 1
        ");
        $stmt->bind_param("s", $paymentId);
        $stmt->execute();
        $billing = $stmt->get_result()->fetch_assoc();

        if (!$billing) continue;
        if ($billing['status'] === 'Paid') continue;

        // UPDATE billing_records
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

        // UPDATE patient_receipt
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

        echo "✔ Billing #{$billing['billing_id']} marked PAID\n";
    }

    $last = end($data['data']);
    $startingAfter = $last['id'] ?? null;

} while ($startingAfter);

echo "✅ Payment sync completed";
