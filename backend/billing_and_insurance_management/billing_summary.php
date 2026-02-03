<?php
// billing_summary.php

include '../../SQL/config.php';
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/vendor/autoload.php';
use GuzzleHttp\Client;

// ==============================
// PAYMONGO CONFIG
// ==============================
define('PAYMONGO_SECRET_KEY', 'sk_test_akT1ZW6za7m6FC9S9VqYNiVV');
define('PAYMONGO_API_BASE', 'https://api.paymongo.com/v1/');

// ==============================
// CREATE PAYMONGO PAYMENT LINK
// ==============================
function create_paymongo_payment_link($amount, $billing_id, $patient_id, $transaction_id)
{
    $payload = [
        'data' => [
            'attributes' => [
                'amount'      => $amount,
                'currency'    => 'PHP',
                'description' => "Billing #$billing_id - Patient $patient_id",
                'remarks'     => "TXN:$transaction_id"
            ]
        ]
    ];

    try {
        $client = new Client([
            'base_uri' => PAYMONGO_API_BASE,
            'headers' => [
                'Accept'        => 'application/json',
                'Content-Type'  => 'application/json',
                'Authorization' => 'Basic ' . base64_encode(PAYMONGO_SECRET_KEY . ':'),
            ]
        ]);

        $response = $client->post('links', ['json' => $payload]);
        $body = json_decode($response->getBody(), true);

        return [
            'success' => true,
            'url' => $body['data']['attributes']['checkout_url']
        ];
    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

// ==============================
// PATIENT & BILLING LOOKUP
// ==============================
$patient_id = intval($_GET['patient_id'] ?? 0);

// Patient info
$stmt = $conn->prepare("
    SELECT *, CONCAT(fname,' ',IFNULL(mname,''),' ',lname) AS full_name
    FROM patientinfo
    WHERE patient_id = ?
");
$stmt->bind_param("i", $patient_id);
$stmt->execute();
$patient = $stmt->get_result()->fetch_assoc();

// Latest finalized billing
$stmt = $conn->prepare("
    SELECT MAX(billing_id) AS billing_id
    FROM billing_items
    WHERE patient_id = ? AND finalized = 1
");
$stmt->bind_param("i", $patient_id);
$stmt->execute();
$billing_id = $stmt->get_result()->fetch_assoc()['billing_id'] ?? null;

// ==============================
// BILLING ITEMS
// ==============================
$items = [];
$total = 0;

if ($billing_id) {
    $stmt = $conn->prepare("
        SELECT bi.*, ds.serviceName, ds.description
        FROM billing_items bi
        LEFT JOIN dl_services ds ON bi.service_id = ds.serviceID
        WHERE bi.patient_id = ? AND bi.billing_id = ?
    ");
    $stmt->bind_param("ii", $patient_id, $billing_id);
    $stmt->execute();

    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $items[] = $row;
        $total += (float)$row['total_price'];
    }
}

// ==============================
// BILLING RECORD VALUES
// ==============================
$insurance_covered = 0;
$out_of_pocket = 0;
$grand_total = 0;
$status = 'Pending';
$transaction_id = null;

if ($billing_id) {
    $stmt = $conn->prepare("
        SELECT insurance_covered, out_of_pocket, grand_total, status, transaction_id
        FROM billing_records
        WHERE patient_id = ? AND billing_id = ?
        LIMIT 1
    ");
    $stmt->bind_param("ii", $patient_id, $billing_id);
    $stmt->execute();
    $br = $stmt->get_result()->fetch_assoc();

    if ($br) {
        $insurance_covered = (float)$br['insurance_covered'];
        $out_of_pocket     = (float)$br['out_of_pocket'];
        $grand_total       = (float)$br['grand_total'];
        $status            = $br['status'];
        $transaction_id    = $br['transaction_id'];
    }
}

// Amount to pay
$payableAmount = ($out_of_pocket > 0) ? $out_of_pocket : $total;

// ==============================
// CREATE PAYMONGO PAYMENT LINK
// ==============================
$payLinkUrl = null;
$linkError = null;

if ($billing_id && $payableAmount > 0 && $status !== 'Paid' && $transaction_id) {
    $link = create_paymongo_payment_link(
        (int)($payableAmount * 100),
        $billing_id,
        $patient_id,
        $transaction_id
    );

    if ($link['success']) {
        $payLinkUrl = $link['url'];
    } else {
        $linkError = $link['error'];
    }
}
?>

<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Billing Summary</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body class="bg-light p-4">
<div class="container">
<div class="card p-4 shadow-sm">

<h4 class="mb-3">Billing Summary</h4>

<p><strong>Patient:</strong> <?= htmlspecialchars($patient['full_name'] ?? 'N/A') ?></p>

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

<table class="table mt-3">
<tr>
    <th>Subtotal</th>
    <td class="text-end">₱<?= number_format($total, 2) ?></td>
</tr>

<?php if ($insurance_covered > 0): ?>
<tr>
    <th>Insurance Covered</th>
    <td class="text-end text-success">
        - ₱<?= number_format($insurance_covered, 2) ?>
    </td>
</tr>
<?php endif; ?>

<tr class="table-success">
    <th>Amount to Pay</th>
    <td class="text-end fw-bold">
        ₱<?= number_format($payableAmount, 2) ?>
    </td>
</tr>
</table>

<?php if ($status === 'Paid'): ?>
<div class="alert alert-success text-center">
    Payment completed successfully.
</div>

<?php elseif ($payableAmount <= 0): ?>
<div class="alert alert-success text-center">
    Fully covered by insurance. No payment required.
</div>

<?php elseif ($payLinkUrl): ?>
<div class="text-center mt-3">
    <a href="<?= htmlspecialchars($payLinkUrl) ?>" target="_blank" class="btn btn-primary btn-lg">
        Pay Now
    </a>
</div>

<?php elseif ($linkError): ?>
<div class="alert alert-danger"><?= htmlspecialchars($linkError) ?></div>
<?php endif; ?>

</div>
</div>
</body>
</html>
