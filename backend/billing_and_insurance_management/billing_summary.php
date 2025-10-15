<?php
include '../../SQL/config.php';

// Safely start session
if (session_status() === PHP_SESSION_NONE) session_start();

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
    $txn_id = 'TXN' . uniqid();
    $ref = 'N/A';
    $payment_method = trim($_POST['payment_method'] ?? '');

    /* ---------------- BILL EASE INTEGRATION ---------------- */
    if (isset($_POST['make_payment']) && strtolower($payment_method) === 'billease') {

        // ⚙️ Replace with your actual BillEase sandbox credentials
        $merchant_id = 'YOUR_SANDBOX_MERCHANT_ID';
        $private_key = 'YOUR_SANDBOX_PRIVATE_KEY';

        if (empty($merchant_id) || empty($private_key)) {
            echo "<script>alert('BillEase API credentials not configured.');</script>";
            exit;
        }

        $order_id = 'ORD-' . uniqid();
        $amount = (float)$total_out_of_pocket;
        $redirect_url = "https://yourdomain.com/billing/billease_success.php?patient_id={$patient_id}&billing_id={$billing_id}";
        $cancel_url   = "https://yourdomain.com/billing/billing_summary.php?patient_id={$patient_id}";

        // ✅ Proper payload
        $payload = [
            "merchant_id" => $merchant_id,
            "order_id" => $order_id,
            "total_amount" => $amount,
            "currency" => "PHP",
            "redirect_url" => $redirect_url,
            "cancel_url" => $cancel_url,
            "items" => [
                [
                    "name" => "Hospital Bill Payment",
                    "price" => $amount,
                    "quantity" => 1
                ]
            ],
            "metadata" => [
                "patient_id" => $patient_id,
                "billing_id" => $billing_id
            ]
        ];

        $api_url = "https://sandbox.billease.ph/api/checkout"; // ✅ Sandbox endpoint

        $ch = curl_init($api_url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Content-Type: application/json",
            "Authorization: Bearer $private_key"
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $response = curl_exec($ch);
        $curl_error = curl_error($ch);
        $http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $result = json_decode($response, true);

        // 🧩 Debug (remove in production)
        if ($curl_error) {
            echo "<pre>⚠️ CURL Error: $curl_error</pre>";
        } elseif ($http_status !== 200) {
            echo "<pre>⚠️ HTTP Status: $http_status\nResponse: $response</pre>";
        }

        if (isset($result['checkout_url'])) {
            // ✅ Save pending record
            $stmt = $conn->prepare("INSERT INTO patient_receipt 
                (patient_id, billing_id, total_charges, total_vat, total_discount, total_out_of_pocket, grand_total, billing_date, insurance_covered, payment_method, status, transaction_id, payment_reference, is_pwd)
                VALUES (?, ?, ?, 0, ?, ?, ?, CURDATE(), ?, 'BillEase', 'Pending', ?, 'Pending', ?)
            ");
            $is_pwd = $selected_patient['is_pwd'] ?? 0;
            $stmt->bind_param("iidddddssi",
                $patient_id,
                $billing_id,
                $total_charges,
                $total_discount,
                $total_out_of_pocket,
                $grand_total,
                $insurance_covered,
                $txn_id,
                $is_pwd
            );
            $stmt->execute();

            header("Location: " . $result['checkout_url']);
            exit;
        } else {
            $error_msg = $result['message'] ?? ($result['error'] ?? 'Unknown API error');
            echo "<script>alert('BillEase API Error: " . htmlspecialchars($error_msg) . "');</script>";
            echo "<pre>BillEase Full Response:\n" . print_r($result, true) . "</pre>";
            exit;
        }
    }

    /* ---------------- INSURANCE PAYMENT HANDLING ---------------- */
    if (isset($_POST['confirm_paid']) && $total_out_of_pocket == 0) {
        $payment_method = $insurance_company ?: "Insurance";
        $ref = "Covered by Insurance";

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
        $stmt->execute();

        echo "<script>alert('Insurance payment recorded successfully!'); window.location='billing_records.php';</script>";
        exit;
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
<link rel="stylesheet" type="text/css" href="assets/css/billing_sidebar.css">
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
            <div class="payment-card" data-value="BillEase">
              <img src="https://billease.ph/images/logo.svg" alt="BillEase" width="80">
              <div>BillEase</div>
            </div>
          </div>
          <input type="hidden" id="payment_method" name="payment_method" required>
        </div>

        <!-- BillEase Info -->
        <div id="billease_box" class="payment-extra text-center">
          <p class="fw-bold">You’ll be redirected to BillEase to complete your payment securely.</p>
        </div>
      </div>

      <div class="modal-footer">
        <button type="submit" name="make_payment" class="btn btn-success">
          <i class="bi bi-check-circle me-1"></i> Proceed with BillEase
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
    if(this.dataset.value === 'BillEase') document.getElementById('billease_box').style.display = 'block';
  });
});
</script>
</body>
</html>
