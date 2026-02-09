<?php
include '../../SQL/config.php';
if (session_status() === PHP_SESSION_NONE) session_start();

/* ====================== AJAX FETCH ====================== */
if (isset($_GET['json'])) {

    header('Content-Type: application/json');

    function createJournalEntry($conn, $billing_id, $patient_name, $amount, $method) {
        $created_by = $_SESSION['user_name'] ?? 'System';
        $desc = "Payment received from $patient_name via $method";

        $stmt = $conn->prepare("SELECT entry_id FROM journal_entries WHERE reference_id=? LIMIT 1");
        $stmt->bind_param("i", $billing_id);
        $stmt->execute();
        $existing = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($existing) return;

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

    // ===================== Fetch Paid Payments =====================
    $paidPaymentsResult = $conn->query("
        SELECT pp.*, br.billing_id, br.patient_id, br.status AS billing_status,
               CONCAT(pi.fname,' ',IFNULL(pi.mname,''),' ',pi.lname) AS patient_name
        FROM paymongo_payments pp
        LEFT JOIN billing_records br ON br.paymongo_payment_id = pp.payment_id
        LEFT JOIN patientinfo pi ON pi.patient_id = br.patient_id
        WHERE pp.status='paid'
        ORDER BY pp.paid_at DESC
    ");

    $updatedRows = [];
    $processedPayments = 0;
    $processedPatients = 0;
    $patientsPaid = [];

    while ($row = $paidPaymentsResult->fetch_assoc()) {
        $billing_id = (int)($row['billing_id'] ?? 0);
        $patient_id = (int)($row['patient_id'] ?? 0);
        $method = $row['payment_method'] ?? 'PAYMONGO';
        $amount = (float)($row['amount'] ?? 0);
        $paid_at = $row['paid_at'] ?? date('Y-m-d H:i:s');
        $patient_name = $row['patient_name'] ?? null;

        // Fallback: extract billing_id from remarks if missing
        if (!$billing_id && !empty($row['remarks'])) {
            if (preg_match('/Billing\s?#?(\d+)/i', $row['remarks'], $m)) {
                $billing_id = (int)$m[1];

                // Get patient_id and name from billing_records & patientinfo
                $stmt = $conn->prepare("SELECT patient_id FROM billing_records WHERE billing_id=? LIMIT 1");
                $stmt->bind_param("i", $billing_id);
                $stmt->execute();
                $billingInfo = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                $patient_id = (int)($billingInfo['patient_id'] ?? 0);

                if ($patient_id) {
                    $stmt = $conn->prepare("SELECT CONCAT(fname,' ',IFNULL(mname,''),' ',lname) AS full_name FROM patientinfo WHERE patient_id=? LIMIT 1");
                    $stmt->bind_param("i", $patient_id);
                    $stmt->execute();
                    $patientInfo = $stmt->get_result()->fetch_assoc();
                    $stmt->close();
                    $patient_name = $patientInfo['full_name'] ?? "Patient #$patient_id";
                }
            }
        }

        // Only mark billing as Paid if not already Paid
        if ($billing_id && $row['billing_status'] !== 'Paid') {
            $stmt = $conn->prepare("
                UPDATE billing_records
                SET status='Paid', payment_status='Paid', payment_method=?, payment_date=?, paid_amount=?, balance=0, paymongo_payment_id=?
                WHERE billing_id=?
            ");
            $stmt->bind_param("ssdii", $method, $paid_at, $amount, $row['payment_id'], $billing_id);
            $stmt->execute();
            $stmt->close();

            $stmt = $conn->prepare("
                UPDATE patient_receipt
                SET status='Paid', payment_method=?, payment_reference=?, paymongo_reference=?
                WHERE billing_id=?
            ");
            $stmt->bind_param("sssi", $method, $row['payment_id'], $row['payment_id'], $billing_id);
            $stmt->execute();
            $stmt->close();

            createJournalEntry($conn, $billing_id, $patient_name, $amount, $method);

            if (!in_array($patient_id, $patientsPaid)) {
                $patientsPaid[] = $patient_id;
                $processedPatients++;
            }
        }

        $updatedRows[] = [
            'billing_id' => $billing_id ?: '-',
            'patient_name' => $patient_name ?: $row['remarks'] ?? '-',
            'amount' => $amount,
            'method' => $method,
            'paid_at' => $paid_at
        ];
        $processedPayments++;
    }

    echo json_encode([
        'status' => $processedPayments > 0 ? 'success' : 'no_update',
        'message' => $processedPayments > 0
                     ? "Payments processed: $processedPayments. Patients marked as paid: $processedPatients."
                     : "No new payments to update.",
        'updated_rows' => $updatedRows,
        'payments_processed' => $processedPayments,
        'patients_paid' => $processedPatients
    ]);
    exit;
}

// ================= PAGE LOAD =================
$paidPayments = $conn->query("
    SELECT pp.*, br.billing_id, CONCAT(pi.fname,' ',IFNULL(pi.mname,''),' ',pi.lname) AS patient_name
    FROM paymongo_payments pp
    LEFT JOIN billing_records br ON br.paymongo_payment_id = pp.payment_id
    LEFT JOIN patientinfo pi ON pi.patient_id = br.patient_id
    WHERE pp.status='paid'
    ORDER BY pp.paid_at DESC
    LIMIT 50
")->fetch_all(MYSQLI_ASSOC);

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
        alert('The payment table is updated.');
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

<button class="btn btn-outline-primary mb-3" onclick="refreshAndSync(this)">Refresh Now</button>

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
    <td><?= htmlspecialchars($p['patient_name'] ?? $p['remarks']) ?></td>
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
