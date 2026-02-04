<?php
include '../../SQL/config.php';

header('Content-Type: application/json; charset=utf-8');

// PayMongo config (test key)
$apiKey = 'sk_test_akT1ZW6za7m6FC9S9VqYNiVV';
$auth = 'Basic ' . base64_encode($apiKey . ':');

$startingAfter = null;
$synced = 0;
$skipped = 0;
$errors = [];

do {
    $url = 'https://api.paymongo.com/v1/payments?limit=50';
    if ($startingAfter) $url .= '&starting_after=' . urlencode($startingAfter);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: ' . $auth,
            'Accept: application/json'
        ],
        CURLOPT_TIMEOUT => 15,
    ]);

    $response = curl_exec($ch);
    if ($response === false) {
        $errors[] = 'cURL error: ' . curl_error($ch);
        curl_close($ch);
        break;
    }
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        $errors[] = "PayMongo API HTTP {$httpCode}";
        break;
    }

    $payload = json_decode($response, true);
    if (!isset($payload['data']) || !is_array($payload['data'])) break;

    foreach ($payload['data'] as $p) {
        $paymentId = $p['id'] ?? null;
        $attr = $p['attributes'] ?? [];
        $status = strtolower($attr['status'] ?? '');

        if ($status !== 'paid') { $skipped++; continue; }

        $amount = isset($attr['amount']) ? ((float)$attr['amount']) / 100 : 0.0;

        // gather possible text fields to search for transaction_id or Billing#:
        $description = $attr['description'] ?? '';
        $remarks = $attr['remarks'] ?? '';
        $metadata = $attr['metadata'] ?? [];
        $meta_desc = is_array($metadata) ? implode(' ', array_filter($metadata)) : ($metadata ?? '');
        $possible_texts = [$remarks, $description, $meta_desc];

        // try to match transaction_id first (common flow: billing_summary sets transaction_id in remarks)
        $matched_billing_id = 0;
        $matched_txn = null;

        $txn_candidates = [];
        if (!empty($attr['remarks'])) $txn_candidates[] = $attr['remarks'];
        if (!empty($metadata['remarks'])) $txn_candidates[] = $metadata['remarks'];
        if (!empty($metadata['reference'])) $txn_candidates[] = $metadata['reference'];
        if (!empty($attr['reference_number'])) $txn_candidates[] = $attr['reference_number'];
        // also search description/meta for txn patterns
        foreach ($possible_texts as $txt) {
            if (preg_match('/TXN[0-9A-Fa-f]{1,}/', $txt, $m)) $txn_candidates[] = $m[0];
        }

        // dedupe
        $txn_candidates = array_values(array_unique(array_filter($txn_candidates)));

        if (!empty($txn_candidates)) {
            foreach ($txn_candidates as $tc) {
                // try to find billing by transaction_id
                $chk = $conn->prepare("SELECT billing_id, status FROM billing_records WHERE transaction_id = ? LIMIT 1");
                $chk->bind_param("s", $tc);
                $chk->execute();
                $row = $chk->get_result()->fetch_assoc();
                $chk->close();
                if ($row) {
                    $matched_billing_id = (int)$row['billing_id'];
                    $matched_txn = $tc;
                    break;
                }
            }
        }

        // if not found by txn, try to extract "Billing #N" from description/metadata
        if (!$matched_billing_id) {
            foreach ($possible_texts as $txt) {
                if (preg_match('/Billing\s*#\s*(\d+)/i', $txt, $m)) {
                    $matched_billing_id = (int)$m[1];
                    break;
                }
            }
        }

        // final fallback: maybe billing_records already has paymongo_payment_id set -> try match (unlikely here)
        if (!$matched_billing_id && $paymentId) {
            $chk = $conn->prepare("SELECT billing_id, status FROM billing_records WHERE paymongo_payment_id = ? LIMIT 1");
            $chk->bind_param("s", $paymentId);
            $chk->execute();
            $row = $chk->get_result()->fetch_assoc();
            $chk->close();
            if ($row) $matched_billing_id = (int)$row['billing_id'];
        }

        if (!$matched_billing_id) {
            $errors[] = "Unable to resolve billing for PayMongo payment {$paymentId}";
            $skipped++;
            continue;
        }

        // load current billing record
        $chk = $conn->prepare("SELECT billing_id, status FROM billing_records WHERE billing_id = ? LIMIT 1");
        $chk->bind_param("i", $matched_billing_id);
        $chk->execute();
        $bill = $chk->get_result()->fetch_assoc();
        $chk->close();

        if (!$bill) { $errors[] = "Billing not found id={$matched_billing_id}"; $skipped++; continue; }
        if (strtolower($bill['status']) === 'paid') { $skipped++; continue; }

        // detect payment method - best effort
        $payment_method = 'PayMongo';
        $source = $attr['source'] ?? ($attr['data']['attributes']['source'] ?? []);
        $stype = strtolower($source['type'] ?? ($attr['type'] ?? ''));
        $brand = $source['brand'] ?? ($metadata['brand'] ?? '');

        if (stripos($stype, 'e_wallet') !== false || stripos($brand, 'gcash') !== false || stripos(json_encode($source), 'gcash') !== false) {
            $payment_method = 'GCash' . ($brand ? " ({$brand})" : '');
        } elseif ($stype === 'card' || stripos($brand, 'visa') !== false || stripos($brand, 'mastercard') !== false) {
            $payment_method = 'Card' . ($brand ? " ({$brand})" : '');
        } elseif (!empty($stype)) {
            $payment_method = ucfirst($stype) . ($brand ? " ({$brand})" : '');
        }

        // update billing_records (mark Paid and save paymongo ids)
        $upd = $conn->prepare("
            UPDATE billing_records
            SET
                status = 'Paid',
                payment_method = ?,
                paymongo_payment_id = ?,
                paymongo_reference = ?,
                paid_amount = ?,
                balance = 0,
                payment_date = NOW()
            WHERE billing_id = ? AND status != 'Paid'
        ");
        $ref = $paymentId; // use payment id as reference
        $upd->bind_param("sssdi", $payment_method, $paymentId, $ref, $amount, $matched_billing_id);
        $upd->execute();
        $affected = $upd->affected_rows;
        $upd->close();

        // update patient_receipt (latest receipt for billing)
        $upd2 = $conn->prepare("
            UPDATE patient_receipt
            SET
                status = 'Paid',
                payment_method = ?,
                paymongo_reference = ?,
                total_out_of_pocket = ?,
                grand_total = ?
            WHERE billing_id = ? AND status != 'Paid'
        ");
        // types: s s d d i
        $upd2->bind_param("ssdii", $payment_method, $ref, $amount, $amount, $matched_billing_id);
        // If your grand_total fields are decimal, change binding accordingly.
        $upd2->execute();
        $upd2->close();

        if ($affected > 0) {
            $synced++;
        } else {
            $skipped++;
        }
    }

    $last = end($payload['data']);
    $startingAfter = $last['id'] ?? null;

} while ($startingAfter);

// return JSON summary
echo json_encode([
    'synced' => $synced,
    'skipped' => $skipped,
    'errors' => $errors
], JSON_PRETTY_PRINT);
