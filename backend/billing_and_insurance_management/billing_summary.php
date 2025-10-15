<?php
include '../../SQL/config.php';
if (session_status() === PHP_SESSION_NONE) session_start();

// QR setup
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
            'version'=>5,'outputType'=>$qrClass::OUTPUT_IMAGE_PNG,'eccLevel'=>$qrClass::ECC_L,'scale'=>5
        ]);
        $png = (new $qrClass($options))->render($payment_text);
        $qrImageSrc = 'data:image/png;base64,' . base64_encode($png);
    } catch (Throwable $e) { $qrImageSrc = null; }
}
if (!$qrImageSrc) $qrImageSrc = 'https://api.qrserver.com/v1/create-qr-code/?size=300x300&format=png&data=' . urlencode($payment_text);

// Get patient
$patient_id = isset($_GET['patient_id']) ? intval($_GET['patient_id']) : 0;
$selected_patient = null;
if ($patient_id > 0) {
    $stmt = $conn->prepare("SELECT * FROM patientinfo WHERE patient_id=?");
    $stmt->bind_param("i", $patient_id); $stmt->execute();
    $selected_patient = $stmt->get_result()->fetch_assoc();
}

// Fetch service prices
$service_prices = [];
$service_stmt = $conn->query("SELECT serviceName, description, price FROM dl_services");
while ($row = $service_stmt->fetch_assoc()) {
    $service_prices[$row['serviceName']] = ['description'=>$row['description'],'price'=>floatval($row['price'])];
}

// Fetch patient services
$billing_items = []; $total_charges=0; $total_discount=0;
if ($patient_id>0) {
    $stmt = $conn->prepare("SELECT * FROM dl_results WHERE patientID=? AND status='Completed' ORDER BY resultDate DESC");
    $stmt->bind_param("i",$patient_id); $stmt->execute();
    $res = $stmt->get_result();
    while($row = $res->fetch_assoc()) {
        $services = explode(',', $row['result']);
        foreach($services as $s) {
            $s = trim($s);
            $price = $service_prices[$s]['price'] ?? 0;
            $desc  = $service_prices[$s]['description'] ?? '';
            $billing_items[] = ['service_name'=>$s,'description'=>$desc,'total_price'=>$price];
            $total_charges += $price;
        }
    }
}

// Fetch insurance
$insurance_covered=0; $insurance_company=null;
if($patient_id>0){
    $stmt=$conn->prepare("SELECT insurance_company, SUM(covered_amount) AS total_covered FROM insurance_requests WHERE patient_id=? AND status='Approved' GROUP BY insurance_company ORDER BY total_covered DESC LIMIT 1");
    $stmt->bind_param("i",$patient_id); $stmt->execute();
    $row=$stmt->get_result()->fetch_assoc();
    $insurance_covered=floatval($row['total_covered']??0);
    $insurance_company=$row['insurance_company']??null;
}

// Totals
$grand_total = $total_charges - $total_discount;
$total_out_of_pocket = max($grand_total - $insurance_covered,0);

