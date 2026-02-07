<?php
include '../../SQL/config.php';
require __DIR__ . '/vendor/autoload.php';

use GuzzleHttp\Client;

header('Content-Type: application/json');

/* ===============================
   PAYMONGO CONFIG
================================ */
define('PAYMONGO_SECRET_KEY', 'sk_test_akT1ZW6za7m6FC9S9VqYNiVV');

$client = new Client([
    'base_uri' => 'https://api.paymongo.com/v1/',
    'headers' => [
        'Accept'        => 'application/json',
        'Authorization' => 'Basic ' . base64_encode(PAYMONGO_SECRET_KEY . ':'),
    ],
    'timeout' => 10
]);

/* ===============================
   FETCH PAYMENTS
================================ */
try {
    $response = $client->get('payments?limit=50');
    $body = json_decode($response->getBody(), true);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'PayMongo API error']);
    exit;
}

$payments = $body['data'] ?? [];

/* ===============================
   FILTER PAID PAYMENTS
================================ */
$paidPayments = array_filter($payments, function ($p) {
    return ($p['attributes']['status'] ?? '') === 'paid';
});

/* ===============================
   UPSERT PAYMONGO PAYMENTS
================================ */
$pmStmt = $conn->prepare("
    INSERT INTO paymongo_payments
        (payment_id, payment_intent_id, amount, remarks, payment_method, status, paid_at)
    VALUES (?, ?, ?, ?, ?, ?, ?)
    ON DUPLICATE KEY UPDATE
        payment_intent_id = VALUES(payment_intent_id),
        amount = VALUES(amount),
        remarks = VALUES(remarks),
        payment_method = VALUES(payment_method),
        status = VALUES(status),
        paid_at = VALUES(paid_at)
");

/* ===============================
   PROCESS PAYMENTS
================================ */
$processed = 0;

foreach ($paidPayments as $p) {

    $attr = $p['attributes'];

    $payment_id = $p['id'];
    $intent_id  = $attr['payment_intent_id'] ?? null;
    $amount     = ($attr['amount'] ?? 0) / 100;
    $remarks    = $attr['remarks'] ?? $attr['description'] ?? null;

    // PayMongo payment method (gcash, grab_pay, maya, card, etc.)
    $method = strtoupper($attr['source']['type'] ?? 'PAYMONGO');

    $status  = $attr['status'];
    $paid_at = date(
        'Y-m-d H:i:s',
        strtotime($attr['paid_at'] ?? $attr['created_at'])
    );

    /* Save PayMongo payment */
    $pmStmt->bind_param(
        "ssdssss",
        $payment_id,
        $intent_id,
        $amount,
        $remarks,
        $method,
        $status,
        $paid_at
    );
    $pmStmt->execute();

    /* ===============================
       FIND MATCHING RECEIPT
    ================================ */
    $rStmt = $conn->prepare("
        SELECT
            receipt_id,
            patient_id,
            billing_id,
            grand_total,
            transaction_id,
            status
        FROM patient_receipt
        WHERE paymongo_reference = ?
        LIMIT 1
    ");
    $rStmt->bind_param("s", $payment_id);
    $rStmt->execute();
    $receipt = $rStmt->get_result()->fetch_assoc();

    if (!$receipt) {
        continue;
    }

    /* ===============================
       UPDATE RECEIPT + BILLING
    ================================ */
    if ($receipt['status'] !== 'Paid') {

        $billing_id = (int)$receipt['billing_id'];

        // Update billing_records
        $bStmt = $conn->prepare("
            UPDATE billing_records
            SET status='Paid',
                payment_method=?
            WHERE billing_id=?
        ");
        $bStmt->bind_param("si", $method, $billing_id);
        $bStmt->execute();

        // Update patient_receipt
        $uStmt = $conn->prepare("
            UPDATE patient_receipt
            SET status='Paid',
                payment_method=?,
                payment_reference=?
            WHERE receipt_id=?
        ");
        $uStmt->bind_param(
            "ssi",
            $method,
            $payment_id,
            $receipt['receipt_id']
        );
        $uStmt->execute();
    }

    /* ===============================
       JOURNAL ENTRY (ONCE ONLY)
    ================================ */
    $txnRef = $receipt['transaction_id'];
    $total  = (float)$receipt['grand_total'];

    if (!$txnRef || $total <= 0) {
        continue;
    }

    // Prevent duplicate journal entries
    $chk = $conn->prepare("
        SELECT entry_id
        FROM journal_entries
        WHERE reference = ?
        LIMIT 1
    ");
    $chk->bind_param("s", $txnRef);
    $chk->execute();
    if ($chk->get_result()->fetch_assoc()) {
        continue;
    }

    $desc = "Payment received for Patient ID {$receipt['patient_id']}. Receipt TXN: {$txnRef}";

    // Journal entry header
    $jeStmt = $conn->prepare("
        INSERT INTO journal_entries
            (entry_date, description, reference_type, reference, status, module, created_by)
        VALUES
            (CURDATE(), ?, 'Patient Billing', ?, 'Posted', 'billing', 'System')
    ");
    $jeStmt->bind_param("ss", $desc, $txnRef);
    $jeStmt->execute();

    $entry_id = $conn->insert_id;

    // Journal entry lines
    $jlStmt = $conn->prepare("
        INSERT INTO journal_entry_lines
            (entry_id, account_name, debit, credit, description)
        VALUES (?, ?, ?, ?, ?)
    ");

    $zero = 0.00;

    // Debit Cash
    $acct = 'Cash';
    $jlStmt->bind_param("isdds", $entry_id, $acct, $total, $zero, $desc);
    $jlStmt->execute();

    // Credit Accounts Receivable
    $acct = 'Accounts Receivable';
    $jlStmt->bind_param("isdds", $entry_id, $acct, $zero, $total, $desc);
    $jlStmt->execute();

    $processed++;
}

echo json_encode([
    'status'    => 'ok',
    'processed' => $processed
]);



?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Paid PayMongo Payments</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
</head>

<body class="p-4">

<h3>Paid Payments (PayMongo)</h3>

<table class="table table-bordered table-striped">
<thead class="table-dark">
<tr>
    <th>Payment ID</th>
    <th>Intent ID</th>
    <th>Amount</th>
    <th>Reference / Remarks</th>
    <th>Date Paid</th>
</tr>
</thead>
<tbody>

<?php if ($paidPayments): ?>
<?php foreach ($paidPayments as $p): ?>
<?php
    $attr = $p['attributes'];
    $amount = ($attr['amount'] ?? 0) / 100;
    $remarks = $attr['remarks'] ?? $attr['description'] ?? '—';
?>
<tr>
    <td><?= htmlspecialchars($p['id']) ?></td>
    <td><?= htmlspecialchars($attr['payment_intent_id'] ?? '—') ?></td>
    <td>₱ <?= number_format($amount, 2) ?></td>
    <td><?= htmlspecialchars($remarks) ?></td>
    <td><?= htmlspecialchars($attr['paid_at'] ?? $attr['created_at']) ?></td>
</tr>
<?php endforeach; ?>
<?php else: ?>
<tr>
    <td colspan="5" class="text-center">No paid payments found</td>
</tr>
<?php endif; ?>

</tbody>
</table>

</body>
</html>
