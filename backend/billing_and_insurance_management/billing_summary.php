<?php
include '../../SQL/config.php';
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/vendor/autoload.php';
use GuzzleHttp\Client;

/* ===============================
   PAYMONGO CONFIG
================================ */
define('PAYMONGO_SECRET_KEY', 'sk_test_akT1ZW6za7m6FC9S9VqYNiVV');
define('PAYMONGO_API_BASE', 'https://api.paymongo.com/v1/');

/* ===============================
   CREATE PAYMONGO PAYMENT LINK
================================ */
function create_paymongo_payment_link($amount_centavos, $billing_id, $patient_id, $transaction_id)
{
    $client = new Client([
        'base_uri' => PAYMONGO_API_BASE,
        'headers' => [
            'Accept'        => 'application/json',
            'Content-Type'  => 'application/json',
            'Authorization' => 'Basic ' . base64_encode(PAYMONGO_SECRET_KEY . ':'),
        ]
    ]);

    $payload = [
        'data' => [
            'attributes' => [
                'amount'      => $amount_centavos,
                'currency'    => 'PHP',
                'description' => "Hospital Billing #{$billing_id}",
                'remarks'     => "Hospital Billing | TXN:{$transaction_id}"
            ]
        ]
    ];

    try {
        $response = $client->post('links', ['json' => $payload]);
        $body = json_decode($response->getBody(), true);
        return $body['data']['attributes']['checkout_url'] ?? null;
    } catch (Exception $e) {
        error_log('PayMongo link error: ' . $e->getMessage());
        return null;
    }
}

/* ===============================
   PATIENT
================================ */
$patient_id = intval($_GET['patient_id'] ?? 0);

$stmt = $conn->prepare("
    SELECT patient_id,
           CONCAT(fname,' ',IFNULL(mname,''),' ',lname) AS full_name
    FROM patientinfo
    WHERE patient_id = ?
");
$stmt->bind_param("i", $patient_id);
$stmt->execute();
$patient = $stmt->get_result()->fetch_assoc();

if (!$patient) {
    die("Patient not found.");
}

/* ===============================
   LATEST BILLING RECORD
================================ */
$stmt = $conn->prepare("
    SELECT *
    FROM billing_records
    WHERE patient_id = ?
    ORDER BY billing_id DESC
    LIMIT 1
");
$stmt->bind_param("i", $patient_id);
$stmt->execute();
$billing = $stmt->get_result()->fetch_assoc();

if (!$billing) {
    die("No finalized billing found.");
}

$billing_id     = $billing['billing_id'];
$out_of_pocket  = (float)$billing['out_of_pocket'];
$status         = $billing['status'];
$transaction_id = $billing['transaction_id'];

/* ===============================
   🚨 REDIRECT IF ALREADY PAID
================================ */
if ($status === 'Paid') {
    header("Location: patient_billing.php");
    exit;
}

/* ===============================
   BILLING ITEMS
================================ */
$items = [];
$subtotal = 0;

$stmt = $conn->prepare("
    SELECT bi.quantity,
           bi.total_price,
           ds.serviceName,
           ds.description
    FROM billing_items bi
    LEFT JOIN dl_services ds ON bi.service_id = ds.serviceID
    WHERE bi.billing_id = ?
");
$stmt->bind_param("i", $billing_id);
$stmt->execute();
$res = $stmt->get_result();

while ($row = $res->fetch_assoc()) {
    $items[] = $row;
    $subtotal += (float)$row['total_price'];
}

/* ===============================
   PAYMONGO LINK
================================ */
$payLinkUrl = null;
if ($out_of_pocket > 0 && $transaction_id) {
    $payLinkUrl = create_paymongo_payment_link(
        (int) round($out_of_pocket * 100),
        $billing_id,
        $patient_id,
        $transaction_id
    );
}
?>

<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Billing Summary</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

<script>
/* ===============================
   JS SAFETY REDIRECT (CACHE CASE)
================================ */
<?php if ($status === 'Paid'): ?>
setTimeout(() => {
    window.location.href = 'patient_billing.php';
}, 1000);
<?php endif; ?>
</script>

</head>

<body class="bg-light p-4">
<div class="container">
<div class="card shadow p-4">

<h4>Billing Summary</h4>
<p><strong>Patient:</strong> <?= htmlspecialchars($patient['full_name']) ?></p>
<p><strong>Transaction ID:</strong> <?= htmlspecialchars($transaction_id) ?></p>

<table class="table table-bordered">
<thead class="table-primary">
<tr>
    <th>Service</th>
    <th>Description</th>
    <th class="text-end">Amount</th>
</tr>
</thead>
<tbody>
<?php foreach ($items as $item): ?>
<tr>
    <td><?= htmlspecialchars($item['serviceName']) ?></td>
    <td><?= htmlspecialchars($item['description']) ?></td>
    <td class="text-end">₱<?= number_format($item['total_price'], 2) ?></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>

<table class="table">
<tr>
    <th>Subtotal</th>
    <td class="text-end">₱<?= number_format($subtotal, 2) ?></td>
</tr>
<tr class="table-success">
    <th>Amount to Pay</th>
    <td class="text-end fw-bold">₱<?= number_format($out_of_pocket, 2) ?></td>
</tr>
</table>

<?php if ($out_of_pocket <= 0): ?>
<div class="alert alert-success text-center">
    ✅ Fully covered — no payment required
</div>

<?php elseif ($payLinkUrl): ?>
<div class="text-center">
    <a href="<?= htmlspecialchars($payLinkUrl) ?>" class="btn btn-primary btn-lg" target="_blank" rel="noopener noreferrer">
    Pay Now
</a>
</div>
<p class="text-muted small mt-3">
    After completing payment, you’ll be redirected automatically.
</p>
<?php else: ?>
<div class="alert alert-warning text-center">
    Unable to generate payment link. Please contact billing.
</div>
<?php endif; ?>

<div class="main-sidebar">
<?php include 'billing_sidebar.php'; ?>
</div>

</div>
</div>
</body>
</html>