// Handle payments & journal entries
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $payment_method = null;
    $txn_id = 'TXN' . uniqid();
    $ref = 'N/A';

    if (isset($_POST['confirm_paid']) && $total_out_of_pocket == 0) {
        $payment_method = $insurance_company ?: "Insurance";
        $ref = "Covered by Insurance";
    } elseif (isset($_POST['make_payment'])) {
        $payment_method = trim($_POST['payment_method'] ?? '');
        $ref = $_POST['payment_reference_' . strtolower($payment_method)] ?? 'N/A';
        $payment_method = ucfirst(strtolower($payment_method));
        if ($payment_method === 'Cash') $ref = "Cash Payment";
    }

    if (!empty($payment_method)) {
        if ($insurance_covered>0 && $total_out_of_pocket>0) $payment_method = ($insurance_company ?: "Insurance") . " / " . $payment_method;

        $is_pwd = $selected_patient['is_pwd'] ?? 0;
        $stmt = $conn->prepare("INSERT INTO patient_receipt 
            (patient_id, billing_id, total_charges, total_vat, total_discount, total_out_of_pocket, grand_total, billing_date, insurance_covered, payment_method, status, transaction_id, payment_reference, is_pwd)
            VALUES (?, ?, ?, 0, ?, ?, ?, CURDATE(), ?, ?, 'Paid', ?, ?, ?)
        ");
        $billing_id = 0; // placeholder
        $stmt->bind_param("iidddddsssi", $patient_id, $billing_id, $total_charges, $total_discount, $total_out_of_pocket, $grand_total, $insurance_covered, $payment_method, $txn_id, $ref, $is_pwd);

        if ($stmt->execute()) {
            // Create Journal Entry
            $receipt_id = $stmt->insert_id;
            $description = "Receipt for Patient #$patient_id ($payment_method)";
            $reference = "RCPT-$receipt_id";
            $status = "Posted";
            $created_by = $_SESSION['username'] ?? 'System';

            $stmt2 = $conn->prepare("INSERT INTO journal_entries (entry_date, module, description, reference, status, created_by, reference_type, reference_id) VALUES (NOW(),'billing',?,?,?,'$status',?, 'Patient Billing', ?)");
            $stmt2->bind_param("ssssii",$description,$reference,$status,$created_by,$receipt_id);
            $stmt2->execute();
            $entry_id = $conn->insert_id;

            // Create Journal Entry Lines
            if($insurance_covered>= $grand_total){
                // Fully covered by insurance
                $stmt3 = $conn->prepare("INSERT INTO journal_entry_lines (entry_id, account_name, debit, credit, description) VALUES (?,?,?,0,?)");
                $acc = 'Accounts Receivable - Insurance';
                $stmt3->bind_param("isds", $entry_id, $acc, $grand_total, $description); 
                $stmt3->execute();
                $stmt3 = $conn->prepare("INSERT INTO journal_entry_lines (entry_id, account_name, debit, credit, description) VALUES (?,?,0,?,?)");
                $acc = 'Service Revenue';
                $stmt3->bind_param("isds", $entry_id, $acc, $grand_total, $description);
                $stmt3->execute();
            } else {
                // Partial insurance
                if($insurance_covered>0){
                    $stmt3 = $conn->prepare("INSERT INTO journal_entry_lines (entry_id, account_name, debit, credit, description) VALUES (?,?,?,0,?)");
                    $acc = 'Accounts Receivable - Insurance';
                    $stmt3->bind_param("isds", $entry_id, $acc, $insurance_covered, $description);
                    $stmt3->execute();
                }
                if($total_out_of_pocket>0){
                    $method_acc = match(strtolower($payment_method)){
                        'cash'=>'Cash on Hand',
                        'gcash'=>'Cash in Bank - GCash',
                        'bank'=>'Cash in Bank',
                        'card'=>'Card Payments',
                        default=>'Cash/Bank'
                    };
                    $stmt3 = $conn->prepare("INSERT INTO journal_entry_lines (entry_id, account_name, debit, credit, description) VALUES (?,?,?,0,?)");
                    $stmt3->bind_param("isds",$entry_id,$method_acc,$total_out_of_pocket,$description); $stmt3->execute();
                }
                $stmt3 = $conn->prepare("INSERT INTO journal_entry_lines (entry_id, account_name, debit, credit, description) VALUES (?,?,0,?,?)");
                $acc = 'Service Revenue';
                $stmt3->bind_param("isds", $entry_id, $acc, $grand_total, $description);
                $stmt3->execute();
            }

            echo "<script>alert('Payment recorded & Journal Entry created successfully!'); window.location='billing_records.php';</script>";
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
<meta charset="utf-8"><title>Billing Summary</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<link rel="stylesheet" href="assets/CSS/billing_summary.css">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
</head>
<body class="bg-light p-4">
<div class="main-sidebar"><?php include 'billing_sidebar.php'; ?></div>

<div class="receipt-box">
    <div class="d-flex justify-content-between mb-3"><h4>Billing Summary</h4><div><?= date('F j, Y') ?></div></div>

    <div class="mb-2">
        <strong>BILLED TO:</strong><br>
        <?= htmlspecialchars($selected_patient ? ($selected_patient['fname']??'').' '.($selected_patient['mname']??'').' '.($selected_patient['lname']??'') : 'N/A') ?><br>
        Phone: <?= htmlspecialchars($selected_patient['phone_number']??'N/A') ?><br>
        Address: <?= htmlspecialchars($selected_patient['address']??'N/A') ?>
    </div>

    <table class="table table-sm table-bordered mb-3">
        <thead><tr><th>Service</th><th>Description</th><th class="text-end">Amount</th></tr></thead>
        <tbody>
        <?php if(!empty($billing_items)): foreach($billing_items as $it): ?>
            <tr>
                <td><?= htmlspecialchars($it['service_name']) ?></td>
                <td><?= htmlspecialchars($it['description']) ?></td>
                <td class="text-end">₱<?= number_format($it['total_price'],2) ?></td>
            </tr>
        <?php endforeach; else: ?>
            <tr><td colspan="3" class="text-center">No billed services found.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>

    <div class="text-end mb-3">
        <strong>Total Charges: ₱<?= number_format($total_charges,2) ?></strong><br>
        <strong>PWD/Senior Discount: ₱<?= number_format($total_discount,2) ?></strong><br>
        <strong>Insurance Covered: ₱<?= number_format($insurance_covered,2) ?></strong><br>
        <strong>Total to Pay: ₱<?= number_format($total_out_of_pocket,2) ?></strong>
    </div>

    <?php if($total_out_of_pocket>0): ?>
        <div class="text-end">
            <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#paymentModal">Proceed to Payment</button>
        </div>
    <?php else: ?>
        <form method="POST" class="text-end">
            <button type="submit" name="confirm_paid" class="btn btn-primary">Confirm & Mark as Paid</button>
        </form>
    <?php endif; ?>
</div>

<!-- Payment Modal (same as before) -->
<div class="modal fade" id="paymentModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <form method="POST" class="modal-content">
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title"><i class="bi bi-wallet2 me-2"></i> Payment</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label class="mb-2 fw-bold">Choose a Payment Method</label>
          <div class="payment-methods d-flex flex-wrap gap-3">
            <div class="payment-card" data-value="GCash"><img src="assets/image/gcash.jpg" width="80"><div>GCash</div></div>
            <div class="payment-card" data-value="Bank"><img src="https://cdn-icons-png.flaticon.com/512/2721/2721227.png" width="60"><div>Bank</div></div>
            <div class="payment-card" data-value="Card"><img src="https://cdn-icons-png.flaticon.com/512/196/196561.png" width="60"><div>Card</div></div>
            <div class="payment-card" data-value="Cash"><img src="https://cdn-icons-png.flaticon.com/512/2331/2331970.png" width="60"><div>Cash</div></div>
          </div>
          <input type="hidden" id="payment_method" name="payment_method" required>
        </div>

        <div id="gcash_box" class="payment-extra text-center" style="display:none;">
          <p class="fw-bold">Hospital Management System</p>
          <p>Send to GCash: <strong><?= htmlspecialchars($gcash_number) ?></strong></p>
          <input type="text" name="payment_reference_gcash" class="form-control mb-2" placeholder="Enter GCash Reference Number">
          <img src="<?= htmlspecialchars($qrImageSrc) ?>" width="220" class="border rounded">
        </div>

        <div id="bank_box" class="payment-extra" style="display:none;">
          <label>Bank Transaction Reference</label>
          <input type="text" name="payment_reference_bank" class="form-control" placeholder="Enter bank reference">
        </div>

        <div id="card_box" class="payment-extra" style="display:none;">
          <label>Card Number</label>
          <input type="text" name="card_number" class="form-control mb-2" placeholder="1234 5678 9012 3456">
          <div class="d-flex gap-2">
            <input type="text" name="expiry" class="form-control" placeholder="MM/YY">
            <input type="text" name="cvv" class="form-control" placeholder="CVV">
          </div>
          <input type="text" name="payment_reference_card" class="form-control mt-2" placeholder="Card Authorization Code">
        </div>

        <div id="cash_box" class="payment-extra" style="display:none;">
          <p class="fw-bold text-success"><i class="bi bi-cash-stack me-2"></i> Cash Payment Selected</p>
          <input type="hidden" name="payment_reference_cash" value="Cash">
        </div>
      </div>
      <div class="modal-footer">
        <button type="submit" name="make_payment" class="btn btn-success"><i class="bi bi-check-circle me-1"></i> Confirm & Save</button>
      </div>
    </form>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.querySelectorAll('.payment-card').forEach(card=>{
  card.addEventListener('click',function(){
    document.querySelectorAll('.payment-card').forEach(c=>c.classList.remove('active'));
    this.classList.add('active');
    document.getElementById('payment_method').value=this.dataset.value;
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
