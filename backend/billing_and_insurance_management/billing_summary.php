<?php
include '../../SQL/config.php';
if (session_status() === PHP_SESSION_NONE) session_start();

// ======================= QR SETUP =======================
$vendorAutoload = __DIR__ . '/vendor/autoload.php';
$qrLocalAvailable = false;
if (file_exists($vendorAutoload)) {
    require_once $vendorAutoload;
    $qrLocalAvailable = class_exists('chillerlan\\QRCode\\QRCode') && class_exists('chillerlan\\QRCode\\QROptions');
}

$gcash_number = "09123456789";
$payment_text = "GCash Payment to Hospital - Number: $gcash_number";

$qrImageSrc = null;
if ($qrLocalAvailable) {
    try {
        $optsClass = 'chillerlan\\QRCode\\QROptions';
        $qrClass   = 'chillerlan\\QRCode\\QRCode';
        $options = new $optsClass([
            'version'    => 5,
            'outputType' => $qrClass::OUTPUT_IMAGE_PNG,
            'eccLevel'   => $qrClass::ECC_L,
            'scale'      => 5,
        ]);
        $png = (new $qrClass($options))->render($payment_text);
        $qrImageSrc = 'data:image/png;base64,' . base64_encode($png);
    } catch (Throwable $e) {
        $qrImageSrc = null;
    }
}
if (!$qrImageSrc) {
    $qrImageSrc = 'https://api.qrserver.com/v1/create-qr-code/?size=300x300&format=png&data=' . urlencode($payment_text);
}

// ======================= PATIENT DATA =======================
$patient_id = isset($_GET['patient_id']) ? intval($_GET['patient_id']) : 0;
$billing_id = null;
$selected_patient = null;
$insurance_discount = 0;
$insurance_discount_type = null;
$insurance_plan = null;

if ($patient_id > 0) {
    $stmt = $conn->prepare("SELECT *, CONCAT(fname,' ',IFNULL(mname,''),' ',lname) AS full_name FROM patientinfo WHERE patient_id = ?");
    $stmt->bind_param("i", $patient_id);
    $stmt->execute();
    $selected_patient = $stmt->get_result()->fetch_assoc();
}

// ======================= BILLING =======================
if ($patient_id > 0) {
    $stmt = $conn->prepare("SELECT MAX(billing_id) AS latest_billing_id FROM billing_items WHERE patient_id = ? AND finalized=1");
    $stmt->bind_param("i", $patient_id);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    $billing_id = $res['latest_billing_id'] ?? null;
}

