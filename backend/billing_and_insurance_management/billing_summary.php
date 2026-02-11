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
   GET PATIENT
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
   GET LATEST BILLING
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

$billing_id     = $billing['billing_id'];
$status         = $billing['status'];
$insurance      = (float)$billing['insurance_covered'];
$transaction_id = $billing['transaction_id'];
$existing_link  = $billing['paymongo_link_id'];

if ($status === 'Paid') {
    header("Location: patient_billing.php");
    exit;
}

/* ===============================
   GET BILLING ITEMS
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
   CHECK IF RECEIPT EXISTS
================================ */
$stmt = $conn->prepare("
    SELECT receipt_id, is_pwd
    FROM patient_receipt
    WHERE billing_id = ?
    LIMIT 1
");
$stmt->bind_param("i", $billing_id);
$stmt->execute();
$existing_receipt = $stmt->get_result()->fetch_assoc();

$is_pwd = $existing_receipt ? (int)$existing_receipt['is_pwd'] : 0;

/* ===============================
   DISCOUNT COMPUTATION (NO VAT)
================================ */
$discount_percentage = 0;
$discount = 0;

if ($is_pwd == 1) {
    $discount_percentage = 20; // PWD/Senior Discount is now 20%
    $discount = $subtotal * ($discount_percentage / 100);
}

$grand_total = $subtotal - $discount - $insurance;
if ($grand_total < 0) $grand_total = 0;

/* ===============================
   INSERT OR UPDATE RECEIPT
================================ */
if (!$existing_receipt) {

    $stmt = $conn->prepare("
        INSERT INTO patient_receipt
        (
            patient_id,
            billing_id,
            total_charges,
            total_vat,
            total_discount,
            total_out_of_pocket,
            grand_total,
            insurance_covered,
            billing_date,
            payment_method,
            status,
            transaction_id,
            is_pwd
        )
        VALUES (?, ?, ?, 0, ?, ?, ?, ?, CURDATE(),
                'GCASH', 'Pending', ?, ?)
    ");

    $stmt->bind_param(
        "iidddddssi",
        $patient_id,
        $billing_id,
        $subtotal,
        $discount,
        $grand_total,
        $grand_total,
        $insurance,
        $transaction_id,
        $is_pwd
    );

    $stmt->execute();

} else {

    $stmt = $conn->prepare("
        UPDATE patient_receipt
        SET total_charges = ?,
            total_vat = 0,
            total_discount = ?,
            total_out_of_pocket = ?,
            grand_total = ?,
            insurance_covered = ?
        WHERE billing_id = ?
    ");

    $stmt->bind_param(
        "dddddi",
        $subtotal,
        $discount,
        $grand_total,
        $grand_total,
        $insurance,
        $billing_id
    );

    $stmt->execute();
}

/* ===============================
   PAYMONGO LINK
================================ */
$payLinkUrl = null;

if ($grand_total > 0) {

    if ($existing_link) {

        $payLinkUrl = "https://checkout.paymongo.com/links/$existing_link";

    } else {

        $payload = [
            'data' => [
                'attributes' => [
                    'amount'      => (int) round($grand_total * 100),
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
            $stmt = $conn->prepare("
                UPDATE billing_records
                SET paymongo_link_id = ?
                WHERE billing_id = ?
            ");
            $stmt->bind_param("si", $link_id, $billing_id);
            $stmt->execute();
        }
    }
}
?>

<!doctype html>
<html>
<head>
<meta charset="utf-8">
<title>Billing Summary</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light p-4">


<div class="main-sidebar">
<?php include 'billing_sidebar.php'; ?>
</div>

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

<?php if ($discount > 0): ?>
<tr class="table-warning">
    <th>PWD/Senior Discount <?= $discount_percentage ?>%</th>
    <td class="text-end">- ₱<?= number_format($discount, 2) ?></td>
</tr>
<?php endif; ?>

<?php if ($insurance > 0): ?>
<tr class="table-info">
    <th>Insurance Discount</th>
    <td class="text-end">- ₱<?= number_format($insurance, 2) ?></td>
</tr>
<?php endif; ?>

<tr class="table-success">
    <th>Amount to Pay</th>
    <td class="text-end fw-bold">₱<?= number_format($grand_total, 2) ?></td>
</tr>
</table>

<?php if ($grand_total <= 0): ?>
<div class="alert alert-success text-center">
    Fully Covered — No Payment Required
</div>
<?php elseif ($payLinkUrl): ?>
<div class="text-center">
    <a href="<?= htmlspecialchars($payLinkUrl) ?>" target="_blank"
       class="btn btn-primary btn-lg">
       Pay Now
    </a>
</div>
<?php endif; ?>

</div>
</div>

</body>
</html>
