<?php
include '../../SQL/config.php';
if (session_status() === PHP_SESSION_NONE) session_start();

// -------------------- BillEase API configuration --------------------
$billease_api_key = "YOUR_BILLEASE_API_KEY"; 
$billease_payment_url = "https://api.billease.ph/v1/payment"; 
$use_simulation = true; // true for testing without real API

// -------------------- Get patient info --------------------
$patient_id = isset($_GET['patient_id']) ? intval($_GET['patient_id']) : 0;
$selected_patient = null;
if ($patient_id > 0) {
    $stmt = $conn->prepare("SELECT * FROM patientinfo WHERE patient_id=?");
    $stmt->bind_param("i", $patient_id);
    $stmt->execute();
    $selected_patient = $stmt->get_result()->fetch_assoc();
}

// -------------------- Fetch completed lab results --------------------
$billing_items = [];
$total_charges = 0;
if ($patient_id > 0) {
    $stmt = $conn->prepare("SELECT * FROM dl_results WHERE patientID=? AND status='Completed'");
    $stmt->bind_param("i", $patient_id);
    $stmt->execute();
    $results = $stmt->get_result();

    $service_prices = [];
    $service_stmt = $conn->query("SELECT serviceName, description, price FROM dl_services");
    while($row = $service_stmt->fetch_assoc()){
        $service_prices[$row['serviceName']] = ['description'=>$row['description'], 'price'=>floatval($row['price'])];
    }

    while($row = $results->fetch_assoc()){
        $services = explode(',', $row['result']);
        foreach($services as $s){
            $s = trim($s);
            $price = $service_prices[$s]['price'] ?? 0;
            $desc = $service_prices[$s]['description'] ?? '';
            $billing_items[] = ['service_name'=>$s, 'description'=>$desc, 'total_price'=>$price];
            $total_charges += $price;
        }
    }
}

// -------------------- Insurance coverage --------------------
$insurance_covered = 0;
$stmt = $conn->prepare("SELECT SUM(covered_amount) AS total_covered FROM insurance_requests WHERE patient_id=? AND status='Approved'");
$stmt->bind_param("i", $patient_id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$insurance_covered = floatval($row['total_covered'] ?? 0);

// -------------------- Totals --------------------
$grand_total = $total_charges;
$total_out_of_pocket = max($grand_total - $insurance_covered, 0);

// -------------------- Handle Payment --------------------
$payment_error = '';
$payment_success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pay_online'])) {

    if ($total_out_of_pocket <= 0) {
        $payment_error = "No payment required. Covered by insurance.";
    } else {

        $order_id = "HMS-" . uniqid();

        // -------------------- Insert into patient_receipt --------------------
        $stmt = $conn->prepare("INSERT INTO patient_receipt 
            (patient_id, total_charges, total_out_of_pocket, grand_total, insurance_covered, payment_method, status, billing_date, created_at, transaction_id) 
            VALUES (?, ?, ?, ?, ?, 'BillEase', 'Pending', CURDATE(), NOW(), ?)");
        $stmt->bind_param("idddds", $patient_id, $total_charges, $total_out_of_pocket, $total_out_of_pocket, $insurance_covered, $order_id);

        if($stmt->execute()){
            $receipt_id = $conn->insert_id;

            // -------------------- Simulated Payment --------------------
            if($use_simulation){
                header("Location: billease_simulation.php?receipt_id=$receipt_id&order_id=$order_id&amount=$total_out_of_pocket");
                exit;
            }

            // -------------------- Real BillEase API --------------------
            $payment_data = [
                "amount" => $total_out_of_pocket,
                "currency" => "PHP",
                "order_id" => $order_id,
                "customer" => [
                    "name" => trim(($selected_patient['fname'] ?? '') . ' ' . ($selected_patient['lname'] ?? '')),
                    "email" => $selected_patient['email'] ?? "noemail@example.com",
                    "phone" => $selected_patient['phone_number'] ?? ""
                ],
                "callback_url" => "http://localhost/hmscapstone/billing_and_insurance_management/bill_payment_callback.php",
                "redirect_url" => "http://localhost/hmscapstone/billing_and_insurance_management/payment_success.php"
            ];

            $ch = curl_init($billease_payment_url);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payment_data));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                "Content-Type: application/json",
                "Authorization: Bearer $billease_api_key"
            ]);
            $response = curl_exec($ch);
            $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curl_error = curl_error($ch);
            curl_close($ch);

            if($curl_error){
                $payment_error = "Connection Error: $curl_error";
            } else {
                $result = json_decode($response, true);
                if(isset($result['payment_url'])){
                    header("Location: ".$result['payment_url']);
                    exit;
                } else {
                    $payment_error = "BillEase API error: " . ($result['message'] ?? 'Unknown error');
                }
            }
        } else {
            $payment_error = "Failed to create receipt record.";
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

    <?php if (!empty($payment_error)): ?>
        <div class="alert alert-danger"><?= $payment_error ?></div>
    <?php endif; ?>

    <div class="mb-2">
        <strong>BILLED TO:</strong><br>
        <?= htmlspecialchars($selected_patient ? trim(($selected_patient['fname'] ?? '') . ' ' . ($selected_patient['mname'] ?? '') . ' ' . ($selected_patient['lname'] ?? '')) : 'N/A') ?><br>
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
            <?php if (!empty($billing_items)): foreach($billing_items as $it): ?>
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
        <strong>Insurance Covered: ₱<?= number_format($insurance_covered,2) ?></strong><br>
        <strong class="text-primary">Total to Pay: ₱<?= number_format($total_out_of_pocket,2) ?></strong>
    </div>

    <?php if($total_out_of_pocket>0): ?>
        <form method="POST" class="text-end">
            <button type="submit" name="pay_online" class="btn btn-success btn-lg">
                <i class="bi bi-credit-card me-1"></i> Pay via BillEase
            </button>
        </form>
    <?php else: ?>
        <div class="alert alert-success text-end">Fully covered by insurance. No payment required.</div>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