// ======================= INSURANCE (MATCH BY FULL NAME) =======================
$insurance_covered = 0;
if ($patient_id > 0 && !empty($selected_patient['full_name'])) {
    $full_name = $selected_patient['full_name'];
    $stmt = $conn->prepare("
        SELECT insurance_number, promo_name, discount_type, discount_value
        FROM patient_insurance
        WHERE full_name = ? AND status = 'Active'
        LIMIT 1
    ");
    $stmt->bind_param("s", $full_name);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();

    if ($row) {
        $insurance_plan = $row['promo_name'] ?? 'N/A';
        $insurance_discount = floatval($row['discount_value'] ?? 0);
        $insurance_discount_type = $row['discount_type'] ?? 'Fixed';
    }
}

// ======================= BILLING ITEMS =======================
$billing_items = [];
$total_charges = 0;
$total_discount = 0;

if ($patient_id > 0 && $billing_id) {
    $stmt = $conn->prepare("
        SELECT bi.*, ds.description, ds.serviceName
        FROM billing_items bi
        LEFT JOIN dl_services ds ON bi.service_id = ds.serviceID
        WHERE bi.patient_id = ? AND bi.billing_id = ?
    ");
    $stmt->bind_param("ii", $patient_id, $billing_id);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $billing_items[] = $row;
        $total_charges += floatval($row['total_price'] ?? 0);
    }
}

// ======================= APPLY INSURANCE DISCOUNT =======================
if ($insurance_discount > 0) {
    if ($insurance_discount_type === 'Percentage') {
        $insurance_covered = $total_charges * ($insurance_discount / 100);
    } else { // Fixed
        $insurance_covered = min($insurance_discount, $total_charges);
    }
}

// ======================= TOTALS =======================
$grand_total = $total_charges - $total_discount;
$total_out_of_pocket = max($grand_total - $insurance_covered, 0);

// ======================= PAYMENT PROCESSING =======================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $payment_method = null;
    $txn_id = 'TXN' . uniqid();
    $ref = 'N/A';

    if (isset($_POST['confirm_paid']) && $total_out_of_pocket == 0) {
        $payment_method = $insurance_plan ?: "Insurance";
        $ref = "Covered by Insurance";
    } elseif (isset($_POST['make_payment'])) {
        $payment_method = trim($_POST['payment_method'] ?? '');
        $ref = $_POST['payment_reference_' . strtolower($payment_method)] ?? 'N/A';
        $payment_method = ucfirst(strtolower($payment_method));
        if ($payment_method === 'Cash') $ref = "Cash Payment";
    }

    if (!empty($payment_method)) {
        if ($insurance_covered > 0 && $total_out_of_pocket > 0) {
            $payment_method = ($insurance_plan ?: "Insurance") . " / " . $payment_method;
        }

        $is_pwd = $selected_patient['is_pwd'] ?? 0;
        $stmt = $conn->prepare("INSERT INTO patient_receipt 
            (patient_id, billing_id, total_charges, total_vat, total_discount, total_out_of_pocket, grand_total, billing_date, insurance_covered, payment_method, status, transaction_id, payment_reference, is_pwd)
            VALUES (?, ?, ?, 0, ?, ?, ?, CURDATE(), ?, ?, 'Paid', ?, ?, ?)
        ");
        $stmt->bind_param(
            "iidddddsssi",
            $patient_id,
            $billing_id,
            $total_charges,
            $total_discount,
            $total_out_of_pocket,
            $grand_total,
            $insurance_covered,
            $payment_method,
            $txn_id,
            $ref,
            $is_pwd
        );

        if ($stmt->execute()) {
            echo "<script>alert('Payment recorded successfully!'); window.location='billing_records.php';</script>";
            exit;
        } else {
            echo "<script>alert('Error saving receipt.');</script>";
        }
    }
}
?>

<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Billing Summary</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<link rel="stylesheet" href="assets/CSS/billing_summary.css">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
</head>
<body class="bg-light p-4">

<div class="main-sidebar">
    <?php include 'billing_sidebar.php'; ?>
</div>

<div class="receipt-box">
    <h4>Billing Summary</h4>
    <div><strong>Billed To:</strong> <?= htmlspecialchars($selected_patient['full_name'] ?? '') ?></div>
    <div>Phone: <?= htmlspecialchars($selected_patient['phone_number'] ?? 'N/A') ?></div>
    <div>Address: <?= htmlspecialchars($selected_patient['address'] ?? 'N/A') ?></div>
    <?php if($insurance_plan): ?>
        <div><strong>Insurance:</strong> <?= htmlspecialchars($insurance_plan) ?> (<?= htmlspecialchars($insurance_discount_type) ?> <?= number_format($insurance_discount,2) ?>)</div>
    <?php endif; ?>

    <table class="table table-sm table-bordered mt-2">
        <thead class="table-primary">
            <tr>
                <th>Service</th>
                <th>Description</th>
                <th class="text-end">Amount</th>
            </tr>
        </thead>
        <tbody>
        <?php if($billing_items): ?>
            <?php foreach($billing_items as $it): ?>
            <tr>
                <td><?= htmlspecialchars($it['serviceName'] ?? '') ?></td>
                <td><?= htmlspecialchars($it['description'] ?? '') ?></td>
                <td class="text-end">₱<?= number_format($it['total_price'] ?? 0,2) ?></td>
            </tr>
            <?php endforeach; ?>
        <?php else: ?>
            <tr><td colspan="3" class="text-center">No billed services found.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>

    <div class="text-end mt-2">
        <strong>Total Charges:</strong> ₱<?= number_format($total_charges,2) ?><br>
        <strong>Discount:</strong> ₱<?= number_format($total_discount,2) ?><br>
        <strong>Insurance Covered:</strong> ₱<?= number_format($insurance_covered,2) ?><br>
        <strong>Total Payable:</strong> ₱<?= number_format($total_out_of_pocket,2) ?>
    </div>

    <?php if($total_out_of_pocket > 0): ?>
    <div class="text-end mt-3">
        <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#paymentModal">Proceed to Payment</button>
    </div>
    <?php else: ?>
    <form method="POST" class="text-end mt-3">
        <button type="submit" name="confirm_paid" class="btn btn-primary">Confirm & Mark as Paid</button>
    </form>
    <?php endif; ?>
</div>

<!-- PAYMENT MODAL -->
<div class="modal fade" id="paymentModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form method="POST">
        <div class="modal-header">
          <h5 class="modal-title">Select Payment Method</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body text-center">
          <input type="hidden" name="payment_method" id="payment_method">
          <div class="d-flex flex-wrap gap-3 justify-content-center mb-4">
            <div class="payment-card" data-value="GCash"><i class="bi bi-phone text-primary"></i><br>GCash</div>
            <div class="payment-card" data-value="Bank"><i class="bi bi-bank text-success"></i><br>Bank</div>
            <div class="payment-card" data-value="Card"><i class="bi bi-credit-card text-warning"></i><br>Card</div>
            <div class="payment-card" data-value="Cash"><i class="bi bi-cash-coin text-success"></i><br>Cash</div>
          </div>
          <div id="gcash_box" class="payment-extra" style="display:none;">
            <img src="<?= $qrImageSrc ?>" class="img-fluid mb-2">
            <input type="text" class="form-control" name="payment_reference_gcash" placeholder="Enter GCash Reference Number">
          </div>
          <div id="bank_box" class="payment-extra" style="display:none;">
            <input type="text" class="form-control" name="payment_reference_bank" placeholder="Enter Bank Transaction ID">
          </div>
          <div id="card_box" class="payment-extra" style="display:none;">
            <input type="text" class="form-control" name="payment_reference_card" placeholder="Enter Card Transaction ID">
          </div>
          <div id="cash_box" class="payment-extra" style="display:none;">
            <p>No reference required for cash payments.</p>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" name="make_payment" class="btn btn-primary">Confirm Payment</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.querySelectorAll('.payment-card').forEach(card => {
    card.addEventListener('click', function(){
        document.querySelectorAll('.payment-card').forEach(c=>c.classList.remove('active'));
        this.classList.add('active');
        document.getElementById('payment_method').value = this.dataset.value;
        document.querySelectorAll('.payment-extra').forEach(el=>el.style.display='none');
        if(this.dataset.value==='GCash') document.getElementById('gcash_box').style.display='block';
        if(this.dataset.value==='Bank') document.getElementById('bank_box').style.display='block';
        if(this.dataset.value==='Card') document.getElementById('card_box').style.display='block';
        if(this.dataset.value==='Cash') document.getElementById('cash_box').style.display='block';
    });
});
</script>
</body>
</html>
