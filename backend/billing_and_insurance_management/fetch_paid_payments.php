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
        'timeout' => 20,
        'verify'  => false // disable SSL verification for localhost testing
    ]);

    $processedPayments = 0;
    $processedPatients = 0;
    $updatedRows       = [];
    $startingAfter     = null;

    try {
        do {
            $url = 'payments?limit=50';
            if ($startingAfter) $url .= '&starting_after=' . $startingAfter;

            $response      = $client->get($url);
            $body          = json_decode($response->getBody(), true);
            $payments      = $body['data'] ?? [];
            $startingAfter = $body['meta']['next']['starting_after'] ?? null;

            foreach ($payments as $p) {
                $attr = $p['attributes'] ?? [];
                if (($attr['status'] ?? '') !== 'paid') continue;

                $payment_id = $p['id'];
                $intent_id  = $attr['payment_intent_id'] ?? null;
                $amount     = ($attr['amount'] ?? 0) / 100;
                $remarks    = $attr['remarks']     ?? $attr['description'] ?? '';
                $method     = strtoupper($attr['source']['type'] ?? 'PAYMONGO');

                try {
                    $paid_at = (new DateTime($attr['paid_at'] ?? $attr['created_at']))->format('Y-m-d H:i:s');
                } catch (Exception $e) {
                    $paid_at = date('Y-m-d H:i:s');
                }

                // ─────────────────────────────────────────────────────
                // STRATEGY 1: Extract billing_id from remarks
                //   Matches: "Billing #123", "Billing123", "Billing #123"
                // ─────────────────────────────────────────────────────
                $billing_id = null;
                preg_match('/Billing\s?#?(\d+)/i', $remarks, $m);
                if (!empty($m[1])) $billing_id = (int)$m[1];

                // ─────────────────────────────────────────────────────
                // STRATEGY 2: Extract billing_id from description field
                //   Matches: "Hospital Bill #123"
                // ─────────────────────────────────────────────────────
                if (!$billing_id) {
                    $description = $attr['description'] ?? '';
                    preg_match('/(?:Hospital Bill|Billing)\s?#?(\d+)/i', $description, $m2);
                    if (!empty($m2[1])) $billing_id = (int)$m2[1];
                }

                // ─────────────────────────────────────────────────────
                // STRATEGY 3: Look up by transaction_id in remarks
                //   Matches: "TXN:TXN-20240101-001" or "TXN TXN-..."
                // ─────────────────────────────────────────────────────
                if (!$billing_id) {
                    preg_match('/TXN[:\s]?([A-Z0-9\-]+)/i', $remarks, $m3);
                    $txn_id = $m3[1] ?? null;
                    if ($txn_id) {
                        $stmt = $conn->prepare("SELECT billing_id FROM billing_records WHERE transaction_id = ? LIMIT 1");
                        $stmt->bind_param("s", $txn_id);
                        $stmt->execute();
                        $row = $stmt->get_result()->fetch_assoc();
                        $stmt->close();
                        if ($row) $billing_id = (int)$row['billing_id'];
                    }
                }

                // ─────────────────────────────────────────────────────
                // STRATEGY 4: Look up by payment_id / link_id in billing_records
                // ─────────────────────────────────────────────────────
                if (!$billing_id) {
                    $stmt = $conn->prepare("
                        SELECT billing_id FROM billing_records
                        WHERE paymongo_payment_id = ?
                           OR paymongo_link_id    = ?
                           OR paymongo_reference_number = ?
                        LIMIT 1
                    ");
                    $stmt->bind_param("sss", $payment_id, $payment_id, $payment_id);
                    $stmt->execute();
                    $row = $stmt->get_result()->fetch_assoc();
                    $stmt->close();
                    if ($row) $billing_id = (int)$row['billing_id'];
                }

                // ─────────────────────────────────────────────────────
                // STRATEGY 5: Look up by existing paymongo_payments record
                // ─────────────────────────────────────────────────────
                if (!$billing_id) {
                    $stmt = $conn->prepare("SELECT billing_id FROM paymongo_payments WHERE payment_id = ? LIMIT 1");
                    $stmt->bind_param("s", $payment_id);
                    $stmt->execute();
                    $row = $stmt->get_result()->fetch_assoc();
                    $stmt->close();
                    if ($row && !empty($row['billing_id'])) $billing_id = (int)$row['billing_id'];
                }

                // ─────────────────────────────────────────────────────
                // RESOLVE PATIENT FROM billing_id
                // ─────────────────────────────────────────────────────
                $patient_id   = null;
                $patient_name = null;

                if ($billing_id) {
                    $stmt = $conn->prepare("SELECT patient_id, status FROM billing_records WHERE billing_id = ? LIMIT 1");
                    $stmt->bind_param("i", $billing_id);
                    $stmt->execute();
                    $billing = $stmt->get_result()->fetch_assoc();
                    $stmt->close();

                    if ($billing) {
                        $patient_id = (int)$billing['patient_id'];

                        $stmt = $conn->prepare("
                            SELECT CONCAT(fname,' ',IFNULL(NULLIF(TRIM(mname),''),''),' ',lname) AS full_name
                            FROM patientinfo
                            WHERE patient_id = ? LIMIT 1
                        ");
                        $stmt->bind_param("i", $patient_id);
                        $stmt->execute();
                        $patient      = $stmt->get_result()->fetch_assoc();
                        $stmt->close();
                        $patient_name = trim($patient['full_name'] ?? "Patient #$patient_id");

                        // Only update if not already Paid
                        if ($billing['status'] !== 'Paid') {
                            // Update billing_records
                            $stmt = $conn->prepare("
                                UPDATE billing_records
                                SET status                      = 'Paid',
                                    payment_status              = 'Paid',
                                    payment_method              = ?,
                                    payment_date                = ?,
                                    paid_amount                 = ?,
                                    balance                     = 0,
                                    paymongo_payment_id         = ?,
                                    paymongo_payment_intent_id  = ?
                                WHERE billing_id = ?
                            ");
                            $stmt->bind_param("ssdssi", $method, $paid_at, $amount, $payment_id, $intent_id, $billing_id);
                            $stmt->execute();
                            $stmt->close();

                            // Update patient_receipt
                            $stmt = $conn->prepare("
                                UPDATE patient_receipt
                                SET status              = 'Paid',
                                    payment_method      = ?,
                                    payment_reference   = ?,
                                    paymongo_reference  = ?
                                WHERE billing_id = ?
                            ");
                            $stmt->bind_param("sssi", $method, $payment_id, $payment_id, $billing_id);
                            $stmt->execute();
                            $stmt->close();

                            $processedPatients++;
                        }
                    }
                }

                // ─────────────────────────────────────────────────────
                // UPSERT INTO paymongo_payments
                // ─────────────────────────────────────────────────────
                $stmt = $conn->prepare("SELECT id FROM paymongo_payments WHERE payment_id = ? LIMIT 1");
                $stmt->bind_param("s", $payment_id);
                $stmt->execute();
                $exists = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                if ($exists) {
                    $stmt = $conn->prepare("
                        UPDATE paymongo_payments
                        SET amount         = ?,
                            status         = ?,
                            paid_at        = ?,
                            payment_method = ?,
                            remarks        = ?,
                            billing_id     = ?,
                            patient_id     = ?
                        WHERE payment_id   = ?
                    ");
                    $stmt->bind_param("dssssiis", $amount, $attr['status'], $paid_at, $method, $remarks, $billing_id, $patient_id, $payment_id);
                    $stmt->execute();
                    $stmt->close();
                } else {
                    $stmt = $conn->prepare("
                        INSERT INTO paymongo_payments
                            (payment_id, payment_intent_id, amount, status, paid_at, payment_method, remarks, billing_id, patient_id)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->bind_param("ssdssssii", $payment_id, $intent_id, $amount, $attr['status'], $paid_at, $method, $remarks, $billing_id, $patient_id);
                    $stmt->execute();
                    $stmt->close();
                }

                $processedPayments++;
                $updatedRows[] = [
                    'payment_id'        => $payment_id,
                    'payment_intent_id' => $intent_id,
                    'billing_id'        => $billing_id,
                    'patient_id'        => $patient_id,
                    'patient_name'      => $patient_name,
                    'amount'            => $amount,
                    'method'            => $method,
                    'status'            => $attr['status'],
                    'paid_at'           => $paid_at,
                    'remarks'           => $remarks,
                ];
            }

        } while ($startingAfter);

    } catch (Exception $e) {
        echo json_encode([
            'status'  => 'error',
            'message' => 'Failed to fetch payments: ' . $e->getMessage(),
            'trace'   => $e->getTraceAsString(),
        ]);
        exit;
    }

    echo json_encode([
        'status'             => 'success',
        'message'            => "Payments processed: $processedPayments | Patients updated: $processedPatients",
        'payments_processed' => $processedPayments,
        'patients_paid'      => $processedPatients,
        'updated_rows'       => $updatedRows,
    ]);
    exit;
}

// ================= PAGE LOAD =================
$paidPayments      = $conn->query("SELECT * FROM paymongo_payments ORDER BY paid_at DESC")->fetch_all(MYSQLI_ASSOC);
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
        const res  = await fetch('fetch_paid_payments.php?json=1');
        const data = await res.json();
        alert(data.message);

        if (data.updated_rows && data.updated_rows.length) {
            const tbody = document.querySelector('#paymentsTable tbody');
            tbody.innerHTML = '';
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
                    <td>${row.payment_intent_id ?? '-'}</td>
                `;
                tbody.prepend(tr);
            });
        }
    } catch (err) {
        console.error(err);
        alert('Failed to fetch payments.');
    } finally {
        btn.disabled  = false;
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
<?php if (empty($paidPayments)): ?>
<tr><td colspan="9" class="text-center text-muted">Click "Refresh Payments" to load data</td></tr>
<?php else: ?>
<?php foreach ($paidPayments as $pay):
    // Resolve patient name if patient_id is known
    $pname = '-';
    if (!empty($pay['patient_id'])) {
        $s = $conn->prepare("SELECT CONCAT(fname,' ',IFNULL(NULLIF(TRIM(mname),''),''),' ',lname) AS n FROM patientinfo WHERE patient_id=? LIMIT 1");
        $s->bind_param("i", $pay['patient_id']); $s->execute();
        $r = $s->get_result()->fetch_assoc(); $s->close();
        if ($r) $pname = trim($r['n']);
    }
?>
<tr>
    <td><?= htmlspecialchars($pay['billing_id'] ?? '-') ?></td>
    <td><?= htmlspecialchars($pname) ?></td>
    <td>₱<?= number_format($pay['amount'], 2) ?></td>
    <td><?= htmlspecialchars($pay['payment_method'] ?? '-') ?></td>
    <td><?= htmlspecialchars($pay['status'] ?? '-') ?></td>
    <td><?= htmlspecialchars($pay['paid_at'] ?? '-') ?></td>
    <td><?= htmlspecialchars($pay['remarks'] ?? '-') ?></td>
    <td><?= htmlspecialchars($pay['payment_id']) ?></td>
    <td><?= htmlspecialchars($pay['payment_intent_id'] ?? '-') ?></td>
</tr>
<?php endforeach; ?>
<?php endif; ?>
</tbody>
</table>

</div>
</div>
</body>
</html>