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
$insurance_covered = 0;
$insurance_company = null;
$selected_patient = null;

if ($patient_id > 0) {
    $stmt = $conn->prepare("SELECT * FROM patientinfo WHERE patient_id = ?");
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

// ======================= INSURANCE (FIXED SECTION) =======================
// --- FIX: Deduct approved insurance even if total_bill = 0 ---
if ($patient_id > 0) {
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
        $total_discount += floatval($row['discount'] ?? 0);
    }
}

// ======================= DIAGNOSTIC RESULTS =======================
$dl_services = [];
if ($patient_id > 0) {
    $stmt = $conn->prepare("SELECT * FROM dl_results WHERE patientID = ? AND status = 'Completed'");
    $stmt->bind_param("i", $patient_id);
    $stmt->execute();
    $res = $stmt->get_result();

    while ($row = $res->fetch_assoc()) {
        $services = array_map('trim', explode(',', $row['result']));
        foreach ($services as $svcName) {
            if (!empty($svcName)) {
                $stmt2 = $conn->prepare("SELECT serviceName, description, price FROM dl_services WHERE serviceName = ?");
                $stmt2->bind_param("s", $svcName);
                $stmt2->execute();
                $svcRes = $stmt2->get_result()->fetch_assoc();

                if ($svcRes) {
                    $dl_services[] = [
                        'service_name' => $svcRes['serviceName'],
                        'description'  => $svcRes['description'] . " (Completed on " . date('F j, Y', strtotime($row['resultDate'])) . ")",
                        'total_price'  => floatval($svcRes['price'])
                    ];
                    $total_charges += floatval($svcRes['price']);
                } else {
                    $dl_services[] = [
                        'service_name' => $svcName,
                        'description'  => 'Diagnostic/Lab Service (Completed on ' . date('F j, Y', strtotime($row['resultDate'])) . ')',
                        'total_price'  => 0
                    ];
                }
            }
        }
    }
}

// ======================= TOTALS =======================
$grand_total = $total_charges - $total_discount;

// --- FIX: Ensure insurance coverage applies to total, not ignored ---
$total_out_of_pocket = max($grand_total - $insurance_covered, 0);

// ======================= PAYMENT PROCESSING =======================
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
            // --- Journal Entry Creation ---
            $receipt_id = $stmt->insert_id;
            $created_by = $_SESSION['username'] ?? 'System';
            $je_module = "billing";
            $je_reference = $txn_id;
            $je_desc = "Payment received for Patient ID {$patient_id}. Receipt TXN: {$txn_id}. Method: {$payment_method}. Amount: ₱" . number_format($grand_total, 2);

            $stmtJe = $conn->prepare("
                INSERT INTO journal_entries (entry_date, module, description, reference, status, created_by)
                VALUES (NOW(), ?, ?, ?, 'Posted', ?)
            ");
            if ($stmtJe) {
                $stmtJe->bind_param("ssss", $je_module, $je_desc, $je_reference, $created_by);
                $stmtJe->execute();
                $je_id = $stmtJe->insert_id;

                $payment_account = 'Cash';
                if (stripos($payment_method, 'bank') !== false) $payment_account = 'Bank';
                if (stripos($payment_method, 'gcash') !== false) $payment_account = 'Cash';

                $amount_cash = floatval($total_out_of_pocket);
                $amount_insurance = floatval($insurance_covered);

                $stmtLine = $conn->prepare("INSERT INTO journal_entry_lines (entry_id, account_name, debit, credit) VALUES (?, ?, ?, ?)");
                if ($stmtLine) {
                    $zero = 0.0;

                    // --- Patient payment ---
                    if ($amount_cash > 0) {
                        $account_name = $payment_account;
                        $debit = $amount_cash;
                        $credit = $zero;
                        $stmtLine->bind_param("isdd", $je_id, $account_name, $debit, $credit);
                        $stmtLine->execute();

                        $account_name = 'Accounts Receivable';
                        $debit = $zero;
                        $credit = $amount_cash;
                        $stmtLine->bind_param("isdd", $je_id, $account_name, $debit, $credit);
                        $stmtLine->execute();
                    }

                    // --- Insurance coverage ---
                    if ($amount_insurance > 0) {
                        $account_name = 'Accounts Receivable - Insurance';
                        $debit = $amount_insurance;
                        $credit = $zero;
                        $stmtLine->bind_param("isdd", $je_id, $account_name, $debit, $credit);
                        $stmtLine->execute();

                        $account_name = 'Accounts Receivable';
                        $debit = $zero;
                        $credit = $amount_insurance;
                        $stmtLine->bind_param("isdd", $je_id, $account_name, $debit, $credit);
                        $stmtLine->execute();
                    }

                    $stmtLine->close();
                }

                $stmtJe->close();
            }

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
    <div class="d-flex justify-content-between mb-3">
        <h4>Billing Summary</h4>
        <div><?= date('F j, Y') ?></div>
    </div>

    <div class="mb-2">
        <strong>BILLED TO:</strong><br>
        <?= htmlspecialchars(($selected_patient['fname'] ?? '') . ' ' . ($selected_patient['mname'] ?? '') . ' ' . ($selected_patient['lname'] ?? '')) ?><br>
        Phone: <?= htmlspecialchars($selected_patient['phone_number'] ?? 'N/A') ?><br>
        Address: <?= htmlspecialchars($selected_patient['address'] ?? 'N/A') ?>
    </div>

    <table class="table table-sm table-bordered mb-3">
        <thead class="table-primary">
            <tr>
                <th>Service</th>
                <th>Description</th>
                <th class="text-end">Amount</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $hasRows = false;
            foreach ([$billing_items, $dl_services] as $list) {
                foreach ($list as $it) {
                    $hasRows = true;
                    echo "<tr>
                        <td>" . htmlspecialchars($it['service_name'] ?? '') . "</td>
                        <td>" . htmlspecialchars($it['description'] ?? '') . "</td>
                        <td class='text-end'>₱" . number_format($it['total_price'] ?? 0, 2) . "</td>
                    </tr>";
                }
            }
            if (!$hasRows) {
                echo "<tr><td colspan='3' class='text-center'>No billed or diagnostic services found.</td></tr>";
            }
            ?>
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

<!-- PAYMENT MODAL -->
<div class="modal fade" id="paymentModal" tabindex="-1" aria-labelledby="paymentModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form method="POST">
        <div class="modal-header">
          <h5 class="modal-title" id="paymentModalLabel">Select Payment Method</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>

        <div class="modal-body text-center">
          <input type="hidden" name="payment_method" id="payment_method">
          <div class="d-flex flex-wrap gap-3 justify-content-center mb-4">
            <div class="payment-card" data-value="GCash">
              <i class="bi bi-phone text-primary"></i><br><span>GCash</span>
            </div>
            <div class="payment-card" data-value="Bank">
              <i class="bi bi-bank text-success"></i><br><span>Bank</span>
            </div>
            <div class="payment-card" data-value="Card">
              <i class="bi bi-credit-card text-warning"></i><br><span>Card</span>
            </div>
            <div class="payment-card" data-value="Cash">
              <i class="bi bi-cash-coin text-success"></i><br><span>Cash</span>
            </div>
          </div>

          <div id="gcash_box" class="payment-extra" style="display:none;">
            <img src="<?= $qrImageSrc ?>" alt="GCash QR" class="img-fluid mb-3">
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
