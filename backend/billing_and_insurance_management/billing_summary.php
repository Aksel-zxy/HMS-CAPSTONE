<?php
include '../../SQL/config.php';
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/vendor/autoload.php';
use GuzzleHttp\Client;

define('PAYMONGO_SECRET_KEY', 'sk_test_akT1ZW6za7m6FC9S9VqYNiVV');
define('PAYMONGO_API_BASE', 'https://api.paymongo.com/v1/');
define('PAYMONGO_PAYMENT_API', 'https://api.paymongo.com/v1/payments');

/* =====================================================
   PAYMONGO LINK CREATOR
===================================================== */
function create_paymongo_payment_link($amount, $billing_id, $patient_id, $transaction_id)
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
                'amount'      => $amount,
                'currency'    => 'PHP',
                'description' => "Billing #$billing_id - Patient #$patient_id",
                'remarks'     => $transaction_id
            ]
        ]
    ];

    try {
        $response = $client->post('links', ['json' => $payload]);
        $body = json_decode($response->getBody(), true);
        return $body['data']['attributes']['checkout_url'];
    } catch (Exception $e) {
        return null;
    }
}

/* =====================================================
   PATIENT
===================================================== */
$patient_id = intval($_GET['patient_id'] ?? 0);

$stmt = $conn->prepare("
    SELECT patient_id, CONCAT(fname,' ',IFNULL(mname,''),' ',lname) AS full_name
    FROM patientinfo WHERE patient_id = ?
");
$stmt->bind_param("i", $patient_id);
$stmt->execute();
$patient = $stmt->get_result()->fetch_assoc();

if (!$patient) {
    die("Patient not found.");
}

/* =====================================================
   GET LATEST FINALIZED BILLING
===================================================== */
$stmt = $conn->prepare("
    SELECT MAX(billing_id) AS billing_id
    FROM billing_items
    WHERE patient_id = ? AND finalized = 1
");
$stmt->bind_param("i", $patient_id);
$stmt->execute();
$billing_id = $stmt->get_result()->fetch_assoc()['billing_id'];

if (!$billing_id) {
    die("No finalized billing found.");
}

/* =====================================================
   BILLING ITEMS
===================================================== */
$items = [];
$total_charges = 0;

$stmt = $conn->prepare("
    SELECT bi.quantity, bi.total_price, ds.serviceName, ds.description
    FROM billing_items bi
    LEFT JOIN dl_services ds ON bi.service_id = ds.serviceID
    WHERE bi.billing_id = ?
");
$stmt->bind_param("i", $billing_id);
$stmt->execute();
$res = $stmt->get_result();

while ($row = $res->fetch_assoc()) {
    $items[] = $row;
    $total_charges += (float)$row['total_price'];
}

/* =====================================================
   BILLING RECORD
===================================================== */
$insurance_covered = 0;
$out_of_pocket = $total_charges;
$status = 'Pending';
$transaction_id = null;

$stmt = $conn->prepare("
    SELECT insurance_covered, out_of_pocket, status, transaction_id
    FROM billing_records
    WHERE patient_id = ? AND billing_id = ?
    LIMIT 1
");
$stmt->bind_param("ii", $patient_id, $billing_id);
$stmt->execute();
$record = $stmt->get_result()->fetch_assoc();

if ($record) {
    $insurance_covered = (float)$record['insurance_covered'];
    $out_of_pocket     = (float)$record['out_of_pocket'];
    $status            = $record['status'];
    $transaction_id    = $record['transaction_id'];
}

/* =====================================================
   AUTO REDIRECT IF PAID
===================================================== */
if ($status === 'Paid') {
    echo "<script>
        setTimeout(() => {
            window.location.href = 'patient_billing.php';
        }, 2000);
    </script>";
}

/* =====================================================
   CREATE TRANSACTION ID
===================================================== */
if (!$transaction_id && $out_of_pocket > 0) {
    $transaction_id = 'TXN' . strtoupper(bin2hex(random_bytes(5)));
    $stmt = $conn->prepare("
        UPDATE billing_records
        SET transaction_id = ?
        WHERE patient_id = ? AND billing_id = ?
    ");
    $stmt->bind_param("sii", $transaction_id, $patient_id, $billing_id);
    $stmt->execute();
}

/* =====================================================
   PAYMONGO LINK
===================================================== */
$payLinkUrl = null;
if ($status !== 'Paid' && $out_of_pocket > 0) {
    $payLinkUrl = create_paymongo_payment_link(
        (int)round($out_of_pocket * 100),
        $billing_id,
        $patient_id,
        $transaction_id
    );
}

/* =====================================================
   MANUAL SYNC: CHECK PAYMONGO FOR LATEST STATUS
===================================================== */
$client = new Client([
    'headers' => [
        'Accept'        => 'application/json',
        'Authorization' => 'Basic ' . base64_encode(PAYMONGO_SECRET_KEY . ':'),
    ],
    'timeout' => 5
]);

$stmt = $conn->prepare("
    SELECT paymongo_payment_id FROM billing_records
    WHERE billing_id = ? AND paymongo_payment_id IS NOT NULL
");
$stmt->bind_param("i", $billing_id);
$stmt->execute();
$payment_record = $stmt->get_result()->fetch_assoc();

if ($payment_record && $payment_record['paymongo_payment_id']) {
    try {
        $response = $client->get(PAYMONGO_PAYMENT_API . '/' . $payment_record['paymongo_payment_id']);
        $payment = json_decode($response->getBody(), true);

        if (isset($payment['data']['attributes']['status']) && 
            $payment['data']['attributes']['status'] === 'paid') {
            
            $stmt = $conn->prepare("
                UPDATE billing_records
                SET status = 'Paid'
                WHERE billing_id = ?
            ");
            $stmt->bind_param("i", $billing_id);
            $stmt->execute();

            $stmt = $conn->prepare("
                UPDATE patient_receipt
                SET status = 'Paid'
                WHERE billing_id = ?
            ");
            $stmt->bind_param("i", $billing_id);
            $stmt->execute();

            $status = 'Paid';
        }
    } catch (Exception $e) {
        error_log("Manual sync failed: " . $e->getMessage());
    }
}
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
    <td class="text-end">₱<?= number_format($item['total_price'],2) ?></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>

<table class="table">
<tr>
    <th>Subtotal</th>
    <td class="text-end">₱<?= number_format($total_charges,2) ?></td>
</tr>
<tr>
    <th>Insurance Covered</th>
    <td class="text-end text-success">- ₱<?= number_format($insurance_covered,2) ?></td>
</tr>
<tr class="table-success">
    <th>Amount to Pay</th>
    <td class="text-end fw-bold">₱<?= number_format($out_of_pocket,2) ?></td>
</tr>
</table>

<?php if ($status === 'Paid'): ?>
<div class="alert alert-success text-center">
    Payment completed. Redirecting to billing list…
</div>

<?php elseif ($out_of_pocket <= 0): ?>
<div class="alert alert-success text-center">
    Fully covered by insurance. No payment required.
</div>

<?php elseif ($payLinkUrl): ?>
<div class="text-center">
    <a href="<?= htmlspecialchars($payLinkUrl) ?>" class="btn btn-primary btn-lg">
        Pay Now
    </a>
</div>
<p class="text-muted small mt-3">You will be redirected back after payment. If not, <a href="patient_billing.php">click here</a>.</p>
<?php endif; ?>

</div>
</div>

<script>
// Auto-check payment status every 3 seconds if not paid
<?php if ($status !== 'Paid' && $out_of_pocket > 0): ?>
let checkCount = 0;
const checkInterval = setInterval(() => {
    fetch(window.location.href)
        .then(r => r.text())
        .then(html => {
            if (html.includes('Payment completed') || html.includes('Fully covered')) {
                clearInterval(checkInterval);
                window.location.reload();
            }
            checkCount++;
            if (checkCount > 20) clearInterval(checkInterval);
        });
}, 3000);
<?php endif; ?>
</script>
</body>
</html>
