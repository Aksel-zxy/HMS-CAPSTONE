<?php
include '../../SQL/config.php';
if (session_status() === PHP_SESSION_NONE) session_start();

require __DIR__ . '/vendor/autoload.php';
use GuzzleHttp\Client;

define('PAYMONGO_SECRET_KEY', 'sk_test_akT1ZW6za7m6FC9S9VqYNiVV');

// ================= AJAX FETCH =================
if (isset($_GET['json'])) {
    header('Content-Type: application/json');

    $client = new Client([
        'base_uri' => 'https://api.paymongo.com/v1/',
        'headers' => [
            'Accept'        => 'application/json',
            'Authorization' => 'Basic ' . base64_encode(PAYMONGO_SECRET_KEY . ':'),
        ],
        'timeout' => 10
    ]);

    try {
        $response = $client->get('payments?limit=50');
        $body = json_decode($response->getBody(), true);
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        exit;
    }

    $payments = $body['data'] ?? [];
    $processedPayments = 0;
    $processedPatients = 0;
    $updatedRows = [];

    foreach ($payments as $p) {
        $attr = $p['attributes'] ?? [];
        if (($attr['status'] ?? '') !== 'paid') continue;

        $payment_id = $p['id'];
        $intent_id  = $attr['payment_intent_id'] ?? null;
        $amount     = ($attr['amount'] ?? 0)/100;
        $remarks    = $attr['remarks'] ?? $attr['description'] ?? '';
        $method     = strtoupper($attr['source']['type'] ?? 'PAYMONGO');

        try {
            $paid_at = (new DateTime($attr['paid_at'] ?? $attr['created_at']))->format('Y-m-d H:i:s');
        } catch (Exception $e) {
            $paid_at = date('Y-m-d H:i:s');
        }

        // Extract billing ID from remarks
        preg_match('/Billing\s?#?(\d+)/i', $remarks, $m);
        $billing_id = $m[1] ?? null;
        $patient_id = null;
        $patient_name = null;

        if ($billing_id) {
            // Get patient info from billing_records
            $stmt = $conn->prepare("SELECT patient_id, status, grand_total, insurance_covered FROM billing_records WHERE billing_id=? LIMIT 1");
            $stmt->bind_param("i", $billing_id);
            $stmt->execute();
            $billing = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if ($billing) {
                $patient_id = (int)$billing['patient_id'];
                $stmt = $conn->prepare("SELECT CONCAT(fname,' ',IFNULL(mname,''),' ',lname) AS full_name FROM patientinfo WHERE patient_id=? LIMIT 1");
                $stmt->bind_param("i", $patient_id);
                $stmt->execute();
                $patient = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                $patient_name = $patient['full_name'] ?? "Patient #$patient_id";

                // Update billing_records if not Paid
                if ($billing['status'] !== 'Paid') {
                    $stmt = $conn->prepare("UPDATE billing_records
                        SET status='Paid', payment_status='Paid', payment_method=?, payment_date=?, paid_amount=?, balance=0, paymongo_payment_id=?, paymongo_payment_intent_id=?
                        WHERE billing_id=?");
                    $stmt->bind_param("ssdssi", $method, $paid_at, $amount, $payment_id, $intent_id, $billing_id);
                    $stmt->execute();
                    $stmt->close();

                    $stmt = $conn->prepare("UPDATE patient_receipt
                        SET status='Paid', payment_method=?, payment_reference=?, paymongo_reference=?
                        WHERE billing_id=?");
                    $stmt->bind_param("sssi", $method, $payment_id, $payment_id, $billing_id);
                    $stmt->execute();
                    $stmt->close();

                    // =========================
                    // CREATE JOURNAL ENTRY
                    // =========================
                    $description = "Payment received from $patient_name. Receipt TXN: $payment_id";
                    $reference   = "Payment received from $patient_name";
                    $entry_date  = date('Y-m-d H:i:s');
                    $module      = 'billing';
                    $created_by  = $_SESSION['username'] ?? 'System';

                    // Insert journal entry with reference as "Payment received from [Patient Name]"
                    $stmt = $conn->prepare("INSERT INTO journal_entries (entry_date, description, reference_type, reference, billing_id, created_at, module, created_by, status) VALUES (?, ?, 'Patient Billing', ?, ?, ?, ?, ?, 'Posted')");
                    $stmt->bind_param("ssissss", $entry_date, $description, $reference, $billing_id, $entry_date, $module, $created_by);
                    $stmt->execute();
                    $entry_id = $conn->insert_id;
                    $stmt->close();

                    // Insert journal entry lines
                    // Debit: Cash / Credit: Accounts Receivable
                    $stmt = $conn->prepare("INSERT INTO journal_entry_lines (entry_id, account_name, debit, credit, description) VALUES (?, 'Cash', ?, 0, ?), (?, 'Accounts Receivable', 0, ?, ?)");
                    $stmt->bind_param("idssid", $entry_id, $amount, $description, $entry_id, $amount, $description);
                    $stmt->execute();
                    $stmt->close();

                    $processedPatients++;
                }
            }
        }

        $processedPayments++;
        $updatedRows[] = [
            'payment_id' => $payment_id,
            'billing_id' => $billing_id,
            'patient_id' => $patient_id,
            'patient_name' => $patient_name,
            'amount' => $amount,
            'method' => $method,
            'status' => $attr['status'],
            'paid_at' => $paid_at,
            'remarks' => $remarks
        ];
    }

    echo json_encode([
        'status'=>'success',
        'message'=>"Payments processed: $processedPayments | Patients updated: $processedPatients",
        'payments_processed'=>$processedPayments,
        'patients_paid'=>$processedPatients,
        'updated_rows'=>$updatedRows
    ]);
    exit;
}


// ================= PAGE LOAD =================
$paidPayments = $conn->query("SELECT * FROM paymongo_payments ORDER BY paid_at DESC")->fetch_all(MYSQLI_ASSOC);
$processedPayments = count($paidPayments);
$processedPatients = $conn->query("SELECT COUNT(DISTINCT patient_id) FROM billing_records WHERE status='Paid'")->fetch_row()[0];
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>PayMongo Payments</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<script>
async function refreshPayments(btn) {
    btn.disabled = true;
    const original = btn.innerHTML;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status"></span> Fetching...';

    try {
        const res = await fetch('fetch_paid_payments.php?json=1');
        const data = await res.json();
        alert(data.message);

        if (data.updated_rows.length) {
            const tbody = document.querySelector('#paymentsTable tbody');
            tbody.innerHTML = ''; // clear previous rows
            data.updated_rows.forEach(row => {
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td>${row.billing_id ?? '-'}</td>
                    <td>${row.patient_name ?? '-'}</td>
                    <td>₱${parseFloat(row.amount).toFixed(2)}</td>
                    <td>${row.method}</td>
                    <td>${row.status}</td>
                    <td>${row.paid_at}</td>
                    <td>${row.remarks}</td>
                    <td>${row.payment_id}</td>
                    <td>${row.payment_intent_id}</td>
                `;
                tbody.prepend(tr);
            });
        }
    } catch (err) {
        console.error(err);
        alert('Failed to fetch payments.');
    } finally {
        btn.disabled = false;
        btn.innerHTML = original;
    }
}
</script>
</head>
<body class="p-4 bg-light">
<div class="container">
<div class="card shadow p-4">

<h4>PayMongo Payments</h4>

<p><strong>Total payments processed:</strong> <?= $processedPayments ?></p>
<p><strong>Total patients marked as paid:</strong> <?= $processedPatients ?></p>

<button class="btn btn-outline-primary mb-3" onclick="refreshPayments(this)">Refresh Payments</button>

<table class="table table-bordered table-striped" id="paymentsTable">
<thead class="table-dark">
<tr>
    <th>Billing ID</th>
    <th>Patient Name</th>
    <th>Amount</th>
    <th>Method</th>
    <th>Status</th>
    <th>Date Paid</th>
    <th>Remarks</th>
    <th>Payment ID</th>
    <th>Payment Intent ID</th>
</tr>
</thead>
<tbody>
<tr><td colspan="9" class="text-center">Click "Refresh Payments" to load data</td></tr>
</tbody>
</table>

</div>
</div>
</body>
</html>
