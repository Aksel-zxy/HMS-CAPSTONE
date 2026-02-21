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
        'headers'  => [
            'Accept'        => 'application/json',
            'Authorization' => 'Basic ' . base64_encode(PAYMONGO_SECRET_KEY . ':'),
        ],
        'timeout' => 20,
        'verify'  => false,
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

                $payment_id  = $p['id'];
                $intent_id   = $attr['payment_intent_id'] ?? null;
                $amount      = ($attr['amount'] ?? 0) / 100;
                $remarks     = $attr['remarks']     ?? $attr['description'] ?? '';
                $description = $attr['description'] ?? '';
                $method      = strtoupper($attr['source']['type'] ?? 'PAYMONGO');

                try {
                    $paid_at = (new DateTime('@' . ($attr['paid_at'] ?? $attr['created_at'])))->format('Y-m-d H:i:s');
                } catch (Exception $e) {
                    $paid_at = date('Y-m-d H:i:s');
                }

                $billing_id    = null;
                $strategy_used = 'none';

                // ─────────────────────────────────────────────────────
                // STRATEGY 1: billing_id from remarks "Billing #123"
                // ─────────────────────────────────────────────────────
                preg_match('/Billing\s?#?(\d+)/i', $remarks, $m);
                if (!empty($m[1])) {
                    $billing_id    = (int)$m[1];
                    $strategy_used = 'remarks_billing_id';
                }

                // ─────────────────────────────────────────────────────
                // STRATEGY 2: billing_id from description field
                // ─────────────────────────────────────────────────────
                if (!$billing_id) {
                    preg_match('/(?:Hospital Bill|Billing)\s?#?(\d+)/i', $description, $m2);
                    if (!empty($m2[1])) {
                        $billing_id    = (int)$m2[1];
                        $strategy_used = 'description_billing_id';
                    }
                }

                // ─────────────────────────────────────────────────────
                // STRATEGY 3: transaction_id in remarks
                // ─────────────────────────────────────────────────────
                if (!$billing_id) {
                    preg_match('/TXN[:\s\-]?([A-Z0-9\-]+)/i', $remarks . ' ' . $description, $m3);
                    $txn_id = $m3[1] ?? null;
                    if ($txn_id) {
                        $stmt = $conn->prepare("SELECT billing_id FROM billing_records WHERE transaction_id = ? LIMIT 1");
                        $stmt->bind_param("s", $txn_id);
                        $stmt->execute();
                        $row = $stmt->get_result()->fetch_assoc();
                        $stmt->close();
                        if ($row) {
                            $billing_id    = (int)$row['billing_id'];
                            $strategy_used = 'txn_id';
                        }
                    }
                }

                // ─────────────────────────────────────────────────────
                // STRATEGY 4: paymongo_payment_id / link_id already stored
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
                    if ($row) {
                        $billing_id    = (int)$row['billing_id'];
                        $strategy_used = 'paymongo_id_in_billing';
                    }
                }

                // ─────────────────────────────────────────────────────
                // STRATEGY 5: existing paymongo_payments record
                // ─────────────────────────────────────────────────────
                if (!$billing_id) {
                    $stmt = $conn->prepare("SELECT billing_id FROM paymongo_payments WHERE payment_id = ? AND billing_id IS NOT NULL LIMIT 1");
                    $stmt->bind_param("s", $payment_id);
                    $stmt->execute();
                    $row = $stmt->get_result()->fetch_assoc();
                    $stmt->close();
                    if ($row && !empty($row['billing_id'])) {
                        $billing_id    = (int)$row['billing_id'];
                        $strategy_used = 'paymongo_payments_table';
                    }
                }

                // ─────────────────────────────────────────────────────
                // STRATEGY 6: Match by exact grand_total amount on a
                //   PENDING billing record (most recent match wins).
                //   This handles cases where no reference was embedded.
                // ─────────────────────────────────────────────────────
                if (!$billing_id && $amount > 0) {
                    $stmt = $conn->prepare("
                        SELECT billing_id FROM billing_records
                        WHERE grand_total = ?
                          AND status NOT IN ('Paid', 'Cancelled')
                        ORDER BY billing_id DESC
                        LIMIT 1
                    ");
                    $stmt->bind_param("d", $amount);
                    $stmt->execute();
                    $row = $stmt->get_result()->fetch_assoc();
                    $stmt->close();
                    if ($row) {
                        $billing_id    = (int)$row['billing_id'];
                        $strategy_used = 'amount_match';
                    }
                }

                // ─────────────────────────────────────────────────────
                // STRATEGY 7: Match by total_amount (subtotal) if grand_total didn't match
                // ─────────────────────────────────────────────────────
                if (!$billing_id && $amount > 0) {
                    $stmt = $conn->prepare("
                        SELECT billing_id FROM billing_records
                        WHERE total_amount = ?
                          AND status NOT IN ('Paid', 'Cancelled')
                        ORDER BY billing_id DESC
                        LIMIT 1
                    ");
                    $stmt->bind_param("d", $amount);
                    $stmt->execute();
                    $row = $stmt->get_result()->fetch_assoc();
                    $stmt->close();
                    if ($row) {
                        $billing_id    = (int)$row['billing_id'];
                        $strategy_used = 'total_amount_match';
                    }
                }

                // ─────────────────────────────────────────────────────
                // RESOLVE PATIENT FROM billing_id
                // ─────────────────────────────────────────────────────
                $patient_id   = null;
                $patient_name = null;
                $was_updated  = false;

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
                            FROM patientinfo WHERE patient_id = ? LIMIT 1
                        ");
                        $stmt->bind_param("i", $patient_id);
                        $stmt->execute();
                        $pat          = $stmt->get_result()->fetch_assoc();
                        $stmt->close();
                        $patient_name = trim($pat['full_name'] ?? "Patient #$patient_id");

                        // Only update if not already Paid
                        if ($billing['status'] !== 'Paid') {
                            // Update billing_records — mark as Paid
                            $stmt = $conn->prepare("
                                UPDATE billing_records
                                SET status                     = 'Paid',
                                    payment_status             = 'Paid',
                                    payment_method             = ?,
                                    payment_date               = ?,
                                    paid_amount                = ?,
                                    balance                    = 0,
                                    paymongo_payment_id        = ?,
                                    paymongo_payment_intent_id = ?
                                WHERE billing_id = ?
                            ");
                            $stmt->bind_param("ssdssi", $method, $paid_at, $amount, $payment_id, $intent_id, $billing_id);
                            $stmt->execute();
                            $stmt->close();

                            // Update patient_receipt — mark as Paid
                            $stmt = $conn->prepare("
                                UPDATE patient_receipt
                                SET status             = 'Paid',
                                    payment_method     = ?,
                                    payment_reference  = ?,
                                    paymongo_reference = ?
                                WHERE billing_id = ?
                            ");
                            $stmt->bind_param("sssi", $method, $payment_id, $payment_id, $billing_id);
                            $stmt->execute();
                            $stmt->close();

                            $processedPatients++;
                            $was_updated = true;
                        }
                    }
                }

                // ─────────────────────────────────────────────────────
                // UPSERT INTO paymongo_payments (only when billing_id known)
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
                            status         = 'paid',
                            paid_at        = ?,
                            payment_method = ?,
                            remarks        = ?,
                            billing_id     = ?,
                            patient_id     = ?
                        WHERE payment_id   = ?
                    ");
                    $stmt->bind_param("dsssiis", $amount, $paid_at, $method, $remarks, $billing_id, $patient_id, $payment_id);
                    $stmt->execute();
                    $stmt->close();
                } else {
                    $stmt = $conn->prepare("
                        INSERT INTO paymongo_payments
                            (payment_id, payment_intent_id, amount, status, paid_at, payment_method, remarks, billing_id, patient_id)
                        VALUES (?, ?, ?, 'paid', ?, ?, ?, ?, ?)
                    ");
                    $stmt->bind_param("ssdssiii", $payment_id, $intent_id, $amount, $paid_at, $method, $remarks, $billing_id, $patient_id);
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
                    'strategy_used'     => $strategy_used,  // 👈 debug: shows which strategy matched
                    'was_updated'       => $was_updated,
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
<style>
body { padding: 24px; background: #f8fafc; }
.strategy-badge { display:inline-block; padding:2px 8px; border-radius:999px; font-size:.68rem; font-weight:700; }
.s-none         { background:#fee2e2; color:#991b1b; }
.s-match        { background:#d1fae5; color:#065f46; }
.s-txn          { background:#dbeafe; color:#1d4ed8; }
.updated-yes    { background:#d1fae5; color:#065f46; font-weight:700; padding:2px 8px; border-radius:999px; font-size:.72rem; }
.updated-no     { background:#f1f5f9; color:#64748b; padding:2px 8px; border-radius:999px; font-size:.72rem; }
</style>
<script>
async function refreshPayments(btn) {
    btn.disabled = true;
    const original = btn.innerHTML;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Fetching…';
    try {
        const res  = await fetch('fetch_paid_payments.php?json=1');
        const data = await res.json();

        if (data.status === 'error') {
            alert('Error: ' + data.message);
            return;
        }

        alert(data.message + '\n\nCheck the table below — strategy_used column shows how each payment was matched.');

        if (data.updated_rows && data.updated_rows.length) {
            const tbody = document.querySelector('#paymentsTable tbody');
            tbody.innerHTML = '';
            data.updated_rows.forEach(row => {
                const stratClass = row.strategy_used === 'none' ? 's-none' :
                                   row.strategy_used.includes('txn') ? 's-txn' : 's-match';
                const updClass   = row.was_updated ? 'updated-yes' : 'updated-no';
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td>${row.billing_id ?? '<span class="text-danger fw-bold">NULL</span>'}</td>
                    <td>${row.patient_name ?? '-'}</td>
                    <td>₱${parseFloat(row.amount).toFixed(2)}</td>
                    <td>${row.method}</td>
                    <td>${row.status}</td>
                    <td>${row.paid_at}</td>
                    <td><span class="strategy-badge ${stratClass}">${row.strategy_used}</span></td>
                    <td><span class="${updClass}">${row.was_updated ? '✓ Updated' : 'No change'}</span></td>
                    <td style="font-size:.75rem">${row.payment_id}</td>
                `;
                tbody.prepend(tr);
            });
        }
    } catch (err) {
        console.error(err);
        alert('Failed to fetch payments: ' + err.message);
    } finally {
        btn.disabled  = false;
        btn.innerHTML = original;
    }
}
</script>
</head>
<body>
<div class="container-fluid">
<div class="card shadow-sm p-4">

<h4 class="mb-1">PayMongo Payments</h4>
<p class="text-muted mb-3" style="font-size:.85rem;">
    Total in DB: <strong><?= $processedPayments ?></strong> &nbsp;|&nbsp;
    Patients marked Paid: <strong><?= $processedPatients ?></strong>
</p>

<button class="btn btn-outline-primary mb-3" onclick="refreshPayments(this)">
    <i class="bi bi-arrow-clockwise"></i> Refresh &amp; Sync Payments
</button>

<div class="alert alert-info py-2" style="font-size:.83rem;">
    <strong>Tip:</strong> If a patient still shows Pending after syncing, check the <strong>strategy_used</strong> column.
    If it shows <span style="background:#fee2e2;color:#991b1b;border-radius:4px;padding:1px 6px;font-size:.75rem;">none</span>,
    the payment could not be matched to a billing record — the PayMongo payment has no billing reference embedded in its remarks/description,
    and no pending bill matches the exact amount. Fix: embed the billing ID when creating the PayMongo payment link.
</div>

<div style="overflow-x:auto;">
<table class="table table-bordered table-striped table-sm" id="paymentsTable">
<thead class="table-dark">
<tr>
    <th>Billing ID</th>
    <th>Patient Name</th>
    <th>Amount</th>
    <th>Method</th>
    <th>Status</th>
    <th>Date Paid</th>
    <th>Strategy Used</th>
    <th>DB Updated?</th>
    <th>Payment ID</th>
</tr>
</thead>
<tbody>
<?php if (empty($paidPayments)): ?>
<tr><td colspan="9" class="text-center text-muted py-4">Click "Refresh & Sync Payments" to load data from PayMongo</td></tr>
<?php else: ?>
<?php foreach ($paidPayments as $pay):
    $pname = '-';
    if (!empty($pay['patient_id'])) {
        $s = $conn->prepare("SELECT CONCAT(fname,' ',IFNULL(NULLIF(TRIM(mname),''),''),' ',lname) AS n FROM patientinfo WHERE patient_id=? LIMIT 1");
        $s->bind_param("i", $pay['patient_id']); $s->execute();
        $r = $s->get_result()->fetch_assoc(); $s->close();
        if ($r) $pname = trim($r['n']);
    }
    // Check current billing status
    $billing_status = '-';
    if (!empty($pay['billing_id'])) {
        $s = $conn->prepare("SELECT status FROM billing_records WHERE billing_id=? LIMIT 1");
        $s->bind_param("i", $pay['billing_id']); $s->execute();
        $r = $s->get_result()->fetch_assoc(); $s->close();
        if ($r) $billing_status = $r['status'];
    }
?>
<tr>
    <td>
        <?php if (empty($pay['billing_id'])): ?>
            <span class="text-danger fw-bold">NULL</span>
        <?php else: ?>
            #<?= htmlspecialchars($pay['billing_id']) ?>
            <small class="text-muted d-block"><?= htmlspecialchars($billing_status) ?></small>
        <?php endif; ?>
    </td>
    <td><?= htmlspecialchars($pname) ?></td>
    <td>₱<?= number_format($pay['amount'], 2) ?></td>
    <td><?= htmlspecialchars($pay['payment_method'] ?? '-') ?></td>
    <td><?= htmlspecialchars($pay['status'] ?? '-') ?></td>
    <td><?= htmlspecialchars($pay['paid_at'] ?? '-') ?></td>
    <td><small class="text-muted">see sync</small></td>
    <td>
        <?php if ($billing_status === 'Paid'): ?>
            <span class="badge bg-success">✓ Paid</span>
        <?php elseif (!empty($pay['billing_id'])): ?>
            <span class="badge bg-warning text-dark">Still Pending</span>
        <?php else: ?>
            <span class="badge bg-danger">No match</span>
        <?php endif; ?>
    </td>
    <td style="font-size:.72rem"><?= htmlspecialchars($pay['payment_id']) ?></td>
</tr>
<?php endforeach; ?>
<?php endif; ?>
</tbody>
</table>
</div>

</div>
</div>
</body>
</html>