<?php
include '../../SQL/config.php';
if (session_status() === PHP_SESSION_NONE) session_start();

require __DIR__ . '/vendor/autoload.php';
use GuzzleHttp\Client;

define('PAYMONGO_SECRET_KEY', 'sk_test_akT1ZW6za7m6FC9S9VqYNiVV');

// ================= AJAX FETCH =================
if (isset($_GET['json'])) {
    $client = new Client([
        'base_uri' => 'https://api.paymongo.com/v1/',
        'headers' => [
            'Accept'        => 'application/json',
            'Authorization' => 'Basic ' . base64_encode(PAYMONGO_SECRET_KEY . ':'),
        ],
        'timeout' => 10
    ]);

    header('Content-Type: application/json');

    function createJournalEntry($conn, $billing_id, $patient_name, $amount, $method) {
        $created_by = $_SESSION['user_name'] ?? 'System';
        $desc = "Payment received from $patient_name via $method";

        $stmt = $conn->prepare("
            INSERT INTO journal_entries 
                (entry_date, description, reference_type, reference_id, reference, status, module, created_by)
            VALUES (NOW(), ?, 'Patient Billing', ?, ?, 'Posted', 'Billing', ?)
        ");
        $stmt->bind_param("siss", $desc, $billing_id, $desc, $created_by);
        $stmt->execute();
        $entry_id = $conn->insert_id;
        $stmt->close();

        $stmt = $conn->prepare("
            INSERT INTO journal_entry_lines (entry_id, account_name, debit, credit, description)
            VALUES (?, ?, ?, 0, ?)
        ");
        $stmt->bind_param("isds", $entry_id, $method, $amount, $desc);
        $stmt->execute();
        $stmt->close();

        $stmt = $conn->prepare("
            INSERT INTO journal_entry_lines (entry_id, account_name, debit, credit, description)
            VALUES (?, 'Patient Revenue', 0, ?, ?)
        ");
        $stmt->bind_param("ids", $entry_id, $amount, $desc);
        $stmt->execute();
        $stmt->close();
    }

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
    $patientsPaid = [];
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
            $dt = new DateTime($attr['paid_at'] ?? $attr['created_at'] ?? 'now');
            $paid_at = $dt->format('Y-m-d H:i:s');
        } catch (Exception $e) {
            $paid_at = date('Y-m-d H:i:s');
        }

        $stmt = $conn->prepare("
            INSERT INTO paymongo_payments
                (payment_id, payment_intent_id, amount, remarks, payment_method, status, paid_at)
            VALUES (?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                amount=VALUES(amount),
                status=VALUES(status),
                paid_at=VALUES(paid_at),
                payment_method=VALUES(payment_method)
        ");
        if ($stmt) {
            $stmt->bind_param("ssdssss", $payment_id, $intent_id, $amount, $remarks, $method, $attr['status'], $paid_at);
            $stmt->execute();
            $stmt->close();
        }

        preg_match('/TXN[A-Z0-9]+/i', $remarks, $m);
        $txn = $m[0] ?? null;
        $billing = null;

        if ($txn) {
            $stmt = $conn->prepare("SELECT billing_id, patient_id, status FROM billing_records WHERE transaction_id=? LIMIT 1");
            $stmt->bind_param("s", $txn);
            $stmt->execute();
            $billing = $stmt->get_result()->fetch_assoc();
            $stmt->close();
        }

        if (empty($billing)) {
            preg_match('/Patient\s#?(\d+)/i', $remarks, $m);
            $patient_id = $m[1] ?? null;
            if ($patient_id) {
                $stmt = $conn->prepare("SELECT billing_id, patient_id, status FROM billing_records WHERE patient_id=? AND status!='Paid' ORDER BY billing_id DESC LIMIT 1");
                $stmt->bind_param("i", $patient_id);
                $stmt->execute();
                $billing = $stmt->get_result()->fetch_assoc();
                $stmt->close();
            }
        }

        if (!$billing || $billing['status'] === 'Paid') continue;

        $billing_id = (int)$billing['billing_id'];
        $patient_id = (int)$billing['patient_id'];

        $stmt = $conn->prepare("SELECT CONCAT(fname,' ',IFNULL(mname,''),' ',lname) AS full_name FROM patientinfo WHERE patient_id=? LIMIT 1");
        $stmt->bind_param("i", $patient_id);
        $stmt->execute();
        $patient = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        $patient_name = $patient['full_name'] ?? "Patient #$patient_id";

        $stmt = $conn->prepare("
            UPDATE billing_records
            SET status='Paid', payment_status='Paid', payment_method=?, payment_date=?, paid_amount=?, balance=0, paymongo_payment_id=?, paymongo_payment_intent_id=?
            WHERE billing_id=?
        ");
        if ($stmt) {
            $stmt->bind_param("ssdssi", $method, $paid_at, $amount, $payment_id, $intent_id, $billing_id);
            $stmt->execute();
            $stmt->close();
        }

        $stmt = $conn->prepare("
            UPDATE patient_receipt
            SET status='Paid', payment_method=?, payment_reference=?, paymongo_reference=?
            WHERE billing_id=?
        ");
        if ($stmt) {
            $stmt->bind_param("sssi", $method, $payment_id, $payment_id, $billing_id);
            $stmt->execute();
            $stmt->close();
        }

        createJournalEntry($conn, $billing_id, $patient_name, $amount, $method);

        if (!in_array($patient_id, $patientsPaid)) {
            $patientsPaid[] = $patient_id;
            $processedPatients++;
        }

        $processedPayments++;
        $updatedRows[] = [
            'billing_id' => $billing_id,
            'patient_name' => $patient_name,
            'amount' => $amount,
            'method' => $method,
            'paid_at' => $paid_at
        ];
    }

    if ($processedPayments === 0) {
        echo json_encode(['status'=>'no_update','message'=>'No updated payments.','updated_rows'=>[]]);
    } else {
        echo json_encode([
            'status'=>'success',
            'message'=>"Payments processed: $processedPayments. Patients marked as paid: $processedPatients.",
            'payments_processed'=>$processedPayments,
            'patients_paid'=>$processedPatients,
            'updated_rows'=>$updatedRows
        ]);
    }
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
<title>PayMongo Payment Sync</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<script>
async function refreshAndSync(btn) {
    btn.disabled = true;
    const original = btn.innerHTML;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Checking...';

    try {
        const res = await fetch('fetch_paid_payments.php?json=1');
        const data = await res.json();
        alert(data.message);

        if(data.updated_rows.length){
            const tbody = document.querySelector('#paymentsTable tbody');
            data.updated_rows.forEach(row => {
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td>${row.billing_id}</td>
                    <td>${row.patient_name}</td>
                    <td>₱${parseFloat(row.amount).toFixed(2)}</td>
                    <td>${row.method}</td>
                    <td>${row.paid_at}</td>
                `;
                tbody.prepend(tr);
            });
        }
    } catch (err) {
        console.error('Sync error', err);
        alert('The payment is updated.');
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

<h4>PayMongo Sync Complete</h4>

<p><strong>Total payments processed:</strong> <?= $processedPayments ?></p>
<p><strong>Total patients successfully marked as paid:</strong> <?= $processedPatients ?></p>

<button class="btn btn-outline-primary mb-3" onclick="refreshAndSync(this)">
    Refresh Now
</button>

<table class="table table-bordered table-striped" id="paymentsTable">
<thead class="table-dark">
<tr>
    <th>Billing ID</th>
    <th>Patient Name</th>
    <th>Amount</th>
    <th>Method</th>
    <th>Date Paid</th>
</tr>
</thead>
<tbody>
<?php foreach ($paidPayments as $p): ?>
<tr>
    <td><?= htmlspecialchars($p['billing_id'] ?? '-') ?></td>
    <td><?= htmlspecialchars($p['remarks'] ?? '-') ?></td>
    <td>₱<?= number_format($p['amount'] ?? 0, 2) ?></td>
    <td><?= htmlspecialchars($p['payment_method'] ?? '-') ?></td>
    <td><?= htmlspecialchars($p['paid_at'] ?? '-') ?></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>

<a href="patient_billing.php" class="btn btn-primary">Back to Billing</a>

</div>
</div>
</body>
</html>
