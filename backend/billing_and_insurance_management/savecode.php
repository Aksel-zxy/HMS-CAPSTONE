<?php
include '../../SQL/config.php';
require_once 'classincludes/billing_records_class.php';

$vendorAutoload = __DIR__ . '/vendor/autoload.php';
$qrLocalAvailable = false;
if (file_exists($vendorAutoload)) {
    require_once $vendorAutoload;
    $qrLocalAvailable = class_exists('chillerlan\\QRCode\\QRCode') && class_exists('chillerlan\\QRCode\\QROptions');
}

// Payment / QR data
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

$billing = new billing_records($conn);
$patient_id = isset($_GET['patient_id']) ? intval($_GET['patient_id']) : 0;

// Fetch patient info
$selected_patient = null;
if ($patient_id > 0) {
    $stmt = $conn->prepare("SELECT * FROM patientinfo WHERE patient_id = ?");
    $stmt->bind_param("i", $patient_id);
    $stmt->execute();
    $selected_patient = $stmt->get_result()->fetch_assoc();
}

// Fetch approved insurance request if exists
$insurance_covered = 0.0;
$insurance_request = null;
$insuranceCompanyName = "";
if ($patient_id > 0) {
    $stmt = $conn->prepare("SELECT * FROM insurance_requests WHERE patient_id = ? AND status = 'Approved' ORDER BY request_id DESC LIMIT 1");
    $stmt->bind_param("i", $patient_id);
    $stmt->execute();
    $insurance_request = $stmt->get_result()->fetch_assoc();
    if ($insurance_request) {
        $insurance_covered = floatval($insurance_request['covered_amount']);
        $insuranceCompanyName = $insurance_request['insurance_company'];
    }
}

// Fetch completed services for patient
$service_items = [];
$subtotal = 0.00;
if ($patient_id > 0) {
    $sql = "SELECT sch.serviceName, ds.description, ds.price
            FROM dl_schedule sch
            LEFT JOIN dl_services ds ON sch.serviceName = ds.serviceName
            WHERE sch.patientID = ? AND sch.status = 'Completed'
            ORDER BY sch.scheduleDate DESC, sch.scheduleTime DESC";
    $s = $conn->prepare($sql);
    $s->bind_param("i", $patient_id);
    $s->execute();
    $res = $s->get_result();
    while ($r = $res->fetch_assoc()) {
        $service_items[] = $r;
        $subtotal += floatval($r['price']);
    }
}

$vat = $subtotal * 0.12;

