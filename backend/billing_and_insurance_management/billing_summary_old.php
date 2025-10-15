<?php
include '../../SQL/config.php';

// Safely start session
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

// Get patient_id
$patient_id = isset($_GET['patient_id']) ? intval($_GET['patient_id']) : 0;
$billing_id = null;
$insurance_covered = 0;
$insurance_company = null;
$selected_patient = null;

if ($patient_id > 0) {
    $stmt = $conn->prepare("SELECT * FROM patientinfo WHERE patient_id = ?");
    $stmt->bind_param("i", $patient_id);
    $stmt->execute();
    $selected_patient = $stmt->get_result()->fetch_assoc();
}

// Get latest finalized billing_id
if ($patient_id > 0) {
    $stmt = $conn->prepare("SELECT MAX(billing_id) AS latest_billing_id FROM billing_items WHERE patient_id = ? AND finalized=1");
    $stmt->bind_param("i", $patient_id);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    $billing_id = $res['latest_billing_id'] ?? null;
}

// Fetch insurance coverage & company
if ($patient_id > 0 && $billing_id) {
    $stmt = $conn->prepare("
        SELECT insurance_company, SUM(covered_amount) AS total_covered
        FROM insurance_requests
        WHERE patient_id = ? AND status='Approved'
        GROUP BY insurance_company
        ORDER BY total_covered DESC
        LIMIT 1
    ");
    $stmt->bind_param("i", $patient_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $insurance_covered = floatval($row['total_covered'] ?? 0);
    $insurance_company = $row['insurance_company'] ?? null;
}

// Fetch billing items
$billing_items = [];
$total_charges = 0;
$total_discount = 0;

if ($patient_id > 0 && $billing_id) {
    $stmt = $conn->prepare("
        SELECT bi.*, ds.description
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
        $total_discount += floatval($row['discount'] ?? 0);
    }
}

// Calculate totals
$grand_total = $total_charges - $total_discount;
$total_out_of_pocket = max($grand_total - $insurance_covered, 0);

// Handle payments
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $payment_method = null;
    $txn_id = 'TXN' . uniqid();
    $ref = 'N/A';

    if (isset($_POST['confirm_paid']) && $total_out_of_pocket == 0) {
        $payment_method = $insurance_company ?: "Insurance";
        $ref = "Covered by Insurance";
    } elseif (isset($_POST['make_payment'])) {
        $payment_method = trim($_POST['payment_method'] ?? '');
        // Only take the reference for the selected method
        $ref = $_POST['payment_reference_' . strtolower($payment_method)] ?? 'N/A';

        $payment_method = ucfirst(strtolower($payment_method));
        if ($payment_method === 'Cash') {
            $ref = "Cash Payment";
        }
    }

    if (!empty($payment_method)) {
        if ($insurance_covered > 0 && $total_out_of_pocket > 0) {
            $payment_method = ($insurance_company ?: "Insurance") . " / " . $payment_method;
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
            $receipt_id = $stmt->insert_id;
            $reference = "RCPT-" . $receipt_id;
            $description = "Receipt for Patient #" . $patient_id . " (" . $payment_method . ")";
            $status = "Posted"; 
            $created_by = $_SESSION['username'] ?? 'System';

            $stmt2 = $conn->prepare("INSERT INTO journal_entries (entry_date, module, description, reference, status, created_by) 
                                     VALUES (NOW(), 'billing', ?, ?, ?, ?)");
            $stmt2->bind_param("ssss", $description, $reference, $status, $created_by);
            $stmt2->execute();
            $entry_id = $stmt2->insert_id;

            if ($insurance_covered >= $grand_total) {
                $stmt3 = $conn->prepare("INSERT INTO journal_entry_lines (entry_id, account_name, debit, credit, description) VALUES (?, 'Accounts Receivable - Insurance', ?, 0, ?)");
                $stmt3->bind_param("ids", $entry_id, $grand_total, $description);
                $stmt3->execute();

                $stmt3 = $conn->prepare("INSERT INTO journal_entry_lines (entry_id, account_name, debit, credit, description) VALUES (?, 'Service Revenue', 0, ?, ?)");
                $stmt3->bind_param("ids", $entry_id, $grand_total, $description);
                $stmt3->execute();
            } else {
                if ($insurance_covered > 0) {
                    $stmt3 = $conn->prepare("INSERT INTO journal_entry_lines (entry_id, account_name, debit, credit, description) VALUES (?, 'Accounts Receivable - Insurance', ?, 0, ?)");
                    $stmt3->bind_param("ids", $entry_id, $insurance_covered, $description);
                    $stmt3->execute();
                }

                $method = strtolower($_POST['payment_method'] ?? '');
                $account = match($method) {
                    'cash' => "Cash on Hand",
                    'gcash' => "Cash in Bank - GCash",
                    'bank' => "Cash in Bank",
                    'card' => "Card Payments",
                    default => "Cash/Bank"
                };

                if ($total_out_of_pocket > 0) {
                    $stmt3 = $conn->prepare("INSERT INTO journal_entry_lines (entry_id, account_name, debit, credit, description) VALUES (?, ?, ?, 0, ?)");
                    $stmt3->bind_param("isds", $entry_id, $account, $total_out_of_pocket, $description);
                    $stmt3->execute();
                }

                $stmt3 = $conn->prepare("INSERT INTO journal_entry_lines (entry_id, account_name, debit, credit, description) VALUES (?, 'Service Revenue', 0, ?, ?)");
                $stmt3->bind_param("ids", $entry_id, $grand_total, $description);
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
        <?= htmlspecialchars($selected_patient ? ($selected_patient['fname'] ?? '') . ' ' . (!empty($selected_patient['mname'] ?? '') ? ($selected_patient['mname'] ?? '') . ' ' : '') . ($selected_patient['lname'] ?? '') : 'N/A') ?><br>
        Phone: <?= htmlspecialchars($selected_patient['phone_number'] ?? 'N/A') ?><br>
        Address: <?= htmlspecialchars($selected_patient['address'] ?? 'N/A') ?>
    </div>

    <table class="table table-sm table-bordered mb-3">
        <thead>
            <tr>
                <th>Service</th>
                <th>Description</th>
                <th class="text-end">Amount</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($billing_items)): foreach ($billing_items as $it): ?>
                <tr>
                    <td><?= htmlspecialchars($it['service_name'] ?? '') ?></td>
                    <td><?= htmlspecialchars($it['description'] ?? '') ?></td>
                    <td class="text-end">₱<?= number_format($it['total_price'] ?? 0,2) ?></td>
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
        <strong>Total: ₱<?= number_format($total_out_of_pocket,2) ?></strong>
    </div>

    <?php if ($total_out_of_pocket > 0): ?>
        <div class="text-end">
            <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#paymentModal">Proceed to Payment</button>
        </div>
    <?php else: ?>
        <form method="POST" class="text-end">
            <button type="submit" name="confirm_paid" class="btn btn-primary">Confirm & Mark as Paid</button>
        </form>
    <?php endif; ?>
</div>

<!-- Payment Modal -->
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
            <div class="payment-card" data-value="GCash">
              <img src="assets/image/gcash.jpg" alt="GCash Logo" width="80">
              <div>GCash</div>
            </div>
            <div class="payment-card" data-value="Bank">
              <img src="https://cdn-icons-png.flaticon.com/512/2721/2721227.png" alt="Bank" width="60">
              <div>Bank</div>
            </div>
            <div class="payment-card" data-value="Card">
              <img src="https://cdn-icons-png.flaticon.com/512/196/196561.png" alt="Card" width="60">
              <div>Card</div>
            </div>
            <div class="payment-card" data-value="Cash">
              <img src="https://cdn-icons-png.flaticon.com/512/2331/2331970.png" alt="Cash" width="60">
              <div>Cash</div>
            </div>
          </div>
          <input type="hidden" id="payment_method" name="payment_method" required>
        </div>

        <!-- GCash -->
        <div id="gcash_box" class="payment-extra text-center">
          <p class="fw-bold">Hospital Management System</p>
          <p>Send to GCash: <strong><?= htmlspecialchars($gcash_number) ?></strong></p>
          <input type="text" name="payment_reference_gcash" class="form-control mb-2" placeholder="Enter GCash Reference Number">
          <img src="<?= htmlspecialchars($qrImageSrc) ?>" alt="GCash QR" width="220" class="border rounded">
        </div>

        <!-- Bank -->
        <div id="bank_box" class="payment-extra">
          <label class="form-label">Bank Transaction Reference</label>
          <input type="text" name="payment_reference_bank" class="form-control" placeholder="Enter bank reference">
        </div>

        <!-- Card -->
        <div id="card_box" class="payment-extra">
          <label class="form-label">Card Number</label>
          <input type="text" name="card_number" class="form-control mb-2" placeholder="1234 5678 9012 3456">
          <div class="d-flex gap-2">
            <input type="text" name="expiry" class="form-control" placeholder="MM/YY">
            <input type="text" name="cvv" class="form-control" placeholder="CVV">
          </div>
          <input type="text" name="payment_reference_card" class="form-control mt-2" placeholder="Card Authorization Code">
        </div>

        <!-- Cash -->
        <div id="cash_box" class="payment-extra">
          <p class="fw-bold text-success">
            <i class="bi bi-cash-stack me-2"></i> Cash Payment Selected
          </p>
          <input type="hidden" name="payment_reference_cash" value="Cash">
        </div>

      </div>
      <div class="modal-footer">
        <button type="submit" name="make_payment" class="btn btn-success">
          <i class="bi bi-check-circle me-1"></i> Confirm & Save
        </button>
      </div>
    </form>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.querySelectorAll('.payment-card').forEach(card => {
  card.addEventListener('click', function(){
    document.querySelectorAll('.payment-card').forEach(c => c.classList.remove('active'));
    this.classList.add('active');
    document.getElementById('payment_method').value = this.dataset.value;

    document.querySelectorAll('.payment-extra').forEach(el => el.style.display = 'none');
    if(this.dataset.value === 'GCash') document.getElementById('gcash_box').style.display = 'block';
    if(this.dataset.value === 'Bank') document.getElementById('bank_box').style.display = 'block';
    if(this.dataset.value === 'Card') document.getElementById('card_box').style.display = 'block';
    if(this.dataset.value === 'Cash') document.getElementById('cash_box').style.display = 'block';
  });
});
</script>
</body>
</html>
