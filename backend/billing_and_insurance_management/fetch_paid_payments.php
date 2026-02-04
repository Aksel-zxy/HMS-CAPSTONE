<?php
require __DIR__ . '/vendor/autoload.php';

use GuzzleHttp\Client;
use Dotenv\Dotenv;

/* ===============================
   LOAD ENV
================================ */
$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

$secretKey = $_ENV['PAYMONGO_SECRET_KEY'] ?? null;

if (!$secretKey) {
    die('PAYMONGO_SECRET_KEY not found in .env');
}

/* ===============================
   PAYMONGO CLIENT
================================ */
$client = new Client([
    'base_uri' => 'https://api.paymongo.com/v1/',
    'headers' => [
        'Accept'        => 'application/json',
        'Authorization' => 'Basic ' . base64_encode($secretKey . ':'),
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
    die('PayMongo API Error: ' . $e->getMessage());
}

$payments = $body['data'] ?? [];

/* ===============================
   FILTER PAID PAYMENTS
================================ */
$paidPayments = array_filter($payments, function ($p) {
    return ($p['attributes']['status'] ?? '') === 'paid';
});
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
