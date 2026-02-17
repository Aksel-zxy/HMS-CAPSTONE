<?php
session_start();
include '../../SQL/config.php';

/* ================================
   FETCH PAYMONGO PAYMENTS
   JOIN patientinfo VIA billing_records IF NEEDED
================================ */
$payments_sql = "
SELECT
    pp.payment_id,
    pp.amount,
    pp.payment_method,
    pp.paid_at,
    pp.remarks,
    br.billing_id,
    br.patient_id,
    pi.patient_id AS pi_patient_id,
    pi.fname,
    pi.mname,
    pi.lname
FROM paymongo_payments pp
LEFT JOIN billing_records br ON pp.billing_id = br.billing_id
LEFT JOIN patientinfo pi ON br.patient_id = pi.patient_id
ORDER BY pp.paid_at DESC
";

$payments = $conn->query($payments_sql)->fetch_all(MYSQLI_ASSOC);

/* ================================
   FETCH RECEIPTS FOR MANUAL / CASH / INSURANCE
   JOIN VIA billing_records → patientinfo
================================ */
$receipts_sql = "
SELECT
    pr.receipt_id,
    pr.status,
    pr.payment_method,
    br.transaction_id,
    pr.created_at AS receipt_created,
    br.billing_id,
    br.patient_id,
    pi.patient_id AS pi_patient_id,
    pi.fname,
    pi.mname,
    pi.lname,
    br.billing_date,
    br.grand_total,
    br.insurance_covered
FROM billing_records br
INNER JOIN (
    SELECT billing_id, MAX(receipt_id) AS latest_receipt_id
    FROM patient_receipt
    GROUP BY billing_id
) latest ON latest.billing_id = br.billing_id
INNER JOIN patient_receipt pr ON pr.receipt_id = latest.latest_receipt_id
LEFT JOIN patientinfo pi ON pi.patient_id = br.patient_id
ORDER BY br.billing_date DESC
";

$receipts = $conn->query($receipts_sql)->fetch_all(MYSQLI_ASSOC);

/* ================================
   FETCH EXISTING JOURNAL ENTRIES
================================ */
$stmt = $conn->prepare("SELECT * FROM journal_entries ORDER BY entry_date DESC");
$stmt->execute();
$entries = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

