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
   PAYMONGO CLIENT
================================ */
$paymongoClient = new Client([
    'base_uri' => PAYMONGO_API_BASE,
    'headers' => [
        'Accept'        => 'application/json',
        'Content-Type'  => 'application/json',
        'Authorization' => 'Basic ' . base64_encode(PAYMONGO_SECRET_KEY . ':'),
    ],
    'timeout' => 8
]);

/* ===============================
   PATIENT
================================ */
$patient_id = (int)($_GET['patient_id'] ?? 0);

$stmt = $conn->prepare("
    SELECT patient_id,
           CONCAT(fname,' ',IFNULL(mname,''),' ',lname) AS full_name
    FROM patientinfo
    WHERE patient_id = ?
");
$stmt->bind_param("i", $patient_id);
$stmt->execute();
$patient = $stmt->get_result()->fetch_assoc();
if (!$patient) die("Patient not found");

/* ===============================
   LATEST BILLING
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
if (!$billing) die("No billing record found");

$billing_id    = $billing['billing_id'];
$status        = $billing['status'];
$out_of_pocket = (float)$billing['out_of_pocket'];
$transaction_id = $billing['transaction_id'];
$existing_link  = $billing['paymongo_link_id'];

/* ===============================
   🔒 LOCK IF PAID
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
    SELECT bi.total_price, ds.serviceName, ds.description
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
   🔁 CREATE PAYMONGO LINK (ONCE)
================================ */
$payLinkUrl = null;

if ($out_of_pocket > 0) {

    // Reuse existing link
    if ($existing_link) {
        $payLinkUrl = "https://checkout.paymongo.com/links/$existing_link";
    } else {

        $payload = [
            'data' => [
                'attributes' => [
                    'amount'      => (int) round($out_of_pocket * 100),
                    'currency'    => 'PHP',
                    'description' => "Hospital Billing #{$billing_id}",
                    'remarks'     => "TXN:{$transaction_id}"
                ]
            ]
        ];

        $response = $paymongoClient->post('links', ['json' => $payload]);
        $body = json_decode($response->getBody(), true);

        $payLinkUrl = $body['data']['attributes']['checkout_url'] ?? null;
        $link_id    = $body['data']['id'] ?? null;

        if ($link_id) {

            // paymongo_payments
            $stmt = $conn->prepare("
                INSERT IGNORE INTO paymongo_payments
                (payment_id, amount, status, payment_method, remarks)
                VALUES (?, ?, 'Pending', 'PAYLINK', ?)
            ");
            $amount_php = $out_of_pocket;
            $desc = "Billing #$billing_id";
            $stmt->bind_param("sds", $link_id, $amount_php, $desc);
            $stmt->execute();

            // billing_records
            $stmt = $conn->prepare("
                UPDATE billing_records
                SET paymongo_link_id=?, paymongo_reference_number=?
                WHERE billing_id=?
            ");
            $stmt->bind_param("ssi", $link_id, $transaction_id, $billing_id);
            $stmt->execute();

            // patient_receipt
            $stmt = $conn->prepare("
                INSERT INTO patient_receipt
                (patient_id, billing_id, status, paymongo_reference, payment_reference)
                VALUES (?, ?, 'Pending', ?, ?)
                ON DUPLICATE KEY UPDATE paymongo_reference=VALUES(paymongo_reference)
            ");
            $stmt->bind_param("iiss", $patient_id, $billing_id, $link_id, $transaction_id);
            $stmt->execute();
        }
    }
}

/* ===============================
   🧾 PAYMENT HISTORY
================================ */
$history = [];
$stmt = $conn->prepare("
    SELECT payment_id, amount, status, paid_at
    FROM paymongo_payments
    WHERE remarks LIKE ?
    ORDER BY updated_at DESC
");
$like = "%$billing_id%";
$stmt->bind_param("s", $like);
$stmt->execute();
$history = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Billing Summary</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
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
    <a href="<?= htmlspecialchars($payLinkUrl) ?>" target="_blank"
       class="btn btn-primary btn-lg">
       Pay Now
    </a>
</div>

<?php endif; ?>

<hr>

<h5>Payment History</h5>
<table class="table table-sm table-bordered">
<thead>
<tr>
    <th>Reference</th>
    <th>Amount</th>
    <th>Status</th>
    <th>Paid At</th>
</tr>
</thead>
<tbody>
<?php if ($history): foreach ($history as $h): ?>
<tr>
    <td><?= htmlspecialchars($h['payment_id']) ?></td>
    <td>₱<?= number_format($h['amount'], 2) ?></td>
    <td><?= htmlspecialchars($h['status']) ?></td>
    <td><?= $h['paid_at'] ?: '-' ?></td>
</tr>
<?php endforeach; else: ?>
<tr><td colspan="4" class="text-center">No payments yet</td></tr>
<?php endif; ?>
</tbody>
</table>

</div>

<div class="main-sidebar">
<?php include 'billing_sidebar.php'; ?>
</div>

</div>

</body>
</html>