// Handle payment saving
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['make_payment'])) {
    $is_pwd = isset($_POST['is_pwd']) && ($_POST['is_pwd'] == '1' || $_POST['is_pwd'] === 'on') ? 1 : 0;
    $transaction_id = uniqid("TXN");

    $total_charges = $subtotal;
    $total_vat = $total_charges * 0.12;
    $total_discount = $is_pwd ? ($total_charges * 0.20) : 0;
    $total_out_of_pocket = $total_charges - $total_discount - $insurance_covered;
    $grand_total = $total_out_of_pocket + $total_vat;

    if ($grand_total <= 0) {
        $total_out_of_pocket = 0;
        $grand_total = 0;
        $payment_method = $insuranceCompanyName ?: "INSURANCE";
        $status = "Paid";
        $payment_reference = "Paid by " . ($insuranceCompanyName ?: "Insurance");
    } else {
        $payment_method = $_POST['payment_method'] ?? 'Cash';
        $status = "Pending";

        $bank_ref = $_POST['bank_ref'] ?? null;
        $card_number = $_POST['card_number'] ?? null;
        $gcash_ref = $_POST['gcash_ref'] ?? null;
        $card_last4 = $card_number ? substr(preg_replace('/\D+/', '', $card_number), -4) : null;

        $payment_reference = null;
        if ($payment_method === 'Bank' && $bank_ref) $payment_reference = "Bank TXN: $bank_ref";
        if ($payment_method === 'Card' && $card_last4) $payment_reference = "Card ****$card_last4";
        if ($payment_method === 'GCash' && $gcash_ref) $payment_reference = "GCash Ref: $gcash_ref";
    }

    $stmt = $conn->prepare(
        "INSERT INTO patient_receipt
         (patient_id, total_charges, total_vat, total_discount, total_out_of_pocket, grand_total, billing_date, insurance_covered, payment_method, status, transaction_id, payment_reference, is_pwd)
         VALUES (?, ?, ?, ?, ?, ?, CURDATE(), ?, ?, ?, ?, ?, ?)"
    );
    $stmt->bind_param(
        "iddddddssssi",
        $patient_id, $total_charges, $total_vat, $total_discount, $total_out_of_pocket, $grand_total,
        $insurance_covered, $payment_method, $status, $transaction_id, $payment_reference, $is_pwd
    );
    $stmt->execute();

    echo "<script>alert('Bill saved successfully.');window.location='billing_summary.php?patient_id={$patient_id}';</script>";
    exit;
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
  <div class="d-flex justify-content-between mb-3">
    <h4>Billing Summary</h4>
    <div><?= date('F j, Y') ?></div>
  </div>

  <div class="mb-2">
    <strong>BILLED TO:</strong><br>
    <?php
      if (!empty($selected_patient)) {
        $full = $selected_patient['fname'].' '.(!empty($selected_patient['mname'])?$selected_patient['mname'].' ':'').$selected_patient['lname'];
        echo htmlspecialchars($full);
      } else {
        echo "N/A";
      }
    ?><br>
    Phone: <?= !empty($selected_patient['phone_number']) ? htmlspecialchars($selected_patient['phone_number']) : 'N/A' ?><br>
    Address: <?= !empty($selected_patient['address']) ? htmlspecialchars($selected_patient['address']) : 'N/A' ?>
  </div>

  <table class="table table-sm table-bordered mb-3">
    <thead><tr><th>Particulars</th><th>Description</th><th class="text-end">Amount</th></tr></thead>
    <tbody>
      <?php if (!empty($service_items)): foreach ($service_items as $it): ?>
        <tr>
          <td><?= htmlspecialchars($it['serviceName']) ?></td>
          <td><?= htmlspecialchars($it['description']) ?></td>
          <td class="text-end">₱<?= number_format($it['price'],2) ?></td>
        </tr>
      <?php endforeach; else: ?>
        <tr><td colspan="3" class="text-center">No completed services found.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>

  <div class="text-end mb-3">
    Subtotal: ₱<span id="subtotal"><?= number_format($subtotal,2) ?></span><br>
    VAT (12%): ₱<span id="vat"><?= number_format($vat,2) ?></span><br>
    <?php if($insurance_covered > 0): ?>
      Insurance Covered: ₱<?= number_format($insurance_covered,2) ?><br>
    <?php endif; ?>
    <div class="form-check d-inline-block mt-2">
      <input class="form-check-input" type="checkbox" id="is_pwd">
      <label class="form-check-label" for="is_pwd">Senior / PWD Discount (20%)</label>
    </div>
    <div id="discount-line" style="display:none;"> Discount: -₱<span id="discount-amount">0.00</span><br></div>
    <strong>Grand Total: ₱<span id="grand-total"><?= number_format($subtotal + $vat - $insurance_covered,2) ?></span></strong>
  </div>

  <?php if ($subtotal + $vat - $insurance_covered > 0): ?>
    <div class="text-end">
      <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#paymentModal">Proceed to Payment</button>
    </div>
  <?php endif; ?>
</div>

<!-- Payment Modal -->
<div class="modal fade" id="paymentModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <form method="POST" class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Payment</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label class="mb-2">Choose a Payment Method</label>
          <div class="payment-methods">
            <div class="payment-card" data-value="GCash">
             <img src="assets/image/gcash.jpg" alt="GCash Logo" width="80">
              <div>GCash</div>
            </div>
            <div class="payment-card" data-value="Bank">
              <img src="https://cdn-icons-png.flaticon.com/512/2721/2721227.png" alt="Bank">
              <div>Bank</div>
            </div>
            <div class="payment-card" data-value="Card">
              <img src="https://cdn-icons-png.flaticon.com/512/196/196561.png" alt="Card">
              <div>Card</div>
            </div>
            <div class="payment-card" data-value="Cash">
              <img src="https://cdn-icons-png.flaticon.com/512/2331/2331970.png" alt="Cash">
              <div>Cash</div>
            </div>
          </div>
          <input type="hidden" id="payment_method" name="payment_method" required>
        </div>

        <!-- GCash -->
        <div id="gcash_box" class="payment-extra text-center">
          <p>Hospital Management System</p>
          <p>Send to GCash: <strong><?= htmlspecialchars($gcash_number) ?></strong></p>
          <input type="text" name="gcash_ref" class="form-control mb-2" placeholder="Enter GCash Reference Number">
          <img src="<?= htmlspecialchars($qrImageSrc) ?>" alt="GCash QR" width="260" class="border rounded">
        </div>

        <!-- Bank -->
        <div id="bank_box" class="payment-extra">
          <label>Bank Transaction Reference</label>
          <input type="text" name="bank_ref" class="form-control" placeholder="Enter bank reference">
        </div>

        <!-- Card -->
        <div id="card_box" class="payment-extra">
          <label>Card Number</label>
          <input type="text" name="card_number" class="form-control mb-2" placeholder="1234 5678 9012 3456">
          <div class="d-flex gap-2">
            <input type="text" name="expiry" class="form-control" placeholder="MM/YY">
            <input type="text" name="cvv" class="form-control" placeholder="CVV">
          </div>
        </div>

        <input type="hidden" name="is_pwd" id="hidden_is_pwd" value="0">
      </div>
      <div class="modal-footer">
        <button type="submit" name="make_payment" class="btn btn-success">Confirm & Save</button>
      </div>
    </form>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
const subtotal = <?= json_encode((float)$subtotal) ?>;
const vat = <?= json_encode((float)$vat) ?>;
const insurance = <?= json_encode((float)$insurance_covered) ?>;
const insuranceCompanyName = <?= json_encode($insuranceCompanyName) ?>;
const cb = document.getElementById('is_pwd');
const discountLine = document.getElementById('discount-line');
const discountAmount = document.getElementById('discount-amount');
const grandTotalSpan = document.getElementById('grand-total');
const hiddenIsPwd = document.getElementById('hidden_is_pwd');

function updateBill(){
    const discount = cb.checked ? subtotal * 0.20 : 0;
    discountAmount.textContent = discount.toFixed(2);
    const total = subtotal + vat - discount - insurance;
    grandTotalSpan.textContent = total.toFixed(2);
    discountLine.style.display = cb.checked ? 'block' : 'none';
    hiddenIsPwd.value = cb.checked ? '1' : '0';
}
cb.addEventListener('change', updateBill);
updateBill();

// Handle payment card selection
document.querySelectorAll('.payment-card').forEach(card => {
  card.addEventListener('click', function(){
    document.querySelectorAll('.payment-card').forEach(c => c.classList.remove('active'));
    this.classList.add('active');
    document.getElementById('payment_method').value = this.dataset.value;

    document.querySelectorAll('.payment-extra').forEach(el => el.style.display = 'none');
    if(this.dataset.value === 'GCash') document.getElementById('gcash_box').style.display = 'block';
    if(this.dataset.value === 'Bank') document.getElementById('bank_box').style.display = 'block';
    if(this.dataset.value === 'Card') document.getElementById('card_box').style.display = 'block';
  });
});
</script>
</body>
</html>