/* ================================
   HELPER FUNCTION: Get Patient Full Name
================================ */
function getPatientName($fname, $mname, $lname, $patient_id = null) {
    $full_name = trim($fname . ' ' . $mname . ' ' . $lname);
    
    if (!empty($full_name)) {
        return $full_name;
    }
    
    // Fallback: Show patient ID if name is missing
    if (!empty($patient_id)) {
        return "Unknown Patient (ID: {$patient_id})";
    }
    
    return "Unknown Patient";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Journal Management Module</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
body { background-color: #f8f9fa; }
.container-wrapper { background-color: white; border-radius: 20px; padding: 30px; margin-top: 50px; }
.table thead { background-color: #007bff; color: white; }
.debit { color: green; font-weight: bold; }
.credit { color: red; font-weight: bold; }
.btn-view { background-color: #17a2b8; color: white; padding: 6px 12px; border-radius: 4px; text-decoration: none; font-size: 12px; cursor: pointer; }
.btn-view:hover { background-color: #138496; text-decoration: none; }
.reference-info { white-space: pre-line; font-size: 0.85em; }
.modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,.6); justify-content: center; align-items: center; }
.modal-content { background: #fff; padding: 20px; width: 600px; border-radius: 8px; max-height: 90vh; overflow-y: auto; }
.close-btn { background: none; border: none; font-size: 24px; cursor: pointer; }
.unknown-patient { background-color: #fff3cd; color: #856404; padding: 8px; border-radius: 4px; font-weight: 500; }
.entry-amount { text-align: right; font-weight: 500; }
</style>
</head>
<body class="p-4">

<div class="main-sidebar">
<?php include 'billing_sidebar.php'; ?>
</div>

<div class="container container-wrapper">
<h2>Journal Entries - Payments</h2>
<p class="text-muted">All payment transactions recorded in the billing system</p>

<table class="table table-bordered table-striped mt-3">
<thead>
<tr>
<th>Date</th>
<th>Debit Account</th>
<th>Credit Account</th>
<th>Amount</th>
<th>Description</th>
<th>Reference</th>
<th>Action</th>
</tr>
</thead>
<tbody>

<?php foreach ($payments as $p): 
    // Get patient name with fallback
    $full_name = getPatientName($p['fname'] ?? '', $p['mname'] ?? '', $p['lname'] ?? '', $p['patient_id'] ?? null);
    
    // Determine if this is an unknown patient
    $is_unknown = strpos($full_name, 'Unknown') === 0;
    
    // Build description
    $method = !empty($p['payment_method']) ? $p['payment_method'] : 'CASH';
    $remarks = !empty($p['remarks']) ? $p['remarks'] : "Billing #" . ($p['billing_id'] ?? 'N/A');
    
    $description = "Payment received from {$full_name}\nMethod: {$method}\nRemarks: {$remarks}";
    $amount = number_format($p['amount'], 2);
    $date = !empty($p['paid_at']) ? date('Y-m-d H:i:s', strtotime($p['paid_at'])) : 'N/A';
?>
<tr <?php echo $is_unknown ? 'style="background-color: #fff3cd;"' : ''; ?>>
<td><?= htmlspecialchars($date) ?></td>
<td class="debit">Cash / Bank</td>
<td class="credit">Patient Receivable</td>
<td class="entry-amount">₱<?= $amount ?></td>
<td class="reference-info">
    <?php if ($is_unknown): ?>
        <span class="unknown-patient">⚠️ <?= htmlspecialchars($full_name) ?></span><br>
    <?php endif; ?>
    <?= nl2br(htmlspecialchars($description)) ?>
</td>
<td><?= htmlspecialchars($p['payment_id']) ?></td>
<td>
    <a href="journal_entry_line.php?payment_id=<?= urlencode($p['payment_id']) ?>" class="btn-view">View</a>
</td>
</tr>
<?php endforeach; ?>

<?php foreach ($receipts as $r): 
    // Get patient name with fallback
    $full_name = getPatientName($r['fname'] ?? '', $r['mname'] ?? '', $r['lname'] ?? '', $r['patient_id'] ?? null);
    
    // Determine if this is an unknown patient
    $is_unknown = strpos($full_name, 'Unknown') === 0;
    
    // Build description
    $method = !empty($r['payment_method']) ? $r['payment_method'] : 'CASH';
    $description = "Payment received from {$full_name}\nMethod: {$method}\nBilling #" . ($r['billing_id'] ?? 'N/A');
    $amount = number_format($r['grand_total'], 2);
    $date = !empty($r['receipt_created']) ? date('Y-m-d H:i:s', strtotime($r['receipt_created'])) : 'N/A';
?>
<tr <?php echo $is_unknown ? 'style="background-color: #fff3cd;"' : ''; ?>>
<td><?= htmlspecialchars($date) ?></td>
<td class="debit">Cash / Bank</td>
<td class="credit">Patient Receivable</td>
<td class="entry-amount">₱<?= $amount ?></td>
<td class="reference-info">
    <?php if ($is_unknown): ?>
        <span class="unknown-patient">⚠️ <?= htmlspecialchars($full_name) ?></span><br>
    <?php endif; ?>
    <?= nl2br(htmlspecialchars($description)) ?>
</td>
<td><?= htmlspecialchars($r['receipt_id']) ?></td>
<td>
    <a href="journal_entry_line.php?receipt_id=<?= urlencode($r['receipt_id']) ?>" class="btn-view">View</a>
</td>
</tr>
<?php endforeach; ?>

<?php if (empty($payments) && empty($receipts)): ?>
<tr>
<td colspan="7" class="text-center text-muted py-4">
    No payment entries found. Create a payment to see journal entries.
</td>
</tr>
<?php endif; ?>

</tbody>
</table>

<button id="openModal" class="btn btn-primary mt-3">Add Manual Journal Entry</button>

</div>

<!-- MANUAL ENTRY MODAL -->
<div id="addEntryModal" class="modal">
<div class="modal-content">

<div class="d-flex justify-content-between mb-3">
<h4>Add Manual Journal Entry</h4>
<button class="close-btn" id="closeModal">&times;</button>
</div>

<form method="POST" action="manual_journal_insert.php">

<input type="hidden" name="add_entry" value="1">

<div class="mb-3">
<label class="form-label">Module <span class="text-danger">*</span></label>
<select name="module" class="form-control" required>
<option value="">-- Select Module --</option>
<option value="billing">Patient Billing</option>
<option value="insurance">Insurance</option>
<option value="supply">Supply</option>
<option value="general">General</option>
</select>
</div>

<div class="mb-3">
<label class="form-label">Reference (Receipt)</label>
<select name="reference" class="form-control">
<option value="">-- Manual Entry --</option>
<?php foreach ($receipts as $r): 
    $full_name = getPatientName($r['fname'] ?? '', $r['mname'] ?? '', $r['lname'] ?? '', $r['patient_id'] ?? null);
    $is_unknown = strpos($full_name, 'Unknown') === 0;
    $label = ($is_unknown ? '⚠️ ' : '') . 'Receipt #' . $r['receipt_id'] . ' | ' . htmlspecialchars($full_name) . ' | TXN ' . htmlspecialchars($r['transaction_id']) . ' | ' . ($r['payment_method'] ?: 'CASH') . ' | ₱' . number_format($r['grand_total'],2) . ' | ' . $r['status'];
?>
<option value="<?= htmlspecialchars($r['receipt_id']) ?>">
<?= $label ?>
</option>
<?php endforeach; ?>
</select>
</div>

<div class="mb-3">
<label class="form-label">Description</label>
<textarea name="description" class="form-control" rows="4" placeholder="Optional: Auto-filled when receipt selected"></textarea>
</div>

<div class="mb-3">
<label class="form-label">Status <span class="text-danger">*</span></label>
<select name="status" class="form-control" required>
<option value="">-- Select Status --</option>
<option value="Draft">Draft</option>
<option value="Posted">Posted</option>
</select>
</div>

<div class="d-flex gap-2 pt-3">
<button type="submit" class="btn btn-success">Save Entry</button>
<button type="reset" class="btn btn-secondary">Clear</button>
</div>

</form>

</div>
</div>

<script>
const modal = document.getElementById('addEntryModal');
document.getElementById('openModal').onclick = () => {
    modal.style.display = 'flex';
};

document.getElementById('closeModal').onclick = () => {
    modal.style.display = 'none';
};

window.onclick = (e) => {
    if (e.target === modal) {
        modal.style.display = 'none';
    }
};

// Auto-fill description when receipt is selected
document.querySelector('select[name="reference"]').addEventListener('change', function() {
    const selectedOption = this.options[this.selectedIndex];
    const descriptionField = document.querySelector('textarea[name="description"]');
    
    if (this.value && selectedOption.textContent) {
        const text = selectedOption.textContent.trim();
        descriptionField.value = 'Payment Reference: ' + text;
    } else {
        descriptionField.value = '';
    }
});
</script>

</body>
</html>