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

$billing_id     = $billing['billing_id'];
$status         = $billing['status'];
$transaction_id = $billing['transaction_id'];
$existing_link  = $billing['paymongo_link_id'];

/* ===============================
   FETCH PATIENT RECEIPT (for discounts)
================================ */
$receipt = null;
$stmt = $conn->prepare("
    SELECT *
    FROM patient_receipt
    WHERE billing_id = ? AND patient_id = ?
    LIMIT 1
");
$stmt->bind_param("ii", $billing_id, $patient_id);
$stmt->execute();
$receipt = $stmt->get_result()->fetch_assoc();

/* ===============================
   🔒 LOCK IF PAID
================================ */
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
   CALCULATE AMOUNTS FROM RECEIPT
================================ */
$pwd_discount = 0;
$insurance_covered = 0;
$grand_total = $subtotal;
$out_of_pocket = $subtotal;

if ($receipt) {
    // Fetch from patient_receipt (where apply_insurance.php stores the data)
    $pwd_discount = (float)($receipt['total_discount'] ?? 0);
    $insurance_covered = (float)($receipt['insurance_covered'] ?? 0);
    $grand_total = (float)($receipt['grand_total'] ?? $subtotal - $pwd_discount - $insurance_covered);
    $out_of_pocket = (float)($receipt['total_out_of_pocket'] ?? $grand_total);
} else {
    // Fallback to billing_records if no receipt found
    $pwd_discount = (float)($billing['total_discount'] ?? 0);
    $insurance_covered = (float)($billing['insurance_covered'] ?? 0);
    $grand_total = (float)($billing['grand_total'] ?? $subtotal - $pwd_discount - $insurance_covered);
    $out_of_pocket = (float)($billing['out_of_pocket'] ?? $grand_total);
}

// Ensure out_of_pocket doesn't go negative
if ($out_of_pocket < 0) $out_of_pocket = 0;
if ($grand_total < 0) $grand_total = 0;

/* ===============================
   🔒 AUTO-MARK AS PAID IF FULLY COVERED
================================ */
if ($grand_total <= 0 && $status !== 'Paid') {
    $stmt = $conn->prepare("
        UPDATE billing_records
        SET status = 'Paid'
        WHERE billing_id = ?
    ");
    $stmt->bind_param("i", $billing_id);
    $stmt->execute();
    
    // Also update patient_receipt
    $stmt = $conn->prepare("
        UPDATE patient_receipt
        SET status = 'Paid'
        WHERE billing_id = ? AND patient_id = ?
    ");
    $stmt->bind_param("ii", $billing_id, $patient_id);
    $stmt->execute();
    
    $status = 'Paid';
}

/* ===============================
   HANDLE CASH PAYMENT
================================ */
if (isset($_POST['payment_method']) && $_POST['payment_method'] === 'cash' && $grand_total > 0) {
    $stmt = $conn->prepare("
        INSERT INTO paymongo_payments
        (payment_id, amount, status, payment_method, remarks)
        VALUES (?, ?, 'Paid', 'CASH', ?)
    ");
    $cash_reference = 'CASH-' . time();
    $desc = "Billing #$billing_id - Cash Payment";
    $stmt->bind_param("sds", $cash_reference, $grand_total, $desc);
    $stmt->execute();
    
    // Update billing_records
    $stmt = $conn->prepare("
        UPDATE billing_records
        SET status = 'Paid', payment_method = 'Cash'
        WHERE billing_id = ?
    ");
    $stmt->bind_param("i", $billing_id);
    $stmt->execute();
    
    // Update patient_receipt
    $stmt = $conn->prepare("
        UPDATE patient_receipt
        SET status = 'Paid', payment_reference = ?
        WHERE billing_id = ? AND patient_id = ?
    ");
    $stmt->bind_param("sii", $cash_reference, $billing_id, $patient_id);
    $stmt->execute();
    
    header("Location: patient_billing.php?success=cash_payment");
    exit;
}

/* ===============================
   🔁 CREATE PAYMONGO LINK (ONCE)
================================ */
$payLinkUrl = null;

if ($grand_total > 0) {

    // Reuse existing link
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

            // paymongo_payments
            $stmt = $conn->prepare("
                INSERT IGNORE INTO paymongo_payments
                (payment_id, amount, status, payment_method, remarks)
                VALUES (?, ?, 'Pending', 'PAYLINK', ?)
            ");
            $amount_php = $grand_total;
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
<html>
<head>
<meta charset="utf-8">
<title>Billing Summary</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<style>
    .payment-method-btn {
        transition: all 0.3s ease;
    }
    .payment-method-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(0,0,0,0.1);
    }
</style>
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

<table class="table table-sm">
<tr>
    <th>Subtotal</th>
    <td class="text-end">₱<?= number_format($subtotal, 2) ?></td>
</tr>

<?php if ($pwd_discount > 0): ?>
<tr class="table-warning">
    <th>PWD/Senior Discount (20%)</th>
    <td class="text-end">- ₱<?= number_format($pwd_discount, 2) ?></td>
</tr>
<?php endif; ?>

<?php if ($insurance_covered > 0): ?>
<tr class="table-info">
    <th>Insurance Coverage</th>
    <td class="text-end">- ₱<?= number_format($insurance_covered, 2) ?></td>
</tr>
<?php endif; ?>

<tr class="table-success fw-bold">
    <th>Amount to Pay</th>
    <td class="text-end">₱<?= number_format($grand_total, 2) ?></td>
</tr>
</table>

<?php if ($grand_total <= 0): ?>
<div class="alert alert-success text-center">
    <i class="bi bi-check-circle-fill"></i>
    <strong>Fully Covered</strong> — No Payment Required
    <br><small class="text-muted">Status: <span class="badge bg-success">PAID</span></small>
</div>
<?php elseif ($payLinkUrl): ?>
<div class="card border-primary mb-4">
    <div class="card-body">
        <h5 class="card-title">Select Payment Method</h5>
        <div class="row g-3">
            <!-- Online Payment -->
            <div class="col-md-6">
                <a href="<?= htmlspecialchars($payLinkUrl) ?>" target="_blank"
                   class="btn btn-primary btn-lg w-100 payment-method-btn">
                   <i class="bi bi-credit-card"></i> 
                   <br>Pay Online
                   <br><small>₱<?= number_format($grand_total, 2) ?></small>
                </a>
                <small class="text-muted d-block text-center mt-2">
                    Card, GCash, GrabPay, Bank Transfer
                </small>
            </div>
            
            <!-- Cash Payment -->
            <div class="col-md-6">
                <form method="POST" onsubmit="return confirm('Confirm cash payment of ₱<?= number_format($grand_total, 2) ?>?');">
                    <input type="hidden" name="payment_method" value="cash">
                    <button type="submit" class="btn btn-success btn-lg w-100 payment-method-btn">
                        <i class="bi bi-cash-coin"></i> 
                        <br>Pay with Cash
                        <br><small>₱<?= number_format($grand_total, 2) ?></small>
                    </button>
                </form>
                <small class="text-muted d-block text-center mt-2">
                    Pay at cashier/office
                </small>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<hr>

<h5>Payment History</h5>
<table class="table table-sm table-bordered">
<thead>
<tr>
    <th>Reference</th>
    <th>Amount</th>
    <th>Method</th>
    <th>Status</th>
    <th>Paid At</th>
</tr>
</thead>
<tbody>
<?php if ($history): foreach ($history as $h): ?>
<tr>
    <td><?= htmlspecialchars($h['payment_id']) ?></td>
    <td>₱<?= number_format($h['amount'], 2) ?></td>
    <td>
        <?php if (strpos($h['payment_id'], 'CASH-') === 0): ?>
            <span class="badge bg-secondary">Cash</span>
        <?php else: ?>
            <span class="badge bg-info">Online</span>
        <?php endif; ?>
    </td>
    <td>
        <span class="badge <?= $h['status'] === 'Paid' ? 'bg-success' : 'bg-warning text-dark' ?>">
            <?= htmlspecialchars($h['status']) ?>
        </span>
    </td>
    <td><?= $h['paid_at'] ?: '-' ?></td>
</tr>
<?php endforeach; else: ?>
<tr><td colspan="5" class="text-center text-muted">No payments yet</td></tr>
<?php endif; ?>
</tbody>
</table>

</div>

<div class="main-sidebar">
<?php include 'billing_sidebar.php'; ?>
</div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>