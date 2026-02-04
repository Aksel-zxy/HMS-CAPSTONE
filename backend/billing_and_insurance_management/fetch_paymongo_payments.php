<?php
include '../../SQL/config.php';

/* ============================================
   PAYMONGO CONFIG
============================================ */
$apiKey = getenv('PAYMONGO_SECRET_KEY'); // SET THIS IN ENV
$auth = 'Basic ' . base64_encode($apiKey . 'sk_test_akT1ZW6za7m6FC9S9VqYNiVV');

$startingAfter = null;
$synced = 0;
$skipped = 0;

do {
    $url = 'https://api.paymongo.com/v1/payment_intents?limit=50';
    if ($startingAfter) {
        $url .= '&starting_after=' . urlencode($startingAfter);
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
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        header('Content-Type: application/json');
        echo json_encode([
            'error' => "PayMongo API error HTTP {$httpCode}",
            'response' => $response
        ]);
        exit;
    }

    $data = json_decode($response, true);
    if (empty($data['data'])) break;

    foreach ($data['data'] as $intent) {

        $attr = $intent['attributes'] ?? [];
        $status = strtolower($attr['status'] ?? '');

        // Only successful payments
        if (!in_array($status, ['succeeded', 'paid'])) {
            $skipped++;
            continue;
        }

        if (($attr['currency'] ?? '') !== 'PHP') {
            $skipped++;
            continue;
        }

        $intentId = $intent['id'];
        $amount = ($attr['amount'] ?? 0) / 100;

        // Match billing record using payment_intent_id
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

        if (!$billing) { $skipped++; continue; }
        if (strtolower($billing['status']) === 'paid') { $skipped++; continue; }

        // UPDATE billing_records
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

        // UPDATE patient_receipt
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

        $synced++;
    }

    $last = end($data['data']);
    $startingAfter = $last['id'] ?? null;

} while ($startingAfter);

header('Content-Type: application/json');
echo json_encode([
    'synced' => $synced,
    'skipped' => $skipped,
    'message' => 'PaymentIntent sync completed'
]);
